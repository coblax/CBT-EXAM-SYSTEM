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
            self::maybe_send_security_refresh_error('Unauthorized', 'security', 403, false);
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

        self::maybe_send_security_refresh_success('Pengaturan security berhasil disimpan.', 'security');

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
            self::maybe_send_security_refresh_error('Unauthorized', 'security-log', 403, false);
            wp_die('Unauthorized');
        }

        self::verify_security_action_nonce('cbt_manage_security_logs', 'security-log');

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
                self::maybe_send_security_refresh_error('Pilih minimal satu security log untuk dihapus.', 'security-log', 400);
                wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Pilih minimal satu security log untuk dihapus.') . $redirect_suffix);
                exit;
            }

            $deleted_count = CBT_Security_Log::delete_logs($selected_log_ids, [
                'teacher_id' => $teacher_id,
            ]);

            if ($deleted_count > 0) {
                self::maybe_send_security_refresh_success(sprintf('Security log berhasil dihapus: %d.', $deleted_count), 'security-log');
                wp_safe_redirect($redirect_url . '&cbt_msg=' . rawurlencode(sprintf('Security log berhasil dihapus: %d.', $deleted_count)) . $redirect_suffix);
                exit;
            }

            self::maybe_send_security_refresh_error('Log yang dipilih tidak ditemukan atau tidak bisa dihapus.', 'security-log', 404);
            wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Log yang dipilih tidak ditemukan atau tidak bisa dihapus.') . $redirect_suffix);
            exit;
        }

        if ($delete_scope === 'all') {
            $deleted_count = CBT_Security_Log::delete_all_logs([
                'teacher_id' => $teacher_id,
            ]);

            if ($deleted_count > 0) {
                self::maybe_send_security_refresh_success(sprintf('Semua security log berhasil dihapus: %d.', $deleted_count), 'security-log');
                wp_safe_redirect($redirect_url . '&cbt_msg=' . rawurlencode(sprintf('Semua security log berhasil dihapus: %d.', $deleted_count)) . $redirect_suffix);
                exit;
            }

            self::maybe_send_security_refresh_error('Tidak ada security log yang bisa dihapus.', 'security-log', 404);
            wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Tidak ada security log yang bisa dihapus.') . $redirect_suffix);
            exit;
        }

        self::maybe_send_security_refresh_error('Aksi security log tidak dikenali.', 'security-log', 400);
        wp_safe_redirect($redirect_url . '&cbt_err=' . rawurlencode('Aksi security log tidak dikenali.') . $redirect_suffix);
        exit;
    }

    public static function handle_simulate_native_security_event(): void
    {
        if (!CBT_Admin_Security_Service::can_manage_exams()) {
            self::maybe_send_security_refresh_error('Unauthorized', 'native', 403, false);
            wp_die('Unauthorized');
        }

        self::verify_security_action_nonce('cbt_simulate_native_security_event', 'native');

        if (!CBT_Security_Log::is_logging_enabled()) {
            self::maybe_send_security_refresh_error('Aktifkan logging security terlebih dahulu sebelum mensimulasikan native event.', 'native', 400);
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
            self::maybe_send_security_refresh_error('Attempt ID wajib diisi untuk simulasi native event.', 'native', 400);
            wp_safe_redirect(admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Attempt ID wajib diisi untuk simulasi native event.')) . '#native');
            exit;
        }

        $native_app_labels = CBT_Security_Log::native_app_labels();
        if ($native_app === '' || !isset($native_app_labels[$native_app])) {
            self::maybe_send_security_refresh_error('Native app tidak dikenali.', 'native', 400);
            wp_safe_redirect(admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Native app tidak dikenali.')) . '#native');
            exit;
        }

        $native_event_definitions = CBT_Security_Log::native_supported_event_definitions_for_app($native_app);
        if ($event_type === '' || !isset($native_event_definitions[$event_type])) {
            self::maybe_send_security_refresh_error('Event native tidak dikenali untuk app yang dipilih.', 'native', 400);
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
            self::maybe_send_security_refresh_error('Simulasi native event gagal dicatat. Pastikan attempt aktif masih ada dan logging security menyala.', 'native', 500);
            wp_safe_redirect(
                admin_url('admin.php?page=cbt-security&cbt_err=' . rawurlencode('Simulasi native event gagal dicatat. Pastikan attempt aktif masih ada dan logging security menyala.')) . '#native'
            );
            exit;
        }

        self::maybe_send_security_refresh_success('Simulasi native event berhasil dicatat ke security log.', 'security-log');

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

        if (self::is_security_local_refresh_request()) {
            $fallback_nonce = isset($_REQUEST['_wpnonce']) ? (string) wp_unslash($_REQUEST['_wpnonce']) : '';
            if ($fallback_nonce !== '' && wp_verify_nonce($fallback_nonce, 'cbt_save_setup_security')) {
                return;
            }

            self::send_security_refresh_json(false, 'Sesi admin sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.', 'security', 403);
        }

        check_admin_referer('cbt_save_setup_security');
    }

    private static function verify_security_action_nonce(string $action, string $tab): void
    {
        if (!self::is_security_local_refresh_request()) {
            check_admin_referer($action);
            return;
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? (string) wp_unslash($_REQUEST['_wpnonce']) : '';
        if ($nonce !== '' && wp_verify_nonce($nonce, $action)) {
            return;
        }

        self::send_security_refresh_json(false, 'Sesi admin sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.', $tab, 403);
    }

    private static function is_security_local_refresh_request(): bool
    {
        $requested = !empty($_POST['cbt_security_local_refresh']) || !empty($_GET['cbt_security_local_refresh']);
        $xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        return $requested || $xhr;
    }

    private static function maybe_send_security_refresh_success(string $message, string $tab): void
    {
        if (!self::is_security_local_refresh_request()) {
            return;
        }

        self::send_security_refresh_json(true, $message, $tab, 200);
    }

    private static function maybe_send_security_refresh_error(string $message, string $tab, int $status, bool $include_html = true): void
    {
        if (!self::is_security_local_refresh_request()) {
            return;
        }

        self::send_security_refresh_json(false, $message, $tab, $status, $include_html);
    }

    private static function send_security_refresh_json(bool $success, string $message, string $tab, int $status, bool $include_html = true): void
    {
        $html = $include_html
            ? self::render_security_page_html(
                $success ? $message : '',
                $success ? '' : $message
            )
            : '';
        $payload = [
            'html' => $html,
            'message' => $message,
            'tab' => $tab,
        ];

        if ($success && function_exists('wp_send_json_success')) {
            wp_send_json_success($payload, $status);
            exit;
        }
        if (!$success && function_exists('wp_send_json_error')) {
            wp_send_json_error($payload, $status);
            exit;
        }

        if (function_exists('status_header')) {
            status_header($status);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo wp_json_encode([
            'success' => $success,
            'data' => $payload,
        ]);
        exit;
    }

    private static function render_security_page_html(string $notice = '', string $error = ''): string
    {
        if (!class_exists('CBT_Admin_Security_Page')) {
            require_once __DIR__ . '/class-cbt-admin-security-page.php';
        }

        $previous_page = $_GET['page'] ?? null;
        $previous_notice = $_GET['cbt_msg'] ?? null;
        $previous_error = $_GET['cbt_err'] ?? null;

        $_GET['page'] = 'cbt-security';
        if ($notice !== '') {
            $_GET['cbt_msg'] = $notice;
            unset($_GET['cbt_err']);
        } elseif ($error !== '') {
            $_GET['cbt_err'] = $error;
            unset($_GET['cbt_msg']);
        }

        $buffer_level = ob_get_level();
        ob_start();
        try {
            CBT_Admin_Security_Page::render();
            $html = (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }

            self::restore_query_value('page', $previous_page);
            self::restore_query_value('cbt_msg', $previous_notice);
            self::restore_query_value('cbt_err', $previous_error);
        }

        return $html;
    }

    /**
     * @param mixed $value
     */
    private static function restore_query_value(string $key, $value): void
    {
        if ($value === null) {
            unset($_GET[$key]);
            return;
        }

        $_GET[$key] = $value;
    }
}
