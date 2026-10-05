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

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
        $code_raw = isset($_POST['code']) ? sanitize_text_field(wp_unslash((string) $_POST['code'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '';
        $list_state = self::read_list_state($_POST);

        $result = CBT_Admin_Subjects_Service::save_subject_record($id, $name, $code_raw, $description);
        if (is_wp_error($result)) {
            // Tetap di mode edit supaya fallback tanpa JS tidak membuang konteks subject yang sedang diubah.
            self::redirect_subjects_page($list_state + ($id > 0 ? ['edit' => $id] : ['cbt_subject_tab' => 'form']) + [
                'cbt_err' => $result->get_error_message(),
            ]);
        }

        CBT_Cache::invalidate_catalog();

        self::redirect_subjects_page($list_state + [
            'cbt_subject_tab' => 'list',
            'cbt_msg' => (string) ($result['message'] ?? ($id > 0 ? 'Subject diperbarui.' : 'Subject ditambahkan.')),
        ]);
    }

    public static function handle_delete_subject(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint(wp_unslash((string) $_GET['id'])) : 0;
        check_admin_referer('cbt_delete_subject_' . $id);

        $redirect_args = self::read_list_state($_GET) + ['cbt_subject_tab' => 'list'];
        if ($id <= 0) {
            self::redirect_subjects_page($redirect_args + [
                'cbt_err' => 'Subject tidak valid.',
            ]);
        }

        $result = CBT_Admin_Subjects_Service::delete_subject($id);
        if (($result['status'] ?? '') !== 'deleted') {
            self::redirect_subjects_page($redirect_args + [
                'cbt_err' => (string) ($result['message'] ?? 'Subject gagal dihapus.'),
            ]);
        }

        CBT_Cache::invalidate_catalog();

        self::redirect_subjects_page($redirect_args + [
            'cbt_msg' => (string) ($result['message'] ?? 'Subject dihapus.'),
        ]);
    }

    public static function handle_bulk_delete_subjects(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_delete_subjects');

        $bulk_mode = isset($_POST['bulk_mode']) ? sanitize_key(wp_unslash((string) $_POST['bulk_mode'])) : 'selected';
        $list_state = self::read_list_state($_POST);
        $redirect_args = $list_state + ['cbt_subject_tab' => 'list'];

        if ($bulk_mode === 'all') {
            $target_ids = CBT_Admin_Subjects_Service::get_subject_ids_matching_search((string) ($list_state['cbt_subject_q'] ?? ''));
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
        $failed_count = 0;

        foreach ($target_ids as $subject_id) {
            $result = CBT_Admin_Subjects_Service::delete_subject((int) $subject_id);
            $status = (string) ($result['status'] ?? '');
            if ($status === 'deleted') {
                $deleted_count++;
            } elseif ($status === 'in_use') {
                $blocked_count++;
            } elseif ($status !== 'not_found') {
                $failed_count++;
            }
        }

        if ($deleted_count > 0) {
            CBT_Cache::invalidate_catalog();
        }

        $messages = [];
        if ($deleted_count > 0) {
            $messages[] = sprintf('%d subject dihapus', $deleted_count);
        }
        if ($blocked_count > 0) {
            $messages[] = sprintf('%d dilewati karena masih dipakai ujian/bank soal', $blocked_count);
        }
        if ($failed_count > 0) {
            $messages[] = sprintf('%d gagal dihapus', $failed_count);
        }

        if ($deleted_count === 0) {
            self::redirect_subjects_page($redirect_args + [
                'cbt_err' => !empty($messages)
                    ? 'Tidak ada subject yang dihapus: ' . implode(', ', $messages) . '.'
                    : 'Tidak ada subject yang dihapus.',
            ]);
        }

        self::redirect_subjects_page($redirect_args + [
            'cbt_msg' => implode(', ', $messages) . '.',
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
                'cbt_subject_import_done' => 1,
                'cbt_msg' => (string) ($result['message'] ?? 'Import subject selesai.'),
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
            'cbt_subject_tab' => 'import',
            'cbt_err' => $message,
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,int|string>
     */
    private static function read_list_state(array $source): array
    {
        $state = [
            'cbt_subject_per_page' => CBT_Admin_Subjects_Service::normalize_standard_list_per_page(
                isset($source['cbt_subject_per_page']) ? absint(wp_unslash((string) $source['cbt_subject_per_page'])) : 20
            ),
            'cbt_subject_paged' => isset($source['cbt_subject_paged']) ? max(1, absint(wp_unslash((string) $source['cbt_subject_paged']))) : 1,
        ];
        $search = CBT_Admin_Subjects_Service::normalize_search_query($source['cbt_subject_q'] ?? '');
        if ($search !== '') {
            $state['cbt_subject_q'] = $search;
        }

        return $state;
    }

    private static function redirect_subjects_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-subjects'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
