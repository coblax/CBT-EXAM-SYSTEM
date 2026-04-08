<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';

final class QuestionSubmissionContextSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeRedisClient();
    }

    public function test_get_snapshots_hydrates_redis_once_and_reuses_cached_values_until_exam_version_changes(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        $first = CBT_Question_Submission_Context_Cache::get_snapshots([201, 202]);
        $second = CBT_Question_Submission_Context_Cache::get_snapshots([201, 202]);

        self::assertSame(1, $wpdb->questionHydrateCalls);
        self::assertSame(1, $wpdb->optionHydrateCalls);
        self::assertSame($first, $second);
        self::assertSame([9002], $first[201]['correct_option_ids']);
        self::assertSame(['Jakarta'], $first[202]['short_answer_values']);
        self::assertArrayNotHasKey('correct_text', $first[201]);
        self::assertArrayNotHasKey('option_text', $first[201]);
        self::assertCount(4, $this->storedRedisKeys());

        CBT_Cache::invalidate_exam(55);
        $third = CBT_Question_Submission_Context_Cache::get_snapshots([201, 202]);

        self::assertSame(2, $wpdb->questionHydrateCalls);
        self::assertSame(2, $wpdb->optionHydrateCalls);
        self::assertSame($first[201]['id'], $third[201]['id']);
    }

    public function test_get_snapshot_discards_invalid_cached_payload_and_rehydrates_from_db(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        $snapshot = CBT_Question_Submission_Context_Cache::get_snapshot(201);
        self::assertSame(1, $wpdb->questionHydrateCalls);

        foreach ($this->storedRedisKeys() as $key) {
            if (strpos($key, 'cbt_submit_context:question:201:') === 0) {
                $GLOBALS['cbt_test_redis_storage'][$key] = '{broken-json';
            }
        }

        $rehydrated = CBT_Question_Submission_Context_Cache::get_snapshot(201);

        self::assertSame(2, $wpdb->questionHydrateCalls);
        self::assertSame($snapshot['correct_option_ids'], $rehydrated['correct_option_ids']);
    }

    public function test_get_snapshot_falls_back_to_db_when_redis_is_unavailable(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();
        $this->setSnapshotRedisUnavailable();

        $snapshot = CBT_Question_Submission_Context_Cache::get_snapshot(203);

        self::assertSame('true_false', $snapshot['question_type']);
        self::assertSame(1, $snapshot['true_false_correct_value']);
        self::assertSame(1, $wpdb->questionHydrateCalls);
        self::assertSame([], $this->storedRedisKeys());
    }

    public function test_warm_exam_snapshots_reports_ready_counts_and_preview_items(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        $diagnostics = CBT_Question_Submission_Context_Cache::warm_exam_snapshots(55);

        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame(2, $diagnostics['question_count']);
        self::assertSame(2, $diagnostics['ready_count']);
        self::assertSame(0, $diagnostics['missing_count']);
        self::assertSame(0, $diagnostics['invalid_count']);
        self::assertSame('', $diagnostics['snapshot_miss_reason']);
        self::assertSame('', $diagnostics['snapshot_miss_reason_label']);
        self::assertCount(2, $diagnostics['preview_items']);
        self::assertGreaterThan(0, $diagnostics['payload_bytes_total']);
    }

    public function test_clear_exam_snapshots_removes_pointer_and_storage_keys(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(55);
        self::assertNotEmpty($this->storedRedisKeys());

        $result = CBT_Question_Submission_Context_Cache::clear_exam_snapshots(55);

        self::assertSame(55, $result['exam_id']);
        self::assertSame(2, $result['question_count']);
        self::assertGreaterThan(0, $result['deleted_keys']);
        self::assertSame([], $this->storedRedisKeys());

        $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics(55);
        self::assertSame('miss', $diagnostics['snapshot_status']);
        self::assertSame('manual_clear', $diagnostics['snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $diagnostics['snapshot_miss_reason_label']);
    }

    public function test_exam_snapshot_diagnostics_marks_invalid_and_missing_items(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(55);
        foreach ($this->storedRedisKeys() as $key) {
            if (strpos($key, 'cbt_submit_context:pointer:question:201') === 0) {
                $GLOBALS['cbt_test_redis_storage'][$key] = '{broken-json';
            }
            if (strpos($key, 'cbt_submit_context:question:202:') === 0) {
                unset($GLOBALS['cbt_test_redis_storage'][$key]);
            }
        }

        $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics(55);

        self::assertSame('warning', $diagnostics['snapshot_status']);
        self::assertSame(0, $diagnostics['ready_count']);
        self::assertSame(1, $diagnostics['missing_count']);
        self::assertSame(1, $diagnostics['invalid_count']);
        self::assertSame('partial_mixed', $diagnostics['snapshot_miss_reason']);
        self::assertSame('Parsial campuran', $diagnostics['snapshot_miss_reason_label']);
        self::assertSame('invalid_payload', $diagnostics['preview_items'][0]['reason']);
        self::assertSame('Payload hilang', $diagnostics['preview_items'][1]['reason_label']);
    }

    public function test_exam_snapshot_diagnostics_marks_revision_changed_when_exam_version_is_invalidated(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(55);
        CBT_Cache::invalidate_exam(55);

        $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics(55);

        self::assertSame('invalid', $diagnostics['snapshot_status']);
        self::assertSame('revision_changed', $diagnostics['snapshot_miss_reason']);
        self::assertSame('Revision berubah', $diagnostics['snapshot_miss_reason_label']);
    }

    public function test_maybe_auto_heal_exam_snapshots_repairs_whitelisted_submit_snapshot_reasons(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(55);

        foreach ($this->storedRedisKeys() as $key) {
            if (strpos($key, 'cbt_submit_context:question:201:') === 0) {
                unset($GLOBALS['cbt_test_redis_storage'][$key]);
            }
        }

        $expiredRepair = CBT_Question_Submission_Context_Cache::maybe_auto_heal_exam_snapshots(55, 'admin');
        self::assertTrue($expiredRepair['success']);
        self::assertSame('ready', $expiredRepair['diagnostics']['snapshot_status']);
        self::assertSame('auto_healed', $expiredRepair['diagnostics']['repair_status']);

        CBT_Cache::invalidate_exam(55);
        $revisionRepair = CBT_Question_Submission_Context_Cache::maybe_auto_heal_exam_snapshots(55, 'admin');
        self::assertTrue($revisionRepair['success']);
        self::assertSame('ready', $revisionRepair['diagnostics']['snapshot_status']);

        foreach ($this->storedRedisKeys() as $key) {
            if (strpos($key, 'cbt_submit_context:pointer:question:201') === 0) {
                $GLOBALS['cbt_test_redis_storage'][$key] = '{broken-json';
            }
        }

        $invalidRepair = CBT_Question_Submission_Context_Cache::maybe_auto_heal_exam_snapshots(55, 'admin');
        self::assertTrue($invalidRepair['success']);
        self::assertSame('ready', $invalidRepair['diagnostics']['snapshot_status']);
    }

    public function test_maybe_auto_heal_exam_snapshots_skips_blacklisted_submit_snapshot_reasons(): void
    {
        global $wpdb;
        $wpdb = new QuestionSubmissionContextSnapshotFakeWpdb();

        $notPrepared = CBT_Question_Submission_Context_Cache::maybe_auto_heal_exam_snapshots(55, 'admin');
        self::assertFalse($notPrepared['success']);
        self::assertSame('miss', $notPrepared['diagnostics']['snapshot_status']);
        self::assertSame('not_prepared', $notPrepared['diagnostics']['snapshot_miss_reason']);

        CBT_Question_Submission_Context_Cache::warm_exam_snapshots(55);
        CBT_Question_Submission_Context_Cache::clear_exam_snapshots(55);

        $manualClear = CBT_Question_Submission_Context_Cache::maybe_auto_heal_exam_snapshots(55, 'admin');
        self::assertFalse($manualClear['success']);
        self::assertSame('miss', $manualClear['diagnostics']['snapshot_status']);
        self::assertSame('manual_clear', $manualClear['diagnostics']['snapshot_miss_reason']);
    }

    private function useFakeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Question_Submission_Context_Cache::class);

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

    private function setSnapshotRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Question_Submission_Context_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
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
            return is_string($key) && strpos($key, 'cbt_submit_context:') === 0;
        }));
    }
}

