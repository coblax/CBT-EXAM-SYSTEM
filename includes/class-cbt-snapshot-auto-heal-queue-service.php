<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Snapshot_Auto_Heal_Queue_Service
{
    public const CRON_HOOK = 'cbt_snapshot_auto_heal_queue_tick';

    private const CRON_SCHEDULE = 'cbt_snapshot_auto_heal_queue_every_minute';
    private const LOCK_KEY = 'snapshot_auto_heal_queue';
    private const LOCK_TTL = 45;
    private const OPTION_STATE = 'cbt_snapshot_auto_heal_queue_state';
    private const TICK_BUDGET_SECONDS = 3.0;
    private const MAX_JOBS_PER_TICK = 25;
    private const MAX_RETRIES = 3;
    private const RESULT_HISTORY_LIMIT = 50;

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
                'display' => 'CBT Snapshot Auto-Heal Queue Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_tick(): void
    {
        self::tick();
    }

    /**
     * @return array{
     *   enqueued:bool,
     *   key:string,
     *   queue_depth:int,
     *   state:array<string,mixed>
     * }
     */
    public static function maybe_enqueue(string $type, int $target_id, string $reason, string $source = 'system'): array
    {
        $type = self::sanitize_type($type);
        $target_id = absint($target_id);
        $reason = sanitize_key($reason);
        $source = sanitize_key($source);
        if ($source === '') {
            $source = 'system';
        }

        $state = self::get_state();
        $default = [
            'enqueued' => false,
            'key' => self::job_key($type, $target_id),
            'queue_depth' => max(0, (int) ($state['queued_count'] ?? 0)),
            'state' => $state,
        ];

        if ($type === '' || $target_id <= 0 || $reason === '') {
            return $default;
        }

        if (!self::is_whitelisted_reason($type, $reason)) {
            return $default;
        }

        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'enqueue_auto_heal',
            'type' => $type,
            'target_id' => $target_id,
        ])) {
            return $default;
        }

        try {
            $state = self::get_state();
            $key = self::job_key($type, $target_id);
            $now_ts = (int) current_time('timestamp');
            $now_mysql = current_time('mysql');
            $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : [];

            if (isset($items[$key]) && is_array($items[$key])) {
                $job = self::normalize_job($items[$key], $key);
                $job['reason'] = $reason;
                $job['source'] = $source;
                $job['last_queued_at'] = $now_mysql;
                $job['next_attempt_at'] = max($now_ts, (int) ($job['next_attempt_at'] ?? $now_ts));
                $job['status'] = 'queued';
                $items[$key] = $job;
            } else {
                $items[$key] = [
                    'key' => $key,
                    'type' => $type,
                    'target_id' => $target_id,
                    'reason' => $reason,
                    'source' => $source,
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'first_queued_at' => $now_mysql,
                    'last_queued_at' => $now_mysql,
                    'next_attempt_at' => $now_ts,
                    'last_message' => '',
                ];
            }

            $state['items'] = $items;
            $state['last_message'] = sprintf(
                'Auto-heal queue diperbarui untuk %s #%d (%s).',
                $type,
                $target_id,
                $reason
            );
            $state = self::normalize_state($state);
            self::save_state($state);
            self::ensure_tick_event();

            return [
                'enqueued' => true,
                'key' => $key,
                'queue_depth' => max(0, (int) ($state['queued_count'] ?? 0)),
                'state' => $state,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array{
     *   queued:bool,
     *   status:string,
     *   message:string,
     *   queued_at:string,
     *   source:string,
     *   reason:string
     * }
     */
    public static function get_target_repair_state(string $type, int $target_id): array
    {
        $type = self::sanitize_type($type);
        $target_id = absint($target_id);
        if ($type === '' || $target_id <= 0) {
            return [
                'queued' => false,
                'status' => '',
                'message' => '',
                'queued_at' => '',
                'source' => '',
                'reason' => '',
            ];
        }

        $state = self::get_state();
        $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : [];
        $job = isset($items[self::job_key($type, $target_id)]) && is_array($items[self::job_key($type, $target_id)])
            ? self::normalize_job($items[self::job_key($type, $target_id)], self::job_key($type, $target_id))
            : null;

        if (!is_array($job)) {
            return [
                'queued' => false,
                'status' => '',
                'message' => '',
                'queued_at' => '',
                'source' => '',
                'reason' => '',
            ];
        }

        return [
            'queued' => true,
            'status' => 'queued_auto_heal',
            'message' => self::build_queue_message($type, (string) ($job['reason'] ?? '')),
            'queued_at' => (string) ($job['first_queued_at'] ?? ''),
            'source' => (string) ($job['source'] ?? ''),
            'reason' => (string) ($job['reason'] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_state(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        return self::normalize_state(is_array($state) ? $state : []);
    }

    /**
     * @return array{
     *   queue_depth:int,
     *   last_tick_at:string,
     *   last_success_count:int,
     *   last_failed_count:int,
     *   last_skipped_count:int,
     *   last_message:string
     * }
     */
    public static function get_summary(): array
    {
        $state = self::get_state();
        return [
            'queue_depth' => max(0, (int) ($state['queued_count'] ?? 0)),
            'last_tick_at' => (string) ($state['last_tick_at'] ?? ''),
            'last_success_count' => max(0, (int) ($state['last_success_count'] ?? 0)),
            'last_failed_count' => max(0, (int) ($state['last_failed_count'] ?? 0)),
            'last_skipped_count' => max(0, (int) ($state['last_skipped_count'] ?? 0)),
            'last_message' => trim((string) ($state['last_message'] ?? '')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'auto_heal_tick',
        ])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : [];
            if (empty($items)) {
                $state['last_tick_at'] = current_time('mysql');
                $state['last_message'] = 'Auto-heal queue kosong.';
                $state = self::normalize_state($state);
                self::save_state($state);
                self::clear_tick_event();
                return $state;
            }

            $now_ts = (int) current_time('timestamp');
            $deadline = microtime(true) + self::TICK_BUDGET_SECONDS;
            $processed = 0;
            $success_count = 0;
            $failed_count = 0;
            $skipped_count = 0;

            foreach (array_keys($items) as $key) {
                if ($processed >= self::MAX_JOBS_PER_TICK || microtime(true) >= $deadline) {
                    break;
                }

                if (!isset($items[$key]) || !is_array($items[$key])) {
                    continue;
                }

                $job = self::normalize_job($items[$key], $key);
                if ((int) ($job['next_attempt_at'] ?? 0) > $now_ts) {
                    continue;
                }

                $processed++;
                $items[$key]['status'] = 'processing';
                $result = self::execute_job($job);

                if (!empty($result['success'])) {
                    unset($items[$key]);
                    $success_count++;
                    $state = self::append_history($state, [
                        'key' => $key,
                        'status' => 'auto_healed',
                        'message' => trim((string) ($result['message'] ?? '')),
                        'finished_at' => current_time('mysql'),
                    ]);
                    continue;
                }

                if (!empty($result['skipped'])) {
                    unset($items[$key]);
                    $skipped_count++;
                    $state = self::append_history($state, [
                        'key' => $key,
                        'status' => 'skipped',
                        'message' => trim((string) ($result['message'] ?? '')),
                        'finished_at' => current_time('mysql'),
                    ]);
                    continue;
                }

                $attempt_count = max(0, (int) ($job['attempt_count'] ?? 0)) + 1;
                if ($attempt_count >= self::MAX_RETRIES) {
                    unset($items[$key]);
                    $failed_count++;
                    $state = self::append_history($state, [
                        'key' => $key,
                        'status' => 'failed',
                        'message' => trim((string) ($result['message'] ?? '')),
                        'finished_at' => current_time('mysql'),
                    ]);
                    continue;
                }

                $job['attempt_count'] = $attempt_count;
                $job['status'] = 'queued';
                $job['last_message'] = trim((string) ($result['message'] ?? ''));
                $job['next_attempt_at'] = $now_ts + self::retry_backoff_seconds($attempt_count);
                $items[$key] = $job;
            }

            $state['items'] = $items;
            $state['last_tick_at'] = current_time('mysql');
            $state['last_success_count'] = $success_count;
            $state['last_failed_count'] = $failed_count;
            $state['last_skipped_count'] = $skipped_count;
            $state['last_message'] = $processed > 0
                ? sprintf(
                    'Auto-heal queue memproses %d job. Berhasil %d, skip %d, gagal %d, tersisa %d.',
                    $processed,
                    $success_count,
                    $skipped_count,
                    $failed_count,
                    count($items)
                )
                : 'Belum ada job auto-heal yang siap diproses pada tick ini.';
            $state = self::normalize_state($state);
            self::save_state($state);

            if (!empty($items)) {
                self::ensure_tick_event();
            } else {
                self::clear_tick_event();
            }

            return $state;
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @param array<string,mixed> $job
     * @return array{success:bool,skipped:bool,message:string}
     */
    private static function execute_job(array $job): array
    {
        $type = self::sanitize_type((string) ($job['type'] ?? ''));
        $target_id = absint($job['target_id'] ?? 0);
        $reason = sanitize_key((string) ($job['reason'] ?? ''));
        $source = sanitize_key((string) ($job['source'] ?? 'auto_heal_queue'));

        if ($type === '' || $target_id <= 0) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Target auto-heal tidak valid.',
            ];
        }

        switch ($type) {
            case 'profile_user':
                return self::execute_profile_job($target_id, $reason, $source);
            case 'login_user':
                return self::execute_login_job($target_id, $reason, $source);
            case 'delivery_exam':
                return self::execute_delivery_job($target_id, $reason, $source);
            case 'start_exam':
                return self::execute_start_job($target_id, $reason, $source);
            case 'submit_exam':
                return self::execute_submit_job($target_id, $reason, $source);
            case 'availability_user':
                return self::execute_availability_job($target_id, $reason, $source);
        }

        return [
            'success' => false,
            'skipped' => true,
            'message' => 'Tipe auto-heal queue tidak dikenali.',
        ];
    }

    /**
     * @return array{success:bool,skipped:bool,message:string}
     */
    private static function execute_profile_job(int $user_id, string $reason, string $source): array
    {
        if (!class_exists('CBT_Student_Profile_Cache')) {
            return ['success' => false, 'skipped' => false, 'message' => 'Helper profile snapshot belum tersedia.'];
        }

        $repair = CBT_Student_Profile_Cache::maybe_auto_heal_snapshot($user_id, $source);
        if (!empty($repair['success'])) {
            return ['success' => true, 'skipped' => false, 'message' => (string) ($repair['message'] ?? '')];
        }

        return self::classify_retryable_result(
            'profile_user',
            $user_id,
            $reason,
            is_array($repair['diagnostics'] ?? null) ? $repair['diagnostics'] : CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id)
        );
    }

    private static function execute_login_job(int $user_id, string $reason, string $source): array
    {
        if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
            return ['success' => false, 'skipped' => false, 'message' => 'Helper login snapshot belum tersedia.'];
        }

        $repair = CBT_Login_Auth_Snapshot_Cache::maybe_auto_heal_snapshot($user_id, $source);
        if (!empty($repair['success'])) {
            return ['success' => true, 'skipped' => false, 'message' => (string) ($repair['message'] ?? '')];
        }

        return self::classify_retryable_result(
            'login_user',
            $user_id,
            $reason,
            is_array($repair['diagnostics'] ?? null) ? $repair['diagnostics'] : CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id)
        );
    }

    private static function execute_delivery_job(int $exam_id, string $reason, string $source): array
    {
        if (!class_exists('CBT_Exam_Question_Delivery_Cache')) {
            return ['success' => false, 'skipped' => false, 'message' => 'Helper delivery snapshot belum tersedia.'];
        }

        $repair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot($exam_id, $source);
        if (!empty($repair['success'])) {
            return ['success' => true, 'skipped' => false, 'message' => (string) ($repair['message'] ?? '')];
        }

        return self::classify_retryable_result(
            'delivery_exam',
            $exam_id,
            $reason,
            is_array($repair['diagnostics'] ?? null) ? $repair['diagnostics'] : CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id)
        );
    }

    private static function execute_start_job(int $exam_id, string $reason, string $source): array
    {
        if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            return ['success' => false, 'skipped' => false, 'message' => 'Helper start snapshot belum tersedia.'];
        }

        $repair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot($exam_id, $source);
        if (!empty($repair['success'])) {
            return ['success' => true, 'skipped' => false, 'message' => (string) ($repair['message'] ?? '')];
        }

        return self::classify_retryable_result(
            'start_exam',
            $exam_id,
            $reason,
            is_array($repair['diagnostics'] ?? null) ? $repair['diagnostics'] : CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id)
        );
    }

    private static function execute_submit_job(int $exam_id, string $reason, string $source): array
    {
        if (!class_exists('CBT_Question_Submission_Context_Cache')) {
            return ['success' => false, 'skipped' => false, 'message' => 'Helper submission context belum tersedia.'];
        }

        $repair = CBT_Question_Submission_Context_Cache::maybe_auto_heal_exam_snapshots($exam_id, $source);
        if (!empty($repair['success'])) {
            return ['success' => true, 'skipped' => false, 'message' => (string) ($repair['message'] ?? '')];
        }

        return self::classify_retryable_result(
            'submit_exam',
            $exam_id,
            $reason,
            is_array($repair['diagnostics'] ?? null) ? $repair['diagnostics'] : CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id)
        );
    }

    private static function execute_availability_job(int $user_id, string $reason, string $source): array
    {
        if (
            !class_exists('CBT_Exam_Availability_Cache')
            || !class_exists('CBT_REST')
            || !method_exists('CBT_REST', 'build_student_exam_availability_snapshot_payload')
        ) {
            return ['success' => false, 'skipped' => false, 'message' => 'Helper availability auto-heal belum tersedia.'];
        }

        if ($reason !== 'version_changed') {
            return ['success' => false, 'skipped' => true, 'message' => 'Reason availability tidak lagi eligible untuk auto-heal queue.'];
        }

        $payload = CBT_REST::build_student_exam_availability_snapshot_payload($user_id);
        $written = CBT_Exam_Availability_Cache::write_prepared_student_snapshot($user_id, is_array($payload) ? $payload : []);
        if ($written) {
            CBT_Exam_Availability_Cache::record_repair_event(
                $user_id,
                'auto_healed',
                'Snapshot availability dipulihkan lewat auto-heal queue.',
                'queued_auto_heal'
            );
            return ['success' => true, 'skipped' => false, 'message' => 'Snapshot availability dipulihkan lewat auto-heal queue.'];
        }

        return self::classify_retryable_result(
            'availability_user',
            $user_id,
            $reason,
            CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id)
        );
    }

    /**
     * @param array<string,mixed> $diagnostics
     * @return array{success:bool,skipped:bool,message:string}
     */
    private static function classify_retryable_result(string $type, int $target_id, string $queued_reason, array $diagnostics): array
    {
        $status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? ''));
        $reason = sanitize_key((string) ($diagnostics['snapshot_miss_reason'] ?? ''));

        if ($status === 'ready') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => trim((string) ($diagnostics['snapshot_message'] ?? 'Target auto-heal sudah READY saat job diproses.')),
            ];
        }

        if ($status === 'unavailable' || $reason === 'redis_unavailable') {
            return [
                'success' => false,
                'skipped' => false,
                'message' => trim((string) ($diagnostics['snapshot_message'] ?? 'Redis belum tersedia untuk auto-heal.')),
            ];
        }

        if (!self::is_whitelisted_reason($type, $reason)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => trim((string) ($diagnostics['snapshot_message'] ?? ('Target #' . $target_id . ' tidak lagi memenuhi syarat auto-heal queue.'))),
            ];
        }

        if ($queued_reason !== '' && $reason !== '' && $queued_reason !== $reason && $reason !== 'expired_or_evicted') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => trim((string) ($diagnostics['snapshot_message'] ?? 'Reason auto-heal berubah dan job saat ini tidak lagi relevan.')),
            ];
        }

        return [
            'success' => false,
            'skipped' => false,
            'message' => trim((string) ($diagnostics['snapshot_message'] ?? 'Auto-heal queue belum berhasil memperbaiki target ini.')),
        ];
    }

    private static function retry_backoff_seconds(int $attempt_count): int
    {
        if ($attempt_count <= 1) {
            return MINUTE_IN_SECONDS;
        }
        if ($attempt_count === 2) {
            return 5 * MINUTE_IN_SECONDS;
        }

        return 15 * MINUTE_IN_SECONDS;
    }

    private static function sanitize_type(string $type): string
    {
        $type = sanitize_key($type);
        return in_array($type, [
            'delivery_exam',
            'start_exam',
            'submit_exam',
            'profile_user',
            'login_user',
            'availability_user',
        ], true) ? $type : '';
    }

    private static function job_key(string $type, int $target_id): string
    {
        return $type . ':' . absint($target_id);
    }

    private static function is_whitelisted_reason(string $type, string $reason): bool
    {
        $type = self::sanitize_type($type);
        $reason = sanitize_key($reason);
        if ($type === '' || $reason === '') {
            return false;
        }

        $map = [
            'delivery_exam' => ['revision_changed', 'invalid_payload', 'expired_or_evicted'],
            'start_exam' => ['revision_changed', 'invalid_payload', 'expired_or_evicted'],
            'submit_exam' => ['expired_or_evicted', 'revision_changed', 'invalid_payload', 'partial_missing', 'partial_invalid', 'partial_mixed'],
            'profile_user' => ['meta_changed', 'user_invalidated', 'invalid_payload', 'expired_or_evicted', 'not_prepared'],
            'login_user' => ['identifier_changed', 'password_changed', 'invalid_payload', 'expired_or_evicted', 'write_failed', 'role_changed'],
            'availability_user' => ['version_changed'],
        ];

        return in_array($reason, $map[$type] ?? [], true);
    }

    private static function build_queue_message(string $type, string $reason): string
    {
        switch ($type) {
            case 'delivery_exam':
                return 'Snapshot soal sedang menunggu background auto-heal.';
            case 'start_exam':
                return 'Start snapshot sedang menunggu background auto-heal.';
            case 'submit_exam':
                return 'Submission context sedang menunggu background auto-heal.';
            case 'profile_user':
                return 'Snapshot profil sedang menunggu background auto-heal.';
            case 'login_user':
                return 'Login snapshot sedang menunggu background auto-heal.';
            case 'availability_user':
                return 'Snapshot availability sedang menunggu background auto-heal.';
        }

        return 'Target sedang menunggu background auto-heal.';
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function append_history(array $state, array $entry): array
    {
        $history = array_values(array_filter((array) ($state['history'] ?? []), 'is_array'));
        $history[] = [
            'key' => trim((string) ($entry['key'] ?? '')),
            'status' => sanitize_key((string) ($entry['status'] ?? '')),
            'message' => trim((string) ($entry['message'] ?? '')),
            'finished_at' => trim((string) ($entry['finished_at'] ?? '')),
        ];
        if (count($history) > self::RESULT_HISTORY_LIMIT) {
            $history = array_slice($history, -self::RESULT_HISTORY_LIMIT);
        }

        $state['history'] = $history;
        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function normalize_state(array $state): array
    {
        $items = [];
        foreach ((array) ($state['items'] ?? []) as $key => $job) {
            if (!is_array($job)) {
                continue;
            }

            $normalized = self::normalize_job($job, is_string($key) ? $key : '');
            if ($normalized['key'] === '' || $normalized['target_id'] <= 0 || $normalized['type'] === '') {
                continue;
            }

            $items[$normalized['key']] = $normalized;
        }

        $history = [];
        foreach (array_values(array_filter((array) ($state['history'] ?? []), 'is_array')) as $entry) {
            $history[] = [
                'key' => trim((string) ($entry['key'] ?? '')),
                'status' => sanitize_key((string) ($entry['status'] ?? '')),
                'message' => trim((string) ($entry['message'] ?? '')),
                'finished_at' => trim((string) ($entry['finished_at'] ?? '')),
            ];
        }

        if (count($history) > self::RESULT_HISTORY_LIMIT) {
            $history = array_slice($history, -self::RESULT_HISTORY_LIMIT);
        }

        return [
            'items' => $items,
            'queued_count' => count($items),
            'last_tick_at' => trim((string) ($state['last_tick_at'] ?? '')),
            'last_success_count' => max(0, (int) ($state['last_success_count'] ?? 0)),
            'last_failed_count' => max(0, (int) ($state['last_failed_count'] ?? 0)),
            'last_skipped_count' => max(0, (int) ($state['last_skipped_count'] ?? 0)),
            'last_message' => trim((string) ($state['last_message'] ?? '')),
            'history' => $history,
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function normalize_job(array $job, string $fallback_key = ''): array
    {
        $type = self::sanitize_type((string) ($job['type'] ?? ''));
        $target_id = absint($job['target_id'] ?? 0);
        $key = trim((string) ($job['key'] ?? ''));
        if ($key === '') {
            $key = $fallback_key;
        }
        if ($key === '' && $type !== '' && $target_id > 0) {
            $key = self::job_key($type, $target_id);
        }

        return [
            'key' => $key,
            'type' => $type,
            'target_id' => $target_id,
            'reason' => sanitize_key((string) ($job['reason'] ?? '')),
            'source' => sanitize_key((string) ($job['source'] ?? 'system')),
            'status' => sanitize_key((string) ($job['status'] ?? 'queued')),
            'attempt_count' => max(0, (int) ($job['attempt_count'] ?? 0)),
            'first_queued_at' => trim((string) ($job['first_queued_at'] ?? '')),
            'last_queued_at' => trim((string) ($job['last_queued_at'] ?? '')),
            'next_attempt_at' => max(0, (int) ($job['next_attempt_at'] ?? 0)),
            'last_message' => trim((string) ($job['last_message'] ?? '')),
        ];
    }

    private static function save_state(array $state): void
    {
        update_option(self::OPTION_STATE, self::normalize_state($state));
    }

    private static function maybe_restore_tick_event(): void
    {
        $state = self::get_state();
        if (!empty($state['queued_count'])) {
            self::ensure_tick_event();
        }
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
