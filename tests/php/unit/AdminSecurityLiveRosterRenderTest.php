<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AdminSecurityLiveRosterRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-page.php';
    }

    public function test_render_panel_outputs_live_roster_groups_and_rows(): void
    {
        $html = $this->renderRosterPanel([
            [
                'exam_title' => 'TEST Security Fixture',
                'kelas_label' => 'KELAS_TEST_02',
                'ruang_label' => 'RUANG_TEST_01',
                'active_total' => 1,
                'online_total' => 1,
                'stale_total' => 0,
                'offline_total' => 0,
                'watch_total' => 1,
                'high_risk_total' => 1,
                'attempts' => [
                    [
                        'attempt_id' => 7,
                        'exam_id' => 22,
                        'student_name' => 'Siswa Test 0002',
                        'student_login' => 'test_siswa_0002',
                        'presence_status' => 'online',
                        'last_seen_at' => '2026-04-03 05:57:21',
                        'connection_status' => 'degraded',
                        'visibility_state' => 'hidden',
                        'has_focus' => 0,
                        'pending_sync_count' => 3,
                        'heartbeat_lost_active' => 1,
                        'risk_tone' => 'high-risk',
                        'risk_score' => 12.5,
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('Live Roster', $html);
        self::assertStringContainsString('TEST Security Fixture', $html);
        self::assertStringContainsString('Kelas:', $html);
        self::assertStringContainsString('KELAS_TEST_02', $html);
        self::assertStringContainsString('Ruang:', $html);
        self::assertStringContainsString('RUANG_TEST_01', $html);
        self::assertStringContainsString('>Online<', $html);
        self::assertStringContainsString('Seen:', $html);
        self::assertStringContainsString('Sync 3', $html);
        self::assertStringContainsString('Hidden', $html);
        self::assertStringContainsString('Focus Off', $html);
        self::assertStringContainsString('Heartbeat', $html);
        self::assertStringContainsString('Conn DEGRADED', $html);
        self::assertStringContainsString('High Risk', $html);
        self::assertStringContainsString('Buka Results', $html);
        self::assertStringNotContainsString('Pelanggaran Dominan', $html);
    }

    public function test_render_panel_shows_empty_state_when_no_live_attempts_exist(): void
    {
        $html = $this->renderRosterPanel([]);

        self::assertStringContainsString('Belum ada attempt aktif yang masuk roster live saat ini.', $html);
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     */
    private function renderRosterPanel(array $groups): string
    {
        ob_start();
        \CBT_Admin_Security_Page::render_security_log_live_roster_panel($groups);

        return (string) ob_get_clean();
    }
}
