<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-login-auth-snapshot-cache.php';
}

final class CBT_Login_Snapshot_Metrics_Service
{
    private const REDIS_DEFAULT_HOST = '127.0.0.1';
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_DATABASE = 2;
    private const REDIS_TIMEOUT = 1.5;
    private const REDIS_PREFIX = 'cbt_login_metrics:';
    private const WINDOW_BUCKET_TTL_SECONDS = 172800;
    private const DAILY_BUCKET_TTL_SECONDS = 691200;

    /** @var Redis|false|null */
    private static $metrics_redis = null;
    /** @var bool */
    private static $metrics_redis_connection_attempted = false;
    /** @var string */
    private static $metrics_redis_last_connection_error = '';

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_snapshot_success(array $meta = []): void
    {
        self::record('snapshot_success', $meta);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_canonical_success(string $miss_reason = '', array $meta = []): void
    {
        $meta['snapshot_miss_reason'] = sanitize_key($miss_reason);
        $meta['source_path'] = 'canonical';
        self::record('canonical_success', $meta);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_invalid_credentials(string $source_path = 'canonical', string $miss_reason = '', array $meta = []): void
    {
        $meta['snapshot_miss_reason'] = sanitize_key($miss_reason);
        $meta['source_path'] = sanitize_key($source_path);
        self::record('invalid_credentials', $meta);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_session_already_active(string $source_path = 'canonical', string $miss_reason = '', array $meta = []): void
    {
        $meta['snapshot_miss_reason'] = sanitize_key($miss_reason);
        $meta['source_path'] = sanitize_key($source_path);
        self::record('session_already_active', $meta);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record_session_takeover_stale(array $meta = []): void
    {
        self::record('session_takeover_stale', $meta);
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
     * @return array<string,mixed>
     */
    public static function get_window_summary(int $window_minutes = 15): array
    {
        $window_minutes = max(1, $window_minutes);
        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return self::empty_summary($window_minutes);
        }

        $now_ts = (int) current_time('timestamp');
        $bucket_keys = [];
        for ($index = 0; $index < $window_minutes; $index++) {
            $bucket_ts = self::bucket_timestamp($now_ts - ($index * MINUTE_IN_SECONDS));
            $bucket_keys[] = self::window_bucket_key($bucket_ts);
        }

        $buckets = self::read_hashes($redis, $bucket_keys);
        return self::build_summary_from_hashes($buckets, [
            'available' => true,
            'minutes' => $window_minutes,
            'window_started_at' => gmdate('Y-m-d H:i:s', max(0, $now_ts - ($window_minutes * MINUTE_IN_SECONDS))),
            'window_ended_at' => current_time('mysql'),
        ]);
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

        $today_key = self::daily_bucket_key(self::current_local_date());
        $hash = self::read_hash($redis, $today_key);

        return self::build_summary_from_hashes([$hash], [
            'available' => true,
            'minutes' => 0,
            'date' => self::current_local_date(),
            'window_started_at' => self::current_local_date() . ' 00:00:00',
            'window_ended_at' => current_time('mysql'),
        ]);
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function record(string $event_type, array $meta = []): void
    {
        $event_type = sanitize_key($event_type);
        if ($event_type === '') {
            return;
        }

        $redis = self::metrics_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $source_path = sanitize_key((string) ($meta['source_path'] ?? ''));
        $miss_reason = sanitize_key((string) ($meta['snapshot_miss_reason'] ?? ''));
        $now_ts = (int) current_time('timestamp');
        $window_key = self::window_bucket_key(self::bucket_timestamp($now_ts));
        $daily_key = self::daily_bucket_key(self::current_local_date());

        self::increment_counter($redis, $window_key, $event_type, self::WINDOW_BUCKET_TTL_SECONDS);
        self::increment_counter($redis, $daily_key, $event_type, self::DAILY_BUCKET_TTL_SECONDS);

        if ($source_path !== '' && in_array($event_type, ['invalid_credentials', 'session_already_active'], true)) {
            $source_field = $event_type . '_' . (in_array($source_path, ['snapshot', 'canonical'], true) ? $source_path : 'unknown');
            self::increment_counter($redis, $window_key, $source_field, self::WINDOW_BUCKET_TTL_SECONDS);
            self::increment_counter($redis, $daily_key, $source_field, self::DAILY_BUCKET_TTL_SECONDS);
        }

        if ($miss_reason !== '') {
            $reason_field = self::reason_counter_field($miss_reason);
            self::increment_counter($redis, $window_key, $reason_field, self::WINDOW_BUCKET_TTL_SECONDS);
            self::increment_counter($redis, $daily_key, $reason_field, self::DAILY_BUCKET_TTL_SECONDS);
        }
    }

    private static function increment_counter(Redis $redis, string $key, string $field, int $ttl_seconds): void
    {
        if (!method_exists($redis, 'hIncrBy') || !method_exists($redis, 'expire')) {
            return;
        }

        try {
            $redis->hIncrBy($key, $field, 1);
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
        $normalized = [
            'snapshot_success' => 0,
            'canonical_success' => 0,
            'invalid_credentials' => 0,
            'session_already_active' => 0,
            'session_takeover_stale' => 0,
            'invalid_credentials_snapshot' => 0,
            'invalid_credentials_canonical' => 0,
            'invalid_credentials_unknown' => 0,
            'session_already_active_snapshot' => 0,
            'session_already_active_canonical' => 0,
            'session_already_active_unknown' => 0,
            'snapshot_miss_by_reason' => [],
        ];

        if (!is_array($hash)) {
            return $normalized;
        }

        foreach ($hash as $field => $value) {
            $safe_field = sanitize_key((string) $field);
            if (str_starts_with($safe_field, 'miss_reason__')) {
                $reason = sanitize_key(substr($safe_field, 13));
                if ($reason !== '') {
                    $normalized['snapshot_miss_by_reason'][$reason] = max(0, (int) $value);
                }
                continue;
            }

            if (array_key_exists($safe_field, $normalized)) {
                $normalized[$safe_field] = max(0, (int) $value);
            }
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $hashes
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function build_summary_from_hashes(array $hashes, array $base = []): array
    {
        $summary = array_merge(self::empty_summary((int) ($base['minutes'] ?? 0)), $base);
        $summary['available'] = !empty($base['available']);
        $summary['snapshot_miss_by_reason'] = [];

        foreach ($hashes as $hash) {
            if (!is_array($hash)) {
                continue;
            }

            $summary['snapshot_success'] += max(0, (int) ($hash['snapshot_success'] ?? 0));
            $summary['canonical_success'] += max(0, (int) ($hash['canonical_success'] ?? 0));
            $summary['invalid_credentials'] += max(0, (int) ($hash['invalid_credentials'] ?? 0));
            $summary['session_already_active'] += max(0, (int) ($hash['session_already_active'] ?? 0));
            $summary['session_takeover_stale'] += max(0, (int) ($hash['session_takeover_stale'] ?? 0));

            foreach ((array) ($hash['snapshot_miss_by_reason'] ?? []) as $reason => $count) {
                $safe_reason = sanitize_key((string) $reason);
                if ($safe_reason === '') {
                    continue;
                }

                $summary['snapshot_miss_by_reason'][$safe_reason] = max(0, (int) ($summary['snapshot_miss_by_reason'][$safe_reason] ?? 0)) + max(0, (int) $count);
            }
        }

        $success_total = max(0, (int) $summary['snapshot_success']) + max(0, (int) $summary['canonical_success']);
        $summary['hit_rate'] = $success_total > 0
            ? ((float) $summary['snapshot_success'] / $success_total)
            : null;
        $summary['hit_rate_label'] = is_float($summary['hit_rate'])
            ? number_format_i18n((float) $summary['hit_rate'] * 100, 1) . '%'
            : 'N/A';

        $top_reason = '';
        $top_reason_count = 0;
        foreach ((array) $summary['snapshot_miss_by_reason'] as $reason => $count) {
            $count = max(0, (int) $count);
            if ($count <= $top_reason_count) {
                continue;
            }

            $top_reason = sanitize_key((string) $reason);
            $top_reason_count = $count;
        }

        $summary['top_miss_reason'] = $top_reason;
        $summary['top_miss_reason_count'] = $top_reason_count;
        $summary['top_miss_reason_label'] = $top_reason !== ''
            ? CBT_Login_Auth_Snapshot_Cache::get_snapshot_miss_reason_label($top_reason)
            : '';

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
            'snapshot_success' => 0,
            'canonical_success' => 0,
            'invalid_credentials' => 0,
            'session_already_active' => 0,
            'session_takeover_stale' => 0,
            'snapshot_miss_by_reason' => [],
            'hit_rate' => null,
            'hit_rate_label' => 'N/A',
            'top_miss_reason' => '',
            'top_miss_reason_label' => '',
            'top_miss_reason_count' => 0,
        ];
    }

    private static function bucket_timestamp(int $timestamp): int
    {
        $timestamp = max(0, $timestamp);
        return $timestamp - ($timestamp % MINUTE_IN_SECONDS);
    }

    private static function window_bucket_key(int $bucket_timestamp): string
    {
        return self::REDIS_PREFIX . 'bucket:' . max(0, $bucket_timestamp);
    }

    private static function daily_bucket_key(string $date): string
    {
        return self::REDIS_PREFIX . 'daily:' . preg_replace('/[^0-9-]/', '', $date);
    }

    private static function reason_counter_field(string $reason): string
    {
        return 'miss_reason__' . sanitize_key($reason);
    }

    private static function current_local_date(): string
    {
        return substr((string) current_time('mysql'), 0, 10);
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
            self::$metrics_redis_last_connection_error = 'Koneksi login metrics Redis gagal: ' . $throwable->getMessage();
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
