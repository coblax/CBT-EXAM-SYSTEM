<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Security_User_Agent_Guard')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-security-user-agent-guard.php';
}

final class CBT_Admin_Security_Actions
{
    public static function handle_save_security_settings(): void
    {
        if (!CBT_Admin_Security_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        self::verify_security_save_nonce();

        $force_fullscreen = self::posted_checkbox('force_fullscreen');
        $block_copy_paste = self::posted_checkbox('block_copy_paste');
        $block_browser_inspection_shortcuts = self::posted_checkbox('block_browser_inspection_shortcuts');
        $log_security_events = self::posted_checkbox('log_security_events');
        $security_redis_first_ingest = self::posted_checkbox('security_redis_first_ingest');
        $detect_idle_during_exam = self::posted_checkbox('detect_idle_during_exam');
        $detect_heartbeat_lost = self::posted_checkbox('detect_heartbeat_lost');
        $idle_threshold_minutes = isset($_POST['idle_threshold_minutes'])
            ? max(1, absint(wp_unslash($_POST['idle_threshold_minutes'])))
            : 5;
        $detect_screenshot_keys = self::posted_checkbox('detect_screenshot_keys');
        $show_exam_watermark = self::posted_checkbox('show_exam_watermark');
        $exam_watermark_opacity = CBT_Admin_Security_Service::normalize_exam_watermark_opacity(
            isset($_POST['exam_watermark_opacity']) ? wp_unslash($_POST['exam_watermark_opacity']) : 0.07
        );
        $restrict_student_user_agent = self::posted_checkbox('restrict_student_user_agent');
        $allowed_user_agents = CBT_Security_User_Agent_Guard::normalize_allowed_user_agents(
            isset($_POST['allowed_user_agents']) ? wp_unslash($_POST['allowed_user_agents']) : []
        );

        $security_settings = [
            'force_fullscreen' => $force_fullscreen ? 1 : 0,
            'block_copy_paste' => $block_copy_paste ? 1 : 0,
            'block_browser_inspection_shortcuts' => $block_browser_inspection_shortcuts ? 1 : 0,
            'log_security_events' => $log_security_events ? 1 : 0,
            'security_redis_first_ingest' => $security_redis_first_ingest ? 1 : 0,
            'detect_idle_during_exam' => $detect_idle_during_exam ? 1 : 0,
            'detect_heartbeat_lost' => $detect_heartbeat_lost ? 1 : 0,
            'idle_threshold_minutes' => $idle_threshold_minutes,
            'detect_screenshot_keys' => $detect_screenshot_keys ? 1 : 0,
            'show_exam_watermark' => $show_exam_watermark ? 1 : 0,
            'exam_watermark_opacity' => $exam_watermark_opacity,
            'restrict_student_user_agent' => $restrict_student_user_agent ? 1 : 0,
            'allowed_user_agents' => $allowed_user_agents,
        ];
        $security_option_key = CBT_Admin_Security_Service::security_option_key();

        CBT_Admin_Security_Service::flush_security_settings_cache();
        update_option($security_option_key, $security_settings, false);
        CBT_Admin_Security_Service::flush_security_settings_cache();

        wp_safe_redirect(
            admin_url('admin.php?page=cbt-security&cbt_msg=' . rawurlencode('Pengaturan security berhasil disimpan.')) . '#security'
        );
        exit;
    }

    public static function handle_save_setup_security(): void
    {
        self::handle_save_security_settings();
    }

    private static function posted_checkbox(string $key): bool
    {
        if (!isset($_POST[$key])) {
            return false;
        }

        $value = wp_unslash($_POST[$key]);
        if (is_array($value)) {
            $value = end($value);
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
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
