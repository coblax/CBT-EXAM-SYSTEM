<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
    require_once __DIR__ . '/class-cbt-live-attempt-roster-index.php';
}

class CBT_Live_Proctoring_Presence
{
    private const PRESENCE_REDIS_TTL_SECONDS = 44100;
    private const PRESENCE_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const PRESENCE_REDIS_DEFAULT_PORT = 6379;
    private const PRESENCE_REDIS_DEFAULT_DATABASE = 2;
    private const PRESENCE_REDIS_PREFIX = 'cbt_presence_live:';
    private const PRESENCE_REDIS_TIMEOUT = 1.5;
    private const ONLINE_THRESHOLD_SECONDS = 45;
    private const STALE_THRESHOLD_SECONDS = 90;

    /** @var Redis|false|null */
    private static $presence_redis = null;
    /** @var bool */
    private static $presence_redis_connection_attempted = false;
    /** @var string */
    private static $presence_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::presence_redis() instanceof Redis;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $presence
     */
    public static function update_attempt_presence(array $attempt, array $presence): void
    {
        $attempt_id = absint($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        $exam_id = absint($attempt['exam_id'] ?? 0);
        $student_id = absint($attempt['student_id'] ?? 0);
        $status = strtolower(trim((string) ($attempt['status'] ?? '')));
        if ($attempt_id <= 0 || $exam_id <= 0 || $student_id <= 0 || $status !== 'in_progress') {
            return;
        }

        $redis = self::presence_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $current_payload = self::read_attempt_payload($redis, $attempt_id);
        $payload = [
            'attempt_id' => $attempt_id,
            'exam_id' => $exam_id,
            'student_id' => $student_id,
            'last_seen_at' => self::normalize_datetime_string(
                $presence['last_seen_at'] ?? ($current_payload['last_seen_at'] ?? current_time('mysql'))
            ),
            'connection_status' => self::normalize_presence_text(
                array_key_exists('connection_status', $presence)
                    ? $presence['connection_status']
                    : ($current_payload['connection_status'] ?? 'online')
            ),
            'visibility_state' => self::normalize_presence_text(
                array_key_exists('visibility_state', $presence)
                    ? $presence['visibility_state']
                    : ($current_payload['visibility_state'] ?? 'visible')
            ),
            'has_focus' => self::normalize_presence_flag(
                array_key_exists('has_focus', $presence)
                    ? $presence['has_focus']
                    : ($current_payload['has_focus'] ?? 1)
            ),
            'pending_sync_count' => self::normalize_presence_count(
                array_key_exists('pending_sync_count', $presence)
                    ? $presence['pending_sync_count']
                    : ($current_payload['pending_sync_count'] ?? 0)
            ),
            'heartbeat_lost_active' => self::normalize_presence_flag(
                array_key_exists('heartbeat_lost_active', $presence)
                    ? $presence['heartbeat_lost_active']
                    : ($current_payload['heartbeat_lost_active'] ?? 0)
            ),
            'risk_tone' => self::normalize_risk_tone(
                array_key_exists('risk_tone', $presence)
                    ? $presence['risk_tone']
                    : ($current_payload['risk_tone'] ?? '')
            ),
        ];

        $encoded = wp_json_encode($payload);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::attempt_presence_key($attempt_id), self::PRESENCE_REDIS_TTL_SECONDS, $encoded);
        $redis->zAdd(self::active_attempts_key(), self::datetime_to_score($payload['last_seen_at']), (string) $attempt_id);

        if (class_exists('CBT_Live_Attempt_Roster_Index')) {
            CBT_Live_Attempt_Roster_Index::sync_attempt($attempt, $payload);
        }
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     */
    public static function update_attempt_presence_from_context(array $attempt, array $context): void
    {
        $presence = [];
        foreach (['connection_status', 'visibility_state', 'has_focus', 'pending_sync_count', 'heartbeat_lost_active', 'risk_tone'] as $key) {
            if (array_key_exists($key, $context)) {
                $presence[$key] = $context[$key];
            }
        }

        if (empty($presence)) {
            return;
        }

        self::update_attempt_presence($attempt, $presence);
    }

    public static function sync_risk_tone(int $attempt_id, string $risk_tone): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::presence_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = self::read_attempt_payload($redis, $attempt_id);
        if (!is_array($payload)) {
            return;
        }

        $payload['risk_tone'] = self::normalize_risk_tone($risk_tone);
        $encoded = wp_json_encode($payload);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::attempt_presence_key($attempt_id), self::PRESENCE_REDIS_TTL_SECONDS, $encoded);
        $redis->zAdd(self::active_attempts_key(), self::datetime_to_score((string) ($payload['last_seen_at'] ?? '')), (string) $attempt_id);

