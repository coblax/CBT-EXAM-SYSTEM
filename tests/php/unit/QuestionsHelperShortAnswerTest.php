<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';

use CbtExamSystem\Tests\TestCase;

final class QuestionsHelperShortAnswerTest extends TestCase
{
    public function test_normalize_short_answer_values_trims_values_and_preserves_order(): void
    {
        $values = \CBT_Admin_Questions_Helper::normalize_short_answer_values("  Satu  \nDua\nSatu ");

        self::assertSame(['Satu', 'Dua', 'Satu'], $values);
    }

    public function test_normalize_short_answer_values_limits_to_eight_items(): void
    {
        $values = \CBT_Admin_Questions_Helper::normalize_short_answer_values(
            'A||B||C||D||E||F||G||H||I||J'
        );

        self::assertCount(8, $values);
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], $values);
    }

    public function test_normalize_short_answer_compare_value_is_case_insensitive_and_tolerates_spacing_and_edge_punctuation(): void
    {
        self::assertSame(
            \CBT_Admin_Questions_Helper::normalize_short_answer_compare_value('Jakarta'),
            \CBT_Admin_Questions_Helper::normalize_short_answer_compare_value('  JAKARTA.  ')
        );
        self::assertSame(
            \CBT_Admin_Questions_Helper::normalize_short_answer_compare_value('ibu kota'),
            \CBT_Admin_Questions_Helper::normalize_short_answer_compare_value(' IBU   KOTA ')
        );
    }

    public function test_resolve_short_answer_input_keys_detects_unique_placeholders_in_order(): void
    {
        $keys = \CBT_Admin_Questions_Helper::resolve_short_answer_input_keys(
            '<p>Lengkapi [INPUT_1], [input-2], [INPUT B], dan [INPUT_1] lagi.</p>'
        );

        self::assertSame(['A', 'B'], $keys);
    }

    public function test_find_duplicate_short_answer_input_keys_detects_reused_placeholder(): void
    {
        $duplicates = \CBT_Admin_Questions_Helper::find_duplicate_short_answer_input_keys(
            '<p>Lengkapi [INPUT_A], [INPUT_C], lalu ulangi [input-a].</p>'
        );

        self::assertSame(['A'], $duplicates);
    }

    public function test_find_duplicate_option_indexes_detects_same_text_with_different_case_and_spacing(): void
    {
        $duplicates = \CBT_Admin_Questions_Helper::find_duplicate_option_indexes([
            ['option_text' => '<p>Jakarta</p>', 'is_correct' => 1],
            ['option_text' => '<p>  jakarta. </p>', 'is_correct' => 0],
            ['option_text' => '<p>Bandung</p>', 'is_correct' => 0],
        ]);

        self::assertSame([2], $duplicates);
    }

    public function test_find_duplicate_true_false_matrix_statement_indexes_detects_duplicate_statement(): void
    {
        $duplicates = \CBT_Admin_Questions_Helper::find_duplicate_true_false_matrix_statement_indexes([
            ['text' => 'Air membeku pada 0C.', 'answer' => 'true'],
            ['text' => '  air membeku pada 0c  ', 'answer' => 'false'],
            ['text' => 'Matahari terbit dari timur.', 'answer' => 'true'],
        ]);

        self::assertSame([2], $duplicates);
    }

    public function test_validate_choice_options_reports_empty_correct_reference_for_multiple_choice_and_multiple_answer(): void
    {
        $multipleChoiceMessage = \CBT_Admin_Questions_Helper::validate_choice_options(
            'multiple_choice',
            [
                ['option_text' => '<p>Jakarta</p>', 'is_correct' => 0],
                ['option_text' => '<p>Bandung</p>', 'is_correct' => 0],
                ['option_text' => '<p>Surabaya</p>', 'is_correct' => 0],
            ],
            ['has_empty_correct_reference' => true]
        );
        $multipleAnswerMessage = \CBT_Admin_Questions_Helper::validate_choice_options(
            'multiple_answer',
            [
                ['option_text' => '<p>2</p>', 'is_correct' => 1],
                ['option_text' => '<p>4</p>', 'is_correct' => 0],
                ['option_text' => '<p>6</p>', 'is_correct' => 0],
            ],
            ['has_empty_correct_reference' => true]
        );

        self::assertSame('Jawaban benar Multiple Choice tidak boleh menunjuk pilihan kosong.', $multipleChoiceMessage);
        self::assertSame('Multiple Answer tidak boleh menandai jawaban benar pada pilihan yang kosong.', $multipleAnswerMessage);
    }

    public function test_validate_true_false_matrix_items_reports_gap_and_empty_statement_from_source_rows(): void
    {
        $gapMessage = \CBT_Admin_Questions_Helper::validate_true_false_matrix_items(
            [
                ['text' => 'Air membeku pada 0C.', 'answer' => 'true'],
                ['text' => 'Matahari terbit dari timur.', 'answer' => 'true'],
            ],
            ['provided_indexes' => [1, 3]]
        );
        $emptyStatementMessage = \CBT_Admin_Questions_Helper::validate_true_false_matrix_items(
            [
                ['text' => 'Air membeku pada 0C.', 'answer' => 'true'],
                ['text' => 'Matahari terbit dari timur.', 'answer' => 'true'],
            ],
            [
                'provided_indexes' => [1, 2],
                'source_rows' => [
                    ['index' => 1, 'text' => 'Air membeku pada 0C.'],
                    ['index' => 2, 'text' => '   '],
                ],
            ]
        );

        self::assertSame('Pernyataan True/False Matrix harus diisi berurutan tanpa nomor yang loncat.', $gapMessage);
        self::assertSame('Pernyataan True/False Matrix tidak boleh kosong.', $emptyStatementMessage);
    }

    public function test_validate_short_answer_definition_allows_duplicate_normalized_answers_for_different_inputs(): void
    {
        $message = \CBT_Admin_Questions_Helper::validate_short_answer_definition(
            'Lengkapi [INPUT_A] dan [INPUT_B].',
            ['Jakarta', ' jakarta. '],
            ['provided_keys' => ['A', 'B']]
        );

        self::assertSame('', $message);
    }
}
