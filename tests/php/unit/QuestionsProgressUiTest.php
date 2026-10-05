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
            'name="question_search"',
            'cbt_bulk_questions_action',
            'name="bulk_question_action"',
            'cbt_duplicate_question',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_questions_javascript_uses_progress_controller_without_global_reload_fallbacks(): void
    {
        foreach ([
            'startQuestionProgress',
            'completeQuestionProgress',
            'getQuestionTabPanels().forEach',
            'function getQuestionListPanel()',
            'const currentPanel = getQuestionListPanel();',
            'replaceQuestionRefreshAreas',
            'runQuestionLocalAction',
            'bindQuestionLocalActions();',
            'bindQuestionContinuations();',
            "questionContinuationInFlight = false;\n                            bindQuestionContinuations();",
            'cbt_questions_local_refresh',
            'cbtLocalActionInFlight',
            'cbtListFilterBound',
            'cbtListAsyncBound',
            'showQuestionLocalRefreshError',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }

        self::assertStringNotContainsString('window.location.href =', $this->viewSource);
        self::assertStringNotContainsString('window.location.assign', $this->viewSource);
        self::assertStringNotContainsString('location.reload', $this->viewSource);
    }

    public function test_questions_page_keeps_form_on_server_error_and_resets_after_create(): void
    {
        foreach ([
            'readQuestionResponseError',
            "const keepSourceForm = errorMessage !== '' && (sourceTab === 'form' || sourceTab === 'import');",
            'resetManualFormAfterCreate(source);',
            'wp-updates-notice-added',
            "['cbt_msg', 'cbt_err', 'cbt_question_tab', 'cbt_questions_local_refresh']",
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }

        self::assertStringNotContainsString('tanpa reload halaman global', $this->viewSource);
        // Akses storage hanya lewat helper try/catch; di mode privat setItem bisa melempar dan mematikan script.
        self::assertSame(1, substr_count($this->viewSource, 'window.localStorage.setItem(pageTabStorageKey'));
    }

    public function test_questions_page_notices_stay_out_of_hero_and_css_is_scoped(): void
    {
        self::assertStringContainsString('<hr class="wp-header-end" />', $this->viewSource);
        self::assertStringContainsString('notice notice-success is-dismissible inline', $this->viewSource);
        self::assertStringContainsString('notice notice-error is-dismissible inline', $this->viewSource);
        self::assertStringNotContainsString('fonts.googleapis.com', $this->viewSource);
        self::assertDoesNotMatchRegularExpression('/^\s*:root\s*\{/m', $this->viewSource);
    }

    public function test_duplicate_question_navigates_to_real_edit_form(): void
    {
        self::assertMatchesRegularExpression(
            '/cbt-questions-row-action--duplicate"[^\n]*data-cbt-questions-tab-link="form"/',
            $this->viewSource
        );
        self::assertDoesNotMatchRegularExpression(
            '/cbt-questions-row-action--duplicate"[^\n]*data-cbt-questions-async-link/',
            $this->viewSource
        );
        self::assertStringContainsString('min="0.01" max="999.99" required id="cbt-points"', $this->viewSource);
    }
}
