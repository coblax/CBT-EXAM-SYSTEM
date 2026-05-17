<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class PluginRedisResetServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-cache.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-plugin-redis-reset-service.php';
    }

    public function test_get_plugin_diagnostics_returns_expected_structure(): void
    {
        $diag = \CBT_Plugin_Redis_Reset_Service::get_plugin_diagnostics();
        $this->assertArrayHasKey('redis_available', $diag);
        $this->assertArrayHasKey('redis_error', $diag);
        $this->assertArrayHasKey('total_keys', $diag);
        $this->assertArrayHasKey('database_summaries', $diag);
        $this->assertArrayHasKey('prefix_counts', $diag);
        $this->assertArrayHasKey('memory_used_human', $diag);
        $this->assertIsArray($diag['database_summaries']);
        $this->assertIsArray($diag['prefix_counts']);
    }

    public function test_reset_all_plugin_keys_returns_success(): void
    {
        $result = \CBT_Plugin_Redis_Reset_Service::reset_all_plugin_keys();
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('deleted_keys', $result);
        $this->assertArrayHasKey('deleted_options', $result);
        $this->assertTrue($result['success']);
    }

    public function test_start_reset_job_returns_state(): void
    {
        $result = \CBT_Plugin_Redis_Reset_Service::start_reset_job('test');
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('state', $result);
        $this->assertTrue($result['success']);
        $this->assertSame('active', $result['state']['status']);
    }

    public function test_get_reset_job_state_returns_null_for_empty_token(): void
    {
        $this->assertNull(\CBT_Plugin_Redis_Reset_Service::get_reset_job_state(''));
    }

    public function test_get_reset_job_state_returns_null_for_unknown_token(): void
    {
        $this->assertNull(\CBT_Plugin_Redis_Reset_Service::get_reset_job_state('nonexistent'));
    }

    public function test_tick_reset_job_returns_failed_for_unknown_token(): void
    {
        $result = \CBT_Plugin_Redis_Reset_Service::tick_reset_job('nonexistent');
        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['state']['status']);
    }

    public function test_start_and_tick_completes_job(): void
    {
        $start = \CBT_Plugin_Redis_Reset_Service::start_reset_job('test');
        $token = $start['state']['token'];
        $maxTicks = 20;
        $lastResult = $start;

        for ($i = 0; $i < $maxTicks; $i++) {
            $lastResult = \CBT_Plugin_Redis_Reset_Service::tick_reset_job($token);
            if (in_array($lastResult['state']['status'], ['completed', 'failed'], true)) {
                break;
            }
        }

        $this->assertSame('completed', $lastResult['state']['status']);
    }

    public function test_build_reset_job_response_returns_expected_structure(): void
    {
        $state = [
            'token' => 'abc',
            'status' => 'active',
            'deleted_keys' => 5,
            'deleted_options' => 2,
            'connection_index' => 0,
            'connection_total' => 1,
            'total_keys' => 10,
        ];

        $response = \CBT_Plugin_Redis_Reset_Service::build_reset_job_response($state);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('status_label', $response);
        $this->assertArrayHasKey('complete', $response);
        $this->assertArrayHasKey('progress_percent', $response);
        $this->assertArrayHasKey('totals', $response);
    }

    public function test_build_reset_job_response_completed_is_100_percent(): void
    {
        $state = [
            'token' => 'abc',
            'status' => 'completed',
            'deleted_keys' => 10,
        ];

        $response = \CBT_Plugin_Redis_Reset_Service::build_reset_job_response($state);
        $this->assertTrue($response['complete']);
        $this->assertSame(100.0, $response['progress_percent']);
    }

    public function test_start_reset_job_returns_existing_active_job(): void
    {
        $first = \CBT_Plugin_Redis_Reset_Service::start_reset_job('test');
        $second = \CBT_Plugin_Redis_Reset_Service::start_reset_job('test');
        $this->assertSame($first['state']['token'], $second['state']['token']);
    }
}
