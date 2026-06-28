<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AdminExamsUiHardeningTest extends TestCase
{
    private string $viewSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/exams/page.php');
    }

    public function test_exam_snapshot_preview_ignores_stale_pagination_responses(): void
    {
        foreach ([
            'examSnapshotPreviewRequestSeq',
            'const requestSeq = examSnapshotPreviewRequestSeq',
            'requestSeq !== examSnapshotPreviewRequestSeq',
            'requestSeq === examSnapshotPreviewRequestSeq',
            'const liveSummaryRow = document.querySelector',
            'const livePreviewRow = document.querySelector',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_question_catalog_keeps_existing_stale_response_guard(): void
    {
        foreach ([
            'questionCatalogRequestSeq',
            'const requestSeq = questionCatalogRequestSeq',
            'requestSeq !== questionCatalogRequestSeq',
            'requestSeq === questionCatalogRequestSeq',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_edit_randomize_toggles_preserve_saved_state(): void
    {
        self::assertStringContainsString(
            "'randomize_questions' => (int) (\$editing_exam['randomize_questions'] ?? 1)",
            $this->viewSource
        );
        self::assertStringContainsString(
            "'randomize_options' => (int) (\$editing_exam['randomize_options'] ?? 1)",
            $this->viewSource
        );
        self::assertStringContainsString(
            'id="cbt-exam-randomize" name="randomize_questions" value="1" <?php checked((int) ($editing_exam[\'randomize_questions\'] ?? 1), 1); ?> autocomplete="off"',
            $this->viewSource
        );
        self::assertStringContainsString(
            'id="cbt-exam-randomize-options" name="randomize_options" value="1" <?php checked((int) ($editing_exam[\'randomize_options\'] ?? 1), 1); ?> autocomplete="off"',
            $this->viewSource
        );
        self::assertStringNotContainsString('name="randomize_questions" value="1" checked="checked"', $this->viewSource);
        self::assertStringNotContainsString('name="randomize_options" value="1" checked="checked"', $this->viewSource);
    }

    public function test_exam_list_title_cell_markup_keeps_balanced_divs(): void
    {
        $start = strpos($this->viewSource, '<div class="cbt-exam-list-title-cell">');
        self::assertIsInt($start);

        $end = strpos($this->viewSource, '</td>', $start);
        self::assertIsInt($end);

        $cellMarkup = substr($this->viewSource, $start, $end - $start);

        self::assertSame(2, substr_count($cellMarkup, '<div'));
        self::assertSame(2, substr_count($cellMarkup, '</div>'));
    }
}
