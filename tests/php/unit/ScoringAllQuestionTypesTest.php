<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ScoringAllQuestionTypesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_multiple_choice_correct_answer(): void
    {
        $this->bootstrapScoringScaffold();

        $context = [
            'id' => 1,
            'exam_id' => 10,
            'question_type' => 'multiple_choice',
            'points' => 2.0,
            'correct_option_ids' => [101],
            'ordering_correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => [],
            'cloze_dropdown_correct_option_ids_by_key' => [],
            'categorization_correct_option_ids_by_key' => [],
            'table_completion_answers_by_key' => [],
        ];

        $result = $this->callEvaluate($context, 101);

        self::assertSame(1, $result['is_correct']);
        self::assertSame(2.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_multiple_choice_wrong_answer(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('multiple_choice', 2.0, ['correct_option_ids' => [101]]);
        $result = $this->callEvaluate($context, 999);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_multiple_answer_all_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('multiple_answer', 3.0, ['correct_option_ids' => [10, 20, 30]]);
        $result = $this->callEvaluate($context, [10, 20, 30]);

        self::assertSame(1, $result['is_correct']);
        self::assertSame(3.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_multiple_answer_partial_gives_zero(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('multiple_answer', 3.0, ['correct_option_ids' => [10, 20, 30]]);
        $result = $this->callEvaluate($context, [10, 20]);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_multiple_answer_with_foreign_option_ids(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('multiple_answer', 3.0, ['correct_option_ids' => [10, 20]]);
        $result = $this->callEvaluate($context, [10, 20, 999]);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_true_false_correct_via_option_id(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('true_false', 1.0, [
            'correct_option_ids' => [50],
            'true_false_correct_value' => 1,
            'true_false_option_value_by_id' => ['50' => 1, '51' => 0],
        ]);
        $result = $this->callEvaluate($context, 50);

        self::assertSame(1, $result['is_correct']);
        self::assertSame(1.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_true_false_wrong_via_option_id(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('true_false', 1.0, [
            'correct_option_ids' => [50],
            'true_false_correct_value' => 1,
            'true_false_option_value_by_id' => ['50' => 1, '51' => 0],
        ]);
        $result = $this->callEvaluate($context, 51);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_ordering_correct_sequence(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('ordering', 4.0, [
            'ordering_correct_option_ids' => [1, 2, 3, 4],
        ]);
        $result = $this->callEvaluate($context, [1, 2, 3, 4]);

        self::assertSame(1, $result['is_correct']);
        self::assertSame(4.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_ordering_wrong_sequence(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('ordering', 4.0, [
            'ordering_correct_option_ids' => [1, 2, 3, 4],
        ]);
        $result = $this->callEvaluate($context, [4, 3, 2, 1]);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_ordering_with_foreign_option_ids(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('ordering', 4.0, [
            'ordering_correct_option_ids' => [1, 2, 3],
        ]);
        $result = $this->callEvaluate($context, [1, 2, 999]);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_short_answer_single_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('short_answer', 2.0, [
            'short_answer_values' => ['Jakarta'],
        ]);
        $result = $this->callEvaluate($context, 'jakarta');

        self::assertSame(1, $result['is_correct']);
        self::assertSame(2.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_short_answer_multiple_inputs_partial(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('short_answer', 1.0, [
            'short_answer_values' => ['Jakarta', 'Bandung', 'Surabaya'],
        ]);
        $result = $this->callEvaluate($context, '["Jakarta","Wrong","Surabaya"]');

        self::assertSame(0, $result['is_correct']);
        self::assertSame(2.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_short_answer_empty_submission(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('short_answer', 2.0, [
            'short_answer_values' => ['Jakarta'],
        ]);
        $result = $this->callEvaluate($context, '');

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_true_false_matrix_all_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('true_false_matrix', 1.0, [
            'true_false_matrix_answers' => ['1' => 'true', '2' => 'false', '3' => 'true'],
        ]);
        $result = $this->callEvaluate($context, '{"1":"true","2":"false","3":"true"}');

        self::assertSame(1, $result['is_correct']);
        self::assertSame(3.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_true_false_matrix_partial_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('true_false_matrix', 1.0, [
            'true_false_matrix_answers' => ['1' => 'true', '2' => 'false', '3' => 'true'],
        ]);
        $result = $this->callEvaluate($context, '{"1":"true","2":"true","3":"false"}');

        self::assertSame(0, $result['is_correct']);
        self::assertSame(1.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_matching_all_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('matching', 1.0, [
            'matching_correct_option_ids_by_key' => ['1' => 100, '2' => 200],
        ]);
        $result = $this->callEvaluate($context, '{"1":100,"2":200}');

        self::assertSame(1, $result['is_correct']);
        self::assertSame(2.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_matching_partial_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('matching', 1.0, [
            'matching_correct_option_ids_by_key' => ['1' => 100, '2' => 200],
        ]);
        $result = $this->callEvaluate($context, '{"1":100,"2":999}');

        self::assertSame(0, $result['is_correct']);
        self::assertSame(1.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_cloze_dropdown_all_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('cloze_dropdown', 1.0, [
            'cloze_dropdown_correct_option_ids_by_key' => ['1' => 55, '2' => 66],
        ]);
        $result = $this->callEvaluate($context, '{"1":55,"2":66}');

        self::assertSame(1, $result['is_correct']);
        self::assertSame(2.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_categorization_all_correct(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('categorization', 1.0, [
            'categorization_correct_option_ids_by_key' => ['1' => 11, '2' => 22, '3' => 33],
        ]);
        $result = $this->callEvaluate($context, '{"1":11,"2":22,"3":33}');

        self::assertSame(1, $result['is_correct']);
        self::assertSame(3.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_table_completion_mixed_cells(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('table_completion', 1.0, [
            'table_completion_answers_by_key' => [
                'A1' => ['cell_type' => 'text', 'correct_values' => ['Jakarta']],
                'B1' => ['cell_type' => 'dropdown', 'correct_option_id' => 77],
            ],
        ]);
        $result = $this->callEvaluate($context, '{"A1":"jakarta","B1":77}');

        self::assertSame(1, $result['is_correct']);
        self::assertSame(2.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_table_completion_partial(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('table_completion', 1.0, [
            'table_completion_answers_by_key' => [
                'A1' => ['cell_type' => 'text', 'correct_values' => ['Jakarta']],
                'B1' => ['cell_type' => 'dropdown', 'correct_option_id' => 77],
            ],
        ]);
        $result = $this->callEvaluate($context, '{"A1":"Bandung","B1":77}');

        self::assertSame(0, $result['is_correct']);
        self::assertSame(1.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_essay_returns_null_is_correct_and_zero_score(): void
    {
        $this->bootstrapScoringScaffold();

        $context = $this->buildContext('essay', 10.0, []);
        $result = $this->callEvaluate($context, 'This is my essay answer about the topic.');

        self::assertNull($result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
        self::assertSame('This is my essay answer about the topic.', $result['answer_text']);
    }

    #[RunInSeparateProcess]
    public function test_empty_answer_returns_zero_for_all_types(): void
    {
        $this->bootstrapScoringScaffold();

        $types = ['multiple_choice', 'multiple_answer', 'true_false', 'ordering', 'short_answer'];
        foreach ($types as $type) {
            $context = $this->buildContext($type, 5.0, [
                'correct_option_ids' => [1],
                'ordering_correct_option_ids' => [1, 2],
                'true_false_correct_value' => 1,
                'true_false_option_value_by_id' => ['1' => 1],
                'short_answer_values' => ['test'],
            ]);
            $result = $this->callEvaluate($context, '');

            self::assertSame(0.0, $result['score_awarded'], "Empty answer for {$type} should score 0");
        }
    }

    private function bootstrapScoringScaffold(): void
    {
        if (!class_exists('CBT_Admin_Questions_Helper')) {
            require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        }
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-scoring.php';

        if (!class_exists('ScoringTestHarness')) {
            eval(<<<'PHP'
class ScoringTestHarness {
    use CBT_REST_Scoring_Helpers;
    private const PRIORITY_WINDOW_TRANSIENT_KEY = 'cbt_exam_priority_window_until';
    public static function evaluate(array $context, $answer) {
        return self::evaluate_answer_from_submission_context($context, $answer);
    }
}
PHP);
        }
    }

    private function buildContext(string $type, float $points, array $overrides): array
    {
        return array_merge([
            'id' => 1,
            'exam_id' => 10,
            'question_type' => $type,
            'points' => $points,
            'correct_option_ids' => [],
            'ordering_correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => [],
            'cloze_dropdown_correct_option_ids_by_key' => [],
            'categorization_correct_option_ids_by_key' => [],
            'table_completion_answers_by_key' => [],
        ], $overrides);
    }

    private function callEvaluate(array $context, $answer): array
    {
        return \ScoringTestHarness::evaluate($context, $answer);
    }
}
