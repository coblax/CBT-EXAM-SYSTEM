<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-helper.php';

final class AdminResultsHelperObjectMapProgressTest extends TestCase
{
    public function test_object_map_answer_progress_marks_empty_maps_as_unanswered(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 10, 'question_type' => 'matching', 'points' => 5],
            ['answer_text' => '{"1":0,"2":""}', 'is_correct' => 0, 'score_awarded' => 0]
        );

        self::assertSame('unanswered', $item['status'] ?? '');
        self::assertSame(0.0, $item['score_awarded'] ?? -1);
        self::assertSame('Belum dijawab', $item['answer_preview'] ?? '');
    }

    public function test_object_map_answer_progress_keeps_partial_scores_visible(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 11, 'question_type' => 'categorization', 'points' => 6],
            ['answer_text' => '{"1":123,"2":124}', 'is_correct' => 0, 'score_awarded' => 3]
        );

        self::assertSame('wrong', $item['status'] ?? '');
        self::assertSame(3.0, $item['score_awarded'] ?? -1);
        self::assertSame('1: #123 | 2: #124', $item['answer_preview'] ?? '');
    }

    public function test_cloze_dropdown_progress_uses_option_labels_when_available(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 14, 'question_type' => 'cloze_dropdown', 'points' => 6],
            ['answer_text' => '{"1":2201,"2":2202}', 'is_correct' => 0, 'score_awarded' => 3],
            [
                2201 => 'Jepang',
                2202 => 'Seoul',
            ]
        );

        self::assertSame('wrong', $item['status'] ?? '');
        self::assertSame(3.0, $item['score_awarded'] ?? -1);
        self::assertSame('1: Jepang | 2: Seoul', $item['answer_preview'] ?? '');
    }

    public function test_table_completion_zero_text_value_counts_as_answered(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 12, 'question_type' => 'table_completion', 'points' => 4, 'table_completion_answer_cell_count' => 1],
            ['answer_text' => '{"A1":"0"}', 'is_correct' => 1, 'score_awarded' => 4]
        );

        self::assertSame('correct', $item['status'] ?? '');
        self::assertSame(4.0, $item['score_awarded'] ?? -1);
        self::assertSame('A1: 0', $item['answer_preview'] ?? '');
    }

    public function test_table_completion_progress_treats_points_as_per_answer_cell(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 17, 'question_type' => 'table_completion', 'points' => 3, 'table_completion_answer_cell_count' => 2],
            ['answer_text' => '{"A1":"Tokyo"}', 'is_correct' => 0, 'score_awarded' => 3]
        );

        self::assertSame('wrong', $item['status'] ?? '');
        self::assertSame(6.0, $item['points'] ?? -1);
        self::assertSame(3.0, $item['score_awarded'] ?? -1);
    }

    public function test_true_false_matrix_progress_treats_points_as_per_statement(): void
    {
        $item = $this->buildProgressItem(
            [
                'id' => 15,
                'question_type' => 'true_false_matrix',
                'points' => 2,
                'correct_text' => json_encode([
                    ['text' => 'Pernyataan A', 'answer' => 'true'],
                    ['text' => 'Pernyataan B', 'answer' => 'false'],
                    ['text' => 'Pernyataan C', 'answer' => 'true'],
                ]),
            ],
            ['answer_text' => '{"1":"true","2":"true","3":"true"}', 'is_correct' => 0, 'score_awarded' => 0]
        );

        self::assertSame('wrong', $item['status'] ?? '');
        self::assertSame(6.0, $item['points'] ?? -1);
        self::assertSame(4.0, $item['score_awarded'] ?? -1);
    }

    public function test_object_map_answer_progress_uses_option_labels_when_available(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 13, 'question_type' => 'matching', 'points' => 8],
            ['answer_text' => '{"1":123,"2":124,"3":125,"4":126}', 'is_correct' => 0, 'score_awarded' => 2],
            [
                123 => 'Jakarta',
                124 => 'Bandung',
                125 => 'Surabaya',
                126 => 'Medan',
            ]
        );

        self::assertSame('1: Jakarta | 2: Bandung | 3: Surabaya +1 item', $item['answer_preview'] ?? '');
    }

    public function test_matching_progress_treats_points_as_per_pair(): void
    {
        $item = $this->buildProgressItem(
            ['id' => 16, 'question_type' => 'matching', 'points' => 2, 'matching_item_count' => 3],
            ['answer_text' => '{"1":101,"2":102}', 'is_correct' => 0, 'score_awarded' => 4]
        );

        self::assertSame('wrong', $item['status'] ?? '');
        self::assertSame(6.0, $item['points'] ?? -1);
        self::assertSame(4.0, $item['score_awarded'] ?? -1);
    }

    /**
     * @param array<string,mixed> $question
     * @param array<string,mixed> $answer
     * @return array<string,mixed>
     */
    private function buildProgressItem(array $question, array $answer, array $optionLabels = []): array
    {
        $method = new ReflectionMethod(CBT_Admin_Results_Helper::class, 'build_attempt_answer_progress_item');
        $method->setAccessible(true);

        return (array) $method->invoke(null, $question, $answer, $optionLabels, [], 1, false);
    }
}
