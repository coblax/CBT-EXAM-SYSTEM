<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Exam_Availability_Cache')) {
    require_once __DIR__ . '/class-cbt-exam-availability-cache.php';
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Exam_Availability_Auto_Warm_Service
{
    public const CRON_HOOK = 'cbt_exam_availability_auto_warm_tick';

    private const CRON_SCHEDULE = 'cbt_exam_availability_auto_warm_every_minute';
    private const LOCK_KEY = 'exam_availability_auto_warm';
    private const LOCK_TTL = 45;
    private const OPTION_STATE = 'cbt_exam_availability_auto_warm_state';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_EXPIRED = 'expired';
    private const STATUS_INACTIVE = 'inactive';
    private const STATUS_STOPPED = 'stopped';
    private const WINDOW_SECONDS = 1800;
    private const BATCH_SIZE = 50;
    private const INITIAL_BURST_SECONDS = 2.0;
    private const INITIAL_BURST_MAX_BATCHES = 8;

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
                'display' => 'CBT Exam Availability Auto Warm Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_tick(): void
    {
        self::tick();
    }

    /**
     * @return array<string,mixed>
     */
    public static function start_for_exam(array $exam_row): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'start',
            'exam_id' => (int) ($exam_row['id'] ?? 0),
        ])) {
            return [
                'success' => false,
                'message' => 'Auto-warm sedang diproses proses lain. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $exam_id = (int) ($exam_row['id'] ?? 0);
            $state = self::get_state();
            $is_same_exam_state = !empty($state['active']) && (int) ($state['exam_id'] ?? 0) === $exam_id;
            if (!empty($state['active']) && (int) ($state['exam_id'] ?? 0) > 0 && (int) ($state['exam_id'] ?? 0) !== $exam_id) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Auto-warm exam lain masih aktif: %s.',
                        (string) ($state['exam_title'] ?? ('Exam #' . (int) ($state['exam_id'] ?? 0)))
                    ),
                    'state' => $state,
                ];
            }

            $eligibility = self::evaluate_exam_eligibility($exam_row);
            if (empty($eligibility['can_start'])) {
                return [
                    'success' => false,
                    'message' => (string) ($eligibility['message'] ?? 'Exam belum memenuhi syarat auto-warm availability.'),
                    'state' => $state,
                ];
            }

            $now = current_time('mysql');
            $stop_after_ts = (int) current_time('timestamp') + self::WINDOW_SECONDS;
            $stop_after_at = wp_date('Y-m-d H:i:s', $stop_after_ts, wp_timezone());
            $target_student_ids = array_values(array_map('intval', (array) ($eligibility['target_student_ids'] ?? [])));
            $exam_title = trim((string) ($exam_row['title'] ?? '')) !== ''
                ? (string) $exam_row['title']
                : ('Exam #' . $exam_id);

            if ($is_same_exam_state) {
                $state['target_student_ids'] = $target_student_ids;
                $state['target_student_count'] = count($target_student_ids);
                $state['exam_title'] = $exam_title;
                $state['active'] = true;
                $state['status'] = self::STATUS_ACTIVE;
                $state['stop_after_ts'] = $stop_after_ts;
                $state['stop_after_at'] = $stop_after_at;
                if ((string) ($state['started_at'] ?? '') === '') {
                    $state['started_at'] = $now;
                }
            } else {
                $state = self::build_state([
                    'active' => true,
                    'status' => self::STATUS_ACTIVE,
                    'session_id' => self::generate_session_id($exam_id),
                    'exam_id' => $exam_id,
                    'exam_title' => $exam_title,
                    'target_student_ids' => $target_student_ids,
                    'target_student_count' => count($target_student_ids),
                    'cursor' => 0,
                    'prepared_student_ids' => [],
                    'prepared_count' => 0,
                    'started_at' => $now,
                    'stop_after_ts' => $stop_after_ts,
                    'stop_after_at' => $stop_after_at,
                    'last_tick_at' => '',
                    'last_success_count' => 0,
                    'last_failure_count' => 0,
                    'last_skip_count' => 0,
                    'last_message' => 'Auto-warm availability dimulai.',
                ]);
            }

            if ($is_same_exam_state) {
                $state = self::run_batch($state, 'resume');
            } else {
                $state = self::run_initial_burst($state);
            }
            self::save_state($state);
            self::ensure_tick_event();

            return [
                'success' => true,
                'message' => (string) ($state['last_message'] ?? 'Auto-warm availability aktif.'),
                'state' => $state,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function stop_for_exam(array $exam_row): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'stop',
            'exam_id' => (int) ($exam_row['id'] ?? 0),
        ])) {
            return [
                'success' => false,
                'message' => 'Auto-warm sedang diproses proses lain. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $exam_id = (int) ($exam_row['id'] ?? 0);
            $state = self::get_state();
            if (empty($state['active'])) {
                return [
                    'success' => false,
                    'message' => 'Belum ada sesi auto-warm availability yang aktif.',
                    'state' => $state,
                ];
            }

            if ((int) ($state['exam_id'] ?? 0) !== $exam_id) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Auto-warm saat ini aktif untuk exam lain: %s.',
                        (string) ($state['exam_title'] ?? ('Exam #' . (int) ($state['exam_id'] ?? 0)))
                    ),
                    'state' => $state,
                ];
            }

            $state = self::mark_inactive_state($state, self::STATUS_STOPPED, 'Auto-warm availability dihentikan manual.');
            self::save_state($state);
            self::clear_tick_event();

            return [
                'success' => true,
                'message' => (string) ($state['last_message'] ?? 'Auto-warm availability dihentikan.'),
                'state' => $state,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function clear_state_for_exam(array $exam_row): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'clear_state',
            'exam_id' => (int) ($exam_row['id'] ?? 0),
        ])) {
            return [
                'success' => false,
                'message' => 'State auto-warm sedang diproses proses lain. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $exam_id = (int) ($exam_row['id'] ?? 0);
            $state = self::get_state();
            if ($exam_id <= 0 || (int) ($state['exam_id'] ?? 0) !== $exam_id) {
                return [
                    'success' => true,
                    'message' => 'State auto-warm untuk exam ini sudah kosong.',
                    'state' => $state,
                ];
            }

            $state = self::build_state([]);
            self::save_state($state);
            self::clear_tick_event();

            return [
                'success' => true,
                'message' => 'State auto-warm dibersihkan.',
                'state' => $state,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'tick',
        ])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            if (empty($state['active'])) {
                self::clear_tick_event();
                return $state;
            }

            if (self::is_expired($state)) {
                $state = self::mark_inactive_state($state, self::STATUS_EXPIRED, 'Window auto-warm availability sudah berakhir.');
                self::save_state($state);
                self::clear_tick_event();
                return $state;
            }

            $state = self::run_batch($state, 'tick');
            self::save_state($state);
            self::ensure_tick_event();

            return $state;
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_exam_panel_context(array $exam_row): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $exam_title = trim((string) ($exam_row['title'] ?? '')) !== ''
            ? (string) ($exam_row['title'] ?? '')
            : ($exam_id > 0 ? ('Exam #' . $exam_id) : 'Exam belum dipilih');
        $state = self::get_state();
        $target_student_ids = self::resolve_target_student_ids($exam_row);
        $target_student_count = count($target_student_ids);
        $redis_ready = CBT_Exam_Availability_Cache::is_available();
        $target_kelas = self::resolve_target_kelas_values($exam_row);
        $exam_status = sanitize_key((string) ($exam_row['status'] ?? ''));
        $is_exam_published = $exam_status === 'published';
        $has_target_kelas = !empty($target_kelas);
        $has_target_students = $target_student_count > 0;
        $active_exam_id = (int) ($state['exam_id'] ?? 0);
        $is_same_exam_state = $exam_id > 0 && $active_exam_id === $exam_id;
        $has_blocking_exam = !empty($state['active']) && !$is_same_exam_state && $active_exam_id > 0;

        $status_key = self::STATUS_INACTIVE;
        if ($is_same_exam_state) {
            $status_key = !empty($state['active'])
                ? self::STATUS_ACTIVE
                : (string) ($state['status'] ?? self::STATUS_INACTIVE);
        }

        $status_meta = self::build_status_meta($status_key);
        $prepared_count = $is_same_exam_state
            ? min(max(0, (int) ($state['prepared_count'] ?? 0)), max(0, $target_student_count > 0 ? $target_student_count : (int) ($state['target_student_count'] ?? 0)))
            : 0;

        $message = '';
        if ($has_blocking_exam) {
            $message = sprintf(
                'Auto-warm saat ini aktif untuk exam lain: %s.',
                (string) ($state['exam_title'] ?? ('Exam #' . $active_exam_id))
            );
        } elseif (!$redis_ready) {
            $message = 'Redis availability belum siap di environment ini.';
        } elseif ($exam_id <= 0) {
            $message = 'Pilih exam dulu untuk menjalankan auto-warm availability.';
        } elseif (!$is_exam_published) {
            $message = 'Auto-warm availability hanya tersedia untuk exam berstatus published.';
        } elseif (!$has_target_kelas) {
            $message = 'Auto-warm availability membutuhkan target kelas pada exam ini.';
        } elseif (!$has_target_students) {
            $message = 'Belum ada siswa target yang cocok dengan target_kelas exam ini.';
        } elseif ($is_same_exam_state && (string) ($state['last_message'] ?? '') !== '') {
            $message = (string) $state['last_message'];
        } else {
            $message = 'Siap menjalankan auto-warm availability untuk peserta exam ini.';
        }

        $can_start = $redis_ready
            && $exam_id > 0
            && $is_exam_published
            && $has_target_kelas
            && $has_target_students
            && !$has_blocking_exam;
        $can_stop = !empty($state['active']) && $is_same_exam_state;

        return [
            'enabled' => $exam_id > 0,
            'status' => $status_key,
            'status_label' => $status_meta['label'],
            'status_tone' => $status_meta['tone'],
            'session_id' => $is_same_exam_state ? (string) ($state['session_id'] ?? '') : '',
            'exam_id' => $exam_id,
            'exam_title' => $exam_title,
            'target_kelas' => $target_kelas,
            'target_student_count' => $target_student_count,
            'prepared_count' => $prepared_count,
            'cursor' => $is_same_exam_state ? max(0, (int) ($state['cursor'] ?? 0)) : 0,
            'batch_size' => self::BATCH_SIZE,
            'started_at' => $is_same_exam_state ? (string) ($state['started_at'] ?? '') : '',
            'stop_after_at' => $is_same_exam_state ? (string) ($state['stop_after_at'] ?? '') : '',
            'last_tick_at' => $is_same_exam_state ? (string) ($state['last_tick_at'] ?? '') : '',
            'last_success_count' => $is_same_exam_state ? max(0, (int) ($state['last_success_count'] ?? 0)) : 0,
            'last_failure_count' => $is_same_exam_state ? max(0, (int) ($state['last_failure_count'] ?? 0)) : 0,
            'last_skip_count' => $is_same_exam_state ? max(0, (int) ($state['last_skip_count'] ?? 0)) : 0,
            'last_message' => $message,
            'can_start' => $can_start,
            'can_stop' => $can_stop,
            'is_exam_published' => $is_exam_published,
            'has_target_kelas' => $has_target_kelas,
            'has_target_students' => $has_target_students,
            'redis_available' => $redis_ready,
            'blocking_exam_id' => $has_blocking_exam ? $active_exam_id : 0,
            'blocking_exam_title' => $has_blocking_exam ? (string) ($state['exam_title'] ?? ('Exam #' . $active_exam_id)) : '',
        ];
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
     * @param array<string,mixed> $exam_row
     * @return int[]
     */
    public static function get_target_student_ids_for_exam(array $exam_row): array
    {
        return self::resolve_target_student_ids($exam_row);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return string[]
     */
    public static function get_target_kelas_for_exam(array $exam_row): array
    {
        return self::resolve_target_kelas_values($exam_row);
    }

    public static function is_active_for_student(int $user_id): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $state = self::get_state();
        if (empty($state['active'])) {
            return false;
        }

        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($state['target_student_ids'] ?? []))));
        $prepared_student_ids = array_values(array_filter(array_map('absint', (array) ($state['prepared_student_ids'] ?? []))));

        return in_array($user_id, $target_student_ids, true) || in_array($user_id, $prepared_student_ids, true);
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

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_state(array $state): array
    {
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($state['target_student_ids'] ?? []))));
        $prepared_student_ids = array_values(array_filter(array_map('absint', (array) ($state['prepared_student_ids'] ?? []))));
        $status = sanitize_key((string) ($state['status'] ?? self::STATUS_INACTIVE));
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_INACTIVE, self::STATUS_STOPPED], true)) {
            $status = self::STATUS_INACTIVE;
        }

        return [
            'active' => !empty($state['active']),
            'status' => $status,
            'session_id' => sanitize_text_field((string) ($state['session_id'] ?? '')),
            'exam_id' => absint($state['exam_id'] ?? 0),
            'exam_title' => sanitize_text_field((string) ($state['exam_title'] ?? '')),
            'target_student_ids' => $target_student_ids,
            'target_student_count' => max(0, (int) ($state['target_student_count'] ?? count($target_student_ids))),
            'cursor' => max(0, (int) ($state['cursor'] ?? 0)),
            'prepared_student_ids' => $prepared_student_ids,
            'prepared_count' => max(0, (int) ($state['prepared_count'] ?? count($prepared_student_ids))),
            'started_at' => sanitize_text_field((string) ($state['started_at'] ?? '')),
            'stop_after_ts' => max(0, (int) ($state['stop_after_ts'] ?? 0)),
            'stop_after_at' => sanitize_text_field((string) ($state['stop_after_at'] ?? '')),
            'last_tick_at' => sanitize_text_field((string) ($state['last_tick_at'] ?? '')),
            'last_success_count' => max(0, (int) ($state['last_success_count'] ?? 0)),
            'last_failure_count' => max(0, (int) ($state['last_failure_count'] ?? 0)),
            'last_skip_count' => max(0, (int) ($state['last_skip_count'] ?? 0)),
            'last_message' => sanitize_text_field((string) ($state['last_message'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_state(array $state): void
    {
        update_option(self::OPTION_STATE, self::build_state($state));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function run_batch(array $state, string $source): array
    {
        $state = self::build_state($state);
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($state['target_student_ids'] ?? []))));
        $total_targets = count($target_student_ids);
        if ($total_targets <= 0) {
            return self::mark_inactive_state($state, self::STATUS_STOPPED, 'Auto-warm berhenti karena target siswa tidak ditemukan lagi.');
        }

        $cursor = min(max(0, (int) ($state['cursor'] ?? 0)), max(0, $total_targets - 1));
        $batch_student_ids = array_slice($target_student_ids, $cursor, self::BATCH_SIZE);
        if (empty($batch_student_ids)) {
            $cursor = 0;
            $batch_student_ids = array_slice($target_student_ids, 0, self::BATCH_SIZE);
        }

        $target_student_id_map = array_fill_keys(array_map('strval', $target_student_ids), true);
        $prepared_student_ids = [];
        foreach ((array) ($state['prepared_student_ids'] ?? []) as $prepared_student_id) {
            $prepared_student_id = absint($prepared_student_id);
            if ($prepared_student_id <= 0) {
                continue;
            }

            if (isset($target_student_id_map[(string) $prepared_student_id])) {
                $prepared_student_ids[(string) $prepared_student_id] = true;
            }
        }
        $success_count = 0;
        $failure_count = 0;
        $skip_count = 0;
        $uncached_user_ids = [];

        foreach ($batch_student_ids as $user_id) {
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                continue;
            }

            try {
                if (CBT_Exam_Availability_Cache::has_current_prepared_snapshot($user_id)) {
                    $prepared_student_ids[(string) $user_id] = true;
                    $skip_count++;
                    continue;
                }
            } catch (Throwable $throwable) {
                unset($prepared_student_ids[(string) $user_id]);
                $failure_count++;
                continue;
            }

            $uncached_user_ids[] = $user_id;
        }

        $payloads_by_user = self::build_batch_snapshot_payloads($uncached_user_ids);
        $prepared_payloads = [];
        foreach ($uncached_user_ids as $user_id) {
            if (isset($payloads_by_user[$user_id]) && is_array($payloads_by_user[$user_id])) {
                $prepared_payloads[$user_id] = (array) $payloads_by_user[$user_id];
            }
        }

        $write_results = CBT_Exam_Availability_Cache::write_prepared_student_snapshots($prepared_payloads);
        foreach ($uncached_user_ids as $user_id) {
            if (!empty($write_results[$user_id])) {
                $prepared_student_ids[(string) $user_id] = true;
                $success_count++;
                continue;
            }

            unset($prepared_student_ids[(string) $user_id]);
            $failure_count++;
        }

        $next_cursor = $cursor + count($batch_student_ids);
        if ($next_cursor >= $total_targets) {
            $next_cursor = 0;
        }

        $prepared_student_ids = array_values(array_map('absint', array_keys($prepared_student_ids)));
        sort($prepared_student_ids, SORT_NUMERIC);

        $state['cursor'] = $next_cursor;
        $state['prepared_student_ids'] = $prepared_student_ids;
        $state['prepared_count'] = count($prepared_student_ids);
        $state['last_tick_at'] = current_time('mysql');
        $state['last_success_count'] = $success_count;
        $state['last_failure_count'] = $failure_count;
        $state['last_skip_count'] = $skip_count;
        $state['status'] = self::STATUS_ACTIVE;
        $state['active'] = true;

        $state['last_message'] = sprintf(
            '%s auto-warm memproses %d siswa. Siap %d/%d. Sukses %d, skip %d, gagal %d.',
            $source === 'start' ? 'Batch awal' : 'Tick',
            count($batch_student_ids),
            $state['prepared_count'],
            $total_targets,
            $success_count,
            $skip_count,
            $failure_count
        );

        if (self::is_expired($state)) {
            return self::mark_inactive_state($state, self::STATUS_EXPIRED, 'Window auto-warm availability sudah berakhir.');
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function run_initial_burst(array $state): array
    {
        $deadline = microtime(true) + self::initial_burst_seconds();
        $max_batches = self::initial_burst_max_batches();
        $burst_count = 0;

        do {
            if (empty($state['active'])) {
                break;
            }

            $state = self::run_batch($state, 'start');
            $burst_count++;
        } while (
            !empty($state['active'])
            && $burst_count < $max_batches
            && microtime(true) < $deadline
        );

        return $state;
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array<string,mixed>>
     */
    private static function build_batch_snapshot_payloads(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_map('absint', $user_ids)));
        if (empty($user_ids) || !class_exists('CBT_REST')) {
            return [];
        }

        if (method_exists('CBT_REST', 'build_batch_student_exam_availability_snapshot_payloads')) {
            $payloads = CBT_REST::build_batch_student_exam_availability_snapshot_payloads($user_ids);
            return is_array($payloads) ? $payloads : [];
        }

        if (!method_exists('CBT_REST', 'build_student_exam_availability_snapshot_payload')) {
            return [];
        }

        $payloads = [];
        foreach ($user_ids as $user_id) {
            $payload = CBT_REST::build_student_exam_availability_snapshot_payload($user_id);
            if (is_array($payload)) {
                $payloads[$user_id] = $payload;
            }
        }

        return $payloads;
    }

    private static function initial_burst_seconds(): float
    {
        $seconds = isset($GLOBALS['cbt_test_availability_initial_burst_seconds'])
            ? (float) $GLOBALS['cbt_test_availability_initial_burst_seconds']
            : self::INITIAL_BURST_SECONDS;

        return max(0.0, $seconds);
    }

    private static function initial_burst_max_batches(): int
    {
        $max_batches = isset($GLOBALS['cbt_test_availability_initial_burst_max_batches'])
            ? (int) $GLOBALS['cbt_test_availability_initial_burst_max_batches']
            : self::INITIAL_BURST_MAX_BATCHES;

        return max(1, $max_batches);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function mark_inactive_state(array $state, string $status, string $message): array
    {
        $state = self::build_state($state);
        $state['active'] = false;
        $state['status'] = $status;
        $state['last_tick_at'] = current_time('mysql');
        $state['last_message'] = $message;

        return $state;
    }

    /**
     * @return array{can_start:bool,message:string,target_student_ids:int[]}
     */
    private static function evaluate_exam_eligibility(array $exam_row): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        if ($exam_id <= 0) {
            return [
                'can_start' => false,
                'message' => 'Exam belum dipilih untuk auto-warm availability.',
                'target_student_ids' => [],
            ];
        }

        if (!CBT_Exam_Availability_Cache::is_available()) {
            return [
                'can_start' => false,
                'message' => 'Redis availability belum siap di environment ini.',
                'target_student_ids' => [],
            ];
        }

        $status = sanitize_key((string) ($exam_row['status'] ?? ''));
        if ($status !== 'published') {
            return [
                'can_start' => false,
                'message' => 'Auto-warm availability hanya tersedia untuk exam berstatus published.',
                'target_student_ids' => [],
            ];
        }

        $target_kelas = self::resolve_target_kelas_values($exam_row);
        if (empty($target_kelas)) {
            return [
                'can_start' => false,
                'message' => 'Auto-warm availability membutuhkan target kelas pada exam ini.',
                'target_student_ids' => [],
            ];
        }

        $target_student_ids = self::resolve_target_student_ids($exam_row);
        if (empty($target_student_ids)) {
            return [
                'can_start' => false,
                'message' => 'Belum ada siswa target yang cocok dengan target_kelas exam ini.',
                'target_student_ids' => [],
            ];
        }

        return [
            'can_start' => true,
            'message' => 'Exam siap untuk auto-warm availability.',
            'target_student_ids' => $target_student_ids,
        ];
    }

    /**
     * @return int[]
     */
    private static function resolve_target_student_ids(array $exam_row): array
    {
        $target_kelas = self::resolve_target_kelas_values($exam_row);
        if (empty($target_kelas)) {
            return [];
        }

        $kelas_map = array_fill_keys($target_kelas, true);
        $users = get_users(['number' => 0]);
        if (!is_array($users)) {
            return [];
        }

        $target_student_ids = [];
        foreach ($users as $user) {
            if (!$user instanceof WP_User || !self::is_snapshot_student_user($user)) {
                continue;
            }

            $user_id = (int) $user->ID;
            if ($user_id <= 0) {
                continue;
            }

            $kode_kelas = self::normalize_kelas((string) get_user_meta($user_id, 'kode_kelas', true));
            if ($kode_kelas === '' || !isset($kelas_map[$kode_kelas])) {
                continue;
            }

            $target_student_ids[$user_id] = $user_id;
        }

        ksort($target_student_ids, SORT_NUMERIC);

        return array_values($target_student_ids);
    }

    /**
     * @return string[]
     */
    private static function resolve_target_kelas_values(array $exam_row): array
    {
        if (class_exists('CBT_Admin_Exams_Service') && method_exists('CBT_Admin_Exams_Service', 'split_target_kelas_csv')) {
            return array_values(array_filter(array_map('strval', CBT_Admin_Exams_Service::split_target_kelas_csv((string) ($exam_row['target_kelas'] ?? '')))));
        }

        $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', (string) ($exam_row['target_kelas'] ?? ''));
        $items = [];
        foreach (explode(',', $raw) as $part) {
            $normalized = self::normalize_kelas($part);
            if ($normalized !== '') {
                $items[$normalized] = $normalized;
            }
        }

        return array_values($items);
    }

    private static function normalize_kelas(string $value): string
    {
        return strtoupper(trim(sanitize_text_field($value)));
    }

    private static function is_snapshot_student_user(WP_User $user): bool
    {
        $roles = isset($user->roles) && is_array($user->roles) ? array_map('strtolower', $user->roles) : [];
        foreach (['student', 'siswa', 'siswa_cbt', 'subscriber'] as $student_role) {
            if (in_array($student_role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{label:string,tone:string}
     */
    private static function build_status_meta(string $status): array
    {
        switch (sanitize_key($status)) {
            case self::STATUS_ACTIVE:
                return ['label' => 'AKTIF', 'tone' => 'success'];
            case self::STATUS_STOPPED:
                return ['label' => 'BERHENTI', 'tone' => 'warning'];
            case self::STATUS_EXPIRED:
                return ['label' => 'EXPIRED', 'tone' => 'warning'];
            case self::STATUS_INACTIVE:
            default:
                return ['label' => 'NONAKTIF', 'tone' => 'warning'];
        }
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function is_expired(array $state): bool
    {
        $stop_after_ts = max(0, (int) ($state['stop_after_ts'] ?? 0));
        if ($stop_after_ts > 0) {
            return $stop_after_ts <= (int) current_time('timestamp');
        }

        $stop_after_at = trim((string) ($state['stop_after_at'] ?? ''));
        if ($stop_after_at === '') {
            return false;
        }

        $stop_after_ts = strtotime($stop_after_at);
        if ($stop_after_ts === false) {
            return false;
        }

        return $stop_after_ts <= (int) current_time('timestamp');
    }

    private static function generate_session_id(int $exam_id): string
    {
        return 'auto-warm-' . $exam_id . '-' . (int) current_time('timestamp') . '-' . wp_rand(100, 999);
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
}
