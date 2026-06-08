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
        self::assertSame('ordering', \CBT_Admin_Questions_Import_Helper::map_import_question_type('mengurutkan'));
        self::assertSame('matching', \CBT_Admin_Questions_Import_Helper::map_import_question_type('menjodohkan'));
        self::assertSame('cloze_dropdown', \CBT_Admin_Questions_Import_Helper::map_import_question_type('cloze dropdown'));
        self::assertSame('categorization', \CBT_Admin_Questions_Import_Helper::map_import_question_type('kategori'));
        self::assertSame('table_completion', \CBT_Admin_Questions_Import_Helper::map_import_question_type('melengkapi tabel'));
    }

    public function test_normalize_question_import_created_question_ids_filters_invalid_values_and_duplicates(): void
    {
        self::assertSame(
            [12, 4, 5, 88],
            \CBT_Admin_Questions_Import_Helper::normalize_question_import_created_question_ids([12, '4', 0, -5, 12, 88, ''])
        );
    }

    public function test_get_question_import_created_question_ids_for_current_user_reads_current_user_state_only(): void
    {
        set_transient('cbt_question_import_validtoken', [
            'user_id' => 1,
            'created_question_ids' => [31, '42', 31, 0],
        ], HOUR_IN_SECONDS);
        set_transient('cbt_question_import_otheruser', [
            'user_id' => 77,
            'created_question_ids' => [99],
        ], HOUR_IN_SECONDS);

        self::assertSame(
            [31, 42],
            \CBT_Admin_Questions_Import_Helper::get_question_import_created_question_ids_for_current_user('validtoken')
        );
        self::assertSame(
            [],
            \CBT_Admin_Questions_Import_Helper::get_question_import_created_question_ids_for_current_user('otheruser')
        );
    }

    public function test_normalize_question_import_created_question_items_filters_invalid_values_and_duplicates(): void
    {
        $normalized = \CBT_Admin_Questions_Import_Helper::normalize_question_import_created_question_items([
            [
                'question_id' => 91,
                'block_number' => 3,
                'question_type' => 'multiple_choice',
                'preview' => '  Preview   soal   pertama  ',
                'diagnostic_entries' => [
                    [
                        'block_number' => 3,
                        'question_type' => 'multiple_choice',
                        'field' => 'SOAL',
                        'kind' => 'fallback',
                        'feature' => 'equation_simplified',
                        'message' => 'Equation disederhanakan.',
                    ],
                ],
            ],
            [
                'question_id' => '91',
                'block_number' => 4,
                'question_type' => 'essay',
                'preview' => 'Duplikat harus diabaikan',
            ],
            [
                'question_id' => 0,
                'question_type' => 'essay',
            ],
            'invalid',
        ]);

        self::assertCount(1, $normalized);
        self::assertSame(91, $normalized[0]['question_id']);
        self::assertSame('multiple_choice', $normalized[0]['question_type']);
        self::assertSame('Preview soal pertama', $normalized[0]['preview']);
        self::assertSame(1, (int) ($normalized[0]['diagnostic_counts']['fallback'] ?? 0));
    }

    public function test_get_question_import_created_question_items_for_current_user_reads_current_user_state_only(): void
    {
        set_transient('cbt_question_import_analysisvalid', [
            'user_id' => 1,
            'created_question_items' => [
                [
                    'question_id' => 120,
                    'block_number' => 1,
                    'question_type' => 'multiple_choice',
                    'preview' => 'Preview 120',
                    'diagnostic_counts' => [
                        'preserved' => 2,
                        'fallback' => 1,
                    ],
                ],
                [
                    'question_id' => 120,
                    'block_number' => 2,
                    'question_type' => 'essay',
                    'preview' => 'Duplikat harus diabaikan',
                ],
            ],
        ], HOUR_IN_SECONDS);
        set_transient('cbt_question_import_analysisother', [
            'user_id' => 77,
            'created_question_items' => [
                [
                    'question_id' => 999,
                    'question_type' => 'multiple_choice',
                ],
            ],
        ], HOUR_IN_SECONDS);

        $items = \CBT_Admin_Questions_Import_Helper::get_question_import_created_question_items_for_current_user('analysisvalid');
        self::assertCount(1, $items);
        self::assertSame(120, $items[0]['question_id']);
        self::assertSame('Preview 120', $items[0]['preview']);
        self::assertSame(
            [],
            \CBT_Admin_Questions_Import_Helper::get_question_import_created_question_items_for_current_user('analysisother')
        );
    }

    public function test_remove_question_import_created_question_ids_for_current_user_updates_transient_state(): void
    {
        set_transient('cbt_question_import_batchtoken', [
            'user_id' => 1,
            'created' => 3,
            'created_question_ids' => [10, 11, 12],
            'created_question_items' => [
                [
                    'question_id' => 10,
                    'block_number' => 1,
                    'question_type' => 'multiple_choice',
                    'preview' => 'Soal 10',
                ],
                [
                    'question_id' => 11,
                    'block_number' => 2,
                    'question_type' => 'multiple_choice',
                    'preview' => 'Soal 11',
                ],
                [
                    'question_id' => 12,
                    'block_number' => 3,
                    'question_type' => 'essay',
                    'preview' => 'Soal 12',
                ],
            ],
        ], HOUR_IN_SECONDS);

        $updatedState = \CBT_Admin_Questions_Import_Helper::remove_question_import_created_question_ids_for_current_user('batchtoken', [11, 999]);

        self::assertIsArray($updatedState);
        self::assertSame([10, 12], $updatedState['created_question_ids']);
        self::assertSame(2, $updatedState['created']);
        self::assertSame([10, 12], array_values(array_map(static function (array $item): int {
            return (int) ($item['question_id'] ?? 0);
        }, $updatedState['created_question_items'] ?? [])));

        $storedState = get_transient('cbt_question_import_batchtoken');
        self::assertIsArray($storedState);
        self::assertSame([10, 12], $storedState['created_question_ids']);
        self::assertSame(2, $storedState['created']);
        self::assertSame([10, 12], array_values(array_map(static function (array $item): int {
            return (int) ($item['question_id'] ?? 0);
        }, $storedState['created_question_items'] ?? [])));
    }

    public function test_remove_question_import_created_question_ids_returns_null_for_invalid_or_expired_token(): void
    {
        self::assertNull(
            \CBT_Admin_Questions_Import_Helper::remove_question_import_created_question_ids_for_current_user('missingtoken', [1, 2])
        );
    }

    public function test_question_import_transient_ttl_is_two_hours_and_gc_removes_only_expired_import_transients(): void
    {
        unset($GLOBALS['wpdb']);

        $reflection = new ReflectionClass(\CBT_Admin_Questions_Import_Helper::class);
        self::assertSame(2 * HOUR_IN_SECONDS, $reflection->getConstant('QUESTION_IMPORT_TRANSIENT_TTL'));

        $GLOBALS['cbt_test_wp_options']['_transient_timeout_cbt_question_import_expired'] = 100;
        $GLOBALS['cbt_test_wp_options']['_transient_cbt_question_import_expired'] = ['state' => 'old'];
        $GLOBALS['cbt_test_wp_options']['_transient_timeout_cbt_question_import_rows_expired'] = 100;
        $GLOBALS['cbt_test_wp_options']['_transient_cbt_question_import_rows_expired'] = ['rows' => ['old']];
        $GLOBALS['cbt_test_wp_options']['_transient_timeout_cbt_question_import_active'] = 999999;
        $GLOBALS['cbt_test_wp_options']['_transient_cbt_question_import_active'] = ['state' => 'active'];
        $GLOBALS['cbt_test_wp_options']['_transient_timeout_unrelated'] = 100;
        $GLOBALS['cbt_test_wp_options']['_transient_unrelated'] = 'keep';

        $deleted = \CBT_Admin_Questions_Import_Helper::cleanup_expired_question_import_transients(500);

        self::assertSame(4, $deleted);
        self::assertArrayNotHasKey('_transient_timeout_cbt_question_import_expired', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayNotHasKey('_transient_cbt_question_import_expired', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayNotHasKey('_transient_timeout_cbt_question_import_rows_expired', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayNotHasKey('_transient_cbt_question_import_rows_expired', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayHasKey('_transient_timeout_cbt_question_import_active', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayHasKey('_transient_cbt_question_import_active', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayHasKey('_transient_timeout_unrelated', $GLOBALS['cbt_test_wp_options']);
        self::assertArrayHasKey('_transient_unrelated', $GLOBALS['cbt_test_wp_options']);
    }

    public function test_import_single_question_row_rejects_incomplete_matching_pairs(): void
    {
        global $wpdb;
        $wpdb = new QuestionsImportPreviewFakeWpdb();
        $affectedExamIds = [];

        $leftOnly = $this->invokeImportHelper('import_single_question_row', [[
            'question_type' => 'matching',
            'question_text' => 'Cocokkan pasangan berikut.',
            'kiri_1' => 'Indonesia',
            'kanan_1' => 'Jakarta',
            'kiri_2' => 'Jepang',
        ], 1, true, 1, &$affectedExamIds]);

        self::assertSame('failed', $leftOnly['status'] ?? '');
        self::assertStringContainsString('Pasangan Matching nomor 2 belum lengkap', (string) ($leftOnly['message'] ?? ''));

        $rightOnly = $this->invokeImportHelper('import_single_question_row', [[
            'question_type' => 'matching',
            'question_text' => 'Cocokkan pasangan berikut.',
            'kiri_1' => 'Indonesia',
            'kanan_1' => 'Jakarta',
            'kanan_2' => 'Tokyo',
        ], 1, true, 1, &$affectedExamIds]);

        self::assertSame('failed', $rightOnly['status'] ?? '');
        self::assertStringContainsString('Pasangan Matching nomor 2 belum lengkap', (string) ($rightOnly['message'] ?? ''));
        self::assertSame(0, $wpdb->insertCalls);
    }

    public function test_import_single_question_row_accepts_complete_matching_pairs(): void
    {
        global $wpdb;
        $wpdb = new QuestionsImportPreviewFakeWpdb();
        $affectedExamIds = [];

        $result = $this->invokeImportHelper('import_single_question_row', [[
            'question_type' => 'matching',
            'question_text' => 'Cocokkan pasangan berikut.',
            'kiri_1' => 'Indonesia',
            'kanan_1' => 'Jakarta',
            'kiri_2' => 'Jepang',
            'kanan_2' => 'Tokyo',
        ], 1, true, 1, &$affectedExamIds]);

        self::assertSame('created', $result['status'] ?? '');
        self::assertGreaterThan(0, (int) ($result['question_id'] ?? 0));
    }

    public function test_import_single_question_row_rejects_ordering_keyed_gaps_but_accepts_freeform(): void
    {
        global $wpdb;
        $wpdb = new QuestionsImportPreviewFakeWpdb();
        $affectedExamIds = [];

        $gap = $this->invokeImportHelper('import_single_question_row', [[
            'question_type' => 'ordering',
            'question_text' => 'Urutkan langkah berikut.',
            'item_1' => 'Langkah pertama',
            'item_3' => 'Langkah ketiga',
            'options' => "Langkah pertama\nLangkah ketiga",
        ], 1, true, 1, &$affectedExamIds]);

        self::assertSame('failed', $gap['status'] ?? '');
        self::assertStringContainsString('ITEM_2 masih kosong', (string) ($gap['message'] ?? ''));

        $freeform = $this->invokeImportHelper('import_single_question_row', [[
            'question_type' => 'ordering',
            'question_text' => 'Urutkan langkah berikut.',
            'options' => "Langkah pertama\nLangkah kedua",
        ], 1, true, 1, &$affectedExamIds]);

        self::assertSame('created', $freeform['status'] ?? '');
    }

    public function test_parse_docx_block_maps_diagnostic_markers_to_active_fields(): void
    {
        $questionDiagnostic = $this->invokeImportHelper('create_docx_diagnostic_marker', [[
            'kind' => 'preserved',
            'feature' => 'paragraph_alignment',
            'message' => 'Alignment paragraf Word dipertahankan.',
        ]]);
        $optionDiagnostic = $this->invokeImportHelper('create_docx_diagnostic_marker', [[
            'kind' => 'fallback',
            'feature' => 'multiline_equation_normalized',
            'message' => 'Equation multiline Word dinormalisasi.',
        ]]);
        $explanationDiagnostic = $this->invokeImportHelper('create_docx_diagnostic_marker', [[
            'kind' => 'unsupported',
            'feature' => 'word_shape_ignored',
            'message' => 'Shape Word non-gambar diabaikan.',
        ]]);

        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION_TYPE: multiple_choice',
            'SOAL: Marker diagnostics field mapping',
            $questionDiagnostic,
            'PILIHAN_1:',
            $optionDiagnostic,
            'PEMBAHASAN:',
            $explanationDiagnostic,
            'PILIHAN_1: Opsi A',
            'PILIHAN_2: Opsi B',
            'PILIHAN_3: Opsi C',
            'JAWABAN: A',
        ]]);

        self::assertIsArray($row);
        self::assertArrayHasKey('__import_diagnostics', $row);

        $diagnostics = \CBT_Admin_Questions_Import_Helper::normalize_question_import_diagnostic_entries($row['__import_diagnostics']);
        self::assertCount(3, $diagnostics);
        self::assertSame('SOAL', $diagnostics[0]['field'] ?? '');
        self::assertSame('PILIHAN_1', $diagnostics[1]['field'] ?? '');
        self::assertSame('PEMBAHASAN', $diagnostics[2]['field'] ?? '');
    }

    public function test_aggregate_question_import_diagnostics_dedupes_and_truncates_detail_entries(): void
    {
        $rows = [
            [
                '__import_diagnostics' => [
                    [
                        'block_number' => 1,
                        'question_type' => 'multiple_choice',
                        'field' => 'SOAL',
                        'kind' => 'preserved',
                        'feature' => 'equation_visual',
                        'message' => 'Equation dipertahankan.',
                    ],
                    [
                        'block_number' => 1,
                        'question_type' => 'multiple_choice',
                        'field' => 'SOAL',
                        'kind' => 'preserved',
                        'feature' => 'equation_visual',
                        'message' => 'Equation dipertahankan.',
                    ],
                ],
            ],
        ];

        for ($index = 1; $index <= 205; $index++) {
            $rows[] = [
                '__import_diagnostics' => [
                    [
                        'block_number' => $index + 1,
                        'question_type' => 'multiple_choice',
                        'field' => 'PILIHAN_' . (($index % 5) + 1),
                        'kind' => 'fallback',
                        'feature' => 'feature_' . $index,
                        'message' => 'Fallback #' . $index,
                    ],
                ],
            ];
        }

        $summary = \CBT_Admin_Questions_Import_Helper::aggregate_question_import_diagnostics($rows);

        self::assertSame(1, (int) ($summary['diagnostic_counts']['preserved'] ?? 0));
        self::assertSame(205, (int) ($summary['diagnostic_counts']['fallback'] ?? 0));
        self::assertCount(200, $summary['diagnostic_entries'] ?? []);
        self::assertTrue((bool) ($summary['diagnostic_truncated'] ?? false));
    }

    public function test_summarize_created_question_items_and_default_selection_prioritize_questions_with_issues(): void
    {
        $items = [
            [
                'question_id' => 701,
                'block_number' => 1,
                'question_type' => 'multiple_choice',
                'preview' => 'Soal aman',
                'diagnostic_counts' => [
                    'preserved' => 3,
                    'fallback' => 0,
                    'unsupported' => 0,
                ],
            ],
            [
                'question_id' => 702,
                'block_number' => 2,
                'question_type' => 'multiple_choice',
                'preview' => 'Soal perlu dicek',
                'diagnostic_counts' => [
                    'preserved' => 1,
                    'fallback' => 2,
                    'unsupported' => 1,
                ],
            ],
            [
                'question_id' => 703,
                'block_number' => 3,
                'question_type' => 'essay',
                'preview' => 'Soal lain',
                'diagnostic_counts' => [
                    'preserved' => 4,
                    'fallback' => 0,
                    'unsupported' => 0,
                ],
            ],
        ];

        self::assertSame(
            [
                'preserved' => 8,
                'fallback' => 2,
                'unsupported' => 1,
            ],
            \CBT_Admin_Questions_Import_Helper::summarize_question_import_created_question_items($items)
        );
        self::assertSame(
            702,
            \CBT_Admin_Questions_Import_Helper::get_default_question_import_created_question_item_id($items)
        );
        self::assertSame(
            701,
            \CBT_Admin_Questions_Import_Helper::get_default_question_import_created_question_item_id([
                [
                    'question_id' => 701,
                    'diagnostic_counts' => ['preserved' => 1, 'fallback' => 0, 'unsupported' => 0],
                ],
                [
                    'question_id' => 703,
                    'diagnostic_counts' => ['preserved' => 2, 'fallback' => 0, 'unsupported' => 0],
                ],
            ])
        );
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

        self::assertSame(
            [
                ['option_text' => 'Satu', 'is_correct' => 1],
                ['option_text' => 'Dua', 'is_correct' => 0],
                ['option_text' => 'Tiga', 'is_correct' => 0],
            ],
            json_decode($multipleChoice, true)
        );
        self::assertSame(
            [
                ['option_text' => 'Satu', 'is_correct' => 1],
                ['option_text' => 'Dua', 'is_correct' => 0],
                ['option_text' => 'Tiga', 'is_correct' => 1],
            ],
            json_decode($multipleAnswer, true)
        );
        self::assertSame('', $multipleAnswerWithoutCorrect);
    }

    public function test_build_options_raw_from_import_preserves_multiline_rich_option_as_single_entry(): void
    {
        $multilineOption = '<div class="cbt-math cbt-math-block" data-cbt-math="\\begin{aligned}\\int_{-\\infty}^{\\infty} f(x)dx \\\\ &= \\sqrt{\\pi}\\end{aligned}" data-cbt-math-display="block">'
            . "∫_(-∞)^(∞) f(x)dx\n=√(π)"
            . '</div>';

        $raw = \CBT_Admin_Questions_Import_Helper::build_options_raw_from_import(
            'Opsi A||Opsi B||' . $multilineOption,
            'C',
            'multiple_choice'
        );

        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);
        self::assertCount(3, $decoded);
        self::assertSame($multilineOption, $decoded[2]['option_text']);
        self::assertSame(1, $decoded[2]['is_correct']);
    }

    public function test_parse_docx_block_moves_pembahasan_multiline_and_image_into_explanation(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Apa warna langit?',
            'QUESTION_TYPE: multiple_choice',
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
            'QUESTION_TYPE',
            'multiple_choice',
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
        self::assertContains('QUESTION_TYPE: multiple_choice', $normalizedLines);
        self::assertContains('EXPLANATION: Jakarta menjadi pusat pemerintahan.', $normalizedLines);
        self::assertContains('ANSWER: A', $normalizedLines);
        self::assertIsArray($row);
        self::assertSame('multiple_choice', $row['question_type']);
        self::assertStringContainsString('Jakarta menjadi pusat pemerintahan.', (string) $row['explanation']);
        self::assertStringNotContainsString('Jakarta menjadi pusat pemerintahan.', (string) $row['question_text']);
    }

    public function test_extract_docx_paragraph_text_preserves_word_line_breaks(): void
    {
        $text = $this->invokeImportHelper('extract_docx_paragraph_text', [
            '<w:r><w:t>FLOW IMPORT LINEBREAK 20260324 BARIS 1</w:t></w:r>'
            . '<w:r><w:br/></w:r>'
            . '<w:r><w:t>BARIS 2</w:t></w:r>',
        ]);

        self::assertSame("FLOW IMPORT LINEBREAK 20260324 BARIS 1\nBARIS 2", $text);
    }

    public function test_extract_docx_paragraph_text_preserves_common_word_equation_fragments(): void
    {
        $text = $this->invokeImportHelper('extract_docx_paragraph_text', [
            '<w:r><w:t>QUESTION: Sederhanakan </w:t></w:r>'
            . '<m:oMath xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'
            . '  <m:sSup>'
            . '    <m:e><m:r><m:t>x</m:t></m:r></m:e>'
            . '    <m:sup><m:r><m:t>2</m:t></m:r></m:sup>'
            . '  </m:sSup>'
            . '  <m:r><m:t> + </m:t></m:r>'
            . '  <m:f>'
            . '    <m:num><m:r><m:t>1</m:t></m:r></m:num>'
            . '    <m:den><m:r><m:t>2</m:t></m:r></m:den>'
            . '  </m:f>'
            . '  <m:r><m:t> + </m:t></m:r>'
            . '  <m:rad>'
            . '    <m:e><m:r><m:t>9</m:t></m:r></m:e>'
            . '  </m:rad>'
            . '</m:oMath>',
        ]);

        self::assertSame('QUESTION: Sederhanakan x^(2) + (1)/(2) + √(9)', $text);
    }

    public function test_complex_word_equation_converter_supports_nth_root_summation_and_binomial(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <m:oMath>
    <m:r><m:t>∝ &lt; </m:t></m:r>
    <m:rad>
      <m:deg><m:r><m:t>4</m:t></m:r></m:deg>
      <m:e>
        <m:d>
          <m:dPr><m:begChr m:val="("/><m:endChr m:val=")"/></m:dPr>
          <m:e>
            <m:sSup>
              <m:e>
                <m:d>
                  <m:dPr><m:begChr m:val="("/><m:endChr m:val=")"/></m:dPr>
                  <m:e><m:r><m:t>x + a</m:t></m:r></m:e>
                </m:d>
              </m:e>
              <m:sup><m:r><m:t>n</m:t></m:r></m:sup>
            </m:sSup>
          </m:e>
        </m:d>
      </m:e>
    </m:rad>
    <m:r><m:t> = </m:t></m:r>
    <m:nary>
      <m:naryPr><m:chr m:val="∑"/></m:naryPr>
      <m:sub><m:r><m:t>k = 0</m:t></m:r></m:sub>
      <m:sup><m:r><m:t>n</m:t></m:r></m:sup>
      <m:e>
        <m:d>
          <m:dPr><m:begChr m:val="("/><m:endChr m:val=")"/></m:dPr>
          <m:e>
            <m:eqArr>
              <m:e><m:r><m:t>n</m:t></m:r></m:e>
              <m:e><m:r><m:t>k</m:t></m:r></m:e>
            </m:eqArr>
          </m:e>
        </m:d>
        <m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>k</m:t></m:r></m:sup></m:sSup>
        <m:sSup><m:e><m:r><m:t>a</m:t></m:r></m:e><m:sup><m:r><m:t>n-k</m:t></m:r></m:sup></m:sSup>
      </m:e>
    </m:nary>
  </m:oMath>
</w:root>
XML;

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));
        $node = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'oMath')->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);

        $fallback = $this->invokeImportHelper('render_docx_math_node', [$node]);
        $katex = $this->invokeImportHelper('render_docx_math_node_to_katex', [$node]);

        self::assertStringContainsString('root[4]', (string) $fallback);
        self::assertStringContainsString('choose', (string) $fallback);
        self::assertStringContainsString('∑_(k = 0)^(n)', (string) $fallback);

        self::assertStringContainsString('\\propto', (string) $katex);
        self::assertStringContainsString('\\sqrt[4]', (string) $katex);
        self::assertStringContainsString('\\sum_{k = 0}^{n}', (string) $katex);
        self::assertStringContainsString('\\binom{n}{k}', (string) $katex);
        self::assertStringContainsString('{x}^{k}', (string) $katex);
        self::assertStringContainsString('{a}^{n-k}', (string) $katex);
    }

    public function test_word_multiline_integral_equation_preserves_breaks_and_defaults_nary_to_integral(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <m:oMathPara>
    <m:oMath>
      <m:nary>
        <m:sub><m:r><m:t>-∞</m:t></m:r></m:sub>
        <m:sup><m:r><m:t>∞</m:t></m:r></m:sup>
        <m:e><m:r><m:t>f(x)dx</m:t></m:r></m:e>
      </m:nary>
      <m:r><m:t>=</m:t></m:r>
      <m:d>
        <m:dPr><m:begChr m:val="["/><m:endChr m:val="]"/></m:dPr>
        <m:e><m:r><m:t>G(x)</m:t></m:r></m:e>
      </m:d>
      <m:sSup>
        <m:e><m:r><m:t></m:t></m:r></m:e>
        <m:sup><m:r><m:t>1/2</m:t></m:r></m:sup>
      </m:sSup>
      <m:r>
        <m:rPr><m:brk m:alnAt="1"/></m:rPr>
        <m:t>=</m:t>
      </m:r>
      <m:rad>
        <m:e><m:r><m:t>π</m:t></m:r></m:e>
      </m:rad>
    </m:oMath>
  </m:oMathPara>
</w:root>
XML;

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));
        $node = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'oMathPara')->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);

        $fallback = $this->invokeImportHelper('render_docx_math_node', [$node]);
        $katex = $this->invokeImportHelper('render_docx_math_node_to_katex', [$node]);

        self::assertStringContainsString("∫_(-∞)^(∞)", (string) $fallback);
        self::assertStringContainsString("\n=", (string) $fallback);

        self::assertStringContainsString('\\begin{aligned}', (string) $katex);
        self::assertStringContainsString('\\int_{-\\infty}^{\\infty}', (string) $katex);
        self::assertStringContainsString('\\\\ &=', (string) $katex);
        self::assertStringContainsString('\\sqrt{\\pi}', (string) $katex);
    }

    public function test_extract_docx_paragraph_text_preserves_common_word_symbols(): void
    {
        $text = $this->invokeImportHelper('extract_docx_paragraph_text', [
            '<w:r><w:t>QUESTION: Simbol </w:t></w:r>'
            . '<w:sym w:font="Symbol" w:char="F061"/>'
            . '<w:r><w:t> dan </w:t></w:r>'
            . '<w:sym w:font="Symbol" w:char="F0B1"/>',
        ]);

        self::assertSame('QUESTION: Simbol α dan ±', $text);
    }

    public function test_resolve_docx_run_styles_reads_word_font_size_in_points(): void
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML(
            '<w:r xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:rPr><w:sz w:val="27"/></w:rPr>'
            . '<w:t>Ukuran Font</w:t>'
            . '</w:r>'
        ));

        $run = $dom->documentElement;
        self::assertInstanceOf(\DOMElement::class, $run);

        $styles = $this->invokeImportHelper('resolve_docx_run_styles', [$run, []]);

        self::assertSame('13.5pt', $styles['font_size'] ?? '');
    }

    public function test_render_docx_inline_tokens_to_html_preserves_font_size_for_text_and_math(): void
    {
        $html = $this->invokeImportHelper('render_docx_inline_tokens_to_html', [[
            [
                'text' => 'Teks Besar',
                'styles' => ['font_size' => '16pt'],
                'href' => '',
                'kind' => 'text',
            ],
            [
                'text' => 'A=πr^(2)',
                'styles' => ['font_size' => '18pt'],
                'href' => '',
                'kind' => 'math',
                'math_source' => 'A=\\pi{r}^{2}',
            ],
        ]]);

        self::assertStringContainsString('font-size:16pt;', $html);
        self::assertStringContainsString('font-size:18pt;', $html);
        self::assertStringContainsString('data-cbt-math="A=\\pi{r}^{2}"', $html);
    }

    public function test_extract_docx_image_render_specs_from_xml_reads_word_dimensions_alignment_and_alt_text(): void
    {
        $xml = <<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:pPr><w:jc w:val="center"/></w:pPr>
  <w:r>
    <w:drawing>
      <wp:inline>
        <wp:extent cx="2286000" cy="1143000"/>
        <wp:docPr id="1" name="Diagram Tengah" descr="Diagram pusat"/>
        <a:graphic>
          <a:graphicData>
            <pic:pic>
              <pic:blipFill>
                <a:blip r:embed="rId5"/>
              </pic:blipFill>
            </pic:pic>
          </a:graphicData>
        </a:graphic>
      </wp:inline>
    </w:drawing>
  </w:r>
</w:p>
XML;

        $specs = $this->invokeImportHelper('extract_docx_image_render_specs_from_xml', [$xml, 'center']);

        self::assertCount(1, $specs);
        self::assertSame('rId5', $specs[0]['rid'] ?? '');
        self::assertSame('center', $specs[0]['alignment'] ?? '');
        self::assertSame('Diagram pusat', $specs[0]['alt'] ?? '');
        self::assertSame(240, $specs[0]['width_px'] ?? 0);
        self::assertSame(120, $specs[0]['height_px'] ?? 0);
    }

    public function test_extract_docx_image_render_specs_prefers_anchor_alignment_for_positioned_images(): void
    {
        $xml = <<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:pPr><w:jc w:val="left"/></w:pPr>
  <w:r>
    <w:drawing>
      <wp:anchor>
        <wp:positionH relativeFrom="column"><wp:align>right</wp:align></wp:positionH>
        <wp:extent cx="1905000" cy="952500"/>
        <wp:docPr id="2" name="Diagram Kanan" descr="Diagram sisi kanan"/>
        <a:graphic>
          <a:graphicData>
            <pic:pic>
              <pic:blipFill>
                <a:blip r:embed="rId9"/>
              </pic:blipFill>
            </pic:pic>
          </a:graphicData>
        </a:graphic>
      </wp:anchor>
    </w:drawing>
  </w:r>
</w:p>
XML;

        $specs = $this->invokeImportHelper('extract_docx_image_render_specs_from_xml', [$xml, 'left']);

        self::assertCount(1, $specs);
        self::assertSame('right', $specs[0]['alignment'] ?? '');
        self::assertSame(200, $specs[0]['width_px'] ?? 0);
        self::assertSame(100, $specs[0]['height_px'] ?? 0);
    }

    public function test_build_docx_image_html_fragment_preserves_size_and_alignment_styles(): void
    {
        $html = $this->invokeImportHelper('build_docx_image_html_fragment', [
            'http://example.test/diagram.png',
            [
                'alignment' => 'right',
                'alt' => 'Diagram kanan',
                'width_px' => 320,
                'height_px' => 180,
            ],
        ]);

        self::assertStringContainsString('<p style="text-align:right;">', $html);
        self::assertStringContainsString('alt="Diagram kanan"', $html);
        self::assertStringContainsString('width="320"', $html);
        self::assertStringContainsString('height="180"', $html);
        self::assertStringContainsString('width:320px', $html);
        self::assertStringContainsString('margin-left:auto', $html);
        self::assertStringContainsString('margin-right:0', $html);
    }

    public function test_extract_docx_image_html_fragments_accepts_valid_png_image(): void
    {
        $fragments = $this->extractDocxImageFragmentsForTarget(
            'word/media/diagram.png',
            $this->validDocxPngBinary()
        );

        self::assertCount(1, $fragments);
        self::assertStringContainsString('<img src="data:image/png;base64,', $fragments[0]);
    }

    public function test_extract_docx_image_html_fragments_rejects_php_image_relationship_target(): void
    {
        $fragments = $this->extractDocxImageFragmentsForTarget(
            'word/media/payload.php',
            "<?php echo 'owned';"
        );

        self::assertSame([], $fragments);
    }

    public function test_extract_docx_image_html_fragments_rejects_svg_image_relationship_target(): void
    {
        $fragments = $this->extractDocxImageFragmentsForTarget(
            'word/media/vector.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>'
        );

        self::assertSame([], $fragments);
    }

    public function test_extract_docx_image_html_fragments_rejects_fake_png_binary(): void
    {
        $fragments = $this->extractDocxImageFragmentsForTarget(
            'word/media/fake.png',
            'not actually an image'
        );

        self::assertSame([], $fragments);
    }

    public function test_extract_docx_image_html_fragments_rejects_extension_mime_mismatch(): void
    {
        $fragments = $this->extractDocxImageFragmentsForTarget(
            'word/media/diagram.jpg',
            $this->validDocxPngBinary()
        );

        self::assertSame([], $fragments);
    }

    public function test_extract_docx_image_html_fragments_rejects_missing_extension(): void
    {
        $fragments = $this->extractDocxImageFragmentsForTarget(
            'word/media/diagram',
            $this->validDocxPngBinary()
        );

        self::assertSame([], $fragments);
    }

    public function test_build_docx_question_text_renders_internal_newline_as_br(): void
    {
        $html = $this->invokeImportHelper('build_docx_question_text', [[
            "FLOW IMPORT LINEBREAK 20260324 BARIS 1\nBARIS 2",
        ]]);

        self::assertSame('<p>FLOW IMPORT LINEBREAK 20260324 BARIS 1<br />BARIS 2</p>', $html);
    }

    public function test_build_docx_question_text_preserves_list_html_fragments(): void
    {
        $html = $this->invokeImportHelper('build_docx_question_text', [[
            '<ul><li><p>Butir 1</p></li><li><p>Butir 2</p></li></ul>',
        ]]);

        self::assertSame('<ul><li><p>Butir 1</p></li><li><p>Butir 2</p></li></ul>', $html);
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
            'QUESTION_TYPE: multiple_choice',
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
            'QUESTION_TYPE: multiple_choice',
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
            'QUESTION_TYPE: multiple_choice',
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
            'QUESTION_TYPE: multiple_choice',
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

    public function test_parse_docx_true_false_matrix_preserves_rich_statement_html(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Tentukan Benar/Salah untuk tiap pernyataan.',
            'QUESTION_TYPE: true_false_matrix',
            'PERNYATAAN_1: Perhatikan poin berikut.',
            '__HTML__:' . base64_encode('<ul><li>Butir A</li><li>Butir B</li></ul>'),
            'KUNCI_1: benar',
            'PERNYATAAN_2: Pernyataan kedua.',
            'KUNCI_2: salah',
        ]]);

        self::assertIsArray($row);
        self::assertSame('true_false_matrix', $row['question_type']);
        $decoded = json_decode((string) $row['correct_text'], true);
        self::assertIsArray($decoded);
        self::assertSame(
            'Perhatikan poin berikut.<ul><li>Butir A</li><li>Butir B</li></ul>',
            $decoded['statements'][0]['text'] ?? ''
        );
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

    public function test_parse_docx_matching_and_cloze_dropdown_structured_blocks(): void
    {
        $matching = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Cocokkan negara dengan ibu kotanya.',
            'QUESTION_TYPE: matching',
            'KIRI_1: Indonesia',
            'KANAN_1: Jakarta',
            'KIRI_2: Jepang',
            'KANAN_2: Tokyo',
        ]]);
        $cloze = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Ibu kota Jepang adalah [DROPDOWN_1].',
            'QUESTION_TYPE: cloze_dropdown',
            'DROPDOWN_1_OPSI_1: Seoul',
            'DROPDOWN_1_OPSI_2: Tokyo',
            'DROPDOWN_1_JAWABAN: 2',
        ]]);

        self::assertIsArray($matching);
        self::assertSame('matching', $matching['question_type']);
        self::assertCount(2, $matching['matching_items']);
        self::assertSame('Indonesia', $matching['matching_items'][0]['prompt_text']);
        self::assertSame('Jakarta', $matching['matching_items'][0]['option_text']);

        self::assertIsArray($cloze);
        self::assertSame('cloze_dropdown', $cloze['question_type']);
        self::assertCount(1, $cloze['cloze_blanks']);
        self::assertSame('1', $cloze['cloze_blanks'][0]['blank_key']);
        self::assertSame(1, $cloze['cloze_blanks'][0]['options'][1]['is_correct']);
    }

    public function test_parse_docx_cloze_dropdown_ignores_options_beyond_template_limit(): void
    {
        $cloze = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pilih nilai [DROPDOWN_1].',
            'QUESTION_TYPE: cloze_dropdown',
            'DROPDOWN_1_OPSI_1: Satu',
            'DROPDOWN_1_OPSI_2: Dua',
            'DROPDOWN_1_OPSI_3: Tiga',
            'DROPDOWN_1_OPSI_4: Empat',
            'DROPDOWN_1_OPSI_5: Lima',
            'DROPDOWN_1_OPSI_6: Enam',
            'DROPDOWN_1_OPSI_7: Tujuh',
            'DROPDOWN_1_JAWABAN: 2',
        ]]);
        $invalidCorrect = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Pilih nilai [DROPDOWN_1].',
            'QUESTION_TYPE: cloze_dropdown',
            'DROPDOWN_1_OPSI_1: Satu',
            'DROPDOWN_1_OPSI_2: Dua',
            'DROPDOWN_1_OPSI_3: Tiga',
            'DROPDOWN_1_OPSI_4: Empat',
            'DROPDOWN_1_OPSI_5: Lima',
            'DROPDOWN_1_OPSI_6: Enam',
            'DROPDOWN_1_OPSI_7: Tujuh',
            'DROPDOWN_1_JAWABAN: 7',
        ]]);

        self::assertIsArray($cloze);
        self::assertCount(6, $cloze['cloze_blanks'][0]['options']);
        self::assertSame(['Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam'], array_column($cloze['cloze_blanks'][0]['options'], 'option_text'));
        self::assertNull($invalidCorrect);
    }

    public function test_parse_docx_categorization_and_table_completion_structured_blocks(): void
    {
        $categorization = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Kelompokkan contoh berikut.',
            'QUESTION_TYPE: categorization',
            'KATEGORI_1: Mamalia',
            'KATEGORI_2: Reptil',
            'ITEM_1: Kucing',
            'KUNCI_1: 1',
            'ITEM_2: Ular',
            'KUNCI_2: Reptil',
        ]]);
        $table = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Lengkapi tabel ibu kota.',
            'QUESTION_TYPE: table_completion',
            'TABLE_ROWS: 2',
            'TABLE_COLS: 2',
            'CELL_A1_TYPE: static',
            'CELL_A1_TEXT: Negara',
            'CELL_B1_TYPE: static',
            'CELL_B1_TEXT: Ibu kota',
            'CELL_A2_TYPE: static',
            'CELL_A2_TEXT: Jepang',
            'CELL_B2_TYPE: dropdown',
            'CELL_B2_OPSI_1: Seoul',
            'CELL_B2_OPSI_2: Tokyo',
            'CELL_B2_JAWABAN: 2',
        ]]);

        self::assertIsArray($categorization);
        self::assertSame('categorization', $categorization['question_type']);
        self::assertCount(2, $categorization['categorization_categories']);
        self::assertCount(2, $categorization['categorization_items']);
        self::assertSame('Ular', $categorization['categorization_items'][1]['item_text']);
        self::assertSame(2, $categorization['categorization_items'][1]['correct_category_index']);

        self::assertIsArray($table);
        self::assertSame('table_completion', $table['question_type']);
        self::assertSame(2, $table['table_completion']['row_count']);
        self::assertSame(2, $table['table_completion']['column_count']);
        $cells = array_column($table['table_completion']['cells'], null, 'cell_key');
        self::assertSame('dropdown', $cells['B2']['cell_type']);
        self::assertSame(1, $cells['B2']['options'][1]['is_correct']);
    }

    public function test_docx_key_normalization_recognizes_categorization_and_table_completion_keys(): void
    {
        self::assertTrue($this->invokeImportHelper('is_docx_key_only_line', ['KATEGORI_1']));
        self::assertTrue($this->invokeImportHelper('is_docx_key_only_line', ['ITEM_24']));
        self::assertTrue($this->invokeImportHelper('is_docx_key_only_line', ['KUNCI_24']));
        self::assertTrue($this->invokeImportHelper('is_docx_key_only_line', ['TABLE_ROWS']));
        self::assertTrue($this->invokeImportHelper('is_docx_key_only_line', ['CELL_B2_OPSI_6']));

        $normalizedLines = $this->invokeImportHelper('normalize_docx_extracted_lines', [[
            'KATEGORI_1',
            'Mamalia',
            'CELL_B2_TEXT',
            'Tokyo',
        ]]);

        self::assertContains('KATEGORI_1: Mamalia', $normalizedLines);
        self::assertContains('CELL_B2_TEXT: Tokyo', $normalizedLines);
    }

    public function test_build_word_template_lines_include_matching_and_cloze_dropdown_templates(): void
    {
        $matchingLines = $this->invokeImportHelper('build_word_template_lines', ['matching', 10]);
        $clozeLines = $this->invokeImportHelper('build_word_template_lines', ['cloze_dropdown', 10]);

        self::assertContains('JENIS_SOAL: matching', $matchingLines);
        self::assertContains('KIRI_1: Istilah pertama', $matchingLines);
        self::assertContains('KANAN_1: Pasangan pertama', $matchingLines);
        self::assertContains('JENIS_SOAL: cloze_dropdown', $clozeLines);
        self::assertContains('DROPDOWN_1_OPSI_1: Opsi 1A', $clozeLines);
        self::assertContains('DROPDOWN_1_JAWABAN: 2', $clozeLines);
    }

    public function test_build_word_template_lines_respects_custom_counts_for_structured_types(): void
    {
        $matchingLines = $this->invokeImportHelper('build_word_template_lines', ['matching', 10, ['pair_count' => 2]]);
        $clozeLines = $this->invokeImportHelper('build_word_template_lines', ['cloze_dropdown', 10, [
            'dropdown_count' => 1,
            'dropdown_option_count' => 2,
        ]]);
        $categorizationLines = $this->invokeImportHelper('build_word_template_lines', ['categorization', 10, [
            'category_count' => 3,
            'categorization_item_count' => 4,
        ]]);
        $tableLines = $this->invokeImportHelper('build_word_template_lines', ['table_completion', 10, [
            'table_rows' => 3,
            'table_cols' => 3,
        ]]);

        self::assertContains('KIRI_2: Istilah kedua', $matchingLines);
        self::assertNotContains('KIRI_3: Istilah ketiga', $matchingLines);
        self::assertContains('DROPDOWN_1_OPSI_2: Opsi 1B', $clozeLines);
        self::assertNotContains('DROPDOWN_1_OPSI_3: Opsi 1C', $clozeLines);
        self::assertStringNotContainsString('[DROPDOWN_2]', implode("\n", $clozeLines));
        self::assertContains('KATEGORI_3: Kategori 3', $categorizationLines);
        self::assertContains('ITEM_4: Item 4', $categorizationLines);
        self::assertNotContains('KATEGORI_4: Kategori 4', $categorizationLines);
        self::assertContains('TABLE_ROWS: 3', $tableLines);
        self::assertContains('TABLE_COLS: 3', $tableLines);
        self::assertContains('CELL_C3_TYPE: text', $tableLines);
        self::assertNotContains('CELL_D1_TYPE: static', $tableLines);
    }

    public function test_build_word_template_lines_clamps_counts_and_omits_unused_fields(): void
    {
        $multipleChoiceLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_choice', 10, ['option_count' => 99]]);
        $multipleAnswerLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_answer', 10, ['option_count' => 1]]);
        $shortAnswerLines = $this->invokeImportHelper('build_word_template_lines', ['short_answer', 10, ['input_count' => 1]]);
        $matrixLines = $this->invokeImportHelper('build_word_template_lines', ['true_false_matrix', 10, ['statement_count' => 99]]);
        $orderingLines = $this->invokeImportHelper('build_word_template_lines', ['ordering', 10, ['item_count' => 1]]);

        self::assertContains('PILIHAN_5: Opsi E', $multipleChoiceLines);
        self::assertNotContains('PILIHAN_6: Opsi F', $multipleChoiceLines);
        self::assertContains('PILIHAN_3: Pernyataan C', $multipleAnswerLines);
        self::assertNotContains('PILIHAN_4: Pernyataan D', $multipleAnswerLines);
        self::assertContains('JAWABAN: 1,3', $multipleAnswerLines);
        self::assertStringNotContainsString('JAWABAN: 1,3,5', implode("\n", $multipleAnswerLines));
        self::assertStringContainsString('[INPUT_1]', implode("\n", $shortAnswerLines));
        self::assertStringNotContainsString('[INPUT_2]', implode("\n", $shortAnswerLines));
        self::assertContains('JAWABAN_A: jawaban-1', $shortAnswerLines);
        self::assertNotContains('JAWABAN_B: jawaban-2', $shortAnswerLines);
        self::assertContains('PERNYATAAN_10: Pernyataan J', $matrixLines);
        self::assertNotContains('PERNYATAAN_11: Pernyataan K', $matrixLines);
        self::assertContains('ITEM_2: Langkah ke-2', $orderingLines);
        self::assertNotContains('ITEM_3: Langkah ke-3', $orderingLines);
    }

    public function test_build_word_template_lines_dynamic_extremes_keep_example_answers_valid(): void
    {
        $multipleChoiceMinLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_choice', 10, ['option_count' => 3]]);
        $multipleChoiceMaxLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_choice', 10, ['option_count' => 99]]);
        $multipleAnswerMinLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_answer', 10, ['option_count' => 1]]);
        $multipleAnswerMaxLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_answer', 10, ['option_count' => 99]]);
        $clozeMinLines = $this->invokeImportHelper('build_word_template_lines', ['cloze_dropdown', 10, [
            'dropdown_count' => 1,
            'dropdown_option_count' => 1,
        ]]);
        $clozeMaxLines = $this->invokeImportHelper('build_word_template_lines', ['cloze_dropdown', 10, [
            'dropdown_count' => 99,
            'dropdown_option_count' => 99,
        ]]);
        $categorizationMaxLines = $this->invokeImportHelper('build_word_template_lines', ['categorization', 10, [
            'category_count' => 99,
            'categorization_item_count' => 99,
        ]]);
        $tableMaxLines = $this->invokeImportHelper('build_word_template_lines', ['table_completion', 10, [
            'table_rows' => 99,
            'table_cols' => 99,
        ]]);

        self::assertContains('PILIHAN_3: Opsi C', $multipleChoiceMinLines);
        self::assertNotContains('PILIHAN_4: Opsi D', $multipleChoiceMinLines);
        self::assertContains('PILIHAN_5: Opsi E', $multipleChoiceMaxLines);
        self::assertNotContains('PILIHAN_6: Opsi F', $multipleChoiceMaxLines);
        $this->assertChoiceTemplateAnswersWithinOptionCount($multipleChoiceMinLines, 3);
        $this->assertChoiceTemplateAnswersWithinOptionCount($multipleChoiceMaxLines, 5);

        self::assertContains('PILIHAN_3: Pernyataan C', $multipleAnswerMinLines);
        self::assertNotContains('PILIHAN_4: Pernyataan D', $multipleAnswerMinLines);
        self::assertContains('PILIHAN_12: Pernyataan L', $multipleAnswerMaxLines);
        self::assertNotContains('PILIHAN_13: Pernyataan M', $multipleAnswerMaxLines);
        $this->assertChoiceTemplateAnswersWithinOptionCount($multipleAnswerMinLines, 3);
        $this->assertChoiceTemplateAnswersWithinOptionCount($multipleAnswerMaxLines, 12);

        self::assertContains('DROPDOWN_1_OPSI_2: Opsi 1B', $clozeMinLines);
        self::assertNotContains('DROPDOWN_1_OPSI_3: Opsi 1C', $clozeMinLines);
        self::assertStringNotContainsString('[DROPDOWN_2]', implode("\n", $clozeMinLines));
        self::assertContains('DROPDOWN_8_OPSI_6: Opsi 8F', $clozeMaxLines);
        self::assertNotContains('DROPDOWN_8_OPSI_7: Opsi 8G', $clozeMaxLines);
        $this->assertDropdownTemplateAnswersWithinOptionCount($clozeMinLines, 2);
        $this->assertDropdownTemplateAnswersWithinOptionCount($clozeMaxLines, 6);

        self::assertContains('KATEGORI_8: Kategori 8', $categorizationMaxLines);
        self::assertNotContains('KATEGORI_9: Kategori 9', $categorizationMaxLines);
        self::assertContains('ITEM_24: Item 24', $categorizationMaxLines);
        self::assertContains('KUNCI_24: 8', $categorizationMaxLines);
        self::assertNotContains('ITEM_25: Item 25', $categorizationMaxLines);

        self::assertContains('TABLE_ROWS: 8', $tableMaxLines);
        self::assertContains('TABLE_COLS: 6', $tableMaxLines);
        self::assertContains('CELL_F8_TYPE: static', $tableMaxLines);
        self::assertNotContains('CELL_G1_TYPE: static', $tableMaxLines);
        self::assertNotContains('CELL_A9_TYPE: static', $tableMaxLines);
    }

    public function test_describe_docx_block_failure_reports_specific_reason_for_empty_correct_option(): void
    {
        $message = $this->invokeImportHelper('describe_docx_block_failure', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'QUESTION_TYPE: multiple_choice',
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

    public function test_validate_parsed_rows_for_requested_import_type_rejects_docx_type_mismatch(): void
    {
        $result = $this->invokeImportHelper('validate_parsed_rows_for_requested_import_type', [[
            [
                '__import_source_block' => 2,
                'question_type' => 'multiple_choice',
                'question_text' => '<p>Soal MC yang salah menu.</p>',
            ],
        ], 'multiple_answer', 'docx']);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('import_type_mismatch', $result->get_error_code());
        self::assertStringContainsString('File DOCX terdeteksi berisi soal Multiple Choice', $result->get_error_message());
        self::assertStringContainsString('menu import aktif adalah Multiple Answer', $result->get_error_message());
        self::assertStringContainsString('#2 Multiple Choice', $result->get_error_message());
    }

    public function test_validate_parsed_rows_for_requested_import_type_rejects_docx_mismatch_for_all_supported_question_types(): void
    {
        $cases = [
            ['multiple_choice', 'multiple_answer'],
            ['multiple_answer', 'multiple_choice'],
            ['true_false', 'essay'],
            ['true_false_matrix', 'short_answer'],
            ['short_answer', 'true_false'],
            ['essay', 'multiple_choice'],
        ];

        foreach ($cases as [$detectedType, $requestedType]) {
            $result = $this->invokeImportHelper('validate_parsed_rows_for_requested_import_type', [[
                [
                    '__import_source_block' => 1,
                    'question_type' => $detectedType,
                    'question_text' => '<p>Blok ' . $detectedType . ' salah menu.</p>',
                ],
            ], $requestedType, 'docx']);

            self::assertInstanceOf(\WP_Error::class, $result);
            self::assertSame('import_type_mismatch', $result->get_error_code());
            self::assertStringContainsString(
                \CBT_Admin_Questions_Helper::get_question_type_label($detectedType),
                $result->get_error_message()
            );
            self::assertStringContainsString(
                \CBT_Admin_Questions_Helper::get_question_type_label($requestedType),
                $result->get_error_message()
            );
        }
    }

    public function test_parse_docx_multiple_choice_requires_explicit_question_type_marker(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'A. Jakarta',
            'B. Bandung',
            'C. Surabaya',
            'ANSWER: A',
        ]]);
        $message = $this->invokeImportHelper('describe_docx_block_failure', [[
            'QUESTION: Ibu kota Indonesia adalah?',
            'A. Jakarta',
            'B. Bandung',
            'C. Surabaya',
            'ANSWER: A',
        ]]);

        self::assertNull($row);
        self::assertSame('Setiap blok DOCX wajib mencantumkan JENIS_SOAL yang valid sesuai template resmi.', $message);
    }

    public function test_parse_docx_essay_preserves_rich_rubric_html_from_answer_context(): void
    {
        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION: Jelaskan proses fotosintesis.',
            'QUESTION_TYPE: essay',
            'ANSWER:',
            '__HTML__:' . base64_encode('<ol><li>Sebutkan bahan utama</li><li>Jelaskan hasil akhir</li></ol>'),
        ]]);

        self::assertIsArray($row);
        self::assertSame('essay', $row['question_type']);
        self::assertStringContainsString('<ol><li>Sebutkan bahan utama</li><li>Jelaskan hasil akhir</li></ol>', (string) $row['correct_text']);
        self::assertStringNotContainsString('Sebutkan bahan utama', (string) $row['question_text']);
    }

    public function test_parse_docx_block_supports_html_table_markers_in_question_option_and_explanation(): void
    {
        $questionTable = '<table><tbody><tr><td>2</td><td>4</td></tr></tbody></table>';
        $optionTable = '<table><tbody><tr><td>A</td><td>Benar</td></tr></tbody></table>';
        $explanationTable = '<table><tbody><tr><td>Kunci</td><td>Pilihan A</td></tr></tbody></table>';

        $row = $this->invokeImportHelper('parse_docx_multiple_choice_block', [[
            'QUESTION_TYPE: multiple_choice',
            'QUESTION: Perhatikan tabel berikut.',
            '__HTML__:' . base64_encode($questionTable),
            'A. Opsi dengan tabel',
            '__HTML__:' . base64_encode($optionTable),
            'B. Opsi dua',
            'C. Opsi tiga',
            'ANSWER: A',
            'PEMBAHASAN:',
            '__HTML__:' . base64_encode($explanationTable),
        ]]);

        self::assertIsArray($row);
        self::assertStringContainsString($questionTable, (string) $row['question_text']);
        self::assertStringContainsString($optionTable, (string) $row['options']);
        self::assertStringContainsString($explanationTable, (string) $row['explanation']);
    }

    public function test_build_word_template_lines_include_template_marker_and_explicit_question_type_for_choice_templates(): void
    {
        $multipleChoiceLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_choice', 10]);
        $multipleAnswerLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_answer', 10]);

        self::assertContains('CBT_TEMPLATE: question_import_v2', $multipleChoiceLines);
        self::assertContains('JENIS_SOAL: multiple_choice', $multipleChoiceLines);
        self::assertContains('PILIHAN_5: Opsi E', $multipleChoiceLines);
        self::assertContains('JAWABAN: 5', $multipleChoiceLines);
        self::assertContains('CBT_TEMPLATE: question_import_v2', $multipleAnswerLines);
        self::assertContains('JENIS_SOAL: multiple_answer', $multipleAnswerLines);
    }

    public function test_docx_template_marker_and_block_extraction_only_accept_official_header(): void
    {
        $officialLines = [
            'CBT_TEMPLATE: question_import_v2',
            'CATATAN_VALIDATOR: jangan hapus marker',
            '---',
            'JENIS_SOAL: multiple_choice',
            'QUESTION: Contoh soal',
            'A. Opsi 1',
            'B. Opsi 2',
            'C. Opsi 3',
            'ANSWER: A',
            '---',
        ];

        $hasMarker = $this->invokeImportHelper('docx_has_required_template_marker', [$officialLines]);
        $missingMarker = $this->invokeImportHelper('docx_has_required_template_marker', [[
            'CATATAN_VALIDATOR: tanpa marker resmi',
            '---',
            'JENIS_SOAL: multiple_choice',
        ]]);
        $blocks = $this->invokeImportHelper('extract_docx_question_blocks', [$officialLines]);

        self::assertTrue($hasMarker);
        self::assertFalse($missingMarker);
        self::assertCount(1, $blocks);
        self::assertSame('JENIS_SOAL: multiple_choice', $blocks[0][0]);
    }

    public function test_extract_docx_content_lines_preserves_table_nodes_as_html_markers(): void
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p><w:r><w:t>QUESTION: Soal dengan tabel</w:t></w:r></w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Kolom 1</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Kolom 2</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $lines = $this->invokeImportHelper('extract_docx_content_lines', [$documentXml, [], new \ZipArchive()]);
        $tableMarker = null;
        foreach ($lines as $line) {
            if (str_starts_with((string) $line, '__HTML__:')) {
                $tableMarker = (string) $line;
                break;
            }
        }

        self::assertContains('QUESTION: Soal dengan tabel', $lines);
        self::assertNotNull($tableMarker);

        $decodedTableHtml = $this->invokeImportHelper('decode_docx_html_marker', [$tableMarker]);
        self::assertStringContainsString('<table>', $decodedTableHtml);
        self::assertStringContainsString('Kolom 1', $decodedTableHtml);
        self::assertStringContainsString('Kolom 2', $decodedTableHtml);
    }

    public function test_extract_docx_content_lines_reads_official_template_table_layout_as_key_value_lines(): void
    {
        $officialLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_choice', 10]);
        $documentXml = $this->invokeImportHelper('build_minimal_docx_document_xml', [$officialLines]);

        $lines = $this->invokeImportHelper('extract_docx_content_lines', [$documentXml, [], new \ZipArchive()]);
        $normalizedLines = $this->invokeImportHelper('normalize_docx_extracted_lines', [$lines]);

        self::assertContains('CBT_TEMPLATE: question_import_v2', $normalizedLines);
        self::assertContains('---', $normalizedLines);
        self::assertContains('JENIS_SOAL: multiple_choice', $normalizedLines);
        self::assertContains('SOAL: [MC 1] Tulis pertanyaan pilihan ganda di sini.', $normalizedLines);
        self::assertContains('PILIHAN_1: Opsi A', $normalizedLines);
        self::assertContains('JAWABAN: 1', $normalizedLines);
    }

    public function test_parse_question_docx_accepts_official_generated_template_without_modification(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $officialLines = $this->invokeImportHelper('build_word_template_lines', ['multiple_choice', 10]);
        $documentXml = $this->invokeImportHelper('build_minimal_docx_document_xml', [$officialLines]);

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-official-template-roundtrip-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);

            self::assertFalse(is_wp_error($rows));
            self::assertIsArray($rows);
            self::assertCount(10, $rows);
            self::assertSame('multiple_choice', $rows[0]['question_type']);
            self::assertStringContainsString('[MC 1]', (string) $rows[0]['question_text']);
            self::assertSame('A', $rows[0]['correct_answer']);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_parse_question_docx_accepts_generated_templates_for_all_supported_types(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        foreach ([
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
        ] as $questionType) {
            $officialLines = $this->invokeImportHelper('build_word_template_lines', [$questionType, 5]);
            $documentXml = $this->invokeImportHelper('build_minimal_docx_document_xml', [$officialLines]);

            $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-template-roundtrip-all-');
            self::assertIsString($tmpPath);
            self::assertNotSame('', $tmpPath);

            $zip = new \ZipArchive();
            self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();

            try {
                $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);

                self::assertFalse(is_wp_error($rows), 'Generated template failed to parse for ' . $questionType);
                self::assertIsArray($rows);
                self::assertCount(5, $rows, 'Generated template row count mismatch for ' . $questionType);
                self::assertSame($questionType, $rows[0]['question_type'] ?? '', 'Generated template type mismatch for ' . $questionType);
            } finally {
                @unlink($tmpPath);
            }
        }
    }

    public function test_extract_docx_content_lines_preserves_table_caption_colspan_and_rowspan(): void
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p><w:r><w:t>QUESTION: Soal dengan tabel merge</w:t></w:r></w:p>
    <w:tbl>
      <w:tblPr>
        <w:tblCaption w:val="Tabel gabungan penting"/>
      </w:tblPr>
      <w:tr>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/></w:tcPr>
          <w:p><w:r><w:t>Header gabung</w:t></w:r></w:p>
        </w:tc>
      </w:tr>
      <w:tr>
        <w:tc>
          <w:tcPr><w:vMerge w:val="restart"/></w:tcPr>
          <w:p><w:r><w:t>Baris kiri</w:t></w:r></w:p>
        </w:tc>
        <w:tc>
          <w:p><w:r><w:t>Baris kanan 1</w:t></w:r></w:p>
        </w:tc>
      </w:tr>
      <w:tr>
        <w:tc>
          <w:tcPr><w:vMerge/></w:tcPr>
          <w:p/>
        </w:tc>
        <w:tc>
          <w:p><w:r><w:t>Baris kanan 2</w:t></w:r></w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $lines = $this->invokeImportHelper('extract_docx_content_lines', [$documentXml, [], new \ZipArchive()]);
        $tableMarker = null;
        foreach ($lines as $line) {
            if (str_starts_with((string) $line, '__HTML__:')) {
                $tableMarker = (string) $line;
                break;
            }
        }

        self::assertNotNull($tableMarker);
        $decodedTableHtml = $this->invokeImportHelper('decode_docx_html_marker', [$tableMarker]);
        self::assertStringContainsString('<figure>', $decodedTableHtml);
        self::assertStringContainsString('<figcaption>Tabel gabungan penting</figcaption>', $decodedTableHtml);
        self::assertStringContainsString('colspan="2"', $decodedTableHtml);
        self::assertStringContainsString('rowspan="2"', $decodedTableHtml);
        self::assertStringContainsString('Header gabung', $decodedTableHtml);
        self::assertStringContainsString('Baris kanan 2', $decodedTableHtml);
    }

    public function test_parse_question_docx_preserves_word_bullet_and_numbering_lists_in_question_option_and_explanation(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p><w:r><w:t>SOAL: Perhatikan poin penting berikut.</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>
      <w:r><w:t>Marker bullet pertama soal</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>
      <w:r><w:t>Marker bullet kedua soal</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_1: Opsi dengan langkah</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr>
      <w:r><w:t>Langkah satu opsi</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr>
      <w:r><w:t>Langkah dua opsi</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi dua</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_3: Opsi tiga</w:t></w:r></w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p><w:r><w:t>PEMBAHASAN:</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>
      <w:r><w:t>Bullet pembahasan pertama</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>
      <w:r><w:t>Bullet pembahasan kedua</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $numberingXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="1">
    <w:lvl w:ilvl="0">
      <w:numFmt w:val="bullet"/>
    </w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="2">
    <w:lvl w:ilvl="0">
      <w:numFmt w:val="decimal"/>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="1">
    <w:abstractNumId w:val="1"/>
  </w:num>
  <w:num w:numId="2">
    <w:abstractNumId w:val="2"/>
  </w:num>
</w:numbering>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-list-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/numbering.xml', $numberingXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertSame('multiple_choice', $rows[0]['question_type']);
        self::assertStringContainsString('<ul>', (string) $rows[0]['question_text']);
        self::assertStringContainsString('Marker bullet pertama soal', (string) $rows[0]['question_text']);
        self::assertStringContainsString('Marker bullet kedua soal', (string) $rows[0]['question_text']);
        self::assertStringContainsString('<ol>', (string) $rows[0]['options']);
        self::assertStringContainsString('Langkah satu opsi', (string) $rows[0]['options']);
        self::assertStringContainsString('Langkah dua opsi', (string) $rows[0]['options']);
        self::assertStringContainsString('<ul>', (string) $rows[0]['explanation']);
        self::assertStringContainsString('Bullet pembahasan pertama', (string) $rows[0]['explanation']);
        self::assertStringContainsString('Bullet pembahasan kedua', (string) $rows[0]['explanation']);
    }

    public function test_parse_question_docx_preserves_common_word_equations_in_question_option_and_explanation(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>SOAL: Sederhanakan </w:t></w:r>
      <m:oMath>
        <m:sSup>
          <m:e><m:r><m:t>x</m:t></m:r></m:e>
          <m:sup><m:r><m:t>2</m:t></m:r></m:sup>
        </m:sSup>
        <m:r><m:t> + </m:t></m:r>
        <m:f>
          <m:num><m:r><m:t>1</m:t></m:r></m:num>
          <m:den><m:r><m:t>2</m:t></m:r></m:den>
        </m:f>
      </m:oMath>
    </w:p>
    <w:p>
      <w:r><w:t>PILIHAN_1: Hasil </w:t></w:r>
      <m:oMath>
        <m:rad>
          <m:e><m:r><m:t>9</m:t></m:r></m:e>
        </m:rad>
      </m:oMath>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi dua</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_3: Opsi tiga</w:t></w:r></w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p><w:r><w:t>PEMBAHASAN: Gunakan bentuk </w:t></w:r>
      <m:oMath>
        <m:func>
          <m:fName><m:r><m:t>sin</m:t></m:r></m:fName>
          <m:e><m:r><m:t>x</m:t></m:r></m:e>
        </m:func>
      </m:oMath>
    </w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-math-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertSame('multiple_choice', $rows[0]['question_type']);
        self::assertStringContainsString('class="cbt-math"', (string) $rows[0]['question_text']);
        self::assertStringContainsString('data-cbt-math-display="inline"', (string) $rows[0]['question_text']);
        self::assertStringContainsString('x^(2) + (1)/(2)', (string) $rows[0]['question_text']);
        self::assertStringContainsString('\\frac{1}{2}', (string) $rows[0]['question_text']);
        self::assertStringContainsString('class="cbt-math"', (string) $rows[0]['options']);
        self::assertStringContainsString('√(9)', (string) $rows[0]['options']);
        self::assertStringContainsString('\\sqrt{9}', (string) $rows[0]['options']);
        self::assertStringContainsString('class="cbt-math"', (string) $rows[0]['explanation']);
        self::assertStringContainsString('sin(x)', (string) $rows[0]['explanation']);
        self::assertStringContainsString('\\sin(x)', (string) $rows[0]['explanation']);
    }

    public function test_parse_question_docx_uses_block_math_wrapper_for_equation_only_paragraphs(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: essay</w:t></w:r></w:p>
    <w:p><w:r><w:t>SOAL: FLOW EQUATION BLOCK 20260328</w:t></w:r></w:p>
    <w:p><w:r><w:t>JAWABAN:</w:t></w:r></w:p>
    <w:p>
      <m:oMath>
        <m:f>
          <m:num><m:r><m:t>a+b</m:t></m:r></m:num>
          <m:den><m:r><m:t>c</m:t></m:r></m:den>
        </m:f>
      </m:oMath>
    </w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-math-block-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertSame('essay', $rows[0]['question_type']);
        self::assertStringContainsString('data-cbt-math-display="block"', (string) $rows[0]['correct_text']);
        self::assertStringContainsString('cbt-math-block', (string) $rows[0]['correct_text']);
        self::assertStringContainsString('\\frac{a+b}{c}', (string) $rows[0]['correct_text']);
        self::assertStringContainsString('(a+b)/(c)', (string) $rows[0]['correct_text']);
    }

    public function test_parse_question_docx_preserves_inline_formatting_and_hyperlinks_in_question_option_and_explanation(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>SOAL: Perhatikan </w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>teks penting</w:t></w:r>
      <w:r><w:t>, </w:t></w:r>
      <w:r><w:rPr><w:i/></w:rPr><w:t>catatan miring</w:t></w:r>
      <w:r><w:t>, dan </w:t></w:r>
      <w:hyperlink r:id="rId9">
        <w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>link resmi</w:t></w:r>
      </w:hyperlink>
      <w:r><w:t>. Rumus kimia H</w:t></w:r>
      <w:r><w:rPr><w:vertAlign w:val="subscript"/></w:rPr><w:t>2</w:t></w:r>
      <w:r><w:t>O dan luas m</w:t></w:r>
      <w:r><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:t>2</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>PILIHAN_1: Opsi </w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>utama</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi dua</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_3: Opsi tiga</w:t></w:r></w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>PEMBAHASAN: Gunakan </w:t></w:r>
      <w:r><w:rPr><w:i/></w:rPr><w:t>referensi</w:t></w:r>
      <w:r><w:t> dan </w:t></w:r>
      <w:hyperlink r:id="rId10">
        <w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>buka sumber</w:t></w:r>
      </w:hyperlink>
    </w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $relsXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/docs" TargetMode="External"/>
  <Relationship Id="rId10" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/explanation" TargetMode="External"/>
</Relationships>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-inline-format-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $relsXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertSame('multiple_choice', $rows[0]['question_type']);
        self::assertStringContainsString('<strong>teks penting</strong>', (string) $rows[0]['question_text']);
        self::assertStringContainsString('<em>catatan miring</em>', (string) $rows[0]['question_text']);
        self::assertStringContainsString('<a href="https://example.test/docs"', (string) $rows[0]['question_text']);
        self::assertStringContainsString('H<sub>2</sub>O', (string) $rows[0]['question_text']);
        self::assertStringContainsString('m<sup>2</sup>', (string) $rows[0]['question_text']);
        self::assertStringContainsString('<strong>utama</strong>', (string) $rows[0]['options']);
        self::assertStringContainsString('<em>referensi</em>', (string) $rows[0]['explanation']);
        self::assertStringContainsString('<a href="https://example.test/explanation"', (string) $rows[0]['explanation']);
    }

    public function test_parse_question_docx_preserves_word_symbols_in_question_and_explanation(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>SOAL: Simbol </w:t></w:r>
      <w:sym w:font="Symbol" w:char="F061"/>
      <w:r><w:t> dan </w:t></w:r>
      <w:sym w:font="Symbol" w:char="F0B1"/>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_1: Opsi A</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi B</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_3: Opsi C</w:t></w:r></w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>PEMBAHASAN: Gunakan simbol </w:t></w:r>
      <w:sym w:font="Symbol" w:char="F06D"/>
    </w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-symbol-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertStringContainsString('Simbol α dan ±', (string) $rows[0]['question_text']);
        self::assertStringContainsString('Gunakan simbol μ', (string) $rows[0]['explanation']);
    }

    public function test_parse_question_docx_preserves_font_size_as_inline_style_markup(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>SOAL: Teks </w:t></w:r>
      <w:r><w:rPr><w:sz w:val="32"/></w:rPr><w:t>besar</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_1: Opsi A</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi B</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>PILIHAN_3: </w:t></w:r>
      <m:oMathPara>
        <m:oMathParaPr><m:jc m:val="left"/></m:oMathParaPr>
        <m:oMath>
          <m:r>
            <w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math"/><w:sz w:val="36"/></w:rPr>
            <m:t>A=π</m:t>
          </m:r>
          <m:sSup>
            <m:e><m:r><m:t>r</m:t></m:r></m:e>
            <m:sup><m:r><m:t>2</m:t></m:r></m:sup>
          </m:sSup>
        </m:oMath>
      </m:oMathPara>
    </w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-font-size-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertStringContainsString('font-size:16pt;', (string) $rows[0]['question_text']);
        self::assertStringContainsString('font-size:18pt;', (string) $rows[0]['options']);
        self::assertStringContainsString('data-cbt-math="A=\\pi{r}^{2}"', (string) $rows[0]['options']);
    }

    public function test_parse_question_docx_preserves_paragraph_alignment_for_block_math_and_text(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:jc w:val="center"/></w:pPr>
      <w:r><w:t>SOAL: Rumus berikut berada di tengah </w:t></w:r>
      <m:oMathPara>
        <m:oMathParaPr><m:jc m:val="center"/></m:oMathParaPr>
        <m:oMath>
          <m:r><m:t>A=π</m:t></m:r>
          <m:sSup><m:e><m:r><m:t>r</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup>
        </m:oMath>
      </m:oMathPara>
    </w:p>
    <w:p><w:r><w:t>PILIHAN_1: Opsi A</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi B</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:jc w:val="right"/></w:pPr>
      <w:r><w:t>PILIHAN_3: Opsi rata kanan</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:jc w:val="both"/></w:pPr>
      <w:r><w:t>PEMBAHASAN: Pembahasan justify untuk uji render.</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-align-import-test-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertStringContainsString('style="text-align:center;"', (string) $rows[0]['question_text']);
        self::assertStringContainsString('data-cbt-math="A=\\pi{r}^{2}"', (string) $rows[0]['question_text']);
        self::assertStringContainsString('style="text-align:right;"', (string) $rows[0]['options']);
        self::assertStringContainsString('style="text-align:justify;"', (string) $rows[0]['explanation']);
    }

    public function test_parse_question_docx_preserves_multiline_integral_equation_as_block_math_markup(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <w:p><w:r><w:t>CBT_TEMPLATE: question_import_v2</w:t></w:r></w:p>
    <w:p><w:r><w:t>CATATAN_VALIDATOR: jangan hapus marker.</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
    <w:p><w:r><w:t>JENIS_SOAL: multiple_choice</w:t></w:r></w:p>
    <w:p><w:r><w:t>SOAL: Uji multiline integral</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_1: Opsi A</w:t></w:r></w:p>
    <w:p><w:r><w:t>PILIHAN_2: Opsi B</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t>PILIHAN_3: </w:t></w:r>
      <m:oMathPara>
        <m:oMath>
          <m:nary>
            <m:sub><m:r><m:t>-∞</m:t></m:r></m:sub>
            <m:sup><m:r><m:t>∞</m:t></m:r></m:sup>
            <m:e><m:r><m:t>f(x)dx</m:t></m:r></m:e>
          </m:nary>
          <m:r><m:t>=</m:t></m:r>
          <m:d>
            <m:dPr><m:begChr m:val="["/><m:endChr m:val="]"/></m:dPr>
            <m:e><m:r><m:t>G(x)</m:t></m:r></m:e>
          </m:d>
          <m:sSup>
            <m:e><m:r><m:t></m:t></m:r></m:e>
            <m:sup><m:r><m:t>1/2</m:t></m:r></m:sup>
          </m:sSup>
          <m:r>
            <m:rPr><m:brk m:alnAt="1"/></m:rPr>
            <m:t>=</m:t>
          </m:r>
          <m:rad>
            <m:e><m:r><m:t>π</m:t></m:r></m:e>
          </m:rad>
        </m:oMath>
      </m:oMathPara>
    </w:p>
    <w:p><w:r><w:t>JAWABAN: 1</w:t></w:r></w:p>
    <w:p><w:r><w:t>---</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-multiline-integral-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        try {
            $rows = $this->invokeImportHelper('parse_question_docx', [$tmpPath]);
        } finally {
            @unlink($tmpPath);
        }

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertStringContainsString('\\begin{aligned}', (string) $rows[0]['options']);
        self::assertStringContainsString('\\int_{-\\infty}^{\\infty}', (string) $rows[0]['options']);
        self::assertStringContainsString('data-cbt-math-display="block"', (string) $rows[0]['options']);
    }

    public function test_convert_docx_table_element_to_html_preserves_table_alignment_and_caption_alignment(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:tbl xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '  <w:tblPr><w:jc w:val="center"/><w:tblCaption w:val="Caption Tengah"/></w:tblPr>'
            . '  <w:tr>'
            . '    <w:tc>'
            . '      <w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:t>Sel kanan</w:t></w:r></w:p>'
            . '    </w:tc>'
            . '  </w:tr>'
            . '</w:tbl>'
        ));

        $table = $dom->documentElement;
        self::assertInstanceOf(\DOMElement::class, $table);

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-align-zip-');
        self::assertIsString($tmpPath);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString('dummy.txt', 'ok'));
        $zip->close();
        $zipRead = new \ZipArchive();
        self::assertNotSame(false, $zipRead->open($tmpPath));

        try {
            $html = $this->invokeImportHelper('convert_docx_table_element_to_html', [$table, [], $zipRead, []]);
        } finally {
            $zipRead->close();
            @unlink($tmpPath);
        }

        self::assertStringContainsString('<table style="margin-left:auto;margin-right:auto;">', $html);
        self::assertStringContainsString('<figcaption style="text-align:center;">Caption Tengah</figcaption>', $html);
        self::assertStringContainsString('<p style="text-align:right;">Sel kanan</p>', $html);
    }

    public function test_convert_docx_table_element_to_html_preserves_cell_alignment_background_width_and_vertical_align(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:tbl xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '  <w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:jc w:val="center"/></w:tblPr>'
            . '  <w:tblGrid><w:gridCol w:w="2400"/><w:gridCol w:w="4800"/></w:tblGrid>'
            . '  <w:tr>'
            . '    <w:trPr><w:tblHeader/></w:trPr>'
            . '    <w:tc>'
            . '      <w:tcPr><w:tcW w:w="2400" w:type="dxa"/><w:vAlign w:val="center"/><w:shd w:fill="D9EAF7"/></w:tcPr>'
            . '      <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Header 1</w:t></w:r></w:p>'
            . '    </w:tc>'
            . '    <w:tc>'
            . '      <w:tcPr><w:tcW w:w="4800" w:type="dxa"/><w:vAlign w:val="bottom"/><w:shd w:fill="D9EAF7"/></w:tcPr>'
            . '      <w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:t>Header 2</w:t></w:r></w:p>'
            . '    </w:tc>'
            . '  </w:tr>'
            . '  <w:tr>'
            . '    <w:tc>'
            . '      <w:tcPr><w:tcW w:w="2400" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>'
            . '      <w:p><w:pPr><w:jc w:val="left"/></w:pPr><w:r><w:t>Isi kiri</w:t></w:r></w:p>'
            . '    </w:tc>'
            . '    <w:tc>'
            . '      <w:tcPr><w:tcW w:w="4800" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
            . '      <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Isi tengah</w:t></w:r></w:p>'
            . '    </w:tc>'
            . '  </w:tr>'
            . '</w:tbl>'
        ));

        $table = $dom->documentElement;
        self::assertInstanceOf(\DOMElement::class, $table);

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-table-format-zip-');
        self::assertIsString($tmpPath);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString('dummy.txt', 'ok'));
        $zip->close();
        $zipRead = new \ZipArchive();
        self::assertNotSame(false, $zipRead->open($tmpPath));

        try {
            $html = $this->invokeImportHelper('convert_docx_table_element_to_html', [$table, [], $zipRead, []]);
        } finally {
            $zipRead->close();
            @unlink($tmpPath);
        }

        self::assertStringContainsString('table-layout:fixed;', $html);
        self::assertStringContainsString('<th', $html);
        self::assertStringContainsString('background-color:#D9EAF7;', $html);
        self::assertStringContainsString('vertical-align:middle;', $html);
        self::assertStringContainsString('vertical-align:bottom;', $html);
        self::assertStringContainsString('width:160px;', $html);
        self::assertStringContainsString('width:320px;', $html);
        self::assertStringContainsString('font-weight:700;', $html);
        self::assertStringContainsString('<p style="text-align:right;">Header 2</p>', $html);
        self::assertStringContainsString('<p style="text-align:center;">Isi tengah</p>', $html);
    }

    public function test_validate_parsed_rows_for_requested_import_type_ignores_rows_without_explicit_type_metadata(): void
    {
        $result = $this->invokeImportHelper('validate_parsed_rows_for_requested_import_type', [[
            [
                'question_text' => 'Soal tanpa metadata type eksplisit.',
            ],
        ], 'multiple_answer', 'docx']);

        self::assertTrue($result);
    }

    public function test_validate_question_import_upload_extension_accepts_only_docx(): void
    {
        self::assertTrue($this->invokeImportHelper('validate_question_import_upload_extension', ['docx']));

        $csvError = $this->invokeImportHelper('validate_question_import_upload_extension', ['csv']);
        self::assertInstanceOf(\WP_Error::class, $csvError);
        self::assertSame('question_import_extension_invalid', $csvError->get_error_code());

        $xlsxError = $this->invokeImportHelper('validate_question_import_upload_extension', ['xlsx']);
        self::assertInstanceOf(\WP_Error::class, $xlsxError);
        self::assertSame('question_import_extension_invalid', $xlsxError->get_error_code());
    }

    public function test_legacy_question_import_csv_xlsx_handlers_and_parsers_are_removed(): void
    {
        self::assertFalse(method_exists(\CBT_Admin_Questions_Actions::class, 'handle_download_question_template'));
        self::assertFalse(method_exists(\CBT_Admin_Questions_Actions::class, 'handle_download_question_template_xlsx'));
        self::assertFalse(method_exists(\CBT_Admin_Questions_Import_Helper::class, 'handle_download_question_template'));
        self::assertFalse(method_exists(\CBT_Admin_Questions_Import_Helper::class, 'handle_download_question_template_xlsx'));

        $helperReflection = new ReflectionClass(\CBT_Admin_Questions_Import_Helper::class);
        self::assertFalse($helperReflection->hasMethod('parse_question_csv'));
        self::assertFalse($helperReflection->hasMethod('parse_question_xlsx'));
    }

    public function test_admin_bootstrap_no_longer_registers_legacy_question_template_download_endpoints(): void
    {
        $bootstrapSource = file_get_contents(CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin.php');
        self::assertIsString($bootstrapSource);
        self::assertStringNotContainsString("add_action('admin_post_cbt_download_question_template',", $bootstrapSource);
        self::assertStringNotContainsString("add_action('admin_post_cbt_download_question_template_xlsx',", $bootstrapSource);
    }

    /**
     * @param string[] $lines
     */
    private function assertChoiceTemplateAnswersWithinOptionCount(array $lines, int $optionCount): void
    {
        $seenAnswerLine = false;
        foreach ($lines as $line) {
            if (preg_match('/^JAWABAN:\s*(.+)$/', trim((string) $line), $matches) !== 1) {
                continue;
            }

            $seenAnswerLine = true;
            $answers = preg_split('/\s*,\s*/', trim((string) ($matches[1] ?? '')));
            self::assertIsArray($answers);
            foreach ($answers as $answer) {
                $answerIndex = (int) $answer;
                self::assertGreaterThanOrEqual(1, $answerIndex, 'Template answer must point to an existing option.');
                self::assertLessThanOrEqual($optionCount, $answerIndex, 'Template answer must not point past generated options.');
            }
        }

        self::assertTrue($seenAnswerLine, 'Expected at least one JAWABAN line in generated choice template.');
    }

    /**
     * @param string[] $lines
     */
    private function assertDropdownTemplateAnswersWithinOptionCount(array $lines, int $optionCount): void
    {
        $seenAnswerLine = false;
        foreach ($lines as $line) {
            if (preg_match('/^DROPDOWN_\d+_JAWABAN:\s*(\d+)$/', trim((string) $line), $matches) !== 1) {
                continue;
            }

            $seenAnswerLine = true;
            $answerIndex = (int) ($matches[1] ?? 0);
            self::assertGreaterThanOrEqual(1, $answerIndex, 'Dropdown answer must point to an existing option.');
            self::assertLessThanOrEqual($optionCount, $answerIndex, 'Dropdown answer must not point past generated options.');
        }

        self::assertTrue($seenAnswerLine, 'Expected at least one DROPDOWN_n_JAWABAN line in generated cloze template.');
    }

    /**
     * @return string[]
     */
    private function extractDocxImageFragmentsForTarget(string $target, string $binary): array
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia.');
        }

        $xml = <<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:r>
    <w:drawing>
      <wp:inline>
        <wp:extent cx="952500" cy="952500"/>
        <wp:docPr id="1" name="Diagram"/>
        <a:graphic>
          <a:graphicData>
            <pic:pic>
              <pic:blipFill>
                <a:blip r:embed="rIdImage"/>
              </pic:blipFill>
            </pic:pic>
          </a:graphicData>
        </a:graphic>
      </wp:inline>
    </w:drawing>
  </w:r>
