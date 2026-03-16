<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Subjects_Actions
{
    public static function handle_save_subject(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_subject');

        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
        $code_raw = isset($_POST['code']) ? sanitize_text_field(wp_unslash((string) $_POST['code'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '';

        if ($name === '') {
            self::redirect_subjects_page([
                'cbt_msg' => 'Nama mapel wajib diisi.',
            ]);
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

        self::redirect_subjects_page([
            'cbt_msg' => $msg,
        ]);
    }

    public static function handle_delete_subject(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint(wp_unslash((string) $_GET['id'])) : 0;
        check_admin_referer('cbt_delete_subject_' . $id);

        $subject_per_page = CBT_Admin_Subjects_Service::normalize_standard_list_per_page(
            isset($_GET['cbt_subject_per_page']) ? absint(wp_unslash((string) $_GET['cbt_subject_per_page'])) : 20
        );
        $subject_filter_id = isset($_GET['cbt_subject_filter_id']) ? absint(wp_unslash((string) $_GET['cbt_subject_filter_id'])) : 0;
        $subject_paged = isset($_GET['cbt_subject_paged']) ? max(1, absint(wp_unslash((string) $_GET['cbt_subject_paged']))) : 1;
        $redirect_args = [
            'cbt_subject_per_page' => $subject_per_page,
            'cbt_subject_paged' => $subject_paged,
        ];
        if ($subject_filter_id > 0) {
            $redirect_args['cbt_subject_filter_id'] = $subject_filter_id;
        }

        if ($id > 0) {
            global $wpdb;
            $exam_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cbt_exams WHERE subject_id = %d",
                $id
            ));

            if ($exam_count > 0) {
                self::redirect_subjects_page($redirect_args + [
                    'cbt_msg' => 'Subject masih dipakai oleh ujian dan tidak bisa dihapus.',
                ]);
            }

            $wpdb->delete($wpdb->prefix . 'cbt_subjects', ['id' => $id], ['%d']);
        }

        CBT_Cache::invalidate_catalog();

        self::redirect_subjects_page($redirect_args + [
            'cbt_msg' => 'Subject deleted',
        ]);
    }

    public static function handle_bulk_delete_subjects(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_delete_subjects');

        global $wpdb;
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $bulk_mode = isset($_POST['bulk_mode']) ? sanitize_text_field(wp_unslash((string) $_POST['bulk_mode'])) : 'selected';
        $subject_per_page = CBT_Admin_Subjects_Service::normalize_standard_list_per_page(
            isset($_POST['cbt_subject_per_page']) ? absint(wp_unslash((string) $_POST['cbt_subject_per_page'])) : 20
        );
        $subject_filter_id = isset($_POST['cbt_subject_filter_id']) ? absint(wp_unslash((string) $_POST['cbt_subject_filter_id'])) : 0;
        $subject_paged = isset($_POST['cbt_subject_paged']) ? max(1, absint(wp_unslash((string) $_POST['cbt_subject_paged']))) : 1;
        $redirect_args = [
            'cbt_subject_per_page' => $subject_per_page,
            'cbt_subject_paged' => $subject_paged,
        ];
        if ($subject_filter_id > 0) {
            $redirect_args['cbt_subject_filter_id'] = $subject_filter_id;
        }

        if ($bulk_mode === 'all') {
            if ($subject_filter_id > 0) {
                $target_ids = [$subject_filter_id];
            } else {
                $target_ids = array_map('intval', (array) $wpdb->get_col("SELECT id FROM {$subject_table}"));
            }
        } else {
            $raw_subject_ids = isset($_POST['subject_ids']) && is_array($_POST['subject_ids']) ? wp_unslash($_POST['subject_ids']) : [];
            $target_ids = array_map('absint', $raw_subject_ids);
        }

        $target_ids = array_values(array_unique(array_filter($target_ids)));
        if (empty($target_ids)) {
            self::redirect_subjects_page($redirect_args + [
                'cbt_err' => 'Pilih minimal satu subject.',
            ]);
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
            self::redirect_subjects_page($redirect_args + [
                'cbt_err' => 'Tidak ada subject yang berhasil dihapus.',
            ]);
        }

        if ($deleted_count > 0) {
            CBT_Cache::invalidate_catalog();
        }

        self::redirect_subjects_page($redirect_args + [
            'cbt_msg' => implode(' | ', $messages),
        ]);
    }

    public static function handle_import_subjects(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        CBT_Admin_Subjects_Service::prepare_runtime_for_bulk_import();

        $token = isset($_GET['cbt_subject_import_token']) ? sanitize_key((string) wp_unslash((string) $_GET['cbt_subject_import_token'])) : '';
        if ($token !== '') {
            $result = CBT_Admin_Subjects_Service::continue_import($token);
            if (is_wp_error($result)) {
                self::redirect_subject_import_with_error($result->get_error_message());
            }

            if (($result['status'] ?? '') === 'continue' && !empty($result['token'])) {
                self::redirect_subjects_page([
                    'cbt_subject_import_token' => (string) $result['token'],
                ]);
            }

            self::redirect_subjects_page([
                'cbt_msg' => (string) ($result['message'] ?? 'Import subjects selesai.'),
            ]);
        }

        check_admin_referer('cbt_import_subjects');

        if (!isset($_FILES['subject_file']) || !is_array($_FILES['subject_file'])) {
            self::redirect_subject_import_with_error('File tidak ditemukan.');
        }

        $result = CBT_Admin_Subjects_Service::start_import((array) $_FILES['subject_file']);
        if (is_wp_error($result)) {
            self::redirect_subject_import_with_error($result->get_error_message());
        }

        self::redirect_subjects_page([
            'cbt_subject_import_token' => (string) ($result['token'] ?? ''),
        ]);
    }

    public static function handle_download_subject_template(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
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
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
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

    private static function redirect_subject_import_with_error(string $message): void
    {
        self::redirect_subjects_page([
            'cbt_err' => $message,
        ]);
    }

    private static function redirect_subjects_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-subjects'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
