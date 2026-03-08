<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Frontend
{
    private const SHORTCODE = 'cbt_exam_frontend';
    private const SETUP_BRANDING_OPTION = 'cbt_setup_branding';

    /** @var bool */
    private static $localized = false;

    public static function init(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
        add_filter('body_class', [self::class, 'filter_body_class']);
    }

    /**
     * @param array<string,mixed> $atts
     */
    public static function render_shortcode(array $atts = []): string
    {
        self::enqueue_assets();

        if (!self::$localized) {
            $rest_base_absolute = trailingslashit((string) rest_url('cbt/v1'));
            $rest_base_path = (string) wp_parse_url($rest_base_absolute, PHP_URL_PATH);
            $rest_base_path = trailingslashit($rest_base_path !== '' ? $rest_base_path : '/wp-json/cbt/v1/');
            $branding = self::get_setup_branding_config();

            wp_localize_script('cbt-frontend-app', 'CBTExamFrontendConfig', [
                'restBase' => $rest_base_absolute,
                'restBasePath' => $rest_base_path,
                'siteName' => $branding['school_name'],
                'schoolName' => $branding['school_name'],
                'schoolLogoUrl' => $branding['logo_url'],
                'homeUrl' => (string) home_url('/'),
                'tokenMinLength' => 6,
                'tokenLength' => 6,
            ]);
            self::$localized = true;
        }

        ob_start();
        ?>
        <div id="cbt-exam-app" class="cbt-web-shell"></div>
        <noscript>
            <div class="cbt-web-noscript">JavaScript harus aktif untuk menggunakan CBT frontend.</div>
        </noscript>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,string> $classes
     * @return array<int,string>
     */
    public static function filter_body_class(array $classes): array
    {
        if (self::is_frontend_shortcode_page()) {
            $classes[] = 'cbt-exam-page';
        }

        return $classes;
    }

    private static function enqueue_assets(): void
    {
        $style_relative = 'public/css/cbt-frontend.css';
        $script_relative = 'public/js/cbt-frontend.js';

        wp_enqueue_style(
            'cbt-frontend-app',
            CBT_EXAM_SYSTEM_URL . $style_relative,
            [],
            self::asset_version($style_relative)
        );

        wp_enqueue_script(
            'cbt-frontend-app',
            CBT_EXAM_SYSTEM_URL . $script_relative,
            [],
            self::asset_version($script_relative),
            true
        );
    }

    private static function asset_version(string $relative_path): string
    {
        $absolute = CBT_EXAM_SYSTEM_PATH . $relative_path;
        if (file_exists($absolute)) {
            return (string) filemtime($absolute);
        }

        return CBT_EXAM_SYSTEM_VERSION;
    }

    private static function is_frontend_shortcode_page(): bool
    {
        if (!is_singular()) {
            return false;
        }

        $post = get_queried_object();
        if (!($post instanceof WP_Post)) {
            return false;
        }

        return has_shortcode((string) $post->post_content, self::SHORTCODE);
    }

    /**
     * @return array{school_name:string,logo_url:string}
     */
    private static function get_setup_branding_config(): array
    {
        $site_name = trim((string) get_bloginfo('name'));
        $raw = get_option(self::SETUP_BRANDING_OPTION, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $school_name = isset($raw['school_name'])
            ? trim(sanitize_text_field((string) $raw['school_name']))
            : '';
        if ($school_name === '') {
            $school_name = $site_name;
        }
        if ($school_name === '') {
            $school_name = 'CBT Exam';
        }

        $logo_url = '';
        $logo_attachment_id = isset($raw['logo_attachment_id']) ? absint($raw['logo_attachment_id']) : 0;
        if ($logo_attachment_id > 0 && wp_attachment_is_image($logo_attachment_id)) {
            $resolved_logo_url = wp_get_attachment_image_url($logo_attachment_id, 'medium');
            if (is_string($resolved_logo_url)) {
                $logo_url = $resolved_logo_url;
            }
        }

        return [
            'school_name' => $school_name,
            'logo_url' => $logo_url,
        ];
    }
}
