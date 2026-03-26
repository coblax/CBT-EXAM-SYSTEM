<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-import-helper.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

final class QuestionsImportPreviewTest extends TestCase
{
    public function test_map_import_question_type_accepts_supported_aliases_consistently(): void
    {
        self::assertSame('multiple_choice', \CBT_Admin_Questions_Import_Helper::map_import_question_type('MCQ'));
        self::assertSame('multiple_answer', \CBT_Admin_Questions_Import_Helper::map_import_question_type('multiple-answers'));
        self::assertSame('true_false', \CBT_Admin_Questions_Import_Helper::map_import_question_type('tf'));
        self::assertSame('true_false_matrix', \CBT_Admin_Questions_Import_Helper::map_import_question_type('True False Matrix'));
        self::assertSame('short_answer', \CBT_Admin_Questions_Import_Helper::map_import_question_type('short'));
        self::assertSame('essay', \CBT_Admin_Questions_Import_Helper::map_import_question_type('essay'));
    }

    public function test_build_options_raw_from_import_normalizes_multiple_choice_and_multiple_answer_rules(): void
    {
        $multipleChoice = \CBT_Admin_Questions_Import_Helper::build_options_raw_from_import(
            "Satu\nDua\nTiga",
            '',
            'multiple_choice'
        );
        $multipleAnswer = \CBT_Admin_Questions_Import_Helper::build_options_raw_from_import(
            "Satu\nDua\nTiga",
            'Satu, Tiga',
            'multiple_answer'
        );
        $multipleAnswerWithoutCorrect = \CBT_Admin_Questions_Import_Helper::build_options_raw_from_import(
            "Satu\nDua\nTiga",
            '',
            'multiple_answer'
        );

        self::assertSame("Satu|1\nDua|0\nTiga|0", $multipleChoice);
        self::assertSame("Satu|1\nDua|0\nTiga|1", $multipleAnswer);
        self::assertSame('', $multipleAnswerWithoutCorrect);
    }

    public function test_parse_docx_block_moves_pembahasan_multiline_and_image_into_explanation(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Apa warna langit?',
            'PEMBAHASAN:',
            'Langit terlihat biru saat siang.',
            'Pembahasan lanjutan.',
            '__IMG__:http://example.test/pembahasan.png',
            'A. Biru',
            'B. Merah',
            'ANSWER: A',
        ]]);

        self::assertIsArray($row);
        self::assertSame('multiple_choice', $row['question_type']);
        self::assertStringContainsString('<p>Apa warna langit?</p>', (string) $row['question_text']);
        self::assertStringContainsString('Langit terlihat biru saat siang.', (string) $row['explanation']);
        self::assertStringContainsString('Pembahasan lanjutan.', (string) $row['explanation']);
        self::assertStringContainsString('<img src="http://example.test/pembahasan.png"', (string) $row['explanation']);
        self::assertStringNotContainsString('Langit terlihat biru saat siang.', (string) $row['question_text']);
        self::assertStringNotContainsString('http://example.test/pembahasan.png', (string) $row['question_text']);
    }

    public function test_parse_docx_block_accepts_explanation_alias_and_key_only_lines(): void
    {
        $normalizedLines = $this->invokeImportHelper('normalize_docx_extracted_lines', [[
            'QUESTION',
            'Ibu kota Indonesia adalah?',
            'EXPLANATION',
            'Jakarta menjadi pusat pemerintahan.',
            'A',
            'Jakarta',
            'B',
            'Bandung',
            'ANSWER: A',
        ]]);
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [$normalizedLines]);

        self::assertContains('QUESTION: Ibu kota Indonesia adalah?', $normalizedLines);
        self::assertContains('EXPLANATION: Jakarta menjadi pusat pemerintahan.', $normalizedLines);
        self::assertContains('ANSWER: A', $normalizedLines);
        self::assertIsArray($row);
        self::assertSame('multiple_choice', $row['question_type']);
        self::assertStringContainsString('Jakarta menjadi pusat pemerintahan.', (string) $row['explanation']);
        self::assertStringNotContainsString('Jakarta menjadi pusat pemerintahan.', (string) $row['question_text']);
    }

    public function test_parse_docx_block_normalizes_true_false_answers_from_text_variants(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pernyataan ini salah?',
            'QUESTION_TYPE: true_false',
            'ANSWER: salah',
        ]]);

        self::assertIsArray($row);
        self::assertSame('true_false', $row['question_type']);
        self::assertSame('false', $row['correct_answer']);
        self::assertSame('', $row['correct_text']);
    }

    private function invokeImportHelper(string $method, array $args): mixed
    {
        $reflection = new ReflectionClass(\CBT_Admin_Questions_Import_Helper::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $args);
    }
}
