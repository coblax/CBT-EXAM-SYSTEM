<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Security_Actions
{
    public static function handle_save_security_settings(): void
    {
        if (!CBT_Admin_Security_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        self::verify_security_save_nonce();

        $force_fullscreen = isset($_POST['force_fullscreen']) && (string) wp_unslash($_POST['force_fullscreen']) === '1';
        $block_copy_paste = isset($_POST['block_copy_paste']) && (string) wp_unslash($_POST['block_copy_paste']) === '1';
        $block_browser_inspection_shortcuts = isset($_POST['block_browser_inspection_shortcuts']) && (string) wp_unslash($_POST['block_browser_inspection_shortcuts']) === '1';
        $log_security_events = isset($_POST['log_security_events']) && (string) wp_unslash($_POST['log_security_events']) === '1';
        $security_redis_first_ingest = isset($_POST['security_redis_first_ingest']) && (string) wp_unslash($_POST['security_redis_first_ingest']) === '1';
        $detect_idle_during_exam = isset($_POST['detect_idle_during_exam']) && (string) wp_unslash($_POST['detect_idle_during_exam']) === '1';
        $detect_heartbeat_lost = isset($_POST['detect_heartbeat_lost']) && (string) wp_unslash($_POST['detect_heartbeat_lost']) === '1';
        $idle_threshold_minutes = isset($_POST['idle_threshold_minutes'])
            ? max(1, absint(wp_unslash($_POST['idle_threshold_minutes'])))
            : 5;

        update_option(
            CBT_Admin_Security_Service::security_option_key(),
            [
                'force_fullscreen' => $force_fullscreen ? 1 : 0,
                'block_copy_paste' => $block_copy_paste ? 1 : 0,
                'block_browser_inspection_shortcuts' => $block_browser_inspection_shortcuts ? 1 : 0,
                'log_security_events' => $log_security_events ? 1 : 0,
                'security_redis_first_ingest' => $security_redis_first_ingest ? 1 : 0,
                'detect_idle_during_exam' => $detect_idle_during_exam ? 1 : 0,
                'detect_heartbeat_lost' => $detect_heartbeat_lost ? 1 : 0,
                'idle_threshold_minutes' => $idle_threshold_minutes,
            ],
            false
        );

        wp_safe_redirect(
            admin_url('admin.php?page=cbt-security&cbt_msg=' . rawurlencode('Pengaturan security berhasil disimpan.')) . '#security'
        );
        exit;
    }

    public static function handle_save_setup_security(): void
    {
        self::handle_save_security_settings();
    }

    public static function handle_manage_security_logs(): void
    {
        if (!CBT_Admin_Security_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_manage_security_logs');

        $teacher_id = CBT_Admin_Security_Service::is_admin_scope() ? 0 : get_current_user_id();
        $delete_scope = isset($_POST['delete_scope'])
            ? sanitize_key((string) wp_unslash($_POST['delete_scope']))
            : '';

        $redirect_url = admin_url('admin.php?page=cbt-security');
        $redirect_suffix = '#security-log';

        if ($delete_scope === 'selected') {
            $selected_log_ids = isset($_POST['selected_log_ids']) && is_array($_POST['selected_log_ids'])
                ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['selected_log_ids'])))))
                : [];

            if (empty($selected_log_ids)) {
                wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Pilih minimal satu security log untuk dihapus.') . $redirect_suffix);
                exit;
            }

            $deleted_count = CBT_Security_Log::delete_logs($selected_log_ids, [
                'teacher_id' => $teacher_id,
            ]);

            if ($deleted_count > 0) {
                wp_safe_redirect($redirect_url . '&cbt_msg=' . rawurlencode(sprintf('Security log berhasil dihapus: %d.', $deleted_count)) . $redirect_suffix);
                exit;
            }

            wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Log yang dipilih tidak ditemukan atau tidak bisa dihapus.') . $redirect_suffix);
            exit;
        }

        if ($delete_scope === 'all') {
            $deleted_count = CBT_Security_Log::delete_all_logs([
                'teacher_id' => $teacher_id,
            ]);

            if ($deleted_count > 0) {
                wp_safe_redirect($redirect_url . '&cbt_msg=' . rawurlencode(sprintf('Semua security log berhasil dihapus: %d.', $deleted_count)) . $redirect_suffix);
                exit;
            }

            wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Tidak ada security log yang bisa dihapus.') . $redirect_suffix);
            exit;
        }

        wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Aksi security log tidak dikenali.') . $redirect_suffix);
        exit;
    }

    public static function handle_simulate_native_security_event(): void
    {
        if (!CBT_Admin_Security_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_simulate_native_security_event');

        if (!CBT_Security_Log::is_logging_enabled()) {
            wp_safe_redirect(
                admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Aktifkan logging security terlebih dahulu sebelum mensimulasikan native event.')) . '#native'
            );
            exit;
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint(wp_unslash($_POST['attempt_id'])) : 0;
        $event_type = isset($_POST['event_type']) ? sanitize_key((string) wp_unslash($_POST['event_type'])) : '';
        $native_app = isset($_POST['native_app']) ? sanitize_key((string) wp_unslash($_POST['native_app'])) : '';
        $warning_code = isset($_POST['warning_code']) ? sanitize_key((string) wp_unslash($_POST['warning_code'])) : '';
        $warning_message = isset($_POST['warning_message']) ? sanitize_text_field((string) wp_unslash($_POST['warning_message'])) : '';
        $native_version = isset($_POST['native_version']) ? sanitize_text_field((string) wp_unslash($_POST['native_version'])) : '';

        if ($attempt_id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Attempt ID wajib diisi untuk simulasi native event.')) . '#native');
            exit;
        }

        $native_app_labels = CBT_Security_Log::native_app_labels();
        if ($native_app === '' || !isset($native_app_labels[$native_app])) {
            wp_safe_redirect(admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Native app tidak dikenali.')) . '#native');
            exit;
        }

        $native_event_definitions = CBT_Security_Log::native_supported_event_definitions_for_app($native_app);
        if ($event_type === '' || !isset($native_event_definitions[$event_type])) {
            wp_safe_redirect(admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Event native tidak dikenali untuk app yang dipilih.')) . '#native');
            exit;
        }

        $context = [
            'source' => 'native_test_tool',
            'native_app' => $native_app,
            'native_version' => $native_version !== '' ? $native_version : '1.0.0',
            'warning_code' => $warning_code !== '' ? $warning_code : 'manual_native_test',
            'warning_message' => $warning_message !== '' ? $warning_message : 'Simulasi native event dari panel CBT Security.',
            'occurred_at_client' => wp_date('c'),
            'device_platform' => $native_app === 'android_webview' ? 'android' : 'windows',
            'device_type' => $native_app === 'android_webview' ? 'mobile' : 'desktop',
        ];

        $logged = CBT_Security_Log::record_attempt_event($attempt_id, $event_type, $context);
        if (!$logged) {
            wp_safe_redirect(
                admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Simulasi native event gagal dicatat. Pastikan attempt aktif masih ada dan logging security menyala.')) . '#native'
            );
            exit;
        }

        wp_safe_redirect(
            admin_url('admin.php?page=cbt-security&cbt_msg=' . rawurlencode('Simulasi native event berhasil dicatat ke security log.')) . '#security-log'
        );
        exit;
    }

    private static function verify_security_save_nonce(): void
    {
        $nonce = isset($_REQUEST['_wpnonce']) ? (string) wp_unslash($_REQUEST['_wpnonce']) : '';

        if ($nonce !== '' && wp_verify_nonce($nonce, 'cbt_save_security_settings')) {
            return;
        }

        check_admin_referer('cbt_save_setup_security');
    }
}
