<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Admin_Questions_Helper')) {
    require_once dirname(__DIR__) . '/admin/class-cbt-admin-questions-helper.php';
}

trait CBT_REST_Question_Snapshot_Helpers
{
    /**
     * @param array<string,mixed> $exam
     * @param array<string,mixed> $attempt
     * @param mixed $question_revision
     * @return WP_REST_Response|WP_Error|null
     */
    private static function build_bootstrap_light_question_window_response(
        array $exam,
        array $attempt,
        int $exam_id,
        int $attempt_id,
        int $offset,
        int $limit,
        bool $include_existing,
        bool $include_answer_manifest,
        $question_revision
    ) {
        $started_at = microtime(true);
        $contract = [];
        if (class_exists('CBT_Attempt_Question_Contract_Cache') && method_exists('CBT_Attempt_Question_Contract_Cache', 'read_cached_attempt_snapshot')) {
            $contract = CBT_Attempt_Question_Contract_Cache::read_cached_attempt_snapshot($attempt_id);
        }
        if (empty($contract)) {
            $bootstrap_lock_key = 'attempt_bootstrap:contract:' . $attempt_id;
            if (!CBT_Cache::acquire_lock($bootstrap_lock_key, 10, [
                'type' => 'attempt_question_contract_bootstrap_light',
                'attempt_id' => $attempt_id,
                'exam_id' => $exam_id,
            ])) {
                self::write_start_attempt_opening_state($exam_id, (int) ($attempt['student_id'] ?? 0), 'bootstrap_questions', 'question_window_pending', [
                    'attempt_id' => $attempt_id,
                    'retry_after_ms' => 1000,
                ]);
                self::record_start_attempt_phase('question_bootstrap_busy', $exam_id, (int) ($attempt['student_id'] ?? 0), [
                    'attempt_id' => $attempt_id,
                    'source' => 'bootstrap_light',
                    'duration_ms' => self::measure_elapsed_ms($started_at),
                ]);
                return new WP_Error(
                    'question_bootstrap_busy',
                    'Server masih menyiapkan soal pertama. Coba lagi sebentar.',
                    ['status' => 429, 'retry_after_ms' => 1000]
                );
            }

            try {
                $contract = self::build_lightweight_attempt_question_contract($attempt, $exam);
            } finally {
                CBT_Cache::release_lock($bootstrap_lock_key);
            }
        }
        if (empty($contract)) {
            $contract = self::build_lightweight_attempt_question_contract($attempt, $exam);
        }
        $question_order_ids = array_values(array_filter(array_map('intval', (array) ($contract['question_order_ids'] ?? [])), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        if ($exam_id <= 0 || $attempt_id <= 0 || empty($question_order_ids)) {
            return null;
        }

        $total_questions = count($question_order_ids);
        $limit = max(1, $limit);
        if ($total_questions > 0 && $offset >= $total_questions) {
            $offset = max(0, (int) (floor(($total_questions - 1) / $limit) * $limit));
        }

        $window_question_ids = array_slice($question_order_ids, max(0, $offset), $limit);
        if (empty($window_question_ids)) {
            return null;
        }

        $window_questions = self::get_question_payload_by_ids($exam_id, $window_question_ids);
        if (empty($window_questions)) {
            return null;
        }
        $window_questions = self::sanitize_question_delivery_payload($window_questions);

        $question_number_map = self::normalize_attempt_question_number_map(
            is_array($contract['question_number_map'] ?? null)
                ? $contract['question_number_map']
                : []
        );
        $option_order_map = self::normalize_attempt_option_order_map($contract['option_order_map'] ?? []);
        $question_manifest = is_array($contract['question_manifest'] ?? null)
            ? array_values(array_filter($contract['question_manifest'], 'is_array'))
            : [];
        if (empty($question_manifest)) {
            $question_manifest = self::build_minimal_question_manifest_from_order($question_order_ids, $question_number_map);
        }

        $window_questions = self::order_question_payload_by_ids($window_questions, $window_question_ids);
        if (!empty($option_order_map)) {
            $window_questions = self::apply_attempt_option_order_to_questions($window_questions, $option_order_map);
        }
        if (!empty($question_number_map)) {
            $window_questions = self::apply_question_numbers_to_payload($window_questions, $question_number_map);
        }

        $attempt_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $answered_question_ids = self::get_attempt_answered_question_ids(
            $attempt_id,
            $attempt,
            $attempt_duration_minutes,
            false
        );
        $existing_answers_map = [];
        if ($include_existing) {
            self::merge_existing_answers_into_question_payload($window_questions, $attempt_id, $window_question_ids);
        }
        if ($include_answer_manifest) {
            $existing_answers_map = self::build_attempt_existing_answers_map(
                $window_questions,
                $attempt_id,
                $attempt,
                $attempt_duration_minutes,
                false
            );
        }

        self::record_start_attempt_phase('question_window_fast_path', $exam_id, (int) ($attempt['student_id'] ?? 0), [
            'attempt_id' => $attempt_id,
            'duration_ms' => self::measure_elapsed_ms($started_at),
            'offset' => max(0, $offset),
            'limit' => $limit,
            'question_count' => count($window_questions),
        ]);
        self::record_start_attempt_phase('question_window_ready', $exam_id, (int) ($attempt['student_id'] ?? 0), [
            'attempt_id' => $attempt_id,
            'duration_ms' => self::measure_elapsed_ms($started_at),
            'source' => 'bootstrap_light',
        ]);
        self::write_start_attempt_opening_state($exam_id, (int) ($attempt['student_id'] ?? 0), 'ready', 'attempt_ready', [
            'attempt_id' => $attempt_id,
            'retry_after_ms' => 0,
        ]);

        return rest_ensure_response([
            'items' => $window_questions ?: [],
            'offset' => max(0, $offset),
            'limit' => $limit,
            'total_questions' => $total_questions,
            'has_next' => ($offset + count($window_questions)) < $total_questions,
            'question_order_ids' => $question_order_ids,
            'question_manifest' => $question_manifest,
            'answered_question_ids' => $answered_question_ids,
            'existing_answers_map' => $existing_answers_map,
            'archived_review_items' => [],
            'question_revision' => $question_revision,
            'question_order_signature' => (string) ($contract['question_order_signature'] ?? ''),
        ]);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array{question_count:int,question_order_signature:string,attempt:array<string,mixed>}
     */
    private static function build_attempt_question_sync_meta(array $attempt, array $exam, string $attempt_table): array
    {
        $exam_id = (int) ($exam['id'] ?? 0);
        if ($exam_id <= 0) {
            return [
                'question_count' => 0,
                'question_order_signature' => '',
                'attempt' => $attempt,
            ];
        }

        if (class_exists('CBT_Attempt_Question_Contract_Cache')) {
            $attempt_contract = CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot((int) ($attempt['id'] ?? 0), function () use ($attempt, $exam, $attempt_table): array {
                $contract = self::build_attempt_runtime_snapshot_contract($attempt, $exam, [], $attempt_table);
                return array_merge($contract, [
                    'attempt_id' => (int) ($attempt['id'] ?? 0),
                    'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                    'student_id' => (int) ($attempt['student_id'] ?? 0),
                    'status' => (string) ($attempt['status'] ?? ''),
                ]);
            });
            $contract_question_order_ids = array_values(array_filter(array_map('intval', (array) ($attempt_contract['question_order_ids'] ?? [])), static function (int $question_id): bool {
                return $question_id > 0;
            }));
            if (!empty($contract_question_order_ids)) {
                $attempt['question_order'] = (string) (wp_json_encode($contract_question_order_ids) ?: '[]');

                return [
                    'question_count' => count($contract_question_order_ids),
                    'question_order_signature' => (string) ($attempt_contract['question_order_signature'] ?? ''),
                    'attempt' => $attempt,
                ];
            }
        }

        $attempt_snapshot_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);
        if (!empty($attempt_snapshot_order_ids)) {
            $attempt_snapshot_order_json = wp_json_encode($attempt_snapshot_order_ids);
            if (is_string($attempt_snapshot_order_json)) {
                $attempt['question_order'] = $attempt_snapshot_order_json;
            }
        }

        $questions = self::get_cached_exam_question_payload($exam_id);
        $questions = self::append_missing_attempt_questions(
            $questions,
            $exam_id,
            (string) ($attempt['question_order'] ?? '')
        );
        $resolved_attempt_payload = self::resolve_attempt_question_payload(
            $questions,
            $attempt,
            $exam,
            $attempt_table
        );
        $resolved_question_order_ids = isset($resolved_attempt_payload['question_order_ids']) && is_array($resolved_attempt_payload['question_order_ids'])
            ? array_values(array_filter(array_map('intval', $resolved_attempt_payload['question_order_ids']), static function ($question_id): bool {
                return $question_id > 0;
            }))
            : [];
        $resolved_questions = isset($resolved_attempt_payload['questions']) && is_array($resolved_attempt_payload['questions'])
            ? $resolved_attempt_payload['questions']
            : [];

        return [
            'question_count' => !empty($resolved_question_order_ids) ? count($resolved_question_order_ids) : count($resolved_questions),
            'question_order_signature' => (string) ($resolved_attempt_payload['question_order_signature'] ?? ''),
            'attempt' => isset($resolved_attempt_payload['attempt']) && is_array($resolved_attempt_payload['attempt'])
                ? $resolved_attempt_payload['attempt']
                : $attempt,
        ];
    }
    private static function get_exam_duration_minutes(int $exam_id): int
    {
        global $wpdb;
        static $duration_cache = [];

        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return 60;
        }

        if (array_key_exists($exam_id, $duration_cache)) {
            return (int) $duration_cache[$exam_id];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $duration = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT duration_minutes
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            )
        );

        $duration_cache[$exam_id] = max(1, $duration);

        return (int) $duration_cache[$exam_id];
    }

    /**
     * @param array<string,mixed>|null $attempt
     */
    private static function resolve_attempt_duration_minutes(?array $attempt, int $exam_duration_minutes): int
    {
        $resolved_duration = max(1, $exam_duration_minutes > 0 ? $exam_duration_minutes : 60);
        if (!is_array($attempt)) {
            return $resolved_duration;
        }

        $extra_time_minutes = max(0, (int) ($attempt['extra_time_minutes'] ?? 0));
        return max(1, $resolved_duration + $extra_time_minutes);
    }

