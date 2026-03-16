<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Tokens_Actions
{
    public static function handle_save_global_exam_token(): void
    {
        if (!CBT_Admin_Tokens_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_global_exam_token');

        $token_mode = isset($_POST['token_mode']) ? sanitize_key((string) wp_unslash($_POST['token_mode'])) : 'save';
        $raw_token = isset($_POST['global_exam_token']) ? (string) wp_unslash($_POST['global_exam_token']) : '';
        $raw_refresh = isset($_POST['global_exam_token_refresh_minutes']) ? (int) $_POST['global_exam_token_refresh_minutes'] : 15;
        $frontend_auto_apply = isset($_POST['global_exam_token_frontend_auto_apply']);
        $is_regenerate = ($token_mode === 'regenerate');

        CBT_Auth::save_global_exam_token_settings($raw_token, $raw_refresh, $is_regenerate, $frontend_auto_apply);
        CBT_Cache::invalidate_catalog();

        $message = $is_regenerate
            ? 'Token global berhasil digenerate ulang.'
            : 'Pengaturan token global berhasil disimpan.';

        self::redirect_tokens_page([
            'cbt_msg' => $message,
        ]);
    }

    private static function redirect_tokens_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-tokens'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
