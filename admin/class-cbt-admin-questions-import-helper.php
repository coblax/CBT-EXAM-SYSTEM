<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Questions_Import_Helper
{
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
    
            $msg = sprintf('Import soal ke Bank Soal selesai. Total: %d, Created: %d, Failed: %d', $total, $created, $failed);
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
                $correct_text = CBT_Admin_Questions_Helper::normalize_true_false_matrix_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
                if ($correct_text === '' || count(CBT_Admin_Questions_Helper::normalize_true_false_matrix_config($correct_text)) < 2) {
                    return 'failed';
                }
                $options_raw = '';
            } elseif ($question_type === 'short_answer') {
                $correct_text = CBT_Admin_Questions_Helper::normalize_short_answer_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
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
            $options_to_insert = CBT_Admin_Questions_Helper::parse_options($options_raw);
    
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
    
            CBT_Admin_Questions_Helper::save_question_type_detail($question_id, $question_type, $correct_text);
    
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

        public static function build_options_raw_from_import(string $options_input, string $correct_answer, string $question_type): string
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
                    $short_answer_values = array_merge($short_answer_values, CBT_Admin_Questions_Helper::normalize_short_answer_values($answer_text));
                }
                $short_answer_values = CBT_Admin_Questions_Helper::normalize_short_answer_values(wp_json_encode($short_answer_values));
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
                    $matrix_items = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config($answer_text);
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

}
