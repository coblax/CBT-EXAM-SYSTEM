<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-gate-service.php';

final class StartAttemptGateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeGateRedis();
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;
    }

    public function test_evaluate_request_queues_after_capacity_and_reuses_existing_ticket(): void
    {
        for ($index = 1; $index <= 50; $index++) {
            $result = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 1000 + $index);
            self::assertSame('admitted', $result['mode']);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71);
        $queuedAgain = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71);

        self::assertSame('queued', $queued['mode']);
        self::assertNotSame('', $queued['queue_ticket']);
        self::assertSame(1, $queued['queue_position']);
        self::assertSame(1000.0, (float) $queued['queue_ticket_created_at']);
        self::assertSame($queued['queue_ticket'], $queuedAgain['queue_ticket']);
        self::assertSame(1, $queuedAgain['queue_position']);
        self::assertSame(1000.0, (float) $queuedAgain['queue_ticket_created_at']);

        $diagnostics = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(77);
        self::assertSame('GATED', $diagnostics['status_label']);
        self::assertSame(1, $diagnostics['queue_depth']);
    }

    public function test_evaluate_request_prunes_stale_head_ticket_and_admits_next_ticket(): void
    {
        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(77, 2000 + $index);
        }

        $queuedFirst = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71);
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.1;
        $queuedSecond = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 72);

        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1062.0;

        $admitted = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 72, (string) $queuedSecond['queue_ticket']);

        self::assertSame('queued', $queuedFirst['mode']);
        self::assertSame('queued', $queuedSecond['mode']);
        self::assertSame('admitted', $admitted['mode']);

        $diagnostics = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(77);
        self::assertSame(0, $diagnostics['queue_depth']);
        self::assertSame('OPEN', $diagnostics['status_label']);
    }

    public function test_evaluate_request_returns_disabled_when_gate_redis_is_unavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);

        $redisProperty = $reflection->getProperty('gate_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('gate_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('gate_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis offline');

        $result = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71);
        $diagnostics = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(77);

        self::assertSame('disabled', $result['mode']);
        self::assertSame('DISABLED', $diagnostics['status_label']);
        self::assertFalse($diagnostics['redis_available']);
    }

    public function test_get_ticket_status_reports_queued_and_admitted_without_consuming_the_ticket(): void
    {
        for ($index = 1; $index <= 50; $index++) {
            CBT_Start_Attempt_Gate_Service::evaluate_request(77, 3000 + $index);
        }

        $queued = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71);
        $queuedStatus = CBT_Start_Attempt_Gate_Service::get_ticket_status(77, 71, (string) $queued['queue_ticket']);

        self::assertSame('queued', $queuedStatus['mode']);
        self::assertSame(1, $queuedStatus['queue_position']);
        self::assertSame((string) $queued['queue_ticket'], (string) $queuedStatus['queue_ticket']);
        self::assertSame(1000.0, (float) $queuedStatus['queue_ticket_created_at']);

        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.6;
        $admittedStatus = CBT_Start_Attempt_Gate_Service::get_ticket_status(77, 71, (string) $queued['queue_ticket']);

        self::assertSame('admitted', $admittedStatus['mode']);
        self::assertSame((string) $queued['queue_ticket'], (string) $admittedStatus['queue_ticket']);

        $stillQueued = CBT_Start_Attempt_Gate_Service::get_exam_diagnostics(77);
        self::assertSame(1, $stillQueued['queue_depth']);

        $admitted = CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71, (string) $queued['queue_ticket']);
        self::assertSame('admitted', $admitted['mode']);
    }

    public function test_get_ticket_status_returns_not_found_when_ticket_is_missing(): void
    {
        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(77, 71, 'missing-ticket');

        self::assertSame('not_found', $status['mode']);
        self::assertSame('', $status['queue_ticket']);
    }

    public function test_get_ticket_status_returns_disabled_when_gate_redis_is_unavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);

        $redisProperty = $reflection->getProperty('gate_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('gate_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('gate_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis offline');

        $status = CBT_Start_Attempt_Gate_Service::get_ticket_status(77, 71, 'ticket-1');

        self::assertSame('disabled', $status['mode']);
    }

    private function useFakeGateRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);

        $redisProperty = $reflection->getProperty('gate_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('gate_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('gate_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}