    private static function local_datetime_to_timestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->getTimestamp();
            }
        }

        try {
            $parsed = new DateTimeImmutable($value, $timezone);
            return $parsed->getTimestamp();
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed>|null $attempt
     * @return array<string,mixed>|null
     */
    private static function build_attempt_timer_payload(?array $attempt, int $exam_duration_minutes): ?array
    {
        if (!is_array($attempt)) {
            return null;
        }

        $attempt_id = (int) ($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return null;
        }

        $duration_minutes = self::resolve_attempt_duration_minutes($attempt, $exam_duration_minutes);
        $started_at = (string) ($attempt['started_at'] ?? '');
        $started_at_ts = self::local_datetime_to_timestamp($started_at);
        $remaining_seconds = max(0, $duration_minutes * MINUTE_IN_SECONDS);
        if ($started_at_ts !== null) {
            $remaining_seconds = max(0, ($started_at_ts + ($duration_minutes * MINUTE_IN_SECONDS)) - time());
        }

        return [
            'attempt_id' => $attempt_id,
            'status' => (string) ($attempt['status'] ?? ''),
            'started_at' => $started_at,
            'duration_minutes' => $duration_minutes,
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
            'remaining_seconds' => $remaining_seconds,
            'server_now' => current_time('mysql'),
        ];
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function ensure_runtime_attempt_state(array $attempt, int $duration_minutes): bool
    {
        if (!CBT_Runtime::is_ready()) {
            return false;
        }

        $attempt_id = (int) ($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return false;
        }

        if (CBT_Runtime::has_attempt_state($attempt_id)) {
            return CBT_Runtime::ensure_attempt_state($attempt, $duration_minutes);
        }

        $ensure_started_at = microtime(true);
        global $wpdb;
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $answer_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, selected_option_ids, answer_text, is_correct, score_awarded, answered_at
                 FROM {$answer_table}
                 WHERE attempt_id = %d",
                $attempt_id
            ),
            ARRAY_A
        );

        $result = CBT_Runtime::ensure_attempt_state($attempt, $duration_minutes, is_array($answer_rows) ? $answer_rows : []);
        self::record_start_attempt_phase('start_attempt_lazy_runtime_state', (int) ($attempt['exam_id'] ?? 0), (int) ($attempt['student_id'] ?? 0), [
            'attempt_id' => $attempt_id,
            'duration_minutes' => $duration_minutes,
            'answer_row_count' => is_array($answer_rows) ? count($answer_rows) : 0,
            'elapsed_ms' => self::measure_elapsed_ms($ensure_started_at),
            'ok' => $result ? 1 : 0,
        ]);

        return $result;
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $attempt
     * @param array<int,int> $question_ids
     */
    private static function merge_runtime_answers_into_question_payload(array &$questions, array $attempt, int $duration_minutes, array $question_ids = []): bool
    {
        $attempt_id = (int) ($attempt['id'] ?? 0);
        if ($attempt_id <= 0 || !CBT_Runtime::is_ready()) {
            return false;
        }

        self::ensure_runtime_attempt_state($attempt, $duration_minutes);
        if (!empty($question_ids)) {
            $runtime_answers = CBT_Runtime::get_existing_answers_for_questions($attempt_id, $question_ids, $state_found);
        } else {
            $runtime_answers = CBT_Runtime::get_existing_answers_map($attempt_id, $state_found);
        }
        if (!$state_found) {
            return false;
        }

        self::apply_existing_answer_map_to_question_payload($questions, $runtime_answers);
        return true;
    }
    private static function get_cached_exam_question_payload(int $exam_id): array
    {
        $payload = CBT_Cache::remember(
            'rest:questions:exam:' . $exam_id . ':static',
            12 * HOUR_IN_SECONDS,
            [CBT_Cache::namespace_exam($exam_id)],
            static function () use ($exam_id): array {
                return self::build_exam_question_payload_from_db($exam_id);
            }
        );

        return is_array($payload) ? $payload : [];
    }

    public static function warm_exam_question_delivery_snapshot(int $exam_id): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || !class_exists('CBT_Exam_Question_Delivery_Cache')) {
            return;
        }

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload($exam_id, static function (int $target_exam_id): array {
            return self::build_student_exam_question_delivery_payload_from_db($target_exam_id);
        });
    }

    public static function warm_exam_start_attempt_snapshot(int $exam_id): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            return;
        }

        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($exam_id, static function (int $target_exam_id): array {
            return self::build_exam_start_attempt_snapshot_from_db($target_exam_id);
        });
    }

    public static function warm_exam_submission_context_snapshot(int $exam_id): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || !class_exists('CBT_Question_Submission_Context_Cache')) {
            return;
        }

        CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
    }

    /**
     * @param array<int,int> $question_ids
     * @return array{success:bool,mode:string,reason:string,exam_id:int,question_ids:array<int,int>}
     */
    public static function refresh_exam_question_snapshots_after_question_updates(int $exam_id, array $question_ids): array
    {
        $exam_id = absint($exam_id);
        $question_ids = self::normalize_partial_snapshot_question_ids($question_ids);
        $default = [
            'success' => false,
            'mode' => 'skipped',
            'reason' => $exam_id > 0 ? 'empty_question_ids' : 'invalid_exam',
            'exam_id' => $exam_id,
            'question_ids' => $question_ids,
        ];
        if ($exam_id <= 0 || empty($question_ids)) {
            return $default;
        }

        if (
            !class_exists('CBT_Exam_Question_Delivery_Cache')
            || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')
        ) {
            return self::rebuild_exam_question_snapshots_after_partial_failure($exam_id, $question_ids, 'snapshot_cache_unavailable');
        }

        $lock_key = 'partial_question_snapshot:exam:' . $exam_id;
        if (!CBT_Cache::acquire_lock($lock_key, 15, [
            'type' => 'partial_question_snapshot',
            'exam_id' => $exam_id,
            'question_ids' => $question_ids,
        ])) {
            return self::rebuild_exam_question_snapshots_after_partial_failure($exam_id, $question_ids, 'lock_busy');
        }

        $fallback_reason = '';
        $partial_result = $default;

        try {
            $delivery_envelope = CBT_Exam_Question_Delivery_Cache::read_current_exam_payload_v2_index_envelope($exam_id);
            if (empty($delivery_envelope['success'])) {
                $fallback_reason = 'delivery_' . sanitize_key((string) ($delivery_envelope['reason'] ?? 'v2_index_unavailable'));
            }

            $start_envelope = [];
            if ($fallback_reason === '') {
                $start_envelope = CBT_Exam_Start_Attempt_Snapshot_Cache::read_current_exam_snapshot_v2_index_envelope($exam_id);
                if (empty($start_envelope['success'])) {
                    $fallback_reason = 'start_' . sanitize_key((string) ($start_envelope['reason'] ?? 'v2_index_unavailable'));
                }
            }

            $fragments = [];
            if ($fallback_reason === '') {
                $fragments = self::build_partial_question_snapshot_fragments($exam_id, $question_ids);
                if (empty($fragments['success'])) {
                    $fallback_reason = sanitize_key((string) ($fragments['reason'] ?? 'question_fragment_unavailable'));
                }
            }

            if ($fallback_reason === '') {
                CBT_Cache::invalidate_exam($exam_id);

                $delivery_written = CBT_Exam_Question_Delivery_Cache::write_current_exam_payload_v2_partial_index(
                    $exam_id,
                    (array) ($delivery_envelope['index'] ?? []),
                    (array) ($fragments['delivery_items_by_id'] ?? []),
                    (int) ($delivery_envelope['ttl_seconds'] ?? 0)
                );
                if (!$delivery_written) {
                    $fallback_reason = 'delivery_v2_write_failed';
                }
            }

            if ($fallback_reason === '') {
                $start_written = CBT_Exam_Start_Attempt_Snapshot_Cache::write_current_exam_snapshot_v2_partial_index(
                    $exam_id,
                    (array) ($start_envelope['index'] ?? []),
                    (array) ($fragments['start_fragments_by_id'] ?? []),
                    (int) ($start_envelope['ttl_seconds'] ?? 0)
                );
                if (!$start_written) {
                    $fallback_reason = 'start_v2_write_failed';
                }
            }

            if ($fallback_reason === '') {
                $partial_result = [
                    'success' => true,
                    'mode' => 'partial',
                    'reason' => 'patched',
                    'exam_id' => $exam_id,
                    'question_ids' => $question_ids,
                ];
            }
        } catch (Throwable $throwable) {
            $fallback_reason = 'partial_exception';
        } finally {
            CBT_Cache::release_lock($lock_key);
        }

        if ($fallback_reason !== '') {
            return self::rebuild_exam_question_snapshots_after_partial_failure($exam_id, $question_ids, $fallback_reason);
        }

        return $partial_result;
    }

    /**
     * @param array<int,int> $question_ids
     * @return array{success:bool,mode:string,reason:string,exam_id:int,question_ids:array<int,int>}
     */
    private static function rebuild_exam_question_snapshots_after_partial_failure(int $exam_id, array $question_ids, string $reason): array
    {
        $exam_id = absint($exam_id);
        $question_ids = self::normalize_partial_snapshot_question_ids($question_ids);
        if ($exam_id <= 0) {
            return [
                'success' => false,
                'mode' => 'skipped',
                'reason' => 'invalid_exam',
                'exam_id' => 0,
                'question_ids' => $question_ids,
            ];
        }

        CBT_Cache::invalidate_exam($exam_id);
        self::warm_exam_question_delivery_snapshot($exam_id);
        self::warm_exam_start_attempt_snapshot($exam_id);

        return [
            'success' => true,
            'mode' => 'full_rebuild',
            'reason' => sanitize_key($reason) ?: 'partial_unavailable',
            'exam_id' => $exam_id,
            'question_ids' => $question_ids,
        ];
    }

    /**
     * @param array<int,int> $question_ids
     * @return array{
     *   success:bool,
     *   reason:string,
     *   delivery_items_by_id:array<int,array<string,mixed>>,
     *   start_fragments_by_id:array<int,array{manifest:array<string,mixed>,option_tokens:array<int,string>,force_shuffle:bool}>
     * }
     */
    private static function build_partial_question_snapshot_fragments(int $exam_id, array $question_ids): array
    {
        $question_ids = self::normalize_partial_snapshot_question_ids($question_ids);
        $default = [
            'success' => false,
            'reason' => empty($question_ids) ? 'empty_question_ids' : 'question_not_found',
            'delivery_items_by_id' => [],
            'start_fragments_by_id' => [],
        ];
        if ($exam_id <= 0 || empty($question_ids)) {
            return $default;
        }

        $questions = self::get_question_payload_by_ids($exam_id, $question_ids);
        if (empty($questions)) {
            return $default;
        }

        $questions_by_id = [];
        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            if ((int) ($question['is_active'] ?? 1) !== 1) {
                $default['reason'] = 'question_inactive';
                return $default;
            }
            $questions_by_id[$question_id] = $question;
        }

        foreach ($question_ids as $question_id) {
            if (!isset($questions_by_id[$question_id])) {
                $default['reason'] = 'question_not_found';
                return $default;
            }
        }

        $delivery_items_by_id = [];
        foreach (self::sanitize_question_delivery_payload(array_values($questions_by_id)) as $delivery_item) {
            $question_id = (int) ($delivery_item['id'] ?? 0);
            if ($question_id > 0) {
                $delivery_items_by_id[$question_id] = $delivery_item;
            }
        }

        $start_fragments_by_id = [];
        foreach ($questions_by_id as $question_id => $question) {
            $manifest_item = [
                'id' => $question_id,
                'question_type' => (string) ($question['question_type'] ?? ''),
                'updated_at' => (string) ($question['updated_at'] ?? ''),
                'points' => (float) ($question['points'] ?? 0),
            ];
            $option_tokens = self::question_supports_option_randomization($question)
                ? self::extract_randomizable_question_item_keys($question)
                : [];
            $question_type = (string) ($question['question_type'] ?? '');
            $force_shuffle = $question_type === 'matching';
            if ($question_type === 'ordering') {
                $ordering_meta = isset($question['ordering_meta']) && is_array($question['ordering_meta'])
                    ? $question['ordering_meta']
                    : [];
                $force_shuffle = ((int) ($ordering_meta['shuffle_items'] ?? 1) !== 0);
            }

            $start_fragments_by_id[$question_id] = [
                'manifest' => $manifest_item,
                'option_tokens' => $option_tokens,
                'force_shuffle' => $force_shuffle,
            ];
        }

        if (count($delivery_items_by_id) !== count($question_ids) || count($start_fragments_by_id) !== count($question_ids)) {
            $default['reason'] = 'fragment_incomplete';
            return $default;
        }

        return [
            'success' => true,
            'reason' => 'ready',
            'delivery_items_by_id' => $delivery_items_by_id,
            'start_fragments_by_id' => $start_fragments_by_id,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $current_items
     * @param array<int,array<string,mixed>> $replacement_items_by_id
     * @param array<int,int> $question_ids
     * @return array{success:bool,reason:string,items:array<int,array<string,mixed>>}
     */
    private static function patch_partial_delivery_snapshot_items(array $current_items, array $replacement_items_by_id, array $question_ids): array
    {
        $question_ids = self::normalize_partial_snapshot_question_ids($question_ids);
        $remaining = array_fill_keys($question_ids, true);
        $patched_items = [];

        foreach ($current_items as $item) {
            $question = (array) $item;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id > 0 && isset($replacement_items_by_id[$question_id])) {
                $patched_items[] = (array) $replacement_items_by_id[$question_id];
                unset($remaining[$question_id]);
                continue;
            }

            $patched_items[] = $question;
        }

        if (!empty($remaining)) {
            return [
                'success' => false,
                'reason' => 'delivery_question_missing',
                'items' => [],
            ];
        }

        return [
            'success' => true,
            'reason' => 'patched',
            'items' => array_values($patched_items),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,array{manifest:array<string,mixed>,option_tokens:array<int,string>,force_shuffle:bool}> $fragments_by_id
     * @param array<int,int> $question_ids
     * @return array{success:bool,reason:string,payload:array<string,mixed>}
     */
    private static function patch_partial_start_attempt_snapshot_payload(array $payload, array $fragments_by_id, array $question_ids): array
    {
        $question_ids = self::normalize_partial_snapshot_question_ids($question_ids);
        $snapshot_question_ids = self::normalize_question_order_ids($payload['question_ids'] ?? []);
        $snapshot_question_lookup = array_fill_keys($snapshot_question_ids, true);
        foreach ($question_ids as $question_id) {
            if (!isset($snapshot_question_lookup[$question_id])) {
                return [
                    'success' => false,
                    'reason' => 'start_question_missing',
                    'payload' => [],
                ];
            }
        }

        $manifest = is_array($payload['question_manifest'] ?? null)
            ? array_values(array_filter((array) $payload['question_manifest'], 'is_array'))
            : [];
        $remaining_manifest = array_fill_keys($question_ids, true);
        foreach ($manifest as $index => $item) {
            $manifest_item = (array) $item;
            $question_id = (int) ($manifest_item['id'] ?? 0);
            if ($question_id > 0 && isset($fragments_by_id[$question_id])) {
                $manifest[$index] = (array) ($fragments_by_id[$question_id]['manifest'] ?? []);
                unset($remaining_manifest[$question_id]);
            }
        }
        if (!empty($remaining_manifest)) {
            return [
                'success' => false,
                'reason' => 'start_manifest_missing',
                'payload' => [],
            ];
        }

        $option_tokens_by_question = self::normalize_attempt_option_order_map($payload['option_randomization_tokens_by_question'] ?? []);
        $force_shuffle_lookup = array_fill_keys(self::normalize_question_order_ids($payload['force_option_shuffle_question_ids'] ?? []), true);

        foreach ($question_ids as $question_id) {
            $fragment = (array) ($fragments_by_id[$question_id] ?? []);
            $option_tokens = array_values(array_filter(array_map('strval', (array) ($fragment['option_tokens'] ?? [])), static function (string $token): bool {
                return trim($token) !== '';
            }));
            if (!empty($option_tokens)) {
                $option_tokens_by_question[$question_id] = $option_tokens;
            } else {
                unset($option_tokens_by_question[$question_id]);
            }

            if (!empty($fragment['force_shuffle'])) {
                $force_shuffle_lookup[$question_id] = true;
            } else {
                unset($force_shuffle_lookup[$question_id]);
            }
        }

        $force_shuffle_question_ids = array_values(array_filter(array_map('intval', array_keys($force_shuffle_lookup)), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        sort($force_shuffle_question_ids, SORT_NUMERIC);

        $payload['question_manifest'] = array_values($manifest);
        $payload['option_randomization_tokens_by_question'] = self::normalize_attempt_option_order_map($option_tokens_by_question);
        $payload['force_option_shuffle_question_ids'] = $force_shuffle_question_ids;

        return [
            'success' => true,
            'reason' => 'patched',
            'payload' => $payload,
        ];
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,int>
     */
    private static function normalize_partial_snapshot_question_ids(array $question_ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
    }

    /**
     * @return array{
     *   ok:bool,
     *   attempt_id:int,
     *   exam_id:int,
     *   message:string,
     *   session_snapshot:array<string,mixed>,
     *   contract_snapshot:array<string,mixed>
     * }
     */
    public static function rebuild_attempt_runtime_snapshots(int $attempt_id, int $expected_exam_id = 0): array
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        $expected_exam_id = absint($expected_exam_id);
        if ($attempt_id <= 0) {
            return [
                'ok' => false,
                'attempt_id' => 0,
                'exam_id' => 0,
                'message' => 'Attempt wajib dipilih untuk refresh runtime snapshot.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, extra_time_minutes, question_order, option_order
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!is_array($attempt) || empty($attempt)) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => 0,
                'message' => 'Attempt tidak ditemukan.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        $exam_id = (int) ($attempt['exam_id'] ?? 0);
        if ($expected_exam_id > 0 && $exam_id !== $expected_exam_id) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $exam_id,
                'message' => 'Attempt tidak termasuk exam yang sedang dipantau.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        if (sanitize_key((string) ($attempt['status'] ?? '')) !== 'in_progress') {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $exam_id,
                'message' => 'Hanya attempt in_progress yang bisa direfresh runtime snapshot-nya.',
                'session_snapshot' => class_exists('CBT_Attempt_Session_Snapshot_Cache')
                    ? CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics($attempt_id)
                    : [],
                'contract_snapshot' => class_exists('CBT_Attempt_Question_Contract_Cache')
                    ? CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics($attempt_id)
                    : [],
            ];
        }

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, duration_minutes, show_student_result, enable_calculator
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            ),
            ARRAY_A
        );
        if (!is_array($exam) || empty($exam)) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $exam_id,
                'message' => 'Exam sumber attempt tidak ditemukan.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        $questions = self::get_cached_exam_question_payload($exam_id);
        $questions = self::append_missing_attempt_questions(
            $questions,
            $exam_id,
            (string) ($attempt['question_order'] ?? '')
        );
        $contract = self::build_attempt_runtime_snapshot_contract($attempt, $exam, $questions, $attempt_table);
        self::sync_attempt_runtime_snapshots(
            $attempt,
            $exam,
            (array) ($contract['question_order_ids'] ?? []),
            (array) ($contract['question_number_map'] ?? []),
            (array) ($contract['option_order_map'] ?? []),
            (array) ($contract['question_manifest'] ?? [])
        );

        $session_snapshot = class_exists('CBT_Attempt_Session_Snapshot_Cache')
            ? CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics($attempt_id)
            : [];
        $contract_snapshot = class_exists('CBT_Attempt_Question_Contract_Cache')
            ? CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics($attempt_id)
            : [];
        $ok = !empty($session_snapshot['snapshot_valid']) && !empty($contract_snapshot['snapshot_valid']);

        return [
            'ok' => $ok,
            'attempt_id' => $attempt_id,
            'exam_id' => $exam_id,
            'message' => $ok
                ? 'Runtime snapshot berhasil diperbarui dari sumber live.'
                : 'Runtime snapshot diperbarui tetapi hasilnya belum valid.',
            'session_snapshot' => $session_snapshot,
            'contract_snapshot' => $contract_snapshot,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_cached_exam_start_attempt_snapshot(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        if (class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            return CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot($exam_id, static function (int $target_exam_id): array {
                return self::build_exam_start_attempt_snapshot_from_db($target_exam_id);
            });
        }

        return self::build_exam_start_attempt_snapshot_from_db($exam_id);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @param array<int,int> $question_order_ids
     * @param array<int,int> $question_number_map
     * @param array<int,array<int,string>> $option_order_map
     * @param array<int,array<string,mixed>> $question_manifest
     */
    private static function sync_attempt_runtime_snapshots(
        array $attempt,
        array $exam,
        array $question_order_ids,
        array $question_number_map,
        array $option_order_map,
        array $question_manifest
    ): void {
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return;
        }

        $question_order_ids = array_values(array_filter(array_map('intval', $question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        $question_number_map = self::normalize_attempt_question_number_map($question_number_map);
        $option_order_map = self::normalize_attempt_option_order_map($option_order_map);
        $question_manifest = is_array($question_manifest) ? array_values(array_filter($question_manifest, 'is_array')) : [];
        $question_order_signature = self::build_attempt_question_order_signature($question_order_ids, $question_number_map);

        if (class_exists('CBT_Attempt_Question_Contract_Cache')) {
            CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot($attempt_id, [
                'attempt_id' => $attempt_id,
                'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'status' => (string) ($attempt['status'] ?? 'in_progress'),
                'question_order_ids' => $question_order_ids,
                'question_number_map' => $question_number_map,
                'question_order_signature' => $question_order_signature,
                'question_manifest' => $question_manifest,
                'option_order_map' => $option_order_map,
            ]);
        }

        if (class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
            CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot($attempt_id, [
                'attempt_id' => $attempt_id,
                'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'status' => (string) ($attempt['status'] ?? 'in_progress'),
                'started_at' => (string) ($attempt['started_at'] ?? ''),
                'duration_minutes' => self::resolve_attempt_duration_minutes(
                    $attempt,
                    (int) ($exam['duration_minutes'] ?? 0)
                ),
                'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
                'question_count' => count($question_order_ids),
                'question_order_signature' => $question_order_signature,
                'show_student_result' => self::normalize_show_student_result($exam['show_student_result'] ?? 1),
                'enable_calculator' => self::normalize_enable_calculator($exam['enable_calculator'] ?? 1),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array<string,mixed>
     */
    private static function build_lightweight_attempt_session_snapshot(array $attempt, array $exam): array
    {
        $question_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);
        $question_number_map = self::build_attempt_question_number_map($question_order_ids, $question_order_ids);

        return [
            'attempt_id' => (int) ($attempt['id'] ?? 0),
            'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
            'student_id' => (int) ($attempt['student_id'] ?? 0),
            'status' => (string) ($attempt['status'] ?? ''),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'duration_minutes' => self::resolve_attempt_duration_minutes($attempt, (int) ($exam['duration_minutes'] ?? 0)),
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
            'question_count' => count($question_order_ids),
            'question_order_signature' => self::build_attempt_question_order_signature($question_order_ids, $question_number_map),
            'show_student_result' => self::normalize_show_student_result($exam['show_student_result'] ?? 1),
            'enable_calculator' => self::normalize_enable_calculator($exam['enable_calculator'] ?? 1),
        ];
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array<string,mixed>
     */
    private static function build_lightweight_attempt_question_contract(array $attempt, array $exam): array
    {
        $question_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);
        $exam_id = (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0);
        $start_snapshot = [];

        if ($exam_id > 0) {
            try {
                $start_snapshot = self::get_cached_exam_start_attempt_snapshot($exam_id);
            } catch (Throwable $throwable) {
                $start_snapshot = [];
            }
        }

        if (empty($question_order_ids)) {
            $question_order_ids = array_values(array_filter(array_map('intval', (array) ($start_snapshot['question_ids'] ?? [])), static function (int $question_id): bool {
                return $question_id > 0;
            }));
        }

        $question_number_map = self::build_attempt_question_number_map($question_order_ids, $question_order_ids);
        $question_manifest = self::build_lightweight_question_manifest_from_start_snapshot(
            $start_snapshot,
            $question_order_ids,
            $question_number_map
        );

        if (empty($question_manifest)) {
            $question_manifest = self::build_minimal_question_manifest_from_order($question_order_ids, $question_number_map);
        }

        return [
            'attempt_id' => (int) ($attempt['id'] ?? 0),
            'exam_id' => $exam_id,
            'student_id' => (int) ($attempt['student_id'] ?? 0),
            'status' => (string) ($attempt['status'] ?? ''),
            'question_order_ids' => $question_order_ids,
            'question_number_map' => $question_number_map,
            'question_order_signature' => self::build_attempt_question_order_signature($question_order_ids, $question_number_map),
            'question_manifest' => $question_manifest,
            'option_order_map' => self::normalize_attempt_option_order_map($attempt['option_order'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $start_snapshot
     * @param array<int,int> $question_order_ids
     * @param array<int,int> $question_number_map
     * @return array<int,array<string,mixed>>
     */
    private static function build_lightweight_question_manifest_from_start_snapshot(
        array $start_snapshot,
        array $question_order_ids,
        array $question_number_map
    ): array {
        $source_manifest = is_array($start_snapshot['question_manifest'] ?? null)
            ? (array) $start_snapshot['question_manifest']
            : [];
        if (empty($source_manifest) || empty($question_order_ids)) {
            return [];
        }

        $manifest_by_id = [];
        foreach ($source_manifest as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question_id = (int) ($item['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $manifest_by_id[$question_id] = $item;
        }

        $manifest = [];
        foreach ($question_order_ids as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || !isset($manifest_by_id[$question_id])) {
                continue;
            }

            $item = (array) $manifest_by_id[$question_id];
            $item['id'] = $question_id;
            $question_number = (int) ($question_number_map[$question_id] ?? 0);
            if ($question_number > 0) {
                $item['question_number'] = $question_number;
            }
            $manifest[] = $item;
        }

        return $manifest;
    }

    /**
     * @param array<int,int> $question_order_ids
     * @param array<int,int> $question_number_map
     * @return array<int,array<string,mixed>>
     */
    private static function build_minimal_question_manifest_from_order(array $question_order_ids, array $question_number_map): array
    {
        $manifest = [];
        foreach ($question_order_ids as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0) {
                continue;
            }

            $item = [
                'id' => $question_id,
                'question_type' => '',
                'updated_at' => '',
            ];
            $question_number = (int) ($question_number_map[$question_id] ?? 0);
            if ($question_number > 0) {
                $item['question_number'] = $question_number;
            }
            $manifest[] = $item;
        }

        return $manifest;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @param array<int,int> $question_order_ids
     * @param array<int,int> $question_number_map
     * @param array<int,array<int,string>> $option_order_map
     * @param array<int,array<string,mixed>> $question_manifest
     */
    private static function write_minimal_attempt_entry_snapshots(
        array $attempt,
        array $exam,
        array $question_order_ids,
        array $question_number_map,
        array $option_order_map,
        array $question_manifest
    ): void {
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0 || empty($question_order_ids)) {
            return;
        }

        self::sync_attempt_runtime_snapshots(
            $attempt,
            $exam,
            $question_order_ids,
            $question_number_map,
            $option_order_map,
            $question_manifest
        );
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @param array<int,array<string,mixed>> $questions
     * @return array{
     *   question_order_ids:array<int,int>,
     *   question_number_map:array<int,int>,
     *   option_order_map:array<int,array<int,string>>,
     *   question_manifest:array<int,array<string,mixed>>,
     *   question_order_signature:string
     * }
     */
    private static function build_attempt_runtime_snapshot_contract(array $attempt, array $exam, array $questions, string $attempt_table): array
    {
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return [
                'question_order_ids' => [],
                'question_number_map' => [],
                'option_order_map' => [],
                'question_manifest' => [],
                'question_order_signature' => '',
            ];
        }

        $use_snapshot_questions = !empty($questions) ? $questions : self::get_cached_exam_question_payload((int) ($exam['id'] ?? 0));
        $resolved_attempt_payload = self::resolve_attempt_question_payload(
            $use_snapshot_questions,
            $attempt,
            $exam,
            $attempt_table
        );
        $resolved_questions = isset($resolved_attempt_payload['questions']) && is_array($resolved_attempt_payload['questions'])
            ? $resolved_attempt_payload['questions']
            : [];
        $question_order_ids = isset($resolved_attempt_payload['question_order_ids']) && is_array($resolved_attempt_payload['question_order_ids'])
            ? array_values(array_filter(array_map('intval', $resolved_attempt_payload['question_order_ids']), static function (int $question_id): bool {
                return $question_id > 0;
            }))
            : self::extract_question_ids_from_payload($resolved_questions);
        $question_number_map = [];
        foreach ($resolved_questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            $question_number = (int) ($question['question_number'] ?? 0);
            if ($question_id > 0 && $question_number > 0) {
                $question_number_map[$question_id] = $question_number;
            }
        }
        $resolved_attempt = isset($resolved_attempt_payload['attempt']) && is_array($resolved_attempt_payload['attempt'])
            ? $resolved_attempt_payload['attempt']
            : $attempt;

        return [
            'question_order_ids' => $question_order_ids,
            'question_number_map' => $question_number_map,
            'option_order_map' => self::normalize_attempt_option_order_map($resolved_attempt['option_order'] ?? ''),
            'question_manifest' => self::build_question_manifest($resolved_questions),
            'question_order_signature' => (string) ($resolved_attempt_payload['question_order_signature'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array<string,mixed>|WP_Error
     */
    private static function get_cached_attempt_session_snapshot(array $attempt, array $exam, string $attempt_table, bool $cached_only = false)
    {
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return [];
        }

        if (!class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
            return self::build_lightweight_attempt_session_snapshot($attempt, $exam);
        }

        if (method_exists('CBT_Attempt_Session_Snapshot_Cache', 'read_cached_attempt_snapshot')) {
            $cached = CBT_Attempt_Session_Snapshot_Cache::read_cached_attempt_snapshot($attempt_id);
            if (!empty($cached)) {
                self::write_start_attempt_opening_state(
                    (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                    (int) ($attempt['student_id'] ?? 0),
                    'bootstrap_session',
                    'session_snapshot_pending',
                    [
                        'attempt_id' => $attempt_id,
                        'retry_after_ms' => 1000,
                    ]
                );
                self::record_start_attempt_phase('get_session_cached_entry_snapshot', (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0), (int) ($attempt['student_id'] ?? 0), [
                    'attempt_id' => $attempt_id,
                    'source' => 'session_cache',
                ]);
                return $cached;
            }
        }

        $bootstrap_lock_key = 'attempt_bootstrap:session:' . $attempt_id;
        if ($cached_only) {
            if (!CBT_Cache::acquire_lock($bootstrap_lock_key, 10, [
                'type' => 'attempt_session_snapshot_cached_only',
                'attempt_id' => $attempt_id,
                'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
            ])) {
                self::write_start_attempt_opening_state(
                    (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                    (int) ($attempt['student_id'] ?? 0),
                    'bootstrap_session',
                    'session_snapshot_pending',
                    [
                        'attempt_id' => $attempt_id,
                        'retry_after_ms' => 1000,
                    ]
                );
                self::record_start_attempt_phase('attempt_bootstrap_busy', (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0), (int) ($attempt['student_id'] ?? 0), [
                    'attempt_id' => $attempt_id,
                    'source' => 'session_snapshot_cached_only',
                ]);
                return new WP_Error(
                    'attempt_bootstrap_busy',
                    'Server masih menyiapkan sesi ujian. Coba lagi sebentar.',
                    ['status' => 429, 'retry_after_ms' => 1000]
                );
            }

            CBT_Cache::release_lock($bootstrap_lock_key);
            self::write_start_attempt_opening_state(
                (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                (int) ($attempt['student_id'] ?? 0),
                'bootstrap_session',
                'session_snapshot_pending',
                [
                    'attempt_id' => $attempt_id,
                    'retry_after_ms' => 1000,
                ]
            );
            self::record_start_attempt_phase('get_session_cached_entry_snapshot', (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0), (int) ($attempt['student_id'] ?? 0), [
                'attempt_id' => $attempt_id,
                'source' => 'session_lightweight_cached_only',
            ]);
            return self::build_lightweight_attempt_session_snapshot($attempt, $exam);
        }

        if (!CBT_Cache::acquire_lock($bootstrap_lock_key, 10, [
            'type' => 'attempt_session_snapshot_bootstrap',
            'attempt_id' => $attempt_id,
            'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
        ])) {
            self::write_start_attempt_opening_state(
                (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                (int) ($attempt['student_id'] ?? 0),
                'bootstrap_session',
                'session_snapshot_pending',
                [
                    'attempt_id' => $attempt_id,
                    'retry_after_ms' => 1000,
                ]
            );
            self::record_start_attempt_phase('attempt_bootstrap_busy', (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0), (int) ($attempt['student_id'] ?? 0), [
                'attempt_id' => $attempt_id,
                'source' => 'session_snapshot',
            ]);
            return self::build_lightweight_attempt_session_snapshot($attempt, $exam);
        }

        try {
            $snapshot = CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot($attempt_id, function () use ($attempt, $exam, $attempt_table): array {
                $contract = self::build_attempt_runtime_snapshot_contract($attempt, $exam, [], $attempt_table);
                return [
                    'attempt_id' => (int) ($attempt['id'] ?? 0),
                    'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                    'student_id' => (int) ($attempt['student_id'] ?? 0),
                    'status' => (string) ($attempt['status'] ?? ''),
                    'started_at' => (string) ($attempt['started_at'] ?? ''),
                    'duration_minutes' => self::resolve_attempt_duration_minutes($attempt, (int) ($exam['duration_minutes'] ?? 0)),
                    'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
                    'question_count' => count((array) ($contract['question_order_ids'] ?? [])),
                    'question_order_signature' => (string) ($contract['question_order_signature'] ?? ''),
                    'show_student_result' => self::normalize_show_student_result($exam['show_student_result'] ?? 1),
                    'enable_calculator' => self::normalize_enable_calculator($exam['enable_calculator'] ?? 1),
                ];
            });
            self::write_start_attempt_opening_state(
                (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                (int) ($attempt['student_id'] ?? 0),
                'bootstrap_session',
                'session_snapshot_pending',
                [
                    'attempt_id' => $attempt_id,
                    'retry_after_ms' => 1000,
                ]
            );
            return $snapshot;
        } finally {
            CBT_Cache::release_lock($bootstrap_lock_key);
        }
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @param array<int,array<string,mixed>> $questions
     * @return array<string,mixed>
     */
    private static function get_cached_attempt_question_contract(array $attempt, array $exam, array $questions, string $attempt_table): array
    {
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return [];
        }

        if (!class_exists('CBT_Attempt_Question_Contract_Cache')) {
            return self::build_lightweight_attempt_question_contract($attempt, $exam);
        }

        if (method_exists('CBT_Attempt_Question_Contract_Cache', 'read_cached_attempt_snapshot')) {
            $cached = CBT_Attempt_Question_Contract_Cache::read_cached_attempt_snapshot($attempt_id);
            if (!empty($cached)) {
                self::write_start_attempt_opening_state(
                    (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                    (int) ($attempt['student_id'] ?? 0),
                    'bootstrap_questions',
                    'question_window_pending',
                    [
                        'attempt_id' => $attempt_id,
                        'retry_after_ms' => 1000,
                    ]
                );
                return $cached;
            }
        }

        $bootstrap_lock_key = 'attempt_bootstrap:contract:' . $attempt_id;
        if (!CBT_Cache::acquire_lock($bootstrap_lock_key, 10, [
            'type' => 'attempt_question_contract_bootstrap',
            'attempt_id' => $attempt_id,
            'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
        ])) {
            self::write_start_attempt_opening_state(
                (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                (int) ($attempt['student_id'] ?? 0),
                'bootstrap_questions',
                'question_window_pending',
                [
                    'attempt_id' => $attempt_id,
                    'retry_after_ms' => 1000,
                ]
            );
            self::record_start_attempt_phase('question_bootstrap_busy', (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0), (int) ($attempt['student_id'] ?? 0), [
                'attempt_id' => $attempt_id,
                'source' => 'question_contract',
            ]);
            return self::build_lightweight_attempt_question_contract($attempt, $exam);
        }

        try {
            $contract_snapshot = CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot($attempt_id, function () use ($attempt, $exam, $questions, $attempt_table): array {
                $contract = self::build_attempt_runtime_snapshot_contract($attempt, $exam, $questions, $attempt_table);
                return array_merge($contract, [
                    'attempt_id' => (int) ($attempt['id'] ?? 0),
                    'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                    'student_id' => (int) ($attempt['student_id'] ?? 0),
                    'status' => (string) ($attempt['status'] ?? ''),
                ]);
            });
            self::write_start_attempt_opening_state(
                (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                (int) ($attempt['student_id'] ?? 0),
                'bootstrap_questions',
                'question_window_pending',
                [
                    'attempt_id' => $attempt_id,
                    'retry_after_ms' => 1000,
                ]
            );
            return $contract_snapshot;
        } finally {
            CBT_Cache::release_lock($bootstrap_lock_key);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,int> $question_order_ids
     * @return array<int,array<string,mixed>>
     */
    private static function append_missing_questions_by_ids(array $questions, int $exam_id, array $question_order_ids): array
    {
        $question_order_ids = array_values(array_filter(array_map('intval', $question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        if ($exam_id <= 0 || empty($question_order_ids)) {
            return $questions;
        }

        $known_question_ids = array_fill_keys(self::extract_question_ids_from_payload($questions), true);
        $missing_question_ids = [];
        foreach ($question_order_ids as $question_id) {
            if (!isset($known_question_ids[$question_id])) {
                $missing_question_ids[] = $question_id;
            }
        }

        if (empty($missing_question_ids)) {
            return $questions;
        }

        $extra_questions = self::get_question_payload_by_ids($exam_id, $missing_question_ids);
        if (empty($extra_questions)) {
            return $questions;
        }

        return array_merge($questions, self::sanitize_question_delivery_payload($extra_questions));
    }

    /**
     * @param array<int,int> $question_number_map
     * @return array<int,int>
     */
    private static function normalize_attempt_question_number_map(array $question_number_map): array
    {
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
     * @return array<int,array<string,mixed>>
     */
    private static function build_exam_question_payload_from_db(int $exam_id): array
    {
        global $wpdb;

        $question_table = $wpdb->prefix . 'cbt_questions';
        $short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';
        $ordering_table = $wpdb->prefix . 'cbt_question_ordering';
        $question_rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.points, q.correct_text, q.created_at, q.updated_at,
                        COALESCE(q.is_active, 1) AS is_active,
                        qsa.correct_text AS short_answer_correct_text,
                        qord.scoring_mode AS ordering_scoring_mode,
                        qord.shuffle_items AS ordering_shuffle_items
                 FROM {$question_table} q
                 LEFT JOIN {$short_answer_table} qsa ON qsa.question_id = q.id
                 LEFT JOIN {$ordering_table} qord ON qord.question_id = q.id
                 WHERE q.exam_id = %d
                   AND COALESCE(q.is_active, 1) = 1
                 ORDER BY q.id ASC",
                $exam_id
            ),
            ARRAY_A
        );

        return self::build_question_payload_from_rows($question_rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_student_exam_question_delivery_payload_from_db(int $exam_id): array
    {
        return self::sanitize_question_delivery_payload(self::build_exam_question_payload_from_db($exam_id));
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_exam_start_attempt_snapshot_from_db(int $exam_id): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';

        $exam_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, randomize_questions, randomize_options, duration_minutes, show_student_result, enable_calculator
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            ),
            ARRAY_A
        );

        $question_rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id, q.question_type, q.correct_text, q.points, q.updated_at
                 FROM {$question_table} q
                 WHERE q.exam_id = %d
                   AND COALESCE(q.is_active, 1) = 1
                 ORDER BY q.id ASC",
                $exam_id
            ),
            ARRAY_A
        );

        $question_ids = [];
        $option_question_ids = [];
        $option_tokens_by_question = [];
        $force_option_shuffle_question_ids = [];
        $question_manifest = [];

        foreach ($question_rows as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_ids[] = $question_id;
            $manifest_item = [
                'id' => $question_id,
                'question_type' => (string) ($question['question_type'] ?? ''),
                'updated_at' => (string) ($question['updated_at'] ?? ''),
                'points' => (float) ($question['points'] ?? 0),
            ];
            $question_manifest[] = $manifest_item;

            $question_type = (string) ($question['question_type'] ?? '');
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'ordering', 'matching', 'categorization'], true)) {
                $option_question_ids[] = $question_id;
                if ($question_type === 'ordering' && (int) ($question['ordering_shuffle_items'] ?? 1) !== 0) {
                    $force_option_shuffle_question_ids[$question_id] = $question_id;
                } elseif ($question_type === 'matching') {
                    $force_option_shuffle_question_ids[$question_id] = $question_id;
                }
                continue;
            }

            if ($question_type === 'true_false_matrix') {
                $matrix_items = self::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
                $tokens = [];
                foreach (array_keys($matrix_items) as $item_index) {
                    $tokens[] = (string) ($item_index + 1);
                }
                if (!empty($tokens)) {
                    $option_tokens_by_question[$question_id] = $tokens;
                }
            }
        }

        $option_question_ids = array_values(array_unique(array_filter(array_map('intval', $option_question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if (!empty($option_question_ids)) {
            $question_ids_sql = implode(',', $option_question_ids);
            $option_rows = (array) $wpdb->get_results(
                "SELECT id, question_id
                 FROM {$option_table}
                 WHERE question_id IN ({$question_ids_sql})
                 ORDER BY question_id ASC, id ASC",
                ARRAY_A
            );

            foreach ($option_rows as $option_row) {
                $question_id = (int) ($option_row['question_id'] ?? 0);
                $option_id = (int) ($option_row['id'] ?? 0);
                if ($question_id <= 0 || $option_id <= 0) {
                    continue;
                }

                if (!isset($option_tokens_by_question[$question_id])) {
                    $option_tokens_by_question[$question_id] = [];
                }
                $option_tokens_by_question[$question_id][] = (string) $option_id;
            }
        }

        return [
            'exam_id' => $exam_id,
            'question_ids' => array_values(array_unique($question_ids)),
            'question_count' => count(array_values(array_unique($question_ids))),
            'question_number_map' => self::build_attempt_question_number_map($question_ids, $question_ids),
            'question_manifest' => $question_manifest,
            'randomize_questions' => (int) ($exam_row['randomize_questions'] ?? 0) === 1 ? 1 : 0,
            'randomize_options' => (int) ($exam_row['randomize_options'] ?? 0) === 1 ? 1 : 0,
            'duration_minutes' => max(0, (int) ($exam_row['duration_minutes'] ?? 0)),
            'show_student_result' => self::normalize_show_student_result($exam_row['show_student_result'] ?? 1),
            'enable_calculator' => self::normalize_enable_calculator($exam_row['enable_calculator'] ?? 1),
            'option_randomization_tokens_by_question' => self::normalize_attempt_option_order_map($option_tokens_by_question),
            'force_option_shuffle_question_ids' => array_values($force_option_shuffle_question_ids),
        ];
    }

    private static function should_use_student_delivery_snapshot(string $role, $attempt): bool
    {
        if (!self::is_student_role($role) || !is_array($attempt)) {
            return false;
        }

        return (string) ($attempt['status'] ?? '') === 'in_progress';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_student_exam_question_delivery_payload(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        if (class_exists('CBT_Exam_Question_Delivery_Cache')) {
            return CBT_Exam_Question_Delivery_Cache::get_exam_payload($exam_id, static function (int $target_exam_id): array {
                return self::build_student_exam_question_delivery_payload_from_db($target_exam_id);
            });
        }

        return self::sanitize_question_delivery_payload(self::get_cached_exam_question_payload($exam_id));
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function append_missing_attempt_questions(array $questions, int $exam_id, string $question_order_raw): array
    {
        $question_order_ids = self::normalize_question_order_ids($question_order_raw);
        if ($exam_id <= 0 || empty($question_order_ids)) {
            return $questions;
        }

        $known_question_ids = array_fill_keys(self::extract_question_ids_from_payload($questions), true);
        $missing_question_ids = [];
        foreach ($question_order_ids as $question_id) {
            if (!isset($known_question_ids[$question_id])) {
                $missing_question_ids[] = $question_id;
            }
        }

        if (empty($missing_question_ids)) {
            return $questions;
        }

        $extra_questions = self::get_question_payload_by_ids($exam_id, $missing_question_ids);
        if (empty($extra_questions)) {
            return $questions;
        }

        return array_merge($questions, self::sanitize_question_delivery_payload($extra_questions));
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<string,mixed>>
     */
    private static function get_question_payload_by_ids(int $exam_id, array $question_ids): array
    {
        global $wpdb;

        $question_table = $wpdb->prefix . 'cbt_questions';
        $short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';
        $ordering_table = $wpdb->prefix . 'cbt_question_ordering';
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if ($exam_id <= 0 || empty($question_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $question_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.points, q.correct_text, q.created_at, q.updated_at,
                        COALESCE(q.is_active, 1) AS is_active,
                        qsa.correct_text AS short_answer_correct_text,
                        qord.scoring_mode AS ordering_scoring_mode,
                        qord.shuffle_items AS ordering_shuffle_items
                 FROM {$question_table} q
                 LEFT JOIN {$short_answer_table} qsa ON qsa.question_id = q.id
                 LEFT JOIN {$ordering_table} qord ON qord.question_id = q.id
                 WHERE q.exam_id = %d
                   AND q.id IN ({$placeholders})
                 ORDER BY q.id ASC",
                $exam_id,
                ...$question_ids
            ),
            ARRAY_A
        );

        return self::build_question_payload_from_rows((array) $question_rows);
    }

    /**
     * @param array<int,array<string,mixed>> $question_rows
     * @return array<int,array<string,mixed>>
     */
    private static function build_question_payload_from_rows(array $question_rows): array
    {
        global $wpdb;

        if (empty($question_rows)) {
            return [];
        }

        $option_table = $wpdb->prefix . 'cbt_options';
        $option_question_ids = [];
        foreach ($question_rows as $question_row) {
            $question_id = (int) ($question_row['id'] ?? 0);
            $question_type = (string) ($question_row['question_type'] ?? '');
            if ($question_id <= 0) {
                continue;
            }
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false', 'ordering', 'matching', 'categorization'], true)) {
                $option_question_ids[] = $question_id;
            }
        }
        $option_question_ids = array_values(array_unique($option_question_ids));

        $options_by_question = [];
        if (!empty($option_question_ids)) {
            $question_ids_sql = implode(',', $option_question_ids);
            $option_rows = $wpdb->get_results(
                "SELECT id, question_id, option_key, option_text
                 FROM {$option_table}
                 WHERE question_id IN ({$question_ids_sql})
                 ORDER BY question_id ASC, id ASC",
                ARRAY_A
            );

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
                    'option_key' => (string) ($option_row['option_key'] ?? ''),
                    'option_text' => (string) ($option_row['option_text'] ?? ''),
                ];
            }
        }

        $matching_items_by_question = [];
        $cloze_blanks_by_question = [];
        $categorization_items_by_question = [];
        $table_completion_by_question = [];
        if (class_exists('CBT_Admin_Questions_Helper')) {
            foreach ($question_rows as $question_row) {
                $question_id = (int) ($question_row['id'] ?? 0);
                $question_type = (string) ($question_row['question_type'] ?? '');
                if ($question_id <= 0) {
                    continue;
                }

                if ($question_type === 'matching') {
                    $matching_items_by_question[$question_id] = CBT_Admin_Questions_Helper::get_matching_items($question_id);
                } elseif ($question_type === 'cloze_dropdown') {
                    $cloze_blanks_by_question[$question_id] = CBT_Admin_Questions_Helper::get_cloze_dropdown_blanks($question_id, false);
                } elseif ($question_type === 'categorization') {
                    $categorization_items_by_question[$question_id] = CBT_Admin_Questions_Helper::get_categorization_items($question_id);
                } elseif ($question_type === 'table_completion') {
                    $table_completion_by_question[$question_id] = CBT_Admin_Questions_Helper::get_table_completion_definition($question_id, false);
                }
            }
        }

        $questions = [];
        foreach ($question_rows as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question['question_text'] = self::normalize_frontend_question_text((string) ($question['question_text'] ?? ''));
            $question['options'] = (array) ($options_by_question[$question_id] ?? []);

            if ((string) ($question['question_type'] ?? '') === 'short_answer') {
                $correct_text = trim((string) ($question['short_answer_correct_text'] ?? ''));
                if ($correct_text === '') {
                    $correct_text = (string) ($question['correct_text'] ?? '');
                }
                $correct_values = self::normalize_short_answer_values($correct_text);
                $input_keys = self::resolve_short_answer_input_keys((string) ($question['question_text'] ?? ''), $correct_values);
                $question['short_answer_meta'] = [
                    'max_inputs' => 8,
                    'input_count' => count($input_keys),
                    'input_keys' => $input_keys,
                ];
            } elseif ((string) ($question['question_type'] ?? '') === 'true_false_matrix') {
                $matrix_items = self::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
                $question['true_false_matrix_meta'] = [
                    'item_count' => count($matrix_items),
                    'items' => array_map(static function (array $row, int $idx): array {
                        return [
                            'key' => (string) ($idx + 1),
                            'text' => (string) ($row['text'] ?? ''),
                        ];
                    }, $matrix_items, array_keys($matrix_items)),
                ];
            } elseif ((string) ($question['question_type'] ?? '') === 'ordering') {
                $question['ordering_meta'] = [
                    'item_count' => count((array) ($question['options'] ?? [])),
                    'scoring_mode' => in_array((string) ($question['ordering_scoring_mode'] ?? 'exact'), ['exact', 'partial_position'], true)
                        ? (string) ($question['ordering_scoring_mode'] ?? 'exact')
                        : 'exact',
                    'shuffle_items' => ((int) ($question['ordering_shuffle_items'] ?? 1) === 0) ? 0 : 1,
                ];
            } elseif ((string) ($question['question_type'] ?? '') === 'matching') {
                $matching_items = (array) ($matching_items_by_question[$question_id] ?? []);
                $question['matching_meta'] = [
                    'item_count' => count($matching_items),
                    'scoring_mode' => 'partial',
                    'shuffle_choices' => 1,
                    'items' => array_map(static function (array $row, int $idx): array {
                        $key = trim((string) ($row['item_key'] ?? ''));
                        if ($key === '') {
                            $key = (string) ($idx + 1);
                        }

                        return [
                            'key' => $key,
                            'text' => (string) ($row['prompt_text'] ?? ''),
                        ];
                    }, $matching_items, array_keys($matching_items)),
                ];
            } elseif ((string) ($question['question_type'] ?? '') === 'cloze_dropdown') {
                $cloze_blanks = (array) ($cloze_blanks_by_question[$question_id] ?? []);
                $question['cloze_dropdown_meta'] = [
                    'blank_count' => count($cloze_blanks),
                    'scoring_mode' => 'partial',
                    'blanks' => array_map(static function (array $row, int $idx): array {
                        $key = trim((string) ($row['blank_key'] ?? ''));
                        if ($key === '') {
                            $key = (string) ($idx + 1);
                        }

                        $options = [];
                        foreach ((array) ($row['options'] ?? []) as $option_row) {
                            $option = (array) $option_row;
                            $option_id = (int) ($option['id'] ?? 0);
                            if ($option_id <= 0) {
                                continue;
                            }
                            $options[] = [
                                'id' => $option_id,
                                'option_key' => (string) ($option['option_key'] ?? ''),
                                'option_text' => (string) ($option['option_text'] ?? ''),
                            ];
                        }

                        return [
                            'key' => $key,
                            'position' => (int) ($row['blank_position'] ?? ($idx + 1)),
                            'options' => $options,
                        ];
                    }, $cloze_blanks, array_keys($cloze_blanks)),
                ];
            } elseif ((string) ($question['question_type'] ?? '') === 'categorization') {
                $categorization_items = (array) ($categorization_items_by_question[$question_id] ?? []);
                $question['categorization_meta'] = [
                    'item_count' => count($categorization_items),
                    'scoring_mode' => 'partial',
                    'shuffle_items' => 1,
                    'items' => array_map(static function (array $row, int $idx): array {
                        $key = trim((string) ($row['item_key'] ?? ''));
                        if ($key === '') {
                            $key = (string) ($idx + 1);
                        }

                        return [
                            'key' => $key,
                            'text' => (string) ($row['item_text'] ?? ''),
                        ];
                    }, $categorization_items, array_keys($categorization_items)),
                ];
            } elseif ((string) ($question['question_type'] ?? '') === 'table_completion') {
                $table_definition = (array) ($table_completion_by_question[$question_id] ?? []);
                $cells = [];
                foreach ((array) ($table_definition['cells'] ?? []) as $cell_row) {
                    $cell = (array) $cell_row;
                    $cell_type = (string) ($cell['cell_type'] ?? 'static');
                    $payload_cell = [
                        'key' => (string) ($cell['cell_key'] ?? ''),
                        'row' => (int) ($cell['row_position'] ?? 0),
                        'column' => (int) ($cell['column_position'] ?? 0),
                        'type' => in_array($cell_type, ['static', 'text', 'dropdown'], true) ? $cell_type : 'static',
                        'text' => (string) ($cell['cell_text'] ?? ''),
                    ];
                    if ($payload_cell['type'] === 'dropdown') {
                        $options = [];
                        foreach ((array) ($cell['options'] ?? []) as $option_row) {
                            $option = (array) $option_row;
                            $option_id = (int) ($option['id'] ?? 0);
                            if ($option_id <= 0) {
                                continue;
                            }
                            $options[] = [
                                'id' => $option_id,
                                'option_key' => (string) ($option['option_key'] ?? ''),
                                'option_text' => (string) ($option['option_text'] ?? ''),
                            ];
                        }
                        $payload_cell['options'] = $options;
                    }
                    $cells[] = $payload_cell;
                }
                $question['table_completion_meta'] = [
                    'rows' => max(0, (int) ($table_definition['row_count'] ?? 0)),
                    'columns' => max(0, (int) ($table_definition['column_count'] ?? 0)),
                    'scoring_mode' => 'partial',
                    'cells' => $cells,
                ];
            }

            unset($question['short_answer_correct_text']);
            unset($question['ordering_scoring_mode'], $question['ordering_shuffle_items']);
            if (in_array((string) ($question['question_type'] ?? ''), ['true_false_matrix', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'], true)) {
                unset($question['correct_text']);
            }

            $questions[] = $question;
        }

        return $questions;
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function sanitize_question_delivery_payload(array $questions): array
    {
        $sanitized_questions = [];

        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $sanitized = [
                'id' => $question_id,
                'exam_id' => (int) ($question['exam_id'] ?? 0),
                'question_text' => (string) ($question['question_text'] ?? ''),
                'question_type' => (string) ($question['question_type'] ?? ''),
                'points' => (float) ($question['points'] ?? 0),
                'created_at' => (string) ($question['created_at'] ?? ''),
                'updated_at' => (string) ($question['updated_at'] ?? ''),
                'is_active' => (int) ($question['is_active'] ?? 1),
                'options' => [],
            ];

            if (!empty($question['options']) && is_array($question['options'])) {
                foreach ((array) $question['options'] as $option_row) {
                    $option = (array) $option_row;
                    $option_id = (int) ($option['id'] ?? 0);
                    if ($option_id <= 0) {
                        continue;
                    }

                    $sanitized['options'][] = [
                        'id' => $option_id,
                        'option_key' => (string) ($option['option_key'] ?? ''),
                        'option_text' => (string) ($option['option_text'] ?? ''),
                    ];
                }
            }

            if (!empty($question['short_answer_meta']) && is_array($question['short_answer_meta'])) {
                $input_keys = array_values(array_filter(array_map(static function ($key): string {
                    return strtoupper(trim((string) $key));
                }, (array) ($question['short_answer_meta']['input_keys'] ?? [])), static function (string $key): bool {
                    return $key !== '';
                }));

                $sanitized['short_answer_meta'] = [
                    'max_inputs' => max(0, (int) ($question['short_answer_meta']['max_inputs'] ?? 0)),
                    'input_count' => max(0, (int) ($question['short_answer_meta']['input_count'] ?? count($input_keys))),
                    'input_keys' => $input_keys,
                ];
            }

            if (!empty($question['true_false_matrix_meta']) && is_array($question['true_false_matrix_meta'])) {
                $items = [];
                foreach ((array) ($question['true_false_matrix_meta']['items'] ?? []) as $item_row) {
                    $item = (array) $item_row;
                    $items[] = [
                        'key' => (string) ($item['key'] ?? ''),
                        'text' => (string) ($item['text'] ?? ''),
                    ];
                }

                $sanitized['true_false_matrix_meta'] = [
                    'item_count' => max(0, (int) ($question['true_false_matrix_meta']['item_count'] ?? count($items))),
                    'items' => $items,
                ];
            }

            if (!empty($question['ordering_meta']) && is_array($question['ordering_meta'])) {
                $scoring_mode = (string) ($question['ordering_meta']['scoring_mode'] ?? 'exact');
                $sanitized['ordering_meta'] = [
                    'item_count' => max(0, (int) ($question['ordering_meta']['item_count'] ?? count($sanitized['options']))),
                    'scoring_mode' => in_array($scoring_mode, ['exact', 'partial_position'], true) ? $scoring_mode : 'exact',
                    'shuffle_items' => ((int) ($question['ordering_meta']['shuffle_items'] ?? 1) === 0) ? 0 : 1,
                ];
            }

            if (!empty($question['matching_meta']) && is_array($question['matching_meta'])) {
                $items = [];
                foreach ((array) ($question['matching_meta']['items'] ?? []) as $item_row) {
                    $item = (array) $item_row;
                    $key = trim((string) ($item['key'] ?? ''));
                    if ($key === '') {
                        continue;
                    }
                    $items[] = [
                        'key' => $key,
                        'text' => (string) ($item['text'] ?? ''),
                    ];
                }

                $sanitized['matching_meta'] = [
                    'item_count' => max(0, (int) ($question['matching_meta']['item_count'] ?? count($items))),
                    'scoring_mode' => 'partial',
                    'shuffle_choices' => ((int) ($question['matching_meta']['shuffle_choices'] ?? 1) === 0) ? 0 : 1,
                    'items' => $items,
                ];
            }

            if (!empty($question['cloze_dropdown_meta']) && is_array($question['cloze_dropdown_meta'])) {
                $blanks = [];
                foreach ((array) ($question['cloze_dropdown_meta']['blanks'] ?? []) as $blank_row) {
                    $blank = (array) $blank_row;
                    $key = trim((string) ($blank['key'] ?? ''));
                    if ($key === '') {
                        continue;
                    }

                    $options = [];
                    foreach ((array) ($blank['options'] ?? []) as $option_row) {
                        $option = (array) $option_row;
                        $option_id = (int) ($option['id'] ?? 0);
                        if ($option_id <= 0) {
                            continue;
                        }
                        $options[] = [
                            'id' => $option_id,
                            'option_key' => (string) ($option['option_key'] ?? ''),
                            'option_text' => (string) ($option['option_text'] ?? ''),
                        ];
                    }

                    $blanks[] = [
                        'key' => $key,
                        'position' => max(1, (int) ($blank['position'] ?? (count($blanks) + 1))),
                        'options' => $options,
                    ];
                }

                $sanitized['cloze_dropdown_meta'] = [
                    'blank_count' => max(0, (int) ($question['cloze_dropdown_meta']['blank_count'] ?? count($blanks))),
                    'scoring_mode' => 'partial',
                    'blanks' => $blanks,
                ];
            }

            if (!empty($question['categorization_meta']) && is_array($question['categorization_meta'])) {
                $items = [];
                foreach ((array) ($question['categorization_meta']['items'] ?? []) as $item_row) {
                    $item = (array) $item_row;
                    $key = trim((string) ($item['key'] ?? ''));
                    if ($key === '') {
                        continue;
                    }
                    $items[] = [
                        'key' => $key,
                        'text' => (string) ($item['text'] ?? ''),
                    ];
                }

                $sanitized['categorization_meta'] = [
                    'item_count' => max(0, (int) ($question['categorization_meta']['item_count'] ?? count($items))),
                    'scoring_mode' => 'partial',
                    'shuffle_items' => ((int) ($question['categorization_meta']['shuffle_items'] ?? 1) === 0) ? 0 : 1,
                    'items' => $items,
                ];
            }

            if (!empty($question['table_completion_meta']) && is_array($question['table_completion_meta'])) {
                $cells = [];
                foreach ((array) ($question['table_completion_meta']['cells'] ?? []) as $cell_row) {
                    $cell = (array) $cell_row;
                    $type = (string) ($cell['type'] ?? 'static');
                    if (!in_array($type, ['static', 'text', 'dropdown'], true)) {
                        $type = 'static';
                    }
                    $payload_cell = [
                        'key' => (string) ($cell['key'] ?? ''),
                        'row' => max(1, (int) ($cell['row'] ?? 0)),
                        'column' => max(1, (int) ($cell['column'] ?? 0)),
                        'type' => $type,
                        'text' => (string) ($cell['text'] ?? ''),
                    ];
                    if ($type === 'dropdown') {
                        $options = [];
                        foreach ((array) ($cell['options'] ?? []) as $option_row) {
                            $option = (array) $option_row;
                            $option_id = (int) ($option['id'] ?? 0);
                            if ($option_id <= 0) {
                                continue;
                            }
                            $options[] = [
                                'id' => $option_id,
                                'option_key' => (string) ($option['option_key'] ?? ''),
                                'option_text' => (string) ($option['option_text'] ?? ''),
                            ];
                        }
                        $payload_cell['options'] = $options;
                    }
                    $cells[] = $payload_cell;
                }

                $sanitized['table_completion_meta'] = [
                    'rows' => max(0, (int) ($question['table_completion_meta']['rows'] ?? 0)),
                    'columns' => max(0, (int) ($question['table_completion_meta']['columns'] ?? 0)),
                    'scoring_mode' => 'partial',
                    'cells' => $cells,
                ];
            }

            $sanitized_questions[] = $sanitized;
        }

        return $sanitized_questions;
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array{questions: array<int,array<string,mixed>>, question_order_ids: array<int,int>, question_order_signature:string, attempt: array<string,mixed>}
     */
    private static function resolve_attempt_question_payload(array $questions, array $attempt, array $exam, string $attempt_table): array
    {
        global $wpdb;

        $attempt_id = (int) ($attempt['id'] ?? 0);
        $attempt_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $persisted_question_order_ids = self::normalize_question_order_ids($attempt['question_order'] ?? '');
        $question_contract = self::resolve_attempt_question_order_contract($questions, $attempt, $exam);
        $questions = isset($question_contract['ordered_questions']) && is_array($question_contract['ordered_questions'])
            ? $question_contract['ordered_questions']
            : [];
        $question_order_ids = isset($question_contract['active_question_order_ids']) && is_array($question_contract['active_question_order_ids'])
            ? array_values(array_filter(array_map('intval', $question_contract['active_question_order_ids']), static function (int $question_id): bool {
                return $question_id > 0;
            }))
            : [];
        $canonical_question_order_ids = isset($question_contract['canonical_question_order_ids']) && is_array($question_contract['canonical_question_order_ids'])
            ? array_values(array_filter(array_map('intval', $question_contract['canonical_question_order_ids']), static function (int $question_id): bool {
                return $question_id > 0;
            }))
            : [];
        $question_number_map = isset($question_contract['display_number_map']) && is_array($question_contract['display_number_map'])
            ? $question_contract['display_number_map']
            : [];
        $question_order_signature = (string) ($question_contract['question_order_signature'] ?? '');
        $stale_attempt_order = !empty($question_contract['stale_attempt_order']);
        $preserve_removed_question_history = !empty($question_contract['preserve_removed_question_history']);

        $stored_question_order_ids = $preserve_removed_question_history
            ? $canonical_question_order_ids
            : $question_order_ids;
        $question_order_json = wp_json_encode($stored_question_order_ids);
        if (!is_string($question_order_json)) {
            $question_order_json = '[]';
        }

        if ($stale_attempt_order && $attempt_id > 0 && self::question_id_lists_differ($stored_question_order_ids, $persisted_question_order_ids)) {
            $wpdb->update(
                $attempt_table,
                [
                    'question_order' => $question_order_json,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $attempt_id],
                ['%s', '%s'],
                ['%d']
            );
        }
        $attempt['question_order'] = $question_order_json;

        $persisted_option_order_map = self::normalize_attempt_option_order_map($attempt['option_order'] ?? '');
        $runtime_option_order_map = [];
        if ($attempt_id > 0 && CBT_Runtime::is_ready()) {
            $runtime_option_order_map = CBT_Runtime::get_attempt_option_order($attempt_id, $runtime_option_order_found);
            if (!$runtime_option_order_found) {
                $runtime_option_order_map = [];
            }
        }

        $resolved_option_order_map = self::resolve_attempt_option_order_map(
            $questions,
            self::merge_attempt_option_order_maps($persisted_option_order_map, $runtime_option_order_map)
        );
        if (!empty($resolved_option_order_map)) {
            $questions = self::apply_attempt_option_order_to_questions($questions, $resolved_option_order_map);
        }
        $option_order_json = self::encode_attempt_option_order_map($resolved_option_order_map);
        if ($attempt_id > 0 && self::attempt_option_order_maps_differ($resolved_option_order_map, $persisted_option_order_map)) {
            $wpdb->update(
                $attempt_table,
                [
                    'option_order' => $option_order_json,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $attempt_id],
                ['%s', '%s'],
                ['%d']
            );
        }
        $attempt['option_order'] = $option_order_json;

        if ($attempt_id > 0 && CBT_Runtime::is_ready()) {
            self::ensure_runtime_attempt_state($attempt, $attempt_duration_minutes);
        }

        $questions = self::apply_question_numbers_to_payload($questions, $question_number_map);

        return [
            'questions' => $questions,
            'question_order_ids' => $question_order_ids,
            'question_order_signature' => $question_order_signature,
            'attempt' => $attempt,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array{
     *   ordered_questions: array<int,array<string,mixed>>,
     *   active_question_order_ids: array<int,int>,
     *   canonical_question_order_ids: array<int,int>,
     *   display_number_map: array<int,int>,
     *   question_order_signature: string,
     *   stale_attempt_order: bool,
     *   preserve_removed_question_history: bool
     * }
     */
    private static function resolve_attempt_question_order_contract(array $questions, array $attempt, array $exam): array
    {
        $should_shuffle = ((int) ($exam['randomize_questions'] ?? 0) === 1);
        $attempt_status = (string) ($attempt['status'] ?? '');
        $is_in_progress_attempt = ($attempt_status === 'in_progress');
        $persisted_question_order_ids = self::normalize_question_order_ids($attempt['question_order'] ?? '');
        $active_question_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);
        $original_question_order_ids = $active_question_order_ids;
        $canonical_question_order_ids = $active_question_order_ids;
        $ordered_questions = [];
        $stale_attempt_order = empty($active_question_order_ids);
        $removed_question_ids = [];
        $preserve_removed_question_history = false;

        if (!empty($active_question_order_ids)) {
            if ($is_in_progress_attempt) {
                $active_question_order_ids = self::reconcile_in_progress_question_order(
                    $questions,
                    $active_question_order_ids,
                    (string) ($attempt['started_at'] ?? ''),
                    $should_shuffle
                );
                $canonical_question_order_ids = self::merge_attempt_question_order_ids(
                    $original_question_order_ids,
                    $active_question_order_ids
                );
                $ordered_questions = self::order_question_payload_by_ids($questions, $active_question_order_ids);
                $removed_question_ids = array_values(array_diff($canonical_question_order_ids, $active_question_order_ids));
                $preserve_removed_question_history = !empty($removed_question_ids);
                $stale_attempt_order = (
                    count($ordered_questions) !== count($questions) ||
                    self::question_id_lists_differ($active_question_order_ids, $original_question_order_ids) ||
                    self::question_id_lists_differ($canonical_question_order_ids, $persisted_question_order_ids)
                );
            } else {
                $ordered_questions = self::order_question_payload_by_ids($questions, $active_question_order_ids);
                $stale_attempt_order = (count($ordered_questions) !== count($active_question_order_ids));
            }
        }

        if ($stale_attempt_order) {
            if ($is_in_progress_attempt && !empty($active_question_order_ids)) {
                $questions = $ordered_questions;
            } else {
                $questions = self::shuffle_question_payload_if_needed($questions, $should_shuffle);
                $active_question_order_ids = self::extract_question_ids_from_payload($questions);
                $ordered_questions = $questions;
            }
        } else {
            $questions = $ordered_questions;
        }

        if (empty($ordered_questions) && !empty($questions)) {
            $ordered_questions = array_values($questions);
        }

        if (empty($active_question_order_ids)) {
            $active_question_order_ids = self::extract_question_ids_from_payload($ordered_questions);
        }

        if (empty($canonical_question_order_ids)) {
            $canonical_question_order_ids = $active_question_order_ids;
        } elseif (!empty($active_question_order_ids)) {
            $canonical_question_order_ids = self::merge_attempt_question_order_ids(
                $canonical_question_order_ids,
                $active_question_order_ids
            );
        }

        $question_number_map = self::build_attempt_question_number_map(
            $canonical_question_order_ids,
            $active_question_order_ids
        );

        return [
            'ordered_questions' => $ordered_questions,
            'active_question_order_ids' => $active_question_order_ids,
            'canonical_question_order_ids' => $canonical_question_order_ids,
            'display_number_map' => $question_number_map,
            'question_order_signature' => self::build_attempt_question_order_signature(
                $active_question_order_ids,
                $question_number_map
            ),
            'stale_attempt_order' => $stale_attempt_order,
            'preserve_removed_question_history' => $preserve_removed_question_history,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,int> $existing_question_order_ids
     * @return array<int,int>
     */
    private static function reconcile_in_progress_question_order(
        array $questions,
        array $existing_question_order_ids,
        string $attempt_started_at = '',
        bool $append_recent_questions_last = false
    ): array
    {
        $active_questions = array_values(array_filter($questions, static function ($question_row): bool {
            $question = (array) $question_row;
            return (int) ($question['is_active'] ?? 1) === 1;
        }));
        $active_question_ids = self::extract_question_ids_from_payload($active_questions);
        if (empty($active_question_ids)) {
            $active_question_ids = self::extract_question_ids_from_payload($questions);
        }
        if (empty($active_question_ids)) {
            return [];
        }

        $active_lookup = array_fill_keys($active_question_ids, true);
        $active_question_created_at = [];
        foreach ($active_questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_created_ts = self::local_datetime_to_timestamp((string) ($question['created_at'] ?? ''));
            if ($question_created_ts !== null) {
                $active_question_created_at[$question_id] = $question_created_ts;
            }
        }
        $existing_lookup = array_fill_keys(array_values(array_filter(array_map('intval', $existing_question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })), true);
        $reconciled = [];
        $reconciled_lookup = [];
        foreach ($existing_question_order_ids as $question_id) {
            $question_id = (int) $question_id;
            if (
                $question_id <= 0
                || !isset($active_lookup[$question_id])
                || isset($reconciled_lookup[$question_id])
            ) {
                continue;
            }
            $reconciled[] = $question_id;
            $reconciled_lookup[$question_id] = true;
        }

        $missing_active_question_ids = [];
        $missing_active_question_created_at = [];
        foreach ($active_question_ids as $question_id) {
            if (isset($reconciled_lookup[$question_id])) {
                continue;
            }

            $missing_active_question_ids[] = $question_id;
            if (!$append_recent_questions_last || isset($existing_lookup[$question_id])) {
                continue;
            }
            if (isset($active_question_created_at[$question_id])) {
                $missing_active_question_created_at[$question_id] = (int) $active_question_created_at[$question_id];
            }
        }

        if ($append_recent_questions_last && count($missing_active_question_ids) > 1) {
            usort($missing_active_question_ids, static function (int $left, int $right) use ($missing_active_question_created_at): int {
                $left_created_at = isset($missing_active_question_created_at[$left])
                    ? (int) $missing_active_question_created_at[$left]
                    : PHP_INT_MAX;
                $right_created_at = isset($missing_active_question_created_at[$right])
                    ? (int) $missing_active_question_created_at[$right]
                    : PHP_INT_MAX;

                if ($left_created_at === $right_created_at) {
                    if ($left === $right) {
                        return 0;
                    }

                    return ($left < $right) ? -1 : 1;
                }

                return ($left_created_at < $right_created_at) ? -1 : 1;
            });
        }

        foreach ($missing_active_question_ids as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || isset($reconciled_lookup[$question_id])) {
                continue;
            }

            $reconciled[] = $question_id;
            $reconciled_lookup[$question_id] = true;
        }

        return $reconciled;
    }

    /**
     * @param array<int,int> $left
     * @param array<int,int> $right
     */
    private static function question_id_lists_differ(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return true;
        }

        foreach ($left as $index => $question_id) {
            if ((int) $question_id !== (int) ($right[$index] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<int,int> $question_ids
     */
    private static function clear_attempt_answers_for_questions(array $attempt, int $duration_minutes, array $question_ids): void
    {
        global $wpdb;

        $attempt_id = (int) ($attempt['id'] ?? 0);
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if ($attempt_id <= 0 || empty($question_ids)) {
            return;
        }

        $answer_table = $wpdb->prefix . 'cbt_answers';
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$answer_table}
                 WHERE attempt_id = %d
                   AND question_id IN ({$placeholders})",
                $attempt_id,
                ...$question_ids
            )
        );

        if (CBT_Runtime::is_ready()) {
            $clear_entries = array_map(static function (int $question_id): array {
                return [
                    'question_id' => $question_id,
                    'clear' => true,
                ];
            }, $question_ids);
            CBT_Runtime::buffer_entries($attempt, $duration_minutes, $clear_entries);
            CBT_Runtime::flush_attempt($attempt_id, true);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,int>
     */
    private static function extract_question_ids_from_payload(array $questions): array
    {
        return array_values(array_filter(array_map('intval', array_column($questions, 'id')), static function (int $question_id): bool {
            return $question_id > 0;
        }));
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
     * @param array<string,mixed> $attempt
     * @return array<int,int>
     */
    private static function resolve_attempt_snapshot_question_order_ids(array $attempt): array
    {
        $persisted_question_order_ids = self::normalize_question_order_ids($attempt['question_order'] ?? '');
        if (!empty($persisted_question_order_ids)) {
            return $persisted_question_order_ids;
        }

        $attempt_id = (int) ($attempt['id'] ?? 0);
        if ($attempt_id <= 0 || !CBT_Runtime::is_ready()) {
            return [];
        }

        $runtime_question_order_ids = CBT_Runtime::get_attempt_question_order($attempt_id, $runtime_order_found);
        if (!$runtime_order_found) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $runtime_question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));
    }

    /**
     * @param array<int,int> $primary_question_order_ids
     * @param array<int,int> $secondary_question_order_ids
     * @return array<int,int>
     */
    private static function merge_attempt_question_order_ids(array $primary_question_order_ids, array $secondary_question_order_ids = []): array
    {
        $primary_question_order_ids = array_values(array_unique(array_filter(array_map('intval', $primary_question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        $secondary_question_order_ids = array_values(array_unique(array_filter(array_map('intval', $secondary_question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));

        if (empty($primary_question_order_ids)) {
            return $secondary_question_order_ids;
        }
        if (empty($secondary_question_order_ids)) {
            return $primary_question_order_ids;
        }

        $merged_question_order_ids = $primary_question_order_ids;
        $merged_lookup = array_fill_keys($merged_question_order_ids, true);
        foreach ($secondary_question_order_ids as $question_id) {
            if (isset($merged_lookup[$question_id])) {
                continue;
            }

            $merged_question_order_ids[] = $question_id;
            $merged_lookup[$question_id] = true;
        }

        return $merged_question_order_ids;
    }

    /**
     * @param mixed $raw_option_order
     * @return array<int,array<int,string>>
     */
    private static function normalize_attempt_option_order_map($raw_option_order): array
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

    /**
     * @param array<int,array<int,string>> $primary_option_order_map
     * @param array<int,array<int,string>> $secondary_option_order_map
     * @return array<int,array<int,string>>
     */
    private static function merge_attempt_option_order_maps(array $primary_option_order_map, array $secondary_option_order_map = []): array
    {
        $primary_option_order_map = self::normalize_attempt_option_order_map($primary_option_order_map);
        $secondary_option_order_map = self::normalize_attempt_option_order_map($secondary_option_order_map);

        if (empty($primary_option_order_map)) {
            return $secondary_option_order_map;
        }
        if (empty($secondary_option_order_map)) {
            return $primary_option_order_map;
        }

        $merged_option_order_map = $primary_option_order_map;
        foreach ($secondary_option_order_map as $question_id => $item_order) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || isset($merged_option_order_map[$safe_question_id]) || empty($item_order)) {
                continue;
            }

            $merged_option_order_map[$safe_question_id] = array_values($item_order);
        }

        return $merged_option_order_map;
    }

    /**
     * @param array<int,array<int,string>> $option_order_map
     */
    private static function encode_attempt_option_order_map(array $option_order_map): string
    {
        $normalized_option_order_map = self::normalize_attempt_option_order_map($option_order_map);
        if (empty($normalized_option_order_map)) {
            return '{}';
        }

        ksort($normalized_option_order_map);
        $encoded = wp_json_encode($normalized_option_order_map);
        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @param array<int,array<int,string>> $left_option_order_map
     * @param array<int,array<int,string>> $right_option_order_map
     */
    private static function attempt_option_order_maps_differ(array $left_option_order_map, array $right_option_order_map): bool
    {
        return self::encode_attempt_option_order_map($left_option_order_map) !== self::encode_attempt_option_order_map($right_option_order_map);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<int,string>>
     */
    private static function build_attempt_option_order_map(array $questions, bool $shuffle_items): array
    {
        $option_order_map = [];

        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0 || !self::question_supports_option_randomization($question)) {
                continue;
            }

            $force_shuffle = in_array((string) ($question['question_type'] ?? ''), ['ordering', 'matching'], true);
            if (!$shuffle_items && !$force_shuffle) {
                continue;
            }

            $item_tokens = self::extract_randomizable_question_item_keys($question);
            if (count($item_tokens) <= 1) {
                continue;
            }

            if ($shuffle_items || $force_shuffle) {
                shuffle($item_tokens);
            }

            $option_order_map[$question_id] = array_values($item_tokens);
        }

        return self::normalize_attempt_option_order_map($option_order_map);
    }

    /**
     * @param array<int,array<int,string>> $option_tokens_by_question
     * @return array<int,array<int,string>>
     */
    private static function build_attempt_option_order_map_from_snapshot_tokens(array $option_tokens_by_question, bool $shuffle_items, array $force_shuffle_question_ids = []): array
    {
        $option_order_map = [];
        $normalized_tokens_by_question = self::normalize_attempt_option_order_map($option_tokens_by_question);
        $force_shuffle_lookup = array_fill_keys(array_values(array_filter(array_map('intval', $force_shuffle_question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })), true);
        foreach ($normalized_tokens_by_question as $question_id => $item_tokens) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || count($item_tokens) <= 1) {
                continue;
            }

            if (!$shuffle_items && empty($force_shuffle_lookup[$safe_question_id])) {
                continue;
            }

            $shuffled_tokens = array_values($item_tokens);
            shuffle($shuffled_tokens);
            $option_order_map[$safe_question_id] = $shuffled_tokens;
        }

        return self::normalize_attempt_option_order_map($option_order_map);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,array<int,string>> $existing_option_order_map
     * @return array<int,array<int,string>>
     */
    private static function resolve_attempt_option_order_map(array $questions, array $existing_option_order_map): array
    {
        $resolved_option_order_map = [];
        $existing_option_order_map = self::normalize_attempt_option_order_map($existing_option_order_map);

        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0 || !self::question_supports_option_randomization($question)) {
                continue;
            }

            $current_tokens = self::extract_randomizable_question_item_keys($question);
            if (count($current_tokens) <= 1) {
                continue;
            }

            $resolved_tokens = self::reconcile_attempt_option_order_tokens(
                $current_tokens,
                (array) ($existing_option_order_map[$question_id] ?? [])
            );

            if (!empty($resolved_tokens)) {
                $resolved_option_order_map[$question_id] = array_values($resolved_tokens);
            }
        }

        return self::normalize_attempt_option_order_map($resolved_option_order_map);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,array<int,string>> $option_order_map
     * @return array<int,array<string,mixed>>
     */
    private static function apply_attempt_option_order_to_questions(array $questions, array $option_order_map): array
    {
        $option_order_map = self::normalize_attempt_option_order_map($option_order_map);
        if (empty($questions) || empty($option_order_map)) {
            return $questions;
        }

        foreach ($questions as $index => $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0 || !isset($option_order_map[$question_id])) {
                continue;
            }

            $question_type = (string) ($question['question_type'] ?? '');
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'ordering', 'matching', 'categorization'], true)) {
                $ordered_options = self::order_question_options_by_attempt_sequence(
                    is_array($question['options'] ?? null) ? $question['options'] : [],
                    $option_order_map[$question_id]
                );
                foreach ($ordered_options as $option_index => $option_row) {
                    $option = (array) $option_row;
                    $option['option_key'] = self::build_display_option_key($option_index);
                    $ordered_options[$option_index] = $option;
                }
                $question['options'] = $ordered_options;
            } elseif ($question_type === 'true_false_matrix') {
                $matrix_meta = isset($question['true_false_matrix_meta']) && is_array($question['true_false_matrix_meta'])
                    ? $question['true_false_matrix_meta']
                    : [];
                $matrix_meta['items'] = self::order_true_false_matrix_items_by_attempt_sequence(
                    isset($matrix_meta['items']) && is_array($matrix_meta['items']) ? $matrix_meta['items'] : [],
                    $option_order_map[$question_id]
                );
                $question['true_false_matrix_meta'] = $matrix_meta;
            }

            $questions[$index] = $question;
        }

        return $questions;
    }

    /**
     * @param array<string,mixed> $question
     */
    private static function question_supports_option_randomization(array $question): bool
    {
        $question_type = (string) ($question['question_type'] ?? '');
        if ($question_type === 'ordering') {
            $meta = isset($question['ordering_meta']) && is_array($question['ordering_meta'])
                ? $question['ordering_meta']
                : [];
            return ((int) ($meta['shuffle_items'] ?? 1) !== 0);
        }

        return in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false_matrix', 'matching', 'categorization'], true);
    }

    /**
     * @param array<string,mixed> $question
     * @return array<int,string>
     */
    private static function extract_randomizable_question_item_keys(array $question): array
    {
        $question_type = (string) ($question['question_type'] ?? '');

        if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'ordering', 'matching', 'categorization'], true)) {
            $tokens = [];
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            foreach ($options as $option_row) {
                $option_id = (int) (((array) $option_row)['id'] ?? 0);
                if ($option_id <= 0) {
                    continue;
                }

                $tokens[] = (string) $option_id;
            }
            return array_values(array_unique($tokens));
        }

        if ($question_type === 'true_false_matrix') {
            $matrix_meta = isset($question['true_false_matrix_meta']) && is_array($question['true_false_matrix_meta'])
                ? $question['true_false_matrix_meta']
                : [];
            $items = isset($matrix_meta['items']) && is_array($matrix_meta['items']) ? $matrix_meta['items'] : [];
            $tokens = [];
            foreach ($items as $item_index => $item_row) {
                $item = (array) $item_row;
                $item_key = trim((string) ($item['key'] ?? ($item_index + 1)));
                if ($item_key === '') {
                    continue;
                }

                $tokens[] = $item_key;
            }
            return array_values(array_unique($tokens));
        }

        return [];
    }

    /**
     * @param array<int,string> $current_tokens
     * @param array<int,string> $requested_tokens
     * @return array<int,string>
     */
    private static function reconcile_attempt_option_order_tokens(array $current_tokens, array $requested_tokens): array
    {
        $current_tokens = array_values(array_unique(array_filter(array_map('strval', $current_tokens), static function (string $token): bool {
            return $token !== '';
        })));
        $requested_tokens = array_values(array_unique(array_filter(array_map('strval', $requested_tokens), static function (string $token): bool {
            return trim($token) !== '';
        })));

        if (empty($current_tokens) || empty($requested_tokens)) {
            return [];
        }

        $allowed_tokens = array_fill_keys($current_tokens, true);
        $ordered_tokens = [];
        $ordered_lookup = [];
        foreach ($requested_tokens as $token) {
            if (!isset($allowed_tokens[$token]) || isset($ordered_lookup[$token])) {
                continue;
            }

            $ordered_tokens[] = $token;
            $ordered_lookup[$token] = true;
        }

        if (empty($ordered_tokens)) {
            return $current_tokens;
        }

        foreach ($current_tokens as $token) {
            if (isset($ordered_lookup[$token])) {
                continue;
            }

            $ordered_tokens[] = $token;
        }

        return $ordered_tokens;
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $option_order_tokens
     * @return array<int,array<string,mixed>>
     */
    private static function order_question_options_by_attempt_sequence(array $options, array $option_order_tokens): array
    {
        if (empty($options) || empty($option_order_tokens)) {
            return array_values($options);
        }

        $options_by_id = [];
        foreach ($options as $option_row) {
            $option = (array) $option_row;
            $option_id = (int) ($option['id'] ?? 0);
            if ($option_id <= 0) {
                continue;
            }

            $options_by_id[$option_id] = $option;
        }

        $ordered_options = [];
        $ordered_lookup = [];
        foreach ($option_order_tokens as $option_token) {
            $option_id = (int) $option_token;
            if ($option_id <= 0 || !isset($options_by_id[$option_id]) || isset($ordered_lookup[$option_id])) {
                continue;
            }

            $ordered_options[] = $options_by_id[$option_id];
            $ordered_lookup[$option_id] = true;
        }

        foreach ($options as $option_row) {
            $option = (array) $option_row;
            $option_id = (int) ($option['id'] ?? 0);
            if ($option_id <= 0 || isset($ordered_lookup[$option_id])) {
                continue;
            }

            $ordered_options[] = $option;
        }

        return array_values($ordered_options);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<int,string> $item_order_tokens
     * @return array<int,array<string,mixed>>
     */
    private static function order_true_false_matrix_items_by_attempt_sequence(array $items, array $item_order_tokens): array
    {
        if (empty($items) || empty($item_order_tokens)) {
            return array_values($items);
        }

        $items_by_key = [];
        foreach ($items as $item_index => $item_row) {
            $item = (array) $item_row;
            $item_key = trim((string) ($item['key'] ?? ($item_index + 1)));
            if ($item_key === '') {
                continue;
            }

            $items_by_key[$item_key] = $item;
        }

        $ordered_items = [];
        $ordered_lookup = [];
        foreach ($item_order_tokens as $item_token) {
            $token = trim((string) $item_token);
            if ($token === '' || !isset($items_by_key[$token]) || isset($ordered_lookup[$token])) {
                continue;
            }

            $ordered_items[] = $items_by_key[$token];
            $ordered_lookup[$token] = true;
        }

        foreach ($items as $item_index => $item_row) {
            $item = (array) $item_row;
            $item_key = trim((string) ($item['key'] ?? ($item_index + 1)));
            if ($item_key === '' || isset($ordered_lookup[$item_key])) {
                continue;
            }

            $ordered_items[] = $item;
        }

        return array_values($ordered_items);
    }

    private static function build_display_option_key(int $index): string
    {
        $code = 65 + $index;
        if ($code >= 65 && $code <= 90) {
            return chr($code);
        }

        return (string) ($index + 1);
    }

    /**
     * @param array<int,int> $preferred_question_order_ids
     * @param array<int,int> $fallback_question_order_ids
     * @return array<int,int>
     */
    private static function build_attempt_question_number_map(array $preferred_question_order_ids, array $fallback_question_order_ids = []): array
    {
        $question_number_map = [];
        $next_number = 1;

        foreach ([$preferred_question_order_ids, $fallback_question_order_ids] as $question_id_list) {
            foreach ($question_id_list as $question_id) {
                $question_id = (int) $question_id;
                if ($question_id <= 0 || isset($question_number_map[$question_id])) {
                    continue;
                }

                $question_number_map[$question_id] = $next_number;
                $next_number++;
            }
        }

        return $question_number_map;
    }

    /**
     * @param array<int,int> $question_order_ids
     * @param array<int,int> $question_number_map
     */
    private static function build_attempt_question_order_signature(array $question_order_ids, array $question_number_map): string
    {
        $signature_parts = [];

        foreach ($question_order_ids as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0) {
                continue;
            }

            $signature_parts[] = $question_id . ':' . (int) ($question_number_map[$question_id] ?? 0);
        }

        if (empty($signature_parts)) {
            return '';
        }

        return sha1(implode('|', $signature_parts));
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function build_attempt_question_order_signature_from_attempt(array $attempt): string
    {
        $raw_question_order = $attempt['question_order'] ?? '';
        $question_order_ids = [];

        if (is_array($raw_question_order)) {
            $question_order_ids = $raw_question_order;
        } elseif (is_string($raw_question_order) && trim($raw_question_order) !== '') {
            $decoded_question_order = json_decode($raw_question_order, true);
            if (is_array($decoded_question_order)) {
                $question_order_ids = $decoded_question_order;
            }
        }

        $question_order_ids = array_values(array_filter(array_map('intval', $question_order_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        if (empty($question_order_ids)) {
            return '';
        }

        $question_number_map = self::build_attempt_question_number_map($question_order_ids, $question_order_ids);
        return self::build_attempt_question_order_signature($question_order_ids, $question_number_map);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,int> $question_number_map
     * @return array<int,array<string,mixed>>
     */
    private static function apply_question_numbers_to_payload(array $questions, array $question_number_map): array
    {
        foreach ($questions as $index => $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_number = (int) ($question_number_map[$question_id] ?? 0);
            if ($question_number > 0) {
                $question['question_number'] = $question_number;
            } else {
                unset($question['question_number']);
            }
            $questions[$index] = $question;
        }

        return $questions;
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function build_question_manifest(array $questions): array
    {
        $manifest = [];

        foreach ($questions as $question) {
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $manifest_item = [
                'id' => $question_id,
                'question_type' => (string) ($question['question_type'] ?? ''),
                'updated_at' => (string) ($question['updated_at'] ?? ''),
            ];
            $question_text = (string) ($question['question_text'] ?? '');
            if ($question_text !== '') {
                $manifest_item['question_text'] = $question_text;
            }
            if (array_key_exists('points', $question)) {
                $manifest_item['points'] = (float) ($question['points'] ?? 0);
            }
            $question_number = (int) ($question['question_number'] ?? 0);
            if ($question_number > 0) {
                $manifest_item['question_number'] = $question_number;
            }

            $question_type = (string) ($question['question_type'] ?? '');
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false', 'ordering', 'matching', 'categorization'], true)) {
                $manifest_item['options'] = array_map(static function ($option): array {
                    $option_row = (array) $option;
                    return [
                        'id' => (int) ($option_row['id'] ?? 0),
                        'option_key' => (string) ($option_row['option_key'] ?? ''),
                        'option_text' => (string) ($option_row['option_text'] ?? ''),
                    ];
                }, is_array($question['options'] ?? null) ? $question['options'] : []);
            }

            if ($question_type === 'ordering' && isset($question['ordering_meta']) && is_array($question['ordering_meta'])) {
                $manifest_item['ordering_meta'] = $question['ordering_meta'];
            }

            if ($question_type === 'true_false_matrix' && isset($question['true_false_matrix_meta']) && is_array($question['true_false_matrix_meta'])) {
                $manifest_item['true_false_matrix_meta'] = $question['true_false_matrix_meta'];
            }

            if ($question_type === 'short_answer' && isset($question['short_answer_meta']) && is_array($question['short_answer_meta'])) {
                $manifest_item['short_answer_meta'] = $question['short_answer_meta'];
            }

            if ($question_type === 'matching' && isset($question['matching_meta']) && is_array($question['matching_meta'])) {
                $manifest_item['matching_meta'] = $question['matching_meta'];
            }

            if ($question_type === 'cloze_dropdown' && isset($question['cloze_dropdown_meta']) && is_array($question['cloze_dropdown_meta'])) {
                $manifest_item['cloze_dropdown_meta'] = $question['cloze_dropdown_meta'];
            }

            if ($question_type === 'categorization' && isset($question['categorization_meta']) && is_array($question['categorization_meta'])) {
                $manifest_item['categorization_meta'] = $question['categorization_meta'];
            }

            if ($question_type === 'table_completion' && isset($question['table_completion_meta']) && is_array($question['table_completion_meta'])) {
                $manifest_item['table_completion_meta'] = $question['table_completion_meta'];
            }

            $manifest[] = $manifest_item;
        }

        return $manifest;
    }

    /**
     * @param array<string,mixed>|null $attempt
     * @return array<int,int>
     */
    private static function get_attempt_answered_question_ids(int $attempt_id, ?array $attempt = null, int $duration_minutes = 0, bool $prefer_runtime = true): array
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        if ($prefer_runtime && is_array($attempt) && CBT_Runtime::is_ready()) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
            $runtime_answers = CBT_Runtime::get_existing_answers_map($attempt_id, $runtime_state_found);
            if ($runtime_state_found) {
                $answered_question_ids = [];
                foreach ($runtime_answers as $question_id => $answer_row) {
                    $question_id = (int) $question_id;
                    if ($question_id <= 0 || !self::answer_row_has_value((array) $answer_row)) {
                        continue;
                    }
                    $answered_question_ids[$question_id] = $question_id;
                }

                return array_values($answered_question_ids);
            }
        }

        $answer_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, selected_option_ids, answer_text, answered_at, updated_at
                 FROM {$wpdb->prefix}cbt_answers
                 WHERE attempt_id = %d",
                $attempt_id
            ),
            ARRAY_A
        );

        if (!is_array($answer_rows) || empty($answer_rows)) {
            return [];
        }

        $answered_question_ids = [];
        foreach ($answer_rows as $answer_row) {
            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id <= 0 || !self::answer_row_has_value((array) $answer_row)) {
                continue;
            }
            $answered_question_ids[$question_id] = $question_id;
        }

        return array_values($answered_question_ids);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed>|null $attempt
     * @return array<string,mixed>
     */
    private static function build_attempt_existing_answers_map(array $questions, int $attempt_id, ?array $attempt = null, int $duration_minutes = 0, bool $prefer_runtime = true): array
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $existing_answers = [];
        if ($prefer_runtime && is_array($attempt) && CBT_Runtime::is_ready()) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
            $existing_answers = CBT_Runtime::get_existing_answers_map($attempt_id, $runtime_state_found);
            if (!$runtime_state_found) {
                $existing_answers = [];
            }
        }

        if (empty($existing_answers)) {
            $answer_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT question_id, selected_option_ids, answer_text, answered_at, updated_at
                     FROM {$wpdb->prefix}cbt_answers
                     WHERE attempt_id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );

            foreach ((array) $answer_rows as $answer_row) {
                $question_id = (int) ($answer_row['question_id'] ?? 0);
                if ($question_id > 0) {
                    $existing_answers[$question_id] = (array) $answer_row;
                }
            }
        }

        if (empty($existing_answers)) {
            return [];
        }

        $questions_by_id = [];
        foreach ($questions as $question) {
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id > 0) {
                $questions_by_id[$question_id] = (array) $question;
            }
        }

        $existing_answers_map = [];
        foreach ($existing_answers as $question_id => $answer_row) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || !isset($questions_by_id[$question_id])) {
                continue;
            }
            if (!self::answer_row_has_value((array) $answer_row)) {
                continue;
            }

            $question_payload = [$questions_by_id[$question_id]];
            self::apply_existing_answer_map_to_question_payload($question_payload, [
                $question_id => (array) $answer_row,
            ]);

            if (isset($question_payload[0]['existing_answer'])) {
                $existing_answers_map[(string) $question_id] = $question_payload[0]['existing_answer'];
            }
        }

        return $existing_answers_map;
    }

    /**
     * @param array<string,mixed> $answer_row
     */
    private static function answer_row_has_value(array $answer_row): bool
    {
        if (!empty($answer_row['clear'])) {
            return false;
        }

        $selected_option_ids = self::decode_selected_option_ids($answer_row['selected_option_ids'] ?? null);
        if (!empty($selected_option_ids)) {
            return true;
        }

        $answer_text = (string) ($answer_row['answer_text'] ?? '');
        if ($answer_text === '') {
            return false;
        }

        $decoded_answer = json_decode($answer_text, true);
        if (is_array($decoded_answer)) {
            return self::decoded_answer_has_value($decoded_answer);
        }

        return trim($answer_text) !== '';
    }

    private static function answer_row_is_stale_for_question_updated_at(array $answer_row, string $question_updated_at): bool
    {
        $question_updated_at = trim($question_updated_at);
        if ($question_updated_at === '') {
            return false;
        }

        $question_updated_ts = strtotime($question_updated_at);
        if ($question_updated_ts === false || $question_updated_ts <= 0) {
            return false;
        }

        $answer_updated_at = trim((string) ($answer_row['updated_at'] ?? ($answer_row['answered_at'] ?? '')));
        if ($answer_updated_at === '') {
            return false;
        }

        $answer_updated_ts = strtotime($answer_updated_at);
        if ($answer_updated_ts === false || $answer_updated_ts <= 0) {
            return false;
        }

        return $question_updated_ts > $answer_updated_ts;
    }

    /**
     * @param array<int|string> $question_ids
     * @return array<int,string>
     */
    private static function get_question_updated_at_lookup(array $question_ids): array
    {
        global $wpdb;

        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if (empty($question_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $question_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, updated_at
                 FROM {$wpdb->prefix}cbt_questions
                 WHERE id IN ({$placeholders})",
                ...$question_ids
            ),
            ARRAY_A
        );

        $lookup = [];
        foreach ((array) $question_rows as $question_row) {
            $question_id = (int) ($question_row['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $lookup[$question_id] = (string) ($question_row['updated_at'] ?? '');
        }

        return $lookup;
    }

    /**
     * @param mixed $value
     */
    private static function decoded_answer_has_value($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::decoded_answer_has_value($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,int> $question_order_ids
     * @return array<int,array<string,mixed>>
     */
    private static function order_question_payload_by_ids(array $questions, array $question_order_ids): array
    {
        $by_id = [];
        foreach ($questions as $question) {
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id > 0) {
                $by_id[$question_id] = $question;
            }
        }

        $ordered = [];
        foreach ($question_order_ids as $question_id) {
            if (isset($by_id[$question_id])) {
                $ordered[] = $by_id[$question_id];
            }
        }

        return $ordered;
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function shuffle_question_payload_if_needed(array $questions, bool $should_shuffle): array
    {
        if ($should_shuffle && !empty($questions)) {
            shuffle($questions);
        }

        return $questions;
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,int> $question_ids
     */
    private static function merge_existing_answers_into_question_payload(array &$questions, int $attempt_id, array $question_ids = []): void
    {
        global $wpdb;

        if ($attempt_id <= 0) {
            return;
        }

        $question_ids = array_values(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));

        $query = "SELECT question_id, selected_option_ids, answer_text
                 FROM {$wpdb->prefix}cbt_answers
                 WHERE attempt_id = %d";
        $params = [$attempt_id];

        if (!empty($question_ids)) {
            $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
            $query .= " AND question_id IN ({$placeholders})";
            $params = array_merge($params, $question_ids);
        }

        $query = str_replace('SELECT question_id, selected_option_ids, answer_text', 'SELECT question_id, selected_option_ids, answer_text, answered_at, updated_at', $query);

        $answer_rows = $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );

        $existing_answers = [];
        foreach ((array) $answer_rows as $answer_row) {
            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id > 0) {
                $existing_answers[$question_id] = (array) $answer_row;
            }
        }

        self::apply_existing_answer_map_to_question_payload($questions, $existing_answers);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,array<string,mixed>> $existing_answers
     */
    private static function apply_existing_answer_map_to_question_payload(array &$questions, array $existing_answers): void
    {
        foreach ($questions as &$question) {
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0 || !isset($existing_answers[$question_id])) {
                continue;
            }

            $existing_answer_row = $existing_answers[$question_id];
            $selected_option_ids = self::decode_selected_option_ids($existing_answer_row['selected_option_ids'] ?? null);
            $existing_text = (string) ($existing_answer_row['answer_text'] ?? '');

            switch ((string) ($question['question_type'] ?? '')) {
                case 'multiple_choice':
                case 'true_false':
                    if (!empty($selected_option_ids)) {
                        $question['existing_answer'] = (int) $selected_option_ids[0];
                    }
                    break;
                case 'multiple_answer':
                    if (!empty($selected_option_ids)) {
                        $question['existing_answer'] = $selected_option_ids;
                    }
                    break;
                case 'ordering':
                    $ordered_option_ids = self::decode_ordered_selected_option_ids($existing_answer_row['selected_option_ids'] ?? null);
                    if (!empty($ordered_option_ids)) {
                        $question['existing_answer'] = $ordered_option_ids;
                    }
                    break;
                case 'true_false_matrix':
                    $existing_matrix = self::normalize_true_false_matrix_submission($existing_text);
                    if (!empty($existing_matrix)) {
                        $question['existing_answer'] = $existing_matrix;
                    }
                    break;
                case 'matching':
                case 'cloze_dropdown':
                case 'categorization':
                    $existing_map = self::normalize_dropdown_option_id_submission($existing_text);
                    if (!empty($existing_map)) {
                        $question['existing_answer'] = $existing_map;
                    }
                    break;
                case 'table_completion':
                    $decoded_table_answer = json_decode($existing_text, true);
                    $table_answer = [];
                    if (is_array($decoded_table_answer)) {
                        foreach ($decoded_table_answer as $key => $value) {
                            $safe_key = strtoupper(trim((string) $key));
                            if ($safe_key === '' || !is_scalar($value)) {
                                continue;
                            }
                            $safe_value = trim((string) $value);
                            if ($safe_value === '') {
                                continue;
                            }
                            $table_answer[$safe_key] = is_numeric($safe_value) ? (int) $safe_value : $safe_value;
                        }
                    }
                    if (!empty($table_answer)) {
                        $question['existing_answer'] = $table_answer;
                    }
                    break;
                case 'short_answer':
                    $submitted_values = self::extract_short_answer_submission_values($existing_text);
                    $input_keys = [];
                    if (isset($question['short_answer_meta']['input_keys']) && is_array($question['short_answer_meta']['input_keys'])) {
                        $input_keys = array_values($question['short_answer_meta']['input_keys']);
                    }
                    if (empty($input_keys)) {
                        $input_keys = ['A'];
                    }
                    $existing_short = [];
                    foreach ($submitted_values as $idx => $value) {
                        if (!isset($input_keys[$idx])) {
                            continue;
                        }
                        $key = strtoupper(trim((string) $input_keys[$idx]));
                        if ($key === '') {
                            continue;
                        }
                        $existing_short[$key] = (string) $value;
                    }
                    if (!empty($existing_short)) {
                        $question['existing_answer'] = $existing_short;
                    }
                    break;
                case 'essay':
                default:
                    if ($existing_text !== '') {
                        $question['existing_answer'] = $existing_text;
                    }
                    break;
            }
        }
        unset($question);
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<string,mixed>>
     */
    private static function get_cached_question_submission_contexts(array $question_ids): array
    {
        return CBT_Question_Submission_Context_Cache::get_snapshots($question_ids);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_cached_question_submission_context(int $question_id): ?array
    {
        return CBT_Question_Submission_Context_Cache::get_snapshot($question_id);
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_attempt_for_ui_state(int $attempt_id, int $user_id)
    {
        global $wpdb;

        if ($attempt_id <= 0 || $user_id <= 0) {
            return new WP_Error('invalid_attempt_id', 'Attempt tidak valid.', ['status' => 400]);
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt tidak ditemukan.', ['status' => 404]);
        }

        if ((int) ($attempt['student_id'] ?? 0) !== $user_id) {
            return new WP_Error('forbidden', 'Anda tidak dapat mengakses attempt ini.', ['status' => 403]);
        }

        return $attempt;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function get_attempt_for_question_revision(int $attempt_id, int $user_id, string $role)
    {
        global $wpdb;

        if ($attempt_id <= 0 || $user_id <= 0) {
            return new WP_Error('invalid_attempt_id', 'Attempt tidak valid.', ['status' => 400]);
        }

        if (in_array($role, ['siswa', 'student'], true)) {
            $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
            if (is_array($runtime_attempt)) {
                return $runtime_attempt;
            }
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.id, a.exam_id, a.student_id, a.status, a.started_at, a.extra_time_minutes, a.question_order, a.option_order, e.created_by, e.duration_minutes AS exam_duration_minutes
                 FROM {$attempt_table} a
                 LEFT JOIN {$exam_table} e ON e.id = a.exam_id
                 WHERE a.id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt tidak ditemukan.', ['status' => 404]);
        }

        if (in_array($role, ['siswa', 'student'], true)) {
            if ((int) ($attempt['student_id'] ?? 0) !== $user_id) {
                return new WP_Error('forbidden', 'Anda tidak dapat mengakses attempt ini.', ['status' => 403]);
            }

            if ((string) ($attempt['status'] ?? '') === 'in_progress') {
                $duration_minutes = self::resolve_attempt_duration_minutes(
                    $attempt,
                    (int) ($attempt['exam_duration_minutes'] ?? 0)
                );
                if ($duration_minutes > 0) {
                    self::ensure_runtime_attempt_state($attempt, $duration_minutes);
                }
            }

            return $attempt;
        }

        if (in_array($role, ['guru', 'teacher'], true) && (int) ($attempt['created_by'] ?? 0) !== $user_id) {
            return new WP_Error('forbidden', 'Anda tidak dapat mengakses attempt ini.', ['status' => 403]);
        }

        return $attempt;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_live_runtime_attempt_envelope(int $attempt_id, int $user_id): ?array
    {
        if ($attempt_id <= 0 || $user_id <= 0 || !CBT_Runtime::is_ready()) {
            return null;
        }

        $runtime_attempt = CBT_Runtime::get_attempt_meta($attempt_id, $state_found);
        if (!$state_found || !is_array($runtime_attempt) || empty($runtime_attempt)) {
            return null;
        }

        if ((int) ($runtime_attempt['student_id'] ?? 0) !== $user_id) {
            return null;
        }

        if ((string) ($runtime_attempt['status'] ?? '') !== 'in_progress') {
            return null;
        }

        $runtime_question_order_ids = CBT_Runtime::get_attempt_question_order($attempt_id, $runtime_question_order_found);
        if (!$runtime_question_order_found) {
            $runtime_question_order_ids = [];
        }
        $runtime_option_order_map = CBT_Runtime::get_attempt_option_order($attempt_id, $runtime_option_order_found);
        if (!$runtime_option_order_found) {
            $runtime_option_order_map = [];
        }

        return [
            'id' => (int) ($runtime_attempt['attempt_id'] ?? $attempt_id),
            'attempt_id' => (int) ($runtime_attempt['attempt_id'] ?? $attempt_id),
            'exam_id' => (int) ($runtime_attempt['exam_id'] ?? 0),
            'student_id' => (int) ($runtime_attempt['student_id'] ?? 0),
            'status' => (string) ($runtime_attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($runtime_attempt['started_at'] ?? ''),
            'extra_time_minutes' => max(0, (int) ($runtime_attempt['extra_time_minutes'] ?? 0)),
            'duration_minutes' => max(1, (int) ($runtime_attempt['duration_minutes'] ?? 60)),
            'exam_duration_minutes' => max(1, (int) ($runtime_attempt['duration_minutes'] ?? 60)),
            'question_order' => !empty($runtime_question_order_ids)
                ? ((string) (wp_json_encode($runtime_question_order_ids) ?: '[]'))
                : '',
            'option_order' => !empty($runtime_option_order_map)
                ? self::encode_attempt_option_order_map($runtime_option_order_map)
                : '',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_attempt_for_question_payload(int $attempt_id, int $user_id, string $role, string $attempt_table, int $exam_duration_minutes): ?array
    {
        global $wpdb;

        if ($attempt_id <= 0) {
            return null;
        }

        if (in_array($role, ['siswa', 'student'], true)) {
            $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
            if (is_array($runtime_attempt)) {
                return $runtime_attempt;
            }
        }

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, question_order, option_order, score, max_score, started_at, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );

        if (
            is_array($attempt)
            && in_array($role, ['siswa', 'student'], true)
            && (int) ($attempt['student_id'] ?? 0) === $user_id
            && (string) ($attempt['status'] ?? '') === 'in_progress'
        ) {
            $duration_minutes = self::resolve_attempt_duration_minutes($attempt, $exam_duration_minutes);
            if ($duration_minutes > 0) {
                self::ensure_runtime_attempt_state($attempt, $duration_minutes);
            }
        }

        return $attempt;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
}
