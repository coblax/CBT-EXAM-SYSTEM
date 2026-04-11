<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Entry_Flow_Metrics_Service
{
    private const REDIS_DEFAULT_HOST = '127.0.0.1';
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_DATABASE = 2;
    private const REDIS_TIMEOUT = 1.5;
    private const REDIS_PREFIX = 'cbt_entry_flow_metrics:';
    private const WINDOW_BUCKET_TTL_SECONDS = 172800;
    private const DAILY_BUCKET_TTL_SECONDS = 691200;
    private const DEDUPE_TTL_SECONDS = 600;

    /**
     * @var int[]
     */
    private const LATENCY_BUCKETS_MS = [100, 250, 500, 1000, 2000, 4000, 6000, 8000, 10000, 15000, 30000, 60000];

    /**
     * @var string[]
     */
    private const FLOWS = [
        'login_to_exam_list',
        'start_to_first_question',
        'resume_to_first_question',
    ];

    /**
     * @var string[]
     */
    private const PHASES = [
        'login_request_ms',
        'login_exam_list_ms',
        'attempt_acquire_ms',
        'attempt_open_shell_ms',
        'first_window_ready_ms',
        'ui_state_reconcile_ms',
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

    public static function is_allowed_flow(string $flow): bool
    {
        return self::normalize_flow($flow) !== '';
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_flow(string $flow, int $duration_ms, array $meta = []): void
    {
        $flow = self::normalize_flow($flow);
        if ($flow === '' || $duration_ms < 0) {
            return;
        }

        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $fields = [
            self::flow_count_field($flow),
            self::flow_bucket_field($flow, self::bucket_label_for_duration($duration_ms)),
        ];

        $now_ts = (int) current_time('timestamp');
        self::increment_fields($redis, self::window_bucket_key(self::bucket_timestamp($now_ts)), $fields, self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_fields($redis, self::daily_bucket_key(self::current_local_date()), $fields, self::DAILY_BUCKET_TTL_SECONDS);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_phase(string $phase, int $duration_ms, array $meta = []): void
    {
        $phase = self::normalize_phase($phase);
        if ($phase === '' || $duration_ms < 0) {
            return;
        }

        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $fields = [
            self::phase_count_field($phase),
            self::phase_bucket_field($phase, self::bucket_label_for_duration($duration_ms)),
        ];

        $now_ts = (int) current_time('timestamp');
        self::increment_fields($redis, self::window_bucket_key(self::bucket_timestamp($now_ts)), $fields, self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_fields($redis, self::daily_bucket_key(self::current_local_date()), $fields, self::DAILY_BUCKET_TTL_SECONDS);
    }

    /**
     * @param array<string,mixed> $phase_durations
     * @param array<string,mixed> $meta
     * @return array{recorded:bool,duplicate:bool,skipped:bool}
     */
    public static function record_flow_event(string $flow, string $metric_key, int $duration_ms, array $phase_durations = [], array $meta = []): array
    {
        $flow = self::normalize_flow($flow);
        $metric_key = self::normalize_metric_key($metric_key);
        $duration_ms = max(0, $duration_ms);
        $phase_durations = self::sanitize_phase_durations($phase_durations);
        $user_id = max(0, (int) ($meta['user_id'] ?? 0));
        $exam_id = max(0, (int) ($meta['exam_id'] ?? 0));

        if ($flow === '' || $metric_key === '') {
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

        $dedupe_namespaces = [CBT_Cache::namespace_analytics()];
        if ($user_id > 0) {
            $dedupe_namespaces[] = CBT_Cache::namespace_user($user_id);
        }
        if ($exam_id > 0) {
            $dedupe_namespaces[] = CBT_Cache::namespace_analytics_exam($exam_id);
        }

        $dedupe_key = self::dedupe_cache_key($flow, $metric_key);
        $cached = CBT_Cache::get($dedupe_key, $dedupe_namespaces, $found);
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
                'flow' => $flow,
                'metric_key' => $metric_key,
                'recorded_at' => current_time('mysql'),
            ],
            self::DEDUPE_TTL_SECONDS,
            $dedupe_namespaces
        );

        self::record_flow($flow, $duration_ms, $meta);
        foreach ($phase_durations as $phase => $phase_duration_ms) {
            self::record_phase((string) $phase, (int) $phase_duration_ms, $meta);
        }

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

        $flowHistogramTotals = [];
        $flowCounts = [];
        foreach (self::FLOWS as $flow) {
            $flowHistogramTotals[$flow] = self::empty_histogram_totals();
            $flowCounts[$flow] = 0;
        }

        $phaseHistogramTotals = [];
        $phaseCountTotals = [];
        foreach (self::PHASES as $phase) {
            $phaseHistogramTotals[$phase] = self::empty_histogram_totals();
            $phaseCountTotals[$phase] = 0;
        }

        foreach ($hashes as $hash) {
            if (!is_array($hash)) {
                continue;
            }

            foreach (self::FLOWS as $flow) {
                $count = max(0, (int) ($hash[self::flow_count_field($flow)] ?? 0));
                $flowCounts[$flow] += $count;
                foreach (self::histogram_bucket_labels() as $bucket_label) {
                    $flowHistogramTotals[$flow][$bucket_label] += max(0, (int) ($hash[self::flow_bucket_field($flow, $bucket_label)] ?? 0));
                }
            }

            foreach (self::PHASES as $phase) {
                $count = max(0, (int) ($hash[self::phase_count_field($phase)] ?? 0));
                $phaseCountTotals[$phase] += $count;
                foreach (self::histogram_bucket_labels() as $bucket_label) {
                    $phaseHistogramTotals[$phase][$bucket_label] += max(0, (int) ($hash[self::phase_bucket_field($phase, $bucket_label)] ?? 0));
                }
            }
        }

        $summary['login_to_exam_list_count'] = $flowCounts['login_to_exam_list'];
        $summary['start_to_first_question_count'] = $flowCounts['start_to_first_question'];
        $summary['resume_to_first_question_count'] = $flowCounts['resume_to_first_question'];
        $summary['login_to_exam_list_p95_ms'] = self::histogram_p95($flowHistogramTotals['login_to_exam_list'], $flowCounts['login_to_exam_list']);
        $summary['start_to_first_question_p95_ms'] = self::histogram_p95($flowHistogramTotals['start_to_first_question'], $flowCounts['start_to_first_question']);
        $summary['resume_to_first_question_p95_ms'] = self::histogram_p95($flowHistogramTotals['resume_to_first_question'], $flowCounts['resume_to_first_question']);

        $top_phase = '';
        $top_phase_p95 = 0;
        foreach (self::PHASES as $phase) {
            $summary['phase_counts'][$phase] = max(0, (int) $phaseCountTotals[$phase]);
            $summary['phase_p95'][$phase] = self::histogram_p95($phaseHistogramTotals[$phase], $phaseCountTotals[$phase]);
            if ($summary['phase_counts'][$phase] <= 0 || $summary['phase_p95'][$phase] <= $top_phase_p95) {
                continue;
            }
            $top_phase = $phase;
            $top_phase_p95 = (int) $summary['phase_p95'][$phase];
        }

        $summary['top_slowest_phase'] = $top_phase;
        $summary['top_slowest_phase_p95_ms'] = $top_phase_p95;

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
            'login_to_exam_list_p95_ms' => 0,
            'start_to_first_question_p95_ms' => 0,
            'resume_to_first_question_p95_ms' => 0,
            'login_to_exam_list_count' => 0,
            'start_to_first_question_count' => 0,
            'resume_to_first_question_count' => 0,
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

    private static function normalize_flow(string $flow): string
    {
        $flow = sanitize_key($flow);
        return in_array($flow, self::FLOWS, true) ? $flow : '';
    }

    private static function normalize_phase(string $phase): string
    {
        $phase = sanitize_key($phase);
        return in_array($phase, self::PHASES, true) ? $phase : '';
    }

    private static function normalize_metric_key(string $metric_key): string
    {
        $metric_key = trim(sanitize_text_field($metric_key));
        if ($metric_key === '') {
            return '';
        }

        return substr($metric_key, 0, 128);
    }

    /**
     * @param array<string,mixed> $phase_durations
     * @return array<string,int>
     */
    private static function sanitize_phase_durations(array $phase_durations): array
    {
        $sanitized = [];
        foreach ($phase_durations as $phase => $duration_ms) {
            $safe_phase = self::normalize_phase((string) $phase);
            if ($safe_phase === '' || !is_numeric($duration_ms)) {
                continue;
            }

            $sanitized[$safe_phase] = max(0, (int) $duration_ms);
        }

        return $sanitized;
    }

    private static function dedupe_cache_key(string $flow, string $metric_key): string
    {
        return 'entry_flow_metric:' . sanitize_key($flow) . ':' . md5($metric_key);
    }

    private static function flow_count_field(string $flow): string
    {
        return 'flow__' . sanitize_key($flow) . '__count';
    }

    private static function flow_bucket_field(string $flow, string $bucket_label): string
    {
        return 'flow__' . sanitize_key($flow) . '__le_' . sanitize_key($bucket_label);
    }

    private static function phase_count_field(string $phase): string
    {
        return 'phase__' . sanitize_key($phase) . '__count';
    }

    private static function phase_bucket_field(string $phase, string $bucket_label): string
    {
        return 'phase__' . sanitize_key($phase) . '__le_' . sanitize_key($bucket_label);
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
            $safe_field = sanitize_key((string) $field);
            if ($safe_field === '') {
                continue;
            }
            $normalized[$safe_field] = max(0, (int) $value);
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
            self::$metrics_redis_last_connection_error = 'Koneksi entry flow metrics Redis gagal: ' . $throwable->getMessage();
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
