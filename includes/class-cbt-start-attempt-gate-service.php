<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Start_Attempt_Gate_Service
{
    private const GATE_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const GATE_REDIS_DEFAULT_PORT = 6379;
    private const GATE_REDIS_DEFAULT_DATABASE = 2;
    private const GATE_REDIS_PREFIX = 'cbt_start_attempt_gate:';
    private const GATE_REDIS_TIMEOUT = 1.5;
    private const BUCKET_CAPACITY = 50.0;
    private const BUCKET_WINDOW_SECONDS = 5.0;
    private const REFILL_PER_SECOND = 10.0;
    private const BUCKET_TTL_SECONDS = 120;
    private const QUEUE_TICKET_IDLE_TTL_SECONDS = 60;
    private const QUEUE_KEY_TTL_SECONDS = 180;
    private const DEFAULT_POLL_AFTER_MS = 1000;

    /** @var Redis|false|null */
    private static $gate_redis = null;
    /** @var bool */
    private static $gate_redis_connection_attempted = false;
    /** @var string */
    private static $gate_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::gate_redis() instanceof Redis;
    }

    /**
     * @return array{
     *   mode:string,
     *   queue_ticket:string,
     *   queue_position:int,
     *   estimated_wait_seconds:int,
     *   poll_after_ms:int,
     *   gate_capacity:int,
     *   gate_window_seconds:int,
     *   bucket_tokens:float,
     *   queue_depth:int
     * }
     */
    public static function evaluate_request(int $exam_id, int $user_id, string $queue_ticket = ''): array
    {
        $exam_id = absint($exam_id);
        $user_id = absint($user_id);
        $queue_ticket = self::normalize_queue_ticket($queue_ticket);

        $default = self::build_result('disabled', '', 0, self::BUCKET_CAPACITY, 0);
        if ($exam_id <= 0 || $user_id <= 0) {
            return $default;
        }

        $redis = self::gate_redis();
        if (!$redis instanceof Redis) {
            return $default;
        }

        $now = self::now();
        self::prune_stale_queue($redis, $exam_id, $now);

        $bucket = self::refill_bucket(self::read_bucket($redis, $exam_id), $now);
        $queue_members = self::get_queue_members($redis, $exam_id);
        $existing_ticket = self::get_user_ticket($redis, $exam_id, $user_id);
        if ($existing_ticket !== '') {
            $ticket_payload = self::read_ticket_payload($redis, $existing_ticket);
            if (
                !is_array($ticket_payload)
                || (int) ($ticket_payload['exam_id'] ?? 0) !== $exam_id
                || (int) ($ticket_payload['user_id'] ?? 0) !== $user_id
            ) {
                self::remove_ticket($redis, $exam_id, $existing_ticket, $user_id);
                $existing_ticket = '';
                $queue_members = self::get_queue_members($redis, $exam_id);
            }
        }

        $active_ticket = '';
        if ($queue_ticket !== '' && $existing_ticket !== '' && hash_equals($existing_ticket, $queue_ticket)) {
            $active_ticket = $existing_ticket;
        } elseif ($existing_ticket !== '') {
            $active_ticket = $existing_ticket;
        }

        if ($active_ticket !== '') {
            $queue_members = self::get_queue_members($redis, $exam_id);
            $queue_position = self::get_queue_position($active_ticket, $queue_members);
            if ($queue_position === 1 && $bucket['tokens'] >= 1.0) {
                $bucket['tokens'] = max(0.0, (float) $bucket['tokens'] - 1.0);
                self::write_bucket($redis, $exam_id, $bucket);
                self::remove_ticket($redis, $exam_id, $active_ticket, $user_id);

                return self::build_result('admitted', '', 0, (float) $bucket['tokens'], 0);
            }

            self::touch_ticket($redis, $exam_id, $active_ticket, $user_id, $now);
            self::write_bucket($redis, $exam_id, $bucket);

            return self::build_result(
                'queued',
                $active_ticket,
                $queue_position > 0 ? $queue_position : max(1, count($queue_members)),
                (float) $bucket['tokens'],
                count($queue_members)
            );
        }

        if (empty($queue_members) && $bucket['tokens'] >= 1.0) {
            $bucket['tokens'] = max(0.0, (float) $bucket['tokens'] - 1.0);
            self::write_bucket($redis, $exam_id, $bucket);

            return self::build_result('admitted', '', 0, (float) $bucket['tokens'], 0);
        }

        $ticket = self::generate_ticket();
        self::write_ticket($redis, $exam_id, $user_id, $ticket, $now);
        $queue_members = self::get_queue_members($redis, $exam_id);
        $queue_position = self::get_queue_position($ticket, $queue_members);
        self::write_bucket($redis, $exam_id, $bucket);

        return self::build_result(
            'queued',
            $ticket,
            $queue_position > 0 ? $queue_position : max(1, count($queue_members)),
            (float) $bucket['tokens'],
            count($queue_members)
        );
    }

    /**
     * @return array{
     *   redis_available:bool,
     *   redis_error:string,
     *   status_label:string,
     *   status_tone:string,
     *   status_slug:string,
     *   queue_depth:int,
     *   bucket_tokens:float,
     *   gate_capacity:int,
     *   gate_window_seconds:int,
     *   release_rate_label:string,
     *   oldest_wait_seconds:int
     * }
     */
    public static function get_exam_diagnostics(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        $redis = self::gate_redis();
        $default = [
            'redis_available' => $redis instanceof Redis,
            'redis_error' => self::$gate_redis_last_connection_error,
            'status_label' => 'DISABLED',
            'status_tone' => 'warning',
            'status_slug' => 'disabled',
            'queue_depth' => 0,
            'bucket_tokens' => self::BUCKET_CAPACITY,
            'gate_capacity' => (int) self::BUCKET_CAPACITY,
            'gate_window_seconds' => (int) self::BUCKET_WINDOW_SECONDS,
            'release_rate_label' => (int) self::BUCKET_CAPACITY . ' / ' . (int) self::BUCKET_WINDOW_SECONDS . ' detik',
            'oldest_wait_seconds' => 0,
        ];
        if ($exam_id <= 0 || !$redis instanceof Redis) {
            return $default;
        }

        $now = self::now();
        self::prune_stale_queue($redis, $exam_id, $now);
        $bucket = self::refill_bucket(self::read_bucket($redis, $exam_id), $now);
        self::write_bucket($redis, $exam_id, $bucket);
        $queue_members = self::get_queue_members($redis, $exam_id);

        $oldest_wait_seconds = 0;
        if (!empty($queue_members)) {
            $oldest_payload = self::read_ticket_payload($redis, (string) $queue_members[0]);
            if (is_array($oldest_payload)) {
                $created_at = (float) ($oldest_payload['created_at'] ?? $now);
                $oldest_wait_seconds = max(0, (int) floor($now - $created_at));
            }
        }

        return [
            'redis_available' => true,
            'redis_error' => self::$gate_redis_last_connection_error,
            'status_label' => !empty($queue_members) ? 'GATED' : 'OPEN',
            'status_tone' => !empty($queue_members) ? 'warning' : 'success',
            'status_slug' => !empty($queue_members) ? 'gated' : 'open',
            'queue_depth' => count($queue_members),
            'bucket_tokens' => round((float) $bucket['tokens'], 1),
            'gate_capacity' => (int) self::BUCKET_CAPACITY,
            'gate_window_seconds' => (int) self::BUCKET_WINDOW_SECONDS,
            'release_rate_label' => (int) self::BUCKET_CAPACITY . ' / ' . (int) self::BUCKET_WINDOW_SECONDS . ' detik',
            'oldest_wait_seconds' => $oldest_wait_seconds,
        ];
    }

    /**
     * @param array<string,mixed> $bucket
     * @return array{tokens:float,last_refill_at:float}
     */
    private static function refill_bucket(array $bucket, float $now): array
    {
        $tokens = isset($bucket['tokens']) ? (float) $bucket['tokens'] : self::BUCKET_CAPACITY;
        $last_refill_at = isset($bucket['last_refill_at']) ? (float) $bucket['last_refill_at'] : $now;

        if ($tokens < 0) {
            $tokens = 0.0;
        }
        if ($tokens > self::BUCKET_CAPACITY) {
            $tokens = self::BUCKET_CAPACITY;
        }
        if ($last_refill_at <= 0) {
            $last_refill_at = $now;
        }

        $elapsed = max(0.0, $now - $last_refill_at);
        if ($elapsed > 0) {
            $tokens = min(self::BUCKET_CAPACITY, $tokens + ($elapsed * self::REFILL_PER_SECOND));
        }

        return [
            'tokens' => $tokens,
            'last_refill_at' => $now,
        ];
    }

    /**
     * @return array{tokens:float,last_refill_at:float}
     */
    private static function read_bucket(Redis $redis, int $exam_id): array
    {
        $raw_payload = $redis->get(self::bucket_storage_key($exam_id));
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return [
                'tokens' => self::BUCKET_CAPACITY,
                'last_refill_at' => self::now(),
            ];
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            return [
                'tokens' => self::BUCKET_CAPACITY,
                'last_refill_at' => self::now(),
            ];
        }

        return [
            'tokens' => isset($decoded['tokens']) ? (float) $decoded['tokens'] : self::BUCKET_CAPACITY,
            'last_refill_at' => isset($decoded['last_refill_at']) ? (float) $decoded['last_refill_at'] : self::now(),
        ];
    }

    /**
     * @param array{tokens:float,last_refill_at:float} $bucket
     */
    private static function write_bucket(Redis $redis, int $exam_id, array $bucket): void
    {
        $encoded = wp_json_encode([
            'tokens' => round((float) ($bucket['tokens'] ?? self::BUCKET_CAPACITY), 4),
            'last_refill_at' => round((float) ($bucket['last_refill_at'] ?? self::now()), 4),
        ]);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::bucket_storage_key($exam_id), self::BUCKET_TTL_SECONDS, $encoded);
    }

    private static function write_ticket(Redis $redis, int $exam_id, int $user_id, string $ticket, float $now): void
    {
        $payload = wp_json_encode([
            'ticket' => $ticket,
            'exam_id' => $exam_id,
            'user_id' => $user_id,
            'created_at' => round($now, 4),
            'last_seen_at' => round($now, 4),
        ]);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        $redis->setEx(self::ticket_storage_key($ticket), self::QUEUE_TICKET_IDLE_TTL_SECONDS, $payload);
        $redis->setEx(self::user_ticket_storage_key($exam_id, $user_id), self::QUEUE_TICKET_IDLE_TTL_SECONDS, $ticket);
        $redis->zAdd(self::queue_storage_key($exam_id), self::queue_score($now), $ticket);
        $redis->expire(self::queue_storage_key($exam_id), self::QUEUE_KEY_TTL_SECONDS);
    }

    private static function touch_ticket(Redis $redis, int $exam_id, string $ticket, int $user_id, float $now): void
    {
        $payload = self::read_ticket_payload($redis, $ticket);
        if (!is_array($payload)) {
            return;
        }

        $payload['last_seen_at'] = round($now, 4);
        $payload['exam_id'] = $exam_id;
        $payload['user_id'] = $user_id;
        $encoded = wp_json_encode($payload);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::ticket_storage_key($ticket), self::QUEUE_TICKET_IDLE_TTL_SECONDS, $encoded);
        $redis->setEx(self::user_ticket_storage_key($exam_id, $user_id), self::QUEUE_TICKET_IDLE_TTL_SECONDS, $ticket);
        $redis->expire(self::queue_storage_key($exam_id), self::QUEUE_KEY_TTL_SECONDS);
    }

    private static function remove_ticket(Redis $redis, int $exam_id, string $ticket, int $user_id = 0): void
    {
        $payload = self::read_ticket_payload($redis, $ticket);
        if ($user_id <= 0 && is_array($payload)) {
            $user_id = (int) ($payload['user_id'] ?? 0);
        }

        $redis->zRem(self::queue_storage_key($exam_id), $ticket);
        $redis->del(self::ticket_storage_key($ticket));
        if ($user_id > 0) {
            $current_ticket = (string) $redis->get(self::user_ticket_storage_key($exam_id, $user_id));
            if ($current_ticket !== '' && hash_equals($current_ticket, $ticket)) {
                $redis->del(self::user_ticket_storage_key($exam_id, $user_id));
            }
        }
    }

    private static function prune_stale_queue(Redis $redis, int $exam_id, float $now): void
    {
        foreach (self::get_queue_members($redis, $exam_id) as $ticket) {
            $safe_ticket = self::normalize_queue_ticket((string) $ticket);
            if ($safe_ticket === '') {
                continue;
            }

            $payload = self::read_ticket_payload($redis, $safe_ticket);
            if (!is_array($payload)) {
                $redis->zRem(self::queue_storage_key($exam_id), $safe_ticket);
                continue;
            }

            $payload_exam_id = (int) ($payload['exam_id'] ?? 0);
            $payload_user_id = (int) ($payload['user_id'] ?? 0);
            $last_seen_at = isset($payload['last_seen_at']) ? (float) $payload['last_seen_at'] : 0.0;
            $expired = $payload_exam_id !== $exam_id
                || $payload_user_id <= 0
                || $last_seen_at <= 0
                || ($now - $last_seen_at) > self::QUEUE_TICKET_IDLE_TTL_SECONDS;

            if ($expired) {
                self::remove_ticket($redis, $exam_id, $safe_ticket, $payload_user_id);
            }
        }
    }

    /**
     * @return array<int,string>
     */
    private static function get_queue_members(Redis $redis, int $exam_id): array
    {
        $members = $redis->zRange(self::queue_storage_key($exam_id), 0, -1);
        if (!is_array($members)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($member): string {
            return self::normalize_queue_ticket((string) $member);
        }, $members), static function (string $ticket): bool {
            return $ticket !== '';
        }));
    }

    private static function get_queue_position(string $ticket, array $queue_members): int
    {
        $position = array_search($ticket, $queue_members, true);
        if ($position === false) {
            return 0;
        }

        return ((int) $position) + 1;
    }

    private static function get_user_ticket(Redis $redis, int $exam_id, int $user_id): string
    {
        $ticket = $redis->get(self::user_ticket_storage_key($exam_id, $user_id));
        if (!is_string($ticket)) {
            return '';
        }

        return self::normalize_queue_ticket($ticket);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_ticket_payload(Redis $redis, string $ticket): ?array
    {
        $raw_payload = $redis->get(self::ticket_storage_key($ticket));
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{
     *   mode:string,
     *   queue_ticket:string,
     *   queue_position:int,
     *   estimated_wait_seconds:int,
     *   poll_after_ms:int,
     *   gate_capacity:int,
     *   gate_window_seconds:int,
     *   bucket_tokens:float,
     *   queue_depth:int
     * }
     */
    private static function build_result(string $mode, string $ticket, int $position, float $bucket_tokens, int $queue_depth): array
    {
        $queue_depth = max($queue_depth, $position);
        $estimated_wait_seconds = 0;
        if ($mode === 'queued' && $position > 0) {
            $estimated_wait_seconds = max(1, (int) ceil($position / self::REFILL_PER_SECOND));
        }

        return [
            'mode' => sanitize_key($mode),
            'queue_ticket' => $ticket,
            'queue_position' => max(0, $position),
            'estimated_wait_seconds' => $estimated_wait_seconds,
            'poll_after_ms' => self::DEFAULT_POLL_AFTER_MS,
            'gate_capacity' => (int) self::BUCKET_CAPACITY,
            'gate_window_seconds' => (int) self::BUCKET_WINDOW_SECONDS,
            'bucket_tokens' => round(max(0.0, min(self::BUCKET_CAPACITY, $bucket_tokens)), 1),
            'queue_depth' => max(0, $queue_depth),
        ];
    }

    private static function gate_redis()
    {
        if (self::$gate_redis_connection_attempted) {
            return self::$gate_redis;
        }

        self::$gate_redis_connection_attempted = true;
        self::$gate_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$gate_redis_last_connection_error = 'Ekstensi Redis tidak tersedia.';
            self::$gate_redis = false;
            return self::$gate_redis;
        }

        $settings = self::gate_redis_settings();
        $host = (string) ($settings['host'] ?? self::GATE_REDIS_DEFAULT_HOST);
        $port = (int) ($settings['port'] ?? self::GATE_REDIS_DEFAULT_PORT);
        $database = (int) ($settings['database'] ?? self::GATE_REDIS_DEFAULT_DATABASE);
        $timeout = isset($settings['timeout']) ? (float) $settings['timeout'] : self::GATE_REDIS_TIMEOUT;
        $password = isset($settings['password']) ? (string) $settings['password'] : '';

        try {
            $redis = new Redis();
            $connected = $redis->connect($host, $port, $timeout);
            if (!$connected) {
                self::$gate_redis_last_connection_error = 'Gagal terhubung ke Redis gate start attempt.';
                self::$gate_redis = false;
                return self::$gate_redis;
            }

            if ($password !== '') {
                $redis->auth($password);
            }
            $redis->select($database);
            if (method_exists($redis, 'ping')) {
                $redis->ping();
            }

            self::$gate_redis = $redis;
            return self::$gate_redis;
        } catch (Throwable $throwable) {
            self::$gate_redis_last_connection_error = $throwable->getMessage();
            self::$gate_redis = false;
            return self::$gate_redis;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float}
     */
    private static function gate_redis_settings(): array
    {
        $host = defined('CBT_REDIS_HOST') ? (string) CBT_REDIS_HOST : self::GATE_REDIS_DEFAULT_HOST;
        $port = defined('CBT_REDIS_PORT') ? (int) CBT_REDIS_PORT : self::GATE_REDIS_DEFAULT_PORT;
        $database = defined('CBT_REDIS_DATABASE') ? (int) CBT_REDIS_DATABASE : self::GATE_REDIS_DEFAULT_DATABASE;
        $password = defined('CBT_REDIS_PASSWORD') ? (string) CBT_REDIS_PASSWORD : '';

        return [
            'host' => $host !== '' ? $host : self::GATE_REDIS_DEFAULT_HOST,
            'port' => $port > 0 ? $port : self::GATE_REDIS_DEFAULT_PORT,
            'database' => max(0, $database),
            'password' => $password,
            'timeout' => self::GATE_REDIS_TIMEOUT,
        ];
    }

    private static function bucket_storage_key(int $exam_id): string
    {
        return self::GATE_REDIS_PREFIX . 'bucket:exam:' . max(0, $exam_id);
    }

    private static function queue_storage_key(int $exam_id): string
    {
        return self::GATE_REDIS_PREFIX . 'queue:exam:' . max(0, $exam_id);
    }

    private static function ticket_storage_key(string $ticket): string
    {
        return self::GATE_REDIS_PREFIX . 'ticket:' . self::normalize_queue_ticket($ticket);
    }

    private static function user_ticket_storage_key(int $exam_id, int $user_id): string
    {
        return self::GATE_REDIS_PREFIX . 'user:exam:' . max(0, $exam_id) . ':user:' . max(0, $user_id);
    }

    private static function queue_score(float $now): float
    {
        return round($now * 1000, 0);
    }

    private static function normalize_queue_ticket(string $ticket): string
    {
        $ticket = trim($ticket);
        if ($ticket === '') {
            return '';
        }

        return preg_replace('/[^a-zA-Z0-9\\-]/', '', $ticket) ?: '';
    }

    private static function generate_ticket(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return (string) wp_generate_uuid4();
        }

        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable $throwable) {
            return md5(uniqid('cbt-start-queue-', true));
        }
    }

    private static function now(): float
    {
        if (isset($GLOBALS['cbt_test_start_attempt_gate_now'])) {
            return (float) $GLOBALS['cbt_test_start_attempt_gate_now'];
        }

        return microtime(true);
    }
}
