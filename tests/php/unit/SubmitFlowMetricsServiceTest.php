<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-submit-flow-metrics-service.php';

final class SubmitFlowMetricsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        $this->useFakeMetricsRedis();
    }

    public function test_record_event_builds_window_today_summary_and_resolves_watchlist(): void
    {
        $started = CBT_Submit_Flow_Metrics_Service::record_event(
            88,
            9,
            'finish_submit_started',
            'submit-start-1',
            1710000000000,
            0,
            [],
            ['user_id' => 71]
        );
        $acknowledged = CBT_Submit_Flow_Metrics_Service::record_event(
            88,
            9,
            'finish_acknowledged',
            'submit-ack-1',
            1710000001800,
            1800,
            [],
            ['user_id' => 71, 'ack_source' => 'finish_exam']
        );

        $watchlistBeforeReady = CBT_Submit_Flow_Metrics_Service::get_unresolved_watchlist_snapshot();

        CBT_Submit_Flow_Metrics_Service::record_event(
            88,
            9,
            'finish_recovery_retry',
            'submit-retry-1',
            1710000002600,
            null,
            [],
            ['user_id' => 71]
        );
        $ready = CBT_Submit_Flow_Metrics_Service::record_event(
            88,
            9,
            'finish_result_ready',
            'submit-ready-1',
            1710000006400,
            6400,
            [
                'ack_to_result_ready_ms' => 4200,
                'submit_to_result_ready_ms' => 6400,
            ],
            ['user_id' => 71, 'ack_source' => 'finish_exam']
        );

        $window = CBT_Submit_Flow_Metrics_Service::get_window_summary(15);
        $today = CBT_Submit_Flow_Metrics_Service::get_today_summary();
        $watchlistAfterReady = CBT_Submit_Flow_Metrics_Service::get_unresolved_watchlist_snapshot();

        self::assertTrue($started['recorded']);
        self::assertTrue($acknowledged['recorded']);
        self::assertTrue($ready['recorded']);
        self::assertTrue($window['available']);
        self::assertSame(1, $window['submit_started_total']);
        self::assertSame(1, $window['finish_acknowledged_total']);
        self::assertSame(1, $window['finish_recovery_retry_total']);
        self::assertSame(1, $window['finish_result_ready_total']);
        self::assertSame(2000, $window['submit_to_ack_p95_ms']);
        self::assertSame(6000, $window['ack_to_result_ready_p95_ms']);
        self::assertSame(8000, $window['submit_to_result_ready_p95_ms']);
        self::assertTrue($today['available']);
        self::assertSame(1, $today['finish_result_ready_total']);
        self::assertTrue($watchlistBeforeReady['available']);
        self::assertSame(1, $watchlistBeforeReady['total']);
        self::assertSame('result_pending', $watchlistBeforeReady['items'][0]['latest_state'] ?? '');
        self::assertTrue($watchlistAfterReady['available']);
        self::assertSame(0, $watchlistAfterReady['total']);
        self::assertSame([], $watchlistAfterReady['items']);
    }

    public function test_record_event_dedupes_event_key_and_ignores_out_of_order_state_regression(): void
    {
        $firstAck = CBT_Submit_Flow_Metrics_Service::record_event(
            99,
            10,
            'finish_acknowledged',
            'same-key',
            1710001000000,
            1200,
            [],
            ['user_id' => 71, 'ack_source' => 'finish_exam']
        );
        $duplicateAck = CBT_Submit_Flow_Metrics_Service::record_event(
            99,
            10,
            'finish_acknowledged',
            'same-key',
            1710001001000,
            2200,
            [],
            ['user_id' => 71, 'ack_source' => 'finish_exam']
        );
        CBT_Submit_Flow_Metrics_Service::record_event(
            99,
            10,
            'finish_result_ready',
            'ready-key',
            1710001005000,
            5000,
            [
                'ack_to_result_ready_ms' => 3000,
                'submit_to_result_ready_ms' => 5000,
            ],
            ['user_id' => 71]
        );
        CBT_Submit_Flow_Metrics_Service::record_event(
            99,
            10,
            'finish_result_recovery_failed',
            'late-stale-error',
            1710001004000,
            null,
            [],
            ['user_id' => 71, 'error_code' => 'late_error', 'error_message' => 'Late stale error']
        );

        $window = CBT_Submit_Flow_Metrics_Service::get_window_summary(15);
        $watchlist = CBT_Submit_Flow_Metrics_Service::get_unresolved_watchlist_snapshot();

        self::assertTrue($firstAck['recorded']);
        self::assertTrue($duplicateAck['duplicate']);
        self::assertSame(1, $window['finish_acknowledged_total']);
        self::assertSame(1, $window['finish_recovery_failed_total']);
        self::assertSame(0, $watchlist['total']);
        self::assertSame([], $watchlist['items']);
    }

    private function useFakeMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Submit_Flow_Metrics_Service::class);

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
