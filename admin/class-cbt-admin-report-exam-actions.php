<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Report_Exam_Actions
{
    public static function handle_export_exam_report_pdf(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_exam_report_pdf');

        $context = CBT_Admin_Report_Exam_Service::build_print_context($_POST);
        if (is_wp_error($context)) {
            $error_data = $context->get_error_data();
            $redirect_args = is_array($error_data) && isset($error_data['redirect_args']) && is_array($error_data['redirect_args'])
                ? $error_data['redirect_args']
                : [];
            self::redirect_report_exam_page($redirect_args + ['cbt_err' => $context->get_error_message()]);
        }

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        extract($context, EXTR_SKIP);
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/report-exam/print.php';
        exit;
    }

    public static function handle_save_exam_incident(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_exam_incident');

        $is_admin_scope = CBT_Admin_Report_Exam_Service::is_admin_scope();
        $current_user_id = get_current_user_id();
        $submission = CBT_Admin_Report_Exam_Service::resolve_report_incident_submission($_POST, $is_admin_scope, $current_user_id);
        if (is_wp_error($submission)) {
            $context = CBT_Admin_Report_Exam_Service::get_report_incident_context_from_request($_POST);
            self::redirect_report_incident($context, null, $submission->get_error_message());
        }

        $context = (array) ($submission['context'] ?? []);
        $student = (array) ($submission['student'] ?? []);
        $staff = (array) ($submission['staff'] ?? []);
        $now = current_time('mysql');

        $incident_id = CBT_Incident_Report::insert([
            'exam_id' => (int) ($context['exam_id'] ?? 0),
            'student_id' => (int) ($student['id'] ?? 0),
            'incident_type' => (string) ($submission['incident_type'] ?? ''),
            'incident_at' => $now,
            'notes' => (string) ($submission['notes'] ?? ''),
            'staff_user_id' => (int) ($staff['id'] ?? 0),
            'student_name_snapshot' => (string) ($student['name'] ?? ''),
            'student_kelas_snapshot' => (string) ($student['kelas'] ?? ''),
            'student_ruang_snapshot' => (string) ($student['ruang'] ?? ''),
            'staff_name_snapshot' => (string) ($staff['name'] ?? ''),
            'created_by' => $current_user_id,
            'updated_by' => $current_user_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($incident_id === false || $incident_id <= 0) {
            self::redirect_report_incident($context, null, 'Gagal menyimpan incident report.');
        }

        $context['edit_id'] = 0;
        self::redirect_report_incident($context, 'Incident berhasil disimpan.');
    }

    public static function handle_update_exam_incident(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $incident_id = isset($_POST['incident_id']) ? absint($_POST['incident_id']) : 0;
        $context = CBT_Admin_Report_Exam_Service::get_report_incident_context_from_request($_POST);
        if ($incident_id <= 0) {
            self::redirect_report_incident($context, null, 'Incident tidak valid.');
        }

        check_admin_referer('cbt_update_exam_incident_' . $incident_id);

        $is_admin_scope = CBT_Admin_Report_Exam_Service::is_admin_scope();
        $current_user_id = get_current_user_id();
        $scope_filters = CBT_Admin_Report_Exam_Service::get_report_incident_scope_filters($is_admin_scope, $current_user_id);
        $existing_incident = CBT_Incident_Report::get_row($incident_id, $scope_filters);
        if (empty($existing_incident)) {
            self::redirect_report_incident($context, null, 'Incident tidak ditemukan atau tidak bisa diakses.');
        }

        $submission = CBT_Admin_Report_Exam_Service::resolve_report_incident_submission($_POST, $is_admin_scope, $current_user_id);
        if (is_wp_error($submission)) {
            $context['edit_id'] = $incident_id;
            self::redirect_report_incident($context, null, $submission->get_error_message());
        }

        $submission_context = (array) ($submission['context'] ?? []);
        if ((int) ($existing_incident['exam_id'] ?? 0) !== (int) ($submission_context['exam_id'] ?? 0)) {
            $context['edit_id'] = 0;
            self::redirect_report_incident($context, null, 'Incident tidak bisa dipindahkan ke exam lain dari mode edit ini.');
        }

        $student = (array) ($submission['student'] ?? []);
        $staff = (array) ($submission['staff'] ?? []);
        $updated = CBT_Incident_Report::update($incident_id, [
            'student_id' => (int) ($student['id'] ?? 0),
            'incident_type' => (string) ($submission['incident_type'] ?? ''),
            'incident_at' => (string) ($existing_incident['incident_at'] ?? current_time('mysql')),
            'notes' => (string) ($submission['notes'] ?? ''),
            'staff_user_id' => (int) ($staff['id'] ?? 0),
            'student_name_snapshot' => (string) ($student['name'] ?? ''),
            'student_kelas_snapshot' => (string) ($student['kelas'] ?? ''),
            'student_ruang_snapshot' => (string) ($student['ruang'] ?? ''),
            'staff_name_snapshot' => (string) ($staff['name'] ?? ''),
            'updated_by' => $current_user_id,
            'updated_at' => current_time('mysql'),
        ]);

        if (!$updated) {
            $context['edit_id'] = $incident_id;
            self::redirect_report_incident($context, null, 'Gagal memperbarui incident report.');
        }

        $context = $submission_context;
        $context['edit_id'] = 0;
        self::redirect_report_incident($context, 'Incident berhasil diperbarui.');
    }

    public static function handle_delete_exam_incident(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $incident_id = isset($_POST['incident_id']) ? absint($_POST['incident_id']) : 0;
        $context = CBT_Admin_Report_Exam_Service::get_report_incident_context_from_request($_POST);
        if ($incident_id <= 0) {
            self::redirect_report_incident($context, null, 'Incident tidak valid.');
        }

        check_admin_referer('cbt_delete_exam_incident_' . $incident_id);

        $is_admin_scope = CBT_Admin_Report_Exam_Service::is_admin_scope();
        $current_user_id = get_current_user_id();
        $scope_filters = CBT_Admin_Report_Exam_Service::get_report_incident_scope_filters($is_admin_scope, $current_user_id);
        $existing_incident = CBT_Incident_Report::get_row($incident_id, $scope_filters);
        if (empty($existing_incident)) {
            self::redirect_report_incident($context, null, 'Incident tidak ditemukan atau tidak bisa dihapus.');
        }

        $deleted = CBT_Incident_Report::delete($incident_id);
        if (!$deleted) {
            self::redirect_report_incident($context, null, 'Gagal menghapus incident report.');
        }

        $context['edit_id'] = 0;
        self::redirect_report_incident($context, 'Incident berhasil dihapus.');
    }

    /**
     * @param array{exam_id:int,kelas:string,ruang:string,edit_id:int} $context
     */
    private static function redirect_report_incident(array $context, ?string $message = null, ?string $error = null): void
    {
        $args = [
            'page' => 'cbt-report-exam',
            'cbt_report_tab' => 'incident-report',
        ];

        if (!empty($context['exam_id'])) {
            $args['cbt_incident_exam_id'] = (int) $context['exam_id'];
        }
        if (!empty($context['kelas'])) {
            $args['cbt_incident_kelas'] = (string) $context['kelas'];
        }
        if (!empty($context['ruang'])) {
            $args['cbt_incident_ruang'] = (string) $context['ruang'];
        }
        if (!empty($context['edit_id'])) {
            $args['cbt_incident_edit_id'] = (int) $context['edit_id'];
        }
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        self::redirect_report_exam_page($args);
    }

    private static function redirect_report_exam_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-report-exam'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
