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
    private const SNAPSHOT_V2_INDEX_VERSION = 1;
    private const SNAPSHOT_V2_STORAGE_SHAPE = 'per_question_v2';
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
    private const DELIVERY_BLOB_REDIS_TTL_SECONDS = 604800;
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
        $storage_shape = 'none';
        $v2_index_status = self::snapshot_v2_disabled() ? 'disabled' : 'v2_index_miss';
        $v2_blob_count = 0;
        $v2_missing_blob_count = 0;
        $raw_fast_available = false;
        $fallback_reason = '';

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
                'storage_shape' => $storage_shape,
                'v2_index_status' => $v2_index_status,
                'v2_blob_count' => $v2_blob_count,
                'v2_missing_blob_count' => $v2_missing_blob_count,
                'raw_fast_available' => false,
                'fallback_reason' => 'idle',
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
                'storage_shape' => $storage_shape,
                'v2_index_status' => $v2_index_status,
                'v2_blob_count' => $v2_blob_count,
                'v2_missing_blob_count' => $v2_missing_blob_count,
                'raw_fast_available' => false,
                'fallback_reason' => 'redis_unavailable',
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

        if (!self::snapshot_v2_disabled()) {
            $v2_payload = self::read_exam_payload_v2($exam_id, $redis, $revision_meta, $v2_meta);
            $v2_index_status = (string) ($v2_meta['reason'] ?? $v2_index_status);
            if ($v2_payload !== null) {
                $snapshot_exists = true;
                $snapshot_valid = true;
                $storage_shape = self::SNAPSHOT_V2_STORAGE_SHAPE;
                $v2_index_status = 'ready';
                $storage_key = (string) ($v2_meta['storage_key'] ?? $storage_key);
                if (method_exists($redis, 'ttl')) {
                    $snapshot_ttl_seconds = (int) $redis->ttl($storage_key);
                }
                $snapshot_payload_bytes = strlen((string) $redis->get($storage_key));
                $items = self::normalize_items($v2_payload);
                $snapshot_item_count = count($items);
                $v2_blob_count = $snapshot_item_count;
                $raw_fast_available = $snapshot_item_count > 0;
                $preview_total_pages = max(1, (int) ceil($snapshot_item_count / $preview_per_page));
                $preview_current_page = min($preview_total_pages, max(1, $preview_page));
                $preview_offset = ($preview_current_page - 1) * $preview_per_page;
                $preview_slice = array_slice($items, $preview_offset, $preview_per_page);
                $preview_question_ids = array_values(array_filter(array_map(static function ($item): int {
                    return absint(is_array($item) ? ($item['id'] ?? 0) : 0);
                }, $preview_slice)));
                $preview_items = self::build_preview_items($preview_slice);
            }
        }

        $raw_payload = (!$snapshot_valid && $storage_key !== '') ? $redis->get(self::storage_key($exam_id, $revision_meta)) : false;
        if (is_string($raw_payload) && trim($raw_payload) !== '') {
            $snapshot_exists = true;
            if ($storage_shape === 'none') {
                $storage_shape = 'legacy_monolith';
            }
            if ($fallback_reason === '' && $v2_index_status !== 'ready') {
                $fallback_reason = $v2_index_status;
            }
            $snapshot_payload_bytes = strlen($raw_payload);
            if (method_exists($redis, 'ttl')) {
                $snapshot_ttl_seconds = (int) $redis->ttl(self::storage_key($exam_id, $revision_meta));
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
        if ($fallback_reason === '' && $v2_index_status !== 'ready') {
            $fallback_reason = $v2_index_status;
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
            'storage_shape' => $storage_shape,
            'v2_index_status' => $v2_index_status,
            'v2_blob_count' => $v2_blob_count,
            'v2_missing_blob_count' => $v2_missing_blob_count,
            'raw_fast_available' => $raw_fast_available,
            'fallback_reason' => $fallback_reason,
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
     *   index:array<string,mixed>
     * }
     */
    public static function read_current_exam_payload_v2_index_envelope(int $exam_id): array
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
            'reason' => $exam_id > 0 ? 'v2_index_miss' : 'invalid_exam',
            'exam_id' => $exam_id,
            'revision_meta' => $empty_revision,
            'revision_signature' => '',
            'storage_key' => '',
            'ttl_seconds' => -2,
            'index' => [],
        ];
        if ($exam_id <= 0) {
            return $default;
        }

        if (self::snapshot_v2_disabled()) {
            $default['reason'] = 'v2_disabled';
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

        $index_key = self::v2_index_storage_key($exam_id, $revision_meta);
        $default['storage_key'] = $index_key;
        $raw_index = $redis->get($index_key);
        if (method_exists($redis, 'ttl')) {
            $default['ttl_seconds'] = (int) $redis->ttl($index_key);
        }
        if (!is_string($raw_index) || trim($raw_index) === '') {
            $default['reason'] = 'v2_index_miss';
            return $default;
        }

        $index = json_decode($raw_index, true);
        if (!is_array($index) || !self::validate_v2_index($index, $exam_id, $revision_signature)) {
            self::write_delivery_event_marker($exam_id, 'invalid_payload');
            $default['reason'] = 'v2_index_invalid';
            return $default;
        }

        $default['success'] = true;
        $default['reason'] = 'ready';
        $default['index'] = self::normalize_v2_index($index);

        return $default;
    }

    /**
     * @param array<int,int> $question_ids
     * @return array{
     *   success:bool,
     *   reason:string,
     *   exam_id:int,
     *   index:array<string,mixed>,
     *   blobs_by_id:array<int,array<string,mixed>>
     * }
     */
    public static function read_current_exam_raw_item_blobs(int $exam_id, array $question_ids): array
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        $default = [
            'success' => false,
            'reason' => empty($question_ids) ? 'empty_question_ids' : 'v2_index_unavailable',
            'exam_id' => absint($exam_id),
            'index' => [],
            'blobs_by_id' => [],
        ];
        if ($exam_id <= 0 || empty($question_ids)) {
            return $default;
        }

        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            $default['reason'] = 'redis_unavailable';
            return $default;
        }

        $index_envelope = self::read_current_exam_payload_v2_index_envelope($exam_id);
        if (empty($index_envelope['success'])) {
            $default['reason'] = sanitize_key((string) ($index_envelope['reason'] ?? 'v2_index_unavailable'));
            return $default;
        }

        $index = (array) ($index_envelope['index'] ?? []);
        $item_key_by_question_id = self::normalize_v2_string_map($index['item_key_by_question_id'] ?? []);
        $item_hash_by_question_id = self::normalize_v2_string_map($index['item_hash_by_question_id'] ?? []);
        $blobs_by_id = [];
        foreach ($question_ids as $question_id) {
            $key = (string) ($item_key_by_question_id[$question_id] ?? '');
            $hash = (string) ($item_hash_by_question_id[$question_id] ?? '');
            if ($key === '' || $hash === '') {
                $default['reason'] = 'v2_question_missing';
                return $default;
            }

            $raw_blob = $redis->get($key);
            if (!is_string($raw_blob) || trim($raw_blob) === '') {
                $default['reason'] = 'v2_blob_miss';
                return $default;
            }

            $blob = json_decode($raw_blob, true);
            if (!is_array($blob) || !self::validate_v2_item_blob($blob, $exam_id, $question_id, $hash)) {
                $default['reason'] = 'v2_blob_invalid';
                return $default;
            }

            $blobs_by_id[$question_id] = $blob;
        }

        return [
            'success' => true,
            'reason' => 'ready',
            'exam_id' => absint($exam_id),
            'index' => $index,
            'blobs_by_id' => $blobs_by_id,
        ];
    }

    /**
     * @param array<string,mixed> $previous_index
     * @param array<int,array<string,mixed>> $replacement_items_by_id
     */
    public static function write_current_exam_payload_v2_partial_index(
        int $exam_id,
        array $previous_index,
        array $replacement_items_by_id,
        int $ttl_seconds = 0
    ): bool {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || empty($previous_index) || self::snapshot_v2_disabled()) {
            return false;
        }

        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return false;
        }

        $question_ids = self::normalize_question_ids($previous_index['question_ids'] ?? []);
        if (empty($question_ids)) {
            return false;
        }

        $item_key_by_question_id = self::normalize_v2_string_map($previous_index['item_key_by_question_id'] ?? []);
        $item_hash_by_question_id = self::normalize_v2_string_map($previous_index['item_hash_by_question_id'] ?? []);
        $question_meta_by_id = self::normalize_v2_array_map($previous_index['question_meta_by_id'] ?? []);

        $normalized_replacements = [];
        foreach ($replacement_items_by_id as $question_id => $item) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || !in_array($safe_question_id, $question_ids, true) || !is_array($item)) {
                return false;
            }

            $normalized_replacements[$safe_question_id] = self::redact_delivery_payload($item);
        }

        $write_ttl = $ttl_seconds > 0 ? $ttl_seconds : self::DELIVERY_REDIS_TTL_SECONDS;
        foreach ($normalized_replacements as $question_id => $item) {
            $blob = self::build_v2_item_blob_payload($exam_id, $item);
            if (empty($blob)) {
                return false;
            }

            $blob_key = (string) ($blob['item_key'] ?? '');
            $encoded_blob = wp_json_encode($blob);
            if ($blob_key === '' || !is_string($encoded_blob) || $encoded_blob === '') {
                return false;
            }

            if (!$redis->setEx($blob_key, self::blob_ttl_seconds($write_ttl), $encoded_blob)) {
                return false;
            }

            $item_key_by_question_id[$question_id] = $blob_key;
            $item_hash_by_question_id[$question_id] = (string) ($blob['item_hash'] ?? '');
            $question_meta_by_id[$question_id] = (array) ($blob['meta'] ?? []);
        }

        $index = self::build_v2_index_payload(
            $exam_id,
            $revision_meta,
            $question_ids,
            $item_key_by_question_id,
            $item_hash_by_question_id,
            $question_meta_by_id
        );
        $encoded_index = wp_json_encode($index);
        if (!is_string($encoded_index) || $encoded_index === '') {
            return false;
        }

        $written = (bool) $redis->setEx(self::v2_index_storage_key($exam_id, $revision_meta), max(1, $write_ttl), $encoded_index);
        if ($written) {
            self::write_delivery_event_marker($exam_id, 'written');
        }

        return $written;
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

        if (!self::snapshot_v2_disabled()) {
            $v2_payload = self::read_exam_payload_v2($exam_id, $redis, $revision_meta, $v2_meta);
            if ($v2_payload !== null) {
                $default['success'] = true;
                $default['reason'] = 'ready';
                $default['storage_key'] = (string) ($v2_meta['storage_key'] ?? self::v2_index_storage_key($exam_id, $revision_meta));
                $default['ttl_seconds'] = (int) ($v2_meta['ttl_seconds'] ?? -2);
                $default['items'] = $v2_payload;

                return $default;
            }
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

        if (!self::snapshot_v2_disabled()) {
            $v2_payload = self::read_exam_payload_v2($exam_id, $redis, $revision_meta, $v2_meta);
            if ($v2_payload !== null) {
                $ttl_seconds = (int) ($v2_meta['ttl_seconds'] ?? 0);
                if ($ttl_seconds > 0) {
                    $redis->expire((string) ($v2_meta['storage_key'] ?? self::v2_index_storage_key($exam_id, $revision_meta)), self::DELIVERY_REDIS_TTL_SECONDS);
                }

                return $v2_payload;
            }
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
        if (!self::snapshot_v2_disabled()) {
            self::write_exam_payload_v2_with_ttl($exam_id, $items, self::DELIVERY_REDIS_TTL_SECONDS);
        }

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

        $v2_written = self::snapshot_v2_disabled()
            ? false
            : self::write_exam_payload_v2_with_ttl($exam_id, $items, $ttl_seconds);

        return $written || $v2_written;
    }

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     * @param array<string,mixed>|null $meta
     * @return array<int,array<string,mixed>>|null
     */
    private static function read_exam_payload_v2(int $exam_id, Redis $redis, array $revision_meta, ?array &$meta = null): ?array
    {
        $meta = [
            'storage_key' => '',
            'ttl_seconds' => -2,
            'reason' => 'v2_index_miss',
        ];
        $revision_signature = self::revision_signature($revision_meta);
        $index_key = self::v2_index_storage_key($exam_id, $revision_meta);
        $meta['storage_key'] = $index_key;
        if (method_exists($redis, 'ttl')) {
            $meta['ttl_seconds'] = (int) $redis->ttl($index_key);
        }

        $raw_index = $redis->get($index_key);
        if (!is_string($raw_index) || trim($raw_index) === '') {
            return null;
        }

        $index = json_decode($raw_index, true);
        if (!is_array($index) || !self::validate_v2_index($index, $exam_id, $revision_signature)) {
            self::write_delivery_event_marker($exam_id, 'invalid_payload');
            $meta['reason'] = 'v2_index_invalid';
            return null;
        }

        $index = self::normalize_v2_index($index);
        $question_ids = self::normalize_question_ids($index['question_ids'] ?? []);
        $item_key_by_question_id = self::normalize_v2_string_map($index['item_key_by_question_id'] ?? []);
        $item_hash_by_question_id = self::normalize_v2_string_map($index['item_hash_by_question_id'] ?? []);
        if (empty($question_ids)) {
            $meta['reason'] = 'v2_index_empty';
            return null;
        }

        $items = [];
        foreach ($question_ids as $question_id) {
            $blob_key = (string) ($item_key_by_question_id[$question_id] ?? '');
            $item_hash = (string) ($item_hash_by_question_id[$question_id] ?? '');
            if ($blob_key === '' || $item_hash === '') {
                $meta['reason'] = 'v2_question_missing';
                return null;
            }

            $raw_blob = $redis->get($blob_key);
            if (!is_string($raw_blob) || trim($raw_blob) === '') {
                $meta['reason'] = 'v2_blob_miss';
                return null;
            }

            $blob = json_decode($raw_blob, true);
            if (!is_array($blob) || !self::validate_v2_item_blob($blob, $exam_id, $question_id, $item_hash)) {
                $meta['reason'] = 'v2_blob_invalid';
                return null;
            }

            $item_raw = (string) ($blob['item_raw'] ?? '');
            $item = json_decode($item_raw, true);
            if (!is_array($item)) {
                $meta['reason'] = 'v2_item_invalid';
                return null;
            }

            $items[] = self::redact_delivery_payload($item);
        }

        $meta['reason'] = 'ready';
        return array_values($items);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function write_exam_payload_v2_with_ttl(int $exam_id, array $items, int $ttl_seconds): bool
    {
        if (self::snapshot_v2_disabled()) {
            return false;
        }

        $redis = self::delivery_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return false;
        }

        $items = self::normalize_items($items);
        if (empty($items)) {
            return false;
        }

        $question_ids = [];
        $item_key_by_question_id = [];
        $item_hash_by_question_id = [];
        $question_meta_by_id = [];
        $blob_ttl = self::blob_ttl_seconds($ttl_seconds);

        foreach ($items as $item) {
            $question_id = (int) ($item['id'] ?? 0);
            if ($question_id <= 0) {
                return false;
            }

            $blob = self::build_v2_item_blob_payload($exam_id, $item);
            if (empty($blob)) {
                return false;
            }

            $blob_key = (string) ($blob['item_key'] ?? '');
            $encoded_blob = wp_json_encode($blob);
            if ($blob_key === '' || !is_string($encoded_blob) || $encoded_blob === '') {
                return false;
            }

            if (!$redis->setEx($blob_key, $blob_ttl, $encoded_blob)) {
                return false;
            }

            $question_ids[] = $question_id;
            $item_key_by_question_id[$question_id] = $blob_key;
            $item_hash_by_question_id[$question_id] = (string) ($blob['item_hash'] ?? '');
            $question_meta_by_id[$question_id] = (array) ($blob['meta'] ?? []);
        }

        $index = self::build_v2_index_payload(
            $exam_id,
            $revision_meta,
            $question_ids,
            $item_key_by_question_id,
            $item_hash_by_question_id,
            $question_meta_by_id
        );
        $encoded_index = wp_json_encode($index);
        if (!is_string($encoded_index) || $encoded_index === '') {
            return false;
        }

        return (bool) $redis->setEx(
            self::v2_index_storage_key($exam_id, $revision_meta),
            max(1, $ttl_seconds),
            $encoded_index
        );
    }

    /**
     * @param array<int,int> $question_ids
     * @param array<int,string> $item_key_by_question_id
     * @param array<int,string> $item_hash_by_question_id
     * @param array<int,array<string,mixed>> $question_meta_by_id
     * @return array<string,mixed>
     */
    private static function build_v2_index_payload(
        int $exam_id,
        array $revision_meta,
        array $question_ids,
        array $item_key_by_question_id,
        array $item_hash_by_question_id,
        array $question_meta_by_id
    ): array {
        $safe_question_ids = self::normalize_question_ids($question_ids);

        return [
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'snapshot_storage_version' => self::SNAPSHOT_V2_INDEX_VERSION,
            'storage_shape' => self::SNAPSHOT_V2_STORAGE_SHAPE,
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
            'question_ids' => $safe_question_ids,
            'question_count' => count($safe_question_ids),
            'item_key_by_question_id' => self::normalize_v2_string_map($item_key_by_question_id),
            'item_hash_by_question_id' => self::normalize_v2_string_map($item_hash_by_question_id),
            'question_meta_by_id' => self::normalize_v2_array_map($question_meta_by_id),
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private static function build_v2_item_blob_payload(int $exam_id, array $item): array
    {
        $item = self::redact_delivery_payload($item);
        $question_id = (int) ($item['id'] ?? 0);
        if ($exam_id <= 0 || $question_id <= 0) {
            return [];
        }

        $has_options = array_key_exists('options', $item) && is_array($item['options']);
        $options = $has_options ? array_values((array) $item['options']) : [];
        $item_without_options = $item;
        unset($item_without_options['options']);

        $item_raw = wp_json_encode($item);
        $item_without_options_raw = wp_json_encode($item_without_options);
        $default_options_raw = wp_json_encode($options);
        if (
            !is_string($item_raw) || $item_raw === ''
            || !is_string($item_without_options_raw) || $item_without_options_raw === ''
            || !is_string($default_options_raw) || $default_options_raw === ''
        ) {
            return [];
        }

        $option_raw_by_token = [];
        $option_token_order = [];
        foreach ($options as $option_row) {
            if (!is_array($option_row)) {
                continue;
            }

            $option = (array) $option_row;
            $option_id = (int) ($option['id'] ?? 0);
            if ($option_id <= 0) {
                continue;
            }

            $option_raw = wp_json_encode($option);
            if (!is_string($option_raw) || $option_raw === '') {
                continue;
            }

            $token = (string) $option_id;
            $option_raw_by_token[$token] = $option_raw;
            $option_token_order[] = $token;
        }

        $short_answer_input_keys = [];
        if (isset($item['short_answer_meta']['input_keys']) && is_array($item['short_answer_meta']['input_keys'])) {
            $short_answer_input_keys = array_values(array_filter(array_map('strval', $item['short_answer_meta']['input_keys']), static function (string $key): bool {
                return trim($key) !== '';
            }));
        }

        $item_hash = hash('sha256', $item_raw);
        $meta = [
            'id' => $question_id,
            'question_type' => sanitize_key((string) ($item['question_type'] ?? '')),
            'points' => (float) ($item['points'] ?? 0),
            'updated_at' => is_scalar($item['updated_at'] ?? null) ? (string) $item['updated_at'] : '',
            'option_count' => count($options),
            'has_options' => $has_options ? 1 : 0,
            'short_answer_input_keys' => $short_answer_input_keys,
        ];

        return [
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'snapshot_storage_version' => self::SNAPSHOT_V2_INDEX_VERSION,
            'storage_shape' => self::SNAPSHOT_V2_STORAGE_SHAPE . '_item',
            'exam_id' => $exam_id,
            'question_id' => $question_id,
            'item_hash' => $item_hash,
            'item_key' => self::v2_item_blob_storage_key($exam_id, $item_hash),
            'item_raw' => $item_raw,
            'item_without_options_raw' => $item_without_options_raw,
            'default_options_raw' => $default_options_raw,
            'has_options' => $has_options ? 1 : 0,
            'option_raw_by_token' => $option_raw_by_token,
            'option_token_order' => $option_token_order,
            'meta' => $meta,
        ];
    }

    private static function validate_v2_index(array $index, int $exam_id, string $revision_signature): bool
    {
        return (int) ($index['snapshot_payload_version'] ?? 0) === self::SNAPSHOT_PAYLOAD_VERSION
            && (int) ($index['snapshot_storage_version'] ?? 0) === self::SNAPSHOT_V2_INDEX_VERSION
            && (string) ($index['storage_shape'] ?? '') === self::SNAPSHOT_V2_STORAGE_SHAPE
            && absint($index['exam_id'] ?? 0) === $exam_id
            && (string) ($index['revision_signature'] ?? '') === $revision_signature
            && !empty($index['question_ids'])
            && is_array($index['item_key_by_question_id'] ?? null)
            && is_array($index['item_hash_by_question_id'] ?? null);
    }

    private static function validate_v2_item_blob(array $blob, int $exam_id, int $question_id, string $item_hash): bool
    {
        return (int) ($blob['snapshot_payload_version'] ?? 0) === self::SNAPSHOT_PAYLOAD_VERSION
            && (int) ($blob['snapshot_storage_version'] ?? 0) === self::SNAPSHOT_V2_INDEX_VERSION
            && (string) ($blob['storage_shape'] ?? '') === self::SNAPSHOT_V2_STORAGE_SHAPE . '_item'
            && absint($blob['exam_id'] ?? 0) === $exam_id
            && absint($blob['question_id'] ?? 0) === $question_id
            && (string) ($blob['item_hash'] ?? '') === $item_hash
            && is_string($blob['item_raw'] ?? null)
            && trim((string) ($blob['item_raw'] ?? '')) !== '';
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalize_v2_index(array $index): array
    {
        $index['question_ids'] = self::normalize_question_ids($index['question_ids'] ?? []);
        $index['question_count'] = count($index['question_ids']);
        $index['item_key_by_question_id'] = self::normalize_v2_string_map($index['item_key_by_question_id'] ?? []);
        $index['item_hash_by_question_id'] = self::normalize_v2_string_map($index['item_hash_by_question_id'] ?? []);
        $index['question_meta_by_id'] = self::normalize_v2_array_map($index['question_meta_by_id'] ?? []);

        return $index;
    }

    /**
     * @param mixed $values
     * @return array<int,int>
     */
    private static function normalize_question_ids($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $values), static function (int $question_id): bool {
            return $question_id > 0;
        })));
    }

    /**
     * @param mixed $values
     * @return array<int,string>
     */
    private static function normalize_v2_string_map($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $key => $value) {
            $question_id = (int) $key;
            if ($question_id <= 0 || !is_scalar($value)) {
                continue;
            }

            $string_value = trim((string) $value);
            if ($string_value !== '') {
                $normalized[$question_id] = $string_value;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $values
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_v2_array_map($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $key => $value) {
            $question_id = (int) $key;
            if ($question_id <= 0 || !is_array($value)) {
                continue;
            }

            $normalized[$question_id] = (array) $value;
        }

        return $normalized;
    }

    private static function snapshot_v2_disabled(): bool
    {
        return defined('CBT_SNAPSHOT_V2_DISABLED') && CBT_SNAPSHOT_V2_DISABLED;
    }

    private static function blob_ttl_seconds(int $ttl_seconds): int
    {
        return max(self::DELIVERY_BLOB_REDIS_TTL_SECONDS, max(1, $ttl_seconds));
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
            if (strpos($key, ':item:') !== false) {
                continue;
            }

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

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     */
    private static function v2_index_storage_key(int $exam_id, array $revision_meta): string
    {
        return self::storage_key($exam_id, $revision_meta) . ':v2:index';
    }

    private static function v2_item_blob_storage_key(int $exam_id, string $item_hash): string
    {
        return self::DELIVERY_REDIS_PREFIX
            . 'exam:' . max(0, $exam_id)
            . ':item:' . preg_replace('/[^a-f0-9]/', '', strtolower($item_hash));
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
