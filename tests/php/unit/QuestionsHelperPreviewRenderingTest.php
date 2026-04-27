<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';

use CbtExamSystem\Tests\TestCase;

final class QuestionsHelperPreviewRenderingTest extends TestCase
{
    public function test_render_editor_html_preserves_data_images_spacers_and_line_break_fallback(): void
    {
        $renderedRichHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<p>Satu</p><p>&nbsp;</p><p>Dua</p><p><img src="data:image/png;base64,abc123" alt="" /></p>'
        );
        $renderedPlainText = \CBT_Admin_Questions_Helper::render_editor_html("Baris 1\n\nBaris 2");

        self::assertStringContainsString('cbt-rich-spacer', $renderedRichHtml);
        self::assertStringContainsString('data:image/png;base64,abc123', $renderedRichHtml);
        self::assertStringContainsString('Baris 1<br />Baris 2', $renderedPlainText);
    }

    public function test_render_editor_html_preserves_list_markup_for_preview_cards(): void
    {
        $renderedListHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<ul><li>Butir pertama</li><li>Butir kedua</li></ul><ol><li>Langkah 1</li><li>Langkah 2</li></ol>'
        );

        self::assertStringContainsString('<ul><li>Butir pertama</li><li>Butir kedua</li></ul>', $renderedListHtml);
        self::assertStringContainsString('<ol><li>Langkah 1</li><li>Langkah 2</li></ol>', $renderedListHtml);
    }

    public function test_render_editor_html_preserves_equation_wrapper_attributes_for_preview_cards(): void
    {
        $renderedMathHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<p><span class="cbt-math" data-cbt-math="\\frac{1}{2}" data-cbt-math-display="inline">(1)/(2)</span></p>'
            . '<div class="cbt-math cbt-math-block" data-cbt-math="\\sqrt{9}" data-cbt-math-display="block">√(9)</div>'
        );

        self::assertStringContainsString('class="cbt-math"', $renderedMathHtml);
        self::assertStringContainsString('data-cbt-math="\\frac{1}{2}"', $renderedMathHtml);
        self::assertStringContainsString('data-cbt-math-display="inline"', $renderedMathHtml);
        self::assertStringContainsString('data-cbt-math="\\sqrt{9}"', $renderedMathHtml);
        self::assertStringContainsString('data-cbt-math-display="block"', $renderedMathHtml);
    }

    public function test_render_editor_html_preserves_font_size_style_for_imported_rich_content(): void
    {
        $renderedHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<p><span style="font-size:16pt;">Teks besar</span></p>'
            . '<div class="cbt-math cbt-math-block" data-cbt-math="A=\\pi{r}^{2}" data-cbt-math-display="block" style="font-size:18pt;">A=πr^(2)</div>'
        );

        self::assertStringContainsString('style="font-size:16pt;"', $renderedHtml);
        self::assertStringContainsString('style="font-size:18pt;"', $renderedHtml);
        self::assertStringContainsString('data-cbt-math="A=\\pi{r}^{2}"', $renderedHtml);
    }

    public function test_render_editor_html_preserves_alignment_styles_for_paragraph_table_and_caption(): void
    {
        $renderedHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<p style="text-align:center;">Paragraf Tengah</p>'
            . '<figure><table style="margin-left:auto;margin-right:auto;"><tbody><tr><td><p style="text-align:right;">Sel kanan</p></td></tr></tbody></table><figcaption style="text-align:center;">Caption Tengah</figcaption></figure>'
        );

        self::assertStringContainsString('style="text-align:center;"', $renderedHtml);
        self::assertStringContainsString('style="margin-left:auto;margin-right:auto;"', $renderedHtml);
        self::assertStringContainsString('style="text-align:right;"', $renderedHtml);
    }

    public function test_render_editor_html_preserves_imported_image_size_and_alignment_styles(): void
    {
        $renderedHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<p style="text-align:center;"><img src="data:image/png;base64,abc123" alt="Diagram tengah" width="240" height="120" style="width:240px;max-width:100%;height:auto;display:block;margin-left:auto;margin-right:auto;" /></p>'
        );

        self::assertStringContainsString('style="text-align:center;"', $renderedHtml);
        self::assertStringContainsString('width="240"', $renderedHtml);
        self::assertStringContainsString('height="120"', $renderedHtml);
        self::assertStringContainsString('style="width:240px;max-width:100%;height:auto;display:block;margin-left:auto;margin-right:auto;"', $renderedHtml);
    }

    public function test_render_editor_html_preserves_imported_table_cell_formatting_styles(): void
    {
        $renderedHtml = \CBT_Admin_Questions_Helper::render_editor_html(
            '<table style="margin-left:auto;margin-right:auto;table-layout:fixed;width:100%;">'
            . '<tbody><tr>'
            . '<th style="text-align:center;vertical-align:middle;background-color:#D9EAF7;width:33.33%;font-weight:700;"><p style="text-align:center;">Header</p></th>'
            . '<td style="text-align:right;vertical-align:bottom;width:66.67%;"><p style="text-align:right;">Nilai</p></td>'
            . '</tr></tbody></table>'
        );

        self::assertStringContainsString('table-layout:fixed;width:100%;', $renderedHtml);
        self::assertStringContainsString('background-color:#D9EAF7;', $renderedHtml);
        self::assertStringContainsString('vertical-align:middle;', $renderedHtml);
        self::assertStringContainsString('width:66.67%;', $renderedHtml);
        self::assertStringContainsString('<p style="text-align:right;">Nilai</p>', $renderedHtml);
    }

    public function test_render_admin_student_preview_card_only_shows_pembahasan_when_explanation_has_content(): void
    {
        $emptyExplanationPreview = \CBT_Admin_Questions_Helper::render_admin_student_preview_card([
            'question_type' => 'multiple_choice',
            'question_text' => '<p>Pertanyaan uji</p>',
            'points' => 1,
            'explanation' => '<p>&nbsp;</p>',
        ]);

        $richExplanationPreview = \CBT_Admin_Questions_Helper::render_admin_student_preview_card([
            'question_type' => 'multiple_choice',
            'question_text' => '<p>Pertanyaan uji</p><table><tr><td>A</td></tr></table>',
            'points' => 1,
            'explanation' => '<table><tr><td>Penjelasan</td></tr></table><p>&nbsp;</p><p><img src="data:image/png;base64,def456" alt="" /></p>',
        ]);

        self::assertStringNotContainsString('Pembahasan', $emptyExplanationPreview);
        self::assertStringContainsString('Pembahasan', $richExplanationPreview);
        self::assertStringContainsString('<table><tr><td>A</td></tr></table>', $richExplanationPreview);
        self::assertStringContainsString('<table><tr><td>Penjelasan</td></tr></table>', $richExplanationPreview);
        self::assertStringContainsString('data:image/png;base64,def456', $richExplanationPreview);
    }

    public function test_render_admin_student_preview_card_can_hide_answer_key_for_student_print_mode(): void
    {
        $preview = \CBT_Admin_Questions_Helper::render_admin_student_preview_card(
            [
                'question_type' => 'multiple_choice',
                'question_text' => '<p>Pertanyaan uji</p>',
                'points' => 1,
                'explanation' => '<p>Pembahasan rahasia</p>',
            ],
            [
                [
                    'option_key' => 'A',
                    'option_text' => '<p>Opsi benar</p>',
                    'is_correct' => 1,
                ],
                [
                    'option_key' => 'B',
                    'option_text' => '<p>Opsi lain</p>',
                    'is_correct' => 0,
                ],
            ],
            [],
            [
                'answer_mode' => 'student',
                'show_answer_key' => false,
            ]
        );

        self::assertStringContainsString('Opsi benar', $preview);
        self::assertStringNotContainsString(' is-correct', $preview);
        self::assertStringNotContainsString('Kunci', $preview);
        self::assertStringNotContainsString('Pembahasan', $preview);
        self::assertStringNotContainsString('Pembahasan rahasia', $preview);
    }

    public function test_render_admin_student_preview_card_keeps_answer_key_for_teacher_print_mode(): void
    {
        $preview = \CBT_Admin_Questions_Helper::render_admin_student_preview_card(
            [
                'question_type' => 'multiple_choice',
                'question_text' => '<p>Pertanyaan uji</p>',
                'points' => 1,
                'explanation' => '<p>Pembahasan guru</p>',
            ],
            [
                [
                    'option_key' => 'A',
                    'option_text' => '<p>Opsi benar</p>',
                    'is_correct' => 1,
                ],
            ],
            [],
            [
                'answer_mode' => 'teacher',
                'show_answer_key' => true,
            ]
        );

        self::assertStringContainsString(' is-correct', $preview);
        self::assertStringContainsString('Kunci', $preview);
        self::assertStringContainsString('Pembahasan', $preview);
        self::assertStringContainsString('Pembahasan guru', $preview);
    }

    public function test_render_admin_student_preview_card_preserves_true_false_matrix_statement_lists(): void
    {
        $preview = \CBT_Admin_Questions_Helper::render_admin_student_preview_card([
            'question_type' => 'true_false_matrix',
            'question_text' => '<p>Cocokkan pernyataan berikut.</p>',
            'correct_text' => wp_json_encode([
                'statements' => [
                    [
                        'text' => '<ul><li>Butir 1</li><li>Butir 2</li></ul>',
                        'answer' => 'true',
                    ],
                    [
                        'text' => '<p>Pernyataan biasa</p>',
                        'answer' => 'false',
                    ],
                ],
            ]),
        ]);

        self::assertStringContainsString('<ul><li>Butir 1</li><li>Butir 2</li></ul>', $preview);
        self::assertStringContainsString('Kunci Benar', $preview);
        self::assertStringContainsString('Kunci Salah', $preview);
    }

    public function test_render_admin_student_preview_card_hides_matrix_answers_for_student_print_mode(): void
    {
        $preview = \CBT_Admin_Questions_Helper::render_admin_student_preview_card(
            [
                'question_type' => 'true_false_matrix',
                'question_text' => '<p>Cocokkan pernyataan berikut.</p>',
                'correct_text' => wp_json_encode([
                    'statements' => [
                        [
                            'text' => '<p>Pernyataan pertama</p>',
                            'answer' => 'true',
                        ],
                        [
                            'text' => '<p>Pernyataan kedua</p>',
                            'answer' => 'false',
                        ],
                    ],
                ]),
            ],
            [],
            [],
            [
                'answer_mode' => 'student',
                'show_answer_key' => false,
            ]
        );

        self::assertStringContainsString('Pernyataan pertama', $preview);
        self::assertStringContainsString('Pernyataan kedua', $preview);
        self::assertStringNotContainsString('Kunci Benar', $preview);
        self::assertStringNotContainsString('Kunci Salah', $preview);
        self::assertStringNotContainsString('cbt-admin-student-preview-matrix-answer', $preview);
    }

    public function test_normalize_optional_rich_text_treats_images_as_content(): void
    {
        self::assertNull(\CBT_Admin_Questions_Helper::normalize_optional_rich_text('<p>&nbsp;</p>'));
        self::assertSame(
            '<p><img src="data:image/png;base64,xyz" alt="" /></p>',
            \CBT_Admin_Questions_Helper::normalize_optional_rich_text('<p><img src="data:image/png;base64,xyz" alt="" /></p>')
        );
    }
}
