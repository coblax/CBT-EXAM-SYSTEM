<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ActiveAttemptIndexStaleTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_set_and_get_active_attempt(): void
    {
        $this->bootstrapIndexScaffold();

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 42,
            'student_id' => 5,
            'exam_id' => 10,
            'status' => 'in_progress',
        ]);

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);

        self::assertSame(42, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_get_returns_zero_for_nonexistent(): void
    {
        $this->bootstrapIndexScaffold();

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(50, 100);

        self::assertSame(0, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_completed_attempt_cleared_on_read(): void
    {
        $this->bootstrapIndexScaffold();

        // Manually write a completed attempt to Redis
        $redis = $this->getRedis();
        $key = 'cbt_active_attempt:user:5:exam:10';
        $redis->setEx($key, 44100, json_encode([
            'attempt_id' => 42,
            'student_id' => 5,
            'exam_id' => 10,
            'status' => 'completed',
        ]));

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);

        self::assertSame(0, $attempt_id);
        // Key should be deleted
        self::assertFalse($redis->get($key));
    }

    #[RunInSeparateProcess]
    public function test_mismatched_student_id_cleared(): void
    {
        $this->bootstrapIndexScaffold();

        $redis = $this->getRedis();
        $key = 'cbt_active_attempt:user:5:exam:10';
        $redis->setEx($key, 44100, json_encode([
            'attempt_id' => 42,
            'student_id' => 99, // Wrong student
            'exam_id' => 10,
            'status' => 'in_progress',
        ]));

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);

        self::assertSame(0, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_mismatched_exam_id_cleared(): void
    {
        $this->bootstrapIndexScaffold();

        $redis = $this->getRedis();
        $key = 'cbt_active_attempt:user:5:exam:10';
        $redis->setEx($key, 44100, json_encode([
            'attempt_id' => 42,
            'student_id' => 5,
            'exam_id' => 99, // Wrong exam
            'status' => 'in_progress',
        ]));

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);

        self::assertSame(0, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_clear_active_attempt_removes_entry(): void
    {
        $this->bootstrapIndexScaffold();

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 42,
            'student_id' => 5,
            'exam_id' => 10,
            'status' => 'in_progress',
        ]);

        CBT_Active_Attempt_Index::clear_active_attempt(5, 10, 42);

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);
        self::assertSame(0, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_clear_does_not_remove_different_attempt(): void
    {
        $this->bootstrapIndexScaffold();

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 42,
            'student_id' => 5,
            'exam_id' => 10,
            'status' => 'in_progress',
        ]);

        // Try to clear a different attempt_id
        CBT_Active_Attempt_Index::clear_active_attempt(5, 10, 99);

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);
        self::assertSame(42, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_set_ignores_non_in_progress_status(): void
    {
        $this->bootstrapIndexScaffold();

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 42,
            'student_id' => 60,
            'exam_id' => 110,
            'status' => 'completed',
        ]);

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(60, 110);
        self::assertSame(0, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_set_ignores_invalid_ids(): void
    {
        $this->bootstrapIndexScaffold();

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 0,
            'student_id' => 70,
            'exam_id' => 120,
            'status' => 'in_progress',
        ]);

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(70, 120);
        self::assertSame(0, $attempt_id);
    }

    #[RunInSeparateProcess]
    public function test_redis_unavailable_returns_zero(): void
    {
        $GLOBALS['cbt_test_redis_should_fail_connect'] = true;
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';

        // Mark as already attempted with failure
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);
        $redisProp = $reflection->getProperty('active_attempt_redis');
        $redisProp->setAccessible(true);
        $redisProp->setValue(null, false);
        $attemptedProp = $reflection->getProperty('active_attempt_redis_connection_attempted');
        $attemptedProp->setAccessible(true);
        $attemptedProp->setValue(null, true);

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(80, 130);

        self::assertSame(0, $attempt_id);
        self::assertFalse(CBT_Active_Attempt_Index::is_available());
    }

    #[RunInSeparateProcess]
    public function test_malformed_json_cleared_on_read(): void
    {
        $this->bootstrapIndexScaffold();

        $redis = $this->getRedis();
        $key = 'cbt_active_attempt:user:5:exam:10';
        $redis->setEx($key, 44100, 'not-valid-json{{{');

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id(5, 10);

        self::assertSame(0, $attempt_id);
        self::assertFalse($redis->get($key));
    }

    private function bootstrapIndexScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';
        $this->resetRedis();
    }

    private function resetRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);
        foreach (['active_attempt_redis', 'active_attempt_redis_connection_attempted', 'active_attempt_redis_last_connection_error'] as $prop) {
            if (!$reflection->hasProperty($prop)) {
                continue;
            }
            $property = $reflection->getProperty($prop);
            $property->setAccessible(true);
            if (str_contains($prop, 'attempted')) {
                $property->setValue(null, false);
            } elseif (str_contains($prop, 'error')) {
                $property->setValue(null, '');
            } else {
                $property->setValue(null, null);
            }
        }
    }

    private function getRedis(): Redis
    {
        CBT_Active_Attempt_Index::is_available();
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);
        $prop = $reflection->getProperty('active_attempt_redis');
        $prop->setAccessible(true);
        return $prop->getValue(null);
    }
}
