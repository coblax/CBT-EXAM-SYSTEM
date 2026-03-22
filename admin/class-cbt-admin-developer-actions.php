<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Developer_Actions
{
    private static function append_message(string $base, string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return $base;
        }

        return rtrim($base) . ' ' . $message;
    }

    public static function handle_save_settings(): void
    {
        if (!CBT_Admin_Developer_Service::can_manage()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_developer_settings');

        if (CBT_Admin_Developer_Service::has_constant_override()) {
            wp_safe_redirect(
                CBT_Admin_Developer_Service::developer_page_url([
                    'cbt_err' => 'Mode developer dikunci oleh constant CBT_EXAM_FRONTEND_DEV_SERVER. Lepas constant terlebih dahulu untuk mengubah pengaturan admin.',
                ])
            );
            exit;
        }

        $raw_input = [
            'mode' => isset($_POST['mode']) ? wp_unslash((string) $_POST['mode']) : 'build',
            'dev_server_url' => isset($_POST['dev_server_url']) ? wp_unslash((string) $_POST['dev_server_url']) : '',
        ];

        $previous_settings = CBT_Admin_Developer_Service::get_settings();
        $settings = CBT_Admin_Developer_Service::sanitize_settings_input($raw_input);

        if ($settings['mode'] === 'dev' && $settings['dev_server_url'] === '') {
            wp_safe_redirect(
                CBT_Admin_Developer_Service::developer_page_url([
                    'cbt_err' => 'URL Vite dev server wajib diisi saat mode dev aktif.',
                ])
            );
            exit;
        }

        CBT_Admin_Developer_Service::save_settings($settings);

        $success_message = 'Pengaturan frontend developer berhasil disimpan.';

        if ($settings['mode'] === 'dev') {
            $stop_build_watch = CBT_Admin_Developer_Service::stop_build_watch();
            if ($stop_build_watch['attempted']) {
                $success_message = self::append_message($success_message, $stop_build_watch['message']);
            }

            $result = CBT_Admin_Developer_Service::ensure_dev_server_available($settings['dev_server_url']);
            $health = $result['health'];
            $launcher = $result['launcher'];
            if ($health['status'] !== 'ok') {
                $message = 'Mode dev aktif, tetapi dev server gagal dijangkau: ' . $health['message'];
                if ($launcher['message'] !== '') {
                    $message .= ' ' . $launcher['message'];
                }
                wp_safe_redirect(
                    CBT_Admin_Developer_Service::developer_page_url([
                        'cbt_err' => $message,
                    ])
                );
                exit;
            }
        } elseif ($settings['mode'] === 'stable') {
            $stop_dev_result = CBT_Admin_Developer_Service::stop_dev_server();
            if ($stop_dev_result['attempted']) {
                $success_message = self::append_message($success_message, $stop_dev_result['message']);
            }

            $build_watch_result = CBT_Admin_Developer_Service::ensure_build_watch_available();
            if (!$build_watch_result['started']) {
                wp_safe_redirect(
                    CBT_Admin_Developer_Service::developer_page_url([
                        'cbt_err' => 'Stable Test Mode aktif, tetapi build watch gagal dijalankan: ' . $build_watch_result['message'],
                    ])
                );
                exit;
            }

            $success_message = self::append_message(
                $success_message,
                self::append_message(
                    $build_watch_result['message'],
                    'Frontend sekarang memakai build statis agar tes reconnect/offline tetap stabil.'
                )
            );
        } else {
            $stop_result = CBT_Admin_Developer_Service::stop_dev_server();
            if ($stop_result['attempted']) {
                $success_message = self::append_message($success_message, $stop_result['message']);
            } elseif ($previous_settings['mode'] === 'dev') {
                $success_message = self::append_message(
                    $success_message,
                    'Vite dev server tidak dihentikan otomatis: ' . $stop_result['message']
                );
            }

            $stop_build_watch = CBT_Admin_Developer_Service::stop_build_watch();
            if ($stop_build_watch['attempted']) {
                $success_message = self::append_message($success_message, $stop_build_watch['message']);
            } elseif ($previous_settings['mode'] === 'stable') {
                $success_message = self::append_message(
                    $success_message,
                    'Build watch tidak dihentikan otomatis: ' . $stop_build_watch['message']
                );
            }
        }

        wp_safe_redirect(
            CBT_Admin_Developer_Service::developer_page_url([
                'cbt_msg' => $success_message,
            ])
        );
        exit;
    }

    public static function handle_check_dev_server(): void
    {
        if (!CBT_Admin_Developer_Service::can_manage()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_check_developer_dev_server');

        $resolved_source = CBT_Admin_Developer_Service::resolve_frontend_asset_source();
        $settings = CBT_Admin_Developer_Service::get_settings();
        $target_url = $resolved_source['is_dev'] && $resolved_source['dev_server_url'] !== ''
            ? $resolved_source['dev_server_url']
            : $settings['dev_server_url'];

        if ($target_url === '') {
            wp_safe_redirect(
                CBT_Admin_Developer_Service::developer_page_url([
                    'cbt_err' => 'URL dev server belum tersedia untuk dicek.',
                ])
            );
            exit;
        }

        $result = $resolved_source['is_dev']
            ? CBT_Admin_Developer_Service::ensure_dev_server_available($target_url)
            : ['health' => CBT_Admin_Developer_Service::get_dev_server_health(true, $target_url, true), 'launcher' => ['attempted' => false, 'started' => false, 'message' => '']];
        $health = $result['health'];
        $launcher = $result['launcher'];
        $query_key = $health['status'] === 'ok' ? 'cbt_msg' : 'cbt_err';
        $query_value = $health['status'] === 'ok'
            ? 'Dev server aktif: ' . $health['message']
            : 'Dev server gagal dicek: ' . $health['message'];
        if ($launcher['message'] !== '') {
            $query_value .= ' ' . $launcher['message'];
        }

        wp_safe_redirect(
            CBT_Admin_Developer_Service::developer_page_url([
                $query_key => $query_value,
            ])
        );
        exit;
    }

    public static function handle_stop_dev_server(): void
    {
        if (!CBT_Admin_Developer_Service::can_manage()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_stop_developer_dev_server');

        $result = CBT_Admin_Developer_Service::stop_dev_server();
        $query_key = (!empty($result['stopped']) || !empty($result['attempted'])) ? 'cbt_msg' : 'cbt_err';
        $query_value = (string) ($result['message'] ?? '');
        if ($query_value === '') {
            $query_value = !empty($result['stopped'])
                ? 'Vite dev server berhasil dihentikan.'
                : 'Vite dev server tidak bisa dihentikan.';
        }

        wp_safe_redirect(
            CBT_Admin_Developer_Service::developer_page_url([
                $query_key => $query_value,
            ])
        );
        exit;
    }
}
