<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';

final class ExamStartAttemptSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeStartSnapshotRedis();
    }

    public function test_get_exam_snapshot_reuses_cached_revision_until_exam_version_changes(): void
    {
        $producerCalls = 0;
        $producer = static function (int $examId) use (&$producerCalls): array {
            $producerCalls++;

            return [
                'exam_id' => $examId,
                'question_ids' => [201, 202],
                'question_number_map' => [201 => 1, 202 => 2],
                'randomize_questions' => 1,
                'randomize_options' => 1,
                'option_randomization_tokens_by_question' => [
                    201 => ['9001', '9002'],
                ],
            ];
        };

        $first = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, $producer);
        $second = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, $producer);

        self::assertSame(1, $producerCalls);
        self::assertSame([201, 202], $first['question_ids']);
        self::assertSame($first['question_ids'], $second['question_ids']);
        self::assertSame($first['question_number_map'], $second['question_number_map']);
        self::assertSame($first['option_randomization_tokens_by_question'], $second['option_randomization_tokens_by_question']);
        self::assertCount(1, $this->storedRedisKeys());

        CBT_Cache::invalidate_exam(55);
        $third = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, $producer);

        self::assertSame(2, $producerCalls);
        self::assertSame([201, 202], $third['question_ids']);
        self::assertGreaterThanOrEqual(2, count($this->storedRedisKeys()));
    }

    public function test_get_exam_snapshot_diagnostics_reports_ready_snapshot(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        $diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);

        self::assertTrue($diagnostics['redis_available']);
        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame(1, $diagnostics['snapshot_item_count']);
        self::assertStringStartsWith('cbt_exam_start_attempt:exam:55:rev:', $diagnostics['storage_key']);
    }

    public function test_get_exam_snapshot_diagnostics_reports_invalid_snapshot_when_signature_mismatches(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        $storageKey = $this->storedRedisKeys()[0] ?? '';
        self::assertNotSame('', $storageKey);
        $payload = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
        self::assertIsArray($payload);
        $payload['revision_signature'] = 'stale-signature';
        $GLOBALS['cbt_test_redis_storage'][$storageKey] = wp_json_encode($payload);

        $diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);

        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertFalse($diagnostics['snapshot_valid']);
        self::assertSame('invalid', $diagnostics['snapshot_status']);
    }

    private function useFakeStartSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Start_Attempt_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('start_snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('start_snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('start_snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    /**
     * @return array<int,string>
     */
    private function storedRedisKeys(): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key): bool {
            return is_string($key) && strpos($key, 'cbt_exam_start_attempt:') === 0;
        }));
    }
}
