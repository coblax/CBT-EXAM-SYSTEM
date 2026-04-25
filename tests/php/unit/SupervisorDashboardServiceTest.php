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
}
PHP);
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, int $decimals = 0): string
    {
        return number_format((float) $number, $decimals, '.', ',');
    }
}

final class SupervisorDashboardServiceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_build_dashboard_payload_uses_teacher_scope_and_returns_structured_sections(): void
    {
        $this->bootstrapSupervisorStubs();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-supervisor-dashboard-service.php';

        global $wpdb;
        $wpdb = new SupervisorDashboardServiceFakeWpdb();

        $payload = \CBT_Supervisor_Dashboard_Service::build_dashboard_payload(41, 'teacher', [
            'exam_id' => 8,
            'kelas' => 'XI TKJ 1',
            'student_keyword' => 'Indar',
            'status' => 'in_progress',
            'roster_page' => 1,
            'attempts_page' => 1,
        ]);

        self::assertTrue($payload['ok'] ?? false);
        self::assertSame(false, $payload['scope']['is_admin_scope'] ?? true);
        self::assertSame(41, $payload['scope']['teacher_scope_user_id'] ?? 0);
        self::assertArrayHasKey('live_roster', $payload);
        self::assertArrayHasKey('must_watch', $payload);
        self::assertArrayHasKey('monitoring_attempts', $payload);
        self::assertArrayHasKey('security_log', $payload);
        self::assertArrayHasKey('token_gate', $payload);
        self::assertArrayHasKey('attendance', $payload);
        self::assertArrayHasKey('submit_recovery', $payload);
        self::assertArrayHasKey('submit_watchlist', $payload);
        self::assertSame(true, $payload['permissions']['can_reset_login'] ?? false);
        self::assertSame(false, $payload['permissions']['can_manage_token'] ?? true);
        self::assertSame(false, $payload['permissions']['can_delete_security_logs'] ?? true);
        self::assertCount(1, $payload['live_roster']['items'] ?? []);
        self::assertSame('Indar Bismoko', $payload['live_roster']['items'][0]['student_name'] ?? '');
        self::assertSame('Must Watch', $payload['must_watch']['items'][0]['risk_label'] ?? '');
        self::assertSame(1, $payload['monitoring_attempts']['total'] ?? 0);
        self::assertSame(1, $payload['submit_watchlist']['display_count'] ?? 0);
        self::assertNotEmpty($payload['filter_options']['exams'] ?? []);
        self::assertNotEmpty($payload['filter_options']['kelas'] ?? []);
        self::assertNotEmpty($payload['filter_options']['ruang'] ?? []);
        self::assertSame([], $payload['security_log']['items'] ?? null);
    }

    #[RunInSeparateProcess]
    public function test_security_log_tab_filters_read_only_rows(): void
    {
        $this->bootstrapSupervisorStubs();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-supervisor-dashboard-service.php';

        global $wpdb;
        $wpdb = new SupervisorDashboardServiceFakeWpdb();

        $payload = \CBT_Supervisor_Dashboard_Service::build_dashboard_payload(41, 'teacher', [
            'tab' => 'security_log',
            'exam_id' => 8,
            'kelas' => 'XI TKJ 1',
            'ruang' => 'R-2',
            'security_severity' => 'critical',
            'security_event_type' => 'tab_hidden',
            'security_device_type' => 'mobile',
        ]);

        self::assertSame('security_log', $payload['filters']['tab'] ?? '');
        self::assertSame(1, $payload['security_log']['total'] ?? 0);
        self::assertSame('tab_hidden', $payload['security_log']['items'][0]['event_type'] ?? '');
        self::assertSame('critical', $payload['security_log']['items'][0]['severity'] ?? '');
        self::assertNotEmpty($payload['security_log']['event_catalog'] ?? []);
        self::assertSame(false, $payload['permissions']['can_delete_security_logs'] ?? true);
    }

    #[RunInSeparateProcess]
    public function test_token_gate_tab_returns_token_and_read_only_gate_context(): void
    {
        $this->bootstrapSupervisorStubs();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-supervisor-dashboard-service.php';

        global $wpdb;
        $wpdb = new SupervisorDashboardServiceFakeWpdb();

        $payload = \CBT_Supervisor_Dashboard_Service::build_dashboard_payload(41, 'teacher', [
            'tab' => 'token_gate',
            'exam_id' => 8,
        ]);

        self::assertSame('token_gate', $payload['filters']['tab'] ?? '');
        self::assertSame('ABC123', $payload['token_gate']['token']['display'] ?? '');
        self::assertSame('OPEN', $payload['token_gate']['gate']['status_label'] ?? '');
        self::assertSame('READY', $payload['token_gate']['auto_warm']['status_label'] ?? '');
        self::assertSame(false, $payload['permissions']['can_manage_token'] ?? true);
    }

    #[RunInSeparateProcess]
    public function test_attendance_tab_requires_exam_scope_and_never_exposes_password(): void
    {
        $this->bootstrapSupervisorStubs();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-supervisor-dashboard-service.php';

        global $wpdb;
        $wpdb = new SupervisorDashboardServiceFakeWpdb();

        $missingExamPayload = \CBT_Supervisor_Dashboard_Service::build_dashboard_payload(41, 'teacher', [
            'tab' => 'attendance',
        ]);
        self::assertSame(false, $missingExamPayload['attendance']['available'] ?? true);

        $payload = \CBT_Supervisor_Dashboard_Service::build_dashboard_payload(41, 'teacher', [
            'tab' => 'attendance',
            'exam_id' => 8,
            'attendance_status' => 'in_progress',
        ]);

        self::assertSame(true, $payload['attendance']['available'] ?? false);
        self::assertSame(1, $payload['attendance']['total'] ?? 0);
        self::assertSame('in_progress', $payload['attendance']['items'][0]['status'] ?? '');
        self::assertArrayNotHasKey('password', $payload['attendance']['items'][0] ?? []);
    }

    #[RunInSeparateProcess]
    public function test_reset_login_for_attempt_forwards_scope_and_source_to_results_service(): void
    {
        $this->bootstrapSupervisorStubs();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-supervisor-dashboard-service.php';

        $result = \CBT_Supervisor_Dashboard_Service::reset_login_for_attempt(55, 99, 'administrator');

        self::assertIsArray($result);
        self::assertSame(55, $result['attempt_id'] ?? 0);
        self::assertSame(99, $GLOBALS['cbt_test_supervisor_reset_scope']['actor_user_id'] ?? 0);
        self::assertSame(true, $GLOBALS['cbt_test_supervisor_reset_scope']['is_admin_scope'] ?? false);
        self::assertSame('supervisor_dashboard', $GLOBALS['cbt_test_supervisor_reset_scope']['source'] ?? '');
    }

    private function bootstrapSupervisorStubs(): void
    {
        if (!class_exists('CBT_Auth', false)) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function is_admin_role(string $role): bool
    {
        return in_array(strtolower($role), ['admin', 'administrator'], true);
    }

    public static function get_global_exam_token(bool $auto_rotate = true): array
    {
        return [
            'token' => 'ABC123',
            'refresh_minutes' => 15,
            'generated_at' => 1770000000,
            'next_refresh_at' => 1770000900,
            'remaining_seconds' => 600,
            'frontend_auto_apply' => 1,
        ];
    }
}
PHP);
        }

        if (!class_exists('CBT_Live_Attempt_Roster_Index', false)) {
            eval(<<<'PHP'
class CBT_Live_Attempt_Roster_Index
{
    public static function is_available(): bool
    {
        return true;
    }

    public static function get_grouped_payloads(array $filters = []): array
    {
        $teacherId = (int) ($filters['teacher_id'] ?? 0);
        $row = [
            'attempt_id' => 55,
            'exam_id' => 8,
            'student_id' => 71,
            'student_name' => 'Indar Bismoko',
            'student_login' => 'indar',
            'student_kode_kelas' => 'XI TKJ 1',
            'student_kode_ruang' => 'R-2',
            'exam_title' => 'UTS',
            'presence_status' => 'online',
            'connection_status' => 'online',
            'visibility_state' => 'visible',
            'has_focus' => 1,
            'pending_sync_count' => 0,
            'heartbeat_lost_active' => 0,
            'risk_tone' => 'watch',
            'risk_score' => 9.0,
            'last_seen_at' => '2026-04-24 08:00:00',
            'teacher_id' => $teacherId,
        ];

        return [[
            'teacher_id' => $teacherId,
            'attempts' => [$row],
        ]];
    }
}
PHP);
        }

        if (!class_exists('CBT_Security_Log', false)) {
            eval(<<<'PHP'
class CBT_Security_Log
{
    public static function get_must_watch_attempts(int $limit = 5, array $filters = []): array
    {
        return [[
            'attempt_id' => 55,
            'exam_id' => 8,
            'student_id' => 71,
            'student_name' => 'Indar Bismoko',
            'student_login' => 'indar',
            'student_kode_kelas' => 'XI TKJ 1',
            'student_kode_ruang' => 'R-2',
            'exam_title' => 'UTS',
            'risk_score' => 9.0,
            'risk_label' => 'Must Watch',
            'primary_event_label' => 'Clipboard diblokir',
            'last_event_at' => '2026-04-24 08:05:00',
            'presence_status' => 'online',
            'top_indicators' => ['Clipboard', 'Blur'],
        ]];
    }

    public static function format_risk_score(float $score): string
    {
        return number_format($score, 1, '.', '');
    }

    public static function event_definitions(): array
    {
        return [
            'tab_hidden' => [
                'label' => 'Tab Hidden',
                'severity' => 'critical',
            ],
            'copy_blocked' => [
                'label' => 'Copy Blocked',
                'severity' => 'warning',
            ],
        ];
    }

    public static function get_recent_logs(int $limit = 50, array $filters = []): array
    {
        return [
            [
                'id' => 901,
                'attempt_id' => 55,
                'exam_id' => 8,
                'student_id' => 71,
                'student_name' => 'Indar Bismoko',
                'student_login' => 'indar',
                'student_kode_kelas' => 'XI TKJ 1',
                'student_kode_ruang' => 'R-2',
                'exam_title' => 'UTS',
                'event_type' => 'tab_hidden',
                'event_label' => 'Tab Hidden',
                'severity' => 'critical',
                'message_display' => 'Tab berpindah saat ujian.',
                'device_type' => 'mobile',
                'device_summary' => 'Mobile • Android',
                'occurred_at' => '2026-04-24 08:05:00',
            ],
            [
                'id' => 902,
                'attempt_id' => 56,
                'exam_id' => 8,
                'student_id' => 72,
                'student_name' => 'Siswa Lain',
                'student_login' => 'lain',
                'student_kode_kelas' => 'XI TKJ 1',
                'student_kode_ruang' => 'R-3',
                'exam_title' => 'UTS',
                'event_type' => 'copy_blocked',
                'event_label' => 'Copy Blocked',
                'severity' => 'warning',
                'message_display' => 'Copy diblokir.',
                'device_type' => 'desktop',
                'device_summary' => 'Desktop',
                'occurred_at' => '2026-04-24 08:04:00',
            ],
        ];
    }
}
PHP);
        }

        if (!class_exists('CBT_Admin_Results_Service', false)) {
            eval(<<<'PHP'
class CBT_Admin_Results_Service
{
    public static function build_frontend_monitoring_context(array $filters): array
    {
        return [
            'items' => [[
                'attempt_id' => 55,
                'exam_id' => 8,
                'exam_title' => 'UTS',
                'student_id' => 71,
                'student_name' => 'Indar Bismoko',
                'student_username' => 'indar',
                'student_nisn' => '1234567890',
                'student_kelas' => 'XI TKJ 1',
                'status' => 'in_progress',
                'status_label' => 'Berjalan',
                'score_percentage_label' => '15.71%',
                'earned_points' => 11,
                'wrong_points' => 59,
                'answer_count' => 70,
                'question_count' => 70,
                'answered_percentage_label' => '100.00%',
                'started_at' => '2026-04-24 07:18:44',
                'remaining_label' => 'Diproses',
                'finalize_pending' => true,
                'presence_label' => 'Offline',
            ]],
            'total' => 1,
            'pagination' => [
                'current_page' => 1,
                'per_page' => 8,
                'total_pages' => 1,
                'total_items' => 1,
            ],
            'submit_health' => [
                'available' => true,
                'finish_ack_total' => 1,
                'result_ready_total' => 0,
                'recovery_failed_total' => 0,
                'ack_to_result_ready_p95_label' => 'N/A',
                'note' => 'Ringkasan 15 menit terakhir untuk submit -> result recovery.',
            ],
            'submit_watchlist' => [
                'available' => true,
                'display_count' => 1,
                'total' => 1,
                'note' => 'Pantau unresolved submit yang butuh perhatian operator.',
                'items' => [[
                    'attempt_id' => 55,
                    'student_name' => 'Indar Bismoko',
                    'student_username' => 'indar',
                    'student_nisn' => '1234567890',
                    'student_kelas' => 'XI TKJ 1',
                    'exam_title' => 'UTS',
                    'state_label' => 'Result Pending',
                    'detail' => 'Ujian sudah selesai di server. Nilai sedang dipulihkan.',
                ]],
            ],
        ];
    }

    public static function reset_login_for_attempt_with_scope(
        int $attempt_id,
        bool $is_admin_scope,
        int $scope_user_id,
        int $actor_user_id = 0,
        string $action_source = 'admin_reset_user_login'
    ): array {
        $GLOBALS['cbt_test_supervisor_reset_scope'] = [
            'attempt_id' => $attempt_id,
            'is_admin_scope' => $is_admin_scope,
            'scope_user_id' => $scope_user_id,
            'actor_user_id' => $actor_user_id,
            'source' => $action_source,
        ];

        return [
            'attempt_id' => $attempt_id,
            'message' => 'Login siswa berhasil di-reset.',
        ];
    }
}
PHP);
        }

        if (!class_exists('CBT_Security_Event_Ingest', false)) {
            eval(<<<'PHP'
class CBT_Security_Event_Ingest
{
    public static function get_status_snapshot(): array
    {
        return [
            'mode' => 'redis_live',
            'status_label' => 'Live Redis • Ingest direct MySQL • Persist direct MySQL',
            'backlog_count' => 0,
            'dead_letter_count' => 0,
            'last_flush_at' => '2026-04-24 08:10:00',
            'next_flush_at' => '2026-04-24 08:11:00',
        ];
    }
}
PHP);
        }

        if (!class_exists('CBT_Start_Attempt_Gate_Service', false)) {
            eval(<<<'PHP'
class CBT_Start_Attempt_Gate_Service
{
    public static function get_exam_diagnostics(int $exam_id): array
    {
        return [
            'redis_available' => true,
            'status_label' => 'OPEN',
            'status_tone' => 'success',
            'status_slug' => 'open',
            'queue_depth' => 0,
            'bucket_tokens' => 50.0,
            'gate_capacity' => 50,
            'gate_window_seconds' => 5,
            'release_rate_label' => '50 / 5 detik',
            'oldest_wait_seconds' => 0,
        ];
    }
}
PHP);
        }

        if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service', false)) {
            eval(<<<'PHP'
class CBT_Exam_Availability_Auto_Warm_Service
{
    public static function get_exam_panel_context(array $exam_row): array
    {
        return [
            'enabled' => true,
            'status' => 'ready',
            'status_label' => 'READY',
            'status_tone' => 'success',
            'target_kelas' => ['XI TKJ 1'],
            'target_student_count' => 2,
            'prepared_count' => 1,
            'last_message' => 'Siap menjalankan auto-warm availability.',
            'can_start' => true,
            'can_stop' => false,
            'redis_available' => true,
        ];
    }
}
PHP);
        }
    }
}

