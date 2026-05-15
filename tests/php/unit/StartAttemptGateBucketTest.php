<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class StartAttemptGateBucketTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_first_request_admitted_immediately_when_bucket_full(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 5);

        self::assertSame('admitted', $result['mode']);
        self::assertSame('', $result['queue_ticket']);
        self::assertLessThanOrEqual(50.0, $result['bucket_tokens']);
    }

    #[RunInSeparateProcess]
    public function test_request_queued_when_bucket_empty(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        // Drain the bucket by admitting many users
        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);

        self::assertSame('queued', $result['mode']);
        self::assertNotEmpty($result['queue_ticket']);
        self::assertGreaterThan(0, $result['queue_position']);
    }

    #[RunInSeparateProcess]
    public function test_same_user_reuses_existing_ticket(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        // Drain bucket
        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $first = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        $second = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99, $first['queue_ticket']);

        self::assertSame('queued', $second['mode']);
        self::assertSame($first['queue_ticket'], $second['queue_ticket']);
    }

    #[RunInSeparateProcess]
    public function test_bucket_refill_does_not_exceed_capacity(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        CBT_Start_Attempt_Gate_Service::evaluate_request(1, 1);

        // Advance time significantly to allow full refill
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 2000.0;

        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 2);

        self::assertSame('admitted', $result['mode']);
        self::assertLessThanOrEqual(50.0, $result['bucket_tokens']);
    }

    #[RunInSeparateProcess]
    public function test_queued_user_admitted_after_bucket_refills(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        // Drain bucket
        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        self::assertSame('queued', $queued['mode']);

        // Advance time enough to refill tokens AND past idle timeout so stale tickets are pruned
        // This leaves user 99 at position 1 with tokens available
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1070.0;

        // Re-evaluate the same user — should be admitted now since stale tickets pruned and bucket refilled
        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99, $queued['queue_ticket']);

        self::assertSame('admitted', $result['mode']);
    }

    #[RunInSeparateProcess]
    public function test_stale_tickets_pruned_after_idle_timeout(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        // Drain bucket and queue a user
        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }
        CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);

        // Advance past idle timeout (60s)
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1070.0;

        $diagnostics = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(1);

        self::assertSame(0, $diagnostics['queue_depth']);
    }

    #[RunInSeparateProcess]
    public function test_diagnostics_returns_correct_state(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        $diagnostics = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(1);

        self::assertTrue($diagnostics['redis_available']);
        self::assertSame('open', $diagnostics['status_slug']);
        self::assertSame(0, $diagnostics['queue_depth']);
        self::assertSame(50, $diagnostics['gate_capacity']);
    }

    #[RunInSeparateProcess]
    public function test_evaluate_request_returns_disabled_for_invalid_input(): void
    {
        $this->bootstrapGateScaffold();

        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(0, 0);

        self::assertSame('disabled', $result['mode']);
    }

    private function bootstrapGateScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-gate-service.php';

        // Reset gate Redis connection
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);
        foreach (['gate_redis', 'gate_redis_connection_attempted', 'gate_redis_last_connection_error'] as $prop) {
            if ($reflection->hasProperty($prop)) {
                $property = $reflection->getProperty($prop);
                $property->setAccessible(true);
                if ($prop === 'gate_redis_connection_attempted') {
                    $property->setValue(null, false);
                } elseif ($prop === 'gate_redis_last_connection_error') {
                    $property->setValue(null, '');
                } else {
                    $property->setValue(null, null);
                }
            }
        }
    }
}
