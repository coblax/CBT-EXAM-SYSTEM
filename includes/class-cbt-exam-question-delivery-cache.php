<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

class CBT_Exam_Question_Delivery_Cache
{
    private const DIAGNOSTIC_PREVIEW_DEFAULT_PER_PAGE = 5;
    private const DELIVERY_REDIS_TTL_SECONDS = 44100;
    private const DELIVERY_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const DELIVERY_REDIS_DEFAULT_PORT = 6379;
    private const DELIVERY_REDIS_DEFAULT_DATABASE = 2;
    private const DELIVERY_REDIS_PREFIX = 'cbt_exam_delivery:';
    private const DELIVERY_REDIS_TIMEOUT = 1.5;

    /** @var Redis|false|null */
    private static $delivery_redis = null;
    /** @var bool */
    private static $delivery_redis_connection_attempted = false;
    /** @var string */
    private static $delivery_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::delivery_redis() instanceof Redis;
    }

    /**
     * @return array{
     *   exam_id:int,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   revision_meta:array{exam_id:int,version:int,invalidated_at:string,signature:string},
     *   storage_key:string,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_status:string,
     *   snapshot_message:string,
     *   snapshot_item_count:int,
     *   snapshot_payload_bytes:int,
     *   snapshot_ttl_seconds:int,
     *   preview_current_page:int,
     *   preview_total_pages:int,
     *   preview_per_page:int,
     *   preview_question_ids:array<int,int>,
     *   preview_items:array<int,array{id:int,question_type:string,points:float,question_text_excerpt:string,option_count:int}>
     * }
     */
    public static function get_exam_payload_diagnostics(int $exam_id, int $preview_page = 1, int $preview_per_page = self::DIAGNOSTIC_PREVIEW_DEFAULT_PER_PAGE): array
    {
        $exam_id = absint($exam_id);
        $preview_page = max(1, $preview_page);
        $preview_per_page = max(1, $preview_per_page);
        $revision_meta = self::exam_revision_meta($exam_id);
        $storage_key = ($exam_id > 0 && (int) ($revision_meta['exam_id'] ?? 0) === $exam_id)
            ? self::storage_key($exam_id, $revision_meta)
            : '';
        $settings = self::delivery_redis_settings();
        $redis = self::delivery_redis();
        $snapshot_exists = false;
        $snapshot_valid = false;
        $snapshot_item_count = 0;
        $snapshot_payload_bytes = 0;
        $snapshot_ttl_seconds = -2;
        $preview_current_page = 1;
        $preview_total_pages = 1;
        $preview_question_ids = [];
        $preview_items = [];
        $snapshot_status = 'idle';
        $snapshot_message = 'Masukkan Exam ID untuk memeriksa snapshot delivery.';

        if ($exam_id <= 0) {
            return [
                'exam_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$delivery_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::DELIVERY_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::DELIVERY_REDIS_DEFAULT_DATABASE),
                'revision_meta' => $revision_meta,
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => $snapshot_status,
                'snapshot_message' => $snapshot_message,
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'preview_current_page' => 1,
                'preview_total_pages' => 1,
                'preview_per_page' => $preview_per_page,
                'preview_question_ids' => [],
                'preview_items' => [],
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'exam_id' => $exam_id,
                'redis_available' => false,
                'redis_error' => self::$delivery_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::DELIVERY_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::DELIVERY_REDIS_DEFAULT_DATABASE),
                'revision_meta' => $revision_meta,
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Redis exam delivery tidak tersedia. Jalur student akan fallback ke DB/cache biasa.',
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'preview_current_page' => 1,
                'preview_total_pages' => 1,
                'preview_per_page' => $preview_per_page,
                'preview_question_ids' => [],
                'preview_items' => [],
            ];
        }

        $raw_payload = $storage_key !== '' ? $redis->get($storage_key) : false;
        if (is_string($raw_payload) && trim($raw_payload) !== '') {
            $snapshot_exists = true;
            $snapshot_payload_bytes = strlen($raw_payload);
            if (method_exists($redis, 'ttl')) {
                $snapshot_ttl_seconds = (int) $redis->ttl($storage_key);
            }

            $decoded = json_decode($raw_payload, true);
            if (is_array($decoded)) {
                $stored_exam_id = absint($decoded['exam_id'] ?? 0);
                $stored_signature = is_scalar($decoded['revision_signature'] ?? null)
                    ? (string) $decoded['revision_signature']
                    : '';
                $expected_signature = self::revision_signature($revision_meta);
                $items = self::normalize_items($decoded['items'] ?? []);
                $snapshot_item_count = count($items);
                $preview_total_pages = max(1, (int) ceil($snapshot_item_count / $preview_per_page));
                $preview_current_page = min($preview_total_pages, max(1, $preview_page));
                $preview_offset = ($preview_current_page - 1) * $preview_per_page;
                $preview_slice = array_slice($items, $preview_offset, $preview_per_page);
                $preview_question_ids = array_values(array_filter(array_map(static function ($item): int {
                    return absint(is_array($item) ? ($item['id'] ?? 0) : 0);
                }, $preview_slice)));
                $preview_items = self::build_preview_items($preview_slice);
                $snapshot_valid = $stored_exam_id === $exam_id && $stored_signature === $expected_signature;
            }
        }

        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Snapshot Redis siap dipakai untuk base payload student GET /questions.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Snapshot ditemukan tetapi signature/revision tidak cocok dan akan diabaikan.';
        } else {
            $snapshot_status = 'miss';
            $snapshot_message = 'Snapshot belum ada untuk revision exam ini. Request student pertama akan hydrate lalu menulis ke Redis.';
        }

        return [
            'exam_id' => $exam_id,
            'redis_available' => true,
            'redis_error' => self::$delivery_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::DELIVERY_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::DELIVERY_REDIS_DEFAULT_DATABASE),
            'revision_meta' => $revision_meta,
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_message' => $snapshot_message,
            'snapshot_item_count' => $snapshot_item_count,
            'snapshot_payload_bytes' => $snapshot_payload_bytes,
            'snapshot_ttl_seconds' => $snapshot_ttl_seconds,
            'preview_current_page' => $preview_current_page,
            'preview_total_pages' => $preview_total_pages,
            'preview_per_page' => $preview_per_page,
            'preview_question_ids' => $preview_question_ids,
            'preview_items' => $preview_items,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{id:int,question_type:string,points:float,question_text_excerpt:string,option_count:int}>
     */
    private static function build_preview_items(array $items): array
    {
        $preview_items = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question_id = absint($item['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_text = wp_strip_all_tags((string) ($item['question_text'] ?? ''), true);
            if (function_exists('mb_substr')) {
                $question_text = mb_substr($question_text, 0, 140);
            } else {
                $question_text = substr($question_text, 0, 140);
            }

            $preview_items[] = [
                'id' => $question_id,
                'question_type' => sanitize_key((string) ($item['question_type'] ?? '')),
                'points' => (float) ($item['points'] ?? 0),
                'question_text_excerpt' => trim($question_text),
                'option_count' => is_array($item['options'] ?? null) ? count($item['options']) : 0,
            ];
        }

        return $preview_items;
    }

    /**
     * @param callable(int):array<int,array<string,mixed>> $producer
     * @return array<int,array<string,mixed>>
     */
    public static function get_exam_payload(int $exam_id, callable $producer): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        $payload = self::read_exam_payload($exam_id, $redis_available);
        if ($payload !== null) {
            return $payload;
        }

        $payload = self::normalize_items($producer($exam_id));
        if ($redis_available) {
            self::write_exam_payload($exam_id, $payload);
        }

        return $payload;
    }

    /**
     * @param callable(int):array<int,array<string,mixed>> $producer
     */
    public static function warm_exam_payload(int $exam_id, callable $producer): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return;
        }

        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = self::normalize_items($producer($exam_id));
        self::write_exam_payload($exam_id, $payload);
    }

    public static function clear_exam_payload(int $exam_id): int
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return 0;
        }

        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $keys = self::collect_exam_storage_keys($redis, $exam_id);
        if (empty($keys)) {
            return 0;
        }

        return (int) $redis->del(...$keys);
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    private static function read_exam_payload(int $exam_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return null;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $raw_payload = $redis->get($storage_key);
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del($storage_key);
            return null;
        }

        $stored_exam_id = absint($decoded['exam_id'] ?? 0);
        $stored_signature = is_scalar($decoded['revision_signature'] ?? null)
            ? (string) $decoded['revision_signature']
            : '';
        $expected_signature = self::revision_signature($revision_meta);
        if ($stored_exam_id !== $exam_id || $stored_signature !== $expected_signature) {
            $redis->del($storage_key);
            return null;
        }

        $items = self::normalize_items($decoded['items'] ?? []);
        $redis->expire($storage_key, self::DELIVERY_REDIS_TTL_SECONDS);

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function write_exam_payload(int $exam_id, array $items): void
    {
        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $encoded_payload = wp_json_encode([
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
            'items' => array_values($items),
        ]);
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return;
        }

        $redis->setEx($storage_key, self::DELIVERY_REDIS_TTL_SECONDS, $encoded_payload);
    }

    /**
     * @return array<int,string>
     */
    private static function collect_exam_storage_keys(Redis $redis, int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        $pattern = self::DELIVERY_REDIS_PREFIX . 'exam:' . $exam_id . ':';
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
                if (!is_string($key) || strpos($key, $pattern) !== 0) {
                    continue;
                }

                $keys[$key] = $key;
            }
        }

        if (empty($keys)) {
            $revision_meta = self::exam_revision_meta($exam_id);
            if ((int) ($revision_meta['exam_id'] ?? 0) === $exam_id) {
                $storage_key = self::storage_key($exam_id, $revision_meta);
                $keys[$storage_key] = $storage_key;
            }
        }

        return array_values($keys);
    }

    /**
     * @return array{exam_id:int,version:int,invalidated_at:string,signature:string}
     */
    private static function exam_revision_meta(int $exam_id): array
    {
        $meta = CBT_Cache::get_exam_revision_meta($exam_id);

        return [
            'exam_id' => absint($meta['exam_id'] ?? $exam_id),
            'version' => max(1, (int) ($meta['version'] ?? 1)),
            'invalidated_at' => is_scalar($meta['invalidated_at'] ?? null)
                ? (string) $meta['invalidated_at']
                : '',
            'signature' => is_scalar($meta['signature'] ?? null)
                ? (string) $meta['signature']
                : '',
        ];
    }

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     */
    private static function storage_key(int $exam_id, array $revision_meta): string
    {
        return self::DELIVERY_REDIS_PREFIX
            . 'exam:' . $exam_id
            . ':rev:' . max(1, (int) ($revision_meta['version'] ?? 1))
            . ':' . md5(self::revision_signature($revision_meta));
    }

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     */
    private static function revision_signature(array $revision_meta): string
    {
        $signature = trim((string) ($revision_meta['signature'] ?? ''));
        if ($signature !== '') {
            return $signature;
        }

        return implode('|', [
            (int) ($revision_meta['exam_id'] ?? 0),
            max(1, (int) ($revision_meta['version'] ?? 1)),
            (string) ($revision_meta['invalidated_at'] ?? ''),
        ]);
    }

    /**
     * @param mixed $items
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_items($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = $item;
        }

        return array_values($normalized);
    }

    /**
     * @return Redis|null
     */
    private static function delivery_redis(): ?Redis
    {
        if (self::$delivery_redis_connection_attempted) {
            return (self::$delivery_redis instanceof Redis) ? self::$delivery_redis : null;
        }

        self::$delivery_redis_connection_attempted = true;
        self::$delivery_redis = false;
        self::$delivery_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$delivery_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::delivery_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::DELIVERY_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::DELIVERY_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::DELIVERY_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::DELIVERY_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::DELIVERY_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis exam delivery gagal.');
            }

            self::$delivery_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$delivery_redis_last_connection_error = 'Koneksi Redis exam delivery gagal: ' . $throwable->getMessage();
            self::$delivery_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function delivery_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::DELIVERY_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::DELIVERY_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::DELIVERY_REDIS_DEFAULT_PORT;
        }

        $database = (int) self::constant_scalar('CBT_RUNTIME_REDIS_DB', -1);
        if ($database < 0) {
            $database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::DELIVERY_REDIS_DEFAULT_DATABASE);
        }
        if ($database < 0) {
            $database = self::DELIVERY_REDIS_DEFAULT_DATABASE;
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $timeout = (float) self::constant_scalar('CBT_RUNTIME_REDIS_TIMEOUT', self::DELIVERY_REDIS_TIMEOUT);
        if ($timeout <= 0) {
            $timeout = self::DELIVERY_REDIS_TIMEOUT;
        }

        $scheme = 'tcp';
        if (str_starts_with($host, '/')) {
            $scheme = 'unix';
        }

        return [
            'host' => $host !== '' ? $host : self::DELIVERY_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => $database,
            'password' => $password,
            'timeout' => $timeout,
            'scheme' => $scheme,
        ];
    }

    private static function constant_scalar(string $name, $default)
    {
        if (defined($name)) {
            return constant($name);
        }

        return $default;
    }
}
