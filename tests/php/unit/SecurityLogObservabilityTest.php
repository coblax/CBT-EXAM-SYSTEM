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
    public function test_idle_detected_native_supported_catalog_and_source_label_are_registered(): void
    {
        $this->bootstrapSecurityLogScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';

        $definitions = \CBT_Security_Log::event_definitions();
        self::assertArrayHasKey('idle_detected', $definitions);
        self::assertArrayHasKey('page_refresh', $definitions);
        self::assertSame('Idle saat ujian', $definitions['idle_detected']['label']);
        self::assertSame('warning', $definitions['idle_detected']['severity']);
        self::assertSame('Refresh halaman', $definitions['page_refresh']['label']);
        self::assertSame('info', $definitions['page_refresh']['severity']);
        self::assertSame(
            'Peserta tidak menunjukkan aktivitas pada halaman ujian selama ambang waktu yang ditentukan.',
            $definitions['idle_detected']['message']
        );

        $browserCatalog = \CBT_Security_Log::browser_supported_event_definitions();
        $nativeCatalog = \CBT_Security_Log::native_supported_event_definitions();
        $windowsCatalog = \CBT_Security_Log::windows_native_supported_event_definitions();
        self::assertArrayHasKey('page_refresh', $browserCatalog);
        self::assertArrayHasKey('tab_hidden', $nativeCatalog);
        self::assertArrayHasKey('fullscreen_exit', $nativeCatalog);
        self::assertArrayHasKey('task_manager_blocked', $windowsCatalog);
        self::assertSame('Task Manager diblok', $windowsCatalog['task_manager_blocked']['label']);
        self::assertArrayNotHasKey('page_refresh', $nativeCatalog);
        self::assertSame('Pindah tab / aplikasi', $nativeCatalog['tab_hidden']['label']);

        $reflection = new \ReflectionClass(\CBT_Security_Log::class);
        $weightsMethod = $reflection->getMethod('must_watch_event_weights');
        $weightsMethod->setAccessible(true);
        $weights = $weightsMethod->invoke(null);
        self::assertSame(3, $weights['session_revoked']);
        self::assertSame(2, $weights['idle_detected']);
        self::assertSame(3, $weights['tab_hidden']);
        self::assertSame(0.5, $weights['page_refresh']);
        self::assertSame(4, $weights['task_manager_blocked']);
        self::assertSame(5, $weights['exit_blocked']);

        $sourceLabelMethod = $reflection->getMethod('security_context_source_label');
        $sourceLabelMethod->setAccessible(true);
        self::assertSame('Timer idle', $sourceLabelMethod->invoke(null, 'idle_timer', 'idle_detected'));
        self::assertSame('Windows CEFSharp Shell', $sourceLabelMethod->invoke(null, 'windows_cefsharp_shell', 'tab_hidden'));
        self::assertSame('Resume setelah refresh', $sourceLabelMethod->invoke(null, 'reload_resume', 'page_refresh'));
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

        return null;
    }

    public function get_results($query, $output = ARRAY_A): array
    {
        if (str_contains((string) $query, "INNER JOIN {$this->prefix}cbt_attempts a ON a.id = l.attempt_id")) {
            return $this->mustWatchRows;
        }

        return $this->recentLogs;
    }

    public function get_col($query): array
    {
        return [];
    }
}
