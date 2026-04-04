<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exams_Page
{
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

    /**
     * @param array{
     *   subjects:array<int,array<string,mixed>>,
     *   exam_status_labels:array<string,string>,
     *   exam_list_state:array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string},
     *   exam_list_kelas_options:array<int,string>,
     *   exam_per_page:int,
     *   exam_active_filters:array<int,array{label:string,value:string}>,
     *   exam_snapshot_tab:string,
     *   exam_snapshot_filter_state:array{exam_id:int},
     *   exam_snapshot_exam_options:array<int,array{id:int,title:string}>,
     *   exam_snapshot_total:int,
     *   exam_snapshot_rows:array<int,array<string,mixed>>,
     *   exam_snapshot_preview_pages:array<int,int>,
     *   exam_snapshot_reset_url:string,
     *   student_snapshot_filter_state:array{search:string,paged:int,per_page:int},
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
        $exam_snapshot_tab = (string) ($args['exam_snapshot_tab'] ?? CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS);
        $exam_snapshot_filter_state = isset($args['exam_snapshot_filter_state']) && is_array($args['exam_snapshot_filter_state'])
            ? $args['exam_snapshot_filter_state']
            : ['exam_id' => 0];
        $exam_snapshot_exam_options = isset($args['exam_snapshot_exam_options']) && is_array($args['exam_snapshot_exam_options'])
            ? $args['exam_snapshot_exam_options']
            : [];
        $exam_snapshot_total = max(0, (int) ($args['exam_snapshot_total'] ?? 0));
        $exam_snapshot_rows = isset($args['exam_snapshot_rows']) && is_array($args['exam_snapshot_rows']) ? $args['exam_snapshot_rows'] : [];
        $exam_snapshot_preview_pages = isset($args['exam_snapshot_preview_pages']) && is_array($args['exam_snapshot_preview_pages'])
            ? $args['exam_snapshot_preview_pages']
            : [];
        $exam_snapshot_reset_url = (string) ($args['exam_snapshot_reset_url'] ?? admin_url('admin.php?page=cbt-exams&cbt_exam_panel=snapshot'));
        $student_snapshot_filter_state = isset($args['student_snapshot_filter_state']) && is_array($args['student_snapshot_filter_state'])
            ? $args['student_snapshot_filter_state']
            : ['search' => '', 'paged' => 1, 'per_page' => 25];
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
        $has_selected_exam_snapshot = !empty($exam_snapshot_filter_state['exam_id']);
        $snapshot_questions_tab_url = self::build_snapshot_tab_url(
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS,
            $exam_list_state,
            $exam_snapshot_filter_state,
            $exam_snapshot_preview_pages,
            $student_snapshot_filter_state
        );
        $snapshot_students_tab_url = self::build_snapshot_tab_url(
            CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS,
            $exam_list_state,
            $exam_snapshot_filter_state,
            $exam_snapshot_preview_pages,
            $student_snapshot_filter_state
        );
        $is_questions_tab_active = $exam_snapshot_tab !== CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS;
        $is_students_tab_active = !$is_questions_tab_active;
        ?>
        <div class="cbt-exam-snapshot-shell">
            <div class="cbt-exam-snapshot-subtabs" role="tablist" aria-label="Subtab snapshot">
                <a
                    href="<?php echo esc_url($snapshot_questions_tab_url); ?>"
                    class="cbt-exam-snapshot-subtab<?php echo $is_questions_tab_active ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $is_questions_tab_active ? 'true' : 'false'; ?>"
                >Snapshot Soal</a>
                <a
                    href="<?php echo esc_url($snapshot_students_tab_url); ?>"
                    class="cbt-exam-snapshot-subtab<?php echo $is_students_tab_active ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $is_students_tab_active ? 'true' : 'false'; ?>"
                >Snapshot Siswa</a>
            </div>

            <section class="cbt-exam-snapshot-section<?php echo $is_questions_tab_active ? ' is-active' : ''; ?>"<?php echo $is_questions_tab_active ? '' : ' hidden="hidden"'; ?>>
                <div class="cbt-exam-snapshot-section-head">
                    <div>
                        <h3>Snapshot Soal</h3>
                        <p class="description cbt-exam-list-description">Pantau kesiapan snapshot Redis untuk delivery payload siswa, lalu siapkan satu exam atau seluruh hasil filter sebelum ujian dimulai.</p>
                    </div>
                </div>

                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-exam-list-toolbar" id="cbt-exam-snapshot-filter-form">
                    <input type="hidden" name="page" value="cbt-exams" />
                    <input type="hidden" name="cbt_exam_panel" value="snapshot" />
                    <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS); ?>
                    <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                    <div class="cbt-exam-list-toolbar-grid">
                        <div class="cbt-exam-list-toolbar-field cbt-exam-list-toolbar-field--search">
                            <label for="cbt-exam-snapshot-exam">Exam</label>
                            <select id="cbt-exam-snapshot-exam" name="cbt_exam_snapshot_exam_id">
                                <option value="0">Pilih exam dulu</option>
                                <?php foreach ($exam_snapshot_exam_options as $exam_snapshot_exam_option): ?>
                                    <?php $snapshot_exam_option_id = (int) ($exam_snapshot_exam_option['id'] ?? 0); ?>
                                    <option value="<?php echo (int) $snapshot_exam_option_id; ?>" <?php echo ((int) ($exam_snapshot_filter_state['exam_id'] ?? 0) === $snapshot_exam_option_id) ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html((string) ($exam_snapshot_exam_option['title'] ?? ('Exam #' . $snapshot_exam_option_id))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-snapshot-subject">Mapel</label>
                            <select id="cbt-exam-snapshot-subject" name="cbt_exam_subject">
                                <option value="0">Semua mapel</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <?php
                                    $subject_id = (int) ($subject['id'] ?? 0);
                                    $subject_name = (string) ($subject['name'] ?? '');
                                    $subject_code = trim((string) ($subject['code'] ?? ''));
                                    $subject_label = $subject_name !== ''
                                        ? $subject_name . ($subject_code !== '' ? ' (' . $subject_code . ')' : '')
                                        : 'Mapel';
                                    ?>
                                    <option value="<?php echo (int) $subject_id; ?>" <?php echo ((int) ($exam_list_state['subject_id'] ?? 0) === $subject_id) ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html($subject_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-snapshot-status">Status</label>
                            <select id="cbt-exam-snapshot-status" name="cbt_exam_status">
                                <option value="">Semua status</option>
                                <?php foreach ($exam_status_labels as $status_key => $status_label): ?>
                                    <option value="<?php echo esc_attr((string) $status_key); ?>" <?php echo ((string) ($exam_list_state['status'] ?? '') === (string) $status_key) ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html((string) $status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-snapshot-kelas">Kelas</label>
                            <select id="cbt-exam-snapshot-kelas" name="cbt_exam_kelas">
                                <option value="">Semua kelas</option>
                                <?php foreach ($exam_list_kelas_options as $kelas_option): ?>
                                    <option value="<?php echo esc_attr((string) $kelas_option); ?>" <?php echo ((string) ($exam_list_state['kelas'] ?? '') === (string) $kelas_option) ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html((string) $kelas_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-snapshot-per-page">Per halaman</label>
                            <select id="cbt-exam-snapshot-per-page" name="cbt_exam_per_page">
                                <?php foreach ([20, 40, 60, 80, 100] as $per_page_option): ?>
                                    <option value="<?php echo (int) $per_page_option; ?>" <?php echo ((int) $exam_per_page === (int) $per_page_option) ? 'selected="selected"' : ''; ?>>
                                        <?php echo esc_html((string) $per_page_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-actions">
                            <a href="<?php echo esc_url($exam_snapshot_reset_url); ?>" class="button cbt-exam-list-reset">Reset</a>
                        </div>
                    </div>
                </form>

                <div class="cbt-exam-snapshot-actions-bar">
                    <div class="cbt-exam-snapshot-actions-copy">
                        <strong><?php echo esc_html($has_selected_exam_snapshot ? sprintf('%d exam terfilter', $exam_snapshot_total) : 'Pilih satu exam'); ?></strong>
                        <span><?php echo esc_html($has_selected_exam_snapshot ? 'Bulk warm hanya memproses exam yang cocok dengan filter aktif saat ini.' : 'Panel snapshot soal baru memuat detail setelah Anda memilih satu exam dari dropdown.'); ?></span>
                    </div>
                    <div class="cbt-exam-snapshot-bulk-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                            <?php wp_nonce_field('cbt_warm_bulk_exam_delivery_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_warm_bulk_exam_delivery_snapshots" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-primary" <?php echo empty($exam_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Snapshot</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                            <?php wp_nonce_field('cbt_clear_bulk_exam_delivery_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_clear_bulk_exam_delivery_snapshots" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button" <?php echo empty($exam_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Snapshot</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($exam_active_filters)): ?>
                    <div class="cbt-exam-list-active-filters" aria-label="Filter snapshot aktif">
                        <span class="cbt-exam-list-active-summary"><?php echo esc_html(sprintf('%d exam cocok', $exam_snapshot_total)); ?></span>
                        <?php foreach ($exam_active_filters as $active_filter): ?>
                            <span class="cbt-exam-list-active-chip">
                                <strong><?php echo esc_html((string) ($active_filter['label'] ?? 'Filter')); ?></strong>
                                <span><?php echo esc_html((string) ($active_filter['value'] ?? '')); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="cbt-exam-list-table-wrap">
                    <table class="widefat striped cbt-exam-snapshot-table">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Ringkasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exam_snapshot_rows)): ?>
                                <tr>
                                    <td colspan="3"><?php echo $has_selected_exam_snapshot ? (!empty($exam_active_filters) ? 'Tidak ada exam yang cocok dengan filter saat ini.' : 'Belum ada exam yang bisa diperiksa snapshot-nya.') : 'Pilih satu exam pada dropdown di atas untuk memeriksa snapshot soal.'; ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exam_snapshot_rows as $row): ?>
                                    <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="cbt-exam-snapshot-section cbt-exam-snapshot-section--students<?php echo $is_students_tab_active ? ' is-active' : ''; ?>"<?php echo $is_students_tab_active ? '' : ' hidden="hidden"'; ?>>
                <div class="cbt-exam-snapshot-section-head">
                    <div>
                        <h3>Snapshot Siswa</h3>
                        <p class="description cbt-exam-list-description">Pantau snapshot ketersediaan exam dan profil siswa dalam satu tabel operasional, lalu siapkan atau bersihkan per siswa maupun bulk.</p>
                    </div>
                </div>

                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-exam-snapshot-student-toolbar" id="cbt-student-snapshot-filter-form">
                    <input type="hidden" name="page" value="cbt-exams" />
                    <input type="hidden" name="cbt_exam_panel" value="snapshot" />
                    <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                    <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                    <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                    <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                    <div class="cbt-exam-snapshot-student-toolbar-grid">
                        <div class="cbt-exam-snapshot-student-search">
                            <label for="cbt-student-snapshot-search">Cari Siswa</label>
                            <input type="search" id="cbt-student-snapshot-search" name="cbt_student_snapshot_q" value="<?php echo esc_attr((string) ($student_snapshot_filter_state['search'] ?? '')); ?>" placeholder="Nama, username, email, kelas, ruang" />
                        </div>
                        <div class="cbt-exam-snapshot-student-toolbar-actions">
                            <button type="submit" class="button button-secondary">Cari</button>
                            <a href="<?php echo esc_url($student_snapshot_reset_url); ?>" class="button">Reset</a>
                        </div>
                    </div>
                </form>

                <div class="cbt-exam-snapshot-actions-bar cbt-exam-snapshot-actions-bar--students">
                    <div class="cbt-exam-snapshot-actions-copy">
                        <strong><?php echo esc_html(sprintf('%d siswa terfilter', $student_snapshot_total)); ?></strong>
                        <span>Bulk action memproses semua siswa yang cocok dengan filter aktif saat ini.</span>
                    </div>
                    <div class="cbt-exam-snapshot-bulk-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                            <?php wp_nonce_field('cbt_warm_bulk_student_exam_availability_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_warm_bulk_student_exam_availability_snapshots" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-primary" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Availability</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                            <?php wp_nonce_field('cbt_clear_bulk_student_exam_availability_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_clear_bulk_student_exam_availability_snapshots" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Availability</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                            <?php wp_nonce_field('cbt_warm_bulk_student_profile_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_warm_bulk_student_profile_snapshots" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button button-primary" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Profil</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                            <?php wp_nonce_field('cbt_clear_bulk_student_profile_snapshots'); ?>
                            <input type="hidden" name="action" value="cbt_clear_bulk_student_profile_snapshots" />
                            <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                            <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                            <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                            <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                            <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                            <button type="submit" class="button" <?php echo empty($student_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Profil</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($student_snapshot_active_filters)): ?>
                    <div class="cbt-exam-list-active-filters" aria-label="Filter snapshot siswa aktif">
                        <span class="cbt-exam-list-active-summary"><?php echo esc_html(sprintf('%d siswa cocok', $student_snapshot_total)); ?></span>
                        <?php foreach ($student_snapshot_active_filters as $active_filter): ?>
                            <span class="cbt-exam-list-active-chip">
                                <strong><?php echo esc_html((string) ($active_filter['label'] ?? 'Filter')); ?></strong>
                                <span><?php echo esc_html((string) ($active_filter['value'] ?? '')); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="cbt-exam-list-table-wrap">
                    <table class="widefat striped cbt-student-snapshot-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Snapshot Ketersediaan</th>
                                <th>Snapshot Profil</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($student_snapshot_rows)): ?>
                                <tr>
                                    <td colspan="4"><?php echo !empty($student_snapshot_active_filters) ? 'Tidak ada siswa yang cocok dengan filter saat ini.' : 'Belum ada siswa yang bisa dipantau snapshot-nya.'; ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($student_snapshot_rows as $student_snapshot_row): ?>
                                    <?php self::render_student_snapshot_row($student_snapshot_row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($student_snapshot_total_pages > 1): ?>
                    <div class="cbt-exam-snapshot-student-pagination" aria-label="Pagination snapshot siswa">
                        <?php if ($student_snapshot_current_page > 1): ?>
                            <a class="button button-secondary" href="<?php echo esc_url(self::build_student_snapshot_page_url($student_snapshot_current_page - 1, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state)); ?>">Sebelumnya</a>
                        <?php else: ?>
                            <span class="button button-secondary disabled" aria-disabled="true">Sebelumnya</span>
                        <?php endif; ?>
                        <span class="cbt-exam-snapshot-student-pagination-state">
                            <?php echo esc_html(sprintf('Halaman %d dari %d · %d siswa', $student_snapshot_current_page, $student_snapshot_total_pages, $student_snapshot_total)); ?>
                        </span>
                        <?php if ($student_snapshot_current_page < $student_snapshot_total_pages): ?>
                            <a class="button button-secondary" href="<?php echo esc_url(self::build_student_snapshot_page_url($student_snapshot_current_page + 1, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages, $student_snapshot_filter_state)); ?>">Berikutnya</a>
                        <?php else: ?>
                            <span class="button button-secondary disabled" aria-disabled="true">Berikutnya</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $row
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_snapshot_row(array $row, array $exam_list_state, array $snapshot_filter_state = ['exam_id' => 0], array $preview_pages = [], array $student_snapshot_filter_state = ['search' => '', 'paged' => 1, 'per_page' => 25]): void
    {
        $exam_id = (int) ($row['exam_id'] ?? $row['id'] ?? 0);
        $title = trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : ('Exam #' . $exam_id);
        $subject_name = trim((string) ($row['subject_name'] ?? ''));
        $exam_status = trim((string) ($row['status'] ?? ''));
        $snapshot_status_label = trim((string) ($row['snapshot_status_label'] ?? 'MISS')) !== ''
            ? (string) $row['snapshot_status_label']
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
        ?>
        <tr class="cbt-exam-snapshot-summary-row" data-cbt-exam-snapshot-summary-row="<?php echo esc_attr((string) $exam_id); ?>">
            <td class="cbt-exam-snapshot-exam-cell">
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
            </td>
            <td class="cbt-exam-snapshot-summary-cell">
                <div class="cbt-exam-snapshot-summary-grid">
                    <div class="cbt-exam-snapshot-summary-card cbt-exam-snapshot-summary-card--status">
                        <span class="cbt-exam-snapshot-summary-label">Status</span>
                        <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($snapshot_status_tone); ?>"><?php echo esc_html($snapshot_status_label); ?></span>
                    </div>
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
            </td>
            <td>
                <div class="cbt-exam-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Siapkan Snapshot Soal</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button">Bersihkan Snapshot Soal</button>
                    </form>
                </div>
            </td>
        </tr>
        <tr class="cbt-exam-snapshot-preview-row" data-cbt-exam-snapshot-preview-row="<?php echo esc_attr((string) $exam_id); ?>">
            <td colspan="3" class="cbt-exam-snapshot-preview-row-cell">
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
                                    <a class="button button-secondary" href="<?php echo esc_url(self::build_snapshot_preview_page_url($exam_id, $preview_current_page - 1, $exam_list_state, $snapshot_filter_state, $preview_pages, $student_snapshot_filter_state)); ?>">Sebelumnya</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" aria-disabled="true">Sebelumnya</span>
                                <?php endif; ?>

                                <span class="cbt-exam-snapshot-preview-pagination-state">
                                    <?php echo esc_html('Halaman ' . $preview_current_page . ' dari ' . $preview_total_pages); ?>
                                </span>

                                <?php if ($preview_current_page < $preview_total_pages): ?>
                                    <a class="button button-secondary" href="<?php echo esc_url(self::build_snapshot_preview_page_url($exam_id, $preview_current_page + 1, $exam_list_state, $snapshot_filter_state, $preview_pages, $student_snapshot_filter_state)); ?>">Berikutnya</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" aria-disabled="true">Berikutnya</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            </td>
        </tr>
        <?php
    }

    /**
     * @param array<string,mixed> $row
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_student_snapshot_row(array $row, array $exam_list_state, array $snapshot_filter_state, array $preview_pages, array $student_snapshot_filter_state): void
    {
        $user_id = (int) ($row['user_id'] ?? 0);
        $display_name = trim((string) ($row['display_name'] ?? '')) !== '' ? (string) $row['display_name'] : ('Siswa #' . $user_id);
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
        $availability_items = max(0, (int) ($availability['item_count'] ?? 0));
        $availability_ttl = (int) ($availability['ttl_seconds'] ?? -2);
        $availability_payload_bytes = max(0, (int) ($availability['payload_bytes'] ?? 0));
        $availability_preview_items = array_values(array_filter((array) ($availability['preview_items'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $availability_current_user = is_array($availability['current_user_preview'] ?? null) ? $availability['current_user_preview'] : null;
        $availability_storage_key = (string) ($availability['storage_key'] ?? '');
        $availability_message = (string) ($availability['snapshot_message'] ?? '');
        $profile_ttl = (int) ($profile['ttl_seconds'] ?? -2);
        $profile_payload_bytes = max(0, (int) ($profile['payload_bytes'] ?? 0));
        $profile_preview = is_array($profile['preview'] ?? null) ? $profile['preview'] : [];
        $profile_storage_key = (string) ($profile['storage_key'] ?? '');
        $profile_message = (string) ($profile['snapshot_message'] ?? '');
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
            <td class="cbt-student-snapshot-status-cell">
                <div class="cbt-student-snapshot-card">
                    <div class="cbt-student-snapshot-card-head">
                        <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($availability_status_tone); ?>"><?php echo esc_html($availability_status_label); ?></span>
                        <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('Items ' . $availability_items); ?></span>
                        <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('TTL ' . ($availability_ttl >= 0 ? $availability_ttl . 's' : 'N/A')); ?></span>
                    </div>
                    <div class="cbt-student-snapshot-detail-grid">
                        <span><strong>Payload:</strong> <?php echo esc_html($availability_payload_bytes > 0 ? number_format_i18n($availability_payload_bytes) . ' bytes' : '0 bytes'); ?></span>
                        <span><strong>Storage Key:</strong> <code><?php echo esc_html($availability_storage_key !== '' ? $availability_storage_key : '-'); ?></code></span>
                        <?php if ($availability_current_user): ?>
                            <span><strong>Current User:</strong> <?php echo esc_html((string) ($availability_current_user['display_name'] ?? ($availability_current_user['username'] ?? '-'))); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($availability_preview_items)): ?>
                        <div class="cbt-student-snapshot-preview-list">
                            <?php foreach ($availability_preview_items as $preview_item): ?>
                                <span class="cbt-student-snapshot-preview-pill">
                                    <?php echo esc_html((string) ($preview_item['title'] ?? ('Exam #' . (int) ($preview_item['id'] ?? 0)))); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($availability_message !== ''): ?>
                        <p class="cbt-exam-snapshot-note"><?php echo esc_html($availability_message); ?></p>
                    <?php endif; ?>
                </div>
            </td>
            <td class="cbt-student-snapshot-status-cell">
                <div class="cbt-student-snapshot-card">
                    <div class="cbt-student-snapshot-card-head">
                        <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($profile_status_tone); ?>"><?php echo esc_html($profile_status_label); ?></span>
                        <span class="cbt-student-snapshot-mini-meta"><?php echo esc_html('TTL ' . ($profile_ttl >= 0 ? $profile_ttl . 's' : 'N/A')); ?></span>
                    </div>
                    <div class="cbt-student-snapshot-detail-grid">
                        <span><strong>Payload:</strong> <?php echo esc_html($profile_payload_bytes > 0 ? number_format_i18n($profile_payload_bytes) . ' bytes' : '0 bytes'); ?></span>
                        <span><strong>Storage Key:</strong> <code><?php echo esc_html($profile_storage_key !== '' ? $profile_storage_key : '-'); ?></code></span>
                        <span><strong>Agama:</strong> <?php echo esc_html((string) ($profile_preview['agama'] ?? '-') ?: '-'); ?></span>
                        <span><strong>Gender:</strong> <?php echo esc_html((string) ($profile_preview['jenis_kelamin'] ?? '-') ?: '-'); ?></span>
                        <span><strong>NISN:</strong> <?php echo esc_html((string) ($profile_preview['nisn'] ?? '-') ?: '-'); ?></span>
                    </div>
                    <?php if ($profile_message !== ''): ?>
                        <p class="cbt-exam-snapshot-note"><?php echo esc_html($profile_message); ?></p>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <div class="cbt-student-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_student_exam_availability_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_student_exam_availability_snapshot" />
                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Siapkan Availability</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_student_exam_availability_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_student_exam_availability_snapshot" />
                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button">Bersihkan Availability</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_student_profile_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_student_profile_snapshot" />
                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button button-secondary">Siapkan Profil</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_student_profile_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_student_profile_snapshot" />
                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>" />
                        <?php self::render_snapshot_tab_hidden_field(CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS); ?>
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <?php self::render_student_snapshot_state_hidden_fields($student_snapshot_filter_state); ?>
                        <button type="submit" class="button">Bersihkan Profil</button>
                    </form>
                </div>
            </td>
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

    /**
     * @param array{exam_id:int} $snapshot_filter_state
     */
    private static function render_snapshot_filter_state_hidden_fields(array $snapshot_filter_state): void
    {
        if (!empty($snapshot_filter_state['exam_id'])) {
            ?>
            <input type="hidden" name="cbt_exam_snapshot_exam_id" value="<?php echo esc_attr((string) ((int) $snapshot_filter_state['exam_id'])); ?>" />
            <?php
        }
    }

    private static function render_snapshot_tab_hidden_field(string $tab): void
    {
        $tab = $tab === CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS
            ? CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS
            : CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS;

        if ($tab !== CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS) {
            return;
        }
        ?>
        <input type="hidden" name="cbt_exam_snapshot_tab" value="<?php echo esc_attr($tab); ?>" />
        <?php
    }

    /**
     * @param array{search:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function render_student_snapshot_state_hidden_fields(array $student_snapshot_filter_state): void
    {
        if (($student_snapshot_filter_state['search'] ?? '') !== '') {
            ?>
            <input type="hidden" name="cbt_student_snapshot_q" value="<?php echo esc_attr((string) $student_snapshot_filter_state['search']); ?>" />
            <?php
        }
        if (!empty($student_snapshot_filter_state['paged'])) {
            ?>
            <input type="hidden" name="cbt_student_snapshot_paged" value="<?php echo esc_attr((string) max(1, (int) $student_snapshot_filter_state['paged'])); ?>" />
            <?php
        }
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_snapshot_preview_page_url(int $exam_id, int $page, array $exam_list_state, array $snapshot_filter_state, array $preview_pages, array $student_snapshot_filter_state = ['search' => '', 'paged' => 1, 'per_page' => 25]): string
    {
        $args = CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
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
                CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS
            ),
            $student_snapshot_filter_state
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
     * @param array{search:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_student_snapshot_page_url(int $page, array $exam_list_state, array $snapshot_filter_state, array $preview_pages, array $student_snapshot_filter_state): string
    {
        $state = $student_snapshot_filter_state;
        $state['paged'] = max(1, $page);

        $args = CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
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
                CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS
            ),
            $state
        );

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     * @param array{search:string,paged:int,per_page:int} $student_snapshot_filter_state
     */
    private static function build_snapshot_tab_url(string $tab, array $exam_list_state, array $snapshot_filter_state, array $preview_pages, array $student_snapshot_filter_state): string
    {
        $args = CBT_Admin_Exams_Service::add_student_snapshot_filter_state_args(
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
        );

        return add_query_arg($args, admin_url('admin.php'));
    }
}
