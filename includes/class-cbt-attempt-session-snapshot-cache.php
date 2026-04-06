<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

class CBT_Attempt_Session_Snapshot_Cache
{
    private const SNAPSHOT_REDIS_TTL_SECONDS = 44100;
    private const SNAPSHOT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const SNAPSHOT_REDIS_DEFAULT_PORT = 6379;
    private const SNAPSHOT_REDIS_DEFAULT_DATABASE = 2;
    private const SNAPSHOT_REDIS_PREFIX = 'cbt_attempt_session:';
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
     * @return array{
     *   attempt_id:int,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   storage_key:string,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_status:string,
     *   snapshot_message:string,
     *   snapshot_payload_bytes:int,
     *   snapshot_ttl_seconds:int,
     *   question_count:int,
     *   question_order_signature:string,
     *   status:string
     * }
     */
    public static function get_attempt_snapshot_diagnostics(int $attempt_id): array
    {
        $attempt_id = absint($attempt_id);
        $settings = self::snapshot_redis_settings();
        $storage_key = $attempt_id > 0 ? self::storage_key($attempt_id) : '';
        $redis = self::snapshot_redis();
        if ($attempt_id <= 0) {
            return [
                'attempt_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'idle',
                'snapshot_message' => 'Attempt ID wajib diisi untuk memeriksa session snapshot.',
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'question_order_signature' => '',
                'status' => '',
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'attempt_id' => $attempt_id,
                'redis_available' => false,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Redis attempt session snapshot tidak tersedia.',
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'question_order_signature' => '',
                'status' => '',
            ];
        }

        $snapshot_exists = false;
        $snapshot_valid = false;
        $snapshot_payload_bytes = 0;
        $snapshot_ttl_seconds = -2;
        $question_count = 0;
        $question_order_signature = '';
        $status = '';
        $snapshot_status = 'miss';
        $snapshot_message = 'Session snapshot belum ada.';

        $raw_payload = $redis->get($storage_key);
        if (is_string($raw_payload) && trim($raw_payload) !== '') {
            $snapshot_exists = true;
            $snapshot_payload_bytes = strlen($raw_payload);
            if (method_exists($redis, 'ttl')) {
                $snapshot_ttl_seconds = (int) $redis->ttl($storage_key);
            }

            $decoded = json_decode($raw_payload, true);
            if (is_array($decoded)) {
                $normalized = self::normalize_snapshot_payload($decoded);
                $snapshot_valid = (int) ($normalized['attempt_id'] ?? 0) === $attempt_id
                    && (int) ($normalized['exam_id'] ?? 0) > 0
                    && (int) ($normalized['student_id'] ?? 0) > 0;
                $question_count = (int) ($normalized['question_count'] ?? 0);
                $question_order_signature = (string) ($normalized['question_order_signature'] ?? '');
                $status = (string) ($normalized['status'] ?? '');
            }
        }

        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Attempt session snapshot siap dipakai untuk GET /session.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Attempt session snapshot ditemukan tetapi payload tidak valid.';
        }

