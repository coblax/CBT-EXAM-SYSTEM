<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Security_User_Agent_Guard')) {
    require_once __DIR__ . '/class-cbt-security-user-agent-guard.php';
}

class CBT_Frontend
{
    private const STUDENT_SHORTCODE = 'cbt_exam_frontend';
    private const SUPERVISOR_SHORTCODE = 'cbt_exam_supervisor_frontend';
    private const STUDENT_FRONTEND_PAGE_OPTION = 'cbt_exam_system_frontend_page_id';
    private const SUPERVISOR_FRONTEND_PAGE_OPTION = 'cbt_exam_system_supervisor_page_id';
    private const STUDENT_FRONTEND_PAGE_SLUG = 'cbt-ujian';
    private const SUPERVISOR_FRONTEND_PAGE_SLUG = 'pengawas';
    private const MINIMAL_TEMPLATE_RELATIVE = 'templates/frontend/minimal-template.php';
    private const SETUP_BRANDING_OPTION = 'cbt_setup_branding';
    private const SETUP_SECURITY_OPTION = 'cbt_setup_security';
    private const EXAM_WATERMARK_OPACITY_DEFAULT = 0.07;
    private const EXAM_WATERMARK_OPACITY_MIN = 0.03;
    private const EXAM_WATERMARK_OPACITY_MAX = 0.12;
    private const FRONTEND_HANDLE = 'cbt-frontend-app';
    private const DEV_CLIENT_HANDLE = 'cbt-frontend-vite-client';
    private const VITE_ENTRY = 'src/frontend/main.js';
    private const VITE_BUILD_DIR = 'public/build/';
    private const VITE_MANIFEST_RELATIVE = 'public/build/manifest.json';
    private const SERVICE_WORKER_QUERY_VAR = 'cbt_exam_sw';
    private const SERVICE_WORKER_STUDENT_VALUE = 'student';
    private const SERVICE_WORKER_CACHE_PREFIX = 'cbt-exam-student-';

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
        add_shortcode(self::STUDENT_SHORTCODE, [self::class, 'render_shortcode']);
        add_shortcode(self::SUPERVISOR_SHORTCODE, [self::class, 'render_supervisor_shortcode']);
        add_filter('body_class', [self::class, 'filter_body_class']);
        add_filter('show_admin_bar', [self::class, 'filter_show_admin_bar']);
        add_filter('template_include', [self::class, 'filter_template_include'], 99);
        add_action('template_redirect', [self::class, 'guard_student_user_agent_access'], 1);
        add_action('template_redirect', [self::class, 'send_nocache_headers']);
        add_action('wp_enqueue_scripts', [self::class, 'prepare_frontend_request'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'strip_frontend_assets'], PHP_INT_MAX);
        add_action('wp_print_styles', [self::class, 'strip_frontend_assets'], PHP_INT_MAX);
        add_action('wp_print_scripts', [self::class, 'strip_frontend_assets'], PHP_INT_MAX);
        add_action('wp_print_footer_scripts', [self::class, 'strip_frontend_assets'], 0);
        add_filter('script_loader_tag', [self::class, 'filter_script_loader_tag'], 10, 3);
        add_filter('script_loader_src', [self::class, 'filter_asset_loader_src'], 10, 2);
        add_filter('style_loader_src', [self::class, 'filter_asset_loader_src'], 10, 2);
        add_action('init', [self::class, 'maybe_render_service_worker']);
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
     * @param array<string,mixed> $atts
     */
    public static function render_supervisor_shortcode(array $atts = []): string
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
        $classes[] = 'cbt-exam-page-mode-' . self::current_frontend_mode();

