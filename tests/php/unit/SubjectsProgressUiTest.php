<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class SubjectsProgressUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-ui-helper.php';
    }

    public function test_subjects_page_renders_local_progress_and_area_refresh_hooks(): void
    {
        $html = $this->renderSubjectsView();

        self::assertStringContainsString('data-cbt-subject-root', $html);
        self::assertStringContainsString('data-cbt-subject-progress', $html);
        self::assertStringContainsString('data-cbt-subject-progress-percent', $html);
        self::assertStringContainsString('data-cbt-subject-progress-fill', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('tanpa reload halaman global', $html);

        self::assertStringContainsString('data-cbt-subject-refresh-area="notices"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-area="overview"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-area="form-panel"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-area="import-panel"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-area="list-panel"', $html);

        self::assertStringContainsString('data-cbt-subject-async-form', $html);
        self::assertStringContainsString('data-cbt-subject-async-link', $html);
        self::assertStringContainsString('data-cbt-subject-progress-profile="save"', $html);
        self::assertStringContainsString('data-cbt-subject-progress-profile="import"', $html);
        self::assertStringContainsString('data-cbt-subject-progress-profile="delete"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-areas="notices,overview,form-panel,list-panel"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-areas="notices,overview,import-panel,list-panel"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-areas="notices,overview,list-panel"', $html);

        self::assertStringContainsString('startSubjectProgress', $html);
        self::assertStringContainsString('completeSubjectProgress', $html);
        self::assertStringContainsString('replaceSubjectRefreshAreas', $html);
        self::assertStringContainsString('bindSubjectLocalActions();', $html);
        self::assertStringContainsString('bindSubjectImportContinuation();', $html);
        self::assertStringContainsString("subjectImportInFlight = false;\n                                bindSubjectImportContinuation();", $html);
        self::assertStringNotContainsString('window.location.href =', $html);
        self::assertStringNotContainsString('window.location.assign', $html);
        self::assertStringNotContainsString('location.reload', $html);
    }

    public function test_subjects_page_renders_import_panel_with_preview_state(): void
    {
        $html = $this->renderSubjectsView();

        self::assertStringContainsString('data-cbt-subject-progress-profile="import"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-areas="notices,overview,import-panel,list-panel"', $html);
    }

    public function test_subjects_page_renders_delete_action_with_local_progress(): void
    {
        $html = $this->renderSubjectsView();

        self::assertStringContainsString('data-cbt-subject-progress-profile="delete"', $html);
        self::assertStringContainsString('data-cbt-subject-refresh-areas="notices,overview,list-panel"', $html);
    }

    private function renderSubjectsView(): string
    {
        $default_subject_tab = 'list';
        $editing = null;
        $error = '';
        $notice = '';
        $subject_clear_edit_url = 'https://example.test/wp-admin/admin.php?page=cbt-subjects';
        $subject_current_page = 1;
        $subject_filter_id = 0;
        $subject_filter_options = [
            10 => 'Matematika',
        ];
        $subject_import_continue_url = '';
        $subject_import_created = 0;
        $subject_import_failed = 0;
        $subject_import_is_running = false;
        $subject_import_offset = 0;
        $subject_import_progress_percent = 0.0;
        $subject_import_state = null;
        $subject_import_token = '';
        $subject_import_total = 0;
        $subject_import_updated = 0;
        $subject_list_chip_label = '1 total';
        $subject_list_query_args = [
            'page' => 'cbt-subjects',
            'cbt_subject_per_page' => 20,
        ];
        $subject_list_total_label = 'Total subject: 1';
        $subject_pagination_links = [];
        $subject_per_page = 20;
        $subject_reset_filter_url = 'https://example.test/wp-admin/admin.php?page=cbt-subjects';
        $subject_tab_is_forced = false;
        $subject_total_pages = 1;
        $subjects = [
            [
                'id' => 10,
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => 'Mata pelajaran Matematika',
            ],
        ];
        $total_subjects = 1;
        $filtered_subject_total = 1;

        ob_start();
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/subjects/page.php';

        return (string) ob_get_clean();
    }
}
