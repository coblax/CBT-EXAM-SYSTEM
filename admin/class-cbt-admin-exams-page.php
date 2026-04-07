<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exams_Page
{
    /**
     * @var array<int,int>
     */
    private static array $snapshot_exam_readiness_pages = [];

    public static function render(): void
    {
        if (!CBT_Admin_Exams_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        $preview_exam_id = isset($_GET['preview_exam_id']) ? absint(wp_unslash((string) $_GET['preview_exam_id'])) : 0;
        if ($preview_exam_id > 0) {
            $context = CBT_Admin_Exams_Service::build_preview_context($_GET);
            extract($context, EXTR_SKIP);

            require CBT_EXAM_SYSTEM_PATH . 'admin/views/exams/preview.php';
            return;
        }

        $context = CBT_Admin_Exams_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/exams/page.php';
    }

    private static function normalize_snapshot_panel_tab(string $tab): string
    {
        $tab = sanitize_key($tab);

        if ($tab === CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS || $tab === '') {
            return CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT;
        }

        if ($tab === CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS) {
            return CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR;
        }

        if (
            in_array(
                $tab,
                [
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR,
                    CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
                ],
                true
            )
        ) {
            return $tab;
        }

        return CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT;
    }

    /**
     * @param array<int,array{id:int,title:string,subject_name?:string,status?:string,target_kelas_list?:array<int,string>}> $exam_snapshot_exam_options
     * @param array<int,int> $selected_snapshot_exam_ids
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_snapshot_exam_picker_form(
        string $tab,
        string $snapshot_picker_label,
        string $snapshot_picker_meta,
        array $exam_snapshot_exam_options,
        array $selected_snapshot_exam_ids,
        string $exam_snapshot_reset_url,
        array $exam_list_state,
        int $exam_readiness_page,
        array $student_snapshot_filter_state
    ): void {
        ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-exam-list-toolbar" id="cbt-exam-snapshot-filter-form">
            <input type="hidden" name="page" value="cbt-exams" />
            <input type="hidden" name="cbt_exam_panel" value="snapshot" />
            <?php self::render_snapshot_tab_hidden_field($tab); ?>
            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
            <div class="cbt-exam-list-toolbar-grid">
                <div class="cbt-exam-list-toolbar-field cbt-exam-list-toolbar-field--search">
                    <label for="cbt-exam-snapshot-picker">Exam</label>
                    <details class="cbt-exam-snapshot-picker" id="cbt-exam-snapshot-picker" data-cbt-exam-snapshot-picker>
                        <summary>
                            <span class="cbt-exam-snapshot-picker-summary">
                                <span class="cbt-exam-snapshot-picker-copy">
                                    <strong data-cbt-exam-snapshot-picker-label><?php echo esc_html($snapshot_picker_label); ?></strong>
                                    <span data-cbt-exam-snapshot-picker-meta><?php echo esc_html($snapshot_picker_meta); ?></span>
                                </span>
                                <span class="cbt-exam-snapshot-picker-caret" aria-hidden="true"></span>
                            </span>
                        </summary>
                        <div class="cbt-exam-snapshot-picker-menu">
                            <?php if (empty($exam_snapshot_exam_options)): ?>
                                <div class="cbt-exam-snapshot-picker-empty">Belum ada exam yang tersedia untuk dipilih.</div>
                            <?php else: ?>
                                <?php foreach ($exam_snapshot_exam_options as $exam_snapshot_exam_option): ?>
                                    <?php
                                    $snapshot_exam_option_id = (int) ($exam_snapshot_exam_option['id'] ?? 0);
                                    $snapshot_exam_option_target_kelas = array_values(array_filter(array_map('strval', (array) ($exam_snapshot_exam_option['target_kelas_list'] ?? []))));
                                    ?>
                                    <label class="cbt-exam-snapshot-picker-option">
                                        <input
                                            type="checkbox"
                                            name="cbt_exam_snapshot_exam_ids[]"
                                            value="<?php echo esc_attr((string) $snapshot_exam_option_id); ?>"
                                            <?php echo in_array($snapshot_exam_option_id, $selected_snapshot_exam_ids, true) ? 'checked="checked"' : ''; ?>
                                            data-cbt-exam-snapshot-checkbox
                                        />
                                        <span class="cbt-exam-snapshot-exam-copy">
                                            <strong><?php echo esc_html((string) ($exam_snapshot_exam_option['title'] ?? ('Exam #' . $snapshot_exam_option_id))); ?></strong>
                                            <span><?php echo esc_html((string) ($exam_snapshot_exam_option['subject_name'] ?? 'Tanpa mapel')); ?> · <?php echo esc_html((string) ($exam_snapshot_exam_option['status'] ?? '-')); ?></span>
                                            <span><?php echo esc_html(!empty($snapshot_exam_option_target_kelas) ? ('Target kelas: ' . implode(', ', $snapshot_exam_option_target_kelas)) : 'Target kelas: Semua kelas'); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <div class="cbt-exam-list-toolbar-actions">
                    <button type="submit" class="button">Terapkan</button>
                    <a href="<?php echo esc_url($exam_snapshot_reset_url); ?>" class="button cbt-exam-list-reset">Reset</a>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $exam_snapshot_rows
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int,exam_ids?:array<int,int>} $exam_snapshot_filter_state
     * @param array<int,int> $exam_snapshot_preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_exam_snapshot_bulk_actions_bar(
        string $tab,
        bool $has_selected_exam_snapshot,
        int $exam_snapshot_total,
        array $exam_snapshot_rows,
        array $exam_list_state,
        array $exam_snapshot_filter_state,
        array $exam_snapshot_preview_pages,
        array $student_snapshot_filter_state,
        int $exam_readiness_page,
        string $copy
    ): void {
        $is_submission_context_mode = $tab === CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR;
        $warm_action = $is_submission_context_mode ? 'cbt_warm_bulk_exam_submission_context_snapshots' : 'cbt_warm_bulk_exam_delivery_snapshots';
        $clear_action = $is_submission_context_mode ? 'cbt_clear_bulk_exam_submission_context_snapshots' : 'cbt_clear_bulk_exam_delivery_snapshots';
        $warm_nonce = $is_submission_context_mode ? 'cbt_warm_bulk_exam_submission_context_snapshots' : 'cbt_warm_bulk_exam_delivery_snapshots';
        $clear_nonce = $is_submission_context_mode ? 'cbt_clear_bulk_exam_submission_context_snapshots' : 'cbt_clear_bulk_exam_delivery_snapshots';
        $warm_label = $is_submission_context_mode ? 'Siapkan Semua Submission Context' : 'Siapkan Semua Snapshot';
        $clear_label = $is_submission_context_mode ? 'Bersihkan Semua Submission Context' : 'Bersihkan Semua Snapshot';
        ?>
        <div class="cbt-exam-snapshot-actions-bar">
            <div class="cbt-exam-snapshot-actions-copy">
                <strong><?php echo esc_html($has_selected_exam_snapshot ? sprintf('%d exam dipilih', $exam_snapshot_total) : 'Pilih exam'); ?></strong>
                <span><?php echo esc_html($has_selected_exam_snapshot ? $copy : 'Pilih satu atau beberapa exam pada dropdown di atas untuk memuat monitor ini.'); ?></span>
            </div>
            <div class="cbt-exam-snapshot-bulk-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                    <?php wp_nonce_field($warm_nonce); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($warm_action); ?>" />
                    <?php self::render_snapshot_tab_hidden_field($tab); ?>
                    <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                    <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                    <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                    <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                    <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                    <button type="submit" class="button button-primary" <?php echo empty($exam_snapshot_rows) ? 'disabled="disabled"' : ''; ?>><?php echo esc_html($warm_label); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                    <?php wp_nonce_field($clear_nonce); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($clear_action); ?>" />
                    <?php self::render_snapshot_tab_hidden_field($tab); ?>
                    <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                    <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                    <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                    <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                    <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                    <button type="submit" class="button" <?php echo empty($exam_snapshot_rows) ? 'disabled="disabled"' : ''; ?>><?php echo esc_html($clear_label); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     * @param string[] $student_snapshot_kelas_options
     * @param string[] $student_snapshot_ruang_options
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int,exam_ids?:array<int,int>} $exam_snapshot_filter_state
     * @param array<int,int> $exam_snapshot_preview_pages
     */
    private static function render_snapshot_student_toolbar(
        string $tab,
        array $student_snapshot_filter_state,
        array $student_snapshot_kelas_options,
        array $student_snapshot_ruang_options,
        string $student_snapshot_reset_url,
        array $exam_list_state,
        array $exam_snapshot_filter_state,
        array $exam_snapshot_preview_pages,
        int $exam_readiness_page
    ): void {
        ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-exam-snapshot-student-toolbar" id="cbt-student-snapshot-filter-form">
            <input type="hidden" name="page" value="cbt-exams" />
            <input type="hidden" name="cbt_exam_panel" value="snapshot" />
            <?php self::render_snapshot_tab_hidden_field($tab); ?>
            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
            <div class="cbt-exam-snapshot-student-toolbar-grid">
                <div class="cbt-exam-snapshot-student-search">
                    <label for="cbt-student-snapshot-search">Cari Siswa</label>
                    <input type="search" id="cbt-student-snapshot-search" name="cbt_student_snapshot_q" value="<?php echo esc_attr((string) ($student_snapshot_filter_state['search'] ?? '')); ?>" placeholder="Nama, username, email, kelas, ruang" />
                </div>
                <div class="cbt-exam-snapshot-student-field">
                    <label for="cbt-student-snapshot-kelas">Kelas</label>
                    <select id="cbt-student-snapshot-kelas" name="cbt_student_snapshot_kelas">
                        <option value="">Semua kelas</option>
                        <?php foreach ($student_snapshot_kelas_options as $student_snapshot_kelas_option): ?>
                            <option value="<?php echo esc_attr((string) $student_snapshot_kelas_option); ?>" <?php echo ((string) ($student_snapshot_filter_state['kelas'] ?? '') === (string) $student_snapshot_kelas_option) ? 'selected="selected"' : ''; ?>>
                                <?php echo esc_html((string) $student_snapshot_kelas_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cbt-exam-snapshot-student-field">
                    <label for="cbt-student-snapshot-ruang">Ruang</label>
                    <select id="cbt-student-snapshot-ruang" name="cbt_student_snapshot_ruang">
                        <option value="">Semua ruang</option>
                        <?php foreach ($student_snapshot_ruang_options as $student_snapshot_ruang_option): ?>
                            <option value="<?php echo esc_attr((string) $student_snapshot_ruang_option); ?>" <?php echo ((string) ($student_snapshot_filter_state['ruang'] ?? '') === (string) $student_snapshot_ruang_option) ? 'selected="selected"' : ''; ?>>
                                <?php echo esc_html((string) $student_snapshot_ruang_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cbt-exam-snapshot-student-toolbar-actions">
                    <button type="submit" class="button button-secondary">Cari</button>
                    <a href="<?php echo esc_url($student_snapshot_reset_url); ?>" class="button">Reset</a>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $student_snapshot_rows
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int,exam_ids?:array<int,int>} $exam_snapshot_filter_state
     * @param array<int,int> $exam_snapshot_preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_student_snapshot_bulk_actions_bar(
        string $tab,
        int $student_snapshot_total,
        array $student_snapshot_rows,
        array $exam_list_state,
        array $exam_snapshot_filter_state,
        array $exam_snapshot_preview_pages,
        array $student_snapshot_filter_state,
        int $exam_readiness_page,
        string $mode
    ): void {
        $is_exam_mode = $mode === 'exam';
        $is_profile_mode = $mode === 'profile';
        $copy = $is_exam_mode
            ? 'Bulk action di tab ini menyiapkan atau membersihkan snapshot exam siswa sesuai filter aktif.'
            : ($is_profile_mode
                ? 'Bulk action di tab ini menyiapkan atau membersihkan snapshot profil siswa sesuai filter aktif.'
                : 'Bulk action di tab ini menyiapkan atau membersihkan login snapshot siswa sesuai filter aktif.');
        ?>
        <div class="cbt-exam-snapshot-actions-bar cbt-exam-snapshot-actions-bar--students">
            <div class="cbt-exam-snapshot-actions-copy">
                <strong><?php echo esc_html(sprintf('%d siswa terfilter', $student_snapshot_total)); ?></strong>
                <span><?php echo esc_html($copy); ?></span>
            </div>
            <div class="cbt-exam-snapshot-bulk-actions">
                <?php if ($is_exam_mode): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_warm_bulk_student_exam_availability_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_warm_bulk_student_exam_availability_snapshots" />
                        <?php self::render_snapshot_tab_hidden_field($tab); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-primary" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Snapshot Exam</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_clear_bulk_student_exam_availability_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_clear_bulk_student_exam_availability_snapshots" />
                        <?php self::render_snapshot_tab_hidden_field($tab); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Snapshot Exam</button>
                    </form>
                <?php elseif ($is_profile_mode): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_warm_bulk_student_profile_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_warm_bulk_student_profile_snapshots" />
                        <?php self::render_snapshot_tab_hidden_field($tab); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-primary" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Profil</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_clear_bulk_student_profile_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_clear_bulk_student_profile_snapshots" />
                        <?php self::render_snapshot_tab_hidden_field($tab); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Profil</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_warm_bulk_student_login_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_warm_bulk_student_login_snapshots" />
                        <?php self::render_snapshot_tab_hidden_field($tab); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-primary" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Login Snapshot</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_clear_bulk_student_login_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_clear_bulk_student_login_snapshots" />
                        <?php self::render_snapshot_tab_hidden_field($tab); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Login Snapshot</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<int,array{label:string,value:string}> $student_snapshot_active_filters
     */
    private static function render_student_snapshot_active_filters(array $student_snapshot_active_filters, int $student_snapshot_total): void
    {
        if (empty($student_snapshot_active_filters)) {
            return;
        }
        ?>
        <div class="cbt-exam-list-active-filters" aria-label="Filter snapshot siswa aktif">
            <span class="cbt-exam-list-active-summary"><?php echo esc_html(sprintf('%d siswa cocok', $student_snapshot_total)); ?></span>
            <?php foreach ($student_snapshot_active_filters as $active_filter): ?>
                <span class="cbt-exam-list-active-chip">
                    <strong><?php echo esc_html((string) ($active_filter['label'] ?? 'Filter')); ?></strong>
                    <span><?php echo esc_html((string) ($active_filter['value'] ?? '')); ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int,exam_ids?:array<int,int>} $exam_snapshot_filter_state
     * @param array<int,int> $exam_snapshot_preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_student_snapshot_pagination(
        string $tab,
        int $student_snapshot_total_pages,
        int $student_snapshot_current_page,
        int $student_snapshot_total,
        array $exam_list_state,
        array $exam_snapshot_filter_state,
        array $exam_snapshot_preview_pages,
        array $student_snapshot_filter_state,
        int $exam_readiness_page
    ): void {
        if ($student_snapshot_total_pages <= 1) {
            return;
        }
        ?>
        <div class="cbt-exam-snapshot-student-pagination" aria-label="Pagination snapshot siswa">
            <?php if ($student_snapshot_current_page > 1): ?>
                <a class="button button-secondary" href="<?php echo esc_url(self::build_student_snapshot_page_url($student_snapshot_current_page - 1, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, $tab)); ?>">Sebelumnya</a>
            <?php else: ?>
                <span class="button button-secondary disabled" aria-disabled="true">Sebelumnya</span>
            <?php endif; ?>
            <span class="cbt-exam-snapshot-student-pagination-state">
                <?php echo esc_html(sprintf('Halaman %d dari %d · %d siswa', $student_snapshot_current_page, $student_snapshot_total_pages, $student_snapshot_total)); ?>
            </span>
            <?php if ($student_snapshot_current_page < $student_snapshot_total_pages): ?>
                <a class="button button-secondary" href="<?php echo esc_url(self::build_student_snapshot_page_url($student_snapshot_current_page + 1, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, $tab)); ?>">Berikutnya</a>
            <?php else: ?>
                <span class="button button-secondary disabled" aria-disabled="true">Berikutnya</span>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function format_preflight_ready_meta(bool $is_ready): string
    {
        return $is_ready ? 'Siap' : 'Tidak siap';
    }

    private static function render_preflight_stage_card(
        string $label,
        string $status_label,
        string $status_tone,
        string $summary,
        string $meta = ''
    ): void {
        ?>
        <div class="cbt-exam-preflight-stage-card">
            <div class="cbt-exam-preflight-stage-card-head">
                <span class="cbt-exam-snapshot-summary-label"><?php echo esc_html($label); ?></span>
                <span class="cbt-exam-snapshot-status is-<?php echo esc_attr(sanitize_html_class($status_tone, 'warning')); ?>"><?php echo esc_html($status_label); ?></span>
            </div>
            <strong class="cbt-exam-preflight-stage-summary"><?php echo esc_html($summary); ?></strong>
            <?php if ($meta !== ''): ?>
                <span class="cbt-exam-preflight-stage-meta"><?php echo esc_html($meta); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array{
     *   subjects:array<int,array<string,mixed>>,
     *   exam_status_labels:array<string,string>,
     *   exam_list_state:array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string},
     *   exam_list_kelas_options:array<int,string>,
     *   exam_per_page:int,
     *   exam_active_filters:array<int,array{label:string,value:string}>,
     *   exam_snapshot_tab:string,
     *   exam_snapshot_filter_state:array{exam_id:int,exam_ids?:array<int,int>},
     *   exam_snapshot_exam_options:array<int,array{id:int,title:string,subject_name?:string,status?:string,target_kelas_list?:array<int,string>}>,
     *   exam_snapshot_total:int,
     *   exam_snapshot_rows:array<int,array<string,mixed>>,
     *   exam_snapshot_preview_pages:array<int,int>,
     *   exam_readiness_page:int,
     *   exam_readiness_pages?:array<int,int>,
     *   exam_snapshot_reset_url:string,
     *   student_snapshot_filter_state:array{search:string,kelas:string,ruang:string,paged:int,per_page:int},
     *   student_snapshot_kelas_options:array<int,string>,
     *   student_snapshot_ruang_options:array<int,string>,
     *   student_snapshot_rows:array<int,array<string,mixed>>,
     *   student_snapshot_total:int,
     *   student_snapshot_total_pages:int,
     *   student_snapshot_current_page:int,
     *   student_snapshot_per_page:int,
     *   student_snapshot_active_filters:array<int,array{label:string,value:string}>,
     *   student_snapshot_reset_url:string
     * } $args
     */
    public static function render_snapshot_panel(array $args): void
    {
        $subjects = isset($args['subjects']) && is_array($args['subjects']) ? $args['subjects'] : [];
        $exam_status_labels = isset($args['exam_status_labels']) && is_array($args['exam_status_labels']) ? $args['exam_status_labels'] : [];
        $exam_list_state = isset($args['exam_list_state']) && is_array($args['exam_list_state']) ? $args['exam_list_state'] : [
            'per_page' => 20,
            'paged' => 1,
            'search' => '',
            'status' => '',
            'subject_id' => 0,
            'kelas' => '',
        ];
        $exam_list_kelas_options = isset($args['exam_list_kelas_options']) && is_array($args['exam_list_kelas_options']) ? $args['exam_list_kelas_options'] : [];
        $exam_per_page = max(20, (int) ($args['exam_per_page'] ?? 20));
        $exam_active_filters = isset($args['exam_active_filters']) && is_array($args['exam_active_filters']) ? $args['exam_active_filters'] : [];
        $exam_snapshot_tab = self::normalize_snapshot_panel_tab(
            (string) ($args['exam_snapshot_tab'] ?? CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT)
        );
        $exam_snapshot_filter_state = isset($args['exam_snapshot_filter_state']) && is_array($args['exam_snapshot_filter_state'])
            ? $args['exam_snapshot_filter_state']
            : ['exam_id' => 0, 'exam_ids' => []];
        $exam_snapshot_exam_options = isset($args['exam_snapshot_exam_options']) && is_array($args['exam_snapshot_exam_options'])
            ? $args['exam_snapshot_exam_options']
            : [];
        $exam_snapshot_total = max(0, (int) ($args['exam_snapshot_total'] ?? 0));
        $exam_snapshot_rows = isset($args['exam_snapshot_rows']) && is_array($args['exam_snapshot_rows']) ? $args['exam_snapshot_rows'] : [];
        $exam_snapshot_preview_pages = isset($args['exam_snapshot_preview_pages']) && is_array($args['exam_snapshot_preview_pages'])
            ? $args['exam_snapshot_preview_pages']
            : [];
        $exam_readiness_page = max(1, (int) ($args['exam_readiness_page'] ?? 1));
        $exam_readiness_pages = isset($args['exam_readiness_pages']) && is_array($args['exam_readiness_pages'])
            ? array_filter(array_map('intval', $args['exam_readiness_pages']), static function (int $page): bool {
                return $page > 1;
            })
            : [];
        $exam_snapshot_reset_url = (string) ($args['exam_snapshot_reset_url'] ?? admin_url('admin.php?page=cbt-exams&cbt_exam_panel=snapshot'));
        $student_snapshot_filter_state = isset($args['student_snapshot_filter_state']) && is_array($args['student_snapshot_filter_state'])
            ? $args['student_snapshot_filter_state']
            : ['search' => '', 'kelas' => '', 'ruang' => '', 'paged' => 1, 'per_page' => 25];
        $student_snapshot_kelas_options = isset($args['student_snapshot_kelas_options']) && is_array($args['student_snapshot_kelas_options'])
            ? $args['student_snapshot_kelas_options']
            : [];
        $student_snapshot_ruang_options = isset($args['student_snapshot_ruang_options']) && is_array($args['student_snapshot_ruang_options'])
            ? $args['student_snapshot_ruang_options']
            : [];
        $student_snapshot_rows = isset($args['student_snapshot_rows']) && is_array($args['student_snapshot_rows'])
            ? $args['student_snapshot_rows']
            : [];
        $student_snapshot_total = max(0, (int) ($args['student_snapshot_total'] ?? 0));
        $student_snapshot_total_pages = max(1, (int) ($args['student_snapshot_total_pages'] ?? 1));
        $student_snapshot_current_page = max(1, (int) ($args['student_snapshot_current_page'] ?? 1));
        $student_snapshot_per_page = max(1, (int) ($args['student_snapshot_per_page'] ?? 25));
        $student_snapshot_active_filters = isset($args['student_snapshot_active_filters']) && is_array($args['student_snapshot_active_filters'])
            ? $args['student_snapshot_active_filters']
            : [];
        $student_snapshot_reset_url = (string) ($args['student_snapshot_reset_url'] ?? admin_url('admin.php?page=cbt-exams&cbt_exam_panel=snapshot'));
        $selected_snapshot_exam_ids = array_values(array_filter(array_map('intval', (array) ($exam_snapshot_filter_state['exam_ids'] ?? []))));
        if (empty($selected_snapshot_exam_ids) && !empty($exam_snapshot_filter_state['exam_id'])) {
            $selected_snapshot_exam_ids[] = (int) $exam_snapshot_filter_state['exam_id'];
        }
        $has_selected_exam_snapshot = !empty($selected_snapshot_exam_ids);
        $selected_snapshot_exam_titles = [];
        foreach ($exam_snapshot_exam_options as $exam_snapshot_exam_option) {
            $snapshot_exam_option_id = (int) ($exam_snapshot_exam_option['id'] ?? 0);
            if (!in_array($snapshot_exam_option_id, $selected_snapshot_exam_ids, true)) {
                continue;
            }

            $selected_snapshot_exam_titles[] = (string) ($exam_snapshot_exam_option['title'] ?? ('Exam #' . $snapshot_exam_option_id));
        }
        $selected_snapshot_exam_count = count($selected_snapshot_exam_ids);
        if (empty($exam_readiness_pages)) {
            foreach ($exam_snapshot_rows as $exam_snapshot_row) {
                if (!is_array($exam_snapshot_row)) {
                    continue;
                }

                $snapshot_exam_id = (int) ($exam_snapshot_row['exam_id'] ?? 0);
                $snapshot_page = max(1, (int) ($exam_snapshot_row['readiness']['problem_page'] ?? 1));
                if ($snapshot_exam_id > 0 && $snapshot_page > 1) {
                    $exam_readiness_pages[$snapshot_exam_id] = $snapshot_page;
                }
            }
        }
        self::$snapshot_exam_readiness_pages = $exam_readiness_pages;
        $snapshot_picker_label = $selected_snapshot_exam_count > 0
            ? $selected_snapshot_exam_count . ' exam dipilih'
            : 'Pilih satu atau beberapa exam';
        if ($selected_snapshot_exam_count === 1) {
            $snapshot_picker_label = '1 exam dipilih';
        }
        $snapshot_picker_meta = 'Buka daftar exam aktif lalu centang exam yang ingin ditampilkan.';
        if ($selected_snapshot_exam_count === 1) {
            $snapshot_picker_meta = (string) ($selected_snapshot_exam_titles[0] ?? '1 exam dipilih');
        } elseif ($selected_snapshot_exam_count > 1) {
            $snapshot_picker_meta = implode(', ', array_slice($selected_snapshot_exam_titles, 0, 2));
            if ($selected_snapshot_exam_count > 2) {
                $snapshot_picker_meta .= ' +' . ($selected_snapshot_exam_count - 2) . ' lainnya';
            }
        }
        $snapshot_tabs = [
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT => [
                'label' => 'One-Click Pra Ujian',
                'description' => 'Fokus pada readiness, blocker, dan eksekusi pra-ujian.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR => [
                'label' => 'Monitor Snapshot Soal',
                'description' => 'Pantau snapshot delivery soal per exam.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR => [
                'label' => 'Monitor Snapshot Start',
                'description' => 'Pantau kontrak start attempt per exam.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR => [
                'label' => 'Monitor Snapshot Submit',
                'description' => 'Pantau submission context per exam.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR => [
                'label' => 'Monitor Session Runtime',
                'description' => 'Pantau session, contract, delivery, dan runtime answer live.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR => [
                'label' => 'Monitor Snapshot Exam',
                'description' => 'Pantau katalog exam siswa per user.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR => [
                'label' => 'Monitor Snapshot Profile',
                'description' => 'Pantau snapshot profil siswa per user.',
            ],
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR => [
                'label' => 'Monitor Snapshot Login',
                'description' => 'Pantau login auth snapshot siswa per user.',
            ],
        ];
        ?>
        <div class="cbt-exam-snapshot-shell">
            <div class="cbt-exam-snapshot-subtabs" role="tablist" aria-label="Subtab snapshot">
                <?php foreach ($snapshot_tabs as $snapshot_tab_key => $snapshot_tab_meta): ?>
                    <a
                        href="<?php echo esc_url(self::build_snapshot_tab_url($snapshot_tab_key, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page)); ?>"
                        class="cbt-exam-snapshot-subtab<?php echo $exam_snapshot_tab === $snapshot_tab_key ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $exam_snapshot_tab === $snapshot_tab_key ? 'true' : 'false'; ?>"
                    >
                        <span class="cbt-exam-snapshot-subtab-label"><?php echo esc_html((string) $snapshot_tab_meta['label']); ?></span>
                        <small><?php echo esc_html((string) $snapshot_tab_meta['description']); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
            <section class="cbt-exam-snapshot-section is-active">
                <?php if (CBT_Admin_Exams_Service::is_exam_snapshot_exam_tab($exam_snapshot_tab)): ?>
                    <?php self::render_snapshot_exam_picker_form(
                        $exam_snapshot_tab,
                        $snapshot_picker_label,
                        $snapshot_picker_meta,
                        $exam_snapshot_exam_options,
                        $selected_snapshot_exam_ids,
                        $exam_snapshot_reset_url,
                        $exam_list_state,
                        $exam_readiness_page,
                        $student_snapshot_filter_state
                    ); ?>
                <?php else: ?>
                    <?php self::render_snapshot_student_toolbar(
                        $exam_snapshot_tab,
                        $student_snapshot_filter_state,
                        $student_snapshot_kelas_options,
                        $student_snapshot_ruang_options,
                        $student_snapshot_reset_url,
                        $exam_list_state,
                        $exam_snapshot_filter_state,
                        $exam_snapshot_preview_pages,
                        $exam_readiness_page
                    ); ?>
                <?php endif; ?>

                <?php switch ($exam_snapshot_tab):
                    case CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Snapshot Soal</h3>
                                <p class="description cbt-exam-list-description">Pantau snapshot delivery soal per exam terpilih, termasuk revision, TTL, payload, preview soal, dan detail teknis Redis.</p>
                            </div>
                        </div>
                        <?php self::render_exam_snapshot_bulk_actions_bar(
                            $exam_snapshot_tab,
                            $has_selected_exam_snapshot,
                            $exam_snapshot_total,
                            $exam_snapshot_rows,
                            $exam_list_state,
                            $exam_snapshot_filter_state,
                            $exam_snapshot_preview_pages,
                            $student_snapshot_filter_state,
                            $exam_readiness_page,
                            'Bulk warm akan menyinkronkan snapshot exam dan start snapshot untuk exam yang sedang dipilih.'
                        ); ?>
                        <?php if (empty($exam_snapshot_rows)): ?>
                            <div class="cbt-exam-snapshot-empty-state">
                                <?php echo esc_html($has_selected_exam_snapshot ? 'Belum ada exam yang bisa diperiksa snapshot soal-nya.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk memantau snapshot soal.'); ?>
                            </div>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-monitor-list">
                                <?php foreach ($exam_snapshot_rows as $row): ?>
                                    <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Snapshot Start</h3>
                                <p class="description cbt-exam-list-description">Pantau start snapshot per exam untuk memastikan `start_attempt` dapat membentuk attempt baru tanpa membangun payload soal penuh dari nol.</p>
                            </div>
                        </div>
                        <?php self::render_exam_snapshot_bulk_actions_bar(
                            $exam_snapshot_tab,
                            $has_selected_exam_snapshot,
                            $exam_snapshot_total,
                            $exam_snapshot_rows,
                            $exam_list_state,
                            $exam_snapshot_filter_state,
                            $exam_snapshot_preview_pages,
                            $student_snapshot_filter_state,
                            $exam_readiness_page,
                            'Aksi warm dan clear di tab ini tetap menyinkronkan snapshot soal dan start snapshot secara bersamaan.'
                        ); ?>
                        <?php if (empty($exam_snapshot_rows)): ?>
                            <div class="cbt-exam-snapshot-empty-state">
                                <?php echo esc_html($has_selected_exam_snapshot ? 'Belum ada exam yang bisa diperiksa start snapshot-nya.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk memantau start snapshot.'); ?>
                            </div>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-monitor-list">
                                <?php foreach ($exam_snapshot_rows as $row): ?>
                                    <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Snapshot Submit</h3>
                                <p class="description cbt-exam-list-description">Pantau submission context per exam untuk memastikan konteks evaluasi jawaban sudah siap sebelum autosave awal, submit jawaban, dan scoring objektif mulai ramai.</p>
                            </div>
                        </div>
                        <?php self::render_exam_snapshot_bulk_actions_bar(
                            $exam_snapshot_tab,
                            $has_selected_exam_snapshot,
                            $exam_snapshot_total,
                            $exam_snapshot_rows,
                            $exam_list_state,
                            $exam_snapshot_filter_state,
                            $exam_snapshot_preview_pages,
                            $student_snapshot_filter_state,
                            $exam_readiness_page,
                            'Bulk action di tab ini menyiapkan atau membersihkan submission context untuk semua soal aktif milik exam yang sedang dipilih.'
                        ); ?>
                        <?php if (empty($exam_snapshot_rows)): ?>
                            <div class="cbt-exam-snapshot-empty-state">
                                <?php echo esc_html($has_selected_exam_snapshot ? 'Belum ada exam yang bisa diperiksa submission context-nya.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk memantau submission context.'); ?>
                            </div>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-monitor-list">
                                <?php foreach ($exam_snapshot_rows as $row): ?>
                                    <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Session Runtime</h3>
                                <p class="description cbt-exam-list-description">Pantau attempt siswa yang sedang `in_progress` untuk exam terpilih. Tab ini membantu melihat apakah jalur live sudah Redis-first lewat session snapshot, contract snapshot, delivery snapshot, dan runtime answers.</p>
                            </div>
                        </div>
                        <div class="cbt-exam-snapshot-actions-bar cbt-exam-snapshot-actions-bar--quiet">
                            <div class="cbt-exam-snapshot-actions-copy">
                                <strong><?php echo esc_html($has_selected_exam_snapshot ? sprintf('%d exam dipilih', $exam_snapshot_total) : 'Pilih exam'); ?></strong>
                                <span><?php echo esc_html($has_selected_exam_snapshot ? 'Pilih exam yang sedang berjalan untuk melihat attempt aktif, status snapshot runtime, dan fallback live per siswa.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk memantau session runtime live.'); ?></span>
                            </div>
                        </div>
                        <?php if (empty($exam_snapshot_rows)): ?>
                            <div class="cbt-exam-snapshot-empty-state">
                                <?php echo esc_html($has_selected_exam_snapshot ? 'Belum ada exam yang bisa dipantau session runtime-nya.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk memantau session runtime.'); ?>
                            </div>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-monitor-list">
                                <?php foreach ($exam_snapshot_rows as $row): ?>
                                    <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Snapshot Exam</h3>
                                <p class="description cbt-exam-list-description">Pantau hasil snapshot availability per siswa. Snapshot exam pada tab ini adalah katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.</p>
                            </div>
                        </div>
                        <?php self::render_student_snapshot_bulk_actions_bar(
                            $exam_snapshot_tab,
                            $student_snapshot_total,
                            $student_snapshot_rows,
                            $exam_list_state,
                            $exam_snapshot_filter_state,
                            $exam_snapshot_preview_pages,
                            $student_snapshot_filter_state,
                            $exam_readiness_page,
                            'exam'
                        ); ?>
                        <?php self::render_student_snapshot_active_filters($student_snapshot_active_filters, $student_snapshot_total); ?>
                        <div class="cbt-exam-list-table-wrap">
                            <table class="widefat striped cbt-student-snapshot-table cbt-student-snapshot-table--single">
                                <colgroup>
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--user" />
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--availability" />
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--actions" />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Siswa</th>
                                        <th>Snapshot Exam</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($student_snapshot_rows)): ?>
                                        <tr>
                                            <td colspan="3"><?php echo !empty($student_snapshot_active_filters) ? 'Tidak ada siswa yang cocok dengan filter saat ini.' : 'Belum ada siswa yang bisa dipantau snapshot exam-nya.'; ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($student_snapshot_rows as $student_snapshot_row): ?>
                                            <?php self::render_student_snapshot_row($student_snapshot_row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php self::render_student_snapshot_pagination($exam_snapshot_tab, $student_snapshot_total_pages, $student_snapshot_current_page, $student_snapshot_total, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page); ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Snapshot Profile</h3>
                                <p class="description cbt-exam-list-description">Pantau kesiapan snapshot profil siswa per user, termasuk payload, foto, dan metadata identitas yang dipakai untuk live payload.</p>
                            </div>
                        </div>
                        <?php self::render_student_snapshot_bulk_actions_bar(
                            $exam_snapshot_tab,
                            $student_snapshot_total,
                            $student_snapshot_rows,
                            $exam_list_state,
                            $exam_snapshot_filter_state,
                            $exam_snapshot_preview_pages,
                            $student_snapshot_filter_state,
                            $exam_readiness_page,
                            'profile'
                        ); ?>
                        <?php self::render_student_snapshot_active_filters($student_snapshot_active_filters, $student_snapshot_total); ?>
                        <div class="cbt-exam-list-table-wrap">
                            <table class="widefat striped cbt-student-snapshot-table cbt-student-snapshot-table--single">
                                <colgroup>
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--user" />
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--profile" />
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--actions" />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Siswa</th>
                                        <th>Snapshot Profile</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($student_snapshot_rows)): ?>
                                        <tr>
                                            <td colspan="3"><?php echo !empty($student_snapshot_active_filters) ? 'Tidak ada siswa yang cocok dengan filter saat ini.' : 'Belum ada siswa yang bisa dipantau snapshot profilnya.'; ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($student_snapshot_rows as $student_snapshot_row): ?>
                                            <?php self::render_student_snapshot_row($student_snapshot_row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php self::render_student_snapshot_pagination($exam_snapshot_tab, $student_snapshot_total_pages, $student_snapshot_current_page, $student_snapshot_total, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page); ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>Monitor Snapshot Login</h3>
                                <p class="description cbt-exam-list-description">Pantau login auth snapshot per siswa sebagai akselerator auth/login. Snapshot ini membantu lookup dan payload login siswa, tetapi bukan active session, JWT, atau state login yang sedang hidup.</p>
                            </div>
                        </div>
                        <?php self::render_student_snapshot_bulk_actions_bar(
                            $exam_snapshot_tab,
                            $student_snapshot_total,
                            $student_snapshot_rows,
                            $exam_list_state,
                            $exam_snapshot_filter_state,
                            $exam_snapshot_preview_pages,
                            $student_snapshot_filter_state,
                            $exam_readiness_page,
                            'login'
                        ); ?>
                        <?php self::render_student_snapshot_active_filters($student_snapshot_active_filters, $student_snapshot_total); ?>
                        <div class="cbt-exam-list-table-wrap">
                            <table class="widefat striped cbt-student-snapshot-table cbt-student-snapshot-table--single">
                                <colgroup>
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--user" />
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--login" />
                                    <col class="cbt-student-snapshot-col cbt-student-snapshot-col--actions" />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Siswa</th>
                                        <th>Snapshot Login</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($student_snapshot_rows)): ?>
                                        <tr>
                                            <td colspan="3"><?php echo !empty($student_snapshot_active_filters) ? 'Tidak ada siswa yang cocok dengan filter saat ini.' : 'Belum ada siswa yang bisa dipantau login snapshot-nya.'; ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($student_snapshot_rows as $student_snapshot_row): ?>
                                            <?php self::render_student_snapshot_row($student_snapshot_row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php self::render_student_snapshot_pagination($exam_snapshot_tab, $student_snapshot_total_pages, $student_snapshot_current_page, $student_snapshot_total, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page); ?>
                        <?php break; ?>

                    <?php case CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT:
                    default: ?>
                        <div class="cbt-exam-snapshot-section-head">
                            <div>
                                <h3>One-Click Pra Ujian</h3>
                                <p class="description cbt-exam-list-description">Pilih satu atau beberapa exam untuk meninjau kesiapan, blocker, warning, peserta target, start snapshot, snapshot soal, snapshot profil, login snapshot, dan auto-warm availability sebelum ujian dimulai. Setelah siswa mulai masuk, pantau jalur live-nya di tab Monitor Session Runtime.</p>
                            </div>
                        </div>
                        <div class="cbt-exam-snapshot-actions-bar cbt-exam-snapshot-actions-bar--quiet">
                            <div class="cbt-exam-snapshot-actions-copy">
                                <strong><?php echo esc_html($has_selected_exam_snapshot ? sprintf('%d exam dipilih', $exam_snapshot_total) : 'Pilih exam'); ?></strong>
                                <span><?php echo esc_html($has_selected_exam_snapshot ? 'Tab ini fokus pada kesiapan pra-ujian per exam terpilih, tanpa menampilkan preview soal atau ringkasan snapshot yang tidak relevan.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk membuka panel kesiapan pra-ujian.'); ?></span>
                            </div>
                        </div>
                        <?php if (empty($exam_snapshot_rows)): ?>
                            <div class="cbt-exam-snapshot-empty-state">
                                <?php echo esc_html($has_selected_exam_snapshot ? 'Belum ada exam yang bisa diperiksa kesiapan pra-ujiannya.' : 'Pilih satu atau beberapa exam pada dropdown di atas untuk menjalankan One-Click Pra Ujian.'); ?>
                            </div>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-monitor-list">
                                <?php foreach ($exam_snapshot_rows as $row): ?>
                                    <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state, $exam_readiness_page, CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php break; ?>
                <?php endswitch; ?>
            </section>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $row
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int,exam_ids?:array<int,int>} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_snapshot_row(
        array $row,
        array $exam_list_state,
        array $snapshot_filter_state = ['exam_id' => 0],
        array $preview_pages = [],
        array $student_snapshot_filter_state = ['search' => '', 'kelas' => '', 'ruang' => '', 'paged' => 1, 'per_page' => 25],
        int $exam_readiness_page = 1,
        string $mode = CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT
    ): void {
        $mode = self::normalize_snapshot_panel_tab($mode);
        $exam_id = (int) ($row['exam_id'] ?? $row['id'] ?? 0);
        $title = trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : ('Exam #' . $exam_id);
        $subject_name = trim((string) ($row['subject_name'] ?? ''));
        $exam_status = trim((string) ($row['status'] ?? ''));
        $snapshot_status_label = trim((string) ($row['snapshot_status_label'] ?? 'MISS')) !== ''
            ? (string) ($row['snapshot_status_label'] ?? 'MISS')
            : 'MISS';
        $snapshot_status_tone = sanitize_html_class((string) ($row['snapshot_status_tone'] ?? 'warning'), 'warning');
        $revision_meta = is_array($row['revision_meta'] ?? null) ? $row['revision_meta'] : [];
        $revision_version = max(1, (int) ($revision_meta['version'] ?? 1));
        $invalidated_at = trim((string) ($revision_meta['invalidated_at'] ?? ''));
        $signature = trim((string) ($revision_meta['signature'] ?? ''));
        $storage_key = trim((string) ($row['storage_key'] ?? ''));
        $preview_question_ids = array_values(array_filter(array_map('intval', (array) ($row['preview_question_ids'] ?? []))));
        $preview_items = array_values(array_filter((array) ($row['preview_items'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $ttl_seconds = (int) ($row['snapshot_ttl_seconds'] ?? -2);
        $ttl_label = $ttl_seconds >= 0 ? $ttl_seconds . 's' : 'N/A';
        $payload_bytes = max(0, (int) ($row['snapshot_payload_bytes'] ?? 0));
        $payload_bytes_label = $payload_bytes > 0 ? number_format_i18n($payload_bytes) . ' bytes' : '0 bytes';
        $snapshot_message = trim((string) ($row['snapshot_message'] ?? ''));
        $redis_host = trim((string) ($row['redis_host'] ?? ''));
        $redis_database = (int) ($row['redis_database'] ?? 0);
        $redis_error = trim((string) ($row['redis_error'] ?? ''));
        $snapshot_item_count = max(0, (int) ($row['snapshot_item_count'] ?? 0));
        $start_snapshot_status_label = trim((string) ($row['start_snapshot_status_label'] ?? 'MISS')) !== ''
            ? (string) ($row['start_snapshot_status_label'] ?? 'MISS')
            : 'MISS';
        $start_snapshot_status_tone = sanitize_html_class((string) ($row['start_snapshot_status_tone'] ?? 'warning'), 'warning');
        $start_snapshot_message = trim((string) ($row['start_snapshot_message'] ?? ''));
        $start_snapshot_storage_key = trim((string) ($row['start_snapshot_storage_key'] ?? ''));
        $start_snapshot_payload_bytes = max(0, (int) ($row['start_snapshot_payload_bytes'] ?? 0));
        $start_snapshot_payload_bytes_label = $start_snapshot_payload_bytes > 0 ? number_format_i18n($start_snapshot_payload_bytes) . ' bytes' : '0 bytes';
        $start_snapshot_ttl_seconds = (int) ($row['start_snapshot_ttl_seconds'] ?? -2);
        $start_snapshot_ttl_label = $start_snapshot_ttl_seconds >= 0 ? $start_snapshot_ttl_seconds . 's' : 'N/A';
        $start_snapshot_item_count = max(0, (int) ($row['start_snapshot_item_count'] ?? 0));
        $start_snapshot_revision_meta = is_array($row['start_snapshot_revision_meta'] ?? null) ? $row['start_snapshot_revision_meta'] : [];
        $start_snapshot_revision_version = max(1, (int) ($start_snapshot_revision_meta['version'] ?? 1));
        $start_snapshot_signature = trim((string) ($start_snapshot_revision_meta['signature'] ?? ''));
        $start_snapshot_invalidated_at = trim((string) ($start_snapshot_revision_meta['invalidated_at'] ?? ''));
        $start_snapshot_redis_host = trim((string) ($row['start_snapshot_redis_host'] ?? ''));
        $start_snapshot_redis_database = (int) ($row['start_snapshot_redis_database'] ?? 0);
        $start_snapshot_redis_error = trim((string) ($row['start_snapshot_redis_error'] ?? ''));
        $preview_current_page = max(1, (int) ($row['preview_current_page'] ?? 1));
        $preview_total_pages = max(1, (int) ($row['preview_total_pages'] ?? 1));
        $preview_per_page = max(1, (int) ($row['preview_per_page'] ?? 7));
        $preview_is_expanded = !empty($row['preview_is_expanded']);
        $preview_start = $snapshot_item_count > 0 ? ((($preview_current_page - 1) * $preview_per_page) + 1) : 0;
        $preview_end = $snapshot_item_count > 0 ? min($snapshot_item_count, $preview_start + count($preview_items) - 1) : 0;
        $preview_range_label = $snapshot_item_count > 0
            ? sprintf('%d-%d dari %d', $preview_start, $preview_end, $snapshot_item_count)
            : '0 dari 0';
        $preview_summary_label = $snapshot_item_count > 0
            ? 'Preview Soal (' . $preview_range_label . ')'
            : 'Preview Soal';
        $auto_warm = is_array($row['auto_warm'] ?? null) ? $row['auto_warm'] : [];
        $auto_warm_status_label = (string) ($auto_warm['status_label'] ?? 'NONAKTIF');
        $auto_warm_status_tone = sanitize_html_class((string) ($auto_warm['status_tone'] ?? 'warning'), 'warning');
        $auto_warm_session_id = trim((string) ($auto_warm['session_id'] ?? ''));
        $auto_warm_target_student_count = max(0, (int) ($auto_warm['target_student_count'] ?? 0));
        $auto_warm_prepared_count = max(0, (int) ($auto_warm['prepared_count'] ?? 0));
        $auto_warm_cursor = max(0, (int) ($auto_warm['cursor'] ?? 0));
        $auto_warm_batch_size = max(0, (int) ($auto_warm['batch_size'] ?? 0));
        $auto_warm_started_at = trim((string) ($auto_warm['started_at'] ?? ''));
        $auto_warm_stop_after_at = trim((string) ($auto_warm['stop_after_at'] ?? ''));
        $auto_warm_last_tick_at = trim((string) ($auto_warm['last_tick_at'] ?? ''));
        $auto_warm_last_success = max(0, (int) ($auto_warm['last_success_count'] ?? 0));
        $auto_warm_last_failure = max(0, (int) ($auto_warm['last_failure_count'] ?? 0));
        $auto_warm_last_skip = max(0, (int) ($auto_warm['last_skip_count'] ?? 0));
        $auto_warm_message = trim((string) ($auto_warm['last_message'] ?? ''));
        $auto_warm_can_start = !empty($auto_warm['can_start']);
        $auto_warm_can_stop = !empty($auto_warm['can_stop']);
        $auto_warm_target_kelas = array_values(array_filter(array_map('strval', (array) ($auto_warm['target_kelas'] ?? []))));
        $auto_warm_redis_available = !empty($auto_warm['redis_available']);
        $auto_warm_blocking_exam_id = max(0, (int) ($auto_warm['blocking_exam_id'] ?? 0));
        $auto_warm_blocking_exam_title = trim((string) ($auto_warm['blocking_exam_title'] ?? ''));
        $readiness = is_array($row['readiness'] ?? null) ? $row['readiness'] : [];
        $readiness_overall_label = trim((string) ($readiness['overall_label'] ?? 'PERLU PERHATIAN'));
        $readiness_overall_tone = sanitize_html_class((string) ($readiness['overall_tone'] ?? 'warning'), 'warning');
        $readiness_blockers = array_values(array_filter((array) ($readiness['blockers'] ?? []), 'is_string'));
        $readiness_warnings = array_values(array_filter((array) ($readiness['warnings'] ?? []), 'is_string'));
        $readiness_profile_missing_count = max(0, (int) ($readiness['profile_missing_count'] ?? 0));
        $readiness_availability_ready_count = max(0, (int) ($readiness['availability_ready_count'] ?? 0));
        $readiness_availability_auto_warm_count = max(0, (int) ($readiness['availability_auto_warm_count'] ?? 0));
        $readiness_availability_missing_count = max(0, (int) ($readiness['availability_missing_count'] ?? 0));
        $readiness_token_label = trim((string) ($readiness['token_label'] ?? 'OFF'));
        $readiness_schedule_label = trim((string) ($readiness['schedule_label'] ?? 'Belum diatur'));
        $readiness_duration_minutes = max(0, (int) ($readiness['duration_minutes'] ?? 0));
        $readiness_show_student_result = ((int) ($readiness['show_student_result'] ?? 0) === 1) ? 'Aktif' : 'Mati';
        $readiness_enable_calculator = ((int) ($readiness['enable_calculator'] ?? 0) === 1) ? 'Aktif' : 'Mati';
        $readiness_problem_students = array_values(array_filter((array) ($readiness['problem_students'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $readiness_problem_total = max(0, (int) ($readiness['problem_total'] ?? 0));
        $readiness_problem_page = max(1, (int) ($readiness['problem_page'] ?? $exam_readiness_page));
        $readiness_problem_total_pages = max(1, (int) ($readiness['problem_total_pages'] ?? 1));
        $preflight = is_array($row['preflight'] ?? null) ? $row['preflight'] : [];
        $preflight_status_label = trim((string) ($preflight['status_label'] ?? 'NONAKTIF'));
        $preflight_status_tone = sanitize_html_class((string) ($preflight['status_tone'] ?? 'warning'), 'warning');
        $preflight_session_id = trim((string) ($preflight['session_id'] ?? ''));
        $preflight_target_student_count = max(0, (int) ($preflight['target_student_count'] ?? 0));
        $preflight_profile_success_count = max(0, (int) ($preflight['profile_success_count'] ?? 0));
        $preflight_profile_failure_count = max(0, (int) ($preflight['profile_failure_count'] ?? 0));
        $preflight_profile_processed_count = max(0, (int) ($preflight['profile_processed_count'] ?? ($preflight_profile_success_count + $preflight_profile_failure_count)));
        $preflight_profiles_reuse_count = max(0, (int) ($preflight['profiles_reuse_count'] ?? 0));
        $preflight_profiles_pending_count = max(0, (int) ($preflight['profiles_pending_count'] ?? max(0, $preflight_target_student_count - $preflight_profile_success_count)));
        $preflight_login_snapshot_success_count = max(0, (int) ($preflight['login_snapshot_success_count'] ?? 0));
        $preflight_login_snapshot_failure_count = max(0, (int) ($preflight['login_snapshot_failure_count'] ?? 0));
        $preflight_login_snapshot_ready_count = max(0, (int) ($preflight['login_snapshot_ready_count'] ?? $preflight_login_snapshot_success_count));
        $preflight_login_snapshot_missing_count = max(0, (int) ($preflight['login_snapshot_missing_count'] ?? max(0, $preflight_target_student_count - $preflight_login_snapshot_ready_count)));
        $preflight_login_reuse_count = max(0, (int) ($preflight['login_reuse_count'] ?? 0));
        $preflight_login_pending_count = max(0, (int) ($preflight['login_pending_count'] ?? $preflight_login_snapshot_missing_count));
        $preflight_availability_ready_count = max(0, (int) ($preflight['availability_ready_count'] ?? 0));
        $preflight_availability_reuse_count = max(0, (int) ($preflight['availability_reuse_count'] ?? 0));
        $preflight_availability_pending_count = max(0, (int) ($preflight['availability_pending_count'] ?? $readiness_availability_missing_count));
        $preflight_availability_failure_count = max(0, (int) ($preflight['availability_failure_count'] ?? 0));
        $preflight_started_at = trim((string) ($preflight['started_at'] ?? ''));
        $preflight_finished_at = trim((string) ($preflight['finished_at'] ?? ''));
        $preflight_last_tick_at = trim((string) ($preflight['last_tick_at'] ?? ''));
        $preflight_message = trim((string) ($preflight['last_message'] ?? ''));
        $preflight_can_start = !empty($preflight['can_start']);
        $preflight_target_kelas = array_values(array_filter(array_map('strval', (array) ($preflight['target_kelas'] ?? []))));
        $preflight_question_stage_label = trim((string) ($preflight['stage_question_label'] ?? 'BELUM'));
        $preflight_question_stage_tone = sanitize_html_class((string) ($preflight['stage_question_tone'] ?? 'warning'), 'warning');
        $preflight_start_snapshot_stage_label = trim((string) ($preflight['stage_start_snapshot_label'] ?? 'BELUM'));
        $preflight_start_snapshot_stage_tone = sanitize_html_class((string) ($preflight['stage_start_snapshot_tone'] ?? 'warning'), 'warning');
        $preflight_profile_stage_label = trim((string) ($preflight['stage_profiles_label'] ?? 'BELUM'));
        $preflight_profile_stage_tone = sanitize_html_class((string) ($preflight['stage_profiles_tone'] ?? 'warning'), 'warning');
        $preflight_login_snapshot_stage_label = trim((string) ($preflight['stage_login_snapshot_label'] ?? 'BELUM'));
        $preflight_login_snapshot_stage_tone = sanitize_html_class((string) ($preflight['stage_login_snapshot_tone'] ?? 'warning'), 'warning');
        $preflight_auto_warm_stage_label = trim((string) ($preflight['stage_auto_warm_label'] ?? 'BELUM'));
        $preflight_auto_warm_stage_tone = sanitize_html_class((string) ($preflight['stage_auto_warm_tone'] ?? 'warning'), 'warning');
        $preflight_question_cache_ready = !empty($preflight['question_cache_ready']);
        $preflight_start_cache_ready = !empty($preflight['start_cache_ready']);
        $preflight_availability_cache_ready = !empty($preflight['availability_cache_ready']);
        $preflight_profile_cache_ready = !empty($preflight['profile_cache_ready']);
        $preflight_login_snapshot_cache_ready = !empty($preflight['login_snapshot_cache_ready']);
        $preflight_rest_warm_ready = !empty($preflight['rest_warm_ready']);
        $preflight_start_warm_ready = !empty($preflight['start_warm_ready']);
        $preflight_blocking_exam_id = max(0, (int) ($preflight['blocking_exam_id'] ?? 0));
        $preflight_blocking_exam_title = trim((string) ($preflight['blocking_exam_title'] ?? ''));
        $preflight_blocking_auto_warm_exam_id = max(0, (int) ($preflight['blocking_auto_warm_exam_id'] ?? 0));
        $preflight_blocking_auto_warm_exam_title = trim((string) ($preflight['blocking_auto_warm_exam_title'] ?? ''));
        $preflight_queue_position = max(0, (int) ($preflight['queue_position'] ?? 0));
        $preflight_queue_total = max(0, (int) ($preflight['queue_total'] ?? 0));
        $preflight_global_runner_exam_id = max(0, (int) ($preflight['global_runner_exam_id'] ?? 0));
        $preflight_global_runner_exam_title = trim((string) ($preflight['global_runner_exam_title'] ?? ''));
        $preflight_global_mode_label = trim((string) ($preflight['global_mode_label'] ?? 'PARALEL'));
        $preflight_global_batch_size = max(0, (int) ($preflight['global_batch_size'] ?? 150));
        $preflight_active_global_layer = trim((string) ($preflight['active_global_layer'] ?? ''));
        $preflight_queued_exam_titles = array_values(array_filter(array_map('strval', (array) ($preflight['queued_exam_titles'] ?? []))));
        $preflight_submission_context_question_count = max(0, (int) ($preflight['submission_context_question_count'] ?? 0));
        $preflight_submission_context_ready_count = max(0, (int) ($preflight['submission_context_ready_count'] ?? 0));
        $preflight_submission_context_missing_count = max(0, (int) ($preflight['submission_context_missing_count'] ?? 0));
        $preflight_submission_context_invalid_count = max(0, (int) ($preflight['submission_context_invalid_count'] ?? 0));
        $preflight_submission_context_stage_label = trim((string) ($preflight['stage_submission_context_label'] ?? 'BELUM'));
        $preflight_submission_context_stage_tone = sanitize_html_class((string) ($preflight['stage_submission_context_tone'] ?? 'warning'), 'warning');
        $preflight_submission_context_cache_ready = !empty($preflight['submission_context_cache_ready']);
        $preflight_submission_context_warm_ready = !empty($preflight['submission_context_warm_ready']);
        $submission_context = is_array($row['submission_context'] ?? null) ? $row['submission_context'] : [];
        $submission_context_status_label = trim((string) ($row['submission_context_status_label'] ?? 'MISS')) !== ''
            ? (string) ($row['submission_context_status_label'] ?? 'MISS')
            : 'MISS';
        $submission_context_status_tone = sanitize_html_class((string) ($row['submission_context_status_tone'] ?? 'warning'), 'warning');
        $submission_context_question_count = max(0, (int) ($submission_context['question_count'] ?? 0));
        $submission_context_ready_count = max(0, (int) ($submission_context['ready_count'] ?? 0));
        $submission_context_missing_count = max(0, (int) ($submission_context['missing_count'] ?? 0));
        $submission_context_invalid_count = max(0, (int) ($submission_context['invalid_count'] ?? 0));
        $submission_context_payload_bytes_total = max(0, (int) ($submission_context['payload_bytes_total'] ?? 0));
        $submission_context_payload_bytes_label = $submission_context_payload_bytes_total > 0 ? number_format_i18n($submission_context_payload_bytes_total) . ' bytes' : '0 bytes';
        $submission_context_message = trim((string) ($submission_context['snapshot_message'] ?? ''));
        $submission_context_preview_items = array_values(array_filter((array) ($submission_context['preview_items'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $submission_context_redis_host = trim((string) ($submission_context['redis_host'] ?? ''));
        $submission_context_redis_database = (int) ($submission_context['redis_database'] ?? 0);
        $submission_context_redis_error = trim((string) ($submission_context['redis_error'] ?? ''));
        $submission_context_snapshot_exists = !empty($submission_context['snapshot_exists']);
        $submission_context_snapshot_valid = !empty($submission_context['snapshot_valid']);
        $session_runtime = is_array($row['session_runtime'] ?? null) ? $row['session_runtime'] : [];
        $session_runtime_rows = array_values(array_filter((array) ($session_runtime['rows'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $session_runtime_attempt_total = max(0, (int) ($session_runtime['attempt_total'] ?? count($session_runtime_rows)));
        $session_runtime_delivery_snapshot = is_array($session_runtime['delivery_snapshot'] ?? null) ? $session_runtime['delivery_snapshot'] : [];
        $session_runtime_delivery_status_label = trim((string) ($session_runtime['delivery_status_label'] ?? 'MISS'));
        if ($session_runtime_delivery_status_label === '') {
            $session_runtime_delivery_status_label = 'MISS';
        }
        $session_runtime_delivery_status_tone = sanitize_html_class((string) ($session_runtime['delivery_status_tone'] ?? 'warning'), 'warning');
        $session_runtime_redis_first_count = max(0, (int) ($session_runtime['redis_first_count'] ?? 0));
        $session_runtime_legacy_count = max(0, (int) ($session_runtime['legacy_count'] ?? max(0, $session_runtime_attempt_total - $session_runtime_redis_first_count)));
        $session_runtime_session_ready_count = max(0, (int) ($session_runtime['session_ready_count'] ?? 0));
        $session_runtime_contract_ready_count = max(0, (int) ($session_runtime['contract_ready_count'] ?? 0));
        $session_runtime_runtime_ready_count = max(0, (int) ($session_runtime['runtime_ready_count'] ?? 0));
        $session_runtime_stale_last_seen_count = max(0, (int) ($session_runtime['stale_last_seen_count'] ?? 0));
        $session_runtime_low_remaining_count = max(0, (int) ($session_runtime['low_remaining_count'] ?? 0));
        $session_runtime_fallback_breakdown = array_values(array_filter((array) ($session_runtime['fallback_breakdown'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $session_runtime_issue_flags = array_values(array_filter((array) ($session_runtime['issue_flags'] ?? []), 'is_string'));
        if ($session_runtime_attempt_total <= 0) {
            $session_runtime_status_label = 'IDLE';
            $session_runtime_status_tone = 'warning';
        } elseif ($session_runtime_legacy_count === 0 && $session_runtime_stale_last_seen_count === 0) {
            $session_runtime_status_label = 'READY';
            $session_runtime_status_tone = 'success';
        } elseif ($session_runtime_redis_first_count > 0 || $session_runtime_stale_last_seen_count > 0) {
            $session_runtime_status_label = 'HYBRID';
            $session_runtime_status_tone = 'warning';
        } else {
            $session_runtime_status_label = 'LEGACY';
            $session_runtime_status_tone = 'warning';
        }
        $primary_status_label = $snapshot_status_label;
        $primary_status_tone = $snapshot_status_tone;
        if ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR) {
            $primary_status_label = $start_snapshot_status_label;
            $primary_status_tone = $start_snapshot_status_tone;
        } elseif ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR) {
            $primary_status_label = $submission_context_status_label;
            $primary_status_tone = $submission_context_status_tone;
        } elseif ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR) {
            $primary_status_label = $session_runtime_status_label;
            $primary_status_tone = $session_runtime_status_tone;
        } elseif ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT) {
            $primary_status_label = $readiness_overall_label;
            $primary_status_tone = $readiness_overall_tone;
        }
        ?>
        <article class="cbt-exam-snapshot-monitor-card" data-cbt-exam-snapshot-monitor-card="<?php echo esc_attr((string) $exam_id); ?>">
            <div class="cbt-exam-snapshot-monitor-card-head">
                <div>
                    <div class="cbt-exam-snapshot-title"><?php echo esc_html($title); ?></div>
                    <div class="cbt-exam-snapshot-meta">
                        <?php if ($subject_name !== ''): ?>
                            <span><strong>Mapel:</strong> <?php echo esc_html($subject_name); ?></span>
                        <?php endif; ?>
                        <?php if ($exam_status !== ''): ?>
                            <span><strong>Status Exam:</strong> <?php echo esc_html($exam_status); ?></span>
                        <?php endif; ?>
                        <span><strong>Exam ID:</strong> <?php echo esc_html((string) $exam_id); ?></span>
                    </div>
                </div>
                <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($primary_status_tone); ?>"><?php echo esc_html($primary_status_label); ?></span>
            </div>

            <?php if ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR): ?>
                <div class="cbt-exam-snapshot-summary-grid">
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Attempt Aktif</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $session_runtime_attempt_total); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Redis-First</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($session_runtime_redis_first_count . ' / ' . $session_runtime_attempt_total); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Legacy</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $session_runtime_legacy_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Session Ready</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($session_runtime_session_ready_count . ' / ' . $session_runtime_attempt_total); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Contract Ready</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($session_runtime_contract_ready_count . ' / ' . $session_runtime_attempt_total); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Runtime Ready</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($session_runtime_runtime_ready_count . ' / ' . $session_runtime_attempt_total); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Stale Last Seen</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $session_runtime_stale_last_seen_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Delivery Snapshot</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($session_runtime_delivery_status_label); ?></strong>
                        <span class="cbt-exam-readiness-summary-meta"><?php echo esc_html('Exam-level · ' . (int) ($session_runtime_delivery_snapshot['snapshot_item_count'] ?? 0) . ' soal'); ?></span>
                    </div>
                </div>
                <p class="cbt-exam-snapshot-note">Gunakan tab ini untuk memeriksa apakah session snapshot dan contract snapshot per attempt sudah siap dipakai oleh request live siswa. Delivery snapshot diringkas sekali di level exam karena sumbernya memang shared untuk seluruh attempt exam ini.</p>
                <div class="cbt-exam-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Refresh Delivery Snapshot</button>
                    </form>
                </div>
                <?php if (!empty($session_runtime_issue_flags)): ?>
                    <div class="cbt-exam-snapshot-detail-grid">
                        <span><strong>Actionable Flags:</strong> <?php echo esc_html(implode(' · ', $session_runtime_issue_flags)); ?></span>
                        <?php if ($session_runtime_low_remaining_count > 0): ?>
                            <span><strong>Low Remaining:</strong> <?php echo esc_html((string) $session_runtime_low_remaining_count); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($session_runtime_fallback_breakdown)): ?>
                    <div class="cbt-exam-snapshot-detail-grid">
                        <span><strong>Fallback Breakdown:</strong>
                            <?php
                            $fallback_chunks = [];
                            foreach ($session_runtime_fallback_breakdown as $fallback_item) {
                                $fallback_chunks[] = trim((string) ($fallback_item['label'] ?? '')) . ' -> ' . max(0, (int) ($fallback_item['count'] ?? 0));
                            }
                            echo esc_html(implode(' · ', array_filter($fallback_chunks)));
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                <details class="cbt-exam-auto-warm-tech">
                    <summary>Detail Delivery Snapshot</summary>
                    <div class="cbt-exam-auto-warm-tech-body">
                        <span><strong>Delivery Status:</strong> <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($session_runtime_delivery_status_tone); ?>"><?php echo esc_html($session_runtime_delivery_status_label); ?></span></span>
                        <span><strong>Delivery Storage Key:</strong> <code><?php echo esc_html((string) ($session_runtime_delivery_snapshot['storage_key'] ?? '-')); ?></code></span>
                        <span><strong>Question Count:</strong> <?php echo esc_html((string) max(0, (int) ($session_runtime_delivery_snapshot['snapshot_item_count'] ?? 0))); ?></span>
                        <span><strong>Delivery Exists / Valid:</strong> <?php echo esc_html(!empty($session_runtime_delivery_snapshot['snapshot_exists']) ? 'Ya' : 'Tidak'); ?> / <?php echo esc_html(!empty($session_runtime_delivery_snapshot['snapshot_valid']) ? 'Ya' : 'Tidak'); ?></span>
                    </div>
                </details>
                <?php if (empty($session_runtime_rows)): ?>
                    <div class="cbt-exam-snapshot-empty-state">
                        Belum ada attempt siswa yang sedang `in_progress` untuk exam ini.
                    </div>
                <?php else: ?>
                    <div class="cbt-exam-list-table-wrap">
                        <table class="widefat striped cbt-exam-runtime-monitor-table">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Attempt</th>
                                    <th>Status Attempt</th>
                                    <th>Session Snapshot</th>
                                    <th>Contract Snapshot</th>
                                    <th>Runtime Answers</th>
                                    <th>Last Seen / Sisa</th>
                                    <th>Fallback</th>
                                    <th>Issue Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($session_runtime_rows as $session_runtime_row): ?>
                                    <?php
                                    $runtime_student_name = trim((string) ($session_runtime_row['display_name'] ?? '')) !== ''
                                        ? (string) $session_runtime_row['display_name']
                                        : ('Siswa #' . (int) ($session_runtime_row['student_id'] ?? 0));
                                    $runtime_user_login = trim((string) ($session_runtime_row['user_login'] ?? ''));
                                    $runtime_kode_kelas = trim((string) ($session_runtime_row['kode_kelas'] ?? ''));
                                    $runtime_kode_ruang = trim((string) ($session_runtime_row['kode_ruang'] ?? ''));
                                    $runtime_attempt_id = (int) ($session_runtime_row['attempt_id'] ?? 0);
                                    $runtime_attempt_status = trim((string) ($session_runtime_row['status'] ?? 'in_progress'));
                                    $runtime_last_seen_at = trim((string) ($session_runtime_row['last_seen_at'] ?? ''));
                                    $runtime_remaining_label = trim((string) ($session_runtime_row['remaining_label'] ?? '00:00:00'));
                                    $runtime_fallback_mode = trim((string) ($session_runtime_row['fallback_mode'] ?? 'LEGACY'));
                                    $runtime_issue_summary = trim((string) ($session_runtime_row['issue_summary'] ?? 'Healthy'));
                                    $runtime_last_seen_is_stale = !empty($session_runtime_row['last_seen_is_stale']);
                                    $runtime_low_remaining = !empty($session_runtime_row['low_remaining']);
                                    $runtime_session_snapshot = is_array($session_runtime_row['session_snapshot'] ?? null) ? $session_runtime_row['session_snapshot'] : [];
                                    $runtime_contract_snapshot = is_array($session_runtime_row['contract_snapshot'] ?? null) ? $session_runtime_row['contract_snapshot'] : [];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($runtime_student_name); ?></strong>
                                            <div class="cbt-exam-snapshot-meta">
                                                <?php if ($runtime_user_login !== ''): ?>
                                                    <span><strong>Login:</strong> <?php echo esc_html($runtime_user_login); ?></span>
                                                <?php endif; ?>
                                                <span><strong>Kelas:</strong> <?php echo esc_html($runtime_kode_kelas !== '' ? $runtime_kode_kelas : '-'); ?></span>
                                                <span><strong>Ruang:</strong> <?php echo esc_html($runtime_kode_ruang !== '' ? $runtime_kode_ruang : '-'); ?></span>
                                            </div>
                                            <details class="cbt-exam-auto-warm-tech">
                                                <summary>Detail teknis</summary>
                                                <div class="cbt-exam-auto-warm-tech-body">
                                                    <span><strong>Session Storage Key:</strong> <code><?php echo esc_html((string) ($runtime_session_snapshot['storage_key'] ?? '-')); ?></code></span>
                                                    <span><strong>Contract Storage Key:</strong> <code><?php echo esc_html((string) ($runtime_contract_snapshot['storage_key'] ?? '-')); ?></code></span>
                                                    <span><strong>Question Count:</strong> <?php echo esc_html((string) max((int) ($runtime_session_snapshot['question_count'] ?? 0), (int) ($runtime_contract_snapshot['question_count'] ?? 0))); ?></span>
                                                    <span><strong>Order Signature:</strong> <code><?php echo esc_html((string) (($runtime_contract_snapshot['question_order_signature'] ?? $runtime_session_snapshot['question_order_signature'] ?? '-') ?: '-')); ?></code></span>
                                                    <span><strong>Session Exists / Valid:</strong> <?php echo esc_html(!empty($runtime_session_snapshot['snapshot_exists']) ? 'Ya' : 'Tidak'); ?> / <?php echo esc_html(!empty($runtime_session_snapshot['snapshot_valid']) ? 'Ya' : 'Tidak'); ?></span>
                                                    <span><strong>Contract Exists / Valid:</strong> <?php echo esc_html(!empty($runtime_contract_snapshot['snapshot_exists']) ? 'Ya' : 'Tidak'); ?> / <?php echo esc_html(!empty($runtime_contract_snapshot['snapshot_valid']) ? 'Ya' : 'Tidak'); ?></span>
                                                    <span><strong>Last Seen Stale:</strong> <?php echo esc_html($runtime_last_seen_is_stale ? 'Ya' : 'Tidak'); ?></span>
                                                    <span><strong>Low Remaining:</strong> <?php echo esc_html($runtime_low_remaining ? 'Ya' : 'Tidak'); ?></span>
                                                </div>
                                            </details>
                                            <div class="cbt-exam-snapshot-row-actions">
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                                                    <?php wp_nonce_field('cbt_refresh_attempt_runtime_snapshot'); ?>
                                                    <input type="hidden" name="action" value="cbt_refresh_attempt_runtime_snapshot" />
                                                    <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                                                    <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $runtime_attempt_id); ?>" />
                                                    <?php self::render_snapshot_tab_hidden_field($mode); ?>
                                                    <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                                                    <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                                                    <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                                                    <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                                                    <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                                                    <button type="submit" class="button button-small">Refresh Runtime Snapshot</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td>#<?php echo esc_html((string) $runtime_attempt_id); ?></td>
                                        <td><?php echo esc_html($runtime_attempt_status !== '' ? $runtime_attempt_status : 'in_progress'); ?></td>
                                        <td><span class="cbt-exam-snapshot-status is-<?php echo esc_attr(sanitize_html_class((string) ($session_runtime_row['session_status_tone'] ?? 'warning'), 'warning')); ?>"><?php echo esc_html((string) ($session_runtime_row['session_status_label'] ?? 'MISS')); ?></span></td>
                                        <td><span class="cbt-exam-snapshot-status is-<?php echo esc_attr(sanitize_html_class((string) ($session_runtime_row['contract_status_tone'] ?? 'warning'), 'warning')); ?>"><?php echo esc_html((string) ($session_runtime_row['contract_status_label'] ?? 'MISS')); ?></span></td>
                                        <td><span class="cbt-exam-snapshot-status is-<?php echo esc_attr(sanitize_html_class((string) ($session_runtime_row['runtime_answers_status_tone'] ?? 'warning'), 'warning')); ?>"><?php echo esc_html((string) ($session_runtime_row['runtime_answers_status_label'] ?? 'MISS')); ?></span></td>
                                        <td>
                                            <div><?php echo esc_html($runtime_last_seen_at !== '' ? $runtime_last_seen_at : '-'); ?></div>
                                            <small><?php echo esc_html('Sisa ' . $runtime_remaining_label); ?></small>
                                        </td>
                                        <td><code><?php echo esc_html($runtime_fallback_mode !== '' ? $runtime_fallback_mode : '-'); ?></code></td>
                                        <td><?php echo esc_html($runtime_issue_summary !== '' ? $runtime_issue_summary : 'Healthy'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php elseif ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR): ?>
                <div class="cbt-exam-snapshot-summary-grid">
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Revision</span>
                        <div class="cbt-exam-snapshot-summary-stack">
                            <strong>v<?php echo esc_html((string) $revision_version); ?></strong>
                            <span><?php echo esc_html($signature !== '' ? $signature : '-'); ?></span>
                        </div>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Jumlah Soal</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $snapshot_item_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">TTL</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($ttl_label); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Ukuran Payload</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($payload_bytes_label); ?></strong>
                    </div>
                </div>
                <div class="cbt-exam-snapshot-detail-grid">
                    <span><strong>Storage Key:</strong> <code><?php echo esc_html($storage_key !== '' ? $storage_key : '-'); ?></code></span>
                    <span><strong>Host Redis:</strong> <code><?php echo esc_html($redis_host !== '' ? $redis_host : '-'); ?></code></span>
                    <span><strong>Database Redis:</strong> <?php echo esc_html((string) $redis_database); ?></span>
                    <span><strong>Invalidated At:</strong> <code><?php echo esc_html($invalidated_at !== '' ? $invalidated_at : '-'); ?></code></span>
                    <span><strong>Signature:</strong> <code><?php echo esc_html($signature !== '' ? $signature : '-'); ?></code></span>
                    <span><strong>Question IDs:</strong> <code><?php echo esc_html(!empty($preview_question_ids) ? implode(', ', $preview_question_ids) : '-'); ?></code></span>
                    <?php if ($redis_error !== ''): ?>
                        <span><strong>Error Redis:</strong> <code><?php echo esc_html($redis_error); ?></code></span>
                    <?php endif; ?>
                </div>
                <?php if ($snapshot_message !== ''): ?>
                    <p class="cbt-exam-snapshot-note"><?php echo esc_html($snapshot_message); ?></p>
                <?php endif; ?>
                <div class="cbt-exam-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Siapkan Snapshot Exam</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button">Bersihkan Snapshot Exam</button>
                    </form>
                </div>
                <details class="cbt-exam-snapshot-preview-dropdown" data-cbt-exam-snapshot-preview-dropdown="<?php echo esc_attr((string) $exam_id); ?>" <?php echo $preview_is_expanded ? 'open="open"' : ''; ?>>
                    <summary class="cbt-exam-snapshot-preview-summary">
                        <span class="cbt-exam-snapshot-preview-summary-title"><?php echo esc_html($preview_summary_label); ?></span>
                        <span class="cbt-exam-snapshot-preview-summary-meta">
                            <span><?php echo esc_html('Hal. ' . $preview_current_page . ' / ' . $preview_total_pages); ?></span>
                            <span><?php echo esc_html($snapshot_item_count . ' soal'); ?></span>
                        </span>
                    </summary>
                    <div class="cbt-exam-snapshot-preview-body">
                        <?php if (empty($preview_items)): ?>
                            <p class="cbt-exam-snapshot-note">Belum ada preview soal pada snapshot ini.</p>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-preview-list">
                                <?php foreach ($preview_items as $preview_item): ?>
                                    <?php
                                    $preview_question_id = (int) ($preview_item['id'] ?? 0);
                                    $preview_question_type = (string) ($preview_item['question_type'] ?? '');
                                    $preview_points = (float) ($preview_item['points'] ?? 0);
                                    $preview_text = (string) ($preview_item['question_text_excerpt'] ?? '');
                                    $preview_option_count = (int) ($preview_item['option_count'] ?? 0);
                                    ?>
                                    <article class="cbt-exam-snapshot-preview-item">
                                        <div class="cbt-exam-snapshot-preview-head">
                                            <span class="cbt-exam-snapshot-preview-pill">Question #<?php echo esc_html((string) $preview_question_id); ?></span>
                                            <span class="cbt-exam-snapshot-preview-pill"><?php echo esc_html($preview_question_type !== '' ? $preview_question_type : 'unknown'); ?></span>
                                            <span class="cbt-exam-snapshot-preview-pill">Poin <?php echo esc_html(number_format_i18n($preview_points, 2)); ?></span>
                                            <span class="cbt-exam-snapshot-preview-pill">Opsi <?php echo esc_html((string) $preview_option_count); ?></span>
                                        </div>
                                        <div class="cbt-exam-snapshot-preview-text"><?php echo esc_html($preview_text !== '' ? $preview_text : '(teks soal kosong)'); ?></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($preview_total_pages > 1): ?>
                            <div class="cbt-exam-snapshot-preview-pagination" aria-label="<?php echo esc_attr('Pagination preview exam ' . $exam_id); ?>">
                                <?php if ($preview_current_page > 1): ?>
                                    <a class="button button-secondary" href="<?php echo esc_url(self::build_snapshot_preview_page_url($exam_id, $preview_current_page - 1, $exam_list_state, $snapshot_filter_state, $preview_pages, $student_snapshot_filter_state, $readiness_problem_page, $mode)); ?>">Sebelumnya</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" aria-disabled="true">Sebelumnya</span>
                                <?php endif; ?>
                                <span class="cbt-exam-snapshot-preview-pagination-state">
                                    <?php echo esc_html('Halaman ' . $preview_current_page . ' dari ' . $preview_total_pages); ?>
                                </span>
                                <?php if ($preview_current_page < $preview_total_pages): ?>
                                    <a class="button button-secondary" href="<?php echo esc_url(self::build_snapshot_preview_page_url($exam_id, $preview_current_page + 1, $exam_list_state, $snapshot_filter_state, $preview_pages, $student_snapshot_filter_state, $readiness_problem_page, $mode)); ?>">Berikutnya</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" aria-disabled="true">Berikutnya</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php elseif ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR): ?>
                <div class="cbt-exam-snapshot-summary-grid">
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Start Revision</span>
                        <div class="cbt-exam-snapshot-summary-stack">
                            <strong>v<?php echo esc_html((string) $start_snapshot_revision_version); ?></strong>
                            <span><?php echo esc_html($start_snapshot_signature !== '' ? $start_snapshot_signature : '-'); ?></span>
                        </div>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Start Items</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $start_snapshot_item_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Start TTL</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($start_snapshot_ttl_label); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Start Payload</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($start_snapshot_payload_bytes_label); ?></strong>
                    </div>
                </div>
                <div class="cbt-exam-snapshot-detail-grid">
                    <span><strong>Start Storage Key:</strong> <code><?php echo esc_html($start_snapshot_storage_key !== '' ? $start_snapshot_storage_key : '-'); ?></code></span>
                    <span><strong>Start Host Redis:</strong> <code><?php echo esc_html($start_snapshot_redis_host !== '' ? $start_snapshot_redis_host : '-'); ?></code></span>
                    <span><strong>Start Database Redis:</strong> <?php echo esc_html((string) $start_snapshot_redis_database); ?></span>
                    <span><strong>Start Invalidated At:</strong> <code><?php echo esc_html($start_snapshot_invalidated_at !== '' ? $start_snapshot_invalidated_at : '-'); ?></code></span>
                    <span><strong>Start Signature:</strong> <code><?php echo esc_html($start_snapshot_signature !== '' ? $start_snapshot_signature : '-'); ?></code></span>
                    <span><strong>Question IDs:</strong> <code><?php echo esc_html(!empty($preview_question_ids) ? implode(', ', $preview_question_ids) : '-'); ?></code></span>
                    <?php if ($start_snapshot_redis_error !== ''): ?>
                        <span><strong>Error Start Redis:</strong> <code><?php echo esc_html($start_snapshot_redis_error); ?></code></span>
                    <?php endif; ?>
                </div>
                <?php if ($start_snapshot_message !== ''): ?>
                    <p class="cbt-exam-snapshot-note"><?php echo esc_html($start_snapshot_message); ?></p>
                <?php endif; ?>
                <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle">Aksi warm dan clear di tab ini tetap menyinkronkan snapshot soal dan start snapshot secara bersamaan.</p>
                <div class="cbt-exam-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Siapkan Snapshot Exam + Start</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button">Bersihkan Snapshot Exam + Start</button>
                    </form>
                </div>
            <?php elseif ($mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR): ?>
                <div class="cbt-exam-snapshot-summary-grid">
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Status</span>
                        <div class="cbt-exam-snapshot-summary-stack">
                            <strong><?php echo esc_html($submission_context_status_label); ?></strong>
                            <span><?php echo esc_html('READY ' . $submission_context_ready_count . ' / ' . $submission_context_question_count); ?></span>
                        </div>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Soal Aktif</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $submission_context_question_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">MISS / INVALID</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html('MISS ' . $submission_context_missing_count . ' · INVALID ' . $submission_context_invalid_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Total Payload</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($submission_context_payload_bytes_label); ?></strong>
                    </div>
                </div>
                <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle">Snapshot ini mempercepat konteks evaluasi jawaban untuk submit/autosave/scoring objektif. Ini bukan delivery soal ke siswa.</p>
                <?php if (!empty($submission_context_preview_items)): ?>
                    <div class="cbt-student-snapshot-preview-list cbt-student-snapshot-preview-list--expanded">
                        <?php foreach ($submission_context_preview_items as $preview_item): ?>
                            <?php
                            $preview_question_id = (int) ($preview_item['question_id'] ?? 0);
                            $preview_question_type = (string) ($preview_item['question_type'] ?? '');
                            $preview_status = strtoupper((string) ($preview_item['status'] ?? 'miss'));
                            $preview_payload_bytes = max(0, (int) ($preview_item['payload_bytes'] ?? 0));
                            ?>
                            <span class="cbt-student-snapshot-preview-pill">
                                <?php echo esc_html('Q#' . $preview_question_id . ' · ' . ($preview_question_type !== '' ? $preview_question_type : 'unknown') . ' · ' . $preview_status . ' · ' . number_format_i18n($preview_payload_bytes) . ' bytes'); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="cbt-exam-snapshot-note">Belum ada preview submission context pada exam ini.</p>
                <?php endif; ?>
                <?php if ($submission_context_question_count > count($submission_context_preview_items)): ?>
                    <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle"><?php echo esc_html('Preview dibatasi ' . count($submission_context_preview_items) . ' soal pertama dari ' . $submission_context_question_count . ' soal aktif.'); ?></p>
                <?php endif; ?>
                <div class="cbt-exam-snapshot-detail-grid">
                    <span><strong>Snapshot Exists:</strong> <?php echo esc_html($submission_context_snapshot_exists ? 'Ya' : 'Tidak'); ?></span>
                    <span><strong>Snapshot Valid:</strong> <?php echo esc_html($submission_context_snapshot_valid ? 'Ya' : 'Tidak'); ?></span>
                    <span><strong>Host Redis:</strong> <code><?php echo esc_html($submission_context_redis_host !== '' ? $submission_context_redis_host : '-'); ?></code></span>
                    <span><strong>Database Redis:</strong> <?php echo esc_html((string) $submission_context_redis_database); ?></span>
                    <span><strong>READY / MISS / INVALID:</strong> <?php echo esc_html($submission_context_ready_count . ' / ' . $submission_context_missing_count . ' / ' . $submission_context_invalid_count); ?></span>
                    <?php if ($submission_context_redis_error !== ''): ?>
                        <span><strong>Error Redis:</strong> <code><?php echo esc_html($submission_context_redis_error); ?></code></span>
                    <?php endif; ?>
                </div>
                <?php if ($submission_context_message !== ''): ?>
                    <p class="cbt-exam-snapshot-note"><?php echo esc_html($submission_context_message); ?></p>
                <?php endif; ?>
                <div class="cbt-exam-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_exam_submission_context_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_exam_submission_context_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Siapkan Submission Context</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_exam_submission_context_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_exam_submission_context_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field($mode); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button">Bersihkan Submission Context</button>
                    </form>
                </div>
            <?php else: ?>
                <?php
                $preflight_exam_meta = $subject_name !== ''
                    ? ($subject_name . ' · Token ' . $readiness_token_label)
                    : ('Token ' . $readiness_token_label);
                $preflight_question_stage_ready_count = ($preflight_question_cache_ready && $preflight_rest_warm_ready)
                    ? $snapshot_item_count
                    : 0;
                $preflight_question_stage_missing_count = max(0, $snapshot_item_count - $preflight_question_stage_ready_count);
                $preflight_question_stage_summary = 'Siap ' . $preflight_question_stage_ready_count . '/' . $snapshot_item_count . ' · Belum ' . $preflight_question_stage_missing_count;
                $preflight_question_stage_meta = 'Total ' . $snapshot_item_count . ' soal · Cache ' . self::format_preflight_ready_meta($preflight_question_cache_ready) . ' · Warm ' . self::format_preflight_ready_meta($preflight_rest_warm_ready);
                $preflight_start_stage_ready_count = ($preflight_start_cache_ready && $preflight_start_warm_ready)
                    ? $start_snapshot_item_count
                    : 0;
                $preflight_start_stage_missing_count = max(0, $start_snapshot_item_count - $preflight_start_stage_ready_count);
                $preflight_start_stage_summary = 'Siap ' . $preflight_start_stage_ready_count . '/' . $start_snapshot_item_count . ' · Belum ' . $preflight_start_stage_missing_count;
                $preflight_start_stage_meta = 'Total ' . $start_snapshot_item_count . ' item · Cache ' . self::format_preflight_ready_meta($preflight_start_cache_ready) . ' · Warm ' . self::format_preflight_ready_meta($preflight_start_warm_ready);
                $preflight_submission_stage_summary = 'Siap ' . $preflight_submission_context_ready_count . '/' . $preflight_submission_context_question_count . ' · Belum ' . $preflight_submission_context_missing_count;
                $preflight_submission_stage_meta = 'Total ' . $preflight_submission_context_question_count . ' soal · INVALID ' . $preflight_submission_context_invalid_count;
                $preflight_profile_stage_summary = 'Siap ' . $preflight_profile_success_count . '/' . $preflight_target_student_count . ' · Pending ' . $preflight_profiles_pending_count;
                $preflight_profile_stage_meta = 'Reuse ' . $preflight_profiles_reuse_count . ' · Gagal ' . $preflight_profile_failure_count . ' · Diproses ' . $preflight_profile_processed_count;
                $preflight_login_stage_summary = 'Siap ' . $preflight_login_snapshot_ready_count . '/' . $preflight_target_student_count . ' · Pending ' . $preflight_login_pending_count;
                $preflight_login_stage_meta = 'Reuse ' . $preflight_login_reuse_count . ' · Gagal ' . $preflight_login_snapshot_failure_count . ' · Diproses ' . max(0, $preflight_login_snapshot_ready_count + $preflight_login_snapshot_failure_count);
                $preflight_auto_warm_stage_summary = 'Siap ' . $preflight_availability_ready_count . '/' . $preflight_target_student_count . ' · Pending ' . $preflight_availability_pending_count;
                $preflight_auto_warm_stage_meta = 'Reuse ' . $preflight_availability_reuse_count . ' · Gagal ' . $preflight_availability_failure_count . ' · Queue ' . $preflight_queue_total;
                $preflight_active_global_layer_label = $preflight_active_global_layer === 'parallel'
                    ? 'PARALEL'
                    : ($preflight_active_global_layer !== '' ? strtoupper(str_replace('_', ' ', $preflight_active_global_layer)) : '-');
                $preflight_queued_exam_summary = !empty($preflight_queued_exam_titles) ? implode(', ', $preflight_queued_exam_titles) : '-';
                ?>
                <div class="cbt-exam-preflight-panel">
                    <div class="cbt-exam-preflight-panel-head">
                        <div>
                            <strong>One-Click Pra Ujian</strong>
                            <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle">Ringkasan kesiapan dan aksi pra-ujian untuk exam terpilih. Detail setiap snapshot diringkas langsung di kartu stage bawah dan panel ini diperbarui otomatis setiap 10 detik saat tab tetap terbuka. Mode global sekarang berjalan paralel untuk Snapshot Profil, Login Snapshot, dan Auto-Warm Availability dengan batch besar agar cohort besar lebih cepat terdorong. Aksi bersihkan di panel ini hanya menghapus snapshot exam-scoped; reset snapshot siswa tetap dilakukan dari panel monitoring siswa bila dibutuhkan. Setelah siswa mulai mengerjakan, status live session bisa dipantau dari tab Monitor Session Runtime.</p>
                        </div>
                        <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($preflight_status_tone); ?>"><?php echo esc_html($preflight_status_label); ?></span>
                    </div>

                    <div class="cbt-exam-preflight-summary-grid">
                        <div class="cbt-exam-readiness-summary-card">
                            <span class="cbt-exam-snapshot-summary-label">Kesiapan Saat Ini</span>
                            <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($readiness_overall_label); ?></strong>
                            <span class="cbt-exam-readiness-summary-meta"><?php echo esc_html('Blocker ' . count($readiness_blockers) . ' · Warning ' . count($readiness_warnings)); ?></span>
                        </div>
                        <div class="cbt-exam-readiness-summary-card">
                            <span class="cbt-exam-snapshot-summary-label">Exam</span>
                            <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($title); ?></strong>
                            <span class="cbt-exam-readiness-summary-meta"><?php echo esc_html($preflight_exam_meta); ?></span>
                        </div>
                        <div class="cbt-exam-readiness-summary-card">
                            <span class="cbt-exam-snapshot-summary-label">Jadwal & Waktu</span>
                            <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($preflight_started_at !== '' ? $preflight_started_at : '-'); ?></strong>
                            <span class="cbt-exam-readiness-summary-meta"><?php echo esc_html($readiness_schedule_label . ' · Durasi ' . ($readiness_duration_minutes > 0 ? $readiness_duration_minutes . ' menit' : '-') . ' · Selesai ' . ($preflight_finished_at !== '' ? $preflight_finished_at : '-') . ' · Tick ' . ($preflight_last_tick_at !== '' ? $preflight_last_tick_at : '-')); ?></span>
                        </div>
                        <div class="cbt-exam-readiness-summary-card">
                            <span class="cbt-exam-snapshot-summary-label">Mode Global</span>
                            <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($preflight_global_mode_label); ?></strong>
                            <span class="cbt-exam-readiness-summary-meta"><?php echo esc_html('Batch ' . $preflight_global_batch_size . ' · Runner ' . ($preflight_global_runner_exam_id > 0 ? 'aktif' : 'idle')); ?></span>
                        </div>
                    </div>

                    <div class="cbt-exam-readiness-target-row">
                        <div class="cbt-exam-readiness-target-count">
                            <span class="cbt-exam-snapshot-summary-label">Peserta Target</span>
                            <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $preflight_target_student_count); ?></strong>
                            <span class="cbt-exam-readiness-summary-meta">Jumlah siswa target exam ini</span>
                        </div>
                        <div class="cbt-exam-readiness-target-classes">
                            <span class="cbt-exam-snapshot-summary-label">Target Kelas</span>
                            <span class="cbt-exam-readiness-target-list"><?php echo esc_html(!empty($preflight_target_kelas) ? implode(', ', $preflight_target_kelas) : '-'); ?></span>
                        </div>
                    </div>

                    <div class="cbt-exam-readiness-flags">
                        <span class="cbt-student-snapshot-preview-pill"><?php echo esc_html('Result ' . $readiness_show_student_result); ?></span>
                        <span class="cbt-student-snapshot-preview-pill"><?php echo esc_html('Calculator ' . $readiness_enable_calculator); ?></span>
                    </div>

                    <div class="cbt-exam-preflight-stage-grid">
                        <?php self::render_preflight_stage_card('Snapshot Soal', $preflight_question_stage_label, $preflight_question_stage_tone, $preflight_question_stage_summary, $preflight_question_stage_meta); ?>
                        <?php self::render_preflight_stage_card('Start Snapshot', $preflight_start_snapshot_stage_label, $preflight_start_snapshot_stage_tone, $preflight_start_stage_summary, $preflight_start_stage_meta); ?>
                        <?php self::render_preflight_stage_card('Submission Context', $preflight_submission_context_stage_label, $preflight_submission_context_stage_tone, $preflight_submission_stage_summary, $preflight_submission_stage_meta); ?>
                        <?php self::render_preflight_stage_card('Snapshot Profil', $preflight_profile_stage_label, $preflight_profile_stage_tone, $preflight_profile_stage_summary, $preflight_profile_stage_meta); ?>
                        <?php self::render_preflight_stage_card('Login Snapshot', $preflight_login_snapshot_stage_label, $preflight_login_snapshot_stage_tone, $preflight_login_stage_summary, $preflight_login_stage_meta); ?>
                        <?php self::render_preflight_stage_card('Auto-Warm Availability', $preflight_auto_warm_stage_label, $preflight_auto_warm_stage_tone, $preflight_auto_warm_stage_summary, $preflight_auto_warm_stage_meta); ?>
                    </div>

                    <div class="cbt-exam-readiness-alerts">
                        <div class="cbt-exam-readiness-alert-group">
                            <strong>Blocker</strong>
                            <?php if (empty($readiness_blockers)): ?>
                                <p class="cbt-exam-snapshot-note">Tidak ada blocker utama.</p>
                            <?php else: ?>
                                <ul class="cbt-exam-readiness-alert-list">
                                    <?php foreach ($readiness_blockers as $readiness_blocker): ?>
                                        <li><?php echo esc_html($readiness_blocker); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="cbt-exam-readiness-alert-group">
                            <strong>Warning</strong>
                            <?php if (empty($readiness_warnings)): ?>
                                <p class="cbt-exam-snapshot-note">Tidak ada warning operasional.</p>
                            <?php else: ?>
                                <ul class="cbt-exam-readiness-alert-list">
                                    <?php foreach ($readiness_warnings as $readiness_warning): ?>
                                        <li><?php echo esc_html($readiness_warning); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($preflight_message !== ''): ?>
                        <p class="cbt-exam-snapshot-note"><?php echo esc_html($preflight_message); ?></p>
                    <?php endif; ?>

                    <details class="cbt-exam-auto-warm-tech">
                        <summary>Detail teknis</summary>
                        <div class="cbt-exam-auto-warm-tech-body">
                            <span><strong>Session ID:</strong> <?php echo esc_html($preflight_session_id !== '' ? $preflight_session_id : '-'); ?></span>
                            <span><strong>Question Cache:</strong> <?php echo esc_html($preflight_question_cache_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Start Cache:</strong> <?php echo esc_html($preflight_start_cache_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Availability Cache:</strong> <?php echo esc_html($preflight_availability_cache_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Profile Cache:</strong> <?php echo esc_html($preflight_profile_cache_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Submission Context Cache:</strong> <?php echo esc_html($preflight_submission_context_cache_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Login Snapshot Cache:</strong> <?php echo esc_html($preflight_login_snapshot_cache_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>REST Warm:</strong> <?php echo esc_html($preflight_rest_warm_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Start Warm:</strong> <?php echo esc_html($preflight_start_warm_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Submission Context Warm:</strong> <?php echo esc_html($preflight_submission_context_warm_ready ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Blocking One-Click:</strong> <?php echo esc_html($preflight_blocking_exam_id > 0 ? (($preflight_blocking_exam_title !== '' ? $preflight_blocking_exam_title : ('Exam #' . $preflight_blocking_exam_id)) . ' (#' . $preflight_blocking_exam_id . ')') : '-'); ?></span>
                            <span><strong>Blocking Auto-Warm:</strong> <?php echo esc_html($preflight_blocking_auto_warm_exam_id > 0 ? (($preflight_blocking_auto_warm_exam_title !== '' ? $preflight_blocking_auto_warm_exam_title : ('Exam #' . $preflight_blocking_auto_warm_exam_id)) . ' (#' . $preflight_blocking_auto_warm_exam_id . ')') : '-'); ?></span>
                            <span><strong>Global Runner Owner:</strong> <?php echo esc_html($preflight_global_runner_exam_id > 0 ? (($preflight_global_runner_exam_title !== '' ? $preflight_global_runner_exam_title : ('Exam #' . $preflight_global_runner_exam_id)) . ' (#' . $preflight_global_runner_exam_id . ')') : '-'); ?></span>
                            <span><strong>Mode Global:</strong> <?php echo esc_html($preflight_global_mode_label); ?></span>
                            <span><strong>Batch Size:</strong> <?php echo esc_html((string) $preflight_global_batch_size); ?></span>
                            <span><strong>Aktivitas Runner:</strong> <?php echo esc_html($preflight_active_global_layer_label); ?></span>
                            <span><strong>Queue Position:</strong> <?php echo esc_html($preflight_queue_position > 0 ? ($preflight_queue_position . ' / ' . max(1, $preflight_queue_total)) : '-'); ?></span>
                            <span><strong>Queued Exams:</strong> <?php echo esc_html($preflight_queued_exam_summary); ?></span>
                        </div>
                    </details>

                    <div class="cbt-exam-preflight-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_start_exam_preflight'); ?>
                            <input type="hidden" name="action" value="cbt_start_exam_preflight" />
                            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-primary" <?php echo $preflight_can_start ? '' : 'disabled="disabled"'; ?>>Jalankan One-Click Pra Ujian</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form" data-cbt-preflight-clean-form="1" data-cbt-clean-exam-title="<?php echo esc_attr($title !== '' ? $title : ('Exam #' . $exam_id)); ?>" data-cbt-clean-target-count="<?php echo esc_attr((string) $preflight_target_student_count); ?>" onsubmit="return confirm('Tindakan ini tidak menghapus jawaban, nilai, attempt, sesi login, atau snapshot siswa. Hanya membersihkan snapshot/cache persiapan yang bersifat per exam. Lanjutkan?');">
                            <?php wp_nonce_field('cbt_clean_exam_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_clean_exam_snapshots" />
                            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button">Bersihkan Semua Snapshot</button>
                        </form>
                    </div>

                    <?php if ($readiness_problem_total > 0): ?>
                        <div class="cbt-exam-readiness-problem-section">
                            <div class="cbt-exam-readiness-problem-head">
                                <strong>Siswa Bermasalah</strong>
                                <span class="cbt-exam-readiness-summary-meta"><?php echo esc_html($readiness_problem_total . ' siswa'); ?></span>
                            </div>

                            <?php if (empty($readiness_problem_students)): ?>
                                <p class="cbt-exam-snapshot-note">Tidak ada siswa bermasalah pada halaman ini.</p>
                            <?php else: ?>
                                <div class="cbt-exam-list-table-wrap">
                                    <table class="widefat striped cbt-exam-readiness-problem-table">
                                        <thead>
                                            <tr>
                                                <th>Siswa</th>
                                                <th>Kelas / Ruang</th>
                                                <th>Profil</th>
                                                <th>Availability</th>
                                                <th>Masalah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($readiness_problem_students as $problem_student): ?>
                                                <tr>
                                                    <td><?php echo esc_html((string) ($problem_student['display_name'] ?? '-')); ?></td>
                                                    <td><?php echo esc_html(trim((string) ($problem_student['kode_kelas'] ?? '-')) . ' / ' . trim((string) ($problem_student['kode_ruang'] ?? '-'))); ?></td>
                                                    <td><span class="cbt-exam-snapshot-status is-<?php echo esc_attr(sanitize_html_class((string) ($problem_student['profile_status_tone'] ?? 'warning'), 'warning')); ?>"><?php echo esc_html((string) ($problem_student['profile_status_label'] ?? 'MISS')); ?></span></td>
                                                    <td><span class="cbt-exam-snapshot-status is-<?php echo esc_attr(sanitize_html_class((string) ($problem_student['availability_status_tone'] ?? 'warning'), 'warning')); ?>"><?php echo esc_html((string) ($problem_student['availability_status_label'] ?? 'MISS')); ?></span></td>
                                                    <td><?php echo esc_html((string) ($problem_student['reason'] ?? '-')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if ($readiness_problem_total_pages > 1): ?>
                                <div class="cbt-exam-readiness-pagination" aria-label="<?php echo esc_attr('Pagination readiness exam ' . $exam_id); ?>">
                                    <?php if ($readiness_problem_page > 1): ?>
                                        <a class="button button-secondary" href="<?php echo esc_url(self::build_exam_readiness_page_url($exam_id, $readiness_problem_page - 1, $exam_list_state, $snapshot_filter_state, $preview_pages, $student_snapshot_filter_state, CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT)); ?>">Sebelumnya</a>
                                    <?php else: ?>
                                        <span class="button button-secondary disabled" aria-disabled="true">Sebelumnya</span>
                                    <?php endif; ?>
                                    <span class="cbt-exam-readiness-pagination-state">
                                        <?php echo esc_html('Halaman ' . $readiness_problem_page . ' dari ' . $readiness_problem_total_pages . ' · ' . $readiness_problem_total . ' siswa'); ?>
                                    </span>
                                    <?php if ($readiness_problem_page < $readiness_problem_total_pages): ?>
                                        <a class="button button-secondary" href="<?php echo esc_url(self::build_exam_readiness_page_url($exam_id, $readiness_problem_page + 1, $exam_list_state, $snapshot_filter_state, $preview_pages, $student_snapshot_filter_state, CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT)); ?>">Berikutnya</a>
                                    <?php else: ?>
                                        <span class="button button-secondary disabled" aria-disabled="true">Berikutnya</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="cbt-exam-auto-warm-panel">
                    <div class="cbt-exam-auto-warm-panel-head">
                        <strong>Auto-Warm Availability</strong>
                        <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($auto_warm_status_tone); ?>"><?php echo esc_html($auto_warm_status_label); ?></span>
                    </div>
                    <div class="cbt-exam-auto-warm-panel-grid">
                        <span><strong>Target:</strong> <?php echo esc_html((string) $auto_warm_target_student_count); ?> siswa</span>
                        <span><strong>Progress:</strong> <?php echo esc_html($auto_warm_prepared_count . ' / ' . $auto_warm_target_student_count); ?></span>
                        <span><strong>Mulai:</strong> <?php echo esc_html($auto_warm_started_at !== '' ? $auto_warm_started_at : '-'); ?></span>
                        <span><strong>Stop:</strong> <?php echo esc_html($auto_warm_stop_after_at !== '' ? $auto_warm_stop_after_at : '-'); ?></span>
                        <span><strong>Tick:</strong> <?php echo esc_html($auto_warm_last_tick_at !== '' ? $auto_warm_last_tick_at : '-'); ?></span>
                        <span><strong>Success / Failure:</strong> <?php echo esc_html($auto_warm_last_success . ' / ' . $auto_warm_last_failure); ?></span>
                    </div>
                    <?php if ($auto_warm_message !== ''): ?>
                        <p class="cbt-exam-snapshot-note"><?php echo esc_html($auto_warm_message); ?></p>
                    <?php endif; ?>
                    <details class="cbt-exam-auto-warm-tech">
                        <summary>Detail teknis</summary>
                        <div class="cbt-exam-auto-warm-tech-body">
                            <span><strong>Session ID:</strong> <?php echo esc_html($auto_warm_session_id !== '' ? $auto_warm_session_id : '-'); ?></span>
                            <span><strong>Status Key:</strong> <?php echo esc_html(sanitize_key((string) ($auto_warm['status'] ?? 'inactive'))); ?></span>
                            <span><strong>Exam ID:</strong> <?php echo esc_html((string) $exam_id); ?></span>
                            <span><strong>Target Kelas:</strong> <?php echo esc_html(!empty($auto_warm_target_kelas) ? implode(', ', $auto_warm_target_kelas) : '-'); ?></span>
                            <span><strong>Cursor:</strong> <?php echo esc_html((string) $auto_warm_cursor); ?></span>
                            <span><strong>Batch Size:</strong> <?php echo esc_html($auto_warm_batch_size > 0 ? (string) $auto_warm_batch_size : '-'); ?></span>
                            <span><strong>Last Skip:</strong> <?php echo esc_html((string) $auto_warm_last_skip); ?></span>
                            <span><strong>Redis Availability:</strong> <?php echo esc_html($auto_warm_redis_available ? 'Siap' : 'Tidak siap'); ?></span>
                            <span><strong>Blocking Exam:</strong> <?php echo esc_html($auto_warm_blocking_exam_id > 0 ? (($auto_warm_blocking_exam_title !== '' ? $auto_warm_blocking_exam_title : ('Exam #' . $auto_warm_blocking_exam_id)) . ' (#' . $auto_warm_blocking_exam_id . ')') : '-'); ?></span>
                        </div>
                    </details>
                    <div class="cbt-exam-snapshot-row-actions cbt-exam-auto-warm-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_start_exam_availability_auto_warm'); ?>
                            <input type="hidden" name="action" value="cbt_start_exam_availability_auto_warm" />
                            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-primary" <?php echo $auto_warm_can_start ? '' : 'disabled="disabled"'; ?>>Mulai Auto-Warm Availability</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_stop_exam_availability_auto_warm'); ?>
                            <input type="hidden" name="action" value="cbt_stop_exam_availability_auto_warm" />
                            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($readiness_problem_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button" <?php echo $auto_warm_can_stop ? '' : 'disabled="disabled"'; ?>>Hentikan Auto-Warm Availability</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    /**
     * @param array<string,mixed> $row
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int,exam_ids?:array<int,int>} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_student_snapshot_row(
        array $row,
        array $exam_list_state,
        array $snapshot_filter_state,
        array $preview_pages,
        array $student_snapshot_filter_state,
        int $exam_readiness_page = 1,
        string $mode = CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR
    ): void {
        $mode = self::normalize_snapshot_panel_tab($mode);
        $user_id = (int) ($row['user_id'] ?? 0);
        $display_name = trim((string) ($row['display_name'] ?? '')) !== '' ? (string) ($row['display_name'] ?? '') : ('Siswa #' . $user_id);
        $user_login = (string) ($row['user_login'] ?? '');
        $user_email = (string) ($row['user_email'] ?? '');
        $kode_kelas = (string) ($row['kode_kelas'] ?? '');
        $kode_ruang = (string) ($row['kode_ruang'] ?? '');
        $availability = is_array($row['availability'] ?? null) ? $row['availability'] : [];
        $profile = is_array($row['profile'] ?? null) ? $row['profile'] : [];
        $availability_status_label = (string) ($row['availability_status_label'] ?? 'MISS');
        $availability_status_tone = sanitize_html_class((string) ($row['availability_status_tone'] ?? 'warning'), 'warning');
        $profile_status_label = (string) ($row['profile_status_label'] ?? 'MISS');
        $profile_status_tone = sanitize_html_class((string) ($row['profile_status_tone'] ?? 'warning'), 'warning');
        $login = is_array($row['login'] ?? null) ? $row['login'] : [];
        $login_status_label = (string) ($row['login_status_label'] ?? 'MISS');
        $login_status_tone = sanitize_html_class((string) ($row['login_status_tone'] ?? 'warning'), 'warning');
        $availability_items = max(0, (int) ($availability['item_count'] ?? 0));
        $availability_ttl = (int) ($availability['ttl_seconds'] ?? -2);
        $availability_payload_bytes = max(0, (int) ($availability['payload_bytes'] ?? 0));
        $availability_preview_items = array_values(array_filter((array) ($availability['preview_items'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $availability_current_user = is_array($availability['current_user_preview'] ?? null) ? $availability['current_user_preview'] : null;
        $availability_storage_key = (string) ($availability['storage_key'] ?? '');
        $availability_message = (string) ($availability['snapshot_message'] ?? '');
        $availability_snapshot_exists = !empty($availability['snapshot_exists']);
        $availability_snapshot_valid = !empty($availability['snapshot_valid']);
        $availability_snapshot_source = sanitize_key((string) ($availability['snapshot_source'] ?? 'miss'));
        $profile_ttl = (int) ($profile['ttl_seconds'] ?? -2);
        $profile_payload_bytes = max(0, (int) ($profile['payload_bytes'] ?? 0));
        $profile_preview = is_array($profile['preview'] ?? null) ? $profile['preview'] : [];
        $profile_storage_key = (string) ($profile['storage_key'] ?? '');
        $profile_message = (string) ($profile['snapshot_message'] ?? '');
        $profile_snapshot_exists = !empty($profile['snapshot_exists']);
        $profile_snapshot_valid = !empty($profile['snapshot_valid']);
        $profile_photo_source = trim((string) ($profile_preview['foto'] ?? ''));
        $profile_photo_url = self::get_student_snapshot_photo_url($profile_preview);
        $login_ttl = (int) ($login['ttl_seconds'] ?? -2);
        $login_payload_bytes = max(0, (int) ($login['payload_bytes'] ?? 0));
        $login_storage_key = (string) ($login['storage_key'] ?? '');
        $login_message = (string) ($login['snapshot_message'] ?? '');
        $login_snapshot_exists = !empty($login['snapshot_exists']);
        $login_snapshot_valid = !empty($login['snapshot_valid']);
        $login_preview = is_array($login['preview'] ?? null) ? $login['preview'] : [];
        $login_generated_at = trim((string) ($login['generated_at'] ?? ''));
        $login_snapshot_source = sanitize_key((string) ($login['snapshot_source'] ?? ''));
        $login_identifiers = array_values(array_filter(array_map('strval', (array) ($login['identifiers'] ?? []))));
        $login_identifiers_visible = array_slice($login_identifiers, 0, 4);
        $login_identifiers_remaining = max(0, count($login_identifiers) - count($login_identifiers_visible));
        $login_photo_url = self::get_student_snapshot_photo_url($login_preview);
        $login_preview_role = trim((string) ($login_preview['role'] ?? ''));
        $login_preview_nisn = trim((string) ($login_preview['nisn'] ?? ''));
        $login_preview_kelas = trim((string) ($login_preview['kode_kelas'] ?? $kode_kelas));
        $login_preview_ruang = trim((string) ($login_preview['kode_ruang'] ?? $kode_ruang));
        $login_preview_agama = trim((string) ($login_preview['agama'] ?? ''));
        $login_preview_gender = trim((string) ($login_preview['jenis_kelamin'] ?? ''));
        $login_redis_host = trim((string) ($login['redis_host'] ?? ''));
        $login_redis_database = (int) ($login['redis_database'] ?? 0);
        $login_redis_error = trim((string) ($login['redis_error'] ?? ''));
        $availability_preview_items_visible = array_slice($availability_preview_items, 0, 2);
        $availability_preview_items_remaining = max(0, count($availability_preview_items) - count($availability_preview_items_visible));
        $availability_preparation_hint = self::build_availability_preparation_hint(
            $availability_status_label,
            $availability_snapshot_source
        );
        $is_profile_mode = $mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR;
        $is_login_mode = $mode === CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR;
        ?>
        <tr class="cbt-student-snapshot-row">
            <td class="cbt-student-snapshot-user-cell">
                <div class="cbt-student-snapshot-user-name"><?php echo esc_html($display_name); ?></div>
                <div class="cbt-student-snapshot-user-meta">
                    <span><strong>Login:</strong> <?php echo esc_html($user_login !== '' ? $user_login : '-'); ?></span>
                    <span><strong>Email:</strong> <?php echo esc_html($user_email !== '' ? $user_email : '-'); ?></span>
                    <span><strong>Kelas:</strong> <?php echo esc_html($kode_kelas !== '' ? $kode_kelas : '-'); ?></span>
                    <span><strong>Ruang:</strong> <?php echo esc_html($kode_ruang !== '' ? $kode_ruang : '-'); ?></span>
                    <span><strong>User ID:</strong> <?php echo esc_html((string) $user_id); ?></span>
                </div>
            </td>
            <?php if ($is_profile_mode): ?>
                <td class="cbt-student-snapshot-status-cell">
                    <div class="cbt-student-snapshot-card">
                        <div class="cbt-student-snapshot-card-head">
                            <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($profile_status_tone); ?>"><?php echo esc_html($profile_status_label); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('TTL ' . ($profile_ttl >= 0 ? $profile_ttl . 's' : 'N/A')); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('Payload ' . number_format_i18n($profile_payload_bytes) . ' bytes'); ?></span>
                        </div>
                        <div class="cbt-student-snapshot-profile-top">
                            <img
                                src="<?php echo esc_url($profile_photo_url); ?>"
                                alt="<?php echo esc_attr('Foto profil ' . $display_name); ?>"
                                class="cbt-student-snapshot-photo"
                                loading="lazy"
                                decoding="async"
                            />
                            <div class="cbt-student-snapshot-preview-list">
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted">
                                    <?php echo esc_html('Agama: ' . ((string) ($profile_preview['agama'] ?? '-') ?: '-')); ?>
                                </span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted">
                                    <?php echo esc_html('Gender: ' . ((string) ($profile_preview['jenis_kelamin'] ?? '-') ?: '-')); ?>
                                </span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted">
                                    <?php echo esc_html('NISN: ' . ((string) ($profile_preview['nisn'] ?? '-') ?: '-')); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($profile_storage_key !== ''): ?>
                            <details class="cbt-student-snapshot-tech">
                                <summary>Detail teknis</summary>
                                <div class="cbt-student-snapshot-tech-body">
                                    <span><strong>User ID:</strong> <?php echo esc_html((string) $user_id); ?></span>
                                    <span><strong>Snapshot Exists:</strong> <?php echo esc_html($profile_snapshot_exists ? 'Ya' : 'Tidak'); ?></span>
                                    <span><strong>Snapshot Valid:</strong> <?php echo esc_html($profile_snapshot_valid ? 'Ya' : 'Tidak'); ?></span>
                                    <span><strong>Storage Key:</strong></span>
                                    <code class="cbt-student-snapshot-storage-key"><?php echo esc_html($profile_storage_key); ?></code>
                                    <span><strong>Foto URL:</strong></span>
                                    <code class="cbt-student-snapshot-storage-key"><?php echo esc_html($profile_photo_source !== '' ? $profile_photo_source : 'placeholder-inline'); ?></code>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($profile_message !== ''): ?>
                            <p class="cbt-exam-snapshot-note"><?php echo esc_html($profile_message); ?></p>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="cbt-student-snapshot-actions-cell">
                    <div class="cbt-student-snapshot-row-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_warm_student_profile_snapshot'); ?>
                            <input type="hidden" name="action" value="cbt_warm_student_profile_snapshot" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field($mode); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-secondary">Siapkan Profil</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_clear_student_profile_snapshot'); ?>
                            <input type="hidden" name="action" value="cbt_clear_student_profile_snapshot" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field($mode); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button">Bersihkan Profil</button>
                        </form>
                    </div>
                </td>
            <?php elseif ($is_login_mode): ?>
                <td class="cbt-student-snapshot-status-cell">
                    <div class="cbt-student-snapshot-card">
                        <div class="cbt-student-snapshot-card-head">
                            <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($login_status_tone); ?>"><?php echo esc_html($login_status_label); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('TTL ' . ($login_ttl >= 0 ? $login_ttl . 's' : 'N/A')); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('Payload ' . number_format_i18n($login_payload_bytes) . ' bytes'); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('Source ' . ($login_snapshot_source !== '' ? $login_snapshot_source : 'miss')); ?></span>
                        </div>
                        <div class="cbt-student-snapshot-compact-copy">
                            <strong>Generated:</strong>
                            <span><?php echo esc_html($login_generated_at !== '' ? $login_generated_at : '-'); ?></span>
                        </div>
                        <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle">Snapshot ini adalah auth/login accelerator per siswa, bukan active session, JWT, atau state login yang sedang hidup.</p>
                        <div class="cbt-student-snapshot-profile-top">
                            <img
                                src="<?php echo esc_url($login_photo_url); ?>"
                                alt="<?php echo esc_attr('Foto login snapshot ' . $display_name); ?>"
                                class="cbt-student-snapshot-photo"
                                loading="lazy"
                                decoding="async"
                            />
                            <div class="cbt-student-snapshot-preview-list">
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('Role: ' . ($login_preview_role !== '' ? $login_preview_role : '-')); ?></span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('NISN: ' . ($login_preview_nisn !== '' ? $login_preview_nisn : '-')); ?></span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('Kelas: ' . ($login_preview_kelas !== '' ? $login_preview_kelas : '-')); ?></span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('Ruang: ' . ($login_preview_ruang !== '' ? $login_preview_ruang : '-')); ?></span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('Agama: ' . ($login_preview_agama !== '' ? $login_preview_agama : '-')); ?></span>
                                <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('Gender: ' . ($login_preview_gender !== '' ? $login_preview_gender : '-')); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($login_identifiers_visible)): ?>
                            <div class="cbt-student-snapshot-preview-list">
                                <?php foreach ($login_identifiers_visible as $identifier): ?>
                                    <span class="cbt-student-snapshot-preview-pill"><?php echo esc_html($identifier); ?></span>
                                <?php endforeach; ?>
                                <?php if ($login_identifiers_remaining > 0): ?>
                                    <span class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted"><?php echo esc_html('+' . $login_identifiers_remaining . ' identifier'); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($login_storage_key !== '' || $login_generated_at !== '' || !empty($login_identifiers) || $login_redis_host !== '' || $login_redis_error !== ''): ?>
                            <details class="cbt-student-snapshot-tech">
                                <summary>Detail teknis</summary>
                                <div class="cbt-student-snapshot-tech-body">
                                    <span><strong>User ID:</strong> <?php echo esc_html((string) $user_id); ?></span>
                                    <span><strong>Snapshot Source:</strong> <?php echo esc_html($login_snapshot_source !== '' ? $login_snapshot_source : 'miss'); ?></span>
                                    <span><strong>Generated At:</strong> <?php echo esc_html($login_generated_at !== '' ? $login_generated_at : '-'); ?></span>
                                    <span><strong>Snapshot Exists:</strong> <?php echo esc_html($login_snapshot_exists ? 'Ya' : 'Tidak'); ?></span>
                                    <span><strong>Snapshot Valid:</strong> <?php echo esc_html($login_snapshot_valid ? 'Ya' : 'Tidak'); ?></span>
                                    <span><strong>Host Redis:</strong> <code><?php echo esc_html($login_redis_host !== '' ? $login_redis_host : '-'); ?></code></span>
                                    <span><strong>Database Redis:</strong> <?php echo esc_html((string) $login_redis_database); ?></span>
                                    <span><strong>Identifiers:</strong></span>
                                    <code class="cbt-student-snapshot-storage-key"><?php echo esc_html(!empty($login_identifiers) ? implode(', ', $login_identifiers) : '-'); ?></code>
                                    <span><strong>Storage Key:</strong></span>
                                    <code class="cbt-student-snapshot-storage-key"><?php echo esc_html($login_storage_key !== '' ? $login_storage_key : '-'); ?></code>
                                    <?php if ($login_redis_error !== ''): ?>
                                        <span><strong>Error Redis:</strong></span>
                                        <code class="cbt-student-snapshot-storage-key"><?php echo esc_html($login_redis_error); ?></code>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($login_message !== ''): ?>
                            <p class="cbt-exam-snapshot-note"><?php echo esc_html($login_message); ?></p>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="cbt-student-snapshot-actions-cell">
                    <div class="cbt-student-snapshot-row-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_warm_student_login_snapshot'); ?>
                            <input type="hidden" name="action" value="cbt_warm_student_login_snapshot" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field($mode); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-secondary">Siapkan Login Snapshot</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_clear_student_login_snapshot'); ?>
                            <input type="hidden" name="action" value="cbt_clear_student_login_snapshot" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field($mode); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button">Bersihkan Login Snapshot</button>
                        </form>
                    </div>
                </td>
            <?php else: ?>
                <td class="cbt-student-snapshot-status-cell">
                    <div class="cbt-student-snapshot-card">
                        <div class="cbt-student-snapshot-card-head">
                            <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($availability_status_tone); ?>"><?php echo esc_html($availability_status_label); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('Items ' . $availability_items); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('TTL ' . ($availability_ttl >= 0 ? $availability_ttl . 's' : 'N/A')); ?></span>
                            <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('Payload ' . number_format_i18n($availability_payload_bytes) . ' bytes'); ?></span>
                        </div>
                        <?php if ($availability_current_user): ?>
                            <div class="cbt-student-snapshot-compact-copy">
                                <strong>Current User:</strong>
                                <span><?php echo esc_html((string) ($availability_current_user['display_name'] ?? ($availability_current_user['username'] ?? '-'))); ?></span>
                            </div>
                        <?php endif; ?>
                        <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle">Snapshot ini memuat katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.</p>
                        <?php if (!empty($availability_preview_items)): ?>
                            <div class="cbt-student-snapshot-preview-list">
                                <?php foreach ($availability_preview_items_visible as $preview_item): ?>
                                    <span class="cbt-student-snapshot-preview-pill">
                                        <?php echo esc_html((string) ($preview_item['title'] ?? ('Exam #' . (int) ($preview_item['id'] ?? 0)))); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($availability_preview_items_remaining > 0): ?>
                                <details class="cbt-student-snapshot-preview-expand">
                                    <summary class="cbt-student-snapshot-preview-pill cbt-student-snapshot-preview-pill--muted">
                                        <?php echo esc_html('+' . $availability_preview_items_remaining . ' lainnya'); ?>
                                    </summary>
                                    <div class="cbt-student-snapshot-preview-expand-body">
                                        <div class="cbt-student-snapshot-preview-expand-label">
                                            <?php echo esc_html('Semua exam di snapshot (' . $availability_items . ')'); ?>
                                        </div>
                                        <div class="cbt-student-snapshot-preview-list cbt-student-snapshot-preview-list--expanded">
                                            <?php foreach ($availability_preview_items as $preview_item): ?>
                                                <span class="cbt-student-snapshot-preview-pill">
                                                    <?php echo esc_html((string) ($preview_item['title'] ?? ('Exam #' . (int) ($preview_item['id'] ?? 0)))); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </details>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($availability_storage_key !== ''): ?>
                            <details class="cbt-student-snapshot-tech">
                                <summary>Detail teknis</summary>
                                <div class="cbt-student-snapshot-tech-body">
                                    <span><strong>User ID:</strong> <?php echo esc_html((string) $user_id); ?></span>
                                    <span><strong>Snapshot Source:</strong> <?php echo esc_html($availability_snapshot_source !== '' ? $availability_snapshot_source : 'miss'); ?></span>
                                    <span><strong>Snapshot Exists:</strong> <?php echo esc_html($availability_snapshot_exists ? 'Ya' : 'Tidak'); ?></span>
                                    <span><strong>Snapshot Valid:</strong> <?php echo esc_html($availability_snapshot_valid ? 'Ya' : 'Tidak'); ?></span>
                                    <span><strong>Storage Key:</strong></span>
                                    <code class="cbt-student-snapshot-storage-key"><?php echo esc_html($availability_storage_key); ?></code>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($availability_message !== ''): ?>
                            <p class="cbt-exam-snapshot-note"><?php echo esc_html($availability_message); ?></p>
                        <?php endif; ?>
                        <?php if ($availability_preparation_hint !== ''): ?>
                            <p class="cbt-exam-snapshot-note cbt-exam-snapshot-note--subtle"><?php echo esc_html($availability_preparation_hint); ?></p>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="cbt-student-snapshot-actions-cell">
                    <div class="cbt-student-snapshot-row-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_warm_student_exam_availability_snapshot'); ?>
                            <input type="hidden" name="action" value="cbt_warm_student_exam_availability_snapshot" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field($mode); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-secondary">Siapkan Snapshot Exam</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                            <?php wp_nonce_field('cbt_clear_student_exam_availability_snapshot'); ?>
                            <input type="hidden" name="action" value="cbt_clear_student_exam_availability_snapshot" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                            <?php self::render_snapshot_tab_hidden_field($mode); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                            <?php self::render_exam_readiness_page_hidden_field($exam_readiness_page); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button">Bersihkan Snapshot Exam</button>
                        </form>
                    </div>
                </td>
            <?php endif; ?>
        </tr>
        <?php
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     */
    private static function render_exam_list_state_hidden_fields(array $exam_list_state): void
    {
        ?>
        <input type="hidden" name="cbt_exam_per_page" value="<?php echo esc_attr((string) ((int) ($exam_list_state['per_page'] ?? 20))); ?>" />
        <input type="hidden" name="cbt_exam_paged" value="<?php echo esc_attr((string) ((int) ($exam_list_state['paged'] ?? 1))); ?>" />
        <input type="hidden" name="cbt_exam_search" value="<?php echo esc_attr((string) ($exam_list_state['search'] ?? '')); ?>" />
        <input type="hidden" name="cbt_exam_status" value="<?php echo esc_attr((string) ($exam_list_state['status'] ?? '')); ?>" />
        <input type="hidden" name="cbt_exam_subject" value="<?php echo esc_attr((string) ((int) ($exam_list_state['subject_id'] ?? 0))); ?>" />
        <input type="hidden" name="cbt_exam_kelas" value="<?php echo esc_attr((string) ($exam_list_state['kelas'] ?? '')); ?>" />
        <?php
    }

    /**
     * @param array<int,int> $preview_pages
     */
    private static function render_snapshot_preview_page_hidden_fields(array $preview_pages): void
    {
        foreach ($preview_pages as $exam_id => $page) {
            $exam_id = absint($exam_id);
            if ($exam_id <= 0) {
                continue;
            }
            ?>
            <input type="hidden" name="<?php echo esc_attr('cbt_exam_snapshot_page_' . $exam_id); ?>" value="<?php echo esc_attr((string) max(1, (int) $page)); ?>" />
            <?php
        }
    }

    private static function render_exam_readiness_page_hidden_field(int $page): void
    {
        foreach (self::$snapshot_exam_readiness_pages as $exam_id => $readiness_page) {
            $exam_id = absint($exam_id);
            $readiness_page = max(1, (int) $readiness_page);
            if ($exam_id <= 0 || $readiness_page <= 1) {
                continue;
            }
            ?>
            <input type="hidden" name="<?php echo esc_attr('cbt_exam_readiness_page_' . $exam_id); ?>" value="<?php echo esc_attr((string) $readiness_page); ?>" />
            <?php
        }

        $page = max(1, $page);
        if ($page <= 1) {
            return;
        }
        ?>
        <input type="hidden" name="cbt_exam_readiness_paged" value="<?php echo esc_attr((string) $page); ?>" />
        <?php
    }

    /**
     * @param array{exam_id:int,exam_ids?:array<int,int>} $snapshot_filter_state
     */
    private static function render_snapshot_filter_state_hidden_fields(array $snapshot_filter_state): void
    {
        $exam_ids = array_values(array_filter(array_map('intval', (array) ($snapshot_filter_state['exam_ids'] ?? []))));
        if (empty($exam_ids) && !empty($snapshot_filter_state['exam_id'])) {
            $exam_ids[] = (int) $snapshot_filter_state['exam_id'];
        }

        foreach ($exam_ids as $exam_id) {
            ?>
            <input type="hidden" name="cbt_exam_snapshot_exam_ids[]" value="<?php echo esc_attr((string) $exam_id); ?>" />
            <?php
        }

        if (count($exam_ids) === 1) {
            ?>
            <input type="hidden" name="cbt_exam_snapshot_exam_id" value="<?php echo esc_attr((string) $exam_ids[0]); ?>" />
            <?php
        }
    }

    private static function render_snapshot_tab_hidden_field(string $tab): void
    {
        $tab = self::normalize_snapshot_panel_tab($tab);

        if ($tab === CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT) {
            return;
        }
        ?>
        <input type="hidden" name="cbt_exam_snapshot_tab" value="<?php echo esc_attr($tab); ?>" />
        <?php
    }

    private static function build_availability_preparation_hint(string $status_label, string $snapshot_source): string
    {
        $status = strtoupper(trim($status_label));
        $source = sanitize_key($snapshot_source);

        if ($status === 'AUTO-WARM') {
            return 'Persiapan memakai Auto-Warm Availability dari tab One-Click Pra Ujian untuk exam yang dipilih.';
        }

        if ($source === 'prepared') {
            return 'Prepared snapshot masih tersimpan dari auto-warm sebelumnya. Saat ini loop auto-warm tidak aktif, tetapi snapshot-nya masih bisa dipakai sampai dibersihkan atau invalidasi.';
        }

        if ($source === 'minute' || $status === 'READY') {
            return 'Snapshot ini berasal dari request siswa. Untuk persiapan pra-ujian yang lebih stabil, gunakan Auto-Warm Availability di tab One-Click Pra Ujian.';
        }

        return 'Untuk persiapan pra-ujian, gunakan Auto-Warm Availability di tab One-Click Pra Ujian. Jika dibiarkan, request siswa berikutnya akan hydrate otomatis.';
    }

    /**
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_student_snapshot_state_hidden_fields(array $student_snapshot_filter_state): void
    {
        if (($student_snapshot_filter_state['search'] ?? '') !== '') {
            ?>
            <input type="hidden" name="cbt_student_snapshot_q" value="<?php echo esc_attr((string) $student_snapshot_filter_state['search']); ?>" />
            <?php
        }
        if (($student_snapshot_filter_state['kelas'] ?? '') !== '') {
            ?>
            <input type="hidden" name="cbt_student_snapshot_kelas" value="<?php echo esc_attr((string) $student_snapshot_filter_state['kelas']); ?>" />
            <?php
        }
        if (($student_snapshot_filter_state['ruang'] ?? '') !== '') {
            ?>
            <input type="hidden" name="cbt_student_snapshot_ruang" value="<?php echo esc_attr((string) $student_snapshot_filter_state['ruang']); ?>" />
            <?php
        }
        if (!empty($student_snapshot_filter_state['paged'])) {
            ?>
            <input type="hidden" name="cbt_student_snapshot_paged" value="<?php echo esc_attr((string) max(1, (int) $student_snapshot_filter_state['paged'])); ?>" />
            <?php
        }
    }

    /**
     * @param array{foto?:string} $profile_preview
     */
    private static function get_student_snapshot_photo_url(array $profile_preview): string
    {
        $foto = trim((string) ($profile_preview['foto'] ?? ''));
        if ($foto !== '') {
            return $foto;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64" fill="none">'
            . '<rect width="64" height="64" rx="32" fill="#EEF5FF"/>'
            . '<circle cx="32" cy="24" r="12" fill="#93C5FD"/>'
            . '<path d="M14 54C18 43.5 25.5 38 32 38C38.5 38 46 43.5 50 54" fill="#BFDBFE"/>'
            . '</svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_snapshot_preview_page_url(
        int $exam_id,
        int $page,
        array $exam_list_state,
        array $snapshot_filter_state,
        array $preview_pages,
        array $student_snapshot_filter_state = ['search' => '', 'kelas' => '', 'ruang' => '', 'paged' => 1, 'per_page' => 25],
        int $readiness_page = 1,
        string $tab = CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR
    ): string
    {
        $args = CBT_Admin_Exams_Service::add_exam_readiness_page_args(
            CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
                CBT_Admin_Exams_Service::add_exam_snapshot_tab_args(
                    CBT_Admin_Exams_Service::add_exam_snapshot_filter_state_args(
                        CBT_Admin_Exams_Service::add_exam_list_state_args(
                            [
                                'page' => 'cbt-exams',
                                'cbt_exam_panel' => 'snapshot',
                            ],
                            $exam_list_state
                        ),
                            $snapshot_filter_state
                        ),
                    $tab
                ),
                $student_snapshot_filter_state
            ),
            self::$snapshot_exam_readiness_pages,
            $readiness_page
        );

        foreach ($preview_pages as $target_exam_id => $target_page) {
            $target_exam_id = absint($target_exam_id);
            if ($target_exam_id <= 0) {
                continue;
            }

            $args['cbt_exam_snapshot_page_' . $target_exam_id] = max(1, (int) $target_page);
        }

        if ($exam_id > 0) {
            $args['cbt_exam_snapshot_page_' . $exam_id] = max(1, $page);
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_student_snapshot_page_url(
        int $page,
        array $exam_list_state,
        array $snapshot_filter_state,
        array $preview_pages,
        array $student_snapshot_filter_state,
        int $readiness_page = 1,
        string $tab = CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR
    ): string
    {
        $state = $student_snapshot_filter_state;
        $state['paged'] = max(1, $page);

        $args = CBT_Admin_Exams_Service::add_exam_readiness_page_args(
            CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
                CBT_Admin_Exams_Service::add_exam_snapshot_tab_args(
                    CBT_Admin_Exams_Service::add_exam_snapshot_preview_page_args(
                        CBT_Admin_Exams_Service::add_exam_snapshot_filter_state_args(
                            CBT_Admin_Exams_Service::add_exam_list_state_args(
                                [
                                    'page' => 'cbt-exams',
                                    'cbt_exam_panel' => 'snapshot',
                                ],
                                $exam_list_state
                            ),
                            $snapshot_filter_state
                        ),
                        $preview_pages
                    ),
                    $tab
                ),
                $state
            ),
            self::$snapshot_exam_readiness_pages,
            $readiness_page
        );

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_snapshot_tab_url(string $tab, array $exam_list_state, array $snapshot_filter_state, array $preview_pages, array $student_snapshot_filter_state, int $readiness_page = 1): string
    {
        $args = CBT_Admin_Exams_Service::add_exam_readiness_page_args(
            CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
                CBT_Admin_Exams_Service::add_exam_snapshot_tab_args(
                    CBT_Admin_Exams_Service::add_exam_snapshot_preview_page_args(
                        CBT_Admin_Exams_Service::add_exam_snapshot_filter_state_args(
                            CBT_Admin_Exams_Service::add_exam_list_state_args(
                                [
                                    'page' => 'cbt-exams',
                                    'cbt_exam_panel' => 'snapshot',
                                ],
                                $exam_list_state
                            ),
                            $snapshot_filter_state
                        ),
                        $preview_pages
                    ),
                    $tab
                ),
                $student_snapshot_filter_state
            ),
            self::$snapshot_exam_readiness_pages,
            $readiness_page
        );

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,kelas:string,ruang:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_exam_readiness_page_url(
        int $exam_id,
        int $page,
        array $exam_list_state,
        array $snapshot_filter_state,
        array $preview_pages,
        array $student_snapshot_filter_state,
        string $tab = CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT
    ): string
    {
        $args = CBT_Admin_Exams_Service::add_exam_readiness_page_args(
            CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
                CBT_Admin_Exams_Service::add_exam_snapshot_tab_args(
                    CBT_Admin_Exams_Service::add_exam_snapshot_preview_page_args(
                        CBT_Admin_Exams_Service::add_exam_snapshot_filter_state_args(
                            CBT_Admin_Exams_Service::add_exam_list_state_args(
                                [
                                    'page' => 'cbt-exams',
                                    'cbt_exam_panel' => 'snapshot',
                                ],
                                $exam_list_state
                            ),
                            $snapshot_filter_state
                        ),
                        $preview_pages
                    ),
                    $tab
                ),
                $student_snapshot_filter_state
            ),
            self::$snapshot_exam_readiness_pages,
            $page
        );

        $exam_id = absint($exam_id);
        $page = max(1, $page);
        if ($exam_id > 0) {
            if ($page > 1) {
                $args['cbt_exam_readiness_page_' . $exam_id] = $page;
            } else {
                unset($args['cbt_exam_readiness_page_' . $exam_id]);
            }
        }

        return add_query_arg($args, admin_url('admin.php'));
    }
}
