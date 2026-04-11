<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Start_Attempt_Gate_Service')) {
    require_once __DIR__ . '/class-cbt-start-attempt-gate-service.php';
}

if (!class_exists('CBT_Login_Snapshot_Metrics_Service')) {
    require_once __DIR__ . '/class-cbt-login-snapshot-metrics-service.php';
}

if (!class_exists('CBT_Start_Attempt_Metrics_Service')) {
    require_once __DIR__ . '/class-cbt-start-attempt-metrics-service.php';
}

if (!class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
    require_once __DIR__ . '/class-cbt-snapshot-auto-heal-queue-service.php';
}

final class CBT_Adaptive_Load_Service
{
    public const CRON_HOOK = 'cbt_adaptive_load_tick';

    private const CRON_SCHEDULE = 'cbt_adaptive_load_every_minute';
    private const LOCK_KEY = 'adaptive_load';
    private const LOCK_TTL = 45;
    private const OPTION_STATE = 'cbt_adaptive_load_state';
    private const OVERRIDE_TTL_SECONDS = 900;
    private const HOLD_SECONDS = 300;
    private const CLEAN_TICKS_TO_DEESCALATE = 3;

    /**
     * @var array<string,int>
     */
    private const LEVEL_PRIORITY = [
        'normal' => 0,
        'busy' => 1,
        'critical' => 2,
    ];