        return [
            'attempt_id' => $attempt_id,
            'redis_available' => true,
            'redis_error' => self::$snapshot_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_message' => $snapshot_message,
            'snapshot_payload_bytes' => $snapshot_payload_bytes,
            'snapshot_ttl_seconds' => $snapshot_ttl_seconds,
            'question_count' => $question_count,
            'question_order_signature' => $question_order_signature,
            'status' => $status,
        ];
    }

    /**
     * @param callable(int):array<string,mixed> $producer
     * @return array<string,mixed>
     */
    public static function get_attempt_snapshot(int $attempt_id, callable $producer): array
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $payload = self::read_attempt_snapshot($attempt_id, $redis_available);
        if ($payload !== null) {
            return $payload;
        }

        $payload = self::normalize_snapshot_payload($producer($attempt_id));
        if ($redis_available) {
            self::write_attempt_snapshot($attempt_id, $payload);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function write_attempt_snapshot(int $attempt_id, array $payload): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $normalized = self::normalize_snapshot_payload($payload);
        if ((int) ($normalized['attempt_id'] ?? 0) <= 0) {
            $normalized['attempt_id'] = $attempt_id;
        }
        if ((int) ($normalized['attempt_id'] ?? 0) !== $attempt_id) {
            return;
        }

        $encoded_payload = wp_json_encode($normalized);
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return;
        }

        $redis->setEx(self::storage_key($attempt_id), self::SNAPSHOT_REDIS_TTL_SECONDS, $encoded_payload);
    }

    public static function update_attempt_status(int $attempt_id, string $status): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $payload = self::read_attempt_snapshot($attempt_id, $redis_available);
        if (!$redis_available || !is_array($payload) || empty($payload)) {
            return;
        }

        $payload['status'] = sanitize_key($status);
        self::write_attempt_snapshot($attempt_id, $payload);
    }

    public static function clear_attempt_snapshot(int $attempt_id): int
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return 0;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        return (int) $redis->del(self::storage_key($attempt_id));
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_attempt_snapshot(int $attempt_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $storage_key = self::storage_key($attempt_id);
        $raw_payload = $redis->get($storage_key);
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del($storage_key);
            return null;
        }

        $payload = self::normalize_snapshot_payload($decoded);
        if (
            (int) ($payload['attempt_id'] ?? 0) !== $attempt_id
            || (int) ($payload['exam_id'] ?? 0) <= 0
            || (int) ($payload['student_id'] ?? 0) <= 0
        ) {
            $redis->del($storage_key);
            return null;
        }

        $redis->expire($storage_key, self::SNAPSHOT_REDIS_TTL_SECONDS);
        return $payload;
    }

    private static function storage_key(int $attempt_id): string
    {
        return self::SNAPSHOT_REDIS_PREFIX . 'attempt:' . $attempt_id;
    }

    /**
     * @param mixed $payload
     * @return array<string,mixed>
     */
    private static function normalize_snapshot_payload($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        return [
            'attempt_id' => absint($payload['attempt_id'] ?? 0),
            'exam_id' => absint($payload['exam_id'] ?? 0),
            'student_id' => absint($payload['student_id'] ?? 0),
            'status' => sanitize_key((string) ($payload['status'] ?? '')),
            'started_at' => is_scalar($payload['started_at'] ?? null) ? (string) $payload['started_at'] : '',
            'duration_minutes' => max(0, (int) ($payload['duration_minutes'] ?? 0)),
            'extra_time_minutes' => max(0, (int) ($payload['extra_time_minutes'] ?? 0)),
            'question_count' => max(0, (int) ($payload['question_count'] ?? 0)),
            'question_order_signature' => is_scalar($payload['question_order_signature'] ?? null)
                ? (string) $payload['question_order_signature']
                : '',
            'show_student_result' => !empty($payload['show_student_result']) ? 1 : 0,
            'enable_calculator' => !empty($payload['enable_calculator']) ? 1 : 0,
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

            if (($config['auth'] ?? '') !== '') {
                $redis->auth((string) $config['auth']);
            }

            $redis->select((int) ($config['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE));
            self::$snapshot_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$snapshot_redis_last_connection_error = $throwable->getMessage();
            self::$snapshot_redis = false;
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function snapshot_redis_settings(): array
    {
        $settings = defined('CBT_RUNTIME_SETTINGS') ? constant('CBT_RUNTIME_SETTINGS') : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'scheme' => (string) ($settings['scheme'] ?? ''),
            'host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
            'port' => (int) ($settings['port'] ?? self::SNAPSHOT_REDIS_DEFAULT_PORT),
            'database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
            'timeout' => (float) ($settings['timeout'] ?? self::SNAPSHOT_REDIS_TIMEOUT),
            'auth' => (string) ($settings['auth'] ?? ''),
        ];
    }
}
