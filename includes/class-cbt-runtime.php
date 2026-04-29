<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Active_Attempt_Index')) {
    require_once __DIR__ . '/class-cbt-active-attempt-index.php';
}

if (!class_exists('CBT_Live_Proctoring_Presence')) {
    require_once __DIR__ . '/class-cbt-live-proctoring-presence.php';
}

if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
    require_once __DIR__ . '/class-cbt-live-attempt-roster-index.php';
}

class CBT_Runtime
{
    public const CRON_HOOK = 'cbt_runtime_flush_pending';

    private const CRON_SCHEDULE = 'cbt_runtime_every_minute';
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = 6379;
    private const DEFAULT_DATABASE = 2;
    private const DEFAULT_PREFIX = 'cbt_runtime:';
    private const DEFAULT_TIMEOUT = 1.5;
    private const FLUSH_DELAY_SECONDS = 5;
    private const FLUSH_THRESHOLD = 10;
    private const FLUSH_BATCH_LIMIT = 200;
    private const FLUSH_DUE_CRON_LIMIT = 100;
    private const FLUSH_LOCK_TTL = 20;
    private const FINISH_LOCK_TTL = 45;
    private const MAX_ATTEMPT_TTL = 172800;
    private const ATTEMPT_TTL_EXTENSION = 7200;

    /** @var Redis|false|null */
    private static $redis = null;

    /** @var bool */
    private static $redis_connection_attempted = false;

    /** @var string */
    private static $last_connection_error = '';

