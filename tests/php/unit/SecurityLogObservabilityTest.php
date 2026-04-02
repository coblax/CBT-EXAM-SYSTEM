<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    public function get_charset_collate(): string
    {
        return '';
    }
}
PHP);
}

final class SecurityLogObservabilityTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_logging_disabled_short_circuits_and_unknown_event_type_is_rejected(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 0]);
        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();

        self::assertFalse(\CBT_Security_Log::record_attempt_event(10, 'clipboard_blocked', []));
        self::assertFalse(\CBT_Security_Log::record_attempt_event(10, 'unknown_event_type', []));

        self::assertSame([], $wpdb->insertedRows);
    }

    #[RunInSeparateProcess]
    public function test_record_attempt_event_and_latest_student_event_store_expected_severity_and_context(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->latestAttemptByStudent[71] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'clipboard_blocked', [
            'source' => 'copy',
            'viewport_width' => 1280,
        ]));
        self::assertTrue(\CBT_Security_Log::record_latest_student_attempt_event(71, 'admin_force_complete', [
            'source' => 'must_watch_panel',
            'note' => 'forced by admin',
        ]));

        self::assertCount(2, $wpdb->insertedRows);
        self::assertSame('clipboard_blocked', $wpdb->insertedRows[0]['event_type']);
        self::assertSame('warning', $wpdb->insertedRows[0]['severity']);
        self::assertSame('Peserta mencoba melakukan copy, cut, atau paste saat ujian berlangsung.', $wpdb->insertedRows[0]['message']);
        self::assertSame(['source' => 'copy', 'viewport_width' => 1280], json_decode((string) $wpdb->insertedRows[0]['context_json'], true));
        self::assertSame('admin_force_complete', $wpdb->insertedRows[1]['event_type']);
        self::assertSame('info', $wpdb->insertedRows[1]['severity']);
        self::assertSame(['source' => 'must_watch_panel', 'note' => 'forced by admin'], json_decode((string) $wpdb->insertedRows[1]['context_json'], true));
    }

    #[RunInSeparateProcess]
    public function test_record_attempt_event_updates_live_summary_after_mysql_insert(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);
        cbt_test_register_user([
            'ID' => 71,
            'user_login' => 'coblax',
            'display_name' => 'Coblax Student',
        ]);
        update_user_meta(71, 'kode_kelas', 'X-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        $this->useFakeLiveSecurityRedis();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->examRows[501] = [
            'id' => 501,
            'title' => 'Security Fixture',
            'created_by' => 9,
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'window_blur', [
            'source' => 'blur',
            'device_type' => 'desktop',
            'device_platform' => 'windows',
        ]));

        $payloads = \CBT_Security_Live_Counters::get_active_attempt_payloads();
        self::assertCount(1, $payloads);
        self::assertSame(10, $payloads[0]['attempt_id']);
        self::assertEquals(2.0, $payloads[0]['risk_score']);
        self::assertSame(1, $payloads[0]['event_total']);
        self::assertSame('Coblax Student', $payloads[0]['student_name']);
        self::assertSame('coblax', $payloads[0]['student_login']);
        self::assertSame('X-A', $payloads[0]['student_kode_kelas']);
        self::assertSame('R1', $payloads[0]['student_kode_ruang']);
        self::assertSame('Security Fixture', $payloads[0]['exam_title']);
        self::assertArrayHasKey('window_blur', $payloads[0]['event_counts']);
        self::assertSame(1, $payloads[0]['event_counts']['window_blur']['count']);
    }

    #[RunInSeparateProcess]
    public function test_insert_failure_does_not_touch_live_summary(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);
        $this->useFakeLiveSecurityRedis();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->insertShouldFail = true;
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];

        self::assertFalse(\CBT_Security_Log::record_attempt_event(10, 'window_blur', [
            'source' => 'blur',
            'device_type' => 'desktop',
        ]));
        self::assertSame([], \CBT_Security_Live_Counters::get_active_attempt_payloads());
    }

    #[RunInSeparateProcess]
    public function test_get_recent_logs_builds_observability_message_display_and_server_context(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->recentLogs = [
            [
                'id' => 1,
                'attempt_id' => 10,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'clipboard_blocked',
                'severity' => 'warning',
                'message' => 'Peserta mencoba melakukan copy, cut, atau paste saat ujian berlangsung.',
                'context_json' => wp_json_encode([
                    'source' => 'copy',
                    'device_type' => 'desktop',
                    'device_platform' => 'windows',
                    'viewport_width' => 1440,
                    'viewport_height' => 900,
                ]),
                'occurred_at' => '2026-03-24 12:00:00',
                'created_at' => '2026-03-24 12:00:00',
                'student_display_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ],
            [
                'id' => 2,
                'attempt_id' => 11,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'admin_reset_login',
                'severity' => 'info',
                'message' => 'Pengawas atau admin mereset login siswa dari panel attempt.',
                'context_json' => wp_json_encode([
                    'source' => 'admin_reset_user_login',
                ]),
                'occurred_at' => '2026-03-24 12:01:00',
                'created_at' => '2026-03-24 12:01:00',
                'student_display_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ],
        ];

        $logs = \CBT_Security_Log::get_recent_logs();

        self::assertCount(2, $logs);
        self::assertSame('Clipboard diblokir', $logs[0]['event_label']);
        self::assertStringContainsString('Diakses dari: Desktop • Windows • 1440x900.', $logs[0]['message_display']);
        self::assertStringContainsString('Sumber deteksi: Shortcut atau menu copy.', $logs[0]['message_display']);
        self::assertSame('server', $logs[1]['device_type']);
        self::assertStringContainsString('Sumber deteksi: Panel admin.', $logs[1]['message_display']);
    }

    #[RunInSeparateProcess]
    public function test_get_must_watch_attempts_keeps_counts_stable_and_orders_ties_by_last_event(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        $this->setLiveSecurityRedisUnavailable();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->mustWatchRows = [
            [
                'id' => 1,
                'attempt_id' => 21,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'session_revoked',
                'severity' => 'critical',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'desktop']),
                'occurred_at' => '2026-03-24 12:01:00',
                'student_display_name' => 'A',
                'student_login' => 'a',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Exam A',
            ],
            [
                'id' => 2,
                'attempt_id' => 21,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'clipboard_blocked',
                'severity' => 'warning',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'desktop']),
                'occurred_at' => '2026-03-24 12:02:00',
                'student_display_name' => 'A',
                'student_login' => 'a',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Exam A',
            ],
            [
                'id' => 3,
                'attempt_id' => 22,
                'exam_id' => 502,
                'student_id' => 72,
                'event_type' => 'session_revoked',
                'severity' => 'critical',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'mobile']),
                'occurred_at' => '2026-03-24 12:03:00',
                'student_display_name' => 'B',
                'student_login' => 'b',
                'student_kode_kelas' => 'X-B',
                'student_kode_ruang' => 'R2',
                'exam_title' => 'Exam B',
            ],
            [
                'id' => 4,
                'attempt_id' => 22,
                'exam_id' => 502,
                'student_id' => 72,
                'event_type' => 'clipboard_blocked',
                'severity' => 'warning',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'mobile']),
                'occurred_at' => '2026-03-24 12:04:00',
                'student_display_name' => 'B',
                'student_login' => 'b',
                'student_kode_kelas' => 'X-B',
                'student_kode_ruang' => 'R2',
                'exam_title' => 'Exam B',
            ],
        ];

        $attempts = \CBT_Security_Log::get_must_watch_attempts();

        self::assertCount(2, $attempts);
        self::assertSame(22, $attempts[0]['attempt_id']);
        self::assertSame(5.0, $attempts[0]['risk_score']);
        self::assertSame(2, $attempts[0]['event_total']);
        self::assertSame(1, $attempts[0]['session_revoked_count']);
        self::assertSame('Sesi dicabut', $attempts[0]['primary_event_label']);
        self::assertSame(['1x Sesi dicabut', '1x Clipboard diblokir'], $attempts[0]['top_indicators']);
        self::assertSame(21, $attempts[1]['attempt_id']);
    }

    #[RunInSeparateProcess]
    public function test_get_must_watch_attempts_prefers_live_counters_when_available(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);
        cbt_test_register_user([
            'ID' => 71,
            'user_login' => 'alpha',
            'display_name' => 'Alpha Student',
        ]);
        cbt_test_register_user([
            'ID' => 72,
            'user_login' => 'beta',
            'display_name' => 'Beta Student',
        ]);
        update_user_meta(71, 'kode_kelas', 'X-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        update_user_meta(72, 'kode_kelas', 'X-B');
        update_user_meta(72, 'kode_ruang', 'R2');
        $this->useFakeLiveSecurityRedis();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[21] = [
            'id' => 21,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->attemptsById[22] = [
            'id' => 22,
            'exam_id' => 502,
            'student_id' => 72,
            'status' => 'in_progress',
        ];
        $wpdb->examRows[501] = [
            'id' => 501,
            'title' => 'Exam A',
            'created_by' => 99,
        ];
        $wpdb->examRows[502] = [
            'id' => 502,
            'title' => 'Exam B',
            'created_by' => 99,
        ];

        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        \CBT_Security_Log::record_attempt_event(21, 'session_revoked', ['device_type' => 'desktop']);
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:01:00';
        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353660;
        \CBT_Security_Log::record_attempt_event(21, 'clipboard_blocked', ['device_type' => 'desktop']);
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:02:00';
        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353720;
        \CBT_Security_Log::record_attempt_event(22, 'session_revoked', ['device_type' => 'mobile']);
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:03:00';
        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353780;
        \CBT_Security_Log::record_attempt_event(22, 'clipboard_blocked', ['device_type' => 'mobile']);

        $attempts = \CBT_Security_Log::get_must_watch_attempts(2, ['teacher_id' => 99]);

        self::assertCount(2, $attempts);
        self::assertSame(22, $attempts[0]['attempt_id']);
        self::assertSame(21, $attempts[1]['attempt_id']);
        self::assertSame(0, $wpdb->mustWatchQueryCount);
    }

    #[RunInSeparateProcess]
    public function test_get_must_watch_attempts_supplements_live_counters_with_mysql_and_dedupes_attempt_id(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);
        cbt_test_register_user([
            'ID' => 71,
            'user_login' => 'alpha',
            'display_name' => 'Alpha Student',
        ]);
        update_user_meta(71, 'kode_kelas', 'X-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        $this->useFakeLiveSecurityRedis();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[21] = [
            'id' => 21,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->examRows[501] = [
            'id' => 501,
            'title' => 'Exam A',
            'created_by' => 77,
        ];

        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        \CBT_Security_Log::record_attempt_event(21, 'session_revoked', ['device_type' => 'desktop']);
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:01:00';
        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353660;
        \CBT_Security_Log::record_attempt_event(21, 'clipboard_blocked', ['device_type' => 'desktop']);

        $wpdb->mustWatchRows = [
            [
                'id' => 1,
                'attempt_id' => 21,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'session_revoked',
                'severity' => 'critical',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'desktop']),
                'occurred_at' => '2026-03-24 12:01:00',
                'student_display_name' => 'Shadow Row',
                'student_login' => 'shadow',
                'student_kode_kelas' => 'X-Z',
                'student_kode_ruang' => 'R9',
                'exam_title' => 'Shadow Exam',
            ],
            [
                'id' => 2,
                'attempt_id' => 22,
                'exam_id' => 502,
                'student_id' => 72,
                'event_type' => 'session_revoked',
                'severity' => 'critical',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'mobile']),
                'occurred_at' => '2026-03-24 12:03:00',
                'student_display_name' => 'B',
                'student_login' => 'b',
                'student_kode_kelas' => 'X-B',
                'student_kode_ruang' => 'R2',
                'exam_title' => 'Exam B',
            ],
            [
                'id' => 3,
                'attempt_id' => 22,
                'exam_id' => 502,
                'student_id' => 72,
                'event_type' => 'clipboard_blocked',
                'severity' => 'warning',
                'message' => '',
                'context_json' => wp_json_encode(['device_type' => 'mobile']),
                'occurred_at' => '2026-03-24 12:04:00',
                'student_display_name' => 'B',
                'student_login' => 'b',
                'student_kode_kelas' => 'X-B',
                'student_kode_ruang' => 'R2',
                'exam_title' => 'Exam B',
            ],
        ];

        $attempts = \CBT_Security_Log::get_must_watch_attempts(2, ['teacher_id' => 77]);

        self::assertCount(2, $attempts);
        self::assertSame(22, $attempts[0]['attempt_id']);
        self::assertSame(21, $attempts[1]['attempt_id']);
        self::assertSame('Alpha Student', $attempts[1]['student_name']);
        self::assertSame(1, $wpdb->mustWatchQueryCount);
    }

    #[RunInSeparateProcess]
    public function test_idle_detected_native_supported_catalog_and_source_label_are_registered(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        $definitions = \CBT_Security_Log::event_definitions();
        self::assertArrayHasKey('idle_detected', $definitions);
        self::assertArrayHasKey('page_refresh', $definitions);
        self::assertArrayHasKey('print_attempt', $definitions);
        self::assertArrayHasKey('context_menu_blocked', $definitions);
        self::assertArrayHasKey('fullscreen_exit_repeat', $definitions);
        self::assertSame('Idle saat ujian', $definitions['idle_detected']['label']);
        self::assertSame('warning', $definitions['idle_detected']['severity']);
        self::assertSame('Refresh halaman', $definitions['page_refresh']['label']);
        self::assertSame('info', $definitions['page_refresh']['severity']);
        self::assertSame('Percobaan print', $definitions['print_attempt']['label']);
        self::assertSame('Context menu diblok', $definitions['context_menu_blocked']['label']);
        self::assertSame('Keluar fullscreen berulang', $definitions['fullscreen_exit_repeat']['label']);
        self::assertSame(
            'Peserta tidak menunjukkan aktivitas pada halaman ujian selama ambang waktu yang ditentukan.',
            $definitions['idle_detected']['message']
        );

        $browserCatalog = \CBT_Security_Log::browser_supported_event_definitions();
        $nativeCatalog = \CBT_Security_Log::native_supported_event_definitions();
        $windowsCatalog = \CBT_Security_Log::windows_native_supported_event_definitions();
        self::assertArrayHasKey('page_refresh', $browserCatalog);
        self::assertArrayHasKey('print_attempt', $browserCatalog);
        self::assertArrayHasKey('context_menu_blocked', $browserCatalog);
        self::assertArrayHasKey('devtools_shortcut_blocked', $browserCatalog);
        self::assertArrayHasKey('view_source_blocked', $browserCatalog);
        self::assertArrayHasKey('save_page_blocked', $browserCatalog);
        self::assertArrayHasKey('heartbeat_lost', $browserCatalog);
        self::assertArrayNotHasKey('fullscreen_exit_repeat', $browserCatalog);
        self::assertArrayNotHasKey('tab_hidden_repeat', $browserCatalog);
        self::assertArrayNotHasKey('window_blur_repeat', $browserCatalog);
        self::assertArrayHasKey('tab_hidden', $nativeCatalog);
        self::assertArrayHasKey('fullscreen_exit', $nativeCatalog);
        self::assertArrayHasKey('task_manager_blocked', $windowsCatalog);
        self::assertSame('Task Manager diblok', $windowsCatalog['task_manager_blocked']['label']);
        self::assertArrayNotHasKey('page_refresh', $nativeCatalog);
        self::assertSame('Pindah tab / aplikasi', $nativeCatalog['tab_hidden']['label']);
        self::assertSame('Pindah tab berulang', $definitions['tab_hidden_repeat']['label']);
        self::assertSame('Blur window berulang', $definitions['window_blur_repeat']['label']);

        $reflection = new \ReflectionClass(\CBT_Security_Log::class);
        $weightsMethod = $reflection->getMethod('must_watch_event_weights');
        $weightsMethod->setAccessible(true);
        $weights = $weightsMethod->invoke(null);
        self::assertSame(3, $weights['session_revoked']);
        self::assertSame(2, $weights['idle_detected']);
        self::assertSame(3, $weights['tab_hidden']);
        self::assertSame(0.5, $weights['page_refresh']);
        self::assertSame(3, $weights['print_attempt']);
        self::assertSame(1, $weights['context_menu_blocked']);
        self::assertSame(4, $weights['devtools_shortcut_blocked']);
        self::assertSame(4, $weights['view_source_blocked']);
        self::assertSame(3, $weights['save_page_blocked']);
        self::assertSame(2, $weights['heartbeat_lost']);
        self::assertSame(5, $weights['fullscreen_exit_repeat']);
        self::assertSame(4, $weights['tab_hidden_repeat']);
        self::assertSame(3, $weights['window_blur_repeat']);
        self::assertSame(4, $weights['task_manager_blocked']);
        self::assertSame(5, $weights['exit_blocked']);

        $sourceLabelMethod = $reflection->getMethod('security_context_source_label');
        $sourceLabelMethod->setAccessible(true);
        self::assertSame('Timer idle', $sourceLabelMethod->invoke(null, 'idle_timer', 'idle_detected'));
        self::assertSame('Windows CEFSharp Shell', $sourceLabelMethod->invoke(null, 'windows_cefsharp_shell', 'tab_hidden'));
        self::assertSame('Resume setelah refresh', $sourceLabelMethod->invoke(null, 'reload_resume', 'page_refresh'));
        self::assertSame('Shortcut print', $sourceLabelMethod->invoke(null, 'print_shortcut', 'print_attempt'));
        self::assertSame('Klik kanan / context menu', $sourceLabelMethod->invoke(null, 'contextmenu', 'context_menu_blocked'));
        self::assertSame('Shortcut buka/tutup DevTools', $sourceLabelMethod->invoke(null, 'devtools_toggle_shortcut', 'devtools_shortcut_blocked'));
        self::assertSame('Shortcut View Source', $sourceLabelMethod->invoke(null, 'view_source_shortcut', 'view_source_blocked'));
        self::assertSame('Shortcut Save Page', $sourceLabelMethod->invoke(null, 'save_page_shortcut', 'save_page_blocked'));
        self::assertSame('Session heartbeat', $sourceLabelMethod->invoke(null, 'session_heartbeat', 'heartbeat_lost'));
        self::assertSame('Agregasi fullscreen berulang', $sourceLabelMethod->invoke(null, 'fullscreen_repeat_threshold', 'fullscreen_exit_repeat'));
        self::assertSame('Agregasi pindah tab berulang', $sourceLabelMethod->invoke(null, 'tab_hidden_repeat_threshold', 'tab_hidden_repeat'));
        self::assertSame('Agregasi blur berulang', $sourceLabelMethod->invoke(null, 'window_blur_repeat_threshold', 'window_blur_repeat'));
    }

    #[RunInSeparateProcess]
    public function test_page_refresh_promotes_recent_page_leave_log_instead_of_inserting_a_new_warning(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 51,
            'student_id' => 9,
            'status' => 'in_progress',
        ];
        $wpdb->recentLogs = [
            [
                'id' => 77,
                'attempt_id' => 10,
                'exam_id' => 51,
                'student_id' => 9,
                'event_type' => 'page_leave',
                'severity' => 'warning',
                'message' => 'Peserta menutup atau meninggalkan halaman ujian.',
                'context_json' => wp_json_encode([
                    'source' => 'beforeunload',
                    'device_type' => 'desktop',
                ]),
                'occurred_at' => '2026-03-27 09:15:00',
                'created_at' => '2026-03-27 09:15:00',
            ],
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'page_refresh', [
            'source' => 'reload_resume',
            'navigation_type' => 'reload',
        ]));
        self::assertCount(0, $wpdb->insertedRows);
        self::assertCount(1, $wpdb->updatedRows);
        self::assertSame('page_refresh', $wpdb->recentLogs[0]['event_type']);
        self::assertSame('info', $wpdb->recentLogs[0]['severity']);
        self::assertSame('Peserta me-refresh halaman ujian saat attempt masih berlangsung.', $wpdb->recentLogs[0]['message']);

        $updatedContext = json_decode((string) $wpdb->recentLogs[0]['context_json'], true);
        self::assertIsArray($updatedContext);
        self::assertSame('reload_resume', $updatedContext['source']);
        self::assertSame('beforeunload', $updatedContext['unload_source']);
    }

    #[RunInSeparateProcess]
    public function test_fullscreen_exit_repeat_is_recorded_once_when_the_third_exit_is_reached(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);
        $this->useFakeLiveSecurityRedis();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 51,
            'student_id' => 9,
            'status' => 'in_progress',
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'fullscreen_exit', [
            'source' => 'fullscreenchange',
            'device_type' => 'desktop',
        ]));
        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'fullscreen_exit', [
            'source' => 'fullscreenchange',
            'device_type' => 'desktop',
        ]));
        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'fullscreen_exit', [
            'source' => 'fullscreenchange',
            'device_type' => 'desktop',
        ]));
        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'fullscreen_exit', [
            'source' => 'fullscreenchange',
            'device_type' => 'desktop',
        ]));

        self::assertCount(5, $wpdb->insertedRows);
        $repeatRows = array_values(array_filter($wpdb->insertedRows, static function (array $row): bool {
            return (string) ($row['event_type'] ?? '') === 'fullscreen_exit_repeat';
        }));

        self::assertCount(1, $repeatRows);
        self::assertSame('critical', $repeatRows[0]['severity']);
        $repeatContext = json_decode((string) $repeatRows[0]['context_json'], true);
        self::assertIsArray($repeatContext);
        self::assertSame('fullscreen_repeat_threshold', $repeatContext['source']);
        self::assertSame(3, $repeatContext['threshold']);
        self::assertSame(3, $repeatContext['fullscreen_exit_count']);
        self::assertSame('fullscreen_exit', $repeatContext['trigger_event']);
    }

    #[RunInSeparateProcess]
    public function test_tab_hidden_and_window_blur_repeat_are_recorded_once_when_threshold_is_reached(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        update_option('cbt_setup_security', ['log_security_events' => 1]);
        $this->useFakeLiveSecurityRedis();

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 51,
            'student_id' => 9,
            'status' => 'in_progress',
        ];

        for ($i = 0; $i < 4; $i++) {
            \CBT_Security_Log::record_attempt_event(10, 'tab_hidden', [
                'source' => 'visibilitychange',
                'device_type' => 'desktop',
            ]);
            \CBT_Security_Log::record_attempt_event(10, 'window_blur', [
                'source' => 'blur',
                'device_type' => 'desktop',
            ]);
        }

        $tabHiddenRepeatRows = array_values(array_filter($wpdb->insertedRows, static function (array $row): bool {
            return (string) ($row['event_type'] ?? '') === 'tab_hidden_repeat';
        }));
        $windowBlurRepeatRows = array_values(array_filter($wpdb->insertedRows, static function (array $row): bool {
            return (string) ($row['event_type'] ?? '') === 'window_blur_repeat';
        }));

        self::assertCount(1, $tabHiddenRepeatRows);
        self::assertCount(1, $windowBlurRepeatRows);
        self::assertSame('warning', $tabHiddenRepeatRows[0]['severity']);
        self::assertSame('warning', $windowBlurRepeatRows[0]['severity']);

        $tabHiddenContext = json_decode((string) $tabHiddenRepeatRows[0]['context_json'], true);
        $windowBlurContext = json_decode((string) $windowBlurRepeatRows[0]['context_json'], true);
        self::assertIsArray($tabHiddenContext);
        self::assertIsArray($windowBlurContext);
        self::assertSame('tab_hidden_repeat_threshold', $tabHiddenContext['source']);
        self::assertSame(3, $tabHiddenContext['tab_hidden_count']);
        self::assertSame('window_blur_repeat_threshold', $windowBlurContext['source']);
        self::assertSame(3, $windowBlurContext['window_blur_count']);
    }

    #[RunInSeparateProcess]
    public function test_recent_logs_show_print_block_status_and_fullscreen_repeat_summary(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->recentLogs = [
            [
                'id' => 10,
                'attempt_id' => 201,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'print_attempt',
                'severity' => 'warning',
                'message' => 'Peserta mencoba membuka dialog print atau mencetak halaman ujian saat attempt masih berlangsung.',
                'context_json' => wp_json_encode([
                    'source' => 'print_shortcut',
                    'blocked' => 1,
                    'device_type' => 'desktop',
                    'device_platform' => 'windows',
                ]),
                'occurred_at' => '2026-03-27 08:00:00',
                'created_at' => '2026-03-27 08:00:00',
                'student_display_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ],
            [
                'id' => 11,
                'attempt_id' => 201,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'fullscreen_exit_repeat',
                'severity' => 'critical',
                'message' => 'Peserta berulang kali keluar dari mode fullscreen saat ujian berlangsung.',
                'context_json' => wp_json_encode([
                    'source' => 'fullscreen_repeat_threshold',
                    'threshold' => 3,
                    'fullscreen_exit_count' => 3,
                    'device_type' => 'desktop',
                    'device_platform' => 'windows',
                ]),
                'occurred_at' => '2026-03-27 08:01:00',
                'created_at' => '2026-03-27 08:01:00',
                'student_display_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ],
        ];

        $logs = \CBT_Security_Log::get_recent_logs();

        self::assertCount(2, $logs);
        self::assertStringContainsString('Sumber deteksi: Shortcut print.', $logs[0]['message_display']);
        self::assertStringContainsString('Diblokir: Ya.', $logs[0]['message_display']);
        self::assertStringContainsString('Sumber deteksi: Agregasi fullscreen berulang.', $logs[1]['message_display']);
        self::assertStringContainsString('Keluar fullscreen tercatat 3x (ambang 3).', $logs[1]['message_display']);
    }

    #[RunInSeparateProcess]
    public function test_recent_logs_show_heartbeat_lost_context_cleanly(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->recentLogs = [
            [
                'id' => 12,
                'attempt_id' => 201,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'heartbeat_lost',
                'severity' => 'warning',
                'message' => 'Frontend mendeteksi heartbeat session gagal berulang saat ujian berlangsung.',
                'context_json' => wp_json_encode([
                    'source' => 'session_heartbeat',
                    'failure_count' => 3,
                    'last_error_code' => 'network_error',
                    'visibility_state' => 'visible',
                    'has_focus' => 1,
                    'device_type' => 'desktop',
                    'device_platform' => 'windows',
                ]),
                'occurred_at' => '2026-03-27 08:02:00',
                'created_at' => '2026-03-27 08:02:00',
                'student_display_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ],
        ];

        $logs = \CBT_Security_Log::get_recent_logs();

        self::assertCount(1, $logs);
        self::assertStringContainsString('Sumber deteksi: Session heartbeat.', $logs[0]['message_display']);
        self::assertStringContainsString('Heartbeat gagal 3x berturut-turut.', $logs[0]['message_display']);
        self::assertStringContainsString('Kode error terakhir: network_error.', $logs[0]['message_display']);
        self::assertStringContainsString('Visibility: visible.', $logs[0]['message_display']);
        self::assertStringContainsString('Fokus dokumen: Ya.', $logs[0]['message_display']);
    }

    #[RunInSeparateProcess]
    public function test_native_recent_log_display_surfaces_native_fields_cleanly(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        global $wpdb;
        $wpdb = new SecurityLogFakeWpdb();
        $wpdb->recentLogs = [
            [
                'id' => 8,
                'attempt_id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'event_type' => 'tab_hidden',
                'severity' => 'warning',
                'message' => 'Peserta berpindah tab atau aplikasi saat ujian berlangsung.',
                'context_json' => wp_json_encode([
                    'source' => 'windows_cefsharp_shell',
                    'native_app' => 'windows_cefsharp',
                    'warning_code' => 'task_switch',
                    'warning_message' => 'Window ujian kehilangan fokus karena task switch',
                    'occurred_at_client' => '2026-03-26T21:31:02+07:00',
                    'device_type' => 'desktop',
                    'device_platform' => 'windows',
                ]),
                'occurred_at' => '2026-03-26 21:31:03',
                'created_at' => '2026-03-26 21:31:03',
                'student_display_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ],
        ];

        $logs = \CBT_Security_Log::get_recent_logs();

        self::assertCount(1, $logs);
        self::assertStringContainsString('Native app: Windows CEFSharp.', $logs[0]['message_display']);
        self::assertStringContainsString('Warning native: Window ujian kehilangan fokus karena task switch [task_switch].', $logs[0]['message_display']);
        self::assertStringContainsString('Waktu client: 2026-03-26T21:31:02+07:00.', $logs[0]['message_display']);
        self::assertStringContainsString('Sumber deteksi: Windows CEFSharp Shell.', $logs[0]['message_display']);
    }

    private function bootstrapSecurityLogScaffold(): void
    {
    }

    private function useFakeLiveSecurityRedis(): void
    {
        $reflection = new \ReflectionClass(\CBT_Security_Live_Counters::class);

        $redisProperty = $reflection->getProperty('live_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('live_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('live_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setLiveSecurityRedisUnavailable(): void
    {
        $reflection = new \ReflectionClass(\CBT_Security_Live_Counters::class);

        $redisProperty = $reflection->getProperty('live_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('live_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('live_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in mysql-only test');
    }
}

class SecurityLogFakeWpdb extends \wpdb
{
    /** @var array<int,array<string,mixed>> */
    public array $attemptsById = [];

    /** @var array<int,array<string,mixed>> */
    public array $latestAttemptByStudent = [];

    /** @var array<int,array<string,mixed>> */
    public array $insertedRows = [];

    /** @var array<int,array<string,mixed>> */
    public array $recentLogs = [];

    /** @var array<int,array<string,mixed>> */
    public array $mustWatchRows = [];

    /** @var array<int,array<string,mixed>> */
    public array $updatedRows = [];

    /** @var array<int,array<string,mixed>> */
    public array $examRows = [];

    public bool $insertShouldFail = false;

    public int $mustWatchQueryCount = 0;

    public function prepare($query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ((array) $args as $arg) {
            $replacement = is_numeric($arg) ? (string) $arg : "'" . str_replace("'", "''", (string) $arg) . "'";
            $query = preg_replace('/%d|%s/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    public function insert($table, $data, $format = null): int|false
    {
        if ($this->insertShouldFail) {
            return false;
        }

        $this->insertedRows[] = is_array($data) ? $data : [];
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null): int|false
    {
        $row = [
            'table' => $table,
            'data' => is_array($data) ? $data : [],
            'where' => is_array($where) ? $where : [],
        ];
        $this->updatedRows[] = $row;

        $targetId = (int) ($row['where']['id'] ?? 0);
        foreach ($this->recentLogs as $index => $existing) {
            if ((int) ($existing['id'] ?? 0) !== $targetId) {
                continue;
            }

            $this->recentLogs[$index] = array_merge($existing, $row['data']);
            return 1;
        }

        return 0;
    }

    public function query($query): int|false
    {
        return 0;
    }

    public function get_row($query, $output = ARRAY_A): ?array
    {
        if (
            preg_match('/FROM ' . preg_quote($this->prefix . 'cbt_exams', '/') . '/', (string) $query)
            && preg_match('/WHERE id = (\d+)/', (string) $query, $matches)
        ) {
            $examId = (int) $matches[1];
            return $this->examRows[$examId] ?? null;
        }

        if (preg_match('/WHERE id = (\d+)/', (string) $query, $matches)) {
            $attemptId = (int) $matches[1];
            return $this->attemptsById[$attemptId] ?? null;
        }

        if (preg_match('/WHERE student_id = (\d+)/', (string) $query, $matches)) {
            $studentId = (int) $matches[1];
            return $this->latestAttemptByStudent[$studentId] ?? null;
        }

        if (
            preg_match('/FROM ' . preg_quote($this->prefix . 'cbt_security_logs', '/') . '/', (string) $query)
            && preg_match('/WHERE attempt_id = (\d+)/', (string) $query, $attemptMatches)
            && str_contains((string) $query, "event_type = 'page_leave'")
        ) {
            $attemptId = (int) $attemptMatches[1];
            foreach ($this->recentLogs as $row) {
                if ((int) ($row['attempt_id'] ?? 0) === $attemptId && (string) ($row['event_type'] ?? '') === 'page_leave') {
                    return [
                        'id' => (int) ($row['id'] ?? 0),
                        'context_json' => (string) ($row['context_json'] ?? ''),
                    ];
                }
            }
        }

        if (
            preg_match('/FROM ' . preg_quote($this->prefix . 'cbt_security_logs', '/') . '/', (string) $query)
            && preg_match('/WHERE attempt_id = (\d+)/', (string) $query, $attemptMatches)
            && preg_match("/event_type = '([^']+)'/", (string) $query, $eventMatches)
        ) {
            $attemptId = (int) $attemptMatches[1];
            $eventType = (string) $eventMatches[1];
            foreach ($this->allSecurityLogRows() as $row) {
                if ((int) ($row['attempt_id'] ?? 0) === $attemptId && (string) ($row['event_type'] ?? '') === $eventType) {
                    return [
                        'id' => (int) ($row['id'] ?? 1),
                        'context_json' => (string) ($row['context_json'] ?? ''),
                    ];
                }
            }
        }

        return null;
    }

    public function get_var($query)
    {
        if (
            preg_match('/FROM ' . preg_quote($this->prefix . 'cbt_security_logs', '/') . '/', (string) $query)
            && preg_match('/WHERE attempt_id = (\d+)/', (string) $query, $attemptMatches)
            && preg_match("/event_type = '([^']+)'/", (string) $query, $eventMatches)
        ) {
            $attemptId = (int) $attemptMatches[1];
            $eventType = (string) $eventMatches[1];
            $count = 0;

            foreach ($this->allSecurityLogRows() as $row) {
                if ((int) ($row['attempt_id'] ?? 0) === $attemptId && (string) ($row['event_type'] ?? '') === $eventType) {
                    $count++;
                }
            }

            return $count;
        }

        return 0;
    }

    public function get_results($query, $output = ARRAY_A): array
    {
        if (str_contains((string) $query, "INNER JOIN {$this->prefix}cbt_attempts a ON a.id = l.attempt_id")) {
            $this->mustWatchQueryCount++;
            return $this->mustWatchRows;
        }

        return $this->recentLogs;
    }

    public function get_col($query): array
    {
        return [];
    }

    /** @return array<int,array<string,mixed>> */
    private function allSecurityLogRows(): array
    {
        $rows = $this->recentLogs;

        foreach ($this->insertedRows as $index => $row) {
            $safeRow = is_array($row) ? $row : [];
            if (!isset($safeRow['id'])) {
                $safeRow['id'] = 1000 + $index;
            }
            $rows[] = $safeRow;
        }

        return $rows;
    }
}
