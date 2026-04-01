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
        $school_regency_country_ln_is_city = isset($_POST['school_regency_country_ln_is_city']) ? 1 : 0;
        $school_province_abroad_ln = isset($_POST['school_province_abroad_ln'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['school_province_abroad_ln'])))
            : '';
        $school_province_abroad_ln_is_foreign = isset($_POST['school_province_abroad_ln_is_foreign']) ? 1 : 0;
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
                'school_regency_country_ln_is_city' => $school_regency_country_ln_is_city,
                'school_province_abroad_ln' => $school_province_abroad_ln,
                'school_province_abroad_ln_is_foreign' => $school_province_abroad_ln_is_foreign,
                'logo_attachment_id' => $logo_1_attachment_id,
                'logo_1_attachment_id' => $logo_1_attachment_id,
                'logo_2_attachment_id' => $logo_2_attachment_id,
            ],
            false
        );

        wp_safe_redirect(admin_url('admin.php?page=cbt-setup&cbt_msg=' . rawurlencode('CBT Branding berhasil disimpan.')));
        exit;
    }

    public static function handle_save_setup_security(): void
    {
        CBT_Admin_Security_Actions::handle_save_setup_security();
    }

    public static function handle_manage_security_logs(): void
    {
        CBT_Admin_Security_Actions::handle_manage_security_logs();
    }
}
