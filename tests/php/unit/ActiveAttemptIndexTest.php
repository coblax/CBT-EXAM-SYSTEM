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

    public function test_set_active_attempt_accepts_attempt_id_alias(): void
    {
        CBT_Active_Attempt_Index::set_active_attempt([
            'attempt_id' => 99,
            'exam_id' => 16,
            'student_id' => 8,
            'status' => 'in_progress',
        ]);

        self::assertSame(99, CBT_Active_Attempt_Index::get_active_attempt_id(8, 16));
    }

    public function test_read_active_attempt_refreshes_redis_ttl(): void
    {
        $GLOBALS['cbt_test_current_time_timestamp'] = 1000;
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 100,
            'exam_id' => 17,
            'student_id' => 9,
            'status' => 'in_progress',
        ]);

        $redis = $this->getRedisClient();
        $key = 'cbt_active_attempt:user:9:exam:17';
        self::assertSame(44100, $redis->ttl($key));

        $GLOBALS['cbt_test_current_time_timestamp'] = 1100;
        self::assertSame(100, CBT_Active_Attempt_Index::get_active_attempt_id(9, 17));

        self::assertSame(44100, $redis->ttl($key));
    }

    public function test_clear_active_attempt_without_guard_deletes_entry(): void
    {
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 101,
            'exam_id' => 18,
            'student_id' => 10,
            'status' => 'in_progress',
        ]);

        CBT_Active_Attempt_Index::clear_active_attempt(10, 18);

        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(10, 18));
    }

    public function test_set_active_attempt_ignores_redis_write_failure(): void
    {
        $GLOBALS['cbt_test_redis_fail_keys'] = ['cbt_active_attempt:user:11:exam:19'];

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 102,
            'exam_id' => 19,
            'student_id' => 11,
            'status' => 'in_progress',
        ]);

        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(11, 19));
    }

    public function test_set_active_attempt_ignores_invalid_contexts(): void
    {
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 103,
            'exam_id' => 20,
            'student_id' => 12,
            'status' => 'completed',
        ]);
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 104,
            'exam_id' => 0,
            'student_id' => 12,
            'status' => 'in_progress',
        ]);

        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(12, 20));
        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(12, 0));
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

    private function getRedisClient(): CBT_Test_Redis_Client
    {
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);
        $redisProperty = $reflection->getProperty('active_attempt_redis');
        $redisProperty->setAccessible(true);
        $redis = $redisProperty->getValue(null);
        self::assertInstanceOf(CBT_Test_Redis_Client::class, $redis);

        return $redis;
    }
}
