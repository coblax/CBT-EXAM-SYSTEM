<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class UpdateJobServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-release-helper.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-backup-service.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-job-service.php';
    }

    public function test_start_check_returns_running_job(): void
    {
        $job = \CBT_Update_Job_Service::start_check('ajax');
        $this->assertSame('running', $job['status']);
        $this->assertSame('check', $job['type']);
        $this->assertNotEmpty($job['token']);
    }

    public function test_get_job_returns_null_for_empty_token(): void
    {
        $this->assertNull(\CBT_Update_Job_Service::get_job(''));
    }

    public function test_get_job_retrieves_saved_job(): void
    {
        $job = \CBT_Update_Job_Service::start_check('test');
        $retrieved = \CBT_Update_Job_Service::get_job($job['token']);
        $this->assertSame($job['token'], $retrieved['token']);
    }

    public function test_tick_returns_failed_for_unknown_token(): void
    {
        $result = \CBT_Update_Job_Service::tick('nonexistent');
        $this->assertSame('failed', $result['status']);
    }

    public function test_clear_finished_job_rejects_running_job(): void
    {
        $job = \CBT_Update_Job_Service::start_check('test');
        $this->assertFalse(\CBT_Update_Job_Service::clear_finished_job($job['token']));
    }

    public function test_response_for_job_structure(): void
    {
        $job = \CBT_Update_Job_Service::start_check('test');
        $response = \CBT_Update_Job_Service::response_for_job($job, 'check');
        $this->assertArrayHasKey('operation', $response);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('complete', $response);
        $this->assertFalse($response['complete']);
    }

    public function test_get_history_returns_array(): void
    {
        $this->assertIsArray(\CBT_Update_Job_Service::get_history());
    }

    public function test_get_active_job_returns_latest(): void
    {
        $job = \CBT_Update_Job_Service::start_check('test');
        $active = \CBT_Update_Job_Service::get_active_job();
        $this->assertSame($job['token'], $active['token']);
    }
}
