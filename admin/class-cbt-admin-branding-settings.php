<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Branding_Settings
{
    private const OPTION_KEY = 'cbt_setup_branding';

    public static function option_key(): string
    {
        return self::OPTION_KEY;
    }

    /**
     * @return array{
     *     exam_program_name:string,
     *     school_name:string,
     *     school_motto:string,
     *     school_npsn:string,
     *     school_address:string,
     *     school_village:string,
     *     school_district_city_ln:string,
     *     school_regency_country_ln:string,
     *     school_regency_country_ln_is_city:bool,
     *     school_province_abroad_ln:string,
     *     school_province_abroad_ln_is_foreign:bool,
     *     logo_attachment_id:int,
     *     logo_1_attachment_id:int,
     *     logo_2_attachment_id:int
     * }
     */
    public static function get_settings(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $exam_program_name = isset($raw['exam_program_name'])
            ? trim(sanitize_text_field((string) $raw['exam_program_name']))
            : '';
        $school_name = isset($raw['school_name'])
            ? trim(sanitize_text_field((string) $raw['school_name']))
            : '';
        $school_motto = isset($raw['school_motto'])
            ? trim(sanitize_text_field((string) $raw['school_motto']))
            : '';
        $school_npsn = isset($raw['school_npsn'])
            ? trim(sanitize_text_field((string) $raw['school_npsn']))
            : '';
        $school_address = isset($raw['school_address'])
            ? trim(sanitize_textarea_field((string) $raw['school_address']))
            : '';
        $school_village = isset($raw['school_village'])
            ? trim(sanitize_text_field((string) $raw['school_village']))
            : '';
        $school_district_city_ln = isset($raw['school_district_city_ln'])
            ? trim(sanitize_text_field((string) $raw['school_district_city_ln']))
            : '';
        $school_regency_country_ln = isset($raw['school_regency_country_ln'])
            ? trim(sanitize_text_field((string) $raw['school_regency_country_ln']))
            : '';
        $school_regency_country_ln_is_city = isset($raw['school_regency_country_ln_is_city'])
            && in_array((string) $raw['school_regency_country_ln_is_city'], ['1', 'true', 'yes'], true);
        $school_province_abroad_ln = isset($raw['school_province_abroad_ln'])
            ? trim(sanitize_text_field((string) $raw['school_province_abroad_ln']))
            : '';
        $school_province_abroad_ln_is_foreign = isset($raw['school_province_abroad_ln_is_foreign'])
            && in_array((string) $raw['school_province_abroad_ln_is_foreign'], ['1', 'true', 'yes'], true);
        $legacy_logo_attachment_id = isset($raw['logo_attachment_id']) ? absint($raw['logo_attachment_id']) : 0;
        if ($legacy_logo_attachment_id > 0 && !wp_attachment_is_image($legacy_logo_attachment_id)) {
            $legacy_logo_attachment_id = 0;
        }

        $logo_1_attachment_id = isset($raw['logo_1_attachment_id']) ? absint($raw['logo_1_attachment_id']) : $legacy_logo_attachment_id;
        if ($logo_1_attachment_id > 0 && !wp_attachment_is_image($logo_1_attachment_id)) {
            $logo_1_attachment_id = 0;
        }

        $logo_2_attachment_id = isset($raw['logo_2_attachment_id']) ? absint($raw['logo_2_attachment_id']) : 0;
        if ($logo_2_attachment_id > 0 && !wp_attachment_is_image($logo_2_attachment_id)) {
            $logo_2_attachment_id = 0;
        }

        return [
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
        ];
    }

    /**
     * @return array{
     *     exam_program_name:string,
     *     school_name:string,
     *     school_motto:string,
     *     school_npsn:string,
     *     school_address:string,
     *     school_village:string,
     *     school_district_city_ln:string,
     *     school_regency_country_ln:string,
     *     school_regency_country_ln_is_city:bool,
     *     school_province_abroad_ln:string,
     *     school_province_abroad_ln_is_foreign:bool,
     *     logo_url:string,
     *     logo_1_url:string,
     *     logo_2_url:string
     * }
     */
    public static function get_print_context(): array
    {
        $branding = self::get_settings();
        $exam_program_name = trim((string) ($branding['exam_program_name'] ?? ''));
        $school_name = trim((string) ($branding['school_name'] ?? ''));
        $school_motto = trim((string) ($branding['school_motto'] ?? ''));
        $school_npsn = trim((string) ($branding['school_npsn'] ?? ''));
        $school_address = trim((string) ($branding['school_address'] ?? ''));
        $school_village = trim((string) ($branding['school_village'] ?? ''));
        $school_district_city_ln = trim((string) ($branding['school_district_city_ln'] ?? ''));
        $school_regency_country_ln = trim((string) ($branding['school_regency_country_ln'] ?? ''));
        $school_regency_country_ln_is_city = !empty($branding['school_regency_country_ln_is_city']);
        $school_province_abroad_ln = trim((string) ($branding['school_province_abroad_ln'] ?? ''));
        $school_province_abroad_ln_is_foreign = !empty($branding['school_province_abroad_ln_is_foreign']);

        $logo_1_url = '';
        $logo_1_attachment_id = (int) ($branding['logo_1_attachment_id'] ?? 0);
        if ($logo_1_attachment_id > 0) {
            $resolved_logo_1_url = wp_get_attachment_image_url($logo_1_attachment_id, 'medium');
            if (is_string($resolved_logo_1_url)) {
                $logo_1_url = $resolved_logo_1_url;
            }
        }

        $logo_2_url = '';
        $logo_2_attachment_id = (int) ($branding['logo_2_attachment_id'] ?? 0);
        if ($logo_2_attachment_id > 0) {
            $resolved_logo_2_url = wp_get_attachment_image_url($logo_2_attachment_id, 'medium');
            if (is_string($resolved_logo_2_url)) {
                $logo_2_url = $resolved_logo_2_url;
            }
        }

        return [
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
            'logo_url' => $logo_1_url,
            'logo_1_url' => $logo_1_url,
            'logo_2_url' => $logo_2_url,
        ];
    }

    public static function normalize_regency_country_value(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $normalized);
        if (is_string($collapsed) && $collapsed !== '') {
            $normalized = trim($collapsed);
        }

        $normalized = preg_replace('/^(?:kabupaten|kab\.?|kab|kota)\s*/iu', '', $normalized) ?? $normalized;

        return trim($normalized, " \t\n\r\0\x0B.-");
    }

    public static function normalize_province_abroad_value(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $normalized);
        if (is_string($collapsed) && $collapsed !== '') {
            $normalized = trim($collapsed);
        }

        $normalized = preg_replace('/^(?:provinsi|propinsi|prov\.?|luar\s+negeri|ln)\s*/iu', '', $normalized) ?? $normalized;

        return trim($normalized, " \t\n\r\0\x0B.-");
    }
}
