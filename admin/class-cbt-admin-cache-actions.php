<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Cache_Actions
{
    public static function handle_cache_action(): void
    {
        if (!CBT_Admin_Cache_Service::can_manage_cache()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_cache_action');

        $operation = isset($_POST['operation']) ? sanitize_key((string) wp_unslash($_POST['operation'])) : '';
        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $attempt_id = isset($_POST['attempt_id']) ? absint(wp_unslash((string) $_POST['attempt_id'])) : 0;
        $lock_key = isset($_POST['lock_key']) ? sanitize_text_field((string) wp_unslash($_POST['lock_key'])) : '';

        switch ($operation) {
            case 'bootstrap_redis':
                $result = CBT_Admin_Cache_Service::bootstrap_redis_wordpress();
                self::redirect_cache_page($result['message'] ?? null, $result['error'] ?? null);
                break;
            case 'rollback_redis':
                $result = CBT_Admin_Cache_Service::rollback_redis_wordpress();
                self::redirect_cache_page($result['message'] ?? null, $result['error'] ?? null);
                break;
            case 'invalidate_all':
                CBT_Cache::invalidate_all();
                self::redirect_cache_page('Semua namespace cache CBT berhasil di-invalidate.');
                break;
            case 'invalidate_catalog':
                CBT_Cache::invalidate_catalog();
                self::redirect_cache_page('Namespace catalog berhasil di-invalidate.');
                break;
            case 'invalidate_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                CBT_Cache::invalidate_exam($exam_id);
                self::redirect_cache_page('Namespace exam berhasil di-invalidate.');
                break;
            case 'invalidate_user':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_Cache::invalidate_user($user_id);
                self::redirect_cache_page('Namespace user berhasil di-invalidate.');
                break;
            case 'invalidate_attempt':
                if ($attempt_id <= 0) {
                    self::redirect_cache_page(null, 'Attempt ID tidak valid.');
                }
                CBT_Cache::invalidate_attempt($attempt_id);
                self::redirect_cache_page('Namespace attempt berhasil di-invalidate.');
                break;
            case 'prune_old_namespaces':
                $pruned = CBT_Cache::prune_old_namespaces();
                self::redirect_cache_page(sprintf('Namespace lama yang dibersihkan dari registry: %d.', $pruned));
                break;
            case 'clear_all_ui_state':
                CBT_UI_State::clear_all();
                self::redirect_cache_page('Semua UI state CBT berhasil dibersihkan.');
                break;
            case 'clear_attempt_ui_state':
                if ($attempt_id <= 0) {
                    self::redirect_cache_page(null, 'Attempt ID tidak valid.');
                }
                CBT_UI_State::clear_attempt_state_by_attempt_id($attempt_id);
                self::redirect_cache_page('UI state attempt berhasil dibersihkan.');
                break;
            case 'clear_ui_preferences':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_UI_State::clear_preferences($user_id);
                self::redirect_cache_page('UI preferences user berhasil dibersihkan.');
                break;
            case 'release_stale_locks':
                $released = CBT_Cache::release_stale_locks();
                self::redirect_cache_page(sprintf('Stale lock yang dilepas: %d.', $released));
                break;
            case 'release_lock':
                if ($lock_key === '') {
                    self::redirect_cache_page(null, 'Lock key tidak valid.');
                }
                CBT_Cache::release_lock($lock_key);
                self::redirect_cache_page('Lock CBT berhasil dilepas.');
                break;
            default:
                self::redirect_cache_page(null, 'Operasi cache tidak dikenali.');
        }
    }

    private static function redirect_cache_page(?string $message = null, ?string $error = null): void
    {
        $redirect_args = ['page' => 'cbt-cache'];
        if ($message !== null && $message !== '') {
            $redirect_args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $redirect_args['cbt_err'] = $error;
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
