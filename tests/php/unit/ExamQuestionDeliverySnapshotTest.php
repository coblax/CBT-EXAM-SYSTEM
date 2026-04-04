<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';

final class ExamQuestionDeliverySnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeDeliveryRedis();
    }

    public function test_get_exam_payload_reuses_cached_revision_until_exam_version_changes(): void
    {
        $producerCalls = 0;
        $producer = static function (int $examId) use (&$producerCalls): array {
            $producerCalls++;

            return [
                [
                    'id' => 201,
                    'exam_id' => $examId,
                    'question_text' => 'Soal 1',
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [
                        ['id' => 9001, 'option_key' => 'A', 'option_text' => 'Pilihan A'],
                    ],
                ],
            ];
        };

        $first = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, $producer);
        $second = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, $producer);

        self::assertSame(1, $producerCalls);
        self::assertSame($first, $second);
        self::assertCount(1, $this->storedRedisKeys());

        CBT_Cache::invalidate_exam(55);
        $third = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, $producer);

        self::assertSame(2, $producerCalls);
        self::assertSame(201, $third[0]['id']);
        self::assertGreaterThanOrEqual(2, count($this->storedRedisKeys()));
    }

    public function test_get_exam_payload_falls_back_to_producer_when_redis_is_unavailable(): void
    {
        $this->setDeliveryRedisUnavailable();

        $producerCalls = 0;
        $payload = CBT_Exam_Question_Delivery_Cache::get_exam_payload(88, static function (int $examId) use (&$producerCalls): array {
            $producerCalls++;

            return [
                [
                    'id' => 301,
                    'exam_id' => $examId,
                    'question_text' => 'Fallback soal',
                    'question_type' => 'essay',
                    'points' => 10,
                    'options' => [],
                ],
            ];
        });

        self::assertSame(1, $producerCalls);
        self::assertSame('Fallback soal', $payload[0]['question_text']);
        self::assertSame([], $this->storedRedisKeys());
    }

    public function test_get_exam_payload_diagnostics_reports_ready_snapshot(): void
    {
        CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, static function (int $examId): array {
            return [
                [
                    'id' => 201,
                    'exam_id' => $examId,
                    'question_text' => 'Soal 1',
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [
                        ['id' => 9001, 'option_key' => 'A', 'option_text' => 'Pilihan A'],
                    ],
                ],
            ];
        });

        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55);

        self::assertTrue($diagnostics['redis_available']);
        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame(1, $diagnostics['snapshot_item_count']);
        self::assertSame([201], $diagnostics['preview_question_ids']);
        self::assertSame([
            [
                'id' => 201,
                'question_type' => 'multiple_choice',
                'points' => 5.0,
                'question_text_excerpt' => 'Soal 1',
                'option_count' => 1,
            ],
        ], $diagnostics['preview_items']);
        self::assertStringStartsWith('cbt_exam_delivery:exam:55:rev:', $diagnostics['storage_key']);
    }

    public function test_get_exam_payload_diagnostics_supports_preview_pagination(): void
    {
        CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, static function (int $examId): array {
            $items = [];
            for ($index = 0; $index < 9; $index++) {
                $items[] = [
                    'id' => 201 + $index,
                    'exam_id' => $examId,
                    'question_text' => 'Soal ' . ($index + 1),
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [
                        ['id' => 9001 + $index, 'option_key' => 'A', 'option_text' => 'Pilihan A'],
                    ],
                ];
            }

            return $items;
        });

        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55, 2, 7);

        self::assertSame(2, $diagnostics['preview_current_page']);
        self::assertSame(2, $diagnostics['preview_total_pages']);
        self::assertSame(7, $diagnostics['preview_per_page']);
        self::assertSame([208, 209], $diagnostics['preview_question_ids']);
        self::assertSame([208, 209], array_column($diagnostics['preview_items'], 'id'));
    }

    public function test_clear_exam_payload_removes_cached_keys_for_exam(): void
    {
        CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, static function (int $examId): array {
            return [
                [
                    'id' => 201,
                    'exam_id' => $examId,
                    'question_text' => 'Soal 1',
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [],
                ],
            ];
        });

        self::assertNotSame([], $this->storedRedisKeys());

        $deleted = CBT_Exam_Question_Delivery_Cache::clear_exam_payload(55);

        self::assertSame(1, $deleted);
        self::assertSame([], $this->storedRedisKeys());
    }

    public function test_get_exam_payload_diagnostics_reports_unavailable_state(): void
    {
        $this->setDeliveryRedisUnavailable();

        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55);

        self::assertFalse($diagnostics['redis_available']);
        self::assertFalse($diagnostics['snapshot_exists']);
        self::assertFalse($diagnostics['snapshot_valid']);
        self::assertSame('unavailable', $diagnostics['snapshot_status']);
    }

    private function useFakeDeliveryRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Question_Delivery_Cache::class);

        $redisProperty = $reflection->getProperty('delivery_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('delivery_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('delivery_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setDeliveryRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Question_Delivery_Cache::class);

        $redisProperty = $reflection->getProperty('delivery_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('delivery_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('delivery_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    /**
     * @return array<int,string>
     */
    private function storedRedisKeys(): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key): bool {
            return is_string($key) && strpos($key, 'cbt_exam_delivery:') === 0;
        }));
    }
}
