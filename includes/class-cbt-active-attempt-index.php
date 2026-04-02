<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Active_Attempt_Index
{
    private const ACTIVE_ATTEMPT_REDIS_TTL_SECONDS = 44100;
    private const ACTIVE_ATTEMPT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const ACTIVE_ATTEMPT_REDIS_DEFAULT_PORT = 6379;
    private const ACTIVE_ATTEMPT_REDIS_DEFAULT_DATABASE = 2;
    private const ACTIVE_ATTEMPT_REDIS_PREFIX = 'cbt_active_attempt:';
    private const ACTIVE_ATTEMPT_REDIS_TIMEOUT = 1.5;

    /** @var Redis|false|null */
    private static $active_attempt_redis = null;
    /** @var bool */
    private static $active_attempt_redis_connection_attempted = false;
    /** @var string */
    private static $active_attempt_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::active_attempt_redis() instanceof Redis;
    }

    public static function get_active_attempt_id(int $user_id, int $exam_id): int
    {
        $payload = self::read_active_attempt_payload($user_id, $exam_id, $redis_available);
        if (!is_array($payload)) {
            return 0;
        }

        $attempt_id = (int) ($payload['attempt_id'] ?? 0);
        $status = (string) ($payload['status'] ?? '');
        if ($attempt_id <= 0 || $status !== 'in_progress') {
            if ($redis_available) {
                self::clear_active_attempt($user_id, $exam_id);
            }
            return 0;
        }

        return $attempt_id;
    }

    /**
     * @param array<string,mixed> $attempt_context
     */
    public static function set_active_attempt(array $attempt_context): void
    {
        $attempt_id = absint($attempt_context['id'] ?? $attempt_context['attempt_id'] ?? 0);
        $user_id = absint($attempt_context['student_id'] ?? 0);
        $exam_id = absint($attempt_context['exam_id'] ?? 0);
        $status = sanitize_key((string) ($attempt_context['status'] ?? ''));

        if ($attempt_id <= 0 || $user_id <= 0 || $exam_id <= 0 || $status !== 'in_progress') {
            return;
        }

        $redis = self::active_attempt_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = wp_json_encode([
            'attempt_id' => $attempt_id,
            'student_id' => $user_id,
            'exam_id' => $exam_id,
            'status' => 'in_progress',
        ]);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        $redis->setEx(
            self::active_attempt_storage_key($user_id, $exam_id),
            self::ACTIVE_ATTEMPT_REDIS_TTL_SECONDS,
            $payload
        );
    }

    public static function clear_active_attempt(int $user_id, int $exam_id, ?int $attempt_id = null): void
    {
        $user_id = absint($user_id);
        $exam_id = absint($exam_id);
        if ($user_id <= 0 || $exam_id <= 0) {
            return;
        }

        $redis = self::active_attempt_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $storage_key = self::active_attempt_storage_key($user_id, $exam_id);
        if ($attempt_id !== null && $attempt_id > 0) {
            $payload = self::read_active_attempt_payload($user_id, $exam_id);
            if (is_array($payload) && (int) ($payload['attempt_id'] ?? 0) !== absint($attempt_id)) {
                return;
            }
        }

        $redis->del($storage_key);
    }

    /**
     * @return array{attempt_id:int,student_id:int,exam_id:int,status:string}|null
     */
    private static function read_active_attempt_payload(int $user_id, int $exam_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $user_id = absint($user_id);
        $exam_id = absint($exam_id);
        if ($user_id <= 0 || $exam_id <= 0) {
            return null;
        }

        $redis = self::active_attempt_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $storage_key = self::active_attempt_storage_key($user_id, $exam_id);
        $raw_payload = $redis->get($storage_key);
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del($storage_key);
            return null;
        }

        $payload = [
            'attempt_id' => absint($decoded['attempt_id'] ?? 0),
            'student_id' => absint($decoded['student_id'] ?? 0),
            'exam_id' => absint($decoded['exam_id'] ?? 0),
            'status' => sanitize_key((string) ($decoded['status'] ?? '')),
        ];
        if (
            (int) ($payload['attempt_id'] ?? 0) <= 0
            || (int) ($payload['student_id'] ?? 0) !== $user_id
            || (int) ($payload['exam_id'] ?? 0) !== $exam_id
        ) {
            $redis->del($storage_key);
            return null;
        }

        $redis->expire($storage_key, self::ACTIVE_ATTEMPT_REDIS_TTL_SECONDS);
        return $payload;
    }

    /**
     * @return Redis|null
     */
    private static function active_attempt_redis(): ?Redis
    {
        if (self::$active_attempt_redis_connection_attempted) {
            return (self::$active_attempt_redis instanceof Redis) ? self::$active_attempt_redis : null;
        }

        self::$active_attempt_redis_connection_attempted = true;
        self::$active_attempt_redis = false;
        self::$active_attempt_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$active_attempt_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::active_attempt_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::ACTIVE_ATTEMPT_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::ACTIVE_ATTEMPT_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::ACTIVE_ATTEMPT_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::ACTIVE_ATTEMPT_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::ACTIVE_ATTEMPT_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis active attempt gagal.');
            }

            self::$active_attempt_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$active_attempt_redis_last_connection_error = 'Koneksi active attempt Redis gagal: ' . $throwable->getMessage();
            self::$active_attempt_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function active_attempt_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::ACTIVE_ATTEMPT_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::ACTIVE_ATTEMPT_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::ACTIVE_ATTEMPT_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::ACTIVE_ATTEMPT_REDIS_DEFAULT_DATABASE - 1);
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
            'host' => $host !== '' ? $host : self::ACTIVE_ATTEMPT_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::ACTIVE_ATTEMPT_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function active_attempt_storage_key(int $user_id, int $exam_id): string
    {
        return self::ACTIVE_ATTEMPT_REDIS_PREFIX
            . 'user:' . max(0, $user_id)
            . ':exam:' . max(0, $exam_id);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $constant_name, $default)
    {
        if (defined($constant_name)) {
            return constant($constant_name);
        }

        return $default;
    }
}
