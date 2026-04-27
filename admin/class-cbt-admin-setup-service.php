<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Setup_Service
{
    public static function can_manage_exams(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_exams');
    }

    public static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    public static function security_option_key(): string
    {
        if (!class_exists(CBT_Admin_Security_Service::class, false)) {
            require_once __DIR__ . '/class-cbt-admin-security-service.php';
        }

        return CBT_Admin_Security_Service::security_option_key();
    }

    /**
     * @return array{
     *     force_fullscreen:int,
     *     block_copy_paste:int,
     *     block_browser_inspection_shortcuts:int,
     *     log_security_events:int,
     *     security_redis_first_ingest:int,
     *     detect_idle_during_exam:int,
     *     detect_heartbeat_lost:int,
     *     idle_threshold_minutes:int,
     *     detect_screenshot_keys:int,
     *     show_exam_watermark:int,
     *     exam_watermark_opacity:float,
     *     restrict_student_user_agent:int,
     *     allowed_user_agents:array<int,string>
     * }
     */
    public static function get_security_settings(): array
    {
        if (!class_exists(CBT_Admin_Security_Service::class, false)) {
            require_once __DIR__ . '/class-cbt-admin-security-service.php';
        }

        return CBT_Admin_Security_Service::get_security_settings();
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_branding_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';

        $branding = CBT_Admin_Branding_Settings::get_settings();
        $exam_program_name = (string) ($branding['exam_program_name'] ?? '');
        $school_name = (string) ($branding['school_name'] ?? '');
        $school_motto = (string) ($branding['school_motto'] ?? '');
        $school_npsn = (string) ($branding['school_npsn'] ?? '');
        $school_address = (string) ($branding['school_address'] ?? '');
        $school_village = (string) ($branding['school_village'] ?? '');
        $school_district_city_ln = (string) ($branding['school_district_city_ln'] ?? '');
        $school_regency_country_ln = (string) ($branding['school_regency_country_ln'] ?? '');
        $school_regency_country_ln_is_city = !empty($branding['school_regency_country_ln_is_city']);
        $school_province_abroad_ln = (string) ($branding['school_province_abroad_ln'] ?? '');
        $school_province_abroad_ln_is_foreign = !empty($branding['school_province_abroad_ln_is_foreign']);

        $logo_1_attachment_id = (int) ($branding['logo_1_attachment_id'] ?? 0);
        $logo_1_url = self::resolve_logo_url($logo_1_attachment_id);
        $logo_2_attachment_id = (int) ($branding['logo_2_attachment_id'] ?? 0);
        $logo_2_url = self::resolve_logo_url($logo_2_attachment_id);

        return compact(
            'error',
            'exam_program_name',
            'logo_1_attachment_id',
            'logo_1_url',
            'logo_2_attachment_id',
            'logo_2_url',
            'notice',
            'school_address',
            'school_district_city_ln',
            'school_motto',
            'school_name',
            'school_npsn',
            'school_province_abroad_ln',
            'school_province_abroad_ln_is_foreign',
            'school_regency_country_ln',
            'school_regency_country_ln_is_city',
            'school_village'
        );
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_security_page_context(array $query): array
    {
        if (!class_exists(CBT_Admin_Security_Service::class, false)) {
            require_once __DIR__ . '/class-cbt-admin-security-service.php';
        }

        return CBT_Admin_Security_Service::build_page_context($query);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        return self::build_branding_page_context($query);
    }

    private static function resolve_logo_url(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        $resolved_url = wp_get_attachment_image_url($attachment_id, 'medium');
        return is_string($resolved_url) ? $resolved_url : '';
    }
}
