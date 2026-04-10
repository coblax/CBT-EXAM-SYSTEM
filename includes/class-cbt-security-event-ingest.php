<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Security_Live_Counters')) {
    require_once __DIR__ . '/class-cbt-security-live-counters.php';
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Security_Event_Ingest
{
    private const SETUP_SECURITY_OPTION = 'cbt_setup_security';
    private const FEATURE_FLAG_KEY = 'security_redis_first_ingest';
    private const CRON_HOOK = 'cbt_security_event_ingest_flush_tick';
    private const CRON_SCHEDULE = 'cbt_security_event_ingest_every_15_seconds';
    private const LOCK_KEY = 'security_event_ingest_flush';
    private const LOCK_TTL = 20;
    private const DEFAULT_BATCH_SIZE = 250;
    private const DEFAULT_BUDGET_SECONDS = 2.0;
    private const MICRO_DRAIN_BATCH_SIZE = 50;
    private const MICRO_DRAIN_BUDGET_SECONDS = 0.25;
    private const RETRY_LIMIT = 5;
    private const STREAM_KEY = 'cbt_security_ingest:events';
    private const DEAD_STREAM_KEY = 'cbt_security_ingest:dead';
    private const META_HASH_KEY = 'cbt_security_ingest:meta';
    private const RETRY_HASH_KEY = 'cbt_security_ingest:retries';
    private const STATUS_OPTION_KEY = 'cbt_security_ingest_status';
    private const CURSOR_OPTION_KEY = 'cbt_security_ingest_last_stream_id';

    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        add_action(self::CRON_HOOK, [self::class, 'process_flush_tick']);

        if (self::is_feature_enabled()) {
            self::ensure_worker_scheduled();
        } elseif (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    public static function activate(): void
    {
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        if (self::is_feature_enabled()) {
            self::ensure_worker_scheduled();
        }
    }

    public static function deactivate(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @param array<string,mixed> $schedules
     * @return array<string,mixed>
     */
    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => 15,
                'display' => 'CBT Security Event Ingest Every 15 Seconds',
            ];
        }

        return $schedules;
    }

    public static function is_feature_enabled(): bool
    {
        $raw = get_option(self::SETUP_SECURITY_OPTION, []);
        if (!is_array($raw)) {
            return false;
        }

        return !empty($raw[self::FEATURE_FLAG_KEY]);
    }

    public static function is_available(): bool
    {
        if (!self::is_feature_enabled()) {
            return false;
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        return method_exists($redis, 'xAdd') && method_exists($redis, 'xRange');
    }

    public static function supports_streams(): bool
    {
        $redis = self::redis();
        return $redis instanceof Redis && method_exists($redis, 'xAdd') && method_exists($redis, 'xRange');
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function enqueue_event_payload(array $payload): bool
    {
        if (!self::is_available()) {
            return false;
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $ingest_id = self::generate_ingest_id();
        $payload['ingest_id'] = $ingest_id;
        $payload_json = wp_json_encode($payload);
        if (!is_string($payload_json) || $payload_json === '') {
            return false;
        }

        $occurred_at = sanitize_text_field((string) ($payload['occurred_at'] ?? current_time('mysql')));
        $event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
        $attempt_id = absint($payload['attempt_id'] ?? 0);
        $exam_id = absint($payload['exam_id'] ?? 0);
        $student_id = absint($payload['student_id'] ?? 0);

        try {
            if (method_exists($redis, 'multi')) {
                $redis->multi();
            } else {
                $redis->pipeline();
            }

            $redis->xAdd(self::STREAM_KEY, '*', [
                'ingest_id' => $ingest_id,
                'attempt_id' => (string) $attempt_id,
                'exam_id' => (string) $exam_id,
                'student_id' => (string) $student_id,
                'event_type' => $event_type,
                'occurred_at' => $occurred_at,
                'payload_json' => $payload_json,
            ]);
            $redis->hMSet(self::META_HASH_KEY, [
                'last_enqueue_at' => $occurred_at,
                'last_ingest_id' => $ingest_id,
                'feature_enabled' => self::is_feature_enabled() ? '1' : '0',
            ]);
            $results = $redis->exec();
        } catch (Throwable $throwable) {
            self::update_status_snapshot([
                'last_enqueue_status' => 'failed',
                'last_enqueue_error' => $throwable->getMessage(),
                'last_enqueue_at' => current_time('mysql'),
            ]);
            return false;
        }

        $stream_id = '';
        if (is_array($results) && isset($results[0]) && is_string($results[0]) && $results[0] !== '') {
            $stream_id = (string) $results[0];
        }

        if ($stream_id === '') {
            self::update_status_snapshot([
                'last_enqueue_status' => 'failed',
                'last_enqueue_error' => 'Redis stream enqueue returned empty stream id.',
                'last_enqueue_at' => current_time('mysql'),
            ]);
            return false;
        }

        self::update_status_snapshot([
            'last_enqueue_status' => 'ok',
            'last_enqueue_error' => '',
            'last_enqueue_at' => current_time('mysql'),
            'last_stream_id' => $stream_id,
            'last_ingest_id' => $ingest_id,
        ]);

        return true;
    }

    /**
     * @return array{
     *   processed:int,
     *   persisted:int,
     *   dead_lettered:int,
     *   failed:int,
     *   backlog_count:int,
     *   dead_letter_count:int,
     *   last_stream_id:string,
     *   source:string
     * }
     */
    public static function flush_batch(
        int $limit = self::DEFAULT_BATCH_SIZE,
        float $budget_seconds = self::DEFAULT_BUDGET_SECONDS,
        string $source = 'manual'
    ): array {
        $result = [
            'processed' => 0,
            'persisted' => 0,
            'dead_lettered' => 0,
            'failed' => 0,
            'backlog_count' => 0,
            'dead_letter_count' => 0,
            'last_stream_id' => '',
            'source' => $source,
        ];

        if (!self::is_available()) {
            $result['backlog_count'] = 0;
            $result['dead_letter_count'] = 0;
            self::update_status_snapshot([
                'last_flush_status' => 'skipped',
                'last_flush_result' => 'redis_unavailable',
                'last_flush_at' => current_time('mysql'),
            ]);
            return $result;
        }

        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => $source])) {
            $result['backlog_count'] = self::get_backlog_count();
            $result['dead_letter_count'] = self::get_dead_letter_count();
            self::update_status_snapshot([
                'last_flush_status' => 'locked',
                'last_flush_result' => 'lock_busy',
                'last_flush_at' => current_time('mysql'),
            ]);
            return $result;
        }

        $started_at = microtime(true);
        try {
            $redis = self::redis();
            if (!$redis instanceof Redis) {
                return $result;
            }

            $entries = $redis->xRange(self::STREAM_KEY, '-', '+', max(1, $limit));
            if (!is_array($entries) || empty($entries)) {
                $result['backlog_count'] = self::get_backlog_count();
                $result['dead_letter_count'] = self::get_dead_letter_count();
                self::update_status_snapshot([
                    'last_flush_status' => 'idle',
                    'last_flush_result' => 'no_entries',
                    'last_flush_at' => current_time('mysql'),
                ]);
                return $result;
            }

            foreach ($entries as $stream_id => $fields) {
                if ((microtime(true) - $started_at) >= max(0.05, $budget_seconds)) {
                    break;
                }

                $payload_json = is_array($fields) ? (string) ($fields['payload_json'] ?? '') : '';
                $payload = json_decode($payload_json, true);
                if (!is_array($payload)) {
                    self::move_to_dead_letter((string) $stream_id, is_array($fields) ? $fields : [], 'invalid_payload_json', 0);
                    $redis->xDel(self::STREAM_KEY, [(string) $stream_id]);
                    $result['processed']++;
                    $result['dead_lettered']++;
                    $result['last_stream_id'] = (string) $stream_id;
                    update_option(self::CURSOR_OPTION_KEY, (string) $stream_id, false);
                    continue;
                }

                $retry_count = self::get_retry_count((string) $stream_id);
                $persisted = CBT_Security_Log::persist_ingested_event_payload($payload, (string) ($payload['ingest_id'] ?? ''));
                if ($persisted) {
                    self::clear_retry_count((string) $stream_id);
                    $redis->xDel(self::STREAM_KEY, [(string) $stream_id]);
                    $result['processed']++;
                    $result['persisted']++;
                    $result['last_stream_id'] = (string) $stream_id;
                    update_option(self::CURSOR_OPTION_KEY, (string) $stream_id, false);
                    continue;
                }

                $retry_count++;
                self::set_retry_count((string) $stream_id, $retry_count);
                $result['failed']++;

                if ($retry_count > self::RETRY_LIMIT) {
                    self::move_to_dead_letter((string) $stream_id, $fields, 'persist_failed', $retry_count);
                    $redis->xDel(self::STREAM_KEY, [(string) $stream_id]);
                    self::clear_retry_count((string) $stream_id);
                    $result['processed']++;
                    $result['dead_lettered']++;
                    $result['last_stream_id'] = (string) $stream_id;
                    update_option(self::CURSOR_OPTION_KEY, (string) $stream_id, false);
                }
            }
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }

        $result['backlog_count'] = self::get_backlog_count();
        $result['dead_letter_count'] = self::get_dead_letter_count();
        self::update_status_snapshot([
            'last_flush_status' => $result['failed'] > 0 ? 'partial' : 'ok',
            'last_flush_result' => wp_json_encode($result),
            'last_flush_at' => current_time('mysql'),
            'last_stream_id' => (string) ($result['last_stream_id'] ?? ''),
        ]);

        return $result;
    }

    public static function maybe_micro_drain(): array
    {
        if (!self::is_available()) {
            return [
                'drained' => 0,
                'skipped' => 1,
                'reason' => 'redis_unavailable',
            ];
        }

        if (self::get_backlog_count() <= self::MICRO_DRAIN_BATCH_SIZE) {
            return [
                'drained' => 0,
                'skipped' => 1,
                'reason' => 'backlog_small',
            ];
        }

        if (CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'micro_drain_probe'])) {
            CBT_Cache::release_lock(self::LOCK_KEY);
        } else {
            return [
                'drained' => 0,
                'skipped' => 1,
                'reason' => 'lock_busy',
            ];
        }

        $result = self::flush_batch(self::MICRO_DRAIN_BATCH_SIZE, self::MICRO_DRAIN_BUDGET_SECONDS, 'admin_micro_drain');

        return [
            'drained' => (int) ($result['persisted'] ?? 0),
            'skipped' => 0,
            'reason' => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_status_snapshot(): array
    {
        $status = get_option(self::STATUS_OPTION_KEY, []);
        if (!is_array($status)) {
            $status = [];
        }

        $live_mode = CBT_Security_Live_Counters::is_available() ? 'redis_live' : 'mysql_fallback';
        $ingest_mode = self::is_feature_enabled()
            ? (self::supports_streams() ? 'redis_first' : 'direct_mysql_fallback')
            : 'disabled';
        $next_flush_timestamp = self::next_flush_timestamp();

        return [
            'feature_enabled' => self::is_feature_enabled() ? 1 : 0,
            'stream_supported' => self::supports_streams() ? 1 : 0,
            'available' => self::is_available() ? 1 : 0,
            'mode' => $live_mode,
            'ingest_mode' => $ingest_mode,
            'status_label' => self::build_status_label($live_mode, $ingest_mode),
            'live_label' => self::build_live_label($live_mode),
            'ingest_label' => self::build_ingest_label($ingest_mode),
            'persist_label' => self::build_persist_label($ingest_mode),
            'backlog_count' => self::get_backlog_count(),
            'dead_letter_count' => self::get_dead_letter_count(),
            'oldest_pending_age_seconds' => self::get_oldest_pending_age_seconds(),
            'last_flush_at' => sanitize_text_field((string) ($status['last_flush_at'] ?? '')),
            'last_flush_status' => sanitize_key((string) ($status['last_flush_status'] ?? '')),
            'last_flush_result' => sanitize_text_field((string) ($status['last_flush_result'] ?? '')),
            'last_enqueue_at' => sanitize_text_field((string) ($status['last_enqueue_at'] ?? '')),
            'last_enqueue_status' => sanitize_key((string) ($status['last_enqueue_status'] ?? '')),
            'last_enqueue_error' => sanitize_text_field((string) ($status['last_enqueue_error'] ?? '')),
            'last_stream_id' => sanitize_text_field((string) ($status['last_stream_id'] ?? ((string) get_option(self::CURSOR_OPTION_KEY, '')))),
            'worker_scheduled' => self::is_worker_scheduled() ? 1 : 0,
            'next_flush_at' => $next_flush_timestamp > 0 ? self::format_timestamp($next_flush_timestamp) : '',
        ];
    }

    public static function process_flush_tick(): void
    {
        self::flush_batch(self::DEFAULT_BATCH_SIZE, self::DEFAULT_BUDGET_SECONDS, 'cron');
    }

    private static function ensure_worker_scheduled(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + 15, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    /**
     * @param array<string,mixed> $fields
     */
    private static function move_to_dead_letter(string $stream_id, array $fields, string $reason, int $retry_count): void
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || !method_exists($redis, 'xAdd')) {
            return;
        }

        try {
            $redis->xAdd(self::DEAD_STREAM_KEY, '*', [
                'stream_id' => $stream_id,
                'reason' => sanitize_key($reason),
                'retry_count' => (string) max(0, $retry_count),
                'failed_at' => current_time('mysql'),
                'payload_json' => (string) ($fields['payload_json'] ?? ''),
                'ingest_id' => (string) ($fields['ingest_id'] ?? ''),
                'event_type' => (string) ($fields['event_type'] ?? ''),
                'attempt_id' => (string) ($fields['attempt_id'] ?? '0'),
            ]);
        } catch (Throwable $throwable) {
            // Keep failure silent to avoid breaking the main flush flow.
        }
    }

    private static function get_backlog_count(): int
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || !method_exists($redis, 'xLen')) {
            return 0;
        }

        try {
            return max(0, (int) $redis->xLen(self::STREAM_KEY));
        } catch (Throwable $throwable) {
            return 0;
        }
    }

    private static function get_dead_letter_count(): int
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || !method_exists($redis, 'xLen')) {
            return 0;
        }

        try {
            return max(0, (int) $redis->xLen(self::DEAD_STREAM_KEY));
        } catch (Throwable $throwable) {
            return 0;
        }
    }

    private static function get_oldest_pending_age_seconds(): int
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || !method_exists($redis, 'xRange')) {
            return 0;
        }

        try {
            $entries = $redis->xRange(self::STREAM_KEY, '-', '+', 1);
        } catch (Throwable $throwable) {
            return 0;
        }

        if (!is_array($entries) || empty($entries)) {
            return 0;
        }

        $stream_id = (string) array_key_first($entries);
        if ($stream_id === '' || strpos($stream_id, '-') === false) {
            return 0;
        }

        $milliseconds = (int) strtok($stream_id, '-');
        if ($milliseconds <= 0) {
            return 0;
        }

        $age = (int) floor((microtime(true) * 1000 - $milliseconds) / 1000);
        return max(0, $age);
    }

    private static function get_retry_count(string $stream_id): int
    {
        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        try {
            $value = method_exists($redis, 'hGet')
                ? $redis->hGet(self::RETRY_HASH_KEY, $stream_id)
                : null;
        } catch (Throwable $throwable) {
            return 0;
        }

        return max(0, (int) $value);
    }

    private static function set_retry_count(string $stream_id, int $retry_count): void
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || !method_exists($redis, 'hSet')) {
            return;
        }

        try {
            $redis->hSet(self::RETRY_HASH_KEY, $stream_id, (string) max(0, $retry_count));
            $redis->expire(self::RETRY_HASH_KEY, CBT_Security_Live_Counters::storage_ttl());
        } catch (Throwable $throwable) {
            // Ignore retry persistence issues.
        }
    }

    private static function clear_retry_count(string $stream_id): void
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || !method_exists($redis, 'hDel')) {
            return;
        }

        try {
            $redis->hDel(self::RETRY_HASH_KEY, $stream_id);
        } catch (Throwable $throwable) {
            // Ignore cleanup issues.
        }
    }

    /**
     * @param array<string,mixed> $status
     */
    private static function update_status_snapshot(array $status): void
    {
        $current = get_option(self::STATUS_OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        $next = array_merge($current, $status);
        update_option(self::STATUS_OPTION_KEY, $next, false);
    }

    private static function build_status_label(string $live_mode, string $ingest_mode): string
    {
        return self::build_live_label($live_mode) . ' • '
            . self::build_ingest_label($ingest_mode) . ' • '
            . self::build_persist_label($ingest_mode);
    }

    private static function build_live_label(string $live_mode): string
    {
        if ($live_mode === 'redis_live') {
            return 'Live Redis';
        }

        return 'Live MySQL fallback';
    }

    private static function build_ingest_label(string $ingest_mode): string
    {
        if ($ingest_mode === 'redis_first') {
            return 'Ingest Redis-first';
        }

        if ($ingest_mode === 'direct_mysql_fallback') {
            return 'Ingest MySQL fallback';
        }

        return 'Ingest direct MySQL';
    }

    private static function build_persist_label(string $ingest_mode): string
    {
        if ($ingest_mode === 'redis_first') {
            return 'Persist batch MySQL';
        }

        return 'Persist direct MySQL';
    }

    private static function redis(): ?Redis
    {
        return CBT_Security_Live_Counters::redis_client();
    }

    private static function is_worker_scheduled(): bool
    {
        return self::next_flush_timestamp() > 0;
    }

    private static function next_flush_timestamp(): int
    {
        if (!function_exists('wp_next_scheduled')) {
            return 0;
        }

        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if (!is_numeric($timestamp)) {
            return 0;
        }

        return max(0, (int) $timestamp);
    }

    private static function format_timestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        if (function_exists('wp_date')) {
            return sanitize_text_field((string) wp_date('Y-m-d H:i:s', $timestamp));
        }

        return sanitize_text_field(gmdate('Y-m-d H:i:s', $timestamp));
    }

    private static function generate_ingest_id(): string
    {
        $time = (int) floor(microtime(true) * 1000);
        $time_bytes = pack('J', $time);
        if ($time_bytes === false || strlen($time_bytes) < 8) {
            $time_bytes = str_pad((string) $time, 8, '0', STR_PAD_LEFT);
        }

        try {
            $random = random_bytes(10);
        } catch (Throwable $throwable) {
            $random = wp_generate_uuid4();
        }

        $raw = substr((string) $time_bytes, -6) . substr((string) $random, 0, 10);
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $bits = '';
        $length = strlen($raw);
        for ($index = 0; $index < $length; $index++) {
            $bits .= str_pad(decbin(ord($raw[$index])), 8, '0', STR_PAD_LEFT);
        }

        $bits = str_pad(substr($bits, 0, 130), 130, '0', STR_PAD_RIGHT);
        $encoded = '';
        for ($offset = 0; $offset < 130; $offset += 5) {
            $encoded .= $alphabet[bindec(substr($bits, $offset, 5))];
        }

        return substr($encoded, 0, 26);
    }
}
