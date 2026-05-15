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
    private const SNAPSHOT_V2_INDEX_VERSION = 1;
    private const SNAPSHOT_V2_STORAGE_SHAPE = 'start_per_question_v2';
    private const START_REDIS_TTL_SECONDS = 44100;
    private const START_FRAGMENT_REDIS_TTL_SECONDS = 604800;
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
        $storage_shape = 'none';
        $v2_index_status = self::snapshot_v2_disabled() ? 'disabled' : 'v2_index_miss';
        $v2_fragment_count = 0;
        $v2_missing_fragment_count = 0;
        $fallback_reason = '';

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
                'storage_shape' => $storage_shape,
                'v2_index_status' => $v2_index_status,
                'v2_fragment_count' => $v2_fragment_count,
                'v2_missing_fragment_count' => $v2_missing_fragment_count,
                'fallback_reason' => 'idle',
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
                'storage_shape' => $storage_shape,
                'v2_index_status' => $v2_index_status,
                'v2_fragment_count' => $v2_fragment_count,
                'v2_missing_fragment_count' => $v2_missing_fragment_count,
                'fallback_reason' => 'redis_unavailable',
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'duration_minutes' => 0,
                'show_student_result' => 0,
                'enable_calculator' => 0,
            ];
        }

        if (!self::snapshot_v2_disabled()) {
            $v2_payload = self::read_exam_snapshot_v2($exam_id, $redis, $revision_meta, $v2_meta);
            $v2_index_status = (string) ($v2_meta['reason'] ?? $v2_index_status);
            if ($v2_payload !== null) {
                $snapshot_exists = true;
                $snapshot_valid = true;
                $storage_shape = self::SNAPSHOT_V2_STORAGE_SHAPE;
                $v2_index_status = 'ready';
                $storage_key = (string) ($v2_meta['storage_key'] ?? $storage_key);
                $raw_index = $redis->get($storage_key);
                $snapshot_payload_bytes = is_string($raw_index) ? strlen($raw_index) : 0;
                if (method_exists($redis, 'ttl')) {
                    $snapshot_ttl_seconds = (int) $redis->ttl($storage_key);
                }
                $question_ids = self::normalize_question_ids($v2_payload['question_ids'] ?? []);
                $snapshot_item_count = count($question_ids);
                $v2_fragment_count = $snapshot_item_count;
                $question_count = max(0, (int) ($v2_payload['question_count'] ?? $snapshot_item_count));
                $duration_minutes = max(0, (int) ($v2_payload['duration_minutes'] ?? 0));
                $show_student_result = !empty($v2_payload['show_student_result']) ? 1 : 0;
                $enable_calculator = !empty($v2_payload['enable_calculator']) ? 1 : 0;
            }
        }

        $legacy_storage_key = self::storage_key($exam_id, $revision_meta);
        $raw_payload = (!$snapshot_valid && $legacy_storage_key !== '') ? $redis->get($legacy_storage_key) : false;
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
                $snapshot_ttl_seconds = (int) $redis->ttl($legacy_storage_key);
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
        if ($fallback_reason === '' && $v2_index_status !== 'ready') {
            $fallback_reason = $v2_index_status;
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
            'storage_shape' => $storage_shape,
            'v2_index_status' => $v2_index_status,
            'v2_fragment_count' => $v2_fragment_count,
            'v2_missing_fragment_count' => $v2_missing_fragment_count,
            'fallback_reason' => $fallback_reason,
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
    public static function read_current_exam_snapshot_v2_index_envelope(int $exam_id): array
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

        $redis = self::start_snapshot_redis();
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
            self::write_start_event_marker($exam_id, 'invalid_payload');
            $default['reason'] = 'v2_index_invalid';
            return $default;
        }

        $default['success'] = true;
        $default['reason'] = 'ready';
        $default['index'] = self::normalize_v2_index($index);

        return $default;
    }

    /**
     * @param array<string,mixed> $previous_index
     * @param array<int,array{manifest:array<string,mixed>,option_tokens:array<int,string>,force_shuffle:bool}> $fragments_by_id
     */
    public static function write_current_exam_snapshot_v2_partial_index(
        int $exam_id,
        array $previous_index,
        array $fragments_by_id,
        int $ttl_seconds = 0
    ): bool {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || empty($previous_index) || empty($fragments_by_id) || self::snapshot_v2_disabled()) {
            return false;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return false;
        }

        $question_ids = self::normalize_question_ids($previous_index['question_ids'] ?? []);
        $fragment_key_by_question_id = self::normalize_v2_string_map($previous_index['fragment_key_by_question_id'] ?? []);
        $fragment_hash_by_question_id = self::normalize_v2_string_map($previous_index['fragment_hash_by_question_id'] ?? []);
        if (empty($question_ids) || empty($fragment_key_by_question_id) || empty($fragment_hash_by_question_id)) {
            return false;
        }

        $write_ttl = $ttl_seconds > 0 ? $ttl_seconds : self::START_REDIS_TTL_SECONDS;
        foreach ($fragments_by_id as $question_id => $fragment) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || !in_array($safe_question_id, $question_ids, true) || !is_array($fragment)) {
                return false;
            }

            $blob = self::build_v2_fragment_payload($exam_id, $safe_question_id, $fragment);
            if (empty($blob)) {
                return false;
            }

            $blob_key = (string) ($blob['fragment_key'] ?? '');
            $encoded_blob = wp_json_encode($blob);
            if ($blob_key === '' || !is_string($encoded_blob) || $encoded_blob === '') {
                return false;
            }

            if (!$redis->setEx($blob_key, self::fragment_ttl_seconds($write_ttl), $encoded_blob)) {
                return false;
            }

            $fragment_key_by_question_id[$safe_question_id] = $blob_key;
            $fragment_hash_by_question_id[$safe_question_id] = (string) ($blob['fragment_hash'] ?? '');
        }

        $index = self::build_v2_index_payload(
            $exam_id,
            $revision_meta,
            self::normalize_snapshot_payload($previous_index),
            $fragment_key_by_question_id,
            $fragment_hash_by_question_id
        );
        $encoded_index = wp_json_encode($index);
        if (!is_string($encoded_index) || $encoded_index === '') {
            return false;
        }

        $written = (bool) $redis->setEx(self::v2_index_storage_key($exam_id, $revision_meta), max(1, $write_ttl), $encoded_index);
        if ($written) {
            self::write_start_event_marker($exam_id, 'written');
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
     *   payload:array<string,mixed>
     * }
     */
    public static function read_current_exam_snapshot_envelope(int $exam_id): array
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
            'payload' => [],
        ];
        if ($exam_id <= 0) {
            return $default;
        }

        $redis = self::start_snapshot_redis();
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
            $v2_payload = self::read_exam_snapshot_v2($exam_id, $redis, $revision_meta, $v2_meta);
            if ($v2_payload !== null) {
                $default['success'] = true;
                $default['reason'] = 'ready';
                $default['storage_key'] = (string) ($v2_meta['storage_key'] ?? self::v2_index_storage_key($exam_id, $revision_meta));
                $default['ttl_seconds'] = (int) ($v2_meta['ttl_seconds'] ?? -2);
                $default['payload'] = $v2_payload;

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
            self::write_start_event_marker($exam_id, 'invalid_payload');
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
                self::write_start_event_marker($exam_id, 'invalid_payload');
            }
            $default['reason'] = 'snapshot_invalid';
            return $default;
        }

        $default['success'] = true;
        $default['reason'] = 'ready';
        $default['payload'] = self::normalize_snapshot_payload($decoded);

        return $default;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function write_current_exam_snapshot(int $exam_id, array $payload, int $ttl_seconds = 0): bool
    {
        return self::write_exam_snapshot_with_ttl(
            $exam_id,
            self::normalize_snapshot_payload($payload),
            $ttl_seconds > 0 ? $ttl_seconds : self::START_REDIS_TTL_SECONDS
        );
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

        if (!self::snapshot_v2_disabled()) {
            $v2_payload = self::read_exam_snapshot_v2($exam_id, $redis, $revision_meta, $v2_meta);
            if ($v2_payload !== null) {
                $redis->expire((string) ($v2_meta['storage_key'] ?? self::v2_index_storage_key($exam_id, $revision_meta)), self::START_REDIS_TTL_SECONDS);
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
        if (!self::snapshot_v2_disabled()) {
            self::write_exam_snapshot_v2_with_ttl($exam_id, $payload, self::START_REDIS_TTL_SECONDS);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function write_exam_snapshot(int $exam_id, array $payload): void
    {
        self::write_exam_snapshot_with_ttl($exam_id, $payload, self::START_REDIS_TTL_SECONDS);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function write_exam_snapshot_with_ttl(int $exam_id, array $payload, int $ttl_seconds): bool
    {
        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return false;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $encoded_payload = wp_json_encode(array_merge($payload, [
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
        ]));
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return false;
        }

        $written = (bool) $redis->setEx($storage_key, max(1, $ttl_seconds), $encoded_payload);
        if ($written) {
            self::write_start_event_marker($exam_id, 'written');
        }

        $v2_written = self::snapshot_v2_disabled()
            ? false
            : self::write_exam_snapshot_v2_with_ttl($exam_id, $payload, $ttl_seconds);

        return $written || $v2_written;
    }

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     * @param array<string,mixed>|null $meta
     * @return array<string,mixed>|null
     */
    private static function read_exam_snapshot_v2(int $exam_id, Redis $redis, array $revision_meta, ?array &$meta = null): ?array
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
            self::write_start_event_marker($exam_id, 'invalid_payload');
            $meta['reason'] = 'v2_index_invalid';
            return null;
        }

        $index = self::normalize_v2_index($index);
        $question_ids = self::normalize_question_ids($index['question_ids'] ?? []);
        $fragment_key_by_question_id = self::normalize_v2_string_map($index['fragment_key_by_question_id'] ?? []);
        $fragment_hash_by_question_id = self::normalize_v2_string_map($index['fragment_hash_by_question_id'] ?? []);
        if (empty($question_ids)) {
            $meta['reason'] = 'v2_index_empty';
            return null;
        }

        $question_manifest = [];
        $option_tokens_by_question = [];
        $force_shuffle_lookup = [];
        foreach ($question_ids as $question_id) {
            $fragment_key = (string) ($fragment_key_by_question_id[$question_id] ?? '');
            $fragment_hash = (string) ($fragment_hash_by_question_id[$question_id] ?? '');
            if ($fragment_key === '' || $fragment_hash === '') {
                $meta['reason'] = 'v2_fragment_missing';
                return null;
            }

            $raw_fragment = $redis->get($fragment_key);
            if (!is_string($raw_fragment) || trim($raw_fragment) === '') {
                $meta['reason'] = 'v2_fragment_miss';
                return null;
            }

            $fragment = json_decode($raw_fragment, true);
            if (!is_array($fragment) || !self::validate_v2_fragment($fragment, $exam_id, $question_id, $fragment_hash)) {
                $meta['reason'] = 'v2_fragment_invalid';
                return null;
            }

            $manifest_item = isset($fragment['manifest']) && is_array($fragment['manifest'])
                ? (array) $fragment['manifest']
                : [];
            if (empty($manifest_item)) {
                $meta['reason'] = 'v2_fragment_manifest_missing';
                return null;
            }

            $question_manifest[] = $manifest_item;
            $tokens = self::normalize_option_randomization_tokens_map([$question_id => $fragment['option_tokens'] ?? []]);
            if (!empty($tokens[$question_id])) {
                $option_tokens_by_question[$question_id] = $tokens[$question_id];
            }
            if (!empty($fragment['force_shuffle'])) {
                $force_shuffle_lookup[$question_id] = true;
            }
        }

        $payload = self::normalize_snapshot_payload(array_merge($index, [
            'question_manifest' => $question_manifest,
            'option_randomization_tokens_by_question' => $option_tokens_by_question,
            'force_option_shuffle_question_ids' => array_keys($force_shuffle_lookup),
        ]));
        $meta['reason'] = 'ready';

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function write_exam_snapshot_v2_with_ttl(int $exam_id, array $payload, int $ttl_seconds): bool
    {
        if (self::snapshot_v2_disabled()) {
            return false;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return false;
        }

        $payload = self::normalize_snapshot_payload($payload);
        $question_ids = self::normalize_question_ids($payload['question_ids'] ?? []);
        if (empty($question_ids)) {
            return false;
        }

        $manifest_by_id = [];
        foreach ((array) ($payload['question_manifest'] ?? []) as $manifest_item) {
            if (!is_array($manifest_item)) {
                continue;
            }

            $question_id = (int) ($manifest_item['id'] ?? 0);
            if ($question_id > 0) {
                $manifest_by_id[$question_id] = (array) $manifest_item;
            }
        }

        $tokens_by_question = self::normalize_option_randomization_tokens_map($payload['option_randomization_tokens_by_question'] ?? []);
        $force_shuffle_lookup = array_fill_keys(self::normalize_question_ids($payload['force_option_shuffle_question_ids'] ?? []), true);
        $fragment_key_by_question_id = [];
        $fragment_hash_by_question_id = [];
        $fragment_ttl = self::fragment_ttl_seconds($ttl_seconds);

        foreach ($question_ids as $question_id) {
            $manifest_item = (array) ($manifest_by_id[$question_id] ?? ['id' => $question_id]);
            $fragment = [
                'manifest' => $manifest_item,
                'option_tokens' => (array) ($tokens_by_question[$question_id] ?? []),
                'force_shuffle' => !empty($force_shuffle_lookup[$question_id]),
            ];
            $blob = self::build_v2_fragment_payload($exam_id, $question_id, $fragment);
            if (empty($blob)) {
                return false;
            }

            $blob_key = (string) ($blob['fragment_key'] ?? '');
            $encoded_blob = wp_json_encode($blob);
            if ($blob_key === '' || !is_string($encoded_blob) || $encoded_blob === '') {
                return false;
            }

            if (!$redis->setEx($blob_key, $fragment_ttl, $encoded_blob)) {
                return false;
            }

            $fragment_key_by_question_id[$question_id] = $blob_key;
            $fragment_hash_by_question_id[$question_id] = (string) ($blob['fragment_hash'] ?? '');
        }

        $index = self::build_v2_index_payload(
            $exam_id,
            $revision_meta,
            $payload,
            $fragment_key_by_question_id,
            $fragment_hash_by_question_id
        );
        $encoded_index = wp_json_encode($index);
        if (!is_string($encoded_index) || $encoded_index === '') {
            return false;
        }

        return (bool) $redis->setEx(self::v2_index_storage_key($exam_id, $revision_meta), max(1, $ttl_seconds), $encoded_index);
    }

    /**
     * @param array<int,string> $fragment_key_by_question_id
     * @param array<int,string> $fragment_hash_by_question_id
     * @return array<string,mixed>
     */
    private static function build_v2_index_payload(
        int $exam_id,
        array $revision_meta,
        array $payload,
        array $fragment_key_by_question_id,
        array $fragment_hash_by_question_id
    ): array {
        $payload = self::normalize_snapshot_payload($payload);
        return [
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'snapshot_storage_version' => self::SNAPSHOT_V2_INDEX_VERSION,
            'storage_shape' => self::SNAPSHOT_V2_STORAGE_SHAPE,
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
            'question_ids' => self::normalize_question_ids($payload['question_ids'] ?? []),
            'question_count' => max(0, (int) ($payload['question_count'] ?? 0)),
            'question_number_map' => self::normalize_question_number_map($payload['question_number_map'] ?? []),
            'randomize_questions' => !empty($payload['randomize_questions']) ? 1 : 0,
            'randomize_options' => !empty($payload['randomize_options']) ? 1 : 0,
            'duration_minutes' => max(0, (int) ($payload['duration_minutes'] ?? 0)),
            'show_student_result' => !empty($payload['show_student_result']) ? 1 : 0,
            'enable_calculator' => !empty($payload['enable_calculator']) ? 1 : 0,
            'fragment_key_by_question_id' => self::normalize_v2_string_map($fragment_key_by_question_id),
            'fragment_hash_by_question_id' => self::normalize_v2_string_map($fragment_hash_by_question_id),
        ];
    }

    /**
     * @param array{manifest:array<string,mixed>,option_tokens:array<int,string>,force_shuffle:bool} $fragment
     * @return array<string,mixed>
     */
    private static function build_v2_fragment_payload(int $exam_id, int $question_id, array $fragment): array
    {
        $manifest = isset($fragment['manifest']) && is_array($fragment['manifest'])
            ? self::normalize_question_manifest([(array) $fragment['manifest']])
            : [];
        $manifest_item = (array) ($manifest[0] ?? ['id' => $question_id]);
        $manifest_item['id'] = $question_id;
        $option_tokens = self::normalize_option_randomization_tokens_map([$question_id => $fragment['option_tokens'] ?? []]);
        $payload = [
            'manifest' => $manifest_item,
            'option_tokens' => array_values((array) ($option_tokens[$question_id] ?? [])),
            'force_shuffle' => !empty($fragment['force_shuffle']) ? 1 : 0,
        ];
        $encoded_payload = wp_json_encode($payload);
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return [];
        }

        $fragment_hash = hash('sha256', $encoded_payload);
        return array_merge($payload, [
            'snapshot_payload_version' => self::SNAPSHOT_PAYLOAD_VERSION,
            'snapshot_storage_version' => self::SNAPSHOT_V2_INDEX_VERSION,
            'storage_shape' => self::SNAPSHOT_V2_STORAGE_SHAPE . '_fragment',
            'exam_id' => $exam_id,
            'question_id' => $question_id,
            'fragment_hash' => $fragment_hash,
            'fragment_key' => self::v2_fragment_storage_key($exam_id, $fragment_hash),
        ]);
    }

    private static function validate_v2_index(array $index, int $exam_id, string $revision_signature): bool
    {
        return (int) ($index['snapshot_payload_version'] ?? 0) === self::SNAPSHOT_PAYLOAD_VERSION
            && (int) ($index['snapshot_storage_version'] ?? 0) === self::SNAPSHOT_V2_INDEX_VERSION
            && (string) ($index['storage_shape'] ?? '') === self::SNAPSHOT_V2_STORAGE_SHAPE
            && absint($index['exam_id'] ?? 0) === $exam_id
            && (string) ($index['revision_signature'] ?? '') === $revision_signature
            && !empty($index['question_ids'])
            && is_array($index['fragment_key_by_question_id'] ?? null)
            && is_array($index['fragment_hash_by_question_id'] ?? null);
    }

    private static function validate_v2_fragment(array $fragment, int $exam_id, int $question_id, string $fragment_hash): bool
    {
        return (int) ($fragment['snapshot_payload_version'] ?? 0) === self::SNAPSHOT_PAYLOAD_VERSION
            && (int) ($fragment['snapshot_storage_version'] ?? 0) === self::SNAPSHOT_V2_INDEX_VERSION
            && (string) ($fragment['storage_shape'] ?? '') === self::SNAPSHOT_V2_STORAGE_SHAPE . '_fragment'
            && absint($fragment['exam_id'] ?? 0) === $exam_id
            && absint($fragment['question_id'] ?? 0) === $question_id
            && (string) ($fragment['fragment_hash'] ?? '') === $fragment_hash
            && is_array($fragment['manifest'] ?? null);
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalize_v2_index(array $index): array
    {
        $index['question_ids'] = self::normalize_question_ids($index['question_ids'] ?? []);
        $index['question_count'] = max(0, (int) ($index['question_count'] ?? count($index['question_ids'])));
        $index['question_number_map'] = self::normalize_question_number_map($index['question_number_map'] ?? []);
        $index['fragment_key_by_question_id'] = self::normalize_v2_string_map($index['fragment_key_by_question_id'] ?? []);
        $index['fragment_hash_by_question_id'] = self::normalize_v2_string_map($index['fragment_hash_by_question_id'] ?? []);

        return $index;
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

    private static function snapshot_v2_disabled(): bool
    {
        return defined('CBT_SNAPSHOT_V2_DISABLED') && CBT_SNAPSHOT_V2_DISABLED;
    }

    private static function fragment_ttl_seconds(int $ttl_seconds): int
    {
        return max(self::START_FRAGMENT_REDIS_TTL_SECONDS, max(1, $ttl_seconds));
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
            if (strpos($key, ':fragment:') !== false) {
                continue;
            }

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

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     */
    private static function v2_index_storage_key(int $exam_id, array $revision_meta): string
    {
        return self::storage_key($exam_id, $revision_meta) . ':v2:index';
    }

    private static function v2_fragment_storage_key(int $exam_id, string $fragment_hash): string
    {
        return self::START_REDIS_PREFIX
            . 'exam:' . max(0, $exam_id)
            . ':fragment:' . preg_replace('/[^a-f0-9]/', '', strtolower($fragment_hash));
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