final class SupervisorDashboardServiceFakeWpdb extends \wpdb
{
    /** @return array{query:string,args:array<int,mixed>} */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (strpos($query, 'SELECT id, title, status, target_kelas') !== false) {
            return [
                [
                    'id' => 8,
                    'title' => 'UTS',
                    'status' => 'published',
                    'target_kelas' => 'XI TKJ 1',
                    'duration_minutes' => 90,
                    'starts_at' => '2026-04-24 08:00:00',
                    'ends_at' => '2026-04-24 09:30:00',
                    'created_by' => 41,
                ],
            ];
        }

        if (strpos($query, 'FROM wp_users u') !== false && strpos($query, 'kode_kelas') !== false) {
            return [
                [
                    'student_id' => 71,
                    'student_username' => 'indar',
                    'student_name' => 'Indar Bismoko',
                    'student_nisn' => '1234567890',
                    'student_kelas' => 'XI TKJ 1',
                    'student_ruang' => 'R-2',
                    'attempt_id' => 55,
                    'attempt_status' => 'in_progress',
                    'started_at' => '2026-04-24 08:00:00',
                    'finished_at' => '',
                    'password' => 'secret',
                ],
                [
                    'student_id' => 72,
                    'student_username' => 'sari',
                    'student_name' => 'Sari',
                    'student_nisn' => '0987654321',
                    'student_kelas' => 'XI TKJ 1',
                    'student_ruang' => 'R-2',
                    'attempt_id' => 0,
                    'attempt_status' => '',
                    'started_at' => '',
                    'finished_at' => '',
                    'password' => 'secret',
                ],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_exams') !== false) {
            return [
                ['id' => 8, 'title' => 'UTS'],
            ];
        }

        if (strpos($query, 'DISTINCT kelas_meta.meta_value AS kelas') !== false) {
            return [
                ['kelas' => 'XI TKJ 1'],
            ];
        }

        if (strpos($query, 'DISTINCT ruang_meta.meta_value AS ruang') !== false) {
            return [
                ['ruang' => 'R-2'],
            ];
        }

        return [];
    }
}