    /**
     * @var array<string,array<string,int|string>>
     */
    private const LEVEL_POLICY = [
        'normal' => [
            'heartbeat_interval_ms' => 20000,
            'admin_snapshot_refresh_seconds' => 10,
            'label' => 'NORMAL',
        ],
        'busy' => [
            'heartbeat_interval_ms' => 30000,
            'admin_snapshot_refresh_seconds' => 20,
            'label' => 'BUSY',
        ],
        'critical' => [
            'heartbeat_interval_ms' => 45000,
            'admin_snapshot_refresh_seconds' => 40,
            'label' => 'CRITICAL',
        ],
    ];

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
                'display' => 'CBT Adaptive Load Every Minute',
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
    public static function get_state(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        return self::normalize_state(is_array($state) ? $state : []);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_effective_state(bool $ensure_evaluated = false): array
    {
        $state = self::get_state();
        $now_ts = (int) current_time('timestamp');
        $override_expires_ts = self::mysql_to_timestamp((string) ($state['override_expires_at'] ?? ''));
        $last_evaluated_ts = self::mysql_to_timestamp((string) ($state['last_evaluated_at'] ?? ''));

        if (
            $ensure_evaluated
            && (
                $last_evaluated_ts <= 0
                || ($override_expires_ts > 0 && $override_expires_ts <= $now_ts)
            )
        ) {
            return self::tick();
        }

        return self::normalize_state($state);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_frontend_payload(): array
    {
        $state = self::get_effective_state(false);
        $policy = self::get_policy((string) ($state['effective_level'] ?? 'normal'));

        return [
            'level' => sanitize_key((string) ($state['effective_level'] ?? 'normal')),
            'source' => sanitize_key((string) ($state['source'] ?? 'auto')),
            'reasons' => array_values(array_filter(array_map('sanitize_text_field', (array) ($state['reasons'] ?? [])))),
            'heartbeat_interval_ms' => max(1000, (int) ($policy['heartbeat_interval_ms'] ?? 20000)),
            'admin_snapshot_refresh_seconds' => max(1, (int) ($policy['admin_snapshot_refresh_seconds'] ?? 10)),
            'last_evaluated_at' => (string) ($state['last_evaluated_at'] ?? ''),
            'override_expires_at' => (string) ($state['override_expires_at'] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_monitoring_summary(): array
    {
        $state = self::get_effective_state(true);
        $policy = self::get_policy((string) ($state['effective_level'] ?? 'normal'));
        $reasons = array_values(array_filter(array_map('sanitize_text_field', (array) ($state['reasons'] ?? []))));

        return [
            'level' => sanitize_key((string) ($state['effective_level'] ?? 'normal')),
            'level_label' => (string) ($policy['label'] ?? 'NORMAL'),
            'source' => sanitize_key((string) ($state['source'] ?? 'auto')),
            'source_label' => sanitize_key((string) ($state['source'] ?? 'auto')) === 'manual_override'
                ? 'Manual override'
                : 'Auto',
            'heartbeat_interval_ms' => max(1000, (int) ($policy['heartbeat_interval_ms'] ?? 20000)),
            'admin_snapshot_refresh_seconds' => max(1, (int) ($policy['admin_snapshot_refresh_seconds'] ?? 10)),
            'reasons' => $reasons,
            'primary_reason' => (string) ($reasons[0] ?? ''),
            'last_evaluated_at' => (string) ($state['last_evaluated_at'] ?? ''),
            'override_expires_at' => (string) ($state['override_expires_at'] ?? ''),
            'signals' => is_array($state['signals'] ?? null) ? $state['signals'] : [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'adaptive_load_tick'])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            $signals = self::collect_signals();
            $evaluation = self::evaluate_candidate_level($signals);
            $state = self::apply_evaluation_to_state($state, $evaluation, $signals);
            $state = self::normalize_state($state);
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
    public static function set_manual_override(string $level, int $user_id): array
    {
        $level = self::sanitize_level($level);
        $user_id = absint($user_id);

        if (!in_array($level, ['busy', 'critical'], true)) {
            return self::get_state();
        }

        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'adaptive_load_override_set', 'level' => $level])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            $now_ts = (int) current_time('timestamp');
            $expires_ts = $now_ts + self::OVERRIDE_TTL_SECONDS;
            $policy = self::get_policy($level);

            $state['override_level'] = $level;
            $state['override_expires_at'] = self::timestamp_to_mysql($expires_ts);
            $state['override_user_id'] = $user_id;
            $state['effective_level'] = $level;
            $state['candidate_level'] = self::sanitize_level((string) ($state['candidate_level'] ?? 'normal'));
            $state['source'] = 'manual_override';
            $state['reasons'] = [
                sprintf(
                    'Override manual %s aktif sampai %s.',
                    (string) ($policy['label'] ?? strtoupper($level)),
                    self::timestamp_to_mysql($expires_ts)
                ),
            ];
            $state['hold_until'] = self::timestamp_to_mysql($expires_ts);
            $state['last_evaluated_at'] = current_time('mysql');
            $state['clean_ticks'] = 0;
            $state = self::normalize_state($state);
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
    public static function clear_manual_override(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'adaptive_load_override_clear'])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            $signals = self::collect_signals();
            $state['override_level'] = '';
            $state['override_expires_at'] = '';
            $state['override_user_id'] = 0;
            $evaluation = self::evaluate_candidate_level($signals);
            $state = self::apply_evaluation_to_state($state, $evaluation, $signals);
            $state = self::normalize_state($state);
            self::save_state($state);
            self::ensure_tick_event();

            return $state;
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @param string $level
     * @return array<string,int|string>
     */
    public static function get_policy(string $level): array
    {
        $level = self::sanitize_level($level);
        return self::LEVEL_POLICY[$level] ?? self::LEVEL_POLICY['normal'];
    }

    /**
     * @return array{scanned_count:int,completed_count:int,failed_count:int,completed_attempt_ids:array<int,int>}
     */
    public static function finalize_expired_in_progress_attempts(int $limit = 200): array
    {
        global $wpdb;

        $limit = max(1, min(1000, absint($limit)));
        if (
            !isset($wpdb)
            || !is_object($wpdb)
            || !class_exists('CBT_REST')
            || !method_exists('CBT_REST', 'finalize_attempt_completion')
        ) {
            return [
                'scanned_count' => 0,
                'completed_count' => 0,
                'failed_count' => 0,
                'completed_attempt_ids' => [],
            ];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $now_mysql = current_time('mysql');
        $attempt_ids = array_values(array_filter(array_map('intval', (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT a.id
                   FROM {$attempt_table} a
             INNER JOIN {$exam_table} e ON e.id = a.exam_id
                  WHERE a.status = 'in_progress'
                    AND e.title NOT LIKE %s
                    AND (
                        (e.starts_at IS NOT NULL AND e.starts_at > %s)
                        OR (e.ends_at IS NOT NULL AND e.ends_at < %s)
                        OR TIMESTAMPADD(MINUTE, (e.duration_minutes + a.extra_time_minutes), a.started_at) < %s
                    )
               ORDER BY a.started_at ASC, a.id ASC
                  LIMIT %d",
                'Bank Soal - %',
                $now_mysql,
                $now_mysql,
                $now_mysql,
                $limit
            )
        ))));

        $completed_attempt_ids = [];
        $failed_count = 0;
        foreach ($attempt_ids as $attempt_id) {
            $completion_result = CBT_REST::finalize_attempt_completion($attempt_id);
            if (is_wp_error($completion_result)) {
                $failed_count++;
                continue;
            }

            $completed_attempt_ids[] = $attempt_id;
        }

        return [
            'scanned_count' => count($attempt_ids),
            'completed_count' => count($completed_attempt_ids),
            'failed_count' => $failed_count,
            'completed_attempt_ids' => $completed_attempt_ids,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function collect_signals(): array
    {
        global $wpdb;

        $exam_ids = [];
        if (isset($wpdb) && is_object($wpdb)) {
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $attempt_table = $wpdb->prefix . 'cbt_attempts';
            $exam_ids = array_values(array_filter(array_map('intval', (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$exam_table} WHERE title NOT LIKE %s ORDER BY id DESC",
                    'Bank Soal - %'
                )
            ))));
            $active_attempt_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                      FROM {$attempt_table} a
                 INNER JOIN {$exam_table} e ON e.id = a.exam_id
                      WHERE a.status = 'in_progress'
                        AND e.status = 'published'
                        AND (e.starts_at IS NULL OR e.starts_at <= %s)
                        AND (e.ends_at IS NULL OR e.ends_at >= %s)
                        AND TIMESTAMPADD(MINUTE, (e.duration_minutes + a.extra_time_minutes + 10), a.started_at) >= %s",
                    current_time('mysql'),
                    current_time('mysql'),
                    current_time('mysql')
                )
            );
        } else {
            $active_attempt_count = 0;
        }

        $gate = class_exists('CBT_Start_Attempt_Gate_Service')
            ? CBT_Start_Attempt_Gate_Service::get_global_diagnostics($exam_ids)
            : ['queue_depth_total' => 0];
        $metrics = class_exists('CBT_Login_Snapshot_Metrics_Service')
            ? CBT_Login_Snapshot_Metrics_Service::get_window_summary(15)
            : [];
        $startAttemptMetrics = class_exists('CBT_Start_Attempt_Metrics_Service')
            ? CBT_Start_Attempt_Metrics_Service::get_window_summary(15)
            : [];
        $auto_heal = class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')
            ? CBT_Snapshot_Auto_Heal_Queue_Service::get_summary()
            : ['queue_depth' => 0];

        $snapshot_success = max(0, (int) ($metrics['snapshot_success'] ?? 0));
        $canonical_success = max(0, (int) ($metrics['canonical_success'] ?? 0));

        return [
            'start_queue_depth_total' => max(0, (int) ($gate['queue_depth_total'] ?? 0)),
            'canonical_fallback' => $canonical_success,
            'hit_rate' => isset($metrics['hit_rate']) && is_numeric($metrics['hit_rate'])
                ? (float) $metrics['hit_rate']
                : null,
            'successful_login_total' => $snapshot_success + $canonical_success,
            'auto_heal_queue_depth' => max(0, (int) ($auto_heal['queue_depth'] ?? 0)),
            'active_attempt_count' => max(0, (int) $active_attempt_count),
            'start_attempt_metrics_available' => !empty($startAttemptMetrics['available']),
            'start_attempt_p95_ms' => max(0, (int) ($startAttemptMetrics['start_attempt_p95_ms'] ?? 0)),
            'start_attempt_status_p95_ms' => max(0, (int) ($startAttemptMetrics['start_attempt_status_p95_ms'] ?? 0)),
            'start_attempt_count' => max(0, (int) ($startAttemptMetrics['start_attempt_count'] ?? 0)),
            'start_attempt_status_count' => max(0, (int) ($startAttemptMetrics['start_attempt_status_count'] ?? 0)),
        ];
    }

    /**
     * @param array<string,mixed> $signals
     * @return array{candidate_level:string,reasons:string[]}
     */
    private static function evaluate_candidate_level(array $signals): array
    {
        $critical_reasons = [];
        $busy_reasons = [];

        $start_queue_depth_total = max(0, (int) ($signals['start_queue_depth_total'] ?? 0));
        $canonical_fallback = max(0, (int) ($signals['canonical_fallback'] ?? 0));
        $successful_login_total = max(0, (int) ($signals['successful_login_total'] ?? 0));
        $auto_heal_queue_depth = max(0, (int) ($signals['auto_heal_queue_depth'] ?? 0));
        $active_attempt_count = max(0, (int) ($signals['active_attempt_count'] ?? 0));
        $start_attempt_metrics_available = !empty($signals['start_attempt_metrics_available']);
        $start_attempt_p95_ms = max(0, (int) ($signals['start_attempt_p95_ms'] ?? 0));
        $start_attempt_status_p95_ms = max(0, (int) ($signals['start_attempt_status_p95_ms'] ?? 0));
        $start_attempt_count = max(0, (int) ($signals['start_attempt_count'] ?? 0));
        $start_attempt_status_count = max(0, (int) ($signals['start_attempt_status_count'] ?? 0));
        $hit_rate = isset($signals['hit_rate']) && is_numeric($signals['hit_rate'])
            ? (float) $signals['hit_rate']
            : null;

        if ($start_queue_depth_total >= 75) {
            $critical_reasons[] = 'Start queue sangat padat (' . number_format_i18n($start_queue_depth_total) . ').';
        } elseif ($start_queue_depth_total >= 25) {
            $busy_reasons[] = 'Start queue mulai padat (' . number_format_i18n($start_queue_depth_total) . ').';
        }

        if ($canonical_fallback >= 50 && $hit_rate !== null && $hit_rate < 0.6) {
            $critical_reasons[] = sprintf(
                'Fallback login tinggi (%s) dan hit rate turun ke %s.',
                number_format_i18n($canonical_fallback),
                self::format_hit_rate_label($hit_rate)
            );
        } elseif ($canonical_fallback >= 20) {
            $busy_reasons[] = 'Fallback canonical login meningkat (' . number_format_i18n($canonical_fallback) . ').';
        }

        if ($hit_rate !== null && $successful_login_total >= 20 && $hit_rate < 0.8) {
            $busy_reasons[] = 'Hit rate login snapshot turun ke ' . self::format_hit_rate_label($hit_rate) . '.';
        }

        if ($auto_heal_queue_depth >= 500) {
            $critical_reasons[] = 'Auto-heal queue menumpuk (' . number_format_i18n($auto_heal_queue_depth) . ').';
        } elseif ($auto_heal_queue_depth >= 200) {
            $busy_reasons[] = 'Auto-heal queue mulai menumpuk (' . number_format_i18n($auto_heal_queue_depth) . ').';
        }

        if ($active_attempt_count >= 800 && $start_queue_depth_total > 0) {
            $critical_reasons[] = 'Attempt aktif sangat tinggi (' . number_format_i18n($active_attempt_count) . ') saat antrean start masih ada.';
        } elseif ($active_attempt_count >= 400) {
            $busy_reasons[] = 'Attempt aktif tinggi (' . number_format_i18n($active_attempt_count) . ').';
        }

        if ($start_attempt_metrics_available && $start_attempt_count >= 10) {
            if ($start_attempt_p95_ms >= 12000) {
                $critical_reasons[] = 'p95 start attempt naik ke ' . number_format_i18n($start_attempt_p95_ms) . ' ms.';
            } elseif ($start_attempt_p95_ms >= 6000) {
                $busy_reasons[] = 'p95 start attempt naik ke ' . number_format_i18n($start_attempt_p95_ms) . ' ms.';
            }
        }

        if ($start_attempt_metrics_available && $start_attempt_status_count >= 10) {
            if ($start_attempt_status_p95_ms >= 10000) {
                $critical_reasons[] = 'p95 start attempt status naik ke ' . number_format_i18n($start_attempt_status_p95_ms) . ' ms.';
            } elseif ($start_attempt_status_p95_ms >= 5000) {
                $busy_reasons[] = 'p95 start attempt status naik ke ' . number_format_i18n($start_attempt_status_p95_ms) . ' ms.';
            }
        }

        if (!empty($critical_reasons)) {
            return [
                'candidate_level' => 'critical',
                'reasons' => $critical_reasons,
            ];
        }

        if (!empty($busy_reasons)) {
            return [
                'candidate_level' => 'busy',
                'reasons' => $busy_reasons,
            ];
        }

        return [
            'candidate_level' => 'normal',
            'reasons' => ['Tekanan sistem saat ini normal.'],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array{candidate_level:string,reasons:string[]} $evaluation
     * @param array<string,mixed> $signals
     * @return array<string,mixed>
     */
    private static function apply_evaluation_to_state(array $state, array $evaluation, array $signals): array
    {
        $state = self::normalize_state($state);
        $now_ts = (int) current_time('timestamp');
        $now_mysql = current_time('mysql');
        $candidate_level = self::sanitize_level((string) ($evaluation['candidate_level'] ?? 'normal'));
        $candidate_reasons = array_values(array_filter(array_map('sanitize_text_field', (array) ($evaluation['reasons'] ?? []))));
        $effective_level = self::sanitize_level((string) ($state['effective_level'] ?? 'normal'));
        $source = 'auto';

        $override_level = self::sanitize_level((string) ($state['override_level'] ?? ''));
        $override_expires_ts = self::mysql_to_timestamp((string) ($state['override_expires_at'] ?? ''));
        if ($override_level !== '' && $override_expires_ts > $now_ts) {
            $policy = self::get_policy($override_level);
            $state['candidate_level'] = $candidate_level;
            $state['effective_level'] = $override_level;
            $state['source'] = 'manual_override';
            $state['reasons'] = [
                sprintf(
                    'Override manual %s aktif sampai %s.',
                    (string) ($policy['label'] ?? strtoupper($override_level)),
                    (string) ($state['override_expires_at'] ?? '')
                ),
            ];
            $state['last_evaluated_at'] = $now_mysql;
            $state['signals'] = $signals;
            $state['clean_ticks'] = 0;

            return $state;
        }

        $state['override_level'] = '';
        $state['override_expires_at'] = '';
        $state['override_user_id'] = 0;

        $effective_priority = self::level_priority($effective_level);
        $candidate_priority = self::level_priority($candidate_level);
        $hold_until_ts = self::mysql_to_timestamp((string) ($state['hold_until'] ?? ''));
        $clean_ticks = max(0, (int) ($state['clean_ticks'] ?? 0));

        // Auto hold should never survive much longer than the configured hold window.
        // If older state contains a far-future value, repair it so the level can de-escalate.
        if ($hold_until_ts > 0 && $hold_until_ts > ($now_ts + self::HOLD_SECONDS)) {
            $hold_until_ts = 0;
        }

        if ($candidate_priority > $effective_priority) {
            $effective_level = $candidate_level;
            $clean_ticks = 0;
            $hold_until_ts = $candidate_level === 'normal' ? 0 : ($now_ts + self::HOLD_SECONDS);
        } elseif ($candidate_priority < $effective_priority) {
            $clean_ticks++;
            if ($clean_ticks >= self::CLEAN_TICKS_TO_DEESCALATE && ($hold_until_ts <= 0 || $hold_until_ts <= $now_ts)) {
                $effective_level = $candidate_level;
                $clean_ticks = 0;
                $hold_until_ts = $effective_level === 'normal' ? 0 : ($now_ts + self::HOLD_SECONDS);
            }
        } else {
            $effective_level = $candidate_level;
            $clean_ticks = 0;
            if ($effective_level === 'normal') {
                $hold_until_ts = 0;
            } elseif ($hold_until_ts <= 0 || $hold_until_ts <= $now_ts) {
                $hold_until_ts = $now_ts + self::HOLD_SECONDS;
            }
        }

        $state['candidate_level'] = $candidate_level;
        $state['effective_level'] = $effective_level;
        $state['source'] = $source;
        $state['reasons'] = $effective_level === $candidate_level
            ? $candidate_reasons
            : array_merge(
                ['Level ditahan di ' . strtoupper($effective_level) . ' untuk mencegah flap.'],
                $candidate_reasons
            );
        $state['last_evaluated_at'] = $now_mysql;
        $state['hold_until'] = $hold_until_ts > 0 ? self::timestamp_to_mysql($hold_until_ts) : '';
        $state['clean_ticks'] = $clean_ticks;
        $state['signals'] = $signals;

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function normalize_state(array $state): array
    {
        $effective_level = self::sanitize_level((string) ($state['effective_level'] ?? 'normal'));
        $candidate_level = self::sanitize_level((string) ($state['candidate_level'] ?? $effective_level));
        $override_level = self::sanitize_level((string) ($state['override_level'] ?? ''));
        if (!in_array($override_level, ['busy', 'critical'], true)) {
            $override_level = '';
        }

        return [
            'effective_level' => $effective_level,
            'candidate_level' => $candidate_level,
            'source' => sanitize_key((string) ($state['source'] ?? 'auto')) === 'manual_override' ? 'manual_override' : 'auto',
            'reasons' => array_values(array_filter(array_map('sanitize_text_field', (array) ($state['reasons'] ?? [])))),
            'last_evaluated_at' => (string) ($state['last_evaluated_at'] ?? ''),
            'hold_until' => (string) ($state['hold_until'] ?? ''),
            'override_level' => $override_level,
            'override_expires_at' => (string) ($state['override_expires_at'] ?? ''),
            'override_user_id' => absint($state['override_user_id'] ?? 0),
            'clean_ticks' => max(0, (int) ($state['clean_ticks'] ?? 0)),
            'signals' => is_array($state['signals'] ?? null) ? $state['signals'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_state(array $state): void
    {
        update_option(self::OPTION_STATE, self::normalize_state($state), false);
    }

    private static function sanitize_level(string $level): string
    {
        $level = sanitize_key($level);
        return isset(self::LEVEL_PRIORITY[$level]) ? $level : 'normal';
    }

    private static function level_priority(string $level): int
    {
        $level = self::sanitize_level($level);
        return (int) (self::LEVEL_PRIORITY[$level] ?? 0);
    }

    private static function format_hit_rate_label(?float $hit_rate): string
    {
        if ($hit_rate === null || !is_finite($hit_rate)) {
            return 'N/A';
        }

        return number_format_i18n($hit_rate * 100, 1) . '%';
    }

    private static function mysql_to_timestamp(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? (int) $timestamp : 0;
    }

    private static function timestamp_to_mysql(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function maybe_restore_tick_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    private static function ensure_tick_event(): void
    {
        self::maybe_restore_tick_event();
    }

    private static function clear_tick_event(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
