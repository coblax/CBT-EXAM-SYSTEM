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

    public function test_get_student_snapshot_auto_heals_minute_rollover_without_rebuilding_payload(): void
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

        self::assertSame(1, $calls);
        self::assertSame($first['items'][0]['id'], $third['items'][0]['id']);
        self::assertSame('ready', CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(7)['snapshot_status']);
        self::assertSame('auto_healed', CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(7)['repair_status']);
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

    public function test_runtime_version_changed_fallback_writes_prepared_current_and_marks_repaired(): void
    {
        $calls = 0;
        $producer = static function () use (&$calls): array {
            $calls++;

            return [
                'items' => [
                    [
                        'id' => 221,
                        'title' => 'Runtime Repair',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                ],
                'current_user' => [
                    'user_id' => 28,
                    'display_name' => 'Repair User',
                ],
            ];
        };

        CBT_Exam_Availability_Cache::get_student_snapshot(28, $producer);
        self::assertSame(1, $calls);

        CBT_Cache::invalidate_user(28);

        $snapshot = CBT_Exam_Availability_Cache::get_student_snapshot(28, $producer);
        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(28);

        self::assertSame(2, $calls);
        self::assertSame(221, $snapshot['items'][0]['id']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame('prepared', $diagnostics['snapshot_source']);
        self::assertSame('repaired', $diagnostics['repair_status']);
        self::assertStringContainsString('prepared current', $diagnostics['repair_message']);
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
        self::assertGreaterThanOrEqual(1, $diagnostics['current_catalog_version']);
        self::assertGreaterThanOrEqual(1, $diagnostics['current_user_version']);
        self::assertGreaterThan(0, $diagnostics['current_minute_bucket']);
        self::assertSame('minute', $diagnostics['detected_snapshot_source']);
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

    public function test_diagnostics_reports_minute_rollover_reason_for_missed_minute_snapshot(): void
    {
        CBT_Exam_Availability_Cache::warm_student_snapshot(61, static function (): array {
            return [
                'items' => [
                    ['id' => 601, 'title' => 'Ujian Minute'],
                ],
                'current_user' => [
                    'user_id' => 61,
                    'display_name' => 'Minute User',
                ],
            ];
        });

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353660;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:01:00';

        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(61);

        self::assertSame('miss', $diagnostics['snapshot_status']);
        self::assertSame('minute_rollover', $diagnostics['snapshot_miss_reason']);
        self::assertSame('Minute rollover', $diagnostics['snapshot_miss_reason_label']);
        self::assertSame('minute', $diagnostics['detected_snapshot_source']);
        self::assertGreaterThan(0, $diagnostics['detected_minute_bucket']);
        self::assertStringContainsString('MISS karena minute rollover', $diagnostics['snapshot_message']);
    }

    public function test_diagnostics_reports_version_changed_reason_for_missed_stale_snapshot(): void
    {
        CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(62, static function (): array {
            return [
                'items' => [
                    ['id' => 602, 'title' => 'Ujian Version'],
                ],
                'current_user' => [
                    'user_id' => 62,
                    'display_name' => 'Version User',
                ],
            ];
        });

        CBT_Cache::invalidate_user(62);

        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(62);

        self::assertSame('miss', $diagnostics['snapshot_status']);
        self::assertSame('version_changed', $diagnostics['snapshot_miss_reason']);
        self::assertSame('Version berubah', $diagnostics['snapshot_miss_reason_label']);
        self::assertSame('prepared', $diagnostics['detected_snapshot_source']);
        self::assertGreaterThanOrEqual(1, $diagnostics['detected_catalog_version']);
        self::assertGreaterThanOrEqual(1, $diagnostics['detected_user_version']);
        self::assertStringContainsString('MISS karena version berubah', $diagnostics['snapshot_message']);
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

    public function test_write_prepared_student_snapshot_persists_payload_without_readback(): void
    {
        $written = CBT_Exam_Availability_Cache::write_prepared_student_snapshot(44, [
            'items' => [
                [
                    'id' => 101,
                    'title' => 'Batch Warm',
                    'availability_reason' => 'ok',
                    'is_available_now' => 1,
                ],
            ],
            'current_user' => [
                'user_id' => 44,
                'display_name' => 'Batch User',
                'username' => 'batch-user',
                'kode_kelas' => 'XI-A',
                'kode_ruang' => 'R1',
            ],
        ]);

        self::assertTrue($written);
        self::assertSame('prepared', CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(44)['snapshot_source']);
    }

    public function test_write_prepared_student_snapshots_batches_pipeline_write_and_fallback(): void
    {
        $results = CBT_Exam_Availability_Cache::write_prepared_student_snapshots([
            51 => [
                'items' => [
                    ['id' => 151, 'title' => 'Batch 1', 'availability_reason' => 'ok', 'is_available_now' => 1],
                ],
                'current_user' => ['user_id' => 51, 'display_name' => 'Batch 1'],
            ],
            52 => [
                'items' => [
                    ['id' => 152, 'title' => 'Batch 2', 'availability_reason' => 'ok', 'is_available_now' => 1],
                ],
                'current_user' => ['user_id' => 52, 'display_name' => 'Batch 2'],
            ],
        ]);

        self::assertTrue($results[51]);
        self::assertTrue($results[52]);
        self::assertCount(1, (array) ($GLOBALS['cbt_test_redis_pipeline_batches'] ?? []));

        $GLOBALS['cbt_test_redis_pipeline_disabled'] = true;
        $GLOBALS['cbt_test_redis_pipeline_batches'] = [];

        $fallbackResults = CBT_Exam_Availability_Cache::write_prepared_student_snapshots([
            53 => [
                'items' => [
                    ['id' => 153, 'title' => 'Fallback', 'availability_reason' => 'ok', 'is_available_now' => 1],
                ],
                'current_user' => ['user_id' => 53, 'display_name' => 'Fallback'],
            ],
        ]);

        self::assertTrue($fallbackResults[53]);
        self::assertSame([], $GLOBALS['cbt_test_redis_pipeline_batches']);
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
