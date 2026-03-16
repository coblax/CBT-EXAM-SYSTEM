<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Setup_Service
{
    private const SECURITY_OPTION_KEY = 'cbt_setup_security';

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
        return self::SECURITY_OPTION_KEY;
    }

    /**
     * @return array{force_fullscreen:int,block_copy_paste:int,log_security_events:int}
     */
    public static function get_security_settings(): array
    {
        $raw = get_option(self::SECURITY_OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $force_fullscreen = !empty($raw['force_fullscreen']);
        $block_copy_paste = !empty($raw['block_copy_paste']);
        $log_security_events = !empty($raw['log_security_events']);

        return [
            'force_fullscreen' => $force_fullscreen ? 1 : 0,
            'block_copy_paste' => $block_copy_paste ? 1 : 0,
            'log_security_events' => $log_security_events ? 1 : 0,
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
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
        $school_province_abroad_ln = (string) ($branding['school_province_abroad_ln'] ?? '');

        $security = self::get_security_settings();
        $security_force_fullscreen = !empty($security['force_fullscreen']);
        $security_block_copy_paste = !empty($security['block_copy_paste']);
        $security_log_events_enabled = !empty($security['log_security_events']);
        $security_log_event_definitions = CBT_Security_Log::event_definitions();

        $teacher_scope = self::is_admin_scope() ? 0 : get_current_user_id();
        $security_log_must_watch_attempts = CBT_Security_Log::get_must_watch_attempts(5, [
            'teacher_id' => $teacher_scope,
        ]);
        $security_logs = CBT_Security_Log::get_recent_logs(20, [
            'teacher_id' => $teacher_scope,
        ]);

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
            'school_regency_country_ln',
            'school_village',
            'security_block_copy_paste',
            'security_force_fullscreen',
            'security_log_event_definitions',
            'security_log_events_enabled',
            'security_log_must_watch_attempts',
            'security_logs'
        );
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
