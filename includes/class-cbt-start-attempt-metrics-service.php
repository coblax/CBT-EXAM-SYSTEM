<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Start_Attempt_Metrics_Service
{
    private const REDIS_DEFAULT_HOST = '127.0.0.1';
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_DATABASE = 2;
    private const REDIS_TIMEOUT = 1.5;
    private const REDIS_PREFIX = 'cbt_start_attempt_metrics:';
    private const WINDOW_BUCKET_TTL_SECONDS = 172800;
    private const DAILY_BUCKET_TTL_SECONDS = 691200;

    /**
     * @var int[]
     */
    private const LATENCY_BUCKETS_MS = [100, 250, 500, 1000, 2000, 4000, 6000, 8000, 10000, 15000, 30000, 60000];

    /**
     * @var string[]
     */
    private const RESOLUTION_FIELDS = [
        'resolution__started',
        'resolution__resumed',
        'resolution__queued_new_start',
        'resolution__lock_conflict_resumed',
        'resolution__lock_conflict_retryable',
        'resolution__completed',
        'resolution__terminal_error',
    ];

    /**
     * @var string[]
     */
    private const ENDPOINT_SLUGS = [
        'start_attempt',
        'start_attempt_status',
    ];

    /**
     * @var string[]
     */
    private const TRACKED_PHASES = [
        'start_attempt_resume_lookup',
        'start_attempt_gate_evaluation',
        'start_attempt_start_snapshot',
        'start_attempt_attempt_insert',
        'start_attempt_response_ready',
        'start_attempt_status_resume_from_index_light',
        'start_attempt_status_resume_from_db_light',
        'start_attempt_status_response_ready',
        'start_attempt_lazy_runtime_state',
        'start_attempt_deferred_roster_sync',
        'start_attempt_deferred_runtime_snapshots',
    ];

    /** @var Redis|false|null */
    private static $metrics_redis = null;
    /** @var bool */
    private static $metrics_redis_connection_attempted = false;
    /** @var string */
    private static $metrics_redis_last_connection_error = '';
    /** @var bool */
    private static $listeners_registered = false;

    public static function init(): void
    {
        if (self::$listeners_registered || !function_exists('add_action')) {
            return;
        }

        add_action('cbt_start_attempt_phase', [self::class, 'handle_phase']);
        add_action('cbt_start_attempt_resolution', [self::class, 'handle_resolution']);
        self::$listeners_registered = true;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function handle_phase(array $payload): void
    {
        $phase = sanitize_key((string) ($payload['phase'] ?? ''));
        $duration_ms = isset($payload['duration_ms']) && is_numeric($payload['duration_ms'])
            ? (float) $payload['duration_ms']
            : null;

        if ($phase === '' || $duration_ms === null) {
            return;
        }

        self::record_phase($phase, $duration_ms, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function handle_resolution(array $payload): void
    {
        $resolution = sanitize_key((string) ($payload['resolution'] ?? ''));
        if ($resolution === '') {
            return;
        }

        self::record_resolution($resolution, $payload);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_phase(string $phase, float $duration_ms, array $meta = []): void
    {
        $phase = sanitize_key($phase);
        if ($phase === '' || $duration_ms < 0) {
            return;
        }

        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $now_ts = (int) current_time('timestamp');
        $window_key = self::window_bucket_key(self::bucket_timestamp($now_ts));
        $daily_key = self::daily_bucket_key(self::current_local_date());
        $fields = [
            self::phase_count_field($phase),
            self::phase_bucket_field($phase, self::bucket_label_for_duration($duration_ms)),
        ];

        $endpoint_slug = self::endpoint_slug_from_phase($phase);
        if ($endpoint_slug !== '') {
            $fields[] = self::endpoint_count_field($endpoint_slug);
            $fields[] = self::endpoint_bucket_field($endpoint_slug, self::bucket_label_for_duration($duration_ms));
        }

        self::increment_fields($redis, $window_key, $fields, self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_fields($redis, $daily_key, $fields, self::DAILY_BUCKET_TTL_SECONDS);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_resolution(string $resolution, array $meta = []): void
    {
        $field = self::resolution_field_for_key($resolution);
        if ($field === '') {
            return;
        }

        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $now_ts = (int) current_time('timestamp');
        $window_key = self::window_bucket_key(self::bucket_timestamp($now_ts));
        $daily_key = self::daily_bucket_key(self::current_local_date());

        self::increment_fields($redis, $window_key, [$field], self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_fields($redis, $daily_key, [$field], self::DAILY_BUCKET_TTL_SECONDS);
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

        $hash = self::read_hash($redis, self::daily_bucket_key(self::current_local_date()));

        return self::build_summary_from_hashes(
            [$hash],
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

        return [
            'available' => !empty($window['available']) && !empty($today['available']),
            'redis_error' => self::$metrics_redis_last_connection_error,
            'window' => $window,
            'today' => $today,
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

        $phaseHistogramTotals = [];
        $phaseCountTotals = [];
        $endpointHistogramTotals = [
            'start_attempt' => self::empty_histogram_totals(),
            'start_attempt_status' => self::empty_histogram_totals(),
        ];

        foreach ($hashes as $hash) {
            if (!is_array($hash)) {
                continue;
            }

            $summary['started_total'] += max(0, (int) ($hash['resolution__started'] ?? 0));
            $summary['resumed_total'] += max(0, (int) ($hash['resolution__resumed'] ?? 0));
            $summary['queued_total'] += max(0, (int) ($hash['resolution__queued_new_start'] ?? 0));
            $summary['lock_conflict_resumed_total'] += max(0, (int) ($hash['resolution__lock_conflict_resumed'] ?? 0));
            $summary['lock_conflict_retryable_total'] += max(0, (int) ($hash['resolution__lock_conflict_retryable'] ?? 0));
            $summary['completed_total'] += max(0, (int) ($hash['resolution__completed'] ?? 0));
            $summary['terminal_error_total'] += max(0, (int) ($hash['resolution__terminal_error'] ?? 0));

            foreach (self::ENDPOINT_SLUGS as $endpoint) {
                $summary[$endpoint . '_count'] += max(0, (int) ($hash[self::endpoint_count_field($endpoint)] ?? 0));
                foreach (self::histogram_bucket_labels() as $bucket_label) {
                    $endpointHistogramTotals[$endpoint][$bucket_label] += max(0, (int) ($hash[self::endpoint_bucket_field($endpoint, $bucket_label)] ?? 0));
                }
            }

            foreach (self::TRACKED_PHASES as $phase) {
                $phaseCountTotals[$phase] = max(0, (int) ($phaseCountTotals[$phase] ?? 0)) + max(0, (int) ($hash[self::phase_count_field($phase)] ?? 0));
                foreach (self::histogram_bucket_labels() as $bucket_label) {
                    if (!isset($phaseHistogramTotals[$phase])) {
                        $phaseHistogramTotals[$phase] = self::empty_histogram_totals();
                    }
                    $phaseHistogramTotals[$phase][$bucket_label] += max(0, (int) ($hash[self::phase_bucket_field($phase, $bucket_label)] ?? 0));
                }
            }
        }

        $summary['start_attempt_p95_ms'] = self::histogram_p95($endpointHistogramTotals['start_attempt'], (int) $summary['start_attempt_count']);
        $summary['start_attempt_status_p95_ms'] = self::histogram_p95($endpointHistogramTotals['start_attempt_status'], (int) $summary['start_attempt_status_count']);

        foreach ($phaseCountTotals as $phase => $count) {
            $summary['phase_counts'][$phase] = max(0, (int) $count);
            $summary['phase_p95'][$phase] = self::histogram_p95(
                $phaseHistogramTotals[$phase] ?? self::empty_histogram_totals(),
                max(0, (int) $count)
            );
        }

        $topPhase = '';
        $topPhaseP95 = 0;
        foreach ((array) $summary['phase_p95'] as $phase => $phaseP95) {
            $phaseP95 = max(0, (int) $phaseP95);
            $phaseCount = max(0, (int) ($summary['phase_counts'][$phase] ?? 0));
            if ($phaseCount <= 0 || $phaseP95 <= $topPhaseP95) {
                continue;
            }

            $topPhase = sanitize_key((string) $phase);
            $topPhaseP95 = $phaseP95;
        }

        $summary['top_slowest_phase'] = $topPhase;
        $summary['top_slowest_phase_p95_ms'] = $topPhaseP95;

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
            'start_attempt_p95_ms' => 0,
            'start_attempt_status_p95_ms' => 0,
            'start_attempt_count' => 0,
            'start_attempt_status_count' => 0,
            'started_total' => 0,
            'resumed_total' => 0,
            'queued_total' => 0,
            'lock_conflict_resumed_total' => 0,
            'lock_conflict_retryable_total' => 0,
            'completed_total' => 0,
            'terminal_error_total' => 0,
            'phase_p95' => [],
            'phase_counts' => [],
            'top_slowest_phase' => '',
            'top_slowest_phase_p95_ms' => 0,
            'redis_error' => self::$metrics_redis_last_connection_error,
        ];
    }

    /**
     * @param array<int,string> $fields
     */
    private static function increment_fields(Redis $redis, string $key, array $fields, int $ttlSeconds): void
    {
        $fields = array_values(array_filter(array_map('strval', $fields)));
        if (empty($fields) || !method_exists($redis, 'hIncrBy') || !method_exists($redis, 'expire')) {
            return;
        }

        try {
            foreach ($fields as $field) {
                $redis->hIncrBy($key, $field, 1);
            }
            $redis->expire($key, $ttlSeconds);
        } catch (Throwable $throwable) {
        }
    }

    private static function endpoint_slug_from_phase(string $phase): string
    {
        if ($phase === 'start_attempt_response_ready') {
            return 'start_attempt';
        }

        if ($phase === 'start_attempt_status_response_ready') {
            return 'start_attempt_status';
        }

        return '';
    }

    private static function resolution_field_for_key(string $resolution): string
    {
        $resolution = sanitize_key($resolution);
        if ($resolution === 'resume_from_index' || $resolution === 'resume_from_db') {
            return 'resolution__resumed';
        }

        $mapping = [
            'started' => 'resolution__started',
            'queued_new_start' => 'resolution__queued_new_start',
            'lock_conflict_resumed' => 'resolution__lock_conflict_resumed',
            'lock_conflict_retryable' => 'resolution__lock_conflict_retryable',
            'completed' => 'resolution__completed',
            'terminal_error' => 'resolution__terminal_error',
        ];

        return (string) ($mapping[$resolution] ?? '');
    }

    private static function phase_count_field(string $phase): string
    {
        return 'phase__' . sanitize_key($phase) . '__count';
    }

    private static function endpoint_count_field(string $endpoint): string
    {
        return 'endpoint__' . sanitize_key($endpoint) . '__count';
    }

    private static function phase_bucket_field(string $phase, string $bucketLabel): string
    {
        return 'phase__' . sanitize_key($phase) . '__le_' . sanitize_key($bucketLabel);
    }

    private static function endpoint_bucket_field(string $endpoint, string $bucketLabel): string
    {
        return 'endpoint__' . sanitize_key($endpoint) . '__le_' . sanitize_key($bucketLabel);
    }

    private static function bucket_label_for_duration(float $durationMs): string
    {
        foreach (self::LATENCY_BUCKETS_MS as $bucketMs) {
            if ($durationMs <= $bucketMs) {
                return (string) $bucketMs;
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
     * @param array<string,int> $histogramTotals
     */
    private static function histogram_p95(array $histogramTotals, int $sampleCount): int
    {
        $sampleCount = max(0, $sampleCount);
        if ($sampleCount <= 0) {
            return 0;
        }

        $threshold = max(1, (int) ceil($sampleCount * 0.95));
        $running = 0;
        foreach (self::histogram_bucket_labels() as $label) {
            $running += max(0, (int) ($histogramTotals[$label] ?? 0));
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

    private static function window_bucket_key(int $bucketTimestamp): string
    {
        return self::REDIS_PREFIX . 'bucket:' . max(0, $bucketTimestamp);
    }

    private static function daily_bucket_key(string $date): string
    {
        return self::REDIS_PREFIX . 'daily:' . preg_replace('/[^0-9-]/', '', $date);
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
            return self::normalize_counter_hash($redis->hGetAll($key));
        } catch (Throwable $throwable) {
            return [];
        }
    }

    /**
     * @param mixed $hash
     * @return array<string,mixed>
     */
    private static function normalize_counter_hash($hash): array
    {
        if (!is_array($hash)) {
            return [];
        }

        $normalized = [];
        foreach ($hash as $field => $value) {
            $safeField = sanitize_key((string) $field);
            if ($safeField === '') {
                continue;
            }
            $normalized[$safeField] = max(0, (int) $value);
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
            self::$metrics_redis_last_connection_error = 'Koneksi start attempt metrics Redis gagal: ' . $throwable->getMessage();
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
