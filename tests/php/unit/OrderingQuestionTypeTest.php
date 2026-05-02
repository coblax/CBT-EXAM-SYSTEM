<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-scoring.php';

final class OrderingQuestionTypeTest extends TestCase
{
    public function test_schema_declares_ordering_tables_and_constraints(): void
    {
        $schema = (string) file_get_contents(dirname(__DIR__, 3) . '/sql/cbt_schema.sql');
        $activator = (string) file_get_contents(dirname(__DIR__, 3) . '/includes/class-cbt-activator.php');

        foreach ([$schema, $activator] as $source) {
            self::assertStringContainsString('cbt_question_ordering', $source);
            self::assertStringContainsString('cbt_question_ordering_items', $source);
            self::assertStringContainsString('scoring_mode', $source);
            self::assertStringContainsString('shuffle_items', $source);
            self::assertStringContainsString('correct_position', $source);
            self::assertStringContainsString('uniq_question_position', $source);
        }

        self::assertStringContainsString('fk_cbt_qordi_option', $activator);
    }

    public function test_ordering_detail_save_load_and_update_replaces_position_mapping(): void
    {
        $fake_wpdb = new CBT_Ordering_Question_Type_Fake_Wpdb();
        $fake_wpdb->optionsByQuestion[77] = [101, 202, 303];
        $GLOBALS['wpdb'] = $fake_wpdb;

        CBT_Admin_Questions_Helper::save_question_type_detail(
            77,
            'ordering',
            '',
            ['ordered_option_ids' => [303, 101, 202, 303, 0]]
        );

        $detail = CBT_Admin_Questions_Helper::get_question_type_detail(77, 'ordering');

        self::assertSame('exact', $detail['scoring_mode'] ?? '');
        self::assertSame(1, (int) ($detail['shuffle_items'] ?? 0));
        self::assertSame([303, 101, 202], $detail['correct_option_ids'] ?? []);
        self::assertSame([1 => 303, 2 => 101, 3 => 202], $fake_wpdb->positionsForQuestion(77));

        CBT_Admin_Questions_Helper::save_question_type_detail(
            77,
            'ordering',
            '',
            ['ordered_option_ids' => [202, 101]]
        );

        $updated_detail = CBT_Admin_Questions_Helper::get_question_type_detail(77, 'ordering');

        self::assertSame([202, 101], $updated_detail['correct_option_ids'] ?? []);
        self::assertSame([1 => 202, 2 => 101], $fake_wpdb->positionsForQuestion(77));
    }

    public function test_ordering_validation_requires_two_non_empty_unique_items(): void
    {
        self::assertSame(
            'Ordering minimal harus punya 2 item.',
            CBT_Admin_Questions_Helper::validate_ordering_options([
                ['option_text' => '<p>Langkah pertama</p>', 'is_correct' => 0],
            ])
        );

        self::assertSame(
            'Item Ordering tidak boleh kosong.',
            CBT_Admin_Questions_Helper::validate_ordering_options([
                ['option_text' => '<p>Langkah pertama</p>', 'is_correct' => 0],
                ['option_text' => '   ', 'is_correct' => 0],
            ])
        );

        self::assertSame(
            'Ordering tidak boleh punya item duplikat.',
            CBT_Admin_Questions_Helper::validate_ordering_options([
                ['option_text' => '<p>Langkah pertama.</p>', 'is_correct' => 0],
                ['option_text' => 'langkah pertama', 'is_correct' => 0],
            ])
        );

        self::assertSame(
            '',
            CBT_Admin_Questions_Helper::validate_ordering_options([
                ['option_text' => '<p>Langkah pertama</p>', 'is_correct' => 0],
                ['option_text' => '<p>Langkah kedua</p>', 'is_correct' => 0],
            ])
        );
    }

    public function test_ordering_exact_scoring_preserves_order_and_awards_full_score_only_for_exact_match(): void
    {
        $correct = CBT_Ordering_Scoring_Test_Harness::evaluateOrdering([11, 12, 13]);
        $wrong_order = CBT_Ordering_Scoring_Test_Harness::evaluateOrdering([11, 13, 12]);

        self::assertSame(1, $correct['is_correct']);
        self::assertSame(5.0, $correct['score_awarded']);
        self::assertSame('[11,12,13]', $correct['selected_option_ids']);
        self::assertSame(0, $wrong_order['is_correct']);
        self::assertSame(0.0, $wrong_order['score_awarded']);
        self::assertSame('[11,13,12]', $wrong_order['selected_option_ids']);
    }

    /**
     * @dataProvider invalidOrderingAnswerProvider
     */
    public function test_ordering_invalid_answers_are_never_correct($answer_input, ?string $expected_stored_ids): void
    {
        $result = CBT_Ordering_Scoring_Test_Harness::evaluateOrdering($answer_input);

        self::assertSame(0, $result['is_correct']);
        self::assertSame(0.0, $result['score_awarded']);
        self::assertSame($expected_stored_ids, $result['selected_option_ids']);
    }

