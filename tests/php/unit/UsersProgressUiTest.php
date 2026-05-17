<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class UsersProgressUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-ui-helper.php';
    }

    public function test_users_page_renders_local_progress_and_area_refresh_hooks(): void
    {
        $html = $this->renderUsersView();

        self::assertStringContainsString('data-cbt-users-root', $html);
        self::assertStringContainsString('data-cbt-users-progress', $html);
        self::assertStringContainsString('data-cbt-users-progress-percent', $html);
        self::assertStringContainsString('data-cbt-users-progress-fill', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('tanpa reload halaman global', $html);

        self::assertStringContainsString('data-cbt-users-refresh-area="notices"', $html);
        self::assertStringContainsString('data-cbt-users-refresh-area="overview"', $html);
        self::assertStringContainsString('data-cbt-users-refresh-area="form-panel"', $html);
        self::assertStringContainsString('data-cbt-users-refresh-area="import-panel"', $html);
        self::assertStringContainsString('data-cbt-users-refresh-area="list-panel"', $html);

        self::assertStringContainsString('data-cbt-users-async-form', $html);
        self::assertStringContainsString('data-cbt-users-async-link', $html);
        self::assertStringContainsString('data-cbt-users-progress-profile="save"', $html);
        self::assertStringContainsString('data-cbt-users-progress-profile="import"', $html);
        self::assertStringContainsString('data-cbt-users-progress-profile="subject-choice"', $html);
        self::assertStringContainsString('data-cbt-users-progress-profile="delete"', $html);
        self::assertStringContainsString('diagnose: {', $html);
        self::assertStringContainsString('data-cbt-users-refresh-areas="notices,overview,form-panel,list-panel"', $html);
        self::assertStringContainsString('data-cbt-users-refresh-areas="notices,overview,import-panel,list-panel"', $html);
        self::assertStringContainsString('data-cbt-users-refresh-areas="notices,overview,list-panel"', $html);
        self::assertStringContainsString('data-cbt-users-import-progress', $html);
        self::assertStringContainsString('data-cbt-users-import-running="1"', $html);

        self::assertStringContainsString('startUsersProgress', $html);
        self::assertStringContainsString('completeUsersProgress', $html);
        self::assertStringContainsString('replaceUsersRefreshAreas', $html);
        self::assertStringContainsString('bindUsersLocalActions();', $html);
        self::assertStringContainsString('bindUsersImportContinuation();', $html);
        self::assertStringContainsString('cbt_users_local_refresh', $html);
        self::assertStringNotContainsString('window.location.href =', $html);
        self::assertStringNotContainsString('window.location.assign', $html);
        self::assertStringNotContainsString('location.reload', $html);
        self::assertStringNotContainsString('form.submit()', $html);
    }

    public function test_users_page_renders_import_progress_with_running_state(): void
    {
        $html = $this->renderUsersView();

        self::assertStringContainsString('data-cbt-users-import-progress', $html);
        self::assertStringContainsString('data-cbt-users-import-running="1"', $html);
        self::assertStringContainsString('bindUsersImportContinuation();', $html);
    }

    public function test_users_page_renders_diagnostic_and_subject_choice_hooks(): void
    {
        $html = $this->renderUsersView();

        self::assertStringContainsString('diagnose: {', $html);
        self::assertStringContainsString('data-cbt-users-progress-profile="subject-choice"', $html);
        self::assertStringContainsString('cbt_users_local_refresh', $html);
    }

    private function renderUsersView(): string
    {
        $agama_options = ['Islam', 'Kristen'];
        $current_page = 1;
        $default_user_tab = 'list';
        $diagnostic_clear_url = 'https://example.test/wp-admin/admin.php?page=cbt-user-import';
        $diagnostic_data = null;
        $diagnostic_user_id = 0;
        $editing_agama_form = '';
        $editing_foto = '';
        $editing_jenis_kelamin_form = '';
        $editing_kelas = '';
        $editing_nisn = '';
        $editing_role = '';
        $editing_ruang = '';
        $editing_subject_choice_ids = [];
        $editing_user = null;
        $editing_user_id = 0;
        $error = '';
        $filter_agama = '';
        $filter_jenis_kelamin = '';
        $filter_kelas = '';
        $filter_role = '';
        $filter_ruang = '';
        $import_batch_size = 100;
        $import_continue_url = 'https://example.test/wp-admin/admin.php?page=cbt-user-import&cbt_import_continue=1';
        $import_created = 25;
        $import_failed = 1;
        $import_is_running = true;
        $import_offset = 50;
        $import_preview = [
            'total' => 2,
            'created' => 1,
            'updated' => 1,
            'failed' => 0,
            'subject_choice_rows' => 1,
            'photo_required' => 0,
            'photo_missing' => 0,
            'can_continue' => true,
            'errors' => [],
        ];
        $import_preview_clear_url = 'https://example.test/wp-admin/admin.php?page=cbt-user-import&clear_import_preview=1';
        $import_preview_run_url = 'https://example.test/wp-admin/admin-post.php';
        $import_preview_state = ['token' => 'users-preview'];
        $import_preview_token = 'users-preview';
        $import_progress_percent = 25.0;
        $import_state = ['token' => 'users-import'];
        $import_token = 'users-import';
        $import_total = 200;
        $import_updated = 24;
        $is_admin_scope = true;
        $is_editing_user = false;
        $jenis_kelamin_options = ['Laki-laki', 'Perempuan'];
        $kelas_options = ['X-A'];
        $notice = '';
        $pagination_links = [];
        $per_page = 20;
        $per_page_options = [20, 50];
        $ruang_options = ['R1'];
        $search = '';
        $subject_choice_labels_by_user = [];
        $subject_choice_preview = [
            'total' => 2,
            'updated' => 1,
            'cleared' => 1,
            'failed' => 0,
            'can_continue' => true,
            'errors' => [],
        ];
        $subject_choice_preview_clear_url = 'https://example.test/wp-admin/admin.php?page=cbt-user-import&clear_subject_choice_preview=1';
        $subject_choice_preview_run_url = 'https://example.test/wp-admin/admin-post.php';
        $subject_choice_preview_state = ['token' => 'subject-preview'];
        $subject_choice_preview_token = 'subject-preview';
        $subject_options = [
            [
                'id' => 10,
                'label' => 'MAT - Matematika',
            ],
        ];
        $total_pages = 1;
        $total_users = 0;
        $user_clear_edit_url = 'https://example.test/wp-admin/admin.php?page=cbt-user-import';
        $user_reset_url = 'https://example.test/wp-admin/admin.php?page=cbt-user-import';
        $user_tab_is_forced = false;
        $users = [];

        ob_start();
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/users/page.php';

        return (string) ob_get_clean();
    }
}
