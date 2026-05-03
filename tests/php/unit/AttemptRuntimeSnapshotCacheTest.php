<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-session-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-question-contract-cache.php';

final class AttemptRuntimeSnapshotCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeAttemptSessionRedis();
        $this->useFakeAttemptContractRedis();
    }

    public function test_attempt_session_snapshot_diagnostics_report_ready_payload(): void
    {
        CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'started_at' => '2026-04-04 07:00:00',
            'duration_minutes' => 90,
            'extra_time_minutes' => 5,
            'question_count' => 8,
            'question_order_signature' => 'runtime-sig-501',
            'show_student_result' => 1,
            'enable_calculator' => 1,
        ]);

        $diagnostics = CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics(501);

        self::assertTrue($diagnostics['redis_available']);
        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame(8, $diagnostics['question_count']);
        self::assertSame('runtime-sig-501', $diagnostics['question_order_signature']);

        CBT_Attempt_Session_Snapshot_Cache::update_attempt_status(501, 'completed');
        $updated = CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot(501, static fn (): array => []);

        self::assertSame(2, (int) ($updated['snapshot_payload_version'] ?? 0));
        self::assertSame('completed', $updated['status']);
    }

    public function test_attempt_session_snapshot_discards_stale_payload_version(): void
    {
        CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'started_at' => '2026-04-04 07:00:00',
            'duration_minutes' => 90,
            'question_count' => 8,
            'question_order_signature' => 'runtime-sig-501',
        ]);

        $storageKey = 'cbt_attempt_session:attempt:501';
        $payload = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
        self::assertIsArray($payload);
        self::assertSame(2, (int) ($payload['snapshot_payload_version'] ?? 0));
        unset($payload['snapshot_payload_version']);
        $GLOBALS['cbt_test_redis_storage'][$storageKey] = wp_json_encode($payload);

        $producerCalls = 0;
        $rehydrated = CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot(501, static function (int $attemptId) use (&$producerCalls): array {
            $producerCalls++;

            return [
                'attempt_id' => $attemptId,
                'exam_id' => 77,
                'student_id' => 71,
                'status' => 'in_progress',
                'started_at' => '2026-04-04 07:00:00',
                'duration_minutes' => 90,
                'question_count' => 9,
                'question_order_signature' => 'runtime-sig-501-new',
            ];
        });

        self::assertSame(1, $producerCalls);
        self::assertSame(9, $rehydrated['question_count']);
        self::assertSame(2, (int) ($rehydrated['snapshot_payload_version'] ?? 0));
    }

    public function test_attempt_contract_snapshot_can_be_cleared(): void
    {
        CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'question_order_ids' => [901, 902],
            'question_number_map' => [901 => 1, 902 => 2],
            'question_order_signature' => 'runtime-sig-501',
            'question_manifest' => [
                ['id' => 901, 'question_number' => 1],
                ['id' => 902, 'question_number' => 2],
            ],
            'option_order_map' => [
                901 => ['A', 'B', 'C'],
            ],
        ]);

        $diagnostics = CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(501);

        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame(2, $diagnostics['question_count']);

        self::assertSame(1, CBT_Attempt_Question_Contract_Cache::clear_attempt_snapshot(501));

        $after_clear = CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(501);
        self::assertFalse($after_clear['snapshot_exists']);
        self::assertSame('miss', $after_clear['snapshot_status']);
    }

    public function test_attempt_contract_snapshot_discards_stale_payload_version(): void
    {
        CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'question_order_ids' => [901],
            'question_number_map' => [901 => 1],
            'question_order_signature' => 'runtime-sig-501',
            'question_manifest' => [
                ['id' => 901, 'question_number' => 1],
            ],
            'option_order_map' => [],
        ]);

        $storageKey = 'cbt_attempt_contract:attempt:501';
        $payload = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
        self::assertIsArray($payload);
        self::assertSame(2, (int) ($payload['snapshot_payload_version'] ?? 0));
        unset($payload['snapshot_payload_version']);
        $GLOBALS['cbt_test_redis_storage'][$storageKey] = wp_json_encode($payload);

        $producerCalls = 0;
        $rehydrated = CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot(501, static function (int $attemptId) use (&$producerCalls): array {
            $producerCalls++;

            return [
                'attempt_id' => $attemptId,
                'exam_id' => 77,
                'student_id' => 71,
                'status' => 'in_progress',
                'question_order_ids' => [903],
                'question_number_map' => [903 => 1],
                'question_order_signature' => 'runtime-sig-501-new',
                'question_manifest' => [
                    ['id' => 903, 'question_number' => 1],
                ],
                'option_order_map' => [],
            ];
        });

        self::assertSame(1, $producerCalls);
        self::assertSame([903], $rehydrated['question_order_ids']);
        self::assertSame(2, (int) ($rehydrated['snapshot_payload_version'] ?? 0));
    }

    private function useFakeAttemptSessionRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Session_Snapshot_Cache::class);

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

    private function useFakeAttemptContractRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Question_Contract_Cache::class);

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
}
