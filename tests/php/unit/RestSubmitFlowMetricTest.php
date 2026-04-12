<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestSubmitFlowMetricTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_submit_flow_metric_records_valid_payload_and_dedupes_event_key(): void
    {
        $this->bootstrapSubmitFlowMetricRestScaffold();
        $this->useFakeMetricsRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new RestSubmitFlowMetricFakeWpdb([
            88 => [
                'id' => 88,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'in_progress',
                'finished_at' => '',
            ],
        ]);

        $first = CBT_REST::submit_flow_metric(new WP_REST_Request(
            [],
            [
                'attempt_id' => 88,
                'exam_id' => 9,
                'event' => 'finish_acknowledged',
                'event_key' => 'submit-ack-88',
                'client_event_at_ms' => 1710000001800,
                'duration_ms' => 1800,
                'meta' => [
                    'ack_source' => 'finish_exam',
                ],
            ],
            [],
            '/cbt/v1/submit_flow_metric',
            'POST'
        ));
        $second = CBT_REST::submit_flow_metric(new WP_REST_Request(
            [],
            [
                'attempt_id' => 88,
                'exam_id' => 9,
                'event' => 'finish_acknowledged',
                'event_key' => 'submit-ack-88',
                'client_event_at_ms' => 1710000001900,
                'duration_ms' => 1900,
            ],
            [],
            '/cbt/v1/submit_flow_metric',
            'POST'
        ));

        $window = CBT_Submit_Flow_Metrics_Service::get_window_summary(15);

        self::assertSame(['ok' => true, 'duplicate' => false, 'skipped' => false], $first);
        self::assertSame(['ok' => true, 'duplicate' => true, 'skipped' => false], $second);
        self::assertSame(1, $window['finish_acknowledged_total']);
        self::assertSame(2000, $window['submit_to_ack_p95_ms']);
    }

    #[RunInSeparateProcess]
    public function test_submit_flow_metric_rejects_invalid_payload(): void
    {
        $this->bootstrapSubmitFlowMetricRestScaffold();
        $this->useFakeMetricsRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        $invalidEvent = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 88,
            'exam_id' => 9,
            'event' => '',
            'event_key' => 'bad-event',
            'client_event_at_ms' => 1710000001800,
        ]));
        $invalidKey = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 88,
            'exam_id' => 9,
            'event' => 'finish_acknowledged',
            'event_key' => '',
            'client_event_at_ms' => 1710000001800,
        ]));
        $invalidTime = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 88,
            'exam_id' => 9,
            'event' => 'finish_acknowledged',
            'event_key' => 'bad-time',
            'client_event_at_ms' => 'bad',
        ]));
        $invalidDuration = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 88,
            'exam_id' => 9,
            'event' => 'finish_acknowledged',
            'event_key' => 'bad-duration',
            'client_event_at_ms' => 1710000001800,
            'duration_ms' => 'bad',
        ]));

        self::assertTrue(is_wp_error($invalidEvent));
        self::assertSame('invalid_submit_flow_metric_event', $invalidEvent->get_error_code());
        self::assertTrue(is_wp_error($invalidKey));
        self::assertSame('invalid_submit_flow_metric_key', $invalidKey->get_error_code());
        self::assertTrue(is_wp_error($invalidTime));
        self::assertSame('invalid_submit_flow_metric_event_time', $invalidTime->get_error_code());
        self::assertTrue(is_wp_error($invalidDuration));
        self::assertSame('invalid_submit_flow_metric_duration', $invalidDuration->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_submit_flow_metric_verifies_attempt_ownership_and_exam_match(): void
    {
        $this->bootstrapSubmitFlowMetricRestScaffold();
        $this->useFakeMetricsRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new RestSubmitFlowMetricFakeWpdb([
            88 => [
                'id' => 88,
                'exam_id' => 9,
                'student_id' => 99,
                'status' => 'completed',
                'finished_at' => '2026-03-24 15:10:00',
            ],
            89 => [
                'id' => 89,
                'exam_id' => 10,
                'student_id' => 7,
                'status' => 'completed',
                'finished_at' => '2026-03-24 15:10:00',
            ],
        ]);

        $forbidden = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 88,
            'exam_id' => 9,
            'event' => 'finish_result_ready',
            'event_key' => 'ownership-test',
            'client_event_at_ms' => 1710000006400,
        ]));
        $examMismatch = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 89,
            'exam_id' => 9,
            'event' => 'finish_result_ready',
            'event_key' => 'exam-mismatch-test',
            'client_event_at_ms' => 1710000006400,
        ]));

        self::assertTrue(is_wp_error($forbidden));
        self::assertSame('forbidden', $forbidden->get_error_code());
        self::assertTrue(is_wp_error($examMismatch));
        self::assertSame('invalid_submit_flow_metric_exam', $examMismatch->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_submit_flow_metric_returns_safe_skipped_response_when_metrics_redis_is_unavailable(): void
    {
        $this->bootstrapSubmitFlowMetricRestScaffold();
        $this->setMetricsRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new RestSubmitFlowMetricFakeWpdb([
            88 => [
                'id' => 88,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'completed',
                'finished_at' => '2026-03-24 15:10:00',
            ],
        ]);

        $response = CBT_REST::submit_flow_metric(new WP_REST_Request([], [
            'attempt_id' => 88,
            'exam_id' => 9,
            'event' => 'finish_result_ready',
            'event_key' => 'safe-skip',
            'client_event_at_ms' => 1710000006400,
        ]));

        self::assertSame(['ok' => true, 'duplicate' => false, 'skipped' => true], $response);
    }

    private function bootstrapSubmitFlowMetricRestScaffold(): void
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
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-submit-flow-metrics-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
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

    private function setMetricsRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Submit_Flow_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis unavailable');
    }
}

final class RestSubmitFlowMetricFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    /** @var array<int,array<string,mixed>> */
    private array $attemptRows;

    /**
     * @param array<int,array<string,mixed>> $attemptRows
     */
    public function __construct(array $attemptRows = [])
    {
        $this->attemptRows = $attemptRows;
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
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $attempt_id = isset($args[0]) ? (int) $args[0] : 0;
        $attempt = $this->attemptRows[$attempt_id] ?? null;
        return is_array($attempt) ? $attempt : null;
    }
}
