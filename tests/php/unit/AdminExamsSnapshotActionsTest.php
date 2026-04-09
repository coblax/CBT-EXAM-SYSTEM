<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
final class AdminExamsSnapshotActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        cbt_test_register_user([
            'ID' => 71,
            'display_name' => 'Salsa',
            'user_login' => 'salsa',
            'user_email' => 'salsa@example.com',
            'user_pass' => 'secret',
            'roles' => ['student'],
        ]);
        update_user_meta(71, 'kode_kelas', 'XI-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        update_user_meta(71, 'agama', 'Islam');
        update_user_meta(71, 'jenis_kelamin', 'Perempuan');
        update_user_meta(71, 'nisn', '71001');
        cbt_test_register_user([
            'ID' => 72,
            'display_name' => 'Bimo',
            'user_login' => 'bimo',
            'user_email' => 'bimo@example.com',
            'user_pass' => 'secret-2',
            'roles' => ['student'],
        ]);
        update_user_meta(72, 'kode_kelas', 'XI-B');
        update_user_meta(72, 'kode_ruang', 'R2');
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_exam_delivery_snapshot_redirects_back_to_snapshot_tab(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
        ];

        try {
            CBT_Admin_Exams_Service::handle_warm_exam_delivery_snapshot();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([77], CBT_REST::$warmedExamIds);
        self::assertSame([77], CBT_REST::$warmedStartExamIds);
        self::assertStringContainsString('page=cbt-exams', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_status=published', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+exam+%2377+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_bulk_exam_delivery_snapshots_warms_filtered_exams_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
        ];

        try {
            CBT_Admin_Exams_Service::handle_warm_bulk_exam_delivery_snapshots();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([77, 54], CBT_REST::$warmedExamIds);
        self::assertSame([77, 54], CBT_REST::$warmedStartExamIds);
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_status=published', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+menyiapkan+2+snapshot+exam.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clear_exam_delivery_snapshot_removes_snapshot_and_redirects_back(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 977,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [977],
                'question_number_map' => [977 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_page_77' => '3',
            'cbt_exam_readiness_paged' => '2',
        ];

        try {
            CBT_Admin_Exams_Service::handle_clear_exam_delivery_snapshot();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+exam+%2377+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clear_bulk_exam_delivery_snapshots_clears_filtered_exams_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 977,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(54, static function (int $examId): array {
            return [
                [
                    'id' => 954,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [977],
                'question_number_map' => [977 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(54, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [954],
                'question_number_map' => [954 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        $_POST = [
            'cbt_exam_status' => 'published',
        ];

        try {
            CBT_Admin_Exams_Service::handle_clear_bulk_exam_delivery_snapshots();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedExamSnapshotKeysFor(54));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(54));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+membersihkan+snapshot+exam+untuk+2+exam.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_refresh_attempt_runtime_snapshot_rebuilds_attempt_snapshots_and_preserves_runtime_tab_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeAttemptSessionRedis();
        $this->useFakeAttemptContractRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();

        CBT_Student_Profile_Cache::warm_snapshot(71);
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'action_test');
        CBT_Exam_Availability_Cache::warm_student_snapshot(71, static function (): array {
            return [
                'items' => [
                    ['id' => 77, 'title' => 'Ujian Matematika'],
                ],
                'current_user' => [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        $_POST = [
            'attempt_id' => '501',
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'session_runtime_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_page_77' => '3',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_refresh_attempt_runtime_snapshot']);

        self::assertSame('ready', CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics(501)['snapshot_status']);
        self::assertSame('ready', CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(501)['snapshot_status']);
        self::assertTrue(CBT_Student_Profile_Cache::get_snapshot_diagnostics(71)['snapshot_exists']);
        self::assertTrue(CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(71)['snapshot_exists']);
        self::assertTrue(CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(71)['snapshot_exists']);
        self::assertStringContainsString('cbt_exam_snapshot_tab=session_runtime_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Runtime+snapshot+attempt+%23501+berhasil+direfresh.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_exam_submission_context_snapshot_redirects_back_to_submission_tab(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_exam_submission_context_snapshot']);

        self::assertSame([77], CBT_REST::$warmedSubmissionContextExamIds);
        self::assertNotSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertStringContainsString('cbt_exam_snapshot_tab=submission_context_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Submission+context+exam+%2377+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_exam_submission_context_snapshot']);

        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertStringContainsString('cbt_exam_snapshot_tab=submission_context_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Submission+context+exam+%2377+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_bulk_exam_submission_context_snapshots_redirects_back_to_submission_tab(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_bulk_exam_submission_context_snapshots']);

        self::assertSame([77, 54], CBT_REST::$warmedSubmissionContextExamIds);
        self::assertNotSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertNotSame([], $this->storedSubmissionContextKeysFor(54));
        self::assertStringContainsString('cbt_exam_snapshot_tab=submission_context_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+menyiapkan+submission+context+untuk+2+exam.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_bulk_exam_submission_context_snapshots']);

        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(54));
        self::assertStringContainsString('cbt_exam_snapshot_tab=submission_context_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+membersihkan+submission+context+untuk+2+exam.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_student_exam_availability_snapshot_redirects_back_with_student_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeAvailabilityRedis();

        $_POST = [
            'user_id' => '71',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'exam_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_student_exam_availability_snapshot']);

        self::assertNotSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_tab=exam_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+ketersediaan+siswa+%2371+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'user_id' => '71',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'exam_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_student_exam_availability_snapshot']);

        self::assertSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertStringContainsString('cbt_exam_snapshot_tab=exam_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+ketersediaan+siswa+%2371+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_start_and_stop_exam_availability_auto_warm_redirects_back_with_snapshot_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeAvailabilityRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_start_exam_availability_auto_warm']);

        $state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertTrue($state['active']);
        self::assertSame(77, $state['exam_id']);
        self::assertGreaterThan(0, $state['prepared_count']);
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_stop_exam_availability_auto_warm']);

        $state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertFalse($state['active']);
        self::assertSame('stopped', $state['status']);
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Auto-warm+availability+dihentikan+manual.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_start_exam_preflight_redirects_back_with_snapshot_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_exam_snapshot_tab' => 'preflight',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_start_exam_preflight']);

        $state = CBT_Exam_Preflight_Service::get_state();
        self::assertSame(77, $state['exam_id']);
        self::assertSame('completed', $state['status']);
        self::assertTrue($state['question_snapshot_ready']);
        self::assertTrue($state['start_snapshot_ready']);
        self::assertTrue($state['submission_context_ready']);
        self::assertTrue($state['auto_warm_started']);
        self::assertSame('ready', $state['stage_submission_context']);
        self::assertSame(1, $state['profile_success_count']);
        self::assertSame(1, $state['login_snapshot_success_count']);
        self::assertSame('ready', $state['stage_login_snapshot']);
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=One-click+pra+ujian+selesai.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_start_bulk_exam_preflight_starts_first_exam_and_queues_second_exam(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        $GLOBALS['cbt_test_preflight_initial_burst_seconds'] = 60.0;
        $GLOBALS['cbt_test_preflight_initial_burst_max_batches'] = 1;
        $GLOBALS['cbt_test_availability_initial_burst_seconds'] = 60.0;
        $GLOBALS['cbt_test_availability_initial_burst_max_batches'] = 1;
        $this->registerAdditionalXiAStudents(220);
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'preflight',
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_page_77' => '3',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_start_bulk_exam_preflight']);

        $jobs = CBT_Exam_Preflight_Service::get_jobs_state();
        self::assertSame('active', $jobs[77]['status']);
        self::assertSame('queued', $jobs[54]['status']);

        $runner = CBT_Exam_Preflight_Service::get_global_runner_state();
        self::assertSame(77, $runner['active_exam_id']);
        self::assertSame([54], $runner['queue_exam_ids']);

        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B0%5D=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B1%5D=54', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Bulk+one-click+memproses+2+exam%3A+aktif+1%2C+antre+1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_start_bulk_exam_preflight_rejects_more_than_ten_selected_exams(): void
    {
        $this->bootstrapSnapshotActionScaffold();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'preflight',
            'cbt_exam_snapshot_exam_ids' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11'],
            'cbt_exam_snapshot_exam_id' => '1',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_start_bulk_exam_preflight']);

        self::assertSame([], CBT_Exam_Preflight_Service::get_jobs_state());
        self::assertStringContainsString('cbt_err=Bulk+One-Click+dibatasi+maksimal+10+exam+per+run.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_start_bulk_exam_preflight_continues_when_one_exam_fails_eligibility(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsBulkFailureFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'preflight',
            'cbt_exam_snapshot_exam_ids' => ['88', '54'],
            'cbt_exam_snapshot_exam_id' => '88',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_start_bulk_exam_preflight']);

        $jobs = CBT_Exam_Preflight_Service::get_jobs_state();
        self::assertSame('failed', $jobs[88]['status']);
        self::assertSame('completed', $jobs[54]['status']);
        self::assertStringContainsString('cbt_msg=Bulk+one-click+memproses+2+exam', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('gagal+1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clean_bulk_exam_snapshots_clears_selected_exam_snapshots_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 900 + $examId,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(54, static function (int $examId): array {
            return [
                [
                    'id' => 900 + $examId,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [900 + $examId],
                'question_number_map' => [900 + $examId => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(54, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [900 + $examId],
                'question_number_map' => [900 + $examId => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(54);

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'preflight',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
            'cbt_exam_readiness_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clean_bulk_exam_snapshots']);

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedExamSnapshotKeysFor(54));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(54));
        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(54));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B0%5D=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B1%5D=54', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Bulk+clean+snapshot+memproses+2+exam%3A+berhasil+2%2C+gagal+0.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clean_exam_snapshots_stops_same_exam_auto_warm_and_clears_target_snapshot_stack(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 900 + $examId,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [900 + $examId],
                'question_number_map' => [900 + $examId => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);
        CBT_Student_Profile_Cache::warm_snapshot(71);
        CBT_Student_Profile_Cache::warm_snapshot(72);
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'test');
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(72, 'test');
        CBT_Exam_Availability_Cache::write_prepared_student_snapshot(71, CBT_REST::build_student_exam_availability_snapshot_payload(71));
        CBT_Exam_Availability_Cache::write_prepared_student_snapshot(72, CBT_REST::build_student_exam_availability_snapshot_payload(72));

        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clean_exam_snapshots']);

        self::assertFalse(CBT_Exam_Availability_Auto_Warm_Service::get_state()['active']);
        self::assertSame('inactive', CBT_Exam_Availability_Auto_Warm_Service::get_state()['status']);
        self::assertSame(0, CBT_Exam_Availability_Auto_Warm_Service::get_state()['exam_id']);
        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(71));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertNotSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(72));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(72));
        self::assertNotSame([], $this->storedAvailabilitySnapshotKeysFor(72));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+pra+ujian+untuk+Ujian+Matematika+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clean_exam_snapshots_stops_same_exam_preflight_and_clears_target_snapshot_stack(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 900 + $examId,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [900 + $examId],
                'question_number_map' => [900 + $examId => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);
        CBT_Student_Profile_Cache::warm_snapshot(71);
        CBT_Student_Profile_Cache::warm_snapshot(72);
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'test');
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(72, 'test');
        CBT_Exam_Availability_Cache::write_prepared_student_snapshot(71, CBT_REST::build_student_exam_availability_snapshot_payload(71));
        CBT_Exam_Availability_Cache::write_prepared_student_snapshot(72, CBT_REST::build_student_exam_availability_snapshot_payload(72));

        $this->seedActivePreflightState(77, 'Ujian Matematika', [71]);

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clean_exam_snapshots']);

        self::assertFalse(CBT_Exam_Preflight_Service::get_state()['active']);
        self::assertSame('inactive', CBT_Exam_Preflight_Service::get_state()['status']);
        self::assertSame(0, CBT_Exam_Preflight_Service::get_state()['exam_id']);
        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(71));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertNotSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(72));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(72));
        self::assertNotSame([], $this->storedAvailabilitySnapshotKeysFor(72));
        self::assertStringContainsString('cbt_exam_readiness_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+pra+ujian+untuk+Ujian+Matematika+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clean_exam_snapshots_keeps_other_exam_preflight_active(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 900 + $examId,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [900 + $examId],
                'question_number_map' => [900 + $examId => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);
        $this->seedActivePreflightState(54, 'Ujian Biologi', [72]);

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clean_exam_snapshots']);

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertTrue(CBT_Exam_Preflight_Service::get_state()['active']);
        self::assertSame(54, CBT_Exam_Preflight_Service::get_state()['exam_id']);
        self::assertStringContainsString('cbt_msg=Snapshot+pra+ujian+untuk+Ujian+Matematika+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clean_exam_snapshots_keeps_other_exam_auto_warm_active(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 900 + $examId,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [900 + $examId],
                'question_number_map' => [900 + $examId => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);

        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 54,
            'title' => 'Ujian Biologi',
            'status' => 'published',
            'target_kelas' => 'XI-B',
        ]);

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clean_exam_snapshots']);

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertTrue(CBT_Exam_Availability_Auto_Warm_Service::get_state()['active']);
        self::assertSame(54, CBT_Exam_Availability_Auto_Warm_Service::get_state()['exam_id']);
        self::assertStringContainsString('cbt_msg=Snapshot+pra+ujian+untuk+Ujian+Matematika+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_hard_reset_cbt_redis_clears_all_cbt_keys_and_state_options(): void
    {
        $this->bootstrapSnapshotActionScaffold();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-plugin-redis-reset-service.php';

        $GLOBALS['cbt_test_redis_storage']['cbt_exam_delivery:exam:77:payload'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_exam_start_attempt:exam:77:snapshot'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_submit_context:pointer:question:901'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_profile:user:71'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:71'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_exam_availability:student:user:71:prepared'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_attempt_session:attempt:501'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_attempt_contract:attempt:501'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_active_attempt:user:71:exam:77'] = '501';
        $GLOBALS['cbt_test_redis_storage']['cbt_auth:user:71:session'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_runtime:attempt:501'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_roster_live:active_attempts'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_presence_live:attempt:501'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_security_live:attempt:501:summary'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['other_cache_marker_should_stay'] = '';
        $GLOBALS['cbt_test_redis_zsets']['cbt_start_attempt_gate:queue:exam:77'] = ['ticket-1' => 100.0];

        update_option('cbt_exam_preflight_state', ['active' => true, 'exam_id' => 77]);
        update_option('cbt_exam_preflight_jobs', [77 => ['active' => true]]);
        update_option('cbt_exam_preflight_global_runner', ['active_exam_id' => 77]);
        update_option('cbt_exam_availability_auto_warm_state', ['active' => true, 'exam_id' => 77]);

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_page_77' => '3',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_hard_reset_cbt_redis']);

        foreach (array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? [])) as $key) {
            self::assertFalse(strpos((string) $key, 'cbt_') === 0, 'Unexpected CBT redis storage key remains: ' . $key);
        }
        foreach (array_keys((array) ($GLOBALS['cbt_test_redis_zsets'] ?? [])) as $key) {
            self::assertFalse(strpos((string) $key, 'cbt_') === 0, 'Unexpected CBT redis zset key remains: ' . $key);
        }
        self::assertArrayHasKey('other_cache_marker_should_stay', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        self::assertSame(false, get_option('cbt_exam_preflight_state', false));
        self::assertSame(false, get_option('cbt_exam_preflight_jobs', false));
        self::assertSame(false, get_option('cbt_exam_preflight_global_runner', false));
        self::assertSame(false, get_option('cbt_exam_availability_auto_warm_state', false));

        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Redis+CBT+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_set_adaptive_load_override_busy_sets_manual_override_and_preserves_snapshot_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeLoginMetricsRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_page_77' => '3',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_paged' => '2',
            'cbt_adaptive_load_override_level' => 'busy',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_set_adaptive_load_override']);

        $state = CBT_Adaptive_Load_Service::get_state();

        self::assertSame('busy', $state['effective_level']);
        self::assertSame('busy', $state['override_level']);
        self::assertSame('manual_override', $state['source']);
        self::assertSame(1, (int) $state['override_user_id']);
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B0%5D=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B1%5D=54', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Adaptive+load+dipaksa+BUSY+selama+15+menit.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_set_adaptive_load_override_critical_sets_manual_override(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeLoginMetricsRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_adaptive_load_override_level' => 'critical',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_set_adaptive_load_override']);

        $state = CBT_Adaptive_Load_Service::get_state();

        self::assertSame('critical', $state['effective_level']);
        self::assertSame('critical', $state['override_level']);
        self::assertSame('manual_override', $state['source']);
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Adaptive+load+dipaksa+CRITICAL+selama+15+menit.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clear_adaptive_load_override_returns_to_auto_mode_and_preserves_snapshot_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeLoginMetricsRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Adaptive_Load_Service::set_manual_override('critical', 1);

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_page_77' => '3',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_adaptive_load_override']);

        $state = CBT_Adaptive_Load_Service::get_state();

        self::assertSame('critical', $state['effective_level']);
        self::assertSame('', $state['override_level']);
        self::assertSame('auto', $state['source']);
        self::assertSame('', $state['override_expires_at']);
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B0%5D=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_ids%5B1%5D=54', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Adaptive+load+dikembalikan+ke+mode+auto.+Level+aktif+sekarang+CRITICAL.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_bulk_student_exam_availability_snapshots_process_filtered_students_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeAvailabilityRedis();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'exam_monitor',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_bulk_student_exam_availability_snapshots']);

        self::assertNotSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertSame([], $this->storedAvailabilitySnapshotKeysFor(72));
        self::assertStringContainsString('cbt_exam_snapshot_tab=exam_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+menyiapkan+1+snapshot+availability.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'exam_monitor',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_bulk_student_exam_availability_snapshots']);

        self::assertSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertSame([], $this->storedAvailabilitySnapshotKeysFor(72));
        self::assertStringContainsString('cbt_exam_snapshot_tab=exam_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+membersihkan+snapshot+availability+untuk+1+siswa.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_student_profile_snapshot_redirects_back_with_student_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeProfileRedis();

        $_POST = [
            'user_id' => '71',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'profile_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_student_profile_snapshot']);

        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(71));
        self::assertStringContainsString('cbt_exam_snapshot_tab=profile_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+profil+siswa+%2371+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'user_id' => '71',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'profile_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_student_profile_snapshot']);

        self::assertSame('', $this->storedProfileSnapshotPayloadFor(71));
        self::assertStringContainsString('cbt_exam_snapshot_tab=profile_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+profil+siswa+%2371+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_bulk_student_profile_snapshots_process_filtered_students_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeProfileRedis();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'profile_monitor',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_bulk_student_profile_snapshots']);

        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(71));
        self::assertSame('', $this->storedProfileSnapshotPayloadFor(72));
        self::assertStringContainsString('cbt_exam_snapshot_tab=profile_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+menyiapkan+1+snapshot+profil.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'profile_monitor',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_bulk_student_profile_snapshots']);

        self::assertSame('', $this->storedProfileSnapshotPayloadFor(71));
        self::assertSame('', $this->storedProfileSnapshotPayloadFor(72));
        self::assertStringContainsString('cbt_exam_snapshot_tab=profile_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+membersihkan+snapshot+profil+untuk+1+siswa.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_student_login_snapshot_redirects_back_with_student_state(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeLoginSnapshotRedis();

        $_POST = [
            'user_id' => '71',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'login_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_student_login_snapshot']);

        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertStringContainsString('cbt_exam_snapshot_tab=login_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Login+snapshot+siswa+%2371+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'user_id' => '71',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'login_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_student_login_snapshot']);

        self::assertSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertStringContainsString('cbt_exam_snapshot_tab=login_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Login+snapshot+siswa+%2371+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_and_clear_bulk_student_login_snapshots_process_filtered_students_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeLoginSnapshotRedis();

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'login_monitor',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_warm_bulk_student_login_snapshots']);

        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertSame('', $this->storedLoginSnapshotPayloadFor(72));
        self::assertStringContainsString('cbt_exam_snapshot_tab=login_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+menyiapkan+1+login+snapshot.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_tab' => 'login_monitor',
            'cbt_exam_readiness_paged' => '2',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '2',
        ];

        $this->invokeSnapshotActionExpectRedirect([CBT_Admin_Exams_Service::class, 'handle_clear_bulk_student_login_snapshots']);

        self::assertSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertSame('', $this->storedLoginSnapshotPayloadFor(72));
        self::assertStringContainsString('cbt_exam_snapshot_tab=login_monitor', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_readiness_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_q=salsa', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_kelas=XI-A', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_ruang=R1', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_student_snapshot_paged=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+membersihkan+login+snapshot+untuk+1+siswa.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    private function bootstrapSnapshotActionScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-session-snapshot-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-question-contract-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-runtime-snapshot-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $warmedExamIds = [];
    public static array $warmedStartExamIds = [];
    public static array $warmedSubmissionContextExamIds = [];
    public static array $batchAvailabilityPayloadRequests = [];

    public static function warm_exam_question_delivery_snapshot(int $exam_id): void
    {
        self::$warmedExamIds[] = $exam_id;
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload($exam_id, static function (int $target_exam_id): array {
            return [
                [
                    'id' => 900 + $target_exam_id,
                    'exam_id' => $target_exam_id,
                    'question_text' => 'Snapshot exam ' . $target_exam_id,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
    }

    public static function warm_exam_start_attempt_snapshot(int $exam_id): void
    {
        self::$warmedStartExamIds[] = $exam_id;
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($exam_id, static function (int $target_exam_id): array {
            return [
                'exam_id' => $target_exam_id,
                'question_ids' => [900 + $target_exam_id],
                'question_number_map' => [900 + $target_exam_id => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
    }

    public static function warm_exam_submission_context_snapshot(int $exam_id): void
    {
        self::$warmedSubmissionContextExamIds[] = $exam_id;
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
    }

    public static function rebuild_attempt_runtime_snapshots(int $attempt_id, int $expected_exam_id = 0): array
    {
        if ($attempt_id !== 501 || ($expected_exam_id > 0 && $expected_exam_id !== 77)) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $expected_exam_id,
                'message' => 'Attempt tidak termasuk exam yang sedang dipantau.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'started_at' => '2026-04-04 07:00:00',
            'duration_minutes' => 90,
            'extra_time_minutes' => 5,
            'question_count' => 2,
            'question_order_signature' => 'runtime-sig-501',
            'show_student_result' => 1,
            'enable_calculator' => 1,
        ]);
        CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'question_order_ids' => [901, 902],
            'question_number_map' => [901 => 1, 902 => 2],
            'question_order_signature' => 'runtime-sig-501',
            'question_manifest' => [
                ['id' => 901, 'question_number' => 1],
                ['id' => 902, 'question_number' => 2],
            ],
            'option_order_map' => [
                901 => ['A', 'B'],
            ],
        ]);

        return [
            'ok' => true,
            'attempt_id' => 501,
            'exam_id' => 77,
            'message' => 'Runtime snapshot berhasil diperbarui dari sumber live.',
            'session_snapshot' => CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics(501),
            'contract_snapshot' => CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(501),
        ];
    }

    public static function build_student_exam_availability_snapshot_payload(int $user_id): array
    {
        $user = get_user_by('id', $user_id);
        $display_name = $user instanceof WP_User ? (string) ($user->display_name ?: $user->user_login) : '';
        $username = $user instanceof WP_User ? (string) $user->user_login : '';

        return [
            'items' => [
                [
                    'id' => 77,
                    'title' => 'Ujian Matematika',
                    'availability_reason' => 'ok',
                    'is_available_now' => 1,
                ],
                [
                    'id' => 54,
                    'title' => 'Ujian Biologi',
                    'availability_reason' => 'ok',
                    'is_available_now' => 1,
                ],
            ],
            'current_user' => [
                'user_id' => $user_id,
                'display_name' => $display_name,
                'username' => $username,
                'kode_kelas' => (string) get_user_meta($user_id, 'kode_kelas', true),
                'kode_ruang' => (string) get_user_meta($user_id, 'kode_ruang', true),
            ],
        ];
    }

    public static function build_batch_student_exam_availability_snapshot_payloads(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
        self::$batchAvailabilityPayloadRequests[] = $user_ids;
        $payloads = [];

        foreach ($user_ids as $user_id) {
            $payloads[$user_id] = self::build_student_exam_availability_snapshot_payload($user_id);
        }

        return $payloads;
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';
    }

    /**
     * @param array<int,int> $targetStudentIds
     */
    private function seedActivePreflightState(int $examId, string $examTitle, array $targetStudentIds): void
    {
        update_option('cbt_exam_preflight_state', [
            'active' => true,
            'status' => 'active',
            'session_id' => 'preflight-' . $examId . '-test',
            'exam_id' => $examId,
            'exam_title' => $examTitle,
            'exam_status' => 'published',
            'target_kelas_csv' => $examId === 54 ? 'XI-B' : 'XI-A',
            'target_student_ids' => $targetStudentIds,
            'target_student_count' => count($targetStudentIds),
            'profile_cursor' => 0,
            'profile_success_count' => 0,
            'profile_failure_count' => 0,
            'login_snapshot_success_count' => 0,
            'login_snapshot_failure_count' => 0,
            'submission_context_question_count' => 0,
            'submission_context_ready_count' => 0,
            'submission_context_missing_count' => 0,
            'submission_context_invalid_count' => 0,
            'question_snapshot_ready' => true,
            'start_snapshot_ready' => true,
            'submission_context_ready' => true,
            'auto_warm_started' => false,
            'started_at' => '2026-04-06 09:00:00',
            'finished_at' => '',
            'last_tick_at' => '2026-04-06 09:00:00',
            'last_message' => 'Sedang berjalan.',
            'stage_question' => 'ready',
            'stage_start_snapshot' => 'ready',
            'stage_submission_context' => 'ready',
            'stage_profiles' => 'active',
            'stage_login_snapshot' => 'active',
            'stage_auto_warm' => 'pending',
        ]);
    }

    private function registerAdditionalXiAStudents(int $count): void
    {
        $count = max(0, $count);
        for ($index = 0; $index < $count; $index++) {
            $user_id = 1000 + $index;
            cbt_test_register_user([
                'ID' => $user_id,
                'display_name' => 'XI-A Extra ' . $index,
                'user_login' => 'xia_extra_' . $index,
                'user_email' => 'xia_extra_' . $index . '@example.com',
                'user_pass' => 'pass-extra-' . $index,
                'roles' => ['student'],
            ]);
            update_user_meta($user_id, 'kode_kelas', 'XI-A');
            update_user_meta($user_id, 'kode_ruang', 'R1');
            update_user_meta($user_id, 'agama', 'Islam');
        }
    }

    private function useFakeDeliveryRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Question_Delivery_Cache::class);

        $redisProperty = $reflection->getProperty('delivery_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('delivery_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('delivery_redis_last_connection_error');
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

    private function useFakeAvailabilityRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Availability_Cache::class);

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

    private function useFakeAttemptSessionRedis(): void
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

    private function useFakeAttemptContractRedis(): void
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

    private function useFakeSubmissionContextRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Question_Submission_Context_Cache::class);

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

    private function useFakeProfileRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeLoginSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Login_Auth_Snapshot_Cache::class);

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

    private function useFakeLoginMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Login_Snapshot_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    /**
     * @param callable():void $action
     */
    private function invokeSnapshotActionExpectRedirect(callable $action): void
    {
        try {
            $action();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }
    }

    /**
     * @return array<int,string>
     */
    private function storedExamSnapshotKeysFor(int $examId): array
    {
        $prefix = 'cbt_exam_delivery:exam:' . $examId . ':';
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }

    /**
     * @return array<int,string>
     */
    private function storedStartSnapshotKeysFor(int $examId): array
    {
        $prefix = 'cbt_exam_start_attempt:exam:' . $examId . ':';
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }

    /**
     * @return array<int,string>
     */
    private function storedAvailabilitySnapshotKeysFor(int $userId): array
    {
        $prefix = 'cbt_exam_availability:student:user:' . $userId . ':';
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }

    private function storedProfileSnapshotPayloadFor(int $userId): string
    {
        return (string) ($GLOBALS['cbt_test_redis_storage']['cbt_profile:user:' . $userId] ?? '');
    }

    private function storedLoginSnapshotPayloadFor(int $userId): string
    {
        return (string) ($GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:' . $userId] ?? '');
    }

    /**
     * @return array<int,string>
     */
    private function storedSubmissionContextKeysFor(int $examId): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($examId): bool {
            return is_string($key)
                && strpos($key, 'cbt_submit_context:') === 0
                && strpos($key, ':exam:' . $examId . ':') !== false;
        }));
    }
}

class AdminExamsSnapshotActionsFakeWpdb
{
    public string $prefix = 'wp_';

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "\\'", (string) $arg) . "'";
            $query = preg_replace('/%[dfs]/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    public function esc_like(string $text): string
    {
        return $text;
    }

    /**
     * @param string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT e.id, e.title, e.status, e.target_kelas, s.name AS subject_name') !== false) {
            return [
                ['id' => 77, 'title' => 'Ujian Matematika', 'status' => 'published', 'target_kelas' => 'XI-A', 'subject_name' => 'Matematika', 'duration_minutes' => 90, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
                ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'target_kelas' => 'XI-B', 'subject_name' => 'Biologi', 'duration_minutes' => 60, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 77') !== false) {
            return [
                ['id' => 977, 'question_type' => 'multiple_choice'],
                ['id' => 978, 'question_type' => 'short_answer'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 54') !== false) {
            return [
                ['id' => 954, 'question_type' => 'multiple_choice'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.id IN (977,978)') !== false) {
            return [
                ['id' => 977, 'exam_id' => 77, 'question_type' => 'multiple_choice', 'points' => 1, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => null],
                ['id' => 978, 'exam_id' => 77, 'question_type' => 'short_answer', 'points' => 1, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => 'Jakarta'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.id IN (954)') !== false) {
            return [
                ['id' => 954, 'exam_id' => 54, 'question_type' => 'multiple_choice', 'points' => 1, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => null],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (977,954)') !== false) {
            return [
                ['id' => 9101, 'question_id' => 977, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9102, 'question_id' => 977, 'option_text' => 'B', 'is_correct' => 0],
                ['id' => 9201, 'question_id' => 954, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9202, 'question_id' => 954, 'option_text' => 'B', 'is_correct' => 0],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (977)') !== false) {
            return [
                ['id' => 9101, 'question_id' => 977, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9102, 'question_id' => 977, 'option_text' => 'B', 'is_correct' => 0],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (954)') !== false) {
            return [
                ['id' => 9201, 'question_id' => 954, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9202, 'question_id' => 954, 'option_text' => 'B', 'is_correct' => 0],
            ];
        }

        return [];
    }

    /**
     * @param string $prepared
     */
    public function get_var($prepared)
    {
        $query = (string) $prepared;

        if (strpos($query, "SELECT COUNT(*) FROM wp_cbt_attempts WHERE status = 'in_progress'") !== false) {
            return 0;
        }

        return 0;
    }

    /**
     * @param string $prepared
     * @return array<int,string>
     */
    public function get_col($prepared): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT id FROM wp_cbt_exams WHERE title NOT LIKE') !== false) {
            return ['77', '54'];
        }

        return [];
    }
}

final class AdminExamsSnapshotActionsBulkFailureFakeWpdb extends AdminExamsSnapshotActionsFakeWpdb
{
    /**
     * @param string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT e.id, e.title, e.status, e.target_kelas, s.name AS subject_name') !== false) {
            return [
                ['id' => 88, 'title' => 'Ujian Draft', 'status' => 'draft', 'target_kelas' => 'XI-A', 'subject_name' => 'Fisika', 'duration_minutes' => 90, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
                ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'target_kelas' => 'XI-B', 'subject_name' => 'Biologi', 'duration_minutes' => 60, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
            ];
        }

        return parent::get_results($prepared, $output);
    }
}
