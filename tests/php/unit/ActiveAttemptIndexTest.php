<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';

final class ActiveAttemptIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeRedisClient();
    }

    public function test_set_and_get_active_attempt_id(): void
    {
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 88,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        self::assertSame(88, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    public function test_clear_active_attempt_respects_optional_attempt_id_guard(): void
    {
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 88,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        CBT_Active_Attempt_Index::clear_active_attempt(7, 15, 77);
        self::assertSame(88, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));

        CBT_Active_Attempt_Index::clear_active_attempt(7, 15, 88);
        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    private function useFakeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);

        $redisProperty = $reflection->getProperty('active_attempt_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('active_attempt_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('active_attempt_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}
