<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Update_Service
{
    public static function can_manage_updates(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $release_state = CBT_Update_Release_Helper::get_cached_release_state();
        $status = is_array($release_state) ? (string) ($release_state['status'] ?? 'not_checked') : 'not_checked';
        $manifest = is_array($release_state) && isset($release_state['manifest']) && is_array($release_state['manifest'])
            ? $release_state['manifest']
            : [];
        $release = is_array($release_state) && isset($release_state['release']) && is_array($release_state['release'])
            ? $release_state['release']
            : [];
        $preflight = is_array($release_state) && isset($release_state['preflight']) && is_array($release_state['preflight'])
            ? $release_state['preflight']
            : ['status' => 'blocked', 'items' => [], 'has_blocked' => false];
        $current_version = CBT_Update_Release_Helper::current_version();
        $remote_version = isset($manifest['version']) ? (string) $manifest['version'] : '';
        $has_checked_state = is_array($release_state);
        $has_update = $remote_version !== '' && version_compare($remote_version, $current_version, '>');
        $can_install = $has_update && (string) ($preflight['status'] ?? 'blocked') !== 'blocked' && $status !== 'check_failed';
        $status_meta = self::status_meta($status);
        $preflight_meta = self::preflight_meta((string) ($preflight['status'] ?? 'blocked'));
        $release_message = is_array($release_state) ? (string) ($release_state['error_message'] ?? '') : '';

        return [
            'notice' => $notice,
            'error' => $error,
            'current_version' => $current_version,
            'remote_version' => $remote_version,
            'has_update' => $has_update,
            'has_checked_state' => $has_checked_state,
            'can_install' => $can_install,
            'status' => $status,
            'status_meta' => $status_meta,
            'preflight_meta' => $preflight_meta,
            'release_state' => $release_state,
            'release' => $release,
            'manifest' => $manifest,
            'preflight' => $preflight,
            'check_action_url' => admin_url('admin-post.php'),
            'install_action_url' => admin_url('admin-post.php'),
            'page_url' => self::page_url(),
            'repo_label' => CBT_Update_Release_Helper::repository_slug(),
            'repo_url' => CBT_Update_Release_Helper::repository_url(),
            'release_url' => isset($release['html_url']) && is_string($release['html_url']) && $release['html_url'] !== ''
                ? $release['html_url']
                : ($status === 'no_release' ? CBT_Update_Release_Helper::releases_html_url() : CBT_Update_Release_Helper::latest_release_html_url()),
            'checked_at_label' => self::format_datetime(is_array($release_state) ? (int) ($release_state['checked_at'] ?? 0) : 0),
            'published_at_label' => self::format_datetime_string((string) ($manifest['published_at'] ?? ($release['published_at'] ?? ''))),
            'changelog' => isset($manifest['changelog']) ? (string) $manifest['changelog'] : '',
            'release_message' => $release_message,
        ];
    }

    public static function handle_check_update_now(): void
    {
        if (!self::can_manage_updates()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_check_update_now');

        $state = CBT_Update_Release_Helper::get_release_state(true);
        $redirect_args = ['page' => 'cbt-update'];

        $state_status = (string) ($state['status'] ?? 'check_failed');

        if ($state_status === 'check_failed') {
            $redirect_args['cbt_err'] = isset($state['error_message']) ? (string) $state['error_message'] : 'Cek update gagal dijalankan.';
        } elseif ($state_status === 'no_release') {
            $redirect_args['cbt_msg'] = isset($state['error_message']) ? (string) $state['error_message'] : 'Belum ada GitHub Release resmi pada repo sumber updater.';
        } else {
            $redirect_args['cbt_msg'] = 'Status update CBT berhasil diperbarui.';
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_install_update_now(): void
    {
        if (!self::can_manage_updates()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_install_update_now');

        $state = CBT_Update_Release_Helper::get_release_state(true);
        $prepared = CBT_Update_Release_Helper::prepare_install_context($state);
        if (is_wp_error($prepared)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-update',
                'cbt_err' => $prepared->get_error_message(),
            ], admin_url('admin.php')));
            exit;
        }

        $package_path = isset($prepared['package_path']) ? (string) $prepared['package_path'] : '';
        $update_response = isset($prepared['update_response']) && is_object($prepared['update_response'])
            ? $prepared['update_response']
            : (object) [];
        $previous_updates = get_site_transient('update_plugins');
        $result = false;

        try {
            self::load_upgrader_dependencies();

            $next_updates = is_object($previous_updates) ? clone $previous_updates : (object) [];
            if (!isset($next_updates->response) || !is_array($next_updates->response)) {
                $next_updates->response = [];
            }
            $next_updates->response[CBT_Update_Release_Helper::plugin_basename()] = $update_response;
            set_site_transient('update_plugins', $next_updates);

            $skin = class_exists('Automatic_Upgrader_Skin')
                ? new Automatic_Upgrader_Skin()
                : null;
            $upgrader = $skin instanceof WP_Upgrader_Skin
                ? new Plugin_Upgrader($skin)
                : new Plugin_Upgrader();

            $result = $upgrader->upgrade(CBT_Update_Release_Helper::plugin_basename());
        } catch (Throwable $exception) {
            $result = new WP_Error('install_failed', $exception->getMessage());
        } finally {
            if (is_object($previous_updates)) {
                set_site_transient('update_plugins', $previous_updates);
            } else {
                delete_site_transient('update_plugins');
            }

            if ($package_path !== '' && file_exists($package_path)) {
                @unlink($package_path);
            }
        }

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-update',
                'cbt_err' => 'Install update gagal: ' . $result->get_error_message(),
            ], admin_url('admin.php')));
            exit;
        }

        if ($result === false) {
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-update',
                'cbt_err' => 'WordPress upgrader tidak menyelesaikan update plugin.',
            ], admin_url('admin.php')));
            exit;
        }

        CBT_Update_Release_Helper::clear_cached_release_state();

        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-update',
            'cbt_msg' => 'Update plugin CBT berhasil diinstall. Reload halaman admin setelah plugin aktif kembali.',
        ], admin_url('admin.php')));
        exit;
    }

    public static function page_url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => 'cbt-update'], $args), admin_url('admin.php'));
    }

    /**
     * @return array<string,string>
     */
    public static function status_meta(string $status): array
    {
        switch ($status) {
            case 'up_to_date':
                return [
                    'label' => 'Up to Date',
                    'tone' => 'ok',
                    'description' => 'Plugin lokal sudah sama atau lebih baru dari release terbaru GitHub.',
                ];
            case 'available':
                return [
                    'label' => 'Update Available',
                    'tone' => 'info',
                    'description' => 'Release baru tersedia dan preflight siap untuk install manual.',
                ];
            case 'no_release':
                return [
                    'label' => 'No Release Yet',
                    'tone' => 'muted',
                    'description' => 'Repo sumber belum punya GitHub Release resmi yang bisa dipakai updater v1.',
                ];
            case 'preflight_blocked':
                return [
                    'label' => 'Preflight Blocked',
                    'tone' => 'warning',
                    'description' => 'Release baru tersedia, tetapi checklist preflight masih memblokir install.',
                ];
            case 'check_failed':
                return [
                    'label' => 'Check Failed',
                    'tone' => 'danger',
                    'description' => 'Status release terbaru belum bisa diambil dari GitHub.',
                ];
            default:
                return [
                    'label' => 'Not Checked',
                    'tone' => 'muted',
                    'description' => 'Belum ada status release yang disimpan. Jalankan cek update manual terlebih dahulu.',
                ];
        }
    }

    /**
     * @return array<string,string>
     */
    public static function preflight_meta(string $status): array
    {
        switch ($status) {
            case 'ok':
                return [
                    'label' => 'OK',
                    'tone' => 'ok',
                ];
            case 'warning':
                return [
                    'label' => 'Warning',
                    'tone' => 'warning',
                ];
            default:
                return [
                    'label' => 'Blocked',
                    'tone' => 'danger',
                ];
        }
    }

    private static function load_upgrader_dependencies(): void
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
    }

    private static function format_datetime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'Belum pernah dicek';
        }

        return wp_date('d M Y H:i', $timestamp);
    }

    private static function format_datetime_string(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date('d M Y H:i', $timestamp);
    }
}
