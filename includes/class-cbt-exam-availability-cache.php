<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Redis_Pipeline_Helper')) {
    require_once __DIR__ . '/class-cbt-redis-pipeline-helper.php';
}

class CBT_Exam_Availability_Cache
{
    private const SNAPSHOT_REDIS_TTL_SECONDS = 44100;
    private const SNAPSHOT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const SNAPSHOT_REDIS_DEFAULT_PORT = 6379;
    private const SNAPSHOT_REDIS_DEFAULT_DATABASE = 2;
    private const SNAPSHOT_REDIS_PREFIX = 'cbt_exam_availability:';
    private const SNAPSHOT_REDIS_TIMEOUT = 1.5;
    private const SNAPSHOT_SOURCE_PREPARED = 'prepared';
    private const SNAPSHOT_SOURCE_MINUTE = 'minute';
    private const SNAPSHOT_SOURCE_MISS = 'miss';
    private const SNAPSHOT_SOURCE_INVALID = 'invalid';

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
     * @param callable():array<string,mixed> $producer
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    public static function warm_student_snapshot(int $user_id, callable $producer): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::empty_payload();
        }

        $payload = $producer();
        $snapshot = self::sanitize_payload(is_array($payload) ? $payload : []);
        $redis = self::snapshot_redis();
        if ($redis instanceof Redis) {
            self::write_student_redis_snapshot($user_id, $snapshot);
        }

        return $snapshot;
    }

    /**
     * @param callable():array<string,mixed> $producer
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    public static function warm_prepared_student_snapshot(int $user_id, callable $producer): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::empty_payload();
        }

        $payload = $producer();
        $snapshot = self::sanitize_payload(is_array($payload) ? $payload : []);
        self::write_prepared_student_snapshot($user_id, $snapshot);

        return $snapshot;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function write_prepared_student_snapshot(int $user_id, array $payload): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $results = self::write_prepared_student_snapshots([
            $user_id => $payload,
        ]);

        return !empty($results[$user_id]);
    }

    /**
     * @param array<int,array<string,mixed>> $payloads_by_user
     * @return array<int,bool>
     */
    public static function write_prepared_student_snapshots(array $payloads_by_user): array
    {
        return self::write_student_redis_snapshots($payloads_by_user, self::SNAPSHOT_SOURCE_PREPARED);
    }

    public static function has_current_prepared_snapshot(int $user_id): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $raw_snapshot = $redis->get(self::prepared_snapshot_storage_key($user_id));
        if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
            return false;
        }

        $decoded = json_decode($raw_snapshot, true);
        if (!is_array($decoded)) {
            $redis->del(self::prepared_snapshot_storage_key($user_id));
            return false;
        }

        return true;
    }

    public static function clear_student_snapshot(int $user_id): int
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $keys = self::collect_student_storage_keys($redis, $user_id);
        if (empty($keys)) {
            return 0;
        }

        return (int) $redis->del(...$keys);
    }

    /**
     * @return array{
     *   user_id:int,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   storage_key:string,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_status:string,
     *   snapshot_source:string,
     *   snapshot_message:string,
     *   item_count:int,
     *   payload_bytes:int,
     *   ttl_seconds:int,
     *   current_user_preview:array<string,mixed>|null,
     *   preview_items:array<int,array{id:int,title:string,availability_reason:string,is_available_now:int}>
     * }
     */
    public static function get_student_snapshot_diagnostics(int $user_id): array
    {
        $user_id = absint($user_id);
        $settings = self::snapshot_redis_settings();
        $storage_key = $user_id > 0 ? self::snapshot_storage_key($user_id) : '';
        $redis = self::snapshot_redis();

        if ($user_id <= 0) {
            return [
                'user_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'idle',
                'snapshot_source' => self::SNAPSHOT_SOURCE_MISS,
                'snapshot_message' => 'User siswa belum dipilih.',
                'item_count' => 0,
                'payload_bytes' => 0,
                'ttl_seconds' => -2,
                'current_user_preview' => null,
                'preview_items' => [],
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'user_id' => $user_id,
                'redis_available' => false,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_source' => self::SNAPSHOT_SOURCE_MISS,
                'snapshot_message' => 'Redis exam availability tidak tersedia.',
                'item_count' => 0,
                'payload_bytes' => 0,
                'ttl_seconds' => -2,
                'current_user_preview' => null,
                'preview_items' => [],
            ];
        }

        $resolved_snapshot = self::resolve_student_snapshot_candidate($user_id, $redis);
        $snapshot_exists = !empty($resolved_snapshot['snapshot_exists']);
        $snapshot_valid = !empty($resolved_snapshot['snapshot_valid']);
        $storage_key = (string) ($resolved_snapshot['storage_key'] ?? $storage_key);
        $snapshot_source = (string) ($resolved_snapshot['snapshot_source'] ?? self::SNAPSHOT_SOURCE_MISS);
        $payload_bytes = max(0, (int) ($resolved_snapshot['payload_bytes'] ?? 0));
        $ttl_seconds = (int) ($resolved_snapshot['ttl_seconds'] ?? -2);
        $snapshot = isset($resolved_snapshot['snapshot']) && is_array($resolved_snapshot['snapshot'])
            ? $resolved_snapshot['snapshot']
            : self::empty_payload();
        $item_count = count((array) ($snapshot['items'] ?? []));
        $current_user_preview = self::build_current_user_preview(
            is_array($snapshot['current_user'] ?? null) ? $snapshot['current_user'] : null
        );
        $preview_items = self::build_preview_items((array) ($snapshot['items'] ?? []));

        if ($snapshot_valid && $snapshot_source === self::SNAPSHOT_SOURCE_PREPARED) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Prepared snapshot availability siap dipakai untuk student GET /exams.';
        } elseif ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Snapshot ketersediaan exam siap dipakai untuk student GET /exams.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Snapshot ditemukan tetapi payload-nya tidak valid dan akan diabaikan.';
        } else {
            $snapshot_status = 'miss';
            $snapshot_message = 'Snapshot belum ada. Request student berikutnya akan hydrate dan menulis ke Redis.';
        }

        return [
            'user_id' => $user_id,
            'redis_available' => true,
            'redis_error' => self::$snapshot_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_source' => $snapshot_source,
            'snapshot_message' => $snapshot_message,
            'item_count' => $item_count,
            'payload_bytes' => $payload_bytes,
            'ttl_seconds' => $ttl_seconds,
            'current_user_preview' => $current_user_preview,
            'preview_items' => $preview_items,
        ];
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
        $resolved = self::resolve_student_snapshot_candidate($user_id, $redis);
        if (empty($resolved['snapshot_valid']) || !isset($resolved['snapshot']) || !is_array($resolved['snapshot'])) {
            return null;
        }

        return $resolved['snapshot'];
    }

    /**
     * @param array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null} $snapshot
     */
    private static function write_student_redis_snapshot(int $user_id, array $snapshot, string $source = self::SNAPSHOT_SOURCE_MINUTE): bool
    {
        $results = self::write_student_redis_snapshots([
            $user_id => $snapshot,
        ], $source);

        return !empty($results[$user_id]);
    }

    /**
     * @param array<int,array<string,mixed>> $payloads_by_user
     * @return array<int,bool>
     */
    private static function write_student_redis_snapshots(array $payloads_by_user, string $source = self::SNAPSHOT_SOURCE_MINUTE): array
    {
        $results = [];
        $operations = [];
        $operation_user_ids = [];

        foreach ($payloads_by_user as $user_id => $payload) {
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                continue;
            }

            $results[$user_id] = false;
            $operation = self::prepare_student_snapshot_write($user_id, is_array($payload) ? $payload : [], $source);
            if (!is_array($operation)) {
                continue;
            }

            $operations[] = $operation;
            $operation_user_ids[] = $user_id;
        }

        if (empty($operations)) {
            return $results;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return $results;
        }

        $write_results = CBT_Redis_Pipeline_Helper::write_setex_results($redis, $operations);
        foreach ($operation_user_ids as $index => $operation_user_id) {
            $results[$operation_user_id] = !empty($write_results[$index]);
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array{key:string,ttl:int,value:string}|null
     */
    private static function prepare_student_snapshot_write(int $user_id, array $snapshot, string $source = self::SNAPSHOT_SOURCE_MINUTE): ?array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $encoded = wp_json_encode(self::sanitize_payload($snapshot));
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $storage_key = $source === self::SNAPSHOT_SOURCE_PREPARED
            ? self::prepared_snapshot_storage_key($user_id)
            : self::snapshot_storage_key($user_id);

        return [
            'key' => $storage_key,
            'ttl' => self::SNAPSHOT_REDIS_TTL_SECONDS,
            'value' => $encoded,
        ];
    }

    /**
     * @return array{
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_source:string,
     *   storage_key:string,
     *   payload_bytes:int,
     *   ttl_seconds:int,
     *   snapshot:array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     * }
     */
    private static function resolve_student_snapshot_candidate(int $user_id, Redis $redis): array
    {
        $default = [
            'snapshot_exists' => false,
            'snapshot_valid' => false,
            'snapshot_source' => self::SNAPSHOT_SOURCE_MISS,
            'storage_key' => self::snapshot_storage_key($user_id),
            'payload_bytes' => 0,
            'ttl_seconds' => -2,
            'snapshot' => self::empty_payload(),
        ];

        $invalid_candidate = null;
        $candidates = [
            [
                'source' => self::SNAPSHOT_SOURCE_PREPARED,
                'storage_key' => self::prepared_snapshot_storage_key($user_id),
                'refresh_dynamic_fields' => true,
            ],
            [
                'source' => self::SNAPSHOT_SOURCE_MINUTE,
                'storage_key' => self::snapshot_storage_key($user_id),
                'refresh_dynamic_fields' => false,
            ],
        ];

        foreach ($candidates as $candidate) {
            $storage_key = (string) ($candidate['storage_key'] ?? '');
            if ($storage_key === '') {
                continue;
            }

            $raw_snapshot = $redis->get($storage_key);
            if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
                continue;
            }

            $decoded = json_decode($raw_snapshot, true);
            if (!is_array($decoded)) {
                $redis->del($storage_key);
                if (!is_array($invalid_candidate)) {
                    $invalid_candidate = array_merge($default, [
                        'snapshot_exists' => true,
                        'snapshot_valid' => false,
                        'snapshot_source' => self::SNAPSHOT_SOURCE_INVALID,
                        'storage_key' => $storage_key,
                        'payload_bytes' => strlen($raw_snapshot),
                        'ttl_seconds' => method_exists($redis, 'ttl') ? (int) $redis->ttl($storage_key) : -2,
                    ]);
                }
                continue;
            }

            $snapshot = self::sanitize_payload($decoded);
            if (!empty($candidate['refresh_dynamic_fields'])) {
                $snapshot = self::refresh_dynamic_payload($snapshot);
            }
            $redis->expire($storage_key, self::SNAPSHOT_REDIS_TTL_SECONDS);

            return array_merge($default, [
                'snapshot_exists' => true,
                'snapshot_valid' => true,
                'snapshot_source' => (string) ($candidate['source'] ?? self::SNAPSHOT_SOURCE_MISS),
                'storage_key' => $storage_key,
                'payload_bytes' => strlen($raw_snapshot),
                'ttl_seconds' => method_exists($redis, 'ttl') ? (int) $redis->ttl($storage_key) : -2,
                'snapshot' => $snapshot,
            ]);
        }

        return is_array($invalid_candidate) ? $invalid_candidate : $default;
    }

    /**
     * @param array<string,mixed>|null $current_user
     * @return array<string,mixed>|null
     */
    private static function build_current_user_preview(?array $current_user): ?array
    {
        if (!is_array($current_user) || empty($current_user)) {
            return null;
        }

        return [
            'user_id' => absint($current_user['user_id'] ?? 0),
            'display_name' => sanitize_text_field((string) ($current_user['display_name'] ?? '')),
            'username' => sanitize_text_field((string) ($current_user['username'] ?? '')),
            'kode_kelas' => sanitize_text_field((string) ($current_user['kode_kelas'] ?? '')),
            'kode_ruang' => sanitize_text_field((string) ($current_user['kode_ruang'] ?? '')),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{id:int,title:string,availability_reason:string,is_available_now:int}>
     */
    private static function build_preview_items(array $items): array
    {
        $preview_items = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $exam_id = absint($item['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $preview_items[] = [
                'id' => $exam_id,
                'title' => sanitize_text_field((string) ($item['title'] ?? ('Exam #' . $exam_id))),
                'availability_reason' => sanitize_key((string) ($item['availability_reason'] ?? '')),
                'is_available_now' => ((int) ($item['is_available_now'] ?? 0) === 1) ? 1 : 0,
            ];
        }

        return $preview_items;
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

    private static function prepared_snapshot_storage_key(int $user_id): string
    {
        $catalog_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        $user_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_user($user_id));
        $catalog_version = max(1, (int) ($catalog_entry['version'] ?? 1));
        $user_version = max(1, (int) ($user_entry['version'] ?? 1));

        return self::SNAPSHOT_REDIS_PREFIX
            . 'student:user:' . max(0, $user_id)
            . ':prepared'
            . ':catalog_v:' . $catalog_version
            . ':user_v:' . $user_version;
    }

    /**
     * @return array<int,string>
     */
    private static function collect_student_storage_keys(Redis $redis, int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [];
        }

        $pattern = self::SNAPSHOT_REDIS_PREFIX . 'student:user:' . $user_id . ':';
        $keys = [];

        if (method_exists($redis, 'scan')) {
            try {
                $iterator = null;
                do {
                    $batch = $redis->scan($iterator, $pattern . '*', 100);
                    if (is_array($batch)) {
                        foreach ($batch as $key) {
                            if (is_string($key) && $key !== '') {
                                $keys[$key] = $key;
                            }
                        }
                    }
                } while ($iterator !== 0 && $iterator !== null);
            } catch (Throwable $throwable) {
                $keys = [];
            }
        }

        if (empty($keys) && isset($GLOBALS['cbt_test_redis_storage']) && is_array($GLOBALS['cbt_test_redis_storage'])) {
            foreach (array_keys($GLOBALS['cbt_test_redis_storage']) as $key) {
                if (is_string($key) && strpos($key, $pattern) === 0) {
                    $keys[$key] = $key;
                }
            }
        }

        if (empty($keys)) {
            $storage_key = self::snapshot_storage_key($user_id);
            $keys[$storage_key] = $storage_key;
        }

        return array_values($keys);
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
     * @param array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null} $snapshot
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    private static function refresh_dynamic_payload(array $snapshot): array
    {
        $current_user = is_array($snapshot['current_user'] ?? null) ? $snapshot['current_user'] : null;
        $student_kelas = self::normalize_kelas_code((string) ($current_user['kode_kelas'] ?? ''));
        $server_now = (string) current_time('mysql');
        $server_timezone = wp_timezone_string();
        $server_now_ts = strtotime($server_now);

        foreach ($snapshot['items'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $start_ts = !empty($item['starts_at']) ? strtotime((string) $item['starts_at']) : false;
            $end_ts = !empty($item['ends_at']) ? strtotime((string) $item['ends_at']) : false;
            $within_schedule = (
                (empty($item['starts_at']) || (string) $item['starts_at'] <= $server_now) &&
                (empty($item['ends_at']) || (string) $item['ends_at'] >= $server_now)
            );
            $class_allowed = self::exam_allows_student_class((array) $item, $student_kelas);
            $schedule_reason = 'in_range';
            if ($start_ts !== false && $server_now_ts !== false && $start_ts > $server_now_ts) {
                $schedule_reason = 'not_started';
            } elseif ($end_ts !== false && $server_now_ts !== false && $end_ts < $server_now_ts) {
                $schedule_reason = 'ended';
            }

            $availability_reason = 'ok';
            if (!$class_allowed) {
                $availability_reason = 'class_mismatch';
            } elseif (!$within_schedule) {
                $availability_reason = $schedule_reason;
            }

            $item['is_within_schedule'] = $within_schedule ? 1 : 0;
            $item['is_class_allowed'] = $class_allowed ? 1 : 0;
            $item['is_available_now'] = ($within_schedule && $class_allowed) ? 1 : 0;
            $item['availability_reason'] = $availability_reason;
            $item['server_now'] = $server_now;
            $item['server_timezone'] = $server_timezone;
            $snapshot['items'][$index] = $item;
        }

        return $snapshot;
    }

    private static function normalize_kelas_code(string $value): string
    {
        return strtoupper(trim(sanitize_text_field($value)));
    }

    /**
     * @return string[]
     */
    private static function parse_exam_target_kelas(string $raw): array
    {
        $parts = preg_split('/[,\n\r;|]+/', $raw) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $normalized = self::normalize_kelas_code((string) $part);
            if ($normalized === '') {
                continue;
            }
            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }

    /**
     * @param array<string,mixed> $exam
     */
    private static function exam_allows_student_class(array $exam, string $student_kelas): bool
    {
        $target_kelas = self::parse_exam_target_kelas((string) ($exam['target_kelas'] ?? ''));
        if (empty($target_kelas)) {
            return true;
        }

        $student_kelas = self::normalize_kelas_code($student_kelas);
        if ($student_kelas === '') {
            return false;
        }

        return in_array($student_kelas, $target_kelas, true);
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
