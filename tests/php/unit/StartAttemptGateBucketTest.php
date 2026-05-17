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
    public function test_ticket_status_returns_not_found_for_wrong_ticket(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        self::assertSame('queued', $queued['mode']);

        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 99, 'wrong-ticket-value');

        self::assertSame('not_found', $status['mode']);
        self::assertSame('', $status['queue_ticket']);
    }

    #[RunInSeparateProcess]
    public function test_ticket_lifecycle_create_poll_then_admit(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        self::assertSame('queued', $queued['mode']);

        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 99, $queued['queue_ticket']);
        self::assertSame('queued', $status['mode']);
        self::assertSame(1, $status['queue_position']);

        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.2;
        $admittable = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 99, $queued['queue_ticket']);

        self::assertSame('admitted', $admittable['mode']);
        self::assertSame($queued['queue_ticket'], $admittable['queue_ticket']);

        $admitted = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99, $queued['queue_ticket']);
        self::assertSame('admitted', $admitted['mode']);
        self::assertSame('', $admitted['queue_ticket']);
    }

    #[RunInSeparateProcess]
    public function test_ticket_status_can_poll_current_user_ticket_without_explicit_ticket(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 99);

        self::assertSame('queued', $status['mode']);
        self::assertSame($queued['queue_ticket'], $status['queue_ticket']);
        self::assertSame(1, $status['queue_position']);
    }

    #[RunInSeparateProcess]
    public function test_ticket_status_normalizes_noisy_ticket_value(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 99, '  ' . $queued['queue_ticket'] . '!! ');

        self::assertSame('queued', $status['mode']);
        self::assertSame($queued['queue_ticket'], $status['queue_ticket']);
    }

    #[RunInSeparateProcess]
    public function test_multiple_queued_users_keep_fifo_positions(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }

        $first = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        $second = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 100);

        self::assertSame(1, $first['queue_position']);
        self::assertSame(2, $second['queue_position']);

        $secondPoll = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 100, $second['queue_ticket']);

        self::assertSame('queued', $secondPoll['mode']);
        self::assertSame(2, $secondPoll['queue_position']);
        self::assertSame($second['queue_ticket'], $secondPoll['queue_ticket']);
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
    public function test_corrupt_bucket_payload_recovers_to_full_capacity(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        $redis = $this->getGateRedis();
        $redis->setEx('cbt_start_attempt_gate:bucket:exam:1', 120, '{broken-json');

        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 1);

        self::assertSame('admitted', $result['mode']);
        self::assertSame('', $result['queue_ticket']);
        self::assertGreaterThanOrEqual(49.0, $result['bucket_tokens']);
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
    public function test_corrupt_ticket_payload_is_pruned_from_queue(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
        }
        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        self::assertSame('queued', $queued['mode']);

        $redis = $this->getGateRedis();
        $redis->setEx('cbt_start_attempt_gate:ticket:' . $queued['queue_ticket'], 60, '{broken-json');

        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(1, 99, $queued['queue_ticket']);
        $diagnostics = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(1);

        self::assertSame('not_found', $status['mode']);
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
    public function test_global_diagnostics_sums_queue_depth_across_exams(): void
    {
        $this->bootstrapGateScaffold();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;

        for ($i = 1; $i <= 50; $i++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(1, $i);
            CBT_Start_Attempt_Gate_Service::evaluate_request(2, $i + 100);
        }
        CBT_Start_Attempt_Gate_Service::evaluate_request(1, 99);
        CBT_Start_Attempt_Gate_Service::evaluate_request(2, 199);

        $diagnostics = CBT_Start_Attempt_Gate_Service::get_global_diagnostics([1, 2, 3]);

        self::assertTrue($diagnostics['redis_available']);
        self::assertSame('GATED', $diagnostics['status_label']);
        self::assertSame(2, $diagnostics['queue_depth_total']);
        self::assertSame(2, $diagnostics['gated_exam_count']);
        self::assertSame(3, $diagnostics['exam_count']);
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
                    $property->setValue(null, true);
                } elseif ($prop === 'gate_redis_last_connection_error') {
                    $property->setValue(null, '');
                } else {
                    $property->setValue(null, new CBT_Test_Redis_Client());
                }
            }
        }
    }

    private function getGateRedis(): CBT_Test_Redis_Client
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);
        $property = $reflection->getProperty('gate_redis');
        $property->setAccessible(true);
        $redis = $property->getValue(null);
        self::assertInstanceOf(CBT_Test_Redis_Client::class, $redis);

        return $redis;
    }
}
