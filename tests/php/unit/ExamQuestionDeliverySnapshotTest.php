<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';

if (!class_exists('CBT_REST')) {
    class CBT_REST
    {
        public static array $warmedExamIds = [];
        public static array $warmedStartExamIds = [];
        public static array $warmedSubmissionContextExamIds = [];

        public static function warm_exam_question_delivery_snapshot(int $exam_id): void
        {
            self::$warmedExamIds[] = $exam_id;
            CBT_Exam_Question_Delivery_Cache::warm_exam_payload($exam_id, static function (int $target_exam_id): array {
                return [
                    [
                        'id' => 900 + $target_exam_id,
                        'exam_id' => $target_exam_id,
                        'question_text' => 'Snapshot exam ' . $target_exam_id,
                        'question_type' => 'multiple_choice',
                        'points' => 1,
                        'options' => [],
                    ],
                ];
            });
        }

        public static function warm_exam_start_attempt_snapshot(int $exam_id): void
        {
            self::$warmedStartExamIds[] = $exam_id;
            CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($exam_id, static function (int $target_exam_id): array {
                return [
                    'exam_id' => $target_exam_id,
                    'question_ids' => [2000 + $target_exam_id],
                    'question_count' => 1,
                    'question_number_map' => [2000 + $target_exam_id => 1],
                    'randomize_questions' => 0,
                    'randomize_options' => 0,
                    'duration_minutes' => 75,
                    'show_student_result' => 0,
                    'enable_calculator' => 1,
                    'option_randomization_tokens_by_question' => [],
                ];
            });
        }

        public static function warm_exam_submission_context_snapshot(int $exam_id): void
        {
            self::$warmedSubmissionContextExamIds[] = $exam_id;
            CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
        }
    }
}

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

    public function test_get_exam_payload_discards_stale_payload_version(): void
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
                    'options' => [],
                ],
            ];
        };

        CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, $producer);
        $storageKey = $this->storedRedisKeys()[0] ?? '';
        self::assertNotSame('', $storageKey);
        $payload = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
        self::assertIsArray($payload);
        self::assertSame(2, (int) ($payload['snapshot_payload_version'] ?? 0));
        unset($payload['snapshot_payload_version']);
        $GLOBALS['cbt_test_redis_storage'][$storageKey] = wp_json_encode($payload);

        $second = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, $producer);

        self::assertSame(2, $producerCalls);
        self::assertSame(201, $second[0]['id']);
    }

    public function test_get_exam_payload_redacts_sensitive_keys_for_new_object_map_types(): void
    {
        $payload = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, static function (int $examId): array {
            return [
                [
                    'id' => 301,
                    'exam_id' => $examId,
                    'question_text' => 'Matching',
                    'question_type' => 'matching',
                    'points' => 5,
                    'correct_text' => '{"1":9101}',
                    'options' => [
                        ['id' => 9101, 'option_key' => 'A', 'option_text' => 'Jakarta', 'is_correct' => 1],
                    ],
                    'matching_meta' => [
                        'items' => [
                            ['key' => '1', 'text' => 'Ibu kota', 'correct_option_id' => 9101],
                        ],
                    ],
                ],
                [
                    'id' => 302,
                    'exam_id' => $examId,
                    'question_text' => 'Cloze [DROPDOWN_1]',
                    'question_type' => 'cloze_dropdown',
                    'points' => 5,
                    'cloze_dropdown_meta' => [
                        'blanks' => [
                            [
                                'key' => '1',
                                'options' => [
                                    ['id' => 9201, 'option_text' => 'Bandung', 'is_correct' => 0],
                                    ['id' => 9202, 'option_text' => 'Jakarta', 'is_correct' => 1],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 303,
                    'exam_id' => $examId,
                    'question_text' => 'Categorization',
                    'question_type' => 'categorization',
                    'points' => 5,
                    'options' => [
                        ['id' => 9301, 'option_key' => 'A', 'option_text' => 'Mamalia', 'is_correct' => 0],
                    ],
                    'categorization_meta' => [
                        'items' => [
                            ['key' => '1', 'text' => 'Kucing', 'correct_option_id' => 9301, 'correct_category_index' => 1],
                        ],
                    ],
                ],
                [
                    'id' => 304,
                    'exam_id' => $examId,
                    'question_text' => 'Table',
                    'question_type' => 'table_completion',
                    'points' => 5,
                    'table_completion_meta' => [
                        'cells' => [
                            ['key' => 'A1', 'type' => 'text', 'text' => '', 'correct_text' => 'Tokyo'],
                            [
                                'key' => 'B1',
                                'type' => 'dropdown',
                                'text' => '',
                                'options' => [
                                    ['id' => 9401, 'option_text' => 'Osaka', 'is_correct' => 0],
                                    ['id' => 9402, 'option_text' => 'Tokyo', 'is_correct' => 1],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        self::assertCount(4, $payload);
        foreach ($payload as $question) {
            self::assertDeliveryPayloadDoesNotContainSensitiveKeys($question);
        }

        $storageKey = $this->storedRedisKeys()[0] ?? '';
        self::assertNotSame('', $storageKey);
        $cached = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
        self::assertIsArray($cached);
        self::assertDeliveryPayloadDoesNotContainSensitiveKeys($cached);
    }

    public function test_get_exam_payload_redacts_sensitive_keys_for_all_question_types(): void
    {
        $payload = CBT_Exam_Question_Delivery_Cache::get_exam_payload(56, static function (int $examId): array {
            $common = [
                'exam_id' => $examId,
                'points' => 5,
                'correct_text' => 'server-only',
                'short_answer_correct_text' => 'server-only',
                'correct_option_ids' => [1],
                'true_false_correct_value' => 1,
                'true_false_option_value_by_id' => ['1' => 1],
                'short_answer_values' => ['server-only'],
                'true_false_matrix_answers' => ['1' => 'true'],
                'matching_correct_option_ids_by_key' => ['1' => 1],
                'cloze_dropdown_correct_option_ids_by_key' => ['1' => 1],
                'categorization_correct_option_ids_by_key' => ['1' => 1],
                'table_completion_answers_by_key' => ['A1' => ['cell_type' => 'text', 'correct_values' => ['server-only']]],
            ];

            return [
                $common + [
                    'id' => 401,
                    'question_text' => 'MC',
                    'question_type' => 'multiple_choice',
                    'options' => [
                        ['id' => 1, 'option_key' => 'A', 'option_text' => 'A', 'is_correct' => 1, 'correct_position' => 1],
                    ],
                ],
                $common + [
                    'id' => 402,
                    'question_text' => 'MA',
                    'question_type' => 'multiple_answer',
                    'options' => [
                        ['id' => 2, 'option_key' => 'A', 'option_text' => 'A', 'is_correct' => 1],
                    ],
                ],
                $common + [
                    'id' => 403,
                    'question_text' => 'TF',
                    'question_type' => 'true_false',
                    'options' => [
                        ['id' => 3, 'option_key' => 'T', 'option_text' => 'Benar', 'correct_value' => 1],
                    ],
                ],
                $common + [
                    'id' => 404,
                    'question_text' => 'TF Matrix',
                    'question_type' => 'true_false_matrix',
                    'true_false_matrix_meta' => [
                        'items' => [
                            ['key' => '1', 'text' => 'Pernyataan', 'correct_value' => 'true'],
                        ],
                    ],
                ],
                $common + [
                    'id' => 405,
                    'question_text' => 'Short Answer',
                    'question_type' => 'short_answer',
                    'short_answer_meta' => [
                        'input_keys' => ['A'],
                        'correct_values' => ['server-only'],
                    ],
                ],
                $common + [
                    'id' => 406,
                    'question_text' => 'Essay',
                    'question_type' => 'essay',
                    'essay_rubric' => 'Rubrik boleh tampil.',
                ],
                $common + [
                    'id' => 407,
                    'question_text' => 'Ordering',
                    'question_type' => 'ordering',
                    'options' => [
                        ['id' => 4, 'option_key' => 'A', 'option_text' => 'A', 'correct_position' => 1],
                    ],
                    'ordering_correct_option_ids' => [4],
                ],
                $common + [
                    'id' => 408,
                    'question_text' => 'Matching',
                    'question_type' => 'matching',
                    'options' => [
                        ['id' => 5, 'option_key' => 'A', 'option_text' => 'A', 'is_correct' => 1],
                    ],
                    'matching_meta' => [
                        'items' => [
                            ['key' => '1', 'text' => 'Prompt', 'correct_option_id' => 5],
                        ],
                    ],
                ],
                $common + [
                    'id' => 409,
                    'question_text' => 'Cloze [DROPDOWN_1]',
                    'question_type' => 'cloze_dropdown',
                    'cloze_dropdown_meta' => [
                        'blanks' => [
                            [
                                'key' => '1',
                                'options' => [
                                    ['id' => 6, 'option_text' => 'A', 'is_correct' => 1, 'correct_option_key' => 'A'],
                                ],
                            ],
                        ],
                    ],
                ],
                $common + [
                    'id' => 410,
                    'question_text' => 'Categorization',
                    'question_type' => 'categorization',
                    'options' => [
                        ['id' => 7, 'option_key' => 'A', 'option_text' => 'Kategori', 'is_correct' => 1],
                    ],
                    'categorization_meta' => [
                        'items' => [
                            ['key' => '1', 'text' => 'Item', 'correct_option_id' => 7, 'correct_category_index' => 1],
                        ],
                    ],
                ],
                $common + [
                    'id' => 411,
                    'question_text' => 'Table',
                    'question_type' => 'table_completion',
                    'table_completion_meta' => [
                        'cells' => [
                            ['key' => 'A1', 'type' => 'text', 'text' => 'Kota', 'correct_text' => 'Tokyo', 'correct_values' => ['Tokyo']],
                            [
                                'key' => 'B1',
                                'type' => 'dropdown',
                                'text' => 'Negara',
                                'options' => [
                                    ['id' => 8, 'option_text' => 'Jepang', 'is_correct' => 1, 'correct_option_text' => 'Jepang'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        self::assertCount(11, $payload);
        self::assertSame([
            'multiple_choice',
            'multiple_answer',
            'true_false',
            'true_false_matrix',
            'short_answer',
            'essay',
            'ordering',
            'matching',
            'cloze_dropdown',
            'categorization',
            'table_completion',
        ], array_column($payload, 'question_type'));

        foreach ($payload as $question) {
            self::assertDeliveryPayloadDoesNotContainSensitiveKeys($question);
        }

        $storageKey = $this->storedRedisKeys()[0] ?? '';
        self::assertNotSame('', $storageKey);
        $cached = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
        self::assertIsArray($cached);
        self::assertDeliveryPayloadDoesNotContainSensitiveKeys($cached);
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
        self::assertSame('', $diagnostics['snapshot_miss_reason']);
        self::assertSame('', $diagnostics['snapshot_miss_reason_label']);
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

    public function test_get_exam_payload_diagnostics_reports_manual_clear_revision_changed_expired_and_not_prepared_reasons(): void
    {
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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

        CBT_Exam_Question_Delivery_Cache::clear_exam_payload(55);
        $afterManualClear = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55);
        self::assertSame('miss', $afterManualClear['snapshot_status']);
        self::assertSame('manual_clear', $afterManualClear['snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $afterManualClear['snapshot_miss_reason_label']);

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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
        CBT_Cache::invalidate_exam(55);
        $afterRevisionChanged = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55);
        self::assertSame('miss', $afterRevisionChanged['snapshot_status']);
        self::assertSame('revision_changed', $afterRevisionChanged['snapshot_miss_reason']);
        self::assertSame('Revision berubah', $afterRevisionChanged['snapshot_miss_reason_label']);

        foreach ($this->storedRedisKeys() as $storedKey) {
            unset($GLOBALS['cbt_test_redis_storage'][$storedKey]);
        }

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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
        $currentStorageKey = (string) CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55)['storage_key'];
        unset($GLOBALS['cbt_test_redis_storage'][$currentStorageKey]);
        $afterKeyMissing = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55);
        self::assertSame('miss', $afterKeyMissing['snapshot_status']);
        self::assertSame('expired_or_evicted', $afterKeyMissing['snapshot_miss_reason']);
        self::assertSame('TTL habis / ter-evict', $afterKeyMissing['snapshot_miss_reason_label']);

        $freshExamDiagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(77);
        self::assertSame('miss', $freshExamDiagnostics['snapshot_status']);
        self::assertSame('not_prepared', $freshExamDiagnostics['snapshot_miss_reason']);
        self::assertSame('Belum disiapkan', $freshExamDiagnostics['snapshot_miss_reason_label']);
    }

    public function test_get_exam_payload_diagnostics_reports_unavailable_state(): void
    {
        $this->setDeliveryRedisUnavailable();

        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55);

        self::assertFalse($diagnostics['redis_available']);
        self::assertFalse($diagnostics['snapshot_exists']);
        self::assertFalse($diagnostics['snapshot_valid']);
        self::assertSame('unavailable', $diagnostics['snapshot_status']);
        self::assertSame('redis_unavailable', $diagnostics['snapshot_miss_reason']);
        self::assertSame('Redis tidak tersedia', $diagnostics['snapshot_miss_reason_label']);
    }

    public function test_maybe_auto_heal_snapshot_repairs_revision_changed_reason(): void
    {
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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
        CBT_Cache::invalidate_exam(55);

        $repair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot(55, 'admin');

        self::assertTrue($repair['success']);
        self::assertSame('auto_healed', $repair['status']);
        self::assertSame('Dipulihkan otomatis dari revision exam terbaru', $repair['message']);
        self::assertSame('ready', $repair['diagnostics']['snapshot_status']);
        self::assertSame('auto_healed', $repair['diagnostics']['repair_status']);
        self::assertSame('Dipulihkan otomatis dari revision exam terbaru', $repair['diagnostics']['repair_message']);
    }

    public function test_maybe_auto_heal_snapshot_repairs_invalid_payload_and_expired_snapshot(): void
    {
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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
        $currentStorageKey = (string) CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55)['storage_key'];
        $GLOBALS['cbt_test_redis_storage'][$currentStorageKey] = '{"broken":';

        $invalidRepair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot(55, 'admin');

        self::assertTrue($invalidRepair['success']);
        self::assertSame('ready', $invalidRepair['diagnostics']['snapshot_status']);
        self::assertSame('Dipulihkan otomatis dari payload soal current', $invalidRepair['message']);

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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
        $currentStorageKey = (string) CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(55)['storage_key'];
        unset($GLOBALS['cbt_test_redis_storage'][$currentStorageKey]);

        $expiredRepair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot(55, 'admin');

        self::assertTrue($expiredRepair['success']);
        self::assertSame('ready', $expiredRepair['diagnostics']['snapshot_status']);
        self::assertSame('Dipulihkan otomatis dari payload soal current', $expiredRepair['message']);
    }

    public function test_maybe_auto_heal_snapshot_skips_manual_clear_not_prepared_and_unavailable_states(): void
    {
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(55, static function (int $examId): array {
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
        CBT_Exam_Question_Delivery_Cache::clear_exam_payload(55);

        $manualClearRepair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot(55, 'admin');
        self::assertFalse($manualClearRepair['success']);
        self::assertSame('miss', $manualClearRepair['diagnostics']['snapshot_status']);
        self::assertSame('manual_clear', $manualClearRepair['diagnostics']['snapshot_miss_reason']);

        $notPreparedRepair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot(77, 'admin');
        self::assertFalse($notPreparedRepair['success']);
        self::assertSame('miss', $notPreparedRepair['diagnostics']['snapshot_status']);
        self::assertSame('not_prepared', $notPreparedRepair['diagnostics']['snapshot_miss_reason']);

        $this->setDeliveryRedisUnavailable();
        $unavailableRepair = CBT_Exam_Question_Delivery_Cache::maybe_auto_heal_snapshot(55, 'admin');
        self::assertFalse($unavailableRepair['success']);
        self::assertSame('unavailable', $unavailableRepair['diagnostics']['snapshot_status']);
        self::assertSame('redis_unavailable', $unavailableRepair['diagnostics']['snapshot_miss_reason']);
    }

    private static function assertDeliveryPayloadDoesNotContainSensitiveKeys(array $payload): void
    {
        $sensitiveKeys = [
            'correct_text',
            'short_answer_correct_text',
            'is_correct',
            'correct_value',
            'correct_values',
            'correct_option_id',
            'correct_option_ids',
            'correct_option_ids_by_key',
            'correct_option_key',
            'correct_option_text',
            'correct_category_index',
            'correct_position',
            'ordering_correct_option_ids',
            'true_false_correct_value',
            'true_false_option_value_by_id',
            'short_answer_values',
            'true_false_matrix_answers',
            'matching_correct_option_ids_by_key',
            'cloze_dropdown_correct_option_ids_by_key',
            'categorization_correct_option_ids_by_key',
            'table_completion_answers_by_key',
        ];

        foreach ($payload as $key => $value) {
            self::assertNotContains((string) $key, $sensitiveKeys);
            if (is_array($value)) {
                self::assertDeliveryPayloadDoesNotContainSensitiveKeys($value);
            }
        }
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
