<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Expired_Attempt_Finalize_Service
{
    private const AUTO_COMPLETE_BATCH_SIZE = 10;
    private const AUTO_COMPLETE_CRON_HOOK = 'cbt_results_expired_auto_finalize_tick';
    private const PROACTIVE_CRON_HOOK = 'cbt_expired_attempt_finalize_proactive_tick';
    private const PROACTIVE_CRON_SCHEDULE = 'cbt_expired_attempt_finalize_every_minute';
    private const AUTO_COMPLETE_LOCK_KEY = 'results_expired_auto_finalize';
    private const AUTO_COMPLETE_LOCK_TTL = 45;
    private const AUTO_COMPLETE_RESCHEDULE_DELAY = 5;
    private const FINALIZE_POLL_AFTER_MS = 2000;
    private const SCHEDULE_SIGNAL_TTL = 15;
    private const SCHEDULE_SIGNAL_PREFIX = 'expired_attempt_finalize_signal:';

    /** @var bool */
    private static $initialized = false;

    /**
     * @var array<string,array<string,int>>
     */
    private const ADAPTIVE_POLICIES = [
        'normal' => [
            'batch_size' => 10,
            'time_budget_seconds' => 3,
            'reschedule_delay_seconds' => 5,
            'finalize_poll_after_ms' => 2000,
        ],
        'busy' => [
            'batch_size' => 5,
            'time_budget_seconds' => 2,
            'reschedule_delay_seconds' => 15,
            'finalize_poll_after_ms' => 5000,
        ],
        'critical' => [
            'batch_size' => 2,
            'time_budget_seconds' => 1,
            'reschedule_delay_seconds' => 30,
            'finalize_poll_after_ms' => 10000,
        ],
    ];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        if (function_exists('add_action')) {
            add_action(self::PROACTIVE_CRON_HOOK, [self::class, 'handle_proactive_cron_tick']);
            add_action(self::AUTO_COMPLETE_CRON_HOOK, [self::class, 'handle_auto_complete_cron'], 10, 1);
        }

        self::maybe_restore_proactive_event();
    }

    public static function activate(): void
    {
        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        self::maybe_restore_proactive_event();
    }

    public static function deactivate(): void
    {
        self::clear_scheduled_events();
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules[self::PROACTIVE_CRON_SCHEDULE])) {
            $schedules[self::PROACTIVE_CRON_SCHEDULE] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'CBT Expired Attempt Finalizer Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_proactive_cron_tick(): void
    {
        self::process_batch(0, self::get_current_worker_policy());
    }

    public static function handle_auto_complete_cron(int $created_by_user_id = 0): void
    {
        self::process_batch(max(0, $created_by_user_id), self::get_current_worker_policy());
    }

    /**
     * @param array<string,mixed> $attempt
     * @return array{
     *   finalize_pending:bool,
     *   finalize_poll_after_ms:int,
     *   remaining_seconds:int,
     *   expired_at:string,
     *   expired_at_ts:int,
     *   scheduled:bool,
     *   throttled:bool,
     *   created_by_user_id:int
     * }
     */
    public static function maybe_schedule_for_attempt(array $attempt, int $exam_duration_minutes, int $created_by_user_id = 0): array
    {
        $created_by_user_id = max(0, $created_by_user_id);
        $derived = self::derive_attempt_state($attempt, $exam_duration_minutes);
        $derived['scheduled'] = false;
        $derived['throttled'] = false;
        $derived['created_by_user_id'] = $created_by_user_id;

        if (empty($derived['finalize_pending'])) {
            return $derived;
        }

        $attempt_id = (int) ($attempt['id'] ?? 0);
        $student_id = (int) ($attempt['student_id'] ?? 0);
        if (self::schedule_signal_is_throttled($attempt_id, $student_id, $created_by_user_id)) {
            $derived['throttled'] = true;
            return $derived;
        }

        $schedule_result = self::maybe_schedule_for_scope($created_by_user_id);
        $derived['scheduled'] = !empty($schedule_result['scheduled']);
        return $derived;
    }

    /**
     * @return array{has_pending:bool,scheduled:bool,created_by_user_id:int}
     */
    public static function maybe_schedule_for_scope(int $created_by_user_id): array
    {
        $created_by_user_id = max(0, $created_by_user_id);
        $has_pending = self::has_pending_expired_attempts_for_scope($created_by_user_id);
        if (!$has_pending) {
            return [
                'has_pending' => false,
                'scheduled' => false,
                'created_by_user_id' => $created_by_user_id,
            ];
        }

        return [
            'has_pending' => true,
            'scheduled' => self::schedule_auto_finalize_tick($created_by_user_id),
            'created_by_user_id' => $created_by_user_id,
        ];
    }

    /**
     * @param array<string,mixed> $attempt
     * @return array{
     *   finalize_pending:bool,
     *   finalize_poll_after_ms:int,
     *   remaining_seconds:int,
     *   expired_at:string,
     *   expired_at_ts:int
     * }
     */
    public static function derive_attempt_state(array $attempt, int $exam_duration_minutes): array
    {
        $status = (string) ($attempt['status'] ?? '');
        $remaining_seconds = self::calculate_attempt_remaining_seconds($attempt, $exam_duration_minutes);
        $expired_at_ts = self::calculate_expired_at_timestamp((string) ($attempt['started_at'] ?? ''), $exam_duration_minutes);
        $policy = self::get_current_worker_policy();

        return [
            'finalize_pending' => ($status === 'in_progress' && $remaining_seconds <= 0),
            'finalize_poll_after_ms' => max(250, (int) ($policy['finalize_poll_after_ms'] ?? self::FINALIZE_POLL_AFTER_MS)),
            'remaining_seconds' => $remaining_seconds,
            'expired_at' => $expired_at_ts > 0 ? wp_date('Y-m-d H:i:s', $expired_at_ts, wp_timezone()) : '',
            'expired_at_ts' => $expired_at_ts,
        ];
    }

    /**
     * @return array{processed_count:int,completed_count:int,has_remaining:bool}
     */
    public static function process_batch(int $created_by_user_id, array $policy = []): array
    {
        $created_by_user_id = max(0, $created_by_user_id);
        $policy = self::normalize_worker_policy($policy);
        $lock_key = self::AUTO_COMPLETE_LOCK_KEY . ':' . $created_by_user_id;
        if (!CBT_Cache::acquire_lock($lock_key, self::AUTO_COMPLETE_LOCK_TTL, [
            'source' => 'results_auto_finalize',
            'created_by_user_id' => $created_by_user_id,
            'adaptive_level' => (string) ($policy['level'] ?? 'normal'),
        ])) {
            return [
                'processed_count' => 0,
                'completed_count' => 0,
                'has_remaining' => true,
            ];
        }

        try {
            $candidate_attempts = self::fetch_expired_attempt_batch(
                $created_by_user_id,
                max(1, (int) ($policy['batch_size'] ?? self::AUTO_COMPLETE_BATCH_SIZE))
            );
            if (empty($candidate_attempts)) {
                return [
                    'processed_count' => 0,
                    'completed_count' => 0,
                    'has_remaining' => false,
                ];
            }

            $result = self::maybe_auto_finalize_attempt_rows($candidate_attempts, [
                'defer_invalidation' => true,
                'time_budget_seconds' => max(0, (int) ($policy['time_budget_seconds'] ?? 0)),
            ]);
            $processed_count = max(0, (int) ($result['processed_count'] ?? 0));
            $has_remaining = $processed_count < count($candidate_attempts)
                || self::has_pending_expired_attempts_for_scope($created_by_user_id);
            if ($has_remaining) {
                self::schedule_auto_finalize_tick(
                    $created_by_user_id,
                    max(1, (int) ($policy['reschedule_delay_seconds'] ?? self::AUTO_COMPLETE_RESCHEDULE_DELAY))
                );
            }

            return [
                'processed_count' => $processed_count,
                'completed_count' => count((array) ($result['completed_attempt_ids'] ?? [])),
                'has_remaining' => $has_remaining,
            ];
        } finally {
            CBT_Cache::release_lock($lock_key);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $candidate_attempts
     * @param array<string,mixed> $options
     * @return array{processed_count:int,completed_attempt_ids:array<int,int>}
     */
    public static function maybe_auto_finalize_attempt_rows(array $candidate_attempts, array $options = []): array
    {
        if (empty($candidate_attempts) || !class_exists('CBT_REST') || !method_exists('CBT_REST', 'finalize_attempt_completion')) {
            return [
                'processed_count' => 0,
                'completed_attempt_ids' => [],
            ];
        }

        $defer_invalidation = !empty($options['defer_invalidation']);
        $time_budget_seconds = max(0.0, (float) ($options['time_budget_seconds'] ?? 0));
        $started_at = microtime(true);
        $processed_count = 0;
        $completed_attempt_ids = [];
        $completed_exam_ids = [];
        $completed_student_ids = [];

        foreach ($candidate_attempts as $candidate_attempt) {
            if ($time_budget_seconds > 0 && $processed_count > 0 && (microtime(true) - $started_at) >= $time_budget_seconds) {
                break;
            }

            $processed_count++;
            if (!is_array($candidate_attempt) || (string) ($candidate_attempt['status'] ?? '') !== 'in_progress') {
                continue;
            }

            $attempt_id = absint($candidate_attempt['id'] ?? 0);
            if ($attempt_id <= 0) {
                continue;
            }

            $attempt_duration_minutes = max(
                1,
                (int) ($candidate_attempt['exam_duration_minutes'] ?? 0) + max(0, (int) ($candidate_attempt['extra_time_minutes'] ?? 0))
            );
            $remaining_seconds = self::calculate_attempt_remaining_seconds($candidate_attempt, $attempt_duration_minutes);
            if ($remaining_seconds > 0) {
                continue;
            }

            $finalize_result = self::finalize_attempt_with_finish_lock($attempt_id, [
                'candidate_attempt' => $candidate_attempt,
                'defer_invalidation' => $defer_invalidation,
                'require_expired_duration' => true,
            ]);
            if (empty($finalize_result['completed'])) {
                continue;
            }

            $completed_attempt_ids[] = $attempt_id;
            $candidate_exam_id = absint($candidate_attempt['exam_id'] ?? 0);
            $candidate_student_id = absint($candidate_attempt['student_id'] ?? 0);
            if ($candidate_exam_id > 0) {
                $completed_exam_ids[$candidate_exam_id] = $candidate_exam_id;
            }
            if ($candidate_student_id > 0) {
                $completed_student_ids[$candidate_student_id] = $candidate_student_id;
            }
        }

        if ($defer_invalidation && !empty($completed_attempt_ids)) {
            foreach ($completed_attempt_ids as $completed_attempt_id) {
                CBT_Cache::invalidate_attempt($completed_attempt_id);
            }

            CBT_Cache::invalidate_analytics();
            foreach ($completed_exam_ids as $completed_exam_id) {
                CBT_Cache::invalidate_analytics_exam($completed_exam_id);
            }

            foreach ($completed_student_ids as $completed_student_id) {
                CBT_Cache::invalidate_user($completed_student_id);
            }

            foreach ($candidate_attempts as $candidate_attempt) {
                if (!is_array($candidate_attempt)) {
                    continue;
                }

                $candidate_attempt_id = absint($candidate_attempt['id'] ?? 0);
                $candidate_student_id = absint($candidate_attempt['student_id'] ?? 0);
                if (
                    $candidate_attempt_id <= 0
                    || $candidate_student_id <= 0
                    || !in_array($candidate_attempt_id, $completed_attempt_ids, true)
                ) {
                    continue;
                }

                if (class_exists('CBT_UI_State')) {
                    CBT_UI_State::clear_attempt_state($candidate_student_id, $candidate_attempt_id);
                }
            }
        }

        return [
            'processed_count' => $processed_count,
            'completed_attempt_ids' => $completed_attempt_ids,
        ];
    }

    /**
     * @param array<string,mixed> $options
     * @return array{completed:bool,skipped:bool,error:bool}
     */
    public static function finalize_attempt_with_finish_lock(int $attempt_id, array $options = []): array
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0 || !class_exists('CBT_REST') || !method_exists('CBT_REST', 'finalize_attempt_completion')) {
            return [
                'completed' => false,
                'skipped' => true,
                'error' => false,
            ];
        }

        $finish_lock = self::acquire_attempt_finish_lock($attempt_id);
        if (empty($finish_lock['acquired'])) {
            return [
                'completed' => false,
                'skipped' => true,
                'error' => false,
            ];
        }

        try {
            $fresh_attempt = self::reload_attempt_for_finalize($attempt_id);
            if (!is_array($fresh_attempt)) {
                return [
                    'completed' => false,
                    'skipped' => true,
                    'error' => false,
                ];
            }

            $candidate_attempt = isset($options['candidate_attempt']) && is_array($options['candidate_attempt'])
                ? $options['candidate_attempt']
                : [];
            $fresh_attempt = array_merge($candidate_attempt, $fresh_attempt);
            if ((string) ($fresh_attempt['status'] ?? '') !== 'in_progress') {
                return [
                    'completed' => false,
                    'skipped' => true,
                    'error' => false,
                ];
            }

            if (!empty($options['require_expired_duration'])) {
                $fresh_attempt_duration_minutes = max(
                    1,
                    (int) ($fresh_attempt['exam_duration_minutes'] ?? 0) + max(0, (int) ($fresh_attempt['extra_time_minutes'] ?? 0))
                );
                $fresh_remaining_seconds = self::calculate_attempt_remaining_seconds($fresh_attempt, $fresh_attempt_duration_minutes);
                if ($fresh_remaining_seconds > 0) {
                    return [
                        'completed' => false,
                        'skipped' => true,
                        'error' => false,
                    ];
                }
            }

            $completion_result = CBT_REST::finalize_attempt_completion($attempt_id, null, [
                'defer_invalidation' => !empty($options['defer_invalidation']),
            ]);
            if (is_wp_error($completion_result)) {
                return [
                    'completed' => false,
                    'skipped' => false,
                    'error' => true,
                ];
            }

            return [
                'completed' => true,
                'skipped' => false,
                'error' => false,
            ];
        } finally {
            self::release_attempt_finish_lock($attempt_id, $finish_lock);
        }
    }

    public static function has_pending_expired_attempts_for_scope(int $created_by_user_id): bool
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $now_mysql = current_time('mysql');
        $where_clauses = self::build_expired_attempt_scope_clauses($created_by_user_id);
        $where_params = self::build_expired_attempt_scope_params($created_by_user_id, $now_mysql);

        $sql = "SELECT a.id
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                WHERE " . implode(' AND ', array_merge($where_clauses, [
                    'a.deadline_at IS NOT NULL',
                    'a.deadline_at < %s',
                ])) . '
                ORDER BY a.deadline_at ASC, a.id ASC
                LIMIT 1';
        $prepared_sql = $wpdb->prepare($sql, $where_params);
        $attempt_id = $wpdb->get_var($prepared_sql);
        if (absint($attempt_id) > 0) {
            return true;
        }

        $legacy_where_params = self::build_expired_attempt_scope_params($created_by_user_id, $now_mysql);
        $legacy_sql = "SELECT a.id
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                WHERE " . implode(' AND ', array_merge($where_clauses, [
                    'a.deadline_at IS NULL',
                    'TIMESTAMPADD(MINUTE, GREATEST(1, COALESCE(e.duration_minutes, 0)) + GREATEST(0, COALESCE(a.extra_time_minutes, 0)), a.started_at) < %s',
                ])) . '
                ORDER BY a.started_at ASC, a.id ASC
                LIMIT 1';
        $attempt_id = $wpdb->get_var($wpdb->prepare($legacy_sql, $legacy_where_params));

        return absint($attempt_id) > 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function fetch_expired_attempt_batch(int $created_by_user_id, ?int $limit = null): array
    {
        global $wpdb;

        $limit = $limit === null ? self::AUTO_COMPLETE_BATCH_SIZE : max(1, min(1000, absint($limit)));
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $now_mysql = current_time('mysql');
        $where_clauses = self::build_expired_attempt_scope_clauses($created_by_user_id);
        $where_params = self::build_expired_attempt_scope_params($created_by_user_id, $now_mysql);

        $sql = "SELECT a.id,
                       a.exam_id,
                       a.student_id,
                       a.status,
                       a.started_at,
                       a.deadline_at,
                       a.extra_time_minutes,
                       e.duration_minutes AS exam_duration_minutes
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                WHERE " . implode(' AND ', array_merge($where_clauses, [
                    'a.deadline_at IS NOT NULL',
                    'a.deadline_at < %s',
                ])) . '
                ORDER BY a.deadline_at ASC, a.id ASC
                LIMIT %d';
        $prepared_sql = $wpdb->prepare(
            $sql,
            array_merge($where_params, [$limit])
        );

        $rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        $rows = is_array($rows) ? $rows : [];
        $remaining = $limit - count($rows);
        if ($remaining <= 0) {
            return $rows;
        }

        $legacy_where_params = self::build_expired_attempt_scope_params($created_by_user_id, $now_mysql);
        $legacy_sql = "SELECT a.id,
                       a.exam_id,
                       a.student_id,
                       a.status,
                       a.started_at,
                       a.deadline_at,
                       a.extra_time_minutes,
                       e.duration_minutes AS exam_duration_minutes
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                WHERE " . implode(' AND ', array_merge($where_clauses, [
                    'a.deadline_at IS NULL',
                    'TIMESTAMPADD(MINUTE, GREATEST(1, COALESCE(e.duration_minutes, 0)) + GREATEST(0, COALESCE(a.extra_time_minutes, 0)), a.started_at) < %s',
                ])) . '
                ORDER BY a.started_at ASC, a.id ASC
                LIMIT %d';
        $legacy_rows = $wpdb->get_results(
            $wpdb->prepare($legacy_sql, array_merge($legacy_where_params, [$remaining])),
            ARRAY_A
        );
        if (is_array($legacy_rows) && !empty($legacy_rows)) {
            $rows = array_merge($rows, $legacy_rows);
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    private static function build_expired_attempt_scope_clauses(int $created_by_user_id): array
    {
        $where_clauses = [
            "a.status = 'in_progress'",
            'e.title NOT LIKE %s',
        ];
        if ($created_by_user_id > 0) {
            $where_clauses[] = 'e.created_by = %d';
        }

        return $where_clauses;
    }

    /**
     * @return array<int,mixed>
     */
    private static function build_expired_attempt_scope_params(int $created_by_user_id, string $now_mysql): array
    {
        $where_params = ['Bank Soal - %'];
        if ($created_by_user_id > 0) {
            $where_params[] = $created_by_user_id;
        }
        $where_params[] = $now_mysql;

        return $where_params;
    }

    public static function get_default_poll_after_ms(): int
    {
        return self::FINALIZE_POLL_AFTER_MS;
    }

    private static function schedule_auto_finalize_tick(int $created_by_user_id, ?int $delay_seconds = null): bool
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
            return false;
        }

        $hook_args = [$created_by_user_id];
        if (wp_next_scheduled(self::AUTO_COMPLETE_CRON_HOOK, $hook_args)) {
            return false;
        }

        $delay_seconds = $delay_seconds === null
            ? (int) self::get_current_worker_policy()['reschedule_delay_seconds']
            : max(1, $delay_seconds);
        $scheduled = wp_schedule_single_event(
            time() + $delay_seconds,
            self::AUTO_COMPLETE_CRON_HOOK,
            $hook_args
        );
        if (!$scheduled) {
            return false;
        }

        if (function_exists('spawn_cron') && (!defined('DOING_CRON') || !DOING_CRON)) {
            spawn_cron(time());
        }

        return true;
    }

    /**
     * @return array{level:string,batch_size:int,time_budget_seconds:int,reschedule_delay_seconds:int,finalize_poll_after_ms:int}
     */
    public static function get_current_worker_policy(): array
    {
        $level = 'normal';
        if (class_exists('CBT_Adaptive_Load_Service')) {
            $state = CBT_Adaptive_Load_Service::get_effective_state(false);
            $level = sanitize_key((string) ($state['effective_level'] ?? 'normal'));
        }

        return self::normalize_worker_policy(['level' => $level]);
    }

    /**
     * @param array<string,mixed> $policy
     * @return array{level:string,batch_size:int,time_budget_seconds:int,reschedule_delay_seconds:int,finalize_poll_after_ms:int}
     */
    private static function normalize_worker_policy(array $policy): array
    {
        $level = sanitize_key((string) ($policy['level'] ?? 'normal'));
        if (!isset(self::ADAPTIVE_POLICIES[$level])) {
            $level = 'normal';
        }

        $defaults = self::ADAPTIVE_POLICIES[$level];
        return [
            'level' => $level,
            'batch_size' => max(1, min(1000, (int) ($policy['batch_size'] ?? $defaults['batch_size']))),
            'time_budget_seconds' => max(0, min(30, (int) ($policy['time_budget_seconds'] ?? $defaults['time_budget_seconds']))),
            'reschedule_delay_seconds' => max(1, min(300, (int) ($policy['reschedule_delay_seconds'] ?? $defaults['reschedule_delay_seconds']))),
            'finalize_poll_after_ms' => max(250, min(60000, (int) ($policy['finalize_poll_after_ms'] ?? $defaults['finalize_poll_after_ms']))),
        ];
    }

    /**
     * @return array{acquired:bool,type:string,token:string}
     */
    private static function acquire_attempt_finish_lock(int $attempt_id): array
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [
                'acquired' => false,
                'type' => '',
                'token' => '',
            ];
        }

        if (class_exists('CBT_Runtime') && method_exists('CBT_Runtime', 'acquire_finish_lock')) {
            $token = (string) CBT_Runtime::acquire_finish_lock($attempt_id);
            if ($token !== '') {
                return [
                    'acquired' => true,
                    'type' => 'runtime',
                    'token' => $token,
                ];
            }
        }

        if (CBT_Cache::acquire_lock('finish_attempt:' . $attempt_id, 45, [
            'type' => 'expired_attempt_finalize',
            'attempt_id' => $attempt_id,
        ])) {
            return [
                'acquired' => true,
                'type' => 'cache',
                'token' => '1',
            ];
        }

        return [
            'acquired' => false,
            'type' => '',
            'token' => '',
        ];
    }

    /**
     * @param array{acquired?:bool,type?:string,token?:string} $lock
     */
    private static function release_attempt_finish_lock(int $attempt_id, array $lock): void
    {
        if (empty($lock['acquired'])) {
            return;
        }

        $attempt_id = absint($attempt_id);
        $type = (string) ($lock['type'] ?? '');
        $token = (string) ($lock['token'] ?? '');
        if ($attempt_id <= 0) {
            return;
        }

        if ($type === 'runtime' && $token !== '' && class_exists('CBT_Runtime') && method_exists('CBT_Runtime', 'release_finish_lock')) {
            CBT_Runtime::release_finish_lock($attempt_id, $token);
            return;
        }

        if ($type === 'cache') {
            CBT_Cache::release_lock('finish_attempt:' . $attempt_id);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function reload_attempt_for_finalize(int $attempt_id): ?array
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if (
            $attempt_id <= 0
            || !isset($wpdb)
            || !is_object($wpdb)
            || !isset($wpdb->prefix)
            || !method_exists($wpdb, 'prepare')
            || !method_exists($wpdb, 'get_row')
        ) {
            return null;
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.id,
                        a.exam_id,
                        a.student_id,
                        a.status,
                        a.started_at,
                        a.deadline_at,
                        a.extra_time_minutes,
                        e.duration_minutes AS exam_duration_minutes
                   FROM {$attempt_table} a
             INNER JOIN {$exam_table} e ON e.id = a.exam_id
                  WHERE a.id = %d
                  LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private static function maybe_restore_proactive_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::PROACTIVE_CRON_HOOK)) {
            return;
        }

        wp_schedule_event(
            time() + MINUTE_IN_SECONDS,
            self::PROACTIVE_CRON_SCHEDULE,
            self::PROACTIVE_CRON_HOOK
        );
    }

    private static function clear_scheduled_events(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::PROACTIVE_CRON_HOOK);
        wp_clear_scheduled_hook(self::AUTO_COMPLETE_CRON_HOOK);
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function calculate_attempt_remaining_seconds(array $attempt, int $exam_duration_minutes): int
    {
        $duration_minutes = max(1, $exam_duration_minutes);
        $started_at_ts = self::local_datetime_to_timestamp((string) ($attempt['started_at'] ?? ''));
        if ($started_at_ts === null) {
            return max(0, $duration_minutes * MINUTE_IN_SECONDS);
        }

        return max(0, ($started_at_ts + ($duration_minutes * MINUTE_IN_SECONDS)) - time());
    }

    private static function calculate_expired_at_timestamp(string $started_at, int $exam_duration_minutes): int
    {
        $started_at_ts = self::local_datetime_to_timestamp($started_at);
        if ($started_at_ts === null) {
            return 0;
        }

        return $started_at_ts + (max(1, $exam_duration_minutes) * MINUTE_IN_SECONDS);
    }

    private static function local_datetime_to_timestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\\TH:i:s',
            'Y-m-d\\TH:i',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->getTimestamp();
            }
        }

        try {
            $parsed = new DateTimeImmutable($value, $timezone);
            return $parsed->getTimestamp();
        } catch (Throwable $throwable) {
            return null;
        }
    }

    private static function schedule_signal_is_throttled(int $attempt_id, int $student_id, int $created_by_user_id): bool
    {
        if ($attempt_id <= 0 && $student_id <= 0) {
            return false;
        }

        if (!method_exists('CBT_Cache', 'get') || !method_exists('CBT_Cache', 'set')) {
            return false;
        }

        $key = self::SCHEDULE_SIGNAL_PREFIX . $created_by_user_id . ':' . $student_id . ':' . $attempt_id;
        $found = false;
        CBT_Cache::get($key, [], $found);
        if ($found) {
            return true;
        }

        CBT_Cache::set($key, [
            'attempt_id' => $attempt_id,
            'student_id' => $student_id,
            'created_by_user_id' => $created_by_user_id,
            'scheduled_at' => time(),
        ], self::SCHEDULE_SIGNAL_TTL, []);

        return false;
    }
}
