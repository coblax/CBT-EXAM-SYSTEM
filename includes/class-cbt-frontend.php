<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Frontend
{
    private const SHORTCODE = 'cbt_exam_frontend';
    private const SETUP_BRANDING_OPTION = 'cbt_setup_branding';
    private const SETUP_SECURITY_OPTION = 'cbt_setup_security';

    /** @var bool */
    private static $localized = false;

    public static function init(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
        add_filter('body_class', [self::class, 'filter_body_class']);
        add_action('template_redirect', [self::class, 'send_nocache_headers']);
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
            $security = self::get_setup_security_config();

            wp_localize_script('cbt-frontend-app', 'CBTExamFrontendConfig', [
                'restBase' => $rest_base_absolute,
                'restBasePath' => $rest_base_path,
                'siteName' => $branding['school_name'],
                'schoolName' => $branding['school_name'],
                'schoolMotto' => $branding['school_motto'],
                'schoolLogoUrl' => $branding['logo_url'],
                'schoolLogo1Url' => $branding['logo_1_url'],
                'schoolLogo2Url' => $branding['logo_2_url'],
                'pluginAuthor' => 'COBLAX',
                'pluginVersion' => CBT_EXAM_SYSTEM_VERSION,
                'securityForceFullscreen' => $security['force_fullscreen'],
                'securityBlockCopyPaste' => $security['block_copy_paste'],
                'securityLogEvents' => $security['log_security_events'],
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

    public static function send_nocache_headers(): void
    {
        if (!self::is_frontend_shortcode_page()) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }
        if (!defined('DONOTMINIFY')) {
            define('DONOTMINIFY', true);
        }
        if (!defined('DONOTCDN')) {
            define('DONOTCDN', true);
        }

        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Vary: Cookie', false);
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
     * @return array{school_name:string,school_motto:string,logo_url:string,logo_1_url:string,logo_2_url:string}
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
        $school_motto = isset($raw['school_motto'])
            ? trim(sanitize_text_field((string) $raw['school_motto']))
            : '';

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

        $logo_1_url = '';
        if ($logo_1_attachment_id > 0) {
            $resolved_logo_1_url = wp_get_attachment_image_url($logo_1_attachment_id, 'medium');
            if (is_string($resolved_logo_1_url)) {
                $logo_1_url = $resolved_logo_1_url;
            }
        }

        $logo_2_url = '';
        if ($logo_2_attachment_id > 0) {
            $resolved_logo_2_url = wp_get_attachment_image_url($logo_2_attachment_id, 'medium');
            if (is_string($resolved_logo_2_url)) {
                $logo_2_url = $resolved_logo_2_url;
            }
        }

        return [
            'school_name' => $school_name,
            'school_motto' => $school_motto,
            'logo_url' => $logo_1_url,
            'logo_1_url' => $logo_1_url,
            'logo_2_url' => $logo_2_url,
        ];
    }

    /**
     * @return array{force_fullscreen:int,block_copy_paste:int,log_security_events:int}
     */
    private static function get_setup_security_config(): array
    {
        $raw = get_option(self::SETUP_SECURITY_OPTION, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'force_fullscreen' => !empty($raw['force_fullscreen']) ? 1 : 0,
            'block_copy_paste' => !empty($raw['block_copy_paste']) ? 1 : 0,
            'log_security_events' => !empty($raw['log_security_events']) ? 1 : 0,
        ];
    }
}
