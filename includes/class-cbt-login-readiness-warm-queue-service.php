<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-login-auth-snapshot-cache.php';
}

final class CBT_Login_Readiness_Warm_Queue_Service
{
    public const CRON_HOOK = 'cbt_login_readiness_warm_queue_tick';

    private const CRON_SCHEDULE = 'cbt_login_readiness_warm_queue_every_minute';
    private const LOCK_KEY = 'login_readiness_warm_queue';
    private const LOCK_TTL = 20;
    private const OPTION_STATE = 'cbt_login_readiness_warm_queue_state';
    private const STATUS_IDLE = 'idle';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_STOPPED = 'stopped';
    private const DEFAULT_BATCH_SIZE = 150;
    private const STUDENT_ROLES = ['student', 'siswa', 'siswa_cbt', 'subscriber'];

    public static function init(): void
    {
        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        if (function_exists('add_action')) {
            add_action(self::CRON_HOOK, [self::class, 'handle_cron_tick']);
        }

        self::maybe_restore_tick_event();
    }

    public static function activate(): void
    {
        self::maybe_restore_tick_event();
    }

    public static function deactivate(): void
    {
        self::clear_tick_event();
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'CBT Login Readiness Warm Queue Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_tick(): void
    {
        self::tick();
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{success:bool,message:string,state:array<string,mixed>}
     */
    public static function start(array $filters, string $source = 'admin'): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'start'])) {
            return [
                'success' => false,
                'message' => 'Queue warm login readiness sedang diproses. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $state = self::get_state();
            if (!empty($state['active'])) {
                return [
                    'success' => false,
                    'message' => 'Queue sedang berjalan.',
                    'state' => $state,
                ];
            }

            $resolution = self::resolve_target_users($filters);
            if (empty($resolution['can_start'])) {
                return [
                    'success' => false,
                    'message' => (string) ($resolution['message'] ?? 'Warm Login Readiness belum bisa dijalankan.'),
                    'state' => self::get_state(),
                ];
            }

            $now = current_time('mysql');
            $next_state = self::build_state([
                'active' => true,
                'status' => self::STATUS_ACTIVE,
                'source' => sanitize_key((string) ($resolution['source'] ?? 'cohort_index')),
                'filter' => self::sanitize_filters((array) ($resolution['filter'] ?? [])),
                'target_user_ids' => array_values(array_filter(array_map('absint', (array) ($resolution['target_user_ids'] ?? [])))),
                'target_count' => max(0, (int) ($resolution['target_count'] ?? 0)),
                'cursor' => 0,
                'ready_count' => 0,
                'failure_count' => 0,
                'last_batch_processed' => 0,
                'started_at' => $now,
                'updated_at' => $now,
                'finished_at' => '',
                'last_message' => sprintf(
                    'Warm Login Readiness dimulai. %d siswa masuk antrean dari %s.',
                    max(0, (int) ($resolution['target_count'] ?? 0)),
                    self::source_label((string) ($resolution['source'] ?? 'cohort_index'))
                ),
            ]);
            self::save_state($next_state);
            self::ensure_tick_event();

            return [
                'success' => true,
                'message' => (string) ($next_state['last_message'] ?? 'Warm Login Readiness dimulai.'),
                'state' => $next_state,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array{success:bool,message:string,state:array<string,mixed>}
     */
    public static function stop(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'stop'])) {
            return [
                'success' => false,
                'message' => 'Queue warm login readiness sedang diproses. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $state = self::get_state();
            if (empty($state['active'])) {
                return [
                    'success' => false,
                    'message' => 'Belum ada queue Warm Login Readiness yang aktif.',
                    'state' => $state,
                ];
            }

            $state['active'] = false;
            $state['status'] = self::STATUS_STOPPED;
            $state['updated_at'] = current_time('mysql');
            $state['finished_at'] = current_time('mysql');
            $state['last_message'] = 'Queue Warm Login Readiness dihentikan manual.';
            self::save_state($state);
            self::clear_tick_event();

            return [
                'success' => true,
                'message' => (string) ($state['last_message'] ?? 'Queue Warm Login Readiness dihentikan.'),
                'state' => self::get_state(),
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(int $batch_size = self::DEFAULT_BATCH_SIZE): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'tick'])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            if (empty($state['active'])) {
                self::clear_tick_event();
                return $state;
            }

            $target_user_ids = array_values(array_filter(array_map('absint', (array) ($state['target_user_ids'] ?? []))));
            $target_count = count($target_user_ids);
            if ($target_count <= 0) {
                $state['active'] = false;
                $state['status'] = self::STATUS_FAILED;
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                $state['last_batch_processed'] = 0;
                $state['last_message'] = 'Warm Login Readiness gagal: target siswa kosong.';
                self::save_state($state);
                self::clear_tick_event();
                return self::get_state();
            }

            $batch_size = max(1, min(1000, $batch_size));
            $cursor = min(max(0, (int) ($state['cursor'] ?? 0)), $target_count);
            $batch_user_ids = array_slice($target_user_ids, $cursor, $batch_size);
            if (empty($batch_user_ids)) {
                $state['active'] = false;
                $state['status'] = self::STATUS_COMPLETED;
                $state['updated_at'] = current_time('mysql');
                $state['finished_at'] = current_time('mysql');
                $state['last_batch_processed'] = 0;
                $state['last_message'] = sprintf(
                    'Warm Login Readiness selesai. %d/%d siswa siap.',
                    max(0, (int) ($state['ready_count'] ?? 0)),
                    $target_count
                );
                self::save_state($state);
                self::clear_tick_event();
                return self::get_state();
            }

            try {
                $results = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results($batch_user_ids, 'login_readiness_queue');
            } catch (Throwable $throwable) {
                $results = [];
            }

            $batch_ready = 0;
            $batch_failure = 0;
            foreach ($batch_user_ids as $user_id) {
                $user_id = absint($user_id);
                if ($user_id <= 0) {
                    $batch_failure++;
                    continue;
                }

                $result = $results[$user_id] ?? [];
                if (!empty($result['ready'])) {
                    $batch_ready++;
                } else {
                    $batch_failure++;
                }
            }

            $state['cursor'] = min($target_count, $cursor + count($batch_user_ids));
            $state['ready_count'] = max(0, (int) ($state['ready_count'] ?? 0)) + $batch_ready;
            $state['failure_count'] = max(0, (int) ($state['failure_count'] ?? 0)) + $batch_failure;
            $state['last_batch_processed'] = count($batch_user_ids);
            $state['updated_at'] = current_time('mysql');
            $state['last_message'] = sprintf(
                'Warm Login Readiness memproses %d siswa. Siap %d/%d, gagal %d.',
                count($batch_user_ids),
                max(0, (int) ($state['ready_count'] ?? 0)),
                $target_count,
                max(0, (int) ($state['failure_count'] ?? 0))
            );

            if ((int) ($state['cursor'] ?? 0) >= $target_count) {
                $state['active'] = false;
                $state['status'] = self::STATUS_COMPLETED;
                $state['finished_at'] = current_time('mysql');
                self::save_state($state);
                self::clear_tick_event();
                return self::get_state();
            }

            $state['active'] = true;
            $state['status'] = self::STATUS_ACTIVE;
            self::save_state($state);
            self::ensure_tick_event();

            return self::get_state();
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_state(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        return self::build_state(is_array($state) ? $state : []);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_panel_context(): array
    {
        $state = self::get_state();
        $filter_options = self::get_filter_options();
        $target_count = max(0, (int) ($state['target_count'] ?? 0));
        $ready_count = max(0, (int) ($state['ready_count'] ?? 0));
        $failure_count = max(0, (int) ($state['failure_count'] ?? 0));
        $processed_count = min($target_count, $ready_count + $failure_count);
        $pending_count = max(0, $target_count - $processed_count);
        $progress_percent = $target_count > 0 ? min(100, ($processed_count / $target_count) * 100) : 0.0;
        $status = sanitize_key((string) ($state['status'] ?? self::STATUS_IDLE));
        $tone = 'neutral';
        if ($status === self::STATUS_ACTIVE) {
            $tone = 'warning';
        } elseif ($status === self::STATUS_COMPLETED) {
            $tone = 'success';
        } elseif (in_array($status, [self::STATUS_FAILED, self::STATUS_STOPPED], true)) {
            $tone = 'error';
        }

        return [
            'active' => !empty($state['active']),
            'status' => $status,
            'status_label' => self::status_label($status),
            'status_tone' => $tone,
            'source' => sanitize_key((string) ($state['source'] ?? '')),
            'source_label' => self::source_label((string) ($state['source'] ?? '')),
            'filter' => is_array($state['filter'] ?? null) ? $state['filter'] : [],
            'target_count' => $target_count,
            'processed_count' => $processed_count,
            'ready_count' => $ready_count,
            'failure_count' => $failure_count,
            'pending_count' => $pending_count,
            'last_batch_processed' => max(0, (int) ($state['last_batch_processed'] ?? 0)),
            'progress_percent' => $progress_percent,
            'progress_label' => sprintf(
                '%s / %s siap',
                number_format_i18n($ready_count),
                number_format_i18n($target_count)
            ),
            'started_at' => (string) ($state['started_at'] ?? ''),
            'updated_at' => (string) ($state['updated_at'] ?? ''),
            'finished_at' => (string) ($state['finished_at'] ?? ''),
            'last_message' => (string) ($state['last_message'] ?? ''),
            'scheduled' => !empty($state['scheduled']),
            'next_run_at' => (string) ($state['next_run_at'] ?? ''),
            'kelas_options' => array_values(array_map('strval', (array) ($filter_options['kelas'] ?? []))),
            'ruang_options' => array_values(array_map('strval', (array) ($filter_options['ruang'] ?? []))),
            'cohort_summary' => class_exists('CBT_Student_Cohort_Index_Service')
                && method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')
                ? CBT_Student_Cohort_Index_Service::get_health_summary()
                : ['available' => false, 'ready' => false, 'status' => 'fallback', 'label' => 'Fallback'],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_state(array $state): array
    {
        $target_user_ids = array_values(array_filter(array_map('absint', (array) ($state['target_user_ids'] ?? []))));
        $status = sanitize_key((string) ($state['status'] ?? self::STATUS_IDLE));
        if (!in_array($status, [self::STATUS_IDLE, self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_STOPPED], true)) {
            $status = self::STATUS_IDLE;
        }

        $next_run_ts = self::next_tick_timestamp();

        return [
            'active' => !empty($state['active']) || $status === self::STATUS_ACTIVE,
            'status' => $status,
            'source' => sanitize_key((string) ($state['source'] ?? '')),
            'filter' => self::sanitize_filters((array) ($state['filter'] ?? [])),
            'target_user_ids' => $target_user_ids,
            'target_count' => max(0, (int) ($state['target_count'] ?? count($target_user_ids))),
            'cursor' => max(0, (int) ($state['cursor'] ?? 0)),
            'ready_count' => max(0, (int) ($state['ready_count'] ?? 0)),
            'failure_count' => max(0, (int) ($state['failure_count'] ?? 0)),
            'last_batch_processed' => max(0, (int) ($state['last_batch_processed'] ?? 0)),
            'started_at' => sanitize_text_field((string) ($state['started_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($state['updated_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($state['finished_at'] ?? '')),
            'last_message' => sanitize_text_field((string) ($state['last_message'] ?? '')),
            'scheduled' => $next_run_ts > 0,
            'next_run_at' => $next_run_ts > 0 ? self::format_timestamp($next_run_ts) : '',
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{can_start:bool,message:string,source:string,filter:array<string,mixed>,target_user_ids:int[],target_count:int}
     */
    private static function resolve_target_users(array $filters): array
    {
        $filters = self::sanitize_filters($filters);
        $exam_id = max(0, (int) ($filters['exam_id'] ?? 0));
        $kelas = (string) ($filters['kelas'] ?? '');
        $ruang = (string) ($filters['ruang'] ?? '');

        $exam_row = $exam_id > 0 ? self::load_exam_row($exam_id) : [];
        if ($exam_id > 0 && empty($exam_row)) {
            return [
                'can_start' => false,
                'message' => 'Exam untuk Warm Login Readiness tidak ditemukan.',
                'source' => '',
                'filter' => $filters,
                'target_user_ids' => [],
                'target_count' => 0,
            ];
        }

        $cohort_summary = class_exists('CBT_Student_Cohort_Index_Service')
            && method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')
            ? CBT_Student_Cohort_Index_Service::get_health_summary()
            : ['available' => false, 'ready' => false];

        if (!empty($cohort_summary['available']) && empty($cohort_summary['ready'])) {
            return [
                'can_start' => false,
                'message' => 'Student Cohort Index masih building. Tunggu sampai ready sebelum menjalankan Warm Login Readiness.',
                'source' => 'index_building',
                'filter' => $filters,
                'target_user_ids' => [],
                'target_count' => 0,
            ];
        }

        $target_user_ids = [];
        $source = 'canonical_fallback';
        if (!empty($cohort_summary['available']) && !empty($cohort_summary['ready'])) {
            $source = 'cohort_index';
            $target_user_ids = self::resolve_target_users_via_cohort($filters, $exam_row);
        } else {
            $target_user_ids = self::resolve_target_users_via_canonical($filters, $exam_row);
        }

        $target_user_ids = array_values(array_unique(array_filter(array_map('absint', $target_user_ids))));
        sort($target_user_ids, SORT_NUMERIC);

        if (empty($target_user_ids)) {
            $message = $exam_id > 0
                ? 'Tidak ada siswa target yang cocok untuk Warm Login Readiness pada exam/filter ini.'
                : 'Pilih filter kelas/ruang yang menghasilkan minimal satu siswa target.';

            return [
                'can_start' => false,
                'message' => $message,
                'source' => $source,
                'filter' => $filters,
                'target_user_ids' => [],
                'target_count' => 0,
            ];
        }

        return [
            'can_start' => true,
            'message' => 'Warm Login Readiness siap dijalankan.',
            'source' => $source,
            'filter' => $filters,
            'target_user_ids' => $target_user_ids,
            'target_count' => count($target_user_ids),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $exam_row
     * @return int[]
     */
    private static function resolve_target_users_via_cohort(array $filters, array $exam_row): array
    {
        if (!class_exists('CBT_Student_Cohort_Index_Service') || !method_exists('CBT_Student_Cohort_Index_Service', 'query_students')) {
            return [];
        }

        $query_filters = [];
        $target_kelas = !empty($exam_row) ? self::resolve_target_kelas_values($exam_row) : [];
        $kelas = (string) ($filters['kelas'] ?? '');
        $ruang = (string) ($filters['ruang'] ?? '');

        if (!empty($target_kelas)) {
            if ($kelas !== '') {
                if (!in_array($kelas, $target_kelas, true)) {
                    return [];
                }
                $query_filters['kelas_values'] = [$kelas];
            } else {
                $query_filters['kelas_values'] = $target_kelas;
            }
        } elseif ($kelas !== '') {
            $query_filters['kelas'] = $kelas;
        }

        if ($ruang !== '') {
            $query_filters['ruang'] = $ruang;
        }

        $result = CBT_Student_Cohort_Index_Service::query_students($query_filters);
        return array_values(array_filter(array_map('absint', (array) ($result['user_ids'] ?? []))));
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $exam_row
     * @return int[]
     */
    private static function resolve_target_users_via_canonical(array $filters, array $exam_row): array
    {
        $exam_target_kelas = !empty($exam_row) ? self::resolve_target_kelas_values($exam_row) : [];
        $exam_target_kelas_map = !empty($exam_target_kelas) ? array_fill_keys($exam_target_kelas, true) : [];
        $kelas = (string) ($filters['kelas'] ?? '');
        $ruang = (string) ($filters['ruang'] ?? '');
        $users = get_users(['number' => 0]);
        if (!is_array($users)) {
            return [];
        }

        $target_user_ids = [];
        foreach ($users as $user) {
            if (!$user instanceof WP_User || !self::is_student_user($user)) {
                continue;
            }

            $user_id = (int) $user->ID;
            if ($user_id <= 0) {
                continue;
            }

            $kode_kelas = self::normalize_filter_value((string) get_user_meta($user_id, 'kode_kelas', true));
            $kode_ruang = self::normalize_filter_value((string) get_user_meta($user_id, 'kode_ruang', true));
            if (!empty($exam_target_kelas_map) && ($kode_kelas === '' || !isset($exam_target_kelas_map[$kode_kelas]))) {
                continue;
            }
            if ($kelas !== '' && $kode_kelas !== $kelas) {
                continue;
            }
            if ($ruang !== '' && $kode_ruang !== $ruang) {
                continue;
            }

            $target_user_ids[$user_id] = $user_id;
        }

        ksort($target_user_ids, SORT_NUMERIC);
        return array_values($target_user_ids);
    }

    /**
     * @return array{kelas:string[],ruang:string[]}
     */
    private static function get_filter_options(): array
    {
        $cohort_summary = class_exists('CBT_Student_Cohort_Index_Service')
            && method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')
            ? CBT_Student_Cohort_Index_Service::get_health_summary()
            : ['available' => false, 'ready' => false];

        if (!empty($cohort_summary['available']) && empty($cohort_summary['ready'])) {
            return ['kelas' => [], 'ruang' => []];
        }

        if (class_exists('CBT_Student_Cohort_Index_Service') && method_exists('CBT_Student_Cohort_Index_Service', 'get_filter_options')) {
            $options = CBT_Student_Cohort_Index_Service::get_filter_options();
            if (!empty($options['ready']) && empty($options['fallback_required'])) {
                return [
                    'kelas' => array_values(array_map('strval', (array) ($options['kelas'] ?? []))),
                    'ruang' => array_values(array_map('strval', (array) ($options['ruang'] ?? []))),
                ];
            }
        }

        $users = get_users(['number' => 0]);
        if (!is_array($users)) {
            return ['kelas' => [], 'ruang' => []];
        }

        $kelas_options = [];
        $ruang_options = [];
        foreach ($users as $user) {
            if (!$user instanceof WP_User || !self::is_student_user($user)) {
                continue;
            }

            $user_id = (int) $user->ID;
            $kode_kelas = self::normalize_filter_value((string) get_user_meta($user_id, 'kode_kelas', true));
            $kode_ruang = self::normalize_filter_value((string) get_user_meta($user_id, 'kode_ruang', true));
            if ($kode_kelas !== '') {
                $kelas_options[$kode_kelas] = $kode_kelas;
            }
            if ($kode_ruang !== '') {
                $ruang_options[$kode_ruang] = $kode_ruang;
            }
        }

        ksort($kelas_options, SORT_NATURAL);
        ksort($ruang_options, SORT_NATURAL);

        return [
            'kelas' => array_values($kelas_options),
            'ruang' => array_values($ruang_options),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function load_exam_row(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_row')) {
            return [];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        try {
            $prepared = $wpdb->prepare(
                "SELECT id, title, status, target_kelas FROM {$exam_table} WHERE id = %d LIMIT 1",
                $exam_id
            );
            if (method_exists($wpdb, 'get_row')) {
                $row = $wpdb->get_row($prepared, ARRAY_A);
            } elseif (method_exists($wpdb, 'get_results')) {
                $results = $wpdb->get_results($prepared, ARRAY_A);
                $row = is_array($results) && isset($results[0]) && is_array($results[0]) ? $results[0] : [];
            } else {
                $row = [];
            }
        } catch (Throwable $throwable) {
            return [];
        }

        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{kelas:string,ruang:string,exam_id:int}
     */
    private static function sanitize_filters(array $filters): array
    {
        return [
            'kelas' => self::normalize_filter_value((string) ($filters['kelas'] ?? '')),
            'ruang' => self::normalize_filter_value((string) ($filters['ruang'] ?? '')),
            'exam_id' => max(0, (int) ($filters['exam_id'] ?? 0)),
        ];
    }

    private static function normalize_filter_value(string $value): string
    {
        $value = strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
        return sanitize_text_field($value);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return string[]
     */
    private static function resolve_target_kelas_values(array $exam_row): array
    {
        if (class_exists('CBT_Exam_Availability_Auto_Warm_Service') && method_exists('CBT_Exam_Availability_Auto_Warm_Service', 'get_target_kelas_for_exam')) {
            return array_values(array_filter(array_map('strval', (array) CBT_Exam_Availability_Auto_Warm_Service::get_target_kelas_for_exam($exam_row))));
        }

        $raw = trim((string) ($exam_row['target_kelas'] ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map([self::class, 'normalize_filter_value'], explode(',', $raw))));
    }

    private static function is_student_user(WP_User $user): bool
    {
        $roles = array_values(array_map('strval', (array) ($user->roles ?? [])));
        foreach ($roles as $role) {
            if (in_array(sanitize_key($role), self::STUDENT_ROLES, true)) {
                return true;
            }
        }

        return false;
    }

    private static function source_label(string $source): string
    {
        return match (sanitize_key($source)) {
            'cohort_index' => 'Cohort Index',
            'canonical_fallback' => 'Canonical Fallback',
            'index_building' => 'Index Building',
            default => '-',
        };
    }

    private static function status_label(string $status): string
    {
        return match (sanitize_key($status)) {
            self::STATUS_ACTIVE => 'RUNNING',
            self::STATUS_COMPLETED => 'COMPLETED',
            self::STATUS_FAILED => 'FAILED',
            self::STATUS_STOPPED => 'STOPPED',
            default => 'IDLE',
        };
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_state(array $state): void
    {
        update_option(self::OPTION_STATE, self::build_state($state), false);
    }

    private static function maybe_restore_tick_event(): void
    {
        $state = self::get_state();
        if (!empty($state['active'])) {
            self::ensure_tick_event();
            return;
        }

        self::clear_tick_event();
    }

    private static function ensure_tick_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    private static function clear_tick_event(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function next_tick_timestamp(): int
    {
        if (!function_exists('wp_next_scheduled')) {
            return 0;
        }

        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        return is_numeric($timestamp) ? max(0, (int) $timestamp) : 0;
    }

    private static function format_timestamp(int $timestamp): string
    {
        return $timestamp > 0 ? wp_date('Y-m-d H:i:s', $timestamp, wp_timezone()) : '';
    }
}
