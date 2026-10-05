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
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-subjects-service.php';
    }

    public function test_subjects_page_renders_local_progress_and_area_refresh_hooks(): void
    {
        $html = $this->renderSubjectsView();

        self::assertStringContainsString('data-cbt-subject-root', $html);
        self::assertStringContainsString('data-cbt-subject-progress', $html);
        self::assertStringContainsString('data-cbt-subject-progress-percent', $html);
        self::assertStringContainsString('data-cbt-subject-progress-fill', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringNotContainsString('tanpa reload halaman global', $html);

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
        self::assertStringContainsString('cbtSubjectActionInFlight', $html);
        self::assertStringNotContainsString('window.location.href =', $html);
        self::assertStringNotContainsString('window.location.assign', $html);
        self::assertStringNotContainsString('location.reload', $html);
    }

    public function test_subjects_page_keeps_form_input_on_validation_error_and_notices_in_place(): void
    {
        $html = $this->renderSubjectsView(['error' => 'Nama subject sudah terdaftar pada subject lain.']);

        self::assertStringContainsString('data-cbt-subject-error-tab="form"', $html);
        self::assertStringContainsString('data-cbt-subject-error-tab="import"', $html);
        self::assertStringContainsString("const keepSourceForm = errorMessage !== '' && errorTab !== '';", $html);
        self::assertStringContainsString('class="wp-header-end"', $html);
        self::assertStringContainsString('notice notice-error is-dismissible inline', $html);
        self::assertStringContainsString('wp-updates-notice-added', $html);
    }

    public function test_subjects_page_locks_delete_for_subjects_in_use(): void
    {
        $html = $this->renderSubjectsView([
            'subjects' => [
                ['id' => 10, 'name' => 'Matematika', 'code' => 'MAT', 'description' => ''],
                ['id' => 11, 'name' => 'Seni Budaya', 'code' => '', 'description' => ''],
            ],
            'subject_usage_map' => [
                10 => ['exam_count' => 3, 'bank_question_count' => 12, 'choice_count' => 0, 'deletable' => false],
                11 => ['exam_count' => 0, 'bank_question_count' => 0, 'choice_count' => 4, 'deletable' => true],
            ],
        ]);

        self::assertStringContainsString('3 ujian', $html);
        self::assertStringContainsString('12 soal bank', $html);
        self::assertStringContainsString('4 siswa memilih', $html);
        self::assertStringContainsString('Tidak bisa dihapus: dipakai 3 ujian dan 12 soal di bank soal', $html);
        self::assertSame(1, substr_count($html, 'data-cbt-subject-async-link data-cbt-subject-progress-profile="delete"'));
        self::assertStringContainsString('pilihan mereka ikut dihapus', $html);
        self::assertMatchesRegularExpression('/value="10"[^>]*disabled/s', $html);
    }

    public function test_subjects_page_renders_search_filter_and_import_failure_details(): void
    {
        $html = $this->renderSubjectsView([
            'subject_search' => 'mat',
            'subject_import_result' => [
                'total' => 3,
                'created' => 1,
                'updated' => 1,
                'failed' => 1,
                'failures' => ['Baris 4 (Fisika): code MAT milik subject "Matematika", tetapi name sudah dipakai subject lain'],
            ],
        ]);

        self::assertStringContainsString('name="cbt_subject_q" value="mat"', $html);
        self::assertStringContainsString('Reset Pencarian', $html);
        self::assertStringContainsString('Hapus Semua Hasil Pencarian', $html);
        self::assertStringContainsString('Baris 4 (Fisika)', $html);
        self::assertStringNotContainsString('cbt_subject_filter_id', $html);
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

    /**
     * @param array<string,mixed> $overrides
     */
    private function renderSubjectsView(array $overrides = []): string
    {
        $default_subject_tab = 'list';
        $editing = null;
        $error = '';
        $notice = '';
        $subject_clear_edit_url = 'https://example.test/wp-admin/admin.php?page=cbt-subjects';
        $subject_current_page = 1;
        $subject_search = '';
        $subject_usage_map = [];
        $subjects_in_use_total = 0;
        $subject_import_failures = [];
        $subject_import_result = null;
        $subject_open_form_url = 'https://example.test/wp-admin/admin.php?page=cbt-subjects&cbt_subject_tab=form';
        $editing_usage = null;
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
        extract($overrides, EXTR_OVERWRITE);

        ob_start();
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/subjects/page.php';

        return (string) ob_get_clean();
    }
}
