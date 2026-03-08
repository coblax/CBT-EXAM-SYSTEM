<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Admin
{
    private const REDIS_BOOTSTRAP_PLUGIN = 'redis-cache/redis-cache.php';
    private const REDIS_BOOTSTRAP_SLUG = 'redis-cache';
    private const REDIS_CONFIG_BLOCK_START = "/** BEGIN CBT Redis Object Cache */";
    private const REDIS_CONFIG_BLOCK_END = "/** END CBT Redis Object Cache */";
    private const SETUP_BRANDING_OPTION = 'cbt_setup_branding';
    private const USER_META_PLAIN_PASSWORD = 'cbt_plain_password';
    private const DEFAULT_STUDENT_PHOTO_RELATIVE_PATH = 'public/images/default-student-avatar.svg';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'redirect_removed_admin_pages']);
        add_action('admin_notices', [self::class, 'render_cache_runtime_notice']);

        add_action('admin_post_cbt_save_subject', [self::class, 'handle_save_subject']);
        add_action('admin_post_cbt_delete_subject', [self::class, 'handle_delete_subject']);
        add_action('admin_post_cbt_bulk_delete_subjects', [self::class, 'handle_bulk_delete_subjects']);
        add_action('admin_post_cbt_import_subjects', [self::class, 'handle_import_subjects']);
        add_action('admin_post_cbt_download_subject_template', [self::class, 'handle_download_subject_template']);
        add_action('admin_post_cbt_download_subject_template_xlsx', [self::class, 'handle_download_subject_template_xlsx']);

        add_action('admin_post_cbt_save_exam', [self::class, 'handle_save_exam']);
        add_action('admin_post_cbt_delete_exam', [self::class, 'handle_delete_exam']);
        add_action('admin_post_cbt_save_global_exam_token', [self::class, 'handle_save_global_exam_token']);
        add_action('admin_post_cbt_save_setup_branding', [self::class, 'handle_save_setup_branding']);
        add_action('admin_post_cbt_cache_action', [self::class, 'handle_cache_action']);
        add_action('admin_post_cbt_reset_database', [self::class, 'handle_reset_database']);
        add_action('admin_post_cbt_backfill_question_sources', [self::class, 'handle_backfill_question_sources']);

        add_action('admin_post_cbt_save_question', [self::class, 'handle_save_question']);
        add_action('admin_post_cbt_delete_question', [self::class, 'handle_delete_question']);
        add_action('admin_post_cbt_bulk_delete_questions', [self::class, 'handle_bulk_delete_questions']);
        add_action('admin_post_cbt_import_questions', [self::class, 'handle_import_questions']);
        add_action('admin_post_cbt_download_question_template_word_mc', [self::class, 'handle_download_question_template_word_mc']);
        add_action('admin_post_cbt_download_question_template_word_ma', [self::class, 'handle_download_question_template_word_ma']);
        add_action('admin_post_cbt_download_question_template_word_sa', [self::class, 'handle_download_question_template_word_sa']);
        add_action('admin_post_cbt_download_question_template_word_tf', [self::class, 'handle_download_question_template_word_tf']);
        add_action('admin_post_cbt_download_question_template_word_tfm', [self::class, 'handle_download_question_template_word_tfm']);
        add_action('admin_post_cbt_download_question_template_word_essay', [self::class, 'handle_download_question_template_word_essay']);
        add_action('admin_post_cbt_grade_essay', [self::class, 'handle_grade_essay']);
        add_action('admin_post_cbt_reset_user_login', [self::class, 'handle_reset_user_login']);
        add_action('admin_post_cbt_reset_attempt', [self::class, 'handle_reset_attempt']);
        add_action('admin_post_cbt_bulk_reset_attempts', [self::class, 'handle_bulk_reset_attempts']);
        add_action('admin_post_cbt_bulk_force_complete_attempts', [self::class, 'handle_bulk_force_complete_attempts']);
        add_action('admin_post_cbt_export_exam_report_pdf', [self::class, 'handle_export_exam_report_pdf']);
        add_action('admin_post_cbt_print_exam_cards', [self::class, 'handle_print_exam_cards']);

        add_action('admin_post_cbt_import_users', [self::class, 'handle_import_users']);
        add_action('admin_post_cbt_create_user_manual', [self::class, 'handle_create_user_manual']);
        add_action('admin_post_cbt_update_user_manual', [self::class, 'handle_update_user_manual']);
        add_action('admin_post_cbt_delete_user_manual', [self::class, 'handle_delete_user_manual']);
        add_action('admin_post_cbt_bulk_delete_users', [self::class, 'handle_bulk_delete_users']);
        add_action('admin_post_cbt_download_user_template', [self::class, 'handle_download_user_template']);
        add_action('admin_post_cbt_download_user_template_xlsx', [self::class, 'handle_download_user_template_xlsx']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'CBT Exams',
            'CBT Exams',
            'cbt_manage_exams',
            'cbt-exams',
            [self::class, 'render_exams_page'],
            'dashicons-welcome-learn-more',
            26
        );

        remove_submenu_page('cbt-exams', 'cbt-exams');

        add_submenu_page(
            'cbt-exams',
            'Exams',
            'Exams',
            'cbt_manage_exams',
            'cbt-exams',
            [self::class, 'render_exams_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Tokens',
            'CBT Tokens',
            'cbt_manage_exams',
            'cbt-tokens',
            [self::class, 'render_tokens_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Setup',
            'CBT Setup',
            'cbt_manage_exams',
            'cbt-setup',
            [self::class, 'render_setup_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Maintenance',
            'CBT Maintenance',
            'manage_options',
            'cbt-maintenance',
            [self::class, 'render_maintenance_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Cache',
            'CBT Cache',
            'manage_options',
            'cbt-cache',
            [self::class, 'render_cache_page']
        );

        add_submenu_page(
            'cbt-exams',
            'Subjects / Mata Pelajaran',
            'Subjects / Mata Pelajaran',
            'manage_options',
            'cbt-subjects',
            [self::class, 'render_subjects_page']
        );

        add_submenu_page(
            'cbt-exams',
            'User Import',
            'User Import',
            'manage_options',
            'cbt-user-import',
            [self::class, 'render_user_import_page']
        );

        add_submenu_page(
            'cbt-exams',
            'Cetak Kartu Ujian',
            'Cetak Kartu Ujian',
            'cbt_manage_users',
            'cbt-exam-cards',
            [self::class, 'render_exam_cards_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Bank Soal',
            'CBT Bank Soal',
            'cbt_manage_questions',
            'cbt-question-bank',
            [self::class, 'render_question_bank_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Results',
            'CBT Results',
            'cbt_view_results',
            'cbt-results',
            [self::class, 'render_results_page']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Report Exam',
            'CBT Report Exam',
            'cbt_view_results',
            'cbt-report-exam',
            [self::class, 'render_report_exam_page']
        );
    }

    public static function render_question_bank_page(): void
    {
        self::render_questions_page(null);
    }

    public static function render_questions_multiple_choice_page(): void
    {
        self::render_questions_page('multiple_choice');
    }

    public static function render_questions_multiple_answer_page(): void
    {
        self::render_questions_page('multiple_answer');
    }

    public static function render_questions_true_false_page(): void
    {
        self::render_questions_page('true_false');
    }

    public static function render_questions_short_answer_page(): void
    {
        self::render_questions_page('short_answer');
    }

    public static function render_questions_essay_page(): void
    {
        self::render_questions_page('essay');
    }

    private static function allowed_question_page_slugs(): array
    {
        return [
            'cbt-question-bank',
            'cbt-questions-mc',
            'cbt-questions-ma',
            'cbt-questions-tf',
            'cbt-questions-sa',
            'cbt-questions-essay',
        ];
    }

    private static function normalize_question_page_slug($raw_page_slug): string
    {
        $page_slug = sanitize_key((string) $raw_page_slug);
        if (!in_array($page_slug, self::allowed_question_page_slugs(), true)) {
            return 'cbt-question-bank';
        }
        return $page_slug;
    }

    private static function forced_question_type_for_page(string $page_slug): string
    {
        switch ($page_slug) {
            case 'cbt-questions-mc':
                return 'multiple_choice';
            case 'cbt-questions-ma':
                return 'multiple_answer';
            case 'cbt-questions-tf':
                return 'true_false';
            case 'cbt-questions-sa':
                return 'short_answer';
            case 'cbt-questions-essay':
                return 'essay';
            default:
                return '';
        }
    }

    public static function render_exams_page(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $option_table = $wpdb->prefix . 'cbt_options';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();

        $preview_exam_id = isset($_GET['preview_exam_id']) ? absint($_GET['preview_exam_id']) : 0;
        if ($preview_exam_id > 0) {
            self::render_exam_questions_preview_page($preview_exam_id, $is_admin_scope, $current_user_id);
            return;
        }

        $subjects = $wpdb->get_results(
            "SELECT id, name, code
             FROM {$subject_table}
             ORDER BY name ASC",
            ARRAY_A
        );

        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $editing_exam = null;
        if ($editing_id > 0) {
            if ($is_admin_scope) {
                $editing_exam = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$exam_table} WHERE id = %d", $editing_id),
                    ARRAY_A
                );
            } else {
                $editing_exam = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$exam_table} WHERE id = %d AND created_by = %d",
                        $editing_id,
                        $current_user_id
                    ),
                    ARRAY_A
                );
            }
        }

        $selected_subject_id = isset($_GET['subject_id']) ? absint($_GET['subject_id']) : 0;
        if ($editing_exam && isset($editing_exam['subject_id'])) {
            $selected_subject_id = (int) $editing_exam['subject_id'];
        }
        if ($selected_subject_id <= 0 && !empty($subjects)) {
            $selected_subject_id = (int) $subjects[0]['id'];
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        if ($editing_id > 0 && !$editing_exam) {
            $error = $error !== '' ? $error : 'Exam tidak ditemukan atau tidak bisa diakses.';
        }

        $source_where_parts = [
            $wpdb->prepare('e.title LIKE %s', 'Bank Soal - %'),
        ];
        $source_where_parts_legacy = [];
        if (!$is_admin_scope) {
            $created_by_clause = $wpdb->prepare('e.created_by = %d', $current_user_id);
            $source_where_parts[] = $created_by_clause;
            $source_where_parts_legacy[] = $created_by_clause;
        }
        $source_where = implode(' AND ', $source_where_parts);
        $source_where_legacy = !empty($source_where_parts_legacy) ? implode(' AND ', $source_where_parts_legacy) : '1=1';
        $source_questions = $wpdb->get_results(
            "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.points, e.title AS exam_title, e.subject_id, s.name AS subject_name
             FROM {$question_table} q
             INNER JOIN {$exam_table} e ON e.id = q.exam_id
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             WHERE {$source_where}
             ORDER BY s.name ASC, q.id DESC",
            ARRAY_A
        );
        if (empty($source_questions)) {
            $source_questions = $wpdb->get_results(
                "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.points, e.title AS exam_title, e.subject_id, s.name AS subject_name
                 FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                 WHERE {$source_where_legacy}
                 ORDER BY s.name ASC, q.id DESC",
                ARRAY_A
            );
        }
        $source_options_map = [];
        $source_question_ids = array_values(array_unique(array_map('intval', wp_list_pluck($source_questions, 'id'))));
        if (!empty($source_question_ids)) {
            $placeholders = implode(',', array_fill(0, count($source_question_ids), '%d'));
            $source_option_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT question_id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id IN ({$placeholders})
                     ORDER BY id ASC",
                    ...$source_question_ids
                ),
                ARRAY_A
            );
            foreach ((array) $source_option_rows as $source_option_row) {
                $question_id = (int) ($source_option_row['question_id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }
                if (!isset($source_options_map[$question_id])) {
                    $source_options_map[$question_id] = [];
                }
                $source_options_map[$question_id][] = $source_option_row;
            }
        }
        $source_exam_options = [];
        foreach ($source_questions as $source_question) {
            $source_exam_id = (int) ($source_question['exam_id'] ?? 0);
            $source_exam_title = (string) ($source_question['exam_title'] ?? '');
            if ($source_exam_id <= 0 || $source_exam_title === '') {
                continue;
            }
            $source_exam_options[$source_exam_id] = $source_exam_title;
        }

        $selected_question_ids = [];
        if ($editing_exam) {
            $selected_question_ids = array_map(
                'intval',
                (array) $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT COALESCE(source_question_id, id) FROM {$question_table} WHERE exam_id = %d ORDER BY id ASC",
                        (int) $editing_exam['id']
                    )
                )
            );
        }

        $exam_list_where_parts = [
            $wpdb->prepare('e.title NOT LIKE %s', 'Bank Soal - %'),
        ];
        if (!$is_admin_scope) {
            $exam_list_where_parts[] = $wpdb->prepare('e.created_by = %d', $current_user_id);
        }
        $exam_list_where = ' WHERE ' . implode(' AND ', $exam_list_where_parts);
        $exam_per_page = isset($_GET['cbt_exam_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash($_GET['cbt_exam_per_page'])))
            : 20;
        $exam_current_page = isset($_GET['cbt_exam_paged']) ? max(1, absint(wp_unslash($_GET['cbt_exam_paged']))) : 1;
        $total_exams = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$exam_table} e {$exam_list_where}");
        $exam_total_pages = max(1, (int) ceil($total_exams / $exam_per_page));
        if ($total_exams > 0 && $exam_current_page > $exam_total_pages) {
            $exam_current_page = $exam_total_pages;
        }
        $exam_offset = ($exam_current_page - 1) * $exam_per_page;

        $exam_limit = (int) $exam_per_page;
        $exam_offset = (int) $exam_offset;
        $exams = $wpdb->get_results(
            "SELECT e.*,
                    s.name AS subject_name,
                    (SELECT COUNT(*) FROM {$question_table} q WHERE q.exam_id = e.id) AS question_count,
                    (SELECT COUNT(*) FROM {$attempt_table} a WHERE a.exam_id = e.id) AS attempt_total,
                    (SELECT COUNT(*) FROM {$attempt_table} a WHERE a.exam_id = e.id AND a.status = 'in_progress') AS attempt_in_progress,
                    (SELECT COUNT(*) FROM {$attempt_table} a WHERE a.exam_id = e.id AND a.status = 'completed') AS attempt_completed
             FROM {$exam_table} e
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             {$exam_list_where}
             ORDER BY e.id DESC
             LIMIT {$exam_limit} OFFSET {$exam_offset}",
            ARRAY_A
        );

        $kelas_options = self::get_distinct_user_meta_values('kode_kelas');
        $editing_target_kelas_values = [];
        if ($editing_exam && isset($editing_exam['target_kelas'])) {
            $editing_target_kelas_values = self::split_target_kelas_csv((string) $editing_exam['target_kelas']);
        }
        foreach ($editing_target_kelas_values as $kelas_value) {
            if (!in_array($kelas_value, $kelas_options, true)) {
                $kelas_options[] = $kelas_value;
            }
        }
        sort($kelas_options, SORT_NATURAL | SORT_FLAG_CASE);

        $question_type_labels = [
            'multiple_choice' => 'Multiple Choice',
            'multiple_answer' => 'Multiple Answer',
            'true_false' => 'True/False',
            'true_false_matrix' => 'True/False Matrix',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
        ];
        ?>
        <div class="wrap">
            <h1>CBT Exams</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php if (empty($subjects)): ?>
                <div class="notice notice-warning"><p>Belum ada mapel. Buat mapel terlebih dahulu di menu Subjects.</p></div>
            <?php else: ?>
                <h2><?php echo $editing_exam ? 'Edit Exam' : 'Buat Exam Baru'; ?></h2>
                <p class="description">Flow: pilih mapel, atur jadwal, tentukan kelas peserta, lalu pilih soal yang dipakai untuk exam.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-exam-builder-form">
                    <?php wp_nonce_field('cbt_save_exam'); ?>
                    <input type="hidden" name="action" value="cbt_save_exam" />
                    <input type="hidden" name="id" value="<?php echo (int) ($editing_exam['id'] ?? 0); ?>" />

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cbt-exam-subject-id">Mapel</label></th>
                            <td>
                                <select required id="cbt-exam-subject-id" name="subject_id">
                                    <option value="">Select subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo (int) $subject['id']; ?>" <?php selected($selected_subject_id, (int) $subject['id']); ?>>
                                            <?php echo esc_html((string) $subject['name'] . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-title">Judul Exam</label></th>
                            <td>
                                <input required type="text" id="cbt-exam-title" name="title" class="regular-text" value="<?php echo esc_attr((string) ($editing_exam['title'] ?? '')); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-duration">Durasi (menit)</label></th>
                            <td>
                                <input type="number" id="cbt-exam-duration" name="duration_minutes" min="1" value="<?php echo esc_attr((string) ((int) ($editing_exam['duration_minutes'] ?? 60))); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php $editing_status = (string) ($editing_exam['status'] ?? 'draft'); ?>
                                <select name="status">
                                    <option value="draft" <?php selected($editing_status, 'draft'); ?>>Draft</option>
                                    <option value="published" <?php selected($editing_status, 'published'); ?>>Published</option>
                                    <option value="closed" <?php selected($editing_status, 'closed'); ?>>Closed</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-starts-at">Mulai</label></th>
                            <td>
                                <input type="datetime-local" id="cbt-exam-starts-at" name="starts_at" value="<?php echo esc_attr(self::to_datetime_local((string) ($editing_exam['starts_at'] ?? ''))); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-ends-at">Selesai</label></th>
                            <td>
                                <input type="datetime-local" id="cbt-exam-ends-at" name="ends_at" value="<?php echo esc_attr(self::to_datetime_local((string) ($editing_exam['ends_at'] ?? ''))); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-target-kelas">Kelas Peserta</label></th>
                            <td>
                                <div style="margin-bottom:8px;">
                                    <button type="button" class="button button-secondary button-small" id="cbt-kelas-check-all">Pilih Semua</button>
                                    <button type="button" class="button button-secondary button-small" id="cbt-kelas-uncheck-all">Kosongkan</button>
                                </div>
                                <div id="cbt-kelas-checklist" style="min-width:360px; max-height:220px; overflow:auto; border:1px solid #ccd0d4; padding:8px; background:#fff;">
                                    <?php if (empty($kelas_options)): ?>
                                        <em>Belum ada data kelas dari User Import.</em>
                                    <?php else: ?>
                                        <?php foreach ($kelas_options as $kelas_option): ?>
                                            <label style="display:block; margin: 0 0 6px;">
                                                <input
                                                    type="checkbox"
                                                    class="cbt-kelas-checkbox"
                                                    name="target_kelas[]"
                                                    value="<?php echo esc_attr($kelas_option); ?>"
                                                    <?php checked(in_array($kelas_option, $editing_target_kelas_values, true)); ?>
                                                />
                                                <?php echo esc_html($kelas_option); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="description">Pilih satu atau lebih kelas. Kosongkan pilihan jika exam bisa diakses semua kelas.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-randomize">Acak Soal</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="cbt-exam-randomize" name="randomize_questions" value="1" <?php checked((int) ($editing_exam['randomize_questions'] ?? 0), 1); ?> />
                                    Acak urutan soal untuk siswa
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-exam-description">Deskripsi</label></th>
                            <td>
                                <textarea id="cbt-exam-description" name="description" rows="4" class="large-text"><?php echo esc_textarea((string) ($editing_exam['description'] ?? '')); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <h3>Pilih Soal Exam</h3>
                    <p class="description">Soal akan disalin dari bank soal yang tersedia (lintas mapel). Saat exam disimpan ulang, daftar soal exam akan disinkronkan dari pilihan ini.</p>

                    <p>
                        <label for="cbt-exam-question-search" style="margin-right:8px;">Cari Soal</label>
                        <input type="text" id="cbt-exam-question-search" class="regular-text" placeholder="Ketik kata kunci soal..." />
                        <label for="cbt-exam-question-type-filter" style="margin-left:12px; margin-right:8px;">Tipe</label>
                        <select id="cbt-exam-question-type-filter">
                            <option value="">Semua tipe</option>
                            <?php foreach ($question_type_labels as $question_type => $question_type_label): ?>
                                <option value="<?php echo esc_attr($question_type); ?>"><?php echo esc_html($question_type_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="cbt-exam-question-source-filter" style="margin-left:12px; margin-right:8px;">Sumber</label>
                        <select id="cbt-exam-question-source-filter">
                            <option value="">Semua sumber</option>
                            <?php foreach ($source_exam_options as $source_exam_id => $source_exam_title): ?>
                                <option value="<?php echo (int) $source_exam_id; ?>"><?php echo esc_html($source_exam_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button button-secondary" id="cbt-exam-select-visible" style="margin-left:8px;">Select Visible</button>
                        <button type="button" class="button button-secondary" id="cbt-exam-unselect-visible">Unselect Visible</button>
                        <span id="cbt-exam-selected-count" style="margin-left:12px;">0 dipilih</span>
                    </p>

                    <div style="max-height: 380px; overflow:auto; border:1px solid #ccd0d4;">
                        <table class="widefat striped" style="margin:0;">
                            <thead>
                            <tr>
                                <th style="width:38px;"><input type="checkbox" id="cbt-exam-select-all-visible" /></th>
                                <th>ID</th>
                                <th>Mapel</th>
                                <th>Sumber</th>
                                <th>Tipe</th>
                                <th>Soal</th>
                                <th>Poin</th>
                                <th>Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($source_questions)): ?>
                                <tr><td colspan="8">Belum ada soal tersedia. Isi dulu di menu CBT Bank Soal.</td></tr>
                            <?php else: ?>
                                <?php foreach ($source_questions as $source_question): ?>
                                    <?php
                                    $source_question_id = (int) ($source_question['id'] ?? 0);
                                    $source_subject_id = (int) ($source_question['subject_id'] ?? 0);
                                    $source_exam_id = (int) ($source_question['exam_id'] ?? 0);
                                    $source_type = (string) ($source_question['question_type'] ?? '');
                                    $source_type_label = (string) ($question_type_labels[$source_type] ?? $source_type);
                                    $question_plain = strtolower(trim(wp_strip_all_tags((string) ($source_question['question_text'] ?? ''))));
                                    $question_preview = wp_trim_words((string) wp_strip_all_tags((string) ($source_question['question_text'] ?? '')), 16);
                                    $is_checked = in_array($source_question_id, $selected_question_ids, true);
                                    $source_options = $source_options_map[$source_question_id] ?? [];
                                    $source_correct_keys = [];
                                    foreach ($source_options as $source_option) {
                                        if ((int) ($source_option['is_correct'] ?? 0) === 1) {
                                            $source_correct_keys[] = strtoupper((string) ($source_option['option_key'] ?? ''));
                                        }
                                    }
                                    ?>
                                    <tr class="cbt-exam-question-row"
                                        data-subject-id="<?php echo (int) $source_subject_id; ?>"
                                        data-source-id="<?php echo (int) $source_exam_id; ?>"
                                        data-type="<?php echo esc_attr($source_type); ?>"
                                        data-search="<?php echo esc_attr($question_plain); ?>">
                                        <td><input type="checkbox" class="cbt-exam-question-checkbox" name="source_question_ids[]" value="<?php echo (int) $source_question_id; ?>" <?php checked($is_checked); ?> /></td>
                                        <td><?php echo (int) $source_question_id; ?></td>
                                        <td><?php echo esc_html((string) ($source_question['subject_name'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($source_question['exam_title'] ?? '-')); ?></td>
                                        <td><?php echo esc_html($source_type_label); ?></td>
                                        <td><?php echo esc_html($question_preview); ?></td>
                                        <td><?php echo esc_html((string) ($source_question['points'] ?? '1')); ?></td>
                                        <td>
                                            <button type="button" class="button-link cbt-quick-view-btn" data-qid="<?php echo (int) $source_question_id; ?>">Lihat Cepat</button>
                                            <div id="cbt-quick-view-content-<?php echo (int) $source_question_id; ?>" style="display:none;">
                                                <p><strong>Subject:</strong> <?php echo esc_html((string) ($source_question['subject_name'] ?? '-')); ?></p>
                                                <p><strong>Sumber:</strong> <?php echo esc_html((string) ($source_question['exam_title'] ?? '-')); ?></p>
                                                <p><strong>Tipe:</strong> <?php echo esc_html($source_type_label); ?></p>
                                                <p><strong>Poin:</strong> <?php echo esc_html((string) ($source_question['points'] ?? '1')); ?></p>
                                                <hr />
                                                <div><?php echo wp_kses_post((string) ($source_question['question_text'] ?? '')); ?></div>
                                                <?php if (!empty($source_options)): ?>
                                                    <hr />
                                                    <p><strong>Opsi:</strong></p>
                                                    <ol style="margin-left:18px;">
                                                        <?php foreach ($source_options as $source_option): ?>
                                                            <?php $option_key = strtoupper((string) ($source_option['option_key'] ?? '')); ?>
                                                            <li>
                                                                <strong><?php echo esc_html($option_key); ?>.</strong>
                                                                <?php echo wp_kses_post((string) ($source_option['option_text'] ?? '')); ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ol>
                                                    <?php if (!empty($source_correct_keys)): ?>
                                                        <p><strong>Kunci:</strong> <?php echo esc_html(implode(', ', $source_correct_keys)); ?></p>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="cbt-exam-quickview-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:100000;">
                        <div style="max-width:760px; margin:6vh auto; background:#fff; border-radius:4px; overflow:hidden;">
                            <div style="padding:10px 12px; border-bottom:1px solid #dcdcde; display:flex; justify-content:space-between; align-items:center;">
                                <strong id="cbt-exam-quickview-title">Preview Soal</strong>
                                <button type="button" class="button button-secondary" id="cbt-exam-quickview-close-top">Tutup</button>
                            </div>
                            <div id="cbt-exam-quickview-body" style="padding:12px; max-height:65vh; overflow:auto;"></div>
                            <div style="padding:10px 12px; border-top:1px solid #dcdcde;">
                                <button type="button" class="button" id="cbt-exam-quickview-close-bottom">Tutup</button>
                            </div>
                        </div>
                    </div>

                    <?php submit_button($editing_exam ? 'Update Exam' : 'Create Exam'); ?>
                    <?php if ($editing_exam): ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cbt-exams')); ?>">Batal Edit</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <hr />

            <h2>Daftar Exam</h2>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:8px 0 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="cbt-exams" />
                <label for="cbt-exam-per-page">Per halaman</label>
                <select id="cbt-exam-per-page" name="cbt_exam_per_page">
                    <?php foreach ([20, 40, 60, 80, 100] as $exam_per_page_option): ?>
                        <option value="<?php echo (int) $exam_per_page_option; ?>" <?php selected($exam_per_page, $exam_per_page_option); ?>>
                            <?php echo esc_html((string) $exam_per_page_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-secondary">Terapkan</button>
            </form>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Mapel</th>
                    <th>Judul</th>
                    <th>Kelas</th>
                    <th>Status</th>
                    <th>Jadwal</th>
                    <th>Durasi</th>
                    <th>Soal</th>
                    <th>Monitoring</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($exams)): ?>
                    <tr><td colspan="10">No exams found.</td></tr>
                <?php else: ?>
                    <?php foreach ($exams as $exam): ?>
                        <?php
                        $kelas_list = self::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
                        $kelas_display = !empty($kelas_list) ? implode(', ', $kelas_list) : 'Semua kelas';
                        $schedule_parts = [];
                        if (!empty($exam['starts_at'])) {
                            $schedule_parts[] = 'Mulai: ' . (string) $exam['starts_at'];
                        }
                        if (!empty($exam['ends_at'])) {
                            $schedule_parts[] = 'Selesai: ' . (string) $exam['ends_at'];
                        }
                        $schedule_display = !empty($schedule_parts) ? implode(' | ', $schedule_parts) : '-';
                        ?>
                        <tr>
                            <td><?php echo (int) $exam['id']; ?></td>
                            <td><?php echo esc_html((string) ($exam['subject_name'] ?? '-')); ?></td>
                            <td><?php echo esc_html((string) ($exam['title'] ?? '')); ?></td>
                            <td><?php echo esc_html($kelas_display); ?></td>
                            <td><?php echo esc_html((string) ($exam['status'] ?? 'draft')); ?></td>
                            <td><?php echo esc_html($schedule_display); ?></td>
                            <td><?php echo esc_html((string) ((int) ($exam['duration_minutes'] ?? 0))); ?> menit</td>
                            <td><?php echo esc_html((string) ((int) ($exam['question_count'] ?? 0))); ?></td>
                            <td>
                                <?php
                                $attempt_total = (int) ($exam['attempt_total'] ?? 0);
                                $attempt_in_progress = (int) ($exam['attempt_in_progress'] ?? 0);
                                $attempt_completed = (int) ($exam['attempt_completed'] ?? 0);
                                ?>
                                Total: <?php echo esc_html((string) $attempt_total); ?><br />
                                Ongoing: <?php echo esc_html((string) $attempt_in_progress); ?><br />
                                Selesai: <?php echo esc_html((string) $attempt_completed); ?>
                            </td>
                            <td>
                                <a
                                    href="<?php echo esc_url(add_query_arg(['page' => 'cbt-exams', 'preview_exam_id' => (int) $exam['id'], 'cbt_exam_per_page' => $exam_per_page, 'cbt_exam_paged' => $exam_current_page], admin_url('admin.php'))); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >Lihat Soal</a>
                                |
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'cbt-results', 'cbt_exam_id' => (int) $exam['id']], admin_url('admin.php'))); ?>">Results</a>
                                |
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'cbt-exams', 'edit' => (int) $exam['id'], 'cbt_exam_per_page' => $exam_per_page, 'cbt_exam_paged' => $exam_current_page], admin_url('admin.php'))); ?>">Edit</a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'cbt_delete_exam', 'id' => (int) $exam['id'], 'cbt_exam_per_page' => $exam_per_page, 'cbt_exam_paged' => $exam_current_page], admin_url('admin-post.php')), 'cbt_delete_exam_' . (int) $exam['id'])); ?>" onclick="return confirm('Delete this exam?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php
            $exam_pagination_links = [];
            if ($exam_total_pages > 1) {
                $exam_pagination_links = paginate_links([
                    'base' => add_query_arg(
                        [
                            'page' => 'cbt-exams',
                            'cbt_exam_per_page' => $exam_per_page,
                            'cbt_exam_paged' => '%#%',
                        ],
                        admin_url('admin.php')
                    ),
                    'format' => '',
                    'current' => $exam_current_page,
                    'total' => $exam_total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type' => 'array',
                    'end_size' => 1,
                    'mid_size' => 1,
                ]);
            }
            ?>
            <div class="tablenav bottom cbt-admin-pagination-wrap" style="margin-top:10px;">
                <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                    <span class="displaying-num cbt-admin-total"><?php echo esc_html(sprintf('Total exam: %d', $total_exams)); ?></span>
                    <?php if (!empty($exam_pagination_links)): ?>
                        <span class="pagination-links cbt-admin-pagination-links">
                            <?php foreach ($exam_pagination_links as $exam_pagination_link): ?>
                                <?php echo wp_kses_post($exam_pagination_link); ?>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <style>
                .cbt-admin-pagination-wrap {
                    clear: both;
                }
                .cbt-admin-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination .cbt-admin-total {
                    font-size: 14px;
                    line-height: 1.4;
                    color: #1d2327;
                    font-weight: 500;
                }
                .cbt-admin-pagination-links {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 34px;
                    height: 34px;
                    padding: 0 12px;
                    border: 1px solid #c3c4c7;
                    border-radius: 6px;
                    background: #fff;
                    color: #1d2327;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                }
                .cbt-admin-pagination-links .page-numbers:hover,
                .cbt-admin-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.15);
                    color: #0a4b78;
                    outline: none;
                }
                .cbt-admin-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #fff;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
                }
                .cbt-admin-pagination-links .page-numbers.prev,
                .cbt-admin-pagination-links .page-numbers.next {
                    padding: 0 14px;
                    font-weight: 600;
                }
                .cbt-admin-pagination-links .page-numbers.dots {
                    border-color: transparent;
                    background: transparent;
                    color: #646970;
                    min-width: auto;
                    padding: 0 4px;
                    box-shadow: none;
                }
                @media (max-width: 782px) {
                    .cbt-admin-pagination {
                        align-items: flex-start;
                    }
                    .cbt-admin-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        padding: 0 10px;
                        font-size: 13px;
                    }
                }
            </style>
        </div>
        <script>
            (function () {
                const searchInput = document.getElementById('cbt-exam-question-search');
                const typeFilter = document.getElementById('cbt-exam-question-type-filter');
                const sourceFilter = document.getElementById('cbt-exam-question-source-filter');
                const selectedCountEl = document.getElementById('cbt-exam-selected-count');
                const selectAllVisible = document.getElementById('cbt-exam-select-all-visible');
                const selectVisibleBtn = document.getElementById('cbt-exam-select-visible');
                const unselectVisibleBtn = document.getElementById('cbt-exam-unselect-visible');
                const form = document.getElementById('cbt-exam-builder-form');
                const rows = Array.from(document.querySelectorAll('.cbt-exam-question-row'));
                const quickViewButtons = Array.from(document.querySelectorAll('.cbt-quick-view-btn'));
                const quickViewModal = document.getElementById('cbt-exam-quickview-modal');
                const quickViewBody = document.getElementById('cbt-exam-quickview-body');
                const quickViewTitle = document.getElementById('cbt-exam-quickview-title');
                const quickViewCloseTop = document.getElementById('cbt-exam-quickview-close-top');
                const quickViewCloseBottom = document.getElementById('cbt-exam-quickview-close-bottom');

                function closeQuickView() {
                    if (!quickViewModal) return;
                    quickViewModal.style.display = 'none';
                    if (quickViewBody) {
                        quickViewBody.innerHTML = '';
                    }
                }

                if (quickViewButtons.length > 0 && quickViewModal && quickViewBody) {
                    quickViewButtons.forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const qid = String(btn.getAttribute('data-qid') || '');
                            const source = document.getElementById(`cbt-quick-view-content-${qid}`);
                            if (!source) return;
                            if (quickViewTitle) {
                                quickViewTitle.textContent = `Preview Soal #${qid}`;
                            }
                            quickViewBody.innerHTML = source.innerHTML;
                            quickViewModal.style.display = 'block';
                        });
                    });

                    if (quickViewCloseTop) {
                        quickViewCloseTop.addEventListener('click', closeQuickView);
                    }
                    if (quickViewCloseBottom) {
                        quickViewCloseBottom.addEventListener('click', closeQuickView);
                    }
                    quickViewModal.addEventListener('click', (event) => {
                        if (event.target === quickViewModal) {
                            closeQuickView();
                        }
                    });
                }

                const kelasCheckAllBtn = document.getElementById('cbt-kelas-check-all');
                const kelasUncheckAllBtn = document.getElementById('cbt-kelas-uncheck-all');
                const kelasCheckboxes = Array.from(document.querySelectorAll('.cbt-kelas-checkbox'));
                if (kelasCheckAllBtn && kelasCheckboxes.length > 0) {
                    kelasCheckAllBtn.addEventListener('click', () => {
                        kelasCheckboxes.forEach((item) => {
                            item.checked = true;
                        });
                    });
                }
                if (kelasUncheckAllBtn && kelasCheckboxes.length > 0) {
                    kelasUncheckAllBtn.addEventListener('click', () => {
                        kelasCheckboxes.forEach((item) => {
                            item.checked = false;
                        });
                    });
                }

                if (rows.length === 0) {
                    return;
                }

                function getVisibleRows() {
                    return rows.filter((row) => row.style.display !== 'none');
                }

                function syncCounter() {
                    const visibleRows = getVisibleRows();
                    const visibleChecked = visibleRows.filter((row) => {
                        const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                        return !!checkbox && checkbox.checked;
                    }).length;

                    if (selectedCountEl) {
                        selectedCountEl.textContent = `${visibleChecked} dipilih / ${visibleRows.length} terlihat`;
                    }

                    if (selectAllVisible) {
                        selectAllVisible.checked = visibleRows.length > 0 && visibleChecked === visibleRows.length;
                        selectAllVisible.indeterminate = visibleChecked > 0 && visibleChecked < visibleRows.length;
                    }
                }

                function applyFilters(uncheckHidden = false) {
                    const selectedType = String(typeFilter?.value || '');
                    const selectedSource = String(sourceFilter?.value || '');
                    const keyword = String(searchInput?.value || '').trim().toLowerCase();

                    rows.forEach((row) => {
                        const rowSource = String(row.getAttribute('data-source-id') || '');
                        const rowType = String(row.getAttribute('data-type') || '');
                        const rowSearch = String(row.getAttribute('data-search') || '');

                        const matchSource = selectedSource === '' || rowSource === selectedSource;
                        const matchType = selectedType === '' || rowType === selectedType;
                        const matchKeyword = keyword === '' || rowSearch.includes(keyword);

                        const visible = matchSource && matchType && matchKeyword;
                        row.style.display = visible ? '' : 'none';

                        if (!visible && uncheckHidden) {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                checkbox.checked = false;
                            }
                        }
                    });

                    syncCounter();
                }

                rows.forEach((row) => {
                    const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                    if (!checkbox) return;
                    checkbox.addEventListener('change', syncCounter);
                });

                if (selectAllVisible) {
                    selectAllVisible.addEventListener('change', () => {
                        getVisibleRows().forEach((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                checkbox.checked = selectAllVisible.checked;
                            }
                        });
                        syncCounter();
                    });
                }

                if (selectVisibleBtn) {
                    selectVisibleBtn.addEventListener('click', () => {
                        getVisibleRows().forEach((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                        syncCounter();
                    });
                }

                if (unselectVisibleBtn) {
                    unselectVisibleBtn.addEventListener('click', () => {
                        getVisibleRows().forEach((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                checkbox.checked = false;
                            }
                        });
                        syncCounter();
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', () => applyFilters(false));
                }
                if (typeFilter) {
                    typeFilter.addEventListener('change', () => applyFilters(false));
                }
                if (sourceFilter) {
                    sourceFilter.addEventListener('change', () => applyFilters(false));
                }

                if (form) {
                    form.addEventListener('submit', (event) => {
                        const selected = rows.some((row) => {
                            if (row.style.display === 'none') return false;
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            return !!checkbox && checkbox.checked;
                        }) || rows.some((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            return !!checkbox && checkbox.checked;
                        });

                        if (!selected) {
                            event.preventDefault();
                            window.alert('Pilih minimal 1 soal untuk exam.');
                        }
                    });
                }

                applyFilters(true);
            })();
        </script>
        <?php
    }

    private static function render_exam_questions_preview_page(int $exam_id, bool $is_admin_scope, int $current_user_id): void
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';

        $return_per_page = isset($_GET['cbt_exam_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash($_GET['cbt_exam_per_page'])))
            : 20;
        $return_page = isset($_GET['cbt_exam_paged']) ? max(1, absint(wp_unslash($_GET['cbt_exam_paged']))) : 1;
        $back_url = add_query_arg(
            [
                'page' => 'cbt-exams',
                'cbt_exam_per_page' => $return_per_page,
                'cbt_exam_paged' => $return_page,
            ],
            admin_url('admin.php')
        );

        if ($is_admin_scope) {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT e.*, s.name AS subject_name
                     FROM {$exam_table} e
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     WHERE e.id = %d
                     LIMIT 1",
                    $exam_id
                ),
                ARRAY_A
            );
        } else {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT e.*, s.name AS subject_name
                     FROM {$exam_table} e
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     WHERE e.id = %d AND e.created_by = %d
                     LIMIT 1",
                    $exam_id,
                    $current_user_id
                ),
                ARRAY_A
            );
        }

        if (!$exam) {
            ?>
            <div class="wrap">
                <h1>Preview Soal Exam</h1>
                <div class="notice notice-error"><p>Exam tidak ditemukan atau tidak bisa diakses.</p></div>
                <p><a class="button" href="<?php echo esc_url($back_url); ?>">Kembali ke Daftar Exam</a></p>
            </div>
            <?php
            return;
        }

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_text, question_type, points, explanation, correct_text
                 FROM {$question_table}
                 WHERE exam_id = %d
                 ORDER BY id ASC",
                $exam_id
            ),
            ARRAY_A
        );

        $question_ids = array_values(array_filter(array_map('intval', wp_list_pluck((array) $questions, 'id')), static function ($id): bool {
            return $id > 0;
        }));
        $options_map = [];
        if (!empty($question_ids)) {
            $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
            $option_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id IN ({$placeholders})
                     ORDER BY question_id ASC, id ASC",
                    ...$question_ids
                ),
                ARRAY_A
            );

            foreach ((array) $option_rows as $option_row) {
                $question_id = (int) ($option_row['question_id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }
                if (!isset($options_map[$question_id])) {
                    $options_map[$question_id] = [];
                }
                $options_map[$question_id][] = $option_row;
            }
        }

        $question_type_labels = [
            'multiple_choice' => 'Multiple Choice',
            'multiple_answer' => 'Multiple Answer',
            'true_false' => 'True/False',
            'true_false_matrix' => 'True/False Matrix',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
        ];
        $question_type_counts = [];
        $total_points = 0.0;
        foreach ((array) $questions as $question_row) {
            $type = (string) ($question_row['question_type'] ?? '');
            if (!isset($question_type_counts[$type])) {
                $question_type_counts[$type] = 0;
            }
            $question_type_counts[$type] += 1;
            $total_points += (float) ($question_row['points'] ?? 0);
        }

        $question_count = count((array) $questions);
        $schedule_parts = [];
        if (!empty($exam['starts_at'])) {
            $schedule_parts[] = 'Mulai: ' . (string) $exam['starts_at'];
        }
        if (!empty($exam['ends_at'])) {
            $schedule_parts[] = 'Selesai: ' . (string) $exam['ends_at'];
        }
        $schedule_text = !empty($schedule_parts) ? implode(' | ', $schedule_parts) : '-';
        ?>
        <div class="wrap cbt-admin-exam-preview-wrap">
            <div class="cbt-admin-exam-preview-topbar">
                <div>
                    <h1>Preview Soal Exam</h1>
                    <p class="description">Validasi cepat isi soal dan kunci jawaban sebelum exam dijalankan.</p>
                </div>
                <div class="cbt-admin-exam-preview-actions">
                    <a class="button" href="<?php echo esc_url($back_url); ?>">Kembali ke Daftar Exam</a>
                </div>
            </div>

            <section class="cbt-admin-exam-preview-meta">
                <div class="cbt-admin-exam-preview-meta-item cbt-admin-exam-preview-meta-main">
                    <div class="cbt-admin-meta-kicker">Judul Exam</div>
                    <div class="cbt-admin-meta-title"><?php echo esc_html((string) ($exam['title'] ?? '')); ?></div>
                    <div class="cbt-admin-meta-subtitle">
                        <?php echo esc_html((string) ($exam['subject_name'] ?? '-')); ?>
                        | Status: <?php echo esc_html((string) ($exam['status'] ?? 'draft')); ?>
                    </div>
                </div>
                <div class="cbt-admin-exam-preview-meta-item">
                    <div class="cbt-admin-meta-kicker">Jumlah Soal</div>
                    <div class="cbt-admin-meta-value"><?php echo esc_html((string) $question_count); ?></div>
                </div>
                <div class="cbt-admin-exam-preview-meta-item">
                    <div class="cbt-admin-meta-kicker">Total Poin</div>
                    <div class="cbt-admin-meta-value"><?php echo esc_html(number_format($total_points, 2)); ?></div>
                </div>
                <div class="cbt-admin-exam-preview-meta-item">
                    <div class="cbt-admin-meta-kicker">Jadwal</div>
                    <div class="cbt-admin-meta-inline"><?php echo esc_html($schedule_text); ?></div>
                </div>
            </section>

            <?php if (!empty($question_type_counts)): ?>
                <div class="cbt-admin-exam-preview-type-chips">
                    <?php foreach ($question_type_counts as $question_type => $question_type_count): ?>
                        <?php
                        if ((int) $question_type_count <= 0) {
                            continue;
                        }
                        $type_label = (string) ($question_type_labels[$question_type] ?? ucwords(str_replace('_', ' ', $question_type)));
                        ?>
                        <span class="cbt-admin-type-chip">
                            <?php echo esc_html($type_label); ?>: <?php echo esc_html((string) $question_type_count); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($questions)): ?>
                <div class="notice notice-warning"><p>Exam ini belum memiliki soal.</p></div>
            <?php else: ?>
                <div class="cbt-admin-review-list">
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                        $question_id = (int) ($question['id'] ?? 0);
                        $question_type = (string) ($question['question_type'] ?? '');
                        $type_label = (string) ($question_type_labels[$question_type] ?? ucwords(str_replace('_', ' ', $question_type)));
                        $points_text = number_format((float) ($question['points'] ?? 0), 2);
                        $options = (array) ($options_map[$question_id] ?? []);
                        $question_detail = self::get_question_type_detail($question_id, $question_type);

                        if ($question_type === 'true_false' && !empty($options)) {
                            $has_correct = false;
                            foreach ($options as $option_row) {
                                if ((int) ($option_row['is_correct'] ?? 0) === 1) {
                                    $has_correct = true;
                                    break;
                                }
                            }

                            if (!$has_correct && isset($question_detail['correct_value'])) {
                                $expected = (int) $question_detail['correct_value'];
                                foreach ($options as $opt_index => $option_row) {
                                    $option_value = self::normalize_true_false_value((string) ($option_row['option_text'] ?? ''));
                                    if ($option_value === $expected) {
                                        $options[$opt_index]['is_correct'] = 1;
                                    }
                                }
                            }
                        }

                        $short_answer_values = [];
                        if ($question_type === 'short_answer') {
                            $short_answer_values = self::normalize_short_answer_values(
                                (string) ($question_detail['correct_text'] ?? ($question['correct_text'] ?? ''))
                            );
                        }

                        $essay_rubric = '';
                        if ($question_type === 'essay') {
                            $essay_rubric = trim((string) ($question_detail['rubric_text'] ?? ($question['correct_text'] ?? '')));
                        }

                        $fallback_answer = trim((string) ($question['correct_text'] ?? ''));
                        ?>
                        <article class="cbt-admin-review-item">
                            <header class="cbt-admin-review-item-head">
                                <div>
                                    <h3>Soal <?php echo esc_html((string) ($index + 1)); ?></h3>
                                    <p class="cbt-admin-review-type"><?php echo esc_html($type_label); ?></p>
                                </div>
                                <span class="cbt-admin-review-points">Poin <?php echo esc_html($points_text); ?></span>
                            </header>

                            <div class="cbt-admin-review-question">
                                <?php echo wp_kses_post((string) ($question['question_text'] ?? '')); ?>
                            </div>

                            <?php if (!empty($options)): ?>
                                <div class="cbt-admin-review-options">
                                    <?php foreach ($options as $option_index => $option): ?>
                                        <?php
                                        $is_correct = ((int) ($option['is_correct'] ?? 0) === 1);
                                        $option_key = strtoupper(trim((string) ($option['option_key'] ?? '')));
                                        if ($option_key === '') {
                                            $option_key = chr(65 + ($option_index % 26));
                                        }
                                        ?>
                                        <div class="cbt-admin-review-option<?php echo $is_correct ? ' is-correct' : ''; ?>">
                                            <div class="cbt-admin-review-option-main">
                                                <span class="cbt-admin-option-key"><?php echo esc_html($option_key); ?></span>
                                                <span class="cbt-admin-option-label"><?php echo wp_kses_post((string) ($option['option_text'] ?? '')); ?></span>
                                            </div>
                                            <?php if ($is_correct): ?>
                                                <span class="cbt-admin-review-badge">Kunci</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($question_type === 'short_answer'): ?>
                                <div class="cbt-admin-review-short-answer">
                                    <strong>Kunci Jawaban:</strong>
                                    <div class="cbt-admin-review-chip-list">
                                        <?php if (!empty($short_answer_values)): ?>
                                            <?php foreach ($short_answer_values as $short_answer): ?>
                                                <span class="cbt-admin-review-chip"><?php echo esc_html($short_answer); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="cbt-admin-review-empty">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($question_type === 'essay'): ?>
                                <div class="cbt-admin-review-essay">
                                    <strong>Acuan/Rubrik:</strong>
                                    <div class="cbt-admin-review-text">
                                        <?php if ($essay_rubric !== ''): ?>
                                            <?php echo wp_kses_post($essay_rubric); ?>
                                        <?php else: ?>
                                            <span class="cbt-admin-review-empty">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($fallback_answer !== ''): ?>
                                <div class="cbt-admin-review-essay">
                                    <strong>Kunci Jawaban:</strong>
                                    <div class="cbt-admin-review-text"><?php echo wp_kses_post($fallback_answer); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (trim((string) ($question['explanation'] ?? '')) !== ''): ?>
                                <div class="cbt-admin-review-explanation">
                                    <strong>Pembahasan:</strong>
                                    <?php echo wp_kses_post((string) $question['explanation']); ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .cbt-admin-exam-preview-wrap {
                max-width: 1180px;
            }
            .cbt-admin-exam-preview-topbar {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }
            .cbt-admin-exam-preview-actions {
                display: flex;
                gap: 8px;
            }
            .cbt-admin-exam-preview-meta {
                display: grid;
                grid-template-columns: 2fr repeat(3, minmax(160px, 1fr));
                gap: 10px;
                margin-bottom: 10px;
            }
            .cbt-admin-exam-preview-meta-item {
                border: 1px solid #d9e4f2;
                border-radius: 12px;
                background: #f8fbff;
                padding: 12px 14px;
            }
            .cbt-admin-exam-preview-meta-main {
                background: linear-gradient(120deg, #eaf4ff, #f4f9ff);
            }
            .cbt-admin-meta-kicker {
                font-size: 11px;
                line-height: 1.2;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #5f6b7a;
                font-weight: 700;
            }
            .cbt-admin-meta-title {
                margin-top: 6px;
                font-size: 19px;
                line-height: 1.35;
                color: #0f172a;
                font-weight: 700;
            }
            .cbt-admin-meta-subtitle {
                margin-top: 4px;
                font-size: 13px;
                color: #475569;
            }
            .cbt-admin-meta-value {
                margin-top: 6px;
                font-size: 24px;
                line-height: 1;
                font-weight: 800;
                color: #0f4c81;
            }
            .cbt-admin-meta-inline {
                margin-top: 6px;
                font-size: 13px;
                line-height: 1.45;
                color: #334155;
            }
            .cbt-admin-exam-preview-type-chips {
                margin-bottom: 12px;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .cbt-admin-type-chip {
                display: inline-flex;
                align-items: center;
                border: 1px solid #c9dbef;
                border-radius: 999px;
                background: #f4f9ff;
                color: #124e81;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
                padding: 6px 11px;
            }
            .cbt-admin-review-list {
                display: grid;
                gap: 12px;
            }
            .cbt-admin-review-item {
                border: 1px solid #d9e4f2;
                border-radius: 12px;
                background: #fff;
                padding: 14px;
                box-shadow: 0 8px 22px rgba(15, 76, 129, 0.08);
            }
            .cbt-admin-review-item-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 10px;
            }
            .cbt-admin-review-item-head h3 {
                margin: 0;
                font-size: 16px;
                line-height: 1.2;
                color: #0f172a;
            }
            .cbt-admin-review-type {
                margin: 5px 0 0;
                font-size: 12px;
                color: #607085;
                font-weight: 600;
            }
            .cbt-admin-review-points {
                display: inline-flex;
                align-items: center;
                border: 1px solid #bcd0e8;
                border-radius: 999px;
                background: #f7fbff;
                color: #145187;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                padding: 5px 10px;
                white-space: nowrap;
            }
            .cbt-admin-review-question {
                font-size: 15px;
                line-height: 1.65;
                color: #0f172a;
                font-weight: 600;
            }
            .cbt-admin-review-options {
                margin-top: 12px;
                display: grid;
                gap: 8px;
            }
            .cbt-admin-review-option {
                border: 1px solid #d9e4f2;
                border-radius: 10px;
                background: #fff;
                padding: 10px 12px;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
            }
            .cbt-admin-review-option.is-correct {
                border-color: #8dd5bc;
                background: #ecfbf4;
            }
            .cbt-admin-review-option-main {
                display: flex;
                align-items: flex-start;
                gap: 9px;
                min-width: 0;
            }
            .cbt-admin-option-key {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 24px;
                height: 24px;
                border-radius: 8px;
                background: #e9f3ff;
                color: #145187;
                font-size: 12px;
                font-weight: 800;
                line-height: 1;
                padding: 0 6px;
            }
            .cbt-admin-option-label {
                line-height: 1.55;
                color: #1e293b;
            }
            .cbt-admin-review-badge {
                display: inline-flex;
                align-items: center;
                border: 1px solid #8dd5bc;
                border-radius: 999px;
                background: #eafbf4;
                color: #0f7a56;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                line-height: 1;
                padding: 5px 8px;
                white-space: nowrap;
            }
            .cbt-admin-review-short-answer,
            .cbt-admin-review-essay {
                margin-top: 12px;
                display: grid;
                gap: 7px;
            }
            .cbt-admin-review-short-answer strong,
            .cbt-admin-review-essay strong {
                font-size: 12px;
                color: #334155;
            }
            .cbt-admin-review-chip-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .cbt-admin-review-chip {
                display: inline-flex;
                align-items: center;
                border: 1px solid #d0def2;
                border-radius: 8px;
                background: #eef4ff;
                color: #1e3a6f;
                font-size: 12px;
                line-height: 1.2;
                padding: 4px 8px;
            }
            .cbt-admin-review-text {
                border: 1px solid #d6deea;
                border-radius: 10px;
                background: #f8fafc;
                padding: 10px 12px;
                font-size: 14px;
                line-height: 1.6;
                color: #1f2937;
            }
            .cbt-admin-review-empty {
                color: #64748b;
                font-size: 13px;
            }
            .cbt-admin-review-explanation {
                margin-top: 12px;
                border-left: 3px solid #bfd8f4;
                background: #f7fbff;
                border-radius: 8px;
                padding: 10px 12px;
                font-size: 13px;
                line-height: 1.55;
                color: #334155;
            }
            @media (max-width: 1024px) {
                .cbt-admin-exam-preview-meta {
                    grid-template-columns: repeat(2, minmax(160px, 1fr));
                }
                .cbt-admin-exam-preview-meta-main {
                    grid-column: 1 / -1;
                }
            }
            @media (max-width: 782px) {
                .cbt-admin-exam-preview-meta {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    public static function render_tokens_page(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';

        $global_token_meta = CBT_Auth::get_global_exam_token(true);
        $global_token_value = (string) ($global_token_meta['token'] ?? '');
        $global_token_refresh_minutes = (int) ($global_token_meta['refresh_minutes'] ?? 15);
        $global_token_next_refresh_at = (int) ($global_token_meta['next_refresh_at'] ?? 0);
        $global_token_remaining_seconds = (int) ($global_token_meta['remaining_seconds'] ?? 0);
        $global_token_frontend_auto_apply = (int) ($global_token_meta['frontend_auto_apply'] ?? 0);
        ?>
        <div class="wrap">
            <h1>CBT Tokens</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <h2>Token Ujian Global</h2>
            <p class="description">Satu token berlaku untuk semua exam. Token otomatis berubah sesuai interval refresh.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0 0 16px;">
                <?php wp_nonce_field('cbt_save_global_exam_token'); ?>
                <input type="hidden" name="action" value="cbt_save_global_exam_token" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cbt-global-exam-token">Token Aktif</label></th>
                        <td>
                            <input type="text" id="cbt-global-exam-token" name="global_exam_token" class="regular-text" maxlength="6" value="<?php echo esc_attr($global_token_value); ?>" />
                            <p class="description">
                                Gunakan tepat 6 karakter huruf/angka, tanpa <code>0</code>, <code>O</code>, <code>I</code>, <code>L</code>.
                            </p>
                            <p class="description">
                                Berikutnya refresh: <?php echo esc_html($global_token_next_refresh_at > 0 ? wp_date('Y-m-d H:i:s', $global_token_next_refresh_at) : '-'); ?>
                                <?php if ($global_token_remaining_seconds > 0): ?>
                                    (<?php echo esc_html((string) ceil($global_token_remaining_seconds / 60)); ?> menit lagi)
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-global-token-refresh">Interval Refresh</label></th>
                        <td>
                            <select id="cbt-global-token-refresh" name="global_exam_token_refresh_minutes">
                                <?php for ($minute = 5; $minute <= 60; $minute += 5): ?>
                                    <option value="<?php echo (int) $minute; ?>" <?php selected($global_token_refresh_minutes, $minute); ?>>
                                        <?php echo esc_html((string) $minute); ?> menit
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">Pilihan: kelipatan 5 menit sampai 60 menit.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-global-token-frontend-auto">Frontend Auto Token</label></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    id="cbt-global-token-frontend-auto"
                                    name="global_exam_token_frontend_auto_apply"
                                    value="1"
                                    <?php checked($global_token_frontend_auto_apply, 1); ?>
                                />
                                Aktifkan token otomatis di frontend (siswa tidak perlu isi token manual)
                            </label>
                            <p class="description">
                                Jika aktif dan token global tersedia, sistem frontend akan mengisi token otomatis saat siswa menekan tombol mulai ujian.
                            </p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary" name="token_mode" value="save">Simpan Pengaturan Token</button>
                    <button type="submit" class="button button-secondary" name="token_mode" value="regenerate">Generate Ulang Sekarang</button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function render_setup_page(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        wp_enqueue_media();

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $branding = self::get_setup_branding_settings();
        $school_name = (string) ($branding['school_name'] ?? '');
        $logo_attachment_id = (int) ($branding['logo_attachment_id'] ?? 0);
        $logo_url = '';
        if ($logo_attachment_id > 0) {
            $resolved_logo_url = wp_get_attachment_image_url($logo_attachment_id, 'medium');
            if (is_string($resolved_logo_url)) {
                $logo_url = $resolved_logo_url;
            }
        }
        ?>
        <div class="wrap">
            <h1>CBT Setup</h1>
            <p class="description">Atur branding frontend CBT untuk topbar dan hero login.</p>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:860px;">
                <?php wp_nonce_field('cbt_save_setup_branding'); ?>
                <input type="hidden" name="action" value="cbt_save_setup_branding" />
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th><label for="cbt-setup-school-name">Nama Sekolah CBT</label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="cbt-setup-school-name"
                                name="school_name"
                                value="<?php echo esc_attr($school_name); ?>"
                                placeholder="<?php echo esc_attr((string) get_bloginfo('name')); ?>"
                            />
                            <p class="description">Jika kosong, otomatis memakai nama situs WordPress.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-setup-logo-attachment-id">Logo Sekolah</label></th>
                        <td>
                            <input
                                type="hidden"
                                id="cbt-setup-logo-attachment-id"
                                name="logo_attachment_id"
                                value="<?php echo esc_attr($logo_attachment_id > 0 ? (string) $logo_attachment_id : ''); ?>"
                            />
                            <div
                                id="cbt-setup-logo-preview"
                                style="width:180px; min-height:84px; margin:0 0 10px; border:1px solid #d0d7e2; border-radius:10px; background:#f8fafc; display:<?php echo $logo_url !== '' ? 'inline-flex' : 'none'; ?>; align-items:center; justify-content:center; padding:10px;"
                            >
                                <img
                                    id="cbt-setup-logo-preview-image"
                                    src="<?php echo esc_url($logo_url); ?>"
                                    alt=""
                                    style="display:block; max-width:100%; max-height:70px; object-fit:contain;"
                                />
                            </div>
                            <p id="cbt-setup-logo-empty" class="description" style="margin:0 0 10px; display:<?php echo $logo_url === '' ? 'block' : 'none'; ?>;">Belum ada logo dipilih.</p>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <button type="button" id="cbt-setup-logo-pick" class="button">
                                    <?php echo esc_html($logo_attachment_id > 0 ? 'Ganti Logo' : 'Pilih Logo'); ?>
                                </button>
                                <button type="button" id="cbt-setup-logo-remove" class="button button-secondary" style="display:<?php echo $logo_attachment_id > 0 ? 'inline-flex' : 'none'; ?>;">Hapus Logo</button>
                            </div>
                            <p class="description">Gunakan gambar dari Media Library WordPress.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary">Simpan Setup Branding</button>
                </p>
            </form>
        </div>
        <script>
            (function () {
                var mediaFrame = null;
                var logoInput = document.getElementById('cbt-setup-logo-attachment-id');
                var previewWrap = document.getElementById('cbt-setup-logo-preview');
                var previewImage = document.getElementById('cbt-setup-logo-preview-image');
                var emptyState = document.getElementById('cbt-setup-logo-empty');
                var pickButton = document.getElementById('cbt-setup-logo-pick');
                var removeButton = document.getElementById('cbt-setup-logo-remove');

                if (!pickButton || !removeButton) {
                    return;
                }

                function setLogoState(attachmentId, imageUrl) {
                    var hasLogo = attachmentId > 0 && String(imageUrl || '').trim() !== '';
                    if (logoInput) {
                        logoInput.value = hasLogo ? String(attachmentId) : '';
                    }
                    if (previewImage) {
                        previewImage.src = hasLogo ? String(imageUrl) : '';
                    }
                    if (previewWrap) {
                        previewWrap.style.display = hasLogo ? 'inline-flex' : 'none';
                    }
                    if (emptyState) {
                        emptyState.style.display = hasLogo ? 'none' : 'block';
                    }
                    if (pickButton) {
                        pickButton.textContent = hasLogo ? 'Ganti Logo' : 'Pilih Logo';
                    }
                    if (removeButton) {
                        removeButton.style.display = hasLogo ? 'inline-flex' : 'none';
                    }
                }

                pickButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (typeof wp === 'undefined' || !wp.media) {
                        window.alert('Media Library belum siap. Coba refresh halaman ini.');
                        return;
                    }

                    if (!mediaFrame) {
                        mediaFrame = wp.media({
                            title: 'Pilih Logo Sekolah',
                            button: { text: 'Gunakan Logo' },
                            multiple: false,
                            library: { type: 'image' }
                        });
                        mediaFrame.on('select', function () {
                            var selection = mediaFrame.state().get('selection').first();
                            if (!selection) {
                                return;
                            }

                            var payload = selection.toJSON();
                            var imageUrl = '';
                            if (payload.sizes && payload.sizes.medium && payload.sizes.medium.url) {
                                imageUrl = payload.sizes.medium.url;
                            } else if (payload.url) {
                                imageUrl = payload.url;
                            }
                            setLogoState(parseInt(payload.id, 10) || 0, imageUrl);
                        });
                    }
                    mediaFrame.open();
                });

                removeButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    setLogoState(0, '');
                });
            })();
        </script>
        <?php
    }

    public static function render_maintenance_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $reset_progress_token = isset($_GET['cbt_reset_progress_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_reset_progress_token'])) : '';
        $reset_progress_state = null;
        $reset_progress_total = 0;
        $reset_progress_processed = 0;
        $reset_progress_deleted_users = 0;
        $reset_progress_failed_tables = 0;
        $reset_progress_percent = 0.0;
        $reset_progress_is_running = false;
        $reset_progress_phase_label = '';
        $reset_progress_continue_url = '';
        if ($reset_progress_token !== '') {
            $reset_progress_state = self::get_reset_progress_state_for_current_user($reset_progress_token);
            if (is_array($reset_progress_state)) {
                $reset_progress_total = max(1, isset($reset_progress_state['total_units']) ? (int) $reset_progress_state['total_units'] : 1);
                $reset_progress_processed = max(0, isset($reset_progress_state['processed_units']) ? (int) $reset_progress_state['processed_units'] : 0);
                if ($reset_progress_processed > $reset_progress_total) {
                    $reset_progress_processed = $reset_progress_total;
                }
                $reset_progress_deleted_users = max(0, isset($reset_progress_state['deleted_user_count']) ? (int) $reset_progress_state['deleted_user_count'] : 0);
                $reset_progress_failed_tables = count((array) ($reset_progress_state['failed_tables'] ?? []));
                $reset_phase = sanitize_key((string) ($reset_progress_state['phase'] ?? 'tables'));
                $phase_labels = [
                    'tables' => 'Mengosongkan tabel CBT',
                    'users' => 'Menghapus user CBT',
                    'finalize' => 'Finalisasi reset',
                ];
                $reset_progress_phase_label = $phase_labels[$reset_phase] ?? 'Memproses reset database';
                $reset_progress_percent = $reset_progress_total > 0
                    ? round(((float) $reset_progress_processed / (float) $reset_progress_total) * 100, 2)
                    : 0.0;
                $reset_progress_is_running = $reset_progress_processed < $reset_progress_total;
                $reset_progress_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_reset_database',
                        'cbt_reset_progress_token' => $reset_progress_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi reset database tidak ditemukan atau sudah berakhir. Silakan mulai ulang reset.';
            }
        }
        ?>
        <div class="wrap">
            <h1>CBT Maintenance</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if (is_array($reset_progress_state)): ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Progress Reset Database:</strong>
                        <?php echo esc_html((string) $reset_progress_processed . ' / ' . (string) $reset_progress_total); ?>
                        (<?php echo esc_html(number_format($reset_progress_percent, 2)); ?>%)
                        | Tahap: <?php echo esc_html($reset_progress_phase_label); ?>
                        | User terhapus: <?php echo esc_html((string) $reset_progress_deleted_users); ?>
                        | Tabel gagal: <?php echo esc_html((string) $reset_progress_failed_tables); ?>
                    </p>
                </div>
                <div style="max-width:760px; margin:0 0 14px; border:1px solid #c3c4c7; border-radius:8px; background:#fff; padding:12px;">
                    <div style="width:100%; height:14px; border-radius:999px; background:#f0f0f1; overflow:hidden; border:1px solid #dcdcde;">
                        <div style="height:100%; width: <?php echo esc_attr((string) $reset_progress_percent); ?>%; background:linear-gradient(90deg,#2271b1,#135e96); transition:width .25s ease;"></div>
                    </div>
                    <div style="margin-top:10px;">
                        <?php if ($reset_progress_is_running): ?>
                            <span style="color:#1d2327;">Memproses reset database batch berikutnya...</span>
                            <script>
                                window.setTimeout(function () {
                                    window.location.href = <?php echo wp_json_encode($reset_progress_continue_url); ?>;
                                }, 350);
                            </script>
                        <?php else: ?>
                            <span style="color:#0a7a2f; font-weight:600;">Reset database selesai diproses.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="max-width:980px; margin:16px 0; padding:16px 18px; border:1px solid #dcdcde; background:#fff;">
                <h2 style="margin-top:0;">Repair Link Soal Bank</h2>
                <p style="margin:0 0 8px;">Aksi ini memasangkan salinan soal exam lama yang belum punya <code>source_question_id</code> ke soal sumber di <code>CBT Bank Soal</code>, lalu menyinkronkan konten soal exam ke versi bank terbaru.</p>
                <p style="margin:0 0 8px;"><strong>Aman untuk data ujian:</strong> aksi ini tidak menghapus exam atau attempt. Cache exam terdampak akan di-invalidate agar peserta aktif mengambil versi terbaru.</p>
                <p style="margin:0 0 12px;"><strong>Kapan dipakai:</strong> setelah upgrade fitur sinkronisasi bank soal, terutama jika sebelumnya Anda sudah punya banyak exam hasil salin dari bank soal.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Jalankan backfill linkage soal bank sekarang?');">
                    <?php wp_nonce_field('cbt_backfill_question_sources'); ?>
                    <input type="hidden" name="action" value="cbt_backfill_question_sources" />
                    <button type="submit" class="button button-secondary">Jalankan Backfill Link Soal Bank</button>
                </form>
            </div>

            <div class="notice notice-warning" style="margin: 16px 0;">
                <p><strong>Peringatan:</strong> aksi di bawah akan menghapus data CBT secara permanen dari database.</p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Yakin reset data CBT? Aksi ini tidak bisa dibatalkan.');">
                <?php wp_nonce_field('cbt_reset_database'); ?>
                <input type="hidden" name="action" value="cbt_reset_database" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Reset tabel CBT</th>
                        <td>
                            <p>Semua data tabel plugin CBT akan dikosongkan (subjects, exams, questions, attempts, answers, options, dan pengaturan token global).</p>
                            <p class="description">Progress reset akan ditampilkan otomatis sampai proses selesai.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cbt-reset-confirm-phrase">Konfirmasi wajib</label></th>
                        <td>
                            <input
                                type="text"
                                id="cbt-reset-confirm-phrase"
                                name="confirm_phrase"
                                class="regular-text"
                                placeholder="Ketik: RESET CBT"
                                autocomplete="off"
                                required
                            />
                            <p class="description">Ketik persis <code>RESET CBT</code> untuk melanjutkan.</p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">Reset Database CBT</button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function render_cache_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $overview = CBT_Cache::get_admin_overview();
        $health = isset($overview['health']) && is_array($overview['health']) ? $overview['health'] : [];
        $namespaces = isset($overview['namespaces']) && is_array($overview['namespaces']) ? $overview['namespaces'] : [];
        $locks = isset($overview['locks']) && is_array($overview['locks']) ? $overview['locks'] : [];
        $ui_states = isset($overview['ui_states']) && is_array($overview['ui_states']) ? $overview['ui_states'] : [];
        $ttl_reference = isset($overview['ttl_reference']) && is_array($overview['ttl_reference']) ? $overview['ttl_reference'] : [];
        $redis_config = isset($health['redis_config']) && is_array($health['redis_config']) ? $health['redis_config'] : [];
        $server_probe = isset($health['server_probe']) && is_array($health['server_probe']) ? $health['server_probe'] : [];
        $probe = isset($health['probe']) && is_array($health['probe']) ? $health['probe'] : [];
        $runtime_buffer = isset($health['runtime_buffer']) && is_array($health['runtime_buffer']) ? $health['runtime_buffer'] : [];
        $runtime_buffer_config = isset($runtime_buffer['config']) && is_array($runtime_buffer['config']) ? $runtime_buffer['config'] : [];
        $runtime_buffer_probe = isset($runtime_buffer['probe']) && is_array($runtime_buffer['probe']) ? $runtime_buffer['probe'] : [];
        $readiness = (string) ($health['readiness'] ?? 'fallback');
        $readiness_meta = self::cache_readiness_meta($readiness);
        $server_probe_meta = self::cache_server_probe_meta((string) ($server_probe['status'] ?? 'skipped'));
        $probe_meta = self::cache_probe_meta((string) ($probe['status'] ?? 'skipped'));
        $runtime_probe_meta = self::cache_probe_meta((string) ($runtime_buffer_probe['status'] ?? 'skipped'));
        $namespace_prune_after = (int) CBT_Cache::namespace_prune_retention_seconds();
        $namespace_prune_label = human_time_diff(max(0, time() - $namespace_prune_after), time());
        $next_steps = self::cache_next_steps($health);
        $show_redis_rollback = self::should_render_redis_rollback_action();
        $namespace_group_meta = self::cache_namespace_group_meta();
        $namespace_group_resolver = static function (string $namespace_name): string {
            $namespace_name = trim($namespace_name);
            if ($namespace_name === '') {
                return '';
            }

            if ($namespace_name === '__global__') {
                return '__global__';
            }

            $parts = explode(':', $namespace_name, 2);
            return sanitize_key((string) ($parts[0] ?? ''));
        };
        $namespace_filter = isset($_GET['cbt_namespace_filter'])
            ? sanitize_text_field(wp_unslash($_GET['cbt_namespace_filter']))
            : '';
        $namespace_filter_options = [];
        foreach ($namespaces as $namespace_entry) {
            $namespace_group = $namespace_group_resolver((string) ($namespace_entry['namespace'] ?? ''));
            if ($namespace_group === '') {
                continue;
            }
            $namespace_filter_options[$namespace_group] = $namespace_group;
        }
        $namespace_filter_options = array_values($namespace_filter_options);
        sort($namespace_filter_options, SORT_NATURAL | SORT_FLAG_CASE);
        if ($namespace_filter !== '' && !in_array($namespace_filter, $namespace_filter_options, true)) {
            $namespace_filter = '';
        }
        if ($namespace_filter !== '') {
            $namespaces = array_values(array_filter($namespaces, static function ($namespace_entry) use ($namespace_filter, $namespace_group_resolver): bool {
                return $namespace_group_resolver((string) ($namespace_entry['namespace'] ?? '')) === $namespace_filter;
            }));
        }
        $namespace_per_page = isset($_GET['cbt_namespace_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash($_GET['cbt_namespace_per_page'])))
            : 20;
        $namespace_current_page = isset($_GET['cbt_namespace_paged'])
            ? max(1, absint(wp_unslash($_GET['cbt_namespace_paged'])))
            : 1;
        $namespace_total = count($namespaces);
        $namespace_total_all = count($namespace_filter_options);
        $namespace_total_pages = max(1, (int) ceil($namespace_total / max(1, $namespace_per_page)));
        if ($namespace_total > 0 && $namespace_current_page > $namespace_total_pages) {
            $namespace_current_page = $namespace_total_pages;
        }
        $namespace_offset = ($namespace_current_page - 1) * $namespace_per_page;
        $visible_namespaces = array_slice($namespaces, $namespace_offset, $namespace_per_page);
        $namespace_pagination_links = [];
        $show_stale_locks = isset($_GET['cbt_lock_show_stale'])
            ? (absint(wp_unslash($_GET['cbt_lock_show_stale'])) === 1)
            : false;
        $lock_per_page = isset($_GET['cbt_lock_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash($_GET['cbt_lock_per_page'])))
            : 20;
        $lock_current_page = isset($_GET['cbt_lock_paged'])
            ? max(1, absint(wp_unslash($_GET['cbt_lock_paged'])))
            : 1;
        $active_locks = [];
        $stale_locks = [];
        foreach ($locks as $lock_entry) {
            if (!empty($lock_entry['is_stale'])) {
                $stale_locks[] = $lock_entry;
                continue;
            }
            $active_locks[] = $lock_entry;
        }
        $visible_lock_source = $show_stale_locks ? $locks : $active_locks;
        $lock_total = count($visible_lock_source);
        $stale_lock_total = count($stale_locks);
        $active_lock_total = count($active_locks);
        $lock_total_pages = max(1, (int) ceil($lock_total / max(1, $lock_per_page)));
        if ($lock_total > 0 && $lock_current_page > $lock_total_pages) {
            $lock_current_page = $lock_total_pages;
        }
        $lock_offset = ($lock_current_page - 1) * $lock_per_page;
        $visible_locks = array_slice($visible_lock_source, $lock_offset, $lock_per_page);
        $lock_pagination_links = [];
        if ($namespace_total_pages > 1) {
            $namespace_pagination_args = [
                'page' => 'cbt-cache',
                'cbt_namespace_per_page' => $namespace_per_page,
                'cbt_namespace_paged' => '%#%',
                'cbt_lock_per_page' => $lock_per_page,
                'cbt_lock_show_stale' => $show_stale_locks ? 1 : 0,
            ];
            if ($namespace_filter !== '') {
                $namespace_pagination_args['cbt_namespace_filter'] = $namespace_filter;
            }
            $namespace_pagination_links = paginate_links([
                'base' => add_query_arg($namespace_pagination_args, admin_url('admin.php')),
                'format' => '',
                'current' => $namespace_current_page,
                'total' => $namespace_total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }
        if ($lock_total_pages > 1) {
            $lock_pagination_links = paginate_links([
                'base' => add_query_arg(
                    [
                        'page' => 'cbt-cache',
                        'cbt_namespace_per_page' => $namespace_per_page,
                        'cbt_namespace_paged' => $namespace_current_page,
                        'cbt_lock_per_page' => $lock_per_page,
                        'cbt_lock_show_stale' => $show_stale_locks ? 1 : 0,
                        'cbt_lock_paged' => '%#%',
                    ],
                    admin_url('admin.php')
                ),
                'format' => '',
                'current' => $lock_current_page,
                'total' => $lock_total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }

        $user_ids = [];
        foreach ($ui_states as $ui_state) {
            $user_id = (int) ($ui_state['user_id'] ?? 0);
            if ($user_id > 0) {
                $user_ids[$user_id] = $user_id;
            }
        }
        $user_labels = [];
        if (!empty($user_ids)) {
            $users = get_users([
                'include' => array_values($user_ids),
                'fields' => ['ID', 'display_name', 'user_login'],
            ]);
            foreach ((array) $users as $user) {
                if (!($user instanceof WP_User)) {
                    continue;
                }
                $user_labels[(int) $user->ID] = trim((string) $user->display_name) !== ''
                    ? (string) $user->display_name . ' (' . $user->user_login . ')'
                    : (string) $user->user_login;
            }
        }
        ?>
        <div class="wrap">
            <h1>CBT Cache</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <div style="margin:16px 0 20px; padding:16px 18px; border:1px solid #dcdcde; border-left:6px solid <?php echo esc_attr($readiness_meta['accent']); ?>; background:#fff;">
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <h2 style="margin:0;">Redis Readiness</h2>
                    <span style="display:inline-block; padding:4px 10px; border-radius:999px; background:<?php echo esc_attr($readiness_meta['background']); ?>; color:<?php echo esc_attr($readiness_meta['accent']); ?>; font-weight:600;">
                        <?php echo esc_html($readiness_meta['label']); ?>
                    </span>
                </div>
                <p style="margin:12px 0 0;"><?php echo esc_html(self::cache_readiness_summary($health)); ?></p>
            </div>

            <?php if ($readiness === 'fallback'): ?>
                <div class="notice notice-warning">
                    <p><strong>Mode fallback aktif.</strong> Redis/object cache WordPress belum siap, jadi plugin CBT masih memakai transient WordPress untuk cache lintas request. Mode ini tetap aman sebagai fallback, tetapi bukan mode yang direkomendasikan untuk ujian serentak.</p>
                </div>
            <?php elseif ($readiness === 'partial'): ?>
                <div class="notice notice-warning">
                    <p><strong>Konfigurasi object cache belum lengkap.</strong> Ada sinyal setup Redis/object cache, tetapi CBT belum melihat runtime Redis yang benar-benar siap. Lanjutkan checklist pada halaman ini sampai status berubah menjadi <code>Ready</code>.</p>
                </div>
            <?php endif; ?>

            <h2>Configuration</h2>
            <table class="widefat striped" style="max-width:980px;">
                <tbody>
                <tr>
                    <th style="width:260px;">Readiness</th>
                    <td><code><?php echo esc_html($readiness); ?></code></td>
                </tr>
                <tr>
                    <th>WP_CACHE</th>
                    <td><?php echo self::cache_boolean_label(!empty($health['wp_cache_enabled'])); ?></td>
                </tr>
                <tr>
                    <th>Object Cache Active</th>
                    <td><?php echo !empty($health['object_cache_active']) ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <th>Drop-in object-cache.php</th>
                    <td><?php echo !empty($health['object_cache_dropin_present']) ? 'Detected' : 'Not detected'; ?></td>
                </tr>
                <tr>
                    <th>Runtime Mode</th>
                    <td><code><?php echo esc_html((string) ($health['runtime_mode'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Backend Hint</th>
                    <td><code><?php echo esc_html((string) ($health['backend_hint'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Cache Group</th>
                    <td><code><?php echo esc_html((string) ($health['cache_group'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Host</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($redis_config['host'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Port</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($redis_config['port'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Database</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($redis_config['database'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Prefix</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($redis_config['prefix'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Scheme</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($redis_config['scheme'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Client</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($redis_config['client'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Password Configured</th>
                    <td><?php echo self::cache_boolean_label(!empty($redis_config['password_configured'])); ?></td>
                </tr>
                <tr>
                    <th>WP_REDIS_DISABLED</th>
                    <td>
                        <?php
                        echo array_key_exists('disabled', $redis_config) && $redis_config['disabled'] !== null
                            ? self::cache_boolean_label(!empty($redis_config['disabled']))
                            : '-';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Redis Server</th>
                    <td>
                        <span style="display:inline-block; padding:3px 9px; border-radius:999px; background:<?php echo esc_attr($server_probe_meta['background']); ?>; color:<?php echo esc_attr($server_probe_meta['accent']); ?>; font-weight:600;">
                            <?php echo esc_html($server_probe_meta['label']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Redis Endpoint</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($server_probe['endpoint'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Server Message</th>
                    <td><?php echo esc_html((string) ($server_probe['message'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Probe Status</th>
                    <td>
                        <span style="display:inline-block; padding:3px 9px; border-radius:999px; background:<?php echo esc_attr($probe_meta['background']); ?>; color:<?php echo esc_attr($probe_meta['accent']); ?>; font-weight:600;">
                            <?php echo esc_html($probe_meta['label']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Probe Message</th>
                    <td><?php echo esc_html((string) ($probe['message'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Probe Tested At</th>
                    <td><?php echo !empty($probe['tested_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $probe['tested_at'])) : '-'; ?></td>
                </tr>
                <tr>
                    <th>Runtime Buffer Enabled</th>
                    <td><?php echo self::cache_boolean_label(!empty($runtime_buffer['enabled'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Buffer Ready</th>
                    <td><?php echo self::cache_boolean_label(!empty($runtime_buffer['ready'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Buffer Status</th>
                    <td><code><?php echo esc_html((string) ($runtime_buffer['status'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Fallback to DB</th>
                    <td><?php echo self::cache_boolean_label(!empty($runtime_buffer['fallback_to_db'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Redis Extension</th>
                    <td><?php echo self::cache_boolean_label(!empty($runtime_buffer['extension_available'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Redis Host</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($runtime_buffer_config['host'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Redis Port</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($runtime_buffer_config['port'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Redis Database</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($runtime_buffer_config['database'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Redis Prefix</th>
                    <td><code><?php echo esc_html(self::cache_scalar_label($runtime_buffer_config['prefix'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Probe Status</th>
                    <td>
                        <span style="display:inline-block; padding:3px 9px; border-radius:999px; background:<?php echo esc_attr($runtime_probe_meta['background']); ?>; color:<?php echo esc_attr($runtime_probe_meta['accent']); ?>; font-weight:600;">
                            <?php echo esc_html($runtime_probe_meta['label']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Runtime Message</th>
                    <td><?php echo esc_html((string) ($runtime_buffer['message'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Pending Flush Attempts</th>
                    <td><?php echo esc_html((string) ((int) ($runtime_buffer['pending_attempts'] ?? 0))); ?></td>
                </tr>
                <tr>
                    <th>Oldest Flush Age</th>
                    <td><?php echo esc_html((string) ((int) ($runtime_buffer['oldest_flush_age'] ?? 0))); ?>s</td>
                </tr>
                <tr>
                    <th>TTL Reference</th>
                    <td>
                        <?php foreach ($ttl_reference as $ttl_key => $ttl_value): ?>
                            <span style="display:inline-block; margin:0 12px 8px 0;"><code><?php echo esc_html((string) $ttl_key); ?></code>: <?php echo esc_html((string) $ttl_value); ?>s</span>
                        <?php endforeach; ?>
                    </td>
                </tr>
                </tbody>
            </table>

            <?php if ($readiness !== 'ready' && !empty($next_steps)): ?>
                <h2 style="margin-top:24px;">Next Steps</h2>
                <div style="max-width:980px; padding:16px 18px; border:1px solid #dcdcde; background:#fff;">
                    <ol style="margin:0; padding-left:20px;">
                        <?php foreach ($next_steps as $next_step): ?>
                            <li style="margin:0 0 8px;"><?php echo esc_html($next_step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if ($readiness !== 'ready'): ?>
                <h2 style="margin-top:24px;">One-Click Bootstrap</h2>
                <div style="max-width:980px; padding:16px 18px; border:1px solid #dcdcde; background:#fff;">
                    <p style="margin-top:0;">Tombol ini mencoba menyiapkan Redis dari sisi WordPress dalam satu aksi: menulis config ke <code>wp-config.php</code>, install/activate plugin <code>Redis Object Cache</code> jika environment mengizinkan, lalu enable <code>object-cache.php</code> hanya jika server Redis benar-benar reachable.</p>
                    <p>Yang tidak bisa dipaksa dari tombol ini: install service Redis OS dan memperbaiki permission filesystem server yang diblokir host.</p>
                    <h3 style="margin:18px 0 8px;">Ubuntu Quick Guide</h3>
                    <ol style="margin:0 0 12px; padding-left:20px;">
                        <li style="margin:0 0 8px;">Install Redis server, tools, dan ekstensi PHP Redis.</li>
                        <li style="margin:0 0 8px;">Aktifkan service Redis lalu cek sampai <code>PONG</code>.</li>
                        <li style="margin:0 0 8px;">Restart PHP-FPM/web server agar ekstensi <code>redis</code> terbaca oleh WordPress.</li>
                        <li style="margin:0;">Kembali ke halaman ini lalu klik <code>Bootstrap Redis Sekali Klik</code>.</li>
                    </ol>
                    <pre style="margin:0 0 12px; padding:12px; background:#f6f7f7; border:1px solid #dcdcde; overflow:auto;"><code><?php echo esc_html(
'sudo apt update
sudo apt install -y redis-server redis-tools php-redis
sudo systemctl enable --now redis-server
redis-cli ping
php -m | grep -i redis

# Deteksi versi PHP CLI aktif lalu restart PHP-FPM jika unitnya ada:
PHP_VER=$(php -v | head -n 1 | sed -E "s/^PHP ([0-9]+\.[0-9]+).*/\1/")
echo "Detected PHP version: ${PHP_VER}"
if systemctl list-unit-files "php${PHP_VER}-fpm.service" --no-legend 2>/dev/null | grep -q "php${PHP_VER}-fpm.service"; then
  sudo systemctl restart "php${PHP_VER}-fpm"
else
  echo "Service php${PHP_VER}-fpm tidak ditemukan. Cek unit PHP-FPM yang tersedia:"
  systemctl list-unit-files "php*-fpm.service" --no-legend 2>/dev/null || true
  echo "Jika kosong, kemungkinan server ini tidak memakai PHP-FPM."
fi
sudo systemctl restart nginx || sudo systemctl restart apache2'
                    ); ?></code></pre>
                    <p style="margin:0 0 12px;">Jika server bukan Ubuntu atau Anda tidak punya akses <code>sudo</code>, install service Redis tetap harus dilakukan dari level OS/panel server oleh admin sistem.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 0;">
                        <?php wp_nonce_field('cbt_cache_action'); ?>
                        <input type="hidden" name="action" value="cbt_cache_action" />
                        <input type="hidden" name="operation" value="bootstrap_redis" />
                        <button type="submit" class="button button-primary">Bootstrap Redis Sekali Klik</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($show_redis_rollback): ?>
                <h2 style="margin-top:24px;">Batalkan Redis</h2>
                <div style="max-width:980px; padding:16px 18px; border:1px solid #dcdcde; background:#fff;">
                    <p style="margin-top:0;">Aksi ini membatalkan integrasi Redis yang disiapkan dari sisi WordPress: menghapus blok konfigurasi CBT Redis di <code>wp-config.php</code>, menghapus <code>object-cache.php</code> jika itu drop-in Redis yang valid, lalu menonaktifkan plugin <code>Redis Object Cache</code>.</p>
                    <p>Aksi ini tidak menghentikan service Redis OS. Jika Redis server juga ingin dimatikan, lakukan dari level server secara manual.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 0;">
                        <?php wp_nonce_field('cbt_cache_action'); ?>
                        <input type="hidden" name="action" value="cbt_cache_action" />
                        <input type="hidden" name="operation" value="rollback_redis" />
                        <button type="submit" class="button button-secondary" onclick="return confirm('Batalkan integrasi Redis CBT dari WordPress?');">Batalkan Redis Sekali Klik</button>
                    </form>
                </div>
            <?php endif; ?>

            <h2 style="margin-top:24px;">Tools</h2>
            <div style="max-width:980px; margin:0 0 16px; padding:12px 14px; border:1px solid #dcdcde; background:#fff;">
                <p style="margin:0 0 8px;"><strong>Cara baca menu di bawah:</strong> kata <code>invalidate</code> berarti cache dibuang agar request berikutnya membaca data terbaru dan membangun cache ulang. Ini tidak menghapus data ujian di database.</p>
                <p style="margin:0;"><strong>Saran penggunaan:</strong> mulai dari scope paling kecil dulu, misalnya <code>Attempt</code> atau <code>User</code>. Pakai <code>Invalidate All CBT</code> hanya jika perubahan atau masalahnya sudah luas.</p>
            </div>
	            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; max-width:1180px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_all" />
                    <h3 style="margin-top:0;">Invalidate All CBT</h3>
                    <p style="margin:0 0 8px;">Naikkan versi semua namespace cache CBT tanpa menyentuh cache plugin/site lain.</p>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah perubahan besar pada exam, bank soal, token, atau saat sulit menentukan cache mana yang stale.</p>
                    <p style="margin:0 0 12px;"><strong>Dampak:</strong> paling luas. Request berikutnya akan membangun ulang hampir semua cache CBT.</p>
                    <button type="submit" class="button button-primary">Invalidate Semua Namespace CBT</button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_catalog" />
                    <h3 style="margin-top:0;">Catalog</h3>
                    <p style="margin:0 0 8px;">Refresh daftar exam/mapel/token global yang dipakai seluruh user.</p>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah tambah/edit/hapus exam, subject, atau token global yang tampil di banyak halaman.</p>
                    <p style="margin:0 0 12px;"><strong>Dampak:</strong> hanya area katalog global, tidak spesifik ke satu user atau satu attempt.</p>
                    <button type="submit" class="button button-secondary">Invalidate Catalog</button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_exam" />
                    <h3 style="margin-top:0;">Exam Namespace</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah edit soal, durasi, jadwal, token, atau setting untuk satu exam tertentu.</p>
                    <p style="margin:0 0 8px;"><strong>Contoh:</strong> jika exam ID <code>12</code> berubah, isi <code>12</code> lalu invalidate.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-exam-id">Exam ID</label></p>
                    <input type="number" min="1" id="cbt-cache-exam-id" name="exam_id" class="small-text" placeholder="contoh: 12" required />
                    <p><button type="submit" class="button button-secondary">Invalidate Exam</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_user" />
                    <h3 style="margin-top:0;">User Namespace</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> saat perubahan hanya terkait satu user, misalnya hak akses, role, assignment exam, atau data user terasa belum ter-refresh.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> hanya cache milik user tersebut, tidak menyentuh user lain.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-user-id">User ID</label></p>
                    <input type="number" min="1" id="cbt-cache-user-id" name="user_id" class="small-text" placeholder="contoh: 45" required />
                    <p><button type="submit" class="button button-secondary">Invalidate User</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_attempt" />
                    <h3 style="margin-top:0;">Attempt Namespace</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> jika satu attempt ujian macet, jawaban terasa tidak sinkron, atau state attempt perlu dipaksa baca ulang.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> paling sempit untuk data ujian. Aman dipakai saat masalahnya hanya pada satu peserta/satu attempt.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-attempt-id">Attempt ID</label></p>
                    <input type="number" min="1" id="cbt-cache-attempt-id" name="attempt_id" class="small-text" placeholder="contoh: 381" required />
                    <p><button type="submit" class="button button-secondary">Invalidate Attempt</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="clear_all_ui_state" />
                    <h3 style="margin-top:0;">UI State</h3>
                    <p style="margin:0 0 8px;">Hapus semua preferences dan attempt UI state CBT yang tersimpan di namespace plugin.</p>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah ubah UI/frontend CBT dan Anda ingin semua browser membangun state tampilan baru dari nol.</p>
                    <p style="margin:0 0 12px;"><strong>Dampak:</strong> preference tampilan, posisi navigasi, atau state UI akan di-reset. Ini tidak menghapus hasil ujian di database.</p>
                    <p><button type="submit" class="button button-secondary">Clear Semua UI State CBT</button></p>
                </form>

	                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding:16px; border:1px solid #dcdcde; background:#fff;">
	                    <?php wp_nonce_field('cbt_cache_action'); ?>
	                    <input type="hidden" name="action" value="cbt_cache_action" />
	                    <input type="hidden" name="operation" value="clear_attempt_ui_state" />
	                    <h3 style="margin-top:0;">Clear Attempt UI State</h3>
	                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> jika satu peserta melihat UI ujian aneh, navigasi macet, palette soal salah, atau timer terasa tidak sinkron.</p>
	                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> hanya state UI untuk satu attempt. Ini lebih aman daripada membersihkan semua UI state.</p>
	                    <p style="margin:0 0 6px;"><label for="cbt-cache-clear-attempt-id">Attempt ID</label></p>
	                    <input type="number" min="1" id="cbt-cache-clear-attempt-id" name="attempt_id" class="small-text" placeholder="contoh: 381" required />
	                    <p><button type="submit" class="button button-secondary">Clear UI State by Attempt</button></p>
	                </form>
	            </div>
	
	            <h2 style="margin-top:24px;">Namespaces</h2>
                <div style="max-width:980px; margin:0 0 12px; padding:12px 14px; border:1px solid #dcdcde; background:#fff;">
                    <p style="margin:0 0 8px;"><strong>Bagian ini adalah registry namespace cache CBT.</strong> Setiap namespace menyimpan versi cache untuk scope tertentu seperti <code>__global__</code>, <code>exam:{id}</code>, <code>user:{id}</code>, atau <code>attempt:{id}</code>.</p>
                    <p style="margin:0 0 8px;">Saat namespace di-<code>invalidate</code>, versinya naik supaya request berikutnya membangun cache baru. Ini tidak menghapus data ujian di database.</p>
                    <p style="margin:0 0 12px;"><strong>Auto-prune:</strong> namespace yang tidak disentuh lebih dari <?php echo esc_html($namespace_prune_label); ?> akan dibersihkan otomatis dari registry. Retention ini sengaja lebih lama dari TTL cache namespace CBT agar tidak memunculkan kembali cache lama.</p>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <p style="margin:0;"><strong>Catatan:</strong> jangan hapus namespace secara manual dari registry. Jika entri namespace hilang, versi akan fallback ke <code>1</code> dan pada object cache persisten ada risiko cache lama versi awal terlihat lagi. Gunakan aksi <code>Invalidate</code>, bukan hapus manual.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                            <?php wp_nonce_field('cbt_cache_action'); ?>
                            <input type="hidden" name="action" value="cbt_cache_action" />
                            <input type="hidden" name="operation" value="prune_old_namespaces" />
                            <button type="submit" class="button button-secondary">Prune Namespace Lama</button>
                        </form>
                    </div>
                </div>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:8px 0 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="cbt-cache" />
                    <input type="hidden" name="cbt_lock_per_page" value="<?php echo (int) $lock_per_page; ?>" />
                    <input type="hidden" name="cbt_lock_show_stale" value="<?php echo $show_stale_locks ? '1' : '0'; ?>" />
                    <label for="cbt-namespace-filter">Filter namespace</label>
                    <select id="cbt-namespace-filter" name="cbt_namespace_filter">
                        <option value="">Semua grup</option>
                        <?php foreach ($namespace_filter_options as $namespace_filter_option): ?>
                            <option value="<?php echo esc_attr($namespace_filter_option); ?>" <?php selected($namespace_filter, $namespace_filter_option); ?>>
                                <?php
                                $namespace_option_meta = isset($namespace_group_meta[$namespace_filter_option]) && is_array($namespace_group_meta[$namespace_filter_option])
                                    ? $namespace_group_meta[$namespace_filter_option]
                                    : [];
                                echo esc_html((string) ($namespace_option_meta['label'] ?? $namespace_filter_option));
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="cbt-namespace-per-page">Per halaman</label>
                    <select id="cbt-namespace-per-page" name="cbt_namespace_per_page">
                        <?php foreach ([20, 40, 60, 80, 100] as $namespace_per_page_option): ?>
                            <option value="<?php echo (int) $namespace_per_page_option; ?>" <?php selected($namespace_per_page, $namespace_per_page_option); ?>>
                                <?php echo esc_html((string) $namespace_per_page_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-secondary">Terapkan</button>
                    <?php if ($namespace_filter !== ''): ?>
                        <a class="button button-link" href="<?php echo esc_url(add_query_arg([
                            'page' => 'cbt-cache',
                            'cbt_namespace_per_page' => $namespace_per_page,
                            'cbt_lock_per_page' => $lock_per_page,
                            'cbt_lock_show_stale' => $show_stale_locks ? 1 : 0,
                        ], admin_url('admin.php'))); ?>">Reset Filter</a>
                    <?php endif; ?>
                </form>
                <div style="max-width:980px; margin:-4px 0 12px; padding:12px 14px; border:1px solid #dcdcde; background:#fff;">
                    <p style="margin:0 0 8px;"><strong>Arti tiap grup namespace:</strong></p>
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($namespace_filter_options as $namespace_filter_option): ?>
                            <?php
                            $namespace_option_meta = isset($namespace_group_meta[$namespace_filter_option]) && is_array($namespace_group_meta[$namespace_filter_option])
                                ? $namespace_group_meta[$namespace_filter_option]
                                : [];
                            $namespace_option_description = (string) ($namespace_option_meta['description'] ?? '');
                            if ($namespace_option_description === '') {
                                continue;
                            }
                            ?>
                            <li style="margin:0 0 6px;">
                                <code><?php echo esc_html($namespace_filter_option); ?></code>
                                : <?php echo esc_html($namespace_option_description); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($namespace_filter !== '' && !empty($namespace_group_meta[$namespace_filter]['description'])): ?>
                        <p style="margin:10px 0 0; padding-top:10px; border-top:1px solid #dcdcde;">
                            <strong>Filter aktif:</strong>
                            <code><?php echo esc_html($namespace_filter); ?></code>
                            : <?php echo esc_html((string) $namespace_group_meta[$namespace_filter]['description']); ?>
                        </p>
                    <?php endif; ?>
                </div>
	            <table class="widefat striped" style="max-width:980px;">
	                <thead>
	                <tr>
	                    <th>Namespace</th>
	                    <th>Version</th>
                    <th>Last Invalidated</th>
                </tr>
                </thead>
                <tbody>
	                <?php if (empty($visible_namespaces)): ?>
	                    <tr><td colspan="3"><?php echo $namespace_filter !== '' ? esc_html('Tidak ada namespace yang cocok dengan filter saat ini.') : esc_html('Belum ada metadata namespace.'); ?></td></tr>
	                <?php else: ?>
	                    <?php foreach ($visible_namespaces as $namespace): ?>
	                        <tr>
	                            <td><code><?php echo esc_html((string) ($namespace['namespace'] ?? '')); ?></code></td>
	                            <td><?php echo esc_html((string) ((int) ($namespace['version'] ?? 1))); ?></td>
	                            <td><?php echo !empty($namespace['invalidated_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $namespace['invalidated_at'])) : '-'; ?></td>
	                        </tr>
                    <?php endforeach; ?>
	                <?php endif; ?>
	                </tbody>
	            </table>
                <div class="tablenav bottom" style="margin-top:10px; max-width:980px;">
                    <div class="tablenav-pages" style="float:none; margin:0; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                        <span class="displaying-num">
                            <?php
                            echo esc_html(
                                $namespace_filter !== ''
                                    ? sprintf('Total namespace: %d hasil filter | Grup tersedia: %d', $namespace_total, $namespace_total_all)
                                    : sprintf('Total namespace: %d', $namespace_total)
                            );
                            ?>
                        </span>
                        <?php if (!empty($namespace_pagination_links)): ?>
                            <span class="pagination-links">
                                <?php foreach ($namespace_pagination_links as $namespace_pagination_link): ?>
                                    <?php echo wp_kses_post($namespace_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

	            <h2 style="margin-top:24px;">Locks</h2>
                <div style="max-width:1180px; margin:0 0 12px; padding:14px 16px; border:1px solid #dcdcde; border-left:6px solid <?php echo $stale_lock_total > 0 ? '#8a4b00' : '#135e36'; ?>; background:#fff;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <p style="margin:0 0 6px;"><strong><?php echo esc_html(sprintf('Active: %d | Stale: %d', $active_lock_total, $stale_lock_total)); ?></strong></p>
                            <p style="margin:0;">
                                <?php if ($stale_lock_total > 0): ?>
                                    Stale lock disembunyikan secara default. Biasanya ini hanya sisa metadata dari request yang timeout/crash dan sudah lewat masa berlaku.
                                <?php else: ?>
                                    Tidak ada stale lock di registry saat ini.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($stale_lock_total > 0): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                                <?php wp_nonce_field('cbt_cache_action'); ?>
                                <input type="hidden" name="action" value="cbt_cache_action" />
                                <input type="hidden" name="operation" value="release_stale_locks" />
                                <button type="submit" class="button button-secondary">Release Semua Stale Lock</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:8px 0 12px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="cbt-cache" />
                    <input type="hidden" name="cbt_namespace_per_page" value="<?php echo (int) $namespace_per_page; ?>" />
                    <input type="hidden" name="cbt_namespace_paged" value="<?php echo (int) $namespace_current_page; ?>" />
                    <label for="cbt-lock-per-page">Per halaman</label>
                    <select id="cbt-lock-per-page" name="cbt_lock_per_page">
                        <?php foreach ([20, 40, 60, 80, 100] as $lock_per_page_option): ?>
                            <option value="<?php echo (int) $lock_per_page_option; ?>" <?php selected($lock_per_page, $lock_per_page_option); ?>>
                                <?php echo esc_html((string) $lock_per_page_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="cbt-lock-show-stale" style="display:inline-flex; align-items:center; gap:6px;">
                        <input type="checkbox" id="cbt-lock-show-stale" name="cbt_lock_show_stale" value="1" <?php checked($show_stale_locks); ?> />
                        Tampilkan stale lock
                    </label>
                    <button type="submit" class="button button-secondary">Terapkan</button>
                </form>
	            <table class="widefat striped">
	                <thead>
	                <tr>
	                    <th>Lock Key</th>
	                    <th>Context</th>
                    <th>Expires</th>
                    <th>Status</th>
	                    <th>Aksi</th>
	                </tr>
	                </thead>
	                <tbody>
	                <?php if (empty($visible_locks)): ?>
                        <tr>
                            <td colspan="5">
                                <?php if (!$show_stale_locks && $stale_lock_total > 0): ?>
                                    Tidak ada lock aktif di registry. <strong><?php echo esc_html((string) $stale_lock_total); ?></strong> stale lock sedang disembunyikan.
                                <?php else: ?>
                                    Tidak ada lock CBT aktif di registry.
                                <?php endif; ?>
                            </td>
                        </tr>
	                <?php else: ?>
	                    <?php foreach ($visible_locks as $lock): ?>
	                        <tr>
	                            <td><code><?php echo esc_html((string) ($lock['lock_key'] ?? '')); ?></code></td>
	                            <td><code><?php echo esc_html(wp_json_encode((array) ($lock['context'] ?? []))); ?></code></td>
	                            <td><?php echo !empty($lock['expires_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $lock['expires_at'])) : '-'; ?></td>
                            <td><?php echo !empty($lock['is_stale']) ? 'Stale' : 'Active'; ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('cbt_cache_action'); ?>
                                    <input type="hidden" name="action" value="cbt_cache_action" />
                                    <input type="hidden" name="operation" value="release_lock" />
                                    <input type="hidden" name="lock_key" value="<?php echo esc_attr((string) ($lock['lock_key'] ?? '')); ?>" />
                                    <button type="submit" class="button button-small">Release</button>
                                </form>
                            </td>
                        </tr>
	                    <?php endforeach; ?>
	                <?php endif; ?>
	                </tbody>
	            </table>
                <div class="tablenav bottom" style="margin-top:10px;">
                    <div class="tablenav-pages" style="float:none; margin:0; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                        <span class="displaying-num">
                            <?php
                            echo esc_html(
                                $show_stale_locks
                                    ? sprintf('Total lock ditampilkan: %d', $lock_total)
                                    : sprintf('Active lock ditampilkan: %d | Stale disembunyikan: %d', $lock_total, $stale_lock_total)
                            );
                            ?>
                        </span>
                        <?php if (!empty($lock_pagination_links)): ?>
                            <span class="pagination-links">
                                <?php foreach ($lock_pagination_links as $lock_pagination_link): ?>
                                    <?php echo wp_kses_post($lock_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

	            <h2 style="margin-top:24px;">UI State Registry</h2>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>User</th>
                    <th>Attempt</th>
                    <th>Updated</th>
                    <th>Expires</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($ui_states)): ?>
                    <tr><td colspan="6">Belum ada UI state tersimpan.</td></tr>
                <?php else: ?>
                    <?php foreach ($ui_states as $ui_state): ?>
                        <?php
                        $entry_type = (string) ($ui_state['type'] ?? '');
                        $entry_user_id = (int) ($ui_state['user_id'] ?? 0);
                        $entry_attempt_id = (int) ($ui_state['attempt_id'] ?? 0);
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($entry_type); ?></code></td>
                            <td>
                                <?php echo esc_html($user_labels[$entry_user_id] ?? ('User #' . $entry_user_id)); ?>
                            </td>
                            <td><?php echo $entry_attempt_id > 0 ? esc_html((string) $entry_attempt_id) : '-'; ?></td>
                            <td><?php echo !empty($ui_state['updated_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $ui_state['updated_at'])) : '-'; ?></td>
                            <td><?php echo !empty($ui_state['expires_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $ui_state['expires_at'])) : '-'; ?></td>
                            <td>
                                <?php if ($entry_type === 'preferences' && $entry_user_id > 0): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('cbt_cache_action'); ?>
                                        <input type="hidden" name="action" value="cbt_cache_action" />
                                        <input type="hidden" name="operation" value="clear_ui_preferences" />
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $entry_user_id); ?>" />
                                        <button type="submit" class="button button-small">Clear</button>
                                    </form>
                                <?php elseif ($entry_type === 'attempt_state' && $entry_attempt_id > 0): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('cbt_cache_action'); ?>
                                        <input type="hidden" name="action" value="cbt_cache_action" />
                                        <input type="hidden" name="operation" value="clear_attempt_ui_state" />
                                        <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $entry_attempt_id); ?>" />
                                        <button type="submit" class="button button-small">Clear</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_cache_runtime_notice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $current_page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($current_page === 'cbt-cache') {
            return;
        }

        $overview = CBT_Cache::get_admin_overview();
        $health = isset($overview['health']) && is_array($overview['health']) ? $overview['health'] : [];
        if ((string) ($health['readiness'] ?? 'fallback') !== 'fallback') {
            return;
        }

        $cache_url = admin_url('admin.php?page=cbt-cache');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>CBT Cache masih berjalan pada mode fallback.</strong>
                Redis/object cache WordPress belum aktif, sehingga cache lintas request masih memakai transient.
                <a href="<?php echo esc_url($cache_url); ?>">Buka CBT Cache</a> untuk melihat checklist aktivasi Redis.
            </p>
        </div>
        <?php
    }

    /**
     * @return array{label:string,accent:string,background:string}
     */
    private static function cache_readiness_meta(string $readiness): array
    {
        switch ($readiness) {
            case 'ready':
                return [
                    'label' => 'Ready',
                    'accent' => '#135e36',
                    'background' => '#edfaef',
                ];
            case 'partial':
                return [
                    'label' => 'Partial',
                    'accent' => '#8a4b00',
                    'background' => '#fff5e6',
                ];
            default:
                return [
                    'label' => 'Fallback',
                    'accent' => '#8a2424',
                    'background' => '#fff1f1',
                ];
        }
    }

    /**
     * @return array{label:string,accent:string,background:string}
     */
    private static function cache_probe_meta(string $status): array
    {
        switch ($status) {
            case 'passed':
                return [
                    'label' => 'Passed',
                    'accent' => '#135e36',
                    'background' => '#edfaef',
                ];
            case 'failed':
                return [
                    'label' => 'Failed',
                    'accent' => '#8a2424',
                    'background' => '#fff1f1',
                ];
            default:
                return [
                    'label' => 'Skipped',
                    'accent' => '#6b7280',
                    'background' => '#f3f4f6',
                ];
        }
    }

    /**
     * @return array{label:string,accent:string,background:string}
     */
    private static function cache_server_probe_meta(string $status): array
    {
        switch ($status) {
            case 'reachable':
                return [
                    'label' => 'Reachable',
                    'accent' => '#135e36',
                    'background' => '#edfaef',
                ];
            case 'unreachable':
                return [
                    'label' => 'Unreachable',
                    'accent' => '#8a2424',
                    'background' => '#fff1f1',
                ];
            default:
                return [
                    'label' => 'Skipped',
                    'accent' => '#6b7280',
                    'background' => '#f3f4f6',
                ];
        }
    }

    /**
     * @param array<string,mixed> $health
     * @return array<int,string>
     */
    private static function cache_next_steps(array $health): array
    {
        $steps = [];
        $redis_config = isset($health['redis_config']) && is_array($health['redis_config']) ? $health['redis_config'] : [];
        $server_probe = isset($health['server_probe']) && is_array($health['server_probe']) ? $health['server_probe'] : [];
        $probe = isset($health['probe']) && is_array($health['probe']) ? $health['probe'] : [];
        $runtime_buffer = isset($health['runtime_buffer']) && is_array($health['runtime_buffer']) ? $health['runtime_buffer'] : [];

        if (empty($health['wp_cache_enabled'])) {
            $steps[] = "Tambahkan define('WP_CACHE', true); di wp-config.php.";
        }

        if ((string) ($server_probe['status'] ?? '') !== 'reachable') {
            $steps[] = 'Install/jalankan service Redis, lalu pastikan host dan port Redis dapat dijangkau dari server WordPress.';
        }

        if (empty($health['object_cache_dropin_present'])) {
            $steps[] = 'Install dan aktifkan plugin/drop-in Redis Object Cache WordPress sampai wp-content/object-cache.php tersedia.';
        }

        if (empty($redis_config['host']) || empty($redis_config['port'])) {
            $steps[] = "Tambahkan WP_REDIS_HOST dan WP_REDIS_PORT di wp-config.php sesuai host Redis yang dipakai.";
        }

        if (trim((string) ($redis_config['database'] ?? '')) === '') {
            $steps[] = 'Tetapkan WP_REDIS_DATABASE khusus agar key CBT tidak bercampur dengan aplikasi WordPress lain.';
        }

        if (empty($redis_config['prefix'])) {
            $steps[] = 'Tetapkan WP_REDIS_PREFIX yang unik per site untuk mencegah collision key.';
        }

        if (!empty($redis_config['disabled'])) {
            $steps[] = 'Pastikan WP_REDIS_DISABLED tidak bernilai true pada environment produksi.';
        }

        if (!empty($health['object_cache_active']) && (string) ($health['backend_hint'] ?? '') !== 'redis') {
            $steps[] = 'Pastikan object cache drop-in yang aktif benar-benar memakai backend Redis, bukan object cache persistent lain.';
        }

        if ((string) ($server_probe['status'] ?? '') === 'unreachable') {
            $steps[] = 'Periksa service Redis, firewall, dan endpoint pada WP_REDIS_HOST/WP_REDIS_PORT sampai status Redis Server menjadi Reachable.';
        }

        if ((string) ($probe['status'] ?? '') === 'failed') {
            $steps[] = 'Periksa koneksi Redis, kredensial, dan status drop-in sampai probe CBT Cache lulus.';
        }

        if (!empty($runtime_buffer['enabled']) && empty($runtime_buffer['ready'])) {
            $steps[] = 'Pastikan runtime Redis CBT memakai phpredis, database terpisah, dan endpoint CBT runtime dapat terhubung agar buffering jawaban aktif.';
        }

        $steps[] = 'Verifikasi ulang pada halaman CBT Cache sampai Readiness = ready, Backend Hint = redis, dan Probe Status = passed.';

        return array_values(array_unique($steps));
    }

    /**
     * @param array<string,mixed> $health
     */
    private static function cache_readiness_summary(array $health): string
    {
        $readiness = (string) ($health['readiness'] ?? 'fallback');
        $server_probe = isset($health['server_probe']) && is_array($health['server_probe']) ? $health['server_probe'] : [];
        $runtime_buffer = isset($health['runtime_buffer']) && is_array($health['runtime_buffer']) ? $health['runtime_buffer'] : [];

        if ($readiness === 'ready') {
            if (!empty($runtime_buffer['enabled']) && empty($runtime_buffer['ready'])) {
                return 'Redis object cache WordPress aktif, tetapi runtime buffer CBT untuk jawaban belum siap. Cache baca sudah siap, namun jalur batch write masih fallback ke database.';
            }
            return 'Redis object cache WordPress aktif dan probe round-trip berhasil. Plugin CBT sekarang memakai persistent object cache lintas request.';
        }

        if ($readiness === 'partial') {
            if ((string) ($server_probe['status'] ?? '') === 'unreachable') {
                return 'Sebagian konfigurasi Redis sudah terdeteksi, tetapi server Redis belum dapat dijangkau dari WordPress. Perbaiki endpoint atau jalankan service Redis terlebih dahulu.';
            }
            return 'Sebagian konfigurasi Redis/object cache sudah terdeteksi, tetapi runtime Redis belum siap penuh untuk CBT. Selesaikan checklist di bawah sampai status menjadi Ready.';
        }

        return 'WordPress masih menjalankan CBT pada mode transient fallback. Mode ini tetap didukung, tetapi bukan pilihan yang direkomendasikan untuk beban ujian serentak dengan trafik tinggi.';
    }

    private static function cache_boolean_label(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    /**
     * @param mixed $value
     */
    private static function cache_scalar_label($value): string
    {
        $label = trim((string) $value);
        return $label !== '' ? $label : '-';
    }

    /**
     * @return array{message:?string,error:?string}
     */
    private static function bootstrap_redis_wordpress(): array
    {
        $messages = [];
        $errors = [];
        $config = self::redis_bootstrap_defaults();

        $config_result = self::ensure_redis_wp_config_block($config);
        if (is_wp_error($config_result)) {
            return [
                'message' => null,
                'error' => $config_result->get_error_message(),
            ];
        }

        if (!empty($config_result['changed'])) {
            $messages[] = 'Konfigurasi Redis berhasil ditambahkan ke wp-config.php.';
        } else {
            $messages[] = 'Blok konfigurasi Redis di wp-config.php sudah tersedia.';
        }

        self::prime_runtime_redis_constants($config);

        $server_probe = CBT_Cache::probe_redis_server($config);
        if ((string) ($server_probe['status'] ?? '') !== 'reachable') {
            $errors[] = 'Server Redis belum bisa dijangkau pada endpoint ' . self::cache_scalar_label($server_probe['endpoint'] ?? '-') . '. Jalankan service Redis lalu klik bootstrap lagi.';

            return [
                'message' => implode(' ', $messages),
                'error' => implode(' ', $errors),
            ];
        }

        $plugin_result = self::ensure_redis_object_cache_plugin();
        if (is_wp_error($plugin_result)) {
            $errors[] = $plugin_result->get_error_message();

            return [
                'message' => implode(' ', $messages),
                'error' => implode(' ', $errors),
            ];
        }

        if (!empty($plugin_result['message'])) {
            $messages[] = (string) $plugin_result['message'];
        }

        $dropin_result = self::enable_redis_object_cache_dropin();
        if (is_wp_error($dropin_result)) {
            $errors[] = $dropin_result->get_error_message();
        } elseif (!empty($dropin_result['message'])) {
            $messages[] = (string) $dropin_result['message'];
        }

        if (empty($errors)) {
            $messages[] = 'Bootstrap Redis WordPress selesai. Halaman CBT Cache akan memverifikasi status ready pada request berikutnya.';
        }

        return [
            'message' => !empty($messages) ? implode(' ', array_values(array_unique($messages))) : null,
            'error' => !empty($errors) ? implode(' ', array_values(array_unique($errors))) : null,
        ];
    }

    private static function should_render_redis_rollback_action(): bool
    {
        if (self::redis_bootstrap_marker_present()) {
            return true;
        }

        if (self::is_redis_object_cache_plugin_active()) {
            return true;
        }

        $dropin_state = self::redis_object_cache_dropin_state();
        return !empty($dropin_state['exists']) && !empty($dropin_state['valid']);
    }

    /**
     * @return array{message:?string,error:?string}
     */
    private static function rollback_redis_wordpress(): array
    {
        $messages = [];
        $errors = [];
        $dropin_state = self::redis_object_cache_dropin_state();

        if (!empty($dropin_state['exists']) && empty($dropin_state['valid'])) {
            return [
                'message' => null,
                'error' => 'Ditemukan object-cache.php yang bukan drop-in Redis Object Cache yang dikenali CBT. Rollback dibatalkan agar tidak menghapus drop-in milik plugin lain.',
            ];
        }

        $dropin_result = self::disable_redis_object_cache_dropin();
        if (is_wp_error($dropin_result)) {
            return [
                'message' => null,
                'error' => $dropin_result->get_error_message(),
            ];
        }
        if (!empty($dropin_result['message'])) {
            $messages[] = (string) $dropin_result['message'];
        }

        $plugin_result = self::deactivate_redis_object_cache_plugin();
        if (is_wp_error($plugin_result)) {
            $errors[] = $plugin_result->get_error_message();
        } elseif (!empty($plugin_result['message'])) {
            $messages[] = (string) $plugin_result['message'];
        }

        $config_result = self::remove_redis_wp_config_block();
        if (is_wp_error($config_result)) {
            $errors[] = $config_result->get_error_message();
        } elseif (!empty($config_result['message'])) {
            $messages[] = (string) $config_result['message'];
        }

        if (empty($errors)) {
            $messages[] = 'Rollback Redis dari sisi WordPress selesai. CBT akan kembali memakai jalur fallback/transient jika object cache lain tidak aktif.';
        }

        return [
            'message' => !empty($messages) ? implode(' ', array_values(array_unique($messages))) : null,
            'error' => !empty($errors) ? implode(' ', array_values(array_unique($errors))) : null,
        ];
    }

    private static function redis_bootstrap_marker_present(): bool
    {
        $wp_config_path = self::wp_config_path();
        if ($wp_config_path === '' || !is_readable($wp_config_path)) {
            return false;
        }

        $contents = file_get_contents($wp_config_path);
        if (!is_string($contents) || $contents === '') {
            return false;
        }

        return strpos($contents, self::REDIS_CONFIG_BLOCK_START) !== false
            || strpos($contents, self::REDIS_CONFIG_BLOCK_END) !== false;
    }

    private static function is_redis_object_cache_plugin_active(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        return is_plugin_active(self::REDIS_BOOTSTRAP_PLUGIN)
            || (is_multisite() && is_plugin_active_for_network(self::REDIS_BOOTSTRAP_PLUGIN));
    }

    /**
     * @return array{path:string,exists:bool,valid:bool,name:string,plugin_uri:string}
     */
    private static function redis_object_cache_dropin_state(): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $path = WP_CONTENT_DIR . '/object-cache.php';
        $state = [
            'path' => $path,
            'exists' => file_exists($path),
            'valid' => false,
            'name' => '',
            'plugin_uri' => '',
        ];

        if (!$state['exists']) {
            return $state;
        }

        $plugin_data = get_plugin_data($path, false, false);
        $state['name'] = trim((string) ($plugin_data['Name'] ?? ''));
        $state['plugin_uri'] = trim((string) ($plugin_data['PluginURI'] ?? ''));

        if ($state['plugin_uri'] === 'https://wordpress.org/plugins/redis-cache/') {
            $state['valid'] = true;
            return $state;
        }

        $source = WP_PLUGIN_DIR . '/redis-cache/includes/object-cache.php';
        if (is_readable($source) && is_readable($path)) {
            $source_hash = md5_file($source);
            $target_hash = md5_file($path);
            if (is_string($source_hash) && is_string($target_hash) && $source_hash !== '' && hash_equals($source_hash, $target_hash)) {
                $state['valid'] = true;
                return $state;
            }
        }

        $contents = file_get_contents($path, false, null, 0, 4096);
        if (is_string($contents) && strpos($contents, 'Redis Object Cache Drop-In') !== false) {
            $state['valid'] = true;
        }

        return $state;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function disable_redis_object_cache_dropin()
    {
        $dropin_state = self::redis_object_cache_dropin_state();
        $path = (string) ($dropin_state['path'] ?? (WP_CONTENT_DIR . '/object-cache.php'));

        if (empty($dropin_state['exists'])) {
            return [
                'changed' => 0,
                'message' => 'Drop-in Redis object cache sudah tidak ada di wp-content/object-cache.php.',
            ];
        }

        if (empty($dropin_state['valid'])) {
            return new WP_Error('redis_dropin_foreign_rollback', 'object-cache.php yang aktif bukan drop-in Redis Object Cache yang dikenali CBT. Hapus manual jika memang ingin membatalkan object cache tersebut.');
        }

        $deleted = false;
        if (is_writable($path)) {
            $deleted = @unlink($path);
        }

        if (!$deleted && file_exists($path)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;
            if (!is_object($wp_filesystem)) {
                return new WP_Error('redis_dropin_delete_fs', 'Filesystem WordPress tidak bisa diinisialisasi untuk menghapus wp-content/object-cache.php.');
            }

            $deleted = (bool) $wp_filesystem->delete($path, false, 'f');
        }

        if (!$deleted && file_exists($path)) {
            return new WP_Error('redis_dropin_delete', 'Gagal menghapus wp-content/object-cache.php. Periksa permission filesystem WordPress.');
        }

        return [
            'changed' => 1,
            'message' => 'Drop-in Redis object cache berhasil dihapus dari wp-content/object-cache.php.',
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function deactivate_redis_object_cache_plugin()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = self::REDIS_BOOTSTRAP_PLUGIN;
        $is_network_active = is_multisite() && is_plugin_active_for_network($plugin_file);
        $is_active = is_plugin_active($plugin_file) || $is_network_active;

        if (!$is_active) {
            return [
                'changed' => 0,
                'message' => 'Plugin Redis Object Cache sudah nonaktif.',
            ];
        }

        if ($is_network_active) {
            if (!current_user_can('manage_network_plugins')) {
                return new WP_Error('redis_plugin_deactivate_cap', 'Plugin Redis Object Cache aktif network-wide. Nonaktifkan dari Network Admin atau gunakan akun dengan izin manage_network_plugins.');
            }
        } elseif (!current_user_can('activate_plugins')) {
            return new WP_Error('redis_plugin_deactivate_cap', 'User saat ini tidak punya izin untuk menonaktifkan plugin Redis Object Cache.');
        }

        deactivate_plugins($plugin_file, false, $is_network_active);

        if (is_plugin_active($plugin_file) || (is_multisite() && is_plugin_active_for_network($plugin_file))) {
            return new WP_Error('redis_plugin_deactivate', 'Plugin Redis Object Cache gagal dinonaktifkan.');
        }

        return [
            'changed' => 1,
            'message' => 'Plugin Redis Object Cache berhasil dinonaktifkan.',
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function remove_redis_wp_config_block()
    {
        $wp_config_path = self::wp_config_path();
        if ($wp_config_path === '') {
            return new WP_Error('redis_wp_config_path', 'wp-config.php tidak ditemukan.');
        }

        if (!is_readable($wp_config_path) || !is_writable($wp_config_path)) {
            return new WP_Error('redis_wp_config_perm', 'wp-config.php tidak bisa dibaca/ditulis oleh proses WordPress.');
        }

        $contents = file_get_contents($wp_config_path);
        if (!is_string($contents)) {
            return new WP_Error('redis_wp_config_read', 'Isi wp-config.php tidak dapat dibaca.');
        }

        $has_start = strpos($contents, self::REDIS_CONFIG_BLOCK_START) !== false;
        $has_end = strpos($contents, self::REDIS_CONFIG_BLOCK_END) !== false;

        if (!$has_start && !$has_end) {
            return [
                'changed' => 0,
                'message' => 'Blok konfigurasi Redis di wp-config.php sudah tidak ada.',
            ];
        }

        if (!$has_start || !$has_end) {
            return new WP_Error('redis_wp_config_inconsistent', 'Marker konfigurasi Redis di wp-config.php tidak lengkap. Periksa file tersebut secara manual sebelum menjalankan rollback lagi.');
        }

        $pattern = '/\R?' . preg_quote(self::REDIS_CONFIG_BLOCK_START, '/') . '.*?' . preg_quote(self::REDIS_CONFIG_BLOCK_END, '/') . '\R*/s';
        $updated = preg_replace($pattern, PHP_EOL, $contents, 1);
        if (!is_string($updated)) {
            return new WP_Error('redis_wp_config_remove', 'Blok konfigurasi Redis di wp-config.php tidak bisa dihapus.');
        }

        if ($updated !== $contents) {
            $normalized = preg_replace("/(\r\n|\r|\n){3,}/", PHP_EOL . PHP_EOL, $updated);
            if (is_string($normalized)) {
                $updated = $normalized;
            }

            if (file_put_contents($wp_config_path, $updated) === false) {
                return new WP_Error('redis_wp_config_write', 'Gagal menyimpan perubahan ke wp-config.php.');
            }

            return [
                'changed' => 1,
                'message' => 'Blok konfigurasi Redis berhasil dihapus dari wp-config.php.',
            ];
        }

        return [
            'changed' => 0,
            'message' => 'Blok konfigurasi Redis di wp-config.php sudah tidak ada.',
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function ensure_redis_object_cache_plugin()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = self::REDIS_BOOTSTRAP_PLUGIN;
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        if (!file_exists($plugin_path)) {
            if (!current_user_can('install_plugins')) {
                return new WP_Error('redis_plugin_missing', 'Plugin Redis Object Cache belum terpasang dan user saat ini tidak punya izin install plugin.');
            }

            if (get_filesystem_method() !== 'direct') {
                return new WP_Error('redis_plugin_fs', 'Install plugin Redis Object Cache otomatis membutuhkan filesystem WordPress mode direct atau plugin redis-cache yang sudah terpasang di server.');
            }

            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

            $api = plugins_api('plugin_information', [
                'slug' => self::REDIS_BOOTSTRAP_SLUG,
                'fields' => [
                    'sections' => false,
                    'icons' => false,
                    'banners' => false,
                ],
            ]);
            if (is_wp_error($api) || empty($api->download_link)) {
                return new WP_Error('redis_plugin_api', 'Gagal mengambil paket plugin Redis Object Cache dari WordPress.org.');
            }

            ob_start();
            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $installed = $upgrader->install((string) $api->download_link);
            ob_end_clean();

            if (is_wp_error($installed) || !$installed) {
                return new WP_Error('redis_plugin_install', 'Plugin Redis Object Cache tidak bisa diinstall otomatis. Periksa permission filesystem WordPress.');
            }

            $plugin_file = $upgrader->plugin_info() ?: $plugin_file;
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        }

        if (!file_exists($plugin_path)) {
            return new WP_Error('redis_plugin_not_found', 'File plugin Redis Object Cache tidak ditemukan setelah proses bootstrap.');
        }

        if (!is_plugin_active($plugin_file)) {
            if (!current_user_can('activate_plugin', $plugin_file)) {
                return new WP_Error('redis_plugin_activate_cap', 'User saat ini tidak punya izin untuk mengaktifkan plugin Redis Object Cache.');
            }

            ob_start();
            $activation = activate_plugin($plugin_file, '', is_network_admin(), false);
            ob_end_clean();

            if (is_wp_error($activation)) {
                return new WP_Error('redis_plugin_activate', 'Plugin Redis Object Cache gagal diaktifkan: ' . $activation->get_error_message());
            }

            return [
                'message' => 'Plugin Redis Object Cache berhasil diinstall dan diaktifkan.',
            ];
        }

        return [
            'message' => 'Plugin Redis Object Cache sudah aktif.',
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function enable_redis_object_cache_dropin()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $plugin_file = self::REDIS_BOOTSTRAP_PLUGIN;
        if (!is_plugin_active($plugin_file)) {
            return new WP_Error('redis_dropin_inactive', 'Plugin Redis Object Cache belum aktif, sehingga drop-in belum bisa di-enable.');
        }

        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (file_exists($plugin_path) && !function_exists('redis_object_cache')) {
            require_once $plugin_path;
        }

        if (!function_exists('redis_object_cache')) {
            return new WP_Error('redis_dropin_runtime', 'Runtime plugin Redis Object Cache tidak tersedia untuk meng-enable drop-in.');
        }

        $redis_plugin = redis_object_cache();
        if (is_object($redis_plugin) && method_exists($redis_plugin, 'object_cache_dropin_exists') && method_exists($redis_plugin, 'validate_object_cache_dropin')) {
            $dropin_exists = (bool) $redis_plugin->object_cache_dropin_exists();
            $dropin_valid = (bool) $redis_plugin->validate_object_cache_dropin();

            if ($dropin_exists && $dropin_valid) {
                return [
                    'message' => 'Drop-in Redis object cache sudah aktif.',
                ];
            }

            if ($dropin_exists && !$dropin_valid) {
                return new WP_Error('redis_dropin_foreign', 'Ditemukan object-cache.php milik plugin lain. Hapus atau ganti drop-in tersebut terlebih dahulu sebelum bootstrap Redis.');
            }

            if (method_exists($redis_plugin, 'test_filesystem_writing')) {
                $fs_test = $redis_plugin->test_filesystem_writing();
                if (is_wp_error($fs_test)) {
                    return new WP_Error('redis_dropin_fs', 'WordPress tidak bisa menulis object-cache.php: ' . $fs_test->get_error_message());
                }
            }
        }

        WP_Filesystem();
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            return new WP_Error('redis_dropin_fs_init', 'Filesystem WordPress tidak bisa diinisialisasi untuk menyalin object-cache.php.');
        }

        if (!defined('WP_REDIS_PLUGIN_PATH')) {
            return new WP_Error('redis_dropin_path', 'Path plugin Redis Object Cache tidak tersedia.');
        }

        $source = WP_REDIS_PLUGIN_PATH . '/includes/object-cache.php';
        $target = WP_CONTENT_DIR . '/object-cache.php';
        if (!file_exists($source)) {
            return new WP_Error('redis_dropin_source', 'File sumber object-cache.php milik plugin Redis tidak ditemukan.');
        }

        $copied = $wp_filesystem->copy($source, $target, true, FS_CHMOD_FILE);
        if (!$copied) {
            return new WP_Error('redis_dropin_copy', 'Gagal menyalin object-cache.php ke wp-content. Periksa permission filesystem WordPress.');
        }

        return [
            'message' => 'Drop-in Redis object cache berhasil di-enable.',
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>|WP_Error
     */
    private static function ensure_redis_wp_config_block(array $config)
    {
        $wp_config_path = self::wp_config_path();
        if ($wp_config_path === '') {
            return new WP_Error('redis_wp_config_path', 'wp-config.php tidak ditemukan.');
        }

        if (!is_readable($wp_config_path) || !is_writable($wp_config_path)) {
            return new WP_Error('redis_wp_config_perm', 'wp-config.php tidak bisa dibaca/ditulis oleh proses WordPress.');
        }

        $contents = file_get_contents($wp_config_path);
        if (!is_string($contents) || $contents === '') {
            return new WP_Error('redis_wp_config_read', 'Isi wp-config.php tidak dapat dibaca.');
        }

        $block = self::redis_wp_config_block($config);
        $changed = false;

        if (strpos($contents, self::REDIS_CONFIG_BLOCK_START) !== false && strpos($contents, self::REDIS_CONFIG_BLOCK_END) !== false) {
            $pattern = '/' . preg_quote(self::REDIS_CONFIG_BLOCK_START, '/') . '.*?' . preg_quote(self::REDIS_CONFIG_BLOCK_END, '/') . '\R*/s';
            $updated = preg_replace($pattern, $block . PHP_EOL . PHP_EOL, $contents, 1);
            if (!is_string($updated)) {
                return new WP_Error('redis_wp_config_replace', 'Blok konfigurasi Redis di wp-config.php tidak bisa diperbarui.');
            }
            $changed = ($updated !== $contents);
            $contents = $updated;
        } else {
            $needle = "/* That's all, stop editing! Happy publishing. */";
            if (strpos($contents, $needle) === false) {
                return new WP_Error('redis_wp_config_marker', 'Marker stop editing pada wp-config.php tidak ditemukan.');
            }

            $contents = str_replace($needle, $block . PHP_EOL . PHP_EOL . $needle, $contents, $replace_count);
            if ($replace_count < 1) {
                return new WP_Error('redis_wp_config_insert', 'Blok Redis tidak bisa disisipkan ke wp-config.php.');
            }
            $changed = true;
        }

        if ($changed && file_put_contents($wp_config_path, $contents) === false) {
            return new WP_Error('redis_wp_config_write', 'Gagal menyimpan perubahan ke wp-config.php.');
        }

        return [
            'changed' => $changed ? 1 : 0,
        ];
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function redis_wp_config_block(array $config): string
    {
        $host = var_export((string) ($config['host'] ?? '127.0.0.1'), true);
        $port = (int) ($config['port'] ?? 6379);
        $database = (int) ($config['database'] ?? 1);
        $prefix = var_export((string) ($config['prefix'] ?? self::default_redis_prefix()), true);

        return implode(PHP_EOL, [
            self::REDIS_CONFIG_BLOCK_START,
            "if ( ! defined( 'WP_CACHE' ) ) {",
            "    define( 'WP_CACHE', true );",
            "}",
            "if ( ! defined( 'WP_REDIS_HOST' ) ) {",
            "    define( 'WP_REDIS_HOST', {$host} );",
            "}",
            "if ( ! defined( 'WP_REDIS_PORT' ) ) {",
            "    define( 'WP_REDIS_PORT', {$port} );",
            "}",
            "if ( ! defined( 'WP_REDIS_DATABASE' ) ) {",
            "    define( 'WP_REDIS_DATABASE', {$database} );",
            "}",
            "if ( ! defined( 'WP_REDIS_PREFIX' ) ) {",
            "    define( 'WP_REDIS_PREFIX', {$prefix} );",
            "}",
            self::REDIS_CONFIG_BLOCK_END,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function redis_bootstrap_defaults(): array
    {
        return [
            'host' => defined('WP_REDIS_HOST') ? (string) constant('WP_REDIS_HOST') : '127.0.0.1',
            'port' => defined('WP_REDIS_PORT') ? (int) constant('WP_REDIS_PORT') : 6379,
            'database' => defined('WP_REDIS_DATABASE') ? (int) constant('WP_REDIS_DATABASE') : 1,
            'prefix' => defined('WP_REDIS_PREFIX') && trim((string) constant('WP_REDIS_PREFIX')) !== ''
                ? (string) constant('WP_REDIS_PREFIX')
                : self::default_redis_prefix(),
        ];
    }

    private static function default_redis_prefix(): string
    {
        global $table_prefix;

        $host = sanitize_key((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($host === '') {
            $host = 'wordpress';
        }

        $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table_prefix);
        if (!is_string($table) || $table === '') {
            $table = 'wp_';
        }

        return strtolower($host . ':' . $table . 'cbt:');
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function prime_runtime_redis_constants(array $config): void
    {
        foreach ([
            'WP_CACHE' => true,
            'WP_REDIS_HOST' => (string) ($config['host'] ?? '127.0.0.1'),
            'WP_REDIS_PORT' => (int) ($config['port'] ?? 6379),
            'WP_REDIS_DATABASE' => (int) ($config['database'] ?? 1),
            'WP_REDIS_PREFIX' => (string) ($config['prefix'] ?? self::default_redis_prefix()),
        ] as $constant => $value) {
            if (!defined($constant)) {
                define($constant, $value);
            }
        }
    }

    private static function wp_config_path(): string
    {
        if (file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        }

        $parent = dirname(ABSPATH) . '/wp-config.php';
        return file_exists($parent) ? $parent : '';
    }

    public static function handle_cache_action(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_cache_action');

        $operation = isset($_POST['operation']) ? sanitize_key((string) wp_unslash($_POST['operation'])) : '';
        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash($_POST['exam_id'])) : 0;
        $user_id = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
        $attempt_id = isset($_POST['attempt_id']) ? absint(wp_unslash($_POST['attempt_id'])) : 0;
        $lock_key = isset($_POST['lock_key']) ? sanitize_text_field((string) wp_unslash($_POST['lock_key'])) : '';

        switch ($operation) {
            case 'bootstrap_redis':
                $result = self::bootstrap_redis_wordpress();
                self::redirect_cache_page($result['message'] ?? null, $result['error'] ?? null);
                break;
            case 'rollback_redis':
                $result = self::rollback_redis_wordpress();
                self::redirect_cache_page($result['message'] ?? null, $result['error'] ?? null);
                break;
            case 'invalidate_all':
                CBT_Cache::invalidate_all();
                self::redirect_cache_page('Semua namespace cache CBT berhasil di-invalidate.');
                break;
            case 'invalidate_catalog':
                CBT_Cache::invalidate_catalog();
                self::redirect_cache_page('Namespace catalog berhasil di-invalidate.');
                break;
            case 'invalidate_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                CBT_Cache::invalidate_exam($exam_id);
                self::redirect_cache_page('Namespace exam berhasil di-invalidate.');
                break;
            case 'invalidate_user':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_Cache::invalidate_user($user_id);
                self::redirect_cache_page('Namespace user berhasil di-invalidate.');
                break;
            case 'invalidate_attempt':
                if ($attempt_id <= 0) {
                    self::redirect_cache_page(null, 'Attempt ID tidak valid.');
                }
                CBT_Cache::invalidate_attempt($attempt_id);
                self::redirect_cache_page('Namespace attempt berhasil di-invalidate.');
                break;
            case 'prune_old_namespaces':
                $pruned = CBT_Cache::prune_old_namespaces();
                self::redirect_cache_page(sprintf('Namespace lama yang dibersihkan dari registry: %d.', $pruned));
                break;
            case 'clear_all_ui_state':
                CBT_UI_State::clear_all();
                self::redirect_cache_page('Semua UI state CBT berhasil dibersihkan.');
                break;
            case 'clear_attempt_ui_state':
                if ($attempt_id <= 0) {
                    self::redirect_cache_page(null, 'Attempt ID tidak valid.');
                }
                CBT_UI_State::clear_attempt_state_by_attempt_id($attempt_id);
                self::redirect_cache_page('UI state attempt berhasil dibersihkan.');
                break;
            case 'clear_ui_preferences':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_UI_State::clear_preferences($user_id);
                self::redirect_cache_page('UI preferences user berhasil dibersihkan.');
                break;
            case 'release_stale_locks':
                $released = CBT_Cache::release_stale_locks();
                self::redirect_cache_page(sprintf('Stale lock yang dilepas: %d.', $released));
                break;
            case 'release_lock':
                if ($lock_key === '') {
                    self::redirect_cache_page(null, 'Lock key tidak valid.');
                }
                CBT_Cache::release_lock($lock_key);
                self::redirect_cache_page('Lock CBT berhasil dilepas.');
                break;
            default:
                self::redirect_cache_page(null, 'Operasi cache tidak dikenali.');
        }
    }

    public static function redirect_removed_admin_pages(): void
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page === '') {
            return;
        }

        $removed_pages = [
            'cbt-questions-mc',
            'cbt-questions-ma',
            'cbt-questions-tf',
            'cbt-questions-sa',
            'cbt-questions-essay',
        ];

        if (!in_array($page, $removed_pages, true)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-subjects'));
        exit;
    }

    public static function render_subjects_page(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $editing = null;

        if ($editing_id > 0) {
            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $editing_id), ARRAY_A);
        }

        $subject_per_page = isset($_GET['cbt_subject_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash($_GET['cbt_subject_per_page'])))
            : 20;
        $subject_current_page = isset($_GET['cbt_subject_paged']) ? max(1, absint(wp_unslash($_GET['cbt_subject_paged']))) : 1;
        $total_subjects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $subject_total_pages = max(1, (int) ceil($total_subjects / $subject_per_page));
        if ($total_subjects > 0 && $subject_current_page > $subject_total_pages) {
            $subject_current_page = $subject_total_pages;
        }
        $subject_offset = ($subject_current_page - 1) * $subject_per_page;
        $subjects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY name ASC LIMIT %d OFFSET %d",
                $subject_per_page,
                $subject_offset
            ),
            ARRAY_A
        );
        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $subject_import_token = isset($_GET['cbt_subject_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_subject_import_token'])) : '';
        $subject_import_state = null;
        $subject_import_total = 0;
        $subject_import_offset = 0;
        $subject_import_created = 0;
        $subject_import_updated = 0;
        $subject_import_failed = 0;
        $subject_import_progress_percent = 0.0;
        $subject_import_is_running = false;
        $subject_import_continue_url = '';
        if ($subject_import_token !== '') {
            $subject_import_state = self::get_subject_import_state_for_current_user($subject_import_token);
            if (is_array($subject_import_state)) {
                $subject_import_total = max(0, isset($subject_import_state['total']) ? (int) $subject_import_state['total'] : 0);
                $subject_import_offset = max(0, isset($subject_import_state['offset']) ? (int) $subject_import_state['offset'] : 0);
                if ($subject_import_total > 0 && $subject_import_offset > $subject_import_total) {
                    $subject_import_offset = $subject_import_total;
                }
                $subject_import_created = max(0, isset($subject_import_state['created']) ? (int) $subject_import_state['created'] : 0);
                $subject_import_updated = max(0, isset($subject_import_state['updated']) ? (int) $subject_import_state['updated'] : 0);
                $subject_import_failed = max(0, isset($subject_import_state['failed']) ? (int) $subject_import_state['failed'] : 0);
                $subject_import_progress_percent = $subject_import_total > 0
                    ? round(((float) $subject_import_offset / (float) $subject_import_total) * 100, 2)
                    : 0.0;
                $subject_import_is_running = $subject_import_total > 0 && $subject_import_offset < $subject_import_total;
                $subject_import_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_import_subjects',
                        'cbt_subject_import_token' => $subject_import_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi import subject tidak ditemukan atau sudah berakhir. Silakan upload ulang file.';
            }
        }
        ?>
        <div class="wrap">
            <h1>Subjects / Mata Pelajaran</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if (is_array($subject_import_state)): ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Progress Import Subject:</strong>
                        <?php echo esc_html((string) $subject_import_offset . ' / ' . (string) $subject_import_total); ?>
                        (<?php echo esc_html(number_format($subject_import_progress_percent, 2)); ?>%)
                        | Created: <?php echo esc_html((string) $subject_import_created); ?>
                        | Updated: <?php echo esc_html((string) $subject_import_updated); ?>
                        | Failed: <?php echo esc_html((string) $subject_import_failed); ?>
                    </p>
                </div>
                <div style="max-width:760px; margin:0 0 14px; border:1px solid #c3c4c7; border-radius:8px; background:#fff; padding:12px;">
                    <div style="width:100%; height:14px; border-radius:999px; background:#f0f0f1; overflow:hidden; border:1px solid #dcdcde;">
                        <div style="height:100%; width: <?php echo esc_attr((string) $subject_import_progress_percent); ?>%; background:linear-gradient(90deg,#2271b1,#135e96); transition:width .25s ease;"></div>
                    </div>
                    <div style="margin-top:10px;">
                        <?php if ($subject_import_is_running): ?>
                            <span style="color:#1d2327;">Memproses batch subject berikutnya...</span>
                            <script>
                                window.setTimeout(function () {
                                    window.location.href = <?php echo wp_json_encode($subject_import_continue_url); ?>;
                                }, 350);
                            </script>
                        <?php else: ?>
                            <span style="color:#0a7a2f; font-weight:600;">Import subject selesai diproses.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h2><?php echo $editing ? 'Edit Subject' : 'Add Subject'; ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('cbt_save_subject'); ?>
                <input type="hidden" name="action" value="cbt_save_subject" />
                <input type="hidden" name="id" value="<?php echo esc_attr($editing['id'] ?? 0); ?>" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cbt-subject-name">Name</label></th>
                        <td><input required type="text" id="cbt-subject-name" name="name" class="regular-text" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-subject-code">Code</label></th>
                        <td><input type="text" id="cbt-subject-code" name="code" class="regular-text" value="<?php echo esc_attr($editing['code'] ?? ''); ?>" placeholder="MAT, IND, ENG" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-subject-description">Description</label></th>
                        <td><textarea id="cbt-subject-description" name="description" class="large-text" rows="3"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea></td>
                    </tr>
                </table>

                <?php submit_button($editing ? 'Update Subject' : 'Save Subject'); ?>
            </form>

            <hr />

            <h2>Import Subjects / Mata Pelajaran</h2>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_subject_template'), 'cbt_download_subject_template')); ?>">
                    Download Template CSV
                </a>
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_subject_template_xlsx'), 'cbt_download_subject_template_xlsx')); ?>">
                    Download Template XLSX
                </a>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('cbt_import_subjects'); ?>
                <input type="hidden" name="action" value="cbt_import_subjects" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cbt-subject-import-file">File Import</label></th>
                        <td>
                            <input required type="file" id="cbt-subject-import-file" name="subject_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                            <p class="description">
                                Kolom minimal: <code>name</code>.
                                Kolom opsional: <code>code</code>, <code>description</code>.
                                Format didukung: <code>.csv</code> dan <code>.xlsx</code>.
                                Import bersifat upsert berdasarkan <code>code</code> (jika code kosong, pakai <code>name</code>).
                                Progress import akan tampil otomatis (jumlah diproses, persentase, created/updated/failed).
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Import Subjects'); ?>
            </form>

            <hr />

            <h2>Subject List</h2>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 8px 0 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="cbt-subjects" />
                <label for="cbt-subject-per-page">Per halaman</label>
                <select id="cbt-subject-per-page" name="cbt_subject_per_page">
                    <?php foreach ([20, 40, 60, 80, 100] as $subject_per_page_option): ?>
                        <option value="<?php echo (int) $subject_per_page_option; ?>" <?php selected($subject_per_page, $subject_per_page_option); ?>>
                            <?php echo esc_html((string) $subject_per_page_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-secondary">Terapkan</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 8px 0 0;">
                <?php wp_nonce_field('cbt_bulk_delete_subjects'); ?>
                <input type="hidden" name="action" value="cbt_bulk_delete_subjects" />
                <input type="hidden" name="cbt_subject_per_page" value="<?php echo (int) $subject_per_page; ?>" />
                <input type="hidden" name="cbt_subject_paged" value="<?php echo (int) $subject_current_page; ?>" />
                <button type="submit" class="button button-secondary" name="bulk_mode" value="selected" onclick="return confirm('Delete selected subjects?');">Delete Selected</button>
                <button type="submit" class="button button-secondary" name="bulk_mode" value="all" onclick="return confirm('Delete ALL subjects? Subject yang dipakai exam akan dilewati.');">Delete All</button>

                <table class="widefat striped" style="margin-top:10px;">
                    <thead>
                    <tr>
                        <th style="width:32px;"><input type="checkbox" id="cbt-subject-select-all" /></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$subjects): ?>
                        <tr><td colspan="6">No subjects found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><input type="checkbox" class="cbt-subject-row-check" name="subject_ids[]" value="<?php echo (int) $subject['id']; ?>" /></td>
                                <td><?php echo (int) $subject['id']; ?></td>
                                <td><?php echo esc_html((string) $subject['name']); ?></td>
                                <td><?php echo esc_html((string) ($subject['code'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($subject['description'] ?? '')); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'cbt-subjects', 'edit' => (int) $subject['id'], 'cbt_subject_per_page' => $subject_per_page, 'cbt_subject_paged' => $subject_current_page], admin_url('admin.php'))); ?>">Edit</a>
                                    |
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'cbt_delete_subject', 'id' => (int) $subject['id'], 'cbt_subject_per_page' => $subject_per_page, 'cbt_subject_paged' => $subject_current_page], admin_url('admin-post.php')), 'cbt_delete_subject_' . (int) $subject['id'])); ?>" onclick="return confirm('Delete this subject?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php
                $subject_pagination_links = [];
                if ($subject_total_pages > 1) {
                    $subject_pagination_links = paginate_links([
                        'base' => add_query_arg(
                            [
                                'page' => 'cbt-subjects',
                                'cbt_subject_per_page' => $subject_per_page,
                                'cbt_subject_paged' => '%#%',
                            ],
                            admin_url('admin.php')
                        ),
                        'format' => '',
                        'current' => $subject_current_page,
                        'total' => $subject_total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'type' => 'array',
                        'end_size' => 1,
                        'mid_size' => 1,
                    ]);
                }
                ?>
                <div class="tablenav bottom cbt-admin-pagination-wrap" style="margin-top:10px;">
                    <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                        <span class="displaying-num cbt-admin-total"><?php echo esc_html(sprintf('Total subject: %d', $total_subjects)); ?></span>
                        <?php if (!empty($subject_pagination_links)): ?>
                            <span class="pagination-links cbt-admin-pagination-links">
                                <?php foreach ($subject_pagination_links as $subject_pagination_link): ?>
                                    <?php echo wp_kses_post($subject_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

            </form>
            <style>
                .cbt-admin-pagination-wrap {
                    clear: both;
                }
                .cbt-admin-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination .cbt-admin-total {
                    font-size: 14px;
                    line-height: 1.4;
                    color: #1d2327;
                    font-weight: 500;
                }
                .cbt-admin-pagination-links {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 34px;
                    height: 34px;
                    padding: 0 12px;
                    border: 1px solid #c3c4c7;
                    border-radius: 6px;
                    background: #fff;
                    color: #1d2327;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                }
                .cbt-admin-pagination-links .page-numbers:hover,
                .cbt-admin-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.15);
                    color: #0a4b78;
                    outline: none;
                }
                .cbt-admin-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #fff;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
                }
                .cbt-admin-pagination-links .page-numbers.prev,
                .cbt-admin-pagination-links .page-numbers.next {
                    padding: 0 14px;
                    font-weight: 600;
                }
                .cbt-admin-pagination-links .page-numbers.dots {
                    border-color: transparent;
                    background: transparent;
                    color: #646970;
                    min-width: auto;
                    padding: 0 4px;
                    box-shadow: none;
                }
                @media (max-width: 782px) {
                    .cbt-admin-pagination {
                        align-items: flex-start;
                    }
                    .cbt-admin-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        padding: 0 10px;
                        font-size: 13px;
                    }
                }
            </style>
        </div>
        <script>
            (function () {
                const selectAll = document.getElementById('cbt-subject-select-all');
                const rowChecks = Array.from(document.querySelectorAll('.cbt-subject-row-check'));
                if (!selectAll || rowChecks.length === 0) return;

                function syncSelectState() {
                    const checkedCount = rowChecks.filter((item) => item.checked).length;
                    selectAll.checked = checkedCount > 0 && checkedCount === rowChecks.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
                }

                selectAll.addEventListener('change', () => {
                    rowChecks.forEach((item) => {
                        item.checked = selectAll.checked;
                    });
                    syncSelectState();
                });

                rowChecks.forEach((item) => {
                    item.addEventListener('change', syncSelectState);
                });
            })();
        </script>
        <?php
    }

    public static function render_questions_page(?string $forced_question_type = null): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $allowed_question_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'];
        $question_type_labels = [
            'multiple_choice' => 'Multiple Choice',
            'multiple_answer' => 'Multiple Answer',
            'true_false' => 'True/False',
            'true_false_matrix' => 'True/False Matrix',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
        ];
        $current_page_slug = self::normalize_question_page_slug(isset($_GET['page']) ? wp_unslash($_GET['page']) : 'cbt-question-bank');
        $page_locked_type = self::forced_question_type_for_page($current_page_slug);
        $active_question_type = '';
        if (is_string($forced_question_type) && in_array($forced_question_type, $allowed_question_types, true)) {
            $active_question_type = $forced_question_type;
        } elseif ($page_locked_type !== '') {
            $active_question_type = $page_locked_type;
        } elseif (isset($_GET['question_type'])) {
            $from_query = sanitize_text_field(wp_unslash($_GET['question_type']));
            if (in_array($from_query, $allowed_question_types, true)) {
                $active_question_type = $from_query;
            }
        }
        $lock_question_type = ($active_question_type !== '');
        $import_type_help_map = [
            'multiple_choice' => 'Mode import aktif: Multiple Choice. DOCX didukung (maks 5 opsi, jawaban nomor opsi, gambar bisa ditempel).',
            'multiple_answer' => 'Mode import aktif: Multiple Answer. DOCX didukung (maks 12 opsi, jawaban bisa lebih dari satu: contoh 1,3,5).',
            'true_false' => 'Mode import aktif: True/False. DOCX didukung (jawaban: true/false).',
            'true_false_matrix' => 'Mode import aktif: True/False Matrix. DOCX didukung (isi PERNYATAAN_1..10 dan KUNCI_1..10: true/false).',
            'short_answer' => 'Mode import aktif: Short Answer. DOCX didukung (maks 8 jawaban valid per soal, gunakan placeholder [INPUT_1] s.d. [INPUT_8] di teks soal).',
            'essay' => 'Mode import aktif: Essay. DOCX didukung (wajib isi acuan jawaban/rubrik).',
        ];
        $import_active_type = $lock_question_type ? $active_question_type : 'multiple_choice';
        $import_help_text = $import_type_help_map[$import_active_type] ?? $import_type_help_map['multiple_choice'];
        $import_allow_docx = in_array($import_active_type, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'], true);
        $import_file_accept = '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $bank_exam_title_like = 'Bank Soal - %';
        $exam_where_parts = [
            $wpdb->prepare('e.title LIKE %s', $bank_exam_title_like),
        ];
        if (!$is_admin_scope) {
            $exam_where_parts[] = $wpdb->prepare('e.created_by = %d', $current_user_id);
        }
        $exam_where = ' WHERE ' . implode(' AND ', $exam_where_parts);
        $exams = $wpdb->get_results(
            "SELECT e.id, e.title, e.subject_id, s.name AS subject_name
             FROM {$exam_table} e
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             {$exam_where}
             ORDER BY e.id DESC",
            ARRAY_A
        );

        if ($is_admin_scope) {
            $subjects = $wpdb->get_results(
                "SELECT id, name, code
                 FROM {$subject_table}
                 ORDER BY name ASC",
                ARRAY_A
            );
        } else {
            $subjects = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT s.id, s.name, s.code
                     FROM {$subject_table} s
                     INNER JOIN {$exam_table} e ON e.subject_id = s.id
                     WHERE e.created_by = %d
                     ORDER BY s.name ASC",
                    $current_user_id
                ),
                ARRAY_A
            );
        }

        $exam_subject_map = [];
        foreach ($exams as $exam) {
            $exam_subject_map[(int) $exam['id']] = (int) ($exam['subject_id'] ?? 0);
        }

        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $editing_question = null;
        $editing_options = [];
        $selected_subject_id = 0;
        $view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;
        $view_question = null;
        $view_options = [];
        $view_detail = [];

        if ($editing_id > 0) {
            if ($is_admin_scope) {
                $editing_question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$question_table} WHERE id = %d", $editing_id), ARRAY_A);
            } else {
                $editing_question = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT q.*
                         FROM {$question_table} q
                         INNER JOIN {$exam_table} e ON e.id = q.exam_id
                         WHERE q.id = %d AND e.created_by = %d",
                        $editing_id,
                        $current_user_id
                    ),
                    ARRAY_A
                );
            }
            $editing_options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$option_table} WHERE question_id = %d ORDER BY id ASC", $editing_id), ARRAY_A);
            if ($editing_question && isset($editing_question['exam_id'])) {
                $selected_subject_id = (int) ($exam_subject_map[(int) $editing_question['exam_id']] ?? 0);
            }
        }

        if ($view_id > 0) {
            if ($is_admin_scope) {
                $view_question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$question_table} WHERE id = %d", $view_id), ARRAY_A);
            } else {
                $view_question = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT q.*
                         FROM {$question_table} q
                         INNER JOIN {$exam_table} e ON e.id = q.exam_id
                         WHERE q.id = %d AND e.created_by = %d",
                        $view_id,
                        $current_user_id
                    ),
                    ARRAY_A
                );
            }

            if ($view_question) {
                $view_options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$option_table} WHERE question_id = %d ORDER BY id ASC", $view_id), ARRAY_A);
                $view_detail = self::get_question_type_detail((int) $view_id, (string) ($view_question['question_type'] ?? ''));
            }
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $question_import_token = isset($_GET['cbt_question_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_question_import_token'])) : '';
        $question_import_state = null;
        $question_import_total = 0;
        $question_import_offset = 0;
        $question_import_created = 0;
        $question_import_failed = 0;
        $question_import_progress_percent = 0.0;
        $question_import_is_running = false;
        $question_import_continue_url = '';
        if ($question_import_token !== '') {
            $question_import_state = self::get_question_import_state_for_current_user($question_import_token);
            if (is_array($question_import_state)) {
                $question_import_total = max(0, isset($question_import_state['total']) ? (int) $question_import_state['total'] : 0);
                $question_import_offset = max(0, isset($question_import_state['offset']) ? (int) $question_import_state['offset'] : 0);
                if ($question_import_total > 0 && $question_import_offset > $question_import_total) {
                    $question_import_offset = $question_import_total;
                }
                $question_import_created = max(0, isset($question_import_state['created']) ? (int) $question_import_state['created'] : 0);
                $question_import_failed = max(0, isset($question_import_state['failed']) ? (int) $question_import_state['failed'] : 0);
                $question_import_progress_percent = $question_import_total > 0
                    ? round(((float) $question_import_offset / (float) $question_import_total) * 100, 2)
                    : 0.0;
                $question_import_is_running = $question_import_total > 0 && $question_import_offset < $question_import_total;
                $question_import_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_import_questions',
                        'cbt_question_import_token' => $question_import_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi import soal tidak ditemukan atau sudah berakhir. Silakan upload ulang file.';
            }
        }
        $show_import_panel_first = is_array($question_import_state);
        $question_delete_token = isset($_GET['cbt_question_delete_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_question_delete_token'])) : '';
        $question_delete_state = null;
        $question_delete_total = 0;
        $question_delete_offset = 0;
        $question_delete_deleted = 0;
        $question_delete_failed = 0;
        $question_delete_progress_percent = 0.0;
        $question_delete_is_running = false;
        $question_delete_continue_url = '';
        if ($question_delete_token !== '') {
            $question_delete_state = self::get_question_delete_state_for_current_user($question_delete_token);
            if (is_array($question_delete_state)) {
                $question_delete_total = max(0, isset($question_delete_state['total']) ? (int) $question_delete_state['total'] : 0);
                $question_delete_offset = max(0, isset($question_delete_state['offset']) ? (int) $question_delete_state['offset'] : 0);
                if ($question_delete_total > 0 && $question_delete_offset > $question_delete_total) {
                    $question_delete_offset = $question_delete_total;
                }
                $question_delete_deleted = max(0, isset($question_delete_state['deleted']) ? (int) $question_delete_state['deleted'] : 0);
                $question_delete_failed = max(0, isset($question_delete_state['failed']) ? (int) $question_delete_state['failed'] : 0);
                $question_delete_progress_percent = $question_delete_total > 0
                    ? round(((float) $question_delete_offset / (float) $question_delete_total) * 100, 2)
                    : 0.0;
                $question_delete_is_running = $question_delete_total > 0 && $question_delete_offset < $question_delete_total;
                $question_delete_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_bulk_delete_questions',
                        'cbt_question_delete_token' => $question_delete_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi hapus soal tidak ditemukan atau sudah berakhir. Silakan pilih ulang soal yang ingin dihapus.';
            }
        }

        if ($lock_question_type && $editing_question && (string) ($editing_question['question_type'] ?? '') !== $active_question_type) {
            $editing_question = null;
            $editing_options = [];
            $selected_subject_id = 0;
            if ($error === '') {
                $error = 'Edit dibatasi untuk jenis soal submenu ini.';
            }
        }

        $list_filter_type = '';
        if ($lock_question_type) {
            $list_filter_type = $active_question_type;
        } elseif (isset($_GET['filter_type'])) {
            $requested_filter_type = sanitize_text_field(wp_unslash($_GET['filter_type']));
            if (in_array($requested_filter_type, $allowed_question_types, true)) {
                $list_filter_type = $requested_filter_type;
            }
        }
        $list_per_page = isset($_GET['cbt_question_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash($_GET['cbt_question_per_page'])))
            : 20;
        $list_current_page = isset($_GET['cbt_question_paged']) ? max(1, absint(wp_unslash($_GET['cbt_question_paged']))) : 1;

        $question_where_parts = [];
        $question_where_parts_legacy = [];
        if (!$is_admin_scope) {
            $created_by_clause = $wpdb->prepare('e.created_by = %d', $current_user_id);
            $question_where_parts[] = $created_by_clause;
            $question_where_parts_legacy[] = $created_by_clause;
        }
        if ($list_filter_type !== '') {
            $type_clause = $wpdb->prepare('q.question_type = %s', $list_filter_type);
            $question_where_parts[] = $type_clause;
            $question_where_parts_legacy[] = $type_clause;
        }
        $question_where_parts[] = $wpdb->prepare('e.title LIKE %s', $bank_exam_title_like);
        $question_where = '';
        if (!empty($question_where_parts)) {
            $question_where = ' WHERE ' . implode(' AND ', $question_where_parts);
        }
        $question_where_legacy = '';
        if (!empty($question_where_parts_legacy)) {
            $question_where_legacy = ' WHERE ' . implode(' AND ', $question_where_parts_legacy);
        }
        $total_questions = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$question_table} q
             INNER JOIN {$exam_table} e ON e.id = q.exam_id
             {$question_where}"
        );
        $active_question_where = $question_where;
        if ($total_questions === 0) {
            $active_question_where = $question_where_legacy;
            $total_questions = (int) $wpdb->get_var(
                "SELECT COUNT(*)
                 FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 {$active_question_where}"
            );
        }
        $total_question_pages = max(1, (int) ceil($total_questions / $list_per_page));
        if ($total_questions > 0 && $list_current_page > $total_question_pages) {
            $list_current_page = $total_question_pages;
        }
        $question_offset = ($list_current_page - 1) * $list_per_page;
        $question_limit = (int) $list_per_page;
        $question_offset = (int) $question_offset;
        $questions = $wpdb->get_results(
            "SELECT q.*, e.title AS exam_title, s.name AS subject_name
             FROM {$question_table} q
             INNER JOIN {$exam_table} e ON e.id = q.exam_id
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             {$active_question_where}
             ORDER BY q.id DESC
             LIMIT {$question_limit} OFFSET {$question_offset}",
            ARRAY_A
        );
        $question_list_args = [
            'page' => $current_page_slug,
            'cbt_question_per_page' => $list_per_page,
            'cbt_question_paged' => $list_current_page,
        ];
        if ($list_filter_type !== '') {
            $question_list_args['filter_type'] = $list_filter_type;
        }

        $editing_type = $editing_question['question_type'] ?? ($lock_question_type ? $active_question_type : 'multiple_choice');
        $editing_detail = [];
        if ($editing_question && isset($editing_question['id'])) {
            $editing_detail = self::get_question_type_detail((int) $editing_question['id'], $editing_type);
        }

        $editing_short_answer_values = self::normalize_short_answer_values((string) ($editing_detail['correct_text'] ?? ($editing_question['correct_text'] ?? '')));
        $editing_short_answer_inputs = array_fill(1, 8, '');
        foreach ($editing_short_answer_values as $idx => $value) {
            $pos = $idx + 1;
            if ($pos > 8) {
                break;
            }
            $editing_short_answer_inputs[$pos] = $value;
        }
        $editing_short_answer_payload = !empty($editing_short_answer_values) ? wp_json_encode($editing_short_answer_values) : '';
        $editing_essay_answer = (string) ($editing_detail['rubric_text'] ?? ($editing_question['correct_text'] ?? ''));
        $editing_tf_matrix_values = self::normalize_true_false_matrix_config((string) ($editing_question['correct_text'] ?? ''));
        $tf_matrix_rows = array_fill(1, 10, ['text' => '', 'answer' => 'true']);
        foreach ($editing_tf_matrix_values as $idx => $row) {
            $pos = $idx + 1;
            if ($pos > 10) {
                break;
            }
            $tf_matrix_rows[$pos] = [
                'text' => (string) ($row['text'] ?? ''),
                'answer' => ((string) ($row['answer'] ?? 'true') === 'false') ? 'false' : 'true',
            ];
        }
        $editing_tf_matrix_payload = !empty($editing_tf_matrix_values)
            ? (string) wp_json_encode(['statements' => array_values($editing_tf_matrix_values)])
            : '';
        $mc_option_values = array_fill(1, 5, '');
        $ma_option_values = array_fill(1, 12, '');
        $ma_option_correct = array_fill(1, 12, false);
        $mc_correct_index = 1;
        $legacy_tf_seed = (string) ($editing_detail['correct_text'] ?? ($editing_question['correct_text'] ?? ''));
        $tf_correct = ((int) ($editing_detail['correct_value'] ?? (strtolower(trim($legacy_tf_seed)) === 'false' ? 0 : 1)) === 0)
            ? 'false'
            : 'true';

        if (!empty($editing_options)) {
            if ($editing_type === 'multiple_choice') {
                foreach ($editing_options as $idx => $opt) {
                    $pos = $idx + 1;
                    if ($pos > 5) {
                        break;
                    }
                    $mc_option_values[$pos] = (string) ($opt['option_text'] ?? '');
                    if ((int) ($opt['is_correct'] ?? 0) === 1) {
                        $mc_correct_index = $pos;
                    }
                }
            } elseif ($editing_type === 'multiple_answer') {
                foreach ($editing_options as $idx => $opt) {
                    $pos = $idx + 1;
                    if ($pos > 12) {
                        break;
                    }
                    $ma_option_values[$pos] = (string) ($opt['option_text'] ?? '');
                    $ma_option_correct[$pos] = ((int) ($opt['is_correct'] ?? 0) === 1);
                }
            } elseif ($editing_type === 'true_false' && empty($editing_detail)) {
                foreach ($editing_options as $opt) {
                    if ((int) ($opt['is_correct'] ?? 0) === 1) {
                        $txt = strtolower(trim((string) $opt['option_text']));
                        if ($txt === 'false') {
                            $tf_correct = 'false';
                        } else {
                            $tf_correct = 'true';
                        }
                        break;
                    }
                }
            }
        }

        if ($editing_type === 'short_answer') {
            $editing_short_answer_values = self::normalize_short_answer_values((string) ($editing_detail['correct_text'] ?? ($editing_question['correct_text'] ?? '')));
            $editing_short_answer_inputs = array_fill(1, 8, '');
            foreach ($editing_short_answer_values as $idx => $value) {
                $pos = $idx + 1;
                if ($pos > 8) {
                    break;
                }
                $editing_short_answer_inputs[$pos] = $value;
            }
            $editing_short_answer_payload = !empty($editing_short_answer_values) ? wp_json_encode($editing_short_answer_values) : '';
        }

        if ($editing_type === 'essay') {
            $editing_essay_answer = (string) ($editing_detail['rubric_text'] ?? ($editing_question['correct_text'] ?? ''));
        }

        if ($editing_type === 'true_false_matrix') {
            $editing_tf_matrix_values = self::normalize_true_false_matrix_config((string) ($editing_question['correct_text'] ?? ''));
            $tf_matrix_rows = array_fill(1, 10, ['text' => '', 'answer' => 'true']);
            foreach ($editing_tf_matrix_values as $idx => $row) {
                $pos = $idx + 1;
                if ($pos > 10) {
                    break;
                }
                $tf_matrix_rows[$pos] = [
                    'text' => (string) ($row['text'] ?? ''),
                    'answer' => ((string) ($row['answer'] ?? 'true') === 'false') ? 'false' : 'true',
                ];
            }
            $editing_tf_matrix_payload = !empty($editing_tf_matrix_values)
                ? (string) wp_json_encode(['statements' => array_values($editing_tf_matrix_values)])
                : '';
        }

        $initial_subject_id = $selected_subject_id > 0
            ? $selected_subject_id
            : (int) ($subjects[0]['id'] ?? 0);
        ?>
        <div class="wrap">
            <h1>CBT Questions / Pertanyaan</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($lock_question_type): ?>
                <div class="notice notice-info"><p>
                    Submenu aktif: <strong><?php echo esc_html((string) ($question_type_labels[$active_question_type] ?? $active_question_type)); ?></strong>.
                    Form dan daftar soal difilter ke jenis ini.
                </p></div>
            <?php endif; ?>
            <?php if ($view_question): ?>
                <div class="notice notice-info">
                    <p><strong>Preview Soal #<?php echo (int) $view_question['id']; ?></strong> (<?php echo esc_html((string) $view_question['question_type']); ?>)</p>
                    <div><?php echo wp_kses_post((string) ($view_question['question_text'] ?? '')); ?></div>
                    <?php if (!empty($view_options)): ?>
                        <ol style="margin-top:8px;">
                            <?php foreach ($view_options as $opt): ?>
                                <li>
                                    <?php echo wp_kses_post((string) ($opt['option_text'] ?? '')); ?>
                                    <?php if ((int) ($opt['is_correct'] ?? 0) === 1): ?>
                                        <strong>(Benar)</strong>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <?php if ((string) ($view_question['question_type'] ?? '') === 'short_answer'): ?>
                        <?php $view_short_answers = self::normalize_short_answer_values((string) ($view_detail['correct_text'] ?? '')); ?>
                        <?php if (!empty($view_short_answers)): ?>
                            <p><strong>Jawaban Valid:</strong></p>
                            <ol style="margin-top:8px;">
                                <?php foreach ($view_short_answers as $ans): ?>
                                    <li><?php echo esc_html($ans); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    <?php elseif ((string) ($view_question['question_type'] ?? '') === 'true_false'): ?>
                        <p><strong>Jawaban:</strong> <?php echo ((int) ($view_detail['correct_value'] ?? 1) === 1) ? 'True' : 'False'; ?></p>
                    <?php elseif ((string) ($view_question['question_type'] ?? '') === 'true_false_matrix'): ?>
                        <?php $view_tf_matrix = self::normalize_true_false_matrix_config((string) ($view_question['correct_text'] ?? '')); ?>
                        <?php if (!empty($view_tf_matrix)): ?>
                            <p><strong>Pernyataan (Kunci Benar/Salah):</strong></p>
                            <ol style="margin-top:8px;">
                                <?php foreach ($view_tf_matrix as $row): ?>
                                    <li>
                                        <?php echo esc_html((string) ($row['text'] ?? '')); ?>
                                        <strong>(<?php echo ((string) ($row['answer'] ?? 'true') === 'false') ? 'Salah' : 'Benar'; ?>)</strong>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    <?php elseif ((string) ($view_question['question_type'] ?? '') === 'essay'): ?>
                        <p><strong>Acuan Jawaban:</strong></p>
                        <div><?php echo wp_kses_post((string) ($view_detail['rubric_text'] ?? '')); ?></div>
                    <?php endif; ?>
                    <p style="margin-top:8px;">
                        <a class="button" href="<?php echo esc_url(add_query_arg($question_list_args, admin_url('admin.php'))); ?>">Tutup Preview</a>
                    </p>
                </div>
            <?php endif; ?>

            <style>
                .cbt-tab-buttons { display:flex; gap:8px; margin:12px 0; }
                .cbt-tab-buttons .button.cbt-active { background:#2271b1; color:#fff; border-color:#2271b1; }
                .cbt-tab-panel { display:none; margin-top:8px; }
                .cbt-tab-panel.cbt-active { display:block; }
                .cbt-qtype-panel { display:none; }
                .cbt-qtype-panel.cbt-active { display:table-row; }
                .cbt-inline-help { margin:6px 0 0; color:#50575e; }
                .cbt-option-list { display:grid; gap:8px; max-width:900px; }
                .cbt-option-row { display:flex; align-items:center; gap:8px; }
                .cbt-option-row label { width:84px; }
                .cbt-option-row label.cbt-inline-check { width:auto; }
                .cbt-option-row .wp-editor-wrap { flex:1; min-width:220px; }
                .cbt-option-row .wp-editor-wrap .wp-editor-area { width:100%; }
            </style>

            <div class="cbt-tab-buttons" id="cbt-question-mode-tabs">
                <button type="button" class="button<?php echo !$show_import_panel_first ? ' cbt-active' : ''; ?>" data-target="cbt-question-manual-panel">Input Manual</button>
                <button type="button" class="button<?php echo $show_import_panel_first ? ' cbt-active' : ''; ?>" data-target="cbt-question-import-panel">Import Word</button>
            </div>

            <div id="cbt-question-manual-panel" class="cbt-tab-panel<?php echo !$show_import_panel_first ? ' cbt-active' : ''; ?>">
                <h2><?php echo $editing_question ? 'Edit Question' : 'Add Question'; ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-question-manual-form">
                    <?php wp_nonce_field('cbt_save_question'); ?>
                    <input type="hidden" name="action" value="cbt_save_question" />
                    <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                    <input type="hidden" name="id" value="<?php echo esc_attr($editing_question['id'] ?? 0); ?>" />
                    <input type="hidden" id="cbt-question-type-hidden" name="question_type" value="<?php echo esc_attr($editing_type); ?>" />
                    <input type="hidden" id="cbt-correct-text-hidden" name="correct_text" value="<?php echo esc_attr($editing_type === 'short_answer' ? $editing_short_answer_payload : ($editing_type === 'true_false' ? $tf_correct : ($editing_type === 'true_false_matrix' ? $editing_tf_matrix_payload : ''))); ?>" />
                    <textarea id="cbt-options-hidden" name="options" style="display:none;"></textarea>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cbt-subject-id">Subject</label></th>
                            <td>
                                <select required id="cbt-subject-id" name="subject_id">
                                    <option value="">Select subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo (int) $subject['id']; ?>" <?php selected($initial_subject_id, (int) $subject['id']); ?>>
                                            <?php echo esc_html((string) $subject['name'] . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    Input manual difokuskan ke Subject + Jenis Soal.
                                    Soal akan masuk ke bank soal subject ini.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Jenis Soal</th>
                            <td>
                                <?php if ($lock_question_type): ?>
                                    <strong><?php echo esc_html((string) ($question_type_labels[$editing_type] ?? $editing_type)); ?></strong>
                                <?php else: ?>
                                    <div class="cbt-tab-buttons" id="cbt-question-type-tabs">
                                        <button type="button" class="button<?php echo $editing_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-qtype="multiple_choice">Multiple Choice</button>
                                        <button type="button" class="button<?php echo $editing_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-qtype="multiple_answer">Multiple Answer</button>
                                        <button type="button" class="button<?php echo $editing_type === 'true_false' ? ' cbt-active' : ''; ?>" data-qtype="true_false">True/False</button>
                                        <button type="button" class="button<?php echo $editing_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-qtype="true_false_matrix">TF Matrix</button>
                                        <button type="button" class="button<?php echo $editing_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-qtype="short_answer">Short Answer</button>
                                        <button type="button" class="button<?php echo $editing_type === 'essay' ? ' cbt-active' : ''; ?>" data-qtype="essay">Essay</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt_question_text_editor">Question</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    (string) ($editing_question['question_text'] ?? ''),
                                    'cbt_question_text_editor',
                                    [
                                        'textarea_name' => 'question_text',
                                        'textarea_rows' => 8,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                    ]
                                );
                                ?>
                                <p class="description">Bisa teks, tabel, dan gambar (upload/paste) langsung di editor soal.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-points">Points</label></th>
                            <td><input type="number" step="0.01" min="0" id="cbt-points" name="points" value="<?php echo esc_attr($editing_question['points'] ?? '1.00'); ?>" /></td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-qtype="multiple_choice">
                            <th>Multiple Choice</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt_mc_option_<?php echo (int) $i; ?>">Pilihan <?php echo (int) $i; ?></label>
                                            <?php
                                            $mc_editor_id = 'cbt_mc_option_' . (int) $i;
                                            wp_editor(
                                                (string) ($mc_option_values[$i] ?? ''),
                                                $mc_editor_id,
                                                [
                                                    'textarea_name' => $mc_editor_id,
                                                    'textarea_rows' => 3,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                ]
                                            );
                                            ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 2 pilihan, maksimal 5 pilihan. Tiap pilihan bisa teks atau gambar (paste/upload).</p>
                                <label for="cbt-correct-mc-index">Jawaban Benar</label>
                                <select id="cbt-correct-mc-index">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $mc_correct_index, $i); ?>>Pilihan <?php echo (int) $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-qtype="multiple_answer">
                            <th>Multiple Answer</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt_ma_option_<?php echo (int) $i; ?>">Pilihan <?php echo (int) $i; ?></label>
                                            <?php
                                            $ma_editor_id = 'cbt_ma_option_' . (int) $i;
                                            wp_editor(
                                                (string) ($ma_option_values[$i] ?? ''),
                                                $ma_editor_id,
                                                [
                                                    'textarea_name' => $ma_editor_id,
                                                    'textarea_rows' => 3,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                ]
                                            );
                                            ?>
                                            <label for="cbt-ma-correct-<?php echo (int) $i; ?>" class="cbt-inline-check">
                                                <input type="checkbox" id="cbt-ma-correct-<?php echo (int) $i; ?>" <?php checked((bool) ($ma_option_correct[$i] ?? false)); ?> />
                                                Benar
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 2 pilihan, maksimal 12 pilihan. Centang semua jawaban yang benar. Tiap pilihan bisa teks atau gambar.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'true_false' ? ' cbt-active' : ''; ?>" data-qtype="true_false">
                            <th>True/False</th>
                            <td>
                                <select id="cbt-correct-tf">
                                    <option value="true" <?php selected($tf_correct, 'true'); ?>>True</option>
                                    <option value="false" <?php selected($tf_correct, 'false'); ?>>False</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-qtype="true_false_matrix">
                            <th>True/False Matrix</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt-tfm-statement-<?php echo (int) $i; ?>">Pernyataan <?php echo (int) $i; ?></label>
                                            <input
                                                type="text"
                                                id="cbt-tfm-statement-<?php echo (int) $i; ?>"
                                                class="regular-text"
                                                style="flex:1; min-width:260px;"
                                                value="<?php echo esc_attr((string) ($tf_matrix_rows[$i]['text'] ?? '')); ?>"
                                                placeholder="Isi pernyataan ke-<?php echo (int) $i; ?>"
                                            />
                                            <select id="cbt-tfm-answer-<?php echo (int) $i; ?>">
                                                <option value="true" <?php selected((string) ($tf_matrix_rows[$i]['answer'] ?? 'true'), 'true'); ?>>Benar</option>
                                                <option value="false" <?php selected((string) ($tf_matrix_rows[$i]['answer'] ?? 'true'), 'false'); ?>>Salah</option>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 2 pernyataan. Siswa akan memilih Benar/Salah untuk setiap pernyataan.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-qtype="short_answer">
                            <th>Short Answer</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt-correct-sa-<?php echo (int) $i; ?>">Input <?php echo esc_html(chr(64 + $i)); ?></label>
                                            <input type="text" id="cbt-correct-sa-<?php echo (int) $i; ?>" class="regular-text" value="<?php echo esc_attr((string) ($editing_short_answer_inputs[$i] ?? '')); ?>" placeholder="Jawaban valid <?php echo esc_attr(chr(64 + $i)); ?>" />
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi berurutan dari Input A sampai maksimal Input H (8 textbox). Kosongkan sisanya jika tidak dipakai.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'essay' ? ' cbt-active' : ''; ?>" data-qtype="essay">
                            <th>Essay</th>
                            <td>
                                <?php
                                wp_editor(
                                    $editing_type === 'essay' ? (string) $editing_essay_answer : '',
                                    'cbt_essay_answer_editor',
                                    [
                                        'textarea_name' => 'essay_answer',
                                        'textarea_rows' => 6,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                    ]
                                );
                                ?>
                                <p class="description">Isi jawaban/acuan jawaban essay. Bisa menyertakan gambar.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button($editing_question ? 'Update Question' : 'Save Question'); ?>
                </form>
            </div>

            <div id="cbt-question-import-panel" class="cbt-tab-panel<?php echo $show_import_panel_first ? ' cbt-active' : ''; ?>">
                <h2>Import Questions (Word)</h2>
                <?php if (is_array($question_import_state)): ?>
                    <div class="notice notice-info">
                        <p>
                            <strong>Progress Import Soal:</strong>
                            <?php echo esc_html((string) $question_import_offset . ' / ' . (string) $question_import_total); ?>
                            (<?php echo esc_html(number_format($question_import_progress_percent, 2)); ?>%)
                            | Created: <?php echo esc_html((string) $question_import_created); ?>
                            | Failed: <?php echo esc_html((string) $question_import_failed); ?>
                        </p>
                    </div>
                    <div style="max-width:760px; margin:0 0 14px; border:1px solid #c3c4c7; border-radius:8px; background:#fff; padding:12px;">
                        <div style="width:100%; height:14px; border-radius:999px; background:#f0f0f1; overflow:hidden; border:1px solid #dcdcde;">
                            <div style="height:100%; width: <?php echo esc_attr((string) $question_import_progress_percent); ?>%; background:linear-gradient(90deg,#2271b1,#135e96); transition:width .25s ease;"></div>
                        </div>
                        <div style="margin-top:10px;">
                            <?php if ($question_import_is_running): ?>
                                <span style="color:#1d2327;">Memproses batch soal berikutnya...</span>
                                <script>
                                    window.setTimeout(function () {
                                        window.location.href = <?php echo wp_json_encode($question_import_continue_url); ?>;
                                    }, 350);
                                </script>
                            <?php else: ?>
                                <span style="color:#0a7a2f; font-weight:600;">Import soal selesai diproses.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <p class="description"><strong>Rekomendasi:</strong> gunakan file <code>.docx</code> sesuai jenis soal yang dipilih.</p>
                <?php if ($lock_question_type): ?>
                    <p><strong>Jenis Soal:</strong> <?php echo esc_html((string) ($question_type_labels[$import_active_type] ?? $import_active_type)); ?></p>
                <?php else: ?>
                    <div class="cbt-tab-buttons" id="cbt-import-type-tabs">
                        <button type="button" class="button<?php echo $import_active_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-import-type="multiple_choice">Multiple Choice</button>
                        <button type="button" class="button<?php echo $import_active_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-import-type="multiple_answer">Multiple Answer</button>
                        <button type="button" class="button<?php echo $import_active_type === 'true_false' ? ' cbt-active' : ''; ?>" data-import-type="true_false">True/False</button>
                        <button type="button" class="button<?php echo $import_active_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-import-type="true_false_matrix">TF Matrix</button>
                        <button type="button" class="button<?php echo $import_active_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-import-type="short_answer">Short Answer</button>
                        <button type="button" class="button<?php echo $import_active_type === 'essay' ? ' cbt-active' : ''; ?>" data-import-type="essay">Essay</button>
                    </div>
                <?php endif; ?>
                <p class="description" id="cbt-import-type-help"><?php echo esc_html($import_help_text); ?></p>
                <p style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <label for="cbt-word-template-count"><strong>Jumlah Soal</strong></label>
                    <select id="cbt-word-template-count">
                        <?php for ($count_option = 10; $count_option <= 100; $count_option += 10): ?>
                            <option value="<?php echo (int) $count_option; ?>"><?php echo (int) $count_option; ?></option>
                        <?php endfor; ?>
                    </select>
                    <a
                        id="cbt-download-word-template"
                        class="button button-secondary"
                        data-url-mc="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_mc'), 'cbt_download_question_template_word_mc')); ?>"
                        data-url-ma="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_ma'), 'cbt_download_question_template_word_ma')); ?>"
                        data-url-tf="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_tf'), 'cbt_download_question_template_word_tf')); ?>"
                        data-url-tfm="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_tfm'), 'cbt_download_question_template_word_tfm')); ?>"
                        data-url-sa="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_sa'), 'cbt_download_question_template_word_sa')); ?>"
                        data-url-essay="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_essay'), 'cbt_download_question_template_word_essay')); ?>"
                        href="<?php echo esc_url(add_query_arg('question_count', 10, wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_mc'), 'cbt_download_question_template_word_mc'))); ?>"
                    >
                        Download Template Word MC (.docx)
                    </a>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('cbt_import_questions'); ?>
                    <input type="hidden" name="action" value="cbt_import_questions" />
                    <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                    <input type="hidden" name="import_question_type" id="cbt-import-question-type" value="<?php echo esc_attr($import_active_type); ?>" />
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cbt-import-subject-id">Subject (utama)</label></th>
                            <td>
                                <select required id="cbt-import-subject-id" name="import_subject_id">
                                    <option value="">Select subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo (int) $subject['id']; ?>" <?php selected($initial_subject_id, (int) $subject['id']); ?>>
                                            <?php echo esc_html((string) $subject['name'] . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Soal import akan masuk ke bank soal untuk subject ini.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-question-file">File Import</label></th>
                            <td>
                                <input required type="file" id="cbt-question-file" name="question_file" accept="<?php echo esc_attr($import_file_accept); ?>" />
                                <p class="description">
                                    Format didukung: <code>.docx</code>.
                                    Template berbentuk <strong>tabel</strong> untuk <strong>multiple choice</strong>, <strong>multiple answer</strong>, <strong>true/false</strong>, <strong>true/false matrix</strong>, <strong>short answer</strong>, dan <strong>essay</strong> (gambar bisa ditempel langsung di soal, termasuk opsi jawaban).
                                    Progress import akan tampil otomatis (jumlah diproses, persentase, created/failed).
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Import Questions'); ?>
                </form>
            </div>

            <hr />

            <h2>Question List</h2>
            <?php if (is_array($question_delete_state)): ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Progress Hapus Soal:</strong>
                        <?php echo esc_html((string) $question_delete_offset . ' / ' . (string) $question_delete_total); ?>
                        (<?php echo esc_html(number_format($question_delete_progress_percent, 2)); ?>%)
                        | Deleted: <?php echo esc_html((string) $question_delete_deleted); ?>
                        | Failed: <?php echo esc_html((string) $question_delete_failed); ?>
                    </p>
                </div>
                <div style="max-width:760px; margin:0 0 14px; border:1px solid #c3c4c7; border-radius:8px; background:#fff; padding:12px;">
                    <div style="width:100%; height:14px; border-radius:999px; background:#f0f0f1; overflow:hidden; border:1px solid #dcdcde;">
                        <div style="height:100%; width: <?php echo esc_attr((string) $question_delete_progress_percent); ?>%; background:linear-gradient(90deg,#2271b1,#135e96); transition:width .25s ease;"></div>
                    </div>
                    <div style="margin-top:10px;">
                        <?php if ($question_delete_is_running): ?>
                            <span style="color:#1d2327;">Memproses batch hapus soal berikutnya...</span>
                            <script>
                                window.setTimeout(function () {
                                    window.location.href = <?php echo wp_json_encode($question_delete_continue_url); ?>;
                                }, 350);
                            </script>
                        <?php else: ?>
                            <span style="color:#0a7a2f; font-weight:600;">Proses hapus soal selesai diproses.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 8px 0 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>" />
                <label for="cbt-filter-question-type" style="margin-right:8px;">Type</label>
                <?php if ($lock_question_type): ?>
                    <input type="hidden" name="filter_type" value="<?php echo esc_attr($list_filter_type); ?>" />
                    <select id="cbt-filter-question-type" disabled style="margin-right:12px;">
                        <option value="<?php echo esc_attr($list_filter_type); ?>">
                            <?php echo esc_html((string) ($question_type_labels[$list_filter_type] ?? $list_filter_type)); ?>
                        </option>
                    </select>
                <?php else: ?>
                    <select id="cbt-filter-question-type" name="filter_type" style="margin-right:12px;">
                        <option value="">All types</option>
                        <?php foreach ($allowed_question_types as $question_type): ?>
                            <option value="<?php echo esc_attr($question_type); ?>" <?php selected($list_filter_type, $question_type); ?>>
                                <?php echo esc_html((string) ($question_type_labels[$question_type] ?? $question_type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label for="cbt-question-per-page">Per halaman</label>
                <select id="cbt-question-per-page" name="cbt_question_per_page">
                    <?php foreach ([20, 40, 60, 80, 100] as $question_per_page_option): ?>
                        <option value="<?php echo (int) $question_per_page_option; ?>" <?php selected($list_per_page, $question_per_page_option); ?>>
                            <?php echo esc_html((string) $question_per_page_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button button-secondary">Filter</button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => $current_page_slug, 'cbt_question_per_page' => $list_per_page], admin_url('admin.php'))); ?>">Reset</a>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Hapus semua soal yang dipilih?');">
                <?php wp_nonce_field('cbt_bulk_delete_questions'); ?>
                <input type="hidden" name="action" value="cbt_bulk_delete_questions" />
                <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                <input type="hidden" name="redirect_filter_type" value="<?php echo esc_attr($list_filter_type); ?>" />
                <input type="hidden" name="redirect_question_per_page" value="<?php echo (int) $list_per_page; ?>" />
                <input type="hidden" name="redirect_question_paged" value="<?php echo (int) $list_current_page; ?>" />
                <p style="margin: 0 0 8px;">
                    <button type="button" class="button button-secondary" id="cbt-view-selected-questions">Lihat Selected</button>
                    <button type="submit" class="button button-secondary">Delete Selected</button>
                </p>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="cbt-select-all-questions" /></th>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Question</th>
                        <th>Points</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$questions): ?>
                        <tr><td colspan="7">No questions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <?php $question_view_url = add_query_arg(array_merge($question_list_args, ['view' => (int) $question['id']]), admin_url('admin.php')); ?>
                                <td><input type="checkbox" class="cbt-question-checkbox" name="question_ids[]" value="<?php echo (int) $question['id']; ?>" data-view-url="<?php echo esc_url($question_view_url); ?>" /></td>
                                <td><?php echo (int) $question['id']; ?></td>
                                <td><?php echo esc_html((string) ($question['subject_name'] ?? '-')); ?></td>
                                <td><?php echo esc_html($question['question_type']); ?></td>
                                <td><?php echo esc_html(wp_trim_words((string) $question['question_text'], 12)); ?></td>
                                <td><?php echo esc_html((string) $question['points']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($question_view_url); ?>">Lihat</a>
                                    |
                                    <a href="<?php echo esc_url(add_query_arg(array_merge($question_list_args, ['edit' => (int) $question['id']]), admin_url('admin.php'))); ?>">Edit</a>
                                    |
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'cbt_delete_question', 'id' => (int) $question['id'], 'return_page' => $current_page_slug, 'filter_type' => $list_filter_type, 'question_per_page' => $list_per_page, 'question_paged' => $list_current_page], admin_url('admin-post.php')), 'cbt_delete_question_' . (int) $question['id'])); ?>" onclick="return confirm('Delete this question?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php
                $question_pagination_links = [];
                if ($total_question_pages > 1) {
                    $question_pagination_links = paginate_links([
                        'base' => add_query_arg(
                            array_merge($question_list_args, ['cbt_question_paged' => '%#%']),
                            admin_url('admin.php')
                        ),
                        'format' => '',
                        'current' => $list_current_page,
                        'total' => $total_question_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'type' => 'array',
                        'end_size' => 1,
                        'mid_size' => 1,
                    ]);
                }
                ?>
                <div class="tablenav bottom cbt-admin-pagination-wrap" style="margin-top:10px;">
                    <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                        <span class="displaying-num cbt-admin-total"><?php echo esc_html(sprintf('Total question: %d', $total_questions)); ?></span>
                        <?php if (!empty($question_pagination_links)): ?>
                            <span class="pagination-links cbt-admin-pagination-links">
                                <?php foreach ($question_pagination_links as $question_pagination_link): ?>
                                    <?php echo wp_kses_post($question_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <style>
                .cbt-admin-pagination-wrap {
                    clear: both;
                }
                .cbt-admin-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination .cbt-admin-total {
                    font-size: 14px;
                    line-height: 1.4;
                    color: #1d2327;
                    font-weight: 500;
                }
                .cbt-admin-pagination-links {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 34px;
                    height: 34px;
                    padding: 0 12px;
                    border: 1px solid #c3c4c7;
                    border-radius: 6px;
                    background: #fff;
                    color: #1d2327;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                }
                .cbt-admin-pagination-links .page-numbers:hover,
                .cbt-admin-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.15);
                    color: #0a4b78;
                    outline: none;
                }
                .cbt-admin-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #fff;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
                }
                .cbt-admin-pagination-links .page-numbers.prev,
                .cbt-admin-pagination-links .page-numbers.next {
                    padding: 0 14px;
                    font-weight: 600;
                }
                .cbt-admin-pagination-links .page-numbers.dots {
                    border-color: transparent;
                    background: transparent;
                    color: #646970;
                    min-width: auto;
                    padding: 0 4px;
                    box-shadow: none;
                }
                @media (max-width: 782px) {
                    .cbt-admin-pagination {
                        align-items: flex-start;
                    }
                    .cbt-admin-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        padding: 0 10px;
                        font-size: 13px;
                    }
                }
            </style>
        </div>
        <script>
            (function () {
                const modeTabs = document.getElementById('cbt-question-mode-tabs');
                const modePanels = document.querySelectorAll('.cbt-tab-panel');
                if (modeTabs) {
                    modeTabs.querySelectorAll('button[data-target]').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            modeTabs.querySelectorAll('button[data-target]').forEach((b) => b.classList.remove('cbt-active'));
                            btn.classList.add('cbt-active');
                            modePanels.forEach((panel) => panel.classList.remove('cbt-active'));
                            const target = document.getElementById(btn.getAttribute('data-target'));
                            if (target) target.classList.add('cbt-active');
                        });
                    });
                }

                const qTypeHidden = document.getElementById('cbt-question-type-hidden');
                const qTypeTabs = document.getElementById('cbt-question-type-tabs');
                const qTypePanels = document.querySelectorAll('.cbt-qtype-panel');

                function activateQType(type, shouldFocus = false) {
                    if (!qTypeHidden) return;
                    qTypeHidden.value = type;
                    if (qTypeTabs) {
                        qTypeTabs.querySelectorAll('button[data-qtype]').forEach((btn) => {
                            btn.classList.toggle('cbt-active', btn.getAttribute('data-qtype') === type);
                        });
                    }
                    qTypePanels.forEach((panel) => {
                        panel.classList.toggle('cbt-active', panel.getAttribute('data-qtype') === type);
                    });

                    if (!shouldFocus) return;

                    if (type === 'multiple_choice') {
                        document.getElementById('cbt-correct-mc-index')?.focus();
                    } else if (type === 'multiple_answer') {
                        document.getElementById('cbt-ma-correct-1')?.focus();
                    } else if (type === 'true_false') {
                        document.getElementById('cbt-correct-tf')?.focus();
                    } else if (type === 'true_false_matrix') {
                        document.getElementById('cbt-tfm-statement-1')?.focus();
                    } else if (type === 'short_answer') {
                        document.getElementById('cbt-correct-sa-1')?.focus();
                    } else if (type === 'essay') {
                        document.getElementById('cbt_essay_answer_editor')?.focus();
                    }
                }

                if (qTypeTabs) {
                    qTypeTabs.querySelectorAll('button[data-qtype]').forEach((btn) => {
                        btn.addEventListener('click', () => activateQType(btn.getAttribute('data-qtype'), true));
                    });
                }
                activateQType(qTypeHidden?.value || 'multiple_choice');

                const importTypeTabs = document.getElementById('cbt-import-type-tabs');
                const importTypeHidden = document.getElementById('cbt-import-question-type');
                const importHelp = document.getElementById('cbt-import-type-help');
                const importFileInput = document.getElementById('cbt-question-file');
                const wordTemplateButton = document.getElementById('cbt-download-word-template');
                const wordTemplateCount = document.getElementById('cbt-word-template-count');
                const docxFileAccept = '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

                const importTypeInfo = {
                    multiple_choice: {
                        help: 'Mode import aktif: Multiple Choice. DOCX didukung (maks 5 opsi, jawaban nomor opsi, gambar bisa ditempel).',
                        buttonLabel: 'Download Template Word MC (.docx)',
                        urlKey: 'urlMc',
                    },
                    multiple_answer: {
                        help: 'Mode import aktif: Multiple Answer. DOCX didukung (maks 12 opsi, jawaban bisa lebih dari satu: contoh 1,3,5).',
                        buttonLabel: 'Download Template Word MA (.docx)',
                        urlKey: 'urlMa',
                    },
                    true_false: {
                        help: 'Mode import aktif: True/False. DOCX didukung (jawaban: true/false).',
                        buttonLabel: 'Download Template Word TF (.docx)',
                        urlKey: 'urlTf',
                    },
                    true_false_matrix: {
                        help: 'Mode import aktif: True/False Matrix. DOCX didukung (isi PERNYATAAN_1..10 dan KUNCI_1..10: true/false).',
                        buttonLabel: 'Download Template Word TF Matrix (.docx)',
                        urlKey: 'urlTfm',
                    },
                    short_answer: {
                        help: 'Mode import aktif: Short Answer. DOCX didukung (maks 8 jawaban valid per soal, gunakan placeholder [INPUT_1] s.d. [INPUT_8] di teks soal).',
                        buttonLabel: 'Download Template Word SA (.docx)',
                        urlKey: 'urlSa',
                    },
                    essay: {
                        help: 'Mode import aktif: Essay. DOCX didukung (wajib isi acuan jawaban/rubrik).',
                        buttonLabel: 'Download Template Word Essay (.docx)',
                        urlKey: 'urlEssay',
                    },
                };

                function activateImportType(type) {
                    if (!importTypeHidden) return;

                    const info = importTypeInfo[type] || importTypeInfo.multiple_choice;
                    importTypeHidden.value = type;

                    if (importTypeTabs) {
                        importTypeTabs.querySelectorAll('button[data-import-type]').forEach((btn) => {
                            btn.classList.toggle('cbt-active', btn.getAttribute('data-import-type') === type);
                        });
                    }

                    if (importHelp) {
                        importHelp.textContent = info.help;
                    }

                    if (importFileInput) {
                        importFileInput.accept = docxFileAccept;
                    }

                    if (wordTemplateButton) {
                        const parsedCount = parseInt(wordTemplateCount?.value || '10', 10);
                        const safeCount = Number.isFinite(parsedCount) ? parsedCount : 10;
                        let selectedCount = Math.floor(safeCount / 10) * 10;
                        if (selectedCount < 10) selectedCount = 10;
                        if (selectedCount > 100) selectedCount = 100;
                        if (wordTemplateCount && String(wordTemplateCount.value) !== String(selectedCount)) {
                            wordTemplateCount.value = String(selectedCount);
                        }
                        const baseUrl = wordTemplateButton.dataset[info.urlKey] || '';
                        if (baseUrl) {
                            const separator = baseUrl.includes('?') ? '&' : '?';
                            wordTemplateButton.setAttribute('href', `${baseUrl}${separator}question_count=${selectedCount}`);
                        }
                        wordTemplateButton.textContent = info.buttonLabel;
                    }
                }

                if (importTypeTabs) {
                    importTypeTabs.querySelectorAll('button[data-import-type]').forEach((btn) => {
                        btn.addEventListener('click', () => activateImportType(btn.getAttribute('data-import-type')));
                    });
                }

                if (wordTemplateCount) {
                    wordTemplateCount.addEventListener('change', () => {
                        activateImportType(importTypeHidden?.value || 'multiple_choice');
                    });
                }

                activateImportType(importTypeHidden?.value || 'multiple_choice');

                const selectAllQuestions = document.getElementById('cbt-select-all-questions');
                const questionCheckboxes = Array.from(document.querySelectorAll('.cbt-question-checkbox'));
                const viewSelectedQuestionsButton = document.getElementById('cbt-view-selected-questions');
                if (selectAllQuestions && questionCheckboxes.length > 0) {
                    const syncSelectAllState = () => {
                        const checkedCount = questionCheckboxes.filter((item) => item.checked).length;
                        selectAllQuestions.checked = checkedCount > 0 && checkedCount === questionCheckboxes.length;
                        selectAllQuestions.indeterminate = checkedCount > 0 && checkedCount < questionCheckboxes.length;
                    };

                    selectAllQuestions.addEventListener('change', () => {
                        questionCheckboxes.forEach((item) => {
                            item.checked = selectAllQuestions.checked;
                        });
                        syncSelectAllState();
                    });

                    questionCheckboxes.forEach((item) => {
                        item.addEventListener('change', syncSelectAllState);
                    });
                }

                if (viewSelectedQuestionsButton && questionCheckboxes.length > 0) {
                    viewSelectedQuestionsButton.addEventListener('click', () => {
                        const selectedViewUrls = questionCheckboxes
                            .filter((item) => item.checked)
                            .map((item) => String(item.dataset.viewUrl || '').trim())
                            .filter((url) => url !== '');

                        if (selectedViewUrls.length === 0) {
                            alert('Pilih minimal 1 soal untuk dilihat.');
                            return;
                        }

                        let openedCount = 0;
                        selectedViewUrls.forEach((url) => {
                            const openedWindow = window.open(url, '_blank', 'noopener');
                            if (openedWindow) {
                                openedCount += 1;
                            }
                        });

                        if (openedCount === 0) {
                            alert('Browser memblokir tab baru. Izinkan pop-up untuk halaman ini.');
                        }
                    });
                }

                const manualForm = document.getElementById('cbt-question-manual-form');
                if (manualForm) {
                    manualForm.addEventListener('submit', (event) => {
                        if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                            window.tinyMCE.triggerSave();
                        }

                        const type = qTypeHidden ? qTypeHidden.value : 'multiple_choice';
                        const optionsHidden = document.getElementById('cbt-options-hidden');
                        const correctTextHidden = document.getElementById('cbt-correct-text-hidden');
                        if (!optionsHidden || !correctTextHidden) return;

                        optionsHidden.value = '';
                        correctTextHidden.value = '';

                        const editorValue = (id) => String(document.getElementById(id)?.value || '').trim();
                        const hasOptionContent = (html) => {
                            const raw = String(html || '');
                            if (/<img\b/i.test(raw)) return true;
                            const textOnly = raw
                                .replace(/<[^>]*>/g, '')
                                .replace(/&nbsp;/gi, ' ')
                                .trim();
                            return textOnly !== '';
                        };

                        if (type === 'multiple_choice') {
                            const optionsPayload = [];
                            const correctIdx = parseInt(String(document.getElementById('cbt-correct-mc-index')?.value || '1'), 10);
                            let filledCount = 0;

                            for (let i = 1; i <= 5; i += 1) {
                                const optVal = editorValue(`cbt_mc_option_${i}`);
                                if (!hasOptionContent(optVal)) continue;
                                filledCount += 1;
                                optionsPayload.push({
                                    option_text: optVal,
                                    is_correct: i === correctIdx ? 1 : 0,
                                });
                            }

                            if (filledCount < 2) {
                                event.preventDefault();
                                window.alert('Multiple Choice minimal harus punya 2 pilihan.');
                                return;
                            }

                            if (!optionsPayload.some((item) => Number(item.is_correct) === 1)) {
                                event.preventDefault();
                                window.alert('Pilih jawaban benar untuk Multiple Choice.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                        } else if (type === 'multiple_answer') {
                            const optionsPayload = [];
                            let filledCount = 0;
                            let correctCount = 0;

                            for (let i = 1; i <= 12; i += 1) {
                                const optVal = editorValue(`cbt_ma_option_${i}`);
                                if (!hasOptionContent(optVal)) continue;
                                filledCount += 1;
                                const checked = !!document.getElementById(`cbt-ma-correct-${i}`)?.checked;
                                if (checked) correctCount += 1;
                                optionsPayload.push({
                                    option_text: optVal,
                                    is_correct: checked ? 1 : 0,
                                });
                            }

                            if (filledCount < 2) {
                                event.preventDefault();
                                window.alert('Multiple Answer minimal harus punya 2 pilihan.');
                                return;
                            }

                            if (correctCount === 0) {
                                event.preventDefault();
                                window.alert('Centang minimal 1 jawaban benar untuk Multiple Answer.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                        } else if (type === 'true_false') {
                            const tf = String(document.getElementById('cbt-correct-tf')?.value || 'true').toLowerCase();
                            correctTextHidden.value = tf === 'false' ? 'false' : 'true';
                        } else if (type === 'true_false_matrix') {
                            const statements = [];
                            for (let i = 1; i <= 10; i += 1) {
                                const statementText = String(document.getElementById(`cbt-tfm-statement-${i}`)?.value || '').trim();
                                if (statementText === '') continue;
                                const answerValue = String(document.getElementById(`cbt-tfm-answer-${i}`)?.value || 'true').toLowerCase();
                                statements.push({
                                    text: statementText,
                                    answer: answerValue === 'false' ? 'false' : 'true',
                                });
                            }

                            if (statements.length < 2) {
                                event.preventDefault();
                                window.alert('True/False Matrix minimal harus punya 2 pernyataan.');
                                return;
                            }

                            correctTextHidden.value = JSON.stringify({ statements });
                        } else if (type === 'short_answer') {
                            const shortAnswerValues = [];
                            for (let i = 1; i <= 8; i += 1) {
                                const val = String(document.getElementById(`cbt-correct-sa-${i}`)?.value || '').trim();
                                if (val !== '') {
                                    shortAnswerValues.push(val);
                                }
                            }

                            if (shortAnswerValues.length === 0) {
                                event.preventDefault();
                                window.alert('Short Answer minimal harus punya 1 jawaban valid.');
                                return;
                            }

                            correctTextHidden.value = JSON.stringify(shortAnswerValues.slice(0, 8));
                        }
                    });
                }
            })();
        </script>
        <?php
    }

    public static function render_results_page(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();

        $selected_exam_id = isset($_GET['cbt_exam_id']) ? absint($_GET['cbt_exam_id']) : 0;
        $selected_status = isset($_GET['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_GET['cbt_attempt_status'])) : '';
        $selected_kelas = isset($_GET['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_GET['cbt_result_kelas'])) : '';
        $student_keyword = isset($_GET['cbt_student_q']) ? sanitize_text_field(wp_unslash($_GET['cbt_student_q'])) : '';
        $current_page = isset($_GET['cbt_results_paged']) ? max(1, absint(wp_unslash($_GET['cbt_results_paged']))) : 1;
        $results_per_page = 20;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($selected_status, $allowed_statuses, true)) {
            $selected_status = '';
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';

        $exam_filter_where = '1=1';
        $exam_filter_params = [];
        if (!$is_admin_scope) {
            $exam_filter_where .= ' AND created_by = %d';
            $exam_filter_params[] = $current_user_id;
        }
        $exam_filter_sql = "SELECT id, title FROM {$exam_table} WHERE {$exam_filter_where} ORDER BY id DESC";
        if (!empty($exam_filter_params)) {
            $exam_filter_sql = $wpdb->prepare($exam_filter_sql, $exam_filter_params);
        }
        $exam_filter_rows = $wpdb->get_results($exam_filter_sql, ARRAY_A);
        $kelas_filter_rows = self::get_distinct_user_meta_values('kode_kelas');

        $selected_kelas = trim($selected_kelas);
        $student_keyword = trim($student_keyword);
        $attempt_base_where_parts = ['1=1'];
        $attempt_base_where_params = [];
        if (!$is_admin_scope) {
            $attempt_base_where_parts[] = 'e.created_by = %d';
            $attempt_base_where_params[] = $current_user_id;
        }
        if ($selected_exam_id > 0) {
            $attempt_base_where_parts[] = 'a.exam_id = %d';
            $attempt_base_where_params[] = $selected_exam_id;
        }
        if ($selected_kelas !== '') {
            $attempt_base_where_parts[] = 'kelas_meta.meta_value = %s';
            $attempt_base_where_params[] = $selected_kelas;
        }
        if ($student_keyword !== '') {
            $student_like = '%' . $wpdb->esc_like($student_keyword) . '%';
            $attempt_base_where_parts[] = '(u.user_login LIKE %s OR nisn_meta.meta_value LIKE %s)';
            $attempt_base_where_params[] = $student_like;
            $attempt_base_where_params[] = $student_like;
        }

        $attempt_where_parts = $attempt_base_where_parts;
        $attempt_where_params = $attempt_base_where_params;
        if ($selected_status !== '') {
            $attempt_where_parts[] = 'a.status = %s';
            $attempt_where_params[] = $selected_status;
        } else {
            $attempt_where_parts[] = "a.status IN ('in_progress', 'completed')";
        }
        $attempt_where = ' WHERE ' . implode(' AND ', $attempt_where_parts);
        $attempts_from_sql = "FROM {$attempt_table} a
                              INNER JOIN {$exam_table} e ON e.id = a.exam_id
                              INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                              LEFT JOIN (
                                  SELECT user_id, MAX(meta_value) AS meta_value
                                  FROM {$wpdb->usermeta}
                                  WHERE meta_key = 'kode_kelas'
                                  GROUP BY user_id
                              ) kelas_meta ON kelas_meta.user_id = u.ID
                              LEFT JOIN (
                                  SELECT user_id, MAX(meta_value) AS meta_value
                                  FROM {$wpdb->usermeta}
                                  WHERE meta_key = 'nisn'
                                  GROUP BY user_id
                              ) nisn_meta ON nisn_meta.user_id = u.ID";

        $attempt_count_sql = "SELECT COUNT(*) {$attempts_from_sql} {$attempt_where}";
        if (!empty($attempt_where_params)) {
            $attempt_count_sql = $wpdb->prepare($attempt_count_sql, $attempt_where_params);
        }
        $total_attempts = (int) $wpdb->get_var($attempt_count_sql);

        $resettable_where_parts = $attempt_base_where_parts;
        $resettable_where_params = $attempt_base_where_params;
        if ($selected_status === 'in_progress') {
            $resettable_where_parts[] = '1 = 0';
        } else {
            $resettable_where_parts[] = "a.status = 'completed'";
        }
        $resettable_where = ' WHERE ' . implode(' AND ', $resettable_where_parts);
        $resettable_count_sql = "SELECT COUNT(*) {$attempts_from_sql} {$resettable_where}";
        if (!empty($resettable_where_params)) {
            $resettable_count_sql = $wpdb->prepare($resettable_count_sql, $resettable_where_params);
        }
        $resettable_attempts_count = (int) $wpdb->get_var($resettable_count_sql);

        $completable_where_parts = $attempt_base_where_parts;
        $completable_where_params = $attempt_base_where_params;
        if ($selected_status === 'completed') {
            $completable_where_parts[] = '1 = 0';
        } else {
            $completable_where_parts[] = "a.status = 'in_progress'";
        }
        $completable_where = ' WHERE ' . implode(' AND ', $completable_where_parts);
        $completable_count_sql = "SELECT COUNT(*) {$attempts_from_sql} {$completable_where}";
        if (!empty($completable_where_params)) {
            $completable_count_sql = $wpdb->prepare($completable_count_sql, $completable_where_params);
        }
        $completable_attempts_count = (int) $wpdb->get_var($completable_count_sql);

        $total_pages = max(1, (int) ceil($total_attempts / $results_per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $results_per_page;

        $attempt_sql = "SELECT a.*,
                               e.title AS exam_title,
                               u.display_name AS student_name,
                               u.user_login AS student_username,
                               kelas_meta.meta_value AS student_kelas,
                               nisn_meta.meta_value AS student_nisn,
                               (SELECT COUNT(*) FROM {$answer_table} ans WHERE ans.attempt_id = a.id) AS answer_count,
                               (SELECT COUNT(*) FROM {$question_table} qcount WHERE qcount.exam_id = a.exam_id) AS question_count,
                               CASE
                                   WHEN COALESCE(a.max_score, 0) > 0 THEN a.max_score
                                   ELSE (SELECT COALESCE(SUM(qpoints.points), 0) FROM {$question_table} qpoints WHERE qpoints.exam_id = a.exam_id)
                               END AS total_points,
                               (SELECT COALESCE(SUM(qanswered.points), 0)
                                FROM {$answer_table} ansanswered
                                INNER JOIN {$question_table} qanswered ON qanswered.id = ansanswered.question_id
                                WHERE ansanswered.attempt_id = a.id) AS answered_points,
                               CASE
                                   WHEN a.status = 'completed' THEN COALESCE(a.score, 0)
                                   ELSE (SELECT COALESCE(SUM(anscore.score_awarded), 0) FROM {$answer_table} anscore WHERE anscore.attempt_id = a.id)
                               END AS earned_points
                        FROM {$attempt_table} a
                        INNER JOIN {$exam_table} e ON e.id = a.exam_id
                        INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                        LEFT JOIN (
                            SELECT user_id, MAX(meta_value) AS meta_value
                            FROM {$wpdb->usermeta}
                            WHERE meta_key = 'kode_kelas'
                            GROUP BY user_id
                        ) kelas_meta ON kelas_meta.user_id = u.ID
                        LEFT JOIN (
                            SELECT user_id, MAX(meta_value) AS meta_value
                            FROM {$wpdb->usermeta}
                            WHERE meta_key = 'nisn'
                            GROUP BY user_id
                        ) nisn_meta ON nisn_meta.user_id = u.ID
                        {$attempt_where}
                        ORDER BY a.id DESC
                        LIMIT %d OFFSET %d";
        $attempt_sql_params = array_merge($attempt_where_params, [$results_per_page, $offset]);
        $attempt_sql = $wpdb->prepare($attempt_sql, $attempt_sql_params);
        $attempts = $wpdb->get_results($attempt_sql, ARRAY_A);
        $attempt_answer_progress_map = self::build_attempt_answer_progress_map(
            $attempts,
            $question_table,
            $answer_table,
            $option_table
        );

        $essay_where_parts = ["q.question_type = 'essay'"];
        $essay_where_params = [];
        if (!$is_admin_scope) {
            $essay_where_parts[] = 'ex.created_by = %d';
            $essay_where_params[] = $current_user_id;
        }
        if ($selected_exam_id > 0) {
            $essay_where_parts[] = 'att.exam_id = %d';
            $essay_where_params[] = $selected_exam_id;
        }
        $essay_where = ' WHERE ' . implode(' AND ', $essay_where_parts);
        $essay_sql = "SELECT ans.id AS answer_id,
                             ans.attempt_id,
                             ans.answer_text,
                             ans.score_awarded,
                             q.points,
                             q.question_text,
                             u.display_name,
                             ex.title AS exam_title
                      FROM {$answer_table} ans
                      INNER JOIN {$question_table} q ON q.id = ans.question_id
                      INNER JOIN {$attempt_table} att ON att.id = ans.attempt_id
                      INNER JOIN {$exam_table} ex ON ex.id = att.exam_id
                      INNER JOIN {$wpdb->users} u ON u.ID = att.student_id
                      {$essay_where}
                      ORDER BY ans.id DESC
                      LIMIT 300";
        if (!empty($essay_where_params)) {
            $essay_sql = $wpdb->prepare($essay_sql, $essay_where_params);
        }
        $essay_rows = $wpdb->get_results($essay_sql, ARRAY_A);
        ?>
        <div class="wrap">
            <h1>CBT Results</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <style>
                .cbt-attempt-answer-cell {
                    min-width: 360px;
                }
                .cbt-attempt-progress-wrap {
                    display: grid;
                    gap: 6px;
                }
                .cbt-attempt-progress-line {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .cbt-attempt-progress-track {
                    position: relative;
                    flex: 1;
                    height: 10px;
                    border-radius: 999px;
                    border: 1px solid #d9e4f2;
                    background: #f3f7fb;
                    overflow: hidden;
                }
                .cbt-attempt-progress-fill {
                    display: block;
                    height: 100%;
                    background: linear-gradient(90deg, #1b7aa5, #38a8ce);
                }
                .cbt-attempt-answer-details {
                    margin-top: 2px;
                }
                .cbt-attempt-answer-details > summary {
                    cursor: pointer;
                    user-select: none;
                    color: #0f4c81;
                    font-weight: 600;
                }
                .cbt-attempt-answer-grid {
                    margin-top: 8px;
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(28px, 1fr));
                    gap: 5px;
                    max-height: 190px;
                    overflow: auto;
                    padding-right: 4px;
                }
                .cbt-attempt-answer-chip {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 24px;
                    border-radius: 6px;
                    border: 1px solid #d8dee8;
                    background: #eef2f7;
                    font-size: 11px;
                    font-weight: 700;
                    color: #334155;
                    line-height: 1;
                }
                .cbt-attempt-answer-chip.is-correct {
                    border-color: #8dd5bc;
                    background: #eafbf4;
                    color: #0f7a56;
                }
                .cbt-attempt-answer-chip.is-wrong {
                    border-color: #f5c2c2;
                    background: #fff2f2;
                    color: #b42323;
                }
                .cbt-attempt-answer-chip.is-manual {
                    border-color: #f0bb63;
                    background: #fff8ea;
                    color: #b4690e;
                }
                .cbt-attempt-answer-list {
                    margin: 8px 0 0;
                    padding: 0;
                    list-style: none;
                    display: grid;
                    gap: 4px;
                    max-height: 210px;
                    overflow: auto;
                }
                .cbt-attempt-answer-list-item {
                    border: 1px solid #d8dee8;
                    border-left: 4px solid #cbd5e1;
                    border-radius: 6px;
                    background: #fff;
                    padding: 6px 8px;
                    font-size: 11px;
                    line-height: 1.45;
                    color: #334155;
                }
                .cbt-attempt-answer-list-item.is-correct {
                    border-left-color: #0f7a56;
                    background: #f4fdf8;
                }
                .cbt-attempt-answer-list-item.is-wrong {
                    border-left-color: #b42323;
                    background: #fff6f6;
                }
                .cbt-attempt-answer-list-item.is-manual {
                    border-left-color: #b4690e;
                    background: #fffaf2;
                }
                .cbt-attempt-answer-list-meta {
                    font-weight: 700;
                    margin-right: 5px;
                }
                .cbt-attempt-answer-slot-group {
                    display: inline-flex;
                    flex-wrap: wrap;
                    gap: 4px;
                    vertical-align: middle;
                }
                .cbt-attempt-answer-slot {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    border: 1px solid #d8dee8;
                    border-radius: 999px;
                    background: #f8fafc;
                    padding: 1px 7px;
                    line-height: 1.5;
                }
                .cbt-attempt-answer-slot-key {
                    font-size: 10px;
                    font-weight: 700;
                    color: #334155;
                }
                .cbt-attempt-answer-slot-val {
                    font-size: 11px;
                    font-weight: 600;
                    color: #0f172a;
                }
                .cbt-attempt-answer-slot.is-correct {
                    border-color: #8dd5bc;
                    background: #eafbf4;
                }
                .cbt-attempt-answer-slot.is-correct .cbt-attempt-answer-slot-key,
                .cbt-attempt-answer-slot.is-correct .cbt-attempt-answer-slot-val {
                    color: #0f7a56;
                }
                .cbt-attempt-answer-slot.is-wrong {
                    border-color: #f5c2c2;
                    background: #fff2f2;
                }
                .cbt-attempt-answer-slot.is-wrong .cbt-attempt-answer-slot-key,
                .cbt-attempt-answer-slot.is-wrong .cbt-attempt-answer-slot-val {
                    color: #b42323;
                }
                .cbt-attempt-answer-slot.is-empty {
                    border-color: #d8dee8;
                    background: #f4f6fa;
                }
                .cbt-attempt-answer-slot.is-empty .cbt-attempt-answer-slot-key,
                .cbt-attempt-answer-slot.is-empty .cbt-attempt-answer-slot-val {
                    color: #64748b;
                }
                .cbt-results-pagination-wrap {
                    margin-top: 12px;
                }
                .cbt-results-pagination {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 10px;
                }
                .cbt-results-pagination .cbt-results-total {
                    float: none;
                    margin: 0;
                    font-size: 13px;
                    font-weight: 600;
                    color: #1d2327;
                }
                .cbt-results-pagination-links {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 6px;
                    margin: 0;
                }
                .cbt-results-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 36px;
                    height: 36px;
                    padding: 0 10px;
                    box-sizing: border-box;
                    border: 1px solid #c3c4c7;
                    border-radius: 8px;
                    background: #fff;
                    color: #2271b1;
                    font-size: 14px;
                    font-weight: 600;
                    line-height: 1;
                    text-decoration: none;
                }
                .cbt-results-pagination-links .page-numbers:hover,
                .cbt-results-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    color: #135e96;
                    box-shadow: 0 0 0 1px #2271b1;
                    outline: none;
                }
                .cbt-results-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #fff;
                    box-shadow: none;
                }
                .cbt-results-pagination-links .page-numbers.prev,
                .cbt-results-pagination-links .page-numbers.next {
                    min-width: 42px;
                    font-size: 16px;
                    font-weight: 700;
                }
                .cbt-results-pagination-links .page-numbers.dots {
                    min-width: auto;
                    border: none;
                    background: transparent;
                    color: #646970;
                    box-shadow: none;
                    padding: 0 2px;
                }
                @media (max-width: 782px) {
                    .cbt-results-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        font-size: 13px;
                    }
                }
            </style>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 0 0 12px;">
                <input type="hidden" name="page" value="cbt-results" />
                <input type="hidden" name="cbt_results_paged" value="1" />
                <label for="cbt-result-filter-exam">Filter Exam</label>
                <select id="cbt-result-filter-exam" name="cbt_exam_id" style="margin: 0 12px 0 8px;">
                    <option value="0">Semua exam</option>
                    <?php foreach ($exam_filter_rows as $exam_filter_row): ?>
                        <option value="<?php echo (int) ($exam_filter_row['id'] ?? 0); ?>" <?php selected($selected_exam_id, (int) ($exam_filter_row['id'] ?? 0)); ?>>
                            <?php echo esc_html((string) ($exam_filter_row['title'] ?? '-')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="cbt-result-filter-kelas">Kelas</label>
                <select id="cbt-result-filter-kelas" name="cbt_result_kelas" style="margin: 0 12px 0 8px;">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelas_filter_rows as $kelas_filter_row): ?>
                        <option value="<?php echo esc_attr($kelas_filter_row); ?>" <?php selected($selected_kelas, $kelas_filter_row); ?>>
                            <?php echo esc_html($kelas_filter_row); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="cbt-result-filter-student">NISN / Username</label>
                <input
                    type="search"
                    id="cbt-result-filter-student"
                    name="cbt_student_q"
                    value="<?php echo esc_attr($student_keyword); ?>"
                    placeholder="Contoh: 1000000001 atau siswa0001"
                    style="margin: 0 12px 0 8px;"
                />
                <label for="cbt-result-filter-status">Status</label>
                <select id="cbt-result-filter-status" name="cbt_attempt_status" style="margin: 0 12px 0 8px;">
                    <option value="" <?php selected($selected_status, ''); ?>>Semua status</option>
                    <option value="in_progress" <?php selected($selected_status, 'in_progress'); ?>>In Progress</option>
                    <option value="completed" <?php selected($selected_status, 'completed'); ?>>Completed</option>
                </select>
                <button class="button button-secondary" type="submit">Terapkan Filter</button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cbt-results')); ?>">Reset Filter</a>
            </form>
            <p class="description">Reset login akan menginvalidasi token login aktif siswa di semua browser. Browser lama akan dipaksa login ulang pada request berikutnya.</p>
            <p class="description">Reset ujian akan mengubah attempt completed menjadi in_progress, sehingga siswa bisa lanjut lagi tanpa menghapus jawaban yang sudah tersimpan.</p>
            <p class="description">Paksa complete akan menutup attempt in_progress menjadi completed sesuai filter, sehingga siswa tidak bisa lanjut lagi pada attempt tersebut.</p>
            <?php
            $bulk_reset_confirm_message = sprintf(
                'Reset %d attempt completed sesuai filter menjadi in_progress? Jawaban tersimpan tidak akan dihapus.',
                $resettable_attempts_count
            );
            $bulk_force_complete_confirm_message = sprintf(
                'Paksa selesai %d attempt in_progress sesuai filter menjadi completed? Attempt tidak bisa dilanjutkan lagi oleh siswa.',
                $completable_attempts_count
            );
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 10px 0 14px;" onsubmit="return confirm('<?php echo esc_attr($bulk_reset_confirm_message); ?>');">
                <?php wp_nonce_field('cbt_bulk_reset_attempts'); ?>
                <input type="hidden" name="action" value="cbt_bulk_reset_attempts" />
                <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                <button class="button button-secondary" type="submit" <?php disabled($resettable_attempts_count <= 0); ?>>
                    <?php echo esc_html(sprintf('Reset Sesuai Filter (%d)', $resettable_attempts_count)); ?>
                </button>
                <span class="description" style="margin-left:8px;">
                    <?php echo esc_html(sprintf('%d attempt completed siap di-reset dari hasil filter saat ini.', $resettable_attempts_count)); ?>
                </span>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0 0 14px;" onsubmit="return confirm('<?php echo esc_attr($bulk_force_complete_confirm_message); ?>');">
                <?php wp_nonce_field('cbt_bulk_force_complete_attempts'); ?>
                <input type="hidden" name="action" value="cbt_bulk_force_complete_attempts" />
                <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                <button class="button button-primary" type="submit" <?php disabled($completable_attempts_count <= 0); ?>>
                    <?php echo esc_html(sprintf('Paksa Complete Sesuai Filter (%d)', $completable_attempts_count)); ?>
                </button>
                <span class="description" style="margin-left:8px;">
                    <?php echo esc_html(sprintf('%d attempt in_progress siap dipaksa selesai dari hasil filter saat ini.', $completable_attempts_count)); ?>
                </span>
            </form>

            <h2>Attempts</h2>
            <p class="description" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <label for="cbt-attempts-auto-refresh-toggle" style="display:inline-flex;align-items:center;gap:6px;margin:0;">
                    <input type="checkbox" id="cbt-attempts-auto-refresh-toggle" checked />
                    <span>Aktifkan auto refresh (10 detik)</span>
                </label>
                <span id="cbt-attempts-live-status">Auto refresh aktif setiap 10 detik.</span>
            </p>
            <table id="cbt-attempts-table" class="widefat striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Jawaban</th>
                    <th>Started</th>
                    <th>Finished</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="cbt-attempts-tbody">
                <?php if (!$attempts): ?>
                    <tr><td colspan="9">No attempts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($attempts as $attempt): ?>
                        <?php
                        $total_points = (float) ($attempt['total_points'] ?? 0);
                        $answered_points = (float) ($attempt['answered_points'] ?? 0);
                        $earned_points = (float) ($attempt['earned_points'] ?? 0);
                        $wrong_points = max(0, $answered_points - $earned_points);
                        $unanswered_points = max(0, $total_points - $answered_points);
                        $percentage = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;
                        $answer_count = (int) ($attempt['answer_count'] ?? 0);
                        $question_count = (int) ($attempt['question_count'] ?? 0);
                        $answered_percentage = $question_count > 0 ? round(($answer_count / $question_count) * 100, 2) : 0;
                        $progress_items = (array) ($attempt_answer_progress_map[(int) ($attempt['id'] ?? 0)] ?? []);
                        ?>
                        <tr>
                            <td><?php echo (int) $attempt['id']; ?></td>
                            <td>
                                <?php echo esc_html((string) ($attempt['student_name'] ?? '-')); ?><br />
                                <small><?php echo esc_html((string) ($attempt['student_username'] ?? '-')); ?></small>
                                <?php
                                $student_nisn = trim((string) ($attempt['student_nisn'] ?? ''));
                                $attempt_student_kelas = trim((string) ($attempt['student_kelas'] ?? ''));
                                if ($attempt_student_kelas !== ''):
                                ?>
                                    <br />
                                    <small><?php echo esc_html('Kelas: ' . $attempt_student_kelas); ?></small>
                                <?php endif; ?>
                                <?php
                                if ($student_nisn !== ''):
                                ?>
                                    <br />
                                    <small><?php echo esc_html('NISN: ' . $student_nisn); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) ($attempt['exam_title'] ?? '-')); ?></td>
                            <td><?php echo esc_html($attempt['status']); ?></td>
                            <td>
                                <strong><?php echo esc_html(number_format($percentage, 2)); ?>%</strong><br />
                                <small>
                                    Benar: <?php echo esc_html(number_format($earned_points, 2)); ?>
                                    |
                                    Salah: <?php echo esc_html(number_format($wrong_points, 2)); ?>
                                    |
                                    Belum Jawab: <?php echo esc_html(number_format($unanswered_points, 2)); ?>
                                    |
                                    Total: <?php echo esc_html(number_format($total_points, 2)); ?>
                                </small>
                            </td>
                            <td class="cbt-attempt-answer-cell">
                                <div class="cbt-attempt-progress-wrap">
                                    <div class="cbt-attempt-progress-line">
                                        <div class="cbt-attempt-progress-track" aria-hidden="true">
                                            <span class="cbt-attempt-progress-fill" style="width: <?php echo esc_attr(number_format(max(0, min(100, $answered_percentage)), 2, '.', '')); ?>%;"></span>
                                        </div>
                                        <strong><?php echo esc_html(number_format($answered_percentage, 2)); ?>%</strong>
                                    </div>
                                    <small>
                                        <?php echo esc_html((string) $answer_count); ?>
                                        <?php if ($question_count > 0): ?>
                                            / <?php echo esc_html((string) $question_count); ?>
                                        <?php endif; ?>
                                        soal terjawab
                                    </small>
                                    <?php if (!empty($progress_items)): ?>
                                        <details class="cbt-attempt-answer-details">
                                            <summary>Lihat Isian Jawaban</summary>
                                            <div class="cbt-attempt-answer-grid">
                                                <?php foreach ($progress_items as $progress_item): ?>
                                                    <?php
                                                    $progress_status = (string) ($progress_item['status'] ?? 'unanswered');
                                                    $progress_class = 'cbt-attempt-answer-chip';
                                                    if ($progress_status === 'correct') {
                                                        $progress_class .= ' is-correct';
                                                    } elseif ($progress_status === 'wrong') {
                                                        $progress_class .= ' is-wrong';
                                                    } elseif ($progress_status === 'manual') {
                                                        $progress_class .= ' is-manual';
                                                    }

                                                    $progress_number = (int) ($progress_item['question_number'] ?? 0);
                                                    $progress_preview = (string) ($progress_item['answer_preview'] ?? '');
                                                    $progress_label = 'Belum dijawab';
                                                    if ($progress_status === 'correct') {
                                                        $progress_label = 'Benar';
                                                    } elseif ($progress_status === 'wrong') {
                                                        $progress_label = 'Salah';
                                                    } elseif ($progress_status === 'manual') {
                                                        $progress_label = 'Menunggu nilai';
                                                    }
                                                    $progress_title = 'Soal ' . $progress_number . ' - ' . $progress_label;
                                                    if ($progress_preview !== '') {
                                                        $progress_title .= ' - ' . $progress_preview;
                                                    }
                                                    ?>
                                                    <span class="<?php echo esc_attr($progress_class); ?>" title="<?php echo esc_attr($progress_title); ?>">
                                                        <?php echo esc_html((string) $progress_number); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <ul class="cbt-attempt-answer-list">
                                                <?php foreach ($progress_items as $progress_item): ?>
                                                    <?php
                                                    $progress_status = (string) ($progress_item['status'] ?? 'unanswered');
                                                    $list_class = 'cbt-attempt-answer-list-item';
                                                    if ($progress_status === 'correct') {
                                                        $list_class .= ' is-correct';
                                                    } elseif ($progress_status === 'wrong') {
                                                        $list_class .= ' is-wrong';
                                                    } elseif ($progress_status === 'manual') {
                                                        $list_class .= ' is-manual';
                                                    }

                                                    $progress_number = (int) ($progress_item['question_number'] ?? 0);
                                                    $progress_preview = trim((string) ($progress_item['answer_preview'] ?? ''));
                                                    $short_answer_slots = isset($progress_item['short_answer_slots']) && is_array($progress_item['short_answer_slots'])
                                                        ? array_values((array) $progress_item['short_answer_slots'])
                                                        : [];
                                                    $progress_label = 'Belum dijawab';
                                                    if ($progress_status === 'correct') {
                                                        $progress_label = 'Benar';
                                                    } elseif ($progress_status === 'wrong') {
                                                        $progress_label = 'Salah';
                                                    } elseif ($progress_status === 'manual') {
                                                        $progress_label = 'Menunggu nilai';
                                                    }
                                                    if ($progress_preview === '') {
                                                        $progress_preview = '-';
                                                    }
                                                    ?>
                                                    <li class="<?php echo esc_attr($list_class); ?>">
                                                        <span class="cbt-attempt-answer-list-meta">
                                                            <?php echo esc_html('Soal ' . $progress_number . ' (' . $progress_label . '):'); ?>
                                                        </span>
                                                        <?php if (!empty($short_answer_slots)): ?>
                                                            <span class="cbt-attempt-answer-slot-group">
                                                                <?php foreach ($short_answer_slots as $short_answer_slot): ?>
                                                                    <?php
                                                                    $slot = (array) $short_answer_slot;
                                                                    $slot_status = (string) ($slot['status'] ?? 'empty');
                                                                    $slot_class = 'cbt-attempt-answer-slot';
                                                                    if ($slot_status === 'correct') {
                                                                        $slot_class .= ' is-correct';
                                                                    } elseif ($slot_status === 'wrong') {
                                                                        $slot_class .= ' is-wrong';
                                                                    } else {
                                                                        $slot_class .= ' is-empty';
                                                                    }
                                                                    $slot_label = trim((string) ($slot['label'] ?? 'INPUT'));
                                                                    if ($slot_label === '') {
                                                                        $slot_label = 'INPUT';
                                                                    }
                                                                    $slot_value = trim((string) ($slot['value'] ?? ''));
                                                                    if ($slot_value === '') {
                                                                        $slot_value = '-';
                                                                    }
                                                                    $slot_correct_value = trim((string) ($slot['correct_value'] ?? ''));
                                                                    $slot_title = $slot_label . ': ' . $slot_value;
                                                                    if ($slot_correct_value !== '') {
                                                                        $slot_title .= ' (Kunci: ' . $slot_correct_value . ')';
                                                                    }
                                                                    ?>
                                                                    <span class="<?php echo esc_attr($slot_class); ?>" title="<?php echo esc_attr($slot_title); ?>">
                                                                        <span class="cbt-attempt-answer-slot-key"><?php echo esc_html($slot_label); ?></span>
                                                                        <span class="cbt-attempt-answer-slot-val"><?php echo esc_html($slot_value); ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span><?php echo esc_html($progress_preview); ?></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo esc_html((string) $attempt['started_at']); ?></td>
                            <td><?php echo esc_html((string) $attempt['finished_at']); ?></td>
                            <td>
                                <div style="display:flex;flex-direction:column;align-items:flex-start;gap:6px;">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Reset login siswa ini? Semua browser aktif user ini akan diminta login ulang.');">
                                        <?php wp_nonce_field('cbt_reset_user_login_' . (int) $attempt['id']); ?>
                                        <input type="hidden" name="action" value="cbt_reset_user_login" />
                                        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>" />
                                        <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                                        <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                                        <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                                        <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                                        <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                                        <button class="button" type="submit">Reset Login</button>
                                    </form>
                                    <?php if ((string) ($attempt['status'] ?? '') === 'completed'): ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Reset attempt ini ke in_progress? Jawaban tersimpan tidak akan dihapus.');">
                                            <?php wp_nonce_field('cbt_reset_attempt_' . (int) $attempt['id']); ?>
                                            <input type="hidden" name="action" value="cbt_reset_attempt" />
                                            <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>" />
                                            <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                                            <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                                            <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                                            <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                                            <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                                            <button class="button button-secondary" type="submit">Reset Ujian</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php
            $results_pagination_args = [
                'page' => 'cbt-results',
            ];
            if ($selected_exam_id > 0) {
                $results_pagination_args['cbt_exam_id'] = $selected_exam_id;
            }
            if ($selected_status !== '') {
                $results_pagination_args['cbt_attempt_status'] = $selected_status;
            }
            if ($selected_kelas !== '') {
                $results_pagination_args['cbt_result_kelas'] = $selected_kelas;
            }
            if ($student_keyword !== '') {
                $results_pagination_args['cbt_student_q'] = $student_keyword;
            }
            $results_pagination_links = [];
            if ($total_pages > 1) {
                $results_pagination_links = paginate_links([
                    'base' => add_query_arg(array_merge($results_pagination_args, ['cbt_results_paged' => '%#%']), admin_url('admin.php')),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'type' => 'array',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
            }
            ?>
            <div class="tablenav bottom cbt-results-pagination-wrap" style="margin-top:10px;">
                <div class="tablenav-pages cbt-results-pagination" style="float:none; margin:0;">
                    <span class="displaying-num cbt-results-total">
                        <?php echo esc_html(sprintf('Total attempts: %d', $total_attempts)); ?>
                    </span>
                    <?php if (!empty($results_pagination_links)): ?>
                        <span class="pagination-links cbt-results-pagination-links">
                            <?php foreach ($results_pagination_links as $results_pagination_link): ?>
                                <?php echo wp_kses_post($results_pagination_link); ?>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <script>
                (function () {
                    var attemptsTable = document.getElementById('cbt-attempts-table');
                    var attemptsBody = document.getElementById('cbt-attempts-tbody');
                    var liveStatus = document.getElementById('cbt-attempts-live-status');
                    var autoRefreshToggle = document.getElementById('cbt-attempts-auto-refresh-toggle');
                    if (!attemptsTable || !attemptsBody || !window.fetch || !window.DOMParser) {
                        return;
                    }

                    var refreshIntervalMs = 10000;
                    var inFlight = false;
                    var lastBodyHtml = String(attemptsBody.innerHTML || '').trim();
                    var autoRefreshStorageKey = 'cbt_attempts_auto_refresh_enabled';
                    var autoRefreshEnabled = true;

                    function readAutoRefreshPreference() {
                        try {
                            var raw = window.localStorage ? window.localStorage.getItem(autoRefreshStorageKey) : null;
                            if (raw === null) {
                                return true;
                            }
                            return raw === '1';
                        } catch (error) {
                            return true;
                        }
                    }

                    function writeAutoRefreshPreference(enabled) {
                        try {
                            if (!window.localStorage) {
                                return;
                            }
                            window.localStorage.setItem(autoRefreshStorageKey, enabled ? '1' : '0');
                        } catch (error) {
                            // Ignore storage write errors.
                        }
                    }

                    function setLiveStatus(message) {
                        if (!liveStatus) {
                            return;
                        }
                        liveStatus.textContent = message;
                    }

                    function syncAutoRefreshUIState() {
                        if (autoRefreshToggle) {
                            autoRefreshToggle.checked = !!autoRefreshEnabled;
                        }
                        if (!autoRefreshEnabled) {
                            setLiveStatus('Auto refresh nonaktif.');
                            return;
                        }
                        setLiveStatus('Auto refresh aktif setiap 10 detik.');
                    }

                    function nowText() {
                        try {
                            return new Date().toLocaleTimeString('id-ID');
                        } catch (error) {
                            return new Date().toLocaleTimeString();
                        }
                    }

                    async function refreshAttemptsTable() {
                        if (!autoRefreshEnabled || inFlight || document.hidden) {
                            return;
                        }

                        inFlight = true;
                        try {
                            var sourceUrl = new URL(window.location.href);
                            sourceUrl.searchParams.set('cbt_live_refresh', String(Date.now()));

                            var response = await fetch(sourceUrl.toString(), {
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                setLiveStatus('Auto refresh gagal (' + response.status + ').');
                                return;
                            }

                            var html = await response.text();
                            var parsed = new DOMParser().parseFromString(html, 'text/html');
                            var nextBody = parsed.getElementById('cbt-attempts-tbody');
                            if (!nextBody) {
                                setLiveStatus('Auto refresh: data attempts tidak ditemukan.');
                                return;
                            }

                            var nextBodyHtml = String(nextBody.innerHTML || '').trim();
                            if (nextBodyHtml !== lastBodyHtml) {
                                attemptsBody.innerHTML = nextBodyHtml;
                                lastBodyHtml = nextBodyHtml;
                                setLiveStatus('Auto refresh: data diperbarui ' + nowText() + '.');
                            } else {
                                setLiveStatus('Auto refresh: tidak ada perubahan (' + nowText() + ').');
                            }
                        } catch (error) {
                            setLiveStatus('Auto refresh gagal. Cek jaringan/browser.');
                        } finally {
                            inFlight = false;
                        }
                    }

                    autoRefreshEnabled = readAutoRefreshPreference();
                    syncAutoRefreshUIState();

                    if (autoRefreshToggle) {
                        autoRefreshToggle.addEventListener('change', function () {
                            autoRefreshEnabled = !!autoRefreshToggle.checked;
                            writeAutoRefreshPreference(autoRefreshEnabled);
                            syncAutoRefreshUIState();
                            if (autoRefreshEnabled) {
                                refreshAttemptsTable();
                            }
                        });
                    }

                    window.setInterval(refreshAttemptsTable, refreshIntervalMs);
                    document.addEventListener('visibilitychange', function () {
                        if (!document.hidden && autoRefreshEnabled) {
                            refreshAttemptsTable();
                        }
                    });
                })();
            </script>

            <hr />

            <h2>Essay Manual Scoring</h2>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Answer ID</th>
                    <th>Student</th>
                    <th>Attempt</th>
                    <th>Exam</th>
                    <th>Question</th>
                    <th>Answer</th>
                    <th>Max Points</th>
                    <th>Score</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$essay_rows): ?>
                    <tr><td colspan="9">No essay answers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($essay_rows as $row): ?>
                        <tr>
                            <td><?php echo (int) $row['answer_id']; ?></td>
                            <td><?php echo esc_html($row['display_name']); ?></td>
                            <td><?php echo (int) $row['attempt_id']; ?></td>
                            <td><?php echo esc_html((string) ($row['exam_title'] ?? '-')); ?></td>
                            <td><?php echo esc_html(wp_trim_words((string) $row['question_text'], 10)); ?></td>
                            <td><?php echo esc_html(wp_trim_words((string) $row['answer_text'], 12)); ?></td>
                            <td><?php echo esc_html((string) $row['points']); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex; gap:8px; align-items:center;">
                                    <?php wp_nonce_field('cbt_grade_essay'); ?>
                                    <input type="hidden" name="action" value="cbt_grade_essay" />
                                    <input type="hidden" name="answer_id" value="<?php echo (int) $row['answer_id']; ?>" />
                                    <input type="number" step="0.01" min="0" max="<?php echo esc_attr((string) $row['points']); ?>" name="score_awarded" value="<?php echo esc_attr((string) $row['score_awarded']); ?>" />
                            </td>
                            <td>
                                    <button class="button button-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_report_exam_page(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $selected_exam_id = isset($_GET['cbt_exam_id']) ? absint($_GET['cbt_exam_id']) : 0;
        $selected_kelas = isset($_GET['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_GET['cbt_result_kelas'])) : '';

        $role_options = self::report_supervisor_role_options();
        $supervisor_inputs = [];
        for ($idx = 1; $idx <= 3; $idx++) {
            $name_key = 'supervisor_' . $idx . '_name';
            $nip_key = 'supervisor_' . $idx . '_nip';
            $role_key = 'supervisor_' . $idx . '_role';
            $supervisor_inputs[$idx] = [
                'name' => isset($_GET[$name_key]) ? trim(sanitize_text_field(wp_unslash((string) $_GET[$name_key]))) : '',
                'nip' => isset($_GET[$nip_key]) ? trim(sanitize_text_field(wp_unslash((string) $_GET[$nip_key]))) : '',
                'role' => isset($_GET[$role_key]) ? self::normalize_report_supervisor_role((string) wp_unslash($_GET[$role_key])) : '',
            ];
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';

        $exam_filter_rows = self::get_accessible_exam_filter_rows($is_admin_scope, $current_user_id);
        $kelas_filter_rows = self::get_distinct_user_meta_values('kode_kelas');
        ?>
        <div class="wrap">
            <h1>CBT Report Exam</h1>
            <p class="description">Cetak rekap nilai berdasarkan exam dan kelas. Export PDF dilakukan lewat browser (Print / Save as PDF). Petugas minimal 1, maksimal 3.</p>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:860px;">
                <?php wp_nonce_field('cbt_export_exam_report_pdf'); ?>
                <input type="hidden" name="action" value="cbt_export_exam_report_pdf" />
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th><label for="cbt-report-exam-id">Exam</label></th>
                        <td>
                            <select required id="cbt-report-exam-id" name="cbt_exam_id">
                                <option value="0">Pilih exam</option>
                                <?php foreach ($exam_filter_rows as $exam_filter_row): ?>
                                    <?php $exam_id = (int) ($exam_filter_row['id'] ?? 0); ?>
                                    <option value="<?php echo $exam_id; ?>" <?php selected($selected_exam_id, $exam_id); ?>>
                                        <?php echo esc_html((string) ($exam_filter_row['title'] ?? '-')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-report-kelas">Kelas</label></th>
                        <td>
                            <select id="cbt-report-kelas" name="cbt_result_kelas">
                                <option value="">Semua kelas</option>
                                <?php foreach ($kelas_filter_rows as $kelas_filter_row): ?>
                                    <option value="<?php echo esc_attr($kelas_filter_row); ?>" <?php selected($selected_kelas, $kelas_filter_row); ?>>
                                        <?php echo esc_html($kelas_filter_row); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php for ($idx = 1; $idx <= 3; $idx++): ?>
                        <?php
                        $is_required = ($idx === 1);
                        $label_suffix = $is_required ? 'wajib' : 'opsional';
                        $supervisor = (array) ($supervisor_inputs[$idx] ?? []);
                        ?>
                        <tr>
                            <th><?php echo esc_html('Petugas ' . $idx); ?></th>
                            <td style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px;">
                                <div>
                                    <label for="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-name'); ?>" style="display:block; margin:0 0 4px;"><?php echo esc_html('Nama (' . $label_suffix . ')'); ?></label>
                                    <input <?php echo $is_required ? 'required' : ''; ?> type="text" id="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-name'); ?>" name="<?php echo esc_attr('supervisor_' . $idx . '_name'); ?>" class="regular-text" value="<?php echo esc_attr((string) ($supervisor['name'] ?? '')); ?>" />
                                </div>
                                <div>
                                    <label for="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-nip'); ?>" style="display:block; margin:0 0 4px;"><?php echo esc_html('NIP (' . $label_suffix . ')'); ?></label>
                                    <input <?php echo $is_required ? 'required' : ''; ?> type="text" id="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-nip'); ?>" name="<?php echo esc_attr('supervisor_' . $idx . '_nip'); ?>" class="regular-text" value="<?php echo esc_attr((string) ($supervisor['nip'] ?? '')); ?>" />
                                </div>
                                <div>
                                    <label for="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-role'); ?>" style="display:block; margin:0 0 4px;"><?php echo esc_html('Jabatan (' . $label_suffix . ')'); ?></label>
                                    <select <?php echo $is_required ? 'required' : ''; ?> id="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-role'); ?>" name="<?php echo esc_attr('supervisor_' . $idx . '_role'); ?>" class="regular-text">
                                        <option value="">Pilih jabatan</option>
                                        <?php foreach ($role_options as $role_option): ?>
                                            <option value="<?php echo esc_attr($role_option); ?>" <?php selected((string) ($supervisor['role'] ?? ''), $role_option); ?>>
                                                <?php echo esc_html($role_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (!$is_required): ?>
                                    <p class="description" style="grid-column:1 / -1; margin:0;"><?php echo esc_html('Jika salah satu field Petugas ' . $idx . ' diisi, maka semua field Petugas ' . $idx . ' wajib diisi.'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
                <p>
                    <button class="button button-primary" type="submit">Export PDF (Print-Ready)</button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function handle_export_exam_report_pdf(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_exam_report_pdf');

        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $selected_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $selected_kelas = trim($selected_kelas);

        $supervisor_inputs = [];
        for ($idx = 1; $idx <= 3; $idx++) {
            $supervisor_inputs[$idx] = self::extract_report_supervisor_input('supervisor_' . $idx, $_POST);
        }
        $supervisor_1 = $supervisor_inputs[1];

        $redirect_args = [
            'cbt_exam_id' => $exam_id,
            'cbt_result_kelas' => $selected_kelas,
            'supervisor_1_name' => $supervisor_1['name'],
            'supervisor_1_nip' => $supervisor_1['nip'],
            'supervisor_1_role' => $supervisor_1['role'],
            'supervisor_2_name' => $supervisor_inputs[2]['name'],
            'supervisor_2_nip' => $supervisor_inputs[2]['nip'],
            'supervisor_2_role' => $supervisor_inputs[2]['role'],
            'supervisor_3_name' => $supervisor_inputs[3]['name'],
            'supervisor_3_nip' => $supervisor_inputs[3]['nip'],
            'supervisor_3_role' => $supervisor_inputs[3]['role'],
        ];

        if ($exam_id <= 0) {
            self::redirect_report_exam_page($redirect_args + ['cbt_err' => 'Exam wajib dipilih.']);
        }

        $exam = self::get_accessible_exam_row($exam_id, $is_admin_scope, $current_user_id);
        if (empty($exam)) {
            self::redirect_report_exam_page($redirect_args + ['cbt_err' => 'Exam tidak ditemukan atau tidak bisa diakses.']);
        }

        if ($supervisor_1['name'] === '' || $supervisor_1['nip'] === '' || $supervisor_1['role'] === '') {
            self::redirect_report_exam_page($redirect_args + ['cbt_err' => 'Data Petugas 1 wajib diisi lengkap (Nama, NIP, Jabatan).']);
        }

        $optional_supervisors = [2, 3];
        $supervisors = [$supervisor_1];
        foreach ($optional_supervisors as $idx) {
            $supervisor = (array) ($supervisor_inputs[$idx] ?? []);
            $has_any = (($supervisor['name'] ?? '') !== '' || ($supervisor['nip'] ?? '') !== '' || ($supervisor['role'] ?? '') !== '');
            if ($has_any && (($supervisor['name'] ?? '') === '' || ($supervisor['nip'] ?? '') === '' || ($supervisor['role'] ?? '') === '')) {
                self::redirect_report_exam_page($redirect_args + ['cbt_err' => 'Jika data Petugas ' . $idx . ' diisi, semua field Petugas ' . $idx . ' wajib lengkap.']);
            }
            if ($has_any) {
                $supervisors[] = $supervisor;
            }
        }

        $report_rows = self::get_exam_report_rows($exam_id, $selected_kelas, $is_admin_scope, $current_user_id);
        $site_name = (string) get_bloginfo('name');
        $printed_at = current_time('d M Y H:i');
        $kelas_label = $selected_kelas !== '' ? $selected_kelas : 'Semua Kelas';

        $signature_role_labels = self::build_report_supervisor_role_labels($supervisors);

        $back_args = [
            'page' => 'cbt-report-exam',
            'cbt_exam_id' => $exam_id,
        ];
        if ($selected_kelas !== '') {
            $back_args['cbt_result_kelas'] = $selected_kelas;
        }
        $back_url = add_query_arg($back_args, admin_url('admin.php'));

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        ?>
        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html('CBT Report Exam - ' . (string) ($exam['title'] ?? '-')); ?></title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 14mm 12mm;
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #111827;
                    background: #fff;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .no-print {
                    padding: 10px 12px;
                    border-bottom: 1px solid #dbe5f2;
                    background: #f8fbff;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .no-print .button {
                    border: 1px solid #1d4ed8;
                    background: #2563eb;
                    color: #fff;
                    padding: 6px 10px;
                    border-radius: 6px;
                    text-decoration: none;
                    cursor: pointer;
                    font-size: 12px;
                    line-height: 1;
                }
                .no-print .button-secondary {
                    border-color: #cbd5e1;
                    background: #fff;
                    color: #0f172a;
                }
                .report-wrap {
                    padding: 18px 18px 14px;
                }
                .report-title {
                    margin: 0;
                    font-size: 18px;
                    line-height: 1.2;
                }
                .report-meta {
                    margin-top: 10px;
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 6px 14px;
                    font-size: 12px;
                }
                .report-meta strong {
                    display: inline-block;
                    min-width: 85px;
                }
                .report-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 14px;
                }
                .report-table th,
                .report-table td {
                    border: 1px solid #475569;
                    padding: 6px 8px;
                }
                .report-table th {
                    background: #f1f5f9;
                    text-align: center;
                    font-size: 11px;
                }
                .report-table td:nth-child(1),
                .report-table td:nth-child(5) {
                    text-align: center;
                    white-space: nowrap;
                }
                .report-empty {
                    text-align: center;
                    color: #64748b;
                    padding: 14px 8px;
                }
                .signature-wrap {
                    margin-top: 40px;
                    display: grid;
                    grid-template-columns: repeat(<?php echo (int) count($supervisors); ?>, minmax(0, 1fr));
                    gap: 24px;
                }
                .signature-card {
                    text-align: center;
                }
                .signature-role {
                    font-weight: 600;
                }
                .signature-space {
                    height: 74px;
                }
                .signature-name {
                    display: inline-block;
                    min-width: 220px;
                    max-width: 320px;
                    border-top: 1px solid #111827;
                    padding-top: 4px;
                    font-weight: 700;
                    margin: 0 auto;
                }
                .signature-nip {
                    margin-top: 2px;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    .report-wrap {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button type="button" class="button" onclick="window.print()">Print / Save PDF</button>
                <a class="button button-secondary" href="<?php echo esc_url($back_url); ?>">Kembali ke Form Report</a>
            </div>
            <main class="report-wrap">
                <h1 class="report-title">CBT Report Exam</h1>
                <div class="report-meta">
                    <div><strong>Sekolah</strong>: <?php echo esc_html($site_name !== '' ? $site_name : '-'); ?></div>
                    <div><strong>Tanggal Cetak</strong>: <?php echo esc_html($printed_at); ?></div>
                    <div><strong>Exam</strong>: <?php echo esc_html((string) ($exam['title'] ?? '-')); ?></div>
                    <div><strong>Kelas</strong>: <?php echo esc_html($kelas_label); ?></div>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width:52px;">NO</th>
                            <th style="width:160px;">NISN</th>
                            <th>NAMA</th>
                            <th style="width:120px;">KELAS</th>
                            <th style="width:96px;">NILAI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr>
                                <td class="report-empty" colspan="5">Tidak ada data peserta sesuai filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_rows as $report_row): ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($report_row['no'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['nisn'] ?? '-')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['nama'] ?? '-')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['kelas'] ?? '-')); ?></td>
                                    <td><?php echo esc_html(number_format((float) ($report_row['nilai'] ?? 0), 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <section class="signature-wrap">
                    <?php foreach ($supervisors as $idx => $supervisor): ?>
                        <div class="signature-card">
                            <p class="signature-role"><?php echo esc_html((string) ($signature_role_labels[$idx] ?? (($supervisor['role'] ?? '') !== '' ? $supervisor['role'] : ('Petugas ' . ($idx + 1))))); ?></p>
                            <div class="signature-space"></div>
                            <p class="signature-name"><?php echo esc_html((string) ($supervisor['name'] ?? '-')); ?></p>
                            <div class="signature-nip"><?php echo esc_html('NIP: ' . (string) ($supervisor['nip'] ?? '-')); ?></div>
                        </div>
                    <?php endforeach; ?>
                </section>
            </main>
            <script>
                window.addEventListener('load', function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 350);
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    public static function render_exam_cards_page(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $selected_kelas = isset($_GET['cbt_card_kelas']) ? sanitize_text_field(wp_unslash($_GET['cbt_card_kelas'])) : '';
        $selected_ruang = isset($_GET['cbt_card_ruang']) ? sanitize_text_field(wp_unslash($_GET['cbt_card_ruang'])) : '';
        $search = isset($_GET['cbt_card_q']) ? sanitize_text_field(wp_unslash($_GET['cbt_card_q'])) : '';

        $kelas_options = self::get_distinct_user_meta_values('kode_kelas');
        $ruang_options = self::get_distinct_user_meta_values('kode_ruang');
        ?>
        <div class="wrap">
            <h1>Cetak Kartu Ujian</h1>
            <p class="description">Generate kartu peserta ujian berdasarkan filter siswa. Output siap dicetak ke PDF (A4, 6 kartu per halaman).</p>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:860px;">
                <?php wp_nonce_field('cbt_print_exam_cards'); ?>
                <input type="hidden" name="action" value="cbt_print_exam_cards" />
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th><label for="cbt-card-kelas">Kelas</label></th>
                        <td>
                            <select id="cbt-card-kelas" name="cbt_card_kelas">
                                <option value="">Semua kelas</option>
                                <?php foreach ($kelas_options as $kelas_option): ?>
                                    <option value="<?php echo esc_attr($kelas_option); ?>" <?php selected($selected_kelas, $kelas_option); ?>>
                                        <?php echo esc_html($kelas_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Opsional. Jika kosong, semua kelas akan diproses.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-card-ruang">Ruang</label></th>
                        <td>
                            <select id="cbt-card-ruang" name="cbt_card_ruang">
                                <option value="">Semua ruang</option>
                                <?php foreach ($ruang_options as $ruang_option): ?>
                                    <option value="<?php echo esc_attr($ruang_option); ?>" <?php selected($selected_ruang, $ruang_option); ?>>
                                        <?php echo esc_html($ruang_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Opsional. Jika kosong, semua ruang di filter kelas akan dicetak.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-card-q">Search</label></th>
                        <td>
                            <input type="text" id="cbt-card-q" name="cbt_card_q" class="regular-text" value="<?php echo esc_attr($search); ?>" placeholder="Cari username / nama / email" />
                            <p class="description">Opsional untuk mempersempit hasil siswa.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p class="description" style="margin:0 0 12px; color:#9a3412;">
                    Password kartu akan memakai nilai tersimpan. Jika masih kosong, sistem akan membuat password 6 digit otomatis untuk siswa tersebut.
                </p>
                <p>
                    <button class="button button-primary" type="submit">Generate &amp; Print Kartu</button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function handle_print_exam_cards(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_print_exam_cards');

        $selected_kelas = isset($_POST['cbt_card_kelas']) ? trim(sanitize_text_field(wp_unslash($_POST['cbt_card_kelas']))) : '';
        $selected_ruang = isset($_POST['cbt_card_ruang']) ? trim(sanitize_text_field(wp_unslash($_POST['cbt_card_ruang']))) : '';
        $search = isset($_POST['cbt_card_q']) ? trim(sanitize_text_field(wp_unslash($_POST['cbt_card_q']))) : '';

        $redirect_args = [
            'cbt_card_kelas' => $selected_kelas,
            'cbt_card_ruang' => $selected_ruang,
            'cbt_card_q' => $search,
        ];

        $students = self::get_exam_card_students($search, $selected_kelas, $selected_ruang);
        if (empty($students)) {
            self::redirect_exam_cards_page($redirect_args + ['cbt_err' => 'Tidak ada siswa sesuai filter untuk dicetak.']);
        }

        foreach ($students as $idx => $student) {
            $student_id = (int) ($student['id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $existing_password = trim((string) ($student['password'] ?? ''));
            if ($existing_password !== '') {
                $students[$idx]['password'] = $existing_password;
                continue;
            }

            $generated_password = self::generate_exam_card_password();
            wp_set_password($generated_password, $student_id);
            update_user_meta($student_id, self::USER_META_PLAIN_PASSWORD, $generated_password);
            $students[$idx]['password'] = $generated_password;
        }

        $schedule_rows = self::get_exam_card_schedule_rows($selected_kelas);
        $schedule_items = [];
        foreach ($schedule_rows as $schedule_row) {
            $schedule_items[] = self::format_exam_card_schedule_line((array) $schedule_row);
        }

        $branding = self::get_setup_branding_print_context();
        $school_name = trim((string) ($branding['school_name'] ?? ''));
        if ($school_name === '') {
            $school_name = trim((string) get_bloginfo('name'));
        }
        if ($school_name === '') {
            $school_name = 'CBT Exam';
        }
        $school_logo_url = (string) ($branding['logo_url'] ?? '');

        $printed_at = current_time('d M Y H:i');
        $kelas_label = $selected_kelas !== '' ? $selected_kelas : 'Semua Kelas';
        $ruang_label = $selected_ruang !== '' ? $selected_ruang : 'Semua Ruang';
        $student_total = count($students);

        $back_args = [
            'page' => 'cbt-exam-cards',
            'cbt_card_kelas' => $selected_kelas,
        ];
        if ($selected_ruang !== '') {
            $back_args['cbt_card_ruang'] = $selected_ruang;
        }
        if ($search !== '') {
            $back_args['cbt_card_q'] = $search;
        }
        $back_url = add_query_arg($back_args, admin_url('admin.php'));

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        ?>
        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html('Cetak Kartu Ujian - ' . $kelas_label); ?></title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 10mm;
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #0f172a;
                    background: #fff;
                    font-size: 11px;
                    line-height: 1.35;
                }
                .no-print {
                    padding: 10px 12px;
                    border-bottom: 1px solid #dbe5f2;
                    background: #f8fbff;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .no-print .button {
                    border: 1px solid #1d4ed8;
                    background: #2563eb;
                    color: #fff;
                    padding: 6px 10px;
                    border-radius: 6px;
                    text-decoration: none;
                    cursor: pointer;
                    font-size: 12px;
                    line-height: 1;
                }
                .no-print .button-secondary {
                    border-color: #cbd5e1;
                    background: #fff;
                    color: #0f172a;
                }
                .cards-wrap {
                    padding: 8px;
                }
                .cards-meta {
                    margin: 0 0 8px;
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 3px 12px;
                    font-size: 11px;
                }
                .cards-meta strong {
                    display: inline-block;
                    min-width: 84px;
                }
                .cards-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 5mm;
                }
                .exam-card {
                    border: 1px solid #0f172a;
                    padding: 2.8mm;
                    min-height: 86mm;
                    break-inside: avoid;
                    page-break-inside: avoid;
                    display: flex;
                    flex-direction: column;
                }
                .exam-card-head {
                    display: grid;
                    grid-template-columns: 18mm minmax(0, 1fr);
                    gap: 2.5mm;
                    align-items: center;
                    padding-bottom: 2mm;
                    border-bottom: 1px solid #64748b;
                }
                .exam-card-school-logo {
                    width: 18mm;
                    height: 18mm;
                    border: 1px solid #cbd5e1;
                    border-radius: 3px;
                    background: #f8fafc;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                }
                .exam-card-school-logo img {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                }
                .exam-card-school-logo-fallback {
                    font-weight: 700;
                    color: #334155;
                    font-size: 10px;
                }
                .exam-card-school-name {
                    font-size: 10px;
                    font-weight: 700;
                    text-transform: uppercase;
                    line-height: 1.2;
                }
                .exam-card-title {
                    margin-top: 1px;
                    font-weight: 700;
                    font-size: 11px;
                    line-height: 1.2;
                }
                .exam-card-content {
                    margin-top: 2.4mm;
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 24mm;
                    gap: 2.4mm;
                    align-items: start;
                }
                .exam-card-main-fields {
                    min-width: 0;
                }
                .exam-card-photo-box {
                    border: 1px solid #94a3b8;
                    border-radius: 3px;
                    min-height: 31mm;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f8fafc;
                    overflow: hidden;
                }
                .exam-card-photo-box img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .exam-card-photo-fallback {
                    font-size: 15px;
                    font-weight: 700;
                    color: #475569;
                }
                .exam-card-row {
                    display: grid;
                    grid-template-columns: 33mm minmax(0, 1fr);
                    gap: 1.2mm;
                    margin-bottom: 1.2mm;
                    align-items: start;
                }
                .exam-card-row-label {
                    font-weight: 700;
                    color: #334155;
                    text-transform: uppercase;
                    font-size: 10px;
                }
                .exam-card-row-value {
                    font-size: 11px;
                    word-break: break-word;
                }
                .exam-card-row-value.is-password {
                    font-weight: 700;
                    letter-spacing: 0.03em;
                }
                .exam-card-schedule-block {
                    margin-top: 2.2mm;
                }
                .exam-card-schedule-title {
                    font-size: 10px;
                    font-weight: 700;
                    text-transform: uppercase;
                    color: #334155;
                    margin-bottom: 1.2mm;
                }
                .exam-card-schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    font-size: 8.3px;
                    line-height: 1.15;
                }
                .exam-card-schedule-table th,
                .exam-card-schedule-table td {
                    border: 1px solid #94a3b8;
                    padding: 0.6mm 0.75mm;
                    vertical-align: top;
                    text-align: left;
                    word-break: break-word;
                }
                .exam-card-schedule-table th {
                    background: #f1f5f9;
                    font-weight: 700;
                    text-transform: uppercase;
                    color: #334155;
                    white-space: nowrap;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    .cards-wrap {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button type="button" class="button" onclick="window.print()">Print / Save PDF</button>
                <a class="button button-secondary" href="<?php echo esc_url($back_url); ?>">Kembali ke Form Cetak</a>
            </div>
            <main class="cards-wrap">
                <div class="cards-meta">
                    <div><strong>Tanggal Cetak</strong>: <?php echo esc_html($printed_at); ?></div>
                    <div><strong>Total Siswa</strong>: <?php echo esc_html((string) $student_total); ?></div>
                    <div><strong>Kelas</strong>: <?php echo esc_html($kelas_label); ?></div>
                    <div><strong>Ruang</strong>: <?php echo esc_html($ruang_label); ?></div>
                </div>
                <section class="cards-grid">
                    <?php foreach ($students as $student): ?>
                        <?php
                        $student_name = trim((string) ($student['name'] ?? ''));
                        if ($student_name === '') {
                            $student_name = (string) ($student['username'] ?? '-');
                        }
                        $student_photo = trim((string) ($student['foto'] ?? ''));
                        $student_initial = strtoupper(substr($student_name, 0, 1));
                        if ($student_initial === '') {
                            $student_initial = 'S';
                        }
                        ?>
                        <article class="exam-card">
                            <header class="exam-card-head">
                                <div class="exam-card-school-logo">
                                    <?php if ($school_logo_url !== ''): ?>
                                        <img src="<?php echo esc_url($school_logo_url); ?>" alt="<?php echo esc_attr($school_name); ?>" loading="lazy" decoding="async" />
                                    <?php else: ?>
                                        <div class="exam-card-school-logo-fallback">CBT</div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="exam-card-school-name"><?php echo esc_html($school_name); ?></div>
                                    <div class="exam-card-title">KARTU PESERTA UJIAN CBT</div>
                                </div>
                            </header>
                            <div class="exam-card-content">
                                <div class="exam-card-main-fields">
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Nama Peserta</div>
                                        <div class="exam-card-row-value"><?php echo esc_html($student_name); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">NISN</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) ($student['nisn'] !== '' ? $student['nisn'] : '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Username</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) ($student['username'] ?? '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Password</div>
                                        <div class="exam-card-row-value is-password"><?php echo esc_html((string) ($student['password'] ?? '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Kelas</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) (($student['kelas'] ?? '') !== '' ? $student['kelas'] : '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Ruangan</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) (($student['ruang'] ?? '') !== '' ? $student['ruang'] : '-')); ?></div>
                                    </div>
                                </div>
                                <div class="exam-card-photo-box">
                                    <?php if ($student_photo !== ''): ?>
                                        <img src="<?php echo esc_url($student_photo); ?>" alt="<?php echo esc_attr($student_name); ?>" loading="lazy" decoding="async" />
                                    <?php else: ?>
                                        <div class="exam-card-photo-fallback"><?php echo esc_html($student_initial); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <section class="exam-card-schedule-block">
                                <div class="exam-card-schedule-title">Jadwal Ujian</div>
                                <?php if (empty($schedule_items)): ?>
                                    -
                                <?php else: ?>
                                    <table class="exam-card-schedule-table">
                                        <thead>
                                            <tr>
                                                <th style="width:24%;">Mapel</th>
                                                <th style="width:34%;">Hari</th>
                                                <th style="width:24%;">Jadwal</th>
                                                <th style="width:18%;">Durasi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($schedule_items as $schedule_item): ?>
                                            <tr>
                                                <td><?php echo esc_html((string) ($schedule_item['title'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($schedule_item['day'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($schedule_item['time'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($schedule_item['duration'] ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </section>
                        </article>
                    <?php endforeach; ?>
                </section>
            </main>
            <script>
                window.addEventListener('load', function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 350);
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    private static function redirect_exam_cards_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-exam-cards'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    private static function redirect_report_exam_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-report-exam'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return array{name:string,nip:string,role:string}
     */
    private static function extract_report_supervisor_input(string $prefix, array $source): array
    {
        $name_key = $prefix . '_name';
        $nip_key = $prefix . '_nip';
        $role_key = $prefix . '_role';

        return [
            'name' => isset($source[$name_key]) ? trim(sanitize_text_field(wp_unslash((string) $source[$name_key]))) : '',
            'nip' => isset($source[$nip_key]) ? trim(sanitize_text_field(wp_unslash((string) $source[$nip_key]))) : '',
            'role' => isset($source[$role_key]) ? self::normalize_report_supervisor_role((string) wp_unslash($source[$role_key])) : '',
        ];
    }

    /**
     * @return string[]
     */
    private static function report_supervisor_role_options(): array
    {
        return ['Pengawas', 'Teknisi Ruang', 'Proktor'];
    }

    private static function normalize_report_supervisor_role(string $raw): string
    {
        $role = trim(sanitize_text_field($raw));
        if ($role === '') {
            return '';
        }

        foreach (self::report_supervisor_role_options() as $option) {
            if (strcasecmp($role, $option) === 0) {
                return $option;
            }
        }

        return '';
    }

    /**
     * @param array<int,array{name:string,nip:string,role:string}> $supervisors
     * @return array<int,string>
     */
    private static function build_report_supervisor_role_labels(array $supervisors): array
    {
        $totals = [];
        foreach ($supervisors as $supervisor) {
            $role = trim((string) ($supervisor['role'] ?? ''));
            if ($role === '') {
                continue;
            }
            if (!isset($totals[$role])) {
                $totals[$role] = 0;
            }
            $totals[$role]++;
        }

        $seen = [];
        $labels = [];
        foreach ($supervisors as $idx => $supervisor) {
            $role = trim((string) ($supervisor['role'] ?? ''));
            if ($role === '') {
                $labels[$idx] = 'Petugas ' . ((int) $idx + 1);
                continue;
            }

            $total_for_role = isset($totals[$role]) ? (int) $totals[$role] : 0;
            if ($total_for_role <= 1) {
                $labels[$idx] = $role;
                continue;
            }

            if (!isset($seen[$role])) {
                $seen[$role] = 0;
            }
            $seen[$role]++;
            $labels[$idx] = $role . ' ' . (string) $seen[$role];
        }

        return $labels;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_accessible_exam_filter_rows(bool $is_admin_scope, int $current_user_id): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $sql = "SELECT id, title FROM {$exam_table} WHERE 1=1";
        $params = [];
        if (!$is_admin_scope) {
            $sql .= ' AND created_by = %d';
            $params[] = $current_user_id;
        }
        $sql .= ' ORDER BY id DESC';
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_accessible_exam_row(int $exam_id, bool $is_admin_scope, int $current_user_id): array
    {
        if ($exam_id <= 0) {
            return [];
        }

        global $wpdb;
        $exam_table = $wpdb->prefix . 'cbt_exams';
        if ($is_admin_scope) {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, title FROM {$exam_table} WHERE id = %d",
                    $exam_id
                ),
                ARRAY_A
            );
        } else {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, title FROM {$exam_table} WHERE id = %d AND created_by = %d",
                    $exam_id,
                    $current_user_id
                ),
                ARRAY_A
            );
        }

        return is_array($exam) ? $exam : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_exam_report_rows(
        int $exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        if ($exam_id <= 0) {
            return [];
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $question_table = $wpdb->prefix . 'cbt_questions';

        $latest_attempt_subquery = "SELECT student_id, MAX(id) AS latest_attempt_id
                                    FROM {$attempt_table}
                                    WHERE exam_id = %d
                                      AND status IN ('in_progress', 'completed')
                                    GROUP BY student_id";

        $selected_kelas = trim($selected_kelas);
        $where_parts = ['a.exam_id = %d'];
        $params = [$exam_id, $exam_id];

        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $current_user_id;
        }
        if ($selected_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $params[] = $selected_kelas;
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $sql = "SELECT a.id,
                       a.status,
                       a.score,
                       a.max_score,
                       u.display_name AS student_name,
                       COALESCE(kelas_meta.meta_value, '') AS student_kelas,
                       COALESCE(nisn_meta.meta_value, '') AS student_nisn,
                       COALESCE(anscore.total_score_awarded, 0) AS answer_score_awarded,
                       COALESCE(qtotal.total_points, 0) AS exam_total_points
                FROM {$attempt_table} a
                INNER JOIN ({$latest_attempt_subquery}) latest ON latest.latest_attempt_id = a.id
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'kode_kelas'
                    GROUP BY user_id
                ) kelas_meta ON kelas_meta.user_id = u.ID
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'nisn'
                    GROUP BY user_id
                ) nisn_meta ON nisn_meta.user_id = u.ID
                LEFT JOIN (
                    SELECT attempt_id, COALESCE(SUM(score_awarded), 0) AS total_score_awarded
                    FROM {$answer_table}
                    GROUP BY attempt_id
                ) anscore ON anscore.attempt_id = a.id
                LEFT JOIN (
                    SELECT exam_id, COALESCE(SUM(points), 0) AS total_points
                    FROM {$question_table}
                    GROUP BY exam_id
                ) qtotal ON qtotal.exam_id = a.exam_id
                {$where_sql}
                ORDER BY COALESCE(kelas_meta.meta_value, '') ASC, u.display_name ASC, a.id DESC";

        $prepared_sql = $wpdb->prepare($sql, $params);
        $raw_rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        if (!is_array($raw_rows)) {
            return [];
        }

        $rows = [];
        $no = 1;
        foreach ($raw_rows as $raw_row) {
            $row = (array) $raw_row;
            $status = (string) ($row['status'] ?? '');

            $attempt_score = (float) ($row['score'] ?? 0);
            $answer_score_awarded = (float) ($row['answer_score_awarded'] ?? 0);
            $has_completed_score = ($status === 'completed')
                && array_key_exists('score', $row)
                && $row['score'] !== null
                && $row['score'] !== '';

            $earned_points = $has_completed_score ? $attempt_score : $answer_score_awarded;
            $total_points = (float) ($row['max_score'] ?? 0);
            if ($total_points <= 0) {
                $total_points = (float) ($row['exam_total_points'] ?? 0);
            }

            $nilai = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0.0;

            $rows[] = [
                'no' => $no++,
                'nisn' => (string) ($row['student_nisn'] ?? ''),
                'nama' => (string) ($row['student_name'] ?? ''),
                'kelas' => (string) ($row['student_kelas'] ?? ''),
                'nilai' => $nilai,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $attempts
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function build_attempt_answer_progress_map(
        array $attempts,
        string $question_table,
        string $answer_table,
        string $option_table
    ): array {
        global $wpdb;

        if (empty($attempts)) {
            return [];
        }

        $attempt_ids = [];
        $exam_ids = [];
        foreach ($attempts as $attempt_row) {
            $attempt = (array) $attempt_row;
            $attempt_id = (int) ($attempt['id'] ?? 0);
            $exam_id = (int) ($attempt['exam_id'] ?? 0);
            if ($attempt_id > 0) {
                $attempt_ids[$attempt_id] = $attempt_id;
            }
            if ($exam_id > 0) {
                $exam_ids[$exam_id] = $exam_id;
            }
        }

        if (empty($attempt_ids) || empty($exam_ids)) {
            return [];
        }

        $exam_ids_sql = implode(',', array_values($exam_ids));
        $question_short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';
        $question_rows = $wpdb->get_results(
            "SELECT q.id, q.exam_id, q.question_type, q.correct_text,
                    qsa.correct_text AS short_answer_correct_text
             FROM {$question_table} q
             LEFT JOIN {$question_short_answer_table} qsa ON qsa.question_id = q.id
             WHERE q.exam_id IN ({$exam_ids_sql})
             ORDER BY q.exam_id ASC, q.id ASC",
            ARRAY_A
        );

        $questions_by_exam = [];
        $questions_by_id = [];
        $all_question_ids = [];
        foreach ((array) $question_rows as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            $exam_id = (int) ($question['exam_id'] ?? 0);
            if ($question_id <= 0 || $exam_id <= 0) {
                continue;
            }
            if (!isset($questions_by_exam[$exam_id])) {
                $questions_by_exam[$exam_id] = [];
            }
            $questions_by_exam[$exam_id][] = $question;
            $questions_by_id[$question_id] = $question;
            $all_question_ids[$question_id] = $question_id;
        }

        $option_labels_by_question = [];
        $correct_option_ids_by_question = [];
        if (!empty($all_question_ids)) {
            $question_ids_sql = implode(',', array_values($all_question_ids));
            $option_rows = $wpdb->get_results(
                "SELECT id, question_id, option_key, option_text, is_correct
                 FROM {$option_table}
                 WHERE question_id IN ({$question_ids_sql})
                 ORDER BY question_id ASC, id ASC",
                ARRAY_A
            );

            foreach ((array) $option_rows as $option_row) {
                $option = (array) $option_row;
                $question_id = (int) ($option['question_id'] ?? 0);
                $option_id = (int) ($option['id'] ?? 0);
                if ($question_id <= 0 || $option_id <= 0) {
                    continue;
                }
                if (!isset($option_labels_by_question[$question_id])) {
                    $option_labels_by_question[$question_id] = [];
                }

                $option_labels_by_question[$question_id][$option_id] = self::format_attempt_option_label(
                    (string) ($option['option_key'] ?? ''),
                    (string) ($option['option_text'] ?? '')
                );

                if ((int) ($option['is_correct'] ?? 0) === 1) {
                    if (!isset($correct_option_ids_by_question[$question_id])) {
                        $correct_option_ids_by_question[$question_id] = [];
                    }
                    $correct_option_ids_by_question[$question_id][$option_id] = $option_id;
                }
            }
        }

        $attempt_ids_sql = implode(',', array_values($attempt_ids));
        $answer_rows = $wpdb->get_results(
            "SELECT attempt_id, question_id, selected_option_ids, answer_text, is_correct
             FROM {$answer_table}
             WHERE attempt_id IN ({$attempt_ids_sql})",
            ARRAY_A
        );

        $answers_by_attempt = [];
        foreach ((array) $answer_rows as $answer_row) {
            $answer = (array) $answer_row;
            $attempt_id = (int) ($answer['attempt_id'] ?? 0);
            $question_id = (int) ($answer['question_id'] ?? 0);
            if ($attempt_id <= 0 || $question_id <= 0) {
                continue;
            }
            if (!isset($answers_by_attempt[$attempt_id])) {
                $answers_by_attempt[$attempt_id] = [];
            }
            $answers_by_attempt[$attempt_id][$question_id] = $answer;
        }

        $progress_map = [];
        foreach ($attempts as $attempt_row) {
            $attempt = (array) $attempt_row;
            $attempt_id = (int) ($attempt['id'] ?? 0);
            $exam_id = (int) ($attempt['exam_id'] ?? 0);
            if ($attempt_id <= 0 || $exam_id <= 0) {
                continue;
            }

            $exam_questions = (array) ($questions_by_exam[$exam_id] ?? []);
            $ordered_question_ids = self::resolve_attempt_question_ids(
                $exam_questions,
                (string) ($attempt['question_order'] ?? '')
            );

            $items = [];
            foreach ($ordered_question_ids as $index => $question_id) {
                $question = (array) ($questions_by_id[$question_id] ?? []);
                if (empty($question)) {
                    continue;
                }

                $answer_row = $answers_by_attempt[$attempt_id][$question_id] ?? null;
                $status = 'unanswered';
                $short_answer_slots = [];
                if (is_array($answer_row)) {
                    if (
                        array_key_exists('is_correct', $answer_row) &&
                        $answer_row['is_correct'] !== null &&
                        $answer_row['is_correct'] !== ''
                    ) {
                        $status = ((int) $answer_row['is_correct'] === 1) ? 'correct' : 'wrong';
                    } else {
                        $status = 'manual';
                    }
                }

                $question_type = (string) ($question['question_type'] ?? '');
                if (
                    $status === 'manual' &&
                    is_array($answer_row) &&
                    in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true)
                ) {
                    $selected_option_ids = self::decode_attempt_selected_option_ids((string) ($answer_row['selected_option_ids'] ?? ''));
                    sort($selected_option_ids);

                    $correct_option_ids = array_values(array_unique(array_map(
                        'intval',
                        (array) ($correct_option_ids_by_question[$question_id] ?? [])
                    )));
                    sort($correct_option_ids);

                    if (empty($selected_option_ids)) {
                        $status = 'unanswered';
                    } elseif (!empty($correct_option_ids)) {
                        $status = ($selected_option_ids === $correct_option_ids) ? 'correct' : 'wrong';
                    }
                }

                if ($question_type === 'short_answer') {
                    $short_answer_slots = self::build_short_answer_progress_slots($question, is_array($answer_row) ? $answer_row : null);
                    if (is_array($answer_row) && !empty($short_answer_slots)) {
                        $slot_count = count($short_answer_slots);
                        $filled_count = 0;
                        $correct_count = 0;
                        foreach ($short_answer_slots as $slot_row) {
                            $slot = (array) $slot_row;
                            $slot_status = (string) ($slot['status'] ?? 'empty');
                            if ($slot_status !== 'empty') {
                                $filled_count++;
                            }
                            if ($slot_status === 'correct') {
                                $correct_count++;
                            }
                        }

                        if ($filled_count <= 0) {
                            $status = 'unanswered';
                        } elseif ($filled_count === $slot_count && $correct_count === $slot_count) {
                            $status = 'correct';
                        } else {
                            $status = 'wrong';
                        }
                    }
                }

                $items[] = [
                    'question_number' => $index + 1,
                    'status' => $status,
                    'answer_preview' => self::build_attempt_answer_preview(
                        (string) ($question['question_type'] ?? ''),
                        is_array($answer_row) ? $answer_row : null,
                        (array) ($option_labels_by_question[$question_id] ?? [])
                    ),
                    'short_answer_slots' => $short_answer_slots,
                ];
            }

            $progress_map[$attempt_id] = $items;
        }

        return $progress_map;
    }

    /**
     * @param array<int,array<string,mixed>> $question_rows
     * @return int[]
     */
    private static function resolve_attempt_question_ids(array $question_rows, string $question_order_raw): array
    {
        $exam_question_ids = array_values(array_filter(array_map('intval', array_column($question_rows, 'id')), static function ($id): bool {
            return $id > 0;
        }));
        $exam_question_ids = array_values(array_unique($exam_question_ids));

        if (empty($exam_question_ids)) {
            return [];
        }

        $decoded = json_decode($question_order_raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return $exam_question_ids;
        }

        $allowed = array_fill_keys($exam_question_ids, true);
        $ordered = [];
        foreach ($decoded as $candidate) {
            $question_id = (int) $candidate;
            if ($question_id <= 0 || !isset($allowed[$question_id])) {
                continue;
            }
            if (!in_array($question_id, $ordered, true)) {
                $ordered[] = $question_id;
            }
        }

        foreach ($exam_question_ids as $question_id) {
            if (!in_array($question_id, $ordered, true)) {
                $ordered[] = $question_id;
            }
        }

        return $ordered;
    }

    /**
     * @param array<int,string> $option_labels
     */
    private static function build_attempt_answer_preview(string $question_type, ?array $answer_row, array $option_labels): string
    {
        if (!is_array($answer_row)) {
            return 'Belum dijawab';
        }

        $answer_text = trim((string) ($answer_row['answer_text'] ?? ''));
        if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true)) {
            $selected_ids = self::decode_attempt_selected_option_ids((string) ($answer_row['selected_option_ids'] ?? ''));
            $labels = [];
            foreach ($selected_ids as $option_id) {
                if (isset($option_labels[$option_id])) {
                    $labels[] = (string) $option_labels[$option_id];
                }
            }
            if (!empty($labels)) {
                return implode(', ', $labels);
            }
            if ($answer_text !== '') {
                return (string) wp_trim_words(wp_strip_all_tags($answer_text), 8, '...');
            }
            return 'Terjawab';
        }

        if ($question_type === 'short_answer') {
            $values = self::normalize_short_answer_values($answer_text);
            if (empty($values) && $answer_text !== '') {
                $values = [sanitize_text_field($answer_text)];
            }
            if (!empty($values)) {
                $preview_values = array_map(static function (string $value): string {
                    return (string) wp_trim_words(wp_strip_all_tags($value), 4, '...');
                }, $values);
                return implode(' | ', $preview_values);
            }
            return 'Terjawab';
        }

        if ($answer_text === '') {
            return 'Terjawab';
        }

        return (string) wp_trim_words(wp_strip_all_tags($answer_text), 10, '...');
    }

    /**
     * @param array<string,mixed> $question
     * @param array<string,mixed>|null $answer_row
     * @return array<int,array<string,string>>
     */
    private static function build_short_answer_progress_slots(array $question, ?array $answer_row): array
    {
        $answer_text = '';
        if (is_array($answer_row)) {
            $answer_text = (string) ($answer_row['answer_text'] ?? '');
        }

        $submitted_values = self::normalize_short_answer_values($answer_text);
        if (empty($submitted_values) && trim($answer_text) !== '') {
            $submitted_values = [sanitize_text_field(trim($answer_text))];
        }

        $correct_raw = trim((string) ($question['short_answer_correct_text'] ?? ''));
        if ($correct_raw === '') {
            $correct_raw = (string) ($question['correct_text'] ?? '');
        }
        $correct_values = self::normalize_short_answer_values($correct_raw);

        $slot_count = max(count($correct_values), count($submitted_values));
        if ($slot_count <= 0) {
            return [];
        }

        $slots = [];
        for ($idx = 0; $idx < $slot_count; $idx++) {
            $submitted = isset($submitted_values[$idx]) ? sanitize_text_field(trim((string) $submitted_values[$idx])) : '';
            $correct = isset($correct_values[$idx]) ? sanitize_text_field(trim((string) $correct_values[$idx])) : '';
            $status = 'empty';
            if ($submitted !== '') {
                $status = (
                    $correct !== '' &&
                    self::normalize_short_answer_compare_value($submitted) === self::normalize_short_answer_compare_value($correct)
                ) ? 'correct' : 'wrong';
            }

            $slots[] = [
                'label' => (string) ($idx + 1),
                'value' => $submitted,
                'correct_value' => $correct,
                'status' => $status,
            ];
        }

        return $slots;
    }

    private static function normalize_short_answer_compare_value(string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value);
        return $value === null ? '' : $value;
    }

    /**
     * @return int[]
     */
    private static function decode_attempt_selected_option_ids(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $ids = array_values(array_filter(array_map('intval', $decoded), static function ($id): bool {
                return $id > 0;
            }));
            return array_values(array_unique($ids));
        }

        if (is_numeric($raw)) {
            $id = (int) $raw;
            return $id > 0 ? [$id] : [];
        }

        return [];
    }

    private static function format_attempt_option_label(string $option_key, string $option_text): string
    {
        $key = strtoupper(trim(sanitize_text_field($option_key)));
        $text = trim(wp_strip_all_tags($option_text));
        if ($text !== '') {
            $text = (string) wp_trim_words($text, 5, '...');
        }

        if ($key !== '' && $text !== '') {
            return $key . ' - ' . $text;
        }

        if ($key !== '') {
            return $key;
        }

        if ($text !== '') {
            return $text;
        }

        return '-';
    }

    public static function render_user_import_page(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        $notice = isset($_GET['cbt_msg']) ? sanitize_text_field(wp_unslash($_GET['cbt_msg'])) : '';
        $error = isset($_GET['cbt_err']) ? sanitize_text_field(wp_unslash($_GET['cbt_err'])) : '';
        $import_token = isset($_GET['cbt_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_import_token'])) : '';
        $search = isset($_GET['cbt_user_q']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_q'])) : '';
        $filter_role = isset($_GET['cbt_user_role']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_role'])) : '';
        $filter_kelas = isset($_GET['cbt_user_kelas']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_kelas'])) : '';
        $filter_ruang = isset($_GET['cbt_user_ruang']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_ruang'])) : '';
        $per_page = isset($_GET['cbt_user_per_page'])
            ? self::normalize_user_list_per_page(absint(wp_unslash($_GET['cbt_user_per_page'])))
            : 20;
        $current_page = isset($_GET['cbt_user_paged']) ? max(1, absint(wp_unslash($_GET['cbt_user_paged']))) : 1;
        $editing_user_id = isset($_GET['edit_user']) ? absint($_GET['edit_user']) : 0;
        $editing_user = null;
        if ($editing_user_id > 0) {
            $editing_user = get_user_by('id', $editing_user_id);
            if (!($editing_user instanceof WP_User)) {
                $editing_user = null;
            }
        }
        $kelas_options = self::get_distinct_user_meta_values('kode_kelas');
        $ruang_options = self::get_distinct_user_meta_values('kode_ruang');
        $per_page_options = [20, 50, 100, 150, 200];
        $import_batch_size = self::get_user_import_batch_size();
        $users_page_data = self::get_cbt_users_paginated($search, $filter_role, $filter_kelas, $filter_ruang, $per_page, $current_page);
        $users = $users_page_data['items'];
        $current_page = $users_page_data['page'];
        $total_pages = $users_page_data['total_pages'];
        $total_users = $users_page_data['total'];
        $import_state = null;
        $import_total = 0;
        $import_offset = 0;
        $import_created = 0;
        $import_updated = 0;
        $import_failed = 0;
        $import_progress_percent = 0.0;
        $import_is_running = false;
        $import_continue_url = '';
        if ($import_token !== '') {
            $import_state = self::get_user_import_state_for_current_user($import_token);
            if (is_array($import_state)) {
                $import_total = max(0, isset($import_state['total']) ? (int) $import_state['total'] : 0);
                $import_offset = max(0, isset($import_state['offset']) ? (int) $import_state['offset'] : 0);
                if ($import_total > 0 && $import_offset > $import_total) {
                    $import_offset = $import_total;
                }
                $import_created = max(0, isset($import_state['created']) ? (int) $import_state['created'] : 0);
                $import_updated = max(0, isset($import_state['updated']) ? (int) $import_state['updated'] : 0);
                $import_failed = max(0, isset($import_state['failed']) ? (int) $import_state['failed'] : 0);
                $import_progress_percent = $import_total > 0
                    ? round(((float) $import_offset / (float) $import_total) * 100, 2)
                    : 0.0;
                $import_is_running = $import_total > 0 && $import_offset < $import_total;
                $import_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_import_users',
                        'cbt_import_token' => $import_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi import tidak ditemukan atau sudah berakhir. Silakan upload ulang file.';
            }
        }
        ?>
        <div class="wrap">
            <h1>CBT User Import</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if (is_array($import_state)): ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Progress Import:</strong>
                        <?php echo esc_html((string) $import_offset . ' / ' . (string) $import_total); ?>
                        (<?php echo esc_html(number_format($import_progress_percent, 2)); ?>%)
                        | Created: <?php echo esc_html((string) $import_created); ?>
                        | Updated: <?php echo esc_html((string) $import_updated); ?>
                        | Failed: <?php echo esc_html((string) $import_failed); ?>
                    </p>
                </div>
                <div style="max-width:760px; margin:0 0 14px; border:1px solid #c3c4c7; border-radius:8px; background:#fff; padding:12px;">
                    <div style="width:100%; height:14px; border-radius:999px; background:#f0f0f1; overflow:hidden; border:1px solid #dcdcde;">
                        <div style="height:100%; width: <?php echo esc_attr((string) $import_progress_percent); ?>%; background:linear-gradient(90deg,#2271b1,#135e96); transition:width .25s ease;"></div>
                    </div>
                    <div style="margin-top:10px;">
                        <?php if ($import_is_running): ?>
                            <span style="color:#1d2327;">Memproses batch berikutnya...</span>
                            <script>
                                window.setTimeout(function () {
                                    window.location.href = <?php echo wp_json_encode($import_continue_url); ?>;
                                }, 350);
                            </script>
                        <?php else: ?>
                            <span style="color:#0a7a2f; font-weight:600;">Import selesai diproses.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <p>Import user massal dari file CSV/XLSX (Microsoft Excel / Google Sheets).</p>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_user_template'), 'cbt_download_user_template')); ?>">
                    Download Template CSV
                </a>
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_user_template_xlsx'), 'cbt_download_user_template_xlsx')); ?>">
                    Download Template XLSX
                </a>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('cbt_import_users'); ?>
                <input type="hidden" name="action" value="cbt_import_users" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cbt-user-file">File Import</label></th>
                        <td>
                            <input required type="file" id="cbt-user-file" name="user_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                            <p class="description">
                                Kolom wajib: <code>name,username,password,role</code> + salah satu <code>email</code> atau <code>nisn</code>.
                                Role yang didukung: <code>admin</code>, <code>guru</code>, <code>siswa</code>
                                (juga kompatibel: <code>teacher</code>, <code>student</code>).
                                Kolom opsional: <code>email</code>, <code>nisn</code>, <code>kode_kelas</code>, <code>kode_ruang</code>, <code>agama</code>, <code>foto</code>.
                                Jika <code>email</code> kosong/tidak valid tapi <code>nisn</code> ada, sistem otomatis membuat email <code>nisn@student.sch.id</code>.
                                Format didukung: <code>.csv</code> dan <code>.xlsx</code>.
                                Untuk CSV, delimiter koma atau titik-koma didukung.
                                Import data besar diproses bertahap otomatis (batch <?php echo (int) $import_batch_size; ?> user per putaran) untuk mencegah timeout.
                                Progress import akan ditampilkan otomatis (jumlah diproses, persentase, created/updated/failed).
                                Untuk >500 user, disarankan pakai <code>.csv</code> karena parsing biasanya lebih cepat.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Import Users'); ?>
            </form>

            <hr />

            <h2>Tambah User Manual</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('cbt_create_user_manual'); ?>
                <input type="hidden" name="action" value="cbt_create_user_manual" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cbt-user-name">Nama</label></th>
                        <td><input required type="text" id="cbt-user-name" name="name" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-email">Email</label></th>
                        <td><input required type="email" id="cbt-user-email" name="email" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-username">Username</label></th>
                        <td><input required type="text" id="cbt-user-username" name="username" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-password">Password</label></th>
                        <td>
                            <input type="text" id="cbt-user-password" name="password" class="regular-text" />
                            <p class="description">Kosongkan untuk generate password otomatis.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-role">Role</label></th>
                        <td>
                            <select id="cbt-user-role" name="role">
                                <option value="siswa">siswa</option>
                                <option value="guru">guru</option>
                                <?php if (self::is_admin_scope()): ?>
                                    <option value="admin">admin</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-kelas">Kode Kelas</label></th>
                        <td><input type="text" id="cbt-user-kelas" name="kode_kelas" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-ruang">Kode Ruang</label></th>
                        <td><input type="text" id="cbt-user-ruang" name="kode_ruang" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-agama">Agama</label></th>
                        <td><input type="text" id="cbt-user-agama" name="agama" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="cbt-user-foto-file">Foto</label></th>
                        <td>
                            <input type="file" id="cbt-user-foto-file" name="foto_file" accept="image/*" />
                            <p class="description">Pilih file foto profil user (opsional).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Simpan User'); ?>
            </form>

            <?php if ($editing_user instanceof WP_User): ?>
                <hr />

                <?php
                $editing_role_raw = isset($editing_user->roles[0]) ? (string) $editing_user->roles[0] : '';
                $editing_role = self::role_for_form($editing_role_raw);
                $editing_kelas = (string) get_user_meta((int) $editing_user->ID, 'kode_kelas', true);
                $editing_ruang = (string) get_user_meta((int) $editing_user->ID, 'kode_ruang', true);
                $editing_agama = (string) get_user_meta((int) $editing_user->ID, 'agama', true);
                $editing_foto = (string) get_user_meta((int) $editing_user->ID, 'foto', true);
                ?>
                <h2>Edit User: <?php echo esc_html((string) $editing_user->user_login); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('cbt_update_user_manual'); ?>
                    <input type="hidden" name="action" value="cbt_update_user_manual" />
                    <input type="hidden" name="user_id" value="<?php echo (int) $editing_user->ID; ?>" />

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cbt-edit-user-name">Nama</label></th>
                            <td><input required type="text" id="cbt-edit-user-name" name="name" class="regular-text" value="<?php echo esc_attr((string) $editing_user->display_name); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-email">Email</label></th>
                            <td><input required type="email" id="cbt-edit-user-email" name="email" class="regular-text" value="<?php echo esc_attr((string) $editing_user->user_email); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-username">Username</label></th>
                            <td><input required type="text" id="cbt-edit-user-username" name="username" class="regular-text" value="<?php echo esc_attr((string) $editing_user->user_login); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-password">Password Baru</label></th>
                            <td>
                                <input type="text" id="cbt-edit-user-password" name="password" class="regular-text" />
                                <p class="description">Kosongkan jika password tidak diubah.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-role">Role</label></th>
                            <td>
                                <select id="cbt-edit-user-role" name="role">
                                    <option value="siswa" <?php selected($editing_role, 'siswa'); ?>>siswa</option>
                                    <option value="guru" <?php selected($editing_role, 'guru'); ?>>guru</option>
                                    <?php if (self::is_admin_scope()): ?>
                                        <option value="admin" <?php selected($editing_role, 'admin'); ?>>admin</option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-kelas">Kode Kelas</label></th>
                            <td><input type="text" id="cbt-edit-user-kelas" name="kode_kelas" class="regular-text" value="<?php echo esc_attr($editing_kelas); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-ruang">Kode Ruang</label></th>
                            <td><input type="text" id="cbt-edit-user-ruang" name="kode_ruang" class="regular-text" value="<?php echo esc_attr($editing_ruang); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-agama">Agama</label></th>
                            <td><input type="text" id="cbt-edit-user-agama" name="agama" class="regular-text" value="<?php echo esc_attr($editing_agama); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="cbt-edit-user-foto-file">Foto</label></th>
                            <td>
                                <?php if ($editing_foto !== ''): ?>
                                    <div style="margin-bottom:8px;">
                                        <a href="<?php echo esc_url($editing_foto); ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?php echo esc_url($editing_foto); ?>" alt="<?php echo esc_attr((string) $editing_user->display_name); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #d0d7de;" />
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" id="cbt-edit-user-foto-file" name="foto_file" accept="image/*" />
                                <p class="description">Pilih file baru jika ingin mengganti foto.</p>
                                <?php if ($editing_foto !== ''): ?>
                                    <label>
                                        <input type="checkbox" name="hapus_foto" value="1" />
                                        Hapus foto saat update.
                                    </label>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Update User'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cbt-user-import')); ?>" class="button">Batal Edit</a>
                </form>
            <?php endif; ?>

            <hr />

            <h2>Daftar User CBT</h2>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="cbt-user-import" />
                <input type="hidden" name="cbt_user_paged" value="1" />
                <input type="search" name="cbt_user_q" value="<?php echo esc_attr($search); ?>" placeholder="Cari username / nama / email" />
                <select name="cbt_user_role">
                    <option value="">Semua Role</option>
                    <option value="admin" <?php selected($filter_role, 'admin'); ?>>admin</option>
                    <option value="guru" <?php selected($filter_role, 'guru'); ?>>guru</option>
                    <option value="siswa" <?php selected($filter_role, 'siswa'); ?>>siswa</option>
                </select>
                <select name="cbt_user_kelas">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelas_options as $kelas_option): ?>
                        <option value="<?php echo esc_attr($kelas_option); ?>" <?php selected($filter_kelas, $kelas_option); ?>>
                            <?php echo esc_html($kelas_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="cbt_user_ruang">
                    <option value="">Semua Ruang</option>
                    <?php foreach ($ruang_options as $ruang_option): ?>
                        <option value="<?php echo esc_attr($ruang_option); ?>" <?php selected($filter_ruang, $ruang_option); ?>>
                            <?php echo esc_html($ruang_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="cbt_user_per_page">
                    <?php foreach ($per_page_options as $per_page_option): ?>
                        <option value="<?php echo (int) $per_page_option; ?>" <?php selected($per_page, $per_page_option); ?>>
                            <?php echo esc_html((string) $per_page_option); ?> / halaman
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Cari</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cbt-user-import')); ?>" class="button">Reset</a>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px;">
                <?php wp_nonce_field('cbt_bulk_delete_users'); ?>
                <input type="hidden" name="action" value="cbt_bulk_delete_users" />
                <input type="hidden" name="cbt_user_q" value="<?php echo esc_attr($search); ?>" />
                <input type="hidden" name="cbt_user_role" value="<?php echo esc_attr($filter_role); ?>" />
                <input type="hidden" name="cbt_user_kelas" value="<?php echo esc_attr($filter_kelas); ?>" />
                <input type="hidden" name="cbt_user_ruang" value="<?php echo esc_attr($filter_ruang); ?>" />
                <input type="hidden" name="cbt_user_per_page" value="<?php echo (int) $per_page; ?>" />
                <input type="hidden" name="cbt_user_paged" value="<?php echo (int) $current_page; ?>" />
                <button type="submit" class="button button-secondary" name="bulk_mode" value="selected" onclick="return confirm('Yakin hapus user yang dipilih?');">Delete Selected</button>
                <button type="submit" class="button button-secondary" name="bulk_mode" value="all_filtered" onclick="return confirm('Yakin hapus semua user sesuai hasil filter saat ini?');">Delete All (Filtered)</button>

                <table class="widefat striped" style="margin-top:10px;">
                    <thead>
                    <tr>
                        <th style="width:32px;"><input type="checkbox" id="cbt-user-select-all" /></th>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Kode Kelas</th>
                        <th>Kode Ruang</th>
                        <th>Agama</th>
                        <th>Foto</th>
                        <th>Registered</th>
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="12">Tidak ada user.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $role = isset($user->roles[0]) ? (string) $user->roles[0] : '';
                            $kelas = (string) get_user_meta((int) $user->ID, 'kode_kelas', true);
                            $ruang = (string) get_user_meta((int) $user->ID, 'kode_ruang', true);
                            $agama = (string) get_user_meta((int) $user->ID, 'agama', true);
                            $foto = (string) get_user_meta((int) $user->ID, 'foto', true);
                            $edit_url = add_query_arg(
                                [
                                    'page' => 'cbt-user-import',
                                    'edit_user' => (int) $user->ID,
                                    'cbt_user_q' => $search,
                                    'cbt_user_role' => $filter_role,
                                    'cbt_user_kelas' => $filter_kelas,
                                    'cbt_user_ruang' => $filter_ruang,
                                    'cbt_user_per_page' => $per_page,
                                    'cbt_user_paged' => $current_page,
                                ],
                                admin_url('admin.php')
                            );
                            $delete_url = wp_nonce_url(
                                admin_url('admin-post.php?action=cbt_delete_user_manual&id=' . (int) $user->ID),
                                'cbt_delete_user_manual_' . (int) $user->ID
                            );
                            $is_current_user = ((int) $user->ID === get_current_user_id());
                            ?>
                            <tr>
                                <td>
                                    <?php if (!$is_current_user): ?>
                                        <input type="checkbox" class="cbt-user-row-check" name="user_ids[]" value="<?php echo (int) $user->ID; ?>" />
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) $user->ID; ?></td>
                                <td><?php echo esc_html((string) $user->user_login); ?></td>
                                <td><?php echo esc_html((string) $user->display_name); ?></td>
                                <td><?php echo esc_html((string) $user->user_email); ?></td>
                                <td><?php echo esc_html(self::humanize_role($role)); ?></td>
                                <td><?php echo esc_html($kelas); ?></td>
                                <td><?php echo esc_html($ruang); ?></td>
                                <td><?php echo esc_html($agama !== '' ? $agama : '-'); ?></td>
                                <td>
                                    <?php if ($foto !== ''): ?>
                                        <a href="<?php echo esc_url($foto); ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?php echo esc_url($foto); ?>" alt="<?php echo esc_attr((string) $user->display_name); ?>" style="width:34px;height:34px;object-fit:cover;border-radius:6px;border:1px solid #d0d7de;" />
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(mysql2date('Y-m-d H:i', (string) $user->user_registered)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($edit_url); ?>">Edit</a>
                                    <?php if (!$is_current_user): ?>
                                        | <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Hapus user ini?');">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php
                $pagination_args = [
                    'page' => 'cbt-user-import',
                    'cbt_user_per_page' => $per_page,
                ];
                if ($search !== '') {
                    $pagination_args['cbt_user_q'] = $search;
                }
                if ($filter_role !== '') {
                    $pagination_args['cbt_user_role'] = $filter_role;
                }
                if ($filter_kelas !== '') {
                    $pagination_args['cbt_user_kelas'] = $filter_kelas;
                }
                if ($filter_ruang !== '') {
                    $pagination_args['cbt_user_ruang'] = $filter_ruang;
                }
                $pagination_links = [];
                if ($total_pages > 1) {
                    $pagination_links = paginate_links([
                        'base' => add_query_arg(array_merge($pagination_args, ['cbt_user_paged' => '%#%']), admin_url('admin.php')),
                        'format' => '',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'type' => 'array',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ]);
                }
                ?>
                <div class="tablenav bottom cbt-user-pagination-wrap" style="margin-top:8px;">
                    <div class="tablenav-pages cbt-user-pagination" style="float:none; margin:0;">
                        <span class="displaying-num cbt-user-total"><?php echo esc_html(sprintf('Total user: %d', $total_users)); ?></span>
                        <?php if (!empty($pagination_links)): ?>
                            <span class="pagination-links cbt-user-pagination-links">
                                <?php foreach ($pagination_links as $pagination_link): ?>
                                    <?php echo wp_kses_post($pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <style>
                .cbt-user-pagination-wrap {
                    margin-top: 12px;
                }

                .cbt-user-pagination {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 10px;
                }

                .cbt-user-pagination .cbt-user-total {
                    float: none;
                    margin: 0;
                    font-size: 13px;
                    font-weight: 600;
                    color: #1d2327;
                }

                .cbt-user-pagination-links {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 6px;
                    margin: 0;
                }

                .cbt-user-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 36px;
                    height: 36px;
                    padding: 0 10px;
                    box-sizing: border-box;
                    border: 1px solid #c3c4c7;
                    border-radius: 8px;
                    background: #fff;
                    color: #2271b1;
                    font-size: 14px;
                    font-weight: 600;
                    line-height: 1;
                    text-decoration: none;
                }

                .cbt-user-pagination-links .page-numbers:hover,
                .cbt-user-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    color: #135e96;
                    box-shadow: 0 0 0 1px #2271b1;
                    outline: none;
                }

                .cbt-user-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #fff;
                    box-shadow: none;
                }

                .cbt-user-pagination-links .page-numbers.prev,
                .cbt-user-pagination-links .page-numbers.next {
                    min-width: 42px;
                    font-size: 16px;
                    font-weight: 700;
                }

                .cbt-user-pagination-links .page-numbers.dots {
                    min-width: auto;
                    border: none;
                    background: transparent;
                    color: #646970;
                    box-shadow: none;
                    padding: 0 2px;
                }

                @media (max-width: 782px) {
                    .cbt-user-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        font-size: 13px;
                    }
                }
            </style>
        </div>
        <script>
            (function () {
                const selectAll = document.getElementById('cbt-user-select-all');
                const rowChecks = Array.from(document.querySelectorAll('.cbt-user-row-check'));
                if (!selectAll || rowChecks.length === 0) {
                    return;
                }

                function syncSelectState() {
                    const checkedCount = rowChecks.filter((item) => item.checked).length;
                    selectAll.checked = checkedCount > 0 && checkedCount === rowChecks.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
                }

                selectAll.addEventListener('change', () => {
                    rowChecks.forEach((item) => {
                        item.checked = selectAll.checked;
                    });
                    syncSelectState();
                });

                rowChecks.forEach((item) => {
                    item.addEventListener('change', syncSelectState);
                });
            })();
        </script>
        <?php
    }

    private static function handle_user_photo_upload(string $field_name): array
    {
        if (!isset($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return [
                'status' => 'empty',
                'url' => '',
                'error' => '',
            ];
        }

        $file = $_FILES[$field_name];
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error_code === UPLOAD_ERR_NO_FILE) {
            return [
                'status' => 'empty',
                'url' => '',
                'error' => '',
            ];
        }

        if ($error_code !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return [
                'status' => 'error',
                'url' => '',
                'error' => 'Upload foto gagal.',
            ];
        }

        if (!defined('ABSPATH')) {
            return [
                'status' => 'error',
                'url' => '',
                'error' => 'Path upload tidak valid.',
            ];
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => $allowed_mimes,
        ]);

        if (!is_array($upload) || !empty($upload['error']) || empty($upload['url'])) {
            $upload_error = is_array($upload) && !empty($upload['error']) ? sanitize_text_field((string) $upload['error']) : 'Upload foto gagal.';
            return [
                'status' => 'error',
                'url' => '',
                'error' => $upload_error,
            ];
        }

        return [
            'status' => 'uploaded',
            'url' => esc_url_raw((string) $upload['url']),
            'error' => '',
        ];
    }

    public static function handle_create_user_manual(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_create_user_manual');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username']), true) : '';
        $raw_password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $role_input = isset($_POST['role']) ? strtolower(sanitize_text_field(wp_unslash($_POST['role']))) : 'siswa';
        $kode_kelas = isset($_POST['kode_kelas']) ? sanitize_text_field(wp_unslash($_POST['kode_kelas'])) : '';
        $kode_ruang = isset($_POST['kode_ruang']) ? sanitize_text_field(wp_unslash($_POST['kode_ruang'])) : '';
        $agama = isset($_POST['agama']) ? sanitize_text_field(wp_unslash($_POST['agama'])) : '';
        $foto = isset($_POST['foto']) ? esc_url_raw(wp_unslash($_POST['foto'])) : '';

        if ($name === '' || $username === '' || !is_email($email)) {
            self::redirect_user_import_with_error('Nama, username, dan email valid wajib diisi.');
        }

        if (username_exists($username)) {
            self::redirect_user_import_with_error('Username sudah terdaftar.');
        }
        if (email_exists($email)) {
            self::redirect_user_import_with_error('Email sudah terdaftar.');
        }

        $role = self::map_import_role($role_input);
        if ($role === 'administrator' && !self::is_admin_scope()) {
            self::redirect_user_import_with_error('Hanya admin yang bisa membuat user role admin.');
        }
        $foto_upload = self::handle_user_photo_upload('foto_file');
        if ($foto_upload['status'] === 'error') {
            self::redirect_user_import_with_error('Gagal upload foto: ' . (string) $foto_upload['error']);
        }
        if ($foto_upload['status'] === 'uploaded') {
            $foto = (string) $foto_upload['url'];
        }
        $foto = self::resolve_student_default_photo($role, $foto);

        $generated_password = false;
        $password = $raw_password;
        if ($password === '') {
            $password = wp_generate_password(12, true, true);
            $generated_password = true;
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            self::redirect_user_import_with_error('Gagal membuat user: ' . $user_id->get_error_message());
        }

        if ($kode_kelas !== '') {
            update_user_meta((int) $user_id, 'kode_kelas', $kode_kelas);
        }
        if ($kode_ruang !== '') {
            update_user_meta((int) $user_id, 'kode_ruang', $kode_ruang);
        }
        if ($agama !== '') {
            update_user_meta((int) $user_id, 'agama', $agama);
        }
        if ($foto !== '') {
            update_user_meta((int) $user_id, 'foto', $foto);
        }
        update_user_meta((int) $user_id, self::USER_META_PLAIN_PASSWORD, $password);

        $msg = 'User berhasil dibuat.';
        if ($generated_password) {
            $msg .= ' Password otomatis: ' . $password;
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode($msg)));
        exit;
    }

    public static function handle_update_user_manual(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_update_user_manual');

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username']), true) : '';
        $raw_password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $role_input = isset($_POST['role']) ? strtolower(sanitize_text_field(wp_unslash($_POST['role']))) : 'siswa';
        $kode_kelas = isset($_POST['kode_kelas']) ? sanitize_text_field(wp_unslash($_POST['kode_kelas'])) : '';
        $kode_ruang = isset($_POST['kode_ruang']) ? sanitize_text_field(wp_unslash($_POST['kode_ruang'])) : '';
        $agama = isset($_POST['agama']) ? sanitize_text_field(wp_unslash($_POST['agama'])) : '';
        $foto = isset($_POST['foto']) ? esc_url_raw(wp_unslash($_POST['foto'])) : '';
        $has_foto_input = isset($_POST['foto']);
        $hapus_foto = isset($_POST['hapus_foto']) && sanitize_text_field(wp_unslash($_POST['hapus_foto'])) === '1';

        if ($user_id <= 0) {
            self::redirect_user_import_with_error('User tidak valid.');
        }
        if ($name === '' || $username === '' || !is_email($email)) {
            self::redirect_user_import_with_error('Nama, username, dan email valid wajib diisi.');
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            self::redirect_user_import_with_error('User tidak ditemukan.');
        }

        $existing_username = get_user_by('login', $username);
        if ($existing_username instanceof WP_User && (int) $existing_username->ID !== $user_id) {
            self::redirect_user_import_with_error('Username sudah terdaftar.');
        }

        $existing_email = get_user_by('email', $email);
        if ($existing_email instanceof WP_User && (int) $existing_email->ID !== $user_id) {
            self::redirect_user_import_with_error('Email sudah terdaftar.');
        }

        $role = self::map_import_role($role_input);
        if ($role === 'administrator' && !self::is_admin_scope()) {
            self::redirect_user_import_with_error('Hanya admin yang bisa mengubah user jadi admin.');
        }
        $foto_upload = self::handle_user_photo_upload('foto_file');
        if ($foto_upload['status'] === 'error') {
            self::redirect_user_import_with_error('Gagal upload foto: ' . (string) $foto_upload['error']);
        }

        if ($username !== (string) $user->user_login) {
            global $wpdb;
            $login_updated = $wpdb->update(
                $wpdb->users,
                [
                    'user_login' => $username,
                    'user_nicename' => sanitize_title($username),
                ],
                ['ID' => $user_id],
                ['%s', '%s'],
                ['%d']
            );
            if ($login_updated === false) {
                self::redirect_user_import_with_error('Gagal mengubah username.');
            }
            clean_user_cache($user_id);
        }

        $updated = wp_update_user([
            'ID' => $user_id,
            'user_email' => $email,
            'display_name' => $name,
            'role' => $role,
        ]);
        if (is_wp_error($updated)) {
            self::redirect_user_import_with_error('Gagal update user: ' . $updated->get_error_message());
        }

        if ($raw_password !== '') {
            wp_set_password($raw_password, $user_id);
            update_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, $raw_password);
        }

        if ($kode_kelas !== '') {
            update_user_meta($user_id, 'kode_kelas', $kode_kelas);
        } else {
            delete_user_meta($user_id, 'kode_kelas');
        }

        if ($kode_ruang !== '') {
            update_user_meta($user_id, 'kode_ruang', $kode_ruang);
        } else {
            delete_user_meta($user_id, 'kode_ruang');
        }
        if ($agama !== '') {
            update_user_meta($user_id, 'agama', $agama);
        } else {
            delete_user_meta($user_id, 'agama');
        }

        if ($foto_upload['status'] === 'uploaded') {
            update_user_meta($user_id, 'foto', (string) $foto_upload['url']);
        } elseif ($hapus_foto) {
            delete_user_meta($user_id, 'foto');
        } elseif ($has_foto_input) {
            if ($foto !== '') {
                update_user_meta($user_id, 'foto', $foto);
            } else {
                delete_user_meta($user_id, 'foto');
            }
        }

        if (self::is_student_role($role)) {
            $current_foto = trim((string) get_user_meta($user_id, 'foto', true));
            if ($current_foto === '') {
                update_user_meta($user_id, 'foto', self::get_default_student_photo_url());
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode('User berhasil diupdate.')));
        exit;
    }

    public static function handle_delete_user_manual(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        $user_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('cbt_delete_user_manual_' . $user_id);

        if ($user_id <= 0) {
            self::redirect_user_import_with_error('User tidak valid.');
        }
        if ($user_id === get_current_user_id()) {
            self::redirect_user_import_with_error('User login saat ini tidak boleh dihapus.');
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            self::redirect_user_import_with_error('User tidak ditemukan.');
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user($user_id);
        if (!$deleted) {
            self::redirect_user_import_with_error('Gagal menghapus user.');
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode('User berhasil dihapus.')));
        exit;
    }

    public static function handle_bulk_delete_users(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_delete_users');

        $search = isset($_POST['cbt_user_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_q'])) : '';
        $filter_role = isset($_POST['cbt_user_role']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_role'])) : '';
        $filter_kelas = isset($_POST['cbt_user_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_kelas'])) : '';
        $filter_ruang = isset($_POST['cbt_user_ruang']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_ruang'])) : '';
        $per_page = isset($_POST['cbt_user_per_page'])
            ? self::normalize_user_list_per_page(absint(wp_unslash($_POST['cbt_user_per_page'])))
            : 20;
        $current_page = isset($_POST['cbt_user_paged']) ? max(1, absint(wp_unslash($_POST['cbt_user_paged']))) : 1;
        $bulk_mode = isset($_POST['bulk_mode']) ? sanitize_text_field(wp_unslash($_POST['bulk_mode'])) : 'selected';

        $redirect_args = [
            'page' => 'cbt-user-import',
        ];
        if ($search !== '') {
            $redirect_args['cbt_user_q'] = $search;
        }
        if ($filter_role !== '') {
            $redirect_args['cbt_user_role'] = $filter_role;
        }
        if ($filter_kelas !== '') {
            $redirect_args['cbt_user_kelas'] = $filter_kelas;
        }
        if ($filter_ruang !== '') {
            $redirect_args['cbt_user_ruang'] = $filter_ruang;
        }
        $redirect_args['cbt_user_per_page'] = $per_page;
        $redirect_args['cbt_user_paged'] = $current_page;

        $target_user_ids = [];
        if ($bulk_mode === 'all_filtered') {
            $target_user_ids = self::get_cbt_user_ids($search, $filter_role, $filter_kelas, $filter_ruang);
        } else {
            $raw_user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? wp_unslash($_POST['user_ids']) : [];
            $target_user_ids = array_map('absint', $raw_user_ids);
        }

        $target_user_ids = array_values(array_unique(array_filter($target_user_ids)));
        $current_user_id = get_current_user_id();
        $target_user_ids = array_values(array_filter($target_user_ids, static function ($user_id) use ($current_user_id): bool {
            return (int) $user_id !== (int) $current_user_id;
        }));

        if (empty($target_user_ids)) {
            $redirect_args['cbt_err'] = 'Tidak ada user yang dipilih untuk dihapus.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $deleted_count = 0;
        foreach ($target_user_ids as $user_id) {
            $deleted = wp_delete_user((int) $user_id);
            if ($deleted) {
                $deleted_count++;
            }
        }

        if ($deleted_count > 0) {
            $redirect_args['cbt_msg'] = sprintf('User berhasil dihapus: %d.', $deleted_count);
        } else {
            $redirect_args['cbt_err'] = 'Tidak ada user yang berhasil dihapus.';
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_save_global_exam_token(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_global_exam_token');

        $token_mode = isset($_POST['token_mode']) ? sanitize_key((string) wp_unslash($_POST['token_mode'])) : 'save';
        $raw_token = isset($_POST['global_exam_token']) ? (string) wp_unslash($_POST['global_exam_token']) : '';
        $raw_refresh = isset($_POST['global_exam_token_refresh_minutes']) ? (int) $_POST['global_exam_token_refresh_minutes'] : 15;
        $frontend_auto_apply = isset($_POST['global_exam_token_frontend_auto_apply']);
        $is_regenerate = ($token_mode === 'regenerate');

        CBT_Auth::save_global_exam_token_settings($raw_token, $raw_refresh, $is_regenerate, $frontend_auto_apply);
        CBT_Cache::invalidate_catalog();

        $message = $is_regenerate
            ? 'Token global berhasil digenerate ulang.'
            : 'Pengaturan token global berhasil disimpan.';

        wp_safe_redirect(admin_url('admin.php?page=cbt-tokens&cbt_msg=' . rawurlencode($message)));
        exit;
    }

    public static function handle_save_setup_branding(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_setup_branding');

        $school_name = isset($_POST['school_name'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_name'])))
            : '';
        $logo_attachment_id = isset($_POST['logo_attachment_id']) ? absint($_POST['logo_attachment_id']) : 0;
        if ($logo_attachment_id > 0 && !wp_attachment_is_image($logo_attachment_id)) {
            $logo_attachment_id = 0;
        }

        update_option(
            self::SETUP_BRANDING_OPTION,
            [
                'school_name' => $school_name,
                'logo_attachment_id' => $logo_attachment_id,
            ],
            false
        );

        wp_safe_redirect(admin_url('admin.php?page=cbt-setup&cbt_msg=' . rawurlencode('Setup branding berhasil disimpan.')));
        exit;
    }

    public static function handle_reset_database(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_reset_progress_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_reset_progress_token'])) : '';
        if ($token !== '') {
            self::continue_reset_database($token);
        }

        check_admin_referer('cbt_reset_database');

        $confirm_phrase = isset($_POST['confirm_phrase'])
            ? trim((string) sanitize_text_field(wp_unslash($_POST['confirm_phrase'])))
            : '';
        if ($confirm_phrase !== 'RESET CBT') {
            self::redirect_maintenance_page(null, 'Konfirmasi tidak valid. Ketik persis: RESET CBT');
        }

        global $wpdb;
        $tables = self::cbt_data_tables($wpdb);
        $user_ids = self::collect_cbt_user_ids_for_reset();
        $token = strtolower((string) wp_generate_password(24, false, false));
        $total_units = count($tables) + max(1, count($user_ids)) + 1; // table reset + user delete + finalize
        $state = [
            'user_id' => get_current_user_id(),
            'started_at' => time(),
            'phase' => 'tables',
            'tables' => $tables,
            'table_index' => 0,
            'failed_tables' => [],
            'foreign_keys_disabled' => 0,
            'user_offset' => 0,
            'users_placeholder_done' => 0,
            'deleted_user_count' => 0,
            'total_units' => max(1, $total_units),
            'processed_units' => 0,
        ];
        $state_saved = set_transient(self::get_reset_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        $users_saved = set_transient(self::get_reset_progress_users_key($token), array_values($user_ids), 12 * HOUR_IN_SECONDS);
        if (!$state_saved || !$users_saved) {
            self::clear_reset_progress_transients($token);
            self::redirect_maintenance_page(null, 'Gagal menyiapkan sesi reset database. Coba ulang lagi.');
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'cbt-maintenance',
                'cbt_reset_progress_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    private static function continue_reset_database(string $token): void
    {
        $state = self::get_reset_progress_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_reset_progress_transients($token);
            self::redirect_maintenance_page(null, 'Sesi reset database berakhir. Silakan mulai ulang reset.');
        }

        $tables = isset($state['tables']) && is_array($state['tables']) ? array_values((array) $state['tables']) : [];
        $users = get_transient(self::get_reset_progress_users_key($token));
        if (!is_array($users)) {
            $users = [];
        }
        $users = array_values(array_map('intval', $users));

        $phase = sanitize_key((string) ($state['phase'] ?? 'tables'));
        $table_index = max(0, isset($state['table_index']) ? (int) $state['table_index'] : 0);
        $user_offset = max(0, isset($state['user_offset']) ? (int) $state['user_offset'] : 0);
        $users_placeholder_done = !empty($state['users_placeholder_done']) ? 1 : 0;
        $deleted_user_count = max(0, isset($state['deleted_user_count']) ? (int) $state['deleted_user_count'] : 0);
        $failed_tables = [];
        if (isset($state['failed_tables']) && is_array($state['failed_tables'])) {
            foreach ($state['failed_tables'] as $failed_table) {
                $failed_table = str_replace('`', '', (string) $failed_table);
                if ($failed_table !== '') {
                    $failed_tables[$failed_table] = $failed_table;
                }
            }
        }

        $table_total = count($tables);
        if ($table_index > $table_total) {
            $table_index = $table_total;
        }
        $user_total = count($users);
        if ($user_offset > $user_total) {
            $user_offset = $user_total;
        }
        $total_units = max(1, isset($state['total_units']) ? (int) $state['total_units'] : ($table_total + max(1, $user_total) + 1));

        $max_batch_seconds = self::get_reset_progress_max_batch_seconds();
        $table_batch_size = self::get_reset_progress_table_batch_size();
        $user_batch_size = self::get_reset_progress_user_batch_size();
        $batch_started_at = microtime(true);

        global $wpdb;

        if ($phase === 'tables') {
            if (empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
                $state['foreign_keys_disabled'] = 1;
            }

            $processed_tables_this_round = 0;
            while ($table_index < $table_total && $processed_tables_this_round < $table_batch_size) {
                $safe_table = str_replace('`', '', (string) $tables[$table_index]);
                if ($safe_table !== '') {
                    $truncated = $wpdb->query("TRUNCATE TABLE `{$safe_table}`");
                    if ($truncated === false) {
                        $deleted = $wpdb->query("DELETE FROM `{$safe_table}`");
                        if ($deleted === false) {
                            $failed_tables[$safe_table] = $safe_table;
                        } else {
                            $wpdb->query("ALTER TABLE `{$safe_table}` AUTO_INCREMENT = 1");
                        }
                    }
                }

                $table_index++;
                $processed_tables_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($table_index >= $table_total) {
                if (!empty($state['foreign_keys_disabled'])) {
                    $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
                }
                $state['foreign_keys_disabled'] = 0;
                $phase = 'users';
            }
        }

        if ($phase === 'users' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            if ($user_total <= 0) {
                $users_placeholder_done = 1;
                $phase = 'finalize';
            } else {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $target_offset = min($user_offset + $user_batch_size, $user_total);
                for ($index = $user_offset; $index < $target_offset; $index++) {
                    $user_id = isset($users[$index]) ? (int) $users[$index] : 0;
                    if ($user_id <= 0) {
                        continue;
                    }
                    $deleted = wp_delete_user($user_id);
                    if ($deleted) {
                        $deleted_user_count++;
                    }

                    if (($index - $user_offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                        $target_offset = $index + 1;
                        break;
                    }
                }
                $user_offset = $target_offset;
                if ($user_offset >= $user_total) {
                    $phase = 'finalize';
                }
            }
        }

        if ($phase === 'finalize' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            self::reset_cbt_global_token_options();
            CBT_UI_State::clear_all();
            CBT_Cache::reset_plugin_cache_state();
            self::clear_reset_progress_transients($token);

            if (!empty($failed_tables)) {
                self::redirect_maintenance_page(
                    null,
                    'Sebagian tabel gagal direset: ' . implode(', ', array_values($failed_tables))
                    . '. User CBT terhapus: ' . $deleted_user_count . '.'
                );
            }

            $message = 'Data database CBT berhasil direset. User CBT terhapus: ' . $deleted_user_count . '.';
            self::redirect_maintenance_page($message);
        }

        $user_progress_units = $user_total > 0
            ? min($user_offset, $user_total)
            : ($users_placeholder_done ? 1 : 0);
        $processed_units = $table_index + $user_progress_units;
        if ($processed_units > ($total_units - 1)) {
            $processed_units = $total_units - 1;
        }
        if ($processed_units < 0) {
            $processed_units = 0;
        }

        $state['phase'] = $phase;
        $state['table_index'] = $table_index;
        $state['user_offset'] = $user_offset;
        $state['users_placeholder_done'] = $users_placeholder_done;
        $state['deleted_user_count'] = $deleted_user_count;
        $state['failed_tables'] = array_values($failed_tables);
        $state['total_units'] = $total_units;
        $state['processed_units'] = $processed_units;

        $state_saved = set_transient(self::get_reset_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$state_saved) {
            if (!empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::clear_reset_progress_transients($token);
            self::redirect_maintenance_page(null, 'Gagal menyimpan progres reset database. Silakan mulai ulang reset.');
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'cbt-maintenance',
                'cbt_reset_progress_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handle_backfill_question_sources(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_backfill_question_sources');

        $result = self::run_question_source_backfill();
        if (!empty($result['error'])) {
            self::redirect_maintenance_page(null, (string) $result['error']);
        }

        $message = sprintf(
            'Backfill soal bank selesai. Source dipindai: %d, soal exam tersinkron: %d, linkage baru: %d, exam terdampak: %d.',
            (int) ($result['scanned_sources'] ?? 0),
            (int) ($result['updated_questions'] ?? 0),
            (int) ($result['new_links'] ?? 0),
            (int) ($result['affected_exams'] ?? 0)
        );

        self::redirect_maintenance_page($message);
    }

    public static function handle_save_exam(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_exam');

        global $wpdb;

        $table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $subject_id = isset($_POST['subject_id']) ? absint($_POST['subject_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
        $duration = max(1, isset($_POST['duration_minutes']) ? absint($_POST['duration_minutes']) : 60);
        $randomize = isset($_POST['randomize_questions']) ? 1 : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'draft';
        $allowed_statuses = ['draft', 'published', 'closed'];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'draft';
        }
        $starts_at = isset($_POST['starts_at']) ? self::from_datetime_local((string) wp_unslash($_POST['starts_at'])) : null;
        $ends_at = isset($_POST['ends_at']) ? self::from_datetime_local((string) wp_unslash($_POST['ends_at'])) : null;
        $target_kelas_raw = isset($_POST['target_kelas']) ? wp_unslash($_POST['target_kelas']) : '';
        $target_kelas = self::normalize_target_kelas_csv($target_kelas_raw);

        $raw_source_question_ids = isset($_POST['source_question_ids']) && is_array($_POST['source_question_ids'])
            ? wp_unslash($_POST['source_question_ids'])
            : [];
        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', $raw_source_question_ids))));

        if ($subject_id <= 0) {
            self::redirect_exam_with_error('Mapel wajib dipilih.', $id);
        }
        if ($title === '') {
            self::redirect_exam_with_error('Judul exam wajib diisi.', $id);
        }
        if ($starts_at !== null && $ends_at !== null && strtotime($ends_at) < strtotime($starts_at)) {
            self::redirect_exam_with_error('Waktu selesai tidak boleh lebih awal dari waktu mulai.', $id);
        }
        if ($id <= 0 && empty($source_question_ids)) {
            self::redirect_exam_with_error('Pilih minimal 1 soal untuk exam baru.');
        }

        $existing_question_count = 0;
        if ($id > 0 && empty($source_question_ids)) {
            $existing_question_count = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cbt_questions WHERE exam_id = %d", $id)
            );
        }

        $data = [
            'subject_id' => $subject_id,
            'title' => $title,
            'description' => $description,
            'duration_minutes' => $duration,
            'total_questions' => $existing_question_count,
            'randomize_questions' => $randomize,
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'target_kelas' => $target_kelas,
            'updated_at' => current_time('mysql'),
        ];

        $saved_exam_id = $id;
        if ($id > 0) {
            if (!$is_admin_scope) {
                $owned_exam = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE id = %d AND created_by = %d",
                    $id,
                    $current_user_id
                ));
                if ($owned_exam === 0) {
                    wp_die('Unauthorized exam update.');
                }
            }

            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $id],
                ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            if ($updated === false) {
                self::redirect_exam_with_error('Gagal mengupdate exam.', $id);
            }
        } else {
            $data['created_by'] = $current_user_id;
            $data['created_at'] = current_time('mysql');

            $inserted = $wpdb->insert(
                $table,
                $data,
                ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );
            if (!$inserted) {
                self::redirect_exam_with_error('Gagal membuat exam.');
            }

            $saved_exam_id = (int) $wpdb->insert_id;
        }

        $synced_questions = null;
        if (!empty($source_question_ids)) {
            $sync_result = self::sync_exam_questions_from_sources(
                $saved_exam_id,
                $source_question_ids,
                $is_admin_scope,
                $current_user_id
            );
            if (is_wp_error($sync_result)) {
                self::redirect_exam_with_error($sync_result->get_error_message(), $saved_exam_id);
            }

            $synced_questions = (int) $sync_result;
            $wpdb->update(
                $table,
                [
                    'total_questions' => $synced_questions,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $saved_exam_id],
                ['%d', '%s'],
                ['%d']
            );
        }

        $msg = ($id > 0) ? 'Exam updated' : 'Exam created';
        if ($synced_questions !== null) {
            $msg .= ' - ' . $synced_questions . ' soal tersinkron.';
        }

        CBT_Cache::invalidate_catalog();
        CBT_Cache::invalidate_exam($saved_exam_id);

        wp_safe_redirect(admin_url('admin.php?page=cbt-exams&cbt_msg=' . rawurlencode($msg)));
        exit;
    }

    public static function handle_delete_exam(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('cbt_delete_exam_' . $id);
        $exam_per_page = self::normalize_standard_list_per_page(
            isset($_GET['cbt_exam_per_page']) ? absint(wp_unslash($_GET['cbt_exam_per_page'])) : 20
        );
        $exam_paged = isset($_GET['cbt_exam_paged']) ? max(1, absint(wp_unslash($_GET['cbt_exam_paged']))) : 1;

        if ($id > 0) {
            global $wpdb;
            $exam_title = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT title FROM {$wpdb->prefix}cbt_exams WHERE id = %d", $id)
            );
            if (stripos($exam_title, 'Bank Soal - ') === 0) {
                wp_safe_redirect(add_query_arg([
                    'page' => 'cbt-exams',
                    'cbt_exam_per_page' => $exam_per_page,
                    'cbt_exam_paged' => $exam_paged,
                    'cbt_err' => 'Exam bank soal tidak boleh dihapus dari menu ini.',
                ], admin_url('admin.php')));
                exit;
            }

            if (!self::is_admin_scope()) {
                $owned_exam = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cbt_exams WHERE id = %d AND created_by = %d",
                    $id,
                    get_current_user_id()
                ));
                if ($owned_exam === 0) {
                    wp_die('Unauthorized exam delete.');
                }
            }

            $wpdb->delete($wpdb->prefix . 'cbt_exams', ['id' => $id], ['%d']);
        }

        if ($id > 0) {
            CBT_Cache::invalidate_catalog();
            CBT_Cache::invalidate_exam($id);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-exams',
            'cbt_exam_per_page' => $exam_per_page,
            'cbt_exam_paged' => $exam_paged,
            'cbt_msg' => 'Exam deleted',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * @return int|WP_Error
     */
    private static function sync_exam_questions_from_sources(
        int $exam_id,
        array $source_question_ids,
        bool $is_admin_scope,
        int $current_user_id
    ) {
        global $wpdb;

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam', 'Exam tidak valid.');
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', $source_question_ids))));

        if (empty($source_question_ids)) {
            return new WP_Error('empty_questions', 'Pilih minimal 1 soal.');
        }

        $source_placeholders = implode(',', array_fill(0, count($source_question_ids), '%d'));
        $query_params = $source_question_ids;

        $source_sql = "SELECT q.*
                       FROM {$question_table} q
                       INNER JOIN {$exam_table} e ON e.id = q.exam_id
                       WHERE q.id IN ({$source_placeholders})";

        if (!$is_admin_scope) {
            $source_sql .= ' AND e.created_by = %d';
            $query_params[] = $current_user_id;
        }

        $source_rows = $wpdb->get_results($wpdb->prepare($source_sql, ...$query_params), ARRAY_A);
        $source_by_id = [];
        foreach ((array) $source_rows as $source_row) {
            $source_by_id[(int) ($source_row['id'] ?? 0)] = $source_row;
        }

        $ordered_sources = [];
        foreach ($source_question_ids as $source_question_id) {
            if (isset($source_by_id[$source_question_id])) {
                $ordered_sources[] = $source_by_id[$source_question_id];
            }
        }

        if (empty($ordered_sources)) {
            return new WP_Error('invalid_questions', 'Soal sumber tidak ditemukan.');
        }

        $ordered_source_ids = array_map(static function ($row): int {
            return (int) ($row['id'] ?? 0);
        }, $ordered_sources);

        $option_placeholders = implode(',', array_fill(0, count($ordered_source_ids), '%d'));
        $option_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$option_table} WHERE question_id IN ({$option_placeholders}) ORDER BY id ASC",
                ...$ordered_source_ids
            ),
            ARRAY_A
        );

        $options_by_question = [];
        foreach ((array) $option_rows as $option_row) {
            $question_id = (int) ($option_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            if (!isset($options_by_question[$question_id])) {
                $options_by_question[$question_id] = [];
            }
            $options_by_question[$question_id][] = $option_row;
        }

        $detail_by_question = [];
        foreach ($ordered_sources as $source_row) {
            $source_question_id = (int) ($source_row['id'] ?? 0);
            $source_question_type = (string) ($source_row['question_type'] ?? '');
            $detail_by_question[$source_question_id] = self::get_question_type_detail($source_question_id, $source_question_type);
        }

        $deleted_existing = $wpdb->delete($question_table, ['exam_id' => $exam_id], ['%d']);
        if ($deleted_existing === false) {
            return new WP_Error('delete_failed', 'Gagal membersihkan soal exam sebelumnya.');
        }

        $inserted_count = 0;
        $now = current_time('mysql');

        foreach ($ordered_sources as $source_row) {
            $source_question_id = (int) ($source_row['id'] ?? 0);
            $question_type = (string) ($source_row['question_type'] ?? 'multiple_choice');
            $source_options = $options_by_question[$source_question_id] ?? [];

            $inserted_question = $wpdb->insert(
                $question_table,
                [
                    'exam_id' => $exam_id,
                    'source_question_id' => $source_question_id,
                    'question_text' => (string) ($source_row['question_text'] ?? ''),
                    'question_type' => $question_type,
                    'points' => (float) ($source_row['points'] ?? 1),
                    'correct_text' => (string) ($source_row['correct_text'] ?? ''),
                    'explanation' => (string) ($source_row['explanation'] ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
            );

            if (!$inserted_question) {
                return new WP_Error('insert_failed', 'Gagal menyalin salah satu soal ke exam.');
            }

            $new_question_id = (int) $wpdb->insert_id;
            foreach ($source_options as $source_option) {
                $wpdb->insert(
                    $option_table,
                    [
                        'question_id' => $new_question_id,
                        'option_key' => (string) ($source_option['option_key'] ?? ''),
                        'option_text' => (string) ($source_option['option_text'] ?? ''),
                        'is_correct' => (int) ($source_option['is_correct'] ?? 0),
                        'created_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%d', '%s']
                );
            }

            $source_detail = $detail_by_question[$source_question_id] ?? [];
            $detail_text = '';
            if ($question_type === 'true_false') {
                $detail_value = isset($source_detail['correct_value'])
                    ? (int) $source_detail['correct_value']
                    : self::normalize_true_false_value((string) ($source_row['correct_text'] ?? ''));
                $detail_text = ($detail_value === 0) ? 'false' : 'true';
            } elseif ($question_type === 'short_answer') {
                $detail_text = (string) ($source_detail['correct_text'] ?? ($source_row['correct_text'] ?? ''));
            } elseif ($question_type === 'essay') {
                $detail_text = (string) ($source_detail['rubric_text'] ?? ($source_row['correct_text'] ?? ''));
            }

            self::save_question_type_detail($new_question_id, $question_type, $detail_text);
            $inserted_count++;
        }

        return $inserted_count;
    }

    private static function is_bank_exam_title(string $exam_title): bool
    {
        return stripos($exam_title, 'Bank Soal - ') === 0;
    }

    private static function is_bank_question_snapshot(array $snapshot): bool
    {
        return self::is_bank_exam_title((string) ($snapshot['exam_title'] ?? ''));
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_question_sync_snapshot(int $question_id): array
    {
        global $wpdb;

        if ($question_id <= 0) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $option_table = $wpdb->prefix . 'cbt_options';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT q.*, e.subject_id, e.title AS exam_title
                 FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 WHERE q.id = %d
                 LIMIT 1",
                $question_id
            ),
            ARRAY_A
        );
        if (!is_array($row) || empty($row)) {
            return [];
        }

        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, option_key, option_text, is_correct
                 FROM {$option_table}
                 WHERE question_id = %d
                 ORDER BY id ASC",
                $question_id
            ),
            ARRAY_A
        );

        $question_type = (string) ($row['question_type'] ?? '');
        $detail = self::get_question_type_detail($question_id, $question_type);
        $normalized_detail_text = '';
        if ($question_type === 'true_false') {
            $detail_value = array_key_exists('correct_value', $detail)
                ? (int) $detail['correct_value']
                : self::normalize_true_false_value((string) ($row['correct_text'] ?? ''));
            $normalized_detail_text = ($detail_value === 0) ? 'false' : 'true';
        } elseif ($question_type === 'short_answer') {
            $normalized_detail_text = (string) ($detail['correct_text'] ?? ($row['correct_text'] ?? ''));
        } elseif ($question_type === 'essay') {
            $normalized_detail_text = (string) ($detail['rubric_text'] ?? ($row['correct_text'] ?? ''));
        } elseif ($question_type === 'true_false_matrix') {
            $normalized_detail_text = (string) ($row['correct_text'] ?? '');
        }

        return [
            'question_id' => (int) ($row['id'] ?? 0),
            'exam_id' => (int) ($row['exam_id'] ?? 0),
            'subject_id' => (int) ($row['subject_id'] ?? 0),
            'exam_title' => (string) ($row['exam_title'] ?? ''),
            'source_question_id' => (int) ($row['source_question_id'] ?? 0),
            'question_text' => (string) ($row['question_text'] ?? ''),
            'question_type' => $question_type,
            'points' => (float) ($row['points'] ?? 0),
            'correct_text' => (string) ($row['correct_text'] ?? ''),
            'explanation' => (string) ($row['explanation'] ?? ''),
            'normalized_detail_text' => $normalized_detail_text,
            'options' => is_array($options) ? $options : [],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_question_sync_options(array $options): array
    {
        $normalized = [];

        foreach ($options as $index => $option_row) {
            $option = (array) $option_row;
            $option_key = trim((string) ($option['option_key'] ?? ''));
            $normalized[] = [
                'id' => (int) ($option['id'] ?? 0),
                'option_key' => $option_key,
                'match_key' => $option_key !== '' ? $option_key : '__idx_' . $index,
                'option_text' => (string) ($option['option_text'] ?? ''),
                'is_correct' => ((int) ($option['is_correct'] ?? 0) === 1) ? 1 : 0,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function option_sync_signature(array $options): array
    {
        $signature = [];
        foreach (self::normalize_question_sync_options($options) as $option) {
            $signature[] = [
                'match_key' => (string) ($option['match_key'] ?? ''),
                'option_text' => (string) ($option['option_text'] ?? ''),
                'is_correct' => (int) ($option['is_correct'] ?? 0),
            ];
        }

        return $signature;
    }

    private static function question_snapshots_are_sync_equivalent(array $left, array $right): bool
    {
        return
            (string) ($left['question_text'] ?? '') === (string) ($right['question_text'] ?? '') &&
            (string) ($left['question_type'] ?? '') === (string) ($right['question_type'] ?? '') &&
            round((float) ($left['points'] ?? 0), 2) === round((float) ($right['points'] ?? 0), 2) &&
            (string) ($left['correct_text'] ?? '') === (string) ($right['correct_text'] ?? '') &&
            (string) ($left['explanation'] ?? '') === (string) ($right['explanation'] ?? '') &&
            (string) ($left['normalized_detail_text'] ?? '') === (string) ($right['normalized_detail_text'] ?? '') &&
            self::option_sync_signature((array) ($left['options'] ?? [])) === self::option_sync_signature((array) ($right['options'] ?? []));
    }

    private static function question_snapshots_are_legacy_descendant_match(array $candidate, array $source): bool
    {
        if (self::question_snapshots_are_sync_equivalent($candidate, $source)) {
            return true;
        }

        return
            (string) ($candidate['question_type'] ?? '') === (string) ($source['question_type'] ?? '') &&
            round((float) ($candidate['points'] ?? 0), 2) === round((float) ($source['points'] ?? 0), 2) &&
            (string) ($candidate['correct_text'] ?? '') === (string) ($source['correct_text'] ?? '') &&
            (string) ($candidate['explanation'] ?? '') === (string) ($source['explanation'] ?? '') &&
            (string) ($candidate['normalized_detail_text'] ?? '') === (string) ($source['normalized_detail_text'] ?? '') &&
            self::option_sync_signature((array) ($candidate['options'] ?? [])) === self::option_sync_signature((array) ($source['options'] ?? [])) &&
            self::question_texts_look_related(
                (string) ($candidate['question_text'] ?? ''),
                (string) ($source['question_text'] ?? '')
            );
    }

    private static function question_texts_look_related(string $left, string $right): bool
    {
        $left = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($left))));
        $right = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($right))));
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        if (strpos($left, $right) !== false || strpos($right, $left) !== false) {
            return true;
        }

        similar_text($left, $right, $percent);
        return $percent >= 82.0;
    }

    /**
     * @return int[]
     */
    private static function collect_descendant_question_ids_for_source(int $source_question_id, array $reference_snapshot): array
    {
        global $wpdb;

        if ($source_question_id <= 0 || empty($reference_snapshot)) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $direct_descendant_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$question_table}
                 WHERE source_question_id = %d
                   AND id <> %d",
                $source_question_id,
                $source_question_id
            )
        );

        $target_question_ids = [];
        foreach ((array) $direct_descendant_ids as $target_question_id) {
            $target_question_id = (int) $target_question_id;
            if ($target_question_id > 0) {
                $target_question_ids[$target_question_id] = $target_question_id;
            }
        }

        foreach (self::find_legacy_descendant_question_ids($source_question_id, $reference_snapshot) as $target_question_id) {
            $target_question_id = (int) $target_question_id;
            if ($target_question_id > 0) {
                $target_question_ids[$target_question_id] = $target_question_id;
            }
        }

        return array_values($target_question_ids);
    }

    /**
     * @return int[]
     */
    private static function propagate_bank_question_update(int $source_question_id, array $before_snapshot, array $after_snapshot): array
    {
        global $wpdb;

        if ($source_question_id <= 0 || empty($before_snapshot) || empty($after_snapshot)) {
            return [];
        }

        $affected_exam_ids = [];
        foreach (self::collect_descendant_question_ids_for_source($source_question_id, $before_snapshot) as $target_question_id) {
            $target_snapshot = self::get_question_sync_snapshot($target_question_id);
            if (empty($target_snapshot)) {
                continue;
            }

            $affected_exam_id = self::apply_source_snapshot_to_question(
                $target_question_id,
                $source_question_id,
                $after_snapshot,
                $target_snapshot
            );
            if ($affected_exam_id > 0) {
                $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
            }
        }

        return array_values($affected_exam_ids);
    }

    /**
     * @return array{scanned_sources:int,updated_questions:int,new_links:int,affected_exams:int,error?:string}
     */
    public static function run_question_source_backfill(): array
    {
        global $wpdb;

        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$question_table}", 0);
        if (!is_array($columns) || !in_array('source_question_id', $columns, true)) {
            return [
                'scanned_sources' => 0,
                'updated_questions' => 0,
                'new_links' => 0,
                'affected_exams' => 0,
                'error' => 'Kolom source_question_id belum tersedia. Muat ulang plugin atau jalankan upgrade terlebih dahulu.',
            ];
        }

        $bank_question_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT q.id
                 FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 WHERE e.title LIKE %s
                 ORDER BY q.id ASC",
                'Bank Soal - %'
            )
        );

        $claimed_question_ids = [];
        $updated_question_ids = [];
        $new_link_ids = [];
        $affected_exam_ids = [];
        $scanned_sources = 0;

        foreach ((array) $bank_question_ids as $source_question_id) {
            $source_question_id = (int) $source_question_id;
            if ($source_question_id <= 0) {
                continue;
            }

            $source_snapshot = self::get_question_sync_snapshot($source_question_id);
            if (empty($source_snapshot) || !self::is_bank_question_snapshot($source_snapshot)) {
                continue;
            }

            $scanned_sources++;
            foreach (self::collect_descendant_question_ids_for_source($source_question_id, $source_snapshot) as $target_question_id) {
                $target_question_id = (int) $target_question_id;
                if ($target_question_id <= 0 || isset($claimed_question_ids[$target_question_id])) {
                    continue;
                }

                $target_snapshot = self::get_question_sync_snapshot($target_question_id);
                if (empty($target_snapshot)) {
                    continue;
                }

                $affected_exam_id = self::apply_source_snapshot_to_question(
                    $target_question_id,
                    $source_question_id,
                    $source_snapshot,
                    $target_snapshot
                );
                if ($affected_exam_id <= 0) {
                    continue;
                }

                $claimed_question_ids[$target_question_id] = true;
                $updated_question_ids[$target_question_id] = $target_question_id;
                if ((int) ($target_snapshot['source_question_id'] ?? 0) !== $source_question_id) {
                    $new_link_ids[$target_question_id] = $target_question_id;
                }
                $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
            }
        }

        if (!empty($affected_exam_ids)) {
            CBT_Cache::invalidate_exams(array_values($affected_exam_ids));
        }

        return [
            'scanned_sources' => $scanned_sources,
            'updated_questions' => count($updated_question_ids),
            'new_links' => count($new_link_ids),
            'affected_exams' => count($affected_exam_ids),
        ];
    }

    /**
     * @return int[]
     */
    private static function find_legacy_descendant_question_ids(int $source_question_id, array $before_snapshot): array
    {
        global $wpdb;

        $source_exam_id = (int) ($before_snapshot['exam_id'] ?? 0);
        $subject_id = (int) ($before_snapshot['subject_id'] ?? 0);
        if ($source_question_id <= 0 || $source_exam_id <= 0 || $subject_id <= 0) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $candidate_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT q.id
                 FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 WHERE e.subject_id = %d
                   AND e.title NOT LIKE %s
                   AND q.exam_id <> %d
                   AND q.id <> %d
                   AND (q.source_question_id IS NULL OR q.source_question_id = 0)
                   AND q.question_type = %s
                   AND q.points = %f
                   AND COALESCE(q.correct_text, '') = %s
                   AND COALESCE(q.explanation, '') = %s",
                $subject_id,
                'Bank Soal - %',
                $source_exam_id,
                $source_question_id,
                (string) ($before_snapshot['question_type'] ?? ''),
                (float) ($before_snapshot['points'] ?? 0),
                (string) ($before_snapshot['correct_text'] ?? ''),
                (string) ($before_snapshot['explanation'] ?? '')
            )
        );

        $matched_ids = [];
        foreach ((array) $candidate_ids as $candidate_id) {
            $candidate_id = (int) $candidate_id;
            if ($candidate_id <= 0) {
                continue;
            }

            $candidate_snapshot = self::get_question_sync_snapshot($candidate_id);
            if (empty($candidate_snapshot)) {
                continue;
            }

            if (!self::question_snapshots_are_legacy_descendant_match($candidate_snapshot, $before_snapshot)) {
                continue;
            }

            $matched_ids[] = $candidate_id;
        }

        return $matched_ids;
    }

    private static function apply_source_snapshot_to_question(
        int $target_question_id,
        int $source_question_id,
        array $source_snapshot,
        array $target_snapshot = []
    ): int {
        global $wpdb;

        if ($target_question_id <= 0 || $source_question_id <= 0 || empty($source_snapshot)) {
            return 0;
        }

        if (empty($target_snapshot)) {
            $target_snapshot = self::get_question_sync_snapshot($target_question_id);
        }
        if (empty($target_snapshot)) {
            return 0;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $now = current_time('mysql');
        $question_type = (string) ($source_snapshot['question_type'] ?? 'multiple_choice');
        $correct_text = (string) ($source_snapshot['correct_text'] ?? '');
        $explanation = (string) ($source_snapshot['explanation'] ?? '');
        $old_question_type = (string) ($target_snapshot['question_type'] ?? '');

        $updated = $wpdb->update(
            $question_table,
            [
                'source_question_id' => $source_question_id,
                'question_text' => (string) ($source_snapshot['question_text'] ?? ''),
                'question_type' => $question_type,
                'points' => (float) ($source_snapshot['points'] ?? 0),
                'correct_text' => $correct_text !== '' ? $correct_text : null,
                'explanation' => $explanation !== '' ? $explanation : null,
                'updated_at' => $now,
            ],
            ['id' => $target_question_id],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s'],
            ['%d']
        );
        if ($updated === false) {
            return 0;
        }

        $option_id_map = self::sync_question_options_from_snapshot(
            $target_question_id,
            (array) ($source_snapshot['options'] ?? []),
            (array) ($target_snapshot['options'] ?? [])
        );

        self::save_question_type_detail(
            $target_question_id,
            $question_type,
            (string) ($source_snapshot['normalized_detail_text'] ?? '')
        );

        if ($old_question_type !== $question_type) {
            self::clear_question_answer_records($target_question_id, true);
            if (class_exists('CBT_Runtime')) {
                CBT_Runtime::remap_active_attempt_answers_for_question($target_question_id, [], true);
            }
        } elseif (self::question_type_uses_choice_options($question_type)) {
            self::remap_question_answer_option_ids($target_question_id, $option_id_map);
            if (class_exists('CBT_Runtime')) {
                CBT_Runtime::remap_active_attempt_answers_for_question($target_question_id, $option_id_map, false);
            }
        }

        return (int) ($target_snapshot['exam_id'] ?? 0);
    }

    /**
     * @param array<int,array<string,mixed>> $desired_options
     * @param array<int,array<string,mixed>> $existing_options
     * @return array<int,int>
     */
    private static function sync_question_options_from_snapshot(int $question_id, array $desired_options, array $existing_options = []): array
    {
        global $wpdb;

        $option_table = $wpdb->prefix . 'cbt_options';
        if ($question_id <= 0) {
            return [];
        }

        if (empty($existing_options)) {
            $existing_options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id = %d
                     ORDER BY id ASC",
                    $question_id
                ),
                ARRAY_A
            );
        }

        $normalized_existing = self::normalize_question_sync_options((array) $existing_options);
        $normalized_desired = self::normalize_question_sync_options($desired_options);

        $existing_ids_by_match_key = [];
        foreach ($normalized_existing as $existing_option) {
            $existing_id = (int) ($existing_option['id'] ?? 0);
            $match_key = (string) ($existing_option['match_key'] ?? '');
            if ($existing_id <= 0 || $match_key === '') {
                continue;
            }

            $existing_ids_by_match_key[$match_key] = $existing_id;
        }

        $new_ids_by_match_key = [];
        $used_existing_ids = [];
        $now = current_time('mysql');

        foreach ($normalized_desired as $desired_option) {
            $match_key = (string) ($desired_option['match_key'] ?? '');
            $existing_id = $match_key !== '' && isset($existing_ids_by_match_key[$match_key])
                ? (int) $existing_ids_by_match_key[$match_key]
                : 0;

            if ($existing_id > 0) {
                $wpdb->update(
                    $option_table,
                    [
                        'option_key' => (string) ($desired_option['option_key'] ?? ''),
                        'option_text' => (string) ($desired_option['option_text'] ?? ''),
                        'is_correct' => (int) ($desired_option['is_correct'] ?? 0),
                    ],
                    ['id' => $existing_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );
                $used_existing_ids[$existing_id] = true;
                $new_ids_by_match_key[$match_key] = $existing_id;
                continue;
            }

            $inserted = $wpdb->insert(
                $option_table,
                [
                    'question_id' => $question_id,
                    'option_key' => (string) ($desired_option['option_key'] ?? ''),
                    'option_text' => (string) ($desired_option['option_text'] ?? ''),
                    'is_correct' => (int) ($desired_option['is_correct'] ?? 0),
                    'created_at' => $now,
                ],
                ['%d', '%s', '%s', '%d', '%s']
            );
            if (!$inserted) {
                continue;
            }

            $new_option_id = (int) $wpdb->insert_id;
            if ($new_option_id > 0) {
                $used_existing_ids[$new_option_id] = true;
                $new_ids_by_match_key[$match_key] = $new_option_id;
            }
        }

        foreach ($normalized_existing as $existing_option) {
            $existing_id = (int) ($existing_option['id'] ?? 0);
            if ($existing_id <= 0 || isset($used_existing_ids[$existing_id])) {
                continue;
            }
            $wpdb->delete($option_table, ['id' => $existing_id], ['%d']);
        }

        $old_to_new = [];
        foreach ($existing_ids_by_match_key as $match_key => $old_option_id) {
            $new_option_id = (int) ($new_ids_by_match_key[$match_key] ?? 0);
            if ($old_option_id > 0 && $new_option_id > 0) {
                $old_to_new[$old_option_id] = $new_option_id;
            }
        }

        return $old_to_new;
    }

    private static function question_type_uses_choice_options(string $question_type): bool
    {
        return in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true);
    }

    /**
     * @param array<int,int> $option_id_map
     */
    private static function remap_question_answer_option_ids(int $question_id, array $option_id_map): void
    {
        global $wpdb;

        if ($question_id <= 0) {
            return;
        }

        $answer_table = $wpdb->prefix . 'cbt_answers';
        $answer_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, attempt_id, selected_option_ids
                 FROM {$answer_table}
                 WHERE question_id = %d
                   AND selected_option_ids IS NOT NULL
                   AND selected_option_ids <> ''",
                $question_id
            ),
            ARRAY_A
        );

        $affected_attempt_ids = [];
        foreach ((array) $answer_rows as $answer_row) {
            $answer_id = (int) ($answer_row['id'] ?? 0);
            if ($answer_id <= 0) {
                continue;
            }

            $existing_option_ids = json_decode((string) ($answer_row['selected_option_ids'] ?? ''), true);
            if (!is_array($existing_option_ids)) {
                $existing_option_ids = [];
            }
            $existing_option_ids = array_values(array_unique(array_filter(array_map('intval', $existing_option_ids), static function (int $option_id): bool {
                return $option_id > 0;
            })));
            sort($existing_option_ids);

            $remapped_option_ids = [];
            foreach ($existing_option_ids as $existing_option_id) {
                $existing_option_id = (int) $existing_option_id;
                $new_option_id = isset($option_id_map[$existing_option_id]) ? (int) $option_id_map[$existing_option_id] : 0;
                if ($new_option_id > 0) {
                    $remapped_option_ids[] = $new_option_id;
                }
            }

            $remapped_option_ids = array_values(array_unique($remapped_option_ids));
            sort($remapped_option_ids);
            if ($remapped_option_ids === $existing_option_ids) {
                continue;
            }
            $selected_option_ids = !empty($remapped_option_ids) ? wp_json_encode($remapped_option_ids) : null;

            $wpdb->update(
                $answer_table,
                [
                    'selected_option_ids' => $selected_option_ids,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $answer_id],
                ['%s', '%s'],
                ['%d']
            );
            $attempt_id = (int) ($answer_row['attempt_id'] ?? 0);
            if ($attempt_id > 0) {
                $affected_attempt_ids[$attempt_id] = $attempt_id;
            }
        }

        if (!empty($affected_attempt_ids)) {
            CBT_Cache::invalidate_attempts(array_values($affected_attempt_ids));
        }
    }

    private static function clear_question_answer_records(int $question_id, bool $clear_answer_text): void
    {
        global $wpdb;

        if ($question_id <= 0) {
            return;
        }

        $answer_table = $wpdb->prefix . 'cbt_answers';
        $affected_attempt_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT attempt_id
                 FROM {$answer_table}
                 WHERE question_id = %d",
                $question_id
            )
        );
        $fields = [
            'selected_option_ids' => null,
            'is_correct' => null,
            'score_awarded' => 0,
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%d', '%f', '%s'];

        if ($clear_answer_text) {
            $fields['answer_text'] = null;
            array_splice($formats, 1, 0, ['%s']);
        }

        $wpdb->update(
            $answer_table,
            $fields,
            ['question_id' => $question_id],
            $formats,
            ['%d']
        );

        if (!empty($affected_attempt_ids)) {
            CBT_Cache::invalidate_attempts(array_map('intval', (array) $affected_attempt_ids));
        }
    }

    private static function redirect_exam_with_error(string $message, int $edit_id = 0): void
    {
        $args = [
            'page' => 'cbt-exams',
            'cbt_err' => $message,
        ];
        if ($edit_id > 0) {
            $args['edit'] = $edit_id;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function redirect_cache_page(?string $message = null, ?string $error = null): void
    {
        $args = ['page' => 'cbt-cache'];
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function redirect_maintenance_page(?string $message = null, ?string $error = null): void
    {
        $args = ['page' => 'cbt-maintenance'];
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_save_subject(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_subject');

        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $code_raw = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        if ($name === '') {
            wp_safe_redirect(admin_url('admin.php?page=cbt-subjects&cbt_msg=' . rawurlencode('Nama mapel wajib diisi.')));
            exit;
        }

        $code = strtoupper(sanitize_key($code_raw));
        if (strlen($code) > 30) {
            $code = substr($code, 0, 30);
        }

        $data = [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        ];

        if ($id > 0) {
            $wpdb->update(
                $table,
                $data,
                ['id' => $id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            $msg = 'Subject updated';
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert(
                $table,
                $data,
                ['%s', '%s', '%s', '%s', '%s']
            );
            $msg = 'Subject created';
        }

        CBT_Cache::invalidate_catalog();

        wp_safe_redirect(admin_url('admin.php?page=cbt-subjects&cbt_msg=' . rawurlencode($msg)));
        exit;
    }

    public static function handle_delete_subject(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('cbt_delete_subject_' . $id);
        $subject_per_page = self::normalize_standard_list_per_page(
            isset($_GET['cbt_subject_per_page']) ? absint(wp_unslash($_GET['cbt_subject_per_page'])) : 20
        );
        $subject_paged = isset($_GET['cbt_subject_paged']) ? max(1, absint(wp_unslash($_GET['cbt_subject_paged']))) : 1;

        if ($id > 0) {
            global $wpdb;
            $exam_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cbt_exams WHERE subject_id = %d",
                $id
            ));

            if ($exam_count > 0) {
                wp_safe_redirect(add_query_arg([
                    'page' => 'cbt-subjects',
                    'cbt_subject_per_page' => $subject_per_page,
                    'cbt_subject_paged' => $subject_paged,
                    'cbt_msg' => 'Subject masih dipakai oleh ujian dan tidak bisa dihapus.',
                ], admin_url('admin.php')));
                exit;
            }

            $wpdb->delete($wpdb->prefix . 'cbt_subjects', ['id' => $id], ['%d']);
        }

        CBT_Cache::invalidate_catalog();

        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-subjects',
            'cbt_subject_per_page' => $subject_per_page,
            'cbt_subject_paged' => $subject_paged,
            'cbt_msg' => 'Subject deleted',
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_bulk_delete_subjects(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_delete_subjects');

        global $wpdb;
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $bulk_mode = isset($_POST['bulk_mode']) ? sanitize_text_field(wp_unslash($_POST['bulk_mode'])) : 'selected';
        $subject_per_page = self::normalize_standard_list_per_page(
            isset($_POST['cbt_subject_per_page']) ? absint(wp_unslash($_POST['cbt_subject_per_page'])) : 20
        );
        $subject_paged = isset($_POST['cbt_subject_paged']) ? max(1, absint(wp_unslash($_POST['cbt_subject_paged']))) : 1;
        $redirect_args = [
            'page' => 'cbt-subjects',
            'cbt_subject_per_page' => $subject_per_page,
            'cbt_subject_paged' => $subject_paged,
        ];

        if ($bulk_mode === 'all') {
            $target_ids = array_map('intval', (array) $wpdb->get_col("SELECT id FROM {$subject_table}"));
        } else {
            $raw_subject_ids = isset($_POST['subject_ids']) && is_array($_POST['subject_ids']) ? wp_unslash($_POST['subject_ids']) : [];
            $target_ids = array_map('absint', $raw_subject_ids);
        }

        $target_ids = array_values(array_unique(array_filter($target_ids)));
        if (empty($target_ids)) {
            $redirect_args['cbt_err'] = 'Pilih minimal satu subject.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $deleted_count = 0;
        $blocked_count = 0;

        foreach ($target_ids as $subject_id) {
            $exam_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$exam_table} WHERE subject_id = %d",
                $subject_id
            ));

            if ($exam_count > 0) {
                $blocked_count++;
                continue;
            }

            $deleted = $wpdb->delete($subject_table, ['id' => $subject_id], ['%d']);
            if ($deleted) {
                $deleted_count++;
            }
        }

        $messages = [];
        if ($deleted_count > 0) {
            $messages[] = sprintf('Deleted: %d', $deleted_count);
        }
        if ($blocked_count > 0) {
            $messages[] = sprintf('Skipped (dipakai exam): %d', $blocked_count);
        }

        if (empty($messages)) {
            $redirect_args['cbt_err'] = 'Tidak ada subject yang berhasil dihapus.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        if ($deleted_count > 0) {
            CBT_Cache::invalidate_catalog();
        }

        $redirect_args['cbt_msg'] = implode(' | ', $messages);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_import_subjects(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_subject_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_subject_import_token'])) : '';
        if ($token !== '') {
            self::continue_subject_import($token);
        }

        check_admin_referer('cbt_import_subjects');

        if (!isset($_FILES['subject_file']) || !is_array($_FILES['subject_file'])) {
            self::redirect_subject_import_with_error('File tidak ditemukan.');
        }

        $file = $_FILES['subject_file'];
        $tmp_path = $file['tmp_name'] ?? '';
        $original_name = $file['name'] ?? '';
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error_code !== UPLOAD_ERR_OK || !$tmp_path) {
            self::redirect_subject_import_with_error('Upload file gagal.');
        }

        $extension = strtolower((string) pathinfo((string) $original_name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            self::redirect_subject_import_with_error('Format file harus CSV atau XLSX.');
        }

        $rows = ($extension === 'xlsx')
            ? self::parse_subject_xlsx($tmp_path)
            : self::parse_subject_csv($tmp_path);

        if (is_wp_error($rows)) {
            self::redirect_subject_import_with_error($rows->get_error_message());
        }
        if (!is_array($rows) || empty($rows)) {
            self::redirect_subject_import_with_error('Tidak ada data subject yang bisa diproses.');
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $state = [
            'total' => count($rows),
            'offset' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'user_id' => get_current_user_id(),
            'started_at' => time(),
        ];
        $rows_saved = set_transient(self::get_subject_import_rows_key($token), array_values($rows), 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient(self::get_subject_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            self::clear_subject_import_transients($token);
            self::redirect_subject_import_with_error('Gagal menyiapkan sesi import subject.');
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-subjects',
            'cbt_subject_import_token' => $token,
        ], admin_url('admin.php')));
        exit;
    }

    private static function continue_subject_import(string $token): void
    {
        $state = self::get_subject_import_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_subject_import_transients($token);
            self::redirect_subject_import_with_error('Sesi import subject berakhir. Silakan upload ulang file.');
        }

        $rows = get_transient(self::get_subject_import_rows_key($token));
        if (!is_array($rows) || empty($rows)) {
            self::clear_subject_import_transients($token);
            self::redirect_subject_import_with_error('Data batch import subject tidak ditemukan. Silakan upload ulang file.');
        }

        $rows = array_values($rows);
        $total = isset($state['total']) ? (int) $state['total'] : count($rows);
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
        $created = isset($state['created']) ? (int) $state['created'] : 0;
        $updated = isset($state['updated']) ? (int) $state['updated'] : 0;
        $failed = isset($state['failed']) ? (int) $state['failed'] : 0;
        if ($total <= 0 || empty($rows)) {
            self::clear_subject_import_transients($token);
            self::redirect_subject_import_with_error('Data import subject kosong.');
        }

        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset > $total) {
            $offset = $total;
        }

        $batch_size = self::get_subject_import_batch_size();
        $max_batch_seconds = self::get_subject_import_max_batch_seconds();
        $target_end = min($offset + $batch_size, $total);
        $end = $offset;
        $batch_started_at = microtime(true);

        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? (array) $rows[$index] : [];

            try {
                $result = self::upsert_subject_from_row($row);
            } catch (Throwable $exception) {
                $result = 'failed';
            }

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $failed++;
            }

            $end = $index + 1;
            if (($end - $offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }

        $state['offset'] = max($offset, $end);
        $state['created'] = $created;
        $state['updated'] = $updated;
        $state['failed'] = $failed;

        if ($state['offset'] < $total) {
            $state_saved = set_transient(self::get_subject_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                self::clear_subject_import_transients($token);
                self::redirect_subject_import_with_error('Gagal menyimpan progres import subject.');
            }
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-subjects',
                'cbt_subject_import_token' => $token,
            ], admin_url('admin.php')));
            exit;
        }

        self::clear_subject_import_transients($token);
        if ($created > 0 || $updated > 0) {
            CBT_Cache::invalidate_catalog();
        }
        $msg = sprintf(
            'Import subjects selesai. Total: %d, Created: %d, Updated: %d, Failed: %d',
            $total,
            $created,
            $updated,
            $failed
        );
        wp_safe_redirect(admin_url('admin.php?page=cbt-subjects&cbt_msg=' . rawurlencode($msg)));
        exit;
    }

    public static function handle_download_subject_template(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_subject_template');

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-subject-template.csv"');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            wp_die('Gagal membuat template CSV.');
        }

        fputcsv($out, ['name', 'code', 'description']);
        fputcsv($out, ['Matematika', 'MAT', 'Mata pelajaran Matematika']);
        fputcsv($out, ['Bahasa Indonesia', 'IND', 'Mata pelajaran Bahasa Indonesia']);
        fclose($out);
        exit;
    }

    public static function handle_download_subject_template_xlsx(): void
    {
        if (!self::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_subject_template_xlsx');

        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            wp_die('Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            [
                ['name', 'code', 'description'],
                ['Matematika', 'MAT', 'Mata pelajaran Matematika'],
                ['Bahasa Indonesia', 'IND', 'Mata pelajaran Bahasa Indonesia'],
            ],
            null,
            'A1'
        );

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cbt-subject-template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private static function parse_subject_csv(string $tmp_path)
    {
        $handle = fopen($tmp_path, 'rb');
        if ($handle === false) {
            return new WP_Error('csv_open_failed', 'Gagal membuka file CSV.');
        }

        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $header = self::normalize_subject_import_header($header);
        $header_check = self::validate_subject_import_header($header);
        if (is_wp_error($header_check)) {
            fclose($handle);
            return $header_check;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        if (empty($rows)) {
            return new WP_Error('csv_no_data', 'Tidak ada data subject di CSV.');
        }

        return $rows;
    }

    private static function parse_subject_xlsx(string $tmp_path)
    {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return new WP_Error(
                'xlsx_library_missing',
                'Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.'
            );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $raw_rows = $sheet->toArray('', false, false, false);
        } catch (Throwable $exception) {
            return new WP_Error('xlsx_read_failed', 'Gagal membaca file XLSX.');
        }

        if (!is_array($raw_rows) || empty($raw_rows)) {
            return new WP_Error('xlsx_empty', 'File XLSX kosong.');
        }

        $header = array_shift($raw_rows);
        if (!is_array($header)) {
            return new WP_Error('xlsx_header_invalid', 'Header XLSX tidak valid.');
        }

        $header = self::normalize_subject_import_header($header);
        $header_check = self::validate_subject_import_header($header);
        if (is_wp_error($header_check)) {
            return $header_check;
        }

        $rows = [];
        foreach ($raw_rows as $data) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            return new WP_Error('xlsx_no_data', 'Tidak ada data subject di XLSX.');
        }

        return $rows;
    }

    private static function normalize_subject_import_header(array $header): array
    {
        return array_map(static function ($item) {
            $clean = trim((string) $item);
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);
            return strtolower($clean);
        }, $header);
    }

    private static function validate_subject_import_header(array $header)
    {
        if (!in_array('name', $header, true)) {
            return new WP_Error('import_header_invalid', 'Header file tidak valid. Kolom name wajib ada.');
        }

        return true;
    }

    private static function upsert_subject_from_row(array $row): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $name = sanitize_text_field($row['name'] ?? '');
        $code_raw = sanitize_text_field($row['code'] ?? '');
        $description = sanitize_textarea_field($row['description'] ?? '');

        if ($name === '') {
            return 'failed';
        }

        $code = strtoupper(sanitize_key($code_raw));
        if (strlen($code) > 30) {
            $code = substr($code, 0, 30);
        }

        $existing = null;
        if ($code !== '') {
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$table} WHERE code = %s ORDER BY id ASC LIMIT 1", $code),
                ARRAY_A
            );
        }

        if (!$existing) {
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$table} WHERE name = %s ORDER BY id ASC LIMIT 1", $name),
                ARRAY_A
            );
        }

        $data = [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing && isset($existing['id'])) {
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            return $updated === false ? 'failed' : 'updated';
        }

        $data['created_at'] = current_time('mysql');
        $inserted = $wpdb->insert(
            $table,
            $data,
            ['%s', '%s', '%s', '%s', '%s']
        );

        return $inserted ? 'created' : 'failed';
    }

    private static function get_subject_import_state_key(string $token): string
    {
        return 'cbt_subject_import_' . $token;
    }

    private static function get_subject_import_rows_key(string $token): string
    {
        return 'cbt_subject_import_rows_' . $token;
    }

    private static function clear_subject_import_transients(string $token): void
    {
        delete_transient(self::get_subject_import_state_key($token));
        delete_transient(self::get_subject_import_rows_key($token));
    }

    private static function get_subject_import_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_subject_import_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function get_subject_import_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_subject_import_batch_size', 250);
        if ($batch_size < 25) {
            return 25;
        }
        if ($batch_size > 1000) {
            return 1000;
        }

        return $batch_size;
    }

    private static function get_subject_import_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_subject_import_batch_max_seconds', 10.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function get_reset_progress_state_key(string $token): string
    {
        return 'cbt_reset_progress_' . $token;
    }

    private static function get_reset_progress_users_key(string $token): string
    {
        return 'cbt_reset_progress_users_' . $token;
    }

    private static function clear_reset_progress_transients(string $token): void
    {
        delete_transient(self::get_reset_progress_state_key($token));
        delete_transient(self::get_reset_progress_users_key($token));
    }

    private static function get_reset_progress_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_reset_progress_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function get_reset_progress_table_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_reset_progress_table_batch_size', 2);
        if ($batch_size < 1) {
            return 1;
        }
        if ($batch_size > 10) {
            return 10;
        }

        return $batch_size;
    }

    private static function get_reset_progress_user_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_reset_progress_user_batch_size', 140);
        if ($batch_size < 20) {
            return 20;
        }
        if ($batch_size > 500) {
            return 500;
        }

        return $batch_size;
    }

    private static function get_reset_progress_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_reset_progress_batch_max_seconds', 8.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function redirect_subject_import_with_error(string $message): void
    {
        wp_safe_redirect(admin_url('admin.php?page=cbt-subjects&cbt_err=' . rawurlencode($message)));
        exit;
    }

    public static function handle_save_question(): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_question');

        global $wpdb;

        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $exam_id = isset($_POST['exam_id']) ? absint($_POST['exam_id']) : 0;
        $subject_id = isset($_POST['subject_id']) ? absint($_POST['subject_id']) : 0;
        $question_text = isset($_POST['question_text']) ? wp_kses_post(wp_unslash($_POST['question_text'])) : '';
        $question_type = isset($_POST['question_type']) ? sanitize_text_field(wp_unslash($_POST['question_type'])) : 'multiple_choice';
        $points = isset($_POST['points']) ? (float) wp_unslash($_POST['points']) : 1.0;
        $correct_text_raw = isset($_POST['correct_text']) ? (string) wp_unslash($_POST['correct_text']) : '';
        $correct_text = sanitize_text_field($correct_text_raw);
        $essay_answer = isset($_POST['essay_answer']) ? wp_kses_post(wp_unslash($_POST['essay_answer'])) : '';
        $options_raw = isset($_POST['options']) ? wp_unslash($_POST['options']) : '';
        $return_page = self::normalize_question_page_slug(isset($_POST['return_page']) ? wp_unslash($_POST['return_page']) : 'cbt-question-bank');
        $forced_question_type = self::forced_question_type_for_page($return_page);
        $previous_exam_id = 0;
        $previous_question_snapshot = [];

        $allowed_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'];
        if (!in_array($question_type, $allowed_types, true)) {
            $question_type = 'multiple_choice';
        }
        if ($forced_question_type !== '') {
            $question_type = $forced_question_type;
        }

        $normalized_detail_text = '';
        if ($question_type === 'true_false') {
            $normalized_detail_text = self::normalize_true_false_value($correct_text) === 1 ? 'true' : 'false';
        } elseif ($question_type === 'true_false_matrix') {
            $normalized_detail_text = self::normalize_true_false_matrix_payload($correct_text_raw);
        } elseif ($question_type === 'short_answer') {
            $normalized_detail_text = self::normalize_short_answer_payload($correct_text_raw);
        } elseif ($question_type === 'essay') {
            $normalized_detail_text = trim($essay_answer);
        }

        if ($exam_id <= 0 && $subject_id > 0) {
            $exam_id = self::ensure_subject_question_bank_exam($subject_id, $is_admin_scope, $current_user_id);
        }

        if ($exam_id <= 0 || trim($question_text) === '' || $subject_id <= 0) {
            self::redirect_question_import_with_error('Subject dan pertanyaan wajib diisi.', $return_page);
        }

        if ($question_type === 'essay' && $normalized_detail_text === '') {
            self::redirect_question_import_with_error('Jawaban/acuan untuk soal essay wajib diisi.', $return_page);
        }

        if ($question_type === 'short_answer' && $normalized_detail_text === '') {
            self::redirect_question_import_with_error('Short Answer minimal harus punya 1 jawaban valid.', $return_page);
        }

        if ($question_type === 'true_false_matrix') {
            $matrix_rows = self::normalize_true_false_matrix_config($normalized_detail_text);
            if (count($matrix_rows) < 2) {
                self::redirect_question_import_with_error('True/False Matrix minimal harus punya 2 pernyataan.', $return_page);
            }
        }

        if (!$is_admin_scope) {
            $owned_exam = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$exam_table} WHERE id = %d AND created_by = %d",
                $exam_id,
                $current_user_id
            ));
            if ($owned_exam === 0) {
                wp_die('Unauthorized exam for question.');
            }
        }

        $data = [
            'exam_id' => $exam_id,
            'question_text' => $question_text,
            'question_type' => $question_type,
            'points' => $points,
            // Keep legacy field for backward compatibility; source of truth is per-type detail table.
            'correct_text' => $normalized_detail_text !== '' ? $normalized_detail_text : null,
            'updated_at' => current_time('mysql'),
        ];

        if ($id > 0) {
            $previous_question_snapshot = self::get_question_sync_snapshot($id);
            $previous_exam_id = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT exam_id FROM {$question_table} WHERE id = %d", $id)
            );
            if (!$is_admin_scope) {
                $owned_question = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} e ON e.id = q.exam_id
                     WHERE q.id = %d AND e.created_by = %d",
                    $id,
                    $current_user_id
                ));
                if ($owned_question === 0) {
                    wp_die('Unauthorized question update.');
                }
            }

            $wpdb->update(
                $question_table,
                $data,
                ['id' => $id],
                ['%d', '%s', '%s', '%f', '%s', '%s'],
                ['%d']
            );
            $question_id = $id;
        } else {
            $data['created_at'] = current_time('mysql');

            $wpdb->insert(
                $question_table,
                $data,
                ['%d', '%s', '%s', '%f', '%s', '%s', '%s']
            );
            $question_id = (int) $wpdb->insert_id;
        }

        if ($question_id > 0) {
            $wpdb->delete($option_table, ['question_id' => $question_id], ['%d']);

            $options_to_insert = self::parse_options($options_raw);

            if ($question_type === 'multiple_choice') {
                if (count($options_to_insert) < 2) {
                    self::redirect_question_import_with_error('Multiple Choice minimal harus punya 2 pilihan.', $return_page);
                }
                if (count($options_to_insert) > 5) {
                    self::redirect_question_import_with_error('Multiple Choice maksimal 5 pilihan.', $return_page);
                }

                $correct_count = 0;
                foreach ($options_to_insert as $opt) {
                    if ((int) ($opt['is_correct'] ?? 0) === 1) {
                        $correct_count++;
                    }
                }
                if ($correct_count !== 1) {
                    self::redirect_question_import_with_error('Multiple Choice harus memiliki tepat 1 jawaban benar.', $return_page);
                }
            }

            if ($question_type === 'multiple_answer') {
                if (count($options_to_insert) < 2) {
                    self::redirect_question_import_with_error('Multiple Answer minimal harus punya 2 pilihan.', $return_page);
                }
                if (count($options_to_insert) > 12) {
                    self::redirect_question_import_with_error('Multiple Answer maksimal 12 pilihan.', $return_page);
                }

                $correct_count = 0;
                foreach ($options_to_insert as $opt) {
                    if ((int) ($opt['is_correct'] ?? 0) === 1) {
                        $correct_count++;
                    }
                }
                if ($correct_count < 1) {
                    self::redirect_question_import_with_error('Multiple Answer harus memiliki minimal 1 jawaban benar.', $return_page);
                }
            }

            if ($question_type === 'true_false' && empty($options_to_insert)) {
                $true_is_correct = self::normalize_true_false_value($normalized_detail_text) === 1 ? 1 : 0;
                $options_to_insert = [
                    ['option_text' => 'True', 'is_correct' => $true_is_correct],
                    ['option_text' => 'False', 'is_correct' => $true_is_correct ? 0 : 1],
                ];
            }

            foreach ($options_to_insert as $idx => $opt) {
                $wpdb->insert(
                    $option_table,
                    [
                        'question_id' => $question_id,
                        'option_key' => chr(65 + $idx),
                        'option_text' => $opt['option_text'],
                        'is_correct' => (int) $opt['is_correct'],
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%s', '%s', '%d', '%s']
                );
            }

            self::save_question_type_detail($question_id, $question_type, $normalized_detail_text);
        }

        CBT_Cache::invalidate_catalog();
        $affected_exam_ids = [];
        if ($exam_id > 0) {
            $affected_exam_ids[$exam_id] = $exam_id;
        }
        if ($previous_exam_id > 0 && $previous_exam_id !== $exam_id) {
            $affected_exam_ids[$previous_exam_id] = $previous_exam_id;
        }

        if ($question_id > 0) {
            $current_question_snapshot = self::get_question_sync_snapshot($question_id);
            if (
                !empty($previous_question_snapshot) &&
                self::is_bank_question_snapshot($previous_question_snapshot) &&
                !empty($current_question_snapshot)
            ) {
                foreach (self::propagate_bank_question_update($question_id, $previous_question_snapshot, $current_question_snapshot) as $affected_exam_id) {
                    $affected_exam_id = (int) $affected_exam_id;
                    if ($affected_exam_id > 0) {
                        $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
                    }
                }
            }
        }

        foreach ($affected_exam_ids as $affected_exam_id) {
            CBT_Cache::invalidate_exam((int) $affected_exam_id);
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => $return_page,
                'cbt_msg' => 'Question saved',
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handle_delete_question(): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('cbt_delete_question_' . $id);
        $return_page = self::normalize_question_page_slug(isset($_GET['return_page']) ? wp_unslash($_GET['return_page']) : 'cbt-question-bank');
        $filter_exam_id = isset($_GET['filter_exam_id']) ? absint($_GET['filter_exam_id']) : 0;
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : '';
        $question_per_page = self::normalize_standard_list_per_page(
            isset($_GET['question_per_page']) ? absint(wp_unslash($_GET['question_per_page'])) : 20
        );
        $question_paged = isset($_GET['question_paged']) ? max(1, absint(wp_unslash($_GET['question_paged']))) : 1;
        $allowed_filter_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'];
        if (!in_array($filter_type, $allowed_filter_types, true)) {
            $filter_type = '';
        }

        if ($id > 0) {
            global $wpdb;
            $affected_exam_id = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT exam_id FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $id)
            );
            if (!self::is_admin_scope()) {
                $owned_question = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->prefix}cbt_questions q
                     INNER JOIN {$wpdb->prefix}cbt_exams e ON e.id = q.exam_id
                     WHERE q.id = %d AND e.created_by = %d",
                    $id,
                    get_current_user_id()
                ));
                if ($owned_question === 0) {
                    wp_die('Unauthorized question delete.');
                }
            }
            $wpdb->delete($wpdb->prefix . 'cbt_questions', ['id' => $id], ['%d']);
            if ($affected_exam_id > 0) {
                CBT_Cache::invalidate_catalog();
                CBT_Cache::invalidate_exam($affected_exam_id);
            }
        }

        $redirect_args = [
            'page' => $return_page,
            'cbt_msg' => 'Question deleted',
            'cbt_question_per_page' => $question_per_page,
            'cbt_question_paged' => $question_paged,
        ];
        if ($filter_exam_id > 0) {
            $redirect_args['filter_exam_id'] = $filter_exam_id;
        }
        if ($filter_type !== '') {
            $redirect_args['filter_type'] = $filter_type;
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_bulk_delete_questions(): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_question_delete_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_question_delete_token'])) : '';
        if ($token !== '') {
            self::continue_bulk_delete_questions($token);
        }

        check_admin_referer('cbt_bulk_delete_questions');
        $return_page = self::normalize_question_page_slug(isset($_POST['return_page']) ? wp_unslash($_POST['return_page']) : 'cbt-question-bank');
        $filter_exam_id = isset($_POST['redirect_filter_exam_id']) ? absint($_POST['redirect_filter_exam_id']) : 0;
        $filter_type = isset($_POST['redirect_filter_type']) ? sanitize_text_field(wp_unslash($_POST['redirect_filter_type'])) : '';
        $question_per_page = self::normalize_standard_list_per_page(
            isset($_POST['redirect_question_per_page']) ? absint(wp_unslash($_POST['redirect_question_per_page'])) : 20
        );
        $question_paged = isset($_POST['redirect_question_paged']) ? max(1, absint(wp_unslash($_POST['redirect_question_paged']))) : 1;
        $allowed_filter_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'];
        if (!in_array($filter_type, $allowed_filter_types, true)) {
            $filter_type = '';
        }

        $raw_question_ids = isset($_POST['question_ids']) && is_array($_POST['question_ids']) ? wp_unslash($_POST['question_ids']) : [];
        $question_ids = array_values(array_unique(array_filter(array_map('absint', $raw_question_ids))));

        $redirect_args = [
            'page' => $return_page,
            'cbt_question_per_page' => $question_per_page,
            'cbt_question_paged' => $question_paged,
        ];
        if ($filter_exam_id > 0) {
            $redirect_args['filter_exam_id'] = $filter_exam_id;
        }
        if ($filter_type !== '') {
            $redirect_args['filter_type'] = $filter_type;
        }

        if (empty($question_ids)) {
            $redirect_args['cbt_err'] = 'Pilih minimal satu soal untuk dihapus.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        global $wpdb;
        $target_ids = $question_ids;
        if (!self::is_admin_scope()) {
            $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
            $query_params = array_merge($question_ids, [get_current_user_id()]);
            $target_ids = array_map(
                'intval',
                (array) $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT q.id
                         FROM {$wpdb->prefix}cbt_questions q
                         INNER JOIN {$wpdb->prefix}cbt_exams e ON e.id = q.exam_id
                         WHERE q.id IN ({$placeholders}) AND e.created_by = %d",
                        ...$query_params
                    )
                )
            );
        }

        if (empty($target_ids)) {
            $redirect_args['cbt_err'] = 'Tidak ada soal yang bisa dihapus.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $affected_exam_ids = [];
        $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
        $exam_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT exam_id FROM {$wpdb->prefix}cbt_questions WHERE id IN ({$placeholders})",
                ...$target_ids
            ),
            ARRAY_A
        );
        foreach ((array) $exam_rows as $exam_row) {
            $exam_id = (int) ($exam_row['exam_id'] ?? 0);
            if ($exam_id > 0) {
                $affected_exam_ids[$exam_id] = $exam_id;
            }
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $state = [
            'total' => count($target_ids),
            'offset' => 0,
            'deleted' => 0,
            'failed' => 0,
            'user_id' => get_current_user_id(),
            'started_at' => time(),
            'return_page' => $return_page,
            'filter_exam_id' => $filter_exam_id,
            'filter_type' => $filter_type,
            'question_per_page' => $question_per_page,
            'question_paged' => $question_paged,
            'affected_exam_ids' => array_values($affected_exam_ids),
        ];
        $rows_saved = set_transient(self::get_question_delete_rows_key($token), array_values($target_ids), 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient(self::get_question_delete_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            self::clear_question_delete_transients($token);
            $redirect_args['cbt_err'] = 'Gagal menyiapkan sesi hapus soal. Coba lagi.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $redirect_args['cbt_question_delete_token'] = $token;
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    private static function continue_bulk_delete_questions(string $token): void
    {
        $state = self::get_question_delete_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_question_delete_transients($token);
            self::redirect_question_import_with_error('Sesi hapus soal berakhir. Silakan pilih ulang soal yang ingin dihapus.');
        }

        $return_page = self::normalize_question_page_slug((string) ($state['return_page'] ?? 'cbt-question-bank'));
        $filter_exam_id = isset($state['filter_exam_id']) ? absint($state['filter_exam_id']) : 0;
        $filter_type = isset($state['filter_type']) ? sanitize_text_field((string) $state['filter_type']) : '';
        $allowed_filter_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'];
        if (!in_array($filter_type, $allowed_filter_types, true)) {
            $filter_type = '';
        }
        $question_per_page = self::normalize_standard_list_per_page(isset($state['question_per_page']) ? (int) $state['question_per_page'] : 20);
        $question_paged = isset($state['question_paged']) ? max(1, (int) $state['question_paged']) : 1;
        $redirect_args = [
            'page' => $return_page,
            'cbt_question_per_page' => $question_per_page,
            'cbt_question_paged' => $question_paged,
        ];
        if ($filter_exam_id > 0) {
            $redirect_args['filter_exam_id'] = $filter_exam_id;
        }
        if ($filter_type !== '') {
            $redirect_args['filter_type'] = $filter_type;
        }

        $target_ids = get_transient(self::get_question_delete_rows_key($token));
        if (!is_array($target_ids) || empty($target_ids)) {
            self::clear_question_delete_transients($token);
            $redirect_args['cbt_err'] = 'Data batch hapus soal tidak ditemukan. Silakan pilih ulang soal.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $target_ids = array_values(array_map('intval', $target_ids));
        $total = isset($state['total']) ? (int) $state['total'] : count($target_ids);
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
        $deleted = isset($state['deleted']) ? (int) $state['deleted'] : 0;
        $failed = isset($state['failed']) ? (int) $state['failed'] : 0;
        if ($total <= 0 || empty($target_ids)) {
            self::clear_question_delete_transients($token);
            $redirect_args['cbt_err'] = 'Data hapus soal kosong.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }
        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset > $total) {
            $offset = $total;
        }

        $affected_exam_ids = [];
        if (isset($state['affected_exam_ids']) && is_array($state['affected_exam_ids'])) {
            foreach ((array) $state['affected_exam_ids'] as $affected_exam_id) {
                $affected_exam_id = (int) $affected_exam_id;
                if ($affected_exam_id > 0) {
                    $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
                }
            }
        }

        $batch_size = self::get_question_delete_batch_size();
        $max_batch_seconds = self::get_question_delete_max_batch_seconds();
        $target_end = min($offset + $batch_size, $total);
        $end = $offset;
        $batch_started_at = microtime(true);

        global $wpdb;
        for ($index = $offset; $index < $target_end; $index++) {
            $question_id = isset($target_ids[$index]) ? (int) $target_ids[$index] : 0;
            if ($question_id <= 0) {
                $failed++;
                $end = $index + 1;
                continue;
            }

            $deleted_rows = $wpdb->delete($wpdb->prefix . 'cbt_questions', ['id' => $question_id], ['%d']);
            if ($deleted_rows) {
                $deleted += (int) $deleted_rows;
            } else {
                $failed++;
            }

            $end = $index + 1;
            if (($end - $offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }

        $state['offset'] = max($offset, $end);
        $state['deleted'] = $deleted;
        $state['failed'] = $failed;
        $state['affected_exam_ids'] = array_values($affected_exam_ids);

        if ((int) $state['offset'] < $total) {
            $state_saved = set_transient(self::get_question_delete_state_key($token), $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                self::clear_question_delete_transients($token);
                $redirect_args['cbt_err'] = 'Gagal menyimpan progres hapus soal. Silakan ulangi proses.';
                wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                exit;
            }
            $redirect_args['cbt_question_delete_token'] = $token;
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        self::clear_question_delete_transients($token);
        if ($deleted > 0) {
            CBT_Cache::invalidate_catalog();
            CBT_Cache::invalidate_exams(array_values($affected_exam_ids));
            $redirect_args['cbt_msg'] = sprintf('Hapus soal selesai. Total: %d, Deleted: %d, Failed: %d', $total, $deleted, $failed);
        } else {
            $redirect_args['cbt_err'] = 'Tidak ada soal yang berhasil dihapus.';
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_import_questions(): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_question_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_question_import_token'])) : '';
        if ($token !== '') {
            self::continue_question_import($token);
        }

        check_admin_referer('cbt_import_questions');
        $return_page = self::normalize_question_page_slug(isset($_POST['return_page']) ? wp_unslash($_POST['return_page']) : 'cbt-question-bank');
        $forced_import_type = self::forced_question_type_for_page($return_page);

        if (!isset($_FILES['question_file']) || !is_array($_FILES['question_file'])) {
            self::redirect_question_import_with_error('File tidak ditemukan.', $return_page);
        }

        $file = $_FILES['question_file'];
        $tmp_path = $file['tmp_name'] ?? '';
        $original_name = $file['name'] ?? '';
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error_code !== UPLOAD_ERR_OK || !$tmp_path) {
            self::redirect_question_import_with_error('Upload file gagal.', $return_page);
        }

        $default_exam_id = 0;
        $import_subject_id = isset($_POST['import_subject_id']) ? absint($_POST['import_subject_id']) : 0;
        if ($import_subject_id <= 0) {
            self::redirect_question_import_with_error('Subject utama wajib dipilih.', $return_page);
        }

        global $wpdb;
        $is_admin_scope = self::is_admin_scope();
        $default_exam_id = self::ensure_subject_question_bank_exam($import_subject_id, $is_admin_scope, get_current_user_id());
        if ($default_exam_id <= 0) {
            self::redirect_question_import_with_error('Gagal menyiapkan exam penampung untuk subject terpilih.', $return_page);
        }

        $requested_import_type = isset($_POST['import_question_type']) ? sanitize_text_field(wp_unslash($_POST['import_question_type'])) : 'all';
        $allowed_import_types = ['all', 'multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'];
        if (!in_array($requested_import_type, $allowed_import_types, true)) {
            $requested_import_type = 'all';
        }
        if ($forced_import_type !== '') {
            $requested_import_type = $forced_import_type;
        }

        $extension = strtolower((string) pathinfo((string) $original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['csv', 'xlsx'];
        if (in_array($requested_import_type, ['all', 'multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'], true)) {
            $allowed_extensions[] = 'docx';
        }

        if (!in_array($extension, $allowed_extensions, true)) {
            if (in_array($requested_import_type, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'all'], true)) {
                self::redirect_question_import_with_error('Format file harus CSV, XLSX, atau DOCX.', $return_page);
            }
            self::redirect_question_import_with_error('Untuk tipe soal ini, format file harus CSV atau XLSX.', $return_page);
        }

        if ($extension === 'docx' && !in_array($requested_import_type, ['all', 'multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay'], true)) {
            self::redirect_question_import_with_error('Import DOCX hanya tersedia untuk tab Multiple Choice, Multiple Answer, True/False, TF Matrix, Short Answer, dan Essay.', $return_page);
        }

        $require_question_type_column = ($requested_import_type === 'all');
        if ($extension === 'xlsx') {
            $parsed = self::parse_question_xlsx($tmp_path, $require_question_type_column);
        } elseif ($extension === 'docx') {
            $parsed = self::parse_question_docx($tmp_path);
        } else {
            $parsed = self::parse_question_csv($tmp_path, $require_question_type_column);
        }

        if (is_wp_error($parsed)) {
            self::redirect_question_import_with_error($parsed->get_error_message(), $return_page);
        }

        if ($requested_import_type !== 'all') {
            foreach ($parsed as &$row) {
                if (is_array($row)) {
                    $row['question_type'] = $requested_import_type;
                }
            }
            unset($row);
        }

        if (!is_array($parsed) || empty($parsed)) {
            self::redirect_question_import_with_error('Tidak ada data soal yang bisa diproses.', $return_page);
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $current_user_id = get_current_user_id();
        $state = [
            'total' => count($parsed),
            'offset' => 0,
            'created' => 0,
            'failed' => 0,
            'user_id' => $current_user_id,
            'started_at' => time(),
            'return_page' => $return_page,
            'default_exam_id' => $default_exam_id,
            'is_admin_scope' => $is_admin_scope ? 1 : 0,
            'import_user_id' => $current_user_id,
            'affected_exam_ids' => [],
        ];

        $rows_saved = set_transient(self::get_question_import_rows_key($token), array_values($parsed), 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient(self::get_question_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            self::clear_question_import_transients($token);
            self::redirect_question_import_with_error('Gagal menyiapkan sesi import soal. Coba file lebih kecil atau ulangi import.', $return_page);
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => $return_page,
                'cbt_question_import_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    private static function continue_question_import(string $token): void
    {
        $state = self::get_question_import_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_question_import_transients($token);
            self::redirect_question_import_with_error('Sesi import soal berakhir. Silakan upload ulang file.');
        }

        $return_page = self::normalize_question_page_slug((string) ($state['return_page'] ?? 'cbt-question-bank'));
        $rows = get_transient(self::get_question_import_rows_key($token));
        if (!is_array($rows) || empty($rows)) {
            self::clear_question_import_transients($token);
            self::redirect_question_import_with_error('Data batch import soal tidak ditemukan. Silakan upload ulang file.', $return_page);
        }

        $rows = array_values($rows);
        $total = isset($state['total']) ? (int) $state['total'] : count($rows);
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
        $created = isset($state['created']) ? (int) $state['created'] : 0;
        $failed = isset($state['failed']) ? (int) $state['failed'] : 0;
        $default_exam_id = isset($state['default_exam_id']) ? (int) $state['default_exam_id'] : 0;
        $is_admin_scope = !empty($state['is_admin_scope']);
        $import_user_id = isset($state['import_user_id']) ? (int) $state['import_user_id'] : get_current_user_id();
        if ($import_user_id <= 0) {
            $import_user_id = get_current_user_id();
        }

        if ($total <= 0 || empty($rows)) {
            self::clear_question_import_transients($token);
            self::redirect_question_import_with_error('Data import soal kosong.', $return_page);
        }
        if ($default_exam_id <= 0) {
            self::clear_question_import_transients($token);
            self::redirect_question_import_with_error('Exam penampung import tidak valid.', $return_page);
        }
        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset > $total) {
            $offset = $total;
        }

        $affected_exam_ids = [];
        if (isset($state['affected_exam_ids']) && is_array($state['affected_exam_ids'])) {
            foreach ((array) $state['affected_exam_ids'] as $affected_exam_id) {
                $affected_exam_id = (int) $affected_exam_id;
                if ($affected_exam_id > 0) {
                    $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
                }
            }
        }

        $batch_size = self::get_question_import_batch_size();
        $max_batch_seconds = self::get_question_import_max_batch_seconds();
        $target_end = min($offset + $batch_size, $total);
        $end = $offset;
        $batch_started_at = microtime(true);
        $batch_affected_exam_ids = [];

        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? (array) $rows[$index] : [];

            try {
                $result = self::import_single_question_row($row, $default_exam_id, $is_admin_scope, $import_user_id, $batch_affected_exam_ids);
            } catch (Throwable $exception) {
                $result = 'failed';
            }

            if ($result === 'created') {
                $created++;
            } else {
                $failed++;
            }

            $end = $index + 1;
            if (($end - $offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }

        foreach ((array) $batch_affected_exam_ids as $affected_exam_id) {
            $affected_exam_id = (int) $affected_exam_id;
            if ($affected_exam_id > 0) {
                $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
            }
        }

        $state['offset'] = max($offset, $end);
        $state['created'] = $created;
        $state['failed'] = $failed;
        $state['affected_exam_ids'] = array_values($affected_exam_ids);

        if ((int) $state['offset'] < $total) {
            $state_saved = set_transient(self::get_question_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                self::clear_question_import_transients($token);
                self::redirect_question_import_with_error('Gagal menyimpan progres import soal.', $return_page);
            }
            wp_safe_redirect(add_query_arg(
                [
                    'page' => $return_page,
                    'cbt_question_import_token' => $token,
                ],
                admin_url('admin.php')
            ));
            exit;
        }

        self::clear_question_import_transients($token);

        if ($created > 0) {
            CBT_Cache::invalidate_catalog();
            CBT_Cache::invalidate_exams(array_values($affected_exam_ids));
        }

        $msg = sprintf('Import soal selesai. Total: %d, Created: %d, Failed: %d', $total, $created, $failed);
        wp_safe_redirect(add_query_arg(
            [
                'page' => $return_page,
                'cbt_msg' => $msg,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handle_download_question_template(): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_question_template');

        $rows = self::question_template_rows();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-question-import-template.csv"');
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            wp_die('Gagal membuat file template.');
        }

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    public static function handle_download_question_template_xlsx(): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_question_template_xlsx');

        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            wp_die('Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::question_template_rows(), null, 'A1');

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cbt-question-import-template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public static function handle_download_question_template_word(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word',
            'multiple_choice',
            'cbt-question-import-template-multiple-choice.docx'
        );
    }

    public static function handle_download_question_template_word_mc(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word_mc',
            'multiple_choice',
            'cbt-question-import-template-multiple-choice.docx'
        );
    }

    public static function handle_download_question_template_word_ma(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word_ma',
            'multiple_answer',
            'cbt-question-import-template-multiple-answer.docx'
        );
    }

    public static function handle_download_question_template_word_sa(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word_sa',
            'short_answer',
            'cbt-question-import-template-short-answer.docx'
        );
    }

    public static function handle_download_question_template_word_tf(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word_tf',
            'true_false',
            'cbt-question-import-template-true-false.docx'
        );
    }

    public static function handle_download_question_template_word_tfm(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word_tfm',
            'true_false_matrix',
            'cbt-question-import-template-true-false-matrix.docx'
        );
    }

    public static function handle_download_question_template_word_essay(): void
    {
        self::download_word_question_template(
            'cbt_download_question_template_word_essay',
            'essay',
            'cbt-question-import-template-essay.docx'
        );
    }

    private static function download_word_question_template(string $nonce_action, string $template_type, string $download_name): void
    {
        if (!current_user_can('cbt_manage_questions')) {
            wp_die('Unauthorized');
        }

        check_admin_referer($nonce_action);

        if (!class_exists('ZipArchive')) {
            wp_die('Extension zip belum aktif. Tidak bisa membuat template Word.');
        }

        $question_count = self::sanitize_word_template_question_count();
        $lines = self::build_word_template_lines($template_type, $question_count);

        self::output_question_template_word_file($lines, $download_name);
    }

    private static function sanitize_word_template_question_count(): int
    {
        $raw_count = isset($_GET['question_count'])
            ? (int) wp_unslash((string) $_GET['question_count'])
            : 10;

        $normalized_count = (int) floor($raw_count / 10) * 10;
        if ($normalized_count < 10) {
            $normalized_count = 10;
        }
        if ($normalized_count > 100) {
            $normalized_count = 100;
        }

        return $normalized_count;
    }

    private static function build_word_template_lines(string $template_type, int $question_count): array
    {
        $question_count = max(10, min(100, $question_count));
        $header_lines = [];
        $blocks = [];

        if ($template_type === 'multiple_answer') {
            $header_lines = [
                'Template Word ini untuk import Multiple Answer (format tabel).',
                'Setiap blok soal dipisahkan oleh ---',
                'Field wajib: SOAL, PILIHAN_1..PILIHAN_minimal_2, JAWABAN.',
                'JAWABAN diisi nomor pilihan (1-12) dan boleh lebih dari satu, contoh 2,4.',
                'POIN opsional, default 1.',
                'Boleh tempel gambar langsung di bawah baris SOAL. Gambar otomatis masuk ke soal.',
                'Jumlah blok template: ' . $question_count . ' soal.',
                '',
            ];

            for ($idx = 1; $idx <= $question_count; $idx++) {
                $block = [
                    'SOAL: [MA ' . $idx . '] Pilih semua pernyataan yang benar.',
                ];
                for ($opt_idx = 1; $opt_idx <= 12; $opt_idx++) {
                    $alpha = chr(ord('A') + $opt_idx - 1);
                    $block[] = 'PILIHAN_' . $opt_idx . ': Pernyataan ' . $alpha;
                }
                $block[] = 'JAWABAN: 1,3,5';
                $block[] = 'POIN: 1';
                $blocks[] = $block;
            }
        } elseif ($template_type === 'true_false') {
            $header_lines = [
                'Template Word ini untuk import True/False (format tabel).',
                'Setiap blok soal dipisahkan oleh ---',
                'Field wajib: JENIS_SOAL, SOAL, JAWABAN.',
                'JAWABAN diisi TRUE atau FALSE.',
                'POIN opsional, default 1.',
                'Boleh tempel gambar langsung di bawah baris SOAL. Gambar otomatis masuk ke soal.',
                'Jumlah blok template: ' . $question_count . ' soal.',
                '',
            ];

            for ($idx = 1; $idx <= $question_count; $idx++) {
                $answer = ($idx % 2 === 0) ? 'false' : 'true';
                $blocks[] = [
                    'JENIS_SOAL: true_false',
                    'SOAL: [TF ' . $idx . '] Tulis pernyataan benar/salah di sini.',
                    'JAWABAN: ' . $answer,
                    'POIN: 1',
                ];
            }
        } elseif ($template_type === 'true_false_matrix') {
            $header_lines = [
                'Template Word ini untuk import True/False Matrix (format tabel).',
                'Setiap blok soal dipisahkan oleh ---',
                'Field wajib: JENIS_SOAL, SOAL, minimal 2 pernyataan + kunci.',
                'Isi PERNYATAAN_1..PERNYATAAN_10 (maks 10 baris).',
                'Isi KUNCI_1..KUNCI_10 dengan TRUE/FALSE (atau BENAR/SALAH).',
                'POIN opsional, default 1.',
                'Boleh tempel gambar langsung di bawah baris SOAL. Gambar otomatis masuk ke soal.',
                'Jumlah blok template: ' . $question_count . ' soal.',
                '',
            ];

            for ($idx = 1; $idx <= $question_count; $idx++) {
                $blocks[] = [
                    'JENIS_SOAL: true_false_matrix',
                    'SOAL: [TFM ' . $idx . '] Tentukan Benar/Salah untuk setiap pernyataan berikut.',
                    'PERNYATAAN_1: Pernyataan A',
                    'KUNCI_1: true',
                    'PERNYATAAN_2: Pernyataan B',
                    'KUNCI_2: false',
                    'PERNYATAAN_3: Pernyataan C',
                    'KUNCI_3: true',
                    'PERNYATAAN_4: Pernyataan D',
                    'KUNCI_4: false',
                    'PERNYATAAN_5: Pernyataan E',
                    'KUNCI_5: true',
                    'POIN: 1',
                ];
            }
        } elseif ($template_type === 'short_answer') {
            $header_lines = [
                'Template Word ini untuk import Short Answer (format tabel, maks 8 jawaban valid).',
                'Setiap blok soal dipisahkan oleh ---',
                'Field wajib: JENIS_SOAL, SOAL, minimal 1 jawaban.',
                'Tandai titik isian di SOAL dengan [INPUT_1] sampai [INPUT_8].',
                'Format lama seperti [INPUT A] / [INPUT 1] tetap didukung.',
                'Isi jawaban bisa pakai JAWABAN_A sampai JAWABAN_H.',
                'Alternatif lama juga didukung: JAWABAN: isi_a||isi_b||isi_c',
                'POIN opsional, default 1.',
                'Boleh tempel gambar langsung di bawah baris SOAL. Gambar otomatis masuk ke soal.',
                'Jumlah blok template: ' . $question_count . ' soal.',
                '',
            ];

            for ($idx = 1; $idx <= $question_count; $idx++) {
                $blocks[] = [
                    'JENIS_SOAL: short_answer',
                    'SOAL: [SA ' . $idx . '] Lengkapi: [INPUT_1], [INPUT_2], [INPUT_3], [INPUT_4], [INPUT_5], [INPUT_6], [INPUT_7], [INPUT_8].',
                    'JAWABAN_A: jawaban-1',
                    'JAWABAN_B: jawaban-2',
                    'JAWABAN_C: jawaban-3',
                    'JAWABAN_D: jawaban-4',
                    'JAWABAN_E: jawaban-5',
                    'JAWABAN_F: jawaban-6',
                    'JAWABAN_G: jawaban-7',
                    'JAWABAN_H: jawaban-8',
                    'POIN: 1',
                ];
            }
        } elseif ($template_type === 'essay') {
            $header_lines = [
                'Template Word ini untuk import Essay (format tabel).',
                'Setiap blok soal dipisahkan oleh ---',
                'Field wajib: JENIS_SOAL, SOAL, JAWABAN.',
                'JAWABAN diisi acuan jawaban/rubrik.',
                'POIN opsional, default 1.',
                'Boleh tempel gambar langsung di bawah baris SOAL. Gambar otomatis masuk ke soal.',
                'Jumlah blok template: ' . $question_count . ' soal.',
                '',
            ];

            for ($idx = 1; $idx <= $question_count; $idx++) {
                $blocks[] = [
                    'JENIS_SOAL: essay',
                    'SOAL: [ESSAY ' . $idx . '] Tulis pertanyaan essay di sini.',
                    'JAWABAN: Tulis acuan jawaban/rubrik penilaian.',
                    'POIN: 1',
                ];
            }
        } else {
            $header_lines = [
                'Template Word ini untuk import Multiple Choice (format tabel).',
                'Setiap blok soal dipisahkan oleh ---',
                'Field wajib: SOAL, PILIHAN_1..PILIHAN_minimal_2, JAWABAN.',
                'JAWABAN diisi nomor pilihan (1-5).',
                'Untuk multiple_choice: hanya satu jawaban, contoh 2.',
                'POIN opsional, default 1.',
                'Boleh tempel gambar langsung di bawah baris SOAL. Gambar otomatis masuk ke soal.',
                'Jumlah blok template: ' . $question_count . ' soal.',
                '',
            ];

            for ($idx = 1; $idx <= $question_count; $idx++) {
                $answer = (string) ((($idx - 1) % 4) + 1);
                $blocks[] = [
                    'SOAL: [MC ' . $idx . '] Tulis pertanyaan pilihan ganda di sini.',
                    'PILIHAN_1: Opsi A',
                    'PILIHAN_2: Opsi B',
                    'PILIHAN_3: Opsi C',
                    'PILIHAN_4: Opsi D',
                    'JAWABAN: ' . $answer,
                    'POIN: 1',
                ];
            }
        }

        $lines = $header_lines;
        foreach ($blocks as $block) {
            $lines[] = '---';
            foreach ($block as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = '---';

        return $lines;
    }

    private static function output_question_template_word_file(array $lines, string $download_name): void
    {
        $doc_xml = self::build_minimal_docx_document_xml($lines);
        $tmp_file = wp_tempnam('cbt-question-import-template.docx');
        if (!$tmp_file) {
            wp_die('Gagal membuat file template sementara.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp_file);
            wp_die('Gagal membuat file docx.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');
        $zip->addFromString('word/document.xml', $doc_xml);
        $zip->close();

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($download_name) . '"');
        header('Content-Length: ' . (string) filesize($tmp_file));
        readfile($tmp_file);
        @unlink($tmp_file);
        exit;
    }

    private static function parse_question_csv(string $tmp_path, bool $require_question_type_column = true)
    {
        $handle = fopen($tmp_path, 'rb');
        if ($handle === false) {
            return new WP_Error('csv_open_failed', 'Gagal membuka file CSV.');
        }

        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $header = self::normalize_question_import_header($header);
        $valid = self::validate_question_import_header($header, $require_question_type_column);
        if (is_wp_error($valid)) {
            fclose($handle);
            return $valid;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($data)) {
                continue;
            }
            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return new WP_Error('csv_no_data', 'Tidak ada data soal di CSV.');
        }

        return $rows;
    }

    private static function parse_question_xlsx(string $tmp_path, bool $require_question_type_column = true)
    {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return new WP_Error(
                'xlsx_library_missing',
                'Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.'
            );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $raw_rows = $sheet->toArray('', false, false, false);
        } catch (Throwable $exception) {
            return new WP_Error('xlsx_read_failed', 'Gagal membaca file XLSX.');
        }

        if (!is_array($raw_rows) || empty($raw_rows)) {
            return new WP_Error('xlsx_empty', 'File XLSX kosong.');
        }

        $header = array_shift($raw_rows);
        if (!is_array($header)) {
            return new WP_Error('xlsx_header_invalid', 'Header XLSX tidak valid.');
        }

        $header = self::normalize_question_import_header($header);
        $valid = self::validate_question_import_header($header, $require_question_type_column);
        if (is_wp_error($valid)) {
            return $valid;
        }

        $rows = [];
        foreach ($raw_rows as $data) {
            if (!is_array($data)) {
                continue;
            }
            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            return new WP_Error('xlsx_no_data', 'Tidak ada data soal di XLSX.');
        }

        return $rows;
    }

    private static function parse_question_docx(string $tmp_path)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('docx_zip_missing', 'Extension zip belum aktif, tidak bisa membaca DOCX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp_path) !== true) {
            return new WP_Error('docx_open_failed', 'File DOCX tidak bisa dibuka.');
        }

        $document_xml = $zip->getFromName('word/document.xml');
        $rels_xml = $zip->getFromName('word/_rels/document.xml.rels');

        $image_rel_map = [];
        if (is_string($rels_xml) && $rels_xml !== '') {
            if (preg_match_all('/<Relationship\b[^>]*>/i', $rels_xml, $rel_nodes)) {
                foreach ($rel_nodes[0] as $node) {
                    if (
                        !preg_match('/\bId="([^"]+)"/i', $node, $id_match) ||
                        !preg_match('/\bType="([^"]+)"/i', $node, $type_match) ||
                        !preg_match('/\bTarget="([^"]+)"/i', $node, $target_match)
                    ) {
                        continue;
                    }

                    $rel_id = trim((string) $id_match[1]);
                    $rel_type = strtolower(trim((string) $type_match[1]));
                    $target = trim((string) $target_match[1]);

                    if ($rel_id === '' || $target === '' || strpos($rel_type, '/image') === false) {
                        continue;
                    }

                    $target = str_replace('\\', '/', $target);
                    while (strpos($target, '../') === 0) {
                        $target = substr($target, 3);
                    }

                    if (strpos($target, 'word/') !== 0) {
                        $target = 'word/' . ltrim($target, '/');
                    }

                    $image_rel_map[$rel_id] = $target;
                }
            }
        }

        $lines = [];
        if (is_string($document_xml) && $document_xml !== '') {
            if (preg_match_all('/<w:p\b[^>]*>(.*?)<\/w:p>/s', $document_xml, $paragraphs)) {
                foreach ($paragraphs[1] as $paragraph) {
                    $paragraph = (string) $paragraph;

                    if (preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $texts)) {
                        $txt = '';
                        foreach ($texts[1] as $fragment) {
                            $txt .= html_entity_decode(strip_tags((string) $fragment), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        }
                        $txt = trim($txt);
                        if ($txt !== '') {
                            $lines[] = $txt;
                        }
                    }

                    if (preg_match_all('/<a:blip\b[^>]*r:embed="([^"]+)"/i', $paragraph, $embeds)) {
                        foreach ($embeds[1] as $embed_id) {
                            $rid = trim((string) $embed_id);
                            if ($rid === '' || !isset($image_rel_map[$rid])) {
                                continue;
                            }

                            $target = $image_rel_map[$rid];
                            $binary = $zip->getFromName($target);
                            if (!is_string($binary) || $binary === '') {
                                $fallback_target = 'word/media/' . basename($target);
                                $binary = $zip->getFromName($fallback_target);
                            }

                            if (!is_string($binary) || $binary === '') {
                                continue;
                            }

                            $image_url = self::store_docx_image_and_get_url($binary, basename($target));
                            if ($image_url !== '') {
                                $lines[] = '__IMG__:' . $image_url;
                            }
                        }
                    }
                }
            }
        }

        $zip->close();

        if (!is_string($document_xml) || $document_xml === '') {
            return new WP_Error('docx_invalid', 'Konten DOCX tidak valid.');
        }

        $lines = self::normalize_docx_extracted_lines($lines);

        if (empty($lines)) {
            return new WP_Error('docx_empty', 'Tidak ada data soal pada DOCX.');
        }

        // New docx format: multiple-choice blocks with answer as option number.
        $blocks = [];
        $current_block = [];
        foreach ($lines as $line) {
            if (trim((string) $line) === '---') {
                if (!empty($current_block)) {
                    $blocks[] = $current_block;
                    $current_block = [];
                }
                continue;
            }
            $current_block[] = (string) $line;
        }
        if (!empty($current_block)) {
            $blocks[] = $current_block;
        }

        $mc_rows = [];
        foreach ($blocks as $block) {
            $row = self::parse_docx_multiple_choice_block($block);
            if (is_array($row) && !empty($row)) {
                $mc_rows[] = $row;
            }
        }

        if (!empty($mc_rows)) {
            return $mc_rows;
        }

        // Backward compatibility: legacy KEY:VALUE docx format.
        $rows = [];
        $current = [];
        foreach ($lines as $line) {
            if (trim($line) === '---') {
                if (!empty($current)) {
                    $rows[] = $current;
                    $current = [];
                }
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = strtolower(trim($parts[0]));
            $key = str_replace([' ', '-'], '_', $key);
            $value = trim($parts[1]);
            $current[$key] = $value;
        }
        if (!empty($current)) {
            $rows[] = $current;
        }

        if (empty($rows)) {
            return new WP_Error('docx_no_data', 'Format DOCX tidak sesuai template.');
        }

        return $rows;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private static function normalize_docx_extracted_lines(array $lines): array
    {
        $normalized = [];
        $total = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $line = trim((string) ($lines[$i] ?? ''));
            if ($line === '') {
                continue;
            }

            if (self::is_docx_key_only_line($line)) {
                $next = trim((string) ($lines[$i + 1] ?? ''));

                if (
                    $next !== '' &&
                    $next !== '---' &&
                    strpos($next, '__IMG__:') !== 0 &&
                    !self::is_docx_key_only_line($next) &&
                    !self::is_docx_key_value_line($next)
                ) {
                    $normalized[] = $line . ': ' . $next;
                    $i++;
                    continue;
                }

                // Keep key marker recognizable for parser even if value is empty or image-only.
                $normalized[] = $line . ':';
                continue;
            }

            $normalized[] = $line;
        }

        return $normalized;
    }

    private static function is_docx_key_only_line(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') !== false || strpos($line, '__IMG__:') === 0) {
            return false;
        }

        return (bool) preg_match(
            '/^(jenis_soal|question_type|type|soal|question|pertanyaan|subject_code|kode_mapel|exam_title|judul_exam|ujian|point|points|poin|nilai|jawaban|answer|correct_answer|jawaban_ke|answer_option|correct_text|rubrik|rubric|rubric_text|(pilihan|opsi|option)_?([1-9]|1[0-2])|(pernyataan|statement|item)_?([1-9]|10)|(kunci|truth|tf)_?([1-9]|10)|(jawaban|answer|correct)_?([1-9]|10|[a-h])|[a-l])$/i',
            $line
        );
    }

    private static function is_docx_key_value_line(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') === false || strpos($line, '__IMG__:') === 0) {
            return false;
        }

        $parts = explode(':', $line, 2);
        return self::is_docx_key_only_line((string) ($parts[0] ?? ''));
    }

    private static function parse_docx_multiple_choice_block(array $block): ?array
    {
        $question_parts = [];
        $max_option_index = 12;
        $options_map = [];
        for ($idx = 1; $idx <= $max_option_index; $idx++) {
            $options_map[$idx] = '';
        }
        $answer_indices = [];
        $points = 1.0;
        $subject_code = '';
        $exam_title = '';
        $forced_question_type = '';
        $answer_text = '';
        $short_answer_map = [];
        $tf_matrix_statement_map = [];
        $tf_matrix_answer_map = [];
        $active_context = 'question';

        foreach ($block as $raw_line) {
            $line = trim((string) $raw_line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '__IMG__:') === 0) {
                $img_url = trim(substr($line, 8));
                if ($img_url !== '') {
                    $img_html = '<p><img src="' . esc_url($img_url) . '" alt="" /></p>';
                    if (is_array($active_context) && ($active_context[0] ?? '') === 'option') {
                        $opt_idx = (int) ($active_context[1] ?? 0);
                        if ($opt_idx >= 1 && $opt_idx <= $max_option_index) {
                            $current = trim((string) ($options_map[$opt_idx] ?? ''));
                            $options_map[$opt_idx] = ($current === '')
                                ? $img_html
                                : ($current . $img_html);
                        } else {
                            $question_parts[] = $img_html;
                        }
                    } elseif (is_array($active_context) && ($active_context[0] ?? '') === 'matrix_statement') {
                        $statement_idx = (int) ($active_context[1] ?? 0);
                        if ($statement_idx >= 1 && $statement_idx <= 10) {
                            $current = trim((string) ($tf_matrix_statement_map[$statement_idx] ?? ''));
                            $tf_matrix_statement_map[$statement_idx] = ($current === '')
                                ? $img_html
                                : ($current . $img_html);
                        } else {
                            $question_parts[] = $img_html;
                        }
                    } else {
                        $question_parts[] = $img_html;
                    }
                }
                continue;
            }

            if (preg_match('/^([1-9]|1[0-2])[\.\)]\s*(.+)$/u', $line, $matches)) {
                $opt_idx = (int) $matches[1];
                if ($opt_idx >= 1 && $opt_idx <= $max_option_index) {
                    $options_map[$opt_idx] = trim((string) $matches[2]);
                    $active_context = ['option', $opt_idx];
                }
                continue;
            }

            if (preg_match('/^([A-La-l])[\.\)]\s*(.+)$/u', $line, $matches)) {
                $opt_idx = ord(strtoupper((string) $matches[1])) - ord('A') + 1;
                if ($opt_idx >= 1 && $opt_idx <= $max_option_index) {
                    $options_map[$opt_idx] = trim((string) $matches[2]);
                    $active_context = ['option', $opt_idx];
                }
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim((string) $parts[0]));
                $key = str_replace([' ', '-'], '_', $key);
                $value = trim((string) $parts[1]);

                if (in_array($key, ['soal', 'question', 'pertanyaan', 'question_text'], true)) {
                    if ($value !== '') {
                        $question_parts[] = $value;
                    }
                    $active_context = 'question';
                    continue;
                }

                if (in_array($key, ['subject_code', 'kode_mapel'], true)) {
                    $subject_code = $value;
                    continue;
                }

                if (in_array($key, ['exam_title', 'judul_exam', 'ujian'], true)) {
                    $exam_title = $value;
                    continue;
                }

                if (in_array($key, ['point', 'points', 'poin', 'nilai'], true)) {
                    if ($value !== '' && is_numeric($value)) {
                        $points = (float) $value;
                    }
                    continue;
                }

                if (in_array($key, ['jenis_soal', 'question_type', 'type'], true)) {
                    $mapped = self::map_import_question_type($value);
                    if (in_array($mapped, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'essay', 'short_answer'], true)) {
                        $forced_question_type = $mapped;
                    }
                    continue;
                }

                if (in_array($key, ['jawaban', 'answer', 'correct_answer', 'jawaban_ke', 'answer_option', 'correct_text', 'rubrik', 'rubric', 'rubric_text'], true)) {
                    $answer_text = $value;
                    $answer_indices = self::normalize_docx_answer_indices($value);
                    $active_context = 'answer';
                    continue;
                }

                if (preg_match('/^(pernyataan|statement|item)_?([1-9]|10)$/', $key, $matches)) {
                    $statement_idx = (int) $matches[2];
                    if ($statement_idx >= 1 && $statement_idx <= 10) {
                        $tf_matrix_statement_map[$statement_idx] = $value;
                    }
                    $active_context = ['matrix_statement', $statement_idx];
                    continue;
                }

                if (preg_match('/^(kunci|truth|tf)_?([1-9]|10)$/', $key, $matches)) {
                    $statement_idx = (int) $matches[2];
                    if ($statement_idx >= 1 && $statement_idx <= 10) {
                        $tf_matrix_answer_map[$statement_idx] = self::normalize_docx_true_false_value($value);
                    }
                    $active_context = ['matrix_answer', $statement_idx];
                    continue;
                }

                if (preg_match('/^(jawaban|answer|correct)_?([1-9]|10)$/', $key, $matches)) {
                    $answer_idx = (int) $matches[2];
                    if ($forced_question_type === 'true_false_matrix' || !empty($tf_matrix_statement_map) || $answer_idx >= 9) {
                        if ($answer_idx >= 1 && $answer_idx <= 10) {
                            $tf_matrix_answer_map[$answer_idx] = self::normalize_docx_true_false_value($value);
                        }
                        $active_context = ['matrix_answer', $answer_idx];
                        continue;
                    }

                    if ($answer_idx >= 1 && $answer_idx <= 8) {
                        $short_answer_map[$answer_idx] = $value;
                    }
                    $active_context = ['short_answer', $answer_idx];
                    continue;
                }

                if (preg_match('/^(jawaban|answer|correct)_?([a-h])$/', $key, $matches)) {
                    $sa_idx = ord(strtoupper((string) $matches[2])) - ord('A') + 1;
                    if ($sa_idx >= 1 && $sa_idx <= 8) {
                        $short_answer_map[$sa_idx] = $value;
                    }
                    $active_context = ['short_answer', $sa_idx];
                    continue;
                }

                if (preg_match('/^(pilihan|opsi|option)_?([1-9]|1[0-2])$/', $key, $matches)) {
                    $opt_idx = (int) $matches[2];
                    if ($opt_idx >= 1 && $opt_idx <= $max_option_index) {
                        $options_map[$opt_idx] = $value;
                    }
                    $active_context = ['option', $opt_idx];
                    continue;
                }

                if (preg_match('/^[a-l]$/', $key)) {
                    if ($forced_question_type === 'short_answer' && preg_match('/^[a-h]$/', $key)) {
                        $sa_idx = ord(strtoupper($key)) - ord('A') + 1;
                        if ($sa_idx >= 1 && $sa_idx <= 8) {
                            $short_answer_map[$sa_idx] = $value;
                        }
                        $active_context = ['short_answer', $sa_idx];
                        continue;
                    }

                    $opt_idx = ord(strtoupper($key)) - ord('A') + 1;
                    if ($opt_idx >= 1 && $opt_idx <= $max_option_index) {
                        $options_map[$opt_idx] = $value;
                    }
                    $active_context = ['option', $opt_idx];
                    continue;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'option') {
                $opt_idx = (int) ($active_context[1] ?? 0);
                if ($opt_idx >= 1 && $opt_idx <= $max_option_index) {
                    $current = trim((string) ($options_map[$opt_idx] ?? ''));
                    $options_map[$opt_idx] = ($current === '')
                        ? $line
                        : ($current . '<br />' . $line);
                    continue;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'matrix_statement') {
                $statement_idx = (int) ($active_context[1] ?? 0);
                if ($statement_idx >= 1 && $statement_idx <= 10) {
                    $current = trim((string) ($tf_matrix_statement_map[$statement_idx] ?? ''));
                    $tf_matrix_statement_map[$statement_idx] = ($current === '')
                        ? $line
                        : ($current . ' ' . $line);
                    continue;
                }
            }

            // Any free-text line in the block is appended as question body.
            $question_parts[] = $line;
            $active_context = 'question';
        }

        $question_text = self::build_docx_question_text($question_parts);
        if ($question_text === '') {
            return null;
        }

        if ($forced_question_type === 'true_false') {
            $tf_value = strtolower(trim($answer_text));
            if ($tf_value === '') {
                return null;
            }
            if (in_array($tf_value, ['false', '0', 'f', 'no', 'tidak', 'salah'], true)) {
                $tf_value = 'false';
            } else {
                $tf_value = 'true';
            }

            $row = [
                'question_type' => 'true_false',
                'question_text' => $question_text,
                'points' => (string) max(0, $points),
                'options' => '',
                'correct_answer' => $tf_value,
                'correct_text' => '',
            ];
            if ($subject_code !== '') {
                $row['subject_code'] = $subject_code;
            }
            if ($exam_title !== '') {
                $row['exam_title'] = $exam_title;
            }
            return $row;
        }

        if ($forced_question_type === 'essay') {
            $essay_rubric = trim($answer_text);
            if ($essay_rubric === '') {
                return null;
            }
            $row = [
                'question_type' => 'essay',
                'question_text' => $question_text,
                'points' => (string) max(0, $points),
                'options' => '',
                'correct_answer' => '',
                'correct_text' => $essay_rubric,
            ];
            if ($subject_code !== '') {
                $row['subject_code'] = $subject_code;
            }
            if ($exam_title !== '') {
                $row['exam_title'] = $exam_title;
            }
            return $row;
        }

        if ($forced_question_type === 'short_answer') {
            ksort($short_answer_map);
            $short_answer_values = [];
            foreach ($short_answer_map as $val) {
                $short_answer_values[] = (string) $val;
            }
            if ($answer_text !== '') {
                $short_answer_values = array_merge($short_answer_values, self::normalize_short_answer_values($answer_text));
            }
            $short_answer_values = self::normalize_short_answer_values(wp_json_encode($short_answer_values));
            if (empty($short_answer_values)) {
                return null;
            }
            $row = [
                'question_type' => 'short_answer',
                'question_text' => $question_text,
                'points' => (string) max(0, $points),
                'options' => '',
                'correct_answer' => '',
                'correct_text' => wp_json_encode($short_answer_values),
            ];
            if ($subject_code !== '') {
                $row['subject_code'] = $subject_code;
            }
            if ($exam_title !== '') {
                $row['exam_title'] = $exam_title;
            }
            return $row;
        }

        if ($forced_question_type === 'true_false_matrix' || !empty($tf_matrix_statement_map)) {
            ksort($tf_matrix_statement_map);
            $matrix_items = [];
            foreach ($tf_matrix_statement_map as $idx => $statement_text) {
                $statement_text = trim((string) $statement_text);
                if ($statement_text === '') {
                    continue;
                }

                $answer_value = isset($tf_matrix_answer_map[$idx])
                    ? self::normalize_docx_true_false_value((string) $tf_matrix_answer_map[$idx])
                    : 'true';
                $matrix_items[] = [
                    'text' => sanitize_text_field($statement_text),
                    'answer' => $answer_value,
                ];
            }

            if (count($matrix_items) < 2 && $answer_text !== '') {
                $matrix_items = self::normalize_true_false_matrix_config($answer_text);
            }

            if (count($matrix_items) < 2) {
                return null;
            }

            $row = [
                'question_type' => 'true_false_matrix',
                'question_text' => $question_text,
                'points' => (string) max(0, $points),
                'options' => '',
                'correct_answer' => '',
                'correct_text' => wp_json_encode([
                    'statements' => $matrix_items,
                ]),
            ];
            if ($subject_code !== '') {
                $row['subject_code'] = $subject_code;
            }
            if ($exam_title !== '') {
                $row['exam_title'] = $exam_title;
            }
            return $row;
        }

        $options = [];
        foreach (range(1, $max_option_index) as $idx) {
            $val = trim((string) ($options_map[$idx] ?? ''));
            if ($val !== '') {
                $options[$idx] = $val;
            }
        }

        if (count($options) < 2) {
            return null;
        }

        $filled_indices = array_keys($options);
        sort($filled_indices);
        $max_idx = (int) max($filled_indices);

        $detected_question_type = count($answer_indices) > 1 ? 'multiple_answer' : 'multiple_choice';
        if ($forced_question_type !== '') {
            $detected_question_type = $forced_question_type;
        }

        $max_allowed_index = ($detected_question_type === 'multiple_answer') ? 12 : 5;
        if ($max_idx > $max_allowed_index) {
            return null;
        }

        for ($idx = 1; $idx <= $max_idx; $idx++) {
            if (!isset($options[$idx])) {
                return null;
            }
        }

        if (empty($answer_indices)) {
            return null;
        }

        $answer_indices = array_values(array_unique(array_filter(
            $answer_indices,
            static fn($idx) => is_int($idx) && $idx >= 1 && $idx <= $max_idx && isset($options[$idx])
        )));
        sort($answer_indices);

        if (empty($answer_indices)) {
            return null;
        }

        if ($detected_question_type === 'multiple_choice' && count($answer_indices) !== 1) {
            return null;
        }

        if ($detected_question_type === 'multiple_answer' && count($answer_indices) < 1) {
            return null;
        }

        $alpha = range('A', 'L');
        $correct_answer_tokens = [];
        foreach ($answer_indices as $idx) {
            $token = $alpha[$idx - 1] ?? '';
            if ($token !== '') {
                $correct_answer_tokens[] = $token;
            }
        }
        if (empty($correct_answer_tokens)) {
            return null;
        }
        $correct_answer = implode(',', $correct_answer_tokens);

        $ordered_options = [];
        for ($idx = 1; $idx <= $max_idx; $idx++) {
            if (isset($options[$idx])) {
                $ordered_options[] = $options[$idx];
            }
        }

        $row = [
            'question_type' => $detected_question_type,
            'question_text' => $question_text,
            'points' => (string) max(0, $points),
            'options' => implode('||', $ordered_options),
            'correct_answer' => $correct_answer,
            'correct_text' => '',
        ];

        if ($subject_code !== '') {
            $row['subject_code'] = $subject_code;
        }
        if ($exam_title !== '') {
            $row['exam_title'] = $exam_title;
        }

        return $row;
    }

    private static function build_docx_question_text(array $parts): string
    {
        $html_parts = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (strpos($part, '<p><img ') === 0) {
                $html_parts[] = $part;
                continue;
            }

            $html_parts[] = '<p>' . esc_html($part) . '</p>';
        }

        return trim(implode('', $html_parts));
    }

    private static function store_docx_image_and_get_url(string $binary, string $filename): string
    {
        if ($binary === '') {
            return '';
        }

        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'png';
        }

        $safe_ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if ($safe_ext === '') {
            $safe_ext = 'png';
        }

        $upload_name = 'cbt-question-' . wp_generate_password(10, false, false) . '.' . $safe_ext;
        $upload = wp_upload_bits($upload_name, null, $binary);
        if (is_array($upload) && empty($upload['error']) && !empty($upload['url'])) {
            return esc_url_raw((string) $upload['url']);
        }

        $mime = self::guess_mime_from_extension($safe_ext);
        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    private static function guess_mime_from_extension(string $ext): string
    {
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'gif':
                return 'image/gif';
            case 'webp':
                return 'image/webp';
            case 'bmp':
                return 'image/bmp';
            case 'svg':
                return 'image/svg+xml';
            case 'png':
            default:
                return 'image/png';
        }
    }

    private static function import_single_question_row(array $row, int $default_exam_id, bool $is_admin_scope, int $current_user_id, array &$affected_exam_ids = []): string
    {
        global $wpdb;

        $question_type = self::map_import_question_type((string) ($row['question_type'] ?? ''));
        $question_text = wp_kses_post((string) ($row['question_text'] ?? ''));
        $question_text = trim($question_text);
        if ($question_type === '' || $question_text === '') {
            return 'failed';
        }

        $exam_id = self::resolve_import_question_exam_id($row, $default_exam_id, $is_admin_scope, $current_user_id);
        if ($exam_id <= 0) {
            return 'failed';
        }
        $affected_exam_ids[$exam_id] = $exam_id;

        $points = isset($row['points']) && $row['points'] !== '' ? (float) $row['points'] : 1.0;
        $points = max(0, $points);

        $options_input = (string) ($row['options'] ?? '');
        $correct_answer = (string) ($row['correct_answer'] ?? '');
        $correct_text = (string) ($row['correct_text'] ?? '');
        $options_raw = '';

        if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
            $built = self::build_options_raw_from_import($options_input, $correct_answer, $question_type);
            if ($built === '') {
                return 'failed';
            }
            $options_raw = $built;
            $correct_text = '';
        } elseif ($question_type === 'true_false') {
            $normalized = strtolower(trim($correct_answer !== '' ? $correct_answer : $correct_text));
            if (in_array($normalized, ['false', '0', 'f', 'no', 'tidak'], true)) {
                $correct_text = 'false';
            } else {
                $correct_text = 'true';
            }
            $options_raw = '';
        } elseif ($question_type === 'true_false_matrix') {
            $correct_text = self::normalize_true_false_matrix_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
            if ($correct_text === '' || count(self::normalize_true_false_matrix_config($correct_text)) < 2) {
                return 'failed';
            }
            $options_raw = '';
        } elseif ($question_type === 'short_answer') {
            $correct_text = self::normalize_short_answer_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
            if ($correct_text === '') {
                return 'failed';
            }
            $options_raw = '';
        } elseif ($question_type === 'essay') {
            $correct_text = trim($correct_text !== '' ? $correct_text : $correct_answer);
            if ($correct_text === '') {
                return 'failed';
            }
            $options_raw = '';
        } else {
            $correct_text = '';
            $options_raw = '';
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'cbt_questions',
            [
                'exam_id' => $exam_id,
                'question_text' => $question_text,
                'question_type' => $question_type,
                'points' => $points,
                'correct_text' => $correct_text !== '' ? $correct_text : null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s']
        );
        if (!$inserted) {
            return 'failed';
        }

        $question_id = (int) $wpdb->insert_id;
        $options_to_insert = self::parse_options($options_raw);

        if ($question_type === 'true_false' && empty($options_to_insert)) {
            $true_is_correct = (strtolower($correct_text) === 'true') ? 1 : 0;
            $options_to_insert = [
                ['option_text' => 'True', 'is_correct' => $true_is_correct],
                ['option_text' => 'False', 'is_correct' => $true_is_correct ? 0 : 1],
            ];
        }

        foreach ($options_to_insert as $idx => $opt) {
            $wpdb->insert(
                $wpdb->prefix . 'cbt_options',
                [
                    'question_id' => $question_id,
                    'option_key' => chr(65 + $idx),
                    'option_text' => $opt['option_text'],
                    'is_correct' => (int) $opt['is_correct'],
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%d', '%s']
            );
        }

        self::save_question_type_detail($question_id, $question_type, $correct_text);

        return 'created';
    }

    private static function resolve_import_question_exam_id(array $row, int $default_exam_id, bool $is_admin_scope, int $current_user_id): int
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';

        $exam_id = isset($row['exam_id']) ? absint((string) $row['exam_id']) : 0;
        if ($exam_id <= 0 && !empty($row['exam_title'])) {
            $exam_title = sanitize_text_field((string) $row['exam_title']);
            $subject_id = isset($row['subject_id']) ? absint((string) $row['subject_id']) : 0;

            if ($subject_id <= 0 && !empty($row['subject_code'])) {
                $subject_id = (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT id FROM {$subject_table} WHERE code = %s LIMIT 1", sanitize_text_field((string) $row['subject_code']))
                );
            }

            if ($subject_id > 0) {
                $exam_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$exam_table} WHERE title = %s AND subject_id = %d ORDER BY id ASC LIMIT 1",
                        $exam_title,
                        $subject_id
                    )
                );
            } else {
                $exam_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$exam_table} WHERE title = %s ORDER BY id ASC LIMIT 1",
                        $exam_title
                    )
                );
            }
        }

        $fallback_exam_id = $default_exam_id;

        if ($exam_id <= 0) {
            $exam_id = $fallback_exam_id;
        }

        if ($exam_id <= 0) {
            return 0;
        }

        if ($is_admin_scope) {
            $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$exam_table} WHERE id = %d", $exam_id));
            if ($exists > 0) {
                return $exam_id;
            }
            if ($fallback_exam_id > 0 && $fallback_exam_id !== $exam_id) {
                $fallback_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$exam_table} WHERE id = %d", $fallback_exam_id));
                if ($fallback_exists > 0) {
                    return $fallback_exam_id;
                }
            }
            return 0;
        }

        $owned = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$exam_table} WHERE id = %d AND created_by = %d",
                $exam_id,
                $current_user_id
            )
        );

        if ($owned > 0) {
            return $exam_id;
        }

        if ($fallback_exam_id > 0 && $fallback_exam_id !== $exam_id) {
            $fallback_owned = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$exam_table} WHERE id = %d AND created_by = %d",
                    $fallback_exam_id,
                    $current_user_id
                )
            );
            if ($fallback_owned > 0) {
                return $fallback_exam_id;
            }
        }

        return 0;
    }

    private static function normalize_docx_answer_indices(string $raw): array
    {
        $value = strtoupper(trim($raw));
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/[,\;\|\/\s]+/', $value);
        if (!is_array($tokens)) {
            $tokens = [$value];
        }

        $indices = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            if (is_numeric($token)) {
                $idx = (int) $token;
                if ($idx >= 1 && $idx <= 12) {
                    $indices[] = $idx;
                }
                continue;
            }

            if (preg_match('/^[A-L]$/', $token)) {
                $indices[] = ord($token) - ord('A') + 1;
            }
        }

        return $indices;
    }

    private static function normalize_docx_true_false_value(string $raw): string
    {
        $normalized = strtolower(trim((string) $raw));
        if ($normalized === '') {
            return 'true';
        }

        if (in_array($normalized, ['false', '0', 'f', 'no', 'tidak', 'salah', 's'], true)) {
            return 'false';
        }

        return 'true';
    }

    /**
     * @return string[]
     */
    private static function normalize_short_answer_values(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $values = [];

        if (($raw[0] ?? '') === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $values[] = (string) $item;
                }
            }
        }

        if (empty($values)) {
            $parts = preg_split('/\|\||\r\n|\r|\n|;/', $raw);
            if (!is_array($parts) || empty($parts)) {
                $parts = [$raw];
            }
            foreach ($parts as $part) {
                if (!is_scalar($part)) {
                    continue;
                }
                $values[] = (string) $part;
            }
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = sanitize_text_field(trim((string) $value));
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
            if (count($normalized) >= 8) {
                break;
            }
        }

        return $normalized;
    }

    private static function normalize_short_answer_payload(string $raw): string
    {
        $values = self::normalize_short_answer_values($raw);
        if (empty($values)) {
            return '';
        }

        return (string) wp_json_encode($values);
    }

    /**
     * @return array<int,array{text:string,answer:string}>
     */
    private static function normalize_true_false_matrix_config(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['statements']) && is_array($decoded['statements'])) {
                $candidates = $decoded['statements'];
            } else {
                $is_list = !empty($decoded) && array_keys($decoded) === range(0, count($decoded) - 1);
                if ($is_list) {
                    $candidates = $decoded;
                }
            }
        }

        if (empty($candidates)) {
            $lines = preg_split('/\r\n|\r|\n/', $raw);
            foreach ((array) $lines as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $parts = explode('|', $line, 2);
                $text = trim((string) ($parts[0] ?? ''));
                $answer = trim((string) ($parts[1] ?? 'true'));
                $candidates[] = [
                    'text' => $text,
                    'answer' => $answer,
                ];
            }
        }

        $normalized = [];
        foreach ((array) $candidates as $candidate) {
            if (count($normalized) >= 10) {
                break;
            }

            if (is_string($candidate) || is_numeric($candidate)) {
                $text = sanitize_text_field(trim((string) $candidate));
                if ($text === '') {
                    continue;
                }
                $normalized[] = [
                    'text' => $text,
                    'answer' => 'true',
                ];
                continue;
            }

            if (!is_array($candidate)) {
                continue;
            }

            $text = sanitize_text_field(
                trim((string) ($candidate['text'] ?? $candidate['statement'] ?? $candidate['pernyataan'] ?? ''))
            );
            if ($text === '') {
                continue;
            }

            $answer_source = $candidate['answer'] ?? $candidate['correct'] ?? 'true';
            if (is_bool($answer_source)) {
                $answer_raw = $answer_source ? 'true' : 'false';
            } else {
                $answer_raw = strtolower(trim((string) $answer_source));
            }
            $answer = in_array($answer_raw, ['false', '0', 'f', 'no', 'tidak', 'salah'], true)
                ? 'false'
                : 'true';

            $normalized[] = [
                'text' => $text,
                'answer' => $answer,
            ];
        }

        return $normalized;
    }

    private static function normalize_true_false_matrix_payload(string $raw): string
    {
        $items = self::normalize_true_false_matrix_config($raw);
        if (empty($items)) {
            return '';
        }

        return (string) wp_json_encode([
            'statements' => $items,
        ]);
    }

    private static function ensure_subject_question_bank_exam(int $subject_id, bool $is_admin_scope, int $current_user_id): int
    {
        if ($subject_id <= 0) {
            return 0;
        }

        global $wpdb;
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $bank_title_like = 'Bank Soal - %';

        if ($is_admin_scope) {
            $exam_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$exam_table} WHERE subject_id = %d AND title LIKE %s ORDER BY id DESC LIMIT 1",
                    $subject_id,
                    $bank_title_like
                )
            );
        } else {
            $exam_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$exam_table} WHERE subject_id = %d AND created_by = %d AND title LIKE %s ORDER BY id DESC LIMIT 1",
                    $subject_id,
                    $current_user_id,
                    $bank_title_like
                )
            );
        }

        if ($exam_id > 0) {
            return $exam_id;
        }

        $subject_name = (string) $wpdb->get_var(
            $wpdb->prepare("SELECT name FROM {$subject_table} WHERE id = %d LIMIT 1", $subject_id)
        );
        if ($subject_name === '') {
            return 0;
        }

        $creator_id = $current_user_id > 0 ? $current_user_id : get_current_user_id();
        if ($creator_id <= 0) {
            return 0;
        }

        $inserted = $wpdb->insert(
            $exam_table,
            [
                'subject_id' => $subject_id,
                'title' => 'Bank Soal - ' . $subject_name,
                'description' => 'Penampung bank soal per mapel.',
                'duration_minutes' => 60,
                'total_questions' => 0,
                'randomize_questions' => 0,
                'status' => 'draft',
                'created_by' => $creator_id,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    private static function build_options_raw_from_import(string $options_input, string $correct_answer, string $question_type): string
    {
        $parts = preg_split('/\|\||\r\n|\r|\n/', $options_input);
        $options = [];
        foreach ((array) $parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $options[] = $part;
            }
        }

        if (empty($options)) {
            return '';
        }

        $token_set = [];
        $tokens = array_filter(array_map('trim', explode(',', strtoupper($correct_answer))), static fn($v) => $v !== '');
        foreach ($tokens as $token) {
            $token_set[$token] = true;
        }

        $alpha = range('A', 'Z');
        $lines = [];
        $correct_count = 0;
        foreach ($options as $idx => $opt) {
            $key = $alpha[$idx] ?? '';
            $correct = false;
            if ($key !== '' && isset($token_set[$key])) {
                $correct = true;
            } else {
                foreach ($token_set as $token => $_) {
                    if (strcasecmp($token, $opt) === 0) {
                        $correct = true;
                        break;
                    }
                }
            }
            if ($correct) {
                $correct_count++;
            }
            $lines[] = $opt . '|' . ($correct ? '1' : '0');
        }

        if ($question_type === 'multiple_choice') {
            if ($correct_count === 0 && !empty($lines)) {
                $lines[0] = preg_replace('/\|0$/', '|1', $lines[0]);
            } elseif ($correct_count > 1) {
                $already = false;
                foreach ($lines as $idx => $line) {
                    if (substr($line, -2) === '|1') {
                        if (!$already) {
                            $already = true;
                        } else {
                            $lines[$idx] = preg_replace('/\|1$/', '|0', $line);
                        }
                    }
                }
            }
        } elseif ($question_type === 'multiple_answer' && $correct_count === 0) {
            return '';
        }

        return implode("\n", $lines);
    }

    private static function map_import_question_type(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = str_replace([' ', '-'], '_', $raw);
        switch ($raw) {
            case 'multiple_choice':
            case 'mcq':
                return 'multiple_choice';
            case 'multiple_answer':
            case 'multiple_answers':
                return 'multiple_answer';
            case 'true_false':
            case 'tf':
                return 'true_false';
            case 'true_false_matrix':
            case 'tf_matrix':
            case 'matrix_tf':
                return 'true_false_matrix';
            case 'short_answer':
            case 'short':
                return 'short_answer';
            case 'essay':
                return 'essay';
            default:
                return '';
        }
    }

    private static function normalize_question_import_header(array $header): array
    {
        return array_map(static function ($item) {
            $clean = trim((string) $item);
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);
            $clean = strtolower($clean);
            return str_replace([' ', '-'], '_', $clean);
        }, $header);
    }

    private static function validate_question_import_header(array $header, bool $require_question_type_column = true)
    {
        $required = ['question_text'];
        if ($require_question_type_column) {
            $required[] = 'question_type';
        }
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                return new WP_Error('import_header_invalid', 'Header file tidak valid. Gunakan template import soal resmi.');
            }
        }
        return true;
    }

    private static function question_template_rows(): array
    {
        return [
            ['subject_code', 'exam_title', 'question_type', 'question_text', 'points', 'options', 'correct_answer', 'correct_text'],
            ['MAT', 'Ujian Matematika X', 'multiple_choice', '2 + 2 = ?', '1', '1||2||3||4', 'D', ''],
            ['MAT', 'Ujian Matematika X', 'multiple_choice', '5 - 2 = ?', '1', '1||2||3||4', 'C', ''],
            ['MAT', 'Ujian Matematika X', 'multiple_answer', 'Bilangan genap adalah ...', '2', '2||3||4||5', 'A,C', ''],
            ['MAT', 'Ujian Matematika X', 'true_false', '10 adalah bilangan genap.', '1', '', 'true', ''],
            ['MAT', 'Ujian Matematika X', 'short_answer', 'Lengkapi warna bendera Indonesia: [INPUT_1] dan [INPUT_2].', '2', '', '', 'merah||putih'],
            ['MAT', 'Ujian Matematika X', 'essay', 'Jelaskan langkah menyelesaikan persamaan kuadrat.', '5', '', '', ''],
        ];
    }

    private static function build_minimal_docx_document_xml(array $lines): string
    {
        // Keep template as table layout, but lock widths within printable page area.
        $col_left = 2300;
        $col_right = 7000;

        $build_cell = static function (string $text, int $width, array $options = []): string {
            $is_header = !empty($options['header']);
            $is_bold = $is_header || !empty($options['bold']);
            $is_center = !empty($options['center']);
            $fill_color = isset($options['fill']) ? strtoupper(trim((string) $options['fill'])) : '';
            $text_color = isset($options['text_color']) ? strtoupper(trim((string) $options['text_color'])) : '';

            $safe = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($fill_color === '' && $is_header) {
                $fill_color = 'E9EEF5';
            }

            $cell_fill_xml = $fill_color !== ''
                ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill_color . '"/>'
                : '';

            $paragraph_align_xml = $is_center ? '<w:jc w:val="center"/>' : '';

            $run_prop = '';
            if ($is_bold || $text_color !== '') {
                $run_prop = '<w:rPr>';
                if ($is_bold) {
                    $run_prop .= '<w:b/>';
                }
                if ($text_color !== '') {
                    $run_prop .= '<w:color w:val="' . $text_color . '"/>';
                }
                $run_prop .= '</w:rPr>';
            }

            return
                '<w:tc>'
                . '<w:tcPr>'
                . '<w:tcW w:w="' . (string) $width . '" w:type="dxa"/>'
                . '<w:vAlign w:val="top"/>'
                . $cell_fill_xml
                . '</w:tcPr>'
                . '<w:p>'
                . '<w:pPr><w:spacing w:before="0" w:after="60" w:line="276" w:lineRule="auto"/>' . $paragraph_align_xml . '</w:pPr>'
                . '<w:r>'
                . $run_prop
                . '<w:t xml:space="preserve">' . $safe . '</w:t>'
                . '</w:r>'
                . '</w:p>'
                . '</w:tc>';
        };

        $table_rows = [];
        $table_rows[] =
            '<w:tr>'
            . $build_cell('FIELD', $col_left, ['header' => true])
            . $build_cell('VALUE', $col_right, ['header' => true])
            . '</w:tr>';

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $left = '';
            $right = '';

            if ($line === '') {
                $table_rows[] = '<w:tr>' . $build_cell('', $col_left) . $build_cell('', $col_right) . '</w:tr>';
                continue;
            }

            if ($line === '---') {
                $table_rows[] =
                    '<w:tr>'
                    . $build_cell('', $col_left, ['fill' => 'FFF2CC'])
                    . $build_cell('---', $col_right, ['fill' => 'FFF2CC', 'bold' => true, 'center' => true, 'text_color' => '7F6000'])
                    . '</w:tr>';
                continue;
            }

            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $left = trim((string) ($parts[0] ?? ''));
                $right = ltrim((string) ($parts[1] ?? ''));
            } else {
                $right = $line;
            }

            $table_rows[] =
                '<w:tr>'
                . $build_cell($left, $col_left)
                . $build_cell($right, $col_right)
                . '</w:tr>';
        }

        $table =
            '<w:tbl>'
            . '<w:tblPr>'
            . '<w:tblW w:w="5000" w:type="pct"/>'
            . '<w:jc w:val="center"/>'
            . '<w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar>'
            . '<w:top w:w="60" w:type="dxa"/>'
            . '<w:left w:w="80" w:type="dxa"/>'
            . '<w:bottom w:w="60" w:type="dxa"/>'
            . '<w:right w:w="80" w:type="dxa"/>'
            . '</w:tblCellMar>'
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:left w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:right w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:insideH w:val="single" w:sz="6" w:space="0" w:color="A6A6A6"/>'
            . '<w:insideV w:val="single" w:sz="6" w:space="0" w:color="A6A6A6"/>'
            . '</w:tblBorders>'
            . '</w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="' . (string) $col_left . '"/><w:gridCol w:w="' . (string) $col_right . '"/></w:tblGrid>'
            . implode('', $table_rows)
            . '</w:tbl>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . $table
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            . '</w:body></w:document>';
    }

    private static function get_question_delete_state_key(string $token): string
    {
        return 'cbt_question_delete_' . $token;
    }

    private static function get_question_delete_rows_key(string $token): string
    {
        return 'cbt_question_delete_rows_' . $token;
    }

    private static function clear_question_delete_transients(string $token): void
    {
        delete_transient(self::get_question_delete_state_key($token));
        delete_transient(self::get_question_delete_rows_key($token));
    }

    private static function get_question_delete_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_question_delete_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function get_question_delete_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_question_delete_batch_size', 220);
        if ($batch_size < 20) {
            return 20;
        }
        if ($batch_size > 1000) {
            return 1000;
        }

        return $batch_size;
    }

    private static function get_question_delete_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_question_delete_batch_max_seconds', 8.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function get_question_import_state_key(string $token): string
    {
        return 'cbt_question_import_' . $token;
    }

    private static function get_question_import_rows_key(string $token): string
    {
        return 'cbt_question_import_rows_' . $token;
    }

    private static function clear_question_import_transients(string $token): void
    {
        delete_transient(self::get_question_import_state_key($token));
        delete_transient(self::get_question_import_rows_key($token));
    }

    private static function get_question_import_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_question_import_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function get_question_import_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_question_import_batch_size', 140);
        if ($batch_size < 20) {
            return 20;
        }
        if ($batch_size > 500) {
            return 500;
        }

        return $batch_size;
    }

    private static function get_question_import_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_question_import_batch_max_seconds', 10.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function redirect_question_import_with_error(string $message, string $return_page = 'cbt-question-bank'): void
    {
        wp_safe_redirect(add_query_arg(
            [
                'page' => self::normalize_question_page_slug($return_page),
                'cbt_err' => $message,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handle_grade_essay(): void
    {
        if (!current_user_can('cbt_grade_essay')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_grade_essay');

        global $wpdb;

        $answer_id = isset($_POST['answer_id']) ? absint($_POST['answer_id']) : 0;
        $score_awarded = isset($_POST['score_awarded']) ? (float) wp_unslash($_POST['score_awarded']) : 0;

        if ($answer_id > 0) {
            $answer_table = $wpdb->prefix . 'cbt_answers';
            $attempt_table = $wpdb->prefix . 'cbt_attempts';
            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';

            $is_admin_scope = self::is_admin_scope();

            if ($is_admin_scope) {
                $answer = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT a.attempt_id, att.student_id, att.exam_id, q.points
                         FROM {$answer_table} a
                         INNER JOIN {$question_table} q ON q.id = a.question_id
                         INNER JOIN {$attempt_table} att ON att.id = a.attempt_id
                         WHERE a.id = %d AND q.question_type = 'essay'",
                        $answer_id
                    ),
                    ARRAY_A
                );
            } else {
                $answer = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT a.attempt_id, att.student_id, att.exam_id, q.points
                         FROM {$answer_table} a
                         INNER JOIN {$question_table} q ON q.id = a.question_id
                         INNER JOIN {$attempt_table} att ON att.id = a.attempt_id
                         INNER JOIN {$exam_table} ex ON ex.id = att.exam_id
                         WHERE a.id = %d AND q.question_type = 'essay' AND ex.created_by = %d",
                        $answer_id,
                        get_current_user_id()
                    ),
                    ARRAY_A
                );
            }

            if ($answer) {
                $max_points = (float) $answer['points'];
                $score_awarded = max(0, min($score_awarded, $max_points));

                $wpdb->update(
                    $answer_table,
                    [
                        'score_awarded' => $score_awarded,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $answer_id],
                    ['%f', '%s'],
                    ['%d']
                );

                $attempt_id = (int) $answer['attempt_id'];
                $total_score = (float) $wpdb->get_var(
                    $wpdb->prepare("SELECT COALESCE(SUM(score_awarded),0) FROM {$answer_table} WHERE attempt_id = %d", $attempt_id)
                );

                $wpdb->update(
                    $attempt_table,
                    [
                        'score' => $total_score,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $attempt_id],
                    ['%f', '%s'],
                    ['%d']
                );

                CBT_Cache::invalidate_attempt($attempt_id);
                CBT_Cache::invalidate_user((int) ($answer['student_id'] ?? 0));
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-results&cbt_msg=' . rawurlencode('Essay score updated')));
        exit;
    }

    public static function handle_reset_attempt(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_exam_id, $return_status, $return_kelas, $return_student_keyword, $return_paged): void {
            $args = ['page' => 'cbt-results'];
            if ($return_exam_id > 0) {
                $args['cbt_exam_id'] = $return_exam_id;
            }
            if ($return_status !== '') {
                $args['cbt_attempt_status'] = $return_status;
            }
            if ($return_kelas !== '') {
                $args['cbt_result_kelas'] = $return_kelas;
            }
            if ($return_student_keyword !== '') {
                $args['cbt_student_q'] = $return_student_keyword;
            }
            if ($return_paged > 1) {
                $args['cbt_results_paged'] = $return_paged;
            }
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        };

        if ($attempt_id <= 0) {
            $redirect_with(null, 'Attempt tidak valid.');
        }

        check_admin_referer('cbt_reset_attempt_' . $attempt_id);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();

        if ($is_admin_scope) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status
                     FROM {$attempt_table} a
                     WHERE a.id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d AND e.created_by = %d",
                    $attempt_id,
                    get_current_user_id()
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            $redirect_with(null, 'Attempt tidak ditemukan atau tidak bisa diakses.');
        }

        if ((string) ($attempt['status'] ?? '') !== 'completed') {
            $redirect_with(null, 'Hanya attempt dengan status completed yang bisa di-reset.');
        }

        $now = current_time('mysql');

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$attempt_table}
                 SET status = 'abandoned', updated_at = %s
                 WHERE exam_id = %d
                   AND student_id = %d
                   AND status = 'in_progress'
                   AND id <> %d",
                $now,
                (int) $attempt['exam_id'],
                (int) $attempt['student_id'],
                $attempt_id
            )
        );

        $updated = $wpdb->update(
            $attempt_table,
            [
                'status' => 'in_progress',
                'score' => 0,
                'max_score' => 0,
                'finished_at' => null,
                'duration_seconds' => 0,
                'started_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $attempt_id],
            null,
            ['%d']
        );

        if ($updated === false) {
            $redirect_with(null, 'Gagal melakukan reset attempt.');
        }

        CBT_Cache::invalidate_attempt($attempt_id);
        CBT_Cache::invalidate_user((int) ($attempt['student_id'] ?? 0));
        CBT_UI_State::clear_attempt_state((int) ($attempt['student_id'] ?? 0), $attempt_id);

        $redirect_with('Attempt berhasil di-reset. Siswa dapat lanjut ujian kembali.');
    }

    public static function handle_reset_user_login(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_exam_id, $return_status, $return_kelas, $return_student_keyword, $return_paged): void {
            $args = ['page' => 'cbt-results'];
            if ($return_exam_id > 0) {
                $args['cbt_exam_id'] = $return_exam_id;
            }
            if ($return_status !== '') {
                $args['cbt_attempt_status'] = $return_status;
            }
            if ($return_kelas !== '') {
                $args['cbt_result_kelas'] = $return_kelas;
            }
            if ($return_student_keyword !== '') {
                $args['cbt_student_q'] = $return_student_keyword;
            }
            if ($return_paged > 1) {
                $args['cbt_results_paged'] = $return_paged;
            }
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        };

        if ($attempt_id <= 0) {
            $redirect_with(null, 'Attempt tidak valid.');
        }

        check_admin_referer('cbt_reset_user_login_' . $attempt_id);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();

        if ($is_admin_scope) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id
                     FROM {$attempt_table} a
                     WHERE a.id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d AND e.created_by = %d",
                    $attempt_id,
                    get_current_user_id()
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            $redirect_with(null, 'Attempt tidak ditemukan atau tidak bisa diakses.');
        }

        $student_id = (int) ($attempt['student_id'] ?? 0);
        if ($student_id <= 0) {
            $redirect_with(null, 'Student pada attempt ini tidak valid.');
        }

        $cleared = CBT_Auth::clear_login_session($student_id);
        if (!$cleared) {
            $redirect_with(null, 'Gagal mereset sesi login siswa.');
        }

        CBT_Cache::invalidate_user($student_id);

        $redirect_with('Login siswa berhasil di-reset. Browser lama akan diminta login ulang dan siswa bisa login kembali.');
    }

    public static function handle_bulk_reset_attempts(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_reset_attempts');

        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_exam_id, $return_status, $return_kelas, $return_student_keyword, $return_paged): void {
            $args = ['page' => 'cbt-results'];
            if ($return_exam_id > 0) {
                $args['cbt_exam_id'] = $return_exam_id;
            }
            if ($return_status !== '') {
                $args['cbt_attempt_status'] = $return_status;
            }
            if ($return_kelas !== '') {
                $args['cbt_result_kelas'] = $return_kelas;
            }
            if ($return_student_keyword !== '') {
                $args['cbt_student_q'] = $return_student_keyword;
            }
            if ($return_paged > 1) {
                $args['cbt_results_paged'] = $return_paged;
            }
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        };

        if ($return_status === 'in_progress') {
            $redirect_with(null, 'Filter status in_progress tidak memiliki attempt completed untuk di-reset.');
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $filter_kelas = trim($return_kelas);
        $filter_student_keyword = trim($return_student_keyword);

        $where_parts = ['1=1'];
        $where_params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }
        if ($return_exam_id > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $where_params[] = $return_exam_id;
        }
        if ($filter_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $where_params[] = $filter_kelas;
        }
        if ($filter_student_keyword !== '') {
            $student_like = '%' . $wpdb->esc_like($filter_student_keyword) . '%';
            $where_parts[] = '(u.user_login LIKE %s OR nisn_meta.meta_value LIKE %s)';
            $where_params[] = $student_like;
            $where_params[] = $student_like;
        }
        $where_parts[] = "a.status = 'completed'";
        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);

        $target_sql = "SELECT a.id, a.exam_id, a.student_id
                       FROM {$attempt_table} a
                       INNER JOIN {$exam_table} e ON e.id = a.exam_id
                       INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                       LEFT JOIN (
                           SELECT user_id, MAX(meta_value) AS meta_value
                           FROM {$wpdb->usermeta}
                           WHERE meta_key = 'kode_kelas'
                           GROUP BY user_id
                       ) kelas_meta ON kelas_meta.user_id = u.ID
                       LEFT JOIN (
                           SELECT user_id, MAX(meta_value) AS meta_value
                           FROM {$wpdb->usermeta}
                           WHERE meta_key = 'nisn'
                           GROUP BY user_id
                       ) nisn_meta ON nisn_meta.user_id = u.ID
                       {$where_sql}
                       ORDER BY a.id DESC";
        if (!empty($where_params)) {
            $target_sql = $wpdb->prepare($target_sql, $where_params);
        }

        $target_rows = $wpdb->get_results($target_sql, ARRAY_A);
        if (empty($target_rows)) {
            $redirect_with(null, 'Tidak ada attempt completed sesuai filter yang bisa di-reset.');
        }

        $target_attempt_ids = [];
        $target_pairs = [];
        $affected_user_ids = [];
        foreach ((array) $target_rows as $target_row) {
            $attempt_id = (int) ($target_row['id'] ?? 0);
            $exam_id = (int) ($target_row['exam_id'] ?? 0);
            $student_id = (int) ($target_row['student_id'] ?? 0);
            if ($attempt_id <= 0 || $exam_id <= 0 || $student_id <= 0) {
                continue;
            }

            $target_attempt_ids[$attempt_id] = $attempt_id;
            $affected_user_ids[$student_id] = $student_id;
            $pair_key = $exam_id . ':' . $student_id;
            $target_pairs[$pair_key] = [
                'exam_id' => $exam_id,
                'student_id' => $student_id,
            ];
        }
        if (empty($target_attempt_ids)) {
            $redirect_with(null, 'Tidak ada attempt valid yang bisa di-reset.');
        }

        $now = current_time('mysql');
        $abandoned_total = 0;
        foreach ($target_pairs as $pair) {
            $affected = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$attempt_table}
                     SET status = 'abandoned', updated_at = %s
                     WHERE exam_id = %d
                       AND student_id = %d
                       AND status = 'in_progress'",
                    $now,
                    (int) ($pair['exam_id'] ?? 0),
                    (int) ($pair['student_id'] ?? 0)
                )
            );
            if (is_int($affected) && $affected > 0) {
                $abandoned_total += $affected;
            }
        }

        $reset_total = 0;
        $attempt_id_chunks = array_chunk(array_values($target_attempt_ids), 200);
        foreach ($attempt_id_chunks as $attempt_id_chunk) {
            $clean_chunk = array_values(array_filter(array_map('absint', (array) $attempt_id_chunk)));
            if (empty($clean_chunk)) {
                continue;
            }

            $attempt_ids_sql = implode(',', $clean_chunk);
            $reset_sql = $wpdb->prepare(
                "UPDATE {$attempt_table}
                 SET status = 'in_progress',
                     score = 0,
                     max_score = 0,
                     finished_at = NULL,
                     duration_seconds = 0,
                     started_at = %s,
                     updated_at = %s
                 WHERE id IN ({$attempt_ids_sql})
                   AND status = 'completed'",
                $now,
                $now
            );
            $affected = $wpdb->query($reset_sql);
            if ($affected === false) {
                $redirect_with(null, 'Gagal melakukan reset attempt secara massal.');
            }
            if (is_int($affected) && $affected > 0) {
                $reset_total += $affected;
            }
        }

        if ($reset_total <= 0) {
            $redirect_with(null, 'Tidak ada attempt yang berhasil di-reset.');
        }

        CBT_Cache::invalidate_attempts(array_values($target_attempt_ids));
        CBT_Cache::invalidate_users(array_values($affected_user_ids));
        CBT_UI_State::clear_attempt_states_by_attempt_ids(array_values($target_attempt_ids));

        $message = sprintf('Berhasil reset %d attempt sesuai filter.', $reset_total);
        if ($abandoned_total > 0) {
            $message .= ' ' . sprintf('%d attempt in_progress lama ditutup otomatis.', $abandoned_total);
        }
        $redirect_with($message);
    }

    public static function handle_bulk_force_complete_attempts(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_force_complete_attempts');

        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_exam_id, $return_status, $return_kelas, $return_student_keyword, $return_paged): void {
            $args = ['page' => 'cbt-results'];
            if ($return_exam_id > 0) {
                $args['cbt_exam_id'] = $return_exam_id;
            }
            if ($return_status !== '') {
                $args['cbt_attempt_status'] = $return_status;
            }
            if ($return_kelas !== '') {
                $args['cbt_result_kelas'] = $return_kelas;
            }
            if ($return_student_keyword !== '') {
                $args['cbt_student_q'] = $return_student_keyword;
            }
            if ($return_paged > 1) {
                $args['cbt_results_paged'] = $return_paged;
            }
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        };

        if ($return_status === 'completed') {
            $redirect_with(null, 'Filter status completed tidak memiliki attempt in_progress untuk dipaksa selesai.');
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $filter_kelas = trim($return_kelas);
        $filter_student_keyword = trim($return_student_keyword);

        $where_parts = ['1=1'];
        $where_params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }
        if ($return_exam_id > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $where_params[] = $return_exam_id;
        }
        if ($filter_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $where_params[] = $filter_kelas;
        }
        if ($filter_student_keyword !== '') {
            $student_like = '%' . $wpdb->esc_like($filter_student_keyword) . '%';
            $where_parts[] = '(u.user_login LIKE %s OR nisn_meta.meta_value LIKE %s)';
            $where_params[] = $student_like;
            $where_params[] = $student_like;
        }
        $where_parts[] = "a.status = 'in_progress'";
        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);

        $target_sql = "SELECT a.id
                       FROM {$attempt_table} a
                       INNER JOIN {$exam_table} e ON e.id = a.exam_id
                       INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                       LEFT JOIN (
                           SELECT user_id, MAX(meta_value) AS meta_value
                           FROM {$wpdb->usermeta}
                           WHERE meta_key = 'kode_kelas'
                           GROUP BY user_id
                       ) kelas_meta ON kelas_meta.user_id = u.ID
                       LEFT JOIN (
                           SELECT user_id, MAX(meta_value) AS meta_value
                           FROM {$wpdb->usermeta}
                           WHERE meta_key = 'nisn'
                           GROUP BY user_id
                       ) nisn_meta ON nisn_meta.user_id = u.ID
                       {$where_sql}
                       ORDER BY a.id DESC";
        if (!empty($where_params)) {
            $target_sql = $wpdb->prepare($target_sql, $where_params);
        }

        $target_rows = $wpdb->get_results($target_sql, ARRAY_A);
        if (empty($target_rows)) {
            $redirect_with(null, 'Tidak ada attempt in_progress sesuai filter yang bisa dipaksa selesai.');
        }

        $target_attempt_ids = [];
        foreach ((array) $target_rows as $target_row) {
            $attempt_id = (int) ($target_row['id'] ?? 0);
            if ($attempt_id <= 0) {
                continue;
            }

            $target_attempt_ids[$attempt_id] = $attempt_id;
        }
        if (empty($target_attempt_ids)) {
            $redirect_with(null, 'Tidak ada attempt valid yang bisa dipaksa selesai.');
        }

        $now = current_time('mysql');
        $completed_total = 0;
        foreach (array_values($target_attempt_ids) as $attempt_id) {
            $completion_result = CBT_REST::finalize_attempt_completion((int) $attempt_id, $now);
            if (is_wp_error($completion_result)) {
                continue;
            }

            $completed_total++;
        }

        if ($completed_total <= 0) {
            $redirect_with(null, 'Tidak ada attempt yang berhasil dipaksa selesai.');
        }

        $failed_total = max(0, count($target_attempt_ids) - $completed_total);
        if ($failed_total > 0) {
            $redirect_with(sprintf('Berhasil memaksa %d attempt in_progress menjadi completed.', $completed_total), sprintf('%d attempt gagal diselesaikan. Coba ulang lagi untuk attempt yang tersisa.', $failed_total));
        }

        $redirect_with(sprintf('Berhasil memaksa %d attempt in_progress menjadi completed.', $completed_total));
    }

    public static function handle_import_users(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_import_token'])) : '';
        if ($token !== '') {
            self::continue_user_import($token);
        }

        check_admin_referer('cbt_import_users');

        if (!isset($_FILES['user_file']) || !is_array($_FILES['user_file'])) {
            self::redirect_user_import_with_error('File tidak ditemukan.');
        }

        $file = $_FILES['user_file'];
        $tmp_path = $file['tmp_name'] ?? '';
        $original_name = $file['name'] ?? '';
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error_code !== UPLOAD_ERR_OK || !$tmp_path) {
            self::redirect_user_import_with_error('Upload file gagal.');
        }

        $extension = strtolower((string) pathinfo((string) $original_name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            self::redirect_user_import_with_error('Format file harus CSV atau XLSX.');
        }

        $parsed = ($extension === 'xlsx')
            ? self::parse_user_xlsx($tmp_path)
            : self::parse_user_csv($tmp_path);
        if (is_wp_error($parsed)) {
            self::redirect_user_import_with_error($parsed->get_error_message());
        }
        if (!is_array($parsed) || empty($parsed)) {
            self::redirect_user_import_with_error('Tidak ada data user yang bisa diproses.');
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $state_key = self::get_user_import_state_key($token);
        $rows_key = self::get_user_import_rows_key($token);
        $state = [
            'total' => count($parsed),
            'offset' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'user_id' => get_current_user_id(),
            'started_at' => time(),
        ];

        $rows_saved = set_transient($rows_key, array_values($parsed), 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient($state_key, $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Gagal menyiapkan sesi import. Coba gunakan file CSV atau kurangi ukuran batch.');
        }
        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-user-import',
            'cbt_import_token' => $token,
        ], admin_url('admin.php')));
        exit;
    }

    private static function prepare_runtime_for_bulk_user_import(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');
    }

    private static function get_user_import_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_user_import_batch_size', 220);
        if ($batch_size < 25) {
            return 25;
        }
        if ($batch_size > 500) {
            return 500;
        }

        return $batch_size;
    }

    private static function get_user_import_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_user_import_batch_max_seconds', 14.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function get_user_import_state_key(string $token): string
    {
        return 'cbt_user_import_' . $token;
    }

    private static function get_user_import_rows_key(string $token): string
    {
        return 'cbt_user_import_rows_' . $token;
    }

    private static function clear_user_import_transients(string $token): void
    {
        delete_transient(self::get_user_import_state_key($token));
        delete_transient(self::get_user_import_rows_key($token));
    }

    private static function get_user_import_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_user_import_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function continue_user_import(string $token): void
    {
        $state_key = self::get_user_import_state_key($token);
        $rows_key = self::get_user_import_rows_key($token);
        $state = get_transient($state_key);
        if (!is_array($state)) {
            self::redirect_user_import_with_error('Sesi import berakhir. Silakan upload ulang file import.');
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Sesi import tidak valid untuk user saat ini.');
        }

        $rows = get_transient($rows_key);
        if (!is_array($rows) || empty($rows)) {
            // Backward compatibility: older state stored rows in the same transient.
            if (isset($state['rows']) && is_array($state['rows']) && !empty($state['rows'])) {
                $rows = array_values($state['rows']);
                set_transient($rows_key, $rows, 12 * HOUR_IN_SECONDS);
                unset($state['rows']);
                set_transient($state_key, $state, 12 * HOUR_IN_SECONDS);
            }
        }
        if (!is_array($rows) || empty($rows)) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Data batch import tidak ditemukan. Silakan upload ulang file.');
        }

        $rows = array_values($rows);
        $total = isset($state['total']) ? (int) $state['total'] : count($rows);
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
        $created = isset($state['created']) ? (int) $state['created'] : 0;
        $updated = isset($state['updated']) ? (int) $state['updated'] : 0;
        $failed = isset($state['failed']) ? (int) $state['failed'] : 0;

        if ($total <= 0 || empty($rows)) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Data import user kosong.');
        }

        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset > $total) {
            $offset = $total;
        }

        $batch_size = self::get_user_import_batch_size();
        $max_batch_seconds = self::get_user_import_max_batch_seconds();
        $target_end = min($offset + $batch_size, $total);
        $end = $offset;
        $batch_started_at = microtime(true);
        $import_lookup = self::build_user_import_lookup($rows, $offset, $target_end);
        $cache_invalidation_prev = null;
        if (function_exists('wp_suspend_cache_invalidation')) {
            $cache_invalidation_prev = wp_suspend_cache_invalidation(true);
        }

        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? $rows[$index] : [];

            try {
                $result = self::upsert_user_from_row($row, $import_lookup);
            } catch (Throwable $exception) {
                $result = 'failed';
            }

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $failed++;
            }

            $end = $index + 1;
            if (($end - $offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }
        if ($cache_invalidation_prev !== null && function_exists('wp_suspend_cache_invalidation')) {
            wp_suspend_cache_invalidation((bool) $cache_invalidation_prev);
        }

        if ($end < $offset) {
            $end = $offset;
        }
        $state['offset'] = $end;
        $state['created'] = $created;
        $state['updated'] = $updated;
        $state['failed'] = $failed;

        if ($end < $total) {
            $state_saved = set_transient($state_key, $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                self::clear_user_import_transients($token);
                self::redirect_user_import_with_error('Gagal menyimpan progres import. Silakan coba import ulang (disarankan format CSV).');
            }
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-user-import',
                'cbt_import_token' => $token,
            ], admin_url('admin.php')));
            exit;
        }

        self::clear_user_import_transients($token);

        $msg = sprintf(
            'Import selesai. Total: %d, Created: %d, Updated: %d, Failed: %d',
            $total,
            $created,
            $updated,
            $failed
        );

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode($msg)));
        exit;
    }

    public static function handle_download_user_template(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_user_template');

        $file = CBT_EXAM_SYSTEM_PATH . 'templates/user-import-template.csv';
        if (!file_exists($file)) {
            wp_die('Template file not found.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-user-import-template.csv"');
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
        exit;
    }

    public static function handle_download_user_template_xlsx(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_user_template_xlsx');

        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            wp_die('Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            [
                ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'foto'],
                ['Budi Santoso', '', '1000000001', 'budi.santoso', 'Password123', 'siswa', 'X-IPA-1', 'LAB-1', 'Islam', ''],
                ['Siti Aminah', 'siti@student.sch.id', '', 'siti.aminah', 'Password123', 'siswa', 'X-IPA-1', 'LAB-1', 'Islam', ''],
                ['Pak Andi', 'andi@school.sch.id', '', 'andi.guru', 'Password123', 'guru', '', '', '', ''],
            ],
            null,
            'A1'
        );

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cbt-user-import-template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private static function parse_user_csv(string $tmp_path)
    {
        $handle = fopen($tmp_path, 'rb');
        if ($handle === false) {
            return new WP_Error('csv_open_failed', 'Gagal membuka file CSV.');
        }

        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $header = self::normalize_user_import_header($header);
        $header_check = self::validate_user_import_header($header);
        if (is_wp_error($header_check)) {
            fclose($handle);
            return $header_check;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        if (empty($rows)) {
            return new WP_Error('csv_no_data', 'Tidak ada data user di CSV.');
        }

        return $rows;
    }

    private static function parse_user_xlsx(string $tmp_path)
    {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return new WP_Error(
                'xlsx_library_missing',
                'Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.'
            );
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp_path);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }
            $spreadsheet = $reader->load($tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $raw_rows = $sheet->toArray('', false, false, false);
        } catch (Throwable $exception) {
            return new WP_Error('xlsx_read_failed', 'Gagal membaca file XLSX.');
        }

        if (!is_array($raw_rows) || empty($raw_rows)) {
            return new WP_Error('xlsx_empty', 'File XLSX kosong.');
        }

        $header = array_shift($raw_rows);
        if (!is_array($header)) {
            return new WP_Error('xlsx_header_invalid', 'Header XLSX tidak valid.');
        }

        $header = self::normalize_user_import_header($header);
        $header_check = self::validate_user_import_header($header);
        if (is_wp_error($header_check)) {
            return $header_check;
        }

        $rows = [];
        foreach ($raw_rows as $data) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            return new WP_Error('xlsx_no_data', 'Tidak ada data user di XLSX.');
        }

        return $rows;
    }

    private static function normalize_user_import_header(array $header): array
    {
        return array_map(static function ($item) {
            $clean = trim((string) $item);
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);
            return strtolower($clean);
        }, $header);
    }

    private static function validate_user_import_header(array $header)
    {
        $required = ['name', 'role'];
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                return new WP_Error('import_header_invalid', 'Header file tidak valid. Gunakan template resmi.');
            }
        }
        $has_username_password_pair = in_array('username', $header, true) && in_array('password', $header, true);
        $has_combined_username_password = in_array('usernamepassword', $header, true) || in_array('username_password', $header, true);
        if (!$has_username_password_pair && !$has_combined_username_password) {
            return new WP_Error('import_header_invalid', 'Header harus punya kolom username + password (atau kolom gabungan usernamepassword).');
        }
        if (!in_array('email', $header, true) && !in_array('nisn', $header, true)) {
            return new WP_Error('import_header_invalid', 'Header harus memiliki salah satu kolom: email atau nisn.');
        }

        return true;
    }

    private static function upsert_user_from_row(array $row, array &$import_lookup = []): string
    {
        $name = sanitize_text_field($row['name'] ?? '');
        $raw_email = trim((string) ($row['email'] ?? ''));
        $email = sanitize_email($raw_email);
        $nisn = preg_replace('/\D+/', '', (string) ($row['nisn'] ?? ''));
        $raw_username = trim((string) ($row['username'] ?? ''));
        $raw_password = trim((string) ($row['password'] ?? ''));
        $raw_combined_username_password = trim((string) ($row['usernamepassword'] ?? ($row['username_password'] ?? '')));

        if (($raw_username === '' || $raw_password === '') && $raw_combined_username_password !== '') {
            $combined_parts = preg_split('/\s+|[:;|]/', $raw_combined_username_password, 2);
            if (is_array($combined_parts) && !empty($combined_parts)) {
                if ($raw_username === '') {
                    $raw_username = trim((string) ($combined_parts[0] ?? ''));
                }
                if ($raw_password === '') {
                    $raw_password = trim((string) ($combined_parts[1] ?? ''));
                }
            }
        }

        $role_raw = strtolower(sanitize_text_field($row['role'] ?? 'siswa'));
        $role = self::map_import_role($role_raw);
        $kode_kelas = sanitize_text_field($row['kode_kelas'] ?? '');
        $kode_ruang = sanitize_text_field($row['kode_ruang'] ?? '');
        $agama = sanitize_text_field($row['agama'] ?? '');
        $foto = esc_url_raw($row['foto'] ?? '');

        if (!is_email($email) && $nisn !== '') {
            $email = sanitize_email($nisn . '@student.sch.id');
        }

        if (!is_email($email)) {
            return 'failed';
        }

        $username = sanitize_user($raw_username, true);
        if ($username === '') {
            $parts = explode('@', $email);
            $username = sanitize_user((string) ($parts[0] ?? ''), true);
        }

        if ($username === '') {
            return 'failed';
        }

        $password = $raw_password !== '' ? (string) $raw_password : wp_generate_password(12, true, true);
        $user_id = self::resolve_user_import_existing_id($email, $username, $import_lookup);

        if ($user_id > 0) {
            $existing_display_name = (string) ($import_lookup['by_id'][$user_id]['display_name'] ?? '');
            $display_name = $name !== '' ? $name : $existing_display_name;
            if ($display_name === '') {
                $display_name = $username;
            }

            $update_result = wp_update_user([
                'ID' => $user_id,
                'display_name' => $display_name,
                'role' => $role,
            ]);
            if (is_wp_error($update_result)) {
                return 'failed';
            }
            self::register_user_import_lookup($import_lookup, $user_id, $email, $username, $display_name);

            if ($raw_password !== '') {
                wp_set_password($password, $user_id);
                update_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, $password);
            }

            if ($kode_kelas !== '') {
                update_user_meta($user_id, 'kode_kelas', $kode_kelas);
            }
            if ($kode_ruang !== '') {
                update_user_meta($user_id, 'kode_ruang', $kode_ruang);
            }
            if ($agama !== '') {
                update_user_meta($user_id, 'agama', $agama);
            }
            if ($foto !== '') {
                update_user_meta($user_id, 'foto', $foto);
            } elseif (self::is_student_role($role)) {
                $existing_foto = trim((string) get_user_meta($user_id, 'foto', true));
                if ($existing_foto === '') {
                    update_user_meta($user_id, 'foto', self::get_default_student_photo_url());
                }
            }
            if ($nisn !== '') {
                update_user_meta($user_id, 'nisn', $nisn);
            }

            return 'updated';
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name !== '' ? $name : $username,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            return 'failed';
        }

        if ($kode_kelas !== '') {
            update_user_meta((int) $user_id, 'kode_kelas', $kode_kelas);
        }
        if ($kode_ruang !== '') {
            update_user_meta((int) $user_id, 'kode_ruang', $kode_ruang);
        }
        if ($agama !== '') {
            update_user_meta((int) $user_id, 'agama', $agama);
        }
        $foto = self::resolve_student_default_photo($role, $foto);
        if ($foto !== '') {
            update_user_meta((int) $user_id, 'foto', $foto);
        }
        if ($nisn !== '') {
            update_user_meta((int) $user_id, 'nisn', $nisn);
        }
        update_user_meta((int) $user_id, self::USER_META_PLAIN_PASSWORD, $password);
        self::register_user_import_lookup($import_lookup, (int) $user_id, $email, $username, $name !== '' ? $name : $username);

        return 'created';
    }

    /**
     * @return array{by_email:array<string,int>,by_login:array<string,int>,by_id:array<int,array{display_name:string}>}
     */
    private static function build_user_import_lookup(array $rows, int $offset, int $target_end): array
    {
        $lookup = [
            'by_email' => [],
            'by_login' => [],
            'by_id' => [],
        ];
        if ($target_end <= $offset) {
            return $lookup;
        }

        $emails = [];
        $logins = [];
        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? (array) $rows[$index] : [];
            $identity = self::extract_user_import_identity($row);
            if (($identity['email'] ?? '') !== '') {
                $email = (string) $identity['email'];
                $emails[self::normalize_user_import_lookup_key($email)] = $email;
            }
            if (($identity['username'] ?? '') !== '') {
                $username = (string) $identity['username'];
                $logins[self::normalize_user_import_lookup_key($username)] = $username;
            }
        }

        if (empty($emails) && empty($logins)) {
            return $lookup;
        }

        global $wpdb;
        $where_clauses = [];
        $params = [];
        if (!empty($emails)) {
            $email_placeholders = implode(',', array_fill(0, count($emails), '%s'));
            $where_clauses[] = "user_email IN ({$email_placeholders})";
            $params = array_merge($params, array_values($emails));
        }
        if (!empty($logins)) {
            $login_placeholders = implode(',', array_fill(0, count($logins), '%s'));
            $where_clauses[] = "user_login IN ({$login_placeholders})";
            $params = array_merge($params, array_values($logins));
        }

        if (empty($where_clauses)) {
            return $lookup;
        }

        $sql = "SELECT ID, user_email, user_login, display_name
                FROM {$wpdb->users}
                WHERE " . implode(' OR ', $where_clauses);
        $prepared_sql = $wpdb->prepare($sql, $params);
        $existing_rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        if (!is_array($existing_rows)) {
            return $lookup;
        }

        foreach ($existing_rows as $existing_row) {
            $row = (array) $existing_row;
            self::register_user_import_lookup(
                $lookup,
                isset($row['ID']) ? (int) $row['ID'] : 0,
                (string) ($row['user_email'] ?? ''),
                (string) ($row['user_login'] ?? ''),
                (string) ($row['display_name'] ?? '')
            );
        }

        return $lookup;
    }

    /**
     * @return array{email:string,username:string}
     */
    private static function extract_user_import_identity(array $row): array
    {
        $raw_email = trim((string) ($row['email'] ?? ''));
        $email = sanitize_email($raw_email);
        $nisn = preg_replace('/\D+/', '', (string) ($row['nisn'] ?? ''));
        if (!is_email($email) && $nisn !== '') {
            $email = sanitize_email($nisn . '@student.sch.id');
        }
        if (!is_email($email)) {
            $email = '';
        }

        $raw_username = trim((string) ($row['username'] ?? ''));
        $raw_combined_username_password = trim((string) ($row['usernamepassword'] ?? ($row['username_password'] ?? '')));
        if ($raw_username === '' && $raw_combined_username_password !== '') {
            $combined_parts = preg_split('/\s+|[:;|]/', $raw_combined_username_password, 2);
            if (is_array($combined_parts) && !empty($combined_parts)) {
                $raw_username = trim((string) ($combined_parts[0] ?? ''));
            }
        }

        $username = sanitize_user($raw_username, true);
        if ($username === '' && $email !== '') {
            $email_parts = explode('@', $email);
            $username = sanitize_user((string) ($email_parts[0] ?? ''), true);
        }
        if ($username === '') {
            $username = '';
        }

        return [
            'email' => $email,
            'username' => $username,
        ];
    }

    private static function resolve_user_import_existing_id(string $email, string $username, array &$lookup): int
    {
        $email_key = self::normalize_user_import_lookup_key($email);
        if ($email_key !== '' && isset($lookup['by_email'][$email_key])) {
            return (int) $lookup['by_email'][$email_key];
        }

        $username_key = self::normalize_user_import_lookup_key($username);
        if ($username_key !== '' && isset($lookup['by_login'][$username_key])) {
            return (int) $lookup['by_login'][$username_key];
        }

        $existing = false;
        if ($email !== '') {
            $existing = get_user_by('email', $email);
        }
        if (!($existing instanceof WP_User) && $username !== '') {
            $existing = get_user_by('login', $username);
        }
        if ($existing instanceof WP_User) {
            $resolved_id = (int) $existing->ID;
            self::register_user_import_lookup(
                $lookup,
                $resolved_id,
                (string) $existing->user_email,
                (string) $existing->user_login,
                (string) $existing->display_name
            );
            return $resolved_id;
        }

        return 0;
    }

    private static function register_user_import_lookup(
        array &$lookup,
        int $user_id,
        string $email,
        string $username,
        string $display_name = ''
    ): void {
        if ($user_id <= 0) {
            return;
        }

        if (!isset($lookup['by_email']) || !is_array($lookup['by_email'])) {
            $lookup['by_email'] = [];
        }
        if (!isset($lookup['by_login']) || !is_array($lookup['by_login'])) {
            $lookup['by_login'] = [];
        }
        if (!isset($lookup['by_id']) || !is_array($lookup['by_id'])) {
            $lookup['by_id'] = [];
        }

        $lookup['by_id'][$user_id] = [
            'display_name' => sanitize_text_field($display_name),
        ];

        $email_key = self::normalize_user_import_lookup_key($email);
        if ($email_key !== '') {
            $lookup['by_email'][$email_key] = $user_id;
        }
        $username_key = self::normalize_user_import_lookup_key($username);
        if ($username_key !== '') {
            $lookup['by_login'][$username_key] = $user_id;
        }
    }

    private static function normalize_user_import_lookup_key(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function map_import_role(string $raw_role): string
    {
        switch ($raw_role) {
            case 'admin':
            case 'administrator':
            case 'admin_cbt':
                return 'administrator';

            case 'guru':
            case 'guru_cbt':
            case 'teacher':
            case 'editor':
                return 'guru_cbt';

            case 'siswa':
            case 'siswa_cbt':
            case 'student':
            case 'subscriber':
            default:
                return 'siswa_cbt';
        }
    }

    private static function humanize_role(string $role): string
    {
        switch ($role) {
            case 'administrator':
            case 'admin_cbt':
                return 'admin';
            case 'guru_cbt':
            case 'editor':
            case 'teacher':
                return 'guru';
            case 'siswa_cbt':
            case 'subscriber':
            case 'student':
                return 'siswa';
            default:
                return $role;
        }
    }

    private static function role_for_form(string $role): string
    {
        $normalized = self::humanize_role($role);
        if (in_array($normalized, ['admin', 'guru', 'siswa'], true)) {
            return $normalized;
        }

        return 'siswa';
    }

    private static function is_student_role(string $role): bool
    {
        $normalized = strtolower(trim($role));
        return in_array($normalized, ['siswa', 'siswa_cbt', 'subscriber', 'student'], true);
    }

    private static function get_default_student_photo_url(): string
    {
        return esc_url_raw(CBT_EXAM_SYSTEM_URL . self::DEFAULT_STUDENT_PHOTO_RELATIVE_PATH);
    }

    private static function resolve_student_default_photo(string $role, string $foto): string
    {
        $clean_foto = esc_url_raw(trim($foto));
        if ($clean_foto !== '') {
            return $clean_foto;
        }
        if (!self::is_student_role($role)) {
            return '';
        }

        return self::get_default_student_photo_url();
    }

    private static function normalize_standard_list_per_page(int $requested): int
    {
        $allowed = [20, 40, 60, 80, 100];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    private static function cache_namespace_group_meta(): array
    {
        return [
            '__global__' => [
                'label' => '__global__ | versi global semua cache CBT',
                'description' => 'Namespace global untuk versi induk semua cache CBT. Jika ini berubah, hampir semua cache CBT akan ikut dibangun ulang pada request berikutnya.',
            ],
            'attempt' => [
                'label' => 'attempt | cache per attempt ujian',
                'description' => 'Cache yang terkait satu attempt ujian tertentu, misalnya payload hasil, state attempt, dan data yang perlu sinkron per peserta per attempt.',
            ],
            'catalog' => [
                'label' => 'catalog | daftar exam, mapel, token global',
                'description' => 'Cache katalog global yang dipakai lintas user, seperti daftar exam, subject, dan metadata global. Ini untuk listing/metadata umum, bukan payload isi soal.',
            ],
            'exam' => [
                'label' => 'exam | cache spesifik satu exam',
                'description' => 'Cache yang hanya berlaku untuk satu exam, termasuk payload soal statis saat frontend/backend ambil soal, setting exam, dan data turunan yang scope-nya satu exam.',
            ],
            'ui_state' => [
                'label' => 'ui_state | preferensi UI dan resume ujian',
                'description' => 'Cache untuk state UI frontend, seperti preferensi tampilan, posisi soal terakhir, dan tanda ragu-ragu yang disimpan lintas browser.',
            ],
            'user' => [
                'label' => 'user | cache spesifik satu user',
                'description' => 'Cache yang terkait satu user tertentu, misalnya daftar exam sesuai hak akses user, profil ringkas, atau state yang scope-nya per akun.',
            ],
        ];
    }

    private static function normalize_user_list_per_page(int $requested): int
    {
        $allowed = [20, 50, 100, 150, 200];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_exam_card_students(string $search, string $kode_kelas, string $kode_ruang): array
    {
        $args = self::build_cbt_user_query_args($search, 'siswa', $kode_kelas, $kode_ruang);
        $args['orderby'] = 'display_name';
        $args['order'] = 'ASC';
        $args['fields'] = 'all';

        $users = get_users($args);
        if (!is_array($users)) {
            return [];
        }

        $rows = [];
        foreach ($users as $user) {
            if (!($user instanceof WP_User)) {
                continue;
            }

            $user_id = (int) $user->ID;
            if ($user_id <= 0) {
                continue;
            }

            $display_name = trim((string) $user->display_name);
            if ($display_name === '') {
                $display_name = (string) $user->user_login;
            }
            $role = isset($user->roles[0]) ? (string) $user->roles[0] : '';
            $foto = esc_url_raw((string) get_user_meta($user_id, 'foto', true));
            if ($foto === '' && self::is_student_role($role)) {
                $foto = self::get_default_student_photo_url();
            }

            $rows[] = [
                'id' => $user_id,
                'username' => (string) $user->user_login,
                'name' => $display_name,
                'nisn' => trim((string) get_user_meta($user_id, 'nisn', true)),
                'kelas' => trim((string) get_user_meta($user_id, 'kode_kelas', true)),
                'ruang' => trim((string) get_user_meta($user_id, 'kode_ruang', true)),
                'foto' => $foto,
                'password' => trim((string) get_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, true)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_exam_card_schedule_rows(string $kelas): array
    {
        global $wpdb;

        $kelas_normalized = strtoupper(sanitize_text_field(trim($kelas)));

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, starts_at, ends_at, duration_minutes, target_kelas
                 FROM {$exam_table}
                 WHERE status = %s
                 ORDER BY starts_at ASC, id ASC",
                'published'
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $schedules = [];
        foreach ($rows as $row) {
            $exam = is_array($row) ? $row : [];
            $target_kelas = self::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
            if ($kelas_normalized !== '' && !empty($target_kelas) && !in_array($kelas_normalized, $target_kelas, true)) {
                continue;
            }
            $schedules[] = $exam;
        }

        return $schedules;
    }

    /**
     * @return array{title:string,day:string,time:string,duration:string}
     */
    private static function format_exam_card_schedule_line(array $exam): array
    {
        $title = trim(sanitize_text_field((string) ($exam['title'] ?? '')));
        if ($title === '') {
            $title = 'Exam';
        }

        $day_label = self::format_exam_card_day_label((string) ($exam['starts_at'] ?? ''), (string) ($exam['ends_at'] ?? ''));
        $duration_label = self::format_exam_card_duration_label(
            isset($exam['duration_minutes']) ? (int) $exam['duration_minutes'] : 0,
            (string) ($exam['starts_at'] ?? ''),
            (string) ($exam['ends_at'] ?? '')
        );
        $start_time_label = self::format_exam_card_time_only((string) ($exam['starts_at'] ?? ''));
        $end_time_label = self::format_exam_card_end_time(
            (string) ($exam['starts_at'] ?? ''),
            (string) ($exam['ends_at'] ?? ''),
            isset($exam['duration_minutes']) ? (int) $exam['duration_minutes'] : 0
        );
        if ($start_time_label !== '-' && $end_time_label !== '-') {
            $time_label = $start_time_label . ' - ' . $end_time_label;
        } elseif ($start_time_label !== '-') {
            $time_label = $start_time_label;
        } elseif ($end_time_label !== '-') {
            $time_label = $end_time_label;
        } else {
            $time_label = 'Jadwal belum diatur';
        }

        return [
            'title' => $title,
            'day' => $day_label,
            'time' => $time_label,
            'duration' => $duration_label,
        ];
    }

    private static function format_exam_card_day_label(string $starts_at, string $ends_at): string
    {
        $start_ts = strtotime(trim($starts_at));
        $end_ts = strtotime(trim($ends_at));
        $active_ts = false;

        if ($start_ts !== false) {
            $active_ts = $start_ts;
        } elseif ($end_ts !== false) {
            $active_ts = $end_ts;
        }
        if ($active_ts === false) {
            return '-';
        }

        $weekday = self::translate_exam_card_weekday((string) wp_date('l', $active_ts));
        $date_label = self::format_exam_card_indonesian_date((int) $active_ts);

        return $weekday . ' , ' . $date_label;
    }

    private static function translate_exam_card_weekday(string $weekday): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[$weekday] ?? sanitize_text_field($weekday);
    }

    private static function format_exam_card_indonesian_date(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        $month_map = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $day = (int) wp_date('j', $timestamp);
        $month = (int) wp_date('n', $timestamp);
        $year = (int) wp_date('Y', $timestamp);
        $month_label = $month_map[$month] ?? (string) wp_date('F', $timestamp);

        return (string) $day . ' ' . $month_label . ' ' . (string) $year;
    }

    private static function format_exam_card_duration_label(int $duration_minutes, string $starts_at, string $ends_at): string
    {
        if ($duration_minutes > 0) {
            return (string) $duration_minutes . ' menit';
        }

        $start_ts = strtotime(trim($starts_at));
        $end_ts = strtotime(trim($ends_at));
        if ($start_ts !== false && $end_ts !== false && $end_ts > $start_ts) {
            $minutes = (int) round(($end_ts - $start_ts) / 60);
            if ($minutes > 0) {
                return (string) $minutes . ' menit';
            }
        }

        return '-';
    }

    private static function format_exam_card_time_only(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return sanitize_text_field($value);
        }

        return wp_date('H:i', $timestamp);
    }

    private static function format_exam_card_end_time(string $starts_at, string $ends_at, int $duration_minutes): string
    {
        $start_ts = strtotime(trim($starts_at));
        if ($start_ts !== false && $duration_minutes > 0) {
            return wp_date('H:i', $start_ts + ($duration_minutes * 60));
        }

        $end_ts = strtotime(trim($ends_at));
        if ($end_ts !== false) {
            return wp_date('H:i', $end_ts);
        }

        return '-';
    }

    private static function generate_exam_card_password(): string
    {
        try {
            return (string) random_int(100000, 999999);
        } catch (Throwable $exception) {
            return str_pad((string) wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }
    }

    /**
     * @return array{school_name:string,logo_url:string}
     */
    private static function get_setup_branding_print_context(): array
    {
        $branding = self::get_setup_branding_settings();
        $school_name = trim((string) ($branding['school_name'] ?? ''));

        $logo_url = '';
        $logo_attachment_id = (int) ($branding['logo_attachment_id'] ?? 0);
        if ($logo_attachment_id > 0) {
            $resolved_logo_url = wp_get_attachment_image_url($logo_attachment_id, 'medium');
            if (is_string($resolved_logo_url)) {
                $logo_url = $resolved_logo_url;
            }
        }

        return [
            'school_name' => $school_name,
            'logo_url' => $logo_url,
        ];
    }

    private static function build_cbt_user_query_args(
        string $search = '',
        string $role_filter = '',
        string $kode_kelas = '',
        string $kode_ruang = ''
    ): array {
        $args = [
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        $role_filter = strtolower(trim($role_filter));
        if ($role_filter === 'admin') {
            $args['role__in'] = ['administrator', 'admin_cbt'];
        } elseif ($role_filter === 'guru') {
            $args['role__in'] = ['guru_cbt', 'editor'];
        } elseif ($role_filter === 'siswa') {
            $args['role__in'] = ['siswa_cbt', 'subscriber'];
        } else {
            $args['role__in'] = ['administrator', 'guru_cbt', 'siswa_cbt', 'editor', 'subscriber'];
        }

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $meta_query = [];
        $kode_kelas = trim($kode_kelas);
        $kode_ruang = trim($kode_ruang);
        if ($kode_kelas !== '') {
            $meta_query[] = [
                'key' => 'kode_kelas',
                'value' => $kode_kelas,
                'compare' => '=',
            ];
        }
        if ($kode_ruang !== '') {
            $meta_query[] = [
                'key' => 'kode_ruang',
                'value' => $kode_ruang,
                'compare' => '=',
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * @return array{items: WP_User[], total: int, total_pages: int, page: int, per_page: int}
     */
    private static function get_cbt_users_paginated(
        string $search = '',
        string $role_filter = '',
        string $kode_kelas = '',
        string $kode_ruang = '',
        int $per_page = 20,
        int $page = 1
    ): array {
        $per_page = self::normalize_user_list_per_page($per_page);
        $page = max(1, $page);

        $args = self::build_cbt_user_query_args($search, $role_filter, $kode_kelas, $kode_ruang);
        $args['number'] = $per_page;
        $args['offset'] = ($page - 1) * $per_page;
        $args['count_total'] = true;

        $query = new WP_User_Query($args);
        $items = $query->get_results();
        $total = isset($query->total_users) ? (int) $query->total_users : 0;
        $total_pages = max(1, (int) ceil($total / $per_page));

        if ($total > 0 && $page > $total_pages) {
            $page = $total_pages;
            $args['offset'] = ($page - 1) * $per_page;
            $query = new WP_User_Query($args);
            $items = $query->get_results();
        }

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
            'total_pages' => $total_pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * @return int[]
     */
    private static function get_cbt_user_ids(
        string $search = '',
        string $role_filter = '',
        string $kode_kelas = '',
        string $kode_ruang = ''
    ): array {
        $args = self::build_cbt_user_query_args($search, $role_filter, $kode_kelas, $kode_ruang);
        $args['fields'] = 'ids';
        $user_ids = get_users($args);
        if (!is_array($user_ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $user_ids), static function ($item): bool {
            return $item > 0;
        }));
    }

    /**
     * @return string[]
     */
    private static function get_distinct_user_meta_values(string $meta_key): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND meta_value IS NOT NULL
               AND TRIM(meta_value) <> ''
             ORDER BY meta_value ASC",
            $meta_key
        );

        $rows = $wpdb->get_col($query);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_text_field', $rows), static function ($value) {
            return $value !== '';
        }));
    }

    private static function redirect_user_import_with_error(string $message): void
    {
        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_err=' . rawurlencode($message)));
        exit;
    }

    private static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    private static function can_manage_subjects(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_subjects');
    }

    private static function can_manage_exams(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_exams');
    }

    private static function can_manage_users(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_users');
    }

    /**
     * @return array{school_name:string,logo_attachment_id:int}
     */
    private static function get_setup_branding_settings(): array
    {
        $raw = get_option(self::SETUP_BRANDING_OPTION, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $school_name = isset($raw['school_name'])
            ? trim(sanitize_text_field((string) $raw['school_name']))
            : '';
        $logo_attachment_id = isset($raw['logo_attachment_id']) ? absint($raw['logo_attachment_id']) : 0;
        if ($logo_attachment_id > 0 && !wp_attachment_is_image($logo_attachment_id)) {
            $logo_attachment_id = 0;
        }

        return [
            'school_name' => $school_name,
            'logo_attachment_id' => $logo_attachment_id,
        ];
    }

    /**
     * @return string[]
     */
    private static function cbt_data_tables(wpdb $wpdb): array
    {
        $prefix = $wpdb->prefix;

        return [
            $prefix . 'cbt_answers',
            $prefix . 'cbt_attempts',
            $prefix . 'cbt_options',
            $prefix . 'cbt_question_essay',
            $prefix . 'cbt_question_short_answer',
            $prefix . 'cbt_question_true_false',
            $prefix . 'cbt_question_multiple_answer',
            $prefix . 'cbt_question_multiple_choice',
            $prefix . 'cbt_questions',
            $prefix . 'cbt_exams',
            $prefix . 'cbt_subjects',
        ];
    }

    private static function reset_cbt_global_token_options(): void
    {
        delete_option('cbt_global_exam_token_value');
        delete_option('cbt_global_exam_token_generated_at');
        delete_option('cbt_global_exam_token_refresh_minutes');
        delete_option('cbt_global_exam_token_frontend_auto_apply');
        delete_option(self::SETUP_BRANDING_OPTION);
        delete_transient('cbt_exam_priority_window_until');
    }

    /**
     * @return int[]
     */
    private static function collect_cbt_user_ids_for_reset(): array
    {
        $roles_to_purge = ['administrator', 'admin_cbt', 'guru_cbt', 'editor', 'teacher', 'siswa_cbt', 'subscriber', 'student'];
        $user_ids = [];

        foreach ($roles_to_purge as $role) {
            $ids = get_users([
                'role' => $role,
                'fields' => 'ids',
            ]);
            if (!is_array($ids)) {
                continue;
            }

            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $user_ids[$id] = $id;
                }
            }
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && isset($user_ids[$current_user_id])) {
            unset($user_ids[$current_user_id]);
        }

        return array_values($user_ids);
    }

    private static function delete_cbt_users_for_reset(): int
    {
        $user_ids = self::collect_cbt_user_ids_for_reset();
        if (empty($user_ids)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $deleted_count = 0;
        foreach ($user_ids as $user_id) {
            $deleted = wp_delete_user((int) $user_id);
            if ($deleted) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * @return string[]
     */
    private static function split_target_kelas_csv($raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $parts[] = trim((string) $item);
            }
        } else {
            $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', (string) $raw);
            $parts = array_map('trim', explode(',', $raw));
        }
        $items = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized = strtoupper(sanitize_text_field($part));
            if ($normalized === '') {
                continue;
            }
            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }

    private static function normalize_target_kelas_csv($raw): string
    {
        return implode(',', self::split_target_kelas_csv($raw));
    }

    /**
     * @return array<string,string>
     */
    private static function question_type_detail_tables(): array
    {
        global $wpdb;

        return [
            'multiple_choice' => $wpdb->prefix . 'cbt_question_multiple_choice',
            'multiple_answer' => $wpdb->prefix . 'cbt_question_multiple_answer',
            'true_false' => $wpdb->prefix . 'cbt_question_true_false',
            'short_answer' => $wpdb->prefix . 'cbt_question_short_answer',
            'essay' => $wpdb->prefix . 'cbt_question_essay',
        ];
    }

    private static function save_question_type_detail(int $question_id, string $question_type, string $correct_text): void
    {
        global $wpdb;

        if ($question_id <= 0) {
            return;
        }

        $tables = self::question_type_detail_tables();
        foreach ($tables as $table) {
            $wpdb->delete($table, ['question_id' => $question_id], ['%d']);
        }

        $now = current_time('mysql');

        if ($question_type === 'multiple_choice' && isset($tables['multiple_choice'])) {
            $wpdb->insert(
                $tables['multiple_choice'],
                [
                    'question_id' => $question_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s']
            );
            return;
        }

        if ($question_type === 'multiple_answer' && isset($tables['multiple_answer'])) {
            $wpdb->insert(
                $tables['multiple_answer'],
                [
                    'question_id' => $question_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s']
            );
            return;
        }

        if ($question_type === 'true_false' && isset($tables['true_false'])) {
            $wpdb->insert(
                $tables['true_false'],
                [
                    'question_id' => $question_id,
                    'correct_value' => self::normalize_true_false_value($correct_text),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s']
            );
            return;
        }

        if ($question_type === 'short_answer' && isset($tables['short_answer'])) {
            $normalized = self::normalize_short_answer_payload($correct_text);
            $wpdb->insert(
                $tables['short_answer'],
                [
                    'question_id' => $question_id,
                    'correct_text' => $normalized !== '' ? $normalized : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%s']
            );
            return;
        }

        if ($question_type === 'essay' && isset($tables['essay'])) {
            $normalized = trim($correct_text);
            $wpdb->insert(
                $tables['essay'],
                [
                    'question_id' => $question_id,
                    'rubric_text' => $normalized !== '' ? $normalized : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
    }

    private static function get_question_type_detail(int $question_id, string $question_type): array
    {
        global $wpdb;

        if ($question_id <= 0) {
            return [];
        }

        $tables = self::question_type_detail_tables();
        if (isset($tables[$question_type])) {
            $detail = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$tables[$question_type]} WHERE question_id = %d", $question_id),
                ARRAY_A
            );
            if (is_array($detail) && !empty($detail)) {
                if ($question_type === 'short_answer') {
                    $values = self::normalize_short_answer_values((string) ($detail['correct_text'] ?? ''));
                    $detail['correct_text'] = !empty($values) ? (string) wp_json_encode($values) : '';
                    $detail['correct_answers'] = $values;
                }
                return $detail;
            }
        }

        // Backward compatibility for older data that has not been migrated yet.
        if ($question_type === 'true_false') {
            $legacy_value = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );
            if (trim($legacy_value) === '') {
                $legacy_value = (string) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT option_text
                         FROM {$wpdb->prefix}cbt_options
                         WHERE question_id = %d AND is_correct = 1
                         ORDER BY id ASC
                         LIMIT 1",
                        $question_id
                    )
                );
            }

            return [
                'question_id' => $question_id,
                'correct_value' => self::normalize_true_false_value($legacy_value),
            ];
        }

        if ($question_type === 'short_answer') {
            $legacy_text = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );
            $values = self::normalize_short_answer_values($legacy_text);
            return [
                'question_id' => $question_id,
                'correct_text' => !empty($values) ? (string) wp_json_encode($values) : '',
                'correct_answers' => $values,
            ];
        }

        if ($question_type === 'essay') {
            $legacy_text = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );
            return [
                'question_id' => $question_id,
                'rubric_text' => $legacy_text,
            ];
        }

        return [];
    }

    private static function normalize_true_false_value(string $value): int
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['false', '0', 'f', 'no', 'n', 'tidak', 'salah'], true)) {
            return 0;
        }
        return 1;
    }

    private static function parse_options(string $options_raw): array
    {
        $items = [];

        $raw_trimmed = trim($options_raw);
        if ($raw_trimmed !== '' && ($raw_trimmed[0] ?? '') === '[') {
            $decoded = json_decode($raw_trimmed, true);
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $text = isset($entry['option_text']) ? wp_kses_post((string) $entry['option_text']) : '';
                    $is_correct = !empty($entry['is_correct']) ? 1 : 0;

                    if (self::has_non_empty_option_content($text)) {
                        $items[] = [
                            'option_text' => $text,
                            'is_correct' => $is_correct,
                        ];
                    }
                }
                return $items;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $options_raw);

        foreach ((array) $lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $text = isset($parts[0]) ? wp_kses_post((string) $parts[0]) : '';
            $is_correct = isset($parts[1]) && $parts[1] === '1' ? 1 : 0;

            if (self::has_non_empty_option_content($text)) {
                $items[] = [
                    'option_text' => $text,
                    'is_correct' => $is_correct,
                ];
            }
        }

        return $items;
    }

    private static function has_non_empty_option_content(string $html): bool
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/<img\b/i', $trimmed)) {
            return true;
        }

        $text = str_replace('&nbsp;', ' ', $trimmed);
        $text = wp_strip_all_tags($text);
        return trim($text) !== '';
    }

    private static function to_datetime_local(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timezone = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        if (!$dt) {
            $timestamp = strtotime($value);
            if (!$timestamp) {
                return '';
            }
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        if (!$dt) {
            return '';
        }

        return $dt->format('Y-m-d\TH:i');
    }

    private static function from_datetime_local(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone);
        if (!$dt) {
            $timestamp = strtotime($value);
            if (!$timestamp) {
                return null;
            }
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
