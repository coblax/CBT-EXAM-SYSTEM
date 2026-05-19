<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class SetupSecurityProgressUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-event-ingest.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-page.php';

        update_option(\CBT_Admin_Security_Service::security_option_key(), [
            'force_fullscreen' => 1,
            'block_copy_paste' => 1,
            'block_browser_inspection_shortcuts' => 0,
            'log_security_events' => 1,
            'security_redis_first_ingest' => 0,
            'detect_idle_during_exam' => 1,
            'detect_heartbeat_lost' => 1,
            'idle_threshold_minutes' => 5,
            'detect_screenshot_keys' => 1,
            'show_exam_watermark' => 1,
            'exam_watermark_opacity' => 0.07,
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid', 'Lab Browser'],
        ], false);
    }

    public function test_security_view_renders_local_progress_and_area_refresh_hooks(): void
    {
        $html = $this->renderSecurityView();

        self::assertStringContainsString('data-security-refresh-root', $html);
        self::assertStringContainsString('data-security-progress', $html);
        self::assertStringContainsString('data-security-progress-percent', $html);
        self::assertStringContainsString('data-security-progress-fill', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('tanpa reload halaman global', $html);

        self::assertStringContainsString('data-security-refresh-area="notices"', $html);
        self::assertStringContainsString('data-security-refresh-area="security-panel"', $html);
        self::assertStringContainsString('data-security-refresh-area="security-log-panel"', $html);
        self::assertStringContainsString('data-security-refresh-area="native-panel"', $html);

        self::assertStringContainsString('data-security-async-form', $html);
        self::assertStringContainsString('data-security-progress-profile="settings"', $html);
        self::assertStringContainsString('data-security-progress-profile="logs"', $html);
        self::assertStringContainsString('data-security-progress-profile="native"', $html);
        self::assertStringContainsString('data-security-refresh-areas="notices,security-panel"', $html);
        self::assertStringContainsString('data-security-refresh-areas="notices,security-log-panel"', $html);
        self::assertStringContainsString('data-security-refresh-areas="notices,native-panel,security-log-panel"', $html);

        self::assertStringContainsString('startSecurityProgress', $html);
        self::assertStringContainsString('completeSecurityProgress', $html);
        self::assertStringContainsString('parseSecurityFetchResponse', $html);
        self::assertStringContainsString('normalizeSecurityJsonPayload', $html);
        self::assertStringContainsString('application/json, text/html', $html);
        self::assertStringContainsString('getSecurityAsyncActionUrl', $html);
        self::assertStringContainsString('admin-ajax.php', $html);
        self::assertStringContainsString('cbt_security_local_refresh', $html);
        self::assertStringContainsString('replaceSecurityRefreshAreas', $html);
        self::assertStringContainsString('bindSecurityAsyncForms();', $html);
        self::assertStringContainsString('if (!card) {', $html);
        self::assertStringContainsString('window.cbtSetupSecurityLogCleanup', $html);
        self::assertStringContainsString('stopAutoRefresh();', $html);
        self::assertStringContainsString('window.cbtSetupSecurityLogActiveCard', $html);
        self::assertStringContainsString('Memperbarui panel Security tanpa reload global.', $html);
        self::assertStringNotContainsString('window.location.reload', $html);
        self::assertStringNotContainsString('location.reload', $html);
    }

    public function test_security_actions_support_json_local_refresh_without_redirect_dependency(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-actions.php');

        self::assertStringContainsString('cbt_security_local_refresh', $source);
        self::assertStringContainsString('maybe_send_security_refresh_success', $source);
        self::assertStringContainsString('maybe_send_security_refresh_error', $source);
        self::assertStringContainsString('send_security_refresh_json', $source);
        self::assertStringContainsString('wp_send_json_success', $source);
        self::assertStringContainsString('render_security_page_html', $source);
    }

    public function test_security_actions_are_registered_for_admin_ajax_local_refresh(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/class-cbt-admin.php');

        self::assertStringContainsString("add_action('wp_ajax_cbt_save_security_settings'", $source);
        self::assertStringContainsString("add_action('wp_ajax_cbt_manage_security_logs'", $source);
        self::assertStringContainsString("add_action('wp_ajax_cbt_simulate_native_security_event'", $source);
    }

    private function renderSecurityView(): string
    {
        $cbt_admin_view_mode = 'security';
        $notice = '';
        $error = '';
        $security_force_fullscreen = true;
        $security_block_copy_paste = true;
        $security_block_browser_inspection_shortcuts = false;
        $security_log_events_enabled = true;
        $security_redis_first_ingest = false;
        $security_detect_idle_during_exam = true;
        $security_detect_heartbeat_lost = true;
        $security_idle_threshold_minutes = 5;
        $security_allowed_user_agents = ['CBXExamLockAndroid', 'Lab Browser'];
        $security_allowed_user_agents_text = implode("\n", $security_allowed_user_agents);
        $security_detect_screenshot_keys = true;
        $security_show_exam_watermark = true;
        $security_exam_watermark_opacity = 0.07;
        $security_restrict_student_user_agent = true;
        $security_log_event_definitions = \CBT_Security_Log::event_definitions();
        $security_log_status_snapshot = [
            'status_label' => 'MySQL fallback',
            'live_label' => 'Live MySQL fallback',
            'ingest_label' => 'Ingest direct MySQL',
            'persist_label' => 'Persist direct MySQL',
            'backlog_count' => 0,
            'dead_letter_count' => 0,
            'mode' => 'mysql_fallback',
            'severity' => 'info',
        ];
        $security_must_watch_high_risk_threshold = 10;
        $security_must_watch_score_threshold = 4;
        $security_live_roster_groups = [];
        $security_live_snapshot = [
            'status_snapshot' => $security_log_status_snapshot,
        ];
        $security_log_must_watch_attempts = [];
        $security_logs = [];
        $security_observability_endpoint_url = 'https://example.test/wp-json/cbt/v1/security_observability_snapshot';
        $security_ingest_action_endpoint_url = 'https://example.test/wp-json/cbt/v1/security_ingest_admin_action';
        $security_logs_page_endpoint_url = 'https://example.test/wp-json/cbt/v1/security_logs_page';
        $security_rest_nonce = 'rest-nonce';
        $native_browser_event_catalog = [
            [
                'event_type' => 'tab_hidden',
                'label' => 'Tab Hidden',
                'severity' => 'warning',
                'risk_weight' => 1,
                'message' => 'Tab ujian disembunyikan.',
            ],
        ];
        $native_android_event_catalog = $native_browser_event_catalog;
        $native_windows_event_catalog = $native_browser_event_catalog;
        $native_security_endpoint_url = 'https://example.test/wp-json/cbt/v1/native_security_event';
        $native_security_sample_attempt_id = 123;
        $native_simulation_event_catalog = [
            [
                'event_type' => 'tab_hidden',
                'label' => 'Tab Hidden',
                'supported_apps' => ['android_webview', 'windows_cefsharp'],
            ],
        ];
        $native_supported_apps = [
            'android_webview' => 'Android WebView',
            'windows_cefsharp' => 'Windows CEFSharp',
        ];

        ob_start();
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/setup/page.php';

        return (string) ob_get_clean();
    }
}
