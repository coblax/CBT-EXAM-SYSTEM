<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';

if (!class_exists('\CBT_REST')) {
    class CBT_REST_AutoHeal_Start_Test_Double
    {
        public static function warm_exam_question_delivery_snapshot(int $exam_id): void
        {
            \CBT_Exam_Question_Delivery_Cache::warm_exam_payload($exam_id, static function (int $target_exam_id): array {
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
            \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($exam_id, static function (int $target_exam_id): array {
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
            \CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
        }
    }

    class_alias(CBT_REST_AutoHeal_Start_Test_Double::class, 'CBT_REST');
}

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
                'question_count' => 2,
                'question_number_map' => [201 => 1, 202 => 2],
                'randomize_questions' => 1,
                'randomize_options' => 1,
                'duration_minutes' => 90,
                'show_student_result' => 1,
                'enable_calculator' => 1,
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
        self::assertSame(2, $first['question_count']);
        self::assertSame(90, $first['duration_minutes']);
        self::assertSame(1, $first['show_student_result']);
        self::assertSame(1, $first['enable_calculator']);
        self::assertGreaterThanOrEqual(3, count($this->storedRedisKeys()));

        CBT_Cache::invalidate_exam(55);
        $third = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, $producer);

        self::assertSame(2, $producerCalls);
        self::assertSame([201, 202], $third['question_ids']);
        self::assertGreaterThanOrEqual(2, count($this->storedRedisKeys()));
    }

    public function test_write_current_exam_snapshot_supports_partial_manifest_patch_with_preserved_ttl(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201, 202],
                'question_count' => 2,
                'question_number_map' => [201 => 1, 202 => 2],
                'question_manifest' => [
                    ['id' => 201, 'question_type' => 'multiple_choice', 'updated_at' => '2026-04-03 05:01:00', 'points' => 5],
                    ['id' => 202, 'question_type' => 'essay', 'updated_at' => '2026-04-03 05:02:00', 'points' => 3],
                ],
                'randomize_questions' => 0,
                'randomize_options' => 1,
                'duration_minutes' => 90,
                'show_student_result' => 1,
                'enable_calculator' => 1,
                'option_randomization_tokens_by_question' => [
                    201 => ['9001', '9002'],
                    202 => ['legacy'],
                ],
                'force_option_shuffle_question_ids' => [201, 202],
            ];
        });

        $envelope = CBT_Exam_Start_Attempt_Snapshot_Cache::read_current_exam_snapshot_envelope(55);
        self::assertTrue($envelope['success']);
        $originalTtl = (int) $envelope['ttl_seconds'];
        $payload = (array) $envelope['payload'];
        $payload['question_manifest'][0] = [
            'id' => 201,
            'question_type' => 'multiple_choice',
            'updated_at' => '2026-04-03 05:05:00',
            'points' => 7,
        ];
        $payload['option_randomization_tokens_by_question'][201] = ['9010', '9011'];
        $payload['force_option_shuffle_question_ids'] = [202];

        CBT_Cache::invalidate_exam(55);
        self::assertTrue(CBT_Exam_Start_Attempt_Snapshot_Cache::write_current_exam_snapshot(55, $payload, $originalTtl));

        $patchedEnvelope = CBT_Exam_Start_Attempt_Snapshot_Cache::read_current_exam_snapshot_envelope(55);
        self::assertTrue($patchedEnvelope['success']);
        self::assertSame($originalTtl, (int) $patchedEnvelope['ttl_seconds']);
        self::assertSame([201, 202], $patchedEnvelope['payload']['question_ids']);
        self::assertSame(2, $patchedEnvelope['payload']['question_count']);
        self::assertSame([201 => 1, 202 => 2], $patchedEnvelope['payload']['question_number_map']);
        self::assertSame(7.0, $patchedEnvelope['payload']['question_manifest'][0]['points']);
        self::assertSame(['9010', '9011'], $patchedEnvelope['payload']['option_randomization_tokens_by_question'][201]);
        self::assertSame([202], $patchedEnvelope['payload']['force_option_shuffle_question_ids']);
    }

    public function test_get_exam_snapshot_discards_stale_payload_version(): void
    {
        $producerCalls = 0;
        $producer = static function (int $examId) use (&$producerCalls): array {
            $producerCalls++;

            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        };

        CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, $producer);
        foreach ($this->storedRedisKeys() as $storageKey) {
            $payload = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
            self::assertIsArray($payload);
            self::assertSame(2, (int) ($payload['snapshot_payload_version'] ?? 0));
            unset($payload['snapshot_payload_version']);
            $GLOBALS['cbt_test_redis_storage'][$storageKey] = wp_json_encode($payload);
        }

        $second = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, $producer);

        self::assertSame(2, $producerCalls);
        self::assertSame([201], $second['question_ids']);
    }

    public function test_get_exam_snapshot_diagnostics_reports_ready_snapshot(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        $diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);

        self::assertTrue($diagnostics['redis_available']);
        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame(1, $diagnostics['snapshot_item_count']);
        self::assertSame(1, $diagnostics['question_count']);
        self::assertSame(60, $diagnostics['duration_minutes']);
        self::assertSame(0, $diagnostics['show_student_result']);
        self::assertSame(0, $diagnostics['enable_calculator']);
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

        foreach ($this->storedRedisKeys() as $storageKey) {
            $payload = json_decode((string) ($GLOBALS['cbt_test_redis_storage'][$storageKey] ?? ''), true);
            if (!is_array($payload) || !array_key_exists('revision_signature', $payload)) {
                continue;
            }
            $payload['revision_signature'] = 'stale-signature';
            $GLOBALS['cbt_test_redis_storage'][$storageKey] = wp_json_encode($payload);
        }

        $diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);

        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertFalse($diagnostics['snapshot_valid']);
        self::assertSame('invalid', $diagnostics['snapshot_status']);
        self::assertContains($diagnostics['snapshot_miss_reason'], ['revision_changed', 'invalid_payload']);
        self::assertContains($diagnostics['snapshot_miss_reason_label'], ['Revision berubah', 'Payload invalid']);
    }

    public function test_get_exam_snapshot_diagnostics_reports_manual_clear_revision_changed_expired_and_not_prepared_reasons(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        CBT_Exam_Start_Attempt_Snapshot_Cache::clear_exam_snapshot(55);
        $afterManualClear = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);
        self::assertSame('miss', $afterManualClear['snapshot_status']);
        self::assertSame('manual_clear', $afterManualClear['snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $afterManualClear['snapshot_miss_reason_label']);

        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Cache::invalidate_exam(55);
        $afterRevisionChanged = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);
        self::assertSame('miss', $afterRevisionChanged['snapshot_status']);
        self::assertSame('revision_changed', $afterRevisionChanged['snapshot_miss_reason']);
        self::assertSame('Revision berubah', $afterRevisionChanged['snapshot_miss_reason_label']);

        foreach ($this->storedRedisKeys() as $storedKey) {
            unset($GLOBALS['cbt_test_redis_storage'][$storedKey]);
        }

        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        foreach ($this->storedRedisKeys() as $storedKey) {
            unset($GLOBALS['cbt_test_redis_storage'][$storedKey]);
        }
        $afterKeyMissing = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);
        self::assertSame('miss', $afterKeyMissing['snapshot_status']);
        self::assertSame('expired_or_evicted', $afterKeyMissing['snapshot_miss_reason']);
        self::assertSame('TTL habis / ter-evict', $afterKeyMissing['snapshot_miss_reason_label']);

        $freshExamDiagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(77);
        self::assertSame('miss', $freshExamDiagnostics['snapshot_status']);
        self::assertSame('not_prepared', $freshExamDiagnostics['snapshot_miss_reason']);
        self::assertSame('Belum disiapkan', $freshExamDiagnostics['snapshot_miss_reason_label']);
    }

    public function test_get_exam_snapshot_diagnostics_reports_invalid_payload_and_unavailable_reasons(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        foreach ($this->storedRedisKeys() as $storageKey) {
            $GLOBALS['cbt_test_redis_storage'][$storageKey] = '{"broken":';
        }

        $invalidDiagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);
        self::assertSame('invalid', $invalidDiagnostics['snapshot_status']);
        self::assertSame('invalid_payload', $invalidDiagnostics['snapshot_miss_reason']);
        self::assertSame('Payload invalid', $invalidDiagnostics['snapshot_miss_reason_label']);

        $this->setStartSnapshotRedisUnavailable();
        $unavailableDiagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics(55);
        self::assertSame('unavailable', $unavailableDiagnostics['snapshot_status']);
        self::assertSame('redis_unavailable', $unavailableDiagnostics['snapshot_miss_reason']);
        self::assertSame('Redis tidak tersedia', $unavailableDiagnostics['snapshot_miss_reason_label']);
    }

    public function test_maybe_auto_heal_snapshot_repairs_revision_changed_invalid_payload_and_expired_or_evicted(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        CBT_Cache::invalidate_exam(55);
        $revisionRepair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot(55, 'admin');
        self::assertTrue($revisionRepair['success']);
        self::assertSame('auto_healed', $revisionRepair['status']);
        self::assertSame('ready', $revisionRepair['diagnostics']['snapshot_status']);
        self::assertSame('Dipulihkan otomatis dari revision exam terbaru', $revisionRepair['diagnostics']['repair_message']);

        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(56, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        foreach ($this->storedRedisKeys() as $storedKey) {
            if (strpos($storedKey, ':exam:56:') !== false) {
                $GLOBALS['cbt_test_redis_storage'][$storedKey] = '{"broken":';
            }
        }
        $invalidRepair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot(56, 'admin');
        self::assertTrue($invalidRepair['success']);
        self::assertSame('ready', $invalidRepair['diagnostics']['snapshot_status']);
        self::assertSame('Dipulihkan otomatis dari payload start current', $invalidRepair['diagnostics']['repair_message']);

        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(57, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        foreach ($this->storedRedisKeys() as $storedKey) {
            if (strpos($storedKey, ':exam:57:') !== false || strpos($storedKey, ':exam:57:fragment:') !== false) {
                unset($GLOBALS['cbt_test_redis_storage'][$storedKey]);
            }
        }
        $expiredRepair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot(57, 'admin');
        self::assertTrue($expiredRepair['success']);
        self::assertSame('ready', $expiredRepair['diagnostics']['snapshot_status']);
        self::assertSame('Dipulihkan otomatis dari payload start current', $expiredRepair['diagnostics']['repair_message']);
    }

    public function test_maybe_auto_heal_snapshot_skips_manual_clear_not_prepared_and_redis_unavailable(): void
    {
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(55, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [201],
                'question_count' => 1,
                'question_number_map' => [201 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::clear_exam_snapshot(55);

        $manualClearRepair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot(55, 'admin');
        self::assertFalse($manualClearRepair['success']);
        self::assertSame('miss', $manualClearRepair['diagnostics']['snapshot_status']);
        self::assertSame('manual_clear', $manualClearRepair['diagnostics']['snapshot_miss_reason']);

        $notPreparedRepair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot(77, 'admin');
        self::assertFalse($notPreparedRepair['success']);
        self::assertSame('miss', $notPreparedRepair['diagnostics']['snapshot_status']);
        self::assertSame('not_prepared', $notPreparedRepair['diagnostics']['snapshot_miss_reason']);

        $this->setStartSnapshotRedisUnavailable();
        $unavailableRepair = CBT_Exam_Start_Attempt_Snapshot_Cache::maybe_auto_heal_snapshot(55, 'admin');
        self::assertFalse($unavailableRepair['success']);
        self::assertSame('unavailable', $unavailableRepair['diagnostics']['snapshot_status']);
        self::assertSame('redis_unavailable', $unavailableRepair['diagnostics']['snapshot_miss_reason']);
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

    private function setStartSnapshotRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Start_Attempt_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('start_snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('start_snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('start_snapshot_redis_last_connection_error');
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
            return is_string($key) && strpos($key, 'cbt_exam_start_attempt:') === 0;
        }));
    }
}
