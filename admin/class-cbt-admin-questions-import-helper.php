<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Questions_Import_Helper
{
    private const DOCX_TEMPLATE_MARKER_KEY = 'cbt_template';
    private const DOCX_TEMPLATE_MARKER_VALUE = 'question_import_v2';
    private const DOCX_HTML_MARKER_PREFIX = '__HTML__:';
    private const DOCX_DIAGNOSTIC_MARKER_PREFIX = '__DIAG__:';
    private const QUESTION_IMPORT_DIAGNOSTIC_ENTRY_LIMIT = 200;
    private const DOCX_IMAGE_MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

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
            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug(isset($_POST['return_page']) ? wp_unslash($_POST['return_page']) : 'cbt-question-bank');
            $forced_import_type = CBT_Admin_Questions_Helper::forced_question_type_for_page($return_page);

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
            $is_admin_scope = CBT_Admin_Questions_Service::is_admin_scope();
            $default_exam_id = CBT_Admin_Questions_Helper::ensure_subject_question_bank_exam($import_subject_id, $is_admin_scope, get_current_user_id());
            if ($default_exam_id <= 0) {
                self::redirect_question_import_with_error('Gagal menyiapkan exam penampung untuk subject terpilih.', $return_page);
            }

            $requested_import_type = isset($_POST['import_question_type']) ? sanitize_text_field(wp_unslash($_POST['import_question_type'])) : 'all';
            $allowed_import_types = ['all', 'multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'];
            if (!in_array($requested_import_type, $allowed_import_types, true)) {
                $requested_import_type = 'all';
            }
            if ($forced_import_type !== '') {
                $requested_import_type = $forced_import_type;
            }

            $extension = strtolower((string) pathinfo((string) $original_name, PATHINFO_EXTENSION));
            $extension_validation = self::validate_question_import_upload_extension($extension);
            if (is_wp_error($extension_validation)) {
                self::redirect_question_import_with_error($extension_validation->get_error_message(), $return_page);
            }

            if ($extension === 'docx' && !in_array($requested_import_type, ['all', 'multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'essay', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'], true)) {
                self::redirect_question_import_with_error('Import DOCX hanya tersedia untuk tab tipe soal resmi CBT yang didukung.', $return_page);
            }

            $parsed = self::parse_question_docx($tmp_path);

            if (is_wp_error($parsed)) {
                self::redirect_question_import_with_error($parsed->get_error_message(), $return_page);
            }

            $type_mismatch_validation = self::validate_parsed_rows_for_requested_import_type(
                is_array($parsed) ? $parsed : [],
                $requested_import_type,
                $extension
            );
            if (is_wp_error($type_mismatch_validation)) {
                self::redirect_question_import_with_error($type_mismatch_validation->get_error_message(), $return_page);
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

            $diagnostic_summary = self::aggregate_question_import_diagnostics($parsed);
            $token = strtolower((string) wp_generate_password(24, false, false));
            $current_user_id = get_current_user_id();
            $state = [
                'total' => count($parsed),
                'offset' => 0,
                'created' => 0,
                'failed' => 0,
                'created_question_ids' => [],
                'created_question_items' => [],
                'recent_failures' => [],
                'user_id' => $current_user_id,
                'started_at' => time(),
                'return_page' => $return_page,
                'default_exam_id' => $default_exam_id,
                'import_subject_id' => $import_subject_id,
                'is_admin_scope' => $is_admin_scope ? 1 : 0,
                'import_user_id' => $current_user_id,
                'affected_exam_ids' => [],
                'diagnostic_counts' => (array) ($diagnostic_summary['diagnostic_counts'] ?? []),
                'diagnostic_entries' => (array) ($diagnostic_summary['diagnostic_entries'] ?? []),
                'diagnostic_truncated' => !empty($diagnostic_summary['diagnostic_truncated']) ? 1 : 0,
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

            $return_page = CBT_Admin_Questions_Helper::normalize_question_page_slug((string) ($state['return_page'] ?? 'cbt-question-bank'));
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
            $created_question_ids = isset($state['created_question_ids']) && is_array($state['created_question_ids'])
                ? self::normalize_question_import_created_question_ids($state['created_question_ids'])
                : [];
            $created_question_items = isset($state['created_question_items']) && is_array($state['created_question_items'])
                ? self::normalize_question_import_created_question_items($state['created_question_items'])
                : [];
            $recent_failures = isset($state['recent_failures']) && is_array($state['recent_failures'])
                ? self::normalize_question_import_failure_entries($state['recent_failures'])
                : [];
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
                    $result = [
                        'status' => 'failed',
                        'message' => 'Terjadi error internal saat memproses blok soal.',
                    ];
                }

                $result_status = (string) ($result['status'] ?? 'failed');
                $result_message = trim((string) ($result['message'] ?? ''));

                if ($result_status === 'created') {
                    $created++;
                    $created_question_id = isset($result['question_id']) ? (int) $result['question_id'] : 0;
                    if ($created_question_id > 0) {
                        $created_question_ids[] = $created_question_id;
                        $created_question_items[] = self::build_question_import_created_question_item($row, $created_question_id);
                    }
                } else {
                    $failed++;
                    $failure_entry = isset($result['failure_entry']) && is_array($result['failure_entry'])
                        ? self::normalize_question_import_failure_entry($result['failure_entry'])
                        : self::normalize_question_import_failure_entry($result_message);
                    if (is_array($failure_entry)) {
                        $recent_failures[] = $failure_entry;
                        if (count($recent_failures) > 8) {
                            $recent_failures = array_slice($recent_failures, -8);
                        }
                    }
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
            $state['created_question_ids'] = self::normalize_question_import_created_question_ids($created_question_ids);
            $state['created_question_items'] = self::normalize_question_import_created_question_items($created_question_items);
            $state['recent_failures'] = $recent_failures;
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

            $state['offset'] = $total;
            $state['completed_at'] = time();
            $state['is_complete'] = true;
            $final_state_saved = set_transient(self::get_question_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);

            if ($created > 0) {
                CBT_Cache::invalidate_catalog();
                CBT_Cache::invalidate_exams(array_values($affected_exam_ids));
            }

            $redirect_args = [
                'page' => $return_page,
                'cbt_question_import_token' => $token,
                'cbt_msg' => sprintf('Import soal ke Bank Soal selesai. Total: %d, Created: %d, Failed: %d', $total, $created, $failed),
            ];
            if (!empty($recent_failures)) {
                $redirect_args['cbt_err'] = implode(' || ', array_map(
                    static function (array $entry): string {
                        return (string) ($entry['formatted'] ?? '');
                    },
                    array_slice($recent_failures, -3)
                ));
            }
            if (!$final_state_saved && empty($redirect_args['cbt_err'])) {
                $redirect_args['cbt_err'] = 'Import selesai, tetapi detail failure terbaru tidak dapat disimpan untuk ditampilkan ulang.';
            }

            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
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

        public static function handle_download_question_template_word_ordering(): void
        {
            self::download_word_question_template(
                'cbt_download_question_template_word_ordering',
                'ordering',
                'cbt-question-import-template-ordering.docx'
            );
        }

        public static function handle_download_question_template_word_matching(): void
        {
            self::download_word_question_template(
                'cbt_download_question_template_word_matching',
                'matching',
                'cbt-question-import-template-matching.docx'
            );
        }

        public static function handle_download_question_template_word_cloze(): void
        {
            self::download_word_question_template(
                'cbt_download_question_template_word_cloze',
                'cloze_dropdown',
                'cbt-question-import-template-cloze-dropdown.docx'
            );
        }

        public static function handle_download_question_template_word_categorization(): void
        {
            self::download_word_question_template(
                'cbt_download_question_template_word_categorization',
                'categorization',
                'cbt-question-import-template-categorization.docx'
            );
        }

        public static function handle_download_question_template_word_table_completion(): void
        {
            self::download_word_question_template(
                'cbt_download_question_template_word_table_completion',
                'table_completion',
                'cbt-question-import-template-table-completion.docx'
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
            $template_config = self::sanitize_word_template_config($template_type);
            $lines = self::build_word_template_lines($template_type, $question_count, $template_config);

            self::output_question_template_word_file($lines, $download_name);
        }

        private static function validate_parsed_rows_for_requested_import_type(array $rows, string $requested_import_type, string $extension)
        {
            if ($requested_import_type === 'all' || empty($rows)) {
                return true;
            }

            $mismatches = [];
            foreach ($rows as $row_index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $detected_question_type = self::map_import_question_type((string) ($row['question_type'] ?? ''));
                if ($detected_question_type === '' || $detected_question_type === $requested_import_type) {
                    continue;
                }

                $block_number = isset($row['__import_source_block']) ? (int) $row['__import_source_block'] : ($row_index + 1);
                $question_preview = self::build_docx_block_preview_text([(string) ($row['question_text'] ?? '')]);
                if ($question_preview === '') {
                    $question_preview = 'Blok #' . $block_number;
                }

                $mismatches[] = [
                    'block_number' => $block_number,
                    'question_type' => $detected_question_type,
                    'question_preview' => $question_preview,
                ];
            }

            if (empty($mismatches)) {
                return true;
            }

            $requested_label = CBT_Admin_Questions_Helper::get_question_type_label($requested_import_type);
            $detected_labels = [];
            foreach ($mismatches as $mismatch) {
                $detected_labels[(string) $mismatch['question_type']] = CBT_Admin_Questions_Helper::get_question_type_label((string) $mismatch['question_type']);
            }

            $source_label = strtoupper($extension) !== '' ? strtoupper($extension) : 'file';
            $message = sprintf(
                'File %s terdeteksi berisi soal %s, tetapi menu import aktif adalah %s. Gunakan menu yang sesuai agar struktur jawaban tidak tertukar.',
                $source_label,
                implode(', ', array_values($detected_labels)),
                $requested_label
            );

            $mismatch_summaries = [];
            foreach (array_slice($mismatches, 0, 3) as $mismatch) {
                $preview = trim((string) ($mismatch['question_preview'] ?? ''));
                if ($preview !== '') {
                    $preview = wp_strip_all_tags($preview);
                    if (function_exists('mb_substr')) {
                        $preview = mb_substr($preview, 0, 80);
                    } else {
                        $preview = substr($preview, 0, 80);
                    }
                }

                $mismatch_summaries[] = sprintf(
                    '#%d %s%s',
                    (int) ($mismatch['block_number'] ?? 0),
                    CBT_Admin_Questions_Helper::get_question_type_label((string) ($mismatch['question_type'] ?? '')),
                    $preview !== '' ? ' (' . $preview . ')' : ''
                );
            }

            if (!empty($mismatch_summaries)) {
                $message .= ' Mismatch: ' . implode('; ', $mismatch_summaries) . '.';
            }

            return new WP_Error('import_type_mismatch', $message);
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
            $numbering_xml = $zip->getFromName('word/numbering.xml');

            $image_rel_map = [];
            $hyperlink_rel_map = [];
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

                        if ($rel_id === '' || $target === '') {
                            continue;
                        }

                        if (strpos($rel_type, '/hyperlink') !== false) {
                            $hyperlink_rel_map[$rel_id] = $target;
                            continue;
                        }

                        if (strpos($rel_type, '/image') === false) {
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

            $numbering_map = self::build_docx_numbering_map((string) $numbering_xml);
            $lines = self::extract_docx_content_lines((string) $document_xml, $image_rel_map, $zip, $numbering_map, $hyperlink_rel_map);

            $zip->close();

            if (!is_string($document_xml) || $document_xml === '') {
                return new WP_Error('docx_invalid', 'Konten DOCX tidak valid.');
            }

            $lines = self::normalize_docx_extracted_lines($lines);

            if (empty($lines)) {
                return new WP_Error('docx_empty', 'Tidak ada data soal pada DOCX.');
            }

            if (!self::docx_has_required_template_marker($lines)) {
                return new WP_Error(
                    'docx_invalid_template',
                    'Template DOCX tidak dikenali. Gunakan template Word resmi CBT terbaru dan jangan hapus marker CBT_TEMPLATE.'
                );
            }

            // Only parse rows after the first block separator so template instructions stay out of question data.
            $blocks = self::extract_docx_question_blocks($lines);
            if (empty($blocks)) {
                return new WP_Error('docx_no_data', 'Format DOCX tidak sesuai template. Pastikan setiap blok soal dipisahkan oleh ---.');
            }

            $structured_rows = [];
            $structured_detected = false;
            foreach ($blocks as $block_index => $block) {
                if (!self::is_docx_structured_question_block($block)) {
                    continue;
                }

                $structured_detected = true;
                $row = self::parse_docx_multiple_choice_block($block);
                if (is_array($row) && !empty($row)) {
                    $block_number = (int) $block_index + 1;
                    $row['__import_source_block'] = $block_number;
                    $row['__import_diagnostics'] = self::finalize_docx_row_import_diagnostics(
                        isset($row['__import_diagnostics']) && is_array($row['__import_diagnostics']) ? $row['__import_diagnostics'] : [],
                        $block_number,
                        (string) ($row['question_type'] ?? '')
                    );
                    $structured_rows[] = $row;
                    continue;
                }

                $structured_rows[] = [
                    '__import_source_block' => (int) $block_index + 1,
                    '__import_error' => self::describe_docx_block_failure($block),
                    'question_type' => self::detect_docx_block_question_type($block),
                    'question_text' => self::build_docx_block_preview_text($block),
                ];
            }

            if ($structured_detected) {
                return $structured_rows;
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

        private static function extract_docx_content_lines(
            string $document_xml,
            array $image_rel_map,
            ZipArchive $zip,
            array $numbering_map = [],
            array $hyperlink_rel_map = []
        ): array
        {
            if ($document_xml === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
                return self::extract_docx_content_lines_legacy($document_xml, $image_rel_map, $zip);
            }

            $previous_libxml_state = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = @$dom->loadXML($document_xml, LIBXML_NONET | LIBXML_COMPACT);
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_state);

            if (!$loaded) {
                return self::extract_docx_content_lines_legacy($document_xml, $image_rel_map, $zip);
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $body = $xpath->query('/w:document/w:body')->item(0);
            if (!$body instanceof DOMNode) {
                return self::extract_docx_content_lines_legacy($document_xml, $image_rel_map, $zip);
            }

            return self::extract_docx_lines_from_container(
                $body,
                $xpath,
                $image_rel_map,
                $zip,
                $numbering_map,
                $hyperlink_rel_map,
                true
            );
        }

        private static function extract_docx_lines_from_container(
            DOMNode $container,
            DOMXPath $xpath,
            array $image_rel_map,
            ZipArchive $zip,
            array $numbering_map = [],
            array $hyperlink_rel_map = [],
            bool $allow_template_tables = false
        ): array
        {
            $lines = [];
            $list_items = [];
            foreach ($container->childNodes as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }

                if ($child->localName === 'p') {
                    $list_context = self::get_docx_paragraph_list_context($child, $xpath, $numbering_map);
                    if (is_array($list_context)) {
                        $item_html = self::convert_docx_paragraph_element_to_html($child, $image_rel_map, $zip, $hyperlink_rel_map);
                        if ($item_html !== '') {
                            $list_items[] = [
                                'tag' => (string) ($list_context['tag'] ?? 'ul'),
                                'level' => (int) ($list_context['level'] ?? 0),
                                'html' => $item_html,
                            ];
                        }
                        continue;
                    }

                    self::flush_docx_list_buffer($lines, $list_items);
                    foreach (self::extract_docx_paragraph_lines_from_dom($child, $image_rel_map, $zip, $hyperlink_rel_map) as $line) {
                        if (trim((string) $line) !== '') {
                            $lines[] = (string) $line;
                        }
                    }
                    continue;
                }

                if ($child->localName === 'tbl') {
                    self::flush_docx_list_buffer($lines, $list_items);
                    if ($allow_template_tables && self::is_docx_template_key_value_table($child)) {
                        $template_lines = self::extract_docx_template_key_value_table_lines(
                            $child,
                            $xpath,
                            $image_rel_map,
                            $zip,
                            $numbering_map,
                            $hyperlink_rel_map
                        );
                        foreach ($template_lines as $line) {
                            if (trim((string) $line) !== '') {
                                $lines[] = (string) $line;
                            }
                        }
                        continue;
                    }

                    $table_html = self::convert_docx_table_element_to_html($child, $image_rel_map, $zip, $hyperlink_rel_map);
                    if ($table_html !== '') {
                        $lines[] = self::create_docx_html_marker($table_html);
                        foreach (self::build_docx_table_diagnostic_markers($child) as $diagnostic_line) {
                            $lines[] = $diagnostic_line;
                        }
                    }
                }
            }

            self::flush_docx_list_buffer($lines, $list_items);

            return $lines;
        }

        private static function is_docx_template_key_value_table(DOMElement $table): bool
        {
            foreach ($table->childNodes as $row_node) {
                if (
                    !$row_node instanceof DOMElement ||
                    (string) $row_node->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    $row_node->localName !== 'tr'
                ) {
                    continue;
                }

                $cells = self::get_docx_direct_table_row_cells($row_node);
                if (count($cells) < 2) {
                    continue;
                }

                $left = strtolower(trim(self::extract_docx_plain_text_from_container($cells[0])));
                $right = strtolower(trim(self::extract_docx_plain_text_from_container($cells[1])));

                return $left === 'field' && $right === 'value';
            }

            return false;
        }

        private static function extract_docx_template_key_value_table_lines(
            DOMElement $table,
            DOMXPath $xpath,
            array $image_rel_map,
            ZipArchive $zip,
            array $numbering_map = [],
            array $hyperlink_rel_map = []
        ): array {
            $lines = [];
            $header_consumed = false;

            foreach ($table->childNodes as $row_node) {
                if (
                    !$row_node instanceof DOMElement ||
                    (string) $row_node->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    $row_node->localName !== 'tr'
                ) {
                    continue;
                }

                $cells = self::get_docx_direct_table_row_cells($row_node);
                if (count($cells) < 2) {
                    continue;
                }

                $left_text = trim(self::extract_docx_plain_text_from_container($cells[0]));
                $right_text = trim(self::extract_docx_plain_text_from_container($cells[1]));

                if (!$header_consumed) {
                    if (strtolower($left_text) === 'field' && strtolower($right_text) === 'value') {
                        $header_consumed = true;
                    }
                    continue;
                }

                $value_lines = self::extract_docx_lines_from_container(
                    $cells[1],
                    $xpath,
                    $image_rel_map,
                    $zip,
                    $numbering_map,
                    $hyperlink_rel_map,
                    false
                );

                if ($left_text === '') {
                    if ($right_text === '---') {
                        $lines[] = '---';
                        continue;
                    }

                    foreach ($value_lines as $value_line) {
                        if (trim((string) $value_line) !== '') {
                            $lines[] = (string) $value_line;
                        }
                    }
                    continue;
                }

                if (empty($value_lines)) {
                    $lines[] = $left_text . ':';
                    continue;
                }

                $first_value_line = (string) array_shift($value_lines);
                if (
                    strpos($first_value_line, '__IMG__:') === 0 ||
                    strpos($first_value_line, self::DOCX_HTML_MARKER_PREFIX) === 0
                ) {
                    $lines[] = $left_text . ':';
                    $lines[] = $first_value_line;
                } else {
                    $lines[] = $left_text . ': ' . $first_value_line;
                }

                foreach ($value_lines as $value_line) {
                    if (trim((string) $value_line) !== '') {
                        $lines[] = (string) $value_line;
                    }
                }
            }

            return $lines;
        }

        /**
         * @return DOMElement[]
         */
        private static function get_docx_direct_table_row_cells(DOMElement $row): array
        {
            $cells = [];
            foreach ($row->childNodes as $cell_node) {
                if (
                    $cell_node instanceof DOMElement &&
                    (string) $cell_node->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' &&
                    $cell_node->localName === 'tc'
                ) {
                    $cells[] = $cell_node;
                }
            }

            return $cells;
        }

        private static function extract_docx_plain_text_from_container(DOMNode $container): string
        {
            $parts = [];

            foreach ($container->childNodes as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }

                if (
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                ) {
                    continue;
                }

                if ($child->localName === 'p') {
                    $xml = $child->ownerDocument instanceof DOMDocument
                        ? (string) $child->ownerDocument->saveXML($child)
                        : '';
                    $text = self::extract_docx_paragraph_text($xml);
                    if (trim($text) !== '') {
                        $parts[] = trim($text);
                    }
                    continue;
                }

                if ($child->localName === 'tbl') {
                    $text = trim((string) $child->textContent);
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }

            return trim(implode(' ', $parts));
        }

        private static function build_docx_numbering_map(string $numbering_xml): array
        {
            if ($numbering_xml === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
                return [];
            }

            $previous_libxml_state = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = @$dom->loadXML($numbering_xml, LIBXML_NONET | LIBXML_COMPACT);
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_state);

            if (!$loaded) {
                return [];
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $abstract_level_map = [];
            foreach ($xpath->query('/w:numbering/w:abstractNum') as $abstract_num_node) {
                if (!$abstract_num_node instanceof DOMElement) {
                    continue;
                }

                $abstract_num_id_raw = self::get_docx_attribute_value($abstract_num_node, 'abstractNumId');
                if ($abstract_num_id_raw === '') {
                    continue;
                }
                $abstract_num_id = (int) $abstract_num_id_raw;

                foreach ($xpath->query('./w:lvl', $abstract_num_node) as $level_node) {
                    if (!$level_node instanceof DOMElement) {
                        continue;
                    }

                    $level = (int) self::get_docx_attribute_value($level_node, 'ilvl');
                    $format = '';
                    $format_node = $xpath->query('./w:numFmt', $level_node)->item(0);
                    if ($format_node instanceof DOMElement) {
                        $format = strtolower(self::get_docx_attribute_value($format_node, 'val'));
                    }

                    $abstract_level_map[$abstract_num_id][$level] = [
                        'tag' => self::map_docx_numbering_format_to_list_tag($format),
                        'format' => $format,
                    ];
                }
            }

            $numbering_map = [];
            foreach ($xpath->query('/w:numbering/w:num') as $num_node) {
                if (!$num_node instanceof DOMElement) {
                    continue;
                }

                $num_id_raw = self::get_docx_attribute_value($num_node, 'numId');
                if ($num_id_raw === '') {
                    continue;
                }
                $num_id = (int) $num_id_raw;

                $abstract_num_id = null;
                $abstract_node = $xpath->query('./w:abstractNumId', $num_node)->item(0);
                if ($abstract_node instanceof DOMElement) {
                    $abstract_num_id_raw = self::get_docx_attribute_value($abstract_node, 'val');
                    if ($abstract_num_id_raw !== '') {
                        $abstract_num_id = (int) $abstract_num_id_raw;
                    }
                }

                if ($abstract_num_id !== null && !empty($abstract_level_map[$abstract_num_id])) {
                    $numbering_map[$num_id] = $abstract_level_map[$abstract_num_id];
                }

                foreach ($xpath->query('./w:lvlOverride', $num_node) as $override_node) {
                    if (!$override_node instanceof DOMElement) {
                        continue;
                    }

                    $level = (int) self::get_docx_attribute_value($override_node, 'ilvl');
                    $override_level_node = $xpath->query('./w:lvl', $override_node)->item(0);
                    if (!$override_level_node instanceof DOMElement) {
                        continue;
                    }

                    $format_node = $xpath->query('./w:numFmt', $override_level_node)->item(0);
                    if (!$format_node instanceof DOMElement) {
                        continue;
                    }

                    $format = strtolower(self::get_docx_attribute_value($format_node, 'val'));
                    $numbering_map[$num_id][$level] = [
                        'tag' => self::map_docx_numbering_format_to_list_tag($format),
                        'format' => $format,
                    ];
                }
            }

            return $numbering_map;
        }

        private static function get_docx_paragraph_list_context(DOMElement $paragraph, DOMXPath $xpath, array $numbering_map): ?array
        {
            $num_pr_node = $xpath->query('./w:pPr/w:numPr', $paragraph)->item(0);
            if (!$num_pr_node instanceof DOMElement) {
                return null;
            }

            $num_id_node = $xpath->query('./w:numId', $num_pr_node)->item(0);
            if (!$num_id_node instanceof DOMElement) {
                return null;
            }

            $num_id_raw = self::get_docx_attribute_value($num_id_node, 'val');
            if ($num_id_raw === '') {
                return null;
            }
            $num_id = (int) $num_id_raw;

            $level = 0;
            $level_node = $xpath->query('./w:ilvl', $num_pr_node)->item(0);
            if ($level_node instanceof DOMElement) {
                $level = max(0, (int) self::get_docx_attribute_value($level_node, 'val'));
            }

            $numbering_meta = $numbering_map[$num_id][$level]
                ?? $numbering_map[$num_id][0]
                ?? null;
            $tag = is_array($numbering_meta) && !empty($numbering_meta['tag'])
                ? (string) $numbering_meta['tag']
                : 'ul';

            return [
                'num_id' => $num_id,
                'level' => $level,
                'tag' => $tag === 'ol' ? 'ol' : 'ul',
            ];
        }

        private static function get_docx_attribute_value(DOMElement $element, string $attribute_name): string
        {
            $candidates = [
                'w:' . $attribute_name,
                $attribute_name,
            ];

            foreach ($candidates as $candidate) {
                if ($element->hasAttribute($candidate)) {
                    return trim((string) $element->getAttribute($candidate));
                }
            }

            foreach ($element->attributes ?? [] as $attribute_node) {
                if (!$attribute_node instanceof DOMNode) {
                    continue;
                }

                if ((string) $attribute_node->localName === $attribute_name) {
                    return trim((string) $attribute_node->nodeValue);
                }
            }

            return '';
        }

        private static function map_docx_numbering_format_to_list_tag(string $format): string
        {
            $format = strtolower(trim($format));
            if ($format === 'bullet') {
                return 'ul';
            }

            return 'ol';
        }

        private static function flush_docx_list_buffer(array &$lines, array &$list_items): void
        {
            if (empty($list_items)) {
                return;
            }

            $list_html = self::render_docx_list_items_to_html($list_items);
            if ($list_html !== '') {
                $lines[] = self::create_docx_html_marker($list_html);
                $lines[] = self::create_docx_diagnostic_marker([
                    'kind' => 'preserved',
                    'feature' => 'list_native',
                    'message' => 'Bullet atau numbering Word native dipertahankan sebagai list HTML.',
                ]);
            }

            $list_items = [];
        }

        private static function render_docx_list_items_to_html(array $list_items): string
        {
            if (empty($list_items)) {
                return '';
            }

            $html_parts = [];
            $open_lists = [];
            $item_started = false;

            foreach ($list_items as $item) {
                $tag = strtolower(trim((string) ($item['tag'] ?? '')));
                $tag = ($tag === 'ol') ? 'ol' : 'ul';
                $level = max(0, (int) ($item['level'] ?? 0));
                $item_html = trim((string) ($item['html'] ?? ''));
                if ($item_html === '') {
                    continue;
                }

                while (count($open_lists) > ($level + 1)) {
                    if ($item_started) {
                        $html_parts[] = '</li>';
                        $item_started = false;
                    }
                    $closing_tag = array_pop($open_lists);
                    $html_parts[] = '</' . $closing_tag . '>';
                }

                if (count($open_lists) === ($level + 1)) {
                    if ($item_started) {
                        $html_parts[] = '</li>';
                        $item_started = false;
                    }

                    $current_tag = end($open_lists);
                    if ($current_tag !== $tag) {
                        $closing_tag = array_pop($open_lists);
                        $html_parts[] = '</' . $closing_tag . '>';
                    }
                }

                while (count($open_lists) < ($level + 1)) {
                    $html_parts[] = '<' . $tag . '>';
                    $open_lists[] = $tag;
                }

                if (!empty($open_lists)) {
                    $current_tag = end($open_lists);
                    if ($current_tag !== $tag) {
                        if ($item_started) {
                            $html_parts[] = '</li>';
                            $item_started = false;
                        }
                        $closing_tag = array_pop($open_lists);
                        $html_parts[] = '</' . $closing_tag . '>';
                        $html_parts[] = '<' . $tag . '>';
                        $open_lists[] = $tag;
                    }
                }

                $html_parts[] = '<li>' . $item_html;
                $item_started = true;
            }

            while (!empty($open_lists)) {
                if ($item_started) {
                    $html_parts[] = '</li>';
                    $item_started = false;
                }
                $closing_tag = array_pop($open_lists);
                $html_parts[] = '</' . $closing_tag . '>';
            }

            return implode('', $html_parts);
        }

        private static function extract_docx_content_lines_legacy(string $document_xml, array $image_rel_map, ZipArchive $zip): array
        {
            $lines = [];
            if ($document_xml === '') {
                return $lines;
            }

            if (preg_match_all('/<w:p\b[^>]*>(.*?)<\/w:p>/s', $document_xml, $paragraphs)) {
                foreach ($paragraphs[1] as $paragraph) {
                    $paragraph = (string) $paragraph;

                    $txt = self::extract_docx_paragraph_text($paragraph);
                    if ($txt !== '') {
                        $lines[] = $txt;
                    }

                    foreach (self::extract_docx_image_marker_lines_from_xml($paragraph, $image_rel_map, $zip) as $line) {
                        $lines[] = $line;
                    }
                }
            }

            return $lines;
        }

        /**
         * @return string[]
         */
        private static function extract_docx_paragraph_lines_from_dom(
            DOMElement $paragraph,
            array $image_rel_map,
            ZipArchive $zip,
            array $hyperlink_rel_map = []
        ): array
        {
            $xml = $paragraph->ownerDocument instanceof DOMDocument
                ? (string) $paragraph->ownerDocument->saveXML($paragraph)
                : '';

            $lines = [];
            $tokens = self::extract_docx_paragraph_inline_tokens($paragraph, $hyperlink_rel_map);
            $paragraph_alignment = self::extract_docx_paragraph_alignment($paragraph);
            $has_rich_inline_markup = self::docx_inline_tokens_require_rich_html($tokens);
            $rich_html = $has_rich_inline_markup ? self::render_docx_inline_tokens_to_html($tokens) : '';
            $plain_text = self::extract_docx_paragraph_text($xml);

            if ($rich_html !== '') {
                $rich_lines = self::build_docx_rich_lines_from_paragraph_tokens($tokens, $plain_text, $paragraph_alignment);
                if (!empty($rich_lines)) {
                    $lines = array_merge($lines, $rich_lines);
                } elseif ($plain_text !== '') {
                    $lines[] = self::create_docx_html_marker(
                        self::wrap_docx_inline_html_fragment($rich_html, $paragraph_alignment)
                    );
                }
            } elseif ($plain_text !== '') {
                if ($paragraph_alignment !== '') {
                    $safe_text = esc_html($plain_text);
                    $safe_text = str_replace(["\r\n", "\r"], "\n", $safe_text);
                    $safe_text = str_replace("\n", '<br />', $safe_text);
                    $colon_position = strpos($plain_text, ':');
                    if ($colon_position !== false) {
                        $key = trim(substr($plain_text, 0, $colon_position));
                        if (self::is_docx_key_only_line($key)) {
                            $value_text = trim(substr($plain_text, $colon_position + 1));
                            if ($value_text === '') {
                                $lines[] = $key . ':';
                            } else {
                                $value_html = esc_html($value_text);
                                $value_html = str_replace(["\r\n", "\r"], "\n", $value_html);
                                $value_html = str_replace("\n", '<br />', $value_html);
                                $lines[] = $key . ':';
                                $lines[] = self::create_docx_html_marker(
                                    self::wrap_docx_inline_html_fragment($value_html, $paragraph_alignment)
                                );
                            }
                        } else {
                            $lines[] = self::create_docx_html_marker(
                                self::wrap_docx_inline_html_fragment($safe_text, $paragraph_alignment)
                            );
                        }
                    } else {
                        $lines[] = self::create_docx_html_marker(
                            self::wrap_docx_inline_html_fragment($safe_text, $paragraph_alignment)
                        );
                    }
                } else {
                    $lines[] = $plain_text;
                }
            }

            foreach (self::collect_docx_paragraph_diagnostic_markers($paragraph_alignment, $tokens, $xml) as $diagnostic_line) {
                $lines[] = $diagnostic_line;
            }

            foreach (self::extract_docx_image_marker_lines_from_xml($xml, $image_rel_map, $zip, $paragraph_alignment) as $line) {
                $lines[] = $line;
            }

            return $lines;
        }

        /**
         * @return string[]
         */
        private static function extract_docx_image_marker_lines_from_xml(
            string $xml,
            array $image_rel_map,
            ZipArchive $zip,
            string $paragraph_alignment = ''
        ): array
        {
            $lines = [];
            foreach (self::extract_docx_image_html_fragments_from_xml($xml, $image_rel_map, $zip, $paragraph_alignment) as $image_html) {
                $image_html = trim($image_html);
                if ($image_html !== '') {
                    $lines[] = self::create_docx_html_marker($image_html);
                }
            }

            foreach (self::extract_docx_image_render_specs_from_xml($xml, $paragraph_alignment) as $image_spec) {
                foreach (self::build_docx_image_diagnostic_markers((array) $image_spec) as $diagnostic_line) {
                    $lines[] = $diagnostic_line;
                }
            }

            return $lines;
        }

        /**
         * @return string[]
         */
        private static function extract_docx_image_html_fragments_from_xml(
            string $xml,
            array $image_rel_map,
            ZipArchive $zip,
            string $paragraph_alignment = ''
        ): array
        {
            $fragments = [];
            if ($xml === '') {
                return $fragments;
            }

            foreach (self::extract_docx_image_render_specs_from_xml($xml, $paragraph_alignment) as $image_spec) {
                $rid = trim((string) ($image_spec['rid'] ?? ''));
                if ($rid === '' || !isset($image_rel_map[$rid])) {
                    continue;
                }

                $target = (string) $image_rel_map[$rid];
                $binary = $zip->getFromName($target);
                if (!is_string($binary) || $binary === '') {
                    $fallback_target = 'word/media/' . basename($target);
                    $binary = $zip->getFromName($fallback_target);
                }

                if (!is_string($binary) || $binary === '') {
                    continue;
                }

                $image_url = self::store_docx_image_and_get_url($binary, basename($target));
                if ($image_url === '') {
                    continue;
                }

                $image_html = self::build_docx_image_html_fragment($image_url, $image_spec);
                if ($image_html !== '') {
                    $fragments[] = $image_html;
                }
            }

            return $fragments;
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function extract_docx_image_render_specs_from_xml(string $xml, string $paragraph_alignment = ''): array
        {
            $specs = [];
            $dom = self::load_docx_fragment_dom_document($xml);
            if (!$dom instanceof DOMDocument) {
                return $specs;
            }

            $xpath = new DOMXPath($dom);
            $drawing_nodes = $xpath->query('//*[local-name()="drawing"]/*[local-name()="inline" or local-name()="anchor"]');
            if (!$drawing_nodes instanceof \DOMNodeList || $drawing_nodes->length === 0) {
                return $specs;
            }

            foreach ($drawing_nodes as $drawing_node) {
                if (!$drawing_node instanceof DOMElement) {
                    continue;
                }

                $blip_node = $xpath->query('.//*[local-name()="blip"][1]', $drawing_node)->item(0);
                if (!$blip_node instanceof DOMElement) {
                    continue;
                }

                $rid = trim((string) $blip_node->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    'embed'
                ));
                if ($rid === '') {
                    $rid = trim((string) $blip_node->getAttribute('r:embed'));
                }
                if ($rid === '') {
                    continue;
                }

                [$width_px, $height_px] = self::extract_docx_image_dimensions_from_container($drawing_node, $xpath);
                $alignment = self::extract_docx_image_alignment_from_container($drawing_node, $xpath, $paragraph_alignment);
                $alt_text = self::extract_docx_image_alt_text_from_container($drawing_node, $xpath);

                $specs[] = [
                    'rid' => $rid,
                    'alignment' => $alignment,
                    'alt' => $alt_text,
                    'width_px' => $width_px,
                    'height_px' => $height_px,
                    'is_anchor' => strtolower((string) $drawing_node->localName) === 'anchor',
                ];
            }

            return $specs;
        }

        private static function load_docx_fragment_dom_document(string $xml): ?DOMDocument
        {
            $xml = trim((string) preg_replace('/<\?xml[^>]*\?>/i', '', $xml));
            if ($xml === '') {
                return null;
            }

            $wrapped_xml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<cbt:root'
                . ' xmlns:cbt="https://coblax.test/cbt-import"'
                . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
                . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
                . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
                . ' xmlns:a14="http://schemas.microsoft.com/office/drawing/2010/main"'
                . ' xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing"'
                . ' xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"'
                . ' xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"'
                . ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"'
                . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
                . ' xmlns:v="urn:schemas-microsoft-com:vml"'
                . '>'
                . $xml
                . '</cbt:root>';

            $previous_errors = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = $dom->loadXML($wrapped_xml);
            libxml_clear_errors();
            libxml_use_internal_errors($previous_errors);

            return $loaded ? $dom : null;
        }

        /**
         * @return array{0:int,1:int}
         */
        private static function extract_docx_image_dimensions_from_container(DOMElement $container, DOMXPath $xpath): array
        {
            $extent_node = $xpath->query('./*[local-name()="extent"][1]', $container)->item(0);
            if (!$extent_node instanceof DOMElement) {
                $extent_node = $xpath->query('.//*[local-name()="xfrm"]/*[local-name()="ext"][1]', $container)->item(0);
            }

            if (!$extent_node instanceof DOMElement) {
                return [0, 0];
            }

            $cx = (int) $extent_node->getAttribute('cx');
            $cy = (int) $extent_node->getAttribute('cy');

            return [
                self::convert_docx_emu_to_pixels($cx),
                self::convert_docx_emu_to_pixels($cy),
            ];
        }

        private static function extract_docx_image_alignment_from_container(
            DOMElement $container,
            DOMXPath $xpath,
            string $paragraph_alignment = ''
        ): string {
            $alignment = '';
            if (strtolower((string) $container->localName) === 'anchor') {
                $align_node = $xpath->query('./*[local-name()="positionH"]/*[local-name()="align"][1]', $container)->item(0);
                if ($align_node instanceof DOMElement) {
                    $alignment = self::normalize_docx_text_alignment((string) $align_node->textContent);
                }
            }

            if ($alignment === '') {
                $alignment = self::normalize_docx_text_alignment($paragraph_alignment);
            }

            if ($alignment === 'justify') {
                return 'left';
            }

            return $alignment;
        }

        private static function extract_docx_image_alt_text_from_container(DOMElement $container, DOMXPath $xpath): string
        {
            $doc_properties = $xpath->query('./*[local-name()="docPr"][1]', $container)->item(0);
            if (!$doc_properties instanceof DOMElement) {
                return '';
            }

            foreach (['descr', 'title', 'name'] as $attribute_name) {
                $value = trim((string) $doc_properties->getAttribute($attribute_name));
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        }

        private static function convert_docx_emu_to_pixels(int $emu): int
        {
            if ($emu <= 0) {
                return 0;
            }

            return max(1, (int) round($emu / 9525));
        }

        /**
         * @param array<string,mixed> $image_spec
         */
        private static function build_docx_image_html_fragment(string $image_url, array $image_spec): string
        {
            $image_url = trim($image_url);
            if ($image_url === '') {
                return '';
            }

            $alignment = self::normalize_docx_text_alignment((string) ($image_spec['alignment'] ?? ''));
            $width_px = max(0, (int) ($image_spec['width_px'] ?? 0));
            $height_px = max(0, (int) ($image_spec['height_px'] ?? 0));
            $alt_text = trim((string) ($image_spec['alt'] ?? ''));

            $wrapper_style = self::build_docx_text_alignment_style($alignment);
            $wrapper_style_attribute = $wrapper_style !== '' ? ' style="' . esc_attr($wrapper_style) . '"' : '';

            $image_styles = [];
            if ($width_px > 0) {
                $image_styles[] = 'width:' . $width_px . 'px';
            }
            $image_styles[] = 'max-width:100%';
            $image_styles[] = 'height:auto';
            $image_styles[] = 'display:block';

            if ($alignment === 'center') {
                $image_styles[] = 'margin-left:auto';
                $image_styles[] = 'margin-right:auto';
            } elseif ($alignment === 'right') {
                $image_styles[] = 'margin-left:auto';
                $image_styles[] = 'margin-right:0';
            }

            $width_attribute = $width_px > 0 ? ' width="' . $width_px . '"' : '';
            $height_attribute = $height_px > 0 ? ' height="' . $height_px . '"' : '';

            return '<p' . $wrapper_style_attribute . '><img src="' . esc_url($image_url) . '" alt="'
                . esc_attr($alt_text)
                . '"'
                . $width_attribute
                . $height_attribute
                . ' style="'
                . esc_attr(implode(';', $image_styles) . ';')
                . '" /></p>';
        }

        private static function extract_src_from_html_image_fragment(string $html): string
        {
            if (preg_match('/<img\b[^>]*\bsrc="([^"]+)"/i', $html, $matches) === 1) {
                return trim((string) ($matches[1] ?? ''));
            }

            return '';
        }

        private static function create_docx_html_marker(string $html): string
        {
            return self::DOCX_HTML_MARKER_PREFIX . base64_encode($html);
        }

        /**
         * @param array<string,mixed> $entry
         */
        private static function create_docx_diagnostic_marker(array $entry): string
        {
            $encoded = base64_encode((string) wp_json_encode($entry));
            return self::DOCX_DIAGNOSTIC_MARKER_PREFIX . $encoded;
        }

        private static function decode_docx_html_marker(string $line): string
        {
            if (strpos($line, self::DOCX_HTML_MARKER_PREFIX) !== 0) {
                return '';
            }

            $encoded = substr($line, strlen(self::DOCX_HTML_MARKER_PREFIX));
            $decoded = base64_decode((string) $encoded, true);
            if (!is_string($decoded) || trim($decoded) === '') {
                return '';
            }

            return CBT_Admin_Questions_Helper::sanitize_editor_html($decoded);
        }

        private static function decode_docx_diagnostic_marker(string $line): ?array
        {
            if (strpos($line, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) !== 0) {
                return null;
            }

            $encoded = substr($line, strlen(self::DOCX_DIAGNOSTIC_MARKER_PREFIX));
            $decoded = base64_decode((string) $encoded, true);
            if (!is_string($decoded) || trim($decoded) === '') {
                return null;
            }

            $entry = json_decode($decoded, true);
            if (!is_array($entry)) {
                return null;
            }

            return self::normalize_question_import_diagnostic_entry($entry);
        }

        /**
         * @param array<string,mixed> $image_spec
         * @return string[]
         */
        private static function build_docx_image_diagnostic_markers(array $image_spec): array
        {
            $markers = [
                self::create_docx_diagnostic_marker([
                    'kind' => 'preserved',
                    'feature' => 'image_size_alignment',
                    'message' => 'Gambar mempertahankan ukuran dan alignment dasar dari Word.',
                ]),
            ];

            if (!empty($image_spec['is_anchor'])) {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'fallback',
                    'feature' => 'image_wrap_normalized',
                    'message' => 'Wrap atau floating gambar Word dinormalisasi ke layout block yang aman di CBT.',
                ]);
            }

            return $markers;
        }

        /**
         * @return string[]
         */
        private static function build_docx_table_diagnostic_markers(DOMElement $table): array
        {
            $markers = [
                self::create_docx_diagnostic_marker([
                    'kind' => 'preserved',
                    'feature' => 'table_formatting',
                    'message' => 'Struktur dan formatting dasar tabel Word dipertahankan.',
                ]),
            ];

            $has_width_normalization = !empty(self::extract_docx_table_grid_column_widths($table))
                || self::extract_docx_table_preferred_width_style($table) !== '';
            if ($has_width_normalization) {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'fallback',
                    'feature' => 'table_width_normalized',
                    'message' => 'Lebar tabel atau kolom Word dinormalisasi ke layout tabel HTML yang stabil.',
                ]);
            }

            return $markers;
        }

        /**
         * @return string[]
         */
        private static function collect_docx_paragraph_diagnostic_markers(string $paragraph_alignment, array $tokens, string $xml): array
        {
            $markers = [];

            if (self::normalize_docx_text_alignment($paragraph_alignment) !== '') {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'preserved',
                    'feature' => 'paragraph_alignment',
                    'message' => 'Alignment paragraf Word dipertahankan.',
                ]);
            }

            $has_inline_formatting = false;
            $has_font_size = false;

            foreach ($tokens as $token) {
                $styles = (array) ($token['styles'] ?? []);
                if (!empty($styles['bold']) || !empty($styles['italic']) || !empty($styles['underline'])) {
                    $has_inline_formatting = true;
                }
                $vert_align = (string) ($styles['vert_align'] ?? '');
                if ($vert_align === 'superscript' || $vert_align === 'subscript') {
                    $has_inline_formatting = true;
                }
                if (trim((string) ($token['href'] ?? '')) !== '') {
                    $has_inline_formatting = true;
                }
                if (trim((string) ($styles['font_size'] ?? '')) !== '') {
                    $has_font_size = true;
                }

                foreach ((array) ($token['diagnostics'] ?? []) as $diagnostic_entry) {
                    if (!is_array($diagnostic_entry)) {
                        continue;
                    }
                    $markers[] = self::create_docx_diagnostic_marker($diagnostic_entry);
                }
            }

            if ($has_inline_formatting) {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'preserved',
                    'feature' => 'inline_formatting',
                    'message' => 'Format inline Word seperti bold, italic, underline, link, atau sup/sub dipertahankan.',
                ]);
            }

            if ($has_font_size) {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'preserved',
                    'feature' => 'font_size',
                    'message' => 'Ukuran font Word dipertahankan ke HTML hasil import.',
                ]);
            }

            if (strpos($xml, '<wps:wsp') !== false || strpos($xml, '<v:shape') !== false) {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'unsupported',
                    'feature' => 'word_shape_ignored',
                    'message' => 'Shape Word non-gambar belum dipertahankan dan diabaikan saat import.',
                ]);
            }

            if (strpos($xml, '<wp:anchor') !== false && strpos($xml, '<a:blip') === false) {
                $markers[] = self::create_docx_diagnostic_marker([
                    'kind' => 'unsupported',
                    'feature' => 'word_floating_object_ignored',
                    'message' => 'Floating object Word yang bukan gambar belum dipertahankan saat import.',
                ]);
            }

            return $markers;
        }

        private static function convert_docx_table_element_to_html(
            DOMElement $table,
            array $image_rel_map,
            ZipArchive $zip,
            array $hyperlink_rel_map = []
        ): string
        {
            $table_grid_widths = self::extract_docx_table_grid_column_widths($table);
            $table_preferred_width = self::extract_docx_table_preferred_width_style($table);
            $parsed_rows = self::parse_docx_table_rows($table, $image_rel_map, $zip, $hyperlink_rel_map, $table_grid_widths);
            $rows_html = self::render_docx_table_rows_to_html($parsed_rows);

            if (empty($rows_html)) {
                return '';
            }

            $table_style = self::build_docx_table_alignment_style(self::extract_docx_table_alignment($table));
            if ($table_preferred_width !== '') {
                $table_style .= $table_preferred_width;
            }
            if (!empty($table_grid_widths)) {
                $table_style .= 'table-layout:fixed;';
            }
            $table_style_attribute = $table_style !== '' ? ' style="' . esc_attr($table_style) . '"' : '';
            $table_html = '<table' . $table_style_attribute . '><tbody>' . implode('', $rows_html) . '</tbody></table>';
            $caption = self::extract_docx_table_caption_text($table);
            if ($caption !== '') {
                $caption_style = self::build_docx_text_alignment_style(self::extract_docx_table_alignment($table));
                $caption_style_attribute = $caption_style !== '' ? ' style="' . esc_attr($caption_style) . '"' : '';
                return '<figure>' . $table_html . '<figcaption' . $caption_style_attribute . '>' . esc_html($caption) . '</figcaption></figure>';
            }

            return $table_html;
        }

        private static function parse_docx_table_rows(
            DOMElement $table,
            array $image_rel_map,
            ZipArchive $zip,
            array $hyperlink_rel_map = [],
            array $table_grid_widths = []
        ): array {
            $parsed_rows = [];
            $row_index = 0;

            foreach ($table->childNodes as $row_node) {
                if (!$row_node instanceof DOMElement || $row_node->localName !== 'tr') {
                    continue;
                }

                $cells = [];
                $column_index = 0;
                $row_is_header = self::extract_docx_table_row_is_header($row_node);
                foreach ($row_node->childNodes as $cell_node) {
                    if (!$cell_node instanceof DOMElement || $cell_node->localName !== 'tc') {
                        continue;
                    }

                    $cell_html = self::convert_docx_table_cell_element_to_html($cell_node, $image_rel_map, $zip, $hyperlink_rel_map);
                    $colspan = self::extract_docx_table_cell_colspan($cell_node);
                    $vmerge = self::extract_docx_table_cell_vertical_merge_state($cell_node);
                    $cell_style = self::build_docx_table_cell_style(
                        $cell_node,
                        $column_index,
                        max(1, $colspan),
                        $table_grid_widths,
                        $row_is_header || $row_index === 0
                    );

                    $cells[] = [
                        'start_col' => $column_index,
                        'colspan' => max(1, $colspan),
                        'vmerge' => $vmerge,
                        'is_header' => $row_is_header,
                        'style' => $cell_style,
                        'html' => $cell_html !== '' ? $cell_html : '&nbsp;',
                    ];

                    $column_index += max(1, $colspan);
                }

                if (!empty($cells)) {
                    $parsed_rows[] = $cells;
                }

                $row_index++;
            }

            return $parsed_rows;
        }

        private static function render_docx_table_rows_to_html(array $parsed_rows): array
        {
            $rows_html = [];

            foreach ($parsed_rows as $row_index => $cells) {
                $cells_html = [];

                foreach ($cells as $cell) {
                    if (($cell['vmerge'] ?? '') === 'continue') {
                        continue;
                    }

                    $attributes = '';
                    $tag_name = !empty($cell['is_header']) ? 'th' : 'td';
                    $colspan = max(1, (int) ($cell['colspan'] ?? 1));
                    if ($colspan > 1) {
                        $attributes .= ' colspan="' . $colspan . '"';
                    }

                    $rowspan = self::count_docx_table_rowspan($parsed_rows, $row_index, (int) ($cell['start_col'] ?? 0));
                    if ($rowspan > 1) {
                        $attributes .= ' rowspan="' . $rowspan . '"';
                    }

                    $cell_style = trim((string) ($cell['style'] ?? ''));
                    if ($cell_style !== '') {
                        $attributes .= ' style="' . esc_attr($cell_style) . '"';
                    }

                    $cells_html[] = '<' . $tag_name . $attributes . '>' . (string) ($cell['html'] ?? '&nbsp;') . '</' . $tag_name . '>';
                }

                if (!empty($cells_html)) {
                    $rows_html[] = '<tr>' . implode('', $cells_html) . '</tr>';
                }
            }

            return $rows_html;
        }

        private static function count_docx_table_rowspan(array $parsed_rows, int $row_index, int $start_col): int
        {
            $rowspan = 1;

            for ($scan_index = $row_index + 1, $total = count($parsed_rows); $scan_index < $total; $scan_index++) {
                $continuation_found = false;
                foreach ((array) ($parsed_rows[$scan_index] ?? []) as $cell) {
                    if (
                        (int) ($cell['start_col'] ?? -1) === $start_col &&
                        (string) ($cell['vmerge'] ?? '') === 'continue'
                    ) {
                        $continuation_found = true;
                        break;
                    }
                }

                if (!$continuation_found) {
                    break;
                }

                $rowspan++;
            }

            return $rowspan;
        }

        private static function extract_docx_table_cell_colspan(DOMElement $cell): int
        {
            foreach ($cell->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'tcPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        $property instanceof DOMElement &&
                        (string) $property->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' &&
                        (string) $property->localName === 'gridSpan'
                    ) {
                        $value = (int) self::get_docx_attribute_value($property, 'val');
                        return max(1, $value);
                    }
                }
            }

            return 1;
        }

        private static function extract_docx_table_cell_vertical_merge_state(DOMElement $cell): string
        {
            foreach ($cell->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'tcPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        !$property instanceof DOMElement ||
                        (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                        (string) $property->localName !== 'vMerge'
                    ) {
                        continue;
                    }

                    $value = strtolower(self::get_docx_attribute_value($property, 'val'));
                    if ($value === 'restart') {
                        return 'restart';
                    }

                    return 'continue';
                }
            }

            return '';
        }

        private static function extract_docx_table_caption_text(DOMElement $table): string
        {
            foreach ($table->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'tblPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        !$property instanceof DOMElement ||
                        (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                    ) {
                        continue;
                    }

                    $property_name = (string) $property->localName;
                    if (!in_array($property_name, ['tblCaption', 'tblDescription'], true)) {
                        continue;
                    }

                    $value = trim(self::get_docx_attribute_value($property, 'val'));
                    if ($value !== '') {
                        return $value;
                    }

                    $text = trim((string) $property->textContent);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }

            return '';
        }

        private static function find_docx_direct_child_element(DOMElement $parent, string $child_local_name): ?DOMElement
        {
            foreach ($parent->childNodes as $child) {
                if (
                    $child instanceof DOMElement &&
                    (string) $child->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' &&
                    (string) $child->localName === $child_local_name
                ) {
                    return $child;
                }
            }

            return null;
        }

        private static function extract_docx_table_row_is_header(DOMElement $row): bool
        {
            foreach ($row->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'trPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        $property instanceof DOMElement &&
                        (string) $property->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' &&
                        (string) $property->localName === 'tblHeader'
                    ) {
                        return true;
                    }
                }
            }

            return false;
        }

        private static function extract_docx_table_grid_column_widths(DOMElement $table): array
        {
            $widths = [];

            foreach ($table->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'tblGrid'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $grid_column) {
                    if (
                        !$grid_column instanceof DOMElement ||
                        (string) $grid_column->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                        (string) $grid_column->localName !== 'gridCol'
                    ) {
                        continue;
                    }

                    $width = absint(self::get_docx_attribute_value($grid_column, 'w'));
                    if ($width > 0) {
                        $widths[] = $width;
                    }
                }
            }

            return $widths;
        }

        private static function extract_docx_table_preferred_width_style(DOMElement $table): string
        {
            $table_properties = self::find_docx_direct_child_element($table, 'tblPr');
            if (!$table_properties instanceof DOMElement) {
                return '';
            }

            foreach ($table_properties->childNodes as $property) {
                if (
                    !$property instanceof DOMElement ||
                    (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $property->localName !== 'tblW'
                ) {
                    continue;
                }

                return self::normalize_docx_word_width_style(
                    self::get_docx_attribute_value($property, 'w'),
                    self::get_docx_attribute_value($property, 'type')
                );
            }

            return '';
        }

        private static function build_docx_table_cell_style(
            DOMElement $cell,
            int $start_col,
            int $colspan,
            array $table_grid_widths,
            bool $prefer_header_font_weight
        ): string {
            $styles = [];

            $alignment = self::extract_docx_table_cell_alignment($cell);
            if ($alignment !== '') {
                $styles[] = 'text-align:' . $alignment . ';';
            }

            $vertical_align = self::extract_docx_table_cell_vertical_alignment($cell);
            if ($vertical_align !== '') {
                $styles[] = 'vertical-align:' . $vertical_align . ';';
            }

            $background_color = self::extract_docx_table_cell_background_color($cell);
            if ($background_color !== '') {
                $styles[] = 'background-color:' . $background_color . ';';
            }

            $width_style = self::extract_docx_table_cell_width_style($cell, $start_col, $colspan, $table_grid_widths);
            if ($width_style !== '') {
                $styles[] = $width_style;
            }

            if ($prefer_header_font_weight && self::docx_table_cell_looks_like_header($cell)) {
                $styles[] = 'font-weight:700;';
            }

            return implode('', $styles);
        }

        private static function extract_docx_table_cell_alignment(DOMElement $cell): string
        {
            foreach ($cell->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'p'
                ) {
                    continue;
                }

                $alignment = self::extract_docx_paragraph_alignment($child);
                if ($alignment !== '') {
                    return $alignment;
                }
            }

            return '';
        }

        private static function extract_docx_table_cell_vertical_alignment(DOMElement $cell): string
        {
            $cell_properties = self::find_docx_direct_child_element($cell, 'tcPr');
            if (!$cell_properties instanceof DOMElement) {
                return '';
            }

            foreach ($cell_properties->childNodes as $property) {
                if (
                    !$property instanceof DOMElement ||
                    (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $property->localName !== 'vAlign'
                ) {
                    continue;
                }

                $value = strtolower(trim(self::get_docx_attribute_value($property, 'val')));
                if (in_array($value, ['top', 'center', 'bottom'], true)) {
                    return $value === 'center' ? 'middle' : $value;
                }
            }

            return '';
        }

        private static function extract_docx_table_cell_background_color(DOMElement $cell): string
        {
            $cell_properties = self::find_docx_direct_child_element($cell, 'tcPr');
            if (!$cell_properties instanceof DOMElement) {
                return '';
            }

            foreach ($cell_properties->childNodes as $property) {
                if (
                    !$property instanceof DOMElement ||
                    (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $property->localName !== 'shd'
                ) {
                    continue;
                }

                return self::normalize_docx_color_hex(self::get_docx_attribute_value($property, 'fill'));
            }

            return '';
        }

        private static function extract_docx_table_cell_width_style(
            DOMElement $cell,
            int $start_col,
            int $colspan,
            array $table_grid_widths
        ): string {
            $cell_properties = self::find_docx_direct_child_element($cell, 'tcPr');
            if ($cell_properties instanceof DOMElement) {
                foreach ($cell_properties->childNodes as $property) {
                    if (
                        !$property instanceof DOMElement ||
                        (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                        (string) $property->localName !== 'tcW'
                    ) {
                        continue;
                    }

                    $width_style = self::normalize_docx_word_width_style(
                        self::get_docx_attribute_value($property, 'w'),
                        self::get_docx_attribute_value($property, 'type')
                    );
                    if ($width_style !== '') {
                        return $width_style;
                    }
                }
            }

            if (empty($table_grid_widths)) {
                return '';
            }

            $width_slice = array_slice($table_grid_widths, $start_col, $colspan);
            if (empty($width_slice)) {
                return '';
            }

            $total_width = array_sum($table_grid_widths);
            $cell_width = array_sum($width_slice);
            if ($total_width <= 0 || $cell_width <= 0) {
                return '';
            }

            $percentage = round(($cell_width / $total_width) * 100, 2);
            $formatted = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');

            return 'width:' . $formatted . '%;';
        }

        private static function docx_table_cell_looks_like_header(DOMElement $cell): bool
        {
            if (self::extract_docx_table_cell_background_color($cell) !== '') {
                return true;
            }

            foreach ($cell->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'b') as $bold_node) {
                if ($bold_node instanceof DOMElement) {
                    return true;
                }
            }

            return false;
        }

        private static function normalize_docx_color_hex(string $value): string
        {
            $value = strtoupper(trim($value));
            $value = preg_replace('/[^0-9A-F]/', '', $value);
            if (!is_string($value) || strlen($value) !== 6) {
                return '';
            }

            if (in_array($value, ['AUTO', 'FFFFFF00'], true)) {
                return '';
            }

            return '#' . $value;
        }

        private static function normalize_docx_word_width_style(string $raw_width, string $width_type): string
        {
            $width = trim($raw_width);
            $type = strtolower(trim($width_type));
            if ($width === '' || $type === '' || $type === 'auto') {
                return '';
            }

            if (!preg_match('/^\d+(?:\.\d+)?$/', $width)) {
                return '';
            }

            $numeric_width = (float) $width;
            if ($numeric_width <= 0) {
                return '';
            }

            if ($type === 'pct') {
                $percentage = $numeric_width / 50;
                $formatted = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');
                return 'width:' . $formatted . '%;';
            }

            if ($type === 'dxa') {
                return 'width:' . self::convert_docx_twips_to_pixels($numeric_width) . 'px;';
            }

            return '';
        }

        private static function convert_docx_twips_to_pixels(float $twips): string
        {
            $pixels = $twips / 15;
            if ($pixels <= 0) {
                return '0';
            }

            return rtrim(rtrim(number_format($pixels, 2, '.', ''), '0'), '.');
        }

        private static function convert_docx_table_cell_element_to_html(
            DOMElement $cell,
            array $image_rel_map,
            ZipArchive $zip,
            array $hyperlink_rel_map = []
        ): string
        {
            $parts = [];

            foreach ($cell->childNodes as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }

                if ($child->localName === 'p') {
                    $paragraph_html = self::convert_docx_paragraph_element_to_html($child, $image_rel_map, $zip, $hyperlink_rel_map);
                    if ($paragraph_html !== '') {
                        $parts[] = $paragraph_html;
                    }
                    continue;
                }

                if ($child->localName === 'tbl') {
                    $nested_table_html = self::convert_docx_table_element_to_html($child, $image_rel_map, $zip, $hyperlink_rel_map);
                    if ($nested_table_html !== '') {
                        $parts[] = $nested_table_html;
                    }
                }
            }

            return implode('', $parts);
        }

        private static function convert_docx_paragraph_element_to_html(
            DOMElement $paragraph,
            array $image_rel_map,
            ZipArchive $zip,
            array $hyperlink_rel_map = []
        ): string
        {
            $xml = $paragraph->ownerDocument instanceof DOMDocument
                ? (string) $paragraph->ownerDocument->saveXML($paragraph)
                : '';
            if ($xml === '') {
                return '';
            }

            $parts = [];
            $paragraph_alignment = self::extract_docx_paragraph_alignment($paragraph);
            $inline_html = self::render_docx_inline_tokens_to_html(
                self::extract_docx_paragraph_inline_tokens($paragraph, $hyperlink_rel_map)
            );
            if ($inline_html !== '') {
                $parts[] = self::wrap_docx_inline_html_fragment($inline_html, $paragraph_alignment);
            } else {
                $text = self::extract_docx_paragraph_text($xml);
                if ($text !== '') {
                    $safe_text = esc_html($text);
                    $safe_text = str_replace(["\r\n", "\r"], "\n", $safe_text);
                    $safe_text = str_replace("\n", '<br />', $safe_text);
                    $parts[] = self::wrap_docx_inline_html_fragment($safe_text, $paragraph_alignment);
                }
            }

            foreach (self::extract_docx_image_html_fragments_from_xml($xml, $image_rel_map, $zip, $paragraph_alignment) as $image_html) {
                $parts[] = $image_html;
            }

            return implode('', $parts);
        }

        private static function extract_docx_paragraph_alignment(DOMElement $paragraph): string
        {
            foreach ($paragraph->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'pPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        !$property instanceof DOMElement ||
                        (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                        (string) $property->localName !== 'jc'
                    ) {
                        continue;
                    }

                    return self::normalize_docx_text_alignment(self::get_docx_attribute_value($property, 'val'));
                }
            }

            return '';
        }

        private static function extract_docx_table_alignment(DOMElement $table): string
        {
            foreach ($table->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'tblPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        !$property instanceof DOMElement ||
                        (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                        (string) $property->localName !== 'jc'
                    ) {
                        continue;
                    }

                    return self::normalize_docx_text_alignment(self::get_docx_attribute_value($property, 'val'));
                }
            }

            return '';
        }

        private static function normalize_docx_text_alignment(string $alignment): string
        {
            $normalized = strtolower(trim($alignment));
            if ($normalized === 'both' || $normalized === 'distribute') {
                return 'justify';
            }

            if (in_array($normalized, ['left', 'center', 'right', 'justify'], true)) {
                return $normalized;
            }

            return '';
        }

        private static function build_docx_text_alignment_style(string $alignment): string
        {
            $normalized = self::normalize_docx_text_alignment($alignment);
            if ($normalized === '') {
                return '';
            }

            return 'text-align:' . $normalized . ';';
        }

        private static function build_docx_table_alignment_style(string $alignment): string
        {
            $normalized = self::normalize_docx_text_alignment($alignment);
            if ($normalized === 'center') {
                return 'margin-left:auto;margin-right:auto;';
            }

            if ($normalized === 'right') {
                return 'margin-left:auto;margin-right:0;';
            }

            return '';
        }

        private static function extract_docx_paragraph_inline_tokens(DOMElement $paragraph, array $hyperlink_rel_map = []): array
        {
            $tokens = [];
            foreach ($paragraph->childNodes as $child) {
                $tokens = array_merge($tokens, self::extract_docx_inline_tokens_from_node($child, $hyperlink_rel_map));
            }

            return $tokens;
        }

        private static function extract_docx_inline_tokens_from_node(
            DOMNode $node,
            array $hyperlink_rel_map = [],
            array $styles = [],
            string $href = ''
        ): array {
            if ($node instanceof DOMText) {
                $value = (string) $node->nodeValue;
                return trim($value) === '' ? [] : [[
                    'text' => $value,
                    'styles' => $styles,
                    'href' => $href,
                    'kind' => 'text',
                ]];
            }

            if (!$node instanceof DOMElement) {
                return [];
            }

            $namespace = (string) $node->namespaceURI;
            $local_name = (string) $node->localName;

            if ($namespace === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                if ($local_name === 'r') {
                    $run_styles = self::resolve_docx_run_styles($node, $styles);
                    $tokens = [];
                    foreach ($node->childNodes as $child) {
                        $tokens = array_merge($tokens, self::extract_docx_inline_tokens_from_node($child, $hyperlink_rel_map, $run_styles, $href));
                    }
                    return $tokens;
                }

                if ($local_name === 'hyperlink') {
                    $link_href = '';
                    $rel_id = self::get_docx_attribute_value($node, 'id');
                    if ($rel_id !== '' && isset($hyperlink_rel_map[$rel_id])) {
                        $link_href = trim((string) $hyperlink_rel_map[$rel_id]);
                    }

                    $tokens = [];
                    foreach ($node->childNodes as $child) {
                        $tokens = array_merge(
                            $tokens,
                            self::extract_docx_inline_tokens_from_node(
                                $child,
                                $hyperlink_rel_map,
                                $styles,
                                $link_href !== '' ? $link_href : $href
                            )
                        );
                    }
                    return $tokens;
                }

                if ($local_name === 't') {
                    $value = html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if ($value === '') {
                        return [];
                    }
                    return [[
                        'text' => $value,
                        'styles' => $styles,
                        'href' => $href,
                        'kind' => 'text',
                    ]];
                }

                if ($local_name === 'br' || $local_name === 'cr') {
                    return [[
                        'text' => "\n",
                        'styles' => [],
                        'href' => '',
                        'kind' => 'break',
                    ]];
                }

                if ($local_name === 'tab') {
                    return [[
                        'text' => "\t",
                        'styles' => [],
                        'href' => '',
                        'kind' => 'tab',
                    ]];
                }

                if ($local_name === 'drawing' || $local_name === 'pict') {
                    return [];
                }

                if ($local_name === 'sym') {
                    $value = self::decode_docx_symbol_element($node);
                    if ($value === '') {
                        return [];
                    }

                    return [[
                        'text' => $value,
                        'styles' => $styles,
                        'href' => $href,
                        'kind' => 'text',
                    ]];
                }
            }

            if ($namespace === 'http://schemas.openxmlformats.org/officeDocument/2006/math') {
                $math_fallback = self::render_docx_math_node($node);
                if ($math_fallback === '') {
                    return [];
                }

                $math_source = self::render_docx_math_node_to_katex($node);
                $math_styles = $styles;
                if (trim((string) ($math_styles['font_size'] ?? '')) === '') {
                    $math_styles['font_size'] = self::resolve_docx_math_node_font_size($node);
                }
                $math_diagnostics = self::collect_docx_math_diagnostic_entries($node, $math_source);
                if ($math_source !== '') {
                    return [[
                        'text' => $math_fallback,
                        'styles' => $math_styles,
                        'href' => $href,
                        'kind' => 'math',
                        'math_source' => $math_source,
                        'diagnostics' => $math_diagnostics,
                    ]];
                }

                return [[
                    'text' => $math_fallback,
                    'styles' => $math_styles,
                    'href' => $href,
                    'kind' => 'text',
                    'diagnostics' => $math_diagnostics,
                ]];
            }

            $tokens = [];
            foreach ($node->childNodes as $child) {
                $tokens = array_merge($tokens, self::extract_docx_inline_tokens_from_node($child, $hyperlink_rel_map, $styles, $href));
            }

            return $tokens;
        }

        private static function resolve_docx_run_styles(DOMElement $run, array $base_styles = []): array
        {
            $styles = [
                'bold' => !empty($base_styles['bold']),
                'italic' => !empty($base_styles['italic']),
                'underline' => !empty($base_styles['underline']),
                'vert_align' => isset($base_styles['vert_align']) ? (string) $base_styles['vert_align'] : '',
                'font_size' => isset($base_styles['font_size']) ? (string) $base_styles['font_size'] : '',
            ];

            foreach ($run->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' ||
                    (string) $child->localName !== 'rPr'
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property) {
                    if (
                        !$property instanceof DOMElement ||
                        (string) $property->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                    ) {
                        continue;
                    }

                    $property_name = (string) $property->localName;
                    if ($property_name === 'b' || $property_name === 'bCs') {
                        $styles['bold'] = self::docx_property_is_enabled($property);
                    } elseif ($property_name === 'i' || $property_name === 'iCs') {
                        $styles['italic'] = self::docx_property_is_enabled($property);
                    } elseif ($property_name === 'u') {
                        $underline_value = strtolower(self::get_docx_attribute_value($property, 'val'));
                        $styles['underline'] = ($underline_value === '' || $underline_value !== 'none');
                    } elseif ($property_name === 'sz' || $property_name === 'szCs') {
                        $font_size = self::normalize_docx_font_size_property($property);
                        if ($font_size !== '') {
                            $styles['font_size'] = $font_size;
                        }
                    } elseif ($property_name === 'vertAlign') {
                        $vert_align = strtolower(self::get_docx_attribute_value($property, 'val'));
                        if (in_array($vert_align, ['superscript', 'subscript'], true)) {
                            $styles['vert_align'] = $vert_align;
                        }
                    }
                }
            }

            return $styles;
        }

        private static function normalize_docx_font_size_property(DOMElement $property): string
        {
            $raw_value = trim(self::get_docx_attribute_value($property, 'val'));
            if ($raw_value === '' || preg_match('/^\d+(?:\.\d+)?$/', $raw_value) !== 1) {
                return '';
            }

            $half_points = (float) $raw_value;
            if ($half_points <= 0.0) {
                return '';
            }

            $points = $half_points / 2;
            if ($points < 4 || $points > 96) {
                return '';
            }

            if (abs($points - floor($points)) < 0.001) {
                return (string) (int) round($points) . 'pt';
            }

            return rtrim(rtrim(number_format($points, 1, '.', ''), '0'), '.') . 'pt';
        }

        private static function resolve_docx_math_node_font_size(DOMElement $node): string
        {
            $largest_points = 0.0;

            foreach ($node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'sz') as $size_node) {
                if (!$size_node instanceof DOMElement) {
                    continue;
                }

                $font_size = self::normalize_docx_font_size_property($size_node);
                $font_points = self::parse_docx_font_size_points($font_size);
                if ($font_points > $largest_points) {
                    $largest_points = $font_points;
                }
            }

            foreach ($node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'szCs') as $size_node) {
                if (!$size_node instanceof DOMElement) {
                    continue;
                }

                $font_size = self::normalize_docx_font_size_property($size_node);
                $font_points = self::parse_docx_font_size_points($font_size);
                if ($font_points > $largest_points) {
                    $largest_points = $font_points;
                }
            }

            if ($largest_points <= 0.0) {
                return '';
            }

            if (abs($largest_points - floor($largest_points)) < 0.001) {
                return (string) (int) round($largest_points) . 'pt';
            }

            return rtrim(rtrim(number_format($largest_points, 1, '.', ''), '0'), '.') . 'pt';
        }

        private static function parse_docx_font_size_points(string $font_size): float
        {
            if (preg_match('/^(\d+(?:\.\d+)?)pt$/', trim($font_size), $matches) !== 1) {
                return 0.0;
            }

            return (float) $matches[1];
        }

        private static function docx_property_is_enabled(DOMElement $property): bool
        {
            $value = strtolower(self::get_docx_attribute_value($property, 'val'));
            if ($value === '' || in_array($value, ['1', 'true', 'on'], true)) {
                return true;
            }

            return !in_array($value, ['0', 'false', 'off', 'none'], true);
        }

        private static function render_docx_inline_tokens_to_html(array $tokens): string
        {
            $html = '';
            $prefer_block_math = self::docx_inline_tokens_prefer_block_math($tokens);

            foreach ($tokens as $token) {
                $kind = (string) ($token['kind'] ?? 'text');
                if ($kind === 'break') {
                    $html .= '<br />';
                    continue;
                }
                if ($kind === 'tab') {
                    $html .= '&nbsp;&nbsp;&nbsp;&nbsp;';
                    continue;
                }

                $text = (string) ($token['text'] ?? '');
                if ($text === '') {
                    continue;
                }

                if ($kind === 'math') {
                    $math_source = (string) ($token['math_source'] ?? '');
                    if ($math_source !== '') {
                        $html .= self::render_docx_math_fragment_html(
                            $math_source,
                            $text,
                            $prefer_block_math ? 'block' : 'inline',
                            (array) ($token['styles'] ?? [])
                        );
                        continue;
                    }
                }

                $html .= self::render_docx_token_fragment_html($text, (array) ($token['styles'] ?? []), (string) ($token['href'] ?? ''));
            }

            return CBT_Admin_Questions_Helper::sanitize_editor_html($html);
        }

        private static function docx_inline_tokens_require_rich_html(array $tokens): bool
        {
            foreach ($tokens as $token) {
                if ((string) ($token['kind'] ?? 'text') === 'math' && trim((string) ($token['math_source'] ?? '')) !== '') {
                    return true;
                }

                $styles = (array) ($token['styles'] ?? []);
                if (!empty($styles['bold']) || !empty($styles['italic']) || !empty($styles['underline'])) {
                    return true;
                }

                $vert_align = isset($styles['vert_align']) ? (string) $styles['vert_align'] : '';
                if ($vert_align === 'superscript' || $vert_align === 'subscript') {
                    return true;
                }

                if (trim((string) ($styles['font_size'] ?? '')) !== '') {
                    return true;
                }

                if (trim((string) ($token['href'] ?? '')) !== '') {
                    return true;
                }
            }

            return false;
        }

        private static function docx_inline_tokens_prefer_block_math(array $tokens): bool
        {
            $has_math = false;

            foreach ($tokens as $token) {
                $kind = (string) ($token['kind'] ?? 'text');
                if ($kind === 'break') {
                    continue;
                }

                if ($kind === 'tab') {
                    continue;
                }

                $text = (string) ($token['text'] ?? '');
                if ($kind === 'math' && trim((string) ($token['math_source'] ?? '')) !== '') {
                    $has_math = true;
                    continue;
                }

                if (trim($text) !== '') {
                    return false;
                }
            }

            return $has_math;
        }

        private static function render_docx_token_fragment_html(string $text, array $styles = [], string $href = ''): string
        {
            $html = esc_html($text);

            $vert_align = isset($styles['vert_align']) ? (string) $styles['vert_align'] : '';
            if ($vert_align === 'superscript') {
                $html = '<sup>' . $html . '</sup>';
            } elseif ($vert_align === 'subscript') {
                $html = '<sub>' . $html . '</sub>';
            }

            if (!empty($styles['underline'])) {
                $html = '<u>' . $html . '</u>';
            }
            if (!empty($styles['italic'])) {
                $html = '<em>' . $html . '</em>';
            }
            if (!empty($styles['bold'])) {
                $html = '<strong>' . $html . '</strong>';
            }

            $font_size = trim((string) ($styles['font_size'] ?? ''));
            if ($font_size !== '') {
                $html = '<span style="' . esc_attr('font-size:' . $font_size . ';') . '">' . $html . '</span>';
            }

            $href = trim($href);
            if ($href !== '') {
                $html = '<a href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer">' . $html . '</a>';
            }

            return $html;
        }

        private static function render_docx_math_fragment_html(string $source, string $fallback, string $display_mode, array $styles = []): string
        {
            $normalized_display_mode = strtolower(trim($display_mode)) === 'block' ? 'block' : 'inline';
            $tag_name = $normalized_display_mode === 'block' ? 'div' : 'span';
            $class_name = $normalized_display_mode === 'block' ? 'cbt-math cbt-math-block' : 'cbt-math';
            $font_size = trim((string) ($styles['font_size'] ?? ''));
            $style_attribute = $font_size !== '' ? ' style="' . esc_attr('font-size:' . $font_size . ';') . '"' : '';

            return sprintf(
                '<%1$s class="%2$s" data-cbt-math="%3$s" data-cbt-math-display="%4$s"%5$s>%6$s</%1$s>',
                $tag_name,
                esc_attr($class_name),
                esc_attr($source),
                esc_attr($normalized_display_mode),
                $style_attribute,
                esc_html($fallback)
            );
        }

        /**
         * @return array<int,array<string,string>>
         */
        private static function collect_docx_math_diagnostic_entries(DOMElement $node, string $math_source): array
        {
            $entries = [];

            if ($math_source !== '') {
                $entries[] = [
                    'kind' => 'preserved',
                    'feature' => 'equation_visual',
                    'message' => 'Equation Word dipertahankan sebagai visual math berbasis KaTeX.',
                ];

                if (strpos($math_source, '\\begin{aligned}') !== false) {
                    $entries[] = [
                        'kind' => 'fallback',
                        'feature' => 'multiline_equation_normalized',
                        'message' => 'Equation multiline Word dinormalisasi ke aligned KaTeX yang stabil.',
                    ];
                }
            }

            if (self::docx_math_node_contains_unsupported_element($node)) {
                $entries[] = [
                    'kind' => 'unsupported',
                    'feature' => 'unsupported_equation_node_dropped',
                    'message' => 'Sebagian node Equation Word belum dipetakan penuh dan diturunkan ke bentuk aman.',
                ];
            }

            return $entries;
        }

        private static function docx_math_node_contains_unsupported_element(DOMElement $node): bool
        {
            $supported_local_names = [
                't', 'oMath', 'oMathPara', 'r', 'e', 'sub', 'sup', 'num', 'den', 'deg', 'fName', 'box', 'bar',
                'borderBox', 'phant', 'sSup', 'sSub', 'sSubSup', 'sPre', 'f', 'rad', 'd', 'func', 'limLow',
                'limUpp', 'nary', 'mr', 'm', 'eqArr', 'acc', 'groupChr', 'accPr', 'argPr', 'ctrlPr', 'dPr',
                'funcPr', 'limLowPr', 'limUppPr', 'naryPr', 'radPr', 'rPr', 'sPrePr', 'sSubPr', 'sSupPr',
                'sSubSupPr', 'brk', 'begChr', 'endChr', 'chr', 'pos', 'val', 'vertJc',
            ];
            $supported_lookup = array_fill_keys($supported_local_names, true);

            foreach ($node->getElementsByTagNameNS('http://schemas.openxmlformats.org/officeDocument/2006/math', '*') as $math_child) {
                if (!$math_child instanceof DOMElement) {
                    continue;
                }

                if (!isset($supported_lookup[(string) $math_child->localName])) {
                    return true;
                }
            }

            return false;
        }

        private static function build_docx_rich_lines_from_paragraph_tokens(array $tokens, string $plain_text, string $paragraph_alignment = ''): array
        {
            $plain_text = trim($plain_text);
            if ($plain_text === '') {
                return [];
            }

            $colon_position = strpos($plain_text, ':');
            if ($colon_position !== false) {
                $key = trim(substr($plain_text, 0, $colon_position));
                if (self::is_docx_key_only_line($key)) {
                    $value_tokens = self::slice_docx_inline_tokens_after_offset($tokens, $colon_position + 1);
                    $value_html = trim(self::render_docx_inline_tokens_to_html($value_tokens));
                    if ($value_html === '') {
                        return [$key . ':'];
                    }

                    return [
                        $key . ':',
                        self::create_docx_html_marker(self::wrap_docx_inline_html_fragment($value_html, $paragraph_alignment)),
                    ];
                }
            }

            $html = trim(self::render_docx_inline_tokens_to_html($tokens));
            if ($html === '') {
                return [$plain_text];
            }

            return [self::create_docx_html_marker(self::wrap_docx_inline_html_fragment($html, $paragraph_alignment))];
        }

        private static function wrap_docx_inline_html_fragment(string $html, string $paragraph_alignment = ''): string
        {
            $html = trim($html);
            if ($html === '') {
                return '';
            }

            $alignment_style = self::build_docx_text_alignment_style($paragraph_alignment);
            $style_attribute = $alignment_style !== '' ? ' style="' . esc_attr($alignment_style) . '"' : '';

            if (preg_match('/^<(?:div|table|thead|tbody|tfoot|tr|td|th|figure|figcaption|img|ul|ol|li)\b/i', $html) === 1) {
                if ($style_attribute === '') {
                    return $html;
                }

                return '<div' . $style_attribute . '>' . $html . '</div>';
            }

            return '<p' . $style_attribute . '>' . $html . '</p>';
        }

        private static function slice_docx_inline_tokens_after_offset(array $tokens, int $offset): array
        {
            $remaining = max(0, $offset);
            $result = [];

            foreach ($tokens as $token) {
                $text = (string) ($token['text'] ?? '');
                $kind = (string) ($token['kind'] ?? 'text');

                if ($kind === 'break' || $kind === 'tab') {
                    if ($remaining <= 0) {
                        $result[] = $token;
                    }
                    continue;
                }

                $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                if ($remaining >= $length) {
                    $remaining -= $length;
                    continue;
                }

                if ($remaining > 0) {
                    $text = function_exists('mb_substr')
                        ? (string) mb_substr($text, $remaining, null, 'UTF-8')
                        : (string) substr($text, $remaining);
                    $remaining = 0;
                }

                $token['text'] = $text;
                $result[] = $token;
            }

            return $result;
        }

        private static function docx_has_required_template_marker(array $lines): bool
        {
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || strpos($line, ':') === false) {
                    continue;
                }

                $parts = explode(':', $line, 2);
                $key = strtolower(trim((string) ($parts[0] ?? '')));
                $key = str_replace([' ', '-'], '_', $key);
                $value = strtolower(trim((string) ($parts[1] ?? '')));

                if ($key === self::DOCX_TEMPLATE_MARKER_KEY && $value === self::DOCX_TEMPLATE_MARKER_VALUE) {
                    return true;
                }
            }

            return false;
        }

        private static function extract_docx_question_blocks(array $lines): array
        {
            $blocks = [];
            $current_block = [];
            $seen_first_separator = false;

            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '---') {
                    if ($seen_first_separator && !empty($current_block)) {
                        $blocks[] = $current_block;
                        $current_block = [];
                    }

                    $seen_first_separator = true;
                    continue;
                }

                if (!$seen_first_separator || $line === '') {
                    continue;
                }

                $current_block[] = $line;
            }

            if (!empty($current_block)) {
                $blocks[] = $current_block;
            }

            return $blocks;
        }

        private static function import_single_question_row(array $row, int $default_exam_id, bool $is_admin_scope, int $current_user_id, array &$affected_exam_ids = []): array
        {
            global $wpdb;

            if (!empty($row['__import_error'])) {
                $failure_entry = self::build_import_failure_entry_from_row($row, (string) $row['__import_error']);
                return [
                    'status' => 'failed',
                    'message' => (string) ($failure_entry['formatted'] ?? ''),
                    'failure_entry' => $failure_entry,
                ];
            }

            $question_type = self::map_import_question_type((string) ($row['question_type'] ?? ''));
            $question_text = wp_kses_post((string) ($row['question_text'] ?? ''));
            $question_text = trim($question_text);
            $explanation = CBT_Admin_Questions_Helper::normalize_optional_rich_text((string) ($row['explanation'] ?? ($row['pembahasan'] ?? '')));
            if ($question_type === '' || $question_text === '') {
                return self::failed_import_result($row, 'Jenis soal atau pertanyaan wajib diisi.');
            }

            $exam_id = self::resolve_import_question_exam_id($row, $default_exam_id, $is_admin_scope, $current_user_id);
            if ($exam_id <= 0) {
                return self::failed_import_result($row, 'Exam penampung untuk blok soal ini tidak valid.');
            }
            $affected_exam_ids[$exam_id] = $exam_id;

            $points = isset($row['points']) && $row['points'] !== '' ? (float) $row['points'] : 1.0;
            $points = max(0, $points);

            $options_input = (string) ($row['options'] ?? '');
            $correct_answer = (string) ($row['correct_answer'] ?? '');
            $correct_text = (string) ($row['correct_text'] ?? '');
            $options_raw = '';
            $matching_items = [];
            $cloze_blanks = [];
            $categorization_categories = [];
            $categorization_items = [];
            $table_completion_definition = [];

            if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
                $built = self::build_options_raw_from_import($options_input, $correct_answer, $question_type);
                if ($built === '') {
                    return self::failed_import_result(
                        $row,
                        $question_type === 'multiple_choice'
                            ? 'Pilihan atau jawaban benar Multiple Choice tidak valid. Pastikan jawaban benar menunjuk ke pilihan yang terisi.'
                            : 'Pilihan atau jawaban benar Multiple Answer tidak valid. Pastikan minimal 1 jawaban benar menunjuk ke pilihan yang terisi.'
                    );
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
                $correct_text = CBT_Admin_Questions_Helper::normalize_true_false_matrix_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
                if ($correct_text === '' || count(CBT_Admin_Questions_Helper::normalize_true_false_matrix_config($correct_text)) < 2) {
                    return self::failed_import_result($row, 'True/False Matrix minimal harus punya 2 pernyataan valid beserta kuncinya.');
                }
                $options_raw = '';
            } elseif ($question_type === 'short_answer') {
                $correct_text = CBT_Admin_Questions_Helper::normalize_short_answer_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
                if ($correct_text === '') {
                    return self::failed_import_result($row, 'Short Answer tidak valid. Pastikan placeholder dan jawaban sudah lengkap.');
                }
                $options_raw = '';
            } elseif ($question_type === 'essay') {
                $correct_text = trim($correct_text !== '' ? $correct_text : $correct_answer);
                if ($correct_text === '') {
                    return self::failed_import_result($row, 'Essay wajib memiliki acuan jawaban atau rubrik.');
                }
                $options_raw = '';
            } elseif ($question_type === 'ordering') {
                $ordering_source = $options_input !== ''
                    ? $options_input
                    : ($correct_text !== '' ? $correct_text : $correct_answer);
                $built = self::build_ordering_options_raw_from_import($ordering_source);
                if ($built === '') {
                    return self::failed_import_result($row, 'Ordering minimal harus punya 2 item valid dan tidak boleh duplikat.');
                }
                $options_raw = $built;
                $correct_text = '';
            } elseif ($question_type === 'matching') {
                $matching_items = isset($row['matching_items']) && is_array($row['matching_items'])
                    ? CBT_Admin_Questions_Helper::normalize_matching_items($row['matching_items'])
                    : self::build_matching_items_from_import_row($row);
                $matching_error = CBT_Admin_Questions_Helper::validate_matching_items($matching_items);
                if ($matching_error !== '') {
                    return self::failed_import_result($row, $matching_error);
                }
                $correct_text = CBT_Admin_Questions_Helper::build_matching_payload($matching_items);
                $options_raw = '';
            } elseif ($question_type === 'cloze_dropdown') {
                $cloze_blanks = isset($row['cloze_blanks']) && is_array($row['cloze_blanks'])
                    ? CBT_Admin_Questions_Helper::normalize_cloze_dropdown_blanks($row['cloze_blanks'])
                    : self::build_cloze_dropdown_blanks_from_import_row($row);
                $cloze_error = CBT_Admin_Questions_Helper::validate_cloze_dropdown_definition($question_text, $cloze_blanks);
                if ($cloze_error !== '') {
                    return self::failed_import_result($row, $cloze_error);
                }
                $correct_text = CBT_Admin_Questions_Helper::build_cloze_dropdown_payload($cloze_blanks);
                $options_raw = '';
            } elseif ($question_type === 'categorization') {
                $categorization_categories = isset($row['categorization_categories']) && is_array($row['categorization_categories'])
                    ? CBT_Admin_Questions_Helper::normalize_categorization_categories($row['categorization_categories'])
                    : self::build_categorization_categories_from_import_row($row);
                $categorization_items = isset($row['categorization_items']) && is_array($row['categorization_items'])
                    ? CBT_Admin_Questions_Helper::normalize_categorization_items($row['categorization_items'])
                    : self::build_categorization_items_from_import_row($row, $categorization_categories);
                $categorization_error = CBT_Admin_Questions_Helper::validate_categorization_definition($categorization_categories, $categorization_items);
                if ($categorization_error !== '') {
                    return self::failed_import_result($row, $categorization_error);
                }
                $correct_text = CBT_Admin_Questions_Helper::build_categorization_payload($categorization_categories, $categorization_items);
                $options_raw = '';
            } elseif ($question_type === 'table_completion') {
                $table_completion_definition = isset($row['table_completion']) && is_array($row['table_completion'])
                    ? CBT_Admin_Questions_Helper::normalize_table_completion_definition($row['table_completion'])
                    : self::build_table_completion_from_import_row($row);
                $table_error = CBT_Admin_Questions_Helper::validate_table_completion_definition($table_completion_definition);
                if ($table_error !== '') {
                    return self::failed_import_result($row, $table_error);
                }
                $correct_text = CBT_Admin_Questions_Helper::build_table_completion_payload($table_completion_definition);
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
                    'explanation' => $explanation,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
            );
            if (!$inserted) {
                return self::failed_import_result($row, 'Gagal menyimpan soal ke database.');
            }

            $question_id = (int) $wpdb->insert_id;
            $options_to_insert = CBT_Admin_Questions_Helper::parse_options($options_raw);
            $inserted_option_ids = [];

            if ($question_type === 'true_false' && empty($options_to_insert)) {
                $true_is_correct = (strtolower($correct_text) === 'true') ? 1 : 0;
                $options_to_insert = [
                    ['option_text' => 'True', 'is_correct' => $true_is_correct],
                    ['option_text' => 'False', 'is_correct' => $true_is_correct ? 0 : 1],
                ];
            }

            if ($question_type === 'matching') {
                $options_to_insert = array_map(static function (array $item): array {
                    return [
                        'option_text' => (string) ($item['option_text'] ?? ''),
                        'is_correct' => 0,
                    ];
                }, $matching_items);
            } elseif ($question_type === 'cloze_dropdown') {
                $options_to_insert = [];
            } elseif ($question_type === 'categorization') {
                $options_to_insert = array_map(static function (array $category): array {
                    return [
                        'option_text' => (string) ($category['option_text'] ?? ''),
                        'is_correct' => 0,
                    ];
                }, $categorization_categories);
            } elseif ($question_type === 'table_completion') {
                $options_to_insert = [];
            }

            foreach ($options_to_insert as $idx => $opt) {
                $option_inserted = $wpdb->insert(
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
                if ($option_inserted) {
                    $inserted_option_ids[] = (int) $wpdb->insert_id;
                }
            }

            $detail_context = $question_type === 'ordering'
                ? ['ordered_option_ids' => $inserted_option_ids]
                : [];
            if ($question_type === 'matching') {
                $matching_detail_items = [];
                foreach ($matching_items as $idx => $matching_item) {
                    $correct_option_id = (int) ($inserted_option_ids[$idx] ?? 0);
                    if ($correct_option_id <= 0) {
                        continue;
                    }
                    $matching_detail_items[] = [
                        'position' => (int) ($matching_item['position'] ?? ($idx + 1)),
                        'item_key' => (string) ($matching_item['item_key'] ?? ($idx + 1)),
                        'prompt_text' => (string) ($matching_item['prompt_text'] ?? ''),
                        'correct_option_id' => $correct_option_id,
                    ];
                }
                $detail_context['matching_items'] = $matching_detail_items;
            }
            if ($question_type === 'cloze_dropdown') {
                $detail_context['cloze_blanks'] = $cloze_blanks;
            }
            if ($question_type === 'categorization') {
                $categorization_detail_items = [];
                foreach ($categorization_items as $idx => $categorization_item) {
                    $category_index = (int) ($categorization_item['correct_category_index'] ?? 0);
                    $correct_option_id = $category_index > 0 ? (int) ($inserted_option_ids[$category_index - 1] ?? 0) : 0;
                    if ($correct_option_id <= 0) {
                        continue;
                    }
                    $categorization_detail_items[] = [
                        'position' => (int) ($categorization_item['position'] ?? ($idx + 1)),
                        'item_key' => (string) ($categorization_item['item_key'] ?? ($idx + 1)),
                        'item_text' => (string) ($categorization_item['item_text'] ?? ''),
                        'correct_option_id' => $correct_option_id,
                    ];
                }
                $detail_context['categorization_items'] = $categorization_detail_items;
            }
            if ($question_type === 'table_completion') {
                $detail_context['table_completion'] = $table_completion_definition;
            }
            CBT_Admin_Questions_Helper::save_question_type_detail($question_id, $question_type, $correct_text, $detail_context);

            return [
                'status' => 'created',
                'message' => '',
                'question_id' => $question_id,
            ];
        }

        private static function failed_import_result(array $row, string $message): array
        {
            $failure_entry = self::build_import_failure_entry_from_row($row, $message);
            return [
                'status' => 'failed',
                'message' => (string) ($failure_entry['formatted'] ?? ''),
                'failure_entry' => $failure_entry,
            ];
        }

        private static function build_import_failure_entry_from_row(array $row, string $message): array
        {
            $entry = [
                'block_number' => isset($row['__import_source_block']) ? (int) $row['__import_source_block'] : 0,
                'question_type' => self::map_import_question_type((string) ($row['question_type'] ?? '')),
                'question_preview' => (string) ($row['question_text'] ?? ''),
                'message' => trim($message),
            ];

            return self::normalize_question_import_failure_entry($entry) ?? [
                'block_number' => 0,
                'question_type' => '',
                'question_preview' => '',
                'message' => trim($message),
                'formatted' => trim($message),
            ];
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

        private static function parse_docx_true_false_value_strict(string $raw): ?string
        {
            $normalized = strtolower(trim((string) $raw));
            if ($normalized === '') {
                return null;
            }

            if (in_array($normalized, ['false', '0', 'f', 'no', 'tidak', 'salah', 's'], true)) {
                return 'false';
            }

            if (in_array($normalized, ['true', '1', 't', 'yes', 'ya', 'y', 'benar', 'b'], true)) {
                return 'true';
            }

            return null;
        }

        public static function build_options_raw_from_import(string $options_input, string $correct_answer, string $question_type): string
        {
            $parts = strpos($options_input, '||') !== false
                ? explode('||', $options_input)
                : preg_split('/\r\n|\r|\n/', $options_input);
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
            $entries = [];
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
                $entries[] = [
                    'option_text' => $opt,
                    'is_correct' => $correct ? 1 : 0,
                ];
            }

            if ($question_type === 'multiple_choice') {
                if ($correct_count === 0 && !empty($entries)) {
                    $entries[0]['is_correct'] = 1;
                } elseif ($correct_count > 1) {
                    $already = false;
                    foreach ($entries as $idx => $entry) {
                        if (!empty($entry['is_correct'])) {
                            if (!$already) {
                                $already = true;
                            } else {
                                $entries[$idx]['is_correct'] = 0;
                            }
                        }
                    }
                }
            } elseif ($question_type === 'multiple_answer' && $correct_count === 0) {
                return '';
            }

            $encoded = wp_json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : '';
        }

        public static function build_ordering_options_raw_from_import(string $options_input): string
        {
            $parts = strpos($options_input, '||') !== false
                ? explode('||', $options_input)
                : preg_split('/\r\n|\r|\n/', $options_input);
            $entries = [];
            $seen = [];

            foreach ((array) $parts as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    continue;
                }

                $part = (string) preg_replace('/^(?:item|urutan|sequence|ordering|pilihan|opsi|option)_?([1-9]|1[0-2])\s*[:\.\)]\s*/iu', '', $part);
                $part = (string) preg_replace('/^([A-La-l])\s*[:\.\)]\s*/u', '', $part);
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $text = CBT_Admin_Questions_Helper::sanitize_editor_html($part);
                $dedupe_key = wp_strip_all_tags($text);
                $dedupe_key = html_entity_decode($dedupe_key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $dedupe_key = (string) preg_replace('/\s+/u', ' ', trim($dedupe_key));
                $dedupe_key = function_exists('mb_strtolower') ? mb_strtolower($dedupe_key, 'UTF-8') : strtolower($dedupe_key);
                if ($dedupe_key === '' || isset($seen[$dedupe_key])) {
                    return '';
                }

                $seen[$dedupe_key] = true;
                $entries[] = [
                    'option_text' => $text,
                    'is_correct' => 0,
                ];
            }

            $validation_error = CBT_Admin_Questions_Helper::validate_ordering_options($entries);
            if ($validation_error !== '') {
                return '';
            }

            $encoded = wp_json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : '';
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function build_matching_items_from_import_row(array $row): array
        {
            $items = [];
            for ($idx = 1; $idx <= 12; $idx++) {
                $left = trim((string) ($row['kiri_' . $idx] ?? ($row['left_' . $idx] ?? ($row['prompt_' . $idx] ?? ''))));
                $right = trim((string) ($row['kanan_' . $idx] ?? ($row['right_' . $idx] ?? ($row['match_' . $idx] ?? ''))));
                if ($left === '' && $right === '') {
                    continue;
                }
                $items[] = [
                    'position' => count($items) + 1,
                    'item_key' => (string) (count($items) + 1),
                    'prompt_text' => $left,
                    'option_text' => $right,
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_matching_items($items);
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function build_cloze_dropdown_blanks_from_import_row(array $row): array
        {
            $blanks = [];
            for ($blank_idx = 1; $blank_idx <= 8; $blank_idx++) {
                $options = [];
                $correct_raw = trim((string) (
                    $row['dropdown_' . $blank_idx . '_jawaban'] ??
                    ($row['dropdown_' . $blank_idx . '_answer'] ?? ($row['dropdown_' . $blank_idx . '_kunci'] ?? ''))
                ));
                $correct_index = 0;
                $correct_index = self::normalize_docx_option_index($correct_raw);

                for ($option_idx = 1; $option_idx <= 6; $option_idx++) {
                    $option_text = trim((string) (
                        $row['dropdown_' . $blank_idx . '_opsi_' . $option_idx] ??
                        ($row['dropdown_' . $blank_idx . '_option_' . $option_idx] ?? ($row['dropdown_' . $blank_idx . '_pilihan_' . $option_idx] ?? ''))
                    ));
                    if ($option_text === '') {
                        continue;
                    }

                    $options[] = [
                        'option_text' => $option_text,
                        'is_correct' => ($option_idx === $correct_index) ? 1 : 0,
                    ];
                }

                if (empty($options) && $correct_raw === '') {
                    continue;
                }

                $blanks[] = [
                    'blank_key' => (string) $blank_idx,
                    'blank_position' => count($blanks) + 1,
                    'options' => $options,
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_cloze_dropdown_blanks($blanks);
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private static function build_categorization_categories_from_import_row(array $row): array
        {
            $categories = [];
            for ($idx = 1; $idx <= 8; $idx++) {
                $label = trim((string) ($row['kategori_' . $idx] ?? ($row['category_' . $idx] ?? '')));
                if ($label === '') {
                    continue;
                }
                $categories[] = [
                    'category_index' => count($categories) + 1,
                    'option_text' => $label,
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_categorization_categories($categories);
        }

        /**
         * @param array<int,array<string,mixed>> $categories
         * @return array<int,array<string,mixed>>
         */
        private static function build_categorization_items_from_import_row(array $row, array $categories): array
        {
            $category_index_by_label = [];
            foreach ($categories as $idx => $category) {
                $label_key = CBT_Admin_Questions_Helper::normalize_short_answer_compare_value((string) ($category['option_text'] ?? ''));
                if ($label_key !== '') {
                    $category_index_by_label[$label_key] = $idx + 1;
                }
            }

            $items = [];
            for ($idx = 1; $idx <= 24; $idx++) {
                $text = trim((string) ($row['item_' . $idx] ?? ''));
                $key_raw = trim((string) ($row['kunci_' . $idx] ?? ($row['answer_' . $idx] ?? '')));
                if ($text === '' && $key_raw === '') {
                    continue;
                }
                $category_index = is_numeric($key_raw) ? (int) $key_raw : 0;
                if ($category_index <= 0) {
                    $label_key = CBT_Admin_Questions_Helper::normalize_short_answer_compare_value($key_raw);
                    $category_index = (int) ($category_index_by_label[$label_key] ?? 0);
                }
                $items[] = [
                    'position' => count($items) + 1,
                    'item_key' => (string) (count($items) + 1),
                    'item_text' => $text,
                    'correct_category_index' => $category_index,
                ];
            }

            return CBT_Admin_Questions_Helper::normalize_categorization_items($items);
        }

        /**
         * @return array<string,mixed>
         */
        private static function build_table_completion_from_import_row(array $row): array
        {
            $row_count = (int) ($row['table_rows'] ?? ($row['rows'] ?? 2));
            $column_count = (int) ($row['table_cols'] ?? ($row['table_columns'] ?? ($row['cols'] ?? 2)));
            $cells = [];
            for ($r = 1; $r <= 8; $r++) {
                for ($c = 1; $c <= 6; $c++) {
                    $cell_key = chr(64 + $c) . (string) $r;
                    $prefix = 'cell_' . strtolower($cell_key) . '_';
                    $prefix_upper = 'cell_' . $cell_key . '_';
                    $type = sanitize_key((string) (
                        $row[$prefix . 'type'] ??
                        ($row[$prefix_upper . 'type'] ?? 'static')
                    ));
                    if (!in_array($type, ['static', 'text', 'dropdown'], true)) {
                        $type = 'static';
                    }
                    $options = [];
                    $correct_raw = trim((string) (
                        $row[$prefix . 'jawaban'] ??
                        ($row[$prefix_upper . 'jawaban'] ?? ($row[$prefix . 'answer'] ?? ($row[$prefix_upper . 'answer'] ?? '')))
                    ));
                    $correct_index = self::normalize_docx_option_index($correct_raw);
                    for ($option_idx = 1; $option_idx <= 6; $option_idx++) {
                        $option_text = trim((string) (
                            $row[$prefix . 'opsi_' . $option_idx] ??
                            ($row[$prefix_upper . 'opsi_' . $option_idx] ?? ($row[$prefix . 'option_' . $option_idx] ?? ($row[$prefix_upper . 'option_' . $option_idx] ?? '')))
                        ));
                        if ($option_text === '') {
                            continue;
                        }
                        $options[] = [
                            'option_text' => $option_text,
                            'is_correct' => ($option_idx === $correct_index) ? 1 : 0,
                        ];
                    }
                    $cells[] = [
                        'cell_key' => $cell_key,
                        'row_position' => $r,
                        'column_position' => $c,
                        'cell_type' => $type,
                        'cell_text' => (string) (
                            $row[$prefix . 'text'] ??
                            ($row[$prefix_upper . 'text'] ?? '')
                        ),
                        'correct_text' => $type === 'text' ? $correct_raw : '',
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

        private static function normalize_docx_option_index(string $raw): int
        {
            $value = strtoupper(trim($raw));
            if ($value === '') {
                return 0;
            }
            if (preg_match('/^[A-L]$/', $value) === 1) {
                return ord($value) - ord('A') + 1;
            }
            if (is_numeric($value)) {
                $index = (int) $value;
                return ($index >= 1 && $index <= 12) ? $index : 0;
            }

            return 0;
        }

        public static function map_import_question_type(string $raw): string
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
                case 'ordering':
                case 'sequence':
                case 'sequencing':
                case 'urut':
                case 'mengurutkan':
                    return 'ordering';
                case 'matching':
                case 'match':
                case 'menjodohkan':
                case 'jodohkan':
                    return 'matching';
                case 'cloze_dropdown':
                case 'cloze':
                case 'dropdown':
                case 'isian_dropdown':
                    return 'cloze_dropdown';
                case 'categorization':
                case 'category':
                case 'kategori':
                case 'klasifikasi':
                    return 'categorization';
                case 'table_completion':
                case 'table':
                case 'completion_table':
                case 'tabel':
                case 'melengkapi_tabel':
                    return 'table_completion';
                default:
                    return '';
            }
        }

        private static function validate_question_import_upload_extension(string $extension)
        {
            if ($extension === 'docx') {
                return true;
            }

            return new WP_Error(
                'question_import_extension_invalid',
                'Format file harus DOCX. Gunakan template Word resmi CBT terbaru.'
            );
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

        private static function map_docx_active_context_to_diagnostic_field($active_context): string
        {
            if (is_array($active_context)) {
                $context_type = (string) ($active_context[0] ?? '');
                $context_index = (int) ($active_context[1] ?? 0);
                if ($context_type === 'option' && $context_index > 0) {
                    return 'PILIHAN_' . $context_index;
                }
                if ($context_type === 'matrix_statement' && $context_index > 0) {
                    return 'PERNYATAAN_' . $context_index;
                }
                if ($context_type === 'ordering_item' && $context_index > 0) {
                    return 'ITEM_' . $context_index;
                }
                if ($context_type === 'matching_left' && $context_index > 0) {
                    return 'KIRI_' . $context_index;
                }
            }

            if ($active_context === 'explanation') {
                return 'PEMBAHASAN';
            }

            if ($active_context === 'answer') {
                return 'JAWABAN';
            }

            return 'SOAL';
        }

        private static function finalize_docx_row_import_diagnostics(array $entries, int $block_number, string $question_type): array
        {
            $normalized = [];
            foreach (self::normalize_question_import_diagnostic_entries($entries) as $entry) {
                $entry['block_number'] = $block_number > 0 ? $block_number : (int) ($entry['block_number'] ?? 0);
                $entry['question_type'] = $question_type !== ''
                    ? self::map_import_question_type($question_type)
                    : (string) ($entry['question_type'] ?? '');
                $entry['question_type_label'] = CBT_Admin_Questions_Helper::get_question_type_label((string) ($entry['question_type'] ?? ''));
                if (trim((string) ($entry['field'] ?? '')) === '') {
                    $entry['field'] = 'SOAL';
                }
                $normalized[] = $entry;
            }

            return $normalized;
        }

        /**
         * @return array{diagnostic_counts: array<string,int>, diagnostic_entries: array<int,array<string,mixed>>, diagnostic_truncated: bool}
         */
        public static function aggregate_question_import_diagnostics(array $rows): array
        {
            $counts = [
                'preserved' => 0,
                'fallback' => 0,
                'unsupported' => 0,
            ];
            $entries = [];
            $seen = [];
            $truncated = false;

            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['__import_diagnostics']) || !is_array($row['__import_diagnostics'])) {
                    continue;
                }

                foreach (self::normalize_question_import_diagnostic_entries($row['__import_diagnostics']) as $entry) {
                    $signature = implode('|', [
                        (string) ($entry['block_number'] ?? 0),
                        (string) ($entry['field'] ?? ''),
                        (string) ($entry['kind'] ?? ''),
                        (string) ($entry['feature'] ?? ''),
                    ]);
                    if (isset($seen[$signature])) {
                        continue;
                    }
                    $seen[$signature] = true;

                    $kind = (string) ($entry['kind'] ?? '');
                    if (isset($counts[$kind])) {
                        $counts[$kind]++;
                    }

                    if (count($entries) < self::QUESTION_IMPORT_DIAGNOSTIC_ENTRY_LIMIT) {
                        $entries[] = $entry;
                    } else {
                        $truncated = true;
                    }
                }
            }

            usort($entries, [self::class, 'compare_question_import_diagnostic_entries']);

            return [
                'diagnostic_counts' => $counts,
                'diagnostic_entries' => $entries,
                'diagnostic_truncated' => $truncated,
            ];
        }

        public static function normalize_question_import_diagnostic_entry($entry): ?array
        {
            if (!is_array($entry)) {
                return null;
            }

            $kind = strtolower(trim((string) ($entry['kind'] ?? '')));
            if (!in_array($kind, ['preserved', 'fallback', 'unsupported'], true)) {
                return null;
            }

            $feature = trim((string) ($entry['feature'] ?? ''));
            $message = trim((string) ($entry['message'] ?? ''));
            if ($feature === '' || $message === '') {
                return null;
            }

            $field = strtoupper(trim((string) ($entry['field'] ?? '')));
            $field = preg_replace('/[^A-Z0-9_]/', '_', $field);
            $field = is_string($field) ? trim($field, '_') : '';
            $question_type = self::map_import_question_type((string) ($entry['question_type'] ?? ''));

            return [
                'block_number' => max(0, (int) ($entry['block_number'] ?? 0)),
                'question_type' => $question_type,
                'question_type_label' => trim((string) ($entry['question_type_label'] ?? CBT_Admin_Questions_Helper::get_question_type_label($question_type))),
                'field' => $field,
                'kind' => $kind,
                'feature' => $feature,
                'message' => $message,
            ];
        }

        public static function normalize_question_import_diagnostic_entries(array $entries): array
        {
            $normalized = [];
            foreach ($entries as $entry) {
                $entry = self::normalize_question_import_diagnostic_entry($entry);
                if (!is_array($entry)) {
                    continue;
                }
                $normalized[] = $entry;
            }

            return $normalized;
        }

        private static function compare_question_import_diagnostic_entries(array $left, array $right): int
        {
            $left_block = (int) ($left['block_number'] ?? 0);
            $right_block = (int) ($right['block_number'] ?? 0);
            if ($left_block !== $right_block) {
                return $left_block <=> $right_block;
            }

            $left_field_priority = self::get_question_import_diagnostic_field_priority((string) ($left['field'] ?? ''));
            $right_field_priority = self::get_question_import_diagnostic_field_priority((string) ($right['field'] ?? ''));
            if ($left_field_priority !== $right_field_priority) {
                return $left_field_priority <=> $right_field_priority;
            }

            $severity_order = [
                'unsupported' => 0,
                'fallback' => 1,
                'preserved' => 2,
            ];
            $left_kind = (string) ($left['kind'] ?? '');
            $right_kind = (string) ($right['kind'] ?? '');
            $left_kind_priority = $severity_order[$left_kind] ?? 99;
            $right_kind_priority = $severity_order[$right_kind] ?? 99;
            if ($left_kind_priority !== $right_kind_priority) {
                return $left_kind_priority <=> $right_kind_priority;
            }

            return strcmp((string) ($left['feature'] ?? ''), (string) ($right['feature'] ?? ''));
        }

        private static function get_question_import_diagnostic_field_priority(string $field): int
        {
            $field = strtoupper(trim($field));
            if ($field === 'SOAL') {
                return 0;
            }
            if (preg_match('/^PILIHAN_(\d+)$/', $field, $matches) === 1) {
                return 10 + (int) $matches[1];
            }
            if ($field === 'PEMBAHASAN') {
                return 200;
            }
            if ($field === 'JAWABAN') {
                return 210;
            }
            if (preg_match('/^PERNYATAAN_(\d+)$/', $field, $matches) === 1) {
                return 300 + (int) $matches[1];
            }

            return 999;
        }

        public static function get_question_import_state_for_current_user(string $token): ?array
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

        /**
         * @param array<int,mixed> $ids
         * @return int[]
         */
        public static function normalize_question_import_created_question_ids(array $ids): array
        {
            $normalized = [];
            foreach ($ids as $id) {
                $id = absint($id);
                if ($id <= 0 || isset($normalized[$id])) {
                    continue;
                }
                $normalized[$id] = $id;
            }

            return array_values($normalized);
        }

        /**
         * @return int[]
         */
        public static function get_question_import_created_question_ids_for_current_user(string $token): array
        {
            $state = self::get_question_import_state_for_current_user($token);
            if (!is_array($state)) {
                return [];
            }

            return self::normalize_question_import_created_question_ids(
                isset($state['created_question_ids']) && is_array($state['created_question_ids'])
                    ? $state['created_question_ids']
                    : []
            );
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        public static function get_question_import_created_question_items_for_current_user(string $token): array
        {
            $state = self::get_question_import_state_for_current_user($token);
            if (!is_array($state)) {
                return [];
            }

            return self::normalize_question_import_created_question_items(
                isset($state['created_question_items']) && is_array($state['created_question_items'])
                    ? $state['created_question_items']
                    : []
            );
        }

        /**
         * @param array<int,mixed> $items
         * @return array<int,array<string,mixed>>
         */
        public static function normalize_question_import_created_question_items(array $items): array
        {
            $normalized = [];
            $seen_question_ids = [];

            foreach ($items as $item) {
                $item = self::normalize_question_import_created_question_item($item);
                if (!is_array($item)) {
                    continue;
                }

                $question_id = (int) ($item['question_id'] ?? 0);
                if ($question_id <= 0 || isset($seen_question_ids[$question_id])) {
                    continue;
                }

                $seen_question_ids[$question_id] = true;
                $normalized[] = $item;
            }

            return $normalized;
        }

        public static function normalize_question_import_created_question_item($item): ?array
        {
            if (!is_array($item)) {
                return null;
            }

            $question_id = absint($item['question_id'] ?? 0);
            if ($question_id <= 0) {
                return null;
            }

            $question_type = self::map_import_question_type((string) ($item['question_type'] ?? ''));
            $diagnostic_entries = self::normalize_question_import_diagnostic_entries(
                isset($item['diagnostic_entries']) && is_array($item['diagnostic_entries'])
                    ? $item['diagnostic_entries']
                    : []
            );
            $diagnostic_counts = self::normalize_question_import_created_item_diagnostic_counts(
                isset($item['diagnostic_counts']) && is_array($item['diagnostic_counts'])
                    ? $item['diagnostic_counts']
                    : self::summarize_question_import_diagnostic_entries($diagnostic_entries)
            );

            $preview = trim((string) ($item['preview'] ?? ''));
            $preview = preg_replace('/\s+/u', ' ', wp_strip_all_tags($preview));
            $preview = is_string($preview) ? trim($preview) : '';
            if ($preview !== '') {
                $preview = function_exists('mb_substr')
                    ? mb_substr($preview, 0, 140, 'UTF-8')
                    : substr($preview, 0, 140);
            }

            return [
                'question_id' => $question_id,
                'block_number' => max(0, (int) ($item['block_number'] ?? 0)),
                'question_type' => $question_type,
                'question_type_label' => trim((string) ($item['question_type_label'] ?? CBT_Admin_Questions_Helper::get_question_type_label($question_type))),
                'preview' => $preview,
                'diagnostic_counts' => $diagnostic_counts,
                'diagnostic_entries' => $diagnostic_entries,
            ];
        }

        /**
         * @param array<string,mixed> $row
         * @return array<string,mixed>
         */
        private static function build_question_import_created_question_item(array $row, int $question_id): array
        {
            $question_type = self::map_import_question_type((string) ($row['question_type'] ?? ''));
            $diagnostic_entries = self::normalize_question_import_diagnostic_entries(
                isset($row['__import_diagnostics']) && is_array($row['__import_diagnostics'])
                    ? $row['__import_diagnostics']
                    : []
            );

            return self::normalize_question_import_created_question_item([
                'question_id' => $question_id,
                'block_number' => isset($row['__import_source_block']) ? (int) $row['__import_source_block'] : 0,
                'question_type' => $question_type,
                'question_type_label' => CBT_Admin_Questions_Helper::get_question_type_label($question_type),
                'preview' => (string) ($row['question_text'] ?? ''),
                'diagnostic_counts' => self::summarize_question_import_diagnostic_entries($diagnostic_entries),
                'diagnostic_entries' => $diagnostic_entries,
            ]) ?? [];
        }

        /**
         * @param array<int,array<string,mixed>> $items
         * @return array<string,int>
         */
        public static function summarize_question_import_created_question_items(array $items): array
        {
            $summary = [
                'preserved' => 0,
                'fallback' => 0,
                'unsupported' => 0,
            ];

            foreach (self::normalize_question_import_created_question_items($items) as $item) {
                $counts = self::normalize_question_import_created_item_diagnostic_counts(
                    isset($item['diagnostic_counts']) && is_array($item['diagnostic_counts'])
                        ? $item['diagnostic_counts']
                        : []
                );
                foreach ($summary as $kind => $value) {
                    $summary[$kind] += (int) ($counts[$kind] ?? 0);
                }
            }

            return $summary;
        }

        /**
         * @param array<int,array<string,mixed>> $items
         */
        public static function get_default_question_import_created_question_item_id(array $items): int
        {
            $normalized_items = self::normalize_question_import_created_question_items($items);
            if (empty($normalized_items)) {
                return 0;
            }

            foreach ($normalized_items as $item) {
                $counts = self::normalize_question_import_created_item_diagnostic_counts(
                    isset($item['diagnostic_counts']) && is_array($item['diagnostic_counts'])
                        ? $item['diagnostic_counts']
                        : []
                );
                if ((int) ($counts['fallback'] ?? 0) > 0 || (int) ($counts['unsupported'] ?? 0) > 0) {
                    return (int) ($item['question_id'] ?? 0);
                }
            }

            return (int) ($normalized_items[0]['question_id'] ?? 0);
        }

        /**
         * @param array<int,array<string,mixed>> $entries
         * @return array<string,int>
         */
        private static function summarize_question_import_diagnostic_entries(array $entries): array
        {
            $summary = [
                'preserved' => 0,
                'fallback' => 0,
                'unsupported' => 0,
            ];

            foreach (self::normalize_question_import_diagnostic_entries($entries) as $entry) {
                $kind = (string) ($entry['kind'] ?? '');
                if (isset($summary[$kind])) {
                    $summary[$kind]++;
                }
            }

            return $summary;
        }

        /**
         * @param array<string,mixed> $counts
         * @return array<string,int>
         */
        private static function normalize_question_import_created_item_diagnostic_counts(array $counts): array
        {
            return [
                'preserved' => max(0, (int) ($counts['preserved'] ?? 0)),
                'fallback' => max(0, (int) ($counts['fallback'] ?? 0)),
                'unsupported' => max(0, (int) ($counts['unsupported'] ?? 0)),
            ];
        }

        public static function remove_question_import_created_question_ids_for_current_user(string $token, array $question_ids): ?array
        {
            $state = self::get_question_import_state_for_current_user($token);
            if (!is_array($state)) {
                return null;
            }

            $remaining_ids = self::normalize_question_import_created_question_ids(
                isset($state['created_question_ids']) && is_array($state['created_question_ids'])
                    ? $state['created_question_ids']
                    : []
            );
            $remaining_items = self::normalize_question_import_created_question_items(
                isset($state['created_question_items']) && is_array($state['created_question_items'])
                    ? $state['created_question_items']
                    : []
            );
            $remove_ids = self::normalize_question_import_created_question_ids($question_ids);
            if (empty($remove_ids)) {
                return $state;
            }

            $remove_lookup = array_fill_keys($remove_ids, true);
            $remaining_ids = array_values(array_filter($remaining_ids, static function (int $question_id) use ($remove_lookup): bool {
                return !isset($remove_lookup[$question_id]);
            }));
            $remaining_items = array_values(array_filter($remaining_items, static function (array $item) use ($remove_lookup): bool {
                $question_id = (int) ($item['question_id'] ?? 0);
                return $question_id > 0 && !isset($remove_lookup[$question_id]);
            }));

            $state['created_question_ids'] = $remaining_ids;
            $state['created_question_items'] = $remaining_items;
            $state['created'] = count($remaining_ids);
            $state_saved = set_transient(self::get_question_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                return null;
            }

            return $state;
        }

        public static function normalize_question_import_failure_entry($entry): ?array
        {
            if (is_string($entry)) {
                $message = trim($entry);
                if ($message === '') {
                    return null;
                }

                return [
                    'block_number' => 0,
                    'question_type' => '',
                    'question_preview' => '',
                    'message' => $message,
                    'formatted' => $message,
                ];
            }

            if (!is_array($entry)) {
                return null;
            }

            $block_number = isset($entry['block_number']) ? (int) $entry['block_number'] : 0;
            $question_type = self::map_import_question_type((string) ($entry['question_type'] ?? ''));
            $question_preview = wp_strip_all_tags((string) ($entry['question_preview'] ?? ''));
            $question_preview = preg_replace('/\s+/u', ' ', trim($question_preview));
            $question_preview = is_string($question_preview) ? trim($question_preview) : '';
            if ($question_preview !== '') {
                if (function_exists('mb_substr')) {
                    $question_preview = mb_substr($question_preview, 0, 90, 'UTF-8');
                } else {
                    $question_preview = substr($question_preview, 0, 90);
                }
            }

            $message = trim((string) ($entry['message'] ?? ''));
            if ($message === '') {
                return null;
            }

            $normalized = [
                'block_number' => max(0, $block_number),
                'question_type' => $question_type,
                'question_preview' => $question_preview,
                'message' => $message,
            ];
            $normalized['formatted'] = self::format_question_import_failure_entry($normalized);

            return $normalized;
        }

        public static function normalize_question_import_failure_entries(array $entries): array
        {
            $normalized = [];
            foreach ($entries as $entry) {
                $entry = self::normalize_question_import_failure_entry($entry);
                if (!is_array($entry)) {
                    continue;
                }
                $normalized[] = $entry;
            }

            return $normalized;
        }

        public static function format_question_import_failure_entry(array $entry): string
        {
            $parts = [];

            $block_number = isset($entry['block_number']) ? (int) $entry['block_number'] : 0;
            if ($block_number > 0) {
                $parts[] = 'Blok DOCX #' . $block_number;
            }

            $question_preview = trim((string) ($entry['question_preview'] ?? ''));
            if ($question_preview !== '') {
                $parts[] = '"' . $question_preview . '"';
            }

            $prefix = empty($parts) ? 'Import soal gagal' : implode(' ', $parts);

            return $prefix . ': ' . trim((string) ($entry['message'] ?? ''));
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
                    'page' => CBT_Admin_Questions_Helper::normalize_question_page_slug($return_page),
                    'cbt_err' => $message,
                ],
                admin_url('admin.php')
            ));
            exit;
        }

        private static function sanitize_word_template_question_count(): int
        {
            $raw_count = isset($_GET['question_count'])
                ? (int) wp_unslash((string) $_GET['question_count'])
                : 10;

            $normalized_count = (int) floor($raw_count / 5) * 5;
            if ($normalized_count < 5) {
                $normalized_count = 5;
            }
            if ($normalized_count > 100) {
                $normalized_count = 100;
            }

            return $normalized_count;
        }

        private static function sanitize_word_template_config(string $template_type): array
        {
            $defaults = self::default_word_template_config();
            $raw_config = [];
            foreach ($defaults as $key => $default_value) {
                $raw_config[$key] = isset($_GET[$key])
                    ? (int) wp_unslash((string) $_GET[$key])
                    : (int) $default_value;
            }

            return self::normalize_word_template_config($template_type, $raw_config);
        }

        private static function default_word_template_config(): array
        {
            return [
                'option_count' => 5,
                'input_count' => 3,
                'statement_count' => 5,
                'item_count' => 4,
                'pair_count' => 3,
                'dropdown_count' => 2,
                'dropdown_option_count' => 3,
                'category_count' => 2,
                'categorization_item_count' => 3,
                'table_rows' => 2,
                'table_cols' => 2,
            ];
        }

        private static function normalize_word_template_config(string $template_type, array $config): array
        {
            $defaults = self::default_word_template_config();
            $value = static function (string $key) use ($config, $defaults): int {
                return (int) ($config[$key] ?? $defaults[$key] ?? 0);
            };

            $normalized = $defaults;
            $normalized['option_count'] = self::clamp_int($value('option_count'), 3, $template_type === 'multiple_answer' ? 12 : 5);
            $normalized['input_count'] = self::clamp_int($value('input_count'), 1, 8);
            $normalized['statement_count'] = self::clamp_int($value('statement_count'), 2, 10);
            $normalized['item_count'] = self::clamp_int($value('item_count'), 2, 12);
            $normalized['pair_count'] = self::clamp_int($value('pair_count'), 2, 12);
            $normalized['dropdown_count'] = self::clamp_int($value('dropdown_count'), 1, 8);
            $normalized['dropdown_option_count'] = self::clamp_int($value('dropdown_option_count'), 2, 6);
            $normalized['category_count'] = self::clamp_int($value('category_count'), 2, 8);
            $normalized['categorization_item_count'] = self::clamp_int($value('categorization_item_count'), 2, 24);
            $normalized['table_rows'] = self::clamp_int($value('table_rows'), 2, 8);
            $normalized['table_cols'] = self::clamp_int($value('table_cols'), 2, 6);

            return $normalized;
        }

        private static function clamp_int(int $value, int $min, int $max): int
        {
            return max($min, min($max, $value));
        }

        private static function build_word_template_lines(string $template_type, int $question_count, $template_config = []): array
        {
            $question_count = max(5, min(100, $question_count));
            if (is_int($template_config)) {
                $template_config = ['option_count' => $template_config];
            }
            if (!is_array($template_config)) {
                $template_config = [];
            }
            $template_config = self::normalize_word_template_config($template_type, $template_config);
            $option_count = (int) ($template_config['option_count'] ?? 5);
            $header_lines = [
                'CBT_TEMPLATE: ' . self::DOCX_TEMPLATE_MARKER_VALUE,
                'CATATAN_VALIDATOR: Jangan hapus marker CBT_TEMPLATE dan field JENIS_SOAL pada tiap blok.',
                '',
            ];
            $blocks = [];

            if ($template_type === 'multiple_answer') {
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Multiple Answer (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, PILIHAN_1..PILIHAN_N sesuai jumlah pilihan, JAWABAN.',
                    'JAWABAN diisi nomor pilihan yang dibuat (1..N) dan boleh lebih dari satu, contoh 2,4.',
                    'Isi pilihan tidak boleh duplikat.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL. Kontennya akan ikut masuk ke soal.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah pilihan per soal: ' . $option_count . ' opsi.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $block = [
                        'JENIS_SOAL: multiple_answer',
                        'SOAL: [MA ' . $idx . '] Pilih semua pernyataan yang benar.',
                    ];
                    for ($opt_idx = 1; $opt_idx <= $option_count; $opt_idx++) {
                        $alpha = chr(ord('A') + $opt_idx - 1);
                        $block[] = 'PILIHAN_' . $opt_idx . ': Pernyataan ' . $alpha;
                    }
                    $answer_indices = array_values(array_filter([1, 3, 5], static function (int $answer_index) use ($option_count): bool {
                        return $answer_index <= $option_count;
                    }));
                    if (empty($answer_indices)) {
                        $answer_indices = [1];
                    }
                    $block[] = 'JAWABAN: ' . implode(',', $answer_indices);
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'true_false') {
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import True/False (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, JAWABAN.',
                    'JAWABAN diisi TRUE atau FALSE.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL. Kontennya akan ikut masuk ke soal.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $answer = ($idx % 2 === 0) ? 'false' : 'true';
                    $blocks[] = [
                        'JENIS_SOAL: true_false',
                        'SOAL: [TF ' . $idx . '] Tulis pernyataan benar/salah di sini.',
                        'JAWABAN: ' . $answer,
                        'POIN: 1',
                        'PEMBAHASAN: Tulis pembahasan opsional di sini.',
                    ];
                }
            } elseif ($template_type === 'true_false_matrix') {
                $statement_count = (int) ($template_config['statement_count'] ?? 5);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import True/False Matrix (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, PERNYATAAN_1..PERNYATAAN_N, KUNCI_1..KUNCI_N.',
                    'Isi PERNYATAAN dan KUNCI sesuai jumlah pernyataan yang dipilih (2-10), tanpa nomor loncat.',
                    'Isi KUNCI_n dengan TRUE/FALSE atau BENAR/SALAH.',
                    'Pernyataan tidak boleh duplikat.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL. Kontennya akan ikut masuk ke soal.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah pernyataan per soal: ' . $statement_count . ' pernyataan.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $block = [
                        'JENIS_SOAL: true_false_matrix',
                        'SOAL: [TFM ' . $idx . '] Tentukan Benar/Salah untuk setiap pernyataan berikut.',
                    ];
                    for ($statement_idx = 1; $statement_idx <= $statement_count; $statement_idx++) {
                        $alpha = chr(ord('A') + $statement_idx - 1);
                        $block[] = 'PERNYATAAN_' . $statement_idx . ': Pernyataan ' . $alpha;
                        $block[] = 'KUNCI_' . $statement_idx . ': ' . ($statement_idx % 2 === 0 ? 'false' : 'true');
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'short_answer') {
                $input_count = (int) ($template_config['input_count'] ?? 3);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Short Answer (format tabel, maks 8 jawaban valid).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, minimal 1 jawaban.',
                    'Tandai titik isian di SOAL dengan [INPUT_1] sampai [INPUT_N] sesuai jumlah input, tanpa placeholder duplikat.',
                    'Jumlah placeholder input harus sama dengan jumlah jawaban valid.',
                    'Format lama seperti [INPUT A] / [INPUT 1] tetap didukung.',
                    'Isi jawaban bisa pakai JAWABAN_A sampai JAWABAN_H, dan key-nya harus cocok dengan placeholder input.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL. Kontennya akan ikut masuk ke soal.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah input per soal: ' . $input_count . ' input.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $placeholders = [];
                    for ($input_idx = 1; $input_idx <= $input_count; $input_idx++) {
                        $placeholders[] = '[INPUT_' . $input_idx . ']';
                    }
                    $block = [
                        'JENIS_SOAL: short_answer',
                        'SOAL: [SA ' . $idx . '] Lengkapi: ' . implode(', ', $placeholders) . '.',
                    ];
                    for ($input_idx = 1; $input_idx <= $input_count; $input_idx++) {
                        $answer_key = chr(64 + $input_idx);
                        $block[] = 'JAWABAN_' . $answer_key . ': jawaban-' . $input_idx;
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'essay') {
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Essay (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, JAWABAN.',
                    'JAWABAN diisi acuan jawaban/rubrik.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL. Kontennya akan ikut masuk ke soal.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $blocks[] = [
                        'JENIS_SOAL: essay',
                        'SOAL: [ESSAY ' . $idx . '] Tulis pertanyaan essay di sini.',
                        'JAWABAN: Tulis acuan jawaban/rubrik penilaian.',
                        'POIN: 1',
                        'PEMBAHASAN: Tulis pembahasan opsional di sini.',
                    ];
                }
            } elseif ($template_type === 'ordering') {
                $item_count = (int) ($template_config['item_count'] ?? 4);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Ordering / Sequencing (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, ITEM_1..ITEM_N sesuai jumlah item.',
                    'ITEM_1 sampai ITEM_N diisi sesuai urutan benar. Sistem akan mengacak item saat ujian.',
                    'Item tidak boleh duplikat.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL atau ITEM_n. Kontennya akan ikut masuk.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah item per soal: ' . $item_count . ' item.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $block = [
                        'JENIS_SOAL: ordering',
                        'SOAL: [ORD ' . $idx . '] Susun langkah berikut sesuai urutan yang benar.',
                    ];
                    for ($item_idx = 1; $item_idx <= $item_count; $item_idx++) {
                        $block[] = 'ITEM_' . $item_idx . ': Langkah ke-' . $item_idx;
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'matching') {
                $pair_count = (int) ($template_config['pair_count'] ?? 3);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Matching (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, KIRI_1..KIRI_N, KANAN_1..KANAN_N sesuai jumlah pasangan.',
                    'KIRI_n adalah prompt kiri; KANAN_n adalah pasangan benar untuk baris yang sama.',
                    'Minimal 2 dan maksimal 12 pasangan. Teks kiri dan kanan tidak boleh duplikat.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL atau KIRI_n. Kontennya akan ikut masuk.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah pasangan per soal: ' . $pair_count . ' pasangan.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $block = [
                        'JENIS_SOAL: matching',
                        'SOAL: [MATCH ' . $idx . '] Pasangkan istilah di kiri dengan pilihan yang tepat.',
                    ];
                    for ($pair_idx = 1; $pair_idx <= $pair_count; $pair_idx++) {
                        $left_label = [
                            1 => 'Istilah pertama',
                            2 => 'Istilah kedua',
                            3 => 'Istilah ketiga',
                        ][$pair_idx] ?? ('Istilah ke-' . $pair_idx);
                        $right_label = [
                            1 => 'Pasangan pertama',
                            2 => 'Pasangan kedua',
                            3 => 'Pasangan ketiga',
                        ][$pair_idx] ?? ('Pasangan ke-' . $pair_idx);
                        $block[] = 'KIRI_' . $pair_idx . ': ' . $left_label;
                        $block[] = 'KANAN_' . $pair_idx . ': ' . $right_label;
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'cloze_dropdown') {
                $dropdown_count = (int) ($template_config['dropdown_count'] ?? 2);
                $dropdown_option_count = (int) ($template_config['dropdown_option_count'] ?? 3);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Cloze Dropdown (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL dengan placeholder [DROPDOWN_1] sampai [DROPDOWN_N] sesuai jumlah dropdown.',
                    'Isi DROPDOWN_n_OPSI_1..M sesuai jumlah opsi per dropdown untuk tiap placeholder yang dipakai.',
                    'Isi DROPDOWN_n_JAWABAN dengan nomor opsi benar. Tiap dropdown minimal 2 opsi dan tepat 1 kunci.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah dropdown per soal: ' . $dropdown_count . ' dropdown.',
                    'Jumlah opsi per dropdown: ' . $dropdown_option_count . ' opsi.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $placeholders = [];
                    for ($dropdown_idx = 1; $dropdown_idx <= $dropdown_count; $dropdown_idx++) {
                        $placeholders[] = '[DROPDOWN_' . $dropdown_idx . ']';
                    }
                    $block = [
                        'JENIS_SOAL: cloze_dropdown',
                        'SOAL: [CLOZE ' . $idx . '] Lengkapi bagian berikut: ' . implode(', ', $placeholders) . '.',
                    ];
                    for ($dropdown_idx = 1; $dropdown_idx <= $dropdown_count; $dropdown_idx++) {
                        for ($option_idx = 1; $option_idx <= $dropdown_option_count; $option_idx++) {
                            $alpha = chr(ord('A') + $option_idx - 1);
                            $block[] = 'DROPDOWN_' . $dropdown_idx . '_OPSI_' . $option_idx . ': Opsi ' . $dropdown_idx . $alpha;
                        }
                        $block[] = 'DROPDOWN_' . $dropdown_idx . '_JAWABAN: ' . min(2, $dropdown_option_count);
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'categorization') {
                $category_count = (int) ($template_config['category_count'] ?? 2);
                $categorization_item_count = (int) ($template_config['categorization_item_count'] ?? 3);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Categorization (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, KATEGORI_1..N, ITEM_1..M, KUNCI_1..M sesuai jumlah kategori/item.',
                    'KUNCI_n diisi nomor kategori atau teks kategori.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah kategori per soal: ' . $category_count . ' kategori.',
                    'Jumlah item per soal: ' . $categorization_item_count . ' item.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $block = [
                        'JENIS_SOAL: categorization',
                        'SOAL: [CAT ' . $idx . '] Kelompokkan item berikut ke kategori yang tepat.',
                    ];
                    for ($category_idx = 1; $category_idx <= $category_count; $category_idx++) {
                        $block[] = 'KATEGORI_' . $category_idx . ': Kategori ' . $category_idx;
                    }
                    for ($item_idx = 1; $item_idx <= $categorization_item_count; $item_idx++) {
                        $block[] = 'ITEM_' . $item_idx . ': Item ' . $item_idx;
                        $block[] = 'KUNCI_' . $item_idx . ': ' . ((($item_idx - 1) % $category_count) + 1);
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } elseif ($template_type === 'table_completion') {
                $table_rows = (int) ($template_config['table_rows'] ?? 2);
                $table_cols = (int) ($template_config['table_cols'] ?? 2);
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Table Completion (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, TABLE_ROWS, TABLE_COLS, dan CELL_*_TYPE sesuai ukuran tabel.',
                    'CELL_*_TYPE dapat static, text, atau dropdown. Text memakai CELL_*_JAWABAN. Dropdown memakai CELL_*_OPSI_1..6 dan CELL_*_JAWABAN.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Ukuran tabel per soal: ' . $table_rows . 'x' . $table_cols . '.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $block = [
                        'JENIS_SOAL: table_completion',
                        'SOAL: [TABLE ' . $idx . '] Lengkapi tabel berikut.',
                        'TABLE_ROWS: ' . $table_rows,
                        'TABLE_COLS: ' . $table_cols,
                    ];
                    $answer_cells = 0;
                    for ($row = 1; $row <= $table_rows; $row++) {
                        for ($col = 1; $col <= $table_cols; $col++) {
                            $cell_key = chr(64 + $col) . (string) $row;
                            $is_answer_cell = $row > 1 && $col > 1 && $answer_cells < 24;
                            if ($is_answer_cell) {
                                $answer_cells++;
                                if ($answer_cells % 3 === 0) {
                                    $block[] = 'CELL_' . $cell_key . '_TYPE: dropdown';
                                    $block[] = 'CELL_' . $cell_key . '_TEXT: Pilih nilai ' . $cell_key;
                                    $block[] = 'CELL_' . $cell_key . '_OPSI_1: Opsi ' . $cell_key . 'A';
                                    $block[] = 'CELL_' . $cell_key . '_OPSI_2: Opsi ' . $cell_key . 'B';
                                    $block[] = 'CELL_' . $cell_key . '_OPSI_3: Opsi ' . $cell_key . 'C';
                                    $block[] = 'CELL_' . $cell_key . '_JAWABAN: 2';
                                    continue;
                                }

                                $block[] = 'CELL_' . $cell_key . '_TYPE: text';
                                $block[] = 'CELL_' . $cell_key . '_JAWABAN: Jawaban ' . $cell_key;
                                continue;
                            }

                            $block[] = 'CELL_' . $cell_key . '_TYPE: static';
                            if ($row === 1 && $col === 1) {
                                $block[] = 'CELL_' . $cell_key . '_TEXT: Label';
                            } elseif ($row === 1) {
                                $block[] = 'CELL_' . $cell_key . '_TEXT: Kolom ' . chr(64 + $col);
                            } elseif ($col === 1) {
                                $block[] = 'CELL_' . $cell_key . '_TEXT: Baris ' . $row;
                            } else {
                                $block[] = 'CELL_' . $cell_key . '_TEXT: Sel ' . $cell_key;
                            }
                        }
                    }
                    if ($answer_cells <= 0) {
                        $block[] = 'CELL_B2_TYPE: text';
                        $block[] = 'CELL_B2_JAWABAN: Jawaban B2';
                    }
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
                }
            } else {
                $header_lines = array_merge($header_lines, [
                    'Template Word ini untuk import Multiple Choice (format tabel).',
                    'Setiap blok soal dipisahkan oleh ---',
                    'Field wajib: JENIS_SOAL, SOAL, PILIHAN_1..PILIHAN_N sesuai jumlah pilihan, JAWABAN.',
                    'JAWABAN diisi satu nomor pilihan yang dibuat (1..N).',
                    'Untuk multiple_choice: hanya satu jawaban, contoh 2.',
                    'Isi pilihan tidak boleh duplikat.',
                    'POIN opsional, default 1.',
                    'PEMBAHASAN opsional. Bisa diisi teks, tabel, atau gambar; gambar/tabel boleh diletakkan setelah field PEMBAHASAN.',
                    'Boleh tempel gambar atau tabel langsung di bawah baris SOAL. Kontennya akan ikut masuk ke soal.',
                    'Jumlah blok template: ' . $question_count . ' soal.',
                    'Jumlah pilihan per soal: ' . $option_count . ' opsi.',
                    '',
                ]);

                for ($idx = 1; $idx <= $question_count; $idx++) {
                    $answer = (string) ((($idx - 1) % $option_count) + 1);
                    $block = [
                        'JENIS_SOAL: multiple_choice',
                        'SOAL: [MC ' . $idx . '] Tulis pertanyaan pilihan ganda di sini.',
                    ];
                    for ($opt_idx = 1; $opt_idx <= $option_count; $opt_idx++) {
                        $alpha = chr(ord('A') + $opt_idx - 1);
                        $block[] = 'PILIHAN_' . $opt_idx . ': Opsi ' . $alpha;
                    }
                    $block[] = 'JAWABAN: ' . $answer;
                    $block[] = 'POIN: 1';
                    $block[] = 'PEMBAHASAN: Tulis pembahasan opsional di sini.';
                    $blocks[] = $block;
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
                        strpos($next, self::DOCX_HTML_MARKER_PREFIX) !== 0 &&
                        strpos($next, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) !== 0 &&
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

        private static function parse_docx_multiple_choice_block(array $block): ?array
        {
            $question_parts = [];
            $explanation_parts = [];
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
            $answer_parts = [];
            $short_answer_map = [];
            $ordering_item_map = [];
            $matching_left_map = [];
            $matching_right_map = [];
            $cloze_dropdown_option_map = [];
            $cloze_dropdown_correct_map = [];
            $categorization_category_map = [];
            $categorization_item_map = [];
            $categorization_key_map = [];
            $table_cell_map = [];
            $tf_matrix_statement_map = [];
            $tf_matrix_answer_map = [];
            $diagnostic_entries = [];
            $active_context = 'question';

            foreach ($block as $raw_line) {
                $line = trim((string) $raw_line);
                if ($line === '') {
                    continue;
                }

                if (strpos($line, self::DOCX_HTML_MARKER_PREFIX) === 0) {
                    $html_fragment = self::decode_docx_html_marker($line);
                    if ($html_fragment !== '') {
                        self::append_docx_html_fragment_to_active_context(
                            $html_fragment,
                            $active_context,
                            $question_parts,
                            $explanation_parts,
                            $answer_parts,
                            $options_map,
                            $tf_matrix_statement_map,
                            $ordering_item_map,
                            $matching_left_map,
                            $categorization_item_map,
                            $table_cell_map
                        );
                    }
                    continue;
                }

                if (strpos($line, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) === 0) {
                    $diagnostic_entry = self::decode_docx_diagnostic_marker($line);
                    if (is_array($diagnostic_entry)) {
                        $field = trim((string) ($diagnostic_entry['field'] ?? ''));
                        if ($field === '') {
                            $diagnostic_entry['field'] = self::map_docx_active_context_to_diagnostic_field($active_context);
                        }
                        $diagnostic_entries[] = $diagnostic_entry;
                    }
                    continue;
                }

                if (strpos($line, '__IMG__:') === 0) {
                    $img_url = trim(substr($line, 8));
                    if ($img_url !== '') {
                        $img_html = '<p><img src="' . esc_url($img_url) . '" alt="" /></p>';
                        self::append_docx_html_fragment_to_active_context(
                            $img_html,
                            $active_context,
                            $question_parts,
                            $explanation_parts,
                            $answer_parts,
                            $options_map,
                            $tf_matrix_statement_map,
                            $ordering_item_map,
                            $matching_left_map,
                            $categorization_item_map,
                            $table_cell_map
                        );
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
                        if (in_array($mapped, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'essay', 'short_answer', 'ordering', 'matching', 'cloze_dropdown', 'categorization', 'table_completion'], true)) {
                            $forced_question_type = $mapped;
                        }
                        continue;
                    }

                    if (in_array($key, ['jawaban', 'answer', 'correct_answer', 'jawaban_ke', 'answer_option', 'correct_text', 'rubrik', 'rubric', 'rubric_text'], true)) {
                        $answer_text = $value;
                        if ($value !== '') {
                            $answer_parts[] = $value;
                        }
                        $answer_indices = self::normalize_docx_answer_indices($value);
                        $active_context = 'answer';
                        continue;
                    }

                    if (in_array($key, ['pembahasan', 'explanation'], true)) {
                        if ($value !== '') {
                            $explanation_parts[] = $value;
                        }
                        $active_context = 'explanation';
                        continue;
                    }

                    if ($forced_question_type === 'ordering' && preg_match('/^(item|urutan|sequence|ordering)_?([1-9]|1[0-2])$/', $key, $matches)) {
                        $item_idx = (int) $matches[2];
                        if ($item_idx >= 1 && $item_idx <= $max_option_index) {
                            $ordering_item_map[$item_idx] = $value;
                        }
                        $active_context = ['ordering_item', $item_idx];
                        continue;
                    }

                    if ($forced_question_type === 'matching' && preg_match('/^(kiri|left|prompt)_?([1-9]|1[0-2])$/', $key, $matches)) {
                        $item_idx = (int) $matches[2];
                        if ($item_idx >= 1 && $item_idx <= $max_option_index) {
                            $matching_left_map[$item_idx] = $value;
                        }
                        $active_context = ['matching_left', $item_idx];
                        continue;
                    }

                    if ($forced_question_type === 'matching' && preg_match('/^(kanan|right|pasangan|match)_?([1-9]|1[0-2])$/', $key, $matches)) {
                        $item_idx = (int) $matches[2];
                        if ($item_idx >= 1 && $item_idx <= $max_option_index) {
                            $matching_right_map[$item_idx] = $value;
                        }
                        $active_context = ['matching_right', $item_idx];
                        continue;
                    }

                    if (
                        $forced_question_type === 'cloze_dropdown' &&
                        preg_match('/^dropdown_?([1-8])_?(opsi|option|pilihan)_?([1-6])$/', $key, $matches)
                    ) {
                        $blank_idx = (int) $matches[1];
                        $option_idx = (int) $matches[3];
                        if ($blank_idx >= 1 && $blank_idx <= 8 && $option_idx >= 1 && $option_idx <= 6) {
                            if (!isset($cloze_dropdown_option_map[$blank_idx])) {
                                $cloze_dropdown_option_map[$blank_idx] = [];
                            }
                            $cloze_dropdown_option_map[$blank_idx][$option_idx] = $value;
                        }
                        $active_context = ['cloze_option', $blank_idx, $option_idx];
                        continue;
                    }

                    if (
                        $forced_question_type === 'cloze_dropdown' &&
                        preg_match('/^dropdown_?([1-8])_?(jawaban|answer|correct|kunci)$/', $key, $matches)
                    ) {
                        $blank_idx = (int) $matches[1];
                        if ($blank_idx >= 1 && $blank_idx <= 8) {
                            $cloze_dropdown_correct_map[$blank_idx] = $value;
                        }
                        $active_context = ['cloze_answer', $blank_idx];
                        continue;
                    }

                    if ($forced_question_type === 'categorization' && preg_match('/^(kategori|category)_?([1-8])$/', $key, $matches)) {
                        $category_idx = (int) $matches[2];
                        if ($category_idx >= 1 && $category_idx <= 8) {
                            $categorization_category_map[$category_idx] = $value;
                        }
                        $active_context = ['categorization_category', $category_idx];
                        continue;
                    }

                    if ($forced_question_type === 'categorization' && preg_match('/^item_?([1-9]|1[0-9]|2[0-4])$/', $key, $matches)) {
                        $item_idx = (int) $matches[1];
                        if ($item_idx >= 1 && $item_idx <= 24) {
                            $categorization_item_map[$item_idx] = $value;
                        }
                        $active_context = ['categorization_item', $item_idx];
                        continue;
                    }

                    if ($forced_question_type === 'categorization' && preg_match('/^(kunci|answer|correct)_?([1-9]|1[0-9]|2[0-4])$/', $key, $matches)) {
                        $item_idx = (int) $matches[2];
                        if ($item_idx >= 1 && $item_idx <= 24) {
                            $categorization_key_map[$item_idx] = $value;
                        }
                        $active_context = ['categorization_key', $item_idx];
                        continue;
                    }

                    if ($forced_question_type === 'table_completion' && preg_match('/^table_?(rows|cols|columns)$/', $key)) {
                        $table_cell_map[$key] = $value;
                        $active_context = 'table_completion';
                        continue;
                    }

                    if ($forced_question_type === 'table_completion' && preg_match('/^cell_?([a-f][1-8])_?(type|text|jawaban|answer|opsi_[1-6]|option_[1-6])$/', $key, $matches)) {
                        $cell_key = strtoupper((string) $matches[1]);
                        $field_key = strtolower((string) $matches[2]);
                        if (!isset($table_cell_map[$cell_key])) {
                            $table_cell_map[$cell_key] = [];
                        }
                        $table_cell_map[$cell_key][$field_key] = $value;
                        $active_context = ['table_cell', $cell_key, $field_key];
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
                            $normalized_tf_answer = self::parse_docx_true_false_value_strict($value);
                            if ($normalized_tf_answer === null) {
                                return null;
                            }
                            $tf_matrix_answer_map[$statement_idx] = $normalized_tf_answer;
                        }
                        $active_context = ['matrix_answer', $statement_idx];
                        continue;
                    }

                    if (preg_match('/^(jawaban|answer|correct)_?([1-9]|10)$/', $key, $matches)) {
                        $answer_idx = (int) $matches[2];
                        if ($forced_question_type === 'true_false_matrix' || !empty($tf_matrix_statement_map) || $answer_idx >= 9) {
                            if ($answer_idx >= 1 && $answer_idx <= 10) {
                                $normalized_tf_answer = self::parse_docx_true_false_value_strict($value);
                                if ($normalized_tf_answer === null) {
                                    return null;
                                }
                                $tf_matrix_answer_map[$answer_idx] = $normalized_tf_answer;
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

                if (is_array($active_context) && ($active_context[0] ?? '') === 'ordering_item') {
                    $item_idx = (int) ($active_context[1] ?? 0);
                    if ($item_idx >= 1 && $item_idx <= $max_option_index) {
                        $current = trim((string) ($ordering_item_map[$item_idx] ?? ''));
                        $ordering_item_map[$item_idx] = ($current === '')
                            ? $line
                            : ($current . '<br />' . $line);
                        continue;
                    }
                }

                if (is_array($active_context) && ($active_context[0] ?? '') === 'matching_left') {
                    $item_idx = (int) ($active_context[1] ?? 0);
                    if ($item_idx >= 1 && $item_idx <= $max_option_index) {
                        $current = trim((string) ($matching_left_map[$item_idx] ?? ''));
                        $matching_left_map[$item_idx] = ($current === '')
                            ? $line
                            : ($current . '<br />' . $line);
                        continue;
                    }
                }

                if (is_array($active_context) && ($active_context[0] ?? '') === 'matching_right') {
                    $item_idx = (int) ($active_context[1] ?? 0);
                    if ($item_idx >= 1 && $item_idx <= $max_option_index) {
                        $current = trim((string) ($matching_right_map[$item_idx] ?? ''));
                        $matching_right_map[$item_idx] = ($current === '')
                            ? $line
                            : ($current . ' ' . $line);
                        continue;
                    }
                }

                if (is_array($active_context) && ($active_context[0] ?? '') === 'categorization_item') {
                    $item_idx = (int) ($active_context[1] ?? 0);
                    if ($item_idx >= 1 && $item_idx <= 24) {
                        $current = trim((string) ($categorization_item_map[$item_idx] ?? ''));
                        $categorization_item_map[$item_idx] = ($current === '')
                            ? $line
                            : ($current . '<br />' . $line);
                        continue;
                    }
                }

                if (is_array($active_context) && ($active_context[0] ?? '') === 'table_cell') {
                    $cell_key = strtoupper((string) ($active_context[1] ?? ''));
                    $field_key = strtolower((string) ($active_context[2] ?? ''));
                    if ($cell_key !== '' && $field_key !== '' && isset($table_cell_map[$cell_key]) && is_array($table_cell_map[$cell_key])) {
                        $current = trim((string) ($table_cell_map[$cell_key][$field_key] ?? ''));
                        $separator = preg_match('/^(opsi|option)_/', $field_key) === 1 ? ' ' : '<br />';
                        $table_cell_map[$cell_key][$field_key] = ($current === '')
                            ? $line
                            : ($current . $separator . $line);
                        continue;
                    }
                }

                if ($active_context === 'answer') {
                    $current_answer = trim((string) $answer_text);
                    $answer_text = ($current_answer === '')
                        ? $line
                        : ($current_answer . "\n" . $line);
                    $answer_parts[] = $line;
                    continue;
                }

                if ($active_context === 'explanation') {
                    $explanation_parts[] = $line;
                    continue;
                }

                // Any free-text line in the block is appended as question body.
                $question_parts[] = $line;
                $active_context = 'question';
            }

            $question_text = self::build_docx_question_text($question_parts);
            $explanation_text = CBT_Admin_Questions_Helper::normalize_optional_rich_text(self::build_docx_question_text($explanation_parts));
            if ($question_text === '') {
                return null;
            }
            if ($forced_question_type === '') {
                return null;
            }

            if ($forced_question_type === 'true_false') {
                $tf_raw = strtolower(trim($answer_text));
                if ($tf_raw === '') {
                    return null;
                }

                if (in_array($tf_raw, ['false', '0', 'f', 'no', 'tidak', 'salah', 's'], true)) {
                    $tf_value = 'false';
                } elseif (in_array($tf_raw, ['true', '1', 't', 'yes', 'ya', 'y', 'benar', 'b'], true)) {
                    $tf_value = 'true';
                } else {
                    return null;
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
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'essay') {
                $essay_rubric = CBT_Admin_Questions_Helper::normalize_optional_rich_text(self::build_docx_question_text($answer_parts));
                if ($essay_rubric === null) {
                    $essay_rubric = trim($answer_text);
                }
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
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'short_answer') {
                ksort($short_answer_map);
                $short_answer_input_keys = CBT_Admin_Questions_Helper::resolve_short_answer_input_keys($question_text);
                $short_answer_values = [];
                if (empty($short_answer_map)) {
                    return null;
                }

                $provided_input_keys = [];
                foreach (array_keys($short_answer_map) as $map_key) {
                    $map_key = (int) $map_key;
                    if ($map_key < 1 || $map_key > 8) {
                        return null;
                    }
                    $provided_input_keys[] = chr(64 + $map_key);
                }
                sort($provided_input_keys);

                foreach ($short_answer_input_keys as $input_key) {
                    $input_index = ord($input_key) - 64;
                    $mapped_value = trim((string) ($short_answer_map[$input_index] ?? ''));
                    if ($mapped_value === '') {
                        return null;
                    }
                    $short_answer_values[] = $mapped_value;
                }
                $short_answer_values = CBT_Admin_Questions_Helper::normalize_short_answer_values(wp_json_encode($short_answer_values));
                if (empty($short_answer_values)) {
                    return null;
                }
                $short_answer_validation_error = CBT_Admin_Questions_Helper::validate_short_answer_definition(
                    $question_text,
                    $short_answer_values,
                    ['provided_keys' => $provided_input_keys]
                );
                if ($short_answer_validation_error !== '') {
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
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'ordering') {
                $source_items = !empty(array_filter($ordering_item_map, static fn($value): bool => trim((string) $value) !== ''))
                    ? $ordering_item_map
                    : $options_map;
                $ordering_items = [];
                foreach (range(1, $max_option_index) as $idx) {
                    $val = trim((string) ($source_items[$idx] ?? ''));
                    if ($val !== '') {
                        $ordering_items[$idx] = $val;
                    }
                }

                if (count($ordering_items) < 2) {
                    return null;
                }

                $filled_indices = array_keys($ordering_items);
                sort($filled_indices);
                $max_idx = (int) max($filled_indices);
                for ($idx = 1; $idx <= $max_idx; $idx++) {
                    if (!isset($ordering_items[$idx])) {
                        return null;
                    }
                }

                $ordered_items = [];
                for ($idx = 1; $idx <= $max_idx; $idx++) {
                    $ordered_items[] = $ordering_items[$idx];
                }
                if (self::build_ordering_options_raw_from_import(implode('||', $ordered_items)) === '') {
                    return null;
                }

                $row = [
                    'question_type' => 'ordering',
                    'question_text' => $question_text,
                    'points' => (string) max(0, $points),
                    'options' => implode('||', $ordered_items),
                    'correct_answer' => '',
                    'correct_text' => '',
                ];
                if ($subject_code !== '') {
                    $row['subject_code'] = $subject_code;
                }
                if ($exam_title !== '') {
                    $row['exam_title'] = $exam_title;
                }
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'matching') {
                $matching_rows = [];
                foreach (range(1, $max_option_index) as $idx) {
                    $left = trim((string) ($matching_left_map[$idx] ?? ''));
                    $right = trim((string) ($matching_right_map[$idx] ?? ''));
                    if ($left === '' && $right === '') {
                        continue;
                    }
                    $matching_rows[] = [
                        'position' => count($matching_rows) + 1,
                        'item_key' => (string) (count($matching_rows) + 1),
                        'prompt_text' => $left,
                        'option_text' => $right,
                    ];
                }

                $matching_items = CBT_Admin_Questions_Helper::normalize_matching_items($matching_rows);
                if (CBT_Admin_Questions_Helper::validate_matching_items($matching_items) !== '') {
                    return null;
                }

                $row = [
                    'question_type' => 'matching',
                    'question_text' => $question_text,
                    'points' => (string) max(0, $points),
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_matching_payload($matching_items),
                    'matching_items' => $matching_items,
                ];
                if ($subject_code !== '') {
                    $row['subject_code'] = $subject_code;
                }
                if ($exam_title !== '') {
                    $row['exam_title'] = $exam_title;
                }
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'cloze_dropdown') {
                $cloze_rows = [];
                foreach (range(1, 8) as $blank_idx) {
                    $options_for_blank = [];
                    $correct_index = self::normalize_docx_option_index((string) ($cloze_dropdown_correct_map[$blank_idx] ?? ''));
                    foreach (range(1, 6) as $option_idx) {
                        $option_text = trim((string) ($cloze_dropdown_option_map[$blank_idx][$option_idx] ?? ''));
                        if ($option_text === '') {
                            continue;
                        }
                        $options_for_blank[] = [
                            'option_text' => $option_text,
                            'is_correct' => ($option_idx === $correct_index) ? 1 : 0,
                        ];
                    }
                    if (empty($options_for_blank) && !isset($cloze_dropdown_correct_map[$blank_idx])) {
                        continue;
                    }

                    $cloze_rows[] = [
                        'blank_key' => (string) $blank_idx,
                        'blank_position' => count($cloze_rows) + 1,
                        'options' => $options_for_blank,
                    ];
                }

                $cloze_blanks = CBT_Admin_Questions_Helper::normalize_cloze_dropdown_blanks($cloze_rows);
                if (CBT_Admin_Questions_Helper::validate_cloze_dropdown_definition($question_text, $cloze_blanks) !== '') {
                    return null;
                }

                $row = [
                    'question_type' => 'cloze_dropdown',
                    'question_text' => $question_text,
                    'points' => (string) max(0, $points),
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_cloze_dropdown_payload($cloze_blanks),
                    'cloze_blanks' => $cloze_blanks,
                ];
                if ($subject_code !== '') {
                    $row['subject_code'] = $subject_code;
                }
                if ($exam_title !== '') {
                    $row['exam_title'] = $exam_title;
                }
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'categorization') {
                $category_rows = [];
                foreach (range(1, 8) as $idx) {
                    $label = trim((string) ($categorization_category_map[$idx] ?? ''));
                    if ($label !== '') {
                        $category_rows[] = [
                            'category_index' => count($category_rows) + 1,
                            'option_text' => $label,
                        ];
                    }
                }
                $categories = CBT_Admin_Questions_Helper::normalize_categorization_categories($category_rows);
                $items = [];
                foreach (range(1, 24) as $idx) {
                    $text = trim((string) ($categorization_item_map[$idx] ?? ''));
                    $key_raw = trim((string) ($categorization_key_map[$idx] ?? ''));
                    if ($text === '' && $key_raw === '') {
                        continue;
                    }
                    $category_index = is_numeric($key_raw) ? (int) $key_raw : 0;
                    if ($category_index <= 0) {
                        foreach ($categories as $category_idx => $category) {
                            if (
                                CBT_Admin_Questions_Helper::normalize_short_answer_compare_value($key_raw) ===
                                CBT_Admin_Questions_Helper::normalize_short_answer_compare_value((string) ($category['option_text'] ?? ''))
                            ) {
                                $category_index = $category_idx + 1;
                                break;
                            }
                        }
                    }
                    $items[] = [
                        'position' => count($items) + 1,
                        'item_key' => (string) (count($items) + 1),
                        'item_text' => $text,
                        'correct_category_index' => $category_index,
                    ];
                }
                $categorization_items = CBT_Admin_Questions_Helper::normalize_categorization_items($items);
                if (CBT_Admin_Questions_Helper::validate_categorization_definition($categories, $categorization_items) !== '') {
                    return null;
                }
                $row = [
                    'question_type' => 'categorization',
                    'question_text' => $question_text,
                    'points' => (string) max(0, $points),
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_categorization_payload($categories, $categorization_items),
                    'categorization_categories' => $categories,
                    'categorization_items' => $categorization_items,
                ];
                if ($subject_code !== '') {
                    $row['subject_code'] = $subject_code;
                }
                if ($exam_title !== '') {
                    $row['exam_title'] = $exam_title;
                }
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'table_completion') {
                $row_count = (int) ($table_cell_map['table_rows'] ?? 2);
                $column_count = (int) ($table_cell_map['table_cols'] ?? ($table_cell_map['table_columns'] ?? 2));
                $cells = [];
                foreach (range(1, 8) as $r) {
                    foreach (range(1, 6) as $c) {
                        $cell_key = chr(64 + $c) . (string) $r;
                        $cell_config = isset($table_cell_map[$cell_key]) && is_array($table_cell_map[$cell_key])
                            ? $table_cell_map[$cell_key]
                            : [];
                        $type = sanitize_key((string) ($cell_config['type'] ?? 'static'));
                        if (!in_array($type, ['static', 'text', 'dropdown'], true)) {
                            $type = 'static';
                        }
                        $options = [];
                        $correct_raw = trim((string) ($cell_config['jawaban'] ?? ($cell_config['answer'] ?? '')));
                        $correct_index = self::normalize_docx_option_index($correct_raw);
                        foreach (range(1, 6) as $option_idx) {
                            $option_text = trim((string) ($cell_config['opsi_' . $option_idx] ?? ($cell_config['option_' . $option_idx] ?? '')));
                            if ($option_text === '') {
                                continue;
                            }
                            $options[] = [
                                'option_text' => $option_text,
                                'is_correct' => ($option_idx === $correct_index) ? 1 : 0,
                            ];
                        }
                        $cells[] = [
                            'cell_key' => $cell_key,
                            'row_position' => $r,
                            'column_position' => $c,
                            'cell_type' => $type,
                            'cell_text' => (string) ($cell_config['text'] ?? ''),
                            'correct_text' => $type === 'text' ? $correct_raw : '',
                            'options' => $options,
                        ];
                    }
                }
                $table_completion = CBT_Admin_Questions_Helper::normalize_table_completion_definition([
                    'row_count' => $row_count,
                    'column_count' => $column_count,
                    'cells' => $cells,
                ]);
                if (CBT_Admin_Questions_Helper::validate_table_completion_definition($table_completion) !== '') {
                    return null;
                }
                $row = [
                    'question_type' => 'table_completion',
                    'question_text' => $question_text,
                    'points' => (string) max(0, $points),
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_table_completion_payload($table_completion),
                    'table_completion' => $table_completion,
                ];
                if ($subject_code !== '') {
                    $row['subject_code'] = $subject_code;
                }
                if ($exam_title !== '') {
                    $row['exam_title'] = $exam_title;
                }
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            if ($forced_question_type === 'true_false_matrix' || !empty($tf_matrix_statement_map)) {
                ksort($tf_matrix_statement_map);
                ksort($tf_matrix_answer_map);

                if (!empty($tf_matrix_statement_map) || !empty($tf_matrix_answer_map)) {
                    $statement_keys = array_map('intval', array_keys($tf_matrix_statement_map));
                    $answer_keys = array_map('intval', array_keys($tf_matrix_answer_map));

                    if (
                        empty($statement_keys) ||
                        empty($answer_keys) ||
                        !self::is_contiguous_one_based_index_set($statement_keys) ||
                        !self::is_contiguous_one_based_index_set($answer_keys) ||
                        $statement_keys !== $answer_keys
                    ) {
                        return null;
                    }
                }

                $matrix_items = [];
                foreach ($tf_matrix_statement_map as $idx => $statement_text) {
                    $statement_text = trim((string) $statement_text);
                    if ($statement_text === '') {
                        continue;
                    }

                    if (!isset($tf_matrix_answer_map[$idx])) {
                        return null;
                    }

                    $answer_value = self::parse_docx_true_false_value_strict((string) $tf_matrix_answer_map[$idx]);
                    if ($answer_value === null) {
                        return null;
                    }
                    $matrix_items[] = [
                        'text' => CBT_Admin_Questions_Helper::sanitize_editor_html($statement_text),
                        'answer' => $answer_value,
                    ];
                }

                if (count($matrix_items) < 2 && $answer_text !== '') {
                    $matrix_items = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config($answer_text);
                }

                $matrix_validation_error = CBT_Admin_Questions_Helper::validate_true_false_matrix_items(
                    $matrix_items,
                    [
                        'provided_indexes' => isset($statement_keys) ? $statement_keys : [],
                        'source_rows' => array_map(static function (int $index, string $text): array {
                            return [
                                'index' => $index,
                                'text' => $text,
                            ];
                        }, array_keys($tf_matrix_statement_map), array_map('strval', array_values($tf_matrix_statement_map))),
                    ]
                );
                if ($matrix_validation_error !== '') {
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
                if ($explanation_text !== null) {
                    $row['explanation'] = $explanation_text;
                }
                $row['__import_diagnostics'] = $diagnostic_entries;
                return $row;
            }

            $options = [];
            foreach (range(1, $max_option_index) as $idx) {
                $val = trim((string) ($options_map[$idx] ?? ''));
                if ($val !== '') {
                    $options[$idx] = $val;
                }
            }

            if (empty($options)) {
                return null;
            }

            $filled_indices = array_keys($options);
            sort($filled_indices);
            $max_idx = (int) max($filled_indices);

            $detected_question_type = count($answer_indices) > 1 ? 'multiple_answer' : 'multiple_choice';
            if ($forced_question_type !== '') {
                $detected_question_type = $forced_question_type;
            }

            $minimum_option_count = in_array($detected_question_type, ['multiple_choice', 'multiple_answer'], true) ? 3 : 2;
            if (count($options) < $minimum_option_count) {
                return null;
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

            $raw_answer_indices = array_values(array_unique($answer_indices));
            sort($raw_answer_indices);
            $answer_indices = array_values(array_unique(array_filter(
                $answer_indices,
                static fn($idx) => is_int($idx) && $idx >= 1 && $idx <= $max_idx && isset($options[$idx])
            )));
            sort($answer_indices);

            $options_to_validate = [];
            foreach ($options as $idx => $option_text) {
                $options_to_validate[] = [
                    'option_text' => $option_text,
                    'is_correct' => in_array((int) $idx, $answer_indices, true) ? 1 : 0,
                ];
            }

            $choice_validation_error = CBT_Admin_Questions_Helper::validate_choice_options(
                $detected_question_type,
                $options_to_validate,
                ['has_empty_correct_reference' => !empty($raw_answer_indices) && $answer_indices !== $raw_answer_indices]
            );
            if ($choice_validation_error !== '') {
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
            if ($explanation_text !== null) {
                $row['explanation'] = $explanation_text;
            }
            $row['__import_diagnostics'] = $diagnostic_entries;

            return $row;
        }

        private static function is_docx_structured_question_block(array $block): bool
        {
            foreach ($block as $raw_line) {
                $line = trim((string) $raw_line);
                if ($line === '' || $line === '---') {
                    continue;
                }

                if (
                    preg_match('/^([1-9]|1[0-2])[\.\)]\s*\S+/u', $line) === 1 ||
                    preg_match('/^([A-La-l])[\.\)]\s*\S+/u', $line) === 1 ||
                    preg_match('/^(soal|question|pertanyaan|question_text|jenis_soal|question_type|type|jawaban|answer|correct_answer|correct_text|rubrik|rubric|pernyataan|statement|item|kunci|truth|tf|pilihan|opsi|option)\b/i', $line) === 1 ||
                    strpos($line, '__IMG__:') === 0 ||
                    strpos($line, self::DOCX_HTML_MARKER_PREFIX) === 0 ||
                    strpos($line, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) === 0
                ) {
                    return true;
                }
            }

            return false;
        }

        private static function build_docx_block_preview_text(array $block): string
        {
            foreach ($block as $raw_line) {
                $line = trim((string) $raw_line);
                if (
                    $line === '' ||
                    $line === '---' ||
                    strpos($line, '__IMG__:') === 0 ||
                    strpos($line, self::DOCX_HTML_MARKER_PREFIX) === 0 ||
                    strpos($line, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) === 0
                ) {
                    continue;
                }

                if (preg_match('/^(?:soal|question|pertanyaan|question_text)\s*:\s*(.+)$/i', $line, $matches)) {
                    return trim((string) ($matches[1] ?? ''));
                }

                if (strpos($line, ':') === false) {
                    return $line;
                }
            }

            return '';
        }

        private static function detect_docx_block_question_type(array $block): string
        {
            $forced_question_type = '';
            $answer_indices = [];
            $has_short_answer_keys = false;
            $has_matrix_keys = false;

            foreach ($block as $raw_line) {
                $line = trim((string) $raw_line);
                if ($line === '' || $line === '---') {
                    continue;
                }

                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $key = strtolower(trim((string) $parts[0]));
                $key = str_replace([' ', '-'], '_', $key);
                $value = trim((string) $parts[1]);

                if (in_array($key, ['jenis_soal', 'question_type', 'type'], true)) {
                    $forced_question_type = self::map_import_question_type($value);
                    continue;
                }

                if (preg_match('/^(pernyataan|statement|item)_?([1-9]|10)$/', $key) === 1 || preg_match('/^(kunci|truth|tf)_?([1-9]|10)$/', $key) === 1) {
                    $has_matrix_keys = true;
                    continue;
                }

                if (preg_match('/^(jawaban|answer|correct)_?([1-9]|10)$/', $key, $matches) === 1) {
                    $index = (int) $matches[2];
                    if ($index >= 9) {
                        $has_matrix_keys = true;
                    } else {
                        $has_short_answer_keys = true;
                    }
                    continue;
                }

                if (preg_match('/^(jawaban|answer|correct)_?([a-h])$/', $key) === 1) {
                    $has_short_answer_keys = true;
                    continue;
                }

                if (in_array($key, ['jawaban', 'answer', 'correct_answer', 'jawaban_ke', 'answer_option'], true)) {
                    $answer_indices = self::normalize_docx_answer_indices($value);
                }
            }

            if ($forced_question_type !== '') {
                return $forced_question_type;
            }
            if ($has_matrix_keys) {
                return 'true_false_matrix';
            }
            if ($has_short_answer_keys) {
                return 'short_answer';
            }

            return count($answer_indices) > 1 ? 'multiple_answer' : 'multiple_choice';
        }

        private static function describe_docx_block_failure(array $block): string
        {
            $normalized_block = array_values(array_filter(array_map(static function ($line): string {
                return trim((string) $line);
            }, $block), static fn(string $line): bool => $line !== '' && $line !== '---'));

            if (empty($normalized_block)) {
                return 'Blok kosong atau tidak sesuai template.';
            }

            $question_text = self::build_docx_block_preview_text($normalized_block);
            if ($question_text === '') {
                return 'Field SOAL / QUESTION wajib diisi.';
            }

            $forced_question_type = '';
            $answer_text = '';
            $answer_indices = [];
            $options_map = [];
            $ordering_item_map = [];
            $tf_matrix_statement_map = [];
            $tf_matrix_answer_map = [];
            $short_answer_map = [];

            foreach ($normalized_block as $line) {
                if (preg_match('/^([1-9]|1[0-2])[\.\)]\s*(.+)$/u', $line, $matches)) {
                    $options_map[(int) $matches[1]] = trim((string) $matches[2]);
                    continue;
                }
                if (preg_match('/^([A-La-l])[\.\)]\s*(.+)$/u', $line, $matches)) {
                    $option_index = ord(strtoupper((string) $matches[1])) - ord('A') + 1;
                    $options_map[$option_index] = trim((string) $matches[2]);
                    continue;
                }

                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $key = strtolower(trim((string) $parts[0]));
                $key = str_replace([' ', '-'], '_', $key);
                $value = trim((string) $parts[1]);

                if (in_array($key, ['jenis_soal', 'question_type', 'type'], true)) {
                    $forced_question_type = self::map_import_question_type($value);
                    continue;
                }

                if (in_array($key, ['jawaban', 'answer', 'correct_answer', 'jawaban_ke', 'answer_option', 'correct_text', 'rubrik', 'rubric', 'rubric_text'], true)) {
                    $answer_text = $value;
                    $answer_indices = self::normalize_docx_answer_indices($value);
                    continue;
                }

                if ($forced_question_type === 'ordering' && preg_match('/^(item|urutan|sequence|ordering)_?([1-9]|1[0-2])$/', $key, $matches)) {
                    $ordering_item_map[(int) $matches[2]] = $value;
                    continue;
                }

                if (preg_match('/^(pernyataan|statement|item)_?([1-9]|10)$/', $key, $matches)) {
                    $tf_matrix_statement_map[(int) $matches[2]] = $value;
                    continue;
                }

                if (preg_match('/^(kunci|truth|tf)_?([1-9]|10)$/', $key, $matches)) {
                    $tf_matrix_answer_map[(int) $matches[2]] = $value;
                    continue;
                }

                if (preg_match('/^(jawaban|answer|correct)_?([1-9]|10)$/', $key, $matches)) {
                    $answer_index = (int) $matches[2];
                    if ($forced_question_type === 'true_false_matrix' || !empty($tf_matrix_statement_map) || $answer_index >= 9) {
                        $tf_matrix_answer_map[$answer_index] = $value;
                    } elseif ($answer_index >= 1 && $answer_index <= 8) {
                        $short_answer_map[$answer_index] = $value;
                    }
                    continue;
                }

                if (preg_match('/^(jawaban|answer|correct)_?([a-h])$/', $key, $matches)) {
                    $short_answer_map[ord(strtoupper((string) $matches[2])) - ord('A') + 1] = $value;
                    continue;
                }

                if (preg_match('/^(pilihan|opsi|option)_?([1-9]|1[0-2])$/', $key, $matches)) {
                    $options_map[(int) $matches[2]] = $value;
                    continue;
                }

                if (preg_match('/^[a-l]$/', $key) === 1) {
                    if ($forced_question_type === 'short_answer' && preg_match('/^[a-h]$/', $key) === 1) {
                        $short_answer_map[ord(strtoupper($key)) - ord('A') + 1] = $value;
                    } else {
                        $options_map[ord(strtoupper($key)) - ord('A') + 1] = $value;
                    }
                }
            }

            if ($forced_question_type === '') {
                return 'Setiap blok DOCX wajib mencantumkan JENIS_SOAL yang valid sesuai template resmi.';
            }

            if ($forced_question_type === 'essay') {
                return trim($answer_text) === ''
                    ? 'Essay wajib memiliki JAWABAN atau rubrik.'
                    : 'Blok essay tidak sesuai template.';
            }

            if ($forced_question_type === 'true_false') {
                if (trim($answer_text) === '') {
                    return 'True/False wajib memiliki JAWABAN.';
                }

                return self::parse_docx_true_false_value_strict($answer_text) === null
                    ? 'Jawaban True/False harus bernilai true/false atau alias yang valid.'
                    : 'Blok True/False tidak sesuai template.';
            }

            if ($forced_question_type === 'short_answer') {
                $input_keys = CBT_Admin_Questions_Helper::resolve_short_answer_input_keys($question_text);
                if (empty($short_answer_map)) {
                    return 'Short Answer DOCX wajib memakai JAWABAN_A sampai JAWABAN_H sesuai key placeholder.';
                }

                $short_answer_values = [];
                $provided_keys = [];
                foreach (array_keys($short_answer_map) as $answer_index) {
                    $answer_index = (int) $answer_index;
                    if ($answer_index < 1 || $answer_index > 8) {
                        return 'Key jawaban Short Answer harus berada pada rentang A sampai H.';
                    }
                    $provided_keys[] = chr(64 + $answer_index);
                }
                sort($provided_keys);
                $expected_keys = $input_keys;
                sort($expected_keys);

                if ($provided_keys !== $expected_keys) {
                    return 'Key placeholder Short Answer harus cocok dengan key jawaban yang diisi.';
                }

                foreach ($input_keys as $input_key) {
                    $input_index = ord($input_key) - 64;
                    $mapped_value = trim((string) ($short_answer_map[$input_index] ?? ''));
                    if ($mapped_value === '') {
                        return 'Semua jawaban Short Answer yang dirujuk placeholder wajib diisi.';
                    }
                    $short_answer_values[] = $mapped_value;
                }

                $short_answer_validation_error = CBT_Admin_Questions_Helper::validate_short_answer_definition(
                    $question_text,
                    $short_answer_values,
                    ['provided_keys' => $provided_keys]
                );
                if ($short_answer_validation_error !== '') {
                    return $short_answer_validation_error;
                }

                return 'Blok Short Answer tidak sesuai template.';
            }

            if ($forced_question_type === 'ordering') {
                $source_items = !empty($ordering_item_map) ? $ordering_item_map : $options_map;
                $items = [];
                foreach ($source_items as $item_index => $item_text) {
                    $item_text = trim((string) $item_text);
                    if ($item_text !== '') {
                        $items[(int) $item_index] = $item_text;
                    }
                }
                ksort($items);

                if (count($items) < 2) {
                    return 'Ordering minimal harus punya 2 ITEM yang valid.';
                }

                $filled_item_indexes = array_keys($items);
                sort($filled_item_indexes);
                $max_item_index = (int) max($filled_item_indexes);
                for ($index = 1; $index <= $max_item_index; $index++) {
                    if (!isset($items[$index])) {
                        return 'ITEM Ordering harus diisi berurutan tanpa nomor yang loncat.';
                    }
                }

                $ordering_options = [];
                foreach ($items as $item_text) {
                    $ordering_options[] = [
                        'option_text' => $item_text,
                        'is_correct' => 0,
                    ];
                }

                $ordering_validation_error = CBT_Admin_Questions_Helper::validate_ordering_options($ordering_options);
                if ($ordering_validation_error !== '') {
                    return $ordering_validation_error;
                }

                return 'Blok Ordering tidak sesuai template.';
            }

            if ($forced_question_type === 'true_false_matrix' || !empty($tf_matrix_statement_map) || !empty($tf_matrix_answer_map)) {
                $statement_keys = array_values(array_unique(array_map('intval', array_keys($tf_matrix_statement_map))));
                $answer_keys = array_values(array_unique(array_map('intval', array_keys($tf_matrix_answer_map))));
                sort($statement_keys);
                sort($answer_keys);

                if (empty($statement_keys) || empty($answer_keys)) {
                    return 'True/False Matrix wajib memiliki minimal 2 PERNYATAAN dan KUNCI yang sesuai.';
                }
                if ($statement_keys !== range(1, count($statement_keys)) || $answer_keys !== range(1, count($answer_keys)) || $statement_keys !== $answer_keys) {
                    return 'PERNYATAAN_n dan KUNCI_n harus diisi berurutan tanpa nomor yang loncat.';
                }

                $matrix_items = [];
                $matrix_source_rows = [];
                foreach ($statement_keys as $statement_index) {
                    $statement_text = trim((string) ($tf_matrix_statement_map[$statement_index] ?? ''));
                    if ($statement_text === '') {
                        return 'Pernyataan True/False Matrix tidak boleh kosong.';
                    }

                    $answer_value = self::parse_docx_true_false_value_strict((string) ($tf_matrix_answer_map[$statement_index] ?? ''));
                    if ($answer_value === null) {
                        return 'KUNCI_n True/False Matrix harus bernilai true/false atau alias yang valid.';
                    }

                    $matrix_items[] = [
                        'text' => $statement_text,
                        'answer' => $answer_value,
                    ];
                    $matrix_source_rows[] = [
                        'index' => $statement_index,
                        'text' => $statement_text,
                    ];
                }

                $matrix_validation_error = CBT_Admin_Questions_Helper::validate_true_false_matrix_items(
                    $matrix_items,
                    [
                        'provided_indexes' => $statement_keys,
                        'source_rows' => $matrix_source_rows,
                    ]
                );
                if ($matrix_validation_error !== '') {
                    return $matrix_validation_error;
                }

                return 'Blok True/False Matrix tidak sesuai template.';
            }

            $options = [];
            foreach ($options_map as $option_index => $option_text) {
                $option_text = trim((string) $option_text);
                if ($option_text !== '') {
                    $options[(int) $option_index] = $option_text;
                }
            }
            ksort($options);

            $detected_question_type = count($answer_indices) > 1 ? 'multiple_answer' : 'multiple_choice';
            if ($forced_question_type !== '') {
                $detected_question_type = $forced_question_type;
            }

            if (empty($options)) {
                return $detected_question_type === 'multiple_answer'
                    ? 'Multiple Answer minimal harus punya 3 pilihan.'
                    : 'Multiple Choice minimal harus punya 3 pilihan.';
            }

            $filled_option_indexes = array_keys($options);
            sort($filled_option_indexes);
            $max_option_index = (int) max($filled_option_indexes);
            $filtered_answer_indexes = array_values(array_unique(array_filter(
                $answer_indices,
                static fn($index): bool => is_int($index) && $index >= 1 && $index <= $max_option_index && isset($options[$index])
            )));
            sort($filtered_answer_indexes);

            $raw_answer_indexes = array_values(array_unique($answer_indices));
            sort($raw_answer_indexes);
            if (!empty($raw_answer_indexes) && $filtered_answer_indexes !== $raw_answer_indexes) {
                return 'Jawaban benar menunjuk ke pilihan yang kosong atau tidak ada.';
            }

            for ($index = 1; $index <= $max_option_index; $index++) {
                if (!isset($options[$index])) {
                    return 'Pilihan harus diisi berurutan tanpa nomor yang loncat.';
                }
            }

            $options_to_validate = [];
            foreach ($options as $option_index => $option_text) {
                $options_to_validate[] = [
                    'option_text' => $option_text,
                    'is_correct' => in_array((int) $option_index, $filtered_answer_indexes, true) ? 1 : 0,
                ];
            }

            $has_empty_correct_reference = !empty($raw_answer_indexes)
                && $filtered_answer_indexes !== $raw_answer_indexes;
            $choice_validation_error = CBT_Admin_Questions_Helper::validate_choice_options(
                $detected_question_type,
                $options_to_validate,
                ['has_empty_correct_reference' => $has_empty_correct_reference]
            );
            if ($choice_validation_error !== '') {
                return $choice_validation_error;
            }

            return 'Blok soal tidak sesuai template DOCX.';
        }

        /**
         * @param int[] $keys
         */
        private static function is_contiguous_one_based_index_set(array $keys): bool
        {
            if (empty($keys)) {
                return false;
            }

            $keys = array_values(array_unique(array_map('intval', $keys)));
            sort($keys);

            return $keys === range(1, count($keys));
        }

        private static function store_docx_image_and_get_url(string $binary, string $filename): string
        {
            if ($binary === '') {
                return '';
            }

            $safe_ext = self::normalize_docx_image_extension($filename);
            if ($safe_ext === '') {
                return '';
            }

            $detected_mime = self::detect_docx_image_mime($binary);
            if ($detected_mime === '' || !self::docx_image_mime_matches_extension($safe_ext, $detected_mime)) {
                return '';
            }

            $upload_name = 'cbt-question-' . wp_generate_password(10, false, false) . '.' . $safe_ext;
            $upload = wp_upload_bits($upload_name, null, $binary);
            if (is_array($upload) && empty($upload['error']) && !empty($upload['url'])) {
                return esc_url_raw((string) $upload['url']);
            }

            $mime = self::guess_mime_from_extension($safe_ext);
            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        }

        private static function normalize_docx_image_extension(string $filename): string
        {
            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            $safe_ext = preg_replace('/[^a-z0-9]/', '', $ext);
            if (!is_string($safe_ext) || $safe_ext === '') {
                return '';
            }

            return isset(self::DOCX_IMAGE_MIME_BY_EXTENSION[$safe_ext]) ? $safe_ext : '';
        }

        private static function detect_docx_image_mime(string $binary): string
        {
            if ($binary === '') {
                return '';
            }

            if (function_exists('getimagesizefromstring')) {
                $image_info = @getimagesizefromstring($binary);
                if (
                    is_array($image_info) &&
                    !empty($image_info['mime']) &&
                    (int) ($image_info[0] ?? 0) > 0 &&
                    (int) ($image_info[1] ?? 0) > 0
                ) {
                    return strtolower(trim((string) $image_info['mime']));
                }

                return '';
            }

            if (function_exists('finfo_open') && function_exists('finfo_buffer')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $mime = @finfo_buffer($finfo, $binary);
                    @finfo_close($finfo);
                    if (is_string($mime) && trim($mime) !== '') {
                        return strtolower(trim($mime));
                    }
                }
            }

            return '';
        }

        private static function docx_image_mime_matches_extension(string $ext, string $mime): bool
        {
            $ext = strtolower($ext);
            $mime = strtolower(trim($mime));
            if ($ext === '' || $mime === '' || !isset(self::DOCX_IMAGE_MIME_BY_EXTENSION[$ext])) {
                return false;
            }

            if (self::DOCX_IMAGE_MIME_BY_EXTENSION[$ext] === $mime) {
                return true;
            }

            return in_array($ext, ['jpg', 'jpeg'], true) && $mime === 'image/pjpeg';
        }

        private static function is_docx_key_only_line(string $line): bool
        {
            $line = trim($line);
            if (
                $line === '' ||
                strpos($line, ':') !== false ||
                strpos($line, '__IMG__:') === 0 ||
                strpos($line, self::DOCX_HTML_MARKER_PREFIX) === 0 ||
                strpos($line, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) === 0
            ) {
                return false;
            }

            return (bool) preg_match(
                '/^(jenis_soal|question_type|type|soal|question|pertanyaan|subject_code|kode_mapel|exam_title|judul_exam|ujian|point|points|poin|nilai|pembahasan|explanation|jawaban|answer|correct_answer|jawaban_ke|answer_option|correct_text|rubrik|rubric|rubric_text|(pilihan|opsi|option)_?([1-9]|1[0-2])|(pernyataan|statement|item)_?([1-9]|1[0-2])|(kunci|truth|tf)_?([1-9]|10)|(jawaban|answer|correct)_?([1-9]|10|[a-h])|(kiri|left|prompt)_?([1-9]|1[0-2])|(kanan|right|pasangan|match)_?([1-9]|1[0-2])|dropdown_?([1-8])_?(opsi|option|pilihan)_?([1-6])|dropdown_?([1-8])_?(jawaban|answer|correct|kunci)|(kategori|category)_?([1-8])|item_?([1-9]|1[0-9]|2[0-4])|(kunci|answer|correct)_?([1-9]|1[0-9]|2[0-4])|table_?(rows|cols|columns)|cell_?([a-f][1-8])_?(type|text|jawaban|answer|opsi_[1-6]|option_[1-6])|[a-l])$/i',
                $line
            );
        }

        private static function is_docx_key_value_line(string $line): bool
        {
            $line = trim($line);
            if (
                $line === '' ||
                strpos($line, ':') === false ||
                strpos($line, '__IMG__:') === 0 ||
                strpos($line, self::DOCX_HTML_MARKER_PREFIX) === 0 ||
                strpos($line, self::DOCX_DIAGNOSTIC_MARKER_PREFIX) === 0
            ) {
                return false;
            }

            $parts = explode(':', $line, 2);
            return self::is_docx_key_only_line((string) ($parts[0] ?? ''));
        }

        private static function build_docx_question_text(array $parts): string
        {
            $html_parts = [];

            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    continue;
                }

                if (self::is_docx_html_fragment($part)) {
                    $html_parts[] = $part;
                    continue;
                }

                $safe_part = esc_html((string) $part);
                $safe_part = str_replace(["\r\n", "\r"], "\n", $safe_part);
                $safe_part = str_replace("\n", '<br />', $safe_part);

                $html_parts[] = '<p>' . $safe_part . '</p>';
            }

            return trim(implode('', $html_parts));
        }

        private static function is_docx_html_fragment(string $part): bool
        {
            return preg_match('/^<(?:p|table|thead|tbody|tfoot|tr|td|th|div|figure|figcaption|img|ul|ol|li)\b/i', trim($part)) === 1;
        }

        private static function append_docx_html_fragment_to_active_context(
            string $html_fragment,
            $active_context,
            array &$question_parts,
            array &$explanation_parts,
            array &$answer_parts,
            array &$options_map,
            array &$tf_matrix_statement_map,
            array &$ordering_item_map,
            array &$matching_left_map,
            array &$categorization_item_map,
            array &$table_cell_map
        ): void {
            if ($html_fragment === '') {
                return;
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'option') {
                $opt_idx = (int) ($active_context[1] ?? 0);
                if ($opt_idx >= 1 && $opt_idx <= 12) {
                    $options_map[$opt_idx] = self::append_docx_html_fragment_to_string((string) ($options_map[$opt_idx] ?? ''), $html_fragment);
                    return;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'matrix_statement') {
                $statement_idx = (int) ($active_context[1] ?? 0);
                if ($statement_idx >= 1 && $statement_idx <= 10) {
                    $tf_matrix_statement_map[$statement_idx] = self::append_docx_html_fragment_to_string((string) ($tf_matrix_statement_map[$statement_idx] ?? ''), $html_fragment);
                    return;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'ordering_item') {
                $item_idx = (int) ($active_context[1] ?? 0);
                if ($item_idx >= 1 && $item_idx <= 12) {
                    $ordering_item_map[$item_idx] = self::append_docx_html_fragment_to_string((string) ($ordering_item_map[$item_idx] ?? ''), $html_fragment);
                    return;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'matching_left') {
                $item_idx = (int) ($active_context[1] ?? 0);
                if ($item_idx >= 1 && $item_idx <= 12) {
                    $matching_left_map[$item_idx] = self::append_docx_html_fragment_to_string((string) ($matching_left_map[$item_idx] ?? ''), $html_fragment);
                    return;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'categorization_item') {
                $item_idx = (int) ($active_context[1] ?? 0);
                if ($item_idx >= 1 && $item_idx <= 24) {
                    $categorization_item_map[$item_idx] = self::append_docx_html_fragment_to_string((string) ($categorization_item_map[$item_idx] ?? ''), $html_fragment);
                    return;
                }
            }

            if (is_array($active_context) && ($active_context[0] ?? '') === 'table_cell') {
                $cell_key = strtoupper((string) ($active_context[1] ?? ''));
                $field_key = strtolower((string) ($active_context[2] ?? ''));
                if ($cell_key !== '' && $field_key !== '') {
                    if (!isset($table_cell_map[$cell_key]) || !is_array($table_cell_map[$cell_key])) {
                        $table_cell_map[$cell_key] = [];
                    }
                    $table_cell_map[$cell_key][$field_key] = self::append_docx_html_fragment_to_string(
                        (string) ($table_cell_map[$cell_key][$field_key] ?? ''),
                        $html_fragment
                    );
                    return;
                }
            }

            if ($active_context === 'explanation') {
                $explanation_parts[] = $html_fragment;
                return;
            }

            if ($active_context === 'answer') {
                $answer_parts[] = $html_fragment;
                return;
            }

            $question_parts[] = $html_fragment;
        }

        private static function append_docx_html_fragment_to_string(string $current, string $html_fragment): string
        {
            $current = trim($current);
            if ($current === '') {
                return $html_fragment;
            }

            return $current . $html_fragment;
        }

        private static function extract_docx_paragraph_text(string $paragraph): string
        {
            if (
                $paragraph !== '' &&
                (strpos($paragraph, '<m:') !== false || strpos($paragraph, '<w:sym') !== false) &&
                class_exists('DOMDocument')
            ) {
                $math_text = self::extract_docx_paragraph_text_with_math($paragraph);
                if ($math_text !== '') {
                    return $math_text;
                }
            }

            $paragraph = (string) preg_replace('/<w:(?:br|cr)\b[^>]*\/>/i', '__CBT_DOCX_BREAK__', (string) $paragraph);
            $paragraph = (string) preg_replace('/<w:tab\b[^>]*\/>/i', '__CBT_DOCX_TAB__', $paragraph);

            if (!preg_match_all('/(__CBT_DOCX_BREAK__|__CBT_DOCX_TAB__|<w:t[^>]*>.*?<\/w:t>)/s', $paragraph, $tokens)) {
                return '';
            }

            $text = '';
            foreach ((array) ($tokens[1] ?? []) as $token) {
                $token = (string) $token;
                if ($token === '__CBT_DOCX_BREAK__') {
                    $text .= "\n";
                    continue;
                }
                if ($token === '__CBT_DOCX_TAB__') {
                    $text .= "\t";
                    continue;
                }
                if (preg_match('/<w:t[^>]*>(.*?)<\/w:t>/s', $token, $fragment_match) !== 1) {
                    continue;
                }

                $text .= html_entity_decode(strip_tags((string) ($fragment_match[1] ?? '')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $text = (string) preg_replace("/[ \t]*\n[ \t]*/", "\n", $text);

            return trim($text);
        }

        private static function extract_docx_paragraph_text_with_math(string $paragraph): string
        {
            $wrapped_xml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<w:root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'
                . $paragraph
                . '</w:root>';

            $previous_libxml_state = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = @$dom->loadXML($wrapped_xml, LIBXML_NONET | LIBXML_COMPACT);
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_state);

            if (!$loaded || !$dom->documentElement instanceof DOMElement) {
                return '';
            }

            $text = self::render_docx_text_like_children($dom->documentElement);
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $text = (string) preg_replace("/[ \t]*\n[ \t]*/", "\n", $text);

            return trim($text);
        }

        private static function render_docx_text_like_children(DOMNode $node, string $separator = ''): string
        {
            $parts = [];

            foreach ($node->childNodes as $child) {
                $parts[] = self::render_docx_text_like_node($child);
            }

            if ($separator === '') {
                return implode('', $parts);
            }

            $parts = array_values(array_filter(array_map(static function ($part): string {
                return trim((string) $part);
            }, $parts), static function (string $part): bool {
                return $part !== '';
            }));

            return implode($separator, $parts);
        }

        private static function render_docx_text_like_node(DOMNode $node): string
        {
            if ($node instanceof DOMText) {
                $value = (string) $node->nodeValue;
                return trim($value) === '' ? '' : $value;
            }

            if (!$node instanceof DOMElement) {
                return '';
            }

            $namespace = (string) $node->namespaceURI;
            $local_name = (string) $node->localName;
            if ($namespace === 'http://schemas.openxmlformats.org/officeDocument/2006/math') {
                return self::render_docx_math_node($node);
            }

            if ($namespace === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                if ($local_name === 't') {
                    return html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                if ($local_name === 'br' || $local_name === 'cr') {
                    return "\n";
                }
                if ($local_name === 'tab') {
                    return "\t";
                }
                if ($local_name === 'sym') {
                    return self::decode_docx_symbol_element($node);
                }
            }

            return self::render_docx_text_like_children($node);
        }

        private static function render_docx_math_node(DOMElement $node): string
        {
            $local_name = (string) $node->localName;

            switch ($local_name) {
                case 't':
                    return html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8');

                case 'oMath':
                    return self::render_docx_text_like_children($node);

                case 'oMathPara':
                    return self::format_docx_multiline_math_text(
                        self::render_docx_text_like_children($node)
                    );

                case 'r':
                    return self::render_docx_math_run_text($node);

                case 'e':
                case 'sub':
                case 'sup':
                case 'num':
                case 'den':
                case 'deg':
                case 'fName':
                case 'box':
                case 'bar':
                case 'borderBox':
                case 'phant':
                    return self::render_docx_text_like_children($node);

                case 'sSup':
                    $base = self::render_docx_math_direct_child_text($node, 'e');
                    $sup = self::render_docx_math_direct_child_text($node, 'sup');
                    if ($base === '' || $sup === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    return $base . '^(' . $sup . ')';

                case 'sSub':
                    $base = self::render_docx_math_direct_child_text($node, 'e');
                    $sub = self::render_docx_math_direct_child_text($node, 'sub');
                    if ($base === '' || $sub === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    return $base . '_(' . $sub . ')';

                case 'sSubSup':
                    $base = self::render_docx_math_direct_child_text($node, 'e');
                    $sub = self::render_docx_math_direct_child_text($node, 'sub');
                    $sup = self::render_docx_math_direct_child_text($node, 'sup');
                    if ($base === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    $result = $base;
                    if ($sub !== '') {
                        $result .= '_(' . $sub . ')';
                    }
                    if ($sup !== '') {
                        $result .= '^(' . $sup . ')';
                    }
                    return $result;

                case 'sPre':
                    $base = self::render_docx_math_direct_child_text($node, 'e');
                    $sub = self::render_docx_math_direct_child_text($node, 'sub');
                    $sup = self::render_docx_math_direct_child_text($node, 'sup');
                    if ($base === '') {
                        return self::render_docx_text_like_children($node);
                    }

                    $result = '';
                    if ($sup !== '') {
                        $result .= '^(' . $sup . ')';
                    }
                    if ($sub !== '') {
                        $result .= '_(' . $sub . ')';
                    }

                    return $result . $base;

                case 'f':
                    $num = self::render_docx_math_direct_child_text($node, 'num');
                    $den = self::render_docx_math_direct_child_text($node, 'den');
                    if ($num === '' && $den === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    return '(' . $num . ')/(' . $den . ')';

                case 'rad':
                    $degree = self::render_docx_math_direct_child_text($node, 'deg');
                    $expression = self::render_docx_math_direct_child_text($node, 'e');
                    if ($expression === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    if ($degree !== '') {
                        return 'root[' . $degree . '](' . $expression . ')';
                    }
                    return '√(' . $expression . ')';

                case 'd':
                    $binomial_parts = self::extract_docx_math_binomial_parts_text($node);
                    if ($binomial_parts !== null) {
                        return '(' . $binomial_parts[0] . ' choose ' . $binomial_parts[1] . ')';
                    }

                    $expression = self::render_docx_math_direct_child_text($node, 'e');
                    if ($expression === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    $beg = self::extract_docx_math_property_value($node, 'dPr', 'begChr', '(');
                    $end = self::extract_docx_math_property_value($node, 'dPr', 'endChr', ')');
                    return $beg . $expression . $end;

                case 'func':
                    $function_name = self::render_docx_math_direct_child_text($node, 'fName');
                    $expression = self::render_docx_math_direct_child_text($node, 'e');
                    if ($function_name === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    if ($expression === '') {
                        return $function_name;
                    }
                    return rtrim($function_name) . '(' . $expression . ')';

                case 'limLow':
                    $base = self::render_docx_math_direct_child_text($node, 'e');
                    $limit = self::render_docx_math_direct_child_text($node, 'lim');
                    if ($base === '' || $limit === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    return $base . '_(' . $limit . ')';

                case 'limUpp':
                    $base = self::render_docx_math_direct_child_text($node, 'e');
                    $limit = self::render_docx_math_direct_child_text($node, 'lim');
                    if ($base === '' || $limit === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    return $base . '^(' . $limit . ')';

                case 'nary':
                    $operator = self::resolve_docx_math_nary_operator($node);
                    $sub = self::render_docx_math_direct_child_text($node, 'sub');
                    $sup = self::render_docx_math_direct_child_text($node, 'sup');
                    $expression = self::render_docx_math_direct_child_text($node, 'e');
                    $result = $operator;
                    if ($sub !== '') {
                        $result .= '_(' . $sub . ')';
                    }
                    if ($sup !== '') {
                        $result .= '^(' . $sup . ')';
                    }
                    if ($expression !== '') {
                        $result .= ' ' . $expression;
                    }
                    return $result;

                case 'mr':
                    return self::render_docx_math_direct_children_text($node, 'e', ', ');

                case 'm':
                    $rows = self::render_docx_math_direct_children_text($node, 'mr', '; ');
                    if ($rows === '') {
                        return self::render_docx_text_like_children($node);
                    }
                    return '[' . $rows . ']';

                case 'eqArr':
                    $expressions = self::render_docx_math_direct_children_text($node, 'e', '; ');
                    return $expressions !== '' ? $expressions : self::render_docx_text_like_children($node);

                case 'acc':
                case 'groupChr':
                    $expression = self::render_docx_math_direct_child_text($node, 'e');
                    return $expression !== '' ? $expression : self::render_docx_text_like_children($node);

                case 'accPr':
                case 'argPr':
                case 'ctrlPr':
                case 'dPr':
                case 'funcPr':
                case 'limLowPr':
                case 'limUppPr':
                case 'naryPr':
                case 'radPr':
                case 'rPr':
                case 'sPrePr':
                case 'sSubPr':
                case 'sSupPr':
                case 'sSubSupPr':
                    return '';
            }

            return self::render_docx_text_like_children($node);
        }

        private static function render_docx_math_node_to_katex(DOMElement $node): string
        {
            $local_name = (string) $node->localName;

            switch ($local_name) {
                case 't':
                    return self::normalize_docx_math_katex_text(
                        html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    );

                case 'oMath':
                    return self::render_docx_math_text_like_children_to_katex($node);

                case 'oMathPara':
                    return self::format_docx_multiline_math_katex(
                        self::render_docx_math_text_like_children_to_katex($node)
                    );

                case 'r':
                    return self::render_docx_math_run_katex($node);

                case 'e':
                case 'sub':
                case 'sup':
                case 'num':
                case 'den':
                case 'deg':
                case 'fName':
                case 'box':
                case 'bar':
                case 'borderBox':
                case 'phant':
                    return self::render_docx_math_text_like_children_to_katex($node);

                case 'sSup':
                    $base = self::render_docx_math_direct_child_katex($node, 'e');
                    $sup = self::render_docx_math_direct_child_katex($node, 'sup');
                    if ($base === '' || $sup === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '{' . $base . '}^{' . $sup . '}';

                case 'sSub':
                    $base = self::render_docx_math_direct_child_katex($node, 'e');
                    $sub = self::render_docx_math_direct_child_katex($node, 'sub');
                    if ($base === '' || $sub === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '{' . $base . '}_{' . $sub . '}';

                case 'sSubSup':
                    $base = self::render_docx_math_direct_child_katex($node, 'e');
                    $sub = self::render_docx_math_direct_child_katex($node, 'sub');
                    $sup = self::render_docx_math_direct_child_katex($node, 'sup');
                    if ($base === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }

                    $result = '{' . $base . '}';
                    if ($sub !== '') {
                        $result .= '_{' . $sub . '}';
                    }
                    if ($sup !== '') {
                        $result .= '^{' . $sup . '}';
                    }

                    return $result;

                case 'sPre':
                    $base = self::render_docx_math_direct_child_katex($node, 'e');
                    $sub = self::render_docx_math_direct_child_katex($node, 'sub');
                    $sup = self::render_docx_math_direct_child_katex($node, 'sup');
                    if ($base === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }

                    $result = '';
                    if ($sub !== '') {
                        $result .= '_{' . $sub . '}';
                    }
                    if ($sup !== '') {
                        $result .= '^{' . $sup . '}';
                    }

                    return '{}' . $result . '{' . $base . '}';

                case 'f':
                    $num = self::render_docx_math_direct_child_katex($node, 'num');
                    $den = self::render_docx_math_direct_child_katex($node, 'den');
                    if ($num === '' && $den === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '\\frac{' . $num . '}{' . $den . '}';

                case 'rad':
                    $degree = self::render_docx_math_direct_child_katex($node, 'deg');
                    $expression = self::render_docx_math_direct_child_katex($node, 'e');
                    if ($expression === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    if ($degree !== '') {
                        return '\\sqrt[' . $degree . ']{' . $expression . '}';
                    }
                    return '\\sqrt{' . $expression . '}';

                case 'd':
                    $binomial_parts = self::extract_docx_math_binomial_parts_katex($node);
                    if ($binomial_parts !== null) {
                        return '\\binom{' . $binomial_parts[0] . '}{' . $binomial_parts[1] . '}';
                    }

                    $expression = self::render_docx_math_direct_child_katex($node, 'e');
                    if ($expression === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    $beg = self::extract_docx_math_property_value($node, 'dPr', 'begChr', '(');
                    $end = self::extract_docx_math_property_value($node, 'dPr', 'endChr', ')');
                    return self::normalize_docx_math_katex_text($beg) . $expression . self::normalize_docx_math_katex_text($end);

                case 'func':
                    $function_name = self::render_docx_math_direct_child_katex($node, 'fName');
                    $expression = self::render_docx_math_direct_child_katex($node, 'e');
                    if ($function_name === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }

                    $normalized_function = self::normalize_docx_math_function_name($function_name);
                    if ($expression === '') {
                        return $normalized_function;
                    }

                    return $normalized_function . '(' . $expression . ')';

                case 'limLow':
                    $base = self::render_docx_math_direct_child_katex($node, 'e');
                    $limit = self::render_docx_math_direct_child_katex($node, 'lim');
                    if ($base === '' || $limit === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '{' . $base . '}_{' . $limit . '}';

                case 'limUpp':
                    $base = self::render_docx_math_direct_child_katex($node, 'e');
                    $limit = self::render_docx_math_direct_child_katex($node, 'lim');
                    if ($base === '' || $limit === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '{' . $base . '}^{' . $limit . '}';

                case 'nary':
                    $operator = self::normalize_docx_math_operator(self::resolve_docx_math_nary_operator($node));
                    $sub = self::render_docx_math_direct_child_katex($node, 'sub');
                    $sup = self::render_docx_math_direct_child_katex($node, 'sup');
                    $expression = self::render_docx_math_direct_child_katex($node, 'e');

                    $result = $operator;
                    if ($sub !== '') {
                        $result .= '_{' . $sub . '}';
                    }
                    if ($sup !== '') {
                        $result .= '^{' . $sup . '}';
                    }
                    if ($expression !== '') {
                        $result .= ' ' . $expression;
                    }

                    return $result;

                case 'mr':
                    return self::render_docx_math_direct_children_katex($node, 'e', ' & ');

                case 'm':
                    $rows = self::render_docx_math_direct_children_katex($node, 'mr', ' \\\\ ');
                    if ($rows === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '\\begin{bmatrix}' . $rows . '\\end{bmatrix}';

                case 'eqArr':
                    $expressions = self::render_docx_math_direct_children_katex($node, 'e', ' \\\\ ');
                    if ($expressions === '') {
                        return self::render_docx_math_text_like_children_to_katex($node);
                    }
                    return '\\begin{aligned}' . $expressions . '\\end{aligned}';

                case 'acc':
                case 'groupChr':
                    $expression = self::render_docx_math_direct_child_katex($node, 'e');
                    return $expression !== '' ? $expression : self::render_docx_math_text_like_children_to_katex($node);

                case 'accPr':
                case 'argPr':
                case 'ctrlPr':
                case 'dPr':
                case 'funcPr':
                case 'limLowPr':
                case 'limUppPr':
                case 'naryPr':
                case 'radPr':
                case 'rPr':
                case 'sPrePr':
                case 'sSubPr':
                case 'sSupPr':
                case 'sSubSupPr':
                    return '';
            }

            return self::render_docx_math_text_like_children_to_katex($node);
        }

        private static function render_docx_math_text_like_children_to_katex(DOMElement $node): string
        {
            $parts = [];
            foreach ($node->childNodes as $child) {
                if (!$child instanceof DOMNode) {
                    continue;
                }

                if ($child instanceof DOMText) {
                    $parts[] = self::normalize_docx_math_katex_text($child->wholeText);
                    continue;
                }

                if ($child instanceof DOMElement) {
                    $parts[] = self::render_docx_math_node_to_katex($child);
                }
            }

            return trim(implode('', array_filter($parts, static function ($part): bool {
                return (string) $part !== '';
            })));
        }

        private static function render_docx_math_run_text(DOMElement $node): string
        {
            $text = self::render_docx_text_like_children($node);
            if (!self::docx_math_run_has_break($node)) {
                return $text;
            }

            return "\n" . ltrim($text);
        }

        private static function render_docx_math_run_katex(DOMElement $node): string
        {
            $text = self::render_docx_math_text_like_children_to_katex($node);
            if (!self::docx_math_run_has_break($node)) {
                return $text;
            }

            return "\n" . ltrim($text);
        }

        private static function docx_math_run_has_break(DOMElement $node): bool
        {
            if ((string) $node->localName !== 'r') {
                return false;
            }

            $run_properties = self::find_docx_math_direct_child_element($node, 'rPr');
            if (!$run_properties instanceof DOMElement) {
                return false;
            }

            return self::find_docx_math_direct_child_element($run_properties, 'brk') instanceof DOMElement;
        }

        private static function format_docx_multiline_math_text(string $text): string
        {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $text = (string) preg_replace("/[ \t]*\n[ \t]*/u", "\n", $text);

            return trim($text);
        }

        private static function format_docx_multiline_math_katex(string $source): string
        {
            $source = self::format_docx_multiline_math_text($source);
            if ($source === '') {
                return '';
            }

            $rows = array_values(array_filter(array_map(static function (string $row): string {
                return trim($row);
            }, explode("\n", $source)), static function (string $row): bool {
                return $row !== '';
            }));

            if (count($rows) <= 1) {
                return $rows[0] ?? '';
            }

            $rows = array_map([self::class, 'insert_docx_math_alignment_marker_into_katex_row'], $rows);

            return '\\begin{aligned}' . implode(' \\\\ ', $rows) . '\\end{aligned}';
        }

        private static function insert_docx_math_alignment_marker_into_katex_row(string $row): string
        {
            $row = trim($row);
            $equals_position = self::find_docx_math_top_level_equals_position($row);
            if ($equals_position === null) {
                return $row;
            }

            return substr($row, 0, $equals_position) . '&=' . substr($row, $equals_position + 1);
        }

        private static function find_docx_math_top_level_equals_position(string $row): ?int
        {
            $depth = 0;
            $length = strlen($row);

            for ($index = 0; $index < $length; $index++) {
                $character = $row[$index];
                if ($character === '{') {
                    $depth++;
                    continue;
                }

                if ($character === '}') {
                    if ($depth > 0) {
                        $depth--;
                    }
                    continue;
                }

                if ($character === '=' && $depth === 0) {
                    return $index;
                }
            }

            return null;
        }

        private static function resolve_docx_math_nary_operator(DOMElement $node): string
        {
            return self::extract_docx_math_property_value($node, 'naryPr', 'chr', '∫');
        }

        private static function render_docx_math_direct_child_katex(DOMElement $parent, string $child_local_name): string
        {
            foreach ($parent->childNodes as $child) {
                if (
                    $child instanceof DOMElement &&
                    (string) $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math' &&
                    (string) $child->localName === $child_local_name
                ) {
                    return trim(self::render_docx_math_node_to_katex($child));
                }
            }

            return '';
        }

        private static function render_docx_math_direct_children_katex(DOMElement $parent, string $child_local_name, string $separator): string
        {
            $parts = [];
            foreach ($parent->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/officeDocument/2006/math' ||
                    (string) $child->localName !== $child_local_name
                ) {
                    continue;
                }

                $rendered = trim(self::render_docx_math_node_to_katex($child));
                if ($rendered !== '') {
                    $parts[] = $rendered;
                }
            }

            return implode($separator, $parts);
        }

        /**
         * @return array{0:string,1:string}|null
         */
        private static function extract_docx_math_binomial_parts_text(DOMElement $node): ?array
        {
            return self::extract_docx_math_binomial_parts(
                $node,
                static function (DOMElement $element): string {
                    return self::render_docx_math_node($element);
                }
            );
        }

        /**
         * @return array{0:string,1:string}|null
         */
        private static function extract_docx_math_binomial_parts_katex(DOMElement $node): ?array
        {
            return self::extract_docx_math_binomial_parts(
                $node,
                static function (DOMElement $element): string {
                    return self::render_docx_math_node_to_katex($element);
                }
            );
        }

        /**
         * @param callable(DOMElement):string $renderer
         * @return array{0:string,1:string}|null
         */
        private static function extract_docx_math_binomial_parts(DOMElement $node, callable $renderer): ?array
        {
            $beg = self::extract_docx_math_property_value($node, 'dPr', 'begChr', '(');
            $end = self::extract_docx_math_property_value($node, 'dPr', 'endChr', ')');
            if ($beg !== '(' || $end !== ')') {
                return null;
            }

            $expression = self::find_docx_math_direct_child_element($node, 'e');
            if (!$expression instanceof DOMElement) {
                return null;
            }

            $eq_array = self::find_docx_math_direct_child_element($expression, 'eqArr');
            if (!$eq_array instanceof DOMElement) {
                return null;
            }

            $rows = [];
            foreach ($eq_array->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/officeDocument/2006/math' ||
                    (string) $child->localName !== 'e'
                ) {
                    continue;
                }

                $rendered = trim((string) $renderer($child));
                if ($rendered !== '') {
                    $rows[] = $rendered;
                }
            }

            if (count($rows) !== 2) {
                return null;
            }

            return [$rows[0], $rows[1]];
        }

        private static function find_docx_math_direct_child_element(DOMElement $parent, string $child_local_name): ?DOMElement
        {
            foreach ($parent->childNodes as $child) {
                if (
                    $child instanceof DOMElement &&
                    (string) $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math' &&
                    (string) $child->localName === $child_local_name
                ) {
                    return $child;
                }
            }

            return null;
        }

        private static function normalize_docx_math_katex_text(string $text): string
        {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $text = preg_replace('/\s+/u', ' ', $text);
            $text = trim((string) $text);
            if ($text === '') {
                return '';
            }

            return strtr($text, [
                '∝' => '\\propto ',
                '∞' => '\\infty ',
                '≤' => '\\le ',
                '≥' => '\\ge ',
                '≠' => '\\neq ',
                '≈' => '\\approx ',
                '±' => '\\pm ',
                '×' => '\\times ',
                '·' => '\\cdot ',
                '→' => '\\rightarrow ',
                '←' => '\\leftarrow ',
                'α' => '\\alpha ',
                'β' => '\\beta ',
                'θ' => '\\theta ',
                'π' => '\\pi ',
                'μ' => '\\mu ',
            ]);
        }

        private static function normalize_docx_math_function_name(string $function_name): string
        {
            $normalized = strtolower(trim($function_name));
            $map = [
                'sin' => '\\sin',
                'cos' => '\\cos',
                'tan' => '\\tan',
                'cot' => '\\cot',
                'sec' => '\\sec',
                'csc' => '\\csc',
                'log' => '\\log',
                'ln' => '\\ln',
                'lim' => '\\lim',
                'max' => '\\max',
                'min' => '\\min',
            ];

            if (isset($map[$normalized])) {
                return $map[$normalized];
            }

            if ($normalized === '') {
                return '';
            }

            return '\\operatorname{' . $normalized . '}';
        }

        private static function normalize_docx_math_operator(string $operator): string
        {
            $normalized = trim($operator);
            $map = [
                '∑' => '\\sum',
                '∫' => '\\int',
                '∏' => '\\prod',
                '⋂' => '\\bigcap',
                '⋃' => '\\bigcup',
            ];

            return $map[$normalized] ?? self::normalize_docx_math_katex_text($normalized);
        }

        private static function render_docx_math_direct_child_text(DOMElement $parent, string $child_local_name): string
        {
            foreach ($parent->childNodes as $child) {
                if (
                    $child instanceof DOMElement &&
                    (string) $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math' &&
                    (string) $child->localName === $child_local_name
                ) {
                    return trim(self::render_docx_math_node($child));
                }
            }

            return '';
        }

        private static function render_docx_math_direct_children_text(DOMElement $parent, string $child_local_name, string $separator): string
        {
            $parts = [];

            foreach ($parent->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/officeDocument/2006/math' ||
                    (string) $child->localName !== $child_local_name
                ) {
                    continue;
                }

                $rendered = trim(self::render_docx_math_node($child));
                if ($rendered !== '') {
                    $parts[] = $rendered;
                }
            }

            return implode($separator, $parts);
        }

        private static function extract_docx_math_property_value(
            DOMElement $parent,
            string $property_local_name,
            string $value_local_name,
            string $default = ''
        ): string {
            foreach ($parent->childNodes as $child) {
                if (
                    !$child instanceof DOMElement ||
                    (string) $child->namespaceURI !== 'http://schemas.openxmlformats.org/officeDocument/2006/math' ||
                    (string) $child->localName !== $property_local_name
                ) {
                    continue;
                }

                foreach ($child->childNodes as $property_value_node) {
                    if (
                        !$property_value_node instanceof DOMElement ||
                        (string) $property_value_node->namespaceURI !== 'http://schemas.openxmlformats.org/officeDocument/2006/math' ||
                        (string) $property_value_node->localName !== $value_local_name
                    ) {
                        continue;
                    }

                    $value = self::get_docx_attribute_value($property_value_node, 'val');
                    if ($value !== '') {
                        return $value;
                    }

                    $text_value = trim((string) $property_value_node->textContent);
                    if ($text_value !== '') {
                        return $text_value;
                    }
                }
            }

            return $default;
        }

        private static function decode_docx_symbol_element(DOMElement $symbol): string
        {
            $font = strtolower(trim(self::get_docx_attribute_value($symbol, 'font')));
            $char_hex = strtoupper((string) preg_replace('/[^0-9A-F]/i', '', self::get_docx_attribute_value($symbol, 'char')));
            if ($char_hex === '') {
                return '';
            }

            $codepoint = hexdec($char_hex);
            if ($codepoint > 0 && ($codepoint < 0xE000 || $codepoint > 0xF8FF)) {
                return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $low_byte = strtoupper(substr($char_hex, -2));
            $mapped_symbol = self::map_docx_symbol_code_to_unicode($font, $low_byte);
            if ($mapped_symbol !== '') {
                return $mapped_symbol;
            }

            $fallback_codepoint = hexdec($low_byte);
            if ($fallback_codepoint > 0) {
                return html_entity_decode('&#x' . dechex($fallback_codepoint) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            return '';
        }

        private static function map_docx_symbol_code_to_unicode(string $font, string $low_byte): string
        {
            $font = strtolower(trim($font));
            $low_byte = strtoupper(trim($low_byte));
            if ($low_byte === '') {
                return '';
            }

            if ($font === 'symbol') {
                $direct_map = [
                    'A3' => '≤',
                    'B0' => '°',
                    'B1' => '±',
                    'B3' => '≥',
                    'B9' => '≠',
                    'C5' => '∅',
                    'D6' => '∂',
                    'E5' => '∞',
                    'F2' => '∫',
                    'F3' => '∑',
                    'F4' => '√',
                    'F5' => '∝',
                ];
                if (isset($direct_map[$low_byte])) {
                    return $direct_map[$low_byte];
                }

                $ascii = chr(hexdec($low_byte));
                $greek_map = [
                    'A' => 'Α', 'B' => 'Β', 'G' => 'Γ', 'D' => 'Δ', 'E' => 'Ε', 'Z' => 'Ζ',
                    'H' => 'Η', 'Q' => 'Θ', 'I' => 'Ι', 'K' => 'Κ', 'L' => 'Λ', 'M' => 'Μ',
                    'N' => 'Ν', 'X' => 'Ξ', 'O' => 'Ο', 'P' => 'Π', 'R' => 'Ρ', 'S' => 'Σ',
                    'T' => 'Τ', 'U' => 'Υ', 'F' => 'Φ', 'C' => 'Χ', 'Y' => 'Ψ', 'W' => 'Ω',
                    'a' => 'α', 'b' => 'β', 'g' => 'γ', 'd' => 'δ', 'e' => 'ε', 'z' => 'ζ',
                    'h' => 'η', 'q' => 'θ', 'i' => 'ι', 'k' => 'κ', 'l' => 'λ', 'm' => 'μ',
                    'n' => 'ν', 'x' => 'ξ', 'o' => 'ο', 'p' => 'π', 'r' => 'ρ', 'V' => 'ς',
                    's' => 'σ', 't' => 'τ', 'u' => 'υ', 'f' => 'φ', 'j' => 'ϕ', 'c' => 'χ',
                    'y' => 'ψ', 'w' => 'ω',
                ];
                if (isset($greek_map[$ascii])) {
                    return $greek_map[$ascii];
                }
            }

            return '';
        }

        private static function guess_mime_from_extension(string $ext): string
        {
            $ext = strtolower($ext);

            return self::DOCX_IMAGE_MIME_BY_EXTENSION[$ext] ?? '';
        }

}
