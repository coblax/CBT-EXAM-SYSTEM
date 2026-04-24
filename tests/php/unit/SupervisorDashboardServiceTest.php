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
        self::assertArrayHasKey('submit_watchlist', $payload);
        self::assertCount(1, $payload['live_roster']['items'] ?? []);
        self::assertSame('Indar Bismoko', $payload['live_roster']['items'][0]['student_name'] ?? '');
        self::assertSame('Must Watch', $payload['must_watch']['items'][0]['risk_label'] ?? '');
        self::assertSame(1, $payload['monitoring_attempts']['total'] ?? 0);
        self::assertSame(1, $payload['submit_watchlist']['display_count'] ?? 0);
        self::assertNotEmpty($payload['filter_options']['exams'] ?? []);
        self::assertNotEmpty($payload['filter_options']['kelas'] ?? []);
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

        return [];
    }
}
