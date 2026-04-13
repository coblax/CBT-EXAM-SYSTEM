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

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam_id', 'exam_id is required', ['status' => 400]);
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, duration_minutes, randomize_questions, randomize_options, status, starts_at, ends_at, target_kelas
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

            if (($role === 'siswa' || $role === 'student') && (int) $attempt['student_id'] !== $user_id) {
                return new WP_Error('forbidden', 'You cannot access this attempt', ['status' => 403]);
            }
        }

        if ($role === 'siswa' || $role === 'student') {
            $student_kelas = self::get_live_user_kelas($user_id);
            if (!self::exam_allows_student_class($exam, $student_kelas)) {
                return new WP_Error(
                    'forbidden',
                    'Exam tidak tersedia untuk kelas akun Anda.',
                    [
                        'status' => 403,
                        'opening_reason' => 'forbidden',
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
                return $bootstrap_response;
            }
        }

        $use_student_delivery_snapshot = self::should_use_student_delivery_snapshot($role, $attempt);
        $questions = $use_student_delivery_snapshot
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

            return rest_ensure_response([
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

        return rest_ensure_response($response);
    }
}
