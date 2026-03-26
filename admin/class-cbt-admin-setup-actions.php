<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Setup_Actions
{
    public static function handle_save_setup_branding(): void
    {
        if (!CBT_Admin_Setup_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_setup_branding');

        $exam_program_name = isset($_POST['exam_program_name'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['exam_program_name'])))
            : '';
        $school_name = isset($_POST['school_name'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_name'])))
            : '';
        $school_motto = isset($_POST['school_motto'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_motto'])))
            : '';
        $school_npsn = isset($_POST['school_npsn'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_npsn'])))
            : '';
        $school_address = isset($_POST['school_address'])
            ? trim(sanitize_textarea_field(wp_unslash((string) $_POST['school_address'])))
            : '';
        $school_village = isset($_POST['school_village'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_village'])))
            : '';
        $school_district_city_ln = isset($_POST['school_district_city_ln'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_district_city_ln'])))
            : '';
        $school_regency_country_ln = isset($_POST['school_regency_country_ln'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_regency_country_ln'])))
            : '';
        $school_province_abroad_ln = isset($_POST['school_province_abroad_ln'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_province_abroad_ln'])))
            : '';
        $logo_1_attachment_id = isset($_POST['logo_1_attachment_id'])
            ? absint($_POST['logo_1_attachment_id'])
            : (isset($_POST['logo_attachment_id']) ? absint($_POST['logo_attachment_id']) : 0);
        if ($logo_1_attachment_id > 0 && !wp_attachment_is_image($logo_1_attachment_id)) {
            $logo_1_attachment_id = 0;
        }
        $logo_2_attachment_id = isset($_POST['logo_2_attachment_id']) ? absint($_POST['logo_2_attachment_id']) : 0;
        if ($logo_2_attachment_id > 0 && !wp_attachment_is_image($logo_2_attachment_id)) {
            $logo_2_attachment_id = 0;
        }

        update_option(
            CBT_Admin_Branding_Settings::option_key(),
            [
                'exam_program_name' => $exam_program_name,
                'school_name' => $school_name,
                'school_motto' => $school_motto,
                'school_npsn' => $school_npsn,
                'school_address' => $school_address,
                'school_village' => $school_village,
                'school_district_city_ln' => $school_district_city_ln,
                'school_regency_country_ln' => $school_regency_country_ln,
                'school_province_abroad_ln' => $school_province_abroad_ln,
                'logo_attachment_id' => $logo_1_attachment_id,
                'logo_1_attachment_id' => $logo_1_attachment_id,
                'logo_2_attachment_id' => $logo_2_attachment_id,
            ],
            false
        );

        wp_safe_redirect(admin_url('admin.php?page=cbt-setup&cbt_msg=' . rawurlencode('Setup branding berhasil disimpan.')));
        exit;
    }

    public static function handle_save_setup_security(): void
    {
        if (!CBT_Admin_Setup_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_setup_security');

        $force_fullscreen = isset($_POST['force_fullscreen']) && (string) wp_unslash($_POST['force_fullscreen']) === '1';
        $block_copy_paste = isset($_POST['block_copy_paste']) && (string) wp_unslash($_POST['block_copy_paste']) === '1';
        $log_security_events = isset($_POST['log_security_events']) && (string) wp_unslash($_POST['log_security_events']) === '1';
        $detect_idle_during_exam = isset($_POST['detect_idle_during_exam']) && (string) wp_unslash($_POST['detect_idle_during_exam']) === '1';
        $idle_threshold_minutes = isset($_POST['idle_threshold_minutes'])
            ? max(1, absint(wp_unslash($_POST['idle_threshold_minutes'])))
            : 5;

        update_option(
            CBT_Admin_Setup_Service::security_option_key(),
            [
                'force_fullscreen' => $force_fullscreen ? 1 : 0,
                'block_copy_paste' => $block_copy_paste ? 1 : 0,
                'log_security_events' => $log_security_events ? 1 : 0,
                'detect_idle_during_exam' => $detect_idle_during_exam ? 1 : 0,
                'idle_threshold_minutes' => $idle_threshold_minutes,
            ],
            false
        );

        wp_safe_redirect(
            admin_url('admin.php?page=cbt-setup&cbt_msg=' . rawurlencode('Pengaturan security berhasil disimpan.')) . '#security'
        );
        exit;
    }

    public static function handle_manage_security_logs(): void
    {
        if (!CBT_Admin_Setup_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_manage_security_logs');

        $teacher_id = CBT_Admin_Setup_Service::is_admin_scope() ? 0 : get_current_user_id();
        $delete_scope = isset($_POST['delete_scope'])
            ? sanitize_key((string) wp_unslash($_POST['delete_scope']))
            : '';

        $redirect_url = admin_url('admin.php?page=cbt-setup');
        $redirect_suffix = '#security-log';

        if ($delete_scope === 'selected') {
            $selected_log_ids = isset($_POST['selected_log_ids']) && is_array($_POST['selected_log_ids'])
                ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['selected_log_ids'])))))
                : [];

            if (empty($selected_log_ids)) {
                wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Pilih minimal satu security log untuk dihapus.') . $redirect_suffix);
                exit;
            }

            $deleted_count = CBT_Security_Log::delete_logs($selected_log_ids, [
                'teacher_id' => $teacher_id,
            ]);

            if ($deleted_count > 0) {
                wp_safe_redirect($redirect_url . '&cbt_msg=' . rawurlencode(sprintf('Security log berhasil dihapus: %d.', $deleted_count)) . $redirect_suffix);
                exit;
            }

            wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Log yang dipilih tidak ditemukan atau tidak bisa dihapus.') . $redirect_suffix);
            exit;
        }

        if ($delete_scope === 'all') {
            $deleted_count = CBT_Security_Log::delete_all_logs([
                'teacher_id' => $teacher_id,
            ]);

            if ($deleted_count > 0) {
                wp_safe_redirect($redirect_url . '&cbt_msg=' . rawurlencode(sprintf('Semua security log berhasil dihapus: %d.', $deleted_count)) . $redirect_suffix);
                exit;
            }

            wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Tidak ada security log yang bisa dihapus.') . $redirect_suffix);
            exit;
        }

        wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Aksi security log tidak dikenali.') . $redirect_suffix);
        exit;
    }
}
