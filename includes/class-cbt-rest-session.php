<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Session_Routes
{
    public static function get_session(WP_REST_Request $request)
    {
        global $wpdb;

        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        $attempt_id = (int) $request->get_param('attempt_id');
        $bootstrap_light = ((int) $request->get_param('bootstrap_light') === 1);

        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $question_revision = null;
        $question_count = 0;
        $question_order_signature = '';
        $attempt_timer = null;
        $show_student_result = null;
        $enable_calculator = null;
        if ($attempt_id > 0) {
            $attempt = self::get_attempt_for_question_revision($attempt_id, $user_id, $role);
            if (is_wp_error($attempt)) {
                return $attempt;
            }

            self::maybe_update_attempt_presence_from_session($attempt, $request);

            $exam_id = (int) ($attempt['exam_id'] ?? 0);
            if ($exam_id > 0) {
                $question_revision = CBT_Cache::get_exam_revision_meta($exam_id);
                $attempt_status = (string) ($attempt['status'] ?? '');
                $attempt_timer = null;
                $attempt_question_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);
                if ($attempt_status === 'in_progress') {
                    $attempt_table = $wpdb->prefix . 'cbt_attempts';
                    $exam_table = $wpdb->prefix . 'cbt_exams';
                    $exam = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT id, duration_minutes, randomize_questions, randomize_options, show_student_result, enable_calculator, status, starts_at, ends_at, target_kelas
                             FROM {$exam_table}
                             WHERE id = %d",
                            $exam_id
                        ),
                        ARRAY_A
                    );

                    if (is_array($exam)) {
                        $attempt_session_snapshot = self::get_cached_attempt_session_snapshot($attempt, $exam, $attempt_table, $bootstrap_light);
                        if (is_wp_error($attempt_session_snapshot)) {
                            return $attempt_session_snapshot;
                        }
                        $session_duration_minutes = max(
                            0,
                            (int) ($attempt_session_snapshot['duration_minutes'] ?? $attempt['exam_duration_minutes'] ?? $exam['duration_minutes'] ?? 0)
                        );
                        $attempt_timer = self::build_attempt_timer_payload($attempt, $session_duration_minutes);
                        $show_student_result = self::normalize_show_student_result(
                            $attempt_session_snapshot['show_student_result'] ?? ($exam['show_student_result'] ?? 1)
                        );
                        $enable_calculator = self::normalize_enable_calculator(
                            $attempt_session_snapshot['enable_calculator'] ?? ($exam['enable_calculator'] ?? 1)
                        );
                        $question_count = max(
                            0,
                            (int) ($attempt_session_snapshot['question_count'] ?? 0)
                        );
                        $question_order_signature = (string) ($attempt_session_snapshot['question_order_signature'] ?? '');
                    } elseif (!empty($attempt_question_order_ids)) {
                        $attempt_timer = self::build_attempt_timer_payload(
                            $attempt,
                            (int) ($attempt['exam_duration_minutes'] ?? 0)
                        );
                        $question_count = count($attempt_question_order_ids);
                    } else {
                        $attempt_timer = self::build_attempt_timer_payload(
                            $attempt,
                            (int) ($attempt['exam_duration_minutes'] ?? 0)
                        );
                        $question_table = $wpdb->prefix . 'cbt_questions';
                        $question_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*)
                                 FROM {$question_table}
                                 WHERE exam_id = %d
                                   AND COALESCE(is_active, 1) = 1",
                                $exam_id
                            )
                        );
                    }
                } elseif (!empty($attempt_question_order_ids)) {
                    $question_count = count($attempt_question_order_ids);
                } else {
                    $question_table = $wpdb->prefix . 'cbt_questions';
                    $question_count = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*)
                             FROM {$question_table}
                             WHERE exam_id = %d
                               AND COALESCE(is_active, 1) = 1",
                            $exam_id
                        )
                    );
                }
            }
        }

        return rest_ensure_response(self::append_adaptive_load_payload([
            'ok' => true,
            'user_id' => $user_id,
            'role' => $role,
            'question_revision' => $question_revision,
            'question_count' => $question_count,
            'question_order_signature' => $question_order_signature,
            'attempt_timer' => $attempt_timer,
            'show_student_result' => $show_student_result,
            'enable_calculator' => $enable_calculator,
        ]));
    }

    public static function get_result(WP_REST_Request $request)
    {
        global $wpdb;

        $attempt_id = (int) $request->get_param('attempt_id');
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        if ($attempt_id > 0) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, exam_id, student_id, status, question_order, option_order, score, max_score
                     FROM {$attempt_table}
                     WHERE id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$attempt_table} WHERE student_id = %d ORDER BY id DESC LIMIT 1",
                    $user_id
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            return new WP_Error('not_found', 'Result not found', ['status' => 404]);
        }

        if (($role === 'siswa' || $role === 'student') && (int) $attempt['student_id'] !== $user_id) {
            return new WP_Error('forbidden', 'You cannot view this result', ['status' => 403]);
        }

        $result_payload = null;
        if ((string) ($attempt['status'] ?? '') === 'completed') {
            $result_payload = CBT_Cache::remember(
                'rest:result:attempt:' . (int) ($attempt['id'] ?? 0),
                300,
                [CBT_Cache::namespace_attempt((int) ($attempt['id'] ?? 0)), CBT_Cache::namespace_exam((int) ($attempt['exam_id'] ?? 0))],
                static function () use ($attempt): array {
                    return self::build_result_payload($attempt);
                }
            );
        } else {
            $result_payload = self::build_result_payload($attempt);
        }

        if (
            is_array($result_payload)
            && ($role === 'siswa' || $role === 'student')
            && self::normalize_show_student_result($result_payload['show_student_result'] ?? 1) !== 1
        ) {
            $result_payload = self::build_restricted_student_result_payload($result_payload);
        }

        return rest_ensure_response(is_array($result_payload) ? $result_payload : []);
    }

    public static function get_ui_state(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $attempt_id = (int) $request->get_param('attempt_id');
        if ($attempt_id > 0) {
            $attempt = self::get_attempt_for_ui_state($attempt_id, $user_id);
            if (is_wp_error($attempt)) {
                return $attempt;
            }
        }

        return rest_ensure_response(CBT_UI_State::get_state($user_id, $attempt_id));
    }

    public static function save_ui_state(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $preferences_input = isset($payload['preferences']) && is_array($payload['preferences'])
            ? $payload['preferences']
            : [];
        $attempt_state_input = isset($payload['attempt_state']) && is_array($payload['attempt_state'])
            ? $payload['attempt_state']
            : [];

        if (empty($preferences_input) && empty($attempt_state_input)) {
            return new WP_Error('invalid_payload', 'Payload UI state tidak valid.', ['status' => 400]);
        }

        $response = [
            'preferences' => CBT_UI_State::get_preferences($user_id),
            'attempt_state' => null,
        ];

        if (!empty($preferences_input)) {
            $response['preferences'] = CBT_UI_State::save_preferences($user_id, $preferences_input);
        }

        if (!empty($attempt_state_input)) {
            $attempt_id = (int) ($attempt_state_input['attempt_id'] ?? 0);
            if ($attempt_id <= 0) {
                return new WP_Error('invalid_attempt_id', 'attempt_id wajib diisi untuk attempt_state.', ['status' => 400]);
            }

            $attempt = self::get_attempt_for_ui_state($attempt_id, $user_id);
            if (is_wp_error($attempt)) {
                return $attempt;
            }

            $response['attempt_state'] = CBT_UI_State::save_attempt_state($user_id, $attempt_id, $attempt_state_input);
        }

        return rest_ensure_response($response);
    }

    public static function delete_ui_state(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $attempt_id = (int) $request->get_param('attempt_id');
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_attempt_id', 'attempt_id wajib diisi.', ['status' => 400]);
        }

        $attempt = self::get_attempt_for_ui_state($attempt_id, $user_id);
        if (is_wp_error($attempt)) {
            return $attempt;
        }

        CBT_UI_State::clear_attempt_state($user_id, $attempt_id);

        return rest_ensure_response([
            'deleted' => 1,
            'attempt_id' => $attempt_id,
        ]);
    }
}