    /** @var string|null */
    private static $cached_prefix = null;

    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        add_action(self::CRON_HOOK, [self::class, 'handle_cron_flush']);
        self::ensure_flush_event();
    }

    public static function activate(): void
    {
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        self::ensure_flush_event();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'CBT Runtime Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_flush(): void
    {
        self::flush_due_attempts(self::runtime_flush_due_cron_limit());
        self::prune_stale_active_attempts();
    }

    public static function ensure_flush_event(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    public static function is_buffer_enabled(): bool
    {
        return self::constant_bool('CBT_RUNTIME_BUFFER_ENABLED', true);
    }

    public static function fallback_to_db_enabled(): bool
    {
        return self::constant_bool('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
    }

    public static function redis_extension_available(): bool
    {
        return class_exists('Redis');
    }

    public static function is_ready(): bool
    {
        return self::redis() instanceof Redis;
    }

    public static function has_attempt_state(int $attempt_id): bool
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return false;
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $answers_count = (int) $redis->hLen(self::attempt_answers_key($attempt_id));
        $dirty_count = (int) $redis->zCard(self::attempt_dirty_key($attempt_id));
        $meta_exists = (int) $redis->exists(self::attempt_meta_key($attempt_id));

        return $meta_exists > 0 || $answers_count > 0 || $dirty_count > 0;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<int,array<string,mixed>> $answer_rows
     */
    public static function ensure_attempt_state(array $attempt, int $duration_minutes, array $answer_rows = []): bool
    {
        $attempt_id = (int) ($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return false;
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $ttl = self::attempt_ttl($duration_minutes);
        $meta_key = self::attempt_meta_key($attempt_id);
        $answers_key = self::attempt_answers_key($attempt_id);
        $dirty_key = self::attempt_dirty_key($attempt_id);
        $question_order_key = self::attempt_question_order_key($attempt_id);
        $option_order_key = self::attempt_option_order_key($attempt_id);
        $question_order_ids = self::normalize_question_order_ids($attempt['question_order'] ?? []);
        $option_order_map = self::normalize_attempt_option_order($attempt['option_order'] ?? []);

        $meta = self::decode_json_string((string) $redis->get($meta_key));
        $last_flushed_at = is_array($meta) ? (int) ($meta['last_flushed_at'] ?? 0) : 0;
        $last_touch_at = is_array($meta) ? (int) ($meta['last_touch_at'] ?? 0) : 0;

        $runtime_meta = [
            'attempt_id' => $attempt_id,
            'exam_id' => (int) ($attempt['exam_id'] ?? 0),
            'student_id' => (int) ($attempt['student_id'] ?? 0),
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
            'duration_minutes' => max(1, $duration_minutes),
            'last_touch_at' => max(time(), $last_touch_at),
            'last_flushed_at' => $last_flushed_at,
        ];

        $redis->setEx($meta_key, $ttl, self::encode_json_string($runtime_meta));
        $redis->zAdd(self::active_attempts_key(), time(), (string) $attempt_id);
        if (!empty($question_order_ids)) {
            $redis->setEx($question_order_key, $ttl, self::encode_json_string($question_order_ids));
        } elseif ((int) $redis->exists($question_order_key) > 0) {
            $redis->expire($question_order_key, $ttl);
        }
        if (!empty($option_order_map)) {
            $redis->setEx($option_order_key, $ttl, self::encode_json_string($option_order_map));
        } elseif ((int) $redis->exists($option_order_key) > 0) {
            $redis->expire($option_order_key, $ttl);
        }

        if ((int) $redis->exists($answers_key) === 0 && !empty($answer_rows)) {
            $encoded_rows = [];
            foreach ($answer_rows as $answer_row) {
                $question_id = (int) ($answer_row['question_id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }

                $payload = self::normalize_stored_entry([
                    'question_id' => $question_id,
                    'selected_option_ids' => (string) ($answer_row['selected_option_ids'] ?? ''),
                    'answer_text' => (string) ($answer_row['answer_text'] ?? ''),
                    'is_correct' => $answer_row['is_correct'] ?? null,
                    'score_awarded' => (float) ($answer_row['score_awarded'] ?? 0),
                    'answered_at' => (string) ($answer_row['answered_at'] ?? ''),
                    'clear' => 0,
                    'answer' => null,
                ]);

                $encoded_rows[(string) $question_id] = self::encode_json_string($payload);
            }

            if (!empty($encoded_rows)) {
                $redis->hMSet($answers_key, $encoded_rows);
            }
        }

        $redis->expire($answers_key, $ttl);
        $redis->expire($dirty_key, $ttl);
        if ((int) $redis->exists($question_order_key) > 0) {
            $redis->expire($question_order_key, $ttl);
        }
        if ((int) $redis->exists($option_order_key) > 0) {
            $redis->expire($option_order_key, $ttl);
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_attempt_meta(int $attempt_id, ?bool &$state_found = null): array
    {
        $state_found = false;
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $meta_key = self::attempt_meta_key($attempt_id);
        $meta = self::decode_json_string((string) $redis->get($meta_key));
        if (!is_array($meta)) {
            return [];
        }

        $state_found = true;
        $duration_minutes = max(1, (int) ($meta['duration_minutes'] ?? 60));
        self::update_meta_touch($redis, $attempt_id, $duration_minutes, $meta, (int) ($meta['last_flushed_at'] ?? 0));

        return [
            'attempt_id' => $attempt_id,
            'exam_id' => (int) ($meta['exam_id'] ?? 0),
            'student_id' => (int) ($meta['student_id'] ?? 0),
            'status' => (string) ($meta['status'] ?? 'in_progress'),
            'started_at' => (string) ($meta['started_at'] ?? ''),
            'extra_time_minutes' => max(0, (int) ($meta['extra_time_minutes'] ?? 0)),
            'duration_minutes' => $duration_minutes,
            'last_touch_at' => max(0, (int) ($meta['last_touch_at'] ?? 0)),
            'last_flushed_at' => max(0, (int) ($meta['last_flushed_at'] ?? 0)),
        ];
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<int,array<string,mixed>> $entries
     * @return array<string,int>
     */
    public static function buffer_entries(array $attempt, int $duration_minutes, array $entries): array
    {
        $attempt_id = (int) ($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return [
                'runtime_used' => 0,
                'buffered' => 0,
                'flushed' => 0,
                'pending_count' => 0,
            ];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [
                'runtime_used' => 0,
                'buffered' => 0,
                'flushed' => 0,
                'pending_count' => 0,
            ];
        }

        self::ensure_attempt_state($attempt, $duration_minutes);

        $ttl = self::attempt_ttl($duration_minutes);
        $answers_key = self::attempt_answers_key($attempt_id);
        $dirty_key = self::attempt_dirty_key($attempt_id);
        $buffered = 0;

        foreach (self::dedupe_entries_by_question($entries) as $entry) {
            $question_id = (int) ($entry['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            if (!empty($entry['clear'])) {
                $redis->hDel($answers_key, (string) $question_id);
            } else {
                $redis->hSet(
                    $answers_key,
                    (string) $question_id,
                    self::encode_json_string(self::normalize_stored_entry($entry))
                );
            }

            $redis->zAdd($dirty_key, time(), (string) $question_id);
            $buffered++;
        }

        $pending_count = (int) $redis->zCard($dirty_key);
        self::update_meta_touch($redis, $attempt_id, $duration_minutes, $attempt, 0);
        $redis->expire($answers_key, $ttl);
        $redis->expire($dirty_key, $ttl);
        self::schedule_attempt_flush($redis, $attempt_id, self::FLUSH_DELAY_SECONDS);

        $flushed = 0;
        if ($pending_count >= self::FLUSH_THRESHOLD || self::oldest_dirty_age($redis, $attempt_id) >= self::FLUSH_DELAY_SECONDS) {
            $flush_result = self::flush_attempt($attempt_id);
            $flushed = (int) ($flush_result['flushed'] ?? 0);
            $pending_count = (int) ($flush_result['pending_count'] ?? $pending_count);
        } else {
            $pending_count = (int) $redis->zCard($dirty_key);
        }

        return [
            'runtime_used' => 1,
            'buffered' => $buffered,
            'flushed' => $flushed,
            'pending_count' => $pending_count,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_existing_answers_map(int $attempt_id, ?bool &$state_found = null): array
    {
        $state_found = false;
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $state_found = self::has_attempt_state($attempt_id);
        if (!$state_found) {
            return [];
        }

        $answer_rows = $redis->hGetAll(self::attempt_answers_key($attempt_id));
        if (!is_array($answer_rows) || empty($answer_rows)) {
            return [];
        }

        $items = [];
        foreach ($answer_rows as $question_id => $encoded) {
            $decoded = self::decode_json_string((string) $encoded);
            $question_id_int = (int) $question_id;
            if ($question_id_int <= 0 || !is_array($decoded)) {
                continue;
            }

            $items[$question_id_int] = self::normalize_stored_entry($decoded);
        }

        return $items;
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<string,mixed>>
     */
    public static function get_existing_answers_for_questions(int $attempt_id, array $question_ids, ?bool &$state_found = null): array
    {
        $state_found = false;
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $question_ids = array_values(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        if (empty($question_ids)) {
            return [];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $state_found = self::has_attempt_state($attempt_id);
        if (!$state_found) {
            return [];
        }

        $raw_rows = $redis->hMGet(
            self::attempt_answers_key($attempt_id),
            array_map(static function (int $question_id): string {
                return (string) $question_id;
            }, $question_ids)
        );
        if (!is_array($raw_rows) || empty($raw_rows)) {
            return [];
        }

        $items = [];
        foreach ($question_ids as $question_id) {
            $encoded = array_key_exists((string) $question_id, $raw_rows)
                ? $raw_rows[(string) $question_id]
                : null;
            $decoded = self::decode_json_string(is_scalar($encoded) ? (string) $encoded : '');
            if (!is_array($decoded)) {
                continue;
            }

            $items[$question_id] = self::normalize_stored_entry($decoded);
        }

        return $items;
    }

    /**
     * @return array<int,int>
     */
    public static function get_attempt_question_order(int $attempt_id, ?bool &$state_found = null): array
    {
        $state_found = false;
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $encoded = (string) $redis->get(self::attempt_question_order_key($attempt_id));
        $state_found = ($encoded !== '');
        if (!$state_found) {
            return [];
        }

        return self::normalize_question_order_ids($encoded);
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function get_attempt_option_order(int $attempt_id, ?bool &$state_found = null): array
    {
        $state_found = false;
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $encoded = (string) $redis->get(self::attempt_option_order_key($attempt_id));
        $state_found = ($encoded !== '');
        if (!$state_found) {
            return [];
        }

        return self::normalize_attempt_option_order($encoded);
    }

    /**
     * @return array<string,int>
     */
    public static function flush_attempt(int $attempt_id, bool $force = false): array
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [
                'runtime_used' => 0,
                'flushed' => 0,
                'pending_count' => 0,
            ];
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [
                'runtime_used' => 0,
                'flushed' => 0,
                'pending_count' => 0,
            ];
        }

        $lock_token = self::acquire_named_lock(self::flush_lock_key($attempt_id), self::FLUSH_LOCK_TTL);
        if ($lock_token === '') {
            return [
                'runtime_used' => 1,
                'flushed' => 0,
                'pending_count' => (int) $redis->zCard(self::attempt_dirty_key($attempt_id)),
            ];
        }

        try {
            global $wpdb;

            $attempt_table = $wpdb->prefix . 'cbt_attempts';
            $attempt_exists = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$attempt_table} WHERE id = %d", $attempt_id)
            );
            if ($attempt_exists <= 0) {
                self::clear_attempt_runtime($attempt_id);
                return [
                    'runtime_used' => 1,
                    'flushed' => 0,
                    'pending_count' => 0,
                ];
            }

            $dirty_members = $redis->zRange(self::attempt_dirty_key($attempt_id), 0, $force ? -1 : (self::FLUSH_BATCH_LIMIT - 1));
            if (!is_array($dirty_members) || empty($dirty_members)) {
                $redis->zRem(self::flush_due_key(), (string) $attempt_id);
                return [
                    'runtime_used' => 1,
                    'flushed' => 0,
                    'pending_count' => 0,
                ];
            }

            $question_ids = array_values(array_filter(array_map('intval', $dirty_members), static function (int $question_id): bool {
                return $question_id > 0;
            }));
            if (empty($question_ids)) {
                $redis->zRem(self::flush_due_key(), (string) $attempt_id);
                return [
                    'runtime_used' => 1,
                    'flushed' => 0,
                    'pending_count' => 0,
                ];
            }

            $raw_entries = $redis->hMGet(
                self::attempt_answers_key($attempt_id),
                array_map(static function (int $question_id): string {
                    return (string) $question_id;
                }, $question_ids)
            );

            $entries = [];
            foreach ($question_ids as $question_id) {
                $encoded = is_array($raw_entries) && array_key_exists((string) $question_id, $raw_entries)
                    ? $raw_entries[(string) $question_id]
                    : null;
                $decoded = self::decode_json_string(is_scalar($encoded) ? (string) $encoded : '');

                if (is_array($decoded)) {
                    $decoded['question_id'] = $question_id;
                    $entries[] = self::normalize_stored_entry($decoded);
                } else {
                    $entries[] = [
                        'question_id' => $question_id,
                        'selected_option_ids' => '',
                        'answer_text' => '',
                        'is_correct' => null,
                        'score_awarded' => 0.0,
                        'answered_at' => current_time('mysql'),
                        'clear' => 1,
                        'answer' => null,
                    ];
                }
            }

            $persisted = self::persist_entries_to_db($attempt_id, $entries);
            if (is_wp_error($persisted)) {
                return [
                    'runtime_used' => 1,
                    'flushed' => 0,
                    'pending_count' => (int) $redis->zCard(self::attempt_dirty_key($attempt_id)),
                ];
            }

            foreach ($question_ids as $question_id) {
                $redis->zRem(self::attempt_dirty_key($attempt_id), (string) $question_id);
            }

            $pending_count = (int) $redis->zCard(self::attempt_dirty_key($attempt_id));
            if ($pending_count > 0) {
                self::schedule_attempt_flush($redis, $attempt_id, self::FLUSH_DELAY_SECONDS);
            } else {
                $redis->zRem(self::flush_due_key(), (string) $attempt_id);
            }

            $meta = self::decode_json_string((string) $redis->get(self::attempt_meta_key($attempt_id)));
            $duration_minutes = is_array($meta) ? (int) ($meta['duration_minutes'] ?? 0) : 0;
            $attempt_meta = [
                'id' => $attempt_id,
                'exam_id' => is_array($meta) ? (int) ($meta['exam_id'] ?? 0) : 0,
                'student_id' => is_array($meta) ? (int) ($meta['student_id'] ?? 0) : 0,
                'status' => is_array($meta) ? (string) ($meta['status'] ?? 'in_progress') : 'in_progress',
                'started_at' => is_array($meta) ? (string) ($meta['started_at'] ?? '') : '',
                'extra_time_minutes' => is_array($meta) ? (int) ($meta['extra_time_minutes'] ?? 0) : 0,
            ];
            self::update_meta_touch($redis, $attempt_id, $duration_minutes, $attempt_meta, time());
            CBT_Cache::invalidate_attempt($attempt_id);

            return [
                'runtime_used' => 1,
                'flushed' => count($question_ids),
                'pending_count' => $pending_count,
            ];
        } finally {
            self::release_named_lock(self::flush_lock_key($attempt_id), $lock_token);
        }
    }

    /**
     * @return array<string,int>
     */
    public static function flush_due_attempts(int $limit = 10): array
    {
        $limit = max(1, $limit);
        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return [
                'runtime_used' => 0,
                'attempts_flushed' => 0,
                'answers_flushed' => 0,
            ];
        }

        $due_attempts = $redis->zRangeByScore(
            self::flush_due_key(),
            '-inf',
            (string) time(),
            ['limit' => [0, $limit]]
        );
        if (!is_array($due_attempts) || empty($due_attempts)) {
            return [
                'runtime_used' => 1,
                'attempts_flushed' => 0,
                'answers_flushed' => 0,
            ];
        }

        $attempts_flushed = 0;
        $answers_flushed = 0;
        foreach ($due_attempts as $attempt_id_raw) {
            $attempt_id = (int) $attempt_id_raw;
            if ($attempt_id <= 0) {
                continue;
            }

            $result = self::flush_attempt($attempt_id);
            $attempts_flushed++;
            $answers_flushed += (int) ($result['flushed'] ?? 0);
        }

        return [
            'runtime_used' => 1,
            'attempts_flushed' => $attempts_flushed,
            'answers_flushed' => $answers_flushed,
        ];
    }

    private static function runtime_flush_due_cron_limit(): int
    {
        return self::normalize_runtime_flush_due_cron_limit(
            apply_filters('cbt_runtime_flush_due_cron_limit', self::FLUSH_DUE_CRON_LIMIT)
        );
    }

    /**
     * @param mixed $limit
     */
    private static function normalize_runtime_flush_due_cron_limit($limit): int
    {
        $limit = (int) $limit;
        return max(1, min(1000, $limit));
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return int|WP_Error
     */
    public static function persist_entries_to_db(int $attempt_id, array $entries)
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_attempt_id', 'Attempt ID tidak valid.');
        }

        global $wpdb;

        $answer_table = $wpdb->prefix . 'cbt_answers';
        $deduped_entries = self::dedupe_entries_by_question($entries);
        $delete_ids = [];
        $upserts = [];

        foreach ($deduped_entries as $entry) {
            $question_id = (int) ($entry['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            if (!empty($entry['clear'])) {
                $delete_ids[] = $question_id;
                continue;
            }

            $upserts[] = self::normalize_stored_entry($entry);
        }

        if (!empty($delete_ids)) {
            $placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));
            $query = $wpdb->prepare(
                "DELETE FROM {$answer_table}
                 WHERE attempt_id = %d
                   AND question_id IN ({$placeholders})",
                array_merge([$attempt_id], $delete_ids)
            );
            $deleted = $wpdb->query($query);
            if ($deleted === false) {
                return new WP_Error('db_failed', 'Failed to clear answer batch');
            }
        }

        if (!empty($upserts)) {
            $values_sql = [];
            $params = [];
            $now = current_time('mysql');

            foreach ($upserts as $entry) {
                $values_sql[] = '(%d, %d, NULLIF(%s, \'\'), NULLIF(%s, \'\'), NULLIF(%d, -1), %f, %s, %s, %s)';
                $params[] = $attempt_id;
                $params[] = (int) ($entry['question_id'] ?? 0);
                $params[] = (string) ($entry['selected_option_ids'] ?? '');
                $params[] = (string) ($entry['answer_text'] ?? '');
                $is_correct = $entry['is_correct'];
                $params[] = ($is_correct === null || $is_correct === '') ? -1 : (int) $is_correct;
                $params[] = (float) ($entry['score_awarded'] ?? 0);
                $params[] = (string) ($entry['answered_at'] ?? $now);
                $params[] = $now;
                $params[] = $now;
            }

            $sql = "INSERT INTO {$answer_table}
                (attempt_id, question_id, selected_option_ids, answer_text, is_correct, score_awarded, answered_at, created_at, updated_at)
                VALUES " . implode(', ', $values_sql) . "
                ON DUPLICATE KEY UPDATE
                    selected_option_ids = VALUES(selected_option_ids),
                    answer_text = VALUES(answer_text),
                    is_correct = VALUES(is_correct),
                    score_awarded = VALUES(score_awarded),
                    answered_at = VALUES(answered_at),
                    updated_at = VALUES(updated_at)";

            $prepared = $wpdb->prepare($sql, $params);
            $saved = $wpdb->query($prepared);
            if ($saved === false) {
                return new WP_Error('db_failed', 'Failed to save answer batch');
            }
        }

        return count($deduped_entries);
    }

    public static function acquire_finish_lock(int $attempt_id, int $ttl = self::FINISH_LOCK_TTL): string
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return '';
        }

        return self::acquire_named_lock(self::finish_lock_key($attempt_id), max(5, $ttl));
    }

    public static function release_finish_lock(int $attempt_id, string $token): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0 || $token === '') {
            return;
        }

        self::release_named_lock(self::finish_lock_key($attempt_id), $token);
    }

    public static function clear_attempt_runtime(int $attempt_id): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $attempt_meta = self::decode_json_string((string) $redis->get(self::attempt_meta_key($attempt_id)));
        $redis->del(
            self::attempt_meta_key($attempt_id),
            self::attempt_answers_key($attempt_id),
            self::attempt_dirty_key($attempt_id),
            self::attempt_question_order_key($attempt_id),
            self::attempt_option_order_key($attempt_id)
        );
        $redis->zRem(self::flush_due_key(), (string) $attempt_id);
        $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
        $redis->del(self::flush_lock_key($attempt_id), self::finish_lock_key($attempt_id));

        if (
            class_exists('CBT_Active_Attempt_Index')
            && is_array($attempt_meta)
            && (int) ($attempt_meta['student_id'] ?? 0) > 0
            && (int) ($attempt_meta['exam_id'] ?? 0) > 0
        ) {
            CBT_Active_Attempt_Index::clear_active_attempt(
                (int) ($attempt_meta['student_id'] ?? 0),
                (int) ($attempt_meta['exam_id'] ?? 0),
                $attempt_id
            );
        }

        if (class_exists('CBT_Security_Live_Counters')) {
            CBT_Security_Live_Counters::clear_attempt($attempt_id);
        }

        if (class_exists('CBT_Live_Proctoring_Presence')) {
            CBT_Live_Proctoring_Presence::clear_attempt($attempt_id);
        }

        if (class_exists('CBT_Live_Attempt_Roster_Index')) {
            CBT_Live_Attempt_Roster_Index::clear_attempt($attempt_id);
        }
    }

    /**
     * @param array<int,int> $option_id_map
     */
    public static function remap_active_attempt_answers_for_question(int $question_id, array $option_id_map = [], bool $clear_answer = false): int
    {
        $question_id = absint($question_id);
        if ($question_id <= 0) {
            return 0;
        }

        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        self::prune_stale_active_attempts();
        $attempt_ids = $redis->zRange(self::active_attempts_key(), 0, -1);
        if (!is_array($attempt_ids) || empty($attempt_ids)) {
            return 0;
        }

        $updated_count = 0;
        $now_unix = time();
        $now_mysql = current_time('mysql');

        foreach ($attempt_ids as $attempt_id_raw) {
            $attempt_id = (int) $attempt_id_raw;
            if ($attempt_id <= 0) {
                continue;
            }

            $answers_key = self::attempt_answers_key($attempt_id);
            $encoded_entry = $redis->hGet($answers_key, (string) $question_id);
            if (!is_scalar($encoded_entry) || (string) $encoded_entry === '') {
                continue;
            }

            $decoded_entry = self::decode_json_string((string) $encoded_entry);
            if (!is_array($decoded_entry)) {
                continue;
            }

            $entry = self::normalize_stored_entry($decoded_entry + ['question_id' => $question_id]);
            $has_changes = false;

            if ($clear_answer) {
                if (
                    (string) ($entry['selected_option_ids'] ?? '') !== '' ||
                    (string) ($entry['answer_text'] ?? '') !== '' ||
                    $entry['is_correct'] !== null ||
                    (float) ($entry['score_awarded'] ?? 0) !== 0.0
                ) {
                    $entry['selected_option_ids'] = '';
                    $entry['answer_text'] = '';
                    $entry['answer'] = null;
                    $entry['is_correct'] = null;
                    $entry['score_awarded'] = 0.0;
                    $entry['clear'] = 0;
                    $entry['answered_at'] = $now_mysql;
                    $has_changes = true;
                }
            } else {
                $selected_option_ids = self::decode_selected_option_ids_string((string) ($entry['selected_option_ids'] ?? ''));
                $remapped_option_ids = [];
                foreach ($selected_option_ids as $selected_option_id) {
                    $new_option_id = isset($option_id_map[$selected_option_id]) ? (int) $option_id_map[$selected_option_id] : 0;
                    if ($new_option_id > 0) {
                        $remapped_option_ids[] = $new_option_id;
                    }
                }

                $remapped_option_ids = array_values(array_unique($remapped_option_ids));
                sort($remapped_option_ids);
                $next_selected_option_ids = !empty($remapped_option_ids) ? (string) wp_json_encode($remapped_option_ids) : '';
                if ($next_selected_option_ids !== (string) ($entry['selected_option_ids'] ?? '')) {
                    $entry['selected_option_ids'] = $next_selected_option_ids;
                    $entry['is_correct'] = null;
                    $entry['score_awarded'] = 0.0;
                    $entry['answered_at'] = $now_mysql;
                    $has_changes = true;
                }
            }

            if (!$has_changes) {
                continue;
            }

            $redis->hSet(
                $answers_key,
                (string) $question_id,
                self::encode_json_string(self::normalize_stored_entry($entry))
            );
            $redis->zAdd(self::attempt_dirty_key($attempt_id), $now_unix, (string) $question_id);
            self::schedule_attempt_flush($redis, $attempt_id, self::FLUSH_DELAY_SECONDS);

            $meta = self::decode_json_string((string) $redis->get(self::attempt_meta_key($attempt_id)));
            $duration_minutes = is_array($meta) ? (int) ($meta['duration_minutes'] ?? 0) : 0;
            $attempt_meta = [
                'id' => $attempt_id,
                'exam_id' => is_array($meta) ? (int) ($meta['exam_id'] ?? 0) : 0,
                'student_id' => is_array($meta) ? (int) ($meta['student_id'] ?? 0) : 0,
                'status' => is_array($meta) ? (string) ($meta['status'] ?? 'in_progress') : 'in_progress',
                'started_at' => is_array($meta) ? (string) ($meta['started_at'] ?? '') : '',
            ];
            self::update_meta_touch($redis, $attempt_id, $duration_minutes, $attempt_meta, 0);
            CBT_Cache::invalidate_attempt($attempt_id);
            $updated_count++;
        }

        return $updated_count;
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_admin_overview(): array
    {
        $config = self::runtime_settings();
        $status = 'disabled';
        $message = 'CBT runtime buffer dimatikan melalui konfigurasi.';
        $probe_status = 'skipped';
        $pending_attempts = 0;
        $oldest_flush_age = 0;

        if (self::is_buffer_enabled()) {
            if (!self::redis_extension_available()) {
                $status = 'unavailable';
                $message = 'Ekstensi phpredis tidak tersedia pada runtime PHP ini.';
                $probe_status = 'failed';
            } else {
                $redis = self::redis();
                if ($redis instanceof Redis) {
                    $status = 'ready';
                    $message = 'Redis runtime CBT aktif untuk buffering jawaban dan flush terjadwal.';
                    $probe_status = 'passed';
                    $pending_attempts = (int) $redis->zCard(self::flush_due_key());
                    $oldest_flush_age = self::oldest_due_age($redis);
                } else {
                    $status = 'unavailable';
                    $message = self::$last_connection_error !== ''
                        ? self::$last_connection_error
                        : 'Runtime Redis CBT belum dapat terhubung.';
                    $probe_status = 'failed';
                }
            }
        }

        return [
            'enabled' => self::is_buffer_enabled() ? 1 : 0,
            'fallback_to_db' => self::fallback_to_db_enabled() ? 1 : 0,
            'extension_available' => self::redis_extension_available() ? 1 : 0,
            'ready' => ($status === 'ready') ? 1 : 0,
            'status' => $status,
            'message' => $message,
            'pending_attempts' => $pending_attempts,
            'oldest_flush_age' => $oldest_flush_age,
            'probe' => [
                'status' => $probe_status,
                'message' => $message,
                'tested_at' => time(),
            ],
            'config' => [
                'host' => (string) ($config['host_label'] ?? ''),
                'port' => (string) ($config['port'] ?? ''),
                'database' => (string) ($config['database'] ?? ''),
                'prefix' => (string) ($config['prefix'] ?? ''),
                'scheme' => (string) ($config['scheme'] ?? ''),
            ],
        ];
    }

    public static function get_active_user_count(int $window_seconds = 300): int
    {
        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $min_score = time() - $window_seconds;
        return (int) $redis->zCount(self::active_attempts_key(), (string) $min_score, '+inf');
    }

    public static function prune_stale_active_attempts(): void
    {
        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return;
        }

        // Hapus data tracking yang sudah expired (lebih tua dari MAX_ATTEMPT_TTL)
        $max_age = time() - self::MAX_ATTEMPT_TTL;
        $redis->zRemRangeByScore(self::active_attempts_key(), '-inf', (string) $max_age);
    }

    /**
     * @return Redis|null
     */
    private static function redis(): ?Redis
    {
        if (self::$redis_connection_attempted) {
            return (self::$redis instanceof Redis) ? self::$redis : null;
        }

        self::$redis_connection_attempted = true;
        self::$redis = false;
        self::$last_connection_error = '';

        if (!self::is_buffer_enabled()) {
            self::$last_connection_error = 'CBT runtime buffer disabled.';
            return null;
        }

        if (!self::redis_extension_available()) {
            self::$last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::runtime_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::DEFAULT_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::DEFAULT_HOST),
                    (int) ($config['port'] ?? self::DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::DEFAULT_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis runtime gagal.');
            }

            self::$redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$last_connection_error = 'Koneksi runtime Redis gagal: ' . $throwable->getMessage();
            self::$redis = false;
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function runtime_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::DEFAULT_DATABASE - 1);
            $database = max(0, $wp_database + 1);
        }

        $prefix = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PREFIX', ''));
        if ($prefix === '') {
            $prefix = self::DEFAULT_PREFIX;
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $scheme = 'tcp';
        $host_label = $host;
        if ($host !== '' && strpos($host, '/') === 0) {
            $scheme = 'unix';
            $host_label = 'unix://' . $host;
        }

        return [
            'host' => $host !== '' ? $host : self::DEFAULT_HOST,
            'host_label' => $host_label !== '' ? $host_label : self::DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'prefix' => self::normalize_prefix($prefix),
            'password' => $password,
            'timeout' => self::DEFAULT_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function attempt_meta_key(int $attempt_id): string
    {
        return self::prefixed_key('attempt:' . $attempt_id . ':meta');
    }

    private static function attempt_answers_key(int $attempt_id): string
    {
        return self::prefixed_key('attempt:' . $attempt_id . ':answers');
    }

    private static function attempt_question_order_key(int $attempt_id): string
    {
        return self::prefixed_key('attempt:' . $attempt_id . ':question_order');
    }

    private static function attempt_option_order_key(int $attempt_id): string
    {
        return self::prefixed_key('attempt:' . $attempt_id . ':option_order');
    }

    private static function attempt_dirty_key(int $attempt_id): string
    {
        return self::prefixed_key('attempt:' . $attempt_id . ':dirty');
    }

    private static function flush_due_key(): string
    {
        return self::prefixed_key('flush:due');
    }

    private static function active_attempts_key(): string
    {
        return self::prefixed_key('active_attempts');
    }

    private static function flush_lock_key(int $attempt_id): string
    {
        return self::prefixed_key('lock:flush:' . $attempt_id);
    }

    private static function finish_lock_key(int $attempt_id): string
    {
        return self::prefixed_key('lock:finish:' . $attempt_id);
    }

    private static function prefixed_key(string $suffix): string
    {
        if (self::$cached_prefix === null) {
            $settings = self::runtime_settings();
            self::$cached_prefix = (string) ($settings['prefix'] ?? self::DEFAULT_PREFIX);
        }

        return self::$cached_prefix . ltrim($suffix, ':');
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function update_meta_touch(Redis $redis, int $attempt_id, int $duration_minutes, array $attempt, int $last_flushed_at): void
    {
        $meta_key = self::attempt_meta_key($attempt_id);
        $meta = self::decode_json_string((string) $redis->get($meta_key));
        $ttl = self::attempt_ttl($duration_minutes > 0 ? $duration_minutes : (is_array($meta) ? (int) ($meta['duration_minutes'] ?? 0) : 0));

        $payload = [
            'attempt_id' => $attempt_id,
            'exam_id' => (int) ($attempt['exam_id'] ?? (is_array($meta) ? (int) ($meta['exam_id'] ?? 0) : 0)),
            'student_id' => (int) ($attempt['student_id'] ?? (is_array($meta) ? (int) ($meta['student_id'] ?? 0) : 0)),
            'status' => (string) ($attempt['status'] ?? (is_array($meta) ? (string) ($meta['status'] ?? 'in_progress') : 'in_progress')),
            'started_at' => (string) ($attempt['started_at'] ?? (is_array($meta) ? (string) ($meta['started_at'] ?? '') : '')),
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? (is_array($meta) ? (int) ($meta['extra_time_minutes'] ?? 0) : 0))),
            'duration_minutes' => max(1, $duration_minutes > 0 ? $duration_minutes : (is_array($meta) ? (int) ($meta['duration_minutes'] ?? 60) : 60)),
            'last_touch_at' => time(),
            'last_flushed_at' => $last_flushed_at > 0
                ? $last_flushed_at
                : (is_array($meta) ? (int) ($meta['last_flushed_at'] ?? 0) : 0),
        ];

        $redis->setEx($meta_key, $ttl, self::encode_json_string($payload));
        $redis->zAdd(self::active_attempts_key(), time(), (string) $attempt_id);
        $redis->expire(self::attempt_answers_key($attempt_id), $ttl);
        if ((int) $redis->exists(self::attempt_question_order_key($attempt_id)) > 0) {
            $redis->expire(self::attempt_question_order_key($attempt_id), $ttl);
        }
        if ((int) $redis->exists(self::attempt_option_order_key($attempt_id)) > 0) {
            $redis->expire(self::attempt_option_order_key($attempt_id), $ttl);
        }
        $redis->expire(self::attempt_dirty_key($attempt_id), $ttl);
    }

    /**
     * @param mixed $raw_question_order
     * @return array<int,int>
     */
    private static function normalize_question_order_ids($raw_question_order): array
    {
        $decoded = $raw_question_order;
        if (is_string($raw_question_order)) {
            $decoded = json_decode($raw_question_order, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static function (int $question_id): bool {
            return $question_id > 0;
        })));
    }

    /**
     * @param mixed $raw_option_order
     * @return array<int,array<int,string>>
     */
    private static function normalize_attempt_option_order($raw_option_order): array
    {
        $decoded = $raw_option_order;
        if (is_string($raw_option_order)) {
            $decoded = json_decode($raw_option_order, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $question_id => $item_order) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || !is_array($item_order)) {
                continue;
            }

            $tokens = [];
            $seen_tokens = [];
            foreach ($item_order as $item_token) {
                if (!is_scalar($item_token)) {
                    continue;
                }

                $token = trim((string) $item_token);
                if ($token === '' || isset($seen_tokens[$token])) {
                    continue;
                }

                $seen_tokens[$token] = true;
                $tokens[] = $token;
            }

            if (!empty($tokens)) {
                $normalized[$safe_question_id] = $tokens;
            }
        }

        return $normalized;
    }

    private static function schedule_attempt_flush(Redis $redis, int $attempt_id, int $delay_seconds): void
    {
        $delay_seconds = max(1, $delay_seconds);
        $redis->zAdd(self::flush_due_key(), time() + $delay_seconds, (string) $attempt_id);
    }

    private static function oldest_dirty_age(Redis $redis, int $attempt_id): int
    {
        $members = $redis->zRange(self::attempt_dirty_key($attempt_id), 0, 0, true);
        if (!is_array($members) || empty($members)) {
            return 0;
        }

        $first_score = (int) reset($members);
        if ($first_score <= 0) {
            return 0;
        }

        return max(0, time() - $first_score);
    }

    private static function oldest_due_age(Redis $redis): int
    {
        $members = $redis->zRange(self::flush_due_key(), 0, 0, true);
        if (!is_array($members) || empty($members)) {
            return 0;
        }

        $first_score = (int) reset($members);
        if ($first_score <= 0) {
            return 0;
        }

        return max(0, time() - $first_score);
    }

    private static function attempt_ttl(int $duration_minutes): int
    {
        $duration_minutes = max(1, $duration_minutes);
        $ttl = max(($duration_minutes * MINUTE_IN_SECONDS) + self::ATTEMPT_TTL_EXTENSION, self::ATTEMPT_TTL_EXTENSION);
        return min(self::MAX_ATTEMPT_TTL, $ttl);
    }

    private static function acquire_named_lock(string $key, int $ttl): string
    {
        $redis = self::redis();
        if (!$redis instanceof Redis) {
            return '';
        }

        $token = wp_generate_password(20, false, false);

        try {
            $result = $redis->set($key, $token, ['nx', 'ex' => max(1, $ttl)]);
            $acquired = ($result === true || strtoupper((string) $result) === 'OK');
            return $acquired ? $token : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }

    private static function release_named_lock(string $key, string $token): void
    {
        $redis = self::redis();
        if (!$redis instanceof Redis || $token === '') {
            return;
        }

        try {
            if (method_exists($redis, 'eval')) {
                $redis->eval(
                    "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end",
                    [$key, $token],
                    1
                );
                return;
            }
        } catch (Throwable $throwable) {
            // Fallback to direct delete below.
        }

        $redis->del($key);
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private static function dedupe_entries_by_question(array $entries): array
    {
        $deduped = [];
        foreach ($entries as $entry) {
            $question_id = (int) ($entry['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $deduped[$question_id] = $entry;
        }

        return array_values($deduped);
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    private static function normalize_stored_entry(array $entry): array
    {
        $is_correct = $entry['is_correct'] ?? null;
        if ($is_correct === '' || $is_correct === -1) {
            $is_correct = null;
        } elseif ($is_correct !== null) {
            $is_correct = ((int) $is_correct === 1) ? 1 : 0;
        }

        $selected_option_ids = trim((string) ($entry['selected_option_ids'] ?? ''));
        $answer_text = (string) ($entry['answer_text'] ?? '');

        return [
            'question_id' => (int) ($entry['question_id'] ?? 0),
            'answer' => self::normalize_raw_answer_value($entry['answer'] ?? null),
            'selected_option_ids' => $selected_option_ids,
            'answer_text' => $answer_text,
            'is_correct' => $is_correct,
            'score_awarded' => round((float) ($entry['score_awarded'] ?? 0), 2),
            'answered_at' => (string) ($entry['answered_at'] ?? current_time('mysql')),
            'clear' => !empty($entry['clear']) ? 1 : 0,
        ];
    }

    /**
     * @return int[]
     */
    private static function decode_selected_option_ids_string(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            if (is_numeric($raw)) {
                $decoded = [(int) $raw];
            } else {
                return [];
            }
        }

        $ids = array_values(array_filter(array_map('intval', $decoded), static function (int $option_id): bool {
            return $option_id > 0;
        }));
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function normalize_raw_answer_value($value)
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $constant_name, $default)
    {
        if (!defined($constant_name)) {
            return $default;
        }

        return constant($constant_name);
    }

    private static function constant_bool(string $constant_name, bool $default): bool
    {
        $value = self::constant_scalar($constant_name, $default);
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'off', 'no', ''], true);
    }

    private static function normalize_prefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return self::DEFAULT_PREFIX;
        }

        return rtrim($prefix, ':') . ':';
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>|null
     */
    private static function decode_json_string(string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function encode_json_string(array $value): string
    {
        $encoded = wp_json_encode($value);
        return is_string($encoded) ? $encoded : '{}';
    }
}
