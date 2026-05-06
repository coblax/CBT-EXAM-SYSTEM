<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class ExamCardsProgressUiTest extends TestCase
{
    private string $viewSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/exam-cards/page.php');
    }

    public function test_exam_cards_page_declares_local_progress_and_refresh_areas(): void
    {
        foreach ([
            'data-cbt-exam-cards-root',
            'data-cbt-exam-cards-progress',
            'data-cbt-exam-cards-progress-percent',
            'data-cbt-exam-cards-progress-fill',
            'role="progressbar"',
            'tanpa reload halaman global',
            'data-cbt-exam-cards-refresh-area="overview"',
            'data-cbt-exam-cards-refresh-area="notices"',
            'data-cbt-exam-cards-refresh-area="summary"',
            'data-cbt-exam-cards-refresh-area="form"',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_cards_actions_are_wired_for_local_area_updates_and_print_target(): void
    {
        foreach ([
            'data-cbt-exam-cards-print-form',
            'data-cbt-exam-cards-async-link',
            'data-cbt-exam-cards-progress-profile="reset"',
            'data-cbt-exam-cards-refresh-areas="overview,notices,summary,form"',
            'window.open',
            "form.setAttribute('target', targetName)",
            'watchPrintWindow',
            'replaceExamCardsRefreshAreas',
            'runExamCardsLocalRefresh',
            'bindExamCardsUi();',
            'Gagal memperbarui area Exam Cards',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_cards_javascript_avoids_global_reload_fallbacks(): void
    {
        self::assertStringNotContainsString('window.location.href =', $this->viewSource);
        self::assertStringNotContainsString('window.location.assign', $this->viewSource);
        self::assertStringNotContainsString('location.reload', $this->viewSource);
        self::assertStringNotContainsString('form.submit()', $this->viewSource);
        self::assertStringNotContainsString('requestSubmit', $this->viewSource);
    }
}
