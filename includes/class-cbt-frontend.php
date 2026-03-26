<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Frontend
{
    private const SHORTCODE = 'cbt_exam_frontend';
    private const FRONTEND_PAGE_OPTION = 'cbt_exam_system_frontend_page_id';
    private const FRONTEND_PAGE_SLUG = 'cbt-ujian';
    private const MINIMAL_TEMPLATE_RELATIVE = 'templates/frontend/minimal-template.php';
    private const SETUP_BRANDING_OPTION = 'cbt_setup_branding';
    private const SETUP_SECURITY_OPTION = 'cbt_setup_security';
    private const FRONTEND_HANDLE = 'cbt-frontend-app';
    private const DEV_CLIENT_HANDLE = 'cbt-frontend-vite-client';
    private const VITE_ENTRY = 'src/frontend/main.js';
    private const VITE_BUILD_DIR = 'public/build/';
    private const VITE_MANIFEST_RELATIVE = 'public/build/manifest.json';

    /** @var bool */
    private static $localized = false;
    /** @var array<string,mixed>|null */
    private static $viteManifest = null;
    /** @var string */
    private static $assetBootstrapError = '';
    /** @var bool */
    private static $assetsPrepared = false;
    /** @var bool */
    private static $noiseSuppressed = false;

    public static function init(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
        add_filter('body_class', [self::class, 'filter_body_class']);
        add_filter('show_admin_bar', [self::class, 'filter_show_admin_bar']);
        add_filter('template_include', [self::class, 'filter_template_include'], 99);
        add_action('template_redirect', [self::class, 'send_nocache_headers']);
        add_action('wp_enqueue_scripts', [self::class, 'prepare_frontend_request'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'strip_frontend_assets'], PHP_INT_MAX);
        add_action('wp_print_styles', [self::class, 'strip_frontend_assets'], PHP_INT_MAX);
        add_action('wp_print_scripts', [self::class, 'strip_frontend_assets'], PHP_INT_MAX);
        add_action('wp_print_footer_scripts', [self::class, 'strip_frontend_assets'], 0);
        add_filter('script_loader_tag', [self::class, 'filter_script_loader_tag'], 10, 3);
        add_filter('script_loader_src', [self::class, 'filter_asset_loader_src'], 10, 2);
        add_filter('style_loader_src', [self::class, 'filter_asset_loader_src'], 10, 2);
    }

    /**
     * @param array<string,mixed> $atts
     */
    public static function render_shortcode(array $atts = []): string
    {
        unset($atts);
        self::ensure_frontend_assets_prepared();
        return self::render_frontend_markup(false);
    }

    /**
     * @param array<int,string> $classes
     * @return array<int,string>
     */
    public static function filter_body_class(array $classes): array
    {
        if (self::is_frontend_request_context()) {
            $classes[] = 'cbt-exam-page';
        }
        if (self::is_canonical_frontend_page()) {
            $classes[] = 'cbt-exam-page-canonical';
        }
        if (self::is_fallback_frontend_shortcode_page()) {
            $classes[] = 'cbt-exam-page-fallback';
        }

        return $classes;
    }

    public static function filter_show_admin_bar(bool $show): bool
    {
        if (self::is_canonical_frontend_page()) {
            return false;
        }

        return $show;
    }

    public static function send_nocache_headers(): void
    {
        if (!self::is_frontend_request_context()) {
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

    public static function prepare_frontend_request(): void
    {
        if (!self::is_frontend_request_context()) {
            return;
        }

        self::suppress_frontend_noise();
        self::ensure_frontend_assets_prepared();
    }

    public static function strip_frontend_assets(): void
    {
        if (!self::is_frontend_request_context()) {
            return;
        }

        if (self::is_canonical_frontend_page()) {
            self::strip_assets_by_whitelist();
            return;
        }

        self::strip_fallback_frontend_noise_handles();
    }

    public static function filter_template_include(string $template): string
    {
        if (!self::is_canonical_frontend_page()) {
            return $template;
        }

        $minimal_template = CBT_EXAM_SYSTEM_PATH . self::MINIMAL_TEMPLATE_RELATIVE;
        if (file_exists($minimal_template)) {
            return $minimal_template;
        }

        return $template;
    }

    public static function render_canonical_frontend_shell(): string
    {
        self::ensure_frontend_assets_prepared();
        return self::render_frontend_markup(true);
    }

    public static function render_canonical_critical_css(): string
    {
        return '<style id="cbt-frontend-critical-css">'
            . 'html{background:linear-gradient(180deg,#e8f0ff 0%,#edf4ff 56%,#f4f8ff 100%);min-height:100%;}'
            . 'body.cbt-exam-page-canonical{margin:0;min-height:100vh;background:transparent;color:#16324f;font-family:"Source Sans 3",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
            . '.cbt-frontpage{min-height:100vh;display:flex;flex-direction:column;}'
            . '.cbt-frontpage__skip{position:absolute;left:-9999px;top:0;}'
            . '.cbt-frontpage__skip:focus{left:16px;top:16px;z-index:1000;background:#fff;border-radius:999px;padding:10px 14px;color:#0f4eb3;text-decoration:none;box-shadow:0 8px 18px rgba(15,78,179,.18);}'
            . '.cbt-frontpage__shell{flex:1;display:grid;place-items:center;padding:32px 18px;}'
            . '.cbt-boot-shell{width:min(100%,1180px);display:grid;gap:22px;}'
            . '.cbt-boot-shell--compact{width:min(100%,560px);}'
            . '.cbt-boot-shell__hero{display:flex;align-items:center;gap:16px;padding:20px 24px;border-radius:24px;background:linear-gradient(135deg,#0f6fd7 0%,#2f8ef2 52%,#5da8ff 100%);color:#fff;box-shadow:0 24px 52px rgba(18,86,176,.22);}'
            . '.cbt-boot-shell__logos{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}'
            . '.cbt-boot-shell__logo{width:56px;height:56px;object-fit:contain;border-radius:16px;background:rgba(255,255,255,.14);padding:8px;backdrop-filter:blur(6px);}'
            . '.cbt-boot-shell__brand{display:grid;gap:2px;min-width:0;}'
            . '.cbt-boot-shell__eyebrow{font-size:12px;letter-spacing:.18em;text-transform:uppercase;opacity:.85;font-weight:700;}'
            . '.cbt-boot-shell__title{font-size:clamp(28px,4vw,44px);line-height:1.02;font-weight:800;letter-spacing:-.03em;margin:0;}'
            . '.cbt-boot-shell__motto{font-size:15px;opacity:.92;margin:0;}'
            . '.cbt-boot-shell__card{background:rgba(255,255,255,.92);border:1px solid rgba(162,191,229,.45);border-radius:28px;padding:28px 24px;box-shadow:0 18px 44px rgba(35,73,132,.12);backdrop-filter:blur(12px);}'
            . '.cbt-boot-shell__card--compact{padding:18px;}'
            . '.cbt-boot-shell__grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);align-items:start;}'
            . '.cbt-boot-shell__block{padding:20px;border-radius:22px;background:linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%);border:1px solid rgba(176,200,233,.48);}'
            . '.cbt-boot-shell__app--compact{gap:14px;}'
            . '.cbt-boot-shell__block h2{margin:0 0 8px;font-size:24px;line-height:1.05;font-weight:800;letter-spacing:-.02em;color:#143861;}'
            . '.cbt-boot-shell__block p{margin:0;color:#506a86;font-size:16px;line-height:1.55;}'
            . '.cbt-boot-shell__progress{position:relative;overflow:hidden;height:14px;margin-top:18px;border-radius:999px;background:#dbe7fb;box-shadow:inset 0 1px 2px rgba(15,23,42,.08);}'
            . '.cbt-boot-shell__progress-fill{display:block;width:12%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#2271b1 0%,#5da8ff 100%);box-shadow:0 10px 24px rgba(34,113,177,.24);transition:width .28s ease;}'
            . '.cbt-boot-shell__progress-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px;font-size:13px;line-height:1.45;color:#4c6480;flex-wrap:wrap;}'
            . '.cbt-boot-shell__progress-label{font-weight:700;color:#1d4f8f;}'
            . '.cbt-boot-shell__progress-value{font-weight:800;color:#19385b;}'
            . '.cbt-boot-shell__status{display:inline-flex;align-items:center;gap:10px;padding:10px 14px;border-radius:999px;background:#fff;border:1px solid rgba(180,199,226,.78);font-size:13px;font-weight:700;color:#1f4fa2;letter-spacing:.08em;text-transform:uppercase;}'
            . '.cbt-boot-shell__dot{width:10px;height:10px;border-radius:999px;background:#0f6fd7;box-shadow:0 0 0 6px rgba(15,111,215,.14);animation:cbtBootDot 1.2s ease-in-out infinite;}'
            . '.cbt-boot-shell__meta{display:grid;gap:10px;margin-top:18px;}'
            . '.cbt-boot-shell__meta-item{display:flex;justify-content:space-between;gap:14px;font-size:14px;color:#4c6480;}'
            . '.cbt-boot-shell__meta-item strong{color:#19385b;}'
            . '.cbt-boot-shell__app{display:grid;gap:12px;}'
            . '.cbt-web-noscript{max-width:1180px;margin:0 auto 24px;padding:16px 18px;border-radius:18px;background:#fff0f0;border:1px solid #efb5b5;color:#8e2424;}'
            . '@keyframes cbtBootDot{0%,100%{transform:scale(.9);opacity:.88;}50%{transform:scale(1.1);opacity:1;}}'
            . '@media (max-width: 960px){.cbt-boot-shell__grid{grid-template-columns:1fr;}.cbt-boot-shell__hero{padding:18px 18px 20px;border-radius:22px;}.cbt-boot-shell__card{padding:18px;}.cbt-boot-shell__block{padding:18px;}}'
            . '</style>';
    }

    public static function filter_script_loader_tag(string $tag, string $handle, string $src): string
    {
        unset($src);
        if (!in_array($handle, [self::FRONTEND_HANDLE, self::DEV_CLIENT_HANDLE], true)) {
            return $tag;
        }

        if (strpos($tag, ' type=') === false) {
            $tag = preg_replace('/<script\b/', '<script type="module"', $tag, 1) ?: $tag;
        }
        if (strpos($tag, ' crossorigin') === false) {
            $tag = preg_replace('/<script\b/', '<script crossorigin', $tag, 1) ?: $tag;
        }

        return $tag;
    }

    public static function filter_asset_loader_src(string $src, string $handle): string
    {
        $source = self::resolve_frontend_asset_source();
        if (!empty($source['is_dev'])) {
            return $src;
        }

        if (!self::is_frontend_build_asset_handle($handle)) {
            return $src;
        }

        $path = wp_parse_url($src, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $src;
        }

        $query = wp_parse_url($src, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return $path;
        }

        return $path . '?' . $query;
    }

    private static function enqueue_assets(): bool
    {
        self::$assetBootstrapError = '';

        $source = self::resolve_frontend_asset_source();
        if (!empty($source['is_dev'])) {
            return self::enqueue_vite_dev_assets((string) ($source['dev_server_url'] ?? ''));
        }

        return self::enqueue_vite_build_assets();
    }

    /**
     * @return array{mode:string,source:string,label:string,is_dev:bool,is_constant_override:bool,dev_server_url:string}
     */
    private static function resolve_frontend_asset_source(): array
    {
        if (class_exists('CBT_Admin_Developer_Service')) {
            return CBT_Admin_Developer_Service::resolve_frontend_asset_source();
        }

        if (defined('CBT_EXAM_FRONTEND_DEV_SERVER')) {
            $base = rtrim(trim((string) constant('CBT_EXAM_FRONTEND_DEV_SERVER')), '/');
            if ($base !== '') {
                return [
                    'mode' => 'dev',
                    'source' => 'constant',
                    'label' => 'Constant Override',
                    'is_dev' => true,
                    'is_constant_override' => true,
                    'dev_server_url' => $base,
                ];
            }
        }

        return [
            'mode' => 'build',
            'source' => 'build',
            'label' => 'Production Build',
            'is_dev' => false,
            'is_constant_override' => false,
            'dev_server_url' => '',
        ];
    }

    /**
     * @return array{enabled:bool,status_label:string,reason:string,audience:string}
     */
    private static function resolve_frontend_debug_context(): array
    {
        if (class_exists('CBT_Admin_Developer_Service')) {
            return CBT_Admin_Developer_Service::resolve_frontend_debug_context();
        }

        return [
            'enabled' => false,
            'status_label' => 'INACTIVE',
            'reason' => 'Production Build',
            'audience' => 'manage_options only',
        ];
    }

    /**
     * @return array{enabled:bool,status_label:string,reason:string,audience:string}
     */
    private static function resolve_frontend_diagnostics_context(): array
    {
        if (class_exists('CBT_Admin_Developer_Service')) {
            return CBT_Admin_Developer_Service::resolve_frontend_diagnostics_context();
        }

        return [
            'enabled' => false,
            'status_label' => 'INACTIVE',
            'reason' => 'Production Build',
            'audience' => 'Browser admin saat ini',
        ];
    }

    /**
     * @return array{
     *   prefix:string,
     *   indexed_db_name:string,
     *   diagnostics_requests_key:string,
     *   diagnostics_snapshot_key:string,
     *   diagnostics_errors_key:string,
     *   diagnostics_state_key:string,
     *   diagnostics_render_stats_key:string,
     *   diagnostics_action_trail_key:string,
     *   diagnostics_command_key:string,
     *   diagnostics_max_entries:int
     * }
     */
    private static function get_frontend_storage_debug_config(): array
    {
        if (class_exists('CBT_Admin_Developer_Service')) {
            return CBT_Admin_Developer_Service::get_storage_debug_config();
        }

        return [
            'prefix' => 'cbt_exam_frontend_',
            'indexed_db_name' => 'cbt_exam_frontend_cache_v2',
            'diagnostics_requests_key' => 'cbt_exam_frontend_debug_rest_v1',
            'diagnostics_snapshot_key' => 'cbt_exam_frontend_debug_snapshot_v1',
            'diagnostics_errors_key' => 'cbt_exam_frontend_debug_errors_v1',
            'diagnostics_state_key' => 'cbt_exam_frontend_debug_state_v1',
            'diagnostics_render_stats_key' => 'cbt_exam_frontend_debug_render_stats_v1',
            'diagnostics_action_trail_key' => 'cbt_exam_frontend_debug_action_trail_v1',
            'diagnostics_command_key' => 'cbt_exam_frontend_debug_command_v1',
            'diagnostics_max_entries' => 50,
        ];
    }

    private static function enqueue_vite_dev_assets(string $base): bool
    {
        if ($base === '') {
            self::$assetBootstrapError = 'Frontend CBT sedang di mode developer, tetapi URL Vite dev server belum tersedia.';
            return false;
        }

        if (class_exists('CBT_Admin_Developer_Service')) {
            $health = CBT_Admin_Developer_Service::get_dev_server_health(false, $base, true);
            if (($health['status'] ?? 'unknown') !== 'ok') {
                $message = isset($health['message']) && is_string($health['message']) && $health['message'] !== ''
                    ? $health['message']
                    : 'Vite dev server tidak dapat dijangkau.';
                self::$assetBootstrapError = 'Frontend CBT sedang memakai Vite dev server, tetapi server tidak bisa dijangkau di ' . $base . '. ' . $message . ' Nyalakan `npm run dev` atau ubah mode ke Production Build di CBT Developer.';
                return false;
            }
        }

        wp_enqueue_script(
            self::DEV_CLIENT_HANDLE,
            $base . '/@vite/client',
            [],
            null,
            true
        );

        wp_enqueue_script(
            self::FRONTEND_HANDLE,
            $base . '/' . self::VITE_ENTRY,
            [self::DEV_CLIENT_HANDLE],
            null,
            true
        );

        return true;
    }

    private static function enqueue_vite_build_assets(): bool
    {
        $manifest = self::get_vite_manifest();
        if ($manifest === []) {
            _doing_it_wrong(
                __METHOD__,
                'Vite frontend build belum tersedia. Jalankan `npm install` lalu `npm run build` agar `public/build/manifest.json` dibuat.',
                CBT_EXAM_SYSTEM_VERSION
            );
            self::$assetBootstrapError = 'Build frontend CBT belum tersedia. Jalankan `npm install` lalu `npm run build` agar manifest frontend dibuat.';
            return false;
        }

        $entry = self::get_vite_manifest_entry($manifest, self::VITE_ENTRY);
        if ($entry === null || empty($entry['file']) || !is_string($entry['file'])) {
            _doing_it_wrong(
                __METHOD__,
                'Entry Vite frontend tidak ditemukan di manifest.',
                CBT_EXAM_SYSTEM_VERSION
            );
            self::$assetBootstrapError = 'Build frontend ditemukan, tetapi entry utama tidak ada di manifest.';
            return false;
        }

        $css_files = self::get_vite_entry_css_files($manifest, $entry);
        $css_index = 0;
        foreach ($css_files as $css_file) {
            $handle = $css_index === 0 ? self::FRONTEND_HANDLE : self::FRONTEND_HANDLE . '-style-' . $css_index;
            wp_enqueue_style(
                $handle,
                self::build_asset_url($css_file),
                [],
                self::build_asset_version($css_file)
            );
            $css_index++;
        }

        wp_enqueue_script(
            self::FRONTEND_HANDLE,
            self::build_asset_url($entry['file']),
            [],
            self::build_asset_version($entry['file']),
            true
        );

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_vite_manifest(): array
    {
        if (self::$viteManifest !== null) {
            return self::$viteManifest;
        }

        $manifest_path = CBT_EXAM_SYSTEM_PATH . self::VITE_MANIFEST_RELATIVE;
        if (!file_exists($manifest_path)) {
            self::$viteManifest = [];
            return self::$viteManifest;
        }

        $contents = file_get_contents($manifest_path);
        if (!is_string($contents) || $contents === '') {
            self::$viteManifest = [];
            return self::$viteManifest;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            self::$viteManifest = [];
            return self::$viteManifest;
        }

        self::$viteManifest = $decoded;
        return self::$viteManifest;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>|null
     */
    private static function get_vite_manifest_entry(array $manifest, string $entry_key): ?array
    {
        if (isset($manifest[$entry_key]) && is_array($manifest[$entry_key])) {
            return $manifest[$entry_key];
        }

        foreach ($manifest as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if ($key === basename($entry_key)) {
                return $value;
            }

            if (!empty($value['src']) && is_string($value['src']) && ltrim($value['src'], './') === ltrim($entry_key, './')) {
                return $value;
            }
        }

        return null;
    }

    private static function build_asset_url(string $relative_output_path): string
    {
        return CBT_EXAM_SYSTEM_URL . self::VITE_BUILD_DIR . ltrim($relative_output_path, '/');
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $entry
     * @return array<int,string>
     */
    private static function get_vite_entry_css_files(array $manifest, array $entry): array
    {
        $css_files = [];

        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                if (is_string($css_file) && $css_file !== '') {
                    $css_files[] = $css_file;
                }
            }
        }

        if ($css_files !== []) {
            return array_values(array_unique($css_files));
        }

        $style_entry = self::get_vite_manifest_entry($manifest, 'style.css');
        if ($style_entry !== null && !empty($style_entry['file']) && is_string($style_entry['file'])) {
            $css_files[] = $style_entry['file'];
        }

        return array_values(array_unique($css_files));
    }

    private static function build_asset_version(string $relative_output_path): string
    {
        $absolute = CBT_EXAM_SYSTEM_PATH . self::VITE_BUILD_DIR . ltrim($relative_output_path, '/');
        if (file_exists($absolute)) {
            return (string) filemtime($absolute);
        }

        return CBT_EXAM_SYSTEM_VERSION;
    }

    private static function ensure_frontend_assets_prepared(): bool
    {
        if (self::$assetsPrepared) {
            return self::$assetBootstrapError === '';
        }

        self::$assetsPrepared = true;
        $assets_enqueued = self::enqueue_assets();

        if ($assets_enqueued) {
            self::localize_frontend_config();
        }

        return $assets_enqueued;
    }

    private static function localize_frontend_config(): void
    {
        if (self::$localized) {
            return;
        }

        $asset_source = self::resolve_frontend_asset_source();
        $debug_context = self::resolve_frontend_debug_context();
        $diagnostics_context = self::resolve_frontend_diagnostics_context();
        $storage_debug_config = self::get_frontend_storage_debug_config();
        $rest_base_absolute = trailingslashit((string) rest_url('cbt/v1'));
        $rest_base_path = (string) wp_parse_url($rest_base_absolute, PHP_URL_PATH);
        $rest_base_path = trailingslashit($rest_base_path !== '' ? $rest_base_path : '/wp-json/cbt/v1/');
        $branding = self::get_setup_branding_config();
        $security = self::get_setup_security_config();
        $developer_page_url = '';

        if (class_exists('CBT_Admin_Developer_Service') && current_user_can('manage_options')) {
            $developer_page_url = CBT_Admin_Developer_Service::developer_page_url();
        }

        wp_localize_script(self::FRONTEND_HANDLE, 'CBTExamFrontendConfig', [
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
            'securityDetectIdle' => $security['detect_idle_during_exam'],
            'securityIdleThresholdMinutes' => $security['idle_threshold_minutes'],
            'securityIdleThresholdSeconds' => $security['idle_threshold_minutes'] * MINUTE_IN_SECONDS,
            'homeUrl' => (string) home_url('/'),
            'tokenMinLength' => 6,
            'tokenLength' => 6,
            'frontendDebugUi' => !empty($debug_context['enabled']) ? 1 : 0,
            'frontendDebugReason' => isset($debug_context['reason']) ? (string) $debug_context['reason'] : 'Production Build',
            'frontendDebugAudience' => isset($debug_context['audience']) ? (string) $debug_context['audience'] : 'manage_options only',
            'frontendDiagnosticsEnabled' => !empty($diagnostics_context['enabled']) ? 1 : 0,
            'frontendDiagnosticsStorageKey' => isset($storage_debug_config['diagnostics_requests_key']) ? (string) $storage_debug_config['diagnostics_requests_key'] : 'cbt_exam_frontend_debug_rest_v1',
            'frontendDiagnosticsSnapshotKey' => isset($storage_debug_config['diagnostics_snapshot_key']) ? (string) $storage_debug_config['diagnostics_snapshot_key'] : 'cbt_exam_frontend_debug_snapshot_v1',
            'frontendDiagnosticsSyncKey' => isset($storage_debug_config['diagnostics_sync_key']) ? (string) $storage_debug_config['diagnostics_sync_key'] : 'cbt_exam_frontend_debug_sync_v1',
            'frontendDiagnosticsTimelineKey' => isset($storage_debug_config['diagnostics_timeline_key']) ? (string) $storage_debug_config['diagnostics_timeline_key'] : 'cbt_exam_frontend_debug_timeline_v1',
            'frontendDiagnosticsScenarioKey' => isset($storage_debug_config['diagnostics_scenario_key']) ? (string) $storage_debug_config['diagnostics_scenario_key'] : 'cbt_exam_frontend_debug_scenarios_v1',
            'frontendDiagnosticsErrorsKey' => isset($storage_debug_config['diagnostics_errors_key']) ? (string) $storage_debug_config['diagnostics_errors_key'] : 'cbt_exam_frontend_debug_errors_v1',
            'frontendDiagnosticsStateKey' => isset($storage_debug_config['diagnostics_state_key']) ? (string) $storage_debug_config['diagnostics_state_key'] : 'cbt_exam_frontend_debug_state_v1',
            'frontendDiagnosticsRenderStatsKey' => isset($storage_debug_config['diagnostics_render_stats_key']) ? (string) $storage_debug_config['diagnostics_render_stats_key'] : 'cbt_exam_frontend_debug_render_stats_v1',
            'frontendDiagnosticsActionTrailKey' => isset($storage_debug_config['diagnostics_action_trail_key']) ? (string) $storage_debug_config['diagnostics_action_trail_key'] : 'cbt_exam_frontend_debug_action_trail_v1',
            'frontendDiagnosticsCommandKey' => isset($storage_debug_config['diagnostics_command_key']) ? (string) $storage_debug_config['diagnostics_command_key'] : 'cbt_exam_frontend_debug_command_v1',
            'frontendDiagnosticsMaxEntries' => isset($storage_debug_config['diagnostics_max_entries']) ? (int) $storage_debug_config['diagnostics_max_entries'] : 50,
            'frontendDiagnosticsTimelineMaxEntries' => isset($storage_debug_config['diagnostics_timeline_max_entries']) ? (int) $storage_debug_config['diagnostics_timeline_max_entries'] : 150,
            'frontendDiagnosticsScenarioEnabled' => !empty($diagnostics_context['enabled']) ? 1 : 0,
            'frontendDiagnosticsRenderStatsEnabled' => !empty($diagnostics_context['enabled']) ? 1 : 0,
            'frontendDiagnosticsStorageExplorerEnabled' => !empty($diagnostics_context['enabled']) ? 1 : 0,
            'frontendDiagnosticsStoragePrefix' => isset($storage_debug_config['prefix']) ? (string) $storage_debug_config['prefix'] : 'cbt_exam_frontend_',
            'frontendDiagnosticsIndexedDbName' => isset($storage_debug_config['indexed_db_name']) ? (string) $storage_debug_config['indexed_db_name'] : 'cbt_exam_frontend_cache_v2',
            'frontendDeveloperPageUrl' => $developer_page_url,
            'frontendAssetSource' => isset($asset_source['label']) ? (string) $asset_source['label'] : 'Production Build',
        ]);

        self::$localized = true;
    }

    private static function is_frontend_build_asset_handle(string $handle): bool
    {
        return $handle === self::FRONTEND_HANDLE
            || strpos($handle, self::FRONTEND_HANDLE . '-style-') === 0;
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

    private static function is_fallback_frontend_shortcode_page(): bool
    {
        return self::is_frontend_shortcode_page() && !self::is_canonical_frontend_page();
    }

    private static function is_frontend_request_context(): bool
    {
        return self::is_canonical_frontend_page() || self::is_frontend_shortcode_page();
    }

    private static function is_canonical_frontend_page(): bool
    {
        if (!is_singular('page')) {
            return false;
        }

        $post = get_queried_object();
        if (!($post instanceof WP_Post)) {
            return false;
        }

        $canonical_id = self::get_canonical_frontend_page_id();
        if ($canonical_id > 0) {
            return (int) $post->ID === $canonical_id;
        }

        return sanitize_title((string) $post->post_name) === self::FRONTEND_PAGE_SLUG;
    }

    private static function get_canonical_frontend_page_id(): int
    {
        $stored_id = (int) get_option(self::FRONTEND_PAGE_OPTION, 0);
        if ($stored_id > 0) {
            return $stored_id;
        }

        $page = get_page_by_path(self::FRONTEND_PAGE_SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post) {
            return (int) $page->ID;
        }

        return 0;
    }

    private static function render_frontend_markup(bool $include_boot_shell): string
    {
        ob_start();
        if (self::$assetBootstrapError !== '') {
            echo self::render_asset_bootstrap_error_panel();
            return (string) ob_get_clean();
        }

        $boot_shell_markup = $include_boot_shell ? self::render_boot_shell_markup() : '';
        ?>
        <div id="cbt-exam-app" class="cbt-web-shell"><?php echo $boot_shell_markup; ?></div>
        <noscript>
            <div class="cbt-web-noscript">JavaScript harus aktif untuk menggunakan CBT frontend.</div>
        </noscript>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_boot_shell_markup(): string
    {
        return implode('', [
            '<div class="cbt-frontpage__shell">',
            '<div class="cbt-boot-shell cbt-boot-shell--compact" role="status" aria-live="polite" aria-busy="true">',
            '<section class="cbt-boot-shell__card cbt-boot-shell__card--compact">',
            '<div class="cbt-boot-shell__block cbt-boot-shell__app cbt-boot-shell__app--compact">',
            '<span class="cbt-boot-shell__status"><span class="cbt-boot-shell__dot" aria-hidden="true"></span>Memuat CBT</span>',
            '<h2>Booting App</h2>',
            '<p>Antarmuka ujian sedang disiapkan dan akan tampil sesaat lagi.</p>',
            '<div class="cbt-boot-shell__progress" aria-hidden="true"><span id="cbt-boot-progress-fill" class="cbt-boot-shell__progress-fill"></span></div>',
            '<div class="cbt-boot-shell__progress-meta"><span id="cbt-boot-progress-label" class="cbt-boot-shell__progress-label">Memuat konfigurasi frontend</span><strong id="cbt-boot-progress-value" class="cbt-boot-shell__progress-value">12%</strong></div>',
            '<div class="cbt-boot-shell__meta">',
            '<div class="cbt-boot-shell__meta-item"><span>Status</span><strong id="cbt-boot-progress-status">Loading frontend runtime</strong></div>',
            '<div class="cbt-boot-shell__meta-item"><span>Mode</span><strong>' . esc_html((string) (self::resolve_frontend_asset_source()['label'] ?? 'Frontend Build')) . '</strong></div>',
            '<div class="cbt-boot-shell__meta-item"><span>Version</span><strong>' . esc_html(CBT_EXAM_SYSTEM_VERSION) . '</strong></div>',
            '</div>',
            '</div>',
            '</section>',
            '</div>',
            '</div>',
        ]);
    }

    private static function suppress_frontend_noise(): void
    {
        if (self::$noiseSuppressed) {
            return;
        }

        self::$noiseSuppressed = true;
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }

    private static function strip_assets_by_whitelist(): void
    {
        $styles = wp_styles();
        if ($styles instanceof WP_Styles) {
            foreach ((array) $styles->queue as $handle) {
                if (self::is_allowed_canonical_style_handle((string) $handle)) {
                    continue;
                }
                wp_dequeue_style((string) $handle);
            }
        }

        $scripts = wp_scripts();
        if ($scripts instanceof WP_Scripts) {
            foreach ((array) $scripts->queue as $handle) {
                if (self::is_allowed_canonical_script_handle((string) $handle)) {
                    continue;
                }
                wp_dequeue_script((string) $handle);
            }
        }
    }

    private static function strip_fallback_frontend_noise_handles(): void
    {
        $style_handles = [
            'wp-block-library',
            'wp-block-library-theme',
            'classic-theme-styles',
            'global-styles',
            'wc-block-style',
        ];
        foreach ($style_handles as $handle) {
            wp_dequeue_style($handle);
        }

        $script_handles = [
            'wp-embed',
        ];
        foreach ($script_handles as $handle) {
            wp_dequeue_script($handle);
        }
    }

    private static function is_allowed_canonical_style_handle(string $handle): bool
    {
        return self::is_frontend_build_asset_handle($handle);
    }

    private static function is_allowed_canonical_script_handle(string $handle): bool
    {
        return in_array($handle, [self::FRONTEND_HANDLE, self::DEV_CLIENT_HANDLE], true);
    }

    private static function render_asset_bootstrap_error_panel(): string
    {
        $developer_page_url = class_exists('CBT_Admin_Developer_Service') && CBT_Admin_Developer_Service::can_manage()
            ? CBT_Admin_Developer_Service::developer_page_url()
            : '';

        ob_start();
        ?>
        <section class="cbt-web-shell" style="max-width:900px;margin:32px auto;padding:0 16px;">
            <div style="background:#fff7f7;border:1px solid #f3b3b3;border-radius:18px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.05);">
                <h2 style="margin:0 0 10px;font-size:28px;line-height:1.1;color:#8a1f1f;">Frontend CBT Tidak Bisa Dimuat</h2>
                <p style="margin:0 0 12px;color:#5f2121;"><?php echo esc_html(self::$assetBootstrapError); ?></p>
                <p style="margin:0;color:#7a4b4b;">Jika sedang debugging, nyalakan Vite dengan <code>npm run dev</code>. Jika tidak, ubah kembali source frontend ke <strong>Production Build</strong>.</p>
                <?php if ($developer_page_url !== ''): ?>
                    <p style="margin:16px 0 0;"><a href="<?php echo esc_url($developer_page_url); ?>" class="button button-primary">Buka CBT Developer</a></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
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
     * @return array{force_fullscreen:int,block_copy_paste:int,log_security_events:int,detect_idle_during_exam:int,idle_threshold_minutes:int}
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
            'detect_idle_during_exam' => !array_key_exists('detect_idle_during_exam', $raw) || !empty($raw['detect_idle_during_exam']) ? 1 : 0,
            'idle_threshold_minutes' => max(1, absint($raw['idle_threshold_minutes'] ?? 5)),
        ];
    }
}
