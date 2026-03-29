<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Update_Release_Helper
{
    private const REPOSITORY_OWNER = 'coblax';
    private const REPOSITORY_NAME = 'CBT-EXAM-SYSTEM';
    private const REPOSITORY_SLUG = self::REPOSITORY_OWNER . '/' . self::REPOSITORY_NAME;
    private const RELEASE_API_URL = 'https://api.github.com/repos/' . self::REPOSITORY_SLUG . '/releases/latest';
    private const RELEASE_HTML_URL = 'https://github.com/' . self::REPOSITORY_SLUG . '/releases/latest';
    private const RELEASES_API_URL = 'https://api.github.com/repos/' . self::REPOSITORY_SLUG . '/releases?per_page=1';
    private const RELEASES_HTML_URL = 'https://github.com/' . self::REPOSITORY_SLUG . '/releases';
    private const REPOSITORY_URL = 'https://github.com/' . self::REPOSITORY_SLUG;
    private const RELEASE_STATE_TRANSIENT = 'cbt_update_release_state_v1';
    private const RELEASE_STATE_TTL = 21600;
    private const PACKAGE_ASSET_NAME = 'cbt-exam-system.zip';
    private const MANIFEST_ASSET_NAME = 'cbt-update-manifest.json';
    private const PLUGIN_BASENAME = 'cbt-exam-system/cbt-exam-system.php';
    private const PLUGIN_ROOT_DIRECTORY = 'cbt-exam-system/';
    private const PLUGIN_BOOTSTRAP_PATH = 'cbt-exam-system/cbt-exam-system.php';

    public static function repository_slug(): string
    {
        return self::REPOSITORY_SLUG;
    }

    public static function repository_url(): string
    {
        return self::REPOSITORY_URL;
    }

    public static function latest_release_api_url(): string
    {
        return self::RELEASE_API_URL;
    }

    public static function latest_release_html_url(): string
    {
        return self::RELEASE_HTML_URL;
    }

    public static function releases_api_url(): string
    {
        return self::RELEASES_API_URL;
    }

    public static function releases_html_url(): string
    {
        return self::RELEASES_HTML_URL;
    }

    public static function package_asset_name(): string
    {
        return self::PACKAGE_ASSET_NAME;
    }

    public static function manifest_asset_name(): string
    {
        return self::MANIFEST_ASSET_NAME;
    }

    public static function release_state_transient(): string
    {
        return self::RELEASE_STATE_TRANSIENT;
    }

    public static function release_state_ttl(): int
    {
        return self::RELEASE_STATE_TTL;
    }

    public static function plugin_basename(): string
    {
        return self::PLUGIN_BASENAME;
    }

    public static function current_version(): string
    {
        return defined('CBT_EXAM_SYSTEM_VERSION') ? (string) CBT_EXAM_SYSTEM_VERSION : '';
    }

    /**
     * @return array<string,mixed>|false
     */
    public static function get_cached_release_state()
    {
        $state = get_transient(self::RELEASE_STATE_TRANSIENT);

        return is_array($state) ? $state : false;
    }

    public static function clear_cached_release_state(): bool
    {
        return delete_transient(self::RELEASE_STATE_TRANSIENT);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_release_state(bool $force = false): array
    {
        if (!$force) {
            $cached = self::get_cached_release_state();
            if (is_array($cached)) {
                return $cached;
            }
        }

        $state = self::fetch_latest_release_state();
        set_transient(self::RELEASE_STATE_TRANSIENT, $state, self::RELEASE_STATE_TTL);

        return $state;
    }

    /**
     * @return array<string,mixed>
     */
    public static function fetch_latest_release_state(): array
    {
        $checked_at = time();
        $release_response = self::perform_remote_get(self::RELEASE_API_URL);
        if (is_wp_error($release_response)) {
            if ($release_response->get_error_code() === 'remote_request_404') {
                return self::resolve_no_release_state($checked_at);
            }

            return self::build_failed_state($checked_at, $release_response->get_error_message());
        }

        $release_body = json_decode((string) wp_remote_retrieve_body($release_response), true);
        if (!is_array($release_body)) {
            return self::build_failed_state($checked_at, 'Response GitHub release tidak valid.');
        }

        $manifest_asset = self::find_release_asset($release_body, self::MANIFEST_ASSET_NAME);
        if (!is_array($manifest_asset)) {
            return self::build_failed_state($checked_at, 'Asset manifest release tidak ditemukan.');
        }

        $package_asset = self::find_release_asset($release_body, self::PACKAGE_ASSET_NAME);
        if (!is_array($package_asset)) {
            return self::build_failed_state($checked_at, 'Asset zip release tidak ditemukan.');
        }

        $manifest_url = self::normalize_url((string) ($manifest_asset['browser_download_url'] ?? ''));
        if ($manifest_url === '') {
            return self::build_failed_state($checked_at, 'URL asset manifest release tidak valid.');
        }

        $manifest_response = self::perform_remote_get($manifest_url);
        if (is_wp_error($manifest_response)) {
            return self::build_failed_state($checked_at, $manifest_response->get_error_message());
        }

        $manifest_body = json_decode((string) wp_remote_retrieve_body($manifest_response), true);
        if (!is_array($manifest_body)) {
            return self::build_failed_state($checked_at, 'Isi manifest release tidak valid.');
        }

        $normalized_manifest = self::normalize_manifest($manifest_body, [
            'tag' => (string) ($release_body['tag_name'] ?? ''),
            'published_at' => (string) ($release_body['published_at'] ?? ''),
            'download_url' => self::normalize_url((string) ($package_asset['browser_download_url'] ?? '')),
        ]);
        if (is_wp_error($normalized_manifest)) {
            return self::build_failed_state($checked_at, $normalized_manifest->get_error_message());
        }

        $preflight = self::build_preflight($normalized_manifest);
        $status = self::determine_release_state_status($normalized_manifest, $preflight);

        return [
            'checked_at' => $checked_at,
            'status' => $status,
            'error_message' => '',
            'manifest' => $normalized_manifest,
            'release' => [
                'tag' => (string) ($release_body['tag_name'] ?? ($normalized_manifest['tag'] ?? '')),
                'name' => sanitize_text_field((string) ($release_body['name'] ?? '')),
                'html_url' => self::normalize_url((string) ($release_body['html_url'] ?? self::RELEASE_HTML_URL)),
                'published_at' => (string) ($release_body['published_at'] ?? ($normalized_manifest['published_at'] ?? '')),
            ],
            'preflight' => $preflight,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>
     */
    public static function build_preflight(array $manifest): array
    {
        $items = [];

        $requires_php = isset($manifest['requires_php']) ? (string) $manifest['requires_php'] : '';
        $requires_wp = isset($manifest['requires_wp']) ? (string) $manifest['requires_wp'] : '';
        $tested_up_to = isset($manifest['tested_up_to']) ? (string) $manifest['tested_up_to'] : '';
        $download_url = isset($manifest['download_url']) ? (string) $manifest['download_url'] : '';
        $sha256 = isset($manifest['sha256']) ? (string) $manifest['sha256'] : '';

        $php_ok = $requires_php !== '' && self::is_php_compatible($requires_php);
        $items[] = [
            'key' => 'php_version',
            'label' => 'Versi PHP',
            'status' => $php_ok ? 'ok' : 'blocked',
            'message' => $php_ok
                ? 'Server memenuhi minimal PHP ' . $requires_php . '.'
                : ($requires_php === '' ? 'Manifest belum mencantumkan requires_php.' : 'Server belum memenuhi minimal PHP ' . $requires_php . '.'),
        ];

        $wp_ok = $requires_wp !== '' && self::is_wp_compatible($requires_wp);
        $items[] = [
            'key' => 'wordpress_version',
            'label' => 'Versi WordPress',
            'status' => $wp_ok ? 'ok' : 'blocked',
            'message' => $wp_ok
                ? 'Instalasi memenuhi minimal WordPress ' . $requires_wp . '.'
                : ($requires_wp === '' ? 'Manifest belum mencantumkan requires_wp.' : 'Instalasi belum memenuhi minimal WordPress ' . $requires_wp . '.'),
        ];

        $package_meta_ok = ($download_url !== '' && $sha256 !== '');
        $items[] = [
            'key' => 'package_metadata',
            'label' => 'Metadata package',
            'status' => $package_meta_ok ? 'ok' : 'blocked',
            'message' => $package_meta_ok
                ? 'URL package dan checksum tersedia.'
                : 'Manifest release harus menyediakan download_url dan sha256.',
        ];

        $filesystem_ok = self::is_filesystem_ready_for_upgrade();
        $items[] = [
            'key' => 'filesystem',
            'label' => 'Filesystem plugin',
            'status' => $filesystem_ok ? 'ok' : 'blocked',
            'message' => $filesystem_ok
                ? 'Direktori plugin siap di-upgrade.'
                : 'Direktori plugin atau parent plugin tidak writable untuk updater v1.',
        ];

        if ($tested_up_to !== '') {
            $status = version_compare(self::current_wp_version(), $tested_up_to, '>') ? 'warning' : 'ok';
            $message = $status === 'warning'
                ? 'Instalasi WordPress lebih baru dari tested_up_to ' . $tested_up_to . '.'
                : 'Release sudah ditandai tested_up_to ' . $tested_up_to . '.';
            $items[] = [
                'key' => 'tested_up_to',
                'label' => 'Tested up to',
                'status' => $status,
                'message' => $message,
            ];
        }

        $package_validation = self::validate_release_package($manifest);
        if (is_wp_error($package_validation)) {
            $items[] = [
                'key' => 'package_validation',
                'label' => 'Validasi package',
                'status' => 'blocked',
                'message' => $package_validation->get_error_message(),
            ];
        } else {
            $items[] = [
                'key' => 'package_validation',
                'label' => 'Validasi package',
                'status' => 'ok',
                'message' => 'Checksum dan struktur zip release valid.',
            ];
        }

        $overall_status = 'ok';
        foreach ($items as $item) {
            $item_status = isset($item['status']) ? (string) $item['status'] : 'ok';
            if ($item_status === 'blocked') {
                $overall_status = 'blocked';
                break;
            }
            if ($item_status === 'warning') {
                $overall_status = 'warning';
            }
        }

        return [
            'status' => $overall_status,
            'items' => $items,
            'has_blocked' => $overall_status === 'blocked',
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return true|\WP_Error
     */
    public static function validate_install_ready(array $state)
    {
        $status = isset($state['status']) ? (string) $state['status'] : 'not_checked';
        if ($status === 'check_failed') {
            return new WP_Error('check_failed', isset($state['error_message']) ? (string) $state['error_message'] : 'Cek update gagal.');
        }
        if ($status === 'no_release') {
            return new WP_Error('no_release', isset($state['error_message']) ? (string) $state['error_message'] : 'Belum ada GitHub Release resmi pada repo sumber updater.');
        }

        $manifest = isset($state['manifest']) && is_array($state['manifest']) ? $state['manifest'] : [];
        if (empty($manifest)) {
            return new WP_Error('missing_manifest', 'Manifest release belum tersedia.');
        }

        $remote_version = isset($manifest['version']) ? (string) $manifest['version'] : '';
        if ($remote_version === '' || version_compare($remote_version, self::current_version(), '<=')) {
            return new WP_Error('not_newer', 'Tidak ada versi update yang lebih baru untuk diinstall.');
        }

        $preflight = isset($state['preflight']) && is_array($state['preflight']) ? $state['preflight'] : [];
        if ((string) ($preflight['status'] ?? 'blocked') === 'blocked') {
            return new WP_Error('preflight_blocked', 'Preflight update masih blocked. Perbaiki checklist sebelum install.');
        }

        return true;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|\WP_Error
     */
    public static function prepare_install_context(array $state)
    {
        $ready = self::validate_install_ready($state);
        if (is_wp_error($ready)) {
            return $ready;
        }

        $manifest = isset($state['manifest']) && is_array($state['manifest']) ? $state['manifest'] : [];
        self::ensure_file_helpers_loaded();
        $package_path = download_url((string) ($manifest['download_url'] ?? ''));
        if (is_wp_error($package_path)) {
            return new WP_Error('download_failed', 'Gagal mengunduh package update: ' . $package_path->get_error_message());
        }

        $validation = self::validate_downloaded_package($package_path, (string) ($manifest['sha256'] ?? ''));
        if (is_wp_error($validation)) {
            @unlink($package_path);
            return $validation;
        }

        return [
            'manifest' => $manifest,
            'package_path' => $package_path,
            'update_response' => self::build_update_response_object($manifest, $package_path),
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     */
    public static function build_update_response_object(array $manifest, string $package_path): object
    {
        return (object) [
            'id' => self::repository_url(),
            'slug' => 'cbt-exam-system',
            'plugin' => self::plugin_basename(),
            'new_version' => (string) ($manifest['version'] ?? ''),
            'url' => self::latest_release_html_url(),
            'package' => $package_path,
            'tested' => (string) ($manifest['tested_up_to'] ?? ''),
            'requires' => (string) ($manifest['requires_wp'] ?? ''),
            'requires_php' => (string) ($manifest['requires_php'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return true|\WP_Error
     */
    public static function validate_release_package(array $manifest)
    {
        $download_url = isset($manifest['download_url']) ? (string) $manifest['download_url'] : '';
        $sha256 = isset($manifest['sha256']) ? (string) $manifest['sha256'] : '';
        if ($download_url === '' || $sha256 === '') {
            return new WP_Error('missing_package_data', 'Metadata package update belum lengkap.');
        }

        self::ensure_file_helpers_loaded();
        $package_path = download_url($download_url);
        if (is_wp_error($package_path)) {
            return new WP_Error('download_failed', 'Gagal mengunduh package release: ' . $package_path->get_error_message());
        }

        try {
            return self::validate_downloaded_package($package_path, $sha256);
        } finally {
            @unlink($package_path);
        }
    }

    /**
     * @return true|\WP_Error
     */
    public static function validate_downloaded_package(string $package_path, string $expected_sha256)
    {
        if ($package_path === '' || !file_exists($package_path)) {
            return new WP_Error('missing_package_file', 'File package update tidak ditemukan.');
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $expected_sha256)) {
            return new WP_Error('invalid_checksum', 'Checksum sha256 release tidak valid.');
        }

        $actual_hash = hash_file('sha256', $package_path);
        if (!is_string($actual_hash) || !hash_equals(strtolower($expected_sha256), strtolower($actual_hash))) {
            return new WP_Error('checksum_mismatch', 'Checksum package update tidak cocok dengan manifest release.');
        }

        return self::validate_zip_package_structure($package_path);
    }

    /**
     * @return true|\WP_Error
     */
    public static function validate_zip_package_structure(string $package_path)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_extension_missing', 'Ekstensi ZipArchive wajib aktif untuk memvalidasi package update.');
        }

        $zip = new ZipArchive();
        if ($zip->open($package_path) !== true) {
            return new WP_Error('zip_open_failed', 'Package update tidak bisa dibuka sebagai zip.');
        }

        $has_bootstrap = false;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry_name = (string) $zip->getNameIndex($index);
            $entry_name = ltrim(str_replace('\\', '/', $entry_name), '/');
            if ($entry_name === '') {
                continue;
            }

            if (!str_starts_with($entry_name, self::PLUGIN_ROOT_DIRECTORY)) {
                $zip->close();
                return new WP_Error('invalid_package_root', 'Root folder package update harus berupa cbt-exam-system/.');
            }

            if ($entry_name === self::PLUGIN_BOOTSTRAP_PATH) {
                $has_bootstrap = true;
            }
        }

        $zip->close();

        if (!$has_bootstrap) {
            return new WP_Error('missing_plugin_bootstrap', 'Package update tidak memuat file bootstrap plugin cbt-exam-system.php.');
        }

        return true;
    }

    public static function current_wp_version(): string
    {
        if (function_exists('wp_get_wp_version')) {
            return (string) wp_get_wp_version();
        }

        global $wp_version;
        if (is_string($wp_version) && $wp_version !== '') {
            return $wp_version;
        }

        return '0.0';
    }

    /**
     * @param array<string,mixed> $release_body
     * @return array<string,mixed>|null
     */
    public static function find_release_asset(array $release_body, string $asset_name): ?array
    {
        $assets = isset($release_body['assets']) && is_array($release_body['assets']) ? $release_body['assets'] : [];
        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            if ((string) ($asset['name'] ?? '') === $asset_name) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,string> $defaults
     * @return array<string,mixed>|\WP_Error
     */
    public static function normalize_manifest(array $raw, array $defaults = [])
    {
        $version = sanitize_text_field((string) ($raw['version'] ?? ''));
        if ($version === '') {
            return new WP_Error('invalid_manifest_version', 'Manifest release tidak memuat version yang valid.');
        }

        $download_url = self::normalize_url((string) ($raw['download_url'] ?? ($defaults['download_url'] ?? '')));
        $sha256 = strtolower(preg_replace('/[^a-f0-9]/i', '', (string) ($raw['sha256'] ?? '')) ?? '');
        if ($sha256 !== '' && strlen($sha256) !== 64) {
            return new WP_Error('invalid_manifest_sha256', 'Manifest release memuat sha256 yang tidak valid.');
        }

        $changelog_raw = $raw['changelog'] ?? '';
        if (is_array($changelog_raw)) {
            $normalized_lines = [];
            foreach ($changelog_raw as $line) {
                $line = trim(is_scalar($line) ? (string) $line : '');
                if ($line !== '') {
                    $normalized_lines[] = $line;
                }
            }
            $changelog = implode("\n", $normalized_lines);
        } else {
            $changelog = trim(is_scalar($changelog_raw) ? (string) $changelog_raw : '');
        }

        return [
            'version' => $version,
            'tag' => sanitize_text_field((string) ($raw['tag'] ?? ($defaults['tag'] ?? ''))),
            'published_at' => sanitize_text_field((string) ($raw['published_at'] ?? ($defaults['published_at'] ?? ''))),
            'download_url' => $download_url,
            'sha256' => $sha256,
            'requires_php' => sanitize_text_field((string) ($raw['requires_php'] ?? '')),
            'requires_wp' => sanitize_text_field((string) ($raw['requires_wp'] ?? '')),
            'tested_up_to' => sanitize_text_field((string) ($raw['tested_up_to'] ?? '')),
            'changelog' => $changelog,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $preflight
     */
    public static function determine_release_state_status(array $manifest, array $preflight): string
    {
        $remote_version = isset($manifest['version']) ? (string) $manifest['version'] : '';
        if ($remote_version === '' || version_compare($remote_version, self::current_version(), '<=')) {
            return 'up_to_date';
        }

        if ((string) ($preflight['status'] ?? 'blocked') === 'blocked') {
            return 'preflight_blocked';
        }

        return 'available';
    }

    private static function is_filesystem_ready_for_upgrade(): bool
    {
        $plugin_root = defined('WP_PLUGIN_DIR')
            ? rtrim((string) WP_PLUGIN_DIR, '/\\')
            : dirname(rtrim(CBT_EXAM_SYSTEM_PATH, '/\\'));
        $plugin_directory = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\');

        return is_writable($plugin_root) && is_writable($plugin_directory);
    }

    private static function is_php_compatible(string $requires_php): bool
    {
        if (function_exists('is_php_version_compatible')) {
            return (bool) is_php_version_compatible($requires_php);
        }

        return version_compare(PHP_VERSION, $requires_php, '>=');
    }

    private static function is_wp_compatible(string $requires_wp): bool
    {
        if (function_exists('is_wp_version_compatible')) {
            return (bool) is_wp_version_compatible($requires_wp);
        }

        return version_compare(self::current_wp_version(), $requires_wp, '>=');
    }

    private static function normalize_url(string $url): string
    {
        return $url !== '' ? esc_url_raw($url) : '';
    }

    private static function ensure_file_helpers_loaded(): void
    {
        if (function_exists('download_url')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function perform_remote_get(string $url)
    {
        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'CBT-Exam-System/' . self::current_version(),
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('remote_request_failed', 'Gagal menghubungi GitHub Releases: ' . $response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            $code = $status_code === 404 ? 'remote_request_404' : 'remote_request_failed';
            return new WP_Error($code, 'GitHub Releases merespons dengan status HTTP ' . $status_code . '.', ['status_code' => $status_code]);
        }

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_failed_state(int $checked_at, string $message): array
    {
        return [
            'checked_at' => $checked_at,
            'status' => 'check_failed',
            'error_message' => sanitize_text_field($message),
            'manifest' => [],
            'release' => [
                'tag' => '',
                'name' => '',
                'html_url' => self::latest_release_html_url(),
                'published_at' => '',
            ],
            'preflight' => [
                'status' => 'blocked',
                'items' => [],
                'has_blocked' => true,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function resolve_no_release_state(int $checked_at): array
    {
        $release_list_response = self::perform_remote_get(self::RELEASES_API_URL);
        if (is_wp_error($release_list_response)) {
            return self::build_failed_state($checked_at, $release_list_response->get_error_message());
        }

        $release_list = json_decode((string) wp_remote_retrieve_body($release_list_response), true);
        if (!is_array($release_list)) {
            return self::build_failed_state($checked_at, 'Response daftar GitHub Releases tidak valid.');
        }

        if ($release_list === []) {
            return self::build_no_release_state(
                $checked_at,
                'Belum ada GitHub Release resmi pada repo sumber. Publish release pertama terlebih dahulu agar updater bisa dipakai.'
            );
        }

        return self::build_no_release_state(
            $checked_at,
            'Belum ada GitHub Release stabil yang bisa dipakai updater. Pastikan release resmi memuat asset zip dan manifest.'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_no_release_state(int $checked_at, string $message): array
    {
        return [
            'checked_at' => $checked_at,
            'status' => 'no_release',
            'error_message' => sanitize_text_field($message),
            'manifest' => [],
            'release' => [
                'tag' => '',
                'name' => '',
                'html_url' => self::releases_html_url(),
                'published_at' => '',
            ],
            'preflight' => [
                'status' => 'blocked',
                'items' => [],
                'has_blocked' => true,
            ],
        ];
    }
}
