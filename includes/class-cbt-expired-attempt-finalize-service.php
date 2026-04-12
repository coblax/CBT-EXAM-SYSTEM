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
    private const AUTO_COMPLETE_LOCK_KEY = 'results_expired_auto_finalize';
    private const AUTO_COMPLETE_LOCK_TTL = 45;
    private const AUTO_COMPLETE_RESCHEDULE_DELAY = 5;
    private const FINALIZE_POLL_AFTER_MS = 2000;
    private const SCHEDULE_SIGNAL_TTL = 15;
    private const SCHEDULE_SIGNAL_PREFIX = 'expired_attempt_finalize_signal:';

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

        return [
            'finalize_pending' => ($status === 'in_progress' && $remaining_seconds <= 0),
            'finalize_poll_after_ms' => self::FINALIZE_POLL_AFTER_MS,
            'remaining_seconds' => $remaining_seconds,
            'expired_at' => $expired_at_ts > 0 ? wp_date('Y-m-d H:i:s', $expired_at_ts, wp_timezone()) : '',
            'expired_at_ts' => $expired_at_ts,
        ];
    }

    /**
     * @return array{processed_count:int,completed_count:int,has_remaining:bool}
     */
    public static function process_batch(int $created_by_user_id): array
    {
        $created_by_user_id = max(0, $created_by_user_id);
        $lock_key = self::AUTO_COMPLETE_LOCK_KEY . ':' . $created_by_user_id;
        if (!CBT_Cache::acquire_lock($lock_key, self::AUTO_COMPLETE_LOCK_TTL, [
            'source' => 'results_auto_finalize',
            'created_by_user_id' => $created_by_user_id,
        ])) {
            return [
                'processed_count' => 0,
                'completed_count' => 0,
                'has_remaining' => true,
            ];
        }

        try {
            $candidate_attempts = self::fetch_expired_attempt_batch($created_by_user_id);
            if (empty($candidate_attempts)) {
                return [
                    'processed_count' => 0,
                    'completed_count' => 0,
                    'has_remaining' => false,
                ];
            }

            $result = self::maybe_auto_finalize_attempt_rows($candidate_attempts, [
                'defer_invalidation' => true,
            ]);
            $has_remaining = self::has_pending_expired_attempts_for_scope($created_by_user_id);
            if ($has_remaining) {
                wp_schedule_single_event(
                    time() + self::AUTO_COMPLETE_RESCHEDULE_DELAY,
                    self::AUTO_COMPLETE_CRON_HOOK,
                    [$created_by_user_id]
                );
            }

            return [
                'processed_count' => (int) ($result['processed_count'] ?? 0),
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
        $completed_attempt_ids = [];
        $completed_exam_ids = [];
        $completed_student_ids = [];

        foreach ($candidate_attempts as $candidate_attempt) {
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

            $completion_result = CBT_REST::finalize_attempt_completion($attempt_id, null, [
                'defer_invalidation' => $defer_invalidation,
            ]);
            if (is_wp_error($completion_result)) {
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
            'processed_count' => count($candidate_attempts),
            'completed_attempt_ids' => $completed_attempt_ids,
        ];
    }

    public static function has_pending_expired_attempts_for_scope(int $created_by_user_id): bool
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where_clauses = [
            "a.status = 'in_progress'",
            'e.title NOT LIKE %s',
            'TIMESTAMPADD(MINUTE, GREATEST(1, COALESCE(e.duration_minutes, 0)) + GREATEST(0, COALESCE(a.extra_time_minutes, 0)), a.started_at) < %s',
        ];
        $where_params = ['Bank Soal - %', current_time('mysql')];
        if ($created_by_user_id > 0) {
            $where_clauses[] = 'e.created_by = %d';
            $where_params[] = $created_by_user_id;
        }

        $sql = "SELECT a.id
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                WHERE " . implode(' AND ', $where_clauses) . '
                LIMIT 1';
        $prepared_sql = $wpdb->prepare($sql, $where_params);
        $attempt_id = $wpdb->get_var($prepared_sql);

        return absint($attempt_id) > 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function fetch_expired_attempt_batch(int $created_by_user_id): array
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where_clauses = [
            "a.status = 'in_progress'",
            'e.title NOT LIKE %s',
            'TIMESTAMPADD(MINUTE, GREATEST(1, COALESCE(e.duration_minutes, 0)) + GREATEST(0, COALESCE(a.extra_time_minutes, 0)), a.started_at) < %s',
        ];
        $where_params = ['Bank Soal - %', current_time('mysql')];
        if ($created_by_user_id > 0) {
            $where_clauses[] = 'e.created_by = %d';
            $where_params[] = $created_by_user_id;
        }

        $sql = "SELECT a.id,
                       a.exam_id,
                       a.student_id,
                       a.status,
                       a.started_at,
                       a.extra_time_minutes,
                       e.duration_minutes AS exam_duration_minutes
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                WHERE " . implode(' AND ', $where_clauses) . '
                ORDER BY a.started_at ASC, a.id ASC
                LIMIT %d';
        $prepared_sql = $wpdb->prepare(
            $sql,
            array_merge($where_params, [self::AUTO_COMPLETE_BATCH_SIZE])
        );

        $rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function get_default_poll_after_ms(): int
    {
        return self::FINALIZE_POLL_AFTER_MS;
    }

    private static function schedule_auto_finalize_tick(int $created_by_user_id): bool
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
            return false;
        }

        $hook_args = [$created_by_user_id];
        if (wp_next_scheduled(self::AUTO_COMPLETE_CRON_HOOK, $hook_args)) {
            return false;
        }

        $scheduled = wp_schedule_single_event(
            time() + 1,
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
