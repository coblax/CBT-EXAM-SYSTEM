<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class QuestionsProgressUiTest extends TestCase
{
    private string $viewSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/questions/page.php');
    }

    public function test_questions_page_declares_local_progress_and_refresh_areas(): void
    {
        foreach ([
            'data-cbt-questions-root',
            'data-cbt-questions-progress',
            'data-cbt-questions-progress-percent',
            'data-cbt-questions-progress-fill',
            'role="progressbar"',
            'tanpa reload halaman global',
            'data-cbt-questions-refresh-area="notices"',
            'data-cbt-questions-refresh-area="overview"',
            'data-cbt-questions-refresh-area="form-panel"',
            'data-cbt-questions-refresh-area="import-panel"',
            'data-cbt-questions-refresh-area="import-status"',
            'data-cbt-questions-refresh-area="list-panel"',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_question_actions_are_wired_for_local_area_updates(): void
    {
        foreach ([
            'data-cbt-questions-async-form',
            'data-cbt-questions-async-link',
            'data-cbt-questions-import-progress',
            'data-cbt-questions-delete-progress',
            'data-cbt-questions-progress-profile="save"',
            'data-cbt-questions-progress-profile="import"',
            'data-cbt-questions-progress-profile="delete"',
            'data-cbt-questions-progress-profile="list"',
            'data-cbt-questions-refresh-areas="notices,overview,list-panel"',
            'data-cbt-questions-refresh-areas="notices,overview,import-status,list-panel"',
            'data-cbt-questions-success-tab="list"',
            'data-cbt-questions-success-tab="import"',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_questions_javascript_uses_progress_controller_without_global_reload_fallbacks(): void
    {
        foreach ([
            'startQuestionProgress',
            'completeQuestionProgress',
            'replaceQuestionRefreshAreas',
            'runQuestionLocalAction',
            'bindQuestionLocalActions();',
            'bindQuestionContinuations();',
            'cbt_questions_local_refresh',
            'showQuestionLocalRefreshError',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }

        self::assertStringNotContainsString('window.location.href =', $this->viewSource);
        self::assertStringNotContainsString('window.location.assign', $this->viewSource);
        self::assertStringNotContainsString('location.reload', $this->viewSource);
    }
}
