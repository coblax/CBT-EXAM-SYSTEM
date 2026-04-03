<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AdminSecurityMustWatchRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-page.php';
    }

    public function test_render_panel_separates_live_status_from_dominant_violations(): void
    {
        $html = $this->renderMustWatchPanel([
            $this->buildAttempt([
                'presence_status' => 'online',
                'presence_last_seen_at' => '2026-04-03 09:10:11',
                'presence_connection_status' => 'degraded',
                'presence_visibility_state' => 'hidden',
                'presence_has_focus' => 0,
                'presence_pending_sync_count' => 3,
                'presence_heartbeat_lost_active' => 1,
                'top_indicators' => [
                    '12x Pindah tab / aplikasi',
                    '8x Fokus window berpindah',
                    '13x Context menu diblok',
                ],
            ]),
        ]);

        self::assertStringContainsString('Status Live', $html);
        self::assertStringContainsString('Pelanggaran Dominan', $html);
        self::assertStringContainsString('Terlihat terakhir:', $html);
        self::assertStringContainsString('Sync 3', $html);
        self::assertStringContainsString('Tab Hidden', $html);
        self::assertStringContainsString('Focus Off', $html);
        self::assertStringContainsString('Heartbeat Lost', $html);
        self::assertStringContainsString('Conn DEGRADED', $html);
        self::assertStringContainsString('12x Pindah tab / aplikasi', $html);
        self::assertStringContainsString('13x Context menu diblok', $html);
        self::assertStringContainsString('>Online<', $html);
        self::assertStringNotContainsString('<strong>Live:</strong>', $html);
    }

    public function test_render_panel_hides_dominant_violations_group_when_absent(): void
    {
        $html = $this->renderMustWatchPanel([
            $this->buildAttempt([
                'presence_status' => 'stale',
                'presence_last_seen_at' => '2026-04-03 09:10:11',
                'presence_pending_sync_count' => 1,
                'top_indicators' => [],
            ]),
        ]);

        self::assertStringContainsString('Status Live', $html);
        self::assertStringNotContainsString('Pelanggaran Dominan', $html);
    }

    public function test_render_panel_hides_live_group_when_presence_snapshot_absent(): void
    {
        $html = $this->renderMustWatchPanel([
            $this->buildAttempt([
                'presence_status' => '',
                'presence_last_seen_at' => '',
                'presence_connection_status' => '',
                'presence_visibility_state' => '',
                'presence_has_focus' => null,
                'presence_pending_sync_count' => 0,
                'presence_heartbeat_lost_active' => 0,
                'top_indicators' => [
                    '4x Clipboard diblokir',
                ],
            ]),
        ]);

        self::assertStringContainsString('Pelanggaran Dominan', $html);
        self::assertStringNotContainsString('Status Live', $html);
        self::assertStringNotContainsString('Terlihat terakhir:', $html);
    }

    /**
     * @param array<int,array<string,mixed>> $attempts
     */
    private function renderMustWatchPanel(array $attempts): string
    {
        ob_start();
        \CBT_Admin_Security_Page::render_security_log_must_watch_panel($attempts);

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function buildAttempt(array $overrides = []): array
    {
        return array_merge([
            'attempt_id' => 5,
            'exam_id' => 22,
            'student_name' => 'COBLAX',
            'student_login' => 'coblax',
            'student_kode_kelas' => 'KELAS_TEST_01',
            'student_kode_ruang' => 'RUANG_TEST_01',
            'exam_title' => 'TEST Exam 017 - TEST Subject 02 [MIXED]',
            'risk_score' => 90.5,
            'risk_tone' => 'high-risk',
            'risk_label' => 'High Risk',
            'primary_event_type' => 'tab_hidden',
            'primary_event_label' => 'Pindah tab / aplikasi',
            'last_event_at' => '2026-04-03 09:08:07',
            'last_device_type' => 'desktop',
            'last_device_label' => 'Desktop',
            'last_device_summary' => 'Desktop',
            'presence_status' => '',
            'presence_last_seen_at' => '',
            'presence_connection_status' => '',
            'presence_visibility_state' => '',
            'presence_has_focus' => null,
            'presence_pending_sync_count' => 0,
            'presence_heartbeat_lost_active' => 0,
            'top_indicators' => [],
        ], $overrides);
    }
}