    public static function invalidOrderingAnswerProvider(): array
    {
        return [
            'duplicate option' => [[11, 11, 12, 13], '[11,12,13]'],
            'non-positive option' => [[0, 11, 12, 13], '[11,12,13]'],
            'foreign option' => [[11, 12, 999], '[11,12,999]'],
            'missing item' => [[11, 12], '[11,12]'],
            'extra item' => [[11, 12, 13, 14], '[11,12,13,14]'],
            'malformed json' => ['[11,12,13', null],
            'non-json text' => ['not-json', null],
        ];
    }

    public function test_storage_normalization_preserves_ordering_order_but_keeps_multiple_answer_sorted(): void
    {
        self::assertSame(
            '[13,11,12]',
            CBT_Ordering_Scoring_Test_Harness::normalizeStorage('ordering', [13, 11, 12])['selected_option_ids']
        );
        self::assertSame(
            '[11,12,13]',
            CBT_Ordering_Scoring_Test_Harness::normalizeStorage('multiple_answer', [13, 11, 12])['selected_option_ids']
        );
    }
}

final class CBT_Ordering_Scoring_Test_Harness
{
    use CBT_REST_Scoring_Helpers;

    public static function evaluateOrdering($answer_input): array
    {
        return self::evaluate_answer_from_submission_context(
            [
                'id' => 90,
                'exam_id' => 7,
                'question_type' => 'ordering',
                'points' => 5.0,
                'correct_option_ids' => [],
                'ordering_correct_option_ids' => [11, 12, 13],
            ],
            $answer_input
        );
    }

    public static function normalizeStorage(string $question_type, $answer_input): array
    {
        return self::normalize_submission_for_storage($question_type, $answer_input);
    }
}

final class CBT_Ordering_Question_Type_Fake_Wpdb
{
    public string $prefix = 'wp_';

    /** @var array<int,array<string,mixed>> */
    public array $orderingDetails = [];

    /** @var array<int,array<int,array<string,mixed>>> */
    public array $orderingItems = [];

    /** @var array<int,int[]> */
    public array $optionsByQuestion = [];

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    public function delete(string $table, array $where, array $format = []): int
    {
        $question_id = (int) ($where['question_id'] ?? 0);
        if ($question_id <= 0) {
            return 0;
        }

        if (str_ends_with($table, 'cbt_question_ordering_items')) {
            unset($this->orderingItems[$question_id]);
            return 1;
        }

        if (str_ends_with($table, 'cbt_question_ordering')) {
            unset($this->orderingDetails[$question_id]);
            return 1;
        }

        return 0;
    }

    public function insert(string $table, array $data, array $format = []): int
    {
        $question_id = (int) ($data['question_id'] ?? 0);
        if ($question_id <= 0) {
            return 0;
        }

        if (str_ends_with($table, 'cbt_question_ordering_items')) {
            $this->orderingItems[$question_id][] = $data;
            return 1;
        }

        if (str_ends_with($table, 'cbt_question_ordering')) {
            $this->orderingDetails[$question_id] = $data;
            return 1;
        }

        return 1;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null): ?array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $question_id = (int) ($args[0] ?? 0);

        if (strpos($query, 'wp_cbt_question_ordering') !== false) {
            return $this->orderingDetails[$question_id] ?? null;
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_col($prepared): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $question_id = (int) ($args[0] ?? 0);

        if (strpos($query, 'FROM wp_cbt_question_ordering_items') !== false) {
            $items = $this->orderingItems[$question_id] ?? [];
            $valid_options = array_fill_keys($this->optionsByQuestion[$question_id] ?? [], true);
            usort($items, static function (array $left, array $right): int {
                $position_compare = ((int) ($left['correct_position'] ?? 0)) <=> ((int) ($right['correct_position'] ?? 0));
                if ($position_compare !== 0) {
                    return $position_compare;
                }

                return ((int) ($left['option_id'] ?? 0)) <=> ((int) ($right['option_id'] ?? 0));
            });

            return array_values(array_filter(array_map(
                static function (array $item) use ($valid_options): int {
                    $option_id = (int) ($item['option_id'] ?? 0);
                    return empty($valid_options) || isset($valid_options[$option_id]) ? $option_id : 0;
                },
                $items
            )));
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            return $this->optionsByQuestion[$question_id] ?? [];
        }

        return [];
    }

    /** @return array<int,int> */
    public function positionsForQuestion(int $question_id): array
    {
        $positions = [];
        foreach ($this->orderingItems[$question_id] ?? [] as $item) {
            $positions[(int) ($item['correct_position'] ?? 0)] = (int) ($item['option_id'] ?? 0);
        }
        ksort($positions);

        return $positions;
    }
}
