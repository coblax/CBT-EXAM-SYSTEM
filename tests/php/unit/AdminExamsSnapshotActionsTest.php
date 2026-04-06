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
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';
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

final class AdminExamsSnapshotActionsFakeWpdb
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
}