</w:p>
XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt-docx-image-hardening-');
        self::assertIsString($tmpPath);
        self::assertNotSame('', $tmpPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString($target, $binary));
        $zip->close();

        $zipRead = new \ZipArchive();
        self::assertNotSame(false, $zipRead->open($tmpPath));

        try {
            return $this->invokeImportHelper('extract_docx_image_html_fragments_from_xml', [
                $xml,
                ['rIdImage' => $target],
                $zipRead,
                'left',
            ]);
        } finally {
            $zipRead->close();
            @unlink($tmpPath);
        }
    }

    private function validDocxPngBinary(): string
    {
        $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
        self::assertIsString($binary);

        return $binary;
    }

    private function invokeImportHelper(string $method, array $args): mixed
    {
        $reflection = new ReflectionClass(\CBT_Admin_Questions_Import_Helper::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $args);
    }
}

final class QuestionsImportPreviewFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 100;
    public int $insertCalls = 0;

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

    public function get_var($prepared)
    {
        return 1;
    }

    public function get_row($prepared, $output = null): array
    {
        return ['question_id' => 101];
    }

    public function get_results($prepared, $output = null): array
    {
        return [];
    }

    public function get_col($prepared, $column = 0): array
    {
        return [];
    }

    public function insert($table, $data, $format = null): int
    {
        $this->insertCalls++;
        $this->insert_id++;

        return 1;
    }

    public function delete($table, $where, $whereFormat = null): int
    {
        return 1;
    }

    public function query($prepared): int
    {
        return 1;
    }
}
