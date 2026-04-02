<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-cache.php';

final class ExamAvailabilitySnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeRedisClient();
    }

    public function test_get_student_snapshot_hydrates_redis_and_reuses_cached_value_until_minute_bucket_changes(): void
    {
        $calls = 0;
        $producer = static function () use (&$calls): array {
            $calls++;

            return [
                'items' => [
                    [
                        'id' => 15,
                        'title' => 'Matematika',
                        'latest_attempt_id' => 91,
                    ],
                ],
                'current_user' => [
                    'user_id' => 7,
                    'role' => 'student',
                ],
            ];
        };

        $first = CBT_Exam_Availability_Cache::get_student_snapshot(7, $producer);
        $second = CBT_Exam_Availability_Cache::get_student_snapshot(7, $producer);

        self::assertSame(1, $calls);
        self::assertSame(91, $first['items'][0]['latest_attempt_id']);
        self::assertSame($first, $second);
        self::assertNotSame([], $this->storedSnapshotKeys());

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353660;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:01:00';

        $third = CBT_Exam_Availability_Cache::get_student_snapshot(7, $producer);

        self::assertSame(2, $calls);
        self::assertSame($first['items'][0]['id'], $third['items'][0]['id']);
    }

    public function test_get_student_snapshot_uses_new_versioned_key_after_user_or_catalog_invalidation(): void
    {
        $calls = 0;
        $producer = static function () use (&$calls): array {
            $calls++;

            return [
                'items' => [
                    ['id' => 21, 'title' => 'Bahasa Indonesia'],
                ],
                'current_user' => [
                    'user_id' => 11,
                    'role' => 'student',
                ],
            ];
        };

        CBT_Exam_Availability_Cache::get_student_snapshot(11, $producer);
        self::assertSame(1, $calls);

        CBT_Cache::invalidate_user(11);
        CBT_Exam_Availability_Cache::get_student_snapshot(11, $producer);
        self::assertSame(2, $calls);

        CBT_Cache::invalidate_catalog();
        CBT_Exam_Availability_Cache::get_student_snapshot(11, $producer);
        self::assertSame(3, $calls);
    }

    private function useFakeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Availability_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    /**
     * @return array<int,string>
     */
    private function storedSnapshotKeys(): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key): bool {
            return is_string($key) && strpos($key, 'cbt_exam_availability:') === 0;
        }));
    }
}
