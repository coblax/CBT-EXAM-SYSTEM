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

if (!class_exists('CBT_Start_Attempt_Gate_Service')) {
    require_once __DIR__ . '/class-cbt-start-attempt-gate-service.php';
}

if (!class_exists('CBT_Start_Attempt_Opening_State_Service')) {
    require_once __DIR__ . '/class-cbt-start-attempt-opening-state-service.php';
}

if (!class_exists('CBT_Entry_Flow_Metrics_Service')) {
    require_once __DIR__ . '/class-cbt-entry-flow-metrics-service.php';
}

if (!class_exists('CBT_Submit_Flow_Metrics_Service')) {
    require_once __DIR__ . '/class-cbt-submit-flow-metrics-service.php';
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

if (!class_exists('CBT_Supervisor_Dashboard_Service')) {
    require_once __DIR__ . '/class-cbt-supervisor-dashboard-service.php';
}

if (!trait_exists('CBT_REST_Login_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-login.php';
}

if (!trait_exists('CBT_REST_Submit_Answer_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-submit-answer.php';
}

if (!trait_exists('CBT_REST_Security_Events_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-security-events.php';
}

if (!trait_exists('CBT_REST_Scoring_Helpers')) {
    require_once __DIR__ . '/class-cbt-rest-scoring.php';
}

if (!trait_exists('CBT_REST_Session_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-session.php';
}

if (!trait_exists('CBT_REST_Exam_Questions_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-exam-questions.php';
}

if (!trait_exists('CBT_REST_Exam_Availability_Helpers')) {
    require_once __DIR__ . '/class-cbt-rest-exam-availability.php';
}

if (!trait_exists('CBT_REST_Finish_Exam_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-finish-exam.php';
}

if (!trait_exists('CBT_REST_Shared_Helpers')) {
    require_once __DIR__ . '/class-cbt-rest-shared.php';
}

if (!trait_exists('CBT_REST_Question_Snapshot_Helpers')) {
    require_once __DIR__ . '/class-cbt-rest-question-snapshots.php';
}

if (!trait_exists('CBT_REST_Start_Attempt_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-start-attempt.php';
}

if (!trait_exists('CBT_REST_Supervisor_Routes')) {
    require_once __DIR__ . '/class-cbt-rest-supervisor.php';
}

class CBT_REST
{
    use CBT_REST_Exam_Availability_Helpers;
    use CBT_REST_Exam_Questions_Routes;
    use CBT_REST_Finish_Exam_Routes;
    use CBT_REST_Login_Routes;
    use CBT_REST_Scoring_Helpers;
    use CBT_REST_Security_Events_Routes;
    use CBT_REST_Session_Routes;
    use CBT_REST_Shared_Helpers;
    use CBT_REST_Question_Snapshot_Helpers;
    use CBT_REST_Start_Attempt_Routes;
    use CBT_REST_Submit_Answer_Routes;
    use CBT_REST_Supervisor_Routes;

    private const PRIORITY_WINDOW_TRANSIENT_KEY = 'cbt_exam_priority_window_until';
    private const AVAILABILITY_BASE_CATALOG_TTL = 900;
    /** @var array<int,array{phase:string,exam_id:int,user_id:int,context:array<string,mixed>,job:callable}> */
    private static array $deferred_start_attempt_jobs = [];
    private static bool $deferred_start_attempt_shutdown_registered = false;
    private static bool $deferred_start_attempt_jobs_flushing = false;
    private static bool $deferred_start_attempt_response_finished = false;

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
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cbt/v1', '/supervisor_dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'supervisor_dashboard'],
            'permission_callback' => [CBT_Auth::class, 'permission_supervisor_dashboard'],
            'args' => [
                'tab' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'exam_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'kelas' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'ruang' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'student_keyword' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'roster_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'attempts_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'security_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'security_severity' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'security_event_type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'security_device_type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'attendance_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'attendance_status' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/supervisor_reset_login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'supervisor_reset_login'],
            'permission_callback' => [CBT_Auth::class, 'permission_supervisor_dashboard'],
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
                'bootstrap_light' => [
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
                'bootstrap_light' => [
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

        register_rest_route('cbt/v1', '/start_attempt_status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'start_attempt_status'],
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
                'queue_ticket' => [
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

        register_rest_route('cbt/v1', '/entry_flow_metric', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'entry_flow_metric'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
        ]);

        register_rest_route('cbt/v1', '/submit_flow_metric', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'submit_flow_metric'],
            'permission_callback' => [CBT_Auth::class, 'permission_teacher_or_student'],
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

        register_rest_route('cbt/v1', '/security_observability_snapshot', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'security_observability_snapshot'],
            'permission_callback' => [self::class, 'permission_manage_security_admin'],
            'args' => [
                'micro_drain' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/security_logs_page', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'security_logs_page'],
            'permission_callback' => [self::class, 'permission_manage_security_admin'],
            'args' => [
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'severity' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'event_type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'device_type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'kelas' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'ruang' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'student_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('cbt/v1', '/security_ingest_admin_action', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'security_ingest_admin_action'],
            'permission_callback' => [self::class, 'permission_manage_security_admin'],
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


}
