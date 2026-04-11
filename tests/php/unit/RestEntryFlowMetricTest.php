<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestEntryFlowMetricTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_entry_flow_metric_records_valid_payload_and_dedupes_metric_key(): void
    {
        $this->bootstrapEntryFlowMetricRestScaffold();
        $this->useFakeMetricsRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        $first = CBT_REST::entry_flow_metric(new WP_REST_Request(
            [],
            [
                'flow' => 'start_to_first_question',
                'metric_key' => 'open_55_abc',
                'duration_ms' => 14200,
                'exam_id' => 55,
                'attempt_id' => 77,
                'phase_durations' => [
                    'attempt_acquire_ms' => 4200,
                    'unknown_phase_ms' => 100,
                ],
            ],
            [],
            '/cbt/v1/entry_flow_metric',
            'POST'
        ));
        $second = CBT_REST::entry_flow_metric(new WP_REST_Request(
            [],
            [
                'flow' => 'start_to_first_question',
                'metric_key' => 'open_55_abc',
                'duration_ms' => 19000,
                'exam_id' => 55,
                'attempt_id' => 77,
                'phase_durations' => [
                    'attempt_acquire_ms' => 9999,
                ],
            ],
            [],
            '/cbt/v1/entry_flow_metric',
            'POST'
        ));

        $window = CBT_Entry_Flow_Metrics_Service::get_window_summary(15);

        self::assertSame(['ok' => true, 'duplicate' => false, 'skipped' => false], $first);
        self::assertSame(['ok' => true, 'duplicate' => true, 'skipped' => false], $second);
        self::assertSame(1, $window['start_to_first_question_count']);
        self::assertSame(1, $window['phase_counts']['attempt_acquire_ms']);
        self::assertArrayNotHasKey('unknown_phase_ms', $window['phase_counts']);
    }

    #[RunInSeparateProcess]
    public function test_entry_flow_metric_rejects_invalid_payload(): void
    {
        $this->bootstrapEntryFlowMetricRestScaffold();
        $this->useFakeMetricsRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        $invalidFlow = CBT_REST::entry_flow_metric(new WP_REST_Request([], [
            'flow' => '',
            'metric_key' => 'login_1',
            'duration_ms' => 1000,
        ]));
        $invalidKey = CBT_REST::entry_flow_metric(new WP_REST_Request([], [
            'flow' => 'login_to_exam_list',
            'metric_key' => '',
            'duration_ms' => 1000,
        ]));
        $invalidDuration = CBT_REST::entry_flow_metric(new WP_REST_Request([], [
            'flow' => 'login_to_exam_list',
            'metric_key' => 'login_1',
            'duration_ms' => 'bad',
        ]));

        self::assertTrue(is_wp_error($invalidFlow));
        self::assertSame('invalid_entry_flow_metric_flow', $invalidFlow->get_error_code());
        self::assertTrue(is_wp_error($invalidKey));
        self::assertSame('invalid_entry_flow_metric_key', $invalidKey->get_error_code());
        self::assertTrue(is_wp_error($invalidDuration));
        self::assertSame('invalid_entry_flow_metric_duration', $invalidDuration->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_entry_flow_metric_returns_safe_skipped_response_when_metrics_redis_is_unavailable(): void
    {
        $this->bootstrapEntryFlowMetricRestScaffold();

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

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        $response = CBT_REST::entry_flow_metric(new WP_REST_Request([], [
            'flow' => 'login_to_exam_list',
            'metric_key' => 'login_1',
            'duration_ms' => 1800,
        ]));

        self::assertSame(['ok' => true, 'duplicate' => false, 'skipped' => true], $response);
    }

    private function bootstrapEntryFlowMetricRestScaffold(): void
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

    public static function permission_teacher_or_student(\WP_REST_Request $request): bool
    {
        return self::current_user_id($request) > 0;
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-entry-flow-metrics-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
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
