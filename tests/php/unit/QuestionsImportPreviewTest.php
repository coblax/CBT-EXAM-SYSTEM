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
            'C. Hijau',
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
            'C',
            'Surabaya',
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

    public function test_parse_docx_true_false_requires_non_empty_and_valid_answer_value(): void
    {
        $missingAnswer = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pernyataan ini benar?',
            'QUESTION_TYPE: true_false',
        ]]);
        $invalidAnswer = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pernyataan ini benar?',
            'QUESTION_TYPE: true_false',
            'ANSWER: mungkin',
        ]]);

        self::assertNull($missingAnswer);
        self::assertNull($invalidAnswer);
    }

    public function test_parse_docx_multiple_choice_requires_at_least_three_options(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'A. Jakarta',
            'B. Bandung',
            'ANSWER: A',
        ]]);

        self::assertNull($row);
    }

    public function test_parse_docx_multiple_choice_requires_exactly_one_correct_answer(): void
    {
        $missingAnswer = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Planet terdekat dari Matahari?',
            'A. Merkurius',
            'B. Venus',
            'C. Bumi',
        ]]);
        $multipleAnswers = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Planet terdekat dari Matahari?',
            'QUESTION_TYPE: multiple_choice',
            'A. Merkurius',
            'B. Venus',
            'C. Bumi',
            'ANSWER: A,C',
        ]]);

        self::assertNull($missingAnswer);
        self::assertNull($multipleAnswers);
    }

    public function test_parse_docx_multiple_choice_and_multiple_answer_reject_duplicate_options(): void
    {
        $duplicateMultipleChoice = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'A. Jakarta',
            'B.  jakarta. ',
            'C. Bandung',
            'ANSWER: A',
        ]]);
        $duplicateMultipleAnswer = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pilih semua pernyataan yang benar.',
            'QUESTION_TYPE: multiple_answer',
            'A. Air membeku pada 0C',
            'B. air membeku pada 0c.',
            'C. Matahari adalah bintang',
            'ANSWER: A,C',
        ]]);

        self::assertNull($duplicateMultipleChoice);
        self::assertNull($duplicateMultipleAnswer);
    }

    public function test_parse_docx_multiple_choice_and_multiple_answer_reject_correct_answer_that_points_to_empty_option(): void
    {
        $multipleChoiceEmptyCorrect = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'A. Jakarta',
            'C. Bandung',
            'D. Surabaya',
            'ANSWER: B',
        ]]);
        $multipleAnswerEmptyCorrect = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pilih semua bilangan genap.',
            'QUESTION_TYPE: multiple_answer',
            'A. 2',
            'B. 4',
            'D. 6',
            'ANSWER: A,C',
        ]]);

        self::assertNull($multipleChoiceEmptyCorrect);
        self::assertNull($multipleAnswerEmptyCorrect);
    }

    public function test_parse_docx_multiple_answer_requires_at_least_three_options_and_one_correct_answer(): void
    {
        $tooFewOptions = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Bilangan genap adalah?',
            'QUESTION_TYPE: multiple_answer',
            'A. 2',
            'B. 3',
            'ANSWER: A',
        ]]);
        $missingCorrectAnswer = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Bilangan genap adalah?',
            'QUESTION_TYPE: multiple_answer',
            'A. 2',
            'B. 3',
            'C. 4',
        ]]);
        $validRow = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Bilangan genap adalah?',
            'QUESTION_TYPE: multiple_answer',
            'A. 2',
            'B. 3',
            'C. 4',
            'ANSWER: A,C',
        ]]);

        self::assertNull($tooFewOptions);
        self::assertNull($missingCorrectAnswer);
        self::assertIsArray($validRow);
        self::assertSame('multiple_answer', $validRow['question_type']);
        self::assertSame('A,C', $validRow['correct_answer']);
    }

    public function test_parse_docx_true_false_matrix_requires_valid_key_for_each_statement(): void
    {
        $missingKey = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Air membeku pada 0C.',
            'KUNCI_1: benar',
            'PERNYATAAN_2: Matahari mengelilingi Bumi.',
        ]]);
        $invalidKey = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Air membeku pada 0C.',
            'KUNCI_1: benar',
            'PERNYATAAN_2: Matahari mengelilingi Bumi.',
            'KUNCI_2: mungkin',
        ]]);
        $validRow = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Air membeku pada 0C.',
            'KUNCI_1: benar',
            'PERNYATAAN_2: Matahari mengelilingi Bumi.',
            'KUNCI_2: salah',
        ]]);

        self::assertNull($missingKey);
        self::assertNull($invalidKey);
        self::assertIsArray($validRow);
        self::assertSame('true_false_matrix', $validRow['question_type']);
        self::assertStringContainsString('"answer":"true"', (string) $validRow['correct_text']);
        self::assertStringContainsString('"answer":"false"', (string) $validRow['correct_text']);
    }

    public function test_parse_docx_true_false_matrix_requires_contiguous_numbering_and_unique_statements(): void
    {
        $gapNumbering = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Air membeku pada 0C.',
            'KUNCI_1: benar',
            'PERNYATAAN_3: Matahari adalah bintang.',
            'KUNCI_3: benar',
        ]]);
        $duplicateStatements = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Air membeku pada 0C.',
            'KUNCI_1: benar',
            'PERNYATAAN_2:  air membeku pada 0c. ',
            'KUNCI_2: salah',
        ]]);

        self::assertNull($gapNumbering);
        self::assertNull($duplicateStatements);
    }

    public function test_parse_docx_true_false_matrix_rejects_key_without_statement(): void
    {
        $keyWithoutStatement = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'KUNCI_1: benar',
            'PERNYATAAN_2: Matahari adalah bintang.',
            'KUNCI_2: benar',
        ]]);

        self::assertNull($keyWithoutStatement);
    }

    public function test_parse_docx_short_answer_requires_placeholder_count_to_match_valid_answers(): void
    {
        $missingPlaceholder = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Sebutkan ibu kota Indonesia.',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: Jakarta',
        ]]);
        $mismatchCount = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi [INPUT_1] dan [INPUT_2].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: merah',
        ]]);
        $validRow = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi [INPUT_1] dan [INPUT_2].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: merah',
            'JAWABAN_B: putih',
        ]]);

        self::assertNull($missingPlaceholder);
        self::assertNull($mismatchCount);
        self::assertIsArray($validRow);
        self::assertSame('short_answer', $validRow['question_type']);
        self::assertStringContainsString('merah', (string) $validRow['correct_text']);
        self::assertStringContainsString('putih', (string) $validRow['correct_text']);
    }

    public function test_parse_docx_short_answer_requires_answer_keys_to_match_placeholder_keys(): void
    {
        $mismatchKeys = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi [INPUT_A] dan [INPUT_C].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: merah',
            'JAWABAN_B: putih',
        ]]);
        $matchingKeys = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi [INPUT_A] dan [INPUT_C].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: merah',
            'JAWABAN_C: putih',
        ]]);

        self::assertNull($mismatchKeys);
        self::assertIsArray($matchingKeys);
        self::assertSame('short_answer', $matchingKeys['question_type']);
        self::assertStringContainsString('merah', (string) $matchingKeys['correct_text']);
        self::assertStringContainsString('putih', (string) $matchingKeys['correct_text']);
    }

    public function test_parse_docx_short_answer_rejects_duplicate_placeholders_and_legacy_unkeyed_answers(): void
    {
        $duplicatePlaceholders = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi [INPUT_A], [INPUT_A], dan [INPUT_B].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: merah',
            'JAWABAN_B: putih',
        ]]);
        $legacyUnkeyedAnswers = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi [INPUT_1] dan [INPUT_2].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN: merah||putih',
        ]]);

        self::assertNull($duplicatePlaceholders);
        self::assertNull($legacyUnkeyedAnswers);
    }

    public function test_describe_docx_block_failure_reports_specific_reason_for_empty_correct_option(): void
    {
        $message = $this->invokeImportHelper('describe_docx_block_failure', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'A. Jakarta',
            'C. Bandung',
            'D. Surabaya',
            'ANSWER: B',
        ]]);

        self::assertSame('Jawaban benar menunjuk ke pilihan yang kosong atau tidak ada.', $message);
    }

    public function test_describe_docx_block_failure_reports_specific_reason_for_true_false_matrix_gap_and_short_answer_key_mismatch(): void
    {
        $gapMessage = $this->invokeImportHelper('describe_docx_block_failure', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Air membeku pada 0C.',
            'KUNCI_1: benar',
            'PERNYATAAN_3: Matahari adalah bintang.',
            'KUNCI_3: benar',
        ]]);
        $shortAnswerMismatch = $this->invokeImportHelper('describe_docx_block_failure', [[
            'QUESTION: Lengkapi [INPUT_A] dan [INPUT_C].',
            'QUESTION_TYPE: short_answer',
            'JAWABAN_A: merah',
            'JAWABAN_B: putih',
        ]]);

        self::assertSame('PERNYATAAN_n dan KUNCI_n harus diisi berurutan tanpa nomor yang loncat.', $gapMessage);
        self::assertSame('Key placeholder Short Answer harus cocok dengan key jawaban yang diisi.', $shortAnswerMismatch);
    }

    public function test_normalize_question_import_failure_entries_preserves_structured_metadata(): void
    {
        $entries = \CBT_Admin_Questions_Import_Helper::normalize_question_import_failure_entries([
            [
                'block_number' => 3,
                'question_type' => 'multiple_choice',
                'question_preview' => '<p>Marker soal gagal yang sangat panjang untuk dipotong otomatis oleh formatter preview.</p>',
                'message' => 'Jawaban benar menunjuk ke pilihan yang kosong atau tidak ada.',
            ],
            'Legacy string failure',
        ]);

        self::assertCount(2, $entries);
        self::assertSame(3, $entries[0]['block_number']);
        self::assertSame('multiple_choice', $entries[0]['question_type']);
        self::assertStringContainsString('Jawaban benar menunjuk ke pilihan yang kosong atau tidak ada.', $entries[0]['formatted']);
        self::assertSame(0, $entries[1]['block_number']);
        self::assertSame('Legacy string failure', $entries[1]['formatted']);
    }

    private function invokeImportHelper(string $method, array $args): mixed
    {
        $reflection = new ReflectionClass(\CBT_Admin_Questions_Import_Helper::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $args);
    }
}
