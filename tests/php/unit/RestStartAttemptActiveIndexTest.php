<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestStartAttemptActiveIndexTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_start_attempt_prefers_active_attempt_index_and_skips_latest_attempt_query(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Runtime::ensure_attempt_state([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-04-02 10:00:00',
            'question_order' => '[]',
            'option_order' => '',
            'extra_time_minutes' => 0,
        ], 90);
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: [
                'id' => 99,
                'status' => 'completed',
                'started_at' => '2026-04-02 08:00:00',
                'finished_at' => '2026-04-02 09:00:00',
                'question_order' => '[]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ]
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(81, $response['attempt_id']);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->attemptByIdQueryCount);
        self::assertSame(81, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_clears_stale_active_index_and_falls_back_to_completed_guard(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 91,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: [
                'id' => 92,
                'status' => 'completed',
                'started_at' => '2026-04-02 08:00:00',
                'finished_at' => '2026-04-02 09:00:00',
                'question_order' => '[]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ],
            attemptRowsById: []
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertTrue(is_wp_error($response));
        self::assertSame('attempt_already_completed', $response->get_error_code());
        self::assertSame(1, $wpdb->attemptByIdQueryCount);
        self::assertSame(3, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_returns_started_response_before_deferred_sync_runs(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('started', $response['status']);
        self::assertSame(123, $response['attempt_id']);
        self::assertSame('ready', $response['opening_state']);
        self::assertSame(3, $wpdb->latestAttemptQueryCount);
        self::assertSame(1, $wpdb->insertCalls);
        self::assertSame(123, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
        self::assertFalse(CBT_Runtime::has_attempt_state(123));
        self::assertArrayNotHasKey('cbt_attempt_session:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
        self::assertArrayNotHasKey('cbt_attempt_contract:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
        self::assertSame('ready', CBT_Start_Attempt_Opening_State_Service::get_state(7, 15)['opening_state'] ?? '');
        self::assertSame(123, (int) (CBT_Start_Attempt_Opening_State_Service::get_state(7, 15)['attempt_id'] ?? 0));

        CBT_REST::flush_deferred_start_attempt_jobs();

        self::assertArrayHasKey('cbt_attempt_session:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
        self::assertArrayHasKey('cbt_attempt_contract:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_returns_resumed_attempt_when_lock_is_busy_but_active_index_is_ready(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_acquire_lock_result'] = false;

        CBT_Runtime::ensure_attempt_state([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-04-02 10:00:00',
            'question_order' => '[]',
            'option_order' => '',
            'extra_time_minutes' => 0,
        ], 90);
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: []
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(81, $response['attempt_id']);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_resumed_attempt_from_active_index(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [
                81 => [
                    'id' => 81,
                    'exam_id' => 15,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-02 10:00:00',
                    'finished_at' => '',
                    'question_order' => '[201,202]',
                    'option_order' => '',
                    'extra_time_minutes' => 0,
                ],
            ]
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(81, $response['attempt_id']);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(1, $wpdb->attemptByIdQueryCount);
        self::assertSame(90, $response['duration_minutes']);
        self::assertSame('2026-04-02 10:00:00', $response['started_at']);
        self::assertIsString($response['server_now']);
        self::assertNotSame('', (string) $response['question_order_signature']);
        self::assertIsArray($response['question_revision']);
        self::assertFalse(CBT_Runtime::has_attempt_state(81));
        self::assertArrayNotHasKey('cbt_attempt_session:attempt:81', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
        self::assertArrayNotHasKey('cbt_attempt_contract:attempt:81', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_bypasses_gate_when_active_attempt_exists_only_in_database(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(15, 3000 + $index);
        }

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123,
            activeAttemptRow: [
                'id' => 81,
                'status' => 'in_progress',
                'started_at' => '2026-04-02 10:00:00',
                'finished_at' => '',
                'question_order' => '[]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ]
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(81, $response['attempt_id']);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_pending_for_resume_only_without_active_attempt(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('pending', $response['status']);
        self::assertSame('attempt_pending', $response['error_code']);
        self::assertSame('resume_lookup', $response['opening_state']);
        self::assertSame('resume_db_miss', $response['opening_reason']);
        self::assertGreaterThanOrEqual(0, (int) ($response['wait_age_seconds'] ?? -1));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_preserves_bootstrap_question_pending_state_from_registry(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Start_Attempt_Opening_State_Service::write_state(7, 15, 'bootstrap_questions', 'question_window_pending', [
            'attempt_id' => 88,
            'retry_after_ms' => 1100,
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('pending', $response['status']);
        self::assertSame('bootstrap_questions', $response['opening_state']);
        self::assertSame('question_window_pending', $response['opening_reason']);
        self::assertSame(88, (int) ($response['attempt_id'] ?? 0));
        self::assertSame(1100, (int) ($response['retry_after_ms'] ?? 0));

        $stored = CBT_Start_Attempt_Opening_State_Service::get_state(7, 15);
        self::assertIsArray($stored);
        self::assertSame('bootstrap_questions', $stored['opening_state']);
        self::assertSame('question_window_pending', $stored['opening_reason']);
        self::assertSame(88, (int) ($stored['attempt_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_does_not_downgrade_ready_registry_state_to_resume_lookup(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Start_Attempt_Opening_State_Service::write_state(7, 15, 'ready', 'attempt_ready', [
            'attempt_id' => 88,
            'retry_after_ms' => 0,
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('pending', $response['status']);
        self::assertSame('ready', $response['opening_state']);
        self::assertSame('attempt_ready', $response['opening_reason']);
        self::assertSame(88, (int) ($response['attempt_id'] ?? 0));

        $stored = CBT_Start_Attempt_Opening_State_Service::get_state(7, 15);
        self::assertIsArray($stored);
        self::assertSame('ready', $stored['opening_state']);
        self::assertSame('attempt_ready', $stored['opening_reason']);
        self::assertSame(88, (int) ($stored['attempt_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_resumed_attempt_from_database_when_active_index_is_missing(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            activeAttemptRow: [
                'id' => 82,
                'status' => 'in_progress',
                'started_at' => '2026-04-02 10:00:00',
                'finished_at' => '',
                'question_order' => '[301,302]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ]
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(82, $response['attempt_id']);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->attemptByIdQueryCount);
        self::assertSame(90, $response['duration_minutes']);
        self::assertNotSame('', (string) $response['question_order_signature']);
        self::assertFalse(CBT_Runtime::has_attempt_state(82));
        self::assertArrayNotHasKey('cbt_attempt_session:attempt:82', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
        self::assertArrayNotHasKey('cbt_attempt_contract:attempt:82', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_finalizing_pending_for_expired_in_progress_attempt(): void
    {
        $this->bootstrapStartAttemptScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'created_by' => 1,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 1,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            activeAttemptRow: [
                'id' => 82,
                'exam_id' => 15,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-04-02 10:00:00',
                'finished_at' => '',
                'question_order' => '[301,302]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ]
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('pending', $response['status']);
        self::assertSame('attempt_finalizing', $response['error_code']);
        self::assertSame('attempt_finalizing', $response['opening_state']);
        self::assertSame('attempt_finalizing', $response['opening_reason']);
        self::assertSame(82, (int) ($response['attempt_id'] ?? 0));
        self::assertSame(1, (int) ($response['finalize_pending'] ?? 0));
        self::assertSame(2000, (int) ($response['retry_after_ms'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_completed_when_latest_attempt_is_done(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: [
                'id' => 92,
                'status' => 'completed',
                'started_at' => '2026-04-02 08:00:00',
                'finished_at' => '2026-04-02 09:00:00',
                'question_order' => '[]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ]
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'resume_only' => 1,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('completed', $response['status']);
        self::assertSame(92, $response['attempt_id']);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_terminal_error_for_invalid_token(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => 'ABC123'];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'exam_token' => 'WRONG1',
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('terminal_error', $response['status']);
        self::assertSame('token_invalid', $response['error_code']);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_status_returns_admitted_for_queue_ticket_at_head(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartAttemptGateRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(15, 5000 + $index);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(15, 7);
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.6;

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null
        );

        $response = CBT_REST::start_attempt_status(new WP_REST_Request([
            'exam_id' => 15,
            'queue_ticket' => (string) ($queued['queue_ticket'] ?? ''),
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('admitted', $response['status']);
        self::assertSame((string) ($queued['queue_ticket'] ?? ''), (string) $response['queue_ticket']);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_uses_ready_start_snapshot_without_hydrating_full_question_payload(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 1,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123,
            startSnapshotQuestionRows: [
                ['id' => 201, 'question_type' => 'multiple_choice', 'correct_text' => ''],
            ],
            startSnapshotOptionRows: [
                ['id' => 9001, 'question_id' => 201],
                ['id' => 9002, 'question_id' => 201],
                ['id' => 9003, 'question_id' => 201],
            ]
        );

        CBT_REST::warm_exam_start_attempt_snapshot(15);

        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 1,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('started', $response['status']);
        self::assertSame(123, $response['attempt_id']);
        self::assertSame(0, $wpdb->questionQueryCount);
        self::assertSame(0, $wpdb->startSnapshotQuestionQueryCount);
        self::assertSame([201], json_decode((string) $wpdb->lastInsertData['question_order'], true));
        $optionOrder = json_decode((string) $wpdb->lastInsertData['option_order'], true);
        self::assertCount(1, $optionOrder);
        self::assertCount(3, $optionOrder['201']);
        self::assertSame(123, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    #[RunInSeparateProcess]
    public function test_get_session_works_before_deferred_attempt_session_snapshot_flush(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
                'show_student_result' => 1,
                'enable_calculator' => 1,
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $started = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($started));
        self::assertSame('started', $started['status']);
        self::assertArrayNotHasKey('cbt_attempt_session:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
                'show_student_result' => 1,
                'enable_calculator' => 1,
            ],
            latestAttemptRow: null,
            attemptRowsById: [
                123 => [
                    'id' => 123,
                    'exam_id' => 15,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-02 10:00:00',
                    'extra_time_minutes' => 0,
                    'question_order' => (string) ($wpdb->lastInsertData['question_order'] ?? '[]'),
                    'option_order' => (string) ($wpdb->lastInsertData['option_order'] ?? ''),
                    'created_by' => 0,
                    'exam_duration_minutes' => 90,
                ],
            ]
        );

        $session = CBT_REST::get_session(new WP_REST_Request([
            'attempt_id' => 123,
        ]));

        self::assertFalse(is_wp_error($session));
        self::assertTrue((bool) ($session['ok'] ?? false));
        self::assertIsArray($session['attempt_timer']);
        self::assertArrayHasKey('cbt_attempt_session:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_get_session_bootstrap_light_returns_retryable_busy_when_session_snapshot_lock_is_active(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeAttemptSessionSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_acquire_lock_result'] = false;

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
                'show_student_result' => 1,
                'enable_calculator' => 1,
            ],
            latestAttemptRow: null,
            attemptRowsById: [
                123 => [
                    'id' => 123,
                    'exam_id' => 15,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-02 10:00:00',
                    'extra_time_minutes' => 0,
                    'question_order' => '[201]',
                    'option_order' => '',
                    'created_by' => 0,
                    'exam_duration_minutes' => 90,
                ],
            ]
        );

        try {
            $session = CBT_REST::get_session(new WP_REST_Request([
                'attempt_id' => 123,
                'bootstrap_light' => 1,
            ]));
        } finally {
            unset($GLOBALS['cbt_test_acquire_lock_result']);
        }

        self::assertTrue(is_wp_error($session));
        self::assertSame('attempt_bootstrap_busy', $session->get_error_code());
        $error_data = $session->get_error_data();
        self::assertIsArray($error_data);
        self::assertSame(429, (int) ($error_data['status'] ?? 0));
        self::assertSame(1000, (int) ($error_data['retry_after_ms'] ?? 0));

        $stored = CBT_Start_Attempt_Opening_State_Service::get_state(7, 15);
        self::assertIsArray($stored);
        self::assertSame('bootstrap_session', $stored['opening_state']);
        self::assertSame('session_snapshot_pending', $stored['opening_reason']);
        self::assertSame(123, (int) ($stored['attempt_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_get_questions_bootstrap_initializes_runtime_state_lazily_after_start_response(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $started = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($started));
        self::assertSame('started', $started['status']);
        self::assertFalse(CBT_Runtime::has_attempt_state(123));

        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [
                123 => [
                    'id' => 123,
                    'exam_id' => 15,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-02 10:00:00',
                    'extra_time_minutes' => 0,
                    'question_order' => (string) ($wpdb->lastInsertData['question_order'] ?? '[]'),
                    'option_order' => (string) ($wpdb->lastInsertData['option_order'] ?? ''),
                ],
            ]
        );

        $questions = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 15,
            'attempt_id' => 123,
        ]));

        self::assertFalse(is_wp_error($questions));
        $questionPayload = $questions instanceof WP_REST_Response ? $questions->get_data() : (array) $questions;
        self::assertArrayHasKey('items', $questionPayload);
        self::assertTrue(CBT_Runtime::has_attempt_state(123));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_returns_queued_payload_when_gate_capacity_is_exhausted(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(15, 1000 + $index);
        }

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('queued', $response['status']);
        self::assertNotSame('', (string) $response['queue_ticket']);
        self::assertSame(1, $response['queue_position']);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_with_queue_ticket_is_admitted_when_ticket_reaches_head(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(15, 2000 + $index);
        }

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $queued = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.6;

        $started = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'queue_ticket' => (string) ($queued['queue_ticket'] ?? ''),
        ]));

        self::assertFalse(is_wp_error($queued));
        self::assertSame('queued', $queued['status']);
        self::assertFalse(is_wp_error($started));
        self::assertSame('started', $started['status']);
        self::assertSame(123, $started['attempt_id']);
        self::assertSame(4, $wpdb->latestAttemptQueryCount);
        self::assertSame(1, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_returns_resumed_attempt_when_lock_is_busy_and_active_attempt_appears_in_database(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_acquire_lock_result'] = false;

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            activeAttemptRowsSequence: [
                null,
                [
                    'id' => 83,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-02 10:00:00',
                    'finished_at' => '',
                    'question_order' => '[]',
                    'option_order' => '',
                    'extra_time_minutes' => 0,
                ],
            ]
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(83, $response['attempt_id']);
        self::assertSame(2, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_replays_started_payload_for_same_idempotency_key(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $started = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'start-shell-1',
        ]));
        $replayed = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'start-shell-1',
        ]));

        self::assertFalse(is_wp_error($started));
        self::assertFalse(is_wp_error($replayed));
        self::assertSame('started', $started['status']);
        self::assertSame('started', $replayed['status']);
        self::assertSame(123, $started['attempt_id']);
        self::assertSame(123, $replayed['attempt_id']);
        self::assertSame(3, $wpdb->latestAttemptQueryCount);
        self::assertSame(1, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_replays_resumed_payload_for_same_idempotency_key(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Runtime::ensure_attempt_state([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-04-02 10:00:00',
            'question_order' => '[]',
            'option_order' => '',
            'extra_time_minutes' => 0,
        ], 90);
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: []
        );

        $resumed = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'resume-shell-1',
        ]));
        $replayed = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'resume-shell-1',
        ]));

        self::assertFalse(is_wp_error($resumed));
        self::assertFalse(is_wp_error($replayed));
        self::assertSame('resumed', $resumed['status']);
        self::assertSame('resumed', $replayed['status']);
        self::assertSame(81, $resumed['attempt_id']);
        self::assertSame(81, $replayed['attempt_id']);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_replays_queued_payload_for_same_idempotency_key(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(15, 1000 + $index);
        }

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $queued = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'queue-shell-1',
        ]));
        $replayed = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'queue-shell-1',
        ]));

        self::assertFalse(is_wp_error($queued));
        self::assertFalse(is_wp_error($replayed));
        self::assertSame('queued', $queued['status']);
        self::assertSame('queued', $replayed['status']);
        self::assertSame((string) $queued['queue_ticket'], (string) $replayed['queue_ticket']);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_returns_retryable_error_while_same_idempotency_key_is_processing(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        $claimResult = CBT_Start_Attempt_Idempotency_Service::begin(7, 15, 'processing-shell-1');
        self::assertSame('claimed', $claimResult['mode']);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
            'idempotency_key' => 'processing-shell-1',
        ]));

        self::assertTrue(is_wp_error($response));
        self::assertSame('attempt_lock_active', $response->get_error_code());
        self::assertSame(0, $wpdb->insertCalls);

        CBT_Start_Attempt_Idempotency_Service::abandon((array) ($claimResult['claim'] ?? []));
    }

    private function bootstrapStartAttemptScaffold(): void
    {
        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function current_user_id(\WP_REST_Request $request): int
    {
        return (int) ($GLOBALS['cbt_test_rest_auth_user_id'] ?? 0);
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return (string) ($GLOBALS['cbt_test_rest_auth_role'] ?? 'student');
    }

    public static function normalize_exam_token_input(string $token): string
    {
        return strtoupper(trim($token));
    }

    public static function get_global_exam_token(bool $auto_rotate = true): array
    {
        $meta = $GLOBALS['cbt_test_global_exam_token_meta'] ?? [];
        return is_array($meta) ? $meta : ['token' => ''];
    }

    public static function is_frontend_auto_exam_token_enabled(): bool
    {
        return false;
    }
}
PHP);
        }

        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    private static array $memory = [];
    private static array $locks = [];

    public static function acquire_lock(string $key, int $ttl, array $context = []): bool
    {
        if (array_key_exists('cbt_test_acquire_lock_result', $GLOBALS)) {
            return (bool) $GLOBALS['cbt_test_acquire_lock_result'];
        }

        $now = time();
        if (isset(self::$locks[$key]) && (int) self::$locks[$key] > $now) {
            return false;
        }

        self::$locks[$key] = $now + max(1, $ttl);
        return true;
    }

    public static function release_lock(string $key): void
    {
        unset(self::$locks[$key]);
    }

    public static function invalidate_user(int $user_id): void
    {
    }

    public static function invalidate_attempt(int $attempt_id): void
    {
    }

    public static function get(string $key, array $namespaces = [], ?bool &$found = null)
    {
        $found = false;
        if (!isset(self::$memory[$key])) {
            return null;
        }

        $entry = self::$memory[$key];
        if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) < time()) {
            unset(self::$memory[$key]);
            return null;
        }

        $found = true;
        return $entry['value'] ?? null;
    }

    public static function set(string $key, $value, int $ttl, array $namespaces = []): bool
    {
        self::$memory[$key] = [
            'value' => $value,
            'expires_at' => time() + max(1, $ttl),
        ];
        return true;
    }

    public static function delete(string $key, array $namespaces = []): bool
    {
        unset(self::$memory[$key]);
        return true;
    }

    public static function remember(string $key, int $ttl, array $namespaces, callable $producer)
    {
        return $producer();
    }

    public static function namespace_exam(int $exam_id): string
    {
        return 'exam:' . $exam_id;
    }

    public static function get_exam_revision_meta(int $exam_id): array
    {
        return [
            'revision' => 1,
            'updated_at' => '2026-04-02 09:00:00',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-gate-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-idempotency-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-opening-state-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function useFakeRuntimeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Start_Attempt_Runtime_Redis_Client());

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeActiveAttemptRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);

        $redisProperty = $reflection->getProperty('active_attempt_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('active_attempt_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('active_attempt_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeStartSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Start_Attempt_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('start_snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('start_snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('start_snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAttemptSessionSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Session_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAttemptContractSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Question_Contract_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeStartAttemptGateRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);

        $redisProperty = $reflection->getProperty('gate_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('gate_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('gate_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}

final class RestStartAttemptActiveIndexFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public int $examQueryCount = 0;
    public int $latestAttemptQueryCount = 0;
    public int $attemptByIdQueryCount = 0;
    public int $questionQueryCount = 0;
    public int $startSnapshotQuestionQueryCount = 0;
    public int $startSnapshotOptionQueryCount = 0;
    public int $insertCalls = 0;
    /** @var array<string,mixed> */
    public array $lastInsertData = [];

    /** @var array<string,mixed>|null */
    private ?array $examRow;

    /** @var array<string,mixed>|null */
    private ?array $latestAttemptRow;

    /** @var array<string,mixed>|null */
    private ?array $activeAttemptRow;

    /** @var array<int,array<string,mixed>|null> */
    private array $activeAttemptRowsSequence;

    /** @var array<int,array<string,mixed>> */
    private array $attemptRowsById;

    /** @var array<int,array<string,mixed>> */
    private array $startSnapshotQuestionRows;

    /** @var array<int,array<string,mixed>> */
    private array $startSnapshotOptionRows;

    /**
     * @param array<string,mixed>|null $examRow
     * @param array<string,mixed>|null $latestAttemptRow
     * @param array<int,array<string,mixed>> $attemptRowsById
     * @param array<int,array<string,mixed>> $startSnapshotQuestionRows
     * @param array<int,array<string,mixed>> $startSnapshotOptionRows
     * @param array<string,mixed>|null $activeAttemptRow
     * @param array<int,array<string,mixed>|null> $activeAttemptRowsSequence
     */
    public function __construct(
        ?array $examRow,
        ?array $latestAttemptRow,
        array $attemptRowsById = [],
        int $insertId = 123,
        array $startSnapshotQuestionRows = [],
        array $startSnapshotOptionRows = [],
        ?array $activeAttemptRow = null,
        array $activeAttemptRowsSequence = []
    )
    {
        $this->examRow = $examRow;
        $this->latestAttemptRow = $latestAttemptRow;
        $this->attemptRowsById = $attemptRowsById;
        $this->insert_id = $insertId;
        $this->startSnapshotQuestionRows = $startSnapshotQuestionRows;
        $this->startSnapshotOptionRows = $startSnapshotOptionRows;
        $this->activeAttemptRow = $activeAttemptRow;
        $this->activeAttemptRowsSequence = $activeAttemptRowsSequence;
    }

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null): ?array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (str_contains($query, 'FROM wp_cbt_exams')) {
            $this->examQueryCount++;
            return $this->examRow;
        }

        if (str_contains($query, "WHERE exam_id = %d AND student_id = %d AND status = 'in_progress'")) {
            $this->latestAttemptQueryCount++;
            if (!empty($this->activeAttemptRowsSequence)) {
                return array_shift($this->activeAttemptRowsSequence);
            }

            return $this->activeAttemptRow;
        }

        if (str_contains($query, "WHERE exam_id = %d AND student_id = %d AND status IN ('in_progress', 'completed')")) {
            $this->latestAttemptQueryCount++;
            return $this->latestAttemptRow;
        }

        if (
            str_contains($query, 'FROM wp_cbt_attempts')
            && (str_contains($query, 'WHERE id = %d') || str_contains($query, 'WHERE a.id = %d'))
        ) {
            $this->attemptByIdQueryCount++;
            $attemptId = isset($args[0]) ? (int) $args[0] : 0;
            return $this->attemptRowsById[$attemptId] ?? null;
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (str_contains($query, 'SELECT q.id, q.question_type, q.correct_text')) {
            $this->startSnapshotQuestionQueryCount++;
            return $this->startSnapshotQuestionRows;
        }

        if (str_contains($query, 'SELECT id, question_id') && str_contains($query, 'FROM wp_cbt_options')) {
            $this->startSnapshotOptionQueryCount++;
            return $this->startSnapshotOptionRows;
        }

        if (str_contains($query, 'FROM wp_cbt_questions q')) {
            $this->questionQueryCount++;
            return [];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string>|null $format
     */
    public function insert(string $table, array $data, ?array $format = null): bool
    {
        $this->insertCalls++;
        $this->lastInsertData = $data;
        return true;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (
            str_contains($query, 'FROM wp_cbt_attempts a')
            && str_contains($query, 'INNER JOIN wp_cbt_exams e')
        ) {
            return '82';
        }

        return null;
    }
}

if (!class_exists('CBT_Start_Attempt_Runtime_Redis_Client')) {
    class CBT_Start_Attempt_Runtime_Redis_Client extends CBT_Test_Redis_Client
    {
        /** @var array<string,array<string,string>> */
        private array $hashStorage = [];

        /** @var array<string,array<string,int>> */
        private array $zsetStorage = [];

        public function exists($key, ...$other_keys): int
        {
            $keys = array_merge([(string) $key], array_map('strval', $other_keys));
            foreach ($keys as $safeKey) {
                if (array_key_exists($safeKey, $GLOBALS['cbt_test_redis_storage'])) {
                    return 1;
                }

                if (isset($this->hashStorage[$safeKey]) || isset($this->zsetStorage[$safeKey])) {
                    return 1;
                }
            }

            return 0;
        }

        public function hLen($key): int
        {
            return count($this->hashStorage[(string) $key] ?? []);
        }

        public function hMSet($key, $pairs): bool
        {
            $key = (string) $key;
            if (!isset($this->hashStorage[$key])) {
                $this->hashStorage[$key] = [];
            }

            foreach ((array) $pairs as $field => $value) {
                $this->hashStorage[$key][(string) $field] = (string) $value;
            }

            return true;
        }

        public function hSet($key, $field, $value): bool
        {
            $key = (string) $key;
            if (!isset($this->hashStorage[$key])) {
                $this->hashStorage[$key] = [];
            }

            $this->hashStorage[$key][(string) $field] = (string) $value;
            return true;
        }

        public function hDel($key, ...$fields): int
        {
            $key = (string) $key;
            $deleted = 0;
            foreach ($fields as $field) {
                $safeField = (string) $field;
                if (isset($this->hashStorage[$key][$safeField])) {
                    unset($this->hashStorage[$key][$safeField]);
                    $deleted++;
                }
            }

            return $deleted;
        }

        public function hGetAll($key): array
        {
            return $this->hashStorage[(string) $key] ?? [];
        }

        public function hMGet($key, $fields): array
        {
            $key = (string) $key;
            $items = [];
            foreach ((array) $fields as $field) {
                $items[(string) $field] = $this->hashStorage[$key][(string) $field] ?? false;
            }

            return $items;
        }

        public function zAdd($key, $score, $member, ...$extra_args): int
        {
            $key = (string) $key;
            if (!isset($this->zsetStorage[$key])) {
                $this->zsetStorage[$key] = [];
            }

            $this->zsetStorage[$key][(string) $member] = (int) $score;
            return 1;
        }

        public function zCard($key): int
        {
            return count($this->zsetStorage[(string) $key] ?? []);
        }

        public function zRange($key, $start, $end, $scores = false): array
        {
            $items = array_keys($this->zsetStorage[(string) $key] ?? []);
            sort($items);
            return $items;
        }

        public function zRangeByScore($key, $min, $max, $options = []): array
        {
            $items = [];
            foreach (($this->zsetStorage[(string) $key] ?? []) as $member => $score) {
                if ((int) $score <= (int) $max) {
                    $items[] = (string) $member;
                }
            }

            return $items;
        }

        public function zRem($key, ...$members): int
        {
            $key = (string) $key;
            $deleted = 0;
            foreach ($members as $member) {
                $safeMember = (string) $member;
                if (isset($this->zsetStorage[$key][$safeMember])) {
                    unset($this->zsetStorage[$key][$safeMember]);
                    $deleted++;
                }
            }

            return $deleted;
        }
    }
}
