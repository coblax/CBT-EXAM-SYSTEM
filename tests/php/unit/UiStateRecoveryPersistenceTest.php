<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-ui-state.php';

use CbtExamSystem\Tests\TestCase;

final class UiStateRecoveryPersistenceTest extends TestCase
{
    private UiStateRecoveryPersistenceFakeWpdb $wpdbStub;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdbStub = new UiStateRecoveryPersistenceFakeWpdb(
            [
                55 => [
                    'id' => 55,
                    'student_id' => 7,
                    'exam_id' => 9,
                    'question_order' => wp_json_encode([101, 102, 103]),
                ],
            ],
            [
                9 => [101, 102, 103],
            ]
        );
        $wpdb = $this->wpdbStub;
    }

    public function test_save_attempt_state_rejects_question_ids_outside_attempt_order_and_persists_valid_snapshot(): void
    {
        $saved = \CBT_UI_State::save_attempt_state(7, 55, [
            'current_index' => 9,
            'doubtful_question_ids' => [101, 999, 102, 101],
        ]);

        self::assertSame([
            'attempt_id' => 55,
            'current_index' => 2,
            'doubtful_question_ids' => [101, 102],
        ], $saved);

        self::assertSame($saved, \CBT_UI_State::get_attempt_state(7, 55));
    }

    public function test_non_ui_cache_invalidation_does_not_remove_active_attempt_state_snapshot(): void
    {
        $saved = \CBT_UI_State::save_attempt_state(7, 55, [
            'current_index' => 1,
            'doubtful_question_ids' => [102],
        ]);

        \CBT_Cache::invalidate_catalog();
        \CBT_Cache::invalidate_namespace(\CBT_Cache::namespace_analytics());

        self::assertSame($saved, \CBT_UI_State::get_attempt_state(7, 55));
        self::assertNotEmpty(\CBT_UI_State::get_registry_entries());
    }

    public function test_save_attempt_state_identical_snapshot_skips_registry_rewrite(): void
    {
        $saved = \CBT_UI_State::save_attempt_state(7, 55, [
            'current_index' => 1,
            'doubtful_question_ids' => [102],
        ]);

        $registry = \CBT_UI_State::get_registry_entries();
        self::assertCount(1, $registry);
        $firstUpdatedAt = (int) ($registry[0]['updated_at'] ?? 0);

        sleep(1);

        $savedAgain = \CBT_UI_State::save_attempt_state(7, 55, [
            'current_index' => 1,
            'doubtful_question_ids' => [102],
        ]);

        $registryAfterNoOp = \CBT_UI_State::get_registry_entries();
        self::assertSame($saved, $savedAgain);
        self::assertCount(1, $registryAfterNoOp);
        self::assertSame($firstUpdatedAt, (int) ($registryAfterNoOp[0]['updated_at'] ?? 0));
    }

    public function test_save_attempt_state_after_question_deletion_returns_clamped_and_pruned_snapshot(): void
    {
        \CBT_UI_State::save_attempt_state(7, 55, [
            'current_index' => 2,
            'doubtful_question_ids' => [101, 103],
        ]);

        $this->wpdbStub->setAttemptQuestionState(55, [101, 102]);

        $savedAfterDeletion = \CBT_UI_State::save_attempt_state(7, 55, [
            'current_index' => 2,
            'doubtful_question_ids' => [101, 103],
        ]);

        self::assertSame([
            'attempt_id' => 55,
            'current_index' => 1,
            'doubtful_question_ids' => [101],
        ], $savedAfterDeletion);
        self::assertSame($savedAfterDeletion, \CBT_UI_State::get_attempt_state(7, 55));
    }
}

final class UiStateRecoveryPersistenceFakeWpdb
{
    /** @var string */
    public $prefix = 'wp_';

    /** @var array<int,array<string,mixed>> */
    private array $attemptRows;

    /** @var array<int,array<int,int>> */
    private array $questionIdsByExam;

    /**
     * @param array<int,array<string,mixed>> $attemptRows
     * @param array<int,array<int,int>> $questionIdsByExam
     */
    public function __construct(array $attemptRows, array $questionIdsByExam)
    {
        $this->attemptRows = $attemptRows;
        $this->questionIdsByExam = $questionIdsByExam;
    }

    /**
     * @param array<int,int> $questionIds
     */
    public function setAttemptQuestionState(int $attemptId, array $questionIds): void
    {
        if (!isset($this->attemptRows[$attemptId])) {
            return;
        }

        $examId = (int) ($this->attemptRows[$attemptId]['exam_id'] ?? 0);
        $this->attemptRows[$attemptId]['question_order'] = wp_json_encode(array_values($questionIds));
        if ($examId > 0) {
            $this->questionIdsByExam[$examId] = array_values($questionIds);
        }
    }

    /**
     * @param mixed ...$args
     * @return array<string,mixed>
     */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /**
     * @param array<string,mixed>|string $prepared
     * @return array<string,mixed>|null
     */
    public function get_row($prepared, $output = null): ?array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $attemptId = isset($args[0]) ? (int) $args[0] : 0;

        return $this->attemptRows[$attemptId] ?? null;
    }

    /**
     * @param array<string,mixed>|string $prepared
     * @return array<int,int>
     */
    public function get_col($prepared): array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $examId = isset($args[0]) ? (int) $args[0] : 0;

        return $this->questionIdsByExam[$examId] ?? [];
    }
}
