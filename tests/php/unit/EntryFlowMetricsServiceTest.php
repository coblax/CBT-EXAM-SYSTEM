<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-entry-flow-metrics-service.php';

final class EntryFlowMetricsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        $this->useFakeMetricsRedis();
    }

    public function test_record_flow_event_builds_window_and_today_summary(): void
    {
        for ($index = 0; $index < 12; $index++) {
            CBT_Entry_Flow_Metrics_Service::record_flow_event(
                'login_to_exam_list',
                'login-' . $index,
                3800,
                [
                    'login_request_ms' => 1200,
                    'login_exam_list_ms' => 2200,
                ],
                ['user_id' => 71]
            );
            CBT_Entry_Flow_Metrics_Service::record_flow_event(
                'start_to_first_question',
                'start-' . $index,
                14000,
                [
                    'attempt_acquire_ms' => 4200,
                    'attempt_open_shell_ms' => 2400,
                    'first_window_ready_ms' => 6800,
                ],
                ['user_id' => 71, 'exam_id' => 55]
            );
            CBT_Entry_Flow_Metrics_Service::record_flow_event(
                'resume_to_first_question',
                'resume-' . $index,
                9000,
                [
                    'attempt_acquire_ms' => 2600,
                    'first_window_ready_ms' => 5200,
                ],
                ['user_id' => 71, 'exam_id' => 55, 'attempt_id' => 77]
            );
        }

        $window = CBT_Entry_Flow_Metrics_Service::get_window_summary(15);
        $today = CBT_Entry_Flow_Metrics_Service::get_today_summary();
        $admin = CBT_Entry_Flow_Metrics_Service::get_admin_summary();

        self::assertTrue($window['available']);
        self::assertSame(12, $window['login_to_exam_list_count']);
        self::assertSame(12, $window['start_to_first_question_count']);
        self::assertSame(12, $window['resume_to_first_question_count']);
        self::assertSame(4000, $window['login_to_exam_list_p95_ms']);
        self::assertSame(15000, $window['start_to_first_question_p95_ms']);
        self::assertSame(10000, $window['resume_to_first_question_p95_ms']);
        self::assertSame(8000, $window['phase_p95']['first_window_ready_ms']);
        self::assertSame('first_window_ready_ms', $window['top_slowest_phase']);
        self::assertSame(8000, $window['top_slowest_phase_p95_ms']);

        self::assertTrue($today['available']);
        self::assertSame(12, $today['start_to_first_question_count']);
        self::assertTrue($admin['available']);
        self::assertSame(15000, $admin['window']['start_to_first_question_p95_ms']);
    }

    public function test_record_flow_event_dedupes_metric_key_and_ignores_unknown_phase_keys(): void
    {
        $first = CBT_Entry_Flow_Metrics_Service::record_flow_event(
            'start_to_first_question',
            'same-key',
            14000,
            [
                'attempt_acquire_ms' => 4100,
                'unknown_phase_ms' => 1234,
            ],
            ['user_id' => 71, 'exam_id' => 55]
        );
        $second = CBT_Entry_Flow_Metrics_Service::record_flow_event(
            'start_to_first_question',
            'same-key',
            20000,
            [
                'attempt_acquire_ms' => 9999,
            ],
            ['user_id' => 71, 'exam_id' => 55]
        );

        $window = CBT_Entry_Flow_Metrics_Service::get_window_summary(15);

        self::assertTrue($first['recorded']);
        self::assertFalse($first['duplicate']);
        self::assertTrue($second['duplicate']);
        self::assertSame(1, $window['start_to_first_question_count']);
        self::assertSame(1, $window['phase_counts']['attempt_acquire_ms']);
        self::assertArrayNotHasKey('unknown_phase_ms', $window['phase_counts']);
    }

    public function test_get_window_summary_returns_empty_payload_when_redis_is_unavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Entry_Flow_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis unavailable');

        $summary = CBT_Entry_Flow_Metrics_Service::get_window_summary(15);

        self::assertFalse($summary['available']);
        self::assertSame(0, $summary['start_to_first_question_count']);
        self::assertSame(0, $summary['start_to_first_question_p95_ms']);
        self::assertSame('Redis unavailable', $summary['redis_error']);
    }

    private function useFakeMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Entry_Flow_Metrics_Service::class);

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
}
