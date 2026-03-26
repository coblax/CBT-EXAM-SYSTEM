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

    public function test_normalize_optional_rich_text_treats_images_as_content(): void
    {
        self::assertNull(\CBT_Admin_Questions_Helper::normalize_optional_rich_text('<p>&nbsp;</p>'));
        self::assertSame(
            '<p><img src="data:image/png;base64,xyz" alt="" /></p>',
            \CBT_Admin_Questions_Helper::normalize_optional_rich_text('<p><img src="data:image/png;base64,xyz" alt="" /></p>')
        );
    }
}
