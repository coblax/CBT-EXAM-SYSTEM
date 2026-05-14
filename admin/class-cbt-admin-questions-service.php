<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Questions_Service
{
    private const TEST_REDIRECT_SIGNAL = '__cbt_admin_questions_redirect__';
    private const QUESTION_IMPORT_SCOPE_CREATED = 'created';

    public static function can_manage_questions(): bool
    {
        return current_user_can('cbt_manage_questions');
    }

    public static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    private static function normalize_question_import_scope(string $requested_scope): string
    {
        $requested_scope = sanitize_key($requested_scope);

        if ($requested_scope === self::QUESTION_IMPORT_SCOPE_CREATED) {
            return self::QUESTION_IMPORT_SCOPE_CREATED;
        }

        return '';
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private static function add_question_import_batch_scope_args(array $args, string $token, string $scope): array
    {
        if ($token !== '' && $scope === self::QUESTION_IMPORT_SCOPE_CREATED) {
            $args['cbt_question_import_token'] = $token;
            $args['cbt_question_import_scope'] = $scope;
        }

        return $args;
    }

    private static function dispatch_redirect(string $url): void
    {
        wp_safe_redirect($url);
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            throw new RuntimeException(self::TEST_REDIRECT_SIGNAL);
        }

        exit;
    }

    /**
     * @param array<int,int> $exam_ids
     */
    private static function warm_exam_question_delivery_snapshots(array $exam_ids): void
    {
        if (!class_exists('CBT_REST') || !method_exists('CBT_REST', 'warm_exam_question_delivery_snapshot')) {
            return;
        }

        $exam_ids = array_values(array_unique(array_filter(array_map('intval', $exam_ids), static function (int $exam_id): bool {
            return $exam_id > 0;
        })));
        foreach ($exam_ids as $exam_id) {
            CBT_REST::warm_exam_question_delivery_snapshot($exam_id);
            if (method_exists('CBT_REST', 'warm_exam_start_attempt_snapshot')) {
                CBT_REST::warm_exam_start_attempt_snapshot($exam_id);
            }
        }
    }

    /**
     * @param array<int,int> $exam_ids
     * @param array<int,array<int,int>> $partial_question_ids_by_exam
     */
    private static function refresh_exam_question_delivery_snapshots_after_question_updates(array $exam_ids, array $partial_question_ids_by_exam): void
    {
        $exam_ids = array_values(array_unique(array_filter(array_map('intval', $exam_ids), static function (int $exam_id): bool {
            return $exam_id > 0;
        })));
        if (empty($exam_ids)) {
            return;
        }

        foreach ($exam_ids as $exam_id) {
            $question_ids = isset($partial_question_ids_by_exam[$exam_id]) && is_array($partial_question_ids_by_exam[$exam_id])
                ? array_values(array_unique(array_filter(array_map('intval', $partial_question_ids_by_exam[$exam_id]), static function (int $question_id): bool {
                    return $question_id > 0;
                })))
                : [];

            if (
                !empty($question_ids)
                && class_exists('CBT_REST')
                && method_exists('CBT_REST', 'refresh_exam_question_snapshots_after_question_updates')
            ) {
                CBT_REST::refresh_exam_question_snapshots_after_question_updates($exam_id, $question_ids);
                continue;
            }

            CBT_Cache::invalidate_exam($exam_id);
            self::warm_exam_question_delivery_snapshots([$exam_id]);
        }
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,int>
     */
    private static function collect_impacted_exam_ids_for_question_ids(array $question_ids): array
    {
        global $wpdb;

        $question_ids = array_values(array_unique(array_filter(array_map('absint', $question_ids))));
        if (empty($question_ids)) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $question_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, exam_id
                 FROM {$question_table}
                 WHERE id IN ({$placeholders})",
                ...$question_ids
            ),
            ARRAY_A
        );

        $impacted_exam_ids = [];
        foreach ((array) $question_rows as $question_row) {
            $exam_id = (int) ($question_row['exam_id'] ?? 0);
            if ($exam_id > 0) {
                $impacted_exam_ids[$exam_id] = $exam_id;
            }
        }

        $descendant_exam_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT exam_id
                 FROM {$question_table}
                 WHERE source_question_id IN ({$placeholders})",
                ...$question_ids
            ),
            ARRAY_A
        );
        foreach ((array) $descendant_exam_rows as $exam_row) {
            $exam_id = (int) ($exam_row['exam_id'] ?? 0);
            if ($exam_id > 0) {
                $impacted_exam_ids[$exam_id] = $exam_id;
            }
        }

        return array_values($impacted_exam_ids);
    }

    /**
     * @param array<int,int> $exam_ids
     */
    private static function has_in_progress_attempts_for_exam_ids(array $exam_ids): bool
    {
        global $wpdb;

        $exam_ids = array_values(array_unique(array_filter(array_map('intval', $exam_ids), static function (int $exam_id): bool {
            return $exam_id > 0;
        })));
        if (empty($exam_ids)) {
            return false;
        }

        if (
            class_exists('CBT_Live_Attempt_Roster_Index')
            && method_exists('CBT_Live_Attempt_Roster_Index', 'is_available')
            && CBT_Live_Attempt_Roster_Index::is_available()
            && method_exists('CBT_Live_Attempt_Roster_Index', 'has_active_attempts_for_exam_ids')
        ) {
            return CBT_Live_Attempt_Roster_Index::has_active_attempts_for_exam_ids($exam_ids);
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $placeholders = implode(',', array_fill(0, count($exam_ids), '%d'));
        $found_attempt_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$attempt_table}
                 WHERE status = %s
                   AND exam_id IN ({$placeholders})
                 LIMIT 1",
                'in_progress',
                ...$exam_ids
            )
        );

        return $found_attempt_id > 0;
    }

    private static function active_attempt_delete_error_message(): string
    {
        return 'Soal tidak bisa dihapus saat masih ada peserta aktif pada exam terkait.';
    }

    /**
     * @param int[] $target_ids
     * @param array<string,mixed> $state
     */
    private static function start_question_delete_session(array $target_ids, array $state): string
    {
        $target_ids = array_values(array_unique(array_filter(array_map('absint', $target_ids))));
        if (empty($target_ids)) {
            return '';
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $state = array_merge(
            [
                'total' => count($target_ids),
                'offset' => 0,
                'deleted' => 0,
                'failed' => 0,
                'user_id' => get_current_user_id(),
                'started_at' => time(),
                'return_page' => 'cbt-question-bank',
                'filter_exam_id' => 0,
                'filter_type' => '',
                'filter_source_kind' => '',
                'filter_subject_id' => 0,
                'question_per_page' => 20,
                'question_paged' => 1,
                'affected_exam_ids' => [],
                'question_import_token' => '',
                'question_import_scope' => '',
            ],
            $state
        );

        $rows_saved = set_transient(self::get_question_delete_rows_key($token), $target_ids, 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient(self::get_question_delete_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            self::clear_question_delete_transients($token);
            return '';
        }

        return $token;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query, ?string $forced_question_type = null): array
    {
        global $wpdb;

                $exam_table = $wpdb->prefix . 'cbt_exams';
                $subject_table = $wpdb->prefix . 'cbt_subjects';
                $question_table = $wpdb->prefix . 'cbt_questions';
                $option_table = $wpdb->prefix . 'cbt_options';
                $is_admin_scope = self::is_admin_scope();
                $current_user_id = get_current_user_id();
            $allowed_question_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'];
            $question_type_labels = [
                'multiple_choice' => 'Multiple Choice',
                'multiple_answer' => 'Multiple Answer',
                'true_false' => 'True/False',
                'true_false_matrix' => 'True/False Matrix',
                'short_answer' => 'Short Answer',
                'essay' => 'Essay',
                'ordering' => 'Ordering',
                'matching' => 'Matching',
                'cloze_dropdown' => 'Cloze Dropdown',
                'categorization' => 'Categorization',
                'table_completion' => 'Table Completion',
            ];
                $current_page_slug = CBT_Admin_Questions_Helper::normalize_question_page_slug(isset($query['page']) ? wp_unslash($query['page']) : 'cbt-question-bank');
                $page_locked_type = CBT_Admin_Questions_Helper::forced_question_type_for_page($current_page_slug);
                $active_question_type = '';
                if (is_string($forced_question_type) && in_array($forced_question_type, $allowed_question_types, true)) {
                    $active_question_type = $forced_question_type;
                } elseif ($page_locked_type !== '') {
                    $active_question_type = $page_locked_type;
                } elseif (isset($query['question_type'])) {
                    $from_query = sanitize_text_field(wp_unslash($query['question_type']));
                    if (in_array($from_query, $allowed_question_types, true)) {
                        $active_question_type = $from_query;
                    }
                }
                $lock_question_type = ($active_question_type !== '');
                $import_type_help_suffix = ' Gambar dan tabel di soal, opsi, serta pembahasan didukung. Wajib gunakan template resmi terbaru dan jangan hapus marker CBT_TEMPLATE atau field JENIS_SOAL.';
                $import_type_help_map = [
                    'multiple_choice' => 'Mode import aktif: Multiple Choice. DOCX didukung (PILIHAN_1..N sesuai Jumlah Pilihan 3-5, JAWABAN berupa satu nomor opsi 1..N, opsi tidak boleh duplikat).' . $import_type_help_suffix,
                    'multiple_answer' => 'Mode import aktif: Multiple Answer. DOCX didukung (PILIHAN_1..N sesuai Jumlah Pilihan 3-12, JAWABAN boleh lebih dari satu seperti 1,3,5, minimal 1 benar, opsi tidak boleh duplikat).' . $import_type_help_suffix,
                    'true_false' => 'Mode import aktif: True/False. DOCX didukung (jawaban: true/false, field opsional PEMBAHASAN didukung).' . $import_type_help_suffix,
                    'true_false_matrix' => 'Mode import aktif: True/False Matrix. DOCX didukung (PERNYATAAN_1..N dan KUNCI_1..N sesuai Jumlah Pernyataan 2-10, kunci true/false berurutan tanpa nomor loncat).' . $import_type_help_suffix,
                    'short_answer' => 'Mode import aktif: Short Answer. DOCX didukung (pakai placeholder [INPUT_1]..[INPUT_N] sesuai Jumlah Input 1-8, lalu isi JAWABAN_A..H sesuai key placeholder).' . $import_type_help_suffix,
                    'essay' => 'Mode import aktif: Essay. DOCX didukung (wajib isi acuan jawaban/rubrik, field opsional PEMBAHASAN didukung).' . $import_type_help_suffix,
                    'ordering' => 'Mode import aktif: Ordering. DOCX didukung (ITEM_1..N sesuai Jumlah Item 2-12 ditulis dalam urutan benar, item tidak boleh duplikat).' . $import_type_help_suffix,
                    'matching' => 'Mode import aktif: Matching. DOCX didukung (KIRI_1..N dan KANAN_1..N sesuai Jumlah Pasangan 2-12; KANAN_n adalah pasangan benar KIRI_n).' . $import_type_help_suffix,
                    'cloze_dropdown' => 'Mode import aktif: Cloze Dropdown. DOCX didukung (pakai [DROPDOWN_1]..[DROPDOWN_N], isi DROPDOWN_n_OPSI_1..M dan DROPDOWN_n_JAWABAN; tiap dropdown tepat 1 kunci).' . $import_type_help_suffix,
                    'categorization' => 'Mode import aktif: Categorization. DOCX didukung (KATEGORI_1..N, ITEM_1..M, dan KUNCI_1..M berisi nomor atau teks kategori benar).' . $import_type_help_suffix,
                    'table_completion' => 'Mode import aktif: Table Completion. DOCX didukung (TABLE_ROWS, TABLE_COLS, lalu CELL_A1_TYPE/TEXT/JAWABAN/OPSI sesuai ukuran tabel).' . $import_type_help_suffix,
                ];
                $import_active_type = $lock_question_type ? $active_question_type : 'multiple_choice';
                $import_help_text = $import_type_help_map[$import_active_type] ?? $import_type_help_map['multiple_choice'];
            $import_allow_docx = in_array($import_active_type, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'], true);
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
        
                $subject_bank_exam_labels = [];
                foreach ($exams as $exam) {
                    $exam_subject_id = (int) ($exam['subject_id'] ?? 0);
                    $exam_title = trim((string) ($exam['title'] ?? ''));
                    if ($exam_subject_id <= 0 || $exam_title === '' || isset($subject_bank_exam_labels[$exam_subject_id])) {
                        continue;
                    }
                    $subject_bank_exam_labels[$exam_subject_id] = $exam_title;
                }
                foreach ($subjects as $subject) {
                    $subject_id = (int) ($subject['id'] ?? 0);
                    if ($subject_id <= 0 || isset($subject_bank_exam_labels[$subject_id])) {
                        continue;
                    }
                    $subject_bank_exam_labels[$subject_id] = 'Bank Soal - ' . (string) ($subject['name'] ?? ('Subject ' . $subject_id));
                }
        
                $editing_id = isset($query['edit']) ? absint($query['edit']) : 0;
                $editing_question = null;
                $editing_options = [];
                $selected_subject_id = 0;
                $editing_question_is_bank_exam = false;
                $editing_question_is_bank_backed = false;
                $editing_question_is_edit_guarded = false;
                $editing_question_source_label = '';
                $editing_question_source_description = '';
                $editing_question_exam_title = '';
                $editing_question_source_exam_title = '';
                $editing_question_source_question_id = 0;
                $editing_question_source_edit_url = '';
                $editing_question_source_view_url = '';
                $editing_question_guard_title = '';
                $editing_question_guard_message = '';
                $view_id = isset($query['view']) ? absint($query['view']) : 0;
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
                        $editing_exam_id = (int) ($editing_question['exam_id'] ?? 0);
                        $editing_question_source_question_id = (int) ($editing_question['source_question_id'] ?? 0);
                        if ($editing_exam_id > 0) {
                            $editing_exam_row = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT e.subject_id,
                                            e.title,
                                            source_exam.title AS source_exam_title
                                     FROM {$exam_table}
                                     e
                                     LEFT JOIN {$question_table} source_q ON source_q.id = %d
                                     LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_q.exam_id
                                     WHERE e.id = %d
                                     LIMIT 1",
                                    (int) ($editing_question['source_question_id'] ?? 0),
                                    $editing_exam_id
                                ),
                                ARRAY_A
                            );
                            if (is_array($editing_exam_row)) {
                                $selected_subject_id = (int) ($editing_exam_row['subject_id'] ?? 0);
                                $editing_question_exam_title = (string) ($editing_exam_row['title'] ?? '');
                                $editing_question_source_exam_title = (string) ($editing_exam_row['source_exam_title'] ?? '');
                                $editing_question_is_bank_exam = stripos($editing_question_exam_title, 'Bank Soal - ') === 0;
                                $editing_question_is_bank_backed = !$editing_question_is_bank_exam
                                    && (int) ($editing_question['source_question_id'] ?? 0) > 0
                                    && stripos($editing_question_source_exam_title, 'Bank Soal - ') === 0;
                            }
                        }
                    }
                    if ($editing_question) {
                        if ($editing_question_is_bank_exam) {
                            $editing_question_source_label = 'Bank Soal';
                            $editing_question_source_description = 'Soal ini adalah soal sumber di bank soal mapel dan perubahan akan mengikuti jalur sinkronisasi bank.';
                        } elseif ($editing_question_is_bank_backed) {
                            $editing_question_source_label = 'Bank-backed';
                            $editing_question_source_description = 'Soal ini adalah turunan exam yang bersumber dari Bank Soal. Row ini bukan legacy, tetapi salinan operasional untuk exam siswa.';
                            $editing_question_is_edit_guarded = $editing_question_source_question_id > 0;
                            $editing_question_guard_title = 'Bank-backed dikunci di sini';
                            $editing_question_guard_message = 'Untuk menjaga sumber kebenaran tetap satu arah, row turunan exam tidak diedit langsung dari CBT Questions. Ubah soal sumbernya di Bank Soal, lalu biarkan jalur sinkronisasi memperbarui turunan exam.';
                        } else {
                            $editing_question_source_label = 'Legacy Source';
                            $editing_question_source_description = 'Soal ini masih tersimpan di exam biasa tanpa lineage bank soal. Edit tetap dilakukan di sumber legacy tanpa dipindahkan otomatis.';
                        }
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
                        $view_detail = CBT_Admin_Questions_Helper::get_question_type_detail((int) $view_id, (string) ($view_question['question_type'] ?? ''));
                    }
                }
        
                $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash($query['cbt_msg'])) : '';
                $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash($query['cbt_err'])) : '';
                $question_import_token = isset($query['cbt_question_import_token']) ? sanitize_key((string) wp_unslash($query['cbt_question_import_token'])) : '';
                $question_import_scope = isset($query['cbt_question_import_scope'])
                    ? self::normalize_question_import_scope((string) wp_unslash($query['cbt_question_import_scope']))
                    : '';
                $question_import_state = null;
                $question_import_total = 0;
                $question_import_offset = 0;
                $question_import_created = 0;
                $question_import_failed = 0;
                $question_import_recent_failures = [];
                $question_import_diagnostic_counts = [
                    'preserved' => 0,
                    'fallback' => 0,
                    'unsupported' => 0,
                ];
                $question_import_diagnostic_entries = [];
                $question_import_has_diagnostics = false;
                $question_import_diagnostic_truncated = false;
                $question_import_progress_percent = 0.0;
                $question_import_is_running = false;
                $question_import_continue_url = '';
                $question_import_batch_active = false;
                $question_import_batch_has_scope_request = $question_import_scope === self::QUESTION_IMPORT_SCOPE_CREATED;
                $question_import_batch_subject_id = 0;
                $question_import_batch_subject_label = '';
                $question_import_batch_created_question_ids = [];
                $question_import_batch_analysis_items = [];
                $question_import_batch_analysis_summary = [
                    'preserved' => 0,
                    'fallback' => 0,
                    'unsupported' => 0,
                ];
                $question_import_batch_selected_question_id = 0;
                $question_import_batch_list_url = '';
                $question_import_batch_back_to_all_url = '';
                $question_import_batch_delete_all_url = '';
                $question_import_batch_expired_notice = '';
                if ($question_import_token !== '') {
                    $question_import_state = CBT_Admin_Questions_Import_Helper::get_question_import_state_for_current_user($question_import_token);
                    if (is_array($question_import_state)) {
                        $question_import_total = max(0, isset($question_import_state['total']) ? (int) $question_import_state['total'] : 0);
                        $question_import_offset = max(0, isset($question_import_state['offset']) ? (int) $question_import_state['offset'] : 0);
                        if ($question_import_total > 0 && $question_import_offset > $question_import_total) {
                            $question_import_offset = $question_import_total;
                        }
                        $question_import_created = max(0, isset($question_import_state['created']) ? (int) $question_import_state['created'] : 0);
                        $question_import_failed = max(0, isset($question_import_state['failed']) ? (int) $question_import_state['failed'] : 0);
                        $question_import_recent_failures = isset($question_import_state['recent_failures']) && is_array($question_import_state['recent_failures'])
                            ? array_map(static function (array $entry) use ($question_type_labels): array {
                                $question_type = (string) ($entry['question_type'] ?? '');
                                $entry['question_type_label'] = $question_type !== '' && isset($question_type_labels[$question_type])
                                    ? (string) $question_type_labels[$question_type]
                                    : '';
                                return $entry;
                            }, CBT_Admin_Questions_Import_Helper::normalize_question_import_failure_entries($question_import_state['recent_failures']))
                            : [];
                        $question_import_diagnostic_counts = array_merge(
                            $question_import_diagnostic_counts,
                            isset($question_import_state['diagnostic_counts']) && is_array($question_import_state['diagnostic_counts'])
                                ? array_intersect_key($question_import_state['diagnostic_counts'], $question_import_diagnostic_counts)
                                : []
                        );
                        $question_import_diagnostic_counts['preserved'] = max(0, (int) ($question_import_diagnostic_counts['preserved'] ?? 0));
                        $question_import_diagnostic_counts['fallback'] = max(0, (int) ($question_import_diagnostic_counts['fallback'] ?? 0));
                        $question_import_diagnostic_counts['unsupported'] = max(0, (int) ($question_import_diagnostic_counts['unsupported'] ?? 0));
                        $question_import_diagnostic_entries = isset($question_import_state['diagnostic_entries']) && is_array($question_import_state['diagnostic_entries'])
                            ? CBT_Admin_Questions_Import_Helper::normalize_question_import_diagnostic_entries($question_import_state['diagnostic_entries'])
                            : [];
                        $question_import_has_diagnostics = !empty($question_import_diagnostic_entries)
                            || array_sum($question_import_diagnostic_counts) > 0;
                        $question_import_diagnostic_truncated = !empty($question_import_state['diagnostic_truncated']);
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
                        $question_import_batch_created_question_ids = CBT_Admin_Questions_Import_Helper::get_question_import_created_question_ids_for_current_user($question_import_token);
                        $question_import_batch_analysis_items = CBT_Admin_Questions_Import_Helper::get_question_import_created_question_items_for_current_user($question_import_token);
                        $question_import_batch_analysis_summary = CBT_Admin_Questions_Import_Helper::summarize_question_import_created_question_items($question_import_batch_analysis_items);
                        $question_import_batch_selected_question_id = CBT_Admin_Questions_Import_Helper::get_default_question_import_created_question_item_id($question_import_batch_analysis_items);
                        $question_import_batch_subject_id = isset($question_import_state['import_subject_id'])
                            ? (int) $question_import_state['import_subject_id']
                            : 0;
                        if ($question_import_batch_has_scope_request) {
                            $question_import_batch_active = true;
                        }
                    } elseif ($notice === '' && $error === '') {
                        $error = $question_import_batch_has_scope_request
                            ? 'Sesi hasil import batch sudah berakhir. Kembali menampilkan semua soal.'
                            : 'Sesi import soal tidak ditemukan atau sudah berakhir. Silakan upload ulang file.';
                        if ($question_import_batch_has_scope_request) {
                            $question_import_batch_expired_notice = $error;
                            $question_import_scope = '';
                        }
                    }
                }
                $show_import_panel_first = is_array($question_import_state);
                $question_delete_token = isset($query['cbt_question_delete_token']) ? sanitize_key((string) wp_unslash($query['cbt_question_delete_token'])) : '';
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
                $allowed_source_filters = ['bank', 'bank_backed', 'legacy'];
                $source_filter_labels = [
                    'bank' => 'Bank Soal',
                    'bank_backed' => 'Bank-backed',
                    'legacy' => 'Legacy Source',
                ];
                if ($lock_question_type) {
                    $list_filter_type = $active_question_type;
                } elseif (isset($query['filter_type'])) {
                    $requested_filter_type = sanitize_text_field(wp_unslash($query['filter_type']));
                    if (in_array($requested_filter_type, $allowed_question_types, true)) {
                        $list_filter_type = $requested_filter_type;
                    }
                }
                $list_filter_source_kind = '';
                if (isset($query['filter_source_kind'])) {
                    $requested_source_kind = sanitize_text_field(wp_unslash($query['filter_source_kind']));
                    if (in_array($requested_source_kind, $allowed_source_filters, true)) {
                        $list_filter_source_kind = $requested_source_kind;
                    }
                }
                $list_filter_subject_id = isset($query['filter_subject_id']) ? absint(wp_unslash($query['filter_subject_id'])) : 0;
                $available_subject_ids = array_map(
                    static function (array $subject_row): int {
                        return (int) ($subject_row['id'] ?? 0);
                    },
                    (array) $subjects
                );
                if ($list_filter_subject_id > 0 && !in_array($list_filter_subject_id, $available_subject_ids, true)) {
                    $list_filter_subject_id = 0;
                }
                $list_per_page = isset($query['cbt_question_per_page'])
                    ? self::normalize_standard_list_per_page(absint(wp_unslash($query['cbt_question_per_page'])))
                    : 20;
                $list_current_page = isset($query['cbt_question_paged']) ? max(1, absint(wp_unslash($query['cbt_question_paged']))) : 1;
                if ($question_import_batch_active && $question_import_batch_subject_id > 0) {
                    foreach ((array) $subjects as $batch_subject_row) {
                        if ((int) ($batch_subject_row['id'] ?? 0) !== $question_import_batch_subject_id) {
                            continue;
                        }
                        $question_import_batch_subject_label = (string) ($batch_subject_row['name'] ?? '');
                        if (!empty($batch_subject_row['code'])) {
                            $question_import_batch_subject_label .= ' (' . (string) $batch_subject_row['code'] . ')';
                        }
                        break;
                    }
                }
                $question_import_batch_action_scope = is_array($question_import_state)
                    ? self::QUESTION_IMPORT_SCOPE_CREATED
                    : '';

                $question_import_batch_list_url = add_query_arg(
                    self::add_question_import_batch_scope_args(
                        [
                            'page' => $current_page_slug,
                            'cbt_question_per_page' => $list_per_page,
                            'cbt_question_paged' => 1,
                        ],
                        $question_import_token,
                        $question_import_batch_action_scope
                    ),
                    admin_url('admin.php')
                );
                $question_import_batch_back_to_all_url = add_query_arg(
                    [
                        'page' => $current_page_slug,
                        'cbt_question_per_page' => $list_per_page,
                    ],
                    admin_url('admin.php')
                );
                $question_import_batch_delete_all_url = wp_nonce_url(
                    add_query_arg(
                        self::add_question_import_batch_scope_args(
                            [
                                'action' => 'cbt_delete_all_import_batch_questions',
                                'return_page' => $current_page_slug,
                                'question_per_page' => $list_per_page,
                                'question_paged' => $list_current_page,
                            ],
                            $question_import_token,
                            $question_import_batch_action_scope
                        ),
                        admin_url('admin-post.php')
                    ),
                    'cbt_delete_all_import_batch_questions'
                );
        
                $question_base_where_parts = [];
                if (!$is_admin_scope) {
                    $created_by_clause = $wpdb->prepare('e.created_by = %d', $current_user_id);
                    $question_base_where_parts[] = $created_by_clause;
                }
                if ($list_filter_type !== '') {
                    $type_clause = $wpdb->prepare('q.question_type = %s', $list_filter_type);
                    $question_base_where_parts[] = $type_clause;
                }
                if ($list_filter_subject_id > 0) {
                    $question_base_where_parts[] = $wpdb->prepare('e.subject_id = %d', $list_filter_subject_id);
                }
                if ($question_import_batch_active) {
                    if (!empty($question_import_batch_created_question_ids)) {
                        $question_base_where_parts[] = 'q.id IN (' . implode(',', array_map('intval', $question_import_batch_created_question_ids)) . ')';
                    } else {
                        $question_base_where_parts[] = 'q.id = 0';
                    }
                }
                $question_where_parts = $question_base_where_parts;
                if ($list_filter_source_kind === 'bank') {
                    $question_where_parts[] = $wpdb->prepare('e.title LIKE %s', $bank_exam_title_like);
                } elseif ($list_filter_source_kind === 'bank_backed') {
                    $question_where_parts[] = $wpdb->prepare('e.title NOT LIKE %s', $bank_exam_title_like);
                    $question_where_parts[] = 'q.source_question_id > 0';
                    $question_where_parts[] = $wpdb->prepare('source_exam.title LIKE %s', $bank_exam_title_like);
                } elseif ($list_filter_source_kind === 'legacy') {
                    $question_where_parts[] = $wpdb->prepare('e.title NOT LIKE %s', $bank_exam_title_like);
                    $question_where_parts[] = $wpdb->prepare('(q.source_question_id <= 0 OR source_exam.title IS NULL OR source_exam.title NOT LIKE %s)', $bank_exam_title_like);
                }
                $question_where = '';
                if (!empty($question_where_parts)) {
                    $question_where = ' WHERE ' . implode(' AND ', $question_where_parts);
                }
                $question_base_where = '';
                if (!empty($question_base_where_parts)) {
                    $question_base_where = ' WHERE ' . implode(' AND ', $question_base_where_parts);
                }
                $question_source_counts = [
                    'bank' => 0,
                    'bank_backed' => 0,
                    'legacy' => 0,
                ];
                $question_source_count_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT
                                SUM(CASE WHEN e.title LIKE %s THEN 1 ELSE 0 END) AS bank_count,
                                SUM(CASE WHEN e.title NOT LIKE %s AND q.source_question_id > 0 AND source_exam.title LIKE %s THEN 1 ELSE 0 END) AS bank_backed_count,
                                SUM(CASE WHEN e.title NOT LIKE %s AND (q.source_question_id <= 0 OR source_exam.title IS NULL OR source_exam.title NOT LIKE %s) THEN 1 ELSE 0 END) AS legacy_count
                         FROM {$question_table} q
                         INNER JOIN {$exam_table} e ON e.id = q.exam_id
                         LEFT JOIN {$question_table} source_q ON source_q.id = q.source_question_id
                         LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_q.exam_id
                         {$question_base_where}",
                        $bank_exam_title_like,
                        $bank_exam_title_like,
                        $bank_exam_title_like,
                        $bank_exam_title_like,
                        $bank_exam_title_like
                    ),
                    ARRAY_A
                );
                if (is_array($question_source_count_row)) {
                    $question_source_counts = [
                        'bank' => max(0, (int) ($question_source_count_row['bank_count'] ?? 0)),
                        'bank_backed' => max(0, (int) ($question_source_count_row['bank_backed_count'] ?? 0)),
                        'legacy' => max(0, (int) ($question_source_count_row['legacy_count'] ?? 0)),
                    ];
                }
                $question_has_legacy_source = $question_source_counts['legacy'] > 0;
                $question_list_intro_text = $question_has_legacy_source
                    ? 'Tinjau soal sumber dan turunan operasional. Bank Soal adalah soal master, Bank-backed adalah salinan soal di exam siswa, dan Legacy Source menandai data lama yang belum memakai lineage bank.'
                    : 'Tinjau soal sumber dan turunan operasional. Bank Soal adalah soal master, sedangkan Bank-backed adalah salinan soal yang sudah menempel ke exam siswa. Data legacy aktif saat ini sudah 0.';
                $question_lineage_info_cards = [
                    [
                        'label' => 'Bank Soal',
                        'class' => 'cbt-questions-chip--bank',
                        'count' => $question_source_counts['bank'],
                        'description' => 'Soal master yang disimpan di Bank Soal dan dipakai sebagai sumber utama saat menyusun exam.',
                    ],
                    [
                        'label' => 'Bank-backed',
                        'class' => 'cbt-questions-chip--bank-backed',
                        'count' => $question_source_counts['bank_backed'],
                        'description' => 'Soal turunan operasional yang sudah disalin ke exam siswa, tetapi masih terhubung ke sumber bank lewat lineage.',
                    ],
                ];
                if ($question_has_legacy_source) {
                    $question_lineage_info_cards[] = [
                        'label' => 'Legacy Source',
                        'class' => 'cbt-questions-chip--legacy',
                        'count' => $question_source_counts['legacy'],
                        'description' => 'Data lama yang masih tersimpan di exam biasa tanpa lineage bank. Tetap dibaca untuk kompatibilitas.',
                    ];
                }
                if ($question_import_batch_active) {
                    $question_list_intro_text = 'Menampilkan hanya soal baru dari batch import ini. Gunakan preview inline untuk inspeksi cepat, lalu hapus row yang tidak dibutuhkan langsung dari scope batch ini.';
                    $question_lineage_info_cards = [];
                }
                $total_questions = (int) $wpdb->get_var(
                    "SELECT COUNT(*)
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} e ON e.id = q.exam_id
                     LEFT JOIN {$question_table} source_q ON source_q.id = q.source_question_id
                     LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_q.exam_id
                     {$question_where}"
                );
                $total_question_pages = max(1, (int) ceil($total_questions / $list_per_page));
                if ($total_questions > 0 && $list_current_page > $total_question_pages) {
                    $list_current_page = $total_question_pages;
                }
                $question_offset = ($list_current_page - 1) * $list_per_page;
                $question_limit = (int) $list_per_page;
                $question_offset = (int) $question_offset;
                $bank_sort_case = $wpdb->prepare("CASE WHEN e.title LIKE %s THEN 0 ELSE 1 END", $bank_exam_title_like);
                $questions = $wpdb->get_results(
                    "SELECT q.*,
                            e.title AS exam_title,
                            s.name AS subject_name,
                            source_exam.title AS source_exam_title
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} e ON e.id = q.exam_id
                     LEFT JOIN {$question_table} source_q ON source_q.id = q.source_question_id
                     LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_q.exam_id
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     {$question_where}
                     ORDER BY {$bank_sort_case} ASC, q.id DESC
                     LIMIT {$question_limit} OFFSET {$question_offset}",
                    ARRAY_A
                );
                $visible_bank_question_ids = [];
                foreach ((array) $questions as $question_row) {
                    $question_id = (int) ($question_row['id'] ?? 0);
                    $question_exam_title = (string) ($question_row['exam_title'] ?? '');
                    if ($question_id > 0 && stripos($question_exam_title, 'Bank Soal - ') === 0) {
                        $visible_bank_question_ids[] = $question_id;
                    }
                }
                $question_bank_usage_summary_map = self::build_bank_usage_summary_map($visible_bank_question_ids, $is_admin_scope, $current_user_id);
                $question_reference_open_id = isset($query['reference']) ? absint(wp_unslash($query['reference'])) : 0;
                if (!in_array($question_reference_open_id, $visible_bank_question_ids, true)) {
                    $question_reference_open_id = 0;
                }
                $question_reference_rows = $question_reference_open_id > 0
                    ? self::build_bank_reference_rows($question_reference_open_id, $is_admin_scope, $current_user_id)
                    : [];
                $question_list_args = [
                    'page' => $current_page_slug,
                    'cbt_question_per_page' => $list_per_page,
                    'cbt_question_paged' => $list_current_page,
                ];
                if ($list_filter_type !== '') {
                    $question_list_args['filter_type'] = $list_filter_type;
                }
                if ($list_filter_source_kind !== '') {
                    $question_list_args['filter_source_kind'] = $list_filter_source_kind;
                }
                if ($list_filter_subject_id > 0) {
                    $question_list_args['filter_subject_id'] = $list_filter_subject_id;
                }
                $question_list_args = self::add_question_import_batch_scope_args(
                    $question_list_args,
                    $question_import_token,
                    $question_import_batch_active ? $question_import_scope : ''
                );
                $question_import_batch_link_args = self::add_question_import_batch_scope_args(
                    $question_list_args,
                    $question_import_token,
                    $question_import_batch_action_scope
                );
                if (is_array($question_import_state) && !empty($question_import_batch_analysis_items)) {
                    foreach ($question_import_batch_analysis_items as &$question_import_batch_analysis_item) {
                        if (!is_array($question_import_batch_analysis_item)) {
                            continue;
                        }
                        $item_question_id = (int) ($question_import_batch_analysis_item['question_id'] ?? 0);
                        $item_counts = isset($question_import_batch_analysis_item['diagnostic_counts']) && is_array($question_import_batch_analysis_item['diagnostic_counts'])
                            ? $question_import_batch_analysis_item['diagnostic_counts']
                            : ['preserved' => 0, 'fallback' => 0, 'unsupported' => 0];
                        $item_issue_count = max(0, (int) ($item_counts['fallback'] ?? 0)) + max(0, (int) ($item_counts['unsupported'] ?? 0));
                        $question_import_batch_analysis_item['issue_count'] = $item_issue_count;
                        $question_import_batch_analysis_item['status_label'] = $item_issue_count > 0
                            ? 'Perlu Dicek ' . $item_issue_count
                            : 'Aman';
                        $question_import_batch_analysis_item['view_url'] = add_query_arg(
                            array_merge($question_import_batch_link_args, ['view' => $item_question_id]),
                            admin_url('admin.php')
                        ) . '#cbt-question-preview-' . $item_question_id;
                    }
                    unset($question_import_batch_analysis_item);
                }
        
                $editing_type = $editing_question['question_type'] ?? ($lock_question_type ? $active_question_type : 'multiple_choice');
                $editing_detail = [];
                if ($editing_question && isset($editing_question['id'])) {
                    $editing_detail = CBT_Admin_Questions_Helper::get_question_type_detail((int) $editing_question['id'], $editing_type);
                }
        
                $editing_short_answer_values = CBT_Admin_Questions_Helper::normalize_short_answer_values((string) ($editing_detail['correct_text'] ?? ($editing_question['correct_text'] ?? '')));
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
                $editing_explanation = (string) ($editing_question['explanation'] ?? '');
                $editing_tf_matrix_values = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config((string) ($editing_question['correct_text'] ?? ''));
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
                $ordering_option_values = array_fill(1, 12, '');
                $matching_left_values = array_fill(1, 12, '');
                $matching_right_values = array_fill(1, 12, '');
                $cloze_dropdown_rows = array_fill(1, 8, [
                    'options' => array_fill(1, 6, ''),
                    'correct' => 1,
                ]);
                $categorization_category_values = array_fill(1, 8, '');
                $categorization_item_values = array_fill(1, 24, '');
                $categorization_item_correct = array_fill(1, 24, 1);
                $table_completion_row_count = 2;
                $table_completion_column_count = 2;
                $table_completion_cells = [];
                for ($table_row = 1; $table_row <= 8; $table_row++) {
                    for ($table_col = 1; $table_col <= 6; $table_col++) {
                        $table_cell_key = chr(64 + $table_col) . (string) $table_row;
                        $table_completion_cells[$table_cell_key] = [
                            'cell_type' => 'static',
                            'cell_text' => '',
                            'correct_text' => '',
                            'options' => array_fill(1, 6, ''),
                            'correct' => 1,
                        ];
                    }
                }
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
                    } elseif ($editing_type === 'ordering') {
                        $ordered_option_ids = is_array($editing_detail['correct_option_ids'] ?? null)
                            ? array_values(array_map('intval', (array) $editing_detail['correct_option_ids']))
                            : [];
                        $options_by_id = [];
                        foreach ($editing_options as $opt) {
                            $option_id = (int) ($opt['id'] ?? 0);
                            if ($option_id > 0) {
                                $options_by_id[$option_id] = (array) $opt;
                            }
                        }
                        $ordered_options = [];
                        foreach ($ordered_option_ids as $option_id) {
                            if (isset($options_by_id[$option_id])) {
                                $ordered_options[] = $options_by_id[$option_id];
                                unset($options_by_id[$option_id]);
                            }
                        }
                        foreach ($editing_options as $opt) {
                            $option_id = (int) ($opt['id'] ?? 0);
                            if ($option_id > 0 && isset($options_by_id[$option_id])) {
                                $ordered_options[] = (array) $opt;
                                unset($options_by_id[$option_id]);
                            }
                        }
                        foreach ($ordered_options as $idx => $opt) {
                            $pos = $idx + 1;
                            if ($pos > 12) {
                                break;
                            }
                            $ordering_option_values[$pos] = (string) ($opt['option_text'] ?? '');
                        }
                    } elseif ($editing_type === 'matching') {
                        $matching_items = isset($editing_detail['items']) && is_array($editing_detail['items'])
                            ? $editing_detail['items']
                            : [];
                        foreach ($matching_items as $idx => $item) {
                            $pos = $idx + 1;
                            if ($pos > 12) {
                                break;
                            }
                            $matching_left_values[$pos] = (string) ($item['prompt_text'] ?? '');
                            $matching_right_values[$pos] = (string) ($item['correct_option_text'] ?? '');
                        }
                    } elseif ($editing_type === 'categorization') {
                        $category_option_index_by_id = [];
                        foreach ($editing_options as $idx => $opt) {
                            $pos = $idx + 1;
                            if ($pos > 8) {
                                break;
                            }
                            $option_id = (int) ($opt['id'] ?? 0);
                            if ($option_id > 0) {
                                $category_option_index_by_id[$option_id] = $pos;
                            }
                            $categorization_category_values[$pos] = (string) ($opt['option_text'] ?? '');
                        }
                        $categorization_items = isset($editing_detail['items']) && is_array($editing_detail['items'])
                            ? $editing_detail['items']
                            : [];
                        foreach ($categorization_items as $idx => $item) {
                            $pos = $idx + 1;
                            if ($pos > 24) {
                                break;
                            }
                            $categorization_item_values[$pos] = (string) ($item['item_text'] ?? '');
                            $correct_option_id = (int) ($item['correct_option_id'] ?? 0);
                            $categorization_item_correct[$pos] = (int) ($category_option_index_by_id[$correct_option_id] ?? 1);
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
                    $editing_short_answer_values = CBT_Admin_Questions_Helper::normalize_short_answer_values((string) ($editing_detail['correct_text'] ?? ($editing_question['correct_text'] ?? '')));
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
                    $editing_tf_matrix_values = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config((string) ($editing_question['correct_text'] ?? ''));
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

                if ($editing_type === 'cloze_dropdown') {
                    $editing_blanks = isset($editing_detail['blanks']) && is_array($editing_detail['blanks'])
                        ? $editing_detail['blanks']
                        : [];
                    foreach ($editing_blanks as $blank) {
                        $pos = (int) ($blank['blank_key'] ?? 0);
                        if ($pos < 1 || $pos > 8) {
                            continue;
                        }
                        $options = array_fill(1, 6, '');
                        $correct = 1;
                        foreach ((array) ($blank['options'] ?? []) as $idx => $option) {
                            $option_pos = $idx + 1;
                            if ($option_pos > 6) {
                                break;
                            }
                            $options[$option_pos] = (string) ($option['option_text'] ?? '');
                            if ((int) ($option['is_correct'] ?? 0) === 1) {
                                $correct = $option_pos;
                            }
                        }
                        $cloze_dropdown_rows[$pos] = [
                            'options' => $options,
                            'correct' => $correct,
                        ];
                    }
                }

                if ($editing_type === 'table_completion') {
                    $table_completion_row_count = max(2, min(8, (int) ($editing_detail['row_count'] ?? $table_completion_row_count)));
                    $table_completion_column_count = max(2, min(6, (int) ($editing_detail['column_count'] ?? $table_completion_column_count)));
                    $editing_cells = isset($editing_detail['cells']) && is_array($editing_detail['cells'])
                        ? $editing_detail['cells']
                        : [];
                    foreach ($editing_cells as $cell) {
                        if (!is_array($cell)) {
                            continue;
                        }
                        $row_position = (int) ($cell['row_position'] ?? 0);
                        $column_position = (int) ($cell['column_position'] ?? 0);
                        if ($row_position < 1 || $row_position > 8 || $column_position < 1 || $column_position > 6) {
                            continue;
                        }
                        $cell_key = chr(64 + $column_position) . (string) $row_position;
                        $options = array_fill(1, 6, '');
                        $correct = 1;
                        foreach ((array) ($cell['options'] ?? []) as $idx => $option) {
                            $option_pos = $idx + 1;
                            if ($option_pos > 6) {
                                break;
                            }
                            $options[$option_pos] = (string) ($option['option_text'] ?? '');
                            if ((int) ($option['is_correct'] ?? 0) === 1) {
                                $correct = $option_pos;
                            }
                        }
                        $correct_values = CBT_Admin_Questions_Helper::normalize_short_answer_values((string) ($cell['correct_text'] ?? ''));
                        $table_completion_cells[$cell_key] = [
                            'cell_type' => (string) ($cell['cell_type'] ?? 'static'),
                            'cell_text' => (string) ($cell['cell_text'] ?? ''),
                            'correct_text' => (string) ($correct_values[0] ?? ''),
                            'options' => $options,
                            'correct' => $correct,
                        ];
                    }
                }

                $question_text_for_count = (string) ($editing_question['question_text'] ?? '');
                $last_non_empty_html_index = static function (array $values, int $max): int {
                    $last = 0;
                    for ($index = 1; $index <= $max; $index++) {
                        if (CBT_Admin_Questions_Helper::has_non_empty_html_content((string) ($values[$index] ?? ''))) {
                            $last = $index;
                        }
                    }

                    return $last;
                };
                $last_non_empty_text_index = static function (array $values, int $max): int {
                    $last = 0;
                    for ($index = 1; $index <= $max; $index++) {
                        if (trim((string) ($values[$index] ?? '')) !== '') {
                            $last = $index;
                        }
                    }

                    return $last;
                };
                $manual_active_count = static function (int $default, int $min, int $max, int $detected) use ($editing_question): int {
                    $target = $editing_question ? max($min, $detected) : $default;
                    if ($target < $min) {
                        $target = $min;
                    }
                    if ($target > $max) {
                        $target = $max;
                    }

                    return $target;
                };
                $extract_max_short_answer_placeholder = static function (string $html): int {
                    $plain = wp_strip_all_tags($html);
                    preg_match_all('/\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i', $plain, $matches);
                    $max = 0;
                    foreach ((array) ($matches[1] ?? []) as $token) {
                        $normalized = strtoupper((string) $token);
                        $index = ctype_digit($normalized) ? (int) $normalized : (ord($normalized) - 64);
                        if ($index >= 1 && $index <= 8) {
                            $max = max($max, $index);
                        }
                    }

                    return $max;
                };
                $extract_max_cloze_placeholder = static function (string $html): int {
                    $plain = wp_strip_all_tags($html);
                    preg_match_all('/\[\s*dropdown(?:\s*[_-]?\s*)?([1-8])\s*\]/i', $plain, $matches);
                    $max = 0;
                    foreach ((array) ($matches[1] ?? []) as $token) {
                        $index = (int) $token;
                        if ($index >= 1 && $index <= 8) {
                            $max = max($max, $index);
                        }
                    }

                    return $max;
                };

                $mc_active_option_count = $manual_active_count(5, 3, 5, $last_non_empty_html_index($mc_option_values, 5));
                $ma_active_option_count = $manual_active_count(5, 3, 12, max($last_non_empty_html_index($ma_option_values, 12), $last_non_empty_text_index($ma_option_correct, 12)));
                $tfm_text_values = [];
                foreach ($tf_matrix_rows as $tfm_index => $tfm_row) {
                    $tfm_text_values[(int) $tfm_index] = (string) ($tfm_row['text'] ?? '');
                }
                $tfm_active_statement_count = $manual_active_count(5, 2, 10, $last_non_empty_html_index($tfm_text_values, 10));
                $short_answer_active_input_count = $manual_active_count(3, 1, 8, max($last_non_empty_text_index($editing_short_answer_inputs, 8), $extract_max_short_answer_placeholder($question_text_for_count)));
                $ordering_active_item_count = $manual_active_count(4, 2, 12, $last_non_empty_html_index($ordering_option_values, 12));
                $matching_active_pair_count = $manual_active_count(3, 2, 12, max($last_non_empty_html_index($matching_left_values, 12), $last_non_empty_html_index($matching_right_values, 12)));
                $cloze_detected_blank_count = $extract_max_cloze_placeholder($question_text_for_count);
                $cloze_detected_option_count = 0;
                foreach ($cloze_dropdown_rows as $blank_index => $blank_row) {
                    $blank_options = isset($blank_row['options']) && is_array($blank_row['options'])
                        ? $blank_row['options']
                        : [];
                    $blank_option_count = $last_non_empty_text_index($blank_options, 6);
                    $cloze_detected_option_count = max($cloze_detected_option_count, $blank_option_count, (int) ($blank_row['correct'] ?? 0));
                    if ($blank_option_count > 0) {
                        $cloze_detected_blank_count = max($cloze_detected_blank_count, (int) $blank_index);
                    }
                }
                $cloze_active_dropdown_count = $manual_active_count(2, 1, 8, $cloze_detected_blank_count);
                $cloze_active_option_count = $manual_active_count(3, 2, 6, $cloze_detected_option_count);
                $categorization_active_category_count = $manual_active_count(2, 2, 8, $last_non_empty_text_index($categorization_category_values, 8));
                $categorization_active_item_count = $manual_active_count(3, 2, 24, $last_non_empty_html_index($categorization_item_values, 24));
        
                $initial_subject_id = $selected_subject_id > 0
                    ? $selected_subject_id
                    : (int) ($subjects[0]['id'] ?? 0);
                $default_question_tab = 'list';
                if ($total_questions === 0) {
                    $default_question_tab = 'form';
                }
                if ($show_import_panel_first) {
                    $default_question_tab = 'import';
                }
                if ($question_import_batch_active) {
                    $default_question_tab = 'list';
                }
                if (!empty($editing_question)) {
                    $default_question_tab = 'form';
                }
                if (!empty($view_question) || is_array($question_delete_state)) {
                    $default_question_tab = 'list';
                }
                $question_tab_is_forced = !empty($editing_question)
                    || $show_import_panel_first
                    || !empty($view_question)
                    || is_array($question_delete_state)
                    || $question_import_batch_active;
                $question_clear_edit_url = add_query_arg($question_list_args, admin_url('admin.php'));
                $question_reset_args = [
                    'page' => $current_page_slug,
                    'cbt_question_per_page' => $list_per_page,
                ];
                if ($lock_question_type && $list_filter_type !== '') {
                    $question_reset_args['filter_type'] = $list_filter_type;
                }
                $question_reset_url = add_query_arg($question_reset_args, admin_url('admin.php'));
                $question_scope_label = $lock_question_type
                    ? (string) ($question_type_labels[$active_question_type] ?? $active_question_type)
                    : 'Semua tipe';
                $list_filter_source_label = $list_filter_source_kind !== ''
                    ? (string) ($source_filter_labels[$list_filter_source_kind] ?? $list_filter_source_kind)
                    : 'Semua sumber';
                $list_filter_subject_label = 'Semua subject';
                if ($list_filter_subject_id > 0) {
                    foreach ($subjects as $subject) {
                        if ((int) ($subject['id'] ?? 0) !== $list_filter_subject_id) {
                            continue;
                        }
                        $list_filter_subject_label = (string) ($subject['name'] ?? 'Subject terpilih');
                        if (!empty($subject['code'])) {
                            $list_filter_subject_label .= ' (' . (string) $subject['code'] . ')';
                        }
                        break;
                    }
                }
                if ($editing_question_is_edit_guarded && $editing_question_source_question_id > 0) {
                    $source_edit_args = array_merge($question_list_args, ['edit' => $editing_question_source_question_id]);
                    $source_view_args = array_merge($question_list_args, ['view' => $editing_question_source_question_id]);
                    $editing_question_source_edit_url = add_query_arg($source_edit_args, admin_url('admin.php'));
                    $editing_question_source_view_url = add_query_arg($source_view_args, admin_url('admin.php'));
                }

        return get_defined_vars();
    }

        /**
         * @param int[] $bank_question_ids
         * @return array<int,array{exam_count:int,descendant_count:int,active_count:int,inactive_count:int}>
         */
        private static function build_bank_usage_summary_map(array $bank_question_ids, bool $is_admin_scope, int $current_user_id): array
        {
            global $wpdb;

            $bank_question_ids = array_values(array_unique(array_filter(array_map('absint', $bank_question_ids))));
            if (empty($bank_question_ids)) {
                return [];
            }

            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $placeholders = implode(',', array_fill(0, count($bank_question_ids), '%d'));
            $where_parts = ["q.source_question_id IN ({$placeholders})"];
            $query_params = $bank_question_ids;
            if (!$is_admin_scope) {
                $where_parts[] = 'target_exam.created_by = %d';
                $query_params[] = $current_user_id;
            }

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT q.source_question_id,
                            COUNT(DISTINCT q.exam_id) AS exam_count,
                            COUNT(*) AS descendant_count,
                            SUM(CASE WHEN COALESCE(q.is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_count,
                            SUM(CASE WHEN COALESCE(q.is_active, 1) = 0 THEN 1 ELSE 0 END) AS inactive_count
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} target_exam ON target_exam.id = q.exam_id
                     WHERE " . implode(' AND ', $where_parts) . '
                     GROUP BY q.source_question_id',
                    ...$query_params
                ),
                ARRAY_A
            );

            $summary_map = [];
            foreach ($bank_question_ids as $bank_question_id) {
                $summary_map[$bank_question_id] = [
                    'exam_count' => 0,
                    'descendant_count' => 0,
                    'active_count' => 0,
                    'inactive_count' => 0,
                ];
            }

            foreach ((array) $rows as $row) {
                $source_question_id = (int) ($row['source_question_id'] ?? 0);
                if ($source_question_id <= 0) {
                    continue;
                }
                $summary_map[$source_question_id] = [
                    'exam_count' => max(0, (int) ($row['exam_count'] ?? 0)),
                    'descendant_count' => max(0, (int) ($row['descendant_count'] ?? 0)),
                    'active_count' => max(0, (int) ($row['active_count'] ?? 0)),
                    'inactive_count' => max(0, (int) ($row['inactive_count'] ?? 0)),
                ];
            }

            return $summary_map;
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function build_bank_reference_rows(int $source_question_id, bool $is_admin_scope, int $current_user_id): array
        {
            global $wpdb;

            if ($source_question_id <= 0) {
                return [];
            }

            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $subject_table = $wpdb->prefix . 'cbt_subjects';
            $where_parts = ['q.source_question_id = %d'];
            $query_params = [$source_question_id];
            if (!$is_admin_scope) {
                $where_parts[] = 'target_exam.created_by = %d';
                $query_params[] = $current_user_id;
            }

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT target_exam.id AS exam_id,
                            target_exam.title AS exam_title,
                            target_exam.status AS exam_status,
                            target_exam.starts_at,
                            target_exam.updated_at,
                            subject.name AS subject_name,
                            subject.code AS subject_code,
                            COUNT(*) AS descendant_count,
                            SUM(CASE WHEN COALESCE(q.is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_count,
                            SUM(CASE WHEN COALESCE(q.is_active, 1) = 0 THEN 1 ELSE 0 END) AS inactive_count
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} target_exam ON target_exam.id = q.exam_id
                     LEFT JOIN {$subject_table} subject ON subject.id = target_exam.subject_id
                     WHERE " . implode(' AND ', $where_parts) . "
                     GROUP BY target_exam.id, target_exam.title, target_exam.status, target_exam.starts_at, target_exam.updated_at, subject.name, subject.code
                     ORDER BY
                        CASE target_exam.status
                            WHEN 'published' THEN 0
                            WHEN 'draft' THEN 1
                            WHEN 'closed' THEN 2
                            ELSE 3
                        END ASC,
                        COALESCE(target_exam.starts_at, target_exam.updated_at, target_exam.created_at) DESC,
                        target_exam.id DESC",
                    ...$query_params
                ),
                ARRAY_A
            );

            $status_labels = [
                'draft' => 'Draft',
                'published' => 'Published',
                'closed' => 'Closed',
            ];

            $result = [];
            foreach ((array) $rows as $row) {
                $exam_id = (int) ($row['exam_id'] ?? 0);
                if ($exam_id <= 0) {
                    continue;
                }

                $subject_name = trim((string) ($row['subject_name'] ?? ''));
                $subject_code = trim((string) ($row['subject_code'] ?? ''));
                $subject_label = $subject_name !== ''
                    ? $subject_name . ($subject_code !== '' ? ' (' . $subject_code . ')' : '')
                    : '-';
                $status = sanitize_key((string) ($row['exam_status'] ?? ''));
                $status_label = (string) ($status_labels[$status] ?? ucfirst($status !== '' ? $status : 'draft'));

                $result[] = [
                    'exam_id' => $exam_id,
                    'exam_title' => (string) ($row['exam_title'] ?? ''),
                    'subject_label' => $subject_label,
                    'status' => $status,
                    'status_label' => $status_label,
                    'descendant_count' => max(0, (int) ($row['descendant_count'] ?? 0)),
                    'active_count' => max(0, (int) ($row['active_count'] ?? 0)),
                    'inactive_count' => max(0, (int) ($row['inactive_count'] ?? 0)),
                    'starts_at' => (string) ($row['starts_at'] ?? ''),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                    'edit_url' => add_query_arg(
                        [
                            'page' => 'cbt-exams',
                            'edit' => $exam_id,
                        ],
                        admin_url('admin.php')
                    ),
                ];
            }

            return $result;
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
            $question_text = isset($_POST['question_text'])
                ? CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST['question_text']))
                : '';
            $question_type = isset($_POST['question_type']) ? sanitize_text_field(wp_unslash($_POST['question_type'])) : 'multiple_choice';
            $points = isset($_POST['points']) ? (float) wp_unslash($_POST['points']) : 1.0;
            $correct_text_raw = isset($_POST['correct_text']) ? (string) wp_unslash($_POST['correct_text']) : '';
            $correct_text = sanitize_text_field($correct_text_raw);
            $essay_answer = isset($_POST['essay_answer'])
                ? CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST['essay_answer']))
                : '';
            $explanation = isset($_POST['explanation'])
                ? CBT_Admin_Questions_Helper::normalize_optional_rich_text((string) wp_unslash($_POST['explanation']))
                : null;
            $options_raw = isset($_POST['options']) ? wp_unslash($_POST['options']) : '';
            $validation_meta_raw = isset($_POST['validation_meta']) ? (string) wp_unslash($_POST['validation_meta']) : '';
            $validation_meta = json_decode($validation_meta_raw, true);
            $validation_meta = is_array($validation_meta) ? $validation_meta : [];
            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug(isset($_POST['return_page']) ? wp_unslash($_POST['return_page']) : 'cbt-question-bank');
            $forced_question_type = CBT_Admin_Questions_Helper::forced_question_type_for_page($return_page);
            $existing_question_type = '';
            $previous_exam_id = 0;
            $previous_question_snapshot = [];

            $allowed_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'];
            if (!in_array($question_type, $allowed_types, true)) {
                $question_type = 'multiple_choice';
            }
            if ($forced_question_type !== '') {
                $question_type = $forced_question_type;
            }
            if ($id > 0) {
                if ($is_admin_scope) {
                    $existing_question_type = (string) $wpdb->get_var(
                        $wpdb->prepare("SELECT question_type FROM {$question_table} WHERE id = %d", $id)
                    );
                } else {
                    $existing_question_type = (string) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT q.question_type
                             FROM {$question_table} q
                             INNER JOIN {$exam_table} e ON e.id = q.exam_id
                             WHERE q.id = %d AND e.created_by = %d
                             LIMIT 1",
                            $id,
                            $current_user_id
                        )
                    );
                }
                if (in_array($existing_question_type, $allowed_types, true)) {
                    $question_type = $existing_question_type;
                }
            }

            $normalized_detail_text = '';
            $matrix_source_rows = [];
            $matrix_provided_indexes = [];
            $matching_items = $question_type === 'matching' ? self::read_matching_items_from_post() : [];
            $cloze_blanks = $question_type === 'cloze_dropdown' ? self::read_cloze_dropdown_blanks_from_post() : [];
            $categorization_categories = $question_type === 'categorization' ? self::read_categorization_categories_from_post() : [];
            $categorization_items = $question_type === 'categorization' ? self::read_categorization_items_from_post() : [];
            $table_completion_definition = $question_type === 'table_completion' ? self::read_table_completion_from_post() : [];
            if ($question_type === 'true_false') {
                $normalized_detail_text = CBT_Admin_Questions_Helper::normalize_true_false_value($correct_text) === 1 ? 'true' : 'false';
            } elseif ($question_type === 'true_false_matrix') {
                $matrix_source_payload = json_decode($correct_text_raw, true);
                if (is_array($matrix_source_payload) && isset($matrix_source_payload['statements']) && is_array($matrix_source_payload['statements'])) {
                    foreach ($matrix_source_payload['statements'] as $statement_row) {
                        if (!is_array($statement_row)) {
                            continue;
                        }

                        $statement_index = isset($statement_row['index']) ? (int) $statement_row['index'] : 0;
                        $statement_text = CBT_Admin_Questions_Helper::sanitize_lightweight_math_html(
                            trim((string) ($statement_row['text'] ?? $statement_row['statement'] ?? ''))
                        );
                        $statement_answer = strtolower(trim((string) ($statement_row['answer'] ?? 'true')));
                        $normalized_statement_row = [
                            'index' => $statement_index,
                            'text' => $statement_text,
                            'answer' => $statement_answer === 'false' ? 'false' : 'true',
                        ];
                        $matrix_source_rows[] = $normalized_statement_row;
                        if ($statement_index > 0) {
                            $matrix_provided_indexes[] = $statement_index;
                        }
                    }
                }
                $normalized_detail_text = (string) wp_json_encode([
                    'statements' => $matrix_source_rows,
                ]);
            } elseif ($question_type === 'short_answer') {
                $normalized_detail_text = CBT_Admin_Questions_Helper::normalize_short_answer_payload($correct_text_raw);
            } elseif ($question_type === 'essay') {
                $normalized_detail_text = trim($essay_answer);
            } elseif ($question_type === 'ordering') {
                $normalized_detail_text = '';
            } elseif ($question_type === 'matching') {
                $normalized_detail_text = CBT_Admin_Questions_Helper::build_matching_payload($matching_items);
            } elseif ($question_type === 'cloze_dropdown') {
                $normalized_detail_text = CBT_Admin_Questions_Helper::build_cloze_dropdown_payload($cloze_blanks);
            } elseif ($question_type === 'categorization') {
                $normalized_detail_text = CBT_Admin_Questions_Helper::build_categorization_payload($categorization_categories, $categorization_items);
            } elseif ($question_type === 'table_completion') {
                $normalized_detail_text = CBT_Admin_Questions_Helper::build_table_completion_payload($table_completion_definition);
            }
    
            $resolved_bank_exam_id = 0;
            if ($subject_id > 0) {
                $resolved_bank_exam_id = CBT_Admin_Questions_Helper::ensure_subject_question_bank_exam($subject_id, $is_admin_scope, $current_user_id);
            }
            if ($id <= 0) {
                $exam_id = $resolved_bank_exam_id;
            } elseif ($exam_id <= 0 && $resolved_bank_exam_id > 0) {
                $exam_id = $resolved_bank_exam_id;
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

            if ($question_type === 'matching') {
                $matching_validation_error = CBT_Admin_Questions_Helper::validate_matching_items($matching_items);
                if ($matching_validation_error !== '') {
                    self::redirect_question_import_with_error($matching_validation_error, $return_page);
                }
            }

            if ($question_type === 'cloze_dropdown') {
                $cloze_validation_error = CBT_Admin_Questions_Helper::validate_cloze_dropdown_definition($question_text, $cloze_blanks);
                if ($cloze_validation_error !== '') {
                    self::redirect_question_import_with_error($cloze_validation_error, $return_page);
                }
            }

            if ($question_type === 'categorization') {
                $categorization_validation_error = CBT_Admin_Questions_Helper::validate_categorization_definition($categorization_categories, $categorization_items);
                if ($categorization_validation_error !== '') {
                    self::redirect_question_import_with_error($categorization_validation_error, $return_page);
                }
            }

            if ($question_type === 'table_completion') {
                $table_completion_validation_error = CBT_Admin_Questions_Helper::validate_table_completion_definition($table_completion_definition);
                if ($table_completion_validation_error !== '') {
                    self::redirect_question_import_with_error($table_completion_validation_error, $return_page);
                }
            }

            if ($question_type === 'short_answer') {
                $short_answer_values = CBT_Admin_Questions_Helper::normalize_short_answer_values($normalized_detail_text);
                $short_answer_validation_error = CBT_Admin_Questions_Helper::validate_short_answer_definition(
                    $question_text,
                    $short_answer_values,
                    [
                        'provided_keys' => isset($validation_meta['provided_keys']) && is_array($validation_meta['provided_keys'])
                            ? $validation_meta['provided_keys']
                            : [],
                    ]
                );
                if ($short_answer_validation_error !== '') {
                    self::redirect_question_import_with_error($short_answer_validation_error, $return_page);
                }
            }

            if ($question_type === 'true_false_matrix') {
                $matrix_rows = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config($normalized_detail_text);
                $matrix_validation_error = CBT_Admin_Questions_Helper::validate_true_false_matrix_items(
                    $matrix_rows,
                    [
                        'provided_indexes' => $matrix_provided_indexes,
                        'source_rows' => $matrix_source_rows,
                    ]
                );
                if ($matrix_validation_error !== '') {
                    self::redirect_question_import_with_error($matrix_validation_error, $return_page);
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
                'explanation' => $explanation,
                'updated_at' => current_time('mysql'),
            ];
    
            if ($id > 0) {
                $previous_question_snapshot = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($id);
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

                $editing_lineage_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT q.source_question_id,
                                target_exam.title AS exam_title,
                                source_exam.title AS source_exam_title
                         FROM {$question_table} q
                         INNER JOIN {$exam_table} target_exam ON target_exam.id = q.exam_id
                         LEFT JOIN {$question_table} source_q ON source_q.id = q.source_question_id
                         LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_q.exam_id
                         WHERE q.id = %d
                         LIMIT 1",
                        $id
                    ),
                    ARRAY_A
                );
                $editing_source_question_id = (int) ($editing_lineage_row['source_question_id'] ?? 0);
                $editing_exam_title = (string) ($editing_lineage_row['exam_title'] ?? '');
                $editing_source_exam_title = (string) ($editing_lineage_row['source_exam_title'] ?? '');
                $editing_is_bank_backed = stripos($editing_exam_title, 'Bank Soal - ') !== 0
                    && $editing_source_question_id > 0
                    && stripos($editing_source_exam_title, 'Bank Soal - ') === 0;

                if ($editing_is_bank_backed) {
                    wp_safe_redirect(add_query_arg(
                        [
                            'page' => $return_page,
                            'edit' => $editing_source_question_id,
                            'cbt_err' => 'Soal bank-backed tidak diedit langsung. Ubah sumbernya di Bank Soal agar sinkronisasi tetap satu arah.',
                        ],
                        admin_url('admin.php')
                    ));
                    exit;
                }
    
                $wpdb->update(
                    $question_table,
                    $data,
                    ['id' => $id],
                    ['%d', '%s', '%s', '%f', '%s', '%s', '%s'],
                    ['%d']
                );
                $question_id = $id;
            } else {
                $data['created_at'] = current_time('mysql');
    
                $wpdb->insert(
                    $question_table,
                    $data,
                    ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
                );
                $question_id = (int) $wpdb->insert_id;
            }
    
            if ($question_id > 0) {
                $wpdb->delete($option_table, ['question_id' => $question_id], ['%d']);
    
                $options_to_insert = CBT_Admin_Questions_Helper::parse_options($options_raw);
    
                if ($question_type === 'multiple_choice') {
                    $selected_correct_index = isset($validation_meta['selected_correct_index']) ? (int) $validation_meta['selected_correct_index'] : 0;
                    $has_empty_correct_reference = CBT_Admin_Questions_Helper::has_empty_correct_option_reference((string) $options_raw);
                    if (
                        !$has_empty_correct_reference &&
                        $selected_correct_index >= 1 &&
                        $selected_correct_index <= 5 &&
                        !self::manual_editor_field_has_content('cbt_mc_option_' . $selected_correct_index)
                    ) {
                        $has_empty_correct_reference = true;
                    }
                    $choice_validation_error = CBT_Admin_Questions_Helper::validate_choice_options(
                        'multiple_choice',
                        $options_to_insert,
                        ['has_empty_correct_reference' => $has_empty_correct_reference]
                    );
                    if ($choice_validation_error !== '') {
                        self::redirect_question_import_with_error($choice_validation_error, $return_page);
                    }
                }

                if ($question_type === 'multiple_answer') {
                    $selected_correct_indexes = isset($validation_meta['selected_correct_indexes']) && is_array($validation_meta['selected_correct_indexes'])
                        ? array_values(array_unique(array_map('intval', $validation_meta['selected_correct_indexes'])))
                        : [];
                    $has_empty_correct_reference = CBT_Admin_Questions_Helper::has_empty_correct_option_reference((string) $options_raw);
                    if (!$has_empty_correct_reference) {
                        foreach ($selected_correct_indexes as $selected_index) {
                            if ($selected_index < 1 || $selected_index > 12) {
                                continue;
                            }
                            if (!self::manual_editor_field_has_content('cbt_ma_option_' . $selected_index)) {
                                $has_empty_correct_reference = true;
                                break;
                            }
                        }
                    }
                    $choice_validation_error = CBT_Admin_Questions_Helper::validate_choice_options(
                        'multiple_answer',
                        $options_to_insert,
                        ['has_empty_correct_reference' => $has_empty_correct_reference]
                    );
                    if ($choice_validation_error !== '') {
                        self::redirect_question_import_with_error($choice_validation_error, $return_page);
                    }
                }

                if ($question_type === 'ordering') {
                    $ordering_validation_error = CBT_Admin_Questions_Helper::validate_ordering_options($options_to_insert);
                    if ($ordering_validation_error !== '') {
                        self::redirect_question_import_with_error($ordering_validation_error, $return_page);
                    }
                    foreach ($options_to_insert as $ordering_idx => $ordering_option) {
                        $options_to_insert[$ordering_idx]['is_correct'] = 0;
                    }
                }

                if ($question_type === 'matching') {
                    $options_to_insert = array_map(static function (array $item): array {
                        return [
                            'option_text' => (string) ($item['option_text'] ?? ''),
                            'is_correct' => 0,
                        ];
                    }, $matching_items);
                }

                if ($question_type === 'cloze_dropdown') {
                    $options_to_insert = [];
                }

                if ($question_type === 'categorization') {
                    $options_to_insert = array_map(static function (array $category): array {
                        return [
                            'option_text' => (string) ($category['option_text'] ?? ''),
                            'is_correct' => 0,
                        ];
                    }, $categorization_categories);
                }

                if ($question_type === 'table_completion') {
                    $options_to_insert = [];
                }
    
                if ($question_type === 'true_false' && empty($options_to_insert)) {
                    $true_is_correct = CBT_Admin_Questions_Helper::normalize_true_false_value($normalized_detail_text) === 1 ? 1 : 0;
                    $options_to_insert = [
                        ['option_text' => 'True', 'is_correct' => $true_is_correct],
                        ['option_text' => 'False', 'is_correct' => $true_is_correct ? 0 : 1],
                    ];
                }
    
                $inserted_option_ids = [];
                foreach ($options_to_insert as $idx => $opt) {
                    $inserted = $wpdb->insert(
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
                    if ($inserted !== false) {
                        $inserted_option_ids[] = (int) $wpdb->insert_id;
                    }
                }

                $matching_detail_items = [];
                if ($question_type === 'matching') {
                    foreach ($matching_items as $idx => $matching_item) {
                        $option_id = (int) ($inserted_option_ids[$idx] ?? 0);
                        if ($option_id <= 0) {
                            continue;
                        }
                        $matching_detail_items[] = [
                            'position' => (int) ($matching_item['position'] ?? ($idx + 1)),
                            'item_key' => (string) ($matching_item['item_key'] ?? ($idx + 1)),
                            'prompt_text' => (string) ($matching_item['prompt_text'] ?? ''),
                            'correct_option_id' => $option_id,
                        ];
                    }
                }

                $categorization_detail_items = [];
                if ($question_type === 'categorization') {
                    foreach ($categorization_items as $idx => $categorization_item) {
                        $category_index = (int) ($categorization_item['correct_category_index'] ?? 0);
                        $option_id = $category_index > 0 ? (int) ($inserted_option_ids[$category_index - 1] ?? 0) : 0;
                        if ($option_id <= 0) {
                            continue;
                        }
                        $categorization_detail_items[] = [
                            'position' => (int) ($categorization_item['position'] ?? ($idx + 1)),
                            'item_key' => (string) ($categorization_item['item_key'] ?? ($idx + 1)),
                            'item_text' => (string) ($categorization_item['item_text'] ?? ''),
                            'correct_option_id' => $option_id,
                        ];
                    }
                }

                $detail_context = ['ordered_option_ids' => $inserted_option_ids];
                if ($question_type === 'matching') {
                    $detail_context['matching_items'] = $matching_detail_items;
                }
                if ($question_type === 'cloze_dropdown') {
                    $detail_context['cloze_blanks'] = $cloze_blanks;
                }
                if ($question_type === 'categorization') {
                    $detail_context['categorization_items'] = $categorization_detail_items;
                }
                if ($question_type === 'table_completion') {
                    $detail_context['table_completion'] = $table_completion_definition;
                }
    
                CBT_Admin_Questions_Helper::save_question_type_detail(
                    $question_id,
                    $question_type,
                    $normalized_detail_text,
                    $detail_context
                );
            }
    
            CBT_Cache::invalidate_catalog();
            $affected_exam_ids = [];
            if ($exam_id > 0) {
                $affected_exam_ids[$exam_id] = $exam_id;
            }
            if ($previous_exam_id > 0 && $previous_exam_id !== $exam_id) {
                $affected_exam_ids[$previous_exam_id] = $previous_exam_id;
            }

            $partial_snapshot_question_ids_by_exam = [];
            if ($id > 0 && $question_id > 0 && $exam_id > 0 && $previous_exam_id === $exam_id) {
                $partial_snapshot_question_ids_by_exam[$exam_id][$question_id] = $question_id;
            }
    
            if ($question_id > 0) {
                $current_question_snapshot = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($question_id);
                if (
                    !empty($previous_question_snapshot) &&
                    CBT_Admin_Questions_Sync_Helper::is_bank_question_snapshot($previous_question_snapshot) &&
                    !empty($current_question_snapshot)
                ) {
                    $bank_update_targets = CBT_Admin_Questions_Sync_Helper::propagate_bank_question_update_with_targets($question_id, $previous_question_snapshot, $current_question_snapshot);
                    foreach ($bank_update_targets as $bank_update_target) {
                        $affected_exam_id = (int) ($bank_update_target['exam_id'] ?? 0);
                        $affected_question_id = (int) ($bank_update_target['question_id'] ?? 0);
                        if ($affected_exam_id > 0) {
                            $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
                        }
                        if ($affected_exam_id > 0 && $affected_question_id > 0) {
                            $partial_snapshot_question_ids_by_exam[$affected_exam_id][$affected_question_id] = $affected_question_id;
                        }
                    }
                }
            }
    
            self::refresh_exam_question_delivery_snapshots_after_question_updates(
                array_values($affected_exam_ids),
                $partial_snapshot_question_ids_by_exam
            );
    
            $success_message = $id > 0 ? 'Question updated' : 'Question saved to Bank Soal';
            wp_safe_redirect(add_query_arg(
                [
                    'page' => $return_page,
                    'cbt_msg' => $success_message,
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
            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug(isset($_GET['return_page']) ? wp_unslash($_GET['return_page']) : 'cbt-question-bank');
            $filter_exam_id = isset($_GET['filter_exam_id']) ? absint($_GET['filter_exam_id']) : 0;
            $filter_type = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : '';
            $filter_source_kind = isset($_GET['filter_source_kind']) ? sanitize_text_field(wp_unslash($_GET['filter_source_kind'])) : '';
            $filter_subject_id = isset($_GET['filter_subject_id']) ? absint(wp_unslash($_GET['filter_subject_id'])) : 0;
            $question_per_page = self::normalize_standard_list_per_page(
                isset($_GET['question_per_page']) ? absint(wp_unslash($_GET['question_per_page'])) : 20
            );
            $question_paged = isset($_GET['question_paged']) ? max(1, absint(wp_unslash($_GET['question_paged']))) : 1;
            $allowed_filter_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'];
            if (!in_array($filter_type, $allowed_filter_types, true)) {
                $filter_type = '';
            }
            $allowed_source_filters = ['bank', 'bank_backed', 'legacy'];
            if (!in_array($filter_source_kind, $allowed_source_filters, true)) {
                $filter_source_kind = '';
            }
            $question_import_token = isset($_GET['cbt_question_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_question_import_token'])) : '';
            $question_import_scope = isset($_GET['cbt_question_import_scope'])
                ? self::normalize_question_import_scope((string) wp_unslash($_GET['cbt_question_import_scope']))
                : '';
            $question_import_batch_ids = [];
            $question_import_batch_notice = '';
            if ($question_import_token !== '' && $question_import_scope === self::QUESTION_IMPORT_SCOPE_CREATED) {
                $question_import_batch_ids = CBT_Admin_Questions_Import_Helper::get_question_import_created_question_ids_for_current_user($question_import_token);
                if (empty($question_import_batch_ids)) {
                    $question_import_batch_notice = 'Sesi hasil import batch sudah berakhir. Kembali menampilkan semua soal.';
                    $question_import_scope = '';
                }
            }
    
            if ($id > 0) {
                global $wpdb;
                $affected_exam_ids = self::collect_impacted_exam_ids_for_question_ids([$id]);
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
                if (self::has_in_progress_attempts_for_exam_ids($affected_exam_ids)) {
                    $redirect_args = [
                        'page' => $return_page,
                        'cbt_err' => self::active_attempt_delete_error_message(),
                        'cbt_question_per_page' => $question_per_page,
                        'cbt_question_paged' => $question_paged,
                    ];
                    if ($filter_exam_id > 0) {
                        $redirect_args['filter_exam_id'] = $filter_exam_id;
                    }
                    if ($filter_type !== '') {
                        $redirect_args['filter_type'] = $filter_type;
                    }
                    if ($filter_source_kind !== '') {
                        $redirect_args['filter_source_kind'] = $filter_source_kind;
                    }
                    if ($filter_subject_id > 0) {
                        $redirect_args['filter_subject_id'] = $filter_subject_id;
                    }
                    if ($question_import_batch_notice === '') {
                        $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
                    }

                    self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                }
                CBT_Admin_Questions_Helper::delete_question_dependents([$id]);
                $wpdb->delete($wpdb->prefix . 'cbt_questions', ['id' => $id], ['%d']);
                if ($question_import_token !== '' && $question_import_scope === self::QUESTION_IMPORT_SCOPE_CREATED && in_array($id, $question_import_batch_ids, true)) {
                    CBT_Admin_Questions_Import_Helper::remove_question_import_created_question_ids_for_current_user($question_import_token, [$id]);
                }
                if (!empty($affected_exam_ids)) {
                    CBT_Cache::invalidate_catalog();
                    CBT_Cache::invalidate_exams($affected_exam_ids);
                    self::warm_exam_question_delivery_snapshots($affected_exam_ids);
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
            if ($filter_source_kind !== '') {
                $redirect_args['filter_source_kind'] = $filter_source_kind;
            }
            if ($filter_subject_id > 0) {
                $redirect_args['filter_subject_id'] = $filter_subject_id;
            }
            if ($question_import_batch_notice !== '') {
                $redirect_args['cbt_err'] = $question_import_batch_notice;
            } else {
                $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
            }

            self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        }

        public static function handle_delete_all_import_batch_questions(): void
        {
            if (!current_user_can('cbt_manage_questions')) {
                wp_die('Unauthorized');
            }

            check_admin_referer('cbt_delete_all_import_batch_questions');

            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug(isset($_GET['return_page']) ? wp_unslash($_GET['return_page']) : 'cbt-question-bank');
            $question_per_page = self::normalize_standard_list_per_page(
                isset($_GET['question_per_page']) ? absint(wp_unslash($_GET['question_per_page'])) : 20
            );
            $question_paged = isset($_GET['question_paged']) ? max(1, absint(wp_unslash($_GET['question_paged']))) : 1;
            $question_import_token = isset($_GET['cbt_question_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_question_import_token'])) : '';
            $question_import_scope = isset($_GET['cbt_question_import_scope'])
                ? self::normalize_question_import_scope((string) wp_unslash($_GET['cbt_question_import_scope']))
                : '';

            $redirect_args = [
                'page' => $return_page,
                'cbt_question_per_page' => $question_per_page,
                'cbt_question_paged' => $question_paged,
            ];

            if ($question_import_token === '' || $question_import_scope !== self::QUESTION_IMPORT_SCOPE_CREATED) {
                $redirect_args['cbt_err'] = 'Batch hasil import tidak valid untuk dihapus.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }

            $target_ids = CBT_Admin_Questions_Import_Helper::get_question_import_created_question_ids_for_current_user($question_import_token);
            if (empty($target_ids)) {
                $redirect_args['cbt_err'] = 'Sesi hasil import batch sudah berakhir atau batch ini sudah kosong.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }

            global $wpdb;
            $affected_exam_ids = self::collect_impacted_exam_ids_for_question_ids($target_ids);
            if (self::has_in_progress_attempts_for_exam_ids($affected_exam_ids)) {
                $redirect_args['cbt_err'] = self::active_attempt_delete_error_message();
                $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }

            $state = [
                'return_page' => $return_page,
                'question_per_page' => $question_per_page,
                'question_paged' => $question_paged,
                'affected_exam_ids' => array_values($affected_exam_ids),
                'question_import_token' => $question_import_token,
                'question_import_scope' => $question_import_scope,
            ];
            $token = self::start_question_delete_session($target_ids, $state);
            if ($token === '') {
                $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
                $redirect_args['cbt_err'] = 'Gagal menyiapkan sesi hapus batch hasil import. Coba lagi.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }

            $redirect_args['cbt_question_delete_token'] = $token;
            $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
            self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
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
            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug(isset($_POST['return_page']) ? wp_unslash($_POST['return_page']) : 'cbt-question-bank');
            $filter_exam_id = isset($_POST['redirect_filter_exam_id']) ? absint($_POST['redirect_filter_exam_id']) : 0;
            $filter_type = isset($_POST['redirect_filter_type']) ? sanitize_text_field(wp_unslash($_POST['redirect_filter_type'])) : '';
            $filter_source_kind = isset($_POST['redirect_filter_source_kind']) ? sanitize_text_field(wp_unslash($_POST['redirect_filter_source_kind'])) : '';
            $filter_subject_id = isset($_POST['redirect_filter_subject_id']) ? absint(wp_unslash($_POST['redirect_filter_subject_id'])) : 0;
            $question_per_page = self::normalize_standard_list_per_page(
                isset($_POST['redirect_question_per_page']) ? absint(wp_unslash($_POST['redirect_question_per_page'])) : 20
            );
            $question_paged = isset($_POST['redirect_question_paged']) ? max(1, absint(wp_unslash($_POST['redirect_question_paged']))) : 1;
            $allowed_filter_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'];
            if (!in_array($filter_type, $allowed_filter_types, true)) {
                $filter_type = '';
            }
            $allowed_source_filters = ['bank', 'bank_backed', 'legacy'];
            if (!in_array($filter_source_kind, $allowed_source_filters, true)) {
                $filter_source_kind = '';
            }
            $question_import_token = isset($_POST['cbt_question_import_token']) ? sanitize_key((string) wp_unslash($_POST['cbt_question_import_token'])) : '';
            $question_import_scope = isset($_POST['cbt_question_import_scope'])
                ? self::normalize_question_import_scope((string) wp_unslash($_POST['cbt_question_import_scope']))
                : '';
            $question_import_batch_ids = [];
            $question_import_batch_expired = false;
    
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
            if ($filter_source_kind !== '') {
                $redirect_args['filter_source_kind'] = $filter_source_kind;
            }
            if ($filter_subject_id > 0) {
                $redirect_args['filter_subject_id'] = $filter_subject_id;
            }
            if ($question_import_token !== '' && $question_import_scope === self::QUESTION_IMPORT_SCOPE_CREATED) {
                $question_import_batch_ids = CBT_Admin_Questions_Import_Helper::get_question_import_created_question_ids_for_current_user($question_import_token);
                if (empty($question_import_batch_ids)) {
                    $question_import_scope = '';
                    $question_import_batch_expired = true;
                    $redirect_args['cbt_err'] = 'Sesi hasil import batch sudah berakhir. Kembali menampilkan semua soal.';
                } else {
                    $question_ids = array_values(array_intersect($question_ids, $question_import_batch_ids));
                    $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
                }
            }
            if ($question_import_batch_expired) {
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }
    
            if (empty($question_ids)) {
                $redirect_args['cbt_err'] = 'Pilih minimal satu soal untuk dihapus.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
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
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }
    
            $affected_exam_ids = self::collect_impacted_exam_ids_for_question_ids($target_ids);
            if (self::has_in_progress_attempts_for_exam_ids($affected_exam_ids)) {
                $redirect_args['cbt_err'] = self::active_attempt_delete_error_message();
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }

            $state = [
                'return_page' => $return_page,
                'filter_exam_id' => $filter_exam_id,
                'filter_type' => $filter_type,
                'filter_source_kind' => $filter_source_kind,
                'filter_subject_id' => $filter_subject_id,
                'question_per_page' => $question_per_page,
                'question_paged' => $question_paged,
                'affected_exam_ids' => array_values($affected_exam_ids),
                'question_import_token' => $question_import_token,
                'question_import_scope' => $question_import_scope,
            ];
            $token = self::start_question_delete_session($target_ids, $state);
            if ($token === '') {
                $redirect_args['cbt_err'] = 'Gagal menyiapkan sesi hapus soal. Coba lagi.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }
    
            $redirect_args['cbt_question_delete_token'] = $token;
            self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        }

        private static function continue_bulk_delete_questions(string $token): void
        {
            $state = self::get_question_delete_state_for_current_user($token);
            if (!is_array($state)) {
                self::clear_question_delete_transients($token);
                self::redirect_question_import_with_error('Sesi hapus soal berakhir. Silakan pilih ulang soal yang ingin dihapus.');
            }
    
            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug((string) ($state['return_page'] ?? 'cbt-question-bank'));
            $filter_exam_id = isset($state['filter_exam_id']) ? absint($state['filter_exam_id']) : 0;
            $filter_type = isset($state['filter_type']) ? sanitize_text_field((string) $state['filter_type']) : '';
            $filter_source_kind = isset($state['filter_source_kind']) ? sanitize_text_field((string) $state['filter_source_kind']) : '';
            $filter_subject_id = isset($state['filter_subject_id']) ? absint($state['filter_subject_id']) : 0;
            $allowed_filter_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'];
            if (!in_array($filter_type, $allowed_filter_types, true)) {
                $filter_type = '';
            }
            $allowed_source_filters = ['bank', 'bank_backed', 'legacy'];
            if (!in_array($filter_source_kind, $allowed_source_filters, true)) {
                $filter_source_kind = '';
            }
            $question_per_page = self::normalize_standard_list_per_page(isset($state['question_per_page']) ? (int) $state['question_per_page'] : 20);
            $question_paged = isset($state['question_paged']) ? max(1, (int) $state['question_paged']) : 1;
            $question_import_token = isset($state['question_import_token']) ? sanitize_key((string) $state['question_import_token']) : '';
            $question_import_scope = isset($state['question_import_scope'])
                ? self::normalize_question_import_scope((string) $state['question_import_scope'])
                : '';
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
            if ($filter_source_kind !== '') {
                $redirect_args['filter_source_kind'] = $filter_source_kind;
            }
            if ($filter_subject_id > 0) {
                $redirect_args['filter_subject_id'] = $filter_subject_id;
            }
            $redirect_args = self::add_question_import_batch_scope_args($redirect_args, $question_import_token, $question_import_scope);
    
            $target_ids = get_transient(self::get_question_delete_rows_key($token));
            if (!is_array($target_ids) || empty($target_ids)) {
                self::clear_question_delete_transients($token);
                $redirect_args['cbt_err'] = 'Data batch hapus soal tidak ditemukan. Silakan pilih ulang soal.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }
    
            $target_ids = array_values(array_map('intval', $target_ids));
            $total = isset($state['total']) ? (int) $state['total'] : count($target_ids);
            $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
            $deleted = isset($state['deleted']) ? (int) $state['deleted'] : 0;
            $failed = isset($state['failed']) ? (int) $state['failed'] : 0;
            if ($total <= 0 || empty($target_ids)) {
                self::clear_question_delete_transients($token);
                $redirect_args['cbt_err'] = 'Data hapus soal kosong.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
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
            if (self::has_in_progress_attempts_for_exam_ids(array_values($affected_exam_ids))) {
                self::clear_question_delete_transients($token);
                $redirect_args['cbt_err'] = 'Proses hapus dibatalkan karena masih ada peserta aktif pada exam terkait.';
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }
    
            $batch_size = self::get_question_delete_batch_size();
            $max_batch_seconds = self::get_question_delete_max_batch_seconds();
            $target_end = min($offset + $batch_size, $total);
            $end = $offset;
            $batch_started_at = microtime(true);
            $deleted_question_ids = [];
    
            global $wpdb;
            for ($index = $offset; $index < $target_end; $index++) {
                $question_id = isset($target_ids[$index]) ? (int) $target_ids[$index] : 0;
                if ($question_id <= 0) {
                    $failed++;
                    $end = $index + 1;
                    continue;
                }

                CBT_Admin_Questions_Helper::delete_question_dependents([$question_id]);
                $deleted_rows = $wpdb->delete($wpdb->prefix . 'cbt_questions', ['id' => $question_id], ['%d']);
                if ($deleted_rows) {
                    $deleted += (int) $deleted_rows;
                    $deleted_question_ids[] = $question_id;
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
            if (!empty($deleted_question_ids) && $question_import_token !== '' && $question_import_scope === self::QUESTION_IMPORT_SCOPE_CREATED) {
                CBT_Admin_Questions_Import_Helper::remove_question_import_created_question_ids_for_current_user(
                    $question_import_token,
                    $deleted_question_ids
                );
            }
    
            if ((int) $state['offset'] < $total) {
                $state_saved = set_transient(self::get_question_delete_state_key($token), $state, 12 * HOUR_IN_SECONDS);
                if (!$state_saved) {
                    self::clear_question_delete_transients($token);
                    $redirect_args['cbt_err'] = 'Gagal menyimpan progres hapus soal. Silakan ulangi proses.';
                    self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                }
                $redirect_args['cbt_question_delete_token'] = $token;
                self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            }
    
            self::clear_question_delete_transients($token);
            if ($deleted > 0) {
                CBT_Cache::invalidate_catalog();
                CBT_Cache::invalidate_exams(array_values($affected_exam_ids));
                self::warm_exam_question_delivery_snapshots(array_values($affected_exam_ids));
                $redirect_args['cbt_msg'] = sprintf('Hapus soal selesai. Total: %d, Deleted: %d, Failed: %d', $total, $deleted, $failed);
            } else {
                $redirect_args['cbt_err'] = 'Tidak ada soal yang berhasil dihapus.';
            }
    
            self::dispatch_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
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

        public static function get_question_delete_state_for_current_user(string $token): ?array
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

        private static function manual_editor_field_has_content(string $field_name): bool
        {
            if ($field_name === '' || !isset($_POST[$field_name])) {
                return false;
            }

            $value = CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST[$field_name]));

            return CBT_Admin_Questions_Helper::has_non_empty_html_content($value);
        }

        private static function read_manual_count_from_post(string $field_name, int $fallback, int $min, int $max): int
        {
            $value = isset($_POST[$field_name]) ? (int) wp_unslash($_POST[$field_name]) : $fallback;
            if ($value < $min) {
                $value = $min;
            }
            if ($value > $max) {
                $value = $max;
            }

            return $value;
        }

        /**
         * @return array<int,array{position:int,item_key:string,prompt_text:string,option_text:string}>
         */
        private static function read_matching_items_from_post(): array
        {
            $rows = [];
            $pair_count = self::read_manual_count_from_post('cbt_matching_pair_count', 12, 2, 12);
            for ($index = 1; $index <= $pair_count; $index++) {
                $left_field = 'cbt_matching_left_' . $index;
                $right_field = 'cbt_matching_right_' . $index;
                $rows[] = [
                    'position' => $index,
                    'prompt_text' => isset($_POST[$left_field])
                        ? CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST[$left_field]))
                        : '',
                    'option_text' => isset($_POST[$right_field])
                        ? CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST[$right_field]))
                        : '',
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_matching_items($rows);
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function read_cloze_dropdown_blanks_from_post(): array
        {
            $rows = [];
            $blank_count = self::read_manual_count_from_post('cbt_cloze_dropdown_count', 8, 1, 8);
            $option_count = self::read_manual_count_from_post('cbt_cloze_option_count', 6, 2, 6);
            for ($blank_index = 1; $blank_index <= $blank_count; $blank_index++) {
                $options = [];
                $correct_index = isset($_POST['cbt_cloze_correct_' . $blank_index])
                    ? (int) wp_unslash($_POST['cbt_cloze_correct_' . $blank_index])
                    : 1;
                for ($option_index = 1; $option_index <= $option_count; $option_index++) {
                    $field = 'cbt_cloze_' . $blank_index . '_option_' . $option_index;
                    $value = isset($_POST[$field]) ? sanitize_text_field((string) wp_unslash($_POST[$field])) : '';
                    if (trim($value) === '') {
                        continue;
                    }
                    $options[] = [
                        'option_key' => chr(64 + count($options) + 1),
                        'option_text' => $value,
                        'is_correct' => ($option_index === $correct_index) ? 1 : 0,
                        'option_order' => count($options) + 1,
                    ];
                }

                if (empty($options)) {
                    continue;
                }

                $rows[] = [
                    'blank_key' => (string) $blank_index,
                    'blank_position' => $blank_index,
                    'options' => $options,
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_cloze_dropdown_blanks($rows);
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function read_categorization_categories_from_post(): array
        {
            $rows = [];
            $category_count = self::read_manual_count_from_post('cbt_cat_category_count', 8, 2, 8);
            for ($index = 1; $index <= $category_count; $index++) {
                $field = 'cbt_cat_category_' . $index;
                $rows[] = [
                    'category_index' => $index,
                    'option_text' => isset($_POST[$field])
                        ? sanitize_text_field((string) wp_unslash($_POST[$field]))
                        : '',
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_categorization_categories($rows);
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function read_categorization_items_from_post(): array
        {
            $rows = [];
            $item_count = self::read_manual_count_from_post('cbt_cat_item_count', 24, 2, 24);
            for ($index = 1; $index <= $item_count; $index++) {
                $item_field = 'cbt_cat_item_' . $index;
                $correct_field = 'cbt_cat_correct_' . $index;
                $rows[] = [
                    'position' => $index,
                    'item_key' => (string) $index,
                    'item_text' => isset($_POST[$item_field])
                        ? CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST[$item_field]))
                        : '',
                    'correct_category_index' => isset($_POST[$correct_field])
                        ? (int) wp_unslash($_POST[$correct_field])
                        : 0,
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_categorization_items($rows);
        }

        /**
         * @return array<string,mixed>
         */
        private static function read_table_completion_from_post(): array
        {
            $row_count = self::read_manual_count_from_post('cbt_table_rows', 2, 2, 8);
            $column_count = self::read_manual_count_from_post('cbt_table_cols', 2, 2, 6);
            $cells = [];

            for ($row = 1; $row <= $row_count; $row++) {
                for ($column = 1; $column <= $column_count; $column++) {
                    $cell_key = chr(64 + $column) . (string) $row;
                    $prefix = 'cbt_table_' . $cell_key . '_';
                    $type = isset($_POST[$prefix . 'type']) ? sanitize_key((string) wp_unslash($_POST[$prefix . 'type'])) : 'static';
                    if (!in_array($type, ['static', 'text', 'dropdown'], true)) {
                        $type = 'static';
                    }

                    $options = [];
                    $correct_index = isset($_POST[$prefix . 'correct']) ? (int) wp_unslash($_POST[$prefix . 'correct']) : 1;
                    if ($type === 'dropdown') {
                        for ($option_index = 1; $option_index <= 6; $option_index++) {
                            $option_field = $prefix . 'option_' . $option_index;
                            $option_text = isset($_POST[$option_field])
                                ? sanitize_text_field((string) wp_unslash($_POST[$option_field]))
                                : '';
                            if (trim($option_text) === '') {
                                continue;
                            }
                            $options[] = [
                                'option_key' => chr(64 + count($options) + 1),
                                'option_text' => $option_text,
                                'is_correct' => ($option_index === $correct_index) ? 1 : 0,
                                'option_order' => count($options) + 1,
                            ];
                        }
                    }

                    $cells[] = [
                        'cell_key' => $cell_key,
                        'row_position' => $row,
                        'column_position' => $column,
                        'cell_type' => $type,
                        'cell_text' => isset($_POST[$prefix . 'text'])
                            ? CBT_Admin_Questions_Helper::sanitize_editor_html((string) wp_unslash($_POST[$prefix . 'text']))
                            : '',
                        'correct_text' => isset($_POST[$prefix . 'answer'])
                            ? sanitize_text_field((string) wp_unslash($_POST[$prefix . 'answer']))
                            : '',
                        'options' => $options,
                    ];
                }
            }

            return CBT_Admin_Questions_Helper::normalize_table_completion_definition([
                'row_count' => $row_count,
                'column_count' => $column_count,
                'cells' => $cells,
            ]);
        }

        private static function redirect_question_import_with_error(string $message, string $return_page = 'cbt-question-bank'): void
        {
            wp_safe_redirect(add_query_arg(
                [
                    'page' => CBT_Admin_Questions_Helper::normalize_question_page_slug($return_page),
                    'cbt_err' => $message,
                ],
                admin_url('admin.php')
            ));
            exit;
        }

        private static function normalize_standard_list_per_page(int $requested): int
        {
            $allowed = [20, 40, 60, 80, 100];
            if (in_array($requested, $allowed, true)) {
                return $requested;
            }
    
            return 20;
        }

}
