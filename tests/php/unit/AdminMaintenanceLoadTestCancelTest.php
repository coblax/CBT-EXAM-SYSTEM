<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminMaintenanceLoadTestCancelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 1;
        $GLOBALS['cbt_test_last_redirect'] = null;
        $_POST = [];
        $_REQUEST = [];

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-maintenance-load-test-service.php';
    }

    #[RunInSeparateProcess]
    public function test_handle_cancel_load_test_marks_running_job_cancelled_without_fatal_when_signal_is_not_confirmed(): void
    {
        update_option('cbt_load_test_jobs', [
            'jobabc123' => [
                'id' => 'jobabc123',
                'user_id' => 1,
                'status' => 'running',
                'pid' => 999999,
                'exam_id' => 77,
                'exam_title' => 'Exam Matematika',
                'subject_name' => 'Matematika',
                'workspace' => '/tmp/cbt-load-test-jobabc123',
                'created_at' => '2026-04-06 08:00:00',
                'started_at' => '2026-04-06 08:00:03',
                'finished_at' => '',
                'exit_code' => null,
                'notes' => 'Runner aktif.',
                'profile' => [],
            ],
        ]);

        $_POST = [
            'job_id' => 'jobabc123',
            'cbt_maintenance_tab' => 'load',
        ];
        $_REQUEST = $_POST;

        try {
            CBT_Admin_Maintenance_Load_Test_Service::handle_cancel_load_test();
            self::fail('Expected maintenance redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_maintenance_redirect__', $runtimeException->getMessage());
        }

        $redirectUrl = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('page=cbt-maintenance', $redirectUrl);
        self::assertStringContainsString('cbt_maintenance_tab=load', $redirectUrl);
        self::assertStringContainsString('cbt_msg=Job+load+test+ditandai+cancelled%2C', $redirectUrl);

        $jobs = get_option('cbt_load_test_jobs', []);
        self::assertIsArray($jobs);
        self::assertIsArray($jobs['jobabc123'] ?? null);

        $job = (array) $jobs['jobabc123'];
        self::assertSame('cancelled', $job['status'] ?? '');
        self::assertSame(0, $job['pid'] ?? null);
        self::assertSame(143, $job['exit_code'] ?? null);
        self::assertNotSame('', (string) ($job['finished_at'] ?? ''));
        self::assertStringContainsString('Dibatalkan dari CBT Maintenance.', (string) ($job['notes'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_cancel_load_test_reports_terminal_job_as_already_inactive(): void
    {
        update_option('cbt_load_test_jobs', [
            'jobdone123' => [
                'id' => 'jobdone123',
                'user_id' => 1,
                'status' => 'success',
                'pid' => 0,
                'exam_id' => 88,
                'exam_title' => 'Exam Bahasa',
                'subject_name' => 'Bahasa',
                'workspace' => '/tmp/cbt-load-test-jobdone123',
                'created_at' => '2026-04-06 08:00:00',
                'started_at' => '2026-04-06 08:00:03',
                'finished_at' => '2026-04-06 08:10:00',
                'exit_code' => 0,
                'notes' => '',
                'profile' => [],
            ],
        ]);

        $_POST = [
            'job_id' => 'jobdone123',
            'cbt_maintenance_tab' => 'load',
        ];
        $_REQUEST = $_POST;

        try {
            CBT_Admin_Maintenance_Load_Test_Service::handle_cancel_load_test();
            self::fail('Expected maintenance redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_maintenance_redirect__', $runtimeException->getMessage());
        }

        $redirectUrl = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('cbt_msg=Job+load+test+ini+sudah+tidak+aktif+lagi.', $redirectUrl);

        $jobs = get_option('cbt_load_test_jobs', []);
        $job = (array) ($jobs['jobdone123'] ?? []);
        self::assertSame('success', $job['status'] ?? '');
        self::assertSame(0, $job['exit_code'] ?? null);
    }
}
