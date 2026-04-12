<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Submit_Flow_Metrics_Service
{
    private const REDIS_DEFAULT_HOST = '127.0.0.1';
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_DATABASE = 2;
    private const REDIS_TIMEOUT = 1.5;
    private const REDIS_PREFIX = 'cbt_submit_flow_metrics:';
    private const WINDOW_BUCKET_TTL_SECONDS = 172800;
    private const DAILY_BUCKET_TTL_SECONDS = 691200;
    private const DEDUPE_TTL_SECONDS = 600;
    private const WATCHLIST_TTL_SECONDS = DAY_IN_SECONDS;
    private const WATCHLIST_LIST_DEFAULT_LIMIT = 100;

    /**
     * @var int[]
     */
    private const LATENCY_BUCKETS_MS = [100, 250, 500, 1000, 2000, 4000, 6000, 8000, 10000, 15000, 30000, 60000];

    /**
     * @var string[]
     */
    private const EVENTS = [
        'finish_submit_started',
        'finish_submit_error',
        'finish_acknowledged',
        'finish_recovery_started',
        'finish_recovery_retry',
        'finish_result_ready',
        'finish_result_recovery_failed',
    ];

    /**
     * @var string[]
     */
    private const LATENCY_METRICS = [
        'submit_to_ack_ms',
        'ack_to_result_ready_ms',
        'submit_to_result_ready_ms',
    ];

    /**
     * @var array<string,string>
     */
    private const EVENT_STATE_MAP = [
        'finish_submit_started' => 'submitting',
        'finish_submit_error' => 'submit_error',
        'finish_acknowledged' => 'result_pending',
        'finish_recovery_started' => 'result_pending',
        'finish_recovery_retry' => 'recovery_retrying',
        'finish_result_ready' => 'resolved',
        'finish_result_recovery_failed' => 'recovery_failed',
    ];

    /**
     * @var array<string,int>
     */
    private const WATCHLIST_STATE_SEVERITY = [
        'recovery_failed' => 1,
        'submit_error' => 2,
        'recovery_retrying' => 3,
        'result_pending' => 4,
        'submitting' => 5,
    ];

    /** @var Redis|false|null */
    private static $metrics_redis = null;
    /** @var bool */
    private static $metrics_redis_connection_attempted = false;
    /** @var string */
    private static $metrics_redis_last_connection_error = '';

    public static function init(): void
    {
    }

    public static function is_allowed_event(string $event): bool
    {
        return self::normalize_event($event) !== '';
    }

    /**
     * @param array<string,mixed> $phase_durations
     * @param array<string,mixed> $meta
     * @return array{recorded:bool,duplicate:bool,skipped:bool}
     */
    public static function record_event(
        int $attempt_id,
        int $exam_id,
        string $event,
        string $event_key,
        int $client_event_at_ms,
        ?int $duration_ms = null,
        array $phase_durations = [],
        array $meta = []
    ): array {
        $attempt_id = max(0, $attempt_id);
        $exam_id = max(0, $exam_id);
        $event = self::normalize_event($event);
        $event_key = self::normalize_event_key($event_key);
        $client_event_at_ms = max(0, $client_event_at_ms);
        $duration_ms = is_int($duration_ms) ? max(0, $duration_ms) : null;
        $phase_durations = self::sanitize_phase_durations($phase_durations);
        $user_id = max(0, (int) ($meta['user_id'] ?? 0));

        if ($attempt_id <= 0 || $exam_id <= 0 || $event === '' || $event_key === '' || $client_event_at_ms <= 0) {
            return [
                'recorded' => false,
                'duplicate' => false,
                'skipped' => true,
            ];
        }

        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return [
                'recorded' => false,
                'duplicate' => false,
                'skipped' => true,
            ];
        }

        $dedupe_namespaces = [
            CBT_Cache::namespace_analytics(),
            CBT_Cache::namespace_attempt($attempt_id),
        ];
        if ($user_id > 0) {
            $dedupe_namespaces[] = CBT_Cache::namespace_user($user_id);
        }
        if ($exam_id > 0) {
            $dedupe_namespaces[] = CBT_Cache::namespace_analytics_exam($exam_id);
        }

        $dedupe_key = self::dedupe_cache_key($event, $event_key);
        CBT_Cache::get($dedupe_key, $dedupe_namespaces, $found);
        if ($found) {
            return [
                'recorded' => false,
                'duplicate' => true,
                'skipped' => false,
            ];
        }

        CBT_Cache::set(
            $dedupe_key,
            [
                'attempt_id' => $attempt_id,
                'exam_id' => $exam_id,
                'event' => $event,
                'event_key' => $event_key,
                'recorded_at' => current_time('mysql'),
            ],
            self::DEDUPE_TTL_SECONDS,
            $dedupe_namespaces
        );

        $resolved_phase_durations = self::merge_default_latency_metric($event, $duration_ms, $phase_durations);
        self::record_event_counter($redis, $event);
        foreach ($resolved_phase_durations as $metric_name => $metric_duration_ms) {
            self::record_latency_metric($redis, $metric_name, $metric_duration_ms);
        }
        self::update_watchlist_state(
            $redis,
            $attempt_id,
            $exam_id,
            $event,
            $client_event_at_ms,
            $meta
        );

        return [
            'recorded' => true,
            'duplicate' => false,
            'skipped' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_window_summary(int $minutes = 15): array
    {
        $minutes = max(1, $minutes);
        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return self::empty_summary($minutes);
        }

        $now_ts = (int) current_time('timestamp');
        $bucket_keys = [];
        for ($index = 0; $index < $minutes; $index++) {
            $bucket_keys[] = self::window_bucket_key(self::bucket_timestamp($now_ts - ($index * MINUTE_IN_SECONDS)));
        }

        return self::build_summary_from_hashes(
            self::read_hashes($redis, $bucket_keys),
            [
                'available' => true,
                'minutes' => $minutes,
                'window_started_at' => gmdate('Y-m-d H:i:s', max(0, $now_ts - ($minutes * MINUTE_IN_SECONDS))),
                'window_ended_at' => current_time('mysql'),
                'redis_error' => '',
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_today_summary(): array
    {
        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return self::empty_summary(0);
        }

        return self::build_summary_from_hashes(
            [self::read_hash($redis, self::daily_bucket_key(self::current_local_date()))],
            [
                'available' => true,
                'minutes' => 0,
                'date' => self::current_local_date(),
                'window_started_at' => self::current_local_date() . ' 00:00:00',
                'window_ended_at' => current_time('mysql'),
                'redis_error' => '',
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_admin_summary(): array
    {
        $window = self::get_window_summary(15);
        $today = self::get_today_summary();
        $watchlist = self::get_unresolved_watchlist_snapshot(self::WATCHLIST_LIST_DEFAULT_LIMIT);

        return [
            'available' => !empty($window['available']) && !empty($today['available']) && !empty($watchlist['available']),
            'redis_error' => self::$metrics_redis_last_connection_error,
            'window' => $window,
            'today' => $today,
            'watchlist' => $watchlist,
        ];
    }

    /**
     * @return array{available:bool,total:int,items:array<int,array<string,mixed>>,redis_error:string}
     */
    public static function get_unresolved_watchlist_snapshot(int $limit = self::WATCHLIST_LIST_DEFAULT_LIMIT): array
    {
        $limit = max(1, $limit);
        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return [
                'available' => false,
                'total' => 0,
                'items' => [],
                'redis_error' => self::$metrics_redis_last_connection_error,
            ];
        }

        $index_key = self::watchlist_index_key();
        $all_members = self::read_zrange($redis, $index_key, 0, -1);
        $members = array_slice($all_members, 0, $limit);
        $items = [];
        foreach ($members as $member) {
            $attempt_id = (int) $member;
            if ($attempt_id <= 0) {
                continue;
            }

            $row = self::read_hash($redis, self::watchlist_attempt_key($attempt_id));
            if (empty($row)) {
                self::remove_watchlist_index_member($redis, $attempt_id);
                continue;
            }

            $normalized = self::normalize_watchlist_row($row);
            if (empty($normalized) || self::is_resolved_state((string) ($normalized['latest_state'] ?? ''))) {
                self::remove_watchlist_index_member($redis, $attempt_id);
                continue;
            }

            $items[] = $normalized;
        }

        return [
            'available' => true,
            'total' => count($all_members),
            'items' => $items,
            'redis_error' => '',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $hashes
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function build_summary_from_hashes(array $hashes, array $base = []): array
    {
        $summary = array_merge(self::empty_summary((int) ($base['minutes'] ?? 15)), $base);
        $summary['available'] = !empty($base['available']);

        $event_counts = [];
        foreach (self::EVENTS as $event) {
            $event_counts[$event] = 0;
        }

        $latency_histogram_totals = [];
        $latency_counts = [];
        foreach (self::LATENCY_METRICS as $metric_name) {
            $latency_histogram_totals[$metric_name] = self::empty_histogram_totals();
            $latency_counts[$metric_name] = 0;
        }

        foreach ($hashes as $hash) {
            if (!is_array($hash)) {
                continue;
            }

            foreach (self::EVENTS as $event) {
                $event_counts[$event] += max(0, (int) ($hash[self::event_count_field($event)] ?? 0));
            }

            foreach (self::LATENCY_METRICS as $metric_name) {
                $latency_counts[$metric_name] += max(0, (int) ($hash[self::latency_count_field($metric_name)] ?? 0));
                foreach (self::histogram_bucket_labels() as $bucket_label) {
                    $latency_histogram_totals[$metric_name][$bucket_label] += max(
                        0,
                        (int) ($hash[self::latency_bucket_field($metric_name, $bucket_label)] ?? 0)
                    );
                }
            }
        }

        $summary['submit_started_total'] = $event_counts['finish_submit_started'];
        $summary['submit_error_total'] = $event_counts['finish_submit_error'];
        $summary['finish_acknowledged_total'] = $event_counts['finish_acknowledged'];
        $summary['finish_recovery_retry_total'] = $event_counts['finish_recovery_retry'];
        $summary['finish_recovery_failed_total'] = $event_counts['finish_result_recovery_failed'];
        $summary['finish_result_ready_total'] = $event_counts['finish_result_ready'];
        $summary['submit_to_ack_p95_ms'] = self::histogram_p95(
            $latency_histogram_totals['submit_to_ack_ms'],
            $latency_counts['submit_to_ack_ms']
        );
        $summary['ack_to_result_ready_p95_ms'] = self::histogram_p95(
            $latency_histogram_totals['ack_to_result_ready_ms'],
            $latency_counts['ack_to_result_ready_ms']
        );
        $summary['submit_to_result_ready_p95_ms'] = self::histogram_p95(
            $latency_histogram_totals['submit_to_result_ready_ms'],
            $latency_counts['submit_to_result_ready_ms']
        );

        return $summary;
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_summary(int $minutes = 15): array
    {
        return [
            'available' => false,
            'minutes' => max(0, $minutes),
            'window_started_at' => '',
            'window_ended_at' => '',
            'date' => '',
            'submit_started_total' => 0,
            'submit_error_total' => 0,
            'finish_acknowledged_total' => 0,
            'finish_recovery_retry_total' => 0,
            'finish_recovery_failed_total' => 0,
            'finish_result_ready_total' => 0,
            'submit_to_ack_p95_ms' => 0,
            'ack_to_result_ready_p95_ms' => 0,
            'submit_to_result_ready_p95_ms' => 0,
            'redis_error' => self::$metrics_redis_last_connection_error,
        ];
    }

    private static function record_event_counter(Redis $redis, string $event): void
    {
        $field = self::event_count_field($event);
        $now_ts = (int) current_time('timestamp');
        self::increment_fields($redis, self::window_bucket_key(self::bucket_timestamp($now_ts)), [$field], self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_fields($redis, self::daily_bucket_key(self::current_local_date()), [$field], self::DAILY_BUCKET_TTL_SECONDS);
    }

    private static function record_latency_metric(Redis $redis, string $metric_name, int $duration_ms): void
    {
        $duration_ms = max(0, $duration_ms);
        $fields = [
            self::latency_count_field($metric_name),
            self::latency_bucket_field($metric_name, self::bucket_label_for_duration($duration_ms)),
        ];
        $now_ts = (int) current_time('timestamp');
        self::increment_fields($redis, self::window_bucket_key(self::bucket_timestamp($now_ts)), $fields, self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_fields($redis, self::daily_bucket_key(self::current_local_date()), $fields, self::DAILY_BUCKET_TTL_SECONDS);
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function update_watchlist_state(
        Redis $redis,
        int $attempt_id,
        int $exam_id,
        string $event,
        int $client_event_at_ms,
        array $meta
    ): void {
        $row_key = self::watchlist_attempt_key($attempt_id);
        $current_row = self::normalize_watchlist_row(self::read_hash($redis, $row_key));
        $current_latest_event_at = max(0, (int) ($current_row['latest_event_at_ms'] ?? 0));
        if ($current_latest_event_at > 0 && $client_event_at_ms < $current_latest_event_at) {
            return;
        }

        $next_state = self::EVENT_STATE_MAP[$event] ?? '';
        if ($next_state === '') {
            return;
        }

        $ack_source = trim((string) ($meta['ack_source'] ?? ($current_row['ack_source'] ?? '')));
        $last_error_code = trim((string) ($meta['error_code'] ?? ''));
        $last_error_message = trim((string) ($meta['error_message'] ?? ''));
        $existing_retry_count = max(0, (int) ($current_row['retry_count'] ?? 0));
        $submit_started_at_ms = max(0, (int) ($current_row['submit_started_at_ms'] ?? 0));
        $acknowledged_at_ms = max(0, (int) ($current_row['acknowledged_at_ms'] ?? 0));
        $resolved_at_ms = 0;

        if ($event === 'finish_submit_started') {
            $submit_started_at_ms = $client_event_at_ms;
            $acknowledged_at_ms = 0;
            $existing_retry_count = 0;
        } elseif ($event === 'finish_acknowledged') {
            if ($submit_started_at_ms <= 0) {
                $submit_started_at_ms = max(0, (int) ($meta['submit_started_at_ms'] ?? 0));
            }
            $acknowledged_at_ms = $client_event_at_ms;
            $last_error_code = '';
            $last_error_message = '';
        } elseif ($event === 'finish_recovery_retry') {
            $existing_retry_count += 1;
        } elseif ($event === 'finish_result_ready') {
            if ($acknowledged_at_ms <= 0) {
                $acknowledged_at_ms = max(
                    0,
                    (int) ($meta['acknowledged_at_ms'] ?? max(0, (int) ($current_row['acknowledged_at_ms'] ?? 0)))
                );
            }
            $resolved_at_ms = $client_event_at_ms;
            $last_error_code = '';
            $last_error_message = '';
        } elseif ($event === 'finish_recovery_started') {
            if ($acknowledged_at_ms <= 0) {
                $acknowledged_at_ms = max(
                    0,
                    (int) ($meta['acknowledged_at_ms'] ?? max(0, (int) ($current_row['acknowledged_at_ms'] ?? 0)))
                );
            }
        } elseif ($event === 'finish_submit_error' || $event === 'finish_result_recovery_failed') {
            if ($last_error_code === '') {
                $last_error_code = trim((string) ($current_row['last_error_code'] ?? ''));
            }
            if ($last_error_message === '') {
                $last_error_message = trim((string) ($current_row['last_error_message'] ?? ''));
            }
        }

        $row = [
            'attempt_id' => $attempt_id,
            'exam_id' => $exam_id,
            'latest_state' => $next_state,
            'latest_event' => $event,
            'latest_event_at_ms' => $client_event_at_ms,
            'submit_started_at_ms' => $submit_started_at_ms,
            'acknowledged_at_ms' => $acknowledged_at_ms,
            'retry_count' => $existing_retry_count,
            'ack_source' => $ack_source,
            'last_error_code' => $last_error_code,
            'last_error_message' => $last_error_message,
            'resolved_at_ms' => $resolved_at_ms,
        ];

        self::write_watchlist_row($redis, $row_key, $row);
        if (self::is_resolved_state($next_state)) {
            self::remove_watchlist_index_member($redis, $attempt_id);
            return;
        }

        self::write_watchlist_index_member(
            $redis,
            $attempt_id,
            self::watchlist_sort_score($next_state, $client_event_at_ms)
        );
    }

    private static function is_resolved_state(string $state): bool
    {
        return strtolower(trim($state)) === 'resolved';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function write_watchlist_row(Redis $redis, string $row_key, array $row): void
    {
        if (!method_exists($redis, 'hMSet') || !method_exists($redis, 'expire')) {
            return;
        }

        try {
            $redis->hMSet($row_key, $row);
            $redis->expire($row_key, self::WATCHLIST_TTL_SECONDS);
        } catch (Throwable $throwable) {
        }
    }

    private static function write_watchlist_index_member(Redis $redis, int $attempt_id, float $score): void
    {
        if (!method_exists($redis, 'zAdd')) {
            return;
        }

        try {
            $redis->zAdd(self::watchlist_index_key(), $score, (string) $attempt_id);
        } catch (Throwable $throwable) {
        }
    }

    private static function remove_watchlist_index_member(Redis $redis, int $attempt_id): void
    {
        if (!method_exists($redis, 'zRem')) {
            return;
        }

        try {
            $redis->zRem(self::watchlist_index_key(), (string) $attempt_id);
        } catch (Throwable $throwable) {
        }
    }

    private static function watchlist_sort_score(string $state, int $latest_event_at_ms): float
    {
        $severity = self::WATCHLIST_STATE_SEVERITY[$state] ?? 99;
        return (float) (($severity * 10000000000000) + max(0, $latest_event_at_ms));
    }

    private static function watchlist_attempt_key(int $attempt_id): string
    {
        return self::REDIS_PREFIX . 'watchlist:attempt:' . max(0, $attempt_id);
    }

    private static function watchlist_index_key(): string
    {
        return self::REDIS_PREFIX . 'watchlist:index';
    }

    /**
     * @param mixed $row
     * @return array<string,mixed>
     */
    private static function normalize_watchlist_row($row): array
    {
        if (!is_array($row)) {
            return [];
        }

        return [
            'attempt_id' => max(0, (int) ($row['attempt_id'] ?? 0)),
            'exam_id' => max(0, (int) ($row['exam_id'] ?? 0)),
            'latest_state' => sanitize_key((string) ($row['latest_state'] ?? '')),
            'latest_event' => sanitize_key((string) ($row['latest_event'] ?? '')),
            'latest_event_at_ms' => max(0, (int) ($row['latest_event_at_ms'] ?? 0)),
            'submit_started_at_ms' => max(0, (int) ($row['submit_started_at_ms'] ?? 0)),
            'acknowledged_at_ms' => max(0, (int) ($row['acknowledged_at_ms'] ?? 0)),
            'retry_count' => max(0, (int) ($row['retry_count'] ?? 0)),
            'ack_source' => sanitize_key((string) ($row['ack_source'] ?? '')),
            'last_error_code' => sanitize_key((string) ($row['last_error_code'] ?? '')),
            'last_error_message' => sanitize_text_field((string) ($row['last_error_message'] ?? '')),
            'resolved_at_ms' => max(0, (int) ($row['resolved_at_ms'] ?? 0)),
        ];
    }

    /**
     * @param array<string,int> $phase_durations
     * @return array<string,int>
     */
    private static function merge_default_latency_metric(string $event, ?int $duration_ms, array $phase_durations): array
    {
        $merged = $phase_durations;
        if ($duration_ms === null || $duration_ms < 0) {
            return $merged;
        }

        if ($event === 'finish_acknowledged' && !isset($merged['submit_to_ack_ms'])) {
            $merged['submit_to_ack_ms'] = $duration_ms;
        } elseif ($event === 'finish_result_ready' && !isset($merged['submit_to_result_ready_ms'])) {
            $merged['submit_to_result_ready_ms'] = $duration_ms;
        }

        return $merged;
    }

    private static function normalize_event(string $event): string
    {
        $event = sanitize_key($event);
        return in_array($event, self::EVENTS, true) ? $event : '';
    }

    private static function normalize_event_key(string $event_key): string
    {
        $event_key = trim(sanitize_text_field($event_key));
        if ($event_key === '') {
            return '';
        }

        return substr($event_key, 0, 190);
    }

    /**
     * @param array<string,mixed> $phase_durations
     * @return array<string,int>
     */
    private static function sanitize_phase_durations(array $phase_durations): array
    {
        $sanitized = [];
        foreach ($phase_durations as $metric_name => $duration_ms) {
            $safe_metric_name = sanitize_key((string) $metric_name);
            if (!in_array($safe_metric_name, self::LATENCY_METRICS, true) || !is_numeric($duration_ms)) {
                continue;
            }

            $sanitized[$safe_metric_name] = max(0, (int) $duration_ms);
        }

        return $sanitized;
    }

    private static function dedupe_cache_key(string $event, string $event_key): string
    {
        return 'submit_flow_metric:' . sanitize_key($event) . ':' . md5($event_key);
    }

    private static function event_count_field(string $event): string
    {
        return 'event__' . sanitize_key($event) . '__count';
    }

    private static function latency_count_field(string $metric_name): string
    {
        return 'latency__' . sanitize_key($metric_name) . '__count';
    }

    private static function latency_bucket_field(string $metric_name, string $bucket_label): string
    {
        return 'latency__' . sanitize_key($metric_name) . '__le_' . sanitize_key($bucket_label);
    }

    private static function bucket_label_for_duration(int $duration_ms): string
    {
        foreach (self::LATENCY_BUCKETS_MS as $bucket_ms) {
            if ($duration_ms <= $bucket_ms) {
                return (string) $bucket_ms;
            }
        }

        return 'overflow';
    }

    /**
     * @return string[]
     */
    private static function histogram_bucket_labels(): array
    {
        $labels = array_map('strval', self::LATENCY_BUCKETS_MS);
        $labels[] = 'overflow';
        return $labels;
    }

    /**
     * @return array<string,int>
     */
    private static function empty_histogram_totals(): array
    {
        $totals = [];
        foreach (self::histogram_bucket_labels() as $label) {
            $totals[$label] = 0;
        }
        return $totals;
    }

    /**
     * @param array<string,int> $histogram_totals
     */
    private static function histogram_p95(array $histogram_totals, int $sample_count): int
    {
        $sample_count = max(0, $sample_count);
        if ($sample_count <= 0) {
            return 0;
        }

        $threshold = max(1, (int) ceil($sample_count * 0.95));
        $running = 0;
        foreach (self::histogram_bucket_labels() as $label) {
            $running += max(0, (int) ($histogram_totals[$label] ?? 0));
            if ($running < $threshold) {
                continue;
            }

            return $label === 'overflow'
                ? 60001
                : max(0, (int) $label);
        }

        return 0;
    }

    private static function bucket_timestamp(int $timestamp): int
    {
        $timestamp = max(0, $timestamp);
        return $timestamp - ($timestamp % MINUTE_IN_SECONDS);
    }

    private static function current_local_date(): string
    {
        return substr((string) current_time('mysql'), 0, 10);
    }

    private static function window_bucket_key(int $bucket_timestamp): string
    {
        return self::REDIS_PREFIX . 'bucket:' . max(0, $bucket_timestamp);
    }

    private static function daily_bucket_key(string $date): string
    {
        return self::REDIS_PREFIX . 'daily:' . preg_replace('/[^0-9-]/', '', $date);
    }

    /**
     * @param array<int,string> $fields
     */
    private static function increment_fields(Redis $redis, string $key, array $fields, int $ttl_seconds): void
    {
        $fields = array_values(array_filter(array_map('strval', $fields)));
        if (empty($fields) || !method_exists($redis, 'hIncrBy') || !method_exists($redis, 'expire')) {
            return;
        }

        try {
            foreach ($fields as $field) {
                $redis->hIncrBy($key, $field, 1);
            }
            $redis->expire($key, $ttl_seconds);
        } catch (Throwable $throwable) {
        }
    }

    /**
     * @param array<int,string> $keys
     * @return array<int,array<string,mixed>>
     */
    private static function read_hashes(Redis $redis, array $keys): array
    {
        $keys = array_values(array_filter(array_map('strval', $keys)));
        if (empty($keys)) {
            return [];
        }

        if (method_exists($redis, 'pipeline') && method_exists($redis, 'exec')) {
            try {
                $pipeline = $redis->pipeline();
                if (is_object($pipeline) && method_exists($pipeline, 'hGetAll') && method_exists($pipeline, 'exec')) {
                    foreach ($keys as $key) {
                        $pipeline->hGetAll($key);
                    }

                    $results = $pipeline->exec();
                    if (is_array($results) && count($results) === count($keys)) {
                        return array_map([self::class, 'normalize_counter_hash'], $results);
                    }
                }
            } catch (Throwable $throwable) {
            }
        }

        $hashes = [];
        foreach ($keys as $key) {
            $hashes[] = self::read_hash($redis, $key);
        }

        return $hashes;
    }

    /**
     * @return array<string,mixed>
     */
    private static function read_hash(Redis $redis, string $key): array
    {
        if (!method_exists($redis, 'hGetAll')) {
            return [];
        }

        try {
            return self::normalize_counter_hash($redis->hGetAll($key), false);
        } catch (Throwable $throwable) {
            return [];
        }
    }

    /**
     * @return array<int,string>
     */
    private static function read_zrange(Redis $redis, string $key, int $start, int $end): array
    {
        if (!method_exists($redis, 'zRange')) {
            return [];
        }

        try {
            $members = $redis->zRange($key, $start, $end);
            if (!is_array($members)) {
                return [];
            }

            return array_values(array_filter(array_map('strval', $members), static function (string $member): bool {
                return $member !== '';
            }));
        } catch (Throwable $throwable) {
            return [];
        }
    }

    /**
     * @param mixed $hash
     * @return array<string,mixed>
     */
    private static function normalize_counter_hash($hash, bool $cast_numeric = true): array
    {
        if (!is_array($hash)) {
            return [];
        }

        $normalized = [];
        foreach ($hash as $field => $value) {
            $safe_field = sanitize_key((string) $field);
            if ($safe_field === '') {
                continue;
            }

            $normalized[$safe_field] = $cast_numeric
                ? max(0, (int) $value)
                : $value;
        }

        return $normalized;
    }

    /**
     * @return Redis|null
     */
    private static function metrics_redis(): ?Redis
    {
        if (self::$metrics_redis_connection_attempted) {
            return (self::$metrics_redis instanceof Redis) ? self::$metrics_redis : null;
        }

        self::$metrics_redis_connection_attempted = true;
        self::$metrics_redis = false;
        self::$metrics_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$metrics_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::REDIS_TIMEOUT)
                );
            }

            if ((string) ($config['password'] ?? '') !== '') {
                $redis->auth((string) $config['password']);
            }

            $database = (int) ($config['database'] ?? self::REDIS_DEFAULT_DATABASE);
            if ($database > 0) {
                $redis->select($database);
            }

            self::$metrics_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$metrics_redis_last_connection_error = 'Koneksi submit flow metrics Redis gagal: ' . $throwable->getMessage();
            self::$metrics_redis = false;
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function redis_settings(): array
    {
        $host = (string) getenv('CBT_REDIS_HOST');
        $port = (int) getenv('CBT_REDIS_PORT');
        $database = (int) getenv('CBT_REDIS_DB');
        $password = (string) getenv('CBT_REDIS_PASSWORD');
        $timeout = (float) getenv('CBT_REDIS_TIMEOUT');

        return [
            'scheme' => str_starts_with($host, '/') ? 'unix' : 'tcp',
            'host' => $host !== '' ? $host : self::REDIS_DEFAULT_HOST,
            'port' => $port > 0 ? $port : self::REDIS_DEFAULT_PORT,
            'database' => $database >= 0 ? $database : self::REDIS_DEFAULT_DATABASE,
            'password' => $password,
            'timeout' => $timeout > 0 ? $timeout : self::REDIS_TIMEOUT,
        ];
    }
}
