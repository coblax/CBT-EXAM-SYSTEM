<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Tokens_Service
{
    public static function can_manage_exams(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_exams');
    }

    public static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';

        $global_token_meta = CBT_Auth::get_global_exam_token(true);
        $global_token_value = (string) ($global_token_meta['token'] ?? '');
        $global_token_refresh_minutes = (int) ($global_token_meta['refresh_minutes'] ?? 15);
        $global_token_next_refresh_at = (int) ($global_token_meta['next_refresh_at'] ?? 0);
        $global_token_remaining_seconds = (int) ($global_token_meta['remaining_seconds'] ?? 0);
        $global_token_frontend_auto_apply = (int) ($global_token_meta['frontend_auto_apply'] ?? 0);
        $global_token_display = $global_token_value !== '' ? strtoupper($global_token_value) : '------';
        $global_token_next_refresh_label = $global_token_next_refresh_at > 0
            ? wp_date('Y-m-d H:i:s', $global_token_next_refresh_at)
            : '-';
        $global_token_remaining_minutes = $global_token_remaining_seconds > 0
            ? (int) ceil($global_token_remaining_seconds / 60)
            : 0;
        $global_token_remaining_label = $global_token_remaining_minutes > 0
            ? $global_token_remaining_minutes . ' menit lagi'
            : 'Menunggu siklus berikutnya';
        $frontend_auto_status_label = $global_token_frontend_auto_apply === 1 ? 'Auto aktif' : 'Manual';
        $frontend_auto_status_description = $global_token_frontend_auto_apply === 1
            ? 'Frontend akan mengisi token otomatis saat siswa mulai ujian.'
            : 'Siswa tetap perlu mengisi token manual di frontend.';

        return compact(
            'error',
            'frontend_auto_status_description',
            'frontend_auto_status_label',
            'global_token_display',
            'global_token_frontend_auto_apply',
            'global_token_next_refresh_label',
            'global_token_refresh_minutes',
            'global_token_remaining_label',
            'global_token_value',
            'notice'
        );
    }
}
