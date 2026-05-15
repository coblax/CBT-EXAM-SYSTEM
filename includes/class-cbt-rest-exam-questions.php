<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Exam_Questions_Routes
{
    public static function get_exams(WP_REST_Request $request)
    {
        self::mark_priority_window('exams');

        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        if (!$user_id) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $payload = self::get_exams_payload($user_id, $role);

        if (!is_array($payload)) {
            $payload = [
                'items' => [],
                'current_user' => null,
            ];
        }

        $payload['items'] = self::append_global_token_meta_to_exam_items(
            isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : []
        );
        $payload = self::append_adaptive_load_payload($payload);

        return rest_ensure_response($payload);
    }

    public static function get_subjects(WP_REST_Request $request)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $rows = $wpdb->get_results(
            "SELECT id, name, code, description FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        return rest_ensure_response([
            'items' => $rows ?: [],
        ]);
    }

    public static function get_questions(WP_REST_Request $request)
    {
        global $wpdb;
        self::mark_priority_window('questions');

        $exam_id = (int) $request->get_param('exam_id');
        $attempt_id = (int) $request->get_param('attempt_id');
        $offset = max(0, (int) $request->get_param('offset'));
        $limit = max(0, (int) $request->get_param('limit'));
        $include_existing_raw = $request->get_param('include_existing');
        $include_existing = true;
        if ($include_existing_raw !== null && $include_existing_raw !== '') {
            $include_existing = ((int) $include_existing_raw !== 0);
        }
        $include_answer_manifest = ((int) $request->get_param('include_answer_manifest') === 1);
        $bootstrap_light = ((int) $request->get_param('bootstrap_light') === 1);
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        $is_student_request = self::is_student_role($role);

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam_id', 'exam_id is required', ['status' => 400]);
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, subject_id, duration_minutes, randomize_questions, randomize_options, status, starts_at, ends_at, target_kelas, target_agama, target_jenis_kelamin, restrict_to_subject_choice
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            ),
            ARRAY_A
        );
        if (!$exam) {
            return new WP_Error('not_found', 'Exam not found', ['status' => 404]);
        }

        $question_revision = CBT_Cache::get_exam_revision_meta($exam_id);

        $question_order_ids = [];
        $question_order_signature = '';
        $attempt = null;

        if ($attempt_id > 0) {
            $attempt = self::get_attempt_for_question_payload(
                $attempt_id,
                $user_id,
                $role,
                $attempt_table,
                (int) ($exam['duration_minutes'] ?? 0)
            );
            if (!$attempt || (int) $attempt['exam_id'] !== $exam_id) {
                return new WP_Error('not_found', 'Attempt not found', ['status' => 404]);
            }

            if ($is_student_request && (int) $attempt['student_id'] !== $user_id) {
                return new WP_Error('forbidden', 'You cannot access this attempt', ['status' => 403]);
            }
        }

        if ($is_student_request) {
            if ($attempt_id <= 0) {
                return new WP_Error(
                    'attempt_required',
                    'Attempt aktif wajib tersedia sebelum memuat soal ujian.',
                    [
                        'status' => 400,
                        'opening_reason' => 'attempt_required',
                        'suggestion' => 'Mulai ujian dari daftar exam agar token dan timer aktif.',
                        'return_to_exam_list_suggestion' => 'Mulai ujian dari daftar exam agar token dan timer aktif.',
                    ]
                );
            }

            if (!is_array($attempt) || (string) ($attempt['status'] ?? '') !== 'in_progress') {
                return new WP_Error(
                    'attempt_closed',
                    'Attempt sudah selesai atau tidak aktif.',
                    [
                        'status' => 400,
                        'opening_reason' => 'attempt_closed',
                        'suggestion' => 'Kembali ke daftar exam untuk memeriksa status attempt.',
                        'return_to_exam_list_suggestion' => 'Kembali ke daftar exam untuk memeriksa status attempt.',
                    ]
                );
            }

            $audience = self::evaluate_student_exam_audience($exam, $user_id, self::get_live_user_profile($user_id));
            if (empty($audience['allowed'])) {
                return new WP_Error(
                    'forbidden',
                    'Exam tidak tersedia untuk akun Anda.',
                    [
                        'status' => 403,
                        'opening_reason' => sanitize_key((string) ($audience['reason'] ?? 'forbidden')),
                        'suggestion' => 'Kembali ke daftar exam untuk memilih exam lain yang sesuai.',
                    ]
                );
            }

            $is_resuming_attempt = (
                is_array($attempt) &&
                (int) ($attempt['student_id'] ?? 0) === $user_id &&
                (string) ($attempt['status'] ?? '') === 'in_progress'
            );

            if (!$is_resuming_attempt) {
                $now = current_time('mysql');
                $within_schedule = (
                    (empty($exam['starts_at']) || (string) $exam['starts_at'] <= $now) &&
                    (empty($exam['ends_at']) || (string) $exam['ends_at'] >= $now)
                );
                if ((string) ($exam['status'] ?? 'draft') !== 'published' || !$within_schedule) {
                    return self::build_start_attempt_inactive_exam_error($exam, $now);
                }
            }
        }

        $window_mode = ($attempt_id > 0 && $limit > 0);
        if (
            $window_mode
            && is_array($attempt)
            && $is_student_request
            && (string) ($attempt['status'] ?? '') === 'in_progress'
        ) {
            $raw_v2_response = self::maybe_build_raw_v2_question_window_response(
                $request,
                $exam,
                $attempt,
                $exam_id,
                $attempt_id,
                $offset,
                $limit,
                $include_existing,
                $include_answer_manifest,
                $question_revision
            );
            if ($raw_v2_response instanceof WP_REST_Response) {
                return $raw_v2_response;
            }
        }

        if (
            $bootstrap_light &&
            $window_mode &&
            is_array($attempt) &&
            self::is_student_role($role) &&
            (string) ($attempt['status'] ?? '') === 'in_progress'
        ) {
            $bootstrap_response = self::build_bootstrap_light_question_window_response(
                $exam,
                $attempt,
                $exam_id,
                $attempt_id,
                $offset,
                $limit,
                $include_existing,
                $include_answer_manifest,
                $question_revision
            );
            if ($bootstrap_response !== null) {
                return self::prepare_questions_conditional_response(
                    $request,
                    $bootstrap_response,
                    [
                        'exam_id' => $exam_id,
                        'attempt_id' => $attempt_id,
                        'offset' => $offset,
                        'limit' => $limit,
                        'include_existing' => $include_existing ? 1 : 0,
                        'include_answer_manifest' => $include_answer_manifest ? 1 : 0,
                        'bootstrap_light' => 1,
                    ]
                );
            }
        }

        $use_student_delivery_snapshot = self::should_use_student_delivery_snapshot($role, $attempt);
        $questions = ($use_student_delivery_snapshot || $is_student_request)
            ? self::get_student_exam_question_delivery_payload($exam_id)
            : self::get_cached_exam_question_payload($exam_id);

        $question_manifest = [];
        if (is_array($attempt)) {
            $attempt_snapshot_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);
            if (!empty($attempt_snapshot_order_ids)) {
                $attempt_snapshot_order_json = wp_json_encode($attempt_snapshot_order_ids);
                if (is_string($attempt_snapshot_order_json)) {
                    $attempt['question_order'] = $attempt_snapshot_order_json;
                }
            }

            if ($use_student_delivery_snapshot) {
                $questions = self::sanitize_question_delivery_payload($questions);
            }

            $used_contract_snapshot = false;
            if ($use_student_delivery_snapshot) {
                $attempt_contract = self::get_cached_attempt_question_contract($attempt, $exam, $questions, $attempt_table);
                $contract_question_order_ids = array_values(array_filter(array_map('intval', (array) ($attempt_contract['question_order_ids'] ?? [])), static function (int $question_id): bool {
                    return $question_id > 0;
                }));
                if (!empty($contract_question_order_ids)) {
                    $questions = self::append_missing_questions_by_ids($questions, $exam_id, $contract_question_order_ids);
                    $questions = self::order_question_payload_by_ids($questions, $contract_question_order_ids);
                    $contract_option_order_map = self::normalize_attempt_option_order_map($attempt_contract['option_order_map'] ?? []);
                    if (!empty($contract_option_order_map)) {
                        $questions = self::apply_attempt_option_order_to_questions($questions, $contract_option_order_map);
                    }
                    $contract_question_number_map = self::normalize_attempt_question_number_map(
                        is_array($attempt_contract['question_number_map'] ?? null)
                            ? $attempt_contract['question_number_map']
                            : []
                    );
                    if (!empty($contract_question_number_map)) {
                        $questions = self::apply_question_numbers_to_payload($questions, $contract_question_number_map);
                    }
                    $question_order_ids = $contract_question_order_ids;
                    $question_order_signature = (string) ($attempt_contract['question_order_signature'] ?? '');
                    $question_manifest = is_array($attempt_contract['question_manifest'] ?? null)
                        ? array_values(array_filter($attempt_contract['question_manifest'], 'is_array'))
                        : [];
                    $attempt['question_order'] = (string) (wp_json_encode($contract_question_order_ids) ?: '[]');
                    if (!empty($contract_option_order_map)) {
                        $attempt['option_order'] = self::encode_attempt_option_order_map($contract_option_order_map);
                    }
                    if (empty($question_manifest)) {
                        $question_manifest = self::build_question_manifest($questions);
                        self::sync_attempt_runtime_snapshots(
                            $attempt,
                            $exam,
                            $question_order_ids,
                            $contract_question_number_map,
                            $contract_option_order_map,
                            $question_manifest
                        );
                    }
                    $used_contract_snapshot = true;
                }
            }

            if (!$used_contract_snapshot) {
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
                $questions = $resolved_attempt_payload['questions'];
                $question_order_ids = $resolved_attempt_payload['question_order_ids'];
                $question_order_signature = (string) ($resolved_attempt_payload['question_order_signature'] ?? '');
                $attempt = $resolved_attempt_payload['attempt'];
            }
        } else {
            $questions = self::shuffle_question_payload_if_needed($questions, (int) ($exam['randomize_questions'] ?? 0) === 1);
        }

        if (empty($question_order_ids)) {
            $question_order_ids = self::extract_question_ids_from_payload($questions);
        }

        $attempt_duration_minutes = self::resolve_attempt_duration_minutes(
            is_array($attempt) ? $attempt : null,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        if (empty($question_manifest)) {
            $question_manifest = self::build_question_manifest($questions);
        }
        $answered_question_ids = [];
        $existing_answers_map = [];
        $archived_review_items = [];
        if ($attempt_id > 0) {
            $answered_question_ids = self::get_attempt_answered_question_ids(
                $attempt_id,
                is_array($attempt) ? $attempt : null,
                $attempt_duration_minutes
            );
            if ($include_answer_manifest) {
                $existing_answers_map = self::build_attempt_existing_answers_map(
                    $questions,
                    $attempt_id,
                    is_array($attempt) ? $attempt : null,
                    $attempt_duration_minutes
                );
            }
            if ($include_answer_manifest && is_array($attempt)) {
                $archived_review_items = self::build_attempt_archived_review_items($attempt);
            }
        }

        if ($window_mode) {
            $total_questions = count($questions);
            if ($total_questions > 0 && $offset >= $total_questions) {
                $offset = max(0, (int) (floor(($total_questions - 1) / $limit) * $limit));
            }

            $window_questions = array_slice($questions, $offset, $limit);
            $window_question_ids = self::extract_question_ids_from_payload($window_questions);

            if ($include_existing && $attempt_id > 0) {
                $used_runtime_state = false;
                if (is_array($attempt)) {
                    $used_runtime_state = self::merge_runtime_answers_into_question_payload(
                        $window_questions,
                        $attempt,
                        $attempt_duration_minutes,
                        $window_question_ids
                    );
                }

                if (!$used_runtime_state) {
                    self::merge_existing_answers_into_question_payload($window_questions, $attempt_id, $window_question_ids);
                }
            }

            return self::prepare_questions_conditional_response($request, [
                'items' => $window_questions ?: [],
                'offset' => $offset,
                'limit' => $limit,
                'total_questions' => $total_questions,
                'has_next' => ($offset + count($window_questions)) < $total_questions,
                'question_order_ids' => $question_order_ids,
                'question_manifest' => $question_manifest,
                'answered_question_ids' => $answered_question_ids,
                'existing_answers_map' => $existing_answers_map,
                'archived_review_items' => $archived_review_items,
                'question_revision' => $question_revision,
                'question_order_signature' => $question_order_signature,
            ], [
                'exam_id' => $exam_id,
                'attempt_id' => $attempt_id,
                'offset' => $offset,
                'limit' => $limit,
                'include_existing' => $include_existing ? 1 : 0,
                'include_answer_manifest' => $include_answer_manifest ? 1 : 0,
                'bootstrap_light' => 0,
            ]);
        }

        if ($include_existing && $attempt_id > 0) {
            $used_runtime_state = false;
            if (is_array($attempt)) {
                $used_runtime_state = self::merge_runtime_answers_into_question_payload(
                    $questions,
                    $attempt,
                    $attempt_duration_minutes
                );
            }

            if (!$used_runtime_state) {
                self::merge_existing_answers_into_question_payload($questions, $attempt_id);
            }
        }

        $response = [
            'items' => $questions ?: [],
        ];

        if ($attempt_id > 0) {
            $response['offset'] = 0;
            $response['limit'] = 0;
            $response['total_questions'] = count($questions);
            $response['has_next'] = false;
            $response['question_order_ids'] = $question_order_ids;
            $response['question_manifest'] = $question_manifest;
            $response['answered_question_ids'] = $answered_question_ids;
            $response['existing_answers_map'] = $existing_answers_map;
            $response['archived_review_items'] = $archived_review_items;
        }

        $response['question_revision'] = $question_revision;
        $response['question_order_signature'] = $question_order_signature;

        return self::prepare_questions_conditional_response($request, $response, [
            'exam_id' => $exam_id,
            'attempt_id' => $attempt_id,
            'offset' => 0,
            'limit' => 0,
            'include_existing' => $include_existing ? 1 : 0,
            'include_answer_manifest' => $include_answer_manifest ? 1 : 0,
            'bootstrap_light' => 0,
        ]);
    }

    /**
     * @param array<string,mixed> $exam
     * @param array<string,mixed> $attempt
     * @param mixed $question_revision
     * @return WP_REST_Response|null
     */
    private static function maybe_build_raw_v2_question_window_response(
        WP_REST_Request $request,
        array $exam,
        array $attempt,
        int $exam_id,
        int $attempt_id,
        int $offset,
        int $limit,
        bool $include_existing,
        bool $include_answer_manifest,
        $question_revision
    ): ?WP_REST_Response {
        if (
            self::raw_questions_disabled()
            || !class_exists('CBT_Raw_JSON_REST_Response')
            || !class_exists('CBT_Exam_Question_Delivery_Cache')
            || !method_exists('CBT_Exam_Question_Delivery_Cache', 'read_current_exam_raw_item_blobs')
        ) {
            return null;
        }
        $if_none_match = trim((string) $request->get_header('if-none-match'));
        if ($if_none_match === '' && isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $if_none_match = trim((string) wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']));
        }
        if ($if_none_match !== '') {
            return null;
        }

        $limit = max(1, $limit);
        if ($exam_id <= 0 || $attempt_id <= 0 || (string) ($attempt['status'] ?? '') !== 'in_progress') {
            return null;
        }

        $contract = [];
        if (
            class_exists('CBT_Attempt_Question_Contract_Cache')
            && method_exists('CBT_Attempt_Question_Contract_Cache', 'read_cached_attempt_snapshot')
        ) {
            $contract = CBT_Attempt_Question_Contract_Cache::read_cached_attempt_snapshot($attempt_id);
        }
        if (empty($contract)) {
            return null;
        }

        $question_order_ids = array_values(array_filter(array_map('intval', (array) ($contract['question_order_ids'] ?? [])), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        if (empty($question_order_ids)) {
            return null;
        }

        $total_questions = count($question_order_ids);
        if ($total_questions > 0 && $offset >= $total_questions) {
            $offset = max(0, (int) (floor(($total_questions - 1) / $limit) * $limit));
        }

        $window_question_ids = array_slice($question_order_ids, max(0, $offset), $limit);
        if (empty($window_question_ids)) {
            return null;
        }

        $blob_result = CBT_Exam_Question_Delivery_Cache::read_current_exam_raw_item_blobs($exam_id, $window_question_ids);
        if (empty($blob_result['success']) || !is_array($blob_result['blobs_by_id'] ?? null)) {
            return null;
        }
        $blobs_by_id = (array) $blob_result['blobs_by_id'];

        $question_number_map = self::normalize_attempt_question_number_map(
            is_array($contract['question_number_map'] ?? null) ? (array) $contract['question_number_map'] : []
        );
        $option_order_map = self::normalize_attempt_option_order_map($contract['option_order_map'] ?? []);
        foreach ($window_question_ids as $question_id) {
            $blob = isset($blobs_by_id[$question_id]) && is_array($blobs_by_id[$question_id])
                ? $blobs_by_id[$question_id]
                : [];
            $question_type = (string) ($blob['meta']['question_type'] ?? '');
            if ($question_type === 'true_false_matrix' && !empty($option_order_map[$question_id])) {
                return null;
            }
        }

        $attempt_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $existing_answer_rows = [];
        if ($include_existing || $include_answer_manifest) {
            $existing_answer_rows = self::get_existing_answer_rows_for_question_ids(
                $attempt_id,
                $window_question_ids,
                $attempt,
                $attempt_duration_minutes
            );
        }

        $item_raws = [];
        $existing_answers_raw_map = [];
        foreach ($window_question_ids as $question_id) {
            $blob = isset($blobs_by_id[$question_id]) && is_array($blobs_by_id[$question_id])
                ? $blobs_by_id[$question_id]
                : [];
            if (empty($blob)) {
                return null;
            }

            $existing_answer_raw = '';
            if (($include_existing || $include_answer_manifest) && isset($existing_answer_rows[$question_id])) {
                $existing_answer_raw = self::build_raw_existing_answer_from_row($blob, (array) $existing_answer_rows[$question_id]);
            }

            $item_raw = self::build_raw_question_item_from_v2_blob(
                $blob,
                (int) ($question_number_map[$question_id] ?? 0),
                (array) ($option_order_map[$question_id] ?? []),
                $include_existing ? $existing_answer_raw : ''
            );
            if ($item_raw === '') {
                return null;
            }

            $item_raws[] = $item_raw;
            if ($include_answer_manifest && $existing_answer_raw !== '') {
                $existing_answers_raw_map[(string) $question_id] = $existing_answer_raw;
            }
        }

        $answered_question_ids = self::get_attempt_answered_question_ids(
            $attempt_id,
            $attempt,
            $attempt_duration_minutes,
            false
        );
        $question_manifest = is_array($contract['question_manifest'] ?? null)
            ? array_values(array_filter((array) $contract['question_manifest'], 'is_array'))
            : [];
        if (empty($question_manifest)) {
            $question_manifest = self::build_minimal_question_manifest_from_order($question_order_ids, $question_number_map);
        }
        $archived_review_items = $include_answer_manifest
            ? self::build_attempt_archived_review_items($attempt)
            : [];

        $items_raw = '[' . implode(',', $item_raws) . ']';
        $existing_answers_map_raw = self::raw_json_object_from_raw_members($existing_answers_raw_map);
        $payload_raw = self::raw_json_object_from_raw_members([
            'items' => $items_raw,
            'offset' => wp_json_encode(max(0, $offset)) ?: '0',
            'limit' => wp_json_encode($limit) ?: '0',
            'total_questions' => wp_json_encode($total_questions) ?: '0',
            'has_next' => (($offset + count($item_raws)) < $total_questions) ? 'true' : 'false',
            'question_order_ids' => wp_json_encode($question_order_ids) ?: '[]',
            'question_manifest' => wp_json_encode($question_manifest) ?: '[]',
            'answered_question_ids' => wp_json_encode($answered_question_ids) ?: '[]',
            'existing_answers_map' => $existing_answers_map_raw,
            'archived_review_items' => wp_json_encode($archived_review_items) ?: '[]',
            'question_revision' => wp_json_encode($question_revision) ?: '{}',
            'question_order_signature' => wp_json_encode((string) ($contract['question_order_signature'] ?? '')) ?: '""',
        ]);
        if ($payload_raw === '') {
            return null;
        }

        $etag = self::build_raw_questions_response_etag($payload_raw, [
            'exam_id' => $exam_id,
            'attempt_id' => $attempt_id,
            'offset' => $offset,
            'limit' => $limit,
            'include_existing' => $include_existing ? 1 : 0,
            'include_answer_manifest' => $include_answer_manifest ? 1 : 0,
            'question_revision_signature' => is_array($question_revision) && is_scalar($question_revision['signature'] ?? null)
                ? (string) $question_revision['signature']
                : '',
            'question_order_signature' => (string) ($contract['question_order_signature'] ?? ''),
        ]);
        if ($etag === '') {
            return null;
        }

        if (self::request_if_none_match_matches($request, $etag)) {
            $not_modified = new WP_REST_Response(null, 304);
            self::add_questions_cache_headers($not_modified, $etag);
            return $not_modified;
        }

        $response = new CBT_Raw_JSON_REST_Response($payload_raw, null, 200);
        self::add_questions_cache_headers($response, $etag);
        $response->header('X-CBT-Questions-Storage', 'v2-raw');
        return $response;
    }

    /**
     * @param array<string,mixed> $blob
     * @param array<int,string> $option_order_tokens
     */
    private static function build_raw_question_item_from_v2_blob(array $blob, int $question_number, array $option_order_tokens, string $existing_answer_raw): string
    {
        $base_raw = trim((string) ($blob['item_without_options_raw'] ?? ''));
        if ($base_raw === '' || $base_raw[0] !== '{' || substr($base_raw, -1) !== '}') {
            return '';
        }

        $members = [];
        if (!empty($blob['has_options'])) {
            $options_raw = self::build_raw_options_from_v2_blob($blob, $option_order_tokens);
            if ($options_raw === '') {
                return '';
            }
            $members['options'] = $options_raw;
        }
        if ($question_number > 0) {
            $members['question_number'] = wp_json_encode($question_number) ?: '0';
        }
        if ($existing_answer_raw !== '') {
            $members['existing_answer'] = $existing_answer_raw;
        }

        return self::append_raw_members_to_json_object($base_raw, $members);
    }

    /**
     * @param array<string,mixed> $blob
     * @param array<int,string> $option_order_tokens
     */
    private static function build_raw_options_from_v2_blob(array $blob, array $option_order_tokens): string
    {
        $default_options_raw = trim((string) ($blob['default_options_raw'] ?? ''));
        if (empty($option_order_tokens)) {
            return $default_options_raw !== '' ? $default_options_raw : '[]';
        }

        $option_raw_by_token = is_array($blob['option_raw_by_token'] ?? null)
            ? (array) $blob['option_raw_by_token']
            : [];
        $default_tokens = array_values(array_filter(array_map('strval', (array) ($blob['option_token_order'] ?? [])), static function (string $token): bool {
            return trim($token) !== '';
        }));
        if (empty($option_raw_by_token) || empty($default_tokens)) {
            return $default_options_raw !== '' ? $default_options_raw : '[]';
        }

        $allowed = array_fill_keys($default_tokens, true);
        $ordered_tokens = [];
        $seen = [];
        foreach ($option_order_tokens as $token) {
            $token = trim((string) $token);
            if ($token === '' || !isset($allowed[$token]) || isset($seen[$token])) {
                continue;
            }
            $ordered_tokens[] = $token;
            $seen[$token] = true;
        }
        foreach ($default_tokens as $token) {
            if (isset($seen[$token])) {
                continue;
            }
            $ordered_tokens[] = $token;
            $seen[$token] = true;
        }

        $option_raws = [];
        foreach ($ordered_tokens as $index => $token) {
            $option_raw = isset($option_raw_by_token[$token]) && is_string($option_raw_by_token[$token])
                ? (string) $option_raw_by_token[$token]
                : '';
            if ($option_raw === '') {
                continue;
            }

            $option = json_decode($option_raw, true);
            if (!is_array($option)) {
                return '';
            }
            $option['option_key'] = self::build_display_option_key($index);
            $encoded_option = wp_json_encode($option);
            if (!is_string($encoded_option) || $encoded_option === '') {
                return '';
            }
            $option_raws[] = $encoded_option;
        }

        return '[' . implode(',', $option_raws) . ']';
    }

    /**
     * @param array<string,mixed> $blob
     * @param array<string,mixed> $answer_row
     */
    private static function build_raw_existing_answer_from_row(array $blob, array $answer_row): string
    {
        if (!self::answer_row_has_value($answer_row)) {
            return '';
        }

        $selected_option_ids = self::decode_selected_option_ids($answer_row['selected_option_ids'] ?? null);
        $existing_text = (string) ($answer_row['answer_text'] ?? '');
        $question_type = (string) ($blob['meta']['question_type'] ?? '');
        $value = null;
        $has_value = false;

        switch ($question_type) {
            case 'multiple_choice':
            case 'true_false':
                if (!empty($selected_option_ids)) {
                    $value = (int) $selected_option_ids[0];
                    $has_value = true;
                }
                break;
            case 'multiple_answer':
                if (!empty($selected_option_ids)) {
                    $value = $selected_option_ids;
                    $has_value = true;
                }
                break;
            case 'ordering':
                $ordered_option_ids = self::decode_ordered_selected_option_ids($answer_row['selected_option_ids'] ?? null);
                if (!empty($ordered_option_ids)) {
                    $value = $ordered_option_ids;
                    $has_value = true;
                }
                break;
            case 'true_false_matrix':
                $existing_matrix = self::normalize_true_false_matrix_submission($existing_text);
                if (!empty($existing_matrix)) {
                    $value = $existing_matrix;
                    $has_value = true;
                }
                break;
            case 'matching':
            case 'cloze_dropdown':
            case 'categorization':
                $existing_map = self::normalize_dropdown_option_id_submission($existing_text);
                if (!empty($existing_map)) {
                    $value = $existing_map;
                    $has_value = true;
                }
                break;
            case 'table_completion':
                $decoded_table_answer = json_decode($existing_text, true);
                $table_answer = [];
                if (is_array($decoded_table_answer)) {
                    foreach ($decoded_table_answer as $key => $raw_value) {
                        $safe_key = strtoupper(trim((string) $key));
                        if ($safe_key === '' || !is_scalar($raw_value)) {
                            continue;
                        }
                        $safe_value = trim((string) $raw_value);
                        if ($safe_value === '') {
                            continue;
                        }
                        $table_answer[$safe_key] = is_numeric($safe_value) ? (int) $safe_value : $safe_value;
                    }
                }
                if (!empty($table_answer)) {
                    $value = $table_answer;
                    $has_value = true;
                }
                break;
            case 'short_answer':
                $submitted_values = self::extract_short_answer_submission_values($existing_text);
                $input_keys = is_array($blob['meta']['short_answer_input_keys'] ?? null)
                    ? array_values((array) $blob['meta']['short_answer_input_keys'])
                    : [];
                if (empty($input_keys)) {
                    $input_keys = ['A'];
                }
                $existing_short = [];
                foreach ($submitted_values as $index => $submitted_value) {
                    if (!isset($input_keys[$index])) {
                        continue;
                    }
                    $key = strtoupper(trim((string) $input_keys[$index]));
                    if ($key === '') {
                        continue;
                    }
                    $existing_short[$key] = (string) $submitted_value;
                }
                if (!empty($existing_short)) {
                    $value = $existing_short;
                    $has_value = true;
                }
                break;
            case 'essay':
            default:
                if ($existing_text !== '') {
                    $value = $existing_text;
                    $has_value = true;
                }
                break;
        }

        if (!$has_value) {
            return '';
        }

        $encoded = wp_json_encode($value);
        return is_string($encoded) ? $encoded : '';
    }

    /**
     * @param array<int,int> $question_ids
     * @param array<string,mixed> $attempt
     * @return array<int,array<string,mixed>>
     */
    private static function get_existing_answer_rows_for_question_ids(
        int $attempt_id,
        array $question_ids,
        array $attempt,
        int $duration_minutes
    ): array {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
        if ($attempt_id <= 0 || empty($question_ids)) {
            return [];
        }

        if (class_exists('CBT_Runtime') && CBT_Runtime::is_ready()) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
            $runtime_answers = CBT_Runtime::get_existing_answers_for_questions($attempt_id, $question_ids, $state_found);
            if ($state_found) {
                return self::normalize_answer_rows_by_question_id($runtime_answers);
            }
        }

        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, selected_option_ids, answer_text, answered_at, updated_at
                 FROM {$wpdb->prefix}cbt_answers
                 WHERE attempt_id = %d AND question_id IN ({$placeholders})",
                ...array_merge([$attempt_id], $question_ids)
            ),
            ARRAY_A
        );

        return self::normalize_answer_rows_by_question_id(is_array($rows) ? $rows : []);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_answer_rows_by_question_id(array $rows): array
    {
        $by_id = [];
        foreach ($rows as $row) {
            $answer_row = (array) $row;
            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $by_id[$question_id] = $answer_row;
        }

        return $by_id;
    }

    /**
     * @param array<string,string> $raw_members
     */
    private static function raw_json_object_from_raw_members(array $raw_members): string
    {
        $members = [];
        foreach ($raw_members as $key => $raw_value) {
            if (!is_string($raw_value) || $raw_value === '') {
                return '';
            }
            $encoded_key = wp_json_encode((string) $key);
            if (!is_string($encoded_key) || $encoded_key === '') {
                return '';
            }
            $members[] = $encoded_key . ':' . $raw_value;
        }

        return '{' . implode(',', $members) . '}';
    }

    /**
     * @param array<string,string> $raw_members
     */
    private static function append_raw_members_to_json_object(string $base_raw, array $raw_members): string
    {
        $base_raw = trim($base_raw);
        if ($base_raw === '' || $base_raw[0] !== '{' || substr($base_raw, -1) !== '}') {
            return '';
        }
        if (empty($raw_members)) {
            return $base_raw;
        }

        $members_raw = self::raw_json_object_from_raw_members($raw_members);
        if ($members_raw === '') {
            return '';
        }
        $members_inner = substr($members_raw, 1, -1);
        $base_inner = trim(substr($base_raw, 1, -1));
        if ($base_inner === '') {
            return '{' . $members_inner . '}';
        }

        return '{' . $base_inner . ',' . $members_inner . '}';
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function build_raw_questions_response_etag(string $payload_raw, array $context): string
    {
        $seed = $context;
        $seed['raw_payload_signature'] = hash('sha256', $payload_raw);
        $encoded = wp_json_encode($seed);
        if (!is_string($encoded) || $encoded === '') {
            return '';
        }

        return '"' . hash('sha256', $encoded) . '"';
    }

    /**
     * @param array<string,mixed>|WP_REST_Response $payload
     * @param array<string,mixed> $context
     * @return WP_REST_Response|array<string,mixed>
     */
    private static function prepare_questions_conditional_response(WP_REST_Request $request, $payload, array $context)
    {
        $response = rest_ensure_response($payload);
        $response_payload = $response instanceof WP_REST_Response
            ? $response->get_data()
            : $response;

        if (!is_array($response_payload)) {
            return $response;
        }

        $etag = self::build_questions_response_etag($response_payload, $context);
        if ($etag === '') {
            return $response;
        }

        if (self::request_if_none_match_matches($request, $etag)) {
            $not_modified = new WP_REST_Response(null, 304);
            self::add_questions_cache_headers($not_modified, $etag);
            return $not_modified;
        }

        if (!$response instanceof WP_REST_Response) {
            $response = new WP_REST_Response($response_payload, 200);
        }

        self::add_questions_cache_headers($response, $etag);
        if (
            !self::raw_questions_disabled()
            && class_exists('CBT_Raw_JSON_REST_Response')
            && $response->get_status() >= 200
            && $response->get_status() < 300
        ) {
            $raw_response = CBT_Raw_JSON_REST_Response::from_payload(
                $response_payload,
                $response->get_status(),
                $response->get_headers()
            );
            if ($raw_response instanceof WP_REST_Response) {
                return $raw_response;
            }
        }

        return $response;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     */
    private static function build_questions_response_etag(array $payload, array $context): string
    {
        $revision = is_array($payload['question_revision'] ?? null) ? $payload['question_revision'] : [];
        $revision_signature = is_scalar($revision['signature'] ?? null) ? (string) $revision['signature'] : '';
        if ($revision_signature === '') {
            return '';
        }

        $seed = [
            'revision_signature' => $revision_signature,
            'question_order_signature' => is_scalar($payload['question_order_signature'] ?? null)
                ? (string) $payload['question_order_signature']
                : '',
            'exam_id' => absint($context['exam_id'] ?? 0),
            'attempt_id' => absint($context['attempt_id'] ?? 0),
            'offset' => absint($payload['offset'] ?? $context['offset'] ?? 0),
            'limit' => absint($payload['limit'] ?? $context['limit'] ?? 0),
            'bootstrap_light' => !empty($context['bootstrap_light']) ? 1 : 0,
            'include_existing' => !empty($context['include_existing']) ? 1 : 0,
            'include_answer_manifest' => !empty($context['include_answer_manifest']) ? 1 : 0,
            'items_signature' => self::questions_payload_fragment_signature($payload['items'] ?? []),
            'answered_signature' => self::questions_payload_fragment_signature($payload['answered_question_ids'] ?? []),
            'existing_signature' => self::questions_payload_fragment_signature($payload['existing_answers_map'] ?? []),
            'archived_signature' => self::questions_payload_fragment_signature($payload['archived_review_items'] ?? []),
        ];

        $encoded = wp_json_encode($seed);
        if (!is_string($encoded) || $encoded === '') {
            return '';
        }

        return '"' . hash('sha256', $encoded) . '"';
    }

    /**
     * @param mixed $fragment
     */
    private static function questions_payload_fragment_signature($fragment): string
    {
        $normalized = self::normalize_questions_payload_fragment($fragment);
        $encoded = wp_json_encode($normalized);
        if (!is_string($encoded) || $encoded === '') {
            return '';
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $fragment
     * @return mixed
     */
    private static function normalize_questions_payload_fragment($fragment)
    {
        if (!is_array($fragment)) {
            return $fragment;
        }

        $normalized = [];
        foreach ($fragment as $key => $value) {
            $normalized[$key] = self::normalize_questions_payload_fragment($value);
        }

        if (!self::is_questions_payload_list($normalized)) {
            ksort($normalized, SORT_STRING);
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $value
     */
    private static function is_questions_payload_list(array $value): bool
    {
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    private static function request_if_none_match_matches(WP_REST_Request $request, string $etag): bool
    {
        $header = trim((string) $request->get_header('if-none-match'));
        if ($header === '' && isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $header = trim((string) wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']));
        }
        if ($header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '*') {
                return true;
            }
            if (stripos($candidate, 'W/') === 0) {
                $candidate = trim(substr($candidate, 2));
            }
            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    private static function add_questions_cache_headers(WP_REST_Response $response, string $etag): void
    {
        $response->header('ETag', $etag);
        $response->header('Cache-Control', 'private, no-cache, must-revalidate');
        $response->header('Vary', 'Authorization, Cookie');
    }

    private static function raw_questions_disabled(): bool
    {
        return defined('CBT_RAW_QUESTIONS_DISABLED') && CBT_RAW_QUESTIONS_DISABLED;
    }
}
