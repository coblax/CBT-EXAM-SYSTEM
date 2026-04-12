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
    private const OPTION_REWARM_QUEUE = 'cbt_exam_availability_rewarm_queue_state';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_EXPIRED = 'expired';
    private const STATUS_INACTIVE = 'inactive';
    private const STATUS_STOPPED = 'stopped';
    private const WINDOW_SECONDS = 1800;
    private const BATCH_SIZE = 150;
    private const INITIAL_BURST_SECONDS = 6.0;
    private const INITIAL_BURST_MAX_BATCHES = 8;
    private const REWARM_QUEUE_LIMIT = 2000;
    private const REWARM_BATCH_SIZE = 50;
    private const REWARM_TICK_BUDGET_SECONDS = 2.5;

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
     * @param int[] $user_ids
     * @return array{enqueued_count:int,updated_count:int,rejected_count:int,state:array<string,mixed>}
     */
    public static function enqueue_rewarm_users(array $user_ids, string $reason, string $source): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'enqueue_rewarm',
        ])) {
            return [
                'enqueued_count' => 0,
                'updated_count' => 0,
                'rejected_count' => count(array_filter(array_map('absint', $user_ids))),
                'state' => self::get_rewarm_queue_state(),
            ];
        }

        try {
            $queue_state = self::get_rewarm_queue_state();
            $reason = sanitize_key($reason);
            if ($reason === '') {
                $reason = 'version_changed';
            }
            $source = sanitize_key($source);
            if ($source === '') {
                $source = 'admin';
            }
            $now = current_time('mysql');
            $enqueued_count = 0;
            $updated_count = 0;
            $rejected_count = 0;

            foreach (array_values(array_filter(array_map('absint', $user_ids))) as $user_id) {
                $item_key = (string) $user_id;
                if (isset($queue_state['items'][$item_key]) && is_array($queue_state['items'][$item_key])) {
                    $existing_item = (array) $queue_state['items'][$item_key];
                    $sources = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($existing_item['sources'] ?? [])))));
                    if (!in_array($source, $sources, true)) {
                        $sources[] = $source;
                    }
                    $existing_item['sources'] = $sources;
                    $existing_item['reason'] = $reason;
                    $existing_item['last_queued_at'] = $now;
                    $queue_state['items'][$item_key] = $existing_item;
                    $updated_count++;
                    continue;
                }

                if (count((array) ($queue_state['items'] ?? [])) >= self::REWARM_QUEUE_LIMIT) {
                    $rejected_count++;
                    continue;
                }

                $current_versions = self::build_queue_version_meta($user_id);
                $queue_state['items'][$item_key] = [
                    'user_id' => $user_id,
                    'reason' => $reason,
                    'sources' => [$source],
                    'requested_catalog_version' => (int) ($current_versions['catalog_version'] ?? 0),
                    'requested_user_version' => (int) ($current_versions['user_version'] ?? 0),
                    'first_queued_at' => $now,
                    'last_queued_at' => $now,
                ];
                $enqueued_count++;
            }

            $queue_state['last_message'] = $enqueued_count > 0 || $updated_count > 0
                ? sprintf(
                    'Queue rewarm availability diperbarui. Baru %d, refresh %d, ditolak %d.',
                    $enqueued_count,
                    $updated_count,
                    $rejected_count
                )
                : 'Tidak ada user baru yang masuk antrean rewarm availability.';
            $queue_state = self::build_rewarm_queue_state($queue_state);
            self::save_rewarm_queue_state($queue_state);
            self::maybe_restore_tick_event();

            return [
                'enqueued_count' => $enqueued_count,
                'updated_count' => $updated_count,
                'rejected_count' => $rejected_count,
                'state' => $queue_state,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_rewarm_queue_state(): array
    {
        $state = get_option(self::OPTION_REWARM_QUEUE, []);
        return self::build_rewarm_queue_state(is_array($state) ? $state : []);
    }

    /**
     * @return array{queued:bool,queued_at:string,source:string,reason:string,message:string}
     */
    public static function get_rewarm_user_state(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [
                'queued' => false,
                'queued_at' => '',
                'source' => '',
                'reason' => '',
                'message' => '',
            ];
        }

        $queue_state = self::get_rewarm_queue_state();
        $item = (array) (($queue_state['items'] ?? [])[(string) $user_id] ?? []);
        if (empty($item)) {
            return [
                'queued' => false,
                'queued_at' => '',
                'source' => '',
                'reason' => '',
                'message' => '',
            ];
        }

        $sources = array_values(array_filter(array_map('strval', (array) ($item['sources'] ?? []))));
        $source_label = !empty($sources) ? implode(', ', $sources) : 'admin';

        return [
            'queued' => true,
            'queued_at' => (string) ($item['first_queued_at'] ?? ''),
            'source' => $source_label,
            'reason' => (string) ($item['reason'] ?? 'version_changed'),
            'message' => 'MISS karena Version berubah. Siswa ini sudah masuk antrean rewarm.',
        ];
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
            self::maybe_restore_tick_event();

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
            self::maybe_restore_tick_event();

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
            $queue_state = self::get_rewarm_queue_state();

            if (!empty($state['active'])) {
                if (self::is_expired($state)) {
                    $state = self::mark_inactive_state($state, self::STATUS_EXPIRED, 'Window auto-warm availability sudah berakhir.');
                    self::save_state($state);
                    self::maybe_restore_tick_event();
                    return $state;
                }

                $state = self::run_batch($state, 'tick');
                self::save_state($state);
                self::maybe_restore_tick_event();

                return $state;
            }

            if (!empty($queue_state['queued_count'])) {
                $queue_state = self::run_rewarm_queue_batch($queue_state);
                self::save_rewarm_queue_state($queue_state);
                self::maybe_restore_tick_event();

                return array_merge($state, [
                    'rewarm_queue' => $queue_state,
                ]);
            }

            self::clear_tick_event();
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
        $target_student_count = self::count_target_student_ids($exam_row, false);
        $redis_ready = CBT_Exam_Availability_Cache::is_available();
        $target_kelas = self::resolve_target_kelas_values($exam_row);
        $exam_status = sanitize_key((string) ($exam_row['status'] ?? ''));
        $is_exam_published = $exam_status === 'published';
        $active_exam_id = (int) ($state['exam_id'] ?? 0);
        $is_same_exam_state = $exam_id > 0 && $active_exam_id === $exam_id;
        if ($is_same_exam_state && $target_student_count <= 0) {
            $target_student_count = max(0, (int) ($state['target_student_count'] ?? 0));
        }
        $has_target_kelas = !empty($target_kelas);
        $has_target_students = $target_student_count > 0;
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
            $message = self::should_defer_student_cohort_canonical_scan()
                ? 'Student Cohort Index masih building; target siswa belum dihitung detail agar halaman snapshot tetap cepat.'
                : 'Belum ada siswa target yang cocok dengan target_kelas exam ini.';
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
    public static function get_target_student_ids_for_exam(array $exam_row, bool $allow_expensive_fallback = true): array
    {
        return self::resolve_target_student_ids($exam_row, $allow_expensive_fallback);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return string[]
     */
    public static function get_target_kelas_for_exam(array $exam_row): array
    {
        return self::resolve_target_kelas_values($exam_row);
    }

    public static function count_target_students_for_exam(array $exam_row, bool $allow_expensive_fallback = true): int
    {
        return self::count_target_student_ids($exam_row, $allow_expensive_fallback);
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
        $queue_state = self::get_rewarm_queue_state();
        if (!empty($state['active']) || !empty($queue_state['queued_count'])) {
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
     * @return array<string,mixed>
     */
    private static function build_rewarm_queue_state(array $state): array
    {
        $items = [];
        foreach ((array) ($state['items'] ?? []) as $item_key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $user_id = absint($item['user_id'] ?? $item_key);
            if ($user_id <= 0) {
                continue;
            }

            $items[(string) $user_id] = [
                'user_id' => $user_id,
                'reason' => sanitize_key((string) ($item['reason'] ?? 'version_changed')),
                'sources' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($item['sources'] ?? []))))),
                'requested_catalog_version' => max(0, (int) ($item['requested_catalog_version'] ?? 0)),
                'requested_user_version' => max(0, (int) ($item['requested_user_version'] ?? 0)),
                'first_queued_at' => sanitize_text_field((string) ($item['first_queued_at'] ?? '')),
                'last_queued_at' => sanitize_text_field((string) ($item['last_queued_at'] ?? '')),
            ];
        }

        return [
            'items' => $items,
            'queued_count' => count($items),
            'last_tick_at' => sanitize_text_field((string) ($state['last_tick_at'] ?? '')),
            'last_processed_count' => max(0, (int) ($state['last_processed_count'] ?? 0)),
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
     */
    private static function save_rewarm_queue_state(array $state): void
    {
        update_option(self::OPTION_REWARM_QUEUE, self::build_rewarm_queue_state($state));
    }

    /**
     * @return array{catalog_version:int,user_version:int}
     */
    private static function build_queue_version_meta(int $user_id): array
    {
        return [
            'catalog_version' => max(1, (int) (CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog())['version'] ?? 1)),
            'user_version' => max(1, (int) (CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_user($user_id))['version'] ?? 1)),
        ];
    }

    /**
     * @param array<string,mixed> $queue_state
     * @return array<string,mixed>
     */
    private static function run_rewarm_queue_batch(array $queue_state): array
    {
        $queue_state = self::build_rewarm_queue_state($queue_state);
        $items = (array) ($queue_state['items'] ?? []);
        if (empty($items)) {
            $queue_state['last_tick_at'] = current_time('mysql');
            $queue_state['last_processed_count'] = 0;
            $queue_state['last_success_count'] = 0;
            $queue_state['last_failure_count'] = 0;
            $queue_state['last_skip_count'] = 0;
            $queue_state['last_message'] = 'Queue rewarm availability sedang kosong.';

            return $queue_state;
        }

        $deadline = microtime(true) + self::REWARM_TICK_BUDGET_SECONDS;
        $batch_item_keys = array_slice(array_keys($items), 0, self::REWARM_BATCH_SIZE);
        $processed_count = 0;
        $success_count = 0;
        $failure_count = 0;
        $skip_count = 0;
        $stale_user_ids = [];

        foreach ($batch_item_keys as $item_key) {
            $user_id = absint($item_key);
            if ($user_id <= 0) {
                unset($items[$item_key]);
                continue;
            }

            if ($processed_count > 0 && microtime(true) >= $deadline) {
                break;
            }

            $processed_count++;
            $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
            if (sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss')) === 'ready') {
                unset($items[$item_key]);
                $skip_count++;
                continue;
            }

            $stale_user_ids[] = $user_id;
        }

        if (!empty($stale_user_ids)) {
            $payloads_by_user = self::build_batch_snapshot_payloads($stale_user_ids);
            $prepared_payloads = [];
            foreach ($stale_user_ids as $user_id) {
                if (isset($payloads_by_user[$user_id]) && is_array($payloads_by_user[$user_id])) {
                    $prepared_payloads[$user_id] = (array) $payloads_by_user[$user_id];
                }
            }

            $write_results = CBT_Exam_Availability_Cache::write_prepared_student_snapshots($prepared_payloads);
            foreach ($stale_user_ids as $user_id) {
                $item_key = (string) $user_id;
                if (!empty($write_results[$user_id])) {
                    unset($items[$item_key]);
                    $success_count++;
                    if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
                        CBT_Snapshot_Auto_Heal_Queue_Service::remove_target(
                            'availability_user',
                            $user_id,
                            'Snapshot availability sudah dipulihkan lewat queued rewarm; auto-heal queue dibersihkan.'
                        );
                    }
                    CBT_Exam_Availability_Cache::record_repair_event(
                        $user_id,
                        'repaired',
                        'Snapshot availability dipulihkan lewat queued rewarm.',
                        'queued_rewarm'
                    );
                    continue;
                }

                $failure_count++;
            }
        }

        $queue_state['items'] = $items;
        $queue_state['queued_count'] = count($items);
        $queue_state['last_tick_at'] = current_time('mysql');
        $queue_state['last_processed_count'] = $processed_count;
        $queue_state['last_success_count'] = $success_count;
        $queue_state['last_failure_count'] = $failure_count;
        $queue_state['last_skip_count'] = $skip_count;
        $queue_state['last_message'] = sprintf(
            'Queue rewarm memproses %d user. Berhasil %d, skip %d, gagal %d, tersisa %d.',
            $processed_count,
            $success_count,
            $skip_count,
            $failure_count,
            $queue_state['queued_count']
        );

        return $queue_state;
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
    private static function resolve_target_student_ids(array $exam_row, bool $allow_expensive_fallback = true): array
    {
        $target_kelas = self::resolve_target_kelas_values($exam_row);
        if (empty($target_kelas)) {
            return [];
        }

        if (class_exists('CBT_Student_Cohort_Index_Service') && method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')) {
            $cohort_summary = CBT_Student_Cohort_Index_Service::get_health_summary();
            if (!empty($cohort_summary['available'])) {
                if (!empty($cohort_summary['ready'])) {
                    $cohort_student_ids = CBT_Student_Cohort_Index_Service::resolve_target_student_ids_for_exam($exam_row);
                    return array_values(array_unique(array_filter(array_map('absint', $cohort_student_ids))));
                }
                if (!$allow_expensive_fallback) {
                    return [];
                }
            }
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

    private static function count_target_student_ids(array $exam_row, bool $allow_expensive_fallback = true): int
    {
        $target_kelas = self::resolve_target_kelas_values($exam_row);
        if (empty($target_kelas)) {
            return 0;
        }

        if (class_exists('CBT_Student_Cohort_Index_Service') && method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')) {
            $cohort_summary = CBT_Student_Cohort_Index_Service::get_health_summary();
            if (!empty($cohort_summary['available'])) {
                if (!empty($cohort_summary['ready']) && method_exists('CBT_Student_Cohort_Index_Service', 'count_target_students_for_exam')) {
                    return max(0, (int) CBT_Student_Cohort_Index_Service::count_target_students_for_exam($exam_row));
                }

                if (!$allow_expensive_fallback) {
                    return 0;
                }
            }
        }

        return count(self::resolve_target_student_ids($exam_row, $allow_expensive_fallback));
    }

    private static function should_defer_student_cohort_canonical_scan(): bool
    {
        if (!class_exists('CBT_Student_Cohort_Index_Service') || !method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')) {
            return false;
        }

        $summary = CBT_Student_Cohort_Index_Service::get_health_summary();
        return !empty($summary['available']) && empty($summary['ready']);
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
