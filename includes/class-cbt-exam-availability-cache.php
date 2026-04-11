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

if (!class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
    require_once __DIR__ . '/class-cbt-snapshot-auto-heal-queue-service.php';
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
    private const OPTION_RECENT_REPAIRS = 'cbt_exam_availability_recent_repairs';
    private const RECENT_REPAIR_LIMIT = 200;

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

        $miss_reason = [
            'code' => '',
            'label' => '',
            'detected_snapshot_source' => '',
            'detected_catalog_version' => 0,
            'detected_user_version' => 0,
            'detected_minute_bucket' => 0,
        ];
        if ($redis_available) {
            $redis = self::snapshot_redis();
            if ($redis instanceof Redis) {
                $miss_reason = self::detect_student_snapshot_miss_reason($user_id, $redis);
                if (sanitize_key((string) ($miss_reason['code'] ?? '')) === 'minute_rollover') {
                    $repair = self::maybe_auto_heal_minute_rollover($user_id);
                    if (!empty($repair['success']) && isset($repair['snapshot']) && is_array($repair['snapshot'])) {
                        return $repair['snapshot'];
                    }
                }
            }
        }

        $produced_payload = $producer();
        $snapshot = self::sanitize_payload(is_array($produced_payload) ? $produced_payload : []);
        if ($redis_available) {
            self::write_current_minute_student_snapshot($user_id, $snapshot);
            if (sanitize_key((string) ($miss_reason['code'] ?? '')) === 'version_changed') {
                $prepared_written = self::write_prepared_student_snapshot($user_id, $snapshot);
                if ($prepared_written) {
                    self::record_recent_repair_event(
                        $user_id,
                        'repaired',
                        'Snapshot availability dipulihkan sinkron oleh runtime dan prepared current ikut diperbarui.',
                        'runtime_sync'
                    );
                }
            }
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

    /**
     * @param array<string,mixed> $payload
     */
    public static function write_current_minute_student_snapshot(int $user_id, array $payload): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $results = self::write_current_minute_student_snapshots([
            $user_id => $payload,
        ]);

        return !empty($results[$user_id]);
    }

    /**
     * @param array<int,array<string,mixed>> $payloads_by_user
     * @return array<int,bool>
     */
    public static function write_current_minute_student_snapshots(array $payloads_by_user): array
    {
        return self::write_student_redis_snapshots($payloads_by_user, self::SNAPSHOT_SOURCE_MINUTE);
    }

    /**
     * @return array{
     *   success:bool,
     *   snapshot:array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null},
     *   message:string,
     *   source_key:string
     * }
     */
    public static function maybe_auto_heal_minute_rollover(int $user_id): array
    {
        $default = [
            'success' => false,
            'snapshot' => self::empty_payload(),
            'message' => '',
            'source_key' => '',
        ];

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return $default;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return $default;
        }

        $stale_candidate = self::find_current_version_stale_minute_candidate($user_id, $redis);
        if (!is_array($stale_candidate) || empty($stale_candidate['snapshot']) || !is_array($stale_candidate['snapshot'])) {
            return $default;
        }

        $snapshot = self::refresh_dynamic_payload((array) $stale_candidate['snapshot']);
        $written = self::write_current_minute_student_snapshot($user_id, $snapshot);
        if (!$written) {
            return $default;
        }

        self::record_recent_repair_event(
            $user_id,
            'auto_healed',
            'Minute rollover dipulihkan otomatis dari snapshot menit sebelumnya.',
            'minute_auto_heal'
        );

        return [
            'success' => true,
            'snapshot' => $snapshot,
            'message' => 'Minute rollover dipulihkan otomatis dari snapshot menit sebelumnya.',
            'source_key' => (string) ($stale_candidate['storage_key'] ?? ''),
        ];
    }

    public static function record_repair_event(int $user_id, string $status, string $message, string $source): void
    {
        self::record_recent_repair_event($user_id, $status, $message, $source);
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
        $deleted = (int) $redis->del(...$keys);
        self::clear_recent_repair_event($user_id);

        return $deleted;
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
     *   snapshot_miss_reason:string,
     *   snapshot_miss_reason_label:string,
     *   current_catalog_version:int,
     *   current_user_version:int,
     *   current_minute_bucket:int,
     *   detected_snapshot_source:string,
     *   detected_catalog_version:int,
     *   detected_user_version:int,
     *   detected_minute_bucket:int,
     *   snapshot_message:string,
     *   repair_status:string,
     *   repair_message:string,
     *   repair_queued_at:string,
     *   repair_source:string,
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
        $current_version_meta = self::build_current_version_meta($user_id);
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
                'snapshot_miss_reason' => 'idle',
                'snapshot_miss_reason_label' => 'Belum dipilih',
                'current_catalog_version' => 0,
                'current_user_version' => 0,
                'current_minute_bucket' => 0,
                'detected_snapshot_source' => '',
                'detected_catalog_version' => 0,
                'detected_user_version' => 0,
                'detected_minute_bucket' => 0,
                'snapshot_message' => 'User siswa belum dipilih.',
                'repair_status' => '',
                'repair_message' => '',
                'repair_queued_at' => '',
                'repair_source' => '',
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
                'snapshot_miss_reason' => 'redis_unavailable',
                'snapshot_miss_reason_label' => 'Redis tidak tersedia',
                'current_catalog_version' => (int) ($current_version_meta['catalog_version'] ?? 0),
                'current_user_version' => (int) ($current_version_meta['user_version'] ?? 0),
                'current_minute_bucket' => (int) ($current_version_meta['minute_bucket'] ?? 0),
                'detected_snapshot_source' => '',
                'detected_catalog_version' => 0,
                'detected_user_version' => 0,
                'detected_minute_bucket' => 0,
                'snapshot_message' => 'Redis exam availability tidak tersedia.',
                'repair_status' => '',
                'repair_message' => '',
                'repair_queued_at' => '',
                'repair_source' => '',
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
        $detected_version_meta = self::build_detected_version_meta(
            self::parse_student_storage_key_meta($storage_key, $user_id)
        );
        $item_count = count((array) ($snapshot['items'] ?? []));
        $current_user_preview = self::build_current_user_preview(
            is_array($snapshot['current_user'] ?? null) ? $snapshot['current_user'] : null
        );
        $preview_items = self::build_preview_items((array) ($snapshot['items'] ?? []));

        $miss_reason = [
            'code' => '',
            'label' => '',
        ];

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
            $miss_reason = self::detect_student_snapshot_miss_reason($user_id, $redis);
            $detected_version_meta = [
                'snapshot_source' => (string) ($miss_reason['detected_snapshot_source'] ?? ''),
                'catalog_version' => max(0, (int) ($miss_reason['detected_catalog_version'] ?? 0)),
                'user_version' => max(0, (int) ($miss_reason['detected_user_version'] ?? 0)),
                'minute_bucket' => max(0, (int) ($miss_reason['detected_minute_bucket'] ?? 0)),
            ];
            $snapshot_message = self::build_student_snapshot_miss_message($miss_reason);
            if (sanitize_key((string) ($miss_reason['code'] ?? '')) === 'version_changed') {
                self::maybe_enqueue_auto_heal_version_changed($user_id, 'diagnostics');
            }
        }

        $repair_meta = self::build_student_snapshot_repair_meta($user_id, $snapshot_status);

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
            'snapshot_miss_reason' => (string) ($miss_reason['code'] ?? ''),
            'snapshot_miss_reason_label' => (string) ($miss_reason['label'] ?? ''),
            'current_catalog_version' => (int) ($current_version_meta['catalog_version'] ?? 0),
            'current_user_version' => (int) ($current_version_meta['user_version'] ?? 0),
            'current_minute_bucket' => (int) ($current_version_meta['minute_bucket'] ?? 0),
            'detected_snapshot_source' => (string) ($detected_version_meta['snapshot_source'] ?? ''),
            'detected_catalog_version' => max(0, (int) ($detected_version_meta['catalog_version'] ?? 0)),
            'detected_user_version' => max(0, (int) ($detected_version_meta['user_version'] ?? 0)),
            'detected_minute_bucket' => max(0, (int) ($detected_version_meta['minute_bucket'] ?? 0)),
            'snapshot_message' => $snapshot_message,
            'repair_status' => (string) ($repair_meta['status'] ?? ''),
            'repair_message' => (string) ($repair_meta['message'] ?? ''),
            'repair_queued_at' => (string) ($repair_meta['queued_at'] ?? ''),
            'repair_source' => (string) ($repair_meta['source'] ?? ''),
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
     * @return array{
     *   storage_key:string,
     *   minute_bucket:int,
     *   snapshot:array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     * }|null
     */
    private static function find_current_version_stale_minute_candidate(int $user_id, Redis $redis): ?array
    {
        $current_version_meta = self::build_current_version_meta($user_id);
        $current_catalog_version = (int) ($current_version_meta['catalog_version'] ?? 0);
        $current_user_version = (int) ($current_version_meta['user_version'] ?? 0);
        $current_minute_bucket = (int) ($current_version_meta['minute_bucket'] ?? 0);
        $candidate = null;

        foreach (self::collect_student_storage_keys($redis, $user_id, false) as $storage_key) {
            $meta = self::parse_student_storage_key_meta((string) $storage_key, $user_id);
            if (!is_array($meta) || (string) ($meta['source'] ?? '') !== self::SNAPSHOT_SOURCE_MINUTE) {
                continue;
            }

            $catalog_version = (int) ($meta['catalog_version'] ?? 0);
            $user_version = (int) ($meta['user_version'] ?? 0);
            $minute_bucket = max(0, (int) ($meta['minute_bucket'] ?? 0));
            if ($catalog_version !== $current_catalog_version || $user_version !== $current_user_version) {
                continue;
            }

            if ($minute_bucket <= 0 || $minute_bucket === $current_minute_bucket) {
                continue;
            }

            $raw_snapshot = $redis->get((string) $storage_key);
            if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
                continue;
            }

            $decoded = json_decode($raw_snapshot, true);
            if (!is_array($decoded)) {
                $redis->del((string) $storage_key);
                continue;
            }

            if (!is_array($candidate) || $minute_bucket > (int) ($candidate['minute_bucket'] ?? 0)) {
                $candidate = [
                    'storage_key' => (string) $storage_key,
                    'minute_bucket' => $minute_bucket,
                    'snapshot' => self::sanitize_payload($decoded),
                ];
            }
        }

        return is_array($candidate) ? $candidate : null;
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
    private static function collect_student_storage_keys(Redis $redis, int $user_id, bool $include_fallback = true): array
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

        if ($include_fallback && empty($keys)) {
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
     * @return array{
     *   code:string,
     *   label:string,
     *   detected_snapshot_source:string,
     *   detected_catalog_version:int,
     *   detected_user_version:int,
     *   detected_minute_bucket:int
     * }
     */
    private static function detect_student_snapshot_miss_reason(int $user_id, Redis $redis): array
    {
        $keys = self::collect_student_storage_keys($redis, $user_id, false);
        if (empty($keys)) {
            return [
                'code' => 'not_prepared',
                'label' => 'Belum disiapkan',
                'detected_snapshot_source' => '',
                'detected_catalog_version' => 0,
                'detected_user_version' => 0,
                'detected_minute_bucket' => 0,
            ];
        }

        $current_catalog_version = max(1, (int) (CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog())['version'] ?? 1));
        $current_user_version = max(1, (int) (CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_user($user_id))['version'] ?? 1));
        $current_minute_bucket = self::current_minute_bucket();
        $has_version_mismatch = false;
        $has_minute_rollover = false;
        $version_mismatch_meta = null;
        $minute_rollover_meta = null;

        foreach ($keys as $key) {
            $meta = self::parse_student_storage_key_meta((string) $key, $user_id);
            if (!is_array($meta)) {
                continue;
            }

            $catalog_version = (int) ($meta['catalog_version'] ?? 0);
            $user_version = (int) ($meta['user_version'] ?? 0);
            $minute_bucket = isset($meta['minute_bucket']) ? (int) $meta['minute_bucket'] : null;
            if ($catalog_version !== $current_catalog_version || $user_version !== $current_user_version) {
                $has_version_mismatch = true;
                if (!is_array($version_mismatch_meta)) {
                    $version_mismatch_meta = $meta;
                }
                continue;
            }

            if ((string) ($meta['source'] ?? '') === self::SNAPSHOT_SOURCE_MINUTE
                && $minute_bucket !== null
                && $minute_bucket !== $current_minute_bucket
            ) {
                $has_minute_rollover = true;
                if (!is_array($minute_rollover_meta)) {
                    $minute_rollover_meta = $meta;
                }
            }
        }

        if ($has_version_mismatch) {
            return self::merge_student_snapshot_miss_reason_meta([
                'code' => 'version_changed',
                'label' => 'Version berubah',
            ], $version_mismatch_meta);
        }

        if ($has_minute_rollover) {
            return self::merge_student_snapshot_miss_reason_meta([
                'code' => 'minute_rollover',
                'label' => 'Minute rollover',
            ], $minute_rollover_meta);
        }

        return [
            'code' => 'not_prepared',
            'label' => 'Belum disiapkan',
            'detected_snapshot_source' => '',
            'detected_catalog_version' => 0,
            'detected_user_version' => 0,
            'detected_minute_bucket' => 0,
        ];
    }

    /**
     * @param array{code:string,label:string} $miss_reason
     */
    private static function build_student_snapshot_miss_message(array $miss_reason): string
    {
        $code = sanitize_key((string) ($miss_reason['code'] ?? ''));

        if ($code === 'minute_rollover') {
            return 'Snapshot MISS karena minute rollover. Bucket menit saat ini sudah berganti, jadi key minute sebelumnya tidak lagi dianggap current.';
        }

        if ($code === 'version_changed') {
            return 'Snapshot MISS karena version berubah. Namespace katalog atau user sudah berubah, jadi key availability sebelumnya tidak lagi dianggap current.';
        }

        return 'Snapshot MISS karena belum pernah disiapkan atau sudah dibersihkan. Request student berikutnya akan hydrate dan menulis key current.';
    }

    /**
     * @return array{status:string,message:string,queued_at:string,source:string}
     */
    private static function build_student_snapshot_repair_meta(int $user_id, string $snapshot_status): array
    {
        $default = [
            'status' => '',
            'message' => '',
            'queued_at' => '',
            'source' => '',
        ];

        if ($user_id <= 0) {
            return $default;
        }

        if (sanitize_key($snapshot_status) === 'ready') {
            $recent_repair = self::get_recent_repair_event($user_id);
            if (is_array($recent_repair)) {
                return [
                    'status' => sanitize_key((string) ($recent_repair['status'] ?? '')),
                    'message' => trim((string) ($recent_repair['message'] ?? '')),
                    'queued_at' => trim((string) ($recent_repair['completed_at'] ?? '')),
                    'source' => trim((string) ($recent_repair['source'] ?? '')),
                ];
            }
        }

        if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            $queue_meta = CBT_Snapshot_Auto_Heal_Queue_Service::get_target_repair_state('availability_user', $user_id);
            if (!empty($queue_meta['queued'])) {
                return [
                    'status' => 'queued_auto_heal',
                    'message' => trim((string) ($queue_meta['message'] ?? 'Snapshot availability sedang menunggu background auto-heal.')),
                    'queued_at' => trim((string) ($queue_meta['queued_at'] ?? '')),
                    'source' => trim((string) ($queue_meta['source'] ?? 'system')),
                ];
            }
        }

        if (
            class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            && method_exists('CBT_Exam_Availability_Auto_Warm_Service', 'get_rewarm_user_state')
        ) {
            $queue_meta = CBT_Exam_Availability_Auto_Warm_Service::get_rewarm_user_state($user_id);
            if (!empty($queue_meta['queued'])) {
                return [
                    'status' => 'queued_rewarm',
                    'message' => trim((string) ($queue_meta['message'] ?? 'MISS karena Version berubah. Siswa ini sudah masuk antrean rewarm.')),
                    'queued_at' => trim((string) ($queue_meta['queued_at'] ?? '')),
                    'source' => trim((string) ($queue_meta['source'] ?? 'admin')),
                ];
            }
        }

        if (sanitize_key($snapshot_status) !== 'ready') {
            return $default;
        }

        return $default;
    }

    private static function maybe_enqueue_auto_heal_version_changed(int $user_id, string $source = 'system'): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            return;
        }

        CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('availability_user', $user_id, 'version_changed', $source);
    }

    /**
     * @return array{source:string,catalog_version:int,user_version:int,minute_bucket:int|null}|null
     */
    private static function parse_student_storage_key_meta(string $storage_key, int $user_id): ?array
    {
        $storage_key = trim($storage_key);
        if ($storage_key === '') {
            return null;
        }

        $pattern = '/^' . preg_quote(self::SNAPSHOT_REDIS_PREFIX . 'student:user:' . max(0, $user_id), '/') . ':(prepared(?::catalog_v:(\d+):user_v:(\d+))|catalog_v:(\d+):user_v:(\d+):minute:(\d+))$/';
        if (!preg_match($pattern, $storage_key, $matches)) {
            return null;
        }

        if (strpos((string) ($matches[1] ?? ''), 'prepared') === 0) {
            return [
                'source' => self::SNAPSHOT_SOURCE_PREPARED,
                'catalog_version' => max(1, (int) ($matches[2] ?? 1)),
                'user_version' => max(1, (int) ($matches[3] ?? 1)),
                'minute_bucket' => null,
            ];
        }

        return [
            'source' => self::SNAPSHOT_SOURCE_MINUTE,
            'catalog_version' => max(1, (int) ($matches[4] ?? 1)),
            'user_version' => max(1, (int) ($matches[5] ?? 1)),
            'minute_bucket' => max(0, (int) ($matches[6] ?? 0)),
        ];
    }

    /**
     * @return array{catalog_version:int,user_version:int,minute_bucket:int}
     */
    private static function build_current_version_meta(int $user_id): array
    {
        if ($user_id <= 0) {
            return [
                'catalog_version' => 0,
                'user_version' => 0,
                'minute_bucket' => 0,
            ];
        }

        return [
            'catalog_version' => max(1, (int) (CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog())['version'] ?? 1)),
            'user_version' => max(1, (int) (CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_user($user_id))['version'] ?? 1)),
            'minute_bucket' => self::current_minute_bucket(),
        ];
    }

    /**
     * @param array{source?:string,catalog_version?:int,user_version?:int,minute_bucket?:int|null}|null $meta
     * @return array{snapshot_source:string,catalog_version:int,user_version:int,minute_bucket:int}
     */
    private static function build_detected_version_meta(?array $meta): array
    {
        return [
            'snapshot_source' => sanitize_key((string) ($meta['source'] ?? '')),
            'catalog_version' => max(0, (int) ($meta['catalog_version'] ?? 0)),
            'user_version' => max(0, (int) ($meta['user_version'] ?? 0)),
            'minute_bucket' => max(0, (int) ($meta['minute_bucket'] ?? 0)),
        ];
    }

    /**
     * @param array{
     *   code:string,
     *   label:string
     * } $reason
     * @param array{source?:string,catalog_version?:int,user_version?:int,minute_bucket?:int|null}|null $meta
     * @return array{
     *   code:string,
     *   label:string,
     *   detected_snapshot_source:string,
     *   detected_catalog_version:int,
     *   detected_user_version:int,
     *   detected_minute_bucket:int
     * }
     */
    private static function merge_student_snapshot_miss_reason_meta(array $reason, ?array $meta): array
    {
        $detected = self::build_detected_version_meta($meta);

        return [
            'code' => sanitize_key((string) ($reason['code'] ?? '')),
            'label' => sanitize_text_field((string) ($reason['label'] ?? '')),
            'detected_snapshot_source' => (string) ($detected['snapshot_source'] ?? ''),
            'detected_catalog_version' => max(0, (int) ($detected['catalog_version'] ?? 0)),
            'detected_user_version' => max(0, (int) ($detected['user_version'] ?? 0)),
            'detected_minute_bucket' => max(0, (int) ($detected['minute_bucket'] ?? 0)),
        ];
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

    private static function record_recent_repair_event(int $user_id, string $status, string $message, string $source): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $events = get_option(self::OPTION_RECENT_REPAIRS, []);
        if (!is_array($events)) {
            $events = [];
        }

        $event_key = (string) $user_id;
        unset($events[$event_key]);
        $events[$event_key] = [
            'status' => sanitize_key($status),
            'message' => sanitize_text_field($message),
            'source' => sanitize_key($source),
            'completed_at' => current_time('mysql'),
        ];

        if (count($events) > self::RECENT_REPAIR_LIMIT) {
            $events = array_slice($events, -self::RECENT_REPAIR_LIMIT, null, true);
        }

        update_option(self::OPTION_RECENT_REPAIRS, $events);
    }

    /**
     * @return array{status:string,message:string,source:string,completed_at:string}|null
     */
    private static function get_recent_repair_event(int $user_id): ?array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $events = get_option(self::OPTION_RECENT_REPAIRS, []);
        if (!is_array($events) || !isset($events[(string) $user_id]) || !is_array($events[(string) $user_id])) {
            return null;
        }

        $event = $events[(string) $user_id];

        return [
            'status' => sanitize_key((string) ($event['status'] ?? '')),
            'message' => sanitize_text_field((string) ($event['message'] ?? '')),
            'source' => sanitize_key((string) ($event['source'] ?? '')),
            'completed_at' => sanitize_text_field((string) ($event['completed_at'] ?? '')),
        ];
    }

    private static function clear_recent_repair_event(int $user_id): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $events = get_option(self::OPTION_RECENT_REPAIRS, []);
        if (!is_array($events) || !isset($events[(string) $user_id])) {
            return;
        }

        unset($events[(string) $user_id]);
        update_option(self::OPTION_RECENT_REPAIRS, $events);
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
