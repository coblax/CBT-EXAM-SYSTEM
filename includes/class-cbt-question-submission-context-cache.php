<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Admin_Questions_Helper')) {
    require_once dirname(__DIR__) . '/admin/class-cbt-admin-questions-helper.php';
}

class CBT_Question_Submission_Context_Cache
{
    private const SNAPSHOT_REDIS_TTL_SECONDS = 44100;
    private const SNAPSHOT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const SNAPSHOT_REDIS_DEFAULT_PORT = 6379;
    private const SNAPSHOT_REDIS_DEFAULT_DATABASE = 2;
    private const SNAPSHOT_REDIS_PREFIX = 'cbt_submit_context:';
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
     * @return array<string,mixed>|null
     */
    public static function get_snapshot(int $question_id): ?array
    {
        $snapshots = self::get_snapshots([$question_id]);
        return $snapshots[$question_id] ?? null;
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<string,mixed>>
     */
    public static function get_snapshots(array $question_ids): array
    {
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if (empty($question_ids)) {
            return [];
        }

        $snapshots = [];
        $missing_ids = $question_ids;
        $redis_available = false;

        $redis_snapshots = self::read_redis_snapshots($question_ids, $redis_available);
        if (!empty($redis_snapshots)) {
            $snapshots = $redis_snapshots;
            $missing_ids = array_values(array_diff($question_ids, array_keys($redis_snapshots)));
        }

        if (empty($missing_ids)) {
            return $snapshots;
        }

        $hydrated = self::hydrate_snapshots_from_db($missing_ids);
        if (!empty($hydrated)) {
            foreach ($hydrated as $question_id => $snapshot) {
                $snapshots[(int) $question_id] = $snapshot;
            }

            if ($redis_available) {
                self::write_redis_snapshots($hydrated);
            }
        }

        return $snapshots;
    }

    /**
     * @return array{
     *   exam_id:int,
     *   question_count:int,
     *   ready_count:int,
     *   missing_count:int,
     *   invalid_count:int,
     *   payload_bytes_total:int,
     *   preview_items:array<int,array{question_id:int,question_type:string,status:string,payload_bytes:int}>,
     *   snapshot_status:string,
     *   snapshot_message:string,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool
     * }
     */
    public static function warm_exam_snapshots(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return self::get_exam_snapshot_diagnostics($exam_id);
        }

        $question_ids = self::get_exam_question_ids($exam_id);
        if (!empty($question_ids)) {
            self::get_snapshots($question_ids);
        }

        return self::get_exam_snapshot_diagnostics($exam_id);
    }

    /**
     * @return array{
     *   exam_id:int,
     *   question_count:int,
     *   deleted_keys:int
     * }
     */
    public static function clear_exam_snapshots(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        $question_ids = self::get_exam_question_ids($exam_id);
        $deleted_keys = 0;

        $redis = self::snapshot_redis();
        if ($redis instanceof Redis && $exam_id > 0 && !empty($question_ids)) {
            $catalog_version = self::catalog_version();
            $exam_version = self::exam_version($exam_id);

            foreach ($question_ids as $question_id) {
                $keys = [self::pointer_key($question_id)];
                $keys[] = self::storage_key($question_id, $exam_id, $catalog_version, $exam_version);

                $raw_pointer = $redis->get(self::pointer_key($question_id));
                if (is_string($raw_pointer) && trim($raw_pointer) !== '') {
                    $pointer = json_decode($raw_pointer, true);
                    if (is_array($pointer)) {
                        $pointer_storage_key = is_scalar($pointer['storage_key'] ?? null) ? trim((string) $pointer['storage_key']) : '';
                        if ($pointer_storage_key !== '') {
                            $keys[] = $pointer_storage_key;
                        }
                    }
                }

                $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
                if (!empty($keys)) {
                    $deleted_keys += (int) $redis->del(...$keys);
                }
            }
        }

        return [
            'exam_id' => $exam_id,
            'question_count' => count($question_ids),
            'deleted_keys' => $deleted_keys,
        ];
    }

