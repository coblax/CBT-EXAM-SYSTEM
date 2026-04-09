<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Security_Live_Counters
{
    private const LIVE_REDIS_TTL_SECONDS = 44100;
    private const LIVE_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const LIVE_REDIS_DEFAULT_PORT = 6379;
    private const LIVE_REDIS_DEFAULT_DATABASE = 2;
    private const LIVE_REDIS_PREFIX = 'cbt_security_live:';
    private const LIVE_REDIS_TIMEOUT = 1.5;

    /** @var Redis|false|null */
    private static $live_redis = null;
    /** @var bool */
    private static $live_redis_connection_attempted = false;
    /** @var string */
    private static $live_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::live_redis() instanceof Redis;
    }

    public static function redis_client(): ?Redis
    {
        return self::live_redis();
    }

    public static function storage_ttl(): int
    {
        return self::LIVE_REDIS_TTL_SECONDS;
    }

    public static function has_attempt_summary(int $attempt_id): bool
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return false;
        }

        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $summary = self::read_summary($redis, $attempt_id);
        return !empty($summary);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $summary_meta
     * @return array{count:int,risk_score:float}
     */
    public static function record_event(
        array $attempt,
        string $event_type,
        float $weight,
        string $label,
        string $occurred_at,
        array $summary_meta = []
    ): array {
        $attempt_id = absint($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        if ($attempt_id <= 0) {
            return ['count' => 0, 'risk_score' => 0.0];
        }

        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return ['count' => 0, 'risk_score' => 0.0];
        }

        $summary = self::read_summary($redis, $attempt_id);
        $events = self::read_events($redis, $attempt_id);
        $flags = self::read_flags($redis, $attempt_id);

        $summary = self::merge_summary($attempt, $summary, $summary_meta);
        $event_type = sanitize_key($event_type);
        $label = trim($label);
        $event = $events[$event_type] ?? [
            'event_type' => $event_type,
            'label' => $label !== '' ? $label : ucwords(str_replace('_', ' ', $event_type)),
            'count' => 0,
            'score' => 0.0,
            'last_at' => '',
        ];

        $event['count'] = max(0, (int) ($event['count'] ?? 0)) + 1;
        $event['score'] = max(0.0, (float) ($event['score'] ?? 0.0)) + max(0.0, $weight);
        $event['last_at'] = self::max_datetime((string) ($event['last_at'] ?? ''), $occurred_at);
        if ($label !== '') {
            $event['label'] = $label;
        }
        $events[$event_type] = $event;

        $summary['risk_score'] = max(0.0, (float) ($summary['risk_score'] ?? 0.0)) + max(0.0, $weight);
        $summary['event_total'] = max(0, (int) ($summary['event_total'] ?? 0)) + 1;
        if ($event_type === 'session_revoked') {
            $summary['session_revoked_count'] = max(0, (int) ($summary['session_revoked_count'] ?? 0)) + 1;
        }

        if (
            (string) ($summary['last_event_at'] ?? '') === ''
            || strcmp($occurred_at, (string) ($summary['last_event_at'] ?? '')) >= 0
        ) {
            $summary['last_event_at'] = $occurred_at;
            $summary['last_device_type'] = sanitize_key((string) ($summary_meta['last_device_type'] ?? ($summary['last_device_type'] ?? 'unknown')));
            $summary['last_device_label'] = sanitize_text_field((string) ($summary_meta['last_device_label'] ?? ($summary['last_device_label'] ?? 'Unknown')));
            $summary['last_device_summary'] = sanitize_text_field((string) ($summary_meta['last_device_summary'] ?? ($summary['last_device_summary'] ?? 'Unknown')));
        }

        self::write_summary($redis, $attempt_id, $summary);
        self::write_events($redis, $attempt_id, $events);
        if (!empty($flags)) {
            self::write_flags($redis, $attempt_id, $flags);
        } else {
            $redis->expire(self::flags_storage_key($attempt_id), self::LIVE_REDIS_TTL_SECONDS);
        }
        $redis->zAdd(self::active_attempts_key(), self::datetime_to_score($summary['last_event_at']), (string) $attempt_id);

        return [
            'count' => (int) $event['count'],
            'risk_score' => max(0.0, (float) ($summary['risk_score'] ?? 0.0)),
        ];
    }

    public static function promote_event(
        int $attempt_id,
        string $from_event_type,
        float $from_weight,
        string $to_event_type,
        float $to_weight,
        string $to_label
    ): void {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $summary = self::read_summary($redis, $attempt_id);
        $events = self::read_events($redis, $attempt_id);
        if (empty($summary) || empty($events)) {
            return;
        }

        $from_event_type = sanitize_key($from_event_type);
        $to_event_type = sanitize_key($to_event_type);
        if (!isset($events[$from_event_type])) {
            return;
        }

        $from_event = $events[$from_event_type];
        $from_count = max(0, (int) ($from_event['count'] ?? 0));
        if ($from_count <= 0) {
            return;
        }

        $from_event['count'] = $from_count - 1;
        $from_event['score'] = max(0.0, (float) ($from_event['score'] ?? 0.0) - max(0.0, $from_weight));
        $promoted_at = (string) ($from_event['last_at'] ?? '');

        if ((int) $from_event['count'] <= 0) {
            unset($events[$from_event_type]);
        } else {
            $events[$from_event_type] = $from_event;
        }

        $to_event = $events[$to_event_type] ?? [
            'event_type' => $to_event_type,
            'label' => trim($to_label) !== '' ? trim($to_label) : ucwords(str_replace('_', ' ', $to_event_type)),
            'count' => 0,
            'score' => 0.0,
            'last_at' => '',
        ];
        $to_event['count'] = max(0, (int) ($to_event['count'] ?? 0)) + 1;
        $to_event['score'] = max(0.0, (float) ($to_event['score'] ?? 0.0)) + max(0.0, $to_weight);
        $to_event['last_at'] = self::max_datetime((string) ($to_event['last_at'] ?? ''), $promoted_at);
        if (trim($to_label) !== '') {
            $to_event['label'] = trim($to_label);
        }
        $events[$to_event_type] = $to_event;

        $summary['risk_score'] = max(0.0, (float) ($summary['risk_score'] ?? 0.0) - max(0.0, $from_weight) + max(0.0, $to_weight));
        self::write_summary($redis, $attempt_id, $summary);
        self::write_events($redis, $attempt_id, $events);
        $redis->expire(self::flags_storage_key($attempt_id), self::LIVE_REDIS_TTL_SECONDS);
        $redis->zAdd(self::active_attempts_key(), self::datetime_to_score((string) ($summary['last_event_at'] ?? '')), (string) $attempt_id);
    }

    public static function has_derived_event(int $attempt_id, string $event_type): bool
    {
        $attempt_id = absint($attempt_id);
        $event_type = sanitize_key($event_type);
        if ($attempt_id <= 0 || $event_type === '') {
            return false;
        }

        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $flags = self::read_flags($redis, $attempt_id);
        return !empty($flags[$event_type]);
    }

    public static function mark_derived_event(int $attempt_id, string $event_type): void
    {
        $attempt_id = absint($attempt_id);
        $event_type = sanitize_key($event_type);
        if ($attempt_id <= 0 || $event_type === '') {
            return;
        }

        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $flags = self::read_flags($redis, $attempt_id);
        $flags[$event_type] = 1;
        self::write_flags($redis, $attempt_id, $flags);
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_active_attempt_payloads(array $filters = []): array
    {
        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        $attempt_ids = $redis->zRange(self::active_attempts_key(), 0, -1);
        if (!is_array($attempt_ids) || empty($attempt_ids)) {
            return [];
        }

        $payloads = [];
        foreach ($attempt_ids as $attempt_id_raw) {
            $attempt_id = absint($attempt_id_raw);
            if ($attempt_id <= 0) {
                continue;
            }

            $summary = self::read_summary($redis, $attempt_id);
            if (empty($summary)) {
                $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
                continue;
            }

            if ($teacher_id > 0 && (int) ($summary['teacher_id'] ?? 0) !== $teacher_id) {
                continue;
            }

            $events = self::read_events($redis, $attempt_id);
            $payloads[] = array_merge($summary, [
                'event_counts' => $events,
            ]);

            $redis->expire(self::summary_storage_key($attempt_id), self::LIVE_REDIS_TTL_SECONDS);
            $redis->expire(self::events_storage_key($attempt_id), self::LIVE_REDIS_TTL_SECONDS);
            $redis->expire(self::flags_storage_key($attempt_id), self::LIVE_REDIS_TTL_SECONDS);
            $redis->zAdd(self::active_attempts_key(), self::datetime_to_score((string) ($summary['last_event_at'] ?? '')), (string) $attempt_id);
        }

        return $payloads;
    }

    public static function clear_attempt(int $attempt_id): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::live_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $redis->del(
            self::summary_storage_key($attempt_id),
            self::events_storage_key($attempt_id),
            self::flags_storage_key($attempt_id)
        );
        $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
    }

    public static function clear_all(): void
    {
        $redis = self::live_redis();
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

                $redis->del(
                    self::summary_storage_key($attempt_id),
                    self::events_storage_key($attempt_id),
                    self::flags_storage_key($attempt_id)
                );
            }
        }

        $redis->del(self::active_attempts_key());
    }

    /**
     * @return array<string,mixed>
     */
    private static function merge_summary(array $attempt, array $existing, array $summary_meta): array
    {
        $attempt_id = absint($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        $base = [
            'attempt_id' => $attempt_id,
            'exam_id' => absint($attempt['exam_id'] ?? 0),
            'student_id' => absint($attempt['student_id'] ?? 0),
            'teacher_id' => absint($summary_meta['teacher_id'] ?? 0),
            'student_name' => sanitize_text_field((string) ($summary_meta['student_name'] ?? '')),
            'student_login' => sanitize_user((string) ($summary_meta['student_login'] ?? ''), true),
            'student_kode_kelas' => sanitize_text_field((string) ($summary_meta['student_kode_kelas'] ?? '')),
            'student_kode_ruang' => sanitize_text_field((string) ($summary_meta['student_kode_ruang'] ?? '')),
            'exam_title' => sanitize_text_field((string) ($summary_meta['exam_title'] ?? '')),
            'risk_score' => 0.0,
            'event_total' => 0,
            'session_revoked_count' => 0,
            'last_event_at' => '',
            'last_device_type' => sanitize_key((string) ($summary_meta['last_device_type'] ?? 'unknown')),
            'last_device_label' => sanitize_text_field((string) ($summary_meta['last_device_label'] ?? 'Unknown')),
            'last_device_summary' => sanitize_text_field((string) ($summary_meta['last_device_summary'] ?? 'Unknown')),
        ];

        $merged = array_merge($base, $existing);
        foreach (['teacher_id', 'student_name', 'student_login', 'student_kode_kelas', 'student_kode_ruang', 'exam_title'] as $field) {
            if (!empty($summary_meta[$field])) {
                $merged[$field] = $base[$field];
            }
        }
        foreach (['last_device_type', 'last_device_label', 'last_device_summary'] as $field) {
            if (!empty($summary_meta[$field])) {
                $merged[$field] = $base[$field];
            }
        }

        $merged['attempt_id'] = $attempt_id;
        $merged['exam_id'] = absint($merged['exam_id'] ?? $attempt['exam_id'] ?? 0);
        $merged['student_id'] = absint($merged['student_id'] ?? $attempt['student_id'] ?? 0);
        $merged['teacher_id'] = absint($merged['teacher_id'] ?? 0);
        $merged['risk_score'] = max(0.0, (float) ($merged['risk_score'] ?? 0.0));
        $merged['event_total'] = max(0, (int) ($merged['event_total'] ?? 0));
        $merged['session_revoked_count'] = max(0, (int) ($merged['session_revoked_count'] ?? 0));
        $merged['student_name'] = sanitize_text_field((string) ($merged['student_name'] ?? ''));
        $merged['student_login'] = sanitize_user((string) ($merged['student_login'] ?? ''), true);
        $merged['student_kode_kelas'] = sanitize_text_field((string) ($merged['student_kode_kelas'] ?? ''));
        $merged['student_kode_ruang'] = sanitize_text_field((string) ($merged['student_kode_ruang'] ?? ''));
        $merged['exam_title'] = sanitize_text_field((string) ($merged['exam_title'] ?? ''));
        $merged['last_event_at'] = trim((string) ($merged['last_event_at'] ?? ''));
        $merged['last_device_type'] = sanitize_key((string) ($merged['last_device_type'] ?? 'unknown'));
        $merged['last_device_label'] = sanitize_text_field((string) ($merged['last_device_label'] ?? 'Unknown'));
        $merged['last_device_summary'] = sanitize_text_field((string) ($merged['last_device_summary'] ?? 'Unknown'));

        return $merged;
    }

    /**
     * @return array<string,mixed>
     */
    private static function read_summary(Redis $redis, int $attempt_id): array
    {
        $raw = method_exists($redis, 'hGetAll')
            ? $redis->hGetAll(self::summary_storage_key($attempt_id))
            : [];
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        return [
            'attempt_id' => absint($raw['attempt_id'] ?? 0),
            'exam_id' => absint($raw['exam_id'] ?? 0),
            'student_id' => absint($raw['student_id'] ?? 0),
            'teacher_id' => absint($raw['teacher_id'] ?? 0),
            'student_name' => sanitize_text_field((string) ($raw['student_name'] ?? '')),
            'student_login' => sanitize_user((string) ($raw['student_login'] ?? ''), true),
            'student_kode_kelas' => sanitize_text_field((string) ($raw['student_kode_kelas'] ?? '')),
            'student_kode_ruang' => sanitize_text_field((string) ($raw['student_kode_ruang'] ?? '')),
            'exam_title' => sanitize_text_field((string) ($raw['exam_title'] ?? '')),
            'risk_score' => max(0.0, (float) ($raw['risk_score'] ?? 0.0)),
            'event_total' => max(0, (int) ($raw['event_total'] ?? 0)),
            'session_revoked_count' => max(0, (int) ($raw['session_revoked_count'] ?? 0)),
            'last_event_at' => trim((string) ($raw['last_event_at'] ?? '')),
            'last_device_type' => sanitize_key((string) ($raw['last_device_type'] ?? 'unknown')),
            'last_device_label' => sanitize_text_field((string) ($raw['last_device_label'] ?? 'Unknown')),
            'last_device_summary' => sanitize_text_field((string) ($raw['last_device_summary'] ?? 'Unknown')),
        ];
    }

    /**
     * @return array<string,array{event_type:string,label:string,count:int,score:float,last_at:string}>
     */
    private static function read_events(Redis $redis, int $attempt_id): array
    {
        $decoded = method_exists($redis, 'hGetAll')
            ? $redis->hGetAll(self::events_storage_key($attempt_id))
            : [];
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        $events = [];
        foreach ($decoded as $event_type => $item_json) {
            $item = self::decode_json((string) $item_json);
            if (!is_array($item)) {
                continue;
            }

            $safe_event_type = sanitize_key((string) ($item['event_type'] ?? $event_type));
            if ($safe_event_type === '') {
                continue;
            }

            $events[$safe_event_type] = [
                'event_type' => $safe_event_type,
                'label' => sanitize_text_field((string) ($item['label'] ?? '')),
                'count' => max(0, (int) ($item['count'] ?? 0)),
                'score' => max(0.0, (float) ($item['score'] ?? 0.0)),
                'last_at' => trim((string) ($item['last_at'] ?? '')),
            ];
        }

        return $events;
    }

    /**
     * @return array<string,int>
     */
    private static function read_flags(Redis $redis, int $attempt_id): array
    {
        $decoded = method_exists($redis, 'hGetAll')
            ? $redis->hGetAll(self::flags_storage_key($attempt_id))
            : [];
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        $flags = [];
        foreach ($decoded as $event_type => $value) {
            $safe_event_type = sanitize_key((string) $event_type);
            if ($safe_event_type === '') {
                continue;
            }

            $flags[$safe_event_type] = max(0, (int) $value);
        }

        return $flags;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function write_summary(Redis $redis, int $attempt_id, array $summary): void
    {
        if (!method_exists($redis, 'hMSet')) {
            return;
        }

        $payload = [];
        foreach ($summary as $field => $value) {
            if (is_scalar($value)) {
                $payload[(string) $field] = (string) $value;
            }
        }

        $redis->hMSet(self::summary_storage_key($attempt_id), $payload);
        $redis->expire(self::summary_storage_key($attempt_id), self::LIVE_REDIS_TTL_SECONDS);
    }

    /**
     * @param array<string,array<string,mixed>> $events
     */
    private static function write_events(Redis $redis, int $attempt_id, array $events): void
    {
        if (!method_exists($redis, 'hDel') || !method_exists($redis, 'hSet')) {
            return;
        }

        $storage_key = self::events_storage_key($attempt_id);
        $existing = method_exists($redis, 'hGetAll') ? $redis->hGetAll($storage_key) : [];
        if (is_array($existing)) {
            foreach (array_keys($existing) as $event_type) {
                if (!isset($events[$event_type])) {
                    $redis->hDel($storage_key, (string) $event_type);
                }
            }
        }

        foreach ($events as $event_type => $payload) {
            $encoded = wp_json_encode($payload);
            if (!is_string($encoded) || $encoded === '') {
                continue;
            }

            $redis->hSet($storage_key, (string) $event_type, $encoded);
        }

        $redis->expire($storage_key, self::LIVE_REDIS_TTL_SECONDS);
    }

    /**
     * @param array<string,int> $flags
     */
    private static function write_flags(Redis $redis, int $attempt_id, array $flags): void
    {
        if (!method_exists($redis, 'hDel') || !method_exists($redis, 'hSet')) {
            return;
        }

        $storage_key = self::flags_storage_key($attempt_id);
        $existing = method_exists($redis, 'hGetAll') ? $redis->hGetAll($storage_key) : [];
        if (is_array($existing)) {
            foreach (array_keys($existing) as $event_type) {
                if (!isset($flags[$event_type])) {
                    $redis->hDel($storage_key, (string) $event_type);
                }
            }
        }

        foreach ($flags as $event_type => $value) {
            $redis->hSet($storage_key, (string) $event_type, (string) max(0, (int) $value));
        }

        $redis->expire($storage_key, self::LIVE_REDIS_TTL_SECONDS);
    }

    private static function summary_storage_key(int $attempt_id): string
    {
        return self::LIVE_REDIS_PREFIX . 'attempt:' . $attempt_id . ':summary';
    }

    private static function events_storage_key(int $attempt_id): string
    {
        return self::LIVE_REDIS_PREFIX . 'attempt:' . $attempt_id . ':events';
    }

    private static function flags_storage_key(int $attempt_id): string
    {
        return self::LIVE_REDIS_PREFIX . 'attempt:' . $attempt_id . ':flags';
    }

    private static function active_attempts_key(): string
    {
        return self::LIVE_REDIS_PREFIX . 'attempts:active';
    }

    private static function datetime_to_score(string $occurred_at): int
    {
        $timestamp = strtotime($occurred_at);
        if ($timestamp === false || $timestamp <= 0) {
            return time();
        }

        return $timestamp;
    }

    private static function max_datetime(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return strcmp($left, $right) >= 0 ? $left : $right;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function decode_json(string $raw): ?array
    {
        if (trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return Redis|null
     */
    private static function live_redis(): ?Redis
    {
        if (self::$live_redis_connection_attempted) {
            return (self::$live_redis instanceof Redis) ? self::$live_redis : null;
        }

        self::$live_redis_connection_attempted = true;
        self::$live_redis = false;
        self::$live_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$live_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::live_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::LIVE_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::LIVE_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::LIVE_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::LIVE_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::LIVE_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis security live gagal.');
            }

            self::$live_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$live_redis_last_connection_error = 'Koneksi security live Redis gagal: ' . $throwable->getMessage();
            self::$live_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function live_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::LIVE_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::LIVE_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::LIVE_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::LIVE_REDIS_DEFAULT_DATABASE - 1);
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
            'host' => $host !== '' ? $host : self::LIVE_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::LIVE_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    /**
     * @return scalar|null
     */
    private static function constant_scalar(string $name, $default = null)
    {
        if (!defined($name)) {
            return $default;
        }

        $value = constant($name);
        return is_scalar($value) ? $value : $default;
    }
}
