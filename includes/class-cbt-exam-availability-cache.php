<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

class CBT_Exam_Availability_Cache
{
    private const SNAPSHOT_REDIS_TTL_SECONDS = 44100;
    private const SNAPSHOT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const SNAPSHOT_REDIS_DEFAULT_PORT = 6379;
    private const SNAPSHOT_REDIS_DEFAULT_DATABASE = 2;
    private const SNAPSHOT_REDIS_PREFIX = 'cbt_exam_availability:';
    private const SNAPSHOT_REDIS_TIMEOUT = 1.5;

    /** @var Redis|false|null */
    private static $snapshot_redis = null;
    /** @var bool */
    private static $snapshot_redis_connection_attempted = false;
    /** @var string */
    private static $snapshot_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::snapshot_redis() instanceof Redis;
    }

    /**
     * @param callable():array<string,mixed> $producer
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    public static function get_student_snapshot(int $user_id, callable $producer): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::empty_payload();
        }

        $redis_available = false;
        $snapshot = self::read_student_redis_snapshot($user_id, $redis_available);
        if (is_array($snapshot)) {
            return $snapshot;
        }

        $produced_payload = $producer();
        $snapshot = self::sanitize_payload(is_array($produced_payload) ? $produced_payload : []);
        if ($redis_available) {
            self::write_student_redis_snapshot($user_id, $snapshot);
        }

        return $snapshot;
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}|null
     */
    private static function read_student_redis_snapshot(int $user_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $storage_key = self::snapshot_storage_key($user_id);
        $raw_snapshot = $redis->get($storage_key);
        if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
            return null;
        }

        $decoded = json_decode($raw_snapshot, true);
        if (!is_array($decoded)) {
            $redis->del($storage_key);
            return null;
        }

        $snapshot = self::sanitize_payload($decoded);
        $redis->expire($storage_key, self::SNAPSHOT_REDIS_TTL_SECONDS);
        return $snapshot;
    }

    /**
     * @param array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null} $snapshot
     */
    private static function write_student_redis_snapshot(int $user_id, array $snapshot): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $encoded = wp_json_encode(self::sanitize_payload($snapshot));
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::snapshot_storage_key($user_id), self::SNAPSHOT_REDIS_TTL_SECONDS, $encoded);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    private static function sanitize_payload(array $payload): array
    {
        $items = [];
        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        $current_user = (isset($payload['current_user']) && is_array($payload['current_user']))
            ? $payload['current_user']
            : null;

        return [
            'items' => $items,
            'current_user' => $current_user,
        ];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    private static function empty_payload(): array
    {
        return [
            'items' => [],
            'current_user' => null,
        ];
    }

    /**
     * @return Redis|null
     */
    private static function snapshot_redis(): ?Redis
    {
        if (self::$snapshot_redis_connection_attempted) {
            return (self::$snapshot_redis instanceof Redis) ? self::$snapshot_redis : null;
        }

        self::$snapshot_redis_connection_attempted = true;
        self::$snapshot_redis = false;
        self::$snapshot_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$snapshot_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::snapshot_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::SNAPSHOT_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::SNAPSHOT_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::SNAPSHOT_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis exam availability gagal.');
            }

            self::$snapshot_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$snapshot_redis_last_connection_error = 'Koneksi exam availability Redis gagal: ' . $throwable->getMessage();
            self::$snapshot_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function snapshot_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::SNAPSHOT_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::SNAPSHOT_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::SNAPSHOT_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::SNAPSHOT_REDIS_DEFAULT_DATABASE - 1);
            $database = max(0, $wp_database + 1);
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $scheme = 'tcp';
        if ($host !== '' && strpos($host, '/') === 0) {
            $scheme = 'unix';
        }

        return [
            'host' => $host !== '' ? $host : self::SNAPSHOT_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::SNAPSHOT_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function snapshot_storage_key(int $user_id): string
    {
        $catalog_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        $user_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_user($user_id));
        $catalog_version = max(1, (int) ($catalog_entry['version'] ?? 1));
        $user_version = max(1, (int) ($user_entry['version'] ?? 1));
        $minute_bucket = self::current_minute_bucket();

        return self::SNAPSHOT_REDIS_PREFIX
            . 'student:user:' . max(0, $user_id)
            . ':catalog_v:' . $catalog_version
            . ':user_v:' . $user_version
            . ':minute:' . $minute_bucket;
    }

    private static function current_minute_bucket(): int
    {
        $timestamp = (int) current_time('timestamp');
        if ($timestamp <= 0) {
            $timestamp = time();
        }

        return (int) floor($timestamp / MINUTE_IN_SECONDS);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $constant_name, $default)
    {
        return defined($constant_name) ? constant($constant_name) : $default;
    }
}