    /**
     * @return array{
     *   exam_id:int,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   question_count:int,
     *   ready_count:int,
     *   missing_count:int,
     *   invalid_count:int,
     *   payload_bytes_total:int,
     *   preview_items:array<int,array{question_id:int,question_type:string,status:string,payload_bytes:int}>,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_status:string,
     *   snapshot_message:string
     * }
     */
    public static function get_exam_snapshot_diagnostics(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        $settings = self::snapshot_redis_settings();
        $redis = self::snapshot_redis();
        $question_rows = $exam_id > 0 ? self::get_exam_question_rows($exam_id) : [];
        $question_count = count($question_rows);
        $preview_items = [];
        $ready_count = 0;
        $missing_count = 0;
        $invalid_count = 0;
        $payload_bytes_total = 0;

        if ($exam_id <= 0) {
            return [
                'exam_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'question_count' => 0,
                'ready_count' => 0,
                'missing_count' => 0,
                'invalid_count' => 0,
                'payload_bytes_total' => 0,
                'preview_items' => [],
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'idle',
                'snapshot_message' => 'Pilih exam dulu untuk memeriksa submission context.',
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'exam_id' => $exam_id,
                'redis_available' => false,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'question_count' => $question_count,
                'ready_count' => 0,
                'missing_count' => $question_count,
                'invalid_count' => 0,
                'payload_bytes_total' => 0,
                'preview_items' => [],
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Redis submission context tidak tersedia.',
            ];
        }

        if ($question_count <= 0) {
            return [
                'exam_id' => $exam_id,
                'redis_available' => true,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'question_count' => 0,
                'ready_count' => 0,
                'missing_count' => 0,
                'invalid_count' => 0,
                'payload_bytes_total' => 0,
                'preview_items' => [],
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'idle',
                'snapshot_message' => 'Belum ada soal aktif pada exam ini.',
            ];
        }

        $catalog_version = self::catalog_version();
        $exam_version = self::exam_version($exam_id);

        foreach ($question_rows as $question_row) {
            $item = self::build_exam_snapshot_item_diagnostics(
                (int) ($question_row['id'] ?? 0),
                $exam_id,
                (string) ($question_row['question_type'] ?? ''),
                $catalog_version,
                $exam_version,
                $redis
            );

            $payload_bytes_total += (int) ($item['payload_bytes'] ?? 0);
            $status = sanitize_key((string) ($item['status'] ?? 'miss'));
            if ($status === 'ready') {
                $ready_count++;
            } elseif ($status === 'invalid') {
                $invalid_count++;
            } else {
                $missing_count++;
            }

            if (count($preview_items) < 10) {
                $preview_items[] = [
                    'question_id' => (int) ($item['question_id'] ?? 0),
                    'question_type' => (string) ($item['question_type'] ?? ''),
                    'status' => $status,
                    'payload_bytes' => (int) ($item['payload_bytes'] ?? 0),
                ];
            }
        }

        $snapshot_exists = ($ready_count + $invalid_count) > 0;
        $snapshot_valid = $question_count > 0 && $ready_count === $question_count;
        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Submission context siap dipakai untuk submit jawaban dan scoring objektif.';
        } elseif ($invalid_count === $question_count) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Submission context ditemukan tetapi ada payload yang tidak valid untuk revision exam saat ini.';
        } elseif ($missing_count === $question_count) {
            $snapshot_status = 'miss';
            $snapshot_message = 'Submission context belum dipanaskan untuk exam ini.';
        } else {
            $snapshot_status = 'warning';
            $snapshot_message = sprintf(
                'Submission context parsial. READY %d/%d · MISS %d · INVALID %d.',
                $ready_count,
                $question_count,
                $missing_count,
                $invalid_count
            );
        }

        return [
            'exam_id' => $exam_id,
            'redis_available' => true,
            'redis_error' => self::$snapshot_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
            'question_count' => $question_count,
            'ready_count' => $ready_count,
            'missing_count' => $missing_count,
            'invalid_count' => $invalid_count,
            'payload_bytes_total' => $payload_bytes_total,
            'preview_items' => $preview_items,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_message' => $snapshot_message,
        ];
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<string,mixed>>
     */
    private static function read_redis_snapshots(array $question_ids, ?bool &$redis_available = null): array
    {
        $redis_available = false;
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if (empty($question_ids)) {
            return [];
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $redis_available = true;
        $catalog_version = self::catalog_version();
        $exam_version_cache = [];
        $snapshots = [];

        foreach ($question_ids as $question_id) {
            $pointer_key = self::pointer_key($question_id);
            $raw_pointer = $redis->get($pointer_key);
            if (!is_string($raw_pointer) || trim($raw_pointer) === '') {
                continue;
            }

            $pointer = json_decode($raw_pointer, true);
            if (!is_array($pointer)) {
                $redis->del($pointer_key);
                continue;
            }

            $exam_id = absint($pointer['exam_id'] ?? 0);
            $pointer_catalog_version = max(1, (int) ($pointer['catalog_version'] ?? 0));
            $pointer_exam_version = max(1, (int) ($pointer['exam_version'] ?? 0));
            $storage_key = is_scalar($pointer['storage_key'] ?? null) ? (string) $pointer['storage_key'] : '';

            if ($exam_id <= 0 || $storage_key === '') {
                $redis->del($pointer_key);
                continue;
            }

            if (!isset($exam_version_cache[$exam_id])) {
                $exam_version_cache[$exam_id] = self::exam_version($exam_id);
            }

            if (
                $pointer_catalog_version !== $catalog_version
                || $pointer_exam_version !== $exam_version_cache[$exam_id]
            ) {
                continue;
            }

            $raw_snapshot = $redis->get($storage_key);
            if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
                $redis->del($pointer_key);
                continue;
            }

            $decoded = json_decode($raw_snapshot, true);
            if (!is_array($decoded)) {
                $redis->del($pointer_key, $storage_key);
                continue;
            }

            $snapshot = self::sanitize_snapshot($decoded);
            if ($snapshot === null || (int) ($snapshot['id'] ?? 0) !== $question_id) {
                $redis->del($pointer_key, $storage_key);
                continue;
            }

            $snapshots[$question_id] = $snapshot;
            $redis->expire($pointer_key, self::SNAPSHOT_REDIS_TTL_SECONDS);
            $redis->expire($storage_key, self::SNAPSHOT_REDIS_TTL_SECONDS);
        }

        return $snapshots;
    }

    /**
     * @param array<int,array<string,mixed>> $snapshots
     */
    private static function write_redis_snapshots(array $snapshots): void
    {
        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis || empty($snapshots)) {
            return;
        }

        $catalog_version = self::catalog_version();
        $exam_version_cache = [];

        foreach ($snapshots as $snapshot) {
            $sanitized = self::sanitize_snapshot(is_array($snapshot) ? $snapshot : []);
            if ($sanitized === null) {
                continue;
            }

            $question_id = (int) ($sanitized['id'] ?? 0);
            $exam_id = (int) ($sanitized['exam_id'] ?? 0);
            if ($question_id <= 0 || $exam_id <= 0) {
                continue;
            }

            if (!isset($exam_version_cache[$exam_id])) {
                $exam_version_cache[$exam_id] = self::exam_version($exam_id);
            }

            $storage_key = self::storage_key($question_id, $exam_id, $catalog_version, $exam_version_cache[$exam_id]);
            $encoded_snapshot = wp_json_encode($sanitized);
            $encoded_pointer = wp_json_encode([
                'question_id' => $question_id,
                'exam_id' => $exam_id,
                'catalog_version' => $catalog_version,
                'exam_version' => $exam_version_cache[$exam_id],
                'storage_key' => $storage_key,
            ]);

            if (!is_string($encoded_snapshot) || $encoded_snapshot === '' || !is_string($encoded_pointer) || $encoded_pointer === '') {
                continue;
            }

            $redis->setEx($storage_key, self::SNAPSHOT_REDIS_TTL_SECONDS, $encoded_snapshot);
            $redis->setEx(self::pointer_key($question_id), self::SNAPSHOT_REDIS_TTL_SECONDS, $encoded_pointer);
        }
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<string,mixed>>
     */
    private static function hydrate_snapshots_from_db(array $question_ids): array
    {
        global $wpdb;

        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if (empty($question_ids)) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $question_true_false_table = $wpdb->prefix . 'cbt_question_true_false';
        $question_short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';
        $option_table = $wpdb->prefix . 'cbt_options';

        $ids_sql = implode(',', $question_ids);
        $question_rows = $wpdb->get_results(
            "SELECT q.id, q.exam_id, q.question_type, q.points, q.correct_text,
                    qtf.correct_value AS true_false_correct_value,
                    qsa.correct_text AS short_answer_correct_text
             FROM {$question_table} q
             LEFT JOIN {$question_true_false_table} qtf ON qtf.question_id = q.id
             LEFT JOIN {$question_short_answer_table} qsa ON qsa.question_id = q.id
             WHERE q.id IN ({$ids_sql})",
            ARRAY_A
        );

        if (!is_array($question_rows) || empty($question_rows)) {
            return [];
        }

        $question_types_by_id = [];
        foreach ($question_rows as $question_row) {
            $question_id = (int) ($question_row['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_types_by_id[$question_id] = (string) ($question_row['question_type'] ?? '');
        }

        $option_rows = [];
        $option_question_ids = array_values(array_filter(array_keys($question_types_by_id), static function (int $question_id) use ($question_types_by_id): bool {
            $type = (string) ($question_types_by_id[$question_id] ?? '');
            return in_array($type, ['multiple_choice', 'multiple_answer', 'true_false'], true);
        }));

        if (!empty($option_question_ids)) {
            $option_ids_sql = implode(',', array_map('intval', $option_question_ids));
            $option_rows = $wpdb->get_results(
                "SELECT id, question_id, option_text, is_correct
                 FROM {$option_table}
                 WHERE question_id IN ({$option_ids_sql})
                 ORDER BY question_id ASC, id ASC",
                ARRAY_A
            );
        }

        $options_by_question = [];
        foreach ((array) $option_rows as $option_row) {
            $question_id = (int) ($option_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            if (!isset($options_by_question[$question_id])) {
                $options_by_question[$question_id] = [];
            }

            $options_by_question[$question_id][] = [
                'id' => (int) ($option_row['id'] ?? 0),
                'option_text' => (string) ($option_row['option_text'] ?? ''),
                'is_correct' => (int) ($option_row['is_correct'] ?? 0),
            ];
        }

        $snapshots = [];
        foreach ($question_rows as $question_row) {
            $snapshot = self::build_snapshot_from_db_row((array) $question_row, (array) ($options_by_question[(int) ($question_row['id'] ?? 0)] ?? []));
            if ($snapshot === null) {
                continue;
            }

            $snapshots[(int) $snapshot['id']] = $snapshot;
        }

        return $snapshots;
    }

    /**
     * @return array<int,array{id:int,question_type:string}>
     */
    private static function get_exam_question_rows(int $exam_id): array
    {
        global $wpdb;

        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || !is_object($wpdb)) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $rows = $wpdb->get_results(
            "SELECT id, question_type
             FROM {$question_table}
             WHERE exam_id = {$exam_id}
               AND COALESCE(is_active, 1) = 1
             ORDER BY id ASC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $question_id = (int) ($row['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $items[] = [
                'id' => $question_id,
                'question_type' => sanitize_key((string) ($row['question_type'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @return array<int,int>
     */
    private static function get_exam_question_ids(int $exam_id): array
    {
        return array_values(array_map('intval', wp_list_pluck(self::get_exam_question_rows($exam_id), 'id')));
    }

    /**
     * @return array{question_id:int,question_type:string,status:string,payload_bytes:int}
     */
    private static function build_exam_snapshot_item_diagnostics(
        int $question_id,
        int $exam_id,
        string $question_type,
        int $catalog_version,
        int $exam_version,
        Redis $redis
    ): array {
        $status = 'miss';
        $payload_bytes = 0;
        $pointer_key = self::pointer_key($question_id);
        $raw_pointer = $redis->get($pointer_key);

        if (!is_string($raw_pointer) || trim($raw_pointer) === '') {
            return [
                'question_id' => $question_id,
                'question_type' => $question_type,
                'status' => $status,
                'payload_bytes' => 0,
            ];
        }

        $pointer = json_decode($raw_pointer, true);
        if (!is_array($pointer)) {
            return [
                'question_id' => $question_id,
                'question_type' => $question_type,
                'status' => 'invalid',
                'payload_bytes' => 0,
            ];
        }

        $pointer_exam_id = absint($pointer['exam_id'] ?? 0);
        $pointer_catalog_version = max(1, (int) ($pointer['catalog_version'] ?? 0));
        $pointer_exam_version = max(1, (int) ($pointer['exam_version'] ?? 0));
        $storage_key = is_scalar($pointer['storage_key'] ?? null) ? trim((string) $pointer['storage_key']) : '';
        if ($pointer_exam_id !== $exam_id || $storage_key === '') {
            return [
                'question_id' => $question_id,
                'question_type' => $question_type,
                'status' => 'invalid',
                'payload_bytes' => 0,
            ];
        }

        if ($pointer_catalog_version !== $catalog_version || $pointer_exam_version !== $exam_version) {
            return [
                'question_id' => $question_id,
                'question_type' => $question_type,
                'status' => 'invalid',
                'payload_bytes' => 0,
            ];
        }

        $raw_snapshot = $redis->get($storage_key);
        if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
            return [
                'question_id' => $question_id,
                'question_type' => $question_type,
                'status' => 'miss',
                'payload_bytes' => 0,
            ];
        }

        $payload_bytes = strlen($raw_snapshot);
        $decoded = json_decode($raw_snapshot, true);
        if (!is_array($decoded)) {
            return [
                'question_id' => $question_id,
                'question_type' => $question_type,
                'status' => 'invalid',
                'payload_bytes' => $payload_bytes,
            ];
        }

        $snapshot = self::sanitize_snapshot($decoded);
        $is_valid = is_array($snapshot)
            && (int) ($snapshot['id'] ?? 0) === $question_id
            && (int) ($snapshot['exam_id'] ?? 0) === $exam_id;

        return [
            'question_id' => $question_id,
            'question_type' => $question_type !== '' ? $question_type : (string) ($snapshot['question_type'] ?? ''),
            'status' => $is_valid ? 'ready' : 'invalid',
            'payload_bytes' => $payload_bytes,
        ];
    }

    /**
     * @param array<string,mixed> $question_row
     * @param array<int,array<string,mixed>> $options
     * @return array<string,mixed>|null
     */
    private static function build_snapshot_from_db_row(array $question_row, array $options): ?array
    {
        $question_id = absint($question_row['id'] ?? 0);
        $exam_id = absint($question_row['exam_id'] ?? 0);
        $question_type = sanitize_key((string) ($question_row['question_type'] ?? ''));
        if ($question_id <= 0 || $exam_id <= 0 || $question_type === '') {
            return null;
        }

        $correct_option_ids = [];
        $true_false_option_value_by_id = [];
        foreach ($options as $option) {
            $option_id = absint($option['id'] ?? 0);
            if ($option_id <= 0) {
                continue;
            }

            if ((int) ($option['is_correct'] ?? 0) === 1) {
                $correct_option_ids[] = $option_id;
            }

            if ($question_type === 'true_false') {
                $normalized_value = self::normalize_true_false_value((string) ($option['option_text'] ?? ''), true);
                if ($normalized_value !== null) {
                    $true_false_option_value_by_id[(string) $option_id] = $normalized_value;
                }
            }
        }

        $correct_option_ids = array_values(array_unique(array_filter(array_map('intval', $correct_option_ids), static function (int $option_id): bool {
            return $option_id > 0;
        })));
        sort($correct_option_ids);

        $true_false_correct_value = null;
        if ($question_type === 'true_false') {
            $correct_value_raw = $question_row['true_false_correct_value'] ?? null;
            if ($correct_value_raw !== null && $correct_value_raw !== '') {
                $true_false_correct_value = ((int) $correct_value_raw === 1) ? 1 : 0;
            } else {
                $legacy_correct = self::normalize_true_false_value((string) ($question_row['correct_text'] ?? ''), true);
                if ($legacy_correct !== null) {
                    $true_false_correct_value = $legacy_correct;
                } else {
                    foreach ($correct_option_ids as $option_id) {
                        $option_key = (string) $option_id;
                        if (array_key_exists($option_key, $true_false_option_value_by_id)) {
                            $true_false_correct_value = (int) $true_false_option_value_by_id[$option_key];
                            break;
                        }
                    }
                }
            }
        }

        $short_answer_values = [];
        if ($question_type === 'short_answer') {
            $short_answer_raw = trim((string) ($question_row['short_answer_correct_text'] ?? ''));
            if ($short_answer_raw === '') {
                $short_answer_raw = (string) ($question_row['correct_text'] ?? '');
            }
            $short_answer_values = CBT_Admin_Questions_Helper::normalize_short_answer_values($short_answer_raw);
        }

        $true_false_matrix_answers = [];
        if ($question_type === 'true_false_matrix') {
            $true_false_matrix_answers = self::normalize_true_false_matrix_answer_map((string) ($question_row['correct_text'] ?? ''));
        }

        return self::sanitize_snapshot([
            'id' => $question_id,
            'exam_id' => $exam_id,
            'question_type' => $question_type,
            'points' => (float) ($question_row['points'] ?? 0),
            'correct_option_ids' => $correct_option_ids,
            'true_false_correct_value' => $true_false_correct_value,
            'true_false_option_value_by_id' => $true_false_option_value_by_id,
            'short_answer_values' => $short_answer_values,
            'true_false_matrix_answers' => $true_false_matrix_answers,
        ]);
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>|null
     */
    private static function sanitize_snapshot(array $raw): ?array
    {
        $question_id = absint($raw['id'] ?? 0);
        $exam_id = absint($raw['exam_id'] ?? 0);
        $question_type = sanitize_key((string) ($raw['question_type'] ?? ''));
        if ($question_id <= 0 || $exam_id <= 0 || $question_type === '') {
            return null;
        }

        $correct_option_ids = [];
        if (isset($raw['correct_option_ids']) && is_array($raw['correct_option_ids'])) {
            $correct_option_ids = array_values(array_unique(array_filter(array_map('intval', $raw['correct_option_ids']), static function (int $option_id): bool {
                return $option_id > 0;
            })));
            sort($correct_option_ids);
        }

        $true_false_option_value_by_id = [];
        if (isset($raw['true_false_option_value_by_id']) && is_array($raw['true_false_option_value_by_id'])) {
            foreach ($raw['true_false_option_value_by_id'] as $option_id => $value) {
                $safe_option_id = absint($option_id);
                if ($safe_option_id <= 0) {
                    continue;
                }

                $normalized_value = self::normalize_true_false_value((string) $value, true);
                if ($normalized_value === null) {
                    if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
                        $normalized_value = (int) $value;
                    } else {
                        continue;
                    }
                }

                $true_false_option_value_by_id[(string) $safe_option_id] = $normalized_value;
            }
        }

        $short_answer_values = [];
        if (isset($raw['short_answer_values']) && is_array($raw['short_answer_values'])) {
            foreach ($raw['short_answer_values'] as $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                $safe_value = sanitize_text_field(trim((string) $value));
                if ($safe_value === '') {
                    continue;
                }

                $short_answer_values[] = $safe_value;
                if (count($short_answer_values) >= 8) {
                    break;
                }
            }
        }

        $true_false_matrix_answers = [];
        if (isset($raw['true_false_matrix_answers']) && is_array($raw['true_false_matrix_answers'])) {
            foreach ($raw['true_false_matrix_answers'] as $key => $value) {
                $safe_key = trim((string) $key);
                if ($safe_key === '' || !preg_match('/^\d+$/', $safe_key)) {
                    continue;
                }

                $normalized_value = (string) $value === 'false' ? 'false' : 'true';
                $true_false_matrix_answers[$safe_key] = $normalized_value;
                if (count($true_false_matrix_answers) >= 20) {
                    break;
                }
            }

            if (!empty($true_false_matrix_answers)) {
                uksort($true_false_matrix_answers, static function (string $left, string $right): int {
                    return ((int) $left) <=> ((int) $right);
                });
            }
        }

        $true_false_correct_value = null;
        if (array_key_exists('true_false_correct_value', $raw) && $raw['true_false_correct_value'] !== null && $raw['true_false_correct_value'] !== '') {
            $true_false_correct_value = ((int) $raw['true_false_correct_value'] === 1) ? 1 : 0;
        }

        return [
            'id' => $question_id,
            'exam_id' => $exam_id,
            'question_type' => $question_type,
            'points' => max(0.0, (float) ($raw['points'] ?? 0)),
            'correct_option_ids' => $correct_option_ids,
            'true_false_correct_value' => $true_false_correct_value,
            'true_false_option_value_by_id' => $true_false_option_value_by_id,
            'short_answer_values' => $short_answer_values,
            'true_false_matrix_answers' => $true_false_matrix_answers,
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function normalize_true_false_matrix_answer_map(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['statements']) && is_array($decoded['statements'])) {
            $candidates = $decoded['statements'];
        } else {
            $is_list = !empty($decoded) && array_keys($decoded) === range(0, count($decoded) - 1);
            $candidates = $is_list ? $decoded : [];
        }

        $answers = [];
        foreach ((array) $candidates as $candidate) {
            if (!is_array($candidate) || count($answers) >= 20) {
                continue;
            }

            $text = CBT_Admin_Questions_Helper::sanitize_editor_html(
                trim((string) ($candidate['text'] ?? $candidate['statement'] ?? $candidate['pernyataan'] ?? ''))
            );
            if (!CBT_Admin_Questions_Helper::has_non_empty_html_content($text)) {
                continue;
            }

            $answer_source = $candidate['answer'] ?? $candidate['correct'] ?? 'true';
            if (is_bool($answer_source)) {
                $answer_raw = $answer_source ? 'true' : 'false';
            } else {
                $answer_raw = (string) $answer_source;
            }

            $normalized_value = self::normalize_true_false_value($answer_raw, true);
            $answers[(string) (count($answers) + 1)] = ($normalized_value === 0) ? 'false' : 'true';
        }

        return $answers;
    }

    private static function normalize_true_false_value(string $value, bool $strict = false): ?int
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['true', '1', 't', 'yes', 'y', 'ya', 'benar'], true)) {
            return 1;
        }
        if (in_array($normalized, ['false', '0', 'f', 'no', 'n', 'tidak', 'salah'], true)) {
            return 0;
        }

        return $strict ? null : 1;
    }

    private static function catalog_version(): int
    {
        $catalog_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        return max(1, (int) ($catalog_entry['version'] ?? 1));
    }

    private static function exam_version(int $exam_id): int
    {
        $exam_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_exam($exam_id));
        return max(1, (int) ($exam_entry['version'] ?? 1));
    }

    private static function pointer_key(int $question_id): string
    {
        return self::SNAPSHOT_REDIS_PREFIX . 'pointer:question:' . max(0, $question_id);
    }

    private static function storage_key(int $question_id, int $exam_id, int $catalog_version, int $exam_version): string
    {
        return self::SNAPSHOT_REDIS_PREFIX
            . 'question:' . max(0, $question_id)
            . ':exam:' . max(0, $exam_id)
            . ':catalog:' . max(1, $catalog_version)
            . ':version:' . max(1, $exam_version);
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

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis submission context gagal.');
            }

            self::$snapshot_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$snapshot_redis_last_connection_error = 'Koneksi submission context Redis gagal: ' . $throwable->getMessage();
            self::$snapshot_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function snapshot_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::SNAPSHOT_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::SNAPSHOT_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::SNAPSHOT_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::SNAPSHOT_REDIS_DEFAULT_DATABASE - 1);
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
            'host' => $host !== '' ? $host : self::SNAPSHOT_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::SNAPSHOT_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $name, $default = null)
    {
        if (!defined($name)) {
            return $default;
        }

        $value = constant($name);
        return is_scalar($value) || $value === null ? $value : $default;
    }
}