        return $classes;
    }

    public static function filter_show_admin_bar(bool $show): bool
    {
        if (self::is_canonical_frontend_page()) {
            return false;
        }

        return $show;
    }

    public static function guard_student_user_agent_access(): void
    {
        if (!self::is_frontend_request_context() || self::current_frontend_mode() !== 'student') {
            return;
        }

        if (CBT_Security_User_Agent_Guard::is_request_allowed()) {
            return;
        }

        if (function_exists('status_header')) {
            status_header(403);
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        wp_die(
            'Akses ujian hanya diizinkan dari aplikasi atau User-Agent yang terdaftar.',
            'Akses Ujian Dibatasi',
            [
                'response' => 403,
                'back_link' => false,
            ]
        );
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

    public static function maybe_render_service_worker(): void
    {
        $requested = isset($_GET[self::SERVICE_WORKER_QUERY_VAR])
            ? sanitize_key(wp_unslash((string) $_GET[self::SERVICE_WORKER_QUERY_VAR]))
            : '';
        if ($requested !== self::SERVICE_WORKER_STUDENT_VALUE) {
            return;
        }

        $manifest = self::get_vite_manifest();
        if ($manifest === []) {
            if (function_exists('status_header')) {
                status_header(404);
            }
            header('Content-Type: application/javascript; charset=UTF-8');
            header('Cache-Control: no-cache, must-revalidate, max-age=0');
            echo '/* CBT service worker unavailable: frontend build manifest missing. */';
            exit;
        }

        $scope = self::get_service_worker_scope_path();
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Service-Worker-Allowed: ' . $scope);
        header('X-Content-Type-Options: nosniff');
        echo self::render_service_worker_script($manifest);
        exit;
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
     * @param array{mode?:string,is_dev?:bool,label?:string} $asset_source
     * @param array<string,mixed>|null $manifest
     * @return array{enabled:int,url:string,scope:string,build_id:string}
     */
    private static function build_service_worker_frontend_config(array $asset_source, string $frontend_mode, ?array $manifest = null): array
    {
        $manifest = $manifest ?? self::get_vite_manifest();
        $mode = sanitize_key((string) ($asset_source['mode'] ?? 'build'));
        $enabled = $frontend_mode === 'student'
            && empty($asset_source['is_dev'])
            && in_array($mode, ['build', 'stable'], true)
            && $manifest !== []
            && self::get_service_worker_precache_asset_paths($manifest) !== [];

        if (!$enabled) {
            return [
                'enabled' => 0,
                'url' => '',
                'scope' => '',
                'build_id' => '',
            ];
        }

        $build_id = self::build_service_worker_build_id($manifest);

        return [
            'enabled' => 1,
            'url' => self::build_service_worker_url($build_id),
            'scope' => self::get_service_worker_scope_path(),
            'build_id' => $build_id,
        ];
    }

    private static function build_service_worker_url(string $build_id): string
    {
        return add_query_arg(
            [
                self::SERVICE_WORKER_QUERY_VAR => self::SERVICE_WORKER_STUDENT_VALUE,
                'v' => $build_id,
            ],
            home_url('/')
        );
    }

    private static function get_service_worker_scope_path(): string
    {
        return self::normalize_service_worker_scope_path(self::frontend_page_url('student'));
    }

    private static function normalize_service_worker_scope_path(string $url): string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return rtrim($path, '/') . '/';
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private static function get_service_worker_precache_asset_paths(array $manifest): array
    {
        $entry = self::get_vite_manifest_entry($manifest, self::VITE_ENTRY);
        if ($entry === null) {
            return [];
        }

        $visited = [];
        $paths = [];
        self::collect_service_worker_manifest_entry_assets($manifest, self::VITE_ENTRY, $entry, $visited, $paths);

        return array_values(array_unique($paths));
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $entry
     * @param array<string,bool> $visited
     * @param array<int,string> $paths
     */
    private static function collect_service_worker_manifest_entry_assets(
        array $manifest,
        string $entry_key,
        array $entry,
        array &$visited,
        array &$paths
    ): void {
        $visit_key = $entry_key !== '' ? $entry_key : self::build_manifest_entry_visit_key($entry);
        if ($visit_key !== '') {
            if (isset($visited[$visit_key])) {
                return;
            }
            $visited[$visit_key] = true;
        }

        if (self::should_skip_service_worker_manifest_entry($entry_key, $entry)) {
            return;
        }

        if (!empty($entry['file']) && is_string($entry['file'])) {
            self::append_service_worker_asset_path($paths, $entry['file']);
        }
        foreach (['css', 'assets'] as $asset_key) {
            if (empty($entry[$asset_key]) || !is_array($entry[$asset_key])) {
                continue;
            }
            foreach ($entry[$asset_key] as $asset_path) {
                if (is_string($asset_path)) {
                    self::append_service_worker_asset_path($paths, $asset_path);
                }
            }
        }

        foreach (['imports', 'dynamicImports'] as $import_group) {
            if (empty($entry[$import_group]) || !is_array($entry[$import_group])) {
                continue;
            }
            foreach ($entry[$import_group] as $import_key) {
                if (!is_string($import_key) || $import_key === '') {
                    continue;
                }
                $import_entry = self::get_vite_manifest_entry($manifest, $import_key);
                if ($import_entry === null) {
                    continue;
                }
                self::collect_service_worker_manifest_entry_assets($manifest, $import_key, $import_entry, $visited, $paths);
            }
        }
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function should_skip_service_worker_manifest_entry(string $entry_key, array $entry): bool
    {
        $haystack = strtolower(implode(' ', [
            str_replace('\\', '/', ltrim($entry_key, './')),
            isset($entry['src']) && is_string($entry['src']) ? str_replace('\\', '/', ltrim($entry['src'], './')) : '',
            isset($entry['name']) && is_string($entry['name']) ? $entry['name'] : '',
            isset($entry['file']) && is_string($entry['file']) ? $entry['file'] : '',
        ]));

        return str_contains($haystack, 'src/admin/')
            || str_contains($haystack, 'admin-math')
            || str_contains($haystack, 'admin-analytics')
            || str_contains($haystack, 'src/frontend/app/supervisor/')
            || str_contains($haystack, 'frontend-supervisor')
            || str_contains($haystack, 'supervisor/runtime');
    }

    /**
     * @param array<int,string> $paths
     */
    private static function append_service_worker_asset_path(array &$paths, string $path): void
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '' || str_contains($path, '..') || preg_match('#^(?:https?:)?//#i', $path)) {
            return;
        }

        $paths[] = $path;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function build_service_worker_build_id(array $manifest): string
    {
        $paths = self::get_service_worker_precache_asset_paths($manifest);
        $seed = CBT_EXAM_SYSTEM_VERSION . '|' . implode('|', $paths);

        return substr(hash('sha256', $seed), 0, 16);
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function render_service_worker_script(array $manifest): string
    {
        $build_id = self::build_service_worker_build_id($manifest);
        $asset_paths = self::get_service_worker_precache_asset_paths($manifest);
        $asset_urls = array_values(array_unique(array_map(static function (string $asset_path): string {
            return self::build_asset_url($asset_path);
        }, $asset_paths)));
        $shell_url = self::frontend_page_url('student');
        $scope_path = self::get_service_worker_scope_path();
        $build_base_path = wp_parse_url(CBT_EXAM_SYSTEM_URL . self::VITE_BUILD_DIR, PHP_URL_PATH);
        $build_base_path = is_string($build_base_path) && $build_base_path !== '' ? $build_base_path : '/';
        if ($build_base_path[0] !== '/') {
            $build_base_path = '/' . $build_base_path;
        }

        $build_id_json = self::service_worker_json($build_id);
        $cache_prefix_json = self::service_worker_json(self::SERVICE_WORKER_CACHE_PREFIX);
        $asset_urls_json = self::service_worker_json($asset_urls);
        $shell_url_json = self::service_worker_json($shell_url);
        $scope_path_json = self::service_worker_json($scope_path);
        $build_base_path_json = self::service_worker_json(rtrim($build_base_path, '/') . '/');
        $offline_html_json = self::service_worker_json(self::render_service_worker_offline_html());
        $rest_base_url = function_exists('rest_url') ? (string) rest_url('cbt/v1') : '/wp-json/cbt/v1/';
        $rest_base_path = self::ensure_trailing_slash((string) wp_parse_url($rest_base_url, PHP_URL_PATH));
        $rest_base_path_json = self::service_worker_json($rest_base_path !== '' ? $rest_base_path : '/wp-json/cbt/v1/');

        return <<<JS
(function () {
    'use strict';

    var BUILD_ID = {$build_id_json};
    var CACHE_PREFIX = {$cache_prefix_json};
    var STATIC_CACHE = CACHE_PREFIX + 'static-' + BUILD_ID;
    var SHELL_CACHE = CACHE_PREFIX + 'shell-' + BUILD_ID;
    var MEDIA_CACHE = CACHE_PREFIX + 'media-' + BUILD_ID;
    var PRECACHE_URLS = {$asset_urls_json};
    var SHELL_URL = {$shell_url_json};
    var SCOPE_PATH = {$scope_path_json};
    var BUILD_BASE_PATH = {$build_base_path_json};
    var REST_BASE_PATH = {$rest_base_path_json};
    var OFFLINE_HTML = {$offline_html_json};
    var ANSWER_QUEUE_DB_NAME = 'cbt_exam_answer_queue_v1';
    var ANSWER_QUEUE_STORE = 'answers';
    var AUTH_GRANT_STORE = 'auth_grants';
    var ANSWER_SYNC_TAG = 'cbt-answer-sync';
    var ANSWER_SYNC_LEASE_MS = 30000;
    var MEDIA_PRECACHE_LIMIT = 200;
    var PRECACHE_LOOKUP = Object.create(null);

    PRECACHE_URLS.forEach(function (url) {
        PRECACHE_LOOKUP[url] = true;
        try {
            PRECACHE_LOOKUP[new URL(url, self.location.href).pathname] = true;
        } catch (error) {}
    });

    function cacheRequest(cache, request) {
        return fetch(request).then(function (response) {
            if (response && (response.ok || response.type === 'opaque')) {
                return cache.put(request, response.clone()).then(function () {
                    return response;
                });
            }
            return response;
        });
    }

    function cachePrecacheUrl(cache, url) {
        return cacheRequest(cache, new Request(url, {
            cache: 'reload',
            credentials: 'same-origin'
        })).catch(function () {
            return null;
        });
    }

    function resolveBuildIdFromCacheName(name) {
        if (name.indexOf(CACHE_PREFIX) !== 0) {
            return '';
        }
        var rest = name.slice(CACHE_PREFIX.length);
        var separator = rest.indexOf('-');
        return separator >= 0 ? rest.slice(separator + 1) : '';
    }

    function isSameOrigin(url) {
        return url.origin === self.location.origin;
    }

    function isWithinScope(url) {
        return url.pathname.indexOf(SCOPE_PATH) === 0;
    }

    function isNavigationRequest(request) {
        if (request.mode === 'navigate') {
            return true;
        }
        var accept = request.headers && request.headers.get ? String(request.headers.get('accept') || '') : '';
        return accept.indexOf('text/html') >= 0;
    }

    function isStaticBuildAsset(url) {
        return url.pathname.indexOf(BUILD_BASE_PATH) === 0
            || PRECACHE_LOOKUP[url.href] === true
            || PRECACHE_LOOKUP[url.pathname] === true;
    }

    function isPrecacheMediaUrl(url) {
        return isSameOrigin(url)
            && url.pathname.indexOf(REST_BASE_PATH) !== 0
            && /\.(?:avif|bmp|gif|jpe?g|m4a|m4v|mov|mp3|mp4|oga|ogg|png|svg|wav|webm|webp)$/i.test(url.pathname);
    }

    function cacheFirstStatic(request) {
        return caches.match(request).then(function (cached) {
            if (cached) {
                return cached;
            }
            return caches.open(STATIC_CACHE).then(function (cache) {
                return cacheRequest(cache, request);
            });
        });
    }

    function cacheFirstMedia(request) {
        return caches.open(MEDIA_CACHE).then(function (cache) {
            return cache.match(request).then(function (cached) {
                if (cached) {
                    return cached;
                }
                return cacheRequest(cache, request);
            });
        });
    }

    function precacheMediaUrls(urls) {
        var normalizedUrls = [];
        (Array.isArray(urls) ? urls : []).forEach(function (rawUrl) {
            if (normalizedUrls.length >= MEDIA_PRECACHE_LIMIT) {
                return;
            }
            try {
                var url = new URL(String(rawUrl || ''), self.location.href);
                if (isPrecacheMediaUrl(url) && normalizedUrls.indexOf(url.href) < 0) {
                    normalizedUrls.push(url.href);
                }
            } catch (error) {}
        });

        if (!normalizedUrls.length) {
            return Promise.resolve(0);
        }

        return caches.open(MEDIA_CACHE).then(function (cache) {
            return Promise.all(normalizedUrls.map(function (url) {
                return cachePrecacheUrl(cache, url);
            }));
        }).then(function (responses) {
            return responses.filter(Boolean).length;
        }).catch(function () {
            return 0;
        });
    }

    function networkFirstNavigation(request) {
        return fetch(request).then(function (response) {
            if (response && response.ok) {
                caches.open(SHELL_CACHE).then(function (cache) {
                    cache.put(request, response.clone()).catch(function () {});
                    cache.put(SHELL_URL, response.clone()).catch(function () {});
                }).catch(function () {});
            }
            return response;
        }).catch(function () {
            return caches.match(request).then(function (cached) {
                if (cached) {
                    return cached;
                }
                return caches.match(SHELL_URL);
            }).then(function (cachedShell) {
                if (cachedShell) {
                    return cachedShell;
                }
                return new Response(OFFLINE_HTML, {
                    headers: {
                        'Content-Type': 'text/html; charset=UTF-8',
                        'Cache-Control': 'no-store'
                    },
                    status: 200
                });
            });
        });
    }

    function openAnswerQueueDb() {
        if (!self.indexedDB) {
            return Promise.resolve(null);
        }

        return new Promise(function (resolve) {
            var request;
            try {
                request = self.indexedDB.open(ANSWER_QUEUE_DB_NAME, 1);
            } catch (error) {
                resolve(null);
                return;
            }

            request.onupgradeneeded = function () {
                var db = request.result;
                if (!db.objectStoreNames.contains(ANSWER_QUEUE_STORE)) {
                    db.createObjectStore(ANSWER_QUEUE_STORE, { keyPath: 'queue_key' });
                }
                if (!db.objectStoreNames.contains(AUTH_GRANT_STORE)) {
                    db.createObjectStore(AUTH_GRANT_STORE, { keyPath: 'grant_key' });
                }
            };
            request.onsuccess = function () {
                resolve(request.result || null);
            };
            request.onerror = function () {
                resolve(null);
            };
            request.onblocked = function () {
                resolve(null);
            };
        });
    }

    function withAnswerQueueStore(storeName, mode, callback) {
        return openAnswerQueueDb().then(function (db) {
            if (!db) {
                return null;
            }

            return new Promise(function (resolve) {
                var result = null;
                var settled = false;
                var tx;
                try {
                    tx = db.transaction(storeName, mode);
                    var request = callback(tx.objectStore(storeName));
                    if (request && typeof request === 'object') {
                        request.onsuccess = function () {
                            result = request.result;
                        };
                        request.onerror = function () {
                            result = null;
                        };
                    }
                } catch (error) {
                    resolve(null);
                    return;
                }

                tx.oncomplete = function () {
                    if (!settled) {
                        settled = true;
                        resolve(result);
                    }
                };
                tx.onerror = function () {
                    if (!settled) {
                        settled = true;
                        resolve(null);
                    }
                };
                tx.onabort = tx.onerror;
            });
        });
    }

    function normalizeQueueItem(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }
        var questionId = Number(raw.question_id) || 0;
        var attemptId = Number(raw.attempt_id) || 0;
        var userId = Number(raw.user_id) || 0;
        if (questionId <= 0 || attemptId <= 0 || userId <= 0) {
            return null;
        }
        return raw;
    }

    function listAnswerQueueItems() {
        return withAnswerQueueStore(ANSWER_QUEUE_STORE, 'readonly', function (store) {
            return store.getAll();
        }).then(function (items) {
            return (Array.isArray(items) ? items : []).map(normalizeQueueItem).filter(Boolean);
        });
    }

    function putAnswerQueueItem(item) {
        return withAnswerQueueStore(ANSWER_QUEUE_STORE, 'readwrite', function (store) {
            return store.put(item);
        });
    }

    function deleteAnswerQueueItem(queueKey) {
        return withAnswerQueueStore(ANSWER_QUEUE_STORE, 'readwrite', function (store) {
            return store.delete(queueKey);
        });
    }

    function listAuthGrants() {
        return withAnswerQueueStore(AUTH_GRANT_STORE, 'readonly', function (store) {
            return store.getAll();
        }).then(function (items) {
            var now = Date.now();
            return (Array.isArray(items) ? items : []).filter(function (grant) {
                return grant && String(grant.token || '') !== '' && Number(grant.expires_at_ms) > now;
            });
        });
    }

    function acquireAnswerQueueBatch(grant) {
        var now = Date.now();
        var owner = 'sw:' + BUILD_ID;
        return listAnswerQueueItems().then(function (items) {
            var available = items.filter(function (item) {
                if (Number(item.user_id) !== Number(grant.user_id) || Number(item.attempt_id) !== Number(grant.attempt_id)) {
                    return false;
                }
                if (item.status === 'pending' || item.status === 'failed_retryable') {
                    return true;
                }
                return item.status === 'syncing' && Number(item.lease_until) > 0 && Number(item.lease_until) <= now;
            }).slice(0, 10);

            return available.reduce(function (promise, item) {
                return promise.then(function (acquired) {
                    item.status = 'syncing';
                    item.lease_owner = owner;
                    item.lease_until = now + ANSWER_SYNC_LEASE_MS;
                    item.attempted_at = now;
                    item.attempt_count = Math.max(0, Number(item.attempt_count) || 0) + 1;
                    item.updated_at = now;
                    return putAnswerQueueItem(item).then(function () {
                        acquired.push(item);
                        return acquired;
                    });
                });
            }, Promise.resolve([]));
        });
    }

    function releaseAnswerQueueBatch(items, status, message) {
        var now = Date.now();
        return (Array.isArray(items) ? items : []).reduce(function (promise, item) {
            return promise.then(function () {
                item.status = status || 'failed_retryable';
                item.lease_owner = '';
                item.lease_until = 0;
                item.last_error = String(message || '');
                item.updated_at = now;
                return putAnswerQueueItem(item);
            });
        }, Promise.resolve());
    }

    function ackAnswerQueueBatch(items) {
        return (Array.isArray(items) ? items : []).reduce(function (promise, item) {
            return promise.then(function () {
                return withAnswerQueueStore(ANSWER_QUEUE_STORE, 'readonly', function (store) {
                    return store.get(item.queue_key);
                }).then(function (current) {
                    if (current && String(current.signature || '') === String(item.signature || '')) {
                        return deleteAnswerQueueItem(item.queue_key);
                    }
                    return null;
                });
            });
        }, Promise.resolve());
    }

    function notifyAnswerSyncComplete() {
        return listAnswerQueueItems().then(function (items) {
            return self.clients.matchAll({ includeUncontrolled: true }).then(function (clients) {
                clients.forEach(function (client) {
                    client.postMessage({
                        type: 'CBT_SW_ANSWER_SYNC_COMPLETE',
                        remaining: items.filter(function (item) {
                            return item.status !== 'acked';
                        }).length
                    });
                });
            });
        });
    }

    function flushAnswerQueueForGrant(grant) {
        return acquireAnswerQueueBatch(grant).then(function (items) {
            if (!items.length) {
                return null;
            }
            return fetch(new URL('submit_answers_batch', self.location.origin + REST_BASE_PATH).toString(), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + String(grant.token || ''),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    attempt_id: Number(grant.attempt_id) || 0,
                    answers: items.map(function (item) {
                        return {
                            question_id: Number(item.question_id) || 0,
                            answer: Object.prototype.hasOwnProperty.call(item, 'answer') ? item.answer : null
                        };
                    })
                })
            }).then(function (response) {
                if (response && response.ok) {
                    return ackAnswerQueueBatch(items);
                }
                if (response && Number(response.status) === 401) {
                    return releaseAnswerQueueBatch(items, 'failed_retryable', 'answer_sync_token_expired');
                }
                if (response && Number(response.status) >= 400 && Number(response.status) < 500) {
                    return releaseAnswerQueueBatch(items, 'failed_terminal', 'answer_sync_rejected');
                }
                return releaseAnswerQueueBatch(items, 'failed_retryable', 'answer_sync_retryable');
            }).catch(function () {
                return releaseAnswerQueueBatch(items, 'failed_retryable', 'answer_sync_network_error');
            });
        });
    }

    function flushQueuedAnswers() {
        return listAuthGrants().then(function (grants) {
            return grants.reduce(function (promise, grant) {
                return promise.then(function () {
                    return flushAnswerQueueForGrant(grant);
                });
            }, Promise.resolve());
        }).then(function () {
            return notifyAnswerSyncComplete();
        });
    }

    self.addEventListener('install', function (event) {
        event.waitUntil(
            caches.open(STATIC_CACHE).then(function (cache) {
                return Promise.all(PRECACHE_URLS.map(function (url) {
                    return cachePrecacheUrl(cache, url);
                }));
            }).then(function () {
                return caches.open(SHELL_CACHE);
            }).then(function (cache) {
                return cachePrecacheUrl(cache, SHELL_URL);
            }).then(function () {
                return self.skipWaiting();
            })
        );
    });

    self.addEventListener('activate', function (event) {
        event.waitUntil(
            caches.keys().then(function (keys) {
                var previousBuildIds = [];
                keys.forEach(function (name) {
                    var buildId = resolveBuildIdFromCacheName(name);
                    if (buildId !== '' && buildId !== BUILD_ID && previousBuildIds.indexOf(buildId) < 0) {
                        previousBuildIds.push(buildId);
                    }
                });
                var previousBuildId = previousBuildIds.length ? previousBuildIds[previousBuildIds.length - 1] : '';
                return Promise.all(keys.map(function (name) {
                    var buildId = resolveBuildIdFromCacheName(name);
                    if (buildId === '' || buildId === BUILD_ID || buildId === previousBuildId) {
                        return null;
                    }
                    return caches.delete(name);
                }));
            }).then(function () {
                return self.clients.claim();
            })
        );
    });

    self.addEventListener('sync', function (event) {
        if (event.tag === ANSWER_SYNC_TAG) {
            event.waitUntil(flushQueuedAnswers());
        }
    });

    self.addEventListener('message', function (event) {
        var data = event && event.data && typeof event.data === 'object' ? event.data : null;
        if (!data) {
            return;
        }
        if (String(data.type || '') === 'CBT_PRECACHE_MEDIA_URLS') {
            var mediaPromise = precacheMediaUrls(data.urls);
            if (event && typeof event.waitUntil === 'function') {
                event.waitUntil(mediaPromise);
            }
            return;
        }
        if (String(data.type || '') === 'CBT_FLUSH_ANSWER_QUEUE') {
            var flushPromise = flushQueuedAnswers();
            if (event && typeof event.waitUntil === 'function') {
                event.waitUntil(flushPromise);
            }
        }
    });

    self.addEventListener('fetch', function (event) {
        var request = event.request;
        if (!request || request.method !== 'GET') {
            return;
        }

        var url;
        try {
            url = new URL(request.url);
        } catch (error) {
            return;
        }

        if (!isSameOrigin(url)) {
            return;
        }

        if (isStaticBuildAsset(url)) {
            event.respondWith(cacheFirstStatic(request));
            return;
        }

        if (isPrecacheMediaUrl(url)) {
            event.respondWith(cacheFirstMedia(request));
            return;
        }

        if (isNavigationRequest(request) && isWithinScope(url)) {
            event.respondWith(networkFirstNavigation(request));
        }
    });
})();
JS;
    }

    private static function render_service_worker_offline_html(): string
    {
        return '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CBT Offline</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#edf4ff;color:#16324f}.card{max-width:520px;margin:24px;padding:24px;border-radius:18px;background:#fff;box-shadow:0 18px 44px rgba(35,73,132,.14)}h1{font-size:24px;margin:0 0 10px}p{line-height:1.55;margin:0;color:#506a86}</style><body><main class="card"><h1>CBT sedang offline</h1><p>Halaman ujian belum tersedia di cache browser ini. Sambungkan koneksi lalu muat ulang halaman.</p></main></body></html>';
    }

    /**
     * @param mixed $value
     */
    private static function service_worker_json($value): string
    {
        $encoded = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : 'null';
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $entry
     * @return array<int,string>
     */
    private static function get_vite_entry_css_files(array $manifest, array $entry): array
    {
        $visited = [];
        $css_files = self::collect_vite_entry_css_files($manifest, $entry, $visited);

        if ($css_files !== []) {
            return array_values(array_unique($css_files));
        }

        $style_entry = self::get_vite_manifest_entry($manifest, 'style.css');
        if ($style_entry !== null && !empty($style_entry['file']) && is_string($style_entry['file'])) {
            $css_files[] = $style_entry['file'];
        }

        return array_values(array_unique($css_files));
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $entry
     * @param array<string,bool>  $visited
     * @return array<int,string>
     */
    private static function collect_vite_entry_css_files(array $manifest, array $entry, array &$visited): array
    {
        $visit_key = self::build_manifest_entry_visit_key($entry);
        if ($visit_key !== '') {
            if (isset($visited[$visit_key])) {
                return [];
            }

            $visited[$visit_key] = true;
        }

        $css_files = [];
        if (!empty($entry['imports']) && is_array($entry['imports'])) {
            foreach ($entry['imports'] as $import_key) {
                if (!is_string($import_key) || $import_key === '') {
                    continue;
                }

                $import_entry = self::get_vite_manifest_entry($manifest, $import_key);
                if ($import_entry === null) {
                    continue;
                }

                $css_files = array_merge(
                    $css_files,
                    self::collect_vite_entry_css_files($manifest, $import_entry, $visited)
                );
            }
        }

        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                if (is_string($css_file) && $css_file !== '') {
                    $css_files[] = $css_file;
                }
            }
        }

        return array_values(array_unique($css_files));
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function build_manifest_entry_visit_key(array $entry): string
    {
        if (!empty($entry['src']) && is_string($entry['src'])) {
            return 'src:' . ltrim((string) $entry['src'], './');
        }

        if (!empty($entry['file']) && is_string($entry['file'])) {
            return 'file:' . ltrim((string) $entry['file'], './');
        }

        if (!empty($entry['name']) && is_string($entry['name'])) {
            return 'name:' . (string) $entry['name'];
        }

        return '';
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
        $frontend_mode = self::current_frontend_mode();
        $rest_base_absolute = self::ensure_trailing_slash((string) rest_url('cbt/v1'));
        $rest_base_path = (string) wp_parse_url($rest_base_absolute, PHP_URL_PATH);
        $rest_base_path = self::ensure_trailing_slash($rest_base_path !== '' ? $rest_base_path : '/wp-json/cbt/v1/');
        $branding = self::get_setup_branding_config();
        $security = self::get_setup_security_config();
        $developer_page_url = '';
        $service_worker_config = self::build_service_worker_frontend_config($asset_source, $frontend_mode);

        if (class_exists('CBT_Admin_Developer_Service') && current_user_can('manage_options')) {
            $developer_page_url = CBT_Admin_Developer_Service::developer_page_url();
        }

        wp_localize_script(self::FRONTEND_HANDLE, 'CBTExamFrontendConfig', [
            'restBase' => $rest_base_absolute,
            'restBasePath' => $rest_base_path,
            'siteName' => $branding['school_name'],
            'examProgramName' => $branding['exam_program_name'],
            'schoolName' => $branding['school_name'],
            'schoolMotto' => $branding['school_motto'],
            'schoolLogoUrl' => $branding['logo_url'],
            'schoolLogo1Url' => $branding['logo_1_url'],
            'schoolLogo2Url' => $branding['logo_2_url'],
            'pluginAuthor' => 'COBLAX',
            'pluginVersion' => CBT_EXAM_SYSTEM_VERSION,
            'securityForceFullscreen' => $security['force_fullscreen'],
            'securityBlockCopyPaste' => $security['block_copy_paste'],
            'securityBlockBrowserInspectionShortcuts' => $security['block_browser_inspection_shortcuts'],
            'securityLogEvents' => $security['log_security_events'],
            'securityDetectIdle' => $security['detect_idle_during_exam'],
            'securityDetectHeartbeatLost' => $security['detect_heartbeat_lost'],
            'securityIdleThresholdMinutes' => $security['idle_threshold_minutes'],
            'securityIdleThresholdSeconds' => $security['idle_threshold_minutes'] * MINUTE_IN_SECONDS,
            'securityDetectScreenshotKeys' => $security['detect_screenshot_keys'],
            'securityShowExamWatermark' => $security['show_exam_watermark'],
            'securityExamWatermarkOpacity' => $security['exam_watermark_opacity'],
            'homeUrl' => (string) home_url('/'),
            'frontendMode' => $frontend_mode,
            'studentFrontendUrl' => self::frontend_page_url('student'),
            'supervisorFrontendUrl' => self::frontend_page_url('supervisor'),
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
            'serviceWorkerEnabled' => $service_worker_config['enabled'],
            'serviceWorkerUrl' => $service_worker_config['url'],
            'serviceWorkerScope' => $service_worker_config['scope'],
            'serviceWorkerBuildId' => $service_worker_config['build_id'],
            'answerSyncBackgroundEnabled' => $service_worker_config['enabled'],
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

        return has_shortcode((string) $post->post_content, self::STUDENT_SHORTCODE)
            || has_shortcode((string) $post->post_content, self::SUPERVISOR_SHORTCODE);
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

        $student_page_id = self::get_canonical_frontend_page_id('student');
        if ($student_page_id > 0 && (int) $post->ID === $student_page_id) {
            return true;
        }

        $supervisor_page_id = self::get_canonical_frontend_page_id('supervisor');
        if ($supervisor_page_id > 0 && (int) $post->ID === $supervisor_page_id) {
            return true;
        }

        $post_slug = sanitize_title((string) $post->post_name);
        return in_array($post_slug, [self::STUDENT_FRONTEND_PAGE_SLUG, self::SUPERVISOR_FRONTEND_PAGE_SLUG], true);
    }

    private static function get_canonical_frontend_page_id(string $mode = 'student'): int
    {
        $normalized_mode = $mode === 'supervisor' ? 'supervisor' : 'student';
        $option_key = $normalized_mode === 'supervisor'
            ? self::SUPERVISOR_FRONTEND_PAGE_OPTION
            : self::STUDENT_FRONTEND_PAGE_OPTION;
        $slug = $normalized_mode === 'supervisor'
            ? self::SUPERVISOR_FRONTEND_PAGE_SLUG
            : self::STUDENT_FRONTEND_PAGE_SLUG;

        $stored_id = (int) get_option($option_key, 0);
        if ($stored_id > 0) {
            return $stored_id;
        }

        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page instanceof WP_Post) {
            return (int) $page->ID;
        }

        return 0;
    }

    private static function current_frontend_mode(): string
    {
        if (!is_singular()) {
            return 'student';
        }

        $post = get_queried_object();
        if (!($post instanceof WP_Post)) {
            return 'student';
        }

        $post_content = (string) $post->post_content;
        if (has_shortcode($post_content, self::SUPERVISOR_SHORTCODE)) {
            return 'supervisor';
        }

        $supervisor_page_id = self::get_canonical_frontend_page_id('supervisor');
        if ($supervisor_page_id > 0 && (int) $post->ID === $supervisor_page_id) {
            return 'supervisor';
        }

        if (sanitize_title((string) $post->post_name) === self::SUPERVISOR_FRONTEND_PAGE_SLUG) {
            return 'supervisor';
        }

        return 'student';
    }

    private static function frontend_page_url(string $mode = 'student'): string
    {
        $normalized_mode = $mode === 'supervisor' ? 'supervisor' : 'student';
        $page_id = self::get_canonical_frontend_page_id($normalized_mode);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        $slug = $normalized_mode === 'supervisor'
            ? self::SUPERVISOR_FRONTEND_PAGE_SLUG
            : self::STUDENT_FRONTEND_PAGE_SLUG;

        return self::ensure_trailing_slash((string) home_url('/' . $slug));
    }

    private static function ensure_trailing_slash(string $value): string
    {
        return rtrim($value, '/') . '/';
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
        $is_supervisor_mode = self::current_frontend_mode() === 'supervisor';
        $status_label = $is_supervisor_mode ? 'Memuat Pengawas' : 'Memuat CBT';
        $title = $is_supervisor_mode ? 'Booting Dashboard Pengawas' : 'Booting App';
        $description = $is_supervisor_mode
            ? 'Dashboard pengawas sedang disiapkan dan akan tampil sesaat lagi.'
            : 'Antarmuka ujian sedang disiapkan dan akan tampil sesaat lagi.';

        return implode('', [
            '<div class="cbt-frontpage__shell">',
            '<div class="cbt-boot-shell cbt-boot-shell--compact" role="status" aria-live="polite" aria-busy="true">',
            '<section class="cbt-boot-shell__card cbt-boot-shell__card--compact">',
            '<div class="cbt-boot-shell__block cbt-boot-shell__app cbt-boot-shell__app--compact">',
            '<span class="cbt-boot-shell__status"><span class="cbt-boot-shell__dot" aria-hidden="true"></span>' . esc_html($status_label) . '</span>',
            '<h2>' . esc_html($title) . '</h2>',
            '<p>' . esc_html($description) . '</p>',
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
     * @return array{exam_program_name:string,school_name:string,school_motto:string,logo_url:string,logo_1_url:string,logo_2_url:string}
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
        $exam_program_name = isset($raw['exam_program_name'])
            ? trim(sanitize_text_field((string) $raw['exam_program_name']))
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
            'exam_program_name' => $exam_program_name,
            'school_name' => $school_name,
            'school_motto' => $school_motto,
            'logo_url' => $logo_1_url,
            'logo_1_url' => $logo_1_url,
            'logo_2_url' => $logo_2_url,
        ];
    }

    /**
     * @return array{
     *     force_fullscreen:int,
     *     block_copy_paste:int,
     *     block_browser_inspection_shortcuts:int,
     *     log_security_events:int,
     *     detect_idle_during_exam:int,
     *     detect_heartbeat_lost:int,
     *     idle_threshold_minutes:int,
     *     detect_screenshot_keys:int,
     *     show_exam_watermark:int,
     *     exam_watermark_opacity:float
     * }
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
            'block_browser_inspection_shortcuts' => !empty($raw['block_browser_inspection_shortcuts']) ? 1 : 0,
            'log_security_events' => !empty($raw['log_security_events']) ? 1 : 0,
            'detect_idle_during_exam' => !array_key_exists('detect_idle_during_exam', $raw) || !empty($raw['detect_idle_during_exam']) ? 1 : 0,
            'detect_heartbeat_lost' => !empty($raw['detect_heartbeat_lost']) ? 1 : 0,
            'idle_threshold_minutes' => max(1, absint($raw['idle_threshold_minutes'] ?? 5)),
            'detect_screenshot_keys' => !empty($raw['detect_screenshot_keys']) ? 1 : 0,
            'show_exam_watermark' => !empty($raw['show_exam_watermark']) ? 1 : 0,
            'exam_watermark_opacity' => self::normalize_exam_watermark_opacity($raw['exam_watermark_opacity'] ?? self::EXAM_WATERMARK_OPACITY_DEFAULT),
        ];
    }

    private static function normalize_exam_watermark_opacity($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (!is_numeric($value)) {
            $opacity = self::EXAM_WATERMARK_OPACITY_DEFAULT;
        } else {
            $opacity = (float) $value;
        }

        $opacity = max(self::EXAM_WATERMARK_OPACITY_MIN, min(self::EXAM_WATERMARK_OPACITY_MAX, $opacity));
        return round($opacity, 3);
    }
}
