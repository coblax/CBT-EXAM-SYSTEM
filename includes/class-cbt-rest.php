<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once __DIR__ . '/class-cbt-student-profile-cache.php';
}

if (!class_exists('CBT_Exam_Availability_Cache')) {
    require_once __DIR__ . '/class-cbt-exam-availability-cache.php';
}

if (!class_exists('CBT_Exam_Question_Delivery_Cache')) {
    require_once __DIR__ . '/class-cbt-exam-question-delivery-cache.php';
}

if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-exam-start-attempt-snapshot-cache.php';
}

if (!class_exists('CBT_Attempt_Question_Contract_Cache')) {
    require_once __DIR__ . '/class-cbt-attempt-question-contract-cache.php';
}

if (!class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-attempt-session-snapshot-cache.php';
}

if (!class_exists('CBT_Question_Submission_Context_Cache')) {
    require_once __DIR__ . '/class-cbt-question-submission-context-cache.php';
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

class CBT_REST
{
    private const PRIORITY_WINDOW_TRANSIENT_KEY = 'cbt_exam_priority_window_until';
    private const AVAILABILITY_BASE_CATALOG_TTL = 900;

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('cbt/v1', '/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cbt/v1', '/logout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'logout'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/session', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_session'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
            'args' => [
                'attempt_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'presence_connection_status' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'presence_visibility_state' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'presence_has_focus' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'presence_pending_sync_count' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'presence_heartbeat_lost_active' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/exams', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_exams'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/subjects', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_subjects'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/questions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_questions'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
            'args' => [
                'exam_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'attempt_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'include_existing' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'include_answer_manifest' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'offset' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/start_attempt', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'start_attempt'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
            'args' => [
                'exam_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'exam_token' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'resume_only' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/submit_answer', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'submit_answer'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/submit_answers_batch', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'submit_answers_batch'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/finish_exam', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'finish_exam'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/security_event', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'security_event'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/native_security_event', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'native_security_event'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/ui_state', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_ui_state'],
                'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
                'args' => [
                    'attempt_id' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save_ui_state'],
                'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_ui_state'],
                'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
                'args' => [
                    'attempt_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/result', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_result'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
            'args' => [
                'attempt_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public static function login(WP_REST_Request $request)
    {
        $identifier = (string) $request->get_param('identifier');
        if ($identifier === '') {
            $identifier = (string) $request->get_param('email');
        }
        if ($identifier === '') {
            $identifier = (string) $request->get_param('username');
        }
        if ($identifier === '') {
            $identifier = (string) $request->get_param('nisn');
        }
        $password = (string) $request->get_param('password');

        if (!$identifier || !$password) {
            return new WP_Error('invalid_payload', 'Identifier and password are required', ['status' => 400]);
        }

        $result = CBT_Auth::login($identifier, $password);
        if (is_wp_error($result)) {
            return $result;
        }

        self::mark_priority_window('login');

        return rest_ensure_response($result);
    }

    public static function logout(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        CBT_Auth::logout_current_session($request);
        CBT_Cache::invalidate_user($user_id);

        return rest_ensure_response([
            'ok' => true,
        ]);
    }

    public static function get_session(WP_REST_Request $request)
    {
        global $wpdb;

        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        $attempt_id = (int) $request->get_param('attempt_id');

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
                        $attempt_session_snapshot = self::get_cached_attempt_session_snapshot($attempt, $exam, $attempt_table);
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

        return rest_ensure_response([
            'ok' => true,
            'user_id' => $user_id,
            'role' => $role,
            'question_revision' => $question_revision,
            'question_count' => $question_count,
            'question_order_signature' => $question_order_signature,
            'attempt_timer' => $attempt_timer,
            'show_student_result' => $show_student_result,
            'enable_calculator' => $enable_calculator,
        ]);
    }

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

        return rest_ensure_response($payload);
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_exams_payload(int $user_id, string $role): array
    {
        if (self::is_student_role($role) && CBT_Exam_Availability_Cache::is_available()) {
            return CBT_Exam_Availability_Cache::get_student_snapshot(
                $user_id,
                static function () use ($user_id, $role): array {
                    return self::build_exams_payload($user_id, $role);
                }
            );
        }

        return CBT_Cache::remember(
            'rest:exams:user:' . $user_id . ':role:' . strtolower((string) $role),
            15,
            [CBT_Cache::namespace_catalog(), CBT_Cache::namespace_user($user_id)],
            static function () use ($user_id, $role): array {
                return self::build_exams_payload($user_id, $role);
            }
        );
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    public static function build_student_exam_availability_snapshot_payload(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [
                'items' => [],
                'current_user' => null,
            ];
        }

        $batch_payloads = self::build_batch_student_exam_availability_snapshot_payloads([$user_id]);
        return isset($batch_payloads[$user_id]) && is_array($batch_payloads[$user_id])
            ? $batch_payloads[$user_id]
            : [
                'items' => [],
                'current_user' => null,
            ];
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}>
     */
    public static function build_batch_student_exam_availability_snapshot_payloads(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_unique(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        self::prime_student_availability_user_caches($user_ids);

        $student_now = current_time('mysql');
        $server_timezone = wp_timezone_string();
        $exam_rows = self::fetch_published_exam_rows_for_availability();
        $latest_attempts_by_user = self::fetch_latest_attempts_by_user_and_exam($user_ids);
        $payloads = [];

        foreach ($user_ids as $user_id) {
            $profile_snapshot = self::get_live_user_profile($user_id);
            $student_kelas = self::normalize_kelas_code((string) ($profile_snapshot['kode_kelas'] ?? ''));
            $latest_attempt_by_exam = isset($latest_attempts_by_user[$user_id]) && is_array($latest_attempts_by_user[$user_id])
                ? $latest_attempts_by_user[$user_id]
                : [];

            $payloads[$user_id] = [
                'items' => self::build_student_exam_availability_items(
                    $exam_rows,
                    $student_kelas,
                    $student_now,
                    $server_timezone,
                    $latest_attempt_by_exam
                ),
                'current_user' => self::build_student_exam_current_user_payload($user_id, 'student', $profile_snapshot),
            ];
        }

        return $payloads;
    }

    private static function is_student_role(string $role): bool
    {
        return in_array(strtolower(trim($role)), ['siswa', 'student'], true);
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
                return new WP_Error('forbidden', 'Exam is not available for your class', ['status' => 403]);
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
                    return new WP_Error('forbidden', 'Exam is not active', ['status' => 403]);
                }
            }
        }

        $use_student_delivery_snapshot = self::should_use_student_delivery_snapshot($role, $attempt);
        $questions = $use_student_delivery_snapshot
            ? self::get_student_exam_question_delivery_payload($exam_id)
            : self::get_cached_exam_question_payload($exam_id);
        $window_mode = ($attempt_id > 0 && $limit > 0);

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

    public static function start_attempt(WP_REST_Request $request)
    {
        global $wpdb;
        self::mark_priority_window('start_attempt');

        $exam_id = (int) $request->get_param('exam_id');
        $exam_token_input = (string) $request->get_param('exam_token');
        $resume_only = ((int) $request->get_param('resume_only') === 1);
        if ($exam_token_input === '') {
            $exam_token_input = (string) $request->get_param('token');
        }
        $exam_token_input = CBT_Auth::normalize_exam_token_input($exam_token_input);
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can start an attempt', ['status' => 403]);
        }

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam_id', 'exam_id is required', ['status' => 400]);
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, created_by, status, starts_at, ends_at, duration_minutes, randomize_questions, randomize_options, show_student_result, enable_calculator, target_kelas
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            ),
            ARRAY_A
        );
        if (!$exam) {
            return new WP_Error('not_found', 'Exam not found', ['status' => 404]);
        }

        $student_kelas = self::get_live_user_kelas($user_id);
        if (!self::exam_allows_student_class($exam, $student_kelas)) {
            return new WP_Error('forbidden', 'Exam is not available for your class', ['status' => 403]);
        }

        $expected_token = null;
        $resolve_expected_token = static function () use (&$expected_token): string {
            if (is_string($expected_token)) {
                return $expected_token;
            }

            $global_token_meta = CBT_Auth::get_global_exam_token(true);
            $expected_token = CBT_Auth::normalize_exam_token_input((string) ($global_token_meta['token'] ?? ''));
            return $expected_token;
        };
        $validate_token_submission = static function (string $submitted_token) use ($resolve_expected_token) {
            $expected_token = $resolve_expected_token();
            if (
                $expected_token !== '' &&
                $submitted_token === '' &&
                CBT_Auth::is_frontend_auto_exam_token_enabled()
            ) {
                $submitted_token = $expected_token;
            }

            return self::validate_exam_token_or_error($expected_token, $submitted_token);
        };

        $indexed_attempt = self::get_active_attempt_from_index($user_id, $exam_id, $attempt_table);
        if (is_array($indexed_attempt)) {
            return self::build_resumed_attempt_response(
                $indexed_attempt,
                $exam,
                $exam_id,
                $user_id,
                $attempt_table,
                $resume_only,
                $exam_token_input,
                $validate_token_submission
            );
        }

        $lock_key = 'start_attempt:user:' . $user_id . ':exam:' . $exam_id;
        if (!CBT_Cache::acquire_lock($lock_key, 15, [
            'type' => 'start_attempt',
            'user_id' => $user_id,
            'exam_id' => $exam_id,
        ])) {
            $indexed_attempt = self::get_active_attempt_from_index($user_id, $exam_id, $attempt_table);
            if (is_array($indexed_attempt)) {
                return self::build_resumed_attempt_response(
                    $indexed_attempt,
                    $exam,
                    $exam_id,
                    $user_id,
                    $attempt_table,
                    $resume_only,
                    $exam_token_input,
                    $validate_token_submission
                );
            }

            return new WP_Error(
                'attempt_lock_active',
                'Permintaan mulai ujian sedang diproses. Coba lagi beberapa detik.',
                ['status' => 429]
            );
        }

        try {
            $indexed_attempt = self::get_active_attempt_from_index($user_id, $exam_id, $attempt_table);
            if (is_array($indexed_attempt)) {
                return self::build_resumed_attempt_response(
                    $indexed_attempt,
                    $exam,
                    $exam_id,
                    $user_id,
                    $attempt_table,
                    $resume_only,
                    $exam_token_input,
                    $validate_token_submission
                );
            }

            $latest_attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                     FROM {$attempt_table}
                     WHERE exam_id = %d AND student_id = %d AND status IN ('in_progress', 'completed')
                     ORDER BY FIELD(status, 'in_progress', 'completed'), id DESC
                     LIMIT 1",
                    $exam_id,
                    $user_id
                ),
                ARRAY_A
            );

            if ($latest_attempt && (string) ($latest_attempt['status'] ?? '') === 'in_progress') {
                $latest_attempt['exam_id'] = $exam_id;
                $latest_attempt['student_id'] = $user_id;

                return self::build_resumed_attempt_response(
                    $latest_attempt,
                    $exam,
                    $exam_id,
                    $user_id,
                    $attempt_table,
                    $resume_only,
                    $exam_token_input,
                    $validate_token_submission
                );
            }

            if ($latest_attempt && (string) ($latest_attempt['status'] ?? '') === 'completed') {
                return new WP_Error(
                    'attempt_already_completed',
                    'Anda sudah menyelesaikan ujian ini. Hubungi pengawas/admin untuk reset jika perlu mengulang.',
                    [
                        'status' => 403,
                        'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                        'finished_at' => (string) ($latest_attempt['finished_at'] ?? ''),
                    ]
                );
            }

            if ($resume_only) {
                return new WP_Error(
                    'attempt_not_found',
                    'Tidak ada attempt ujian aktif untuk dilanjutkan.',
                    ['status' => 404]
                );
            }

            $now = current_time('mysql');
            $within_schedule = (
                (empty($exam['starts_at']) || (string) $exam['starts_at'] <= $now) &&
                (empty($exam['ends_at']) || (string) $exam['ends_at'] >= $now)
            );
            if ((string) ($exam['status'] ?? 'draft') !== 'published' || !$within_schedule) {
                return new WP_Error('forbidden', 'Exam is not active', ['status' => 403]);
            }

            $token_check = $validate_token_submission($exam_token_input);
            if (is_wp_error($token_check)) {
                return $token_check;
            }

            $question_ids = [];
            $question_number_map = [];
            $option_order = '{}';
            $used_start_snapshot = false;

            try {
                $start_snapshot = self::get_cached_exam_start_attempt_snapshot($exam_id);
                $snapshot_question_ids = array_values(array_filter(array_map('intval', (array) ($start_snapshot['question_ids'] ?? [])), static function (int $question_id): bool {
                    return $question_id > 0;
                }));
                if (!empty($snapshot_question_ids)) {
                    $question_ids = $snapshot_question_ids;
                    if ((int) ($exam['randomize_questions'] ?? 0) === 1) {
                        shuffle($question_ids);
                    }
                    $question_number_map = self::build_attempt_question_number_map($question_ids, $question_ids);
                    $option_order = self::encode_attempt_option_order_map(
                        self::build_attempt_option_order_map_from_snapshot_tokens(
                            isset($start_snapshot['option_randomization_tokens_by_question']) && is_array($start_snapshot['option_randomization_tokens_by_question'])
                                ? $start_snapshot['option_randomization_tokens_by_question']
                                : [],
                            (int) ($exam['randomize_options'] ?? 0) === 1
                        )
                    );
                    $used_start_snapshot = true;
                }
            } catch (Throwable $throwable) {
                $used_start_snapshot = false;
            }

            if (!$used_start_snapshot) {
                $questions = self::get_cached_exam_question_payload($exam_id);
                $question_ids = self::extract_question_ids_from_payload($questions);
                $option_order = self::encode_attempt_option_order_map(
                    self::build_attempt_option_order_map(
                        $questions,
                        (int) ($exam['randomize_options'] ?? 0) === 1
                    )
                );
                if ((int) $exam['randomize_questions'] === 1 && !empty($question_ids)) {
                    shuffle($question_ids);
                }
                $question_number_map = self::build_attempt_question_number_map($question_ids, $question_ids);
            }

            $question_order = wp_json_encode($question_ids);
            if (!is_string($question_order)) {
                $question_order = '[]';
            }

            $inserted = $wpdb->insert(
                $attempt_table,
                [
                    'exam_id' => $exam_id,
                    'student_id' => $user_id,
                    'status' => 'in_progress',
                    'question_order' => $question_order,
                    'option_order' => $option_order,
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                return new WP_Error('db_insert_failed', 'Failed to start attempt', ['status' => 500]);
            }

            $created_attempt_id = (int) $wpdb->insert_id;
            $created_attempt = [
                'id' => $created_attempt_id,
                'exam_id' => $exam_id,
                'student_id' => $user_id,
                'status' => 'in_progress',
                'started_at' => $now,
                'question_order' => $question_order,
                'option_order' => $option_order,
                'extra_time_minutes' => 0,
            ];
            CBT_Cache::invalidate_user($user_id);
            CBT_Cache::invalidate_attempt($created_attempt_id);
            self::ensure_runtime_attempt_state($created_attempt, (int) ($exam['duration_minutes'] ?? 0));
            self::sync_active_attempt_index($created_attempt);
            self::sync_live_attempt_roster($created_attempt, [
                'teacher_id' => (int) ($exam['created_by'] ?? 0),
                'exam_title' => (string) ($exam['title'] ?? ''),
            ]);
            $attempt_timer = self::build_attempt_timer_payload([
                'id' => $created_attempt_id,
                'status' => 'in_progress',
                'started_at' => $now,
                'extra_time_minutes' => 0,
            ], (int) ($exam['duration_minutes'] ?? 0));
            $question_order_signature = self::build_attempt_question_order_signature(
                $question_ids,
                $question_number_map
            );
            self::sync_attempt_runtime_snapshots(
                $created_attempt,
                $exam,
                $question_ids,
                $question_number_map,
                self::normalize_attempt_option_order_map($option_order),
                []
            );

            return rest_ensure_response([
                'attempt_id' => $created_attempt_id,
                'status' => 'started',
                'duration_minutes' => (int) $exam['duration_minutes'],
                'extra_time_minutes' => 0,
                'started_at' => $now,
                'remaining_seconds' => (int) ($attempt_timer['remaining_seconds'] ?? max(0, ((int) $exam['duration_minutes']) * MINUTE_IN_SECONDS)),
                'server_now' => (string) ($attempt_timer['server_now'] ?? current_time('mysql')),
                'question_revision' => CBT_Cache::get_exam_revision_meta($exam_id),
                'question_order_signature' => $question_order_signature,
            ]);
        } finally {
            CBT_Cache::release_lock($lock_key);
        }
    }

    private static function validate_exam_token_or_error(string $expected_token, string $submitted_token)
    {
        $expected_token = CBT_Auth::normalize_exam_token_input($expected_token);
        $submitted_token = CBT_Auth::normalize_exam_token_input($submitted_token);

        if ($expected_token === '') {
            return true;
        }

        if ($submitted_token === '') {
            return new WP_Error('token_required', 'Token ujian wajib diisi', ['status' => 400]);
        }

        if (!hash_equals($expected_token, $submitted_token)) {
            return new WP_Error('token_invalid', 'Token ujian tidak valid', ['status' => 403]);
        }

        return true;
    }

    /**
     * @param callable(string):mixed $validate_token_submission
     * @return array<string,mixed>|WP_Error
     */
    private static function build_resumed_attempt_response(
        array $attempt,
        array $exam,
        int $exam_id,
        int $user_id,
        string $attempt_table,
        bool $resume_only,
        string $exam_token_input,
        callable $validate_token_submission
    ) {
        if (!$resume_only) {
            $token_check = $validate_token_submission($exam_token_input);
            if (is_wp_error($token_check)) {
                return $token_check;
            }
        }

        $attempt_id = (int) ($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        if ($attempt_id <= 0) {
            return new WP_Error('attempt_not_found', 'Tidak ada attempt ujian aktif untuk dilanjutkan.', ['status' => 404]);
        }

        $resolved_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $runtime_attempt = [
            'id' => $attempt_id,
            'exam_id' => $exam_id,
            'student_id' => $user_id,
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'question_order' => (string) ($attempt['question_order'] ?? ''),
            'option_order' => (string) ($attempt['option_order'] ?? ''),
            'extra_time_minutes' => (int) ($attempt['extra_time_minutes'] ?? 0),
        ];

        self::ensure_runtime_attempt_state($runtime_attempt, $resolved_duration_minutes);
        self::sync_active_attempt_index($runtime_attempt);
        self::sync_live_attempt_roster($runtime_attempt, [
            'teacher_id' => (int) ($exam['created_by'] ?? 0),
            'exam_title' => (string) ($exam['title'] ?? ''),
        ]);
        $attempt_contract = self::build_attempt_runtime_snapshot_contract(
            $runtime_attempt,
            $exam,
            [],
            $attempt_table
        );
        self::sync_attempt_runtime_snapshots(
            $runtime_attempt,
            $exam,
            (array) ($attempt_contract['question_order_ids'] ?? []),
            (array) ($attempt_contract['question_number_map'] ?? []),
            (array) ($attempt_contract['option_order_map'] ?? []),
            (array) ($attempt_contract['question_manifest'] ?? [])
        );

        $attempt_timer = self::build_attempt_timer_payload([
            'id' => $attempt_id,
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'extra_time_minutes' => (int) ($attempt['extra_time_minutes'] ?? 0),
        ], (int) ($exam['duration_minutes'] ?? 0));
        $attempt_sync_meta = self::build_attempt_question_sync_meta($runtime_attempt, $exam, $attempt_table);

        return rest_ensure_response([
            'attempt_id' => $attempt_id,
            'status' => 'resumed',
            'duration_minutes' => $resolved_duration_minutes,
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'remaining_seconds' => (int) ($attempt_timer['remaining_seconds'] ?? max(0, $resolved_duration_minutes * MINUTE_IN_SECONDS)),
            'server_now' => (string) ($attempt_timer['server_now'] ?? current_time('mysql')),
            'question_revision' => CBT_Cache::get_exam_revision_meta($exam_id),
            'question_order_signature' => (string) ($attempt_sync_meta['question_order_signature'] ?? ''),
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_active_attempt_from_index(int $user_id, int $exam_id, string $attempt_table): ?array
    {
        global $wpdb;

        if (
            $user_id <= 0
            || $exam_id <= 0
            || !class_exists('CBT_Active_Attempt_Index')
        ) {
            return null;
        }

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id($user_id, $exam_id);
        if ($attempt_id <= 0) {
            return null;
        }

        $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
        if (
            is_array($runtime_attempt)
            && (int) ($runtime_attempt['exam_id'] ?? 0) === $exam_id
            && (string) ($runtime_attempt['status'] ?? '') === 'in_progress'
        ) {
            CBT_Active_Attempt_Index::set_active_attempt($runtime_attempt);
            return $runtime_attempt;
        }

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );

        if (
            !is_array($attempt)
            || (int) ($attempt['student_id'] ?? 0) !== $user_id
            || (int) ($attempt['exam_id'] ?? 0) !== $exam_id
            || (string) ($attempt['status'] ?? '') !== 'in_progress'
        ) {
            CBT_Active_Attempt_Index::clear_active_attempt($user_id, $exam_id, $attempt_id);
            return null;
        }

        CBT_Active_Attempt_Index::set_active_attempt($attempt);
        return $attempt;
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function sync_active_attempt_index(array $attempt): void
    {
        if (!class_exists('CBT_Active_Attempt_Index')) {
            return;
        }

        CBT_Active_Attempt_Index::set_active_attempt($attempt);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     */
    private static function sync_live_attempt_roster(array $attempt, array $context = []): void
    {
        if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
            return;
        }

        CBT_Live_Attempt_Roster_Index::sync_attempt($attempt, $context);
    }

    public static function submit_answer(WP_REST_Request $request)
    {
        $attempt_id = (int) $request->get_param('attempt_id');
        $question_id = (int) $request->get_param('question_id');
        $answer_input = $request->get_param('answer');

        if ($attempt_id <= 0 || $question_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id and question_id are required', ['status' => 400]);
        }

        $result = self::submit_answers_batch_internal($attempt_id, [
            [
                'question_id' => $question_id,
                'answer' => $answer_input,
            ],
        ], CBT_Auth::current_user_id($request), CBT_Auth::current_user_role($request));
        if (is_wp_error($result)) {
            return $result;
        }

        $item = (isset($result['items'][0]) && is_array($result['items'][0])) ? $result['items'][0] : [];

        return rest_ensure_response([
            'attempt_id' => $attempt_id,
            'question_id' => (int) ($item['question_id'] ?? $question_id),
            'is_correct' => $item['is_correct'] ?? null,
            'score_awarded' => (float) ($item['score_awarded'] ?? 0),
            'cleared' => !empty($item['cleared']) ? 1 : 0,
            'deferred' => !empty($item['deferred']) ? 1 : 0,
        ]);
    }

    public static function submit_answers_batch(WP_REST_Request $request)
    {
        $attempt_id = (int) $request->get_param('attempt_id');
        $answers = $request->get_param('answers');
        if (!is_array($answers)) {
            $answers = [];
        }

        if ($attempt_id <= 0 || empty($answers)) {
            return new WP_Error('invalid_payload', 'attempt_id and answers are required', ['status' => 400]);
        }

        $result = self::submit_answers_batch_internal(
            $attempt_id,
            $answers,
            CBT_Auth::current_user_id($request),
            CBT_Auth::current_user_role($request)
        );

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function finish_exam(WP_REST_Request $request)
    {
        global $wpdb;

        $attempt_id = (int) $request->get_param('attempt_id');
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can finish exam', ['status' => 403]);
        }

        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id is required', ['status' => 400]);
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, finished_at, score, max_score, duration_seconds
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt not found', ['status' => 404]);
        }

        if ((int) $attempt['student_id'] !== $user_id) {
            return new WP_Error('forbidden', 'You cannot finish this attempt', ['status' => 403]);
        }

        if ((string) ($attempt['status'] ?? '') === 'completed') {
            return rest_ensure_response(self::build_idempotent_finish_exam_response($attempt_id, $attempt));
        }

        if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
            return new WP_Error('attempt_closed', 'Attempt already finished', ['status' => 400]);
        }

        $finish_token = CBT_Runtime::acquire_finish_lock($attempt_id);
        $fallback_finish_lock = false;
        if ($finish_token === '') {
            $fallback_finish_lock = CBT_Cache::acquire_lock('finish_attempt:' . $attempt_id, 45, [
                'type' => 'finish_attempt',
                'attempt_id' => $attempt_id,
            ]);
            if (!$fallback_finish_lock) {
                return new WP_Error('attempt_finish_locked', 'Finalisasi ujian sedang diproses. Coba lagi beberapa detik.', ['status' => 429]);
            }
        }

        try {
            $completion_result = self::finalize_attempt_completion($attempt_id);
            if (is_wp_error($completion_result)) {
                return $completion_result;
            }

            return rest_ensure_response($completion_result);
        } finally {
            if ($finish_token !== '') {
                CBT_Runtime::release_finish_lock($attempt_id, $finish_token);
            }
            if ($fallback_finish_lock) {
                CBT_Cache::release_lock('finish_attempt:' . $attempt_id);
            }
        }
    }

    /**
     * @param array<string,mixed> $attempt
     * @return array<string,mixed>
     */
    private static function build_idempotent_finish_exam_response(int $attempt_id, array $attempt): array
    {
        $score = is_finite((float) ($attempt['score'] ?? 0))
            ? round((float) ($attempt['score'] ?? 0), 2)
            : 0.0;
        $max_score = is_finite((float) ($attempt['max_score'] ?? 0))
            ? round(max(0.0, (float) ($attempt['max_score'] ?? 0)), 2)
            : 0.0;
        $percentage = ($max_score > 0)
            ? round(($score / $max_score) * 100, 2)
            : 0.0;
        $show_student_result = self::get_exam_show_student_result((int) ($attempt['exam_id'] ?? 0));
        $kkm_percentage = self::get_exam_kkm_percentage((int) ($attempt['exam_id'] ?? 0));
        $pass_meta = self::build_result_pass_meta($score, $max_score, $kkm_percentage);

        $response = [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
            'show_student_result' => $show_student_result,
            'result_view_mode' => ($show_student_result === 1 ? 'full' : 'restricted'),
            'submission_summary' => [
                'total_questions' => 0,
                'answered_questions' => 0,
                'pending_manual_questions' => 0,
            ],
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'finished_at' => (string) ($attempt['finished_at'] ?? ''),
            'kkm_percentage' => $pass_meta['kkm_percentage'],
            'passing_score' => $pass_meta['passing_score'],
            'is_passed' => $pass_meta['is_passed'],
            'pass_label' => $pass_meta['pass_label'],
            'result_tone' => $pass_meta['result_tone'],
        ];

        if ($show_student_result !== 1) {
            unset(
                $response['score'],
                $response['max_score'],
                $response['percentage'],
                $response['kkm_percentage'],
                $response['passing_score'],
                $response['is_passed'],
                $response['pass_label'],
                $response['result_tone']
            );
        }

        return $response;
    }

    public static function security_event(WP_REST_Request $request)
    {
        return self::handle_security_event_request($request, false);
    }

    public static function native_security_event(WP_REST_Request $request)
    {
        return self::handle_security_event_request($request, true);
    }

    private static function handle_security_event_request(WP_REST_Request $request, bool $native_mode)
    {
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can log security events', ['status' => 403]);
        }

        if (!CBT_Security_Log::is_logging_enabled()) {
            return rest_ensure_response([
                'ok' => true,
                'logged' => 0,
                'skipped' => 1,
                'reason' => 'logging_disabled',
            ]);
        }

        $attempt_id = (int) self::get_request_payload_value($request, 'attempt_id');
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id is required', ['status' => 400]);
        }

        $event_type = sanitize_key((string) self::get_request_payload_value($request, 'event_type'));
        if ($event_type === '') {
            return new WP_Error('invalid_event_type', 'Event type is not allowed', ['status' => 400]);
        }

        if ($native_mode) {
            if (!isset(CBT_Security_Log::event_definitions()[$event_type])) {
                return new WP_Error('invalid_event_type', 'Event type is not allowed', ['status' => 400]);
            }
        } else {
            $browser_supported_event_definitions = CBT_Security_Log::browser_supported_event_definitions();
            if (!isset($browser_supported_event_definitions[$event_type])) {
                if (isset(CBT_Security_Log::native_supported_event_definitions()[$event_type])) {
                    return new WP_Error('native_event_requires_native_endpoint', 'Native event type must use native_security_event endpoint', ['status' => 400]);
                }

                return new WP_Error('invalid_event_type', 'Event type is not allowed', ['status' => 400]);
            }
        }

        $native_app = '';
        if ($native_mode) {
            $native_app = sanitize_key((string) self::get_request_payload_value($request, 'native_app'));
            $allowed_native_apps = CBT_Security_Log::native_app_labels();
            if ($native_app === '' || !isset($allowed_native_apps[$native_app])) {
                return new WP_Error('invalid_native_app', 'Native app is not allowed', ['status' => 400]);
            }

            if (!isset(CBT_Security_Log::native_supported_event_definitions_for_app($native_app)[$event_type])) {
                return new WP_Error('invalid_native_event_type', 'Native endpoint only accepts supported CBT security events', ['status' => 400]);
            }
        }

        $attempt = self::get_attempt_for_submission($attempt_id, $user_id);
        if (is_wp_error($attempt)) {
            return $attempt;
        }

        $context = self::get_request_payload_value($request, 'context');
        if (!is_array($context)) {
            $context = [];
        }

        if ($native_mode) {
            $context = self::enrich_native_security_event_context($request, $context, [
                'native_app' => $native_app,
                'native_version' => self::get_request_payload_value($request, 'native_version'),
                'warning_code' => self::get_request_payload_value($request, 'warning_code'),
                'warning_message' => self::get_request_payload_value($request, 'warning_message'),
                'occurred_at_client' => self::get_request_payload_value($request, 'occurred_at_client'),
            ]);
        } else {
            $context = self::enrich_security_event_context($request, $context);
        }

        $logged = CBT_Security_Log::record_attempt_event_for_context($attempt, $event_type, $context);
        if ($logged) {
            self::maybe_update_attempt_presence_from_context($attempt, $context);
        }

        return rest_ensure_response([
            'ok' => true,
            'event_type' => $event_type,
            'logged' => $logged ? 1 : 0,
            'skipped' => $logged ? 0 : 1,
        ]);
    }

    private static function get_request_payload_value(WP_REST_Request $request, string $key)
    {
        $value = $request->get_param($key);
        if ($value !== null) {
            return $value;
        }

        if (method_exists($request, 'get_json_params')) {
            $json_params = $request->get_json_params();
            if (is_array($json_params) && array_key_exists($key, $json_params)) {
                return $json_params[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function enrich_security_event_context(WP_REST_Request $request, array $context): array
    {
        $user_agent = '';

        if (method_exists($request, 'get_header')) {
            $user_agent = (string) $request->get_header('user_agent');
            if ($user_agent === '') {
                $user_agent = (string) $request->get_header('user-agent');
            }
        }

        if (empty($context['device_type'])) {
            $context['device_type'] = self::detect_security_request_device_type($user_agent);
        }

        if (empty($context['device_platform'])) {
            $context['device_platform'] = self::detect_security_request_device_platform($user_agent);
        }

        return $context;
    }

    /**
     * @param array<string,mixed> $context
     * @param array{native_app:mixed,native_version:mixed,warning_code:mixed,warning_message:mixed,occurred_at_client:mixed} $payload
     * @return array<string,mixed>
     */
    private static function enrich_native_security_event_context(WP_REST_Request $request, array $context, array $payload): array
    {
        $context = self::enrich_security_event_context($request, $context);

        $native_app = sanitize_key((string) ($payload['native_app'] ?? ''));
        $context['source'] = CBT_Security_Log::native_app_source($native_app);
        $context['native_app'] = $native_app;

        $native_version = sanitize_text_field((string) ($payload['native_version'] ?? ''));
        if ($native_version !== '') {
            $context['native_version'] = $native_version;
        }

        $warning_code = sanitize_key((string) ($payload['warning_code'] ?? ''));
        if ($warning_code !== '') {
            $context['warning_code'] = $warning_code;
        }

        $warning_message = sanitize_text_field((string) ($payload['warning_message'] ?? ''));
        if ($warning_message !== '') {
            $context['warning_message'] = $warning_message;
        }

        $occurred_at_client = sanitize_text_field((string) ($payload['occurred_at_client'] ?? ''));
        if ($occurred_at_client !== '') {
            $context['occurred_at_client'] = $occurred_at_client;
        }

        return $context;
    }

    private static function detect_security_request_device_type(string $user_agent): string
    {
        $user_agent = strtolower($user_agent);
        if ($user_agent === '') {
            return 'unknown';
        }

        $is_tablet = (bool) preg_match('/\b(ipad|tablet|playbook|silk)\b/', $user_agent)
            || (strpos($user_agent, 'android') !== false && strpos($user_agent, 'mobile') === false);
        if ($is_tablet) {
            return 'tablet';
        }

        if (preg_match('/\b(mobi|iphone|ipod|android.*mobile|windows phone)\b/', $user_agent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function maybe_update_attempt_presence_from_session(array $attempt, WP_REST_Request $request): void
    {
        if (!class_exists('CBT_Live_Proctoring_Presence') || !CBT_Live_Proctoring_Presence::is_available()) {
            return;
        }

        if (strtolower((string) ($attempt['status'] ?? '')) !== 'in_progress') {
            return;
        }

        $presence = [];
        if ($request->get_param('presence_connection_status') !== null) {
            $presence['connection_status'] = sanitize_text_field((string) $request->get_param('presence_connection_status'));
        }
        if ($request->get_param('presence_visibility_state') !== null) {
            $presence['visibility_state'] = sanitize_text_field((string) $request->get_param('presence_visibility_state'));
        }
        if ($request->get_param('presence_has_focus') !== null) {
            $presence['has_focus'] = self::normalize_request_flag($request->get_param('presence_has_focus'));
        }
        if ($request->get_param('presence_pending_sync_count') !== null) {
            $presence['pending_sync_count'] = max(0, (int) $request->get_param('presence_pending_sync_count'));
        }
        if ($request->get_param('presence_heartbeat_lost_active') !== null) {
            $presence['heartbeat_lost_active'] = self::normalize_request_flag($request->get_param('presence_heartbeat_lost_active'));
        }

        if (empty($presence)) {
            return;
        }

        CBT_Live_Proctoring_Presence::update_attempt_presence($attempt, $presence);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     */
    private static function maybe_update_attempt_presence_from_context(array $attempt, array $context): void
    {
        if (!class_exists('CBT_Live_Proctoring_Presence') || !CBT_Live_Proctoring_Presence::is_available()) {
            return;
        }

        if (strtolower((string) ($attempt['status'] ?? '')) !== 'in_progress') {
            return;
        }

        $presence = [];
        foreach (['connection_status', 'visibility_state', 'has_focus', 'pending_sync_count', 'heartbeat_lost_active'] as $key) {
            if (array_key_exists($key, $context)) {
                $presence[$key] = $context[$key];
            }
        }

        if (empty($presence)) {
            return;
        }

        CBT_Live_Proctoring_Presence::update_attempt_presence($attempt, $presence);
    }

    /**
     * @param mixed $value
     */
    private static function normalize_request_flag($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private static function detect_security_request_device_platform(string $user_agent): string
    {
        $user_agent = strtolower($user_agent);
        if ($user_agent === '') {
            return 'unknown';
        }

        if (strpos($user_agent, 'android') !== false) {
            return 'android';
        }
        if (preg_match('/\b(iphone|ipad|ipod)\b/', $user_agent)) {
            return 'ios';
        }
        if (strpos($user_agent, 'cros') !== false) {
            return 'chromeos';
        }
        if (strpos($user_agent, 'windows') !== false) {
            return 'windows';
        }
        if (strpos($user_agent, 'mac os') !== false || strpos($user_agent, 'macintosh') !== false) {
            return 'macos';
        }
        if (strpos($user_agent, 'linux') !== false || strpos($user_agent, 'x11') !== false) {
            return 'linux';
        }

        return 'unknown';
    }

    /**
     * @param array<int,array<string,mixed>> $answers
     * @return array<string,mixed>|WP_Error
     */
    private static function submit_answers_batch_internal(int $attempt_id, array $answers, int $user_id, string $role)
    {
        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can submit answers', ['status' => 403]);
        }

        if ($attempt_id <= 0 || empty($answers)) {
            return new WP_Error('invalid_payload', 'attempt_id and answers are required', ['status' => 400]);
        }

        $attempt = self::get_attempt_for_submission($attempt_id, $user_id);
        if (is_wp_error($attempt)) {
            return $attempt;
        }

        $question_ids = [];
        foreach ($answers as $answer_row) {
            if (!is_array($answer_row)) {
                continue;
            }

            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id > 0) {
                $question_ids[] = $question_id;
            }
        }
        $question_context_map = self::get_cached_question_submission_contexts($question_ids);

        $prepared_entries = [];
        foreach ($answers as $answer_row) {
            if (!is_array($answer_row)) {
                return new WP_Error('invalid_payload', 'Each answer item must be an object', ['status' => 400]);
            }

            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                return new WP_Error('invalid_payload', 'question_id is required for each answer item', ['status' => 400]);
            }

            $prepared = self::prepare_submission_entry(
                $attempt,
                $question_id,
                $answer_row['answer'] ?? null,
                is_array($question_context_map[$question_id] ?? null) ? $question_context_map[$question_id] : null
            );
            if (is_wp_error($prepared)) {
                return $prepared;
            }

            $prepared_entries[$question_id] = $prepared;
        }

        $prepared_entries = array_values($prepared_entries);
        if (empty($prepared_entries)) {
            return new WP_Error('invalid_payload', 'No valid answers submitted', ['status' => 400]);
        }

        $duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            self::get_exam_duration_minutes((int) ($attempt['exam_id'] ?? 0))
        );
        $runtime_used = false;
        $buffered = 0;
        $flushed = 0;
        $pending_count = 0;

        if (CBT_Runtime::is_ready()) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
            $buffer_result = CBT_Runtime::buffer_entries($attempt, $duration_minutes, $prepared_entries);
            $runtime_used = !empty($buffer_result['runtime_used']);
            $buffered = (int) ($buffer_result['buffered'] ?? 0);
            $flushed = (int) ($buffer_result['flushed'] ?? 0);
            $pending_count = (int) ($buffer_result['pending_count'] ?? 0);
        }

        if (!$runtime_used) {
            if (CBT_Runtime::is_buffer_enabled() && !CBT_Runtime::fallback_to_db_enabled()) {
                return new WP_Error('runtime_buffer_unavailable', 'Redis runtime CBT tidak siap dan fallback DB dimatikan.', ['status' => 503]);
            }

            $persisted = CBT_Runtime::persist_entries_to_db($attempt_id, $prepared_entries);
            if (is_wp_error($persisted)) {
                return $persisted;
            }

            CBT_Cache::invalidate_attempt($attempt_id);
            $flushed = count($prepared_entries);
        }

        return [
            'attempt_id' => $attempt_id,
            'accepted_count' => count($prepared_entries),
            'buffered' => $buffered,
            'flushed' => $flushed,
            'pending_count' => $pending_count,
            'runtime_used' => $runtime_used ? 1 : 0,
            'items' => array_map([self::class, 'format_submission_response_item'], $prepared_entries),
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function get_attempt_for_submission(int $attempt_id, int $user_id)
    {
        global $wpdb;

        $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
        if (is_array($runtime_attempt)) {
            return $runtime_attempt;
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt atau soal tidak ditemukan pada exam ini', ['status' => 404]);
        }

        if ((int) ($attempt['student_id'] ?? 0) !== $user_id) {
            return new WP_Error('forbidden', 'You cannot submit answer for this attempt', ['status' => 403]);
        }

        if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
            return new WP_Error('attempt_closed', 'Attempt already finished', ['status' => 400]);
        }

        $duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            self::get_exam_duration_minutes((int) ($attempt['exam_id'] ?? 0))
        );
        if ($duration_minutes > 0) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
        }

        return $attempt;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function prepare_submission_entry(array $attempt, int $question_id, $answer_input, ?array $question_context = null)
    {
        $question_context = is_array($question_context) ? $question_context : self::get_cached_question_submission_context($question_id);
        if (!$question_context || (int) ($question_context['exam_id'] ?? 0) !== (int) ($attempt['exam_id'] ?? 0)) {
            return new WP_Error('not_found', 'Attempt atau soal tidak ditemukan pada exam ini', ['status' => 404]);
        }

        $now = current_time('mysql');

        if (self::is_empty_answer_submission($answer_input)) {
            return [
                'question_id' => $question_id,
                'selected_option_ids' => '',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0.0,
                'answered_at' => $now,
                'clear' => 1,
                'deferred' => 0,
                'answer' => null,
            ];
        }

        $question_type = (string) ($question_context['question_type'] ?? '');
        $deferred_scoring = self::should_defer_submit_scoring();
        if ($deferred_scoring) {
            $normalized_storage = self::normalize_submission_for_storage($question_type, $answer_input);
            $evaluated = [
                'selected_option_ids' => $normalized_storage['selected_option_ids'],
                'answer_text' => $normalized_storage['answer_text'],
                'is_correct' => null,
                'score_awarded' => 0.0,
            ];
        } else {
            $evaluated = self::evaluate_answer_from_submission_context($question_context, $answer_input);
        }

        return [
            'question_id' => $question_id,
            'selected_option_ids' => (string) ($evaluated['selected_option_ids'] ?? ''),
            'answer_text' => (string) ($evaluated['answer_text'] ?? ''),
            'is_correct' => $evaluated['is_correct'] ?? null,
            'score_awarded' => (float) ($evaluated['score_awarded'] ?? 0),
            'answered_at' => $now,
            'clear' => 0,
            'deferred' => $deferred_scoring ? 1 : 0,
            'answer' => self::normalize_runtime_answer_value($answer_input),
        ];
    }

    /**
     * @param mixed $answer_input
     * @return mixed
     */
    private static function normalize_runtime_answer_value($answer_input)
    {
        if ($answer_input === null || is_scalar($answer_input)) {
            return $answer_input;
        }

        return is_array($answer_input) ? $answer_input : null;
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    private static function format_submission_response_item(array $entry): array
    {
        return [
            'question_id' => (int) ($entry['question_id'] ?? 0),
            'is_correct' => array_key_exists('is_correct', $entry) ? $entry['is_correct'] : null,
            'score_awarded' => (float) ($entry['score_awarded'] ?? 0),
            'deferred' => !empty($entry['deferred']) ? 1 : 0,
            'cleared' => !empty($entry['clear']) ? 1 : 0,
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

        return CBT_Runtime::ensure_attempt_state($attempt, $duration_minutes, is_array($answer_rows) ? $answer_rows : []);
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

    /**
     * @return array<string,mixed>
     */
    private static function build_exams_payload(int $user_id, string $role): array
    {
        if (self::is_student_role($role)) {
            $batch_payloads = self::build_batch_student_exam_availability_snapshot_payloads([$user_id]);
            return isset($batch_payloads[$user_id]) && is_array($batch_payloads[$user_id])
                ? $batch_payloads[$user_id]
                : [
                    'items' => [],
                    'current_user' => null,
                ];
        }

        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';

        $where = '1=1';
        $params = [];

        if ($role === 'guru' || $role === 'teacher') {
            $where .= ' AND created_by = %d';
            $params[] = $user_id;
        }

                $sql = "SELECT
                    e.id,
                    e.subject_id,
                    e.title,
                    e.duration_minutes,
                    e.kkm_percentage,
                    e.total_questions,
                    e.randomize_questions,
                    e.show_student_result,
                    e.enable_calculator,
                    e.status,
                    e.starts_at,
                    e.ends_at,
                    e.target_kelas,
                    e.created_by,
                    e.created_at,
                    e.updated_at,
                    s.name AS subject_name,
                    s.code AS subject_code,
                    COALESCE(NULLIF(e.total_questions, 0), 0) AS question_count
                FROM {$exam_table} e
                LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                WHERE {$where}
                ORDER BY e.created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = array_map(static function ($row): array {
                $item = (array) $row;
                $item['kkm_percentage'] = self::normalize_exam_kkm_percentage((float) ($item['kkm_percentage'] ?? 75.0));
                $item['show_student_result'] = self::normalize_show_student_result($item['show_student_result'] ?? 1);
                $item['enable_calculator'] = self::normalize_enable_calculator($item['enable_calculator'] ?? 1);
                return $item;
            }, (array) $wpdb->get_results($sql, ARRAY_A));

        $current_user_payload = null;
        $current_user = get_user_by('id', $user_id);
        if ($current_user instanceof WP_User) {
            $profile_snapshot = self::get_live_user_profile($user_id);
            $current_user_payload = [
                'user_id' => $user_id,
                'role' => $role,
                'display_name' => (string) $current_user->display_name,
                'username' => (string) $current_user->user_login,
                'email' => (string) $current_user->user_email,
                'kode_kelas' => (string) ($profile_snapshot['kode_kelas'] ?? ''),
                'kode_ruang' => (string) ($profile_snapshot['kode_ruang'] ?? ''),
                'agama' => (string) ($profile_snapshot['agama'] ?? ''),
                'foto' => (string) ($profile_snapshot['foto'] ?? ''),
            ];
        }

        return [
            'items' => $rows ?: [],
            'current_user' => $current_user_payload,
        ];
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    private static function get_live_user_profile(int $user_id): array
    {
        return CBT_Student_Profile_Cache::get_snapshot($user_id);
    }

    private static function get_live_user_kelas(int $user_id): string
    {
        $profile = self::get_live_user_profile($user_id);
        return self::normalize_kelas_code((string) ($profile['kode_kelas'] ?? ''));
    }

    /**
     * @param int[] $user_ids
     */
    private static function prime_student_availability_user_caches(array $user_ids): void
    {
        if (empty($user_ids)) {
            return;
        }

        if (function_exists('cache_users')) {
            cache_users($user_ids);
        }

        if (function_exists('update_meta_cache')) {
            update_meta_cache('user', $user_ids);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetch_published_exam_rows_for_availability(): array
    {
        static $request_cache = null;
        $catalog_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        $catalog_version = max(1, (int) ($catalog_entry['version'] ?? 1));

        if (
            is_array($request_cache)
            && (int) ($request_cache['catalog_version'] ?? 0) === $catalog_version
            && isset($request_cache['items'])
            && is_array($request_cache['items'])
        ) {
            return $request_cache['items'];
        }

        $catalog = CBT_Cache::remember(
            'rest:exams:availability:base_catalog',
            self::AVAILABILITY_BASE_CATALOG_TTL,
            [CBT_Cache::namespace_catalog()],
            static function (): array {
                global $wpdb;

                $exam_table = $wpdb->prefix . 'cbt_exams';
                $subject_table = $wpdb->prefix . 'cbt_subjects';
                $sql = "SELECT
                            e.id,
                            e.subject_id,
                            e.title,
                            e.duration_minutes,
                            e.kkm_percentage,
                            e.total_questions,
                            e.randomize_questions,
                            e.show_student_result,
                            e.enable_calculator,
                            e.status,
                            e.starts_at,
                            e.ends_at,
                            e.target_kelas,
                            e.created_by,
                            e.created_at,
                            e.updated_at,
                            s.name AS subject_name,
                            s.code AS subject_code,
                            COALESCE(NULLIF(e.total_questions, 0), 0) AS question_count
                        FROM {$exam_table} e
                        LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                        WHERE e.status = 'published'
                        ORDER BY e.created_at DESC";

                return array_map(static function ($row): array {
                    $item = (array) $row;
                    $item['kkm_percentage'] = self::normalize_exam_kkm_percentage((float) ($item['kkm_percentage'] ?? 75.0));
                    $item['show_student_result'] = self::normalize_show_student_result($item['show_student_result'] ?? 1);
                    $item['enable_calculator'] = self::normalize_enable_calculator($item['enable_calculator'] ?? 1);
                    return $item;
                }, (array) $wpdb->get_results($sql, ARRAY_A));
            }
        );

        $request_cache = [
            'catalog_version' => $catalog_version,
            'items' => is_array($catalog) ? $catalog : [],
        ];

        return $request_cache['items'];
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function fetch_latest_attempts_by_user_and_exam(array $user_ids): array
    {
        global $wpdb;

        $user_ids = array_values(array_filter(array_unique(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $placeholders = implode(', ', array_fill(0, count($user_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT a.student_id, a.exam_id, a.id, a.status, a.score, a.max_score, a.started_at, a.finished_at
             FROM {$attempt_table} a
             WHERE a.student_id IN ({$placeholders})
               AND a.status IN ('in_progress', 'completed')
             ORDER BY a.student_id ASC, a.exam_id ASC, FIELD(a.status, 'in_progress', 'completed'), a.id DESC",
            ...$user_ids
        );
        $rows = (array) $wpdb->get_results($query, ARRAY_A);
        $attempts_by_user = [];

        foreach ($rows as $attempt_row) {
            $attempt_row = (array) $attempt_row;
            $student_id = (int) ($attempt_row['student_id'] ?? 0);
            if ($student_id <= 0 && count($user_ids) === 1) {
                $student_id = (int) $user_ids[0];
            }

            $exam_id = (int) ($attempt_row['exam_id'] ?? 0);
            if ($student_id <= 0 || $exam_id <= 0) {
                continue;
            }

            if (!isset($attempts_by_user[$student_id])) {
                $attempts_by_user[$student_id] = [];
            }

            if (!isset($attempts_by_user[$student_id][$exam_id])) {
                $attempts_by_user[$student_id][$exam_id] = $attempt_row;
            }
        }

        return $attempts_by_user;
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     * @param array<int,array<string,mixed>> $latest_attempt_by_exam
     * @return array<int,array<string,mixed>>
     */
    private static function build_student_exam_availability_items(
        array $exam_rows,
        string $student_kelas,
        string $student_now,
        string $server_timezone,
        array $latest_attempt_by_exam
    ): array {
        return array_map(static function ($row) use ($student_kelas, $student_now, $server_timezone, $latest_attempt_by_exam): array {
            $item = (array) $row;
            $now_ts = strtotime($student_now);
            $start_ts = !empty($item['starts_at']) ? strtotime((string) $item['starts_at']) : false;
            $end_ts = !empty($item['ends_at']) ? strtotime((string) $item['ends_at']) : false;

            $within_schedule = (
                (empty($item['starts_at']) || (string) $item['starts_at'] <= $student_now) &&
                (empty($item['ends_at']) || (string) $item['ends_at'] >= $student_now)
            );
            $class_allowed = self::exam_allows_student_class($item, $student_kelas);

            $schedule_reason = 'in_range';
            if ($start_ts !== false && $now_ts !== false && $start_ts > $now_ts) {
                $schedule_reason = 'not_started';
            } elseif ($end_ts !== false && $now_ts !== false && $end_ts < $now_ts) {
                $schedule_reason = 'ended';
            }

            $availability_reason = 'ok';
            if (!$class_allowed) {
                $availability_reason = 'class_mismatch';
            } elseif (!$within_schedule) {
                $availability_reason = $schedule_reason;
            }

            $item['is_within_schedule'] = $within_schedule ? 1 : 0;
            $item['is_class_allowed'] = $class_allowed ? 1 : 0;
            $item['is_available_now'] = ($within_schedule && $class_allowed) ? 1 : 0;
            $item['availability_reason'] = $availability_reason;
            $item['server_now'] = $student_now;
            $item['server_timezone'] = $server_timezone;

            $exam_id = (int) ($item['id'] ?? 0);
            $latest_attempt = ($exam_id > 0 && isset($latest_attempt_by_exam[$exam_id]))
                ? (array) $latest_attempt_by_exam[$exam_id]
                : null;
            if ($latest_attempt) {
                $show_student_result = self::normalize_show_student_result($item['show_student_result'] ?? 1);
                $attempt_score = (float) ($latest_attempt['score'] ?? 0);
                $attempt_max_score = (float) ($latest_attempt['max_score'] ?? 0);
                $attempt_percentage = $attempt_max_score > 0
                    ? round(($attempt_score / $attempt_max_score) * 100, 2)
                    : 0.0;
                $attempt_pass_meta = self::build_result_pass_meta($attempt_score, $attempt_max_score, (float) ($item['kkm_percentage'] ?? 75.0));
                $item['latest_attempt_id'] = (int) ($latest_attempt['id'] ?? 0);
                $item['latest_attempt_status'] = (string) ($latest_attempt['status'] ?? '');
                $item['latest_attempt_score'] = $attempt_score;
                $item['latest_attempt_max_score'] = $attempt_max_score;
                $item['latest_attempt_percentage'] = $attempt_percentage;
                $item['latest_attempt_passing_score'] = $attempt_pass_meta['passing_score'];
                $item['latest_attempt_is_passed'] = $attempt_pass_meta['is_passed'];
                $item['latest_attempt_pass_label'] = $attempt_pass_meta['pass_label'];
                $item['latest_attempt_result_tone'] = $attempt_pass_meta['result_tone'];
                $item['latest_attempt_started_at'] = (string) ($latest_attempt['started_at'] ?? '');
                $item['latest_attempt_finished_at'] = (string) ($latest_attempt['finished_at'] ?? '');
                if ($show_student_result !== 1 && (string) ($item['latest_attempt_status'] ?? '') === 'completed') {
                    $item['latest_attempt_score'] = 0.0;
                    $item['latest_attempt_max_score'] = 0.0;
                    $item['latest_attempt_percentage'] = 0.0;
                    $item['latest_attempt_passing_score'] = 0.0;
                    $item['latest_attempt_is_passed'] = 0;
                    $item['latest_attempt_pass_label'] = '';
                    $item['latest_attempt_result_tone'] = '';
                }
            } else {
                $item['latest_attempt_id'] = 0;
                $item['latest_attempt_status'] = '';
                $item['latest_attempt_score'] = 0.0;
                $item['latest_attempt_max_score'] = 0.0;
                $item['latest_attempt_percentage'] = 0.0;
                $item['latest_attempt_passing_score'] = self::calculate_result_passing_score(0.0, (float) ($item['kkm_percentage'] ?? 75.0));
                $item['latest_attempt_is_passed'] = 0;
                $item['latest_attempt_pass_label'] = 'TIDAK LULUS';
                $item['latest_attempt_result_tone'] = 'fail';
                $item['latest_attempt_started_at'] = '';
                $item['latest_attempt_finished_at'] = '';
            }

            return $item;
        }, $exam_rows);
    }

    /**
     * @param array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string} $profile_snapshot
     * @return array<string,mixed>|null
     */
    private static function build_student_exam_current_user_payload(int $user_id, string $role, array $profile_snapshot): ?array
    {
        $current_user = get_user_by('id', $user_id);
        if (!($current_user instanceof WP_User)) {
            return null;
        }

        return [
            'user_id' => $user_id,
            'role' => $role,
            'display_name' => (string) $current_user->display_name,
            'username' => (string) $current_user->user_login,
            'email' => (string) $current_user->user_email,
            'kode_kelas' => (string) ($profile_snapshot['kode_kelas'] ?? ''),
            'kode_ruang' => (string) ($profile_snapshot['kode_ruang'] ?? ''),
            'agama' => (string) ($profile_snapshot['agama'] ?? ''),
            'foto' => (string) ($profile_snapshot['foto'] ?? ''),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private static function append_global_token_meta_to_exam_items(array $items): array
    {
        $global_token_meta = CBT_Auth::get_global_exam_token(true);
        $requires_token = trim((string) ($global_token_meta['token'] ?? '')) !== '' ? 1 : 0;
        $token_frontend_auto_apply = (int) ($global_token_meta['frontend_auto_apply'] ?? 0);
        $token_auto_value = ($requires_token === 1 && $token_frontend_auto_apply === 1)
            ? strtoupper(trim((string) ($global_token_meta['token'] ?? '')))
            : '';
        $token_refresh_minutes = (int) ($global_token_meta['refresh_minutes'] ?? 0);
        $token_next_refresh_at = (int) ($global_token_meta['next_refresh_at'] ?? 0);

        return array_map(static function ($row) use ($requires_token, $token_frontend_auto_apply, $token_auto_value, $token_refresh_minutes, $token_next_refresh_at): array {
            $item = (array) $row;
            $item['requires_token'] = $requires_token;
            $item['token_frontend_auto_apply'] = $token_frontend_auto_apply;
            $item['token_input_required'] = ($requires_token === 1 && $token_frontend_auto_apply !== 1) ? 1 : 0;
            $item['token_auto_value'] = $token_auto_value;
            $item['token_refresh_minutes'] = $token_refresh_minutes;
            $item['token_next_refresh_at'] = $token_next_refresh_at;
            unset($item['exam_token']);
            return $item;
        }, $items);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
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
     * @return array<string,mixed>
     */
    private static function get_cached_attempt_session_snapshot(array $attempt, array $exam, string $attempt_table): array
    {
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return [];
        }

        if (!class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
            $contract = self::build_attempt_runtime_snapshot_contract($attempt, $exam, [], $attempt_table);
            return [
                'attempt_id' => $attempt_id,
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
        }

        return CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot($attempt_id, function () use ($attempt, $exam, $attempt_table): array {
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
            return self::build_attempt_runtime_snapshot_contract($attempt, $exam, $questions, $attempt_table);
        }

        return CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot($attempt_id, function () use ($attempt, $exam, $questions, $attempt_table): array {
            $contract = self::build_attempt_runtime_snapshot_contract($attempt, $exam, $questions, $attempt_table);
            return array_merge($contract, [
                'attempt_id' => (int) ($attempt['id'] ?? 0),
                'exam_id' => (int) ($attempt['exam_id'] ?? $exam['id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'status' => (string) ($attempt['status'] ?? ''),
            ]);
        });
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

        return array_merge($questions, self::get_question_payload_by_ids($exam_id, $missing_question_ids));
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
        $question_rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.points, q.correct_text, q.created_at, q.updated_at,
                        COALESCE(q.is_active, 1) AS is_active,
                        qsa.correct_text AS short_answer_correct_text
                 FROM {$question_table} q
                 LEFT JOIN {$short_answer_table} qsa ON qsa.question_id = q.id
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
                "SELECT q.id, q.question_type, q.correct_text
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

        foreach ($question_rows as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_ids[] = $question_id;

            $question_type = (string) ($question['question_type'] ?? '');
            if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
                $option_question_ids[] = $question_id;
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
            'randomize_questions' => (int) ($exam_row['randomize_questions'] ?? 0) === 1 ? 1 : 0,
            'randomize_options' => (int) ($exam_row['randomize_options'] ?? 0) === 1 ? 1 : 0,
            'duration_minutes' => max(0, (int) ($exam_row['duration_minutes'] ?? 0)),
            'show_student_result' => self::normalize_show_student_result($exam_row['show_student_result'] ?? 1),
            'enable_calculator' => self::normalize_enable_calculator($exam_row['enable_calculator'] ?? 1),
            'option_randomization_tokens_by_question' => self::normalize_attempt_option_order_map($option_tokens_by_question),
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

        return array_merge($questions, $extra_questions);
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
                        qsa.correct_text AS short_answer_correct_text
                 FROM {$question_table} q
                 LEFT JOIN {$short_answer_table} qsa ON qsa.question_id = q.id
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
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true)) {
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
            }

            unset($question['short_answer_correct_text']);
            if ((string) ($question['question_type'] ?? '') === 'true_false_matrix') {
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
        if (!$shuffle_items) {
            return [];
        }

        $option_order_map = [];

        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0 || !self::question_supports_option_randomization($question)) {
                continue;
            }

            $item_tokens = self::extract_randomizable_question_item_keys($question);
            if (count($item_tokens) <= 1) {
                continue;
            }

            if ($shuffle_items) {
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
    private static function build_attempt_option_order_map_from_snapshot_tokens(array $option_tokens_by_question, bool $shuffle_items): array
    {
        if (!$shuffle_items) {
            return [];
        }

        $option_order_map = [];
        $normalized_tokens_by_question = self::normalize_attempt_option_order_map($option_tokens_by_question);
        foreach ($normalized_tokens_by_question as $question_id => $item_tokens) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || count($item_tokens) <= 1) {
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
            if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
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
        return in_array((string) ($question['question_type'] ?? ''), ['multiple_choice', 'multiple_answer', 'true_false_matrix'], true);
    }

    /**
     * @param array<string,mixed> $question
     * @return array<int,string>
     */
    private static function extract_randomizable_question_item_keys(array $question): array
    {
        $question_type = (string) ($question['question_type'] ?? '');

        if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
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
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true)) {
                $manifest_item['options'] = array_map(static function ($option): array {
                    $option_row = (array) $option;
                    return [
                        'id' => (int) ($option_row['id'] ?? 0),
                        'option_key' => (string) ($option_row['option_key'] ?? ''),
                        'option_text' => (string) ($option_row['option_text'] ?? ''),
                    ];
                }, is_array($question['options'] ?? null) ? $question['options'] : []);
            }

            if ($question_type === 'true_false_matrix' && isset($question['true_false_matrix_meta']) && is_array($question['true_false_matrix_meta'])) {
                $manifest_item['true_false_matrix_meta'] = $question['true_false_matrix_meta'];
            }

            if ($question_type === 'short_answer' && isset($question['short_answer_meta']) && is_array($question['short_answer_meta'])) {
                $manifest_item['short_answer_meta'] = $question['short_answer_meta'];
            }

            $manifest[] = $manifest_item;
        }

        return $manifest;
    }

    /**
     * @param array<string,mixed>|null $attempt
     * @return array<int,int>
     */
    private static function get_attempt_answered_question_ids(int $attempt_id, ?array $attempt = null, int $duration_minutes = 0): array
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        if (is_array($attempt) && CBT_Runtime::is_ready()) {
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
    private static function build_attempt_existing_answers_map(array $questions, int $attempt_id, ?array $attempt = null, int $duration_minutes = 0): array
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $existing_answers = [];
        if (is_array($attempt) && CBT_Runtime::is_ready()) {
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
                case 'true_false_matrix':
                    $existing_matrix = self::normalize_true_false_matrix_submission($existing_text);
                    if (!empty($existing_matrix)) {
                        $question['existing_answer'] = $existing_matrix;
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
    private static function build_attempt_score_snapshot(array $attempt): array
    {
        $review_items = self::build_attempt_review_items($attempt);
        self::sync_attempt_auto_scores((int) ($attempt['id'] ?? 0), $review_items);

        $review_summary = self::summarize_review_items($review_items);
        $review_score = 0.0;
        $review_max_score = 0.0;
        foreach ($review_items as $item_row) {
            $item = (array) $item_row;
            $review_score += max(0.0, (float) ($item['score_awarded'] ?? 0));
            $review_max_score += max(0.0, (float) ($item['points'] ?? 0));
        }

        $review_score = round($review_score, 2);
        $review_max_score = round($review_max_score, 2);
        $stored_score = (float) ($attempt['score'] ?? 0);
        $stored_max_score = (float) ($attempt['max_score'] ?? 0);
        $has_score_drift = (
            abs($stored_score - $review_score) > 0.0001 ||
            abs($stored_max_score - $review_max_score) > 0.0001
        );

        return [
            'review_items' => $review_items,
            'review_summary' => $review_summary,
            'score' => $review_score,
            'max_score' => $review_max_score,
            'percentage' => ($review_max_score > 0)
                ? round(($review_score / $review_max_score) * 100, 2)
                : 0,
            'has_score_drift' => $has_score_drift ? 1 : 0,
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id is required', ['status' => 400]);
        }

        $defer_invalidation = !empty($options['defer_invalidation']);

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, question_order, option_order, score, max_score, started_at, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt not found', ['status' => 404]);
        }

        $duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            self::get_exam_duration_minutes((int) ($attempt['exam_id'] ?? 0))
        );
        if ($duration_minutes > 0) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
        }
        CBT_Runtime::flush_attempt($attempt_id, true);

        $score_snapshot = self::build_attempt_score_snapshot($attempt);
        $score = (float) ($score_snapshot['score'] ?? 0.0);
        $max_score = (float) ($score_snapshot['max_score'] ?? 0.0);
        $percentage = (float) ($score_snapshot['percentage'] ?? 0.0);
        $review_summary = isset($score_snapshot['review_summary']) && is_array($score_snapshot['review_summary'])
            ? (array) $score_snapshot['review_summary']
            : [];
        $kkm_percentage = self::get_exam_kkm_percentage((int) ($attempt['exam_id'] ?? 0));
        $show_student_result = self::get_exam_show_student_result((int) ($attempt['exam_id'] ?? 0));
        $pass_meta = self::build_result_pass_meta($score, $max_score, $kkm_percentage);
        $finished_at = is_string($finished_at) && $finished_at !== '' ? $finished_at : current_time('mysql');

        $started_ts = self::local_datetime_to_timestamp((string) ($attempt['started_at'] ?? ''));
        $duration_seconds = max(0, time() - (int) ($started_ts ?? time()));

        $updated = $wpdb->update(
            $attempt_table,
            [
                'status' => 'completed',
                'score' => $score,
                'max_score' => $max_score,
                'finished_at' => $finished_at,
                'duration_seconds' => $duration_seconds,
                'updated_at' => $finished_at,
            ],
            ['id' => $attempt_id],
            ['%s', '%f', '%f', '%s', '%d', '%s'],
            ['%d']
        );
        if ($updated === false) {
            return new WP_Error('db_failed', 'Failed to finish exam', ['status' => 500]);
        }

        if (class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
            CBT_Attempt_Session_Snapshot_Cache::update_attempt_status($attempt_id, 'completed');
        }
        if (class_exists('CBT_Attempt_Question_Contract_Cache')) {
            CBT_Attempt_Question_Contract_Cache::update_attempt_status($attempt_id, 'completed');
        }

        $student_id = (int) ($attempt['student_id'] ?? 0);
        CBT_Runtime::clear_attempt_runtime($attempt_id);
        if (class_exists('CBT_Active_Attempt_Index')) {
            CBT_Active_Attempt_Index::clear_active_attempt(
                $student_id,
                (int) ($attempt['exam_id'] ?? 0),
                $attempt_id
            );
        }
        if (!$defer_invalidation) {
            CBT_Cache::invalidate_attempt($attempt_id);
            CBT_Cache::invalidate_analytics();
            CBT_Cache::invalidate_analytics_exam((int) ($attempt['exam_id'] ?? 0));
            if ($student_id > 0) {
                CBT_Cache::invalidate_user($student_id);
                CBT_UI_State::clear_attempt_state($student_id, $attempt_id);
            }
        }

        $response = [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
            'show_student_result' => $show_student_result,
            'result_view_mode' => ($show_student_result === 1 ? 'full' : 'restricted'),
            'submission_summary' => self::build_result_submission_summary($review_summary),
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'finished_at' => $finished_at,
            'kkm_percentage' => $pass_meta['kkm_percentage'],
            'passing_score' => $pass_meta['passing_score'],
            'is_passed' => $pass_meta['is_passed'],
            'pass_label' => $pass_meta['pass_label'],
            'result_tone' => $pass_meta['result_tone'],
        ];

        if ($show_student_result !== 1) {
            unset(
                $response['score'],
                $response['max_score'],
                $response['percentage'],
                $response['kkm_percentage'],
                $response['passing_score'],
                $response['is_passed'],
                $response['pass_label'],
                $response['result_tone']
            );
        }

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_result_payload(array $attempt): array
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $answer_table = $wpdb->prefix . 'cbt_answers';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.id, e.title, e.duration_minutes, e.kkm_percentage, e.show_student_result, e.subject_id, s.name AS subject_name, s.code AS subject_code
                 FROM {$exam_table} e
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                 WHERE e.id = %d",
                (int) ($attempt['exam_id'] ?? 0)
            ),
            ARRAY_A
        );

        $score_snapshot = self::build_attempt_score_snapshot($attempt);
        $review_items = (array) ($score_snapshot['review_items'] ?? []);
        $review_summary = (array) ($score_snapshot['review_summary'] ?? []);
        $review_score = (float) ($score_snapshot['score'] ?? 0.0);
        $review_max_score = (float) ($score_snapshot['max_score'] ?? 0.0);
        $has_score_drift = !empty($score_snapshot['has_score_drift']);

        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, q.question_text, q.question_type, q.points
                 FROM {$answer_table} a
                 INNER JOIN {$question_table} q ON q.id = a.question_id
                 WHERE a.attempt_id = %d
                 ORDER BY a.question_id ASC",
                (int) ($attempt['id'] ?? 0)
            ),
            ARRAY_A
        );

        $attempt['score'] = $review_score;
        $attempt['max_score'] = $review_max_score;
        if ((string) ($attempt['status'] ?? '') === 'completed' && $has_score_drift) {
            $wpdb->update(
                $attempt_table,
                [
                    'score' => $review_score,
                    'max_score' => $review_max_score,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) ($attempt['id'] ?? 0)],
                ['%f', '%f', '%s'],
                ['%d']
            );
        }

        $percentage = ($review_max_score > 0)
            ? round(($review_score / $review_max_score) * 100, 2)
            : 0;
        $kkm_percentage = self::normalize_exam_kkm_percentage((float) ($exam['kkm_percentage'] ?? 75.0));
        if (is_array($exam)) {
            $exam['kkm_percentage'] = $kkm_percentage;
            $exam['show_student_result'] = self::normalize_show_student_result($exam['show_student_result'] ?? 1);
        }
        $pass_meta = self::build_result_pass_meta($review_score, $review_max_score, $kkm_percentage);
        $show_student_result = self::normalize_show_student_result($exam['show_student_result'] ?? 1);
        $submission_summary = self::build_result_submission_summary($review_summary);

        return [
            'attempt' => $attempt,
            'exam' => $exam,
            'show_student_result' => $show_student_result,
            'result_view_mode' => 'full',
            'submission_summary' => $submission_summary,
            'percentage' => $percentage,
            'kkm_percentage' => $pass_meta['kkm_percentage'],
            'passing_score' => $pass_meta['passing_score'],
            'is_passed' => $pass_meta['is_passed'],
            'pass_label' => $pass_meta['pass_label'],
            'result_tone' => $pass_meta['result_tone'],
            'answers' => $answers ?: [],
            'review_items' => $review_items,
            'review_summary' => $review_summary,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function build_restricted_student_result_payload(array $payload): array
    {
        $attempt = isset($payload['attempt']) && is_array($payload['attempt']) ? (array) $payload['attempt'] : [];
        $exam = isset($payload['exam']) && is_array($payload['exam']) ? (array) $payload['exam'] : [];
        $submission_summary = self::build_result_submission_summary(
            isset($payload['submission_summary']) && is_array($payload['submission_summary'])
                ? (array) $payload['submission_summary']
                : (isset($payload['review_summary']) && is_array($payload['review_summary']) ? (array) $payload['review_summary'] : [])
        );

        return [
            'attempt' => [
                'id' => (int) ($attempt['id'] ?? 0),
                'exam_id' => (int) ($attempt['exam_id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'status' => (string) ($attempt['status'] ?? ''),
                'started_at' => (string) ($attempt['started_at'] ?? ''),
                'finished_at' => (string) ($attempt['finished_at'] ?? ''),
            ],
            'exam' => [
                'id' => (int) ($exam['id'] ?? 0),
                'title' => (string) ($exam['title'] ?? ''),
                'duration_minutes' => (int) ($exam['duration_minutes'] ?? 0),
                'show_student_result' => 0,
            ],
            'show_student_result' => 0,
            'result_view_mode' => 'restricted',
            'submission_summary' => $submission_summary,
        ];
    }

    /**
     * @param array<string,mixed> $summary
     * @return array{total_questions:int,answered_questions:int,pending_manual_questions:int}
     */
    private static function build_result_submission_summary(array $summary): array
    {
        $total_questions = max(0, (int) ($summary['total_questions'] ?? 0));
        $explicit_answered_questions = array_key_exists('answered_questions', $summary)
            ? max(0, (int) ($summary['answered_questions'] ?? 0))
            : null;
        $correct_questions = max(0, (int) ($summary['correct_questions'] ?? 0));
        $wrong_questions = max(0, (int) ($summary['wrong_questions'] ?? 0));
        $graded_questions = max(0, (int) ($summary['graded_questions'] ?? 0));
        $manual_questions = max(0, (int) ($summary['manual_questions'] ?? $summary['pending_manual_questions'] ?? 0));
        $unanswered_questions = max(0, (int) ($summary['unanswered_questions'] ?? 0));

        if ($total_questions <= 0) {
            $total_questions = max(0, $correct_questions + $wrong_questions + $graded_questions + $manual_questions + $unanswered_questions);
        }

        $answered_questions = $explicit_answered_questions !== null
            ? max(0, min($total_questions, $explicit_answered_questions))
            : max(0, min($total_questions, $correct_questions + $wrong_questions + $graded_questions + $manual_questions));

        return [
            'total_questions' => $total_questions,
            'answered_questions' => $answered_questions,
            'pending_manual_questions' => $manual_questions,
        ];
    }

    private static function is_essay_answer_reviewed(?array $answer_row): bool
    {
        if (!is_array($answer_row)) {
            return false;
        }

        return array_key_exists('is_correct', $answer_row)
            && $answer_row['is_correct'] !== null
            && $answer_row['is_correct'] !== '';
    }

    private static function get_exam_show_student_result(int $exam_id): int
    {
        global $wpdb;
        static $show_result_cache = [];

        if ($exam_id <= 0) {
            return 1;
        }

        if (array_key_exists($exam_id, $show_result_cache)) {
            return (int) $show_result_cache[$exam_id];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT show_student_result FROM {$exam_table} WHERE id = %d LIMIT 1",
                $exam_id
            )
        );

        $show_result_cache[$exam_id] = self::normalize_show_student_result($value);

        return (int) $show_result_cache[$exam_id];
    }

    private static function get_exam_kkm_percentage(int $exam_id): float
    {
        global $wpdb;
        static $kkm_cache = [];

        if ($exam_id <= 0) {
            return 75.0;
        }

        if (array_key_exists($exam_id, $kkm_cache)) {
            return (float) $kkm_cache[$exam_id];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT kkm_percentage FROM {$exam_table} WHERE id = %d LIMIT 1",
                $exam_id
            )
        );

        $kkm_cache[$exam_id] = self::normalize_exam_kkm_percentage((float) $value);

        return (float) $kkm_cache[$exam_id];
    }

    private static function normalize_exam_kkm_percentage(float $value): float
    {
        if (!is_finite($value)) {
            return 75.0;
        }

        return round(min(100.0, max(0.0, $value)), 2);
    }

    /**
     * @param mixed $value
     */
    private static function normalize_show_student_result($value): int
    {
        return ((int) $value === 0) ? 0 : 1;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_enable_calculator($value): int
    {
        return ((int) $value === 0) ? 0 : 1;
    }

    private static function calculate_result_passing_score(float $max_score, float $kkm_percentage): float
    {
        if (!is_finite($max_score) || $max_score <= 0) {
            return 0.0;
        }

        return round(max(0.0, $max_score) * (self::normalize_exam_kkm_percentage($kkm_percentage) / 100), 2);
    }

    /**
     * @return array{kkm_percentage:float,passing_score:float,is_passed:int,pass_label:string,result_tone:string}
     */
    private static function build_result_pass_meta(float $score, float $max_score, float $kkm_percentage): array
    {
        $normalized_kkm = self::normalize_exam_kkm_percentage($kkm_percentage);
        $safe_score = is_finite($score) ? round($score, 2) : 0.0;
        $safe_max_score = is_finite($max_score) ? round(max(0.0, $max_score), 2) : 0.0;
        $passing_score = self::calculate_result_passing_score($safe_max_score, $normalized_kkm);
        $is_passed = $safe_max_score > 0
            ? (($safe_score + 0.0001) >= $passing_score)
            : ($normalized_kkm <= 0.0);

        return [
            'kkm_percentage' => $normalized_kkm,
            'passing_score' => $passing_score,
            'is_passed' => $is_passed ? 1 : 0,
            'pass_label' => $is_passed ? 'LULUS' : 'TIDAK LULUS',
            'result_tone' => $is_passed ? 'pass' : 'fail',
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
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
    private static function build_attempt_review_items(array $attempt): array
    {
        global $wpdb;
        static $exam_review_cache = [];
        static $question_review_cache = [];
        static $option_review_cache = [];

        $attempt_id = (int) ($attempt['id'] ?? 0);
        $exam_id = (int) ($attempt['exam_id'] ?? 0);
        if ($attempt_id <= 0 || $exam_id <= 0) {
            return [];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        if (!array_key_exists($exam_id, $exam_review_cache)) {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, randomize_questions
                     FROM {$exam_table}
                     WHERE id = %d
                     LIMIT 1",
                    $exam_id
                ),
                ARRAY_A
            );
            if (!is_array($exam)) {
                $exam = [
                    'id' => $exam_id,
                    'randomize_questions' => 0,
                ];
            }
            $exam_review_cache[$exam_id] = $exam;
        }
        $exam = (array) $exam_review_cache[$exam_id];

        if (!array_key_exists($exam_id, $question_review_cache)) {
            $question_review_cache[$exam_id] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_text, question_type, points, correct_text, explanation, COALESCE(is_active, 1) AS is_active
                     FROM {$question_table}
                     WHERE exam_id = %d
                     ORDER BY id ASC",
                    $exam_id
                ),
                ARRAY_A
            );
        }
        $questions = is_array($question_review_cache[$exam_id]) ? (array) $question_review_cache[$exam_id] : [];

        $attempt_question_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);

        $attempt_question_order_json = wp_json_encode($attempt_question_order_ids);
        $attempt_question_order = is_string($attempt_question_order_json)
            ? $attempt_question_order_json
            : (string) ($attempt['question_order'] ?? '');
        $questions = self::append_missing_attempt_review_questions($questions, $exam_id, $attempt_question_order);
        $review_question_contract = self::resolve_attempt_question_order_contract($questions, $attempt, $exam);
        $canonical_question_order_ids = isset($review_question_contract['canonical_question_order_ids']) && is_array($review_question_contract['canonical_question_order_ids'])
            ? array_values(array_filter(array_map('intval', $review_question_contract['canonical_question_order_ids']), static function (int $question_id): bool {
                return $question_id > 0;
            }))
            : [];
        $display_number_map = isset($review_question_contract['display_number_map']) && is_array($review_question_contract['display_number_map'])
            ? $review_question_contract['display_number_map']
            : [];
        $canonical_question_order_json = wp_json_encode(!empty($canonical_question_order_ids) ? $canonical_question_order_ids : $attempt_question_order_ids);
        $questions = self::order_questions_by_attempt_sequence(
            $questions,
            is_string($canonical_question_order_json) ? $canonical_question_order_json : $attempt_question_order
        );

        $attempt_option_order_map = self::normalize_attempt_option_order_map($attempt['option_order'] ?? '');
        if (CBT_Runtime::is_ready() && (string) ($attempt['status'] ?? '') === 'in_progress') {
            $runtime_attempt_option_order_map = CBT_Runtime::get_attempt_option_order($attempt_id, $runtime_attempt_option_order_found);
            if (!$runtime_attempt_option_order_found) {
                $runtime_attempt_option_order_map = [];
            }
            $attempt_option_order_map = self::merge_attempt_option_order_maps(
                $attempt_option_order_map,
                $runtime_attempt_option_order_map
            );
        }

        if (!is_array($questions) || empty($questions)) {
            return [];
        }
        $question_ids = array_values(array_filter(array_map('intval', array_column($questions, 'id')), static function ($id): bool {
            return $id > 0;
        }));
        $submission_contexts = self::get_cached_question_submission_contexts($question_ids);

        $options_by_question = [];
        if (!empty($question_ids)) {
            if (!array_key_exists($exam_id, $option_review_cache) || !is_array($option_review_cache[$exam_id])) {
                $option_review_cache[$exam_id] = [];
            }

            $missing_option_question_ids = array_values(array_filter(array_diff(
                $question_ids,
                array_map('intval', array_keys((array) $option_review_cache[$exam_id]))
            ), static function (int $question_id): bool {
                return $question_id > 0;
            }));
            if (!empty($missing_option_question_ids)) {
                $ids_sql = implode(',', $missing_option_question_ids);
                $option_rows = $wpdb->get_results(
                    "SELECT id, question_id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id IN ({$ids_sql})
                     ORDER BY question_id ASC, id ASC",
                    ARRAY_A
                );

                foreach ((array) $option_rows as $option_row) {
                    $question_id = (int) ($option_row['question_id'] ?? 0);
                    if ($question_id <= 0) {
                        continue;
                    }

                    if (!isset($option_review_cache[$exam_id][$question_id])) {
                        $option_review_cache[$exam_id][$question_id] = [];
                    }

                    $option_review_cache[$exam_id][$question_id][] = [
                        'id' => (int) ($option_row['id'] ?? 0),
                        'question_id' => $question_id,
                        'option_key' => (string) ($option_row['option_key'] ?? ''),
                        'option_text' => (string) ($option_row['option_text'] ?? ''),
                        'is_correct' => (int) ($option_row['is_correct'] ?? 0),
                    ];
                }
            }

            foreach ($question_ids as $question_id) {
                if ($question_id <= 0) {
                    continue;
                }

                $options_by_question[$question_id] = isset($option_review_cache[$exam_id][$question_id]) && is_array($option_review_cache[$exam_id][$question_id])
                    ? array_values((array) $option_review_cache[$exam_id][$question_id])
                    : [];
            }
        }

        foreach ($options_by_question as $question_id => $option_rows) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || !isset($attempt_option_order_map[$question_id])) {
                continue;
            }

            $ordered_option_rows = self::order_question_options_by_attempt_sequence(
                is_array($option_rows) ? $option_rows : [],
                (array) $attempt_option_order_map[$question_id]
            );
            foreach ($ordered_option_rows as $option_index => $option_row) {
                $option = (array) $option_row;
                $option['option_key'] = self::build_display_option_key($option_index);
                $ordered_option_rows[$option_index] = $option;
            }
            $options_by_question[$question_id] = $ordered_option_rows;
        }

        $answer_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, selected_option_ids, answer_text, is_correct, score_awarded, answered_at, updated_at
                 FROM {$answer_table}
                 WHERE attempt_id = %d",
                $attempt_id
            ),
            ARRAY_A
        );

        $answers_by_question = [];
        foreach ((array) $answer_rows as $answer_row) {
            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $answers_by_question[$question_id] = (array) $answer_row;
        }

        $review_items = [];
        foreach ($questions as $index => $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_type = (string) ($question['question_type'] ?? '');
            $options = (array) ($options_by_question[$question_id] ?? []);
            $submission_context = is_array($submission_contexts[$question_id] ?? null) ? $submission_contexts[$question_id] : null;
            $answer_row = $answers_by_question[$question_id] ?? null;
            $is_answered = is_array($answer_row);

            $selected_option_ids = $is_answered
                ? self::decode_selected_option_ids($answer_row['selected_option_ids'] ?? null)
                : [];
            sort($selected_option_ids);

            $correct_option_ids = is_array($submission_context['correct_option_ids'] ?? null)
                ? array_values(array_unique(array_map('intval', $submission_context['correct_option_ids'])))
                : [];
            $has_submission_correct_option_ids = !empty($correct_option_ids);
            $options_with_state = [];
            foreach ($options as $option_row) {
                $option = (array) $option_row;
                $option_id = (int) ($option['id'] ?? 0);
                $is_correct_option = ((int) ($option['is_correct'] ?? 0) === 1);
                if ($is_correct_option && $option_id > 0 && !$has_submission_correct_option_ids) {
                    $correct_option_ids[] = $option_id;
                }

                $options_with_state[] = [
                    'id' => $option_id,
                    'option_key' => (string) ($option['option_key'] ?? ''),
                    'option_text' => (string) ($option['option_text'] ?? ''),
                    'is_correct' => $is_correct_option ? 1 : 0,
                    'is_selected' => in_array($option_id, $selected_option_ids, true) ? 1 : 0,
                ];
            }

            $question_detail = [];
            if (
                $question_type === 'true_false'
                && !empty($options_with_state)
                && isset($submission_context['true_false_correct_value'])
                && $submission_context['true_false_correct_value'] !== null
            ) {
                $expected_true_false = (int) $submission_context['true_false_correct_value'];
                foreach ($options_with_state as $opt_idx => $option_payload) {
                    $option_value = self::normalize_true_false_value((string) ($option_payload['option_text'] ?? ''), true);
                    if ($option_value !== null && $option_value === $expected_true_false) {
                        $option_id = (int) ($option_payload['id'] ?? 0);
                        if ($option_id > 0) {
                            $correct_option_ids[] = $option_id;
                        }
                        $options_with_state[$opt_idx]['is_correct'] = 1;
                    }
                }
            } elseif ($question_type === 'essay') {
                $question_detail = self::get_question_type_detail($question_id, $question_type);
            }
            $correct_option_ids = array_values(array_unique($correct_option_ids));
            sort($correct_option_ids);

            $is_correct = null;
            $answer_text = '';
            $score_awarded = 0.0;
            if ($is_answered) {
                if (
                    array_key_exists('is_correct', $answer_row) &&
                    $answer_row['is_correct'] !== null &&
                    $answer_row['is_correct'] !== ''
                ) {
                    $is_correct = ((int) $answer_row['is_correct'] === 1) ? 1 : 0;
                }
                $answer_text = (string) ($answer_row['answer_text'] ?? '');
                $score_awarded = (float) ($answer_row['score_awarded'] ?? 0);
            }

            if (
                $is_answered &&
                in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix'], true)
            ) {
                $answer_input_for_eval = null;
                if ($question_type === 'multiple_answer') {
                    $answer_input_for_eval = $selected_option_ids;
                } elseif ($question_type === 'true_false_matrix') {
                    $answer_input_for_eval = $answer_text;
                } elseif (!empty($selected_option_ids)) {
                    $answer_input_for_eval = (int) $selected_option_ids[0];
                } elseif (trim($answer_text) !== '') {
                    $answer_input_for_eval = $answer_text;
                }

                $deferred_eval = self::evaluate_answer_against_current_question(
                    $question,
                    $options,
                    $answer_input_for_eval,
                    $question_detail,
                    $submission_context
                );
                $evaluated_is_correct = $deferred_eval['is_correct'] ?? null;
                $is_correct = ($evaluated_is_correct === null || $evaluated_is_correct === '')
                    ? null
                    : (((int) $evaluated_is_correct === 1) ? 1 : 0);
                $score_awarded = (float) ($deferred_eval['score_awarded'] ?? 0);
            }

            $submitted_short_answers = [];
            $correct_short_answers = [];
            $short_answer_input_keys = [];
            $true_false_matrix_rows = [];
            $essay_rubric = '';
            $question_max_points = (float) ($question['points'] ?? 0);
            if ($question_type === 'short_answer') {
                $submitted_short_answers = self::extract_short_answer_submission_values($answer_text);
                $correct_short_answers = is_array($submission_context['short_answer_values'] ?? null)
                    ? array_values($submission_context['short_answer_values'])
                    : self::normalize_short_answer_values((string) ($question['correct_text'] ?? ''));
                $short_answer_input_keys = self::resolve_short_answer_input_keys(
                    (string) ($question['question_text'] ?? ''),
                    $correct_short_answers
                );
                $input_count = count($correct_short_answers);
                if ($input_count <= 0) {
                    $input_count = 1;
                }
                $question_max_points *= $input_count;
                if ($is_answered) {
                    $short_answer_eval = self::evaluate_answer_against_current_question(
                        $question,
                        [],
                        $answer_text,
                        [],
                        $submission_context
                    );
                    $evaluated_is_correct = $short_answer_eval['is_correct'] ?? null;
                    $is_correct = ($evaluated_is_correct === null || $evaluated_is_correct === '')
                        ? null
                        : (((int) $evaluated_is_correct === 1) ? 1 : 0);
                    $score_awarded = (float) ($short_answer_eval['score_awarded'] ?? 0);
                }
            } elseif ($question_type === 'true_false_matrix') {
                $matrix_config = self::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
                $matrix_items = array_map(static function (array $row, int $idx): array {
                    return [
                        'key' => (string) ($idx + 1),
                        'text' => (string) ($row['text'] ?? ''),
                        'answer' => (string) ($row['answer'] ?? 'true'),
                    ];
                }, $matrix_config, array_keys($matrix_config));
                if (isset($attempt_option_order_map[$question_id])) {
                    $matrix_items = self::order_true_false_matrix_items_by_attempt_sequence(
                        $matrix_items,
                        (array) $attempt_option_order_map[$question_id]
                    );
                }
                $submitted_matrix = self::normalize_true_false_matrix_submission($answer_text, count($matrix_items));
                foreach ($matrix_items as $idx => $row) {
                    $key = trim((string) ($row['key'] ?? ($idx + 1)));
                    if ($key === '') {
                        $key = (string) ($idx + 1);
                    }
                    $submitted_value = (string) ($submitted_matrix[$key] ?? '');
                    $correct_value = ((string) ($row['answer'] ?? 'true') === 'false') ? 'false' : 'true';
                    $true_false_matrix_rows[] = [
                        'key' => $key,
                        'text' => (string) ($row['text'] ?? ''),
                        'submitted' => ($submitted_value === 'true') ? 'Benar' : (($submitted_value === 'false') ? 'Salah' : '-'),
                        'correct' => ($correct_value === 'true') ? 'Benar' : 'Salah',
                        'is_match' => ($submitted_value !== '' && $submitted_value === $correct_value) ? 1 : 0,
                    ];
                }
            } elseif ($question_type === 'essay') {
                $essay_rubric = (string) ($question_detail['rubric_text'] ?? ($question['correct_text'] ?? ''));
            }

            $status = 'unanswered';
            if ($is_answered) {
                if ($question_type === 'essay' && self::is_essay_answer_reviewed($answer_row)) {
                    $status = 'graded';
                } elseif ($is_correct === 1) {
                    $status = 'correct';
                } elseif ($is_correct === 0) {
                    $status = 'wrong';
                } else {
                    $status = 'manual';
                }
            }

            $review_items[] = [
                'question_id' => $question_id,
                'question_number' => (int) ($display_number_map[$question_id] ?? 0),
                'question_type' => $question_type,
                'is_active' => (int) ($question['is_active'] ?? 1),
                'question_text' => (string) ($question['question_text'] ?? ''),
                'points' => $question_max_points,
                'explanation' => (string) ($question['explanation'] ?? ''),
                'status' => $status,
                'is_answered' => $is_answered ? 1 : 0,
                'is_correct' => $is_correct,
                'score_awarded' => $score_awarded,
                'selected_option_ids' => $selected_option_ids,
                'correct_option_ids' => $correct_option_ids,
                'answer_text' => $answer_text,
                'submitted_short_answers' => $submitted_short_answers,
                'correct_short_answers' => $correct_short_answers,
                'short_answer_input_keys' => $short_answer_input_keys,
                'true_false_matrix_rows' => $true_false_matrix_rows,
                'essay_rubric' => $essay_rubric,
                'options' => $options_with_state,
            ];
        }

        return $review_items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_attempt_archived_review_items(array $attempt): array
    {
        $review_items = self::build_attempt_review_items($attempt);
        if (empty($review_items)) {
            return [];
        }

        return array_values(array_filter($review_items, static function ($item_row): bool {
            $item = (array) $item_row;
            return ((int) ($item['is_active'] ?? 1) !== 1);
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function append_missing_attempt_review_questions(array $questions, int $exam_id, string $question_order_raw): array
    {
        global $wpdb;

        $question_order_ids = self::normalize_question_order_ids($question_order_raw);
        if ($exam_id <= 0 || empty($question_order_ids)) {
            return $questions;
        }

        $known_question_ids = [];
        foreach ($questions as $question_row) {
            $question_id = (int) ($question_row['id'] ?? 0);
            if ($question_id > 0) {
                $known_question_ids[$question_id] = true;
            }
        }

        $missing_question_ids = [];
        foreach ($question_order_ids as $question_id) {
            if (!isset($known_question_ids[$question_id])) {
                $missing_question_ids[] = $question_id;
            }
        }

        if (empty($missing_question_ids)) {
            return $questions;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $placeholders = implode(',', array_fill(0, count($missing_question_ids), '%d'));
        $extra_questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_text, question_type, points, correct_text, explanation, COALESCE(is_active, 1) AS is_active
                 FROM {$question_table}
                 WHERE exam_id = %d
                   AND id IN ({$placeholders})
                 ORDER BY id ASC",
                $exam_id,
                ...$missing_question_ids
            ),
            ARRAY_A
        );

        if (!is_array($extra_questions) || empty($extra_questions)) {
            return $questions;
        }

        return array_merge($questions, $extra_questions);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function order_questions_by_attempt_sequence(array $questions, string $question_order_raw): array
    {
        $question_order_ids = [];
        $decoded = json_decode($question_order_raw, true);
        if (is_array($decoded)) {
            $question_order_ids = array_values(array_filter(array_map('intval', $decoded), static function ($id): bool {
                return $id > 0;
            }));
        }

        if (empty($question_order_ids)) {
            $active_questions = array_values(array_filter($questions, static function ($question_row): bool {
                $question = (array) $question_row;
                return (int) ($question['is_active'] ?? 1) === 1;
            }));

            return !empty($active_questions) ? $active_questions : $questions;
        }

        $by_id = [];
        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $by_id[$question_id] = $question;
        }

        $ordered = [];
        foreach ($question_order_ids as $question_id) {
            if (!isset($by_id[$question_id])) {
                continue;
            }
            $ordered[] = $by_id[$question_id];
            unset($by_id[$question_id]);
        }

        if (!empty($by_id)) {
            foreach ($by_id as $remaining_question) {
                $ordered[] = $remaining_question;
            }
        }

        if (!empty($ordered)) {
            return $ordered;
        }

        $active_questions = array_values(array_filter($questions, static function ($question_row): bool {
            $question = (array) $question_row;
            return (int) ($question['is_active'] ?? 1) === 1;
        }));

        return !empty($active_questions) ? $active_questions : $questions;
    }

    /**
     * @return int[]
     */
    private static function decode_selected_option_ids($raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $raw_text = trim((string) $raw);
            if ($raw_text === '') {
                return [];
            }

            $decoded = json_decode($raw_text, true);
            if (is_array($decoded)) {
                $values = $decoded;
            } elseif (is_numeric($raw_text)) {
                $values = [(int) $raw_text];
            } else {
                return [];
            }
        }

        $ids = array_values(array_filter(array_map('intval', (array) $values), static function ($id): bool {
            return $id > 0;
        }));
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * @param array<int,array<string,mixed>> $review_items
     * @return array<string,int>
     */
    private static function summarize_review_items(array $review_items): array
    {
        $summary = [
            'total_questions' => count($review_items),
            'answered_questions' => 0,
            'correct_questions' => 0,
            'wrong_questions' => 0,
            'graded_questions' => 0,
            'manual_questions' => 0,
            'unanswered_questions' => 0,
        ];

        foreach ($review_items as $item_row) {
            $item = (array) $item_row;
            $status = (string) ($item['status'] ?? 'unanswered');
            if ($status !== 'unanswered') {
                $summary['answered_questions']++;
            }

            if ($status === 'correct') {
                $summary['correct_questions']++;
            } elseif ($status === 'wrong') {
                $summary['wrong_questions']++;
            } elseif ($status === 'graded') {
                $summary['graded_questions']++;
            } elseif ($status === 'manual') {
                $summary['manual_questions']++;
            } else {
                $summary['unanswered_questions']++;
            }
        }

        return $summary;
    }

    /**
     * Persist auto-gradable item scores after deferred mode so admin views do not stay in "manual".
     *
     * @param array<int,array<string,mixed>> $review_items
     */
    private static function sync_attempt_auto_scores(int $attempt_id, array $review_items): void
    {
        global $wpdb;

        if ($attempt_id <= 0 || empty($review_items)) {
            return;
        }

        $auto_graded_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer'];
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $now = current_time('mysql');

        foreach ($review_items as $item_row) {
            $item = (array) $item_row;
            $question_id = (int) ($item['question_id'] ?? 0);
            $question_type = (string) ($item['question_type'] ?? '');
            $is_answered = ((int) ($item['is_answered'] ?? 0) === 1);

            if (
                $question_id <= 0 ||
                !$is_answered ||
                !in_array($question_type, $auto_graded_types, true)
            ) {
                continue;
            }

            $is_correct_raw = $item['is_correct'] ?? null;
            $is_correct_value = ($is_correct_raw === null || $is_correct_raw === '')
                ? -1
                : (((int) $is_correct_raw === 1) ? 1 : 0);
            $score_awarded = max(0.0, (float) ($item['score_awarded'] ?? 0));

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$answer_table}
                     SET is_correct = NULLIF(%d, -1),
                         score_awarded = %f,
                         updated_at = %s
                     WHERE attempt_id = %d
                       AND question_id = %d",
                    $is_correct_value,
                    $score_awarded,
                    $now,
                    $attempt_id,
                    $question_id
                )
            );
        }
    }

    private static function calculate_exam_max_score(int $exam_id): float
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return 0.0;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $question_short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';

        $question_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.question_type, q.points, q.correct_text,
                        qsa.correct_text AS short_answer_correct_text
                 FROM {$question_table} q
                 LEFT JOIN {$question_short_answer_table} qsa ON qsa.question_id = q.id
                 WHERE q.exam_id = %d",
                $exam_id
            ),
            ARRAY_A
        );

        $max_score = 0.0;
        foreach ((array) $question_rows as $question_row) {
            $question = (array) $question_row;
            $question_type = (string) ($question['question_type'] ?? '');
            $base_points = max(0.0, (float) ($question['points'] ?? 0));

            if ($question_type !== 'short_answer') {
                $max_score += $base_points;
                continue;
            }

            $short_answer_raw = trim((string) ($question['short_answer_correct_text'] ?? ''));
            if ($short_answer_raw === '') {
                $short_answer_raw = (string) ($question['correct_text'] ?? '');
            }

            $input_count = count(self::normalize_short_answer_values($short_answer_raw));
            if ($input_count <= 0) {
                $input_count = 1;
            }

            $max_score += ($base_points * $input_count);
        }

        return $max_score;
    }

    private static function is_empty_answer_submission($answer_input): bool
    {
        if ($answer_input === null) {
            return true;
        }

        if (is_string($answer_input)) {
            return trim($answer_input) === '';
        }

        if (is_array($answer_input)) {
            if (empty($answer_input)) {
                return true;
            }

            foreach ($answer_input as $value) {
                if (!self::is_empty_answer_submission($value)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private static function mark_priority_window(string $source = ''): void
    {
        $seconds = (int) apply_filters('cbt_exam_priority_window_seconds', 10, $source);
        if ($seconds <= 0) {
            return;
        }
        if ($seconds > 120) {
            $seconds = 120;
        }

        $now = time();
        $target_until = $now + $seconds;
        $current_until = (int) get_transient(self::PRIORITY_WINDOW_TRANSIENT_KEY);
        if ($current_until >= $target_until) {
            return;
        }

        set_transient(
            self::PRIORITY_WINDOW_TRANSIENT_KEY,
            $target_until,
            max(30, $seconds * 3)
        );
    }

    private static function is_priority_window_active(): bool
    {
        $until = (int) get_transient(self::PRIORITY_WINDOW_TRANSIENT_KEY);
        return $until > time();
    }

    private static function should_defer_submit_scoring(): bool
    {
        $enabled = (bool) apply_filters('cbt_submit_priority_mode_enabled', true);
        if (!$enabled) {
            return false;
        }

        if (self::is_priority_window_active()) {
            return true;
        }

        $load_threshold = (float) apply_filters('cbt_submit_defer_load_threshold', 0.0);
        if ($load_threshold > 0 && function_exists('sys_getloadavg')) {
            $load_values = sys_getloadavg();
            $load_1m = is_array($load_values) ? (float) ($load_values[0] ?? 0.0) : 0.0;
            if ($load_1m >= $load_threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{selected_option_ids:?string,answer_text:?string}
     */
    private static function normalize_submission_for_storage(string $question_type, $answer_input): array
    {
        $selected_option_ids = [];
        $answer_text = null;

        if ($question_type === 'multiple_choice' || $question_type === 'true_false') {
            $selected_option_ids = self::decode_selected_option_ids($answer_input);
            if (count($selected_option_ids) > 1) {
                $selected_option_ids = [(int) $selected_option_ids[0]];
            }
        } elseif ($question_type === 'multiple_answer') {
            $selected_option_ids = self::decode_selected_option_ids($answer_input);
        } elseif ($question_type === 'true_false_matrix') {
            $normalized_matrix = self::normalize_true_false_matrix_submission($answer_input);
            if (!empty($normalized_matrix)) {
                $answer_text = wp_json_encode($normalized_matrix);
            }
        } elseif ($question_type === 'short_answer') {
            $submitted_values = self::extract_short_answer_submission_values($answer_input);
            if (count($submitted_values) > 1) {
                $encoded = wp_json_encode($submitted_values);
                if (is_string($encoded) && $encoded !== '') {
                    $answer_text = $encoded;
                }
            } else {
                $single_value = trim((string) ($submitted_values[0] ?? ''));
                if ($single_value !== '') {
                    $answer_text = $single_value;
                }
            }
        } elseif ($question_type === 'essay') {
            if (is_scalar($answer_input)) {
                $essay_text = trim((string) $answer_input);
                if ($essay_text !== '') {
                    $answer_text = $essay_text;
                }
            }
        } else {
            if (is_scalar($answer_input)) {
                $generic_text = trim((string) $answer_input);
                if ($generic_text !== '') {
                    $answer_text = $generic_text;
                }
            } elseif (is_array($answer_input)) {
                $encoded_generic = wp_json_encode($answer_input);
                if (is_string($encoded_generic) && $encoded_generic !== '' && $encoded_generic !== '[]' && $encoded_generic !== '{}') {
                    $answer_text = $encoded_generic;
                }
            }
        }

        $selected_option_ids = array_values(array_unique(array_filter(array_map('intval', $selected_option_ids), static function ($id): bool {
            return $id > 0;
        })));
        sort($selected_option_ids);

        return [
            'selected_option_ids' => !empty($selected_option_ids) ? wp_json_encode($selected_option_ids) : null,
            'answer_text' => $answer_text,
        ];
    }

    /**
     * @param array<string,mixed> $question
     * @param array<int,array<string,mixed>> $options
     * @param array<string,mixed> $question_detail
     * @return array<string,mixed>
     */
    private static function build_submission_context_from_evaluation_inputs(array $question, array $options = [], array $question_detail = []): array
    {
        $question_type = (string) ($question['question_type'] ?? '');
        $correct_option_ids = [];
        $true_false_option_value_by_id = [];

        foreach ($options as $option_row) {
            $option = (array) $option_row;
            $option_id = (int) ($option['id'] ?? 0);
            if ($option_id <= 0) {
                continue;
            }

            if ((int) ($option['is_correct'] ?? 0) === 1) {
                $correct_option_ids[] = $option_id;
            }

            if ($question_type === 'true_false') {
                $normalized_option_value = self::normalize_true_false_value((string) ($option['option_text'] ?? ''), true);
                if ($normalized_option_value !== null) {
                    $true_false_option_value_by_id[(string) $option_id] = $normalized_option_value;
                }
            }
        }

        $correct_option_ids = array_values(array_unique($correct_option_ids));
        sort($correct_option_ids);

        $true_false_correct_value = null;
        if ($question_type === 'true_false') {
            if (array_key_exists('correct_value', $question_detail) && $question_detail['correct_value'] !== null && $question_detail['correct_value'] !== '') {
                $true_false_correct_value = ((int) $question_detail['correct_value'] === 1) ? 1 : 0;
            } elseif (array_key_exists('true_false_correct_value', $question) && $question['true_false_correct_value'] !== null && $question['true_false_correct_value'] !== '') {
                $true_false_correct_value = ((int) $question['true_false_correct_value'] === 1) ? 1 : 0;
            } else {
                $legacy_true_false = self::normalize_true_false_value((string) ($question['correct_text'] ?? ''), true);
                if ($legacy_true_false !== null) {
                    $true_false_correct_value = $legacy_true_false;
                }
            }
        }

        $short_answer_values = [];
        if ($question_type === 'short_answer') {
            $short_answer_values = self::normalize_short_answer_values((string) ($question_detail['correct_text'] ?? ($question['correct_text'] ?? '')));
        }

        $true_false_matrix_answers = [];
        if ($question_type === 'true_false_matrix') {
            $matrix_items = self::normalize_true_false_matrix_config((string) ($question_detail['correct_text'] ?? ($question['correct_text'] ?? '')));
            foreach ($matrix_items as $idx => $item) {
                $true_false_matrix_answers[(string) ($idx + 1)] = ((string) ($item['answer'] ?? 'true') === 'false') ? 'false' : 'true';
            }
        }

        return [
            'id' => (int) ($question['id'] ?? 0),
            'exam_id' => (int) ($question['exam_id'] ?? 0),
            'question_type' => $question_type,
            'points' => (float) ($question['points'] ?? 0),
            'correct_option_ids' => $correct_option_ids,
            'true_false_correct_value' => $true_false_correct_value,
            'true_false_option_value_by_id' => $true_false_option_value_by_id,
            'short_answer_values' => $short_answer_values,
            'true_false_matrix_answers' => $true_false_matrix_answers,
        ];
    }

    /**
     * @param array<string,mixed> $question_context
     * @return array<string,mixed>
     */
    private static function evaluate_answer_from_submission_context(array $question_context, $answer_input): array
    {
        $type = (string) ($question_context['question_type'] ?? '');
        $points = max(0.0, (float) ($question_context['points'] ?? 0));
        $correct_option_ids = isset($question_context['correct_option_ids']) && is_array($question_context['correct_option_ids'])
            ? array_values(array_unique(array_map('intval', $question_context['correct_option_ids'])))
            : [];
        sort($correct_option_ids);

        $true_false_option_value_by_id = isset($question_context['true_false_option_value_by_id']) && is_array($question_context['true_false_option_value_by_id'])
            ? $question_context['true_false_option_value_by_id']
            : [];
        $short_answer_values = isset($question_context['short_answer_values']) && is_array($question_context['short_answer_values'])
            ? array_values($question_context['short_answer_values'])
            : [];
        $true_false_matrix_answers = isset($question_context['true_false_matrix_answers']) && is_array($question_context['true_false_matrix_answers'])
            ? $question_context['true_false_matrix_answers']
            : [];

        $selected_option_ids = [];
        $answer_text = '';
        $is_correct = null;
        $score = 0.0;

        switch ($type) {
            case 'multiple_choice':
                if (is_numeric($answer_input)) {
                    $selected_option_ids = [(int) $answer_input];
                }

                sort($selected_option_ids);
                $is_correct = ($selected_option_ids === $correct_option_ids) ? 1 : 0;
                $score = $is_correct ? $points : 0.0;
                break;

            case 'true_false':
                $selected_true_false = null;

                if (is_numeric($answer_input)) {
                    $selected_option_ids = [(int) $answer_input];
                    $selected_true_false = $true_false_option_value_by_id[(string) ((int) $answer_input)] ?? null;
                    $selected_true_false = $selected_true_false === null ? null : (int) $selected_true_false;
                } elseif (is_string($answer_input)) {
                    $selected_true_false = self::normalize_true_false_value($answer_input, true);
                    if ($selected_true_false !== null) {
                        foreach ($true_false_option_value_by_id as $option_id => $option_value) {
                            if ((int) $option_value === $selected_true_false) {
                                $selected_option_ids = [(int) $option_id];
                                break;
                            }
                        }
                    }
                }

                sort($selected_option_ids);

                $correct_true_false = array_key_exists('true_false_correct_value', $question_context)
                    && $question_context['true_false_correct_value'] !== null
                    && $question_context['true_false_correct_value'] !== ''
                    ? (((int) $question_context['true_false_correct_value'] === 1) ? 1 : 0)
                    : null;

                if ($correct_true_false === null) {
                    $is_correct = ($selected_option_ids === $correct_option_ids) ? 1 : 0;
                } else {
                    $is_correct = ($selected_true_false !== null && $selected_true_false === $correct_true_false) ? 1 : 0;
                }

                $score = $is_correct ? $points : 0.0;
                break;

            case 'multiple_answer':
                if (is_array($answer_input)) {
                    $selected_option_ids = array_map('intval', $answer_input);
                } elseif (is_string($answer_input)) {
                    $decoded = json_decode($answer_input, true);
                    if (is_array($decoded)) {
                        $selected_option_ids = array_map('intval', $decoded);
                    }
                }

                $selected_option_ids = array_values(array_unique($selected_option_ids));
                sort($selected_option_ids);

                $is_correct = ($selected_option_ids === $correct_option_ids) ? 1 : 0;
                $score = $is_correct ? $points : 0.0;
                break;

            case 'true_false_matrix':
                $submitted_map = self::normalize_true_false_matrix_submission($answer_input, count($true_false_matrix_answers));
                $answer_text = !empty($submitted_map) ? (string) wp_json_encode($submitted_map) : '';

                $total_items = count($true_false_matrix_answers);
                $matched_items = 0;
                foreach ($true_false_matrix_answers as $key => $correct_value) {
                    $submitted_value = (string) ($submitted_map[(string) $key] ?? '');
                    if ($submitted_value !== '' && $submitted_value === (string) $correct_value) {
                        $matched_items++;
                    }
                }

                $is_correct = ($total_items > 0 && $matched_items === $total_items) ? 1 : 0;
                $score = ($total_items > 0)
                    ? ($points * ((float) $matched_items / (float) $total_items))
                    : 0.0;
                if ($score < 0) {
                    $score = 0.0;
                }
                if ($score > $points) {
                    $score = $points;
                }
                break;

            case 'short_answer':
                $submitted_values = self::extract_short_answer_submission_values($answer_input);
                $correct_input_count = 0;
                $expected_input_count = count($short_answer_values);
                $max_short_answer_score = $points * max(1, $expected_input_count);

                foreach ($short_answer_values as $idx => $candidate) {
                    $submitted = (string) ($submitted_values[$idx] ?? '');
                    if (
                        self::normalize_short_answer_compare_text($submitted) ===
                        self::normalize_short_answer_compare_text((string) $candidate)
                    ) {
                        $correct_input_count++;
                    }
                }

                $is_correct = (
                    $expected_input_count > 0 &&
                    count($submitted_values) === $expected_input_count &&
                    $correct_input_count === $expected_input_count
                ) ? 1 : 0;
                $answer_text = (count($submitted_values) > 1)
                    ? (string) wp_json_encode($submitted_values)
                    : (string) ($submitted_values[0] ?? '');
                $score = min($max_short_answer_score, $points * $correct_input_count);
                break;

            case 'essay':
                $answer_text = is_scalar($answer_input) ? trim((string) $answer_input) : '';
                $is_correct = null;
                $score = 0.0;
                break;

            default:
                $answer_text = is_scalar($answer_input) ? trim((string) $answer_input) : '';
                $is_correct = 0;
                $score = 0.0;
                break;
        }

        return [
            'selected_option_ids' => !empty($selected_option_ids) ? wp_json_encode($selected_option_ids) : null,
            'answer_text' => $answer_text ?: null,
            'is_correct' => $is_correct,
            'score_awarded' => $score,
        ];
    }

    private static function evaluate_answer(array $question, array $options, $answer_input, array $question_detail = []): array
    {
        $question_context = self::build_submission_context_from_evaluation_inputs($question, $options, $question_detail);
        return self::evaluate_answer_from_submission_context($question_context, $answer_input);
    }

    public static function evaluate_answer_against_current_question(
        array $question,
        array $options,
        $answer_input,
        array $question_detail = [],
        ?array $submission_context = null
    ): array {
        if (is_array($submission_context) && !empty($submission_context)) {
            return self::evaluate_answer_from_submission_context($submission_context, $answer_input);
        }

        $question_id = (int) ($question['id'] ?? 0);
        if ($question_id > 0) {
            $cached_context = self::get_cached_question_submission_context($question_id);
            if (is_array($cached_context) && !empty($cached_context)) {
                return self::evaluate_answer_from_submission_context($cached_context, $answer_input);
            }
        }

        if (empty($question_detail)) {
            $question_type = (string) ($question['question_type'] ?? '');
            if ($question_id > 0 && $question_type !== '') {
                $question_detail = self::get_question_type_detail($question_id, $question_type);
            }
        }

        return self::evaluate_answer($question, $options, $answer_input, $question_detail);
    }

    private static function get_question_type_detail(int $question_id, string $question_type): array
    {
        global $wpdb;

        if ($question_id <= 0) {
            return [];
        }

        $table_map = [
            'multiple_choice' => $wpdb->prefix . 'cbt_question_multiple_choice',
            'multiple_answer' => $wpdb->prefix . 'cbt_question_multiple_answer',
            'true_false' => $wpdb->prefix . 'cbt_question_true_false',
            'short_answer' => $wpdb->prefix . 'cbt_question_short_answer',
            'essay' => $wpdb->prefix . 'cbt_question_essay',
        ];

        if (isset($table_map[$question_type])) {
            $detail = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_map[$question_type]} WHERE question_id = %d", $question_id),
                ARRAY_A
            );
            if (is_array($detail) && !empty($detail)) {
                return $detail;
            }
        }

        // Backward compatibility for data created before per-type detail tables.
        if ($question_type === 'short_answer') {
            $legacy_correct_text = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );
            return ['question_id' => $question_id, 'correct_text' => $legacy_correct_text];
        }

        if ($question_type === 'true_false') {
            $legacy_value = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );

            if (trim($legacy_value) === '') {
                $legacy_value = (string) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT option_text
                         FROM {$wpdb->prefix}cbt_options
                         WHERE question_id = %d AND is_correct = 1
                         ORDER BY id ASC
                         LIMIT 1",
                        $question_id
                    )
                );
            }

            $normalized = self::normalize_true_false_value($legacy_value, true);
            if ($normalized !== null) {
                return ['question_id' => $question_id, 'correct_value' => $normalized];
            }
        }

        return [];
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

    /**
     * @return array<int,array{text:string,answer:string}>
     */
    private static function normalize_true_false_matrix_config(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['statements']) && is_array($decoded['statements'])) {
                $candidates = $decoded['statements'];
            } else {
                $is_list = !empty($decoded) && array_keys($decoded) === range(0, count($decoded) - 1);
                if ($is_list) {
                    $candidates = $decoded;
                }
            }
        }

        $normalized = [];
        foreach ((array) $candidates as $candidate) {
            if (count($normalized) >= 20) {
                break;
            }

            if (!is_array($candidate)) {
                $text = CBT_Admin_Questions_Helper::sanitize_editor_html(trim((string) $candidate));
                if (!CBT_Admin_Questions_Helper::has_non_empty_html_content($text)) {
                    continue;
                }
                $normalized[] = [
                    'text' => $text,
                    'answer' => 'true',
                ];
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
            $answer_normalized = self::normalize_true_false_value($answer_raw, true);
            $normalized[] = [
                'text' => $text,
                'answer' => ($answer_normalized === 0) ? 'false' : 'true',
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string,string>
     */
    private static function normalize_true_false_matrix_submission($answer_input, int $max_items = 20): array
    {
        if (is_scalar($answer_input)) {
            $raw = trim((string) $answer_input);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalize_true_false_matrix_submission($decoded, $max_items);
            }
            return [];
        }

        if (!is_array($answer_input) || empty($answer_input)) {
            return [];
        }

        $normalized = [];
        $is_list = array_keys($answer_input) === range(0, count($answer_input) - 1);
        if ($is_list) {
            foreach ($answer_input as $idx => $value) {
                if (count($normalized) >= $max_items) {
                    break;
                }

                $bool_value = null;
                if (is_bool($value)) {
                    $bool_value = $value ? 1 : 0;
                } else {
                    $bool_value = self::normalize_true_false_value((string) $value, true);
                }
                if ($bool_value === null) {
                    continue;
                }
                $normalized[(string) ($idx + 1)] = ($bool_value === 1) ? 'true' : 'false';
            }
        } else {
            foreach ($answer_input as $key => $value) {
                if (count($normalized) >= $max_items) {
                    break;
                }

                $key_text = trim((string) $key);
                if ($key_text === '' || !preg_match('/^\d+$/', $key_text)) {
                    continue;
                }
                $key_number = (int) $key_text;
                if ($key_number <= 0) {
                    continue;
                }

                $bool_value = null;
                if (is_bool($value)) {
                    $bool_value = $value ? 1 : 0;
                } else {
                    $bool_value = self::normalize_true_false_value((string) $value, true);
                }
                if ($bool_value === null) {
                    continue;
                }
                $normalized[(string) $key_number] = ($bool_value === 1) ? 'true' : 'false';
            }
        }

        if (!empty($normalized)) {
            uksort($normalized, static function ($a, $b): int {
                return ((int) $a) <=> ((int) $b);
            });
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private static function normalize_short_answer_values(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $values = [];
        if (($raw[0] ?? '') === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $values[] = (string) $item;
                }
            }
        }

        if (empty($values)) {
            $parts = preg_split('/\|\||\r\n|\r|\n|;/', $raw);
            if (!is_array($parts) || empty($parts)) {
                $parts = [$raw];
            }
            foreach ($parts as $part) {
                if (!is_scalar($part)) {
                    continue;
                }
                $values[] = (string) $part;
            }
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = sanitize_text_field(trim((string) $value));
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
            if (count($normalized) >= 8) {
                break;
            }
        }

        return $normalized;
    }

    private static function normalize_short_answer_compare_text(string $value): string
    {
        $value = trim((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = is_string($value) ? preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $value) : '';
        return $value === null ? '' : $value;
    }

    /**
     * @return string[]
     */
    private static function extract_short_answer_submission_values($answer_input): array
    {
        if (is_scalar($answer_input)) {
            $raw = trim((string) $answer_input);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::extract_short_answer_submission_values($decoded);
            }
            return [sanitize_text_field($raw)];
        }

        if (!is_array($answer_input)) {
            return [];
        }

        $values = [];
        $ordered_keys = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        $keyed_values = [];
        $has_keyed_input = false;
        $highest_key_index = 0;
        foreach ($ordered_keys as $key) {
            $candidates = [
                $key,
                strtoupper($key),
                'input_' . $key,
                'INPUT_' . strtoupper($key),
                'jawaban_' . $key,
                'JAWABAN_' . strtoupper($key),
            ];
            foreach ($candidates as $candidate_key) {
                if (!array_key_exists($candidate_key, $answer_input)) {
                    continue;
                }
                $has_keyed_input = true;
                $key_index = (ord($key) - 96);
                if ($key_index > $highest_key_index) {
                    $highest_key_index = $key_index;
                }
                $raw = trim((string) $answer_input[$candidate_key]);
                if ($raw !== '') {
                    $keyed_values[$key_index] = sanitize_text_field($raw);
                }
                continue 2;
            }
        }

        if ($has_keyed_input) {
            $max_key_index = min(8, max($highest_key_index, count($keyed_values)));
            for ($i = 1; $i <= $max_key_index; $i++) {
                $values[] = isset($keyed_values[$i]) ? (string) $keyed_values[$i] : '';
            }

            if (!empty($values)) {
                return $values;
            }
        }

        if (!empty($values)) {
            return array_slice($values, 0, 8);
        }

        foreach ($answer_input as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $raw = trim((string) $value);
            if ($raw === '') {
                continue;
            }
            $values[] = sanitize_text_field($raw);
            if (count($values) >= 8) {
                break;
            }
        }

        return $values;
    }

    private static function normalize_frontend_question_text(string $question_text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $question_text);

        // Remove blank paragraphs often produced by editor/autop conversion.
        $normalized = (string) preg_replace('/<p\b[^>]*>\s*(?:&nbsp;|&#160;|<br(?:\s+[^>]*)?\s*\/?>)*\s*<\/p>/i', '', $normalized);

        $has_explicit_line_break_markup = preg_match('/<(?:br|p|div|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|blockquote|pre|figure|figcaption|h[1-6]|hr)\b/i', $normalized) === 1;
        if (!$has_explicit_line_break_markup) {
            // For plain text questions, compress repeated blank lines.
            $normalized = (string) preg_replace('/\n[ \t\x{00A0}]*(?:\n[ \t\x{00A0}]*)+/u', "\n", $normalized);
        }

        return $normalized;
    }

    /**
     * @param string[] $correct_values
     * @return string[]
     */
    private static function resolve_short_answer_input_keys(string $question_text, array $correct_values): array
    {
        $plain = wp_strip_all_tags($question_text);
        $keys = [];
        if (preg_match_all('/\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i', $plain, $matches)) {
            foreach ((array) ($matches[1] ?? []) as $token) {
                $token = strtoupper(trim((string) $token));
                if ($token === '') {
                    continue;
                }
                if (is_numeric($token)) {
                    $idx = (int) $token;
                    if ($idx >= 1 && $idx <= 8) {
                        $token = chr(64 + $idx);
                    }
                }
                if (!preg_match('/^[A-H]$/', $token)) {
                    continue;
                }
                if (!in_array($token, $keys, true)) {
                    $keys[] = $token;
                }
            }
        }

        if (!empty($keys)) {
            return $keys;
        }

        $count = count($correct_values);
        if ($count <= 0) {
            $count = 1;
        }
        $count = min(8, $count);
        $resolved = [];
        for ($i = 1; $i <= $count; $i++) {
            $resolved[] = chr(64 + $i);
        }
        return $resolved;
    }

    private static function normalize_kelas_code(string $value): string
    {
        return strtoupper(sanitize_text_field(trim($value)));
    }

    /**
     * @return string[]
     */
    private static function parse_exam_target_kelas(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $items = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized = self::normalize_kelas_code($part);
            if ($normalized === '') {
                continue;
            }
            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }

    private static function exam_allows_student_class(array $exam, string $student_kelas): bool
    {
        $target_kelas = self::parse_exam_target_kelas((string) ($exam['target_kelas'] ?? ''));
        if (empty($target_kelas)) {
            return true;
        }

        $student_kelas = self::normalize_kelas_code($student_kelas);
        if ($student_kelas === '') {
            return false;
        }

        return in_array($student_kelas, $target_kelas, true);
    }
}
