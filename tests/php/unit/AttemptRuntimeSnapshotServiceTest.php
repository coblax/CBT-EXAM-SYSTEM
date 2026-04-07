<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AttemptRuntimeSnapshotServiceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_rebuild_attempt_snapshots_succeeds_and_returns_ready_diagnostics(): void
    {
        $this->bootstrapRuntimeSnapshotServiceScaffold();
        $this->useFakeAttemptSessionRedis();
        $this->useFakeAttemptContractRedis();

        $result = CBT_Attempt_Runtime_Snapshot_Service::rebuild_attempt_snapshots(501, 77);

        self::assertTrue($result['ok']);
        self::assertSame(501, $result['attempt_id']);
        self::assertSame(77, $result['exam_id']);
        self::assertSame('ready', $result['session_snapshot']['snapshot_status']);
        self::assertSame('ready', $result['contract_snapshot']['snapshot_status']);
    }

    #[RunInSeparateProcess]
    public function test_rebuild_attempt_snapshots_fails_safely_for_exam_mismatch(): void
    {
        $this->bootstrapRuntimeSnapshotServiceScaffold();

        $result = CBT_Attempt_Runtime_Snapshot_Service::rebuild_attempt_snapshots(501, 54);

        self::assertFalse($result['ok']);
        self::assertSame(501, $result['attempt_id']);
        self::assertSame(54, $result['exam_id']);
        self::assertStringContainsString('tidak termasuk exam', strtolower($result['message']));
    }

    private function bootstrapRuntimeSnapshotServiceScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-session-snapshot-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-question-contract-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-runtime-snapshot-service.php';

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static function rebuild_attempt_runtime_snapshots(int $attempt_id, int $expected_exam_id = 0): array
    {
        if ($attempt_id !== 501 || ($expected_exam_id > 0 && $expected_exam_id !== 77)) {
            return [
                'ok' => false,
                'attempt_id' => $attempt_id,
                'exam_id' => $expected_exam_id,
                'message' => 'Attempt tidak termasuk exam yang sedang dipantau.',
                'session_snapshot' => [],
                'contract_snapshot' => [],
            ];
        }

        CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'started_at' => '2026-04-04 07:00:00',
            'duration_minutes' => 90,
            'extra_time_minutes' => 5,
            'question_count' => 2,
            'question_order_signature' => 'runtime-sig-501',
            'show_student_result' => 1,
            'enable_calculator' => 1,
        ]);
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
                901 => ['A', 'B'],
            ],
        ]);

        return [
            'ok' => true,
            'attempt_id' => 501,
            'exam_id' => 77,
            'message' => 'Runtime snapshot berhasil diperbarui dari sumber live.',
            'session_snapshot' => CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics(501),
            'contract_snapshot' => CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(501),
        ];
    }
}
PHP);
        }
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
