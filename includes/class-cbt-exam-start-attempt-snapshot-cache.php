<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
    require_once __DIR__ . '/class-cbt-snapshot-auto-heal-queue-service.php';
}

class CBT_Exam_Start_Attempt_Snapshot_Cache
{
    private const SNAPSHOT_PAYLOAD_VERSION = 2;
    private const START_REDIS_TTL_SECONDS = 44100;
    private const START_EVENT_REDIS_TTL_SECONDS = 604800;
    private const START_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const START_REDIS_DEFAULT_PORT = 6379;
    private const START_REDIS_DEFAULT_DATABASE = 2;
    private const START_REDIS_PREFIX = 'cbt_exam_start_attempt:';
    private const START_EVENT_REDIS_PREFIX = 'cbt_exam_start_attempt_meta:';
    private const START_REDIS_TIMEOUT = 1.5;

    /** @var Redis|false|null */
    private static $start_snapshot_redis = null;
    /** @var bool */
    private static $start_snapshot_redis_connection_attempted = false;
    /** @var string */
    private static $start_snapshot_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::start_snapshot_redis() instanceof Redis;
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
     *   snapshot_miss_reason:string,
     *   snapshot_miss_reason_label:string,
     *   snapshot_message:string,
     *   repair_status:string,
     *   repair_message:string,
     *   snapshot_item_count:int,
     *   snapshot_payload_bytes:int,
     *   snapshot_ttl_seconds:int,
     *   question_count:int,
     *   duration_minutes:int,
     *   show_student_result:int,
     *   enable_calculator:int
     * }
     */
    public static function get_exam_snapshot_diagnostics(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        $revision_meta = self::exam_revision_meta($exam_id);
        $storage_key = ($exam_id > 0 && (int) ($revision_meta['exam_id'] ?? 0) === $exam_id)
            ? self::storage_key($exam_id, $revision_meta)
            : '';
        $settings = self::start_snapshot_redis_settings();
        $redis = self::start_snapshot_redis();
        $snapshot_exists = false;
        $snapshot_valid = false;
        $snapshot_item_count = 0;
        $snapshot_payload_bytes = 0;
        $snapshot_ttl_seconds = -2;
        $question_count = 0;
        $duration_minutes = 0;
        $show_student_result = 0;
        $enable_calculator = 0;
        $snapshot_status = 'idle';
        $snapshot_message = 'Masukkan Exam ID untuk memeriksa start snapshot.';

        if ($exam_id <= 0) {
            return [
                'exam_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$start_snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::START_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::START_REDIS_DEFAULT_DATABASE),
                'revision_meta' => $revision_meta,
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => $snapshot_status,
                'snapshot_miss_reason' => 'idle',
                'snapshot_miss_reason_label' => 'Belum dipilih',
                'snapshot_message' => $snapshot_message,
                'repair_status' => '',
                'repair_message' => '',
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'duration_minutes' => 0,
                'show_student_result' => 0,
                'enable_calculator' => 0,
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'exam_id' => $exam_id,
                'redis_available' => false,
                'redis_error' => self::$start_snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::START_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::START_REDIS_DEFAULT_DATABASE),
                'revision_meta' => $revision_meta,
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_miss_reason' => 'redis_unavailable',
                'snapshot_miss_reason_label' => 'Redis tidak tersedia',
                'snapshot_message' => 'Redis start snapshot tidak tersedia. Start attempt akan fallback ke jalur legacy.',
                'repair_status' => '',
                'repair_message' => '',
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'duration_minutes' => 0,
                'show_student_result' => 0,
                'enable_calculator' => 0,
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
                $stored_payload_version = max(0, (int) ($decoded['snapshot_payload_version'] ?? 0));
                $expected_signature = self::revision_signature($revision_meta);
                $question_ids = self::normalize_question_ids($decoded['question_ids'] ?? []);
                $snapshot_item_count = count($question_ids);
                $question_count = max(0, (int) ($decoded['question_count'] ?? $snapshot_item_count));
                $duration_minutes = max(0, (int) ($decoded['duration_minutes'] ?? 0));
                $show_student_result = !empty($decoded['show_student_result']) ? 1 : 0;
                $enable_calculator = !empty($decoded['enable_calculator']) ? 1 : 0;
                $snapshot_valid = $stored_exam_id === $exam_id
                    && $stored_signature === $expected_signature
                    && $stored_payload_version === self::SNAPSHOT_PAYLOAD_VERSION;
                if (!$snapshot_valid && $stored_payload_version !== self::SNAPSHOT_PAYLOAD_VERSION) {
                    self::write_start_event_marker($exam_id, 'invalid_payload');
                }
            } else {
                self::write_start_event_marker($exam_id, 'invalid_payload');
            }
        }

        $snapshot_miss_reason = '';
        $snapshot_miss_reason_label = '';
        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Start snapshot Redis siap dipakai untuk kontrak start_attempt.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $invalid_reason = self::detect_invalid_snapshot_reason($exam_id, $storage_key, $redis);
            $snapshot_miss_reason = (string) ($invalid_reason['code'] ?? '');
            $snapshot_miss_reason_label = (string) ($invalid_reason['label'] ?? '');
            $snapshot_message = self::build_invalid_snapshot_message($invalid_reason);
        } else {
            $snapshot_status = 'miss';
            $miss_reason = self::detect_snapshot_miss_reason($exam_id, $storage_key, $redis);
            $snapshot_miss_reason = (string) ($miss_reason['code'] ?? '');
            $snapshot_miss_reason_label = (string) ($miss_reason['label'] ?? '');
            $snapshot_message = self::build_snapshot_miss_message($miss_reason);
        }

        if (in_array($snapshot_status, ['miss', 'invalid'], true)) {
            self::maybe_enqueue_auto_heal($exam_id, $snapshot_miss_reason, 'diagnostics');
        }
        $queue_meta = self::build_queue_repair_meta($exam_id);

        return [
            'exam_id' => $exam_id,
            'redis_available' => true,
            'redis_error' => self::$start_snapshot_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::START_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::START_REDIS_DEFAULT_DATABASE),
            'revision_meta' => $revision_meta,
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_miss_reason' => $snapshot_miss_reason,
            'snapshot_miss_reason_label' => $snapshot_miss_reason_label,
            'snapshot_message' => $snapshot_message,
            'repair_status' => (string) ($queue_meta['status'] ?? ''),
            'repair_message' => (string) ($queue_meta['message'] ?? ''),
            'snapshot_item_count' => $snapshot_item_count,
            'snapshot_payload_bytes' => $snapshot_payload_bytes,
            'snapshot_ttl_seconds' => $snapshot_ttl_seconds,
            'question_count' => $question_count,
            'duration_minutes' => $duration_minutes,
            'show_student_result' => $show_student_result,
            'enable_calculator' => $enable_calculator,
        ];
    }

    /**
     * @return array{
     *   success:bool,
     *   status:string,
     *   message:string,
     *   diagnostics:array{
     *     exam_id:int,
     *     redis_available:bool,
     *     redis_error:string,
     *     redis_host:string,
     *     redis_database:int,
     *     revision_meta:array{exam_id:int,version:int,invalidated_at:string,signature:string},
     *     storage_key:string,
     *     snapshot_exists:bool,
     *     snapshot_valid:bool,
     *     snapshot_status:string,
     *     snapshot_miss_reason:string,
     *     snapshot_miss_reason_label:string,
     *     snapshot_message:string,
     *     repair_status:string,
     *     repair_message:string,
     *     snapshot_item_count:int,
     *     snapshot_payload_bytes:int,
     *     snapshot_ttl_seconds:int,
     *     question_count:int,
     *     duration_minutes:int,
     *     show_student_result:int,
     *     enable_calculator:int
     *   }
     * }
     */
    public static function maybe_auto_heal_snapshot(int $exam_id, string $source = 'admin'): array
    {
        unset($source);

        $exam_id = absint($exam_id);
        $diagnostics = self::get_exam_snapshot_diagnostics($exam_id);
        $default = [
            'success' => false,
            'status' => '',
            'message' => '',
            'diagnostics' => $diagnostics,
        ];

        if ($exam_id <= 0) {
            return $default;
        }

        $snapshot_status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss'));
        $miss_reason = sanitize_key((string) ($diagnostics['snapshot_miss_reason'] ?? ''));
        if (!in_array($snapshot_status, ['miss', 'invalid'], true)) {
            return $default;
        }

        if (!self::is_auto_heal_miss_reason($miss_reason)) {
            return $default;
        }

        if (!class_exists('CBT_REST') || !method_exists('CBT_REST', 'warm_exam_start_attempt_snapshot')) {
            return $default;
        }

        CBT_REST::warm_exam_start_attempt_snapshot($exam_id);
        $healed_diagnostics = self::get_exam_snapshot_diagnostics($exam_id);
        if (sanitize_key((string) ($healed_diagnostics['snapshot_status'] ?? 'miss')) !== 'ready') {
            return [
                'success' => false,
                'status' => '',
                'message' => '',
                'diagnostics' => $healed_diagnostics,
            ];
        }

        $message = self::build_auto_heal_repair_message($miss_reason);
        $healed_diagnostics['repair_status'] = 'auto_healed';
        $healed_diagnostics['repair_message'] = $message;

        return [
            'success' => true,
            'status' => 'auto_healed',
            'message' => $message,
            'diagnostics' => $healed_diagnostics,
        ];
    }

    /**
     * @param callable(int):array<string,mixed> $producer
     * @return array<string,mixed>
     */
    public static function get_exam_snapshot(int $exam_id, callable $producer): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        $payload = self::read_exam_snapshot($exam_id, $redis_available);
        if ($payload !== null) {
            return $payload;
        }

        $payload = self::normalize_snapshot_payload($producer($exam_id));
        if ($redis_available) {
            self::write_exam_snapshot($exam_id, $payload);
        }

        return $payload;
    }

    /**
     * @param callable(int):array<string,mixed> $producer
     */
    public static function warm_exam_snapshot(int $exam_id, callable $producer): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = self::normalize_snapshot_payload($producer($exam_id));
        self::write_exam_snapshot($exam_id, $payload);
    }

    public static function clear_exam_snapshot(int $exam_id): int
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return 0;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $keys = self::collect_exam_storage_keys($redis, $exam_id);
        if (empty($keys)) {
            self::write_start_event_marker($exam_id, 'manual_clear');
            return 0;
        }

        $deleted = (int) $redis->del(...$keys);
        self::write_start_event_marker($exam_id, 'manual_clear');

        return $deleted;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_exam_snapshot(int $exam_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $redis = self::start_snapshot_redis();
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
            self::write_start_event_marker($exam_id, 'invalid_payload');
            return null;
        }

        $stored_exam_id = absint($decoded['exam_id'] ?? 0);
        $stored_signature = is_scalar($decoded['revision_signature'] ?? null)
            ? (string) $decoded['revision_signature']
            : '';
        $stored_payload_version = max(0, (int) ($decoded['snapshot_payload_version'] ?? 0));
        $expected_signature = self::revision_signature($revision_meta);
        if (
            $stored_exam_id !== $exam_id
            || $stored_signature !== $expected_signature
            || $stored_payload_version !== self::SNAPSHOT_PAYLOAD_VERSION
        ) {
            $redis->del($storage_key);
            if ($stored_payload_version !== self::SNAPSHOT_PAYLOAD_VERSION) {
                self::write_start_event_marker($exam_id, 'invalid_payload');
            }
            return null;
        }

        $payload = self::normalize_snapshot_payload($decoded);
        $redis->expire($storage_key, self::START_REDIS_TTL_SECONDS);

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function write_exam_snapshot(int $exam_id, array $payload): void
    {
        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $encoded_payload = wp_json_encode(array_merge($payload, [
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
        ]));
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return;
        }

        $redis->setEx($storage_key, self::START_REDIS_TTL_SECONDS, $encoded_payload);
        self::write_start_event_marker($exam_id, 'written');
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

        $pattern = self::START_REDIS_PREFIX . 'exam:' . $exam_id . ':';
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
     * @return array{code:string,label:string}
     */
    private static function detect_snapshot_miss_reason(int $exam_id, string $current_storage_key, Redis $redis): array
    {
        $event = self::read_start_event_marker($exam_id, $redis);
        $event_code = sanitize_key((string) ($event['event'] ?? ''));

        if ($event_code === 'manual_clear') {
            return ['code' => 'manual_clear', 'label' => 'Dibersihkan manual'];
        }

        if ($event_code === 'invalid_payload') {
            return ['code' => 'invalid_payload', 'label' => 'Payload invalid'];
        }

        if (self::has_stale_revision_snapshot($exam_id, $current_storage_key, $redis)) {
            return ['code' => 'revision_changed', 'label' => 'Revision berubah'];
        }

        if ($event_code === 'written') {
            return ['code' => 'expired_or_evicted', 'label' => 'TTL habis / ter-evict'];
        }

        return ['code' => 'not_prepared', 'label' => 'Belum disiapkan'];
    }

    /**
     * @return array{code:string,label:string}
     */
    private static function detect_invalid_snapshot_reason(int $exam_id, string $current_storage_key, Redis $redis): array
    {
        $event = self::read_start_event_marker($exam_id, $redis);
        $event_code = sanitize_key((string) ($event['event'] ?? ''));

        if ($event_code === 'invalid_payload') {
            return ['code' => 'invalid_payload', 'label' => 'Payload invalid'];
        }

        if (self::has_stale_revision_snapshot($exam_id, $current_storage_key, $redis)) {
            return ['code' => 'revision_changed', 'label' => 'Revision berubah'];
        }

        return ['code' => 'revision_changed', 'label' => 'Revision berubah'];
    }

    /**
     * @param array{code:string,label:string} $miss_reason
     */
    private static function build_snapshot_miss_message(array $miss_reason): string
    {
        $code = sanitize_key((string) ($miss_reason['code'] ?? ''));

        if ($code === 'revision_changed') {
            return 'Start snapshot MISS karena revision exam berubah. Key revision sebelumnya tidak lagi dianggap current dan perlu dihangatkan ulang.';
        }

        if ($code === 'manual_clear') {
            return 'Start snapshot MISS karena dibersihkan manual dari panel admin. Start attempt berikutnya akan fallback lalu dapat dipanaskan ulang.';
        }

        if ($code === 'invalid_payload') {
            return 'Start snapshot MISS karena payload Redis sebelumnya tidak valid, sehingga key lama dibuang dan perlu dihydrate ulang.';
        }

        if ($code === 'expired_or_evicted') {
            return 'Start snapshot MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict. Start attempt berikutnya akan fallback lalu dapat dipanaskan ulang.';
        }

        return 'Start snapshot belum ada untuk revision exam ini. Start attempt akan fallback lalu dapat dipanaskan ulang.';
    }

    /**
     * @param array{code:string,label:string} $invalid_reason
     */
    private static function build_invalid_snapshot_message(array $invalid_reason): string
    {
        $code = sanitize_key((string) ($invalid_reason['code'] ?? ''));

        if ($code === 'invalid_payload') {
            return 'Start snapshot ditemukan tetapi payload Redis tidak valid dan akan diabaikan sampai dibangun ulang.';
        }

        return 'Start snapshot ditemukan tetapi signature/revision tidak cocok dan akan diabaikan.';
    }

    private static function is_auto_heal_miss_reason(string $reason): bool
    {
        return in_array(
            sanitize_key($reason),
            ['revision_changed', 'invalid_payload', 'expired_or_evicted'],
            true
        );
    }

    private static function maybe_enqueue_auto_heal(int $exam_id, string $reason, string $source = 'system'): void
    {
        $exam_id = absint($exam_id);
        $reason = sanitize_key($reason);
        if ($exam_id <= 0 || $reason === '' || !self::is_auto_heal_miss_reason($reason)) {
            return;
        }

        if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('start_exam', $exam_id, $reason, $source);
        }
    }

    /**
     * @return array{status:string,message:string}
     */
    private static function build_queue_repair_meta(int $exam_id): array
    {
        if ($exam_id <= 0 || !class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            return [
                'status' => '',
                'message' => '',
            ];
        }

        $queue_meta = CBT_Snapshot_Auto_Heal_Queue_Service::get_target_repair_state('start_exam', $exam_id);
        if (empty($queue_meta['queued'])) {
            return [
                'status' => '',
                'message' => '',
            ];
        }

        return [
            'status' => (string) ($queue_meta['status'] ?? 'queued_auto_heal'),
            'message' => (string) ($queue_meta['message'] ?? ''),
        ];
    }

    private static function build_auto_heal_repair_message(string $reason): string
    {
        if (sanitize_key($reason) === 'revision_changed') {
            return 'Dipulihkan otomatis dari revision exam terbaru';
        }

        return 'Dipulihkan otomatis dari payload start current';
    }

    private static function has_stale_revision_snapshot(int $exam_id, string $current_storage_key, Redis $redis): bool
    {
        $current_storage_key = trim($current_storage_key);
        $pattern = self::START_REDIS_PREFIX . 'exam:' . $exam_id . ':';
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

        if (isset($GLOBALS['cbt_test_redis_storage']) && is_array($GLOBALS['cbt_test_redis_storage'])) {
            foreach (array_keys($GLOBALS['cbt_test_redis_storage']) as $key) {
                if (is_string($key) && strpos($key, $pattern) === 0) {
                    $keys[$key] = $key;
                }
            }
        }

        foreach (array_values($keys) as $key) {
            if ($key !== '' && $key !== $current_storage_key) {
                return true;
            }
        }

        return false;
    }

    private static function write_start_event_marker(int $exam_id, string $event): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = wp_json_encode([
            'event' => sanitize_key($event),
            'recorded_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        $redis->setEx(
            self::start_event_storage_key($exam_id),
            self::START_EVENT_REDIS_TTL_SECONDS,
            $payload
        );
    }

    private static function read_start_event_marker(int $exam_id, Redis $redis): ?array
    {
        $raw_event = $redis->get(self::start_event_storage_key($exam_id));
        if (!is_string($raw_event) || trim($raw_event) === '') {
            return null;
        }

        $decoded = json_decode($raw_event, true);
        if (!is_array($decoded)) {
            $redis->del(self::start_event_storage_key($exam_id));
            return null;
        }

        return $decoded;
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
        return self::START_REDIS_PREFIX
            . 'exam:' . $exam_id
            . ':rev:' . max(1, (int) ($revision_meta['version'] ?? 1))
            . ':' . md5(self::revision_signature($revision_meta));
    }

    private static function start_event_storage_key(int $exam_id): string
    {
        return self::START_EVENT_REDIS_PREFIX . 'exam:' . max(0, $exam_id);
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
     * @param mixed $payload
     * @return array<string,mixed>
     */
    private static function normalize_snapshot_payload($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $normalized = [
            'exam_id' => absint($payload['exam_id'] ?? 0),
            'revision_signature' => is_scalar($payload['revision_signature'] ?? null)
                ? (string) $payload['revision_signature']
                : '',
            'question_ids' => self::normalize_question_ids($payload['question_ids'] ?? []),
            'question_count' => max(0, (int) ($payload['question_count'] ?? count((array) ($payload['question_ids'] ?? [])))),
            'question_number_map' => self::normalize_question_number_map($payload['question_number_map'] ?? []),
            'question_manifest' => self::normalize_question_manifest($payload['question_manifest'] ?? []),
            'randomize_questions' => !empty($payload['randomize_questions']) ? 1 : 0,
            'randomize_options' => !empty($payload['randomize_options']) ? 1 : 0,
            'duration_minutes' => max(0, (int) ($payload['duration_minutes'] ?? 0)),
            'show_student_result' => !empty($payload['show_student_result']) ? 1 : 0,
            'enable_calculator' => !empty($payload['enable_calculator']) ? 1 : 0,
            'option_randomization_tokens_by_question' => self::normalize_option_randomization_tokens_map(
                $payload['option_randomization_tokens_by_question'] ?? []
            ),
            'force_option_shuffle_question_ids' => self::normalize_question_ids($payload['force_option_shuffle_question_ids'] ?? []),
        ];

        return $normalized;
    }

    /**
     * @param mixed $manifest
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_question_manifest($manifest): array
    {
        if (!is_array($manifest)) {
            return [];
        }

        $normalized = [];
        foreach ($manifest as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question_id = absint($item['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $normalized_item = [
                'id' => $question_id,
                'question_type' => is_scalar($item['question_type'] ?? null) ? sanitize_key((string) $item['question_type']) : '',
                'updated_at' => is_scalar($item['updated_at'] ?? null) ? (string) $item['updated_at'] : '',
            ];
            if (array_key_exists('points', $item)) {
                $normalized_item['points'] = (float) ($item['points'] ?? 0);
            }
            $question_number = absint($item['question_number'] ?? 0);
            if ($question_number > 0) {
                $normalized_item['question_number'] = $question_number;
            }

            $normalized[] = $normalized_item;
        }

        return $normalized;
    }

    /**
     * @param mixed $question_ids
     * @return array<int,int>
     */
    private static function normalize_question_ids($question_ids): array
    {
        if (!is_array($question_ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
    }

    /**
     * @param mixed $question_number_map
     * @return array<int,int>
     */
    private static function normalize_question_number_map($question_number_map): array
    {
        if (!is_array($question_number_map)) {
            return [];
        }

        $normalized = [];
        foreach ($question_number_map as $question_id => $question_number) {
            $safe_question_id = (int) $question_id;
            $safe_question_number = (int) $question_number;
            if ($safe_question_id <= 0 || $safe_question_number <= 0) {
                continue;
            }

            $normalized[$safe_question_id] = $safe_question_number;
        }

        return $normalized;
    }

    /**
     * @param mixed $token_map
     * @return array<int,array<int,string>>
     */
    private static function normalize_option_randomization_tokens_map($token_map): array
    {
        if (!is_array($token_map)) {
            return [];
        }

        $normalized = [];
        foreach ($token_map as $question_id => $tokens) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || !is_array($tokens)) {
                continue;
            }

            $normalized_tokens = [];
            $seen_tokens = [];
            foreach ($tokens as $token) {
                if (!is_scalar($token)) {
                    continue;
                }

                $safe_token = trim((string) $token);
                if ($safe_token === '' || isset($seen_tokens[$safe_token])) {
                    continue;
                }

                $seen_tokens[$safe_token] = true;
                $normalized_tokens[] = $safe_token;
            }

            if (!empty($normalized_tokens)) {
                $normalized[$safe_question_id] = $normalized_tokens;
            }
        }

        return $normalized;
    }

    /**
     * @return Redis|null
     */
    private static function start_snapshot_redis(): ?Redis
    {
        if (self::$start_snapshot_redis_connection_attempted) {
            return (self::$start_snapshot_redis instanceof Redis) ? self::$start_snapshot_redis : null;
        }

        self::$start_snapshot_redis_connection_attempted = true;
        self::$start_snapshot_redis = false;
        self::$start_snapshot_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$start_snapshot_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::start_snapshot_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::START_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::START_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::START_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::START_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::START_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis start snapshot gagal.');
            }

            self::$start_snapshot_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$start_snapshot_redis_last_connection_error = 'Koneksi Redis start snapshot gagal: ' . $throwable->getMessage();
            self::$start_snapshot_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function start_snapshot_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::START_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::START_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::START_REDIS_DEFAULT_PORT;
        }

        $database = (int) self::constant_scalar('CBT_RUNTIME_REDIS_DB', -1);
        if ($database < 0) {
            $database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::START_REDIS_DEFAULT_DATABASE);
        }
        if ($database < 0) {
            $database = self::START_REDIS_DEFAULT_DATABASE;
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $timeout = (float) self::constant_scalar('CBT_RUNTIME_REDIS_TIMEOUT', self::START_REDIS_TIMEOUT);
        if ($timeout <= 0) {
            $timeout = self::START_REDIS_TIMEOUT;
        }

        $scheme = 'tcp';
        if (str_starts_with($host, '/')) {
            $scheme = 'unix';
        }

        return [
            'host' => $host !== '' ? $host : self::START_REDIS_DEFAULT_HOST,
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