        if (class_exists('CBT_Live_Attempt_Roster_Index')) {
            CBT_Live_Attempt_Roster_Index::sync_risk_summary($attempt_id, (string) ($payload['risk_tone'] ?? ''), null);
        }
    }

    /**
     * @param int[] $attempt_ids
     * @return array<int,array<string,mixed>>
     */
    public static function get_attempt_payloads(array $attempt_ids = []): array
    {
        $redis = self::presence_redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $normalized_attempt_ids = array_values(array_unique(array_filter(array_map('absint', $attempt_ids))));
        if (empty($normalized_attempt_ids)) {
            $normalized_attempt_ids = array_map('absint', (array) $redis->zRange(self::active_attempts_key(), 0, -1));
        }

        $payloads = [];
        foreach ($normalized_attempt_ids as $attempt_id) {
            if ($attempt_id <= 0) {
                continue;
            }

            $payload = self::read_attempt_payload($redis, $attempt_id);
            if (!is_array($payload)) {
                $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
                continue;
            }

            $payload['presence_status'] = self::derive_presence_status((string) ($payload['last_seen_at'] ?? ''));
            $payloads[$attempt_id] = $payload;

            $redis->expire(self::attempt_presence_key($attempt_id), self::PRESENCE_REDIS_TTL_SECONDS);
            $redis->zAdd(self::active_attempts_key(), self::datetime_to_score((string) ($payload['last_seen_at'] ?? '')), (string) $attempt_id);
        }

        return $payloads;
    }

    public static function clear_attempt(int $attempt_id): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::presence_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $redis->del(self::attempt_presence_key($attempt_id));
        $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
    }

    public static function clear_all(): void
    {
        $redis = self::presence_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $attempt_ids = $redis->zRange(self::active_attempts_key(), 0, -1);
        if (is_array($attempt_ids)) {
            foreach ($attempt_ids as $attempt_id_raw) {
                $attempt_id = absint($attempt_id_raw);
                if ($attempt_id <= 0) {
                    continue;
                }

                $redis->del(self::attempt_presence_key($attempt_id));
            }
        }

        $redis->del(self::active_attempts_key());
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_attempt_payload(Redis $redis, int $attempt_id): ?array
    {
        $raw_payload = $redis->get(self::attempt_presence_key($attempt_id));
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del(self::attempt_presence_key($attempt_id));
            return null;
        }

        $payload = [
            'attempt_id' => absint($decoded['attempt_id'] ?? 0),
            'exam_id' => absint($decoded['exam_id'] ?? 0),
            'student_id' => absint($decoded['student_id'] ?? 0),
            'last_seen_at' => self::normalize_datetime_string($decoded['last_seen_at'] ?? ''),
            'connection_status' => self::normalize_presence_text($decoded['connection_status'] ?? ''),
            'visibility_state' => self::normalize_presence_text($decoded['visibility_state'] ?? ''),
            'has_focus' => self::normalize_presence_flag($decoded['has_focus'] ?? null),
            'pending_sync_count' => self::normalize_presence_count($decoded['pending_sync_count'] ?? 0),
            'heartbeat_lost_active' => self::normalize_presence_flag($decoded['heartbeat_lost_active'] ?? 0),
            'risk_tone' => self::normalize_risk_tone($decoded['risk_tone'] ?? ''),
        ];

        if (
            (int) ($payload['attempt_id'] ?? 0) !== $attempt_id
            || (int) ($payload['exam_id'] ?? 0) <= 0
            || (int) ($payload['student_id'] ?? 0) <= 0
            || (string) ($payload['last_seen_at'] ?? '') === ''
        ) {
            $redis->del(self::attempt_presence_key($attempt_id));
            $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
            return null;
        }

        return $payload;
    }

    private static function derive_presence_status(string $last_seen_at): string
    {
        $last_seen_timestamp = self::datetime_to_timestamp($last_seen_at);
        if ($last_seen_timestamp <= 0) {
            return '';
        }

        $age = max(0, (int) current_time('timestamp') - $last_seen_timestamp);
        if ($age <= self::ONLINE_THRESHOLD_SECONDS) {
            return 'online';
        }

        if ($age <= self::STALE_THRESHOLD_SECONDS) {
            return 'stale';
        }

        return 'offline';
    }

    private static function normalize_presence_text($value): string
    {
        return strtolower(trim(sanitize_text_field((string) $value)));
    }

    private static function normalize_presence_flag($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private static function normalize_presence_count($value): int
    {
        return max(0, (int) $value);
    }

    private static function normalize_risk_tone($value): string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'high-risk') {
            return 'high-risk';
        }

        if ($normalized === 'watch') {
            return 'watch';
        }

        return '';
    }

    private static function normalize_datetime_string($value): string
    {
        $normalized = trim(sanitize_text_field((string) $value));
        if ($normalized === '') {
            return current_time('mysql');
        }

        $timestamp = self::datetime_to_timestamp($normalized);
        if ($timestamp <= 0) {
            return current_time('mysql');
        }

        return $normalized;
    }

    private static function datetime_to_timestamp(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : (int) $timestamp;
    }

    private static function datetime_to_score(string $value): float
    {
        return (float) max(0, self::datetime_to_timestamp($value));
    }

    /**
     * @return Redis|null
     */
    private static function presence_redis(): ?Redis
    {
        if (self::$presence_redis_connection_attempted) {
            return (self::$presence_redis instanceof Redis) ? self::$presence_redis : null;
        }

        self::$presence_redis_connection_attempted = true;
        self::$presence_redis = false;
        self::$presence_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$presence_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::presence_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::PRESENCE_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::PRESENCE_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::PRESENCE_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::PRESENCE_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::PRESENCE_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis presence gagal.');
            }

            self::$presence_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$presence_redis_last_connection_error = 'Koneksi presence Redis gagal: ' . $throwable->getMessage();
            self::$presence_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function presence_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::PRESENCE_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::PRESENCE_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::PRESENCE_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::PRESENCE_REDIS_DEFAULT_DATABASE - 1);
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
            'host' => $host !== '' ? $host : self::PRESENCE_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::PRESENCE_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function attempt_presence_key(int $attempt_id): string
    {
        return self::PRESENCE_REDIS_PREFIX . 'attempt:' . max(0, $attempt_id);
    }

    private static function active_attempts_key(): string
    {
        return self::PRESENCE_REDIS_PREFIX . 'active_attempts';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $name, $default)
    {
        return defined($name) ? constant($name) : $default;
    }
}
