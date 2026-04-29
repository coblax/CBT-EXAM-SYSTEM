<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Assets
{
    private const VITE_BUILD_DIR = 'public/build/';
    private const VITE_MANIFEST_RELATIVE = 'public/build/manifest.json';
    private const ADMIN_UI_CSS_RELATIVE = 'admin/assets/cbt-admin-ui.css';
    private const ADMIN_UI_HANDLE = 'cbt-admin-ui';
    private const MATH_ENTRY = 'src/admin/math-main.js';
    private const MATH_HANDLE = 'cbt-admin-math';

    public static function enqueue_admin_assets(): void
    {
        if (self::is_cbt_admin_page()) {
            wp_enqueue_style(
                self::ADMIN_UI_HANDLE,
                CBT_EXAM_SYSTEM_URL . self::ADMIN_UI_CSS_RELATIVE,
                [],
                self::build_plain_asset_version(self::ADMIN_UI_CSS_RELATIVE)
            );
        }

        if (!self::should_enqueue_math_assets()) {
            return;
        }

        $manifest = self::get_vite_manifest();
        if ($manifest === []) {
            return;
        }

        $entry = self::get_manifest_entry($manifest, self::MATH_ENTRY);
        if ($entry === null || empty($entry['file']) || !is_string($entry['file'])) {
            return;
        }

        foreach (self::get_entry_css_files($manifest, $entry) as $index => $css_file) {
            $handle = $index === 0 ? self::MATH_HANDLE : self::MATH_HANDLE . '-style-' . $index;
            wp_enqueue_style(
                $handle,
                self::build_asset_url($css_file),
                [],
                self::build_asset_version($css_file)
            );
        }

        wp_enqueue_script(
            self::MATH_HANDLE,
            self::build_asset_url((string) $entry['file']),
            [],
            self::build_asset_version((string) $entry['file']),
            true
        );
    }

    public static function filter_script_loader_tag(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::MATH_HANDLE) {
            return $tag;
        }

        return sprintf(
            '<script type="module" src="%1$s" id="%2$s-js"></script>' . "\n",
            esc_url($src),
            esc_attr($handle)
        );
    }

    private static function should_enqueue_math_assets(): bool
    {
        $page = self::get_current_admin_page_slug();
        if ($page === '') {
            return false;
        }

        return in_array($page, [
            'cbt-exams',
            'cbt-question-bank',
            'cbt-questions-mc',
            'cbt-questions-ma',
            'cbt-questions-tf',
            'cbt-questions-sa',
            'cbt-questions-essay',
        ], true);
    }

    private static function is_cbt_admin_page(): bool
    {
        $page = self::get_current_admin_page_slug();
        return $page !== '' && str_starts_with($page, 'cbt-');
    }

    private static function get_current_admin_page_slug(): string
    {
        if (!is_admin()) {
            return '';
        }

        return isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_vite_manifest(): array
    {
        $manifest_path = CBT_EXAM_SYSTEM_PATH . self::VITE_MANIFEST_RELATIVE;
        if (!file_exists($manifest_path)) {
            return [];
        }

        $contents = file_get_contents($manifest_path);
        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>|null
     */
    private static function get_manifest_entry(array $manifest, string $entry_key): ?array
    {
        if (isset($manifest[$entry_key]) && is_array($manifest[$entry_key])) {
            return $manifest[$entry_key];
        }

        foreach ($manifest as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                continue;
            }

            if ($key === $entry_key || str_ends_with($key, '/' . ltrim($entry_key, './'))) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $entry
     * @return string[]
     */
    private static function get_entry_css_files(array $manifest, array $entry): array
    {
        $visited = [];
        $files = self::collect_entry_css_files($manifest, $entry, $visited);

        if (empty($files) && isset($manifest['style.css']) && is_array($manifest['style.css'])) {
            $style_entry = $manifest['style.css'];
            if (!empty($style_entry['file']) && is_string($style_entry['file'])) {
                $files[] = $style_entry['file'];
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $entry
     * @param array<string,bool>  $visited
     * @return string[]
     */
    private static function collect_entry_css_files(array $manifest, array $entry, array &$visited): array
    {
        $visit_key = self::build_manifest_entry_visit_key($entry);
        if ($visit_key !== '') {
            if (isset($visited[$visit_key])) {
                return [];
            }

            $visited[$visit_key] = true;
        }

        $files = [];
        if (!empty($entry['imports']) && is_array($entry['imports'])) {
            foreach ($entry['imports'] as $import_key) {
                if (!is_string($import_key) || $import_key === '') {
                    continue;
                }

                $import_entry = self::get_manifest_entry($manifest, $import_key);
                if ($import_entry === null) {
                    continue;
                }

                $files = array_merge($files, self::collect_entry_css_files($manifest, $import_entry, $visited));
            }
        }

        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                if (is_string($css_file) && $css_file !== '') {
                    $files[] = $css_file;
                }
            }
        }

        return array_values(array_unique($files));
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

    private static function build_asset_url(string $relative_path): string
    {
        return trailingslashit(CBT_EXAM_SYSTEM_URL . self::VITE_BUILD_DIR) . ltrim($relative_path, '/');
    }

    private static function build_asset_version(string $relative_path): string
    {
        $absolute_path = CBT_EXAM_SYSTEM_PATH . self::VITE_BUILD_DIR . ltrim($relative_path, '/');
        if (!file_exists($absolute_path)) {
            return CBT_EXAM_SYSTEM_VERSION;
        }

        $mtime = filemtime($absolute_path);
        return $mtime ? (string) $mtime : CBT_EXAM_SYSTEM_VERSION;
    }

    private static function build_plain_asset_version(string $relative_path): string
    {
        $absolute_path = CBT_EXAM_SYSTEM_PATH . ltrim($relative_path, '/');
        if (!file_exists($absolute_path)) {
            return CBT_EXAM_SYSTEM_VERSION;
        }

        $mtime = filemtime($absolute_path);
        return $mtime ? (string) $mtime : CBT_EXAM_SYSTEM_VERSION;
    }
}