final class QuestionSubmissionContextSnapshotFakeWpdb
{
    public string $prefix = 'wp_';

    public int $questionHydrateCalls = 0;
    public int $optionHydrateCalls = 0;

    /** @var array<int,array<string,mixed>> */
    private array $questionRows;

    /** @var array<int,array<int,array<string,mixed>>> */
    private array $optionsByQuestion;

    public function __construct()
    {
        $this->questionRows = [
            201 => [
                'id' => 201,
                'exam_id' => 55,
                'question_type' => 'multiple_choice',
                'points' => 5,
                'correct_text' => '',
                'true_false_correct_value' => null,
                'short_answer_correct_text' => null,
            ],
            202 => [
                'id' => 202,
                'exam_id' => 55,
                'question_type' => 'short_answer',
                'points' => 3,
                'correct_text' => '',
                'true_false_correct_value' => null,
                'short_answer_correct_text' => 'Jakarta',
            ],
            203 => [
                'id' => 203,
                'exam_id' => 56,
                'question_type' => 'true_false',
                'points' => 2,
                'correct_text' => '',
                'true_false_correct_value' => 1,
                'short_answer_correct_text' => null,
            ],
        ];

        $this->optionsByQuestion = [
            201 => [
                ['id' => 9001, 'question_id' => 201, 'option_text' => 'Bandung', 'is_correct' => 0],
                ['id' => 9002, 'question_id' => 201, 'option_text' => 'Jakarta', 'is_correct' => 1],
            ],
            203 => [
                ['id' => 9010, 'question_id' => 203, 'option_text' => 'Benar', 'is_correct' => 1],
                ['id' => 9011, 'question_id' => 203, 'option_text' => 'Salah', 'is_correct' => 0],
            ],
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (strpos($query, 'FROM wp_cbt_questions q') !== false) {
            $this->questionHydrateCalls++;
            $ids = $this->extractIdsFromInClause($query);
            $rows = [];
            foreach ($ids as $questionId) {
                if (isset($this->questionRows[$questionId])) {
                    $rows[] = $this->questionRows[$questionId];
                }
            }

            return $rows;
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = ') !== false) {
            preg_match('/WHERE exam_id = (\d+)/', $query, $matches);
            $examId = isset($matches[1]) ? (int) $matches[1] : 0;
            $rows = [];
            foreach ($this->questionRows as $questionRow) {
                if ((int) ($questionRow['exam_id'] ?? 0) !== $examId) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) ($questionRow['id'] ?? 0),
                    'question_type' => (string) ($questionRow['question_type'] ?? ''),
                ];
            }

            return $rows;
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            $this->optionHydrateCalls++;
            $ids = $this->extractIdsFromInClause($query);
            $rows = [];
            foreach ($ids as $questionId) {
                foreach ($this->optionsByQuestion[$questionId] ?? [] as $optionRow) {
                    $rows[] = $optionRow;
                }
            }

            return $rows;
        }

        return [];
    }

    /**
     * @return array<int,int>
     */
    private function extractIdsFromInClause(string $query): array
    {
        if (!preg_match('/IN\s*\(([^)]+)\)/', $query, $matches)) {
            return [];
        }

        $parts = array_map('trim', explode(',', (string) ($matches[1] ?? '')));
        return array_values(array_filter(array_map('intval', $parts), static function (int $value): bool {
            return $value > 0;
        }));
    }
}
