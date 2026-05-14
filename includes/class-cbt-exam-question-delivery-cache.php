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

class CBT_Exam_Question_Delivery_Cache
{
    private const SNAPSHOT_PAYLOAD_VERSION = 2;
    private const SENSITIVE_DELIVERY_KEYS = [
        'correct_text',
        'short_answer_correct_text',
        'is_correct',
        'correct_value',
        'correct_values',
        'correct_option_id',
        'correct_option_ids',
        'correct_option_ids_by_key',
        'correct_option_key',
        'correct_option_text',
        'correct_category_index',
        'correct_position',
        'ordering_correct_option_ids',
        'true_false_correct_value',
        'true_false_option_value_by_id',
        'short_answer_values',
        'true_false_matrix_answers',
        'matching_correct_option_ids_by_key',
        'cloze_dropdown_correct_option_ids_by_key',
        'categorization_correct_option_ids_by_key',
        'table_completion_answers_by_key',
    ];
    private const DIAGNOSTIC_PREVIEW_DEFAULT_PER_PAGE = 5;
    private const DELIVERY_REDIS_TTL_SECONDS = 44100;
    private const DELIVERY_EVENT_REDIS_TTL_SECONDS = 604800;
    private const DELIVERY_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const DELIVERY_REDIS_DEFAULT_PORT = 6379;
    private const DELIVERY_REDIS_DEFAULT_DATABASE = 2;
    private const DELIVERY_REDIS_PREFIX = 'cbt_exam_delivery:';
    private const DELIVERY_EVENT_REDIS_PREFIX = 'cbt_exam_delivery_meta:';
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
     *   snapshot_miss_reason:string,
     *   snapshot_miss_reason_label:string,
     *   snapshot_message:string,
     *   repair_status:string,
     *   repair_message:string,
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
                'snapshot_miss_reason' => 'idle',
                'snapshot_miss_reason_label' => 'Belum dipilih',
                'snapshot_message' => $snapshot_message,
                'repair_status' => '',
                'repair_message' => '',
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
                'snapshot_miss_reason' => 'redis_unavailable',
                'snapshot_miss_reason_label' => 'Redis tidak tersedia',
                'snapshot_message' => 'Redis exam delivery tidak tersedia. Jalur student akan fallback ke DB/cache biasa.',
                'repair_status' => '',
                'repair_message' => '',
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
                $stored_payload_version = max(0, (int) ($decoded['snapshot_payload_version'] ?? 0));
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
                $snapshot_valid = $stored_exam_id === $exam_id
                    && $stored_signature === $expected_signature
                    && $stored_payload_version === self::SNAPSHOT_PAYLOAD_VERSION;
                if (!$snapshot_valid && $stored_payload_version !== self::SNAPSHOT_PAYLOAD_VERSION) {
                    self::write_delivery_event_marker($exam_id, 'invalid_payload');
                }
            } else {
                self::write_delivery_event_marker($exam_id, 'invalid_payload');
            }
        }

        $snapshot_miss_reason = '';
        $snapshot_miss_reason_label = '';
        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Snapshot Redis siap dipakai untuk base payload student GET /questions.';
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
            'redis_error' => self::$delivery_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::DELIVERY_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::DELIVERY_REDIS_DEFAULT_DATABASE),
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
            'preview_current_page' => $preview_current_page,
            'preview_total_pages' => $preview_total_pages,
            'preview_per_page' => $preview_per_page,
            'preview_question_ids' => $preview_question_ids,
            'preview_items' => $preview_items,
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
     *     preview_current_page:int,
     *     preview_total_pages:int,
     *     preview_per_page:int,
     *     preview_question_ids:array<int,int>,
     *     preview_items:array<int,array{id:int,question_type:string,points:float,question_text_excerpt:string,option_count:int}>
     *   }
     * }
     */
    public static function maybe_auto_heal_snapshot(int $exam_id, string $source = 'admin'): array
    {
        $exam_id = absint($exam_id);
        $diagnostics = self::get_exam_payload_diagnostics($exam_id);
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

        if (!class_exists('CBT_REST') || !method_exists('CBT_REST', 'warm_exam_question_delivery_snapshot')) {
            return $default;
        }

        CBT_REST::warm_exam_question_delivery_snapshot($exam_id);
        $healed_diagnostics = self::get_exam_payload_diagnostics($exam_id);
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

    /**
     * @return array{
     *   success:bool,
     *   reason:string,
     *   exam_id:int,
     *   revision_meta:array{exam_id:int,version:int,invalidated_at:string,signature:string},
     *   revision_signature:string,
     *   storage_key:string,
     *   ttl_seconds:int,
     *   items:array<int,array<string,mixed>>
     * }
     */
    public static function read_current_exam_payload_envelope(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        $empty_revision = [
            'exam_id' => $exam_id,
            'version' => 0,
            'invalidated_at' => '',
            'signature' => '',
        ];
        $default = [
            'success' => false,
            'reason' => $exam_id > 0 ? 'snapshot_miss' : 'invalid_exam',
            'exam_id' => $exam_id,
            'revision_meta' => $empty_revision,
            'revision_signature' => '',
            'storage_key' => '',
            'ttl_seconds' => -2,
            'items' => [],
        ];
        if ($exam_id <= 0) {
            return $default;
        }

        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            $default['reason'] = 'redis_unavailable';
            return $default;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        $revision_signature = self::revision_signature($revision_meta);
        $default['revision_meta'] = $revision_meta;
        $default['revision_signature'] = $revision_signature;
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            $default['reason'] = 'revision_unavailable';
            return $default;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $default['storage_key'] = $storage_key;
        $raw_payload = $redis->get($storage_key);
        if (method_exists($redis, 'ttl')) {
            $default['ttl_seconds'] = (int) $redis->ttl($storage_key);
        }
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            $default['reason'] = 'snapshot_miss';
            return $default;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del($storage_key);
            self::write_delivery_event_marker($exam_id, 'invalid_payload');
            $default['reason'] = 'invalid_payload';
            return $default;
        }

        $stored_exam_id = absint($decoded['exam_id'] ?? 0);
        $stored_signature = is_scalar($decoded['revision_signature'] ?? null)
            ? (string) $decoded['revision_signature']
            : '';
        $stored_payload_version = max(0, (int) ($decoded['snapshot_payload_version'] ?? 0));
        if (
            $stored_exam_id !== $exam_id
            || $stored_signature !== $revision_signature
            || $stored_payload_version !== self::SNAPSHOT_PAYLOAD_VERSION
        ) {
            if ($stored_payload_version !== self::SNAPSHOT_PAYLOAD_VERSION) {
                self::write_delivery_event_marker($exam_id, 'invalid_payload');
            }
            $default['reason'] = 'snapshot_invalid';
            return $default;
        }

        $default['success'] = true;
        $default['reason'] = 'ready';
        $default['items'] = self::normalize_items($decoded['items'] ?? []);

        return $default;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    public static function write_current_exam_payload(int $exam_id, array $items, int $ttl_seconds = 0): bool
    {
        return self::write_exam_payload_with_ttl(
            $exam_id,
            self::normalize_items($items),
            $ttl_seconds > 0 ? $ttl_seconds : self::DELIVERY_REDIS_TTL_SECONDS
        );
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
        $deleted = 0;
        if (!empty($keys)) {
            $deleted = (int) $redis->del(...$keys);
        }
        self::write_delivery_event_marker($exam_id, 'manual_clear');

        return $deleted;
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
            self::write_delivery_event_marker($exam_id, 'invalid_payload');
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
                self::write_delivery_event_marker($exam_id, 'invalid_payload');
            }
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
        self::write_exam_payload_with_ttl($exam_id, $items, self::DELIVERY_REDIS_TTL_SECONDS);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function write_exam_payload_with_ttl(int $exam_id, array $items, int $ttl_seconds): bool
    {
        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return false;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $encoded_payload = wp_json_encode([
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
            'items' => array_values($items),
        ]);
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return false;
        }

        $written = (bool) $redis->setEx($storage_key, max(1, $ttl_seconds), $encoded_payload);
        if ($written) {
            self::write_delivery_event_marker($exam_id, 'written');
        }

        return $written;
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
     * @return array{code:string,label:string}
     */
    private static function detect_snapshot_miss_reason(int $exam_id, string $current_storage_key, Redis $redis): array
    {
        $event = self::read_delivery_event_marker($exam_id, $redis);
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
        $event = self::read_delivery_event_marker($exam_id, $redis);
        $event_code = sanitize_key((string) ($event['event'] ?? ''));

        if ($event_code === 'invalid_payload') {
            return ['code' => 'invalid_payload', 'label' => 'Payload invalid'];
        }

        if (self::has_stale_revision_snapshot($exam_id, $current_storage_key, $redis)) {
            return ['code' => 'revision_changed', 'label' => 'Revision berubah'];
        }

        return ['code' => 'invalid_payload', 'label' => 'Payload invalid'];
    }

    /**
     * @param array{code:string,label:string} $miss_reason
     */
    private static function build_snapshot_miss_message(array $miss_reason): string
    {
        $code = sanitize_key((string) ($miss_reason['code'] ?? ''));

        if ($code === 'revision_changed') {
            return 'Snapshot MISS karena revision exam berubah. Key revision sebelumnya tidak lagi dianggap current dan perlu dihangatkan ulang.';
        }

        if ($code === 'manual_clear') {
            return 'Snapshot MISS karena dibersihkan manual dari panel admin. Request student pertama atau warm berikutnya akan menulis ulang ke Redis.';
        }

        if ($code === 'invalid_payload') {
            return 'Snapshot MISS karena payload Redis sebelumnya tidak valid, sehingga key lama dibuang dan perlu dihydrate ulang.';
        }

        if ($code === 'expired_or_evicted') {
            return 'Snapshot MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict. Request student pertama atau warm berikutnya akan menulis ulang ke Redis.';
        }

        return 'Snapshot belum ada untuk revision exam ini. Request student pertama akan hydrate lalu menulis ke Redis.';
    }

    /**
     * @param array{code:string,label:string} $invalid_reason
     */
    private static function build_invalid_snapshot_message(array $invalid_reason): string
    {
        $code = sanitize_key((string) ($invalid_reason['code'] ?? ''));

        if ($code === 'revision_changed') {
            return 'Snapshot ditemukan tetapi signature/revision tidak cocok. Monitor ini akan memakai revision exam current saat dipulihkan ulang.';
        }

        return 'Snapshot ditemukan tetapi payload Redis tidak valid dan akan diabaikan sampai dibangun ulang.';
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
            CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('delivery_exam', $exam_id, $reason, $source);
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

        $queue_meta = CBT_Snapshot_Auto_Heal_Queue_Service::get_target_repair_state('delivery_exam', $exam_id);
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

        return 'Dipulihkan otomatis dari payload soal current';
    }

    private static function has_stale_revision_snapshot(int $exam_id, string $current_storage_key, Redis $redis): bool
    {
        $current_storage_key = trim($current_storage_key);
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

    private static function write_delivery_event_marker(int $exam_id, string $event): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return;
        }

        $redis = self::delivery_redis();
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
            self::delivery_event_storage_key($exam_id),
            self::DELIVERY_EVENT_REDIS_TTL_SECONDS,
            $payload
        );
    }

    private static function read_delivery_event_marker(int $exam_id, Redis $redis): ?array
    {
        $raw_event = $redis->get(self::delivery_event_storage_key($exam_id));
        if (!is_string($raw_event) || trim($raw_event) === '') {
            return null;
        }

        $decoded = json_decode($raw_event, true);
        if (!is_array($decoded)) {
            $redis->del(self::delivery_event_storage_key($exam_id));
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
        return self::DELIVERY_REDIS_PREFIX
            . 'exam:' . $exam_id
            . ':rev:' . max(1, (int) ($revision_meta['version'] ?? 1))
            . ':' . md5(self::revision_signature($revision_meta));
    }

    private static function delivery_event_storage_key(int $exam_id): string
    {
        return self::DELIVERY_EVENT_REDIS_PREFIX . 'exam:' . max(0, $exam_id);
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

            $normalized[] = self::redact_delivery_payload($item);
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $payload
     * @return mixed
     */
    private static function redact_delivery_payload($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }

        $redacted = [];
        foreach ($payload as $key => $value) {
            $safe_key = is_string($key) ? sanitize_key($key) : $key;
            if (is_string($safe_key) && in_array($safe_key, self::SENSITIVE_DELIVERY_KEYS, true)) {
                continue;
            }

            $redacted[$key] = is_array($value) ? self::redact_delivery_payload($value) : $value;
        }

        return $redacted;
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
