<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-metrics-service.php';

final class StartAttemptMetricsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        $this->useFakeMetricsRedis();
    }

    public function test_record_phase_and_resolution_build_window_and_today_summary(): void
    {
        for ($index = 0; $index < 12; $index++) {
            CBT_Start_Attempt_Metrics_Service::record_resolution('started');
            CBT_Start_Attempt_Metrics_Service::record_resolution('resume_from_index');
            CBT_Start_Attempt_Metrics_Service::record_resolution('queued_new_start');
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_response_ready', 6500);
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_status_response_ready', 5200);
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_start_snapshot', 15000);
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_gate_evaluation', 700);
        }

        CBT_Start_Attempt_Metrics_Service::record_resolution('lock_conflict_retryable');

        $window = CBT_Start_Attempt_Metrics_Service::get_window_summary(15);
        $today = CBT_Start_Attempt_Metrics_Service::get_today_summary();
        $admin = CBT_Start_Attempt_Metrics_Service::get_admin_summary();

        self::assertTrue($window['available']);
        self::assertSame(12, $window['start_attempt_count']);
        self::assertSame(12, $window['start_attempt_status_count']);
        self::assertSame(12, $window['started_total']);
        self::assertSame(12, $window['resumed_total']);
        self::assertSame(12, $window['queued_total']);
        self::assertSame(1, $window['lock_conflict_retryable_total']);
        self::assertSame(8000, $window['start_attempt_p95_ms']);
        self::assertSame(6000, $window['start_attempt_status_p95_ms']);
        self::assertSame(15000, $window['phase_p95']['start_attempt_start_snapshot']);
        self::assertSame('start_attempt_start_snapshot', $window['top_slowest_phase']);
        self::assertSame(15000, $window['top_slowest_phase_p95_ms']);

        self::assertTrue($today['available']);
        self::assertSame(12, $today['started_total']);
        self::assertSame(12, $today['resumed_total']);
        self::assertTrue($admin['available']);
        self::assertSame(8000, $admin['window']['start_attempt_p95_ms']);
    }

    public function test_get_window_summary_returns_empty_payload_when_redis_is_unavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis unavailable');

        $summary = CBT_Start_Attempt_Metrics_Service::get_window_summary(15);

        self::assertFalse($summary['available']);
        self::assertSame(0, $summary['start_attempt_count']);
        self::assertSame(0, $summary['start_attempt_p95_ms']);
        self::assertSame('Redis unavailable', $summary['redis_error']);
    }

    private function useFakeMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');

        $listenersProperty = $reflection->getProperty('listeners_registered');
        $listenersProperty->setAccessible(true);
        $listenersProperty->setValue(null, false);
    }
}
