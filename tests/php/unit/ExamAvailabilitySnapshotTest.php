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

    public function test_warm_clear_and_diagnostics_manage_student_snapshot_operationally(): void
    {
        CBT_Exam_Availability_Cache::warm_student_snapshot(21, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 15,
                        'title' => 'Matematika',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                    [
                        'id' => 16,
                        'title' => 'Biologi',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                    [
                        'id' => 17,
                        'title' => 'Kimia',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                ],
                'current_user' => [
                    'user_id' => 21,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(21);

        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame('minute', $diagnostics['snapshot_source']);
        self::assertSame(3, $diagnostics['item_count']);
        self::assertSame('Salsa', $diagnostics['current_user_preview']['display_name']);
        self::assertSame('Matematika', $diagnostics['preview_items'][0]['title']);
        self::assertCount(3, $diagnostics['preview_items']);
        self::assertSame('Kimia', $diagnostics['preview_items'][2]['title']);

        CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(21, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 54,
                        'title' => 'Biologi',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                ],
                'current_user' => [
                    'user_id' => 21,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        $preparedDiagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(21);
        self::assertSame('prepared', $preparedDiagnostics['snapshot_source']);
        self::assertSame('Prepared snapshot availability siap dipakai untuk student GET /exams.', $preparedDiagnostics['snapshot_message']);

        self::assertGreaterThan(0, CBT_Exam_Availability_Cache::clear_student_snapshot(21));
        self::assertSame('miss', CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(21)['snapshot_status']);
    }

    public function test_prepared_snapshot_is_preferred_and_refreshes_dynamic_fields_at_read_time(): void
    {
        CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(31, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 88,
                        'title' => 'Ujian Fisika',
                        'starts_at' => '2026-03-24 12:10:00',
                        'ends_at' => '2026-03-24 13:10:00',
                        'target_kelas' => 'XI-A',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                        'server_now' => '2026-03-24 11:00:00',
                        'server_timezone' => 'UTC',
                    ],
                ],
                'current_user' => [
                    'user_id' => 31,
                    'display_name' => 'Alya',
                    'username' => 'alya',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        $calls = 0;
        $producer = static function () use (&$calls): array {
            $calls++;
            return ['items' => [], 'current_user' => null];
        };

        $beforeStart = CBT_Exam_Availability_Cache::get_student_snapshot(31, $producer);
        self::assertSame(0, $calls);
        self::assertSame('2026-03-24 12:00:00', $beforeStart['items'][0]['server_now']);
        self::assertSame('Asia/Jakarta', $beforeStart['items'][0]['server_timezone']);
        self::assertSame(0, $beforeStart['items'][0]['is_available_now']);
        self::assertSame('not_started', $beforeStart['items'][0]['availability_reason']);

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774354500;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:15:00';

        $afterStart = CBT_Exam_Availability_Cache::get_student_snapshot(31, $producer);
        self::assertSame(0, $calls);
        self::assertSame('2026-03-24 12:15:00', $afterStart['items'][0]['server_now']);
        self::assertSame(1, $afterStart['items'][0]['is_available_now']);
        self::assertSame('ok', $afterStart['items'][0]['availability_reason']);
        self::assertSame('prepared', CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(31)['snapshot_source']);
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
