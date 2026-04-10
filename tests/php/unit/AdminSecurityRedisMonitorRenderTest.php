<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AdminSecurityRedisMonitorRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-page.php';
    }

    public function test_render_panel_outputs_mode_health_queue_and_activity_fields(): void
    {
        $html = $this->renderMonitorPanel([
            'feature_enabled' => 1,
            'available' => 1,
            'stream_supported' => 1,
            'worker_scheduled' => 1,
            'backlog_count' => 17,
            'dead_letter_count' => 0,
            'oldest_pending_age_seconds' => 125,
            'last_stream_id' => '1744010000000-0',
            'last_enqueue_at' => '2026-04-10 08:15:00',
            'last_enqueue_status' => 'ok',
            'last_flush_at' => '2026-04-10 08:15:15',
            'last_flush_status' => 'ok',
            'last_flush_result' => '{"persisted":17}',
            'next_flush_at' => '2026-04-10 08:15:30',
            'live_label' => 'Live Redis',
            'ingest_label' => 'Ingest Redis-first',
            'persist_label' => 'Persist batch MySQL',
            'status_label' => 'Live Redis • Ingest Redis-first • Persist batch MySQL',
        ]);

        self::assertStringContainsString('Redis Monitor', $html);
        self::assertStringContainsString('Live Redis', $html);
        self::assertStringContainsString('Ingest Redis-first', $html);
        self::assertStringContainsString('Persist batch MySQL', $html);
        self::assertStringContainsString('Feature Flag', $html);
        self::assertStringContainsString('Redis Available', $html);
        self::assertStringContainsString('Stream Supported', $html);
        self::assertStringContainsString('Worker Scheduled', $html);
        self::assertStringContainsString('Backlog', $html);
        self::assertStringContainsString('Dead Letter', $html);
        self::assertStringContainsString('Last Stream ID', $html);
        self::assertStringContainsString('Last Enqueue', $html);
        self::assertStringContainsString('Last Flush', $html);
        self::assertStringContainsString('Run Micro-Drain', $html);
        self::assertStringContainsString('Force Flush Now', $html);
        self::assertStringContainsString('Copy Diagnostics', $html);
        self::assertStringContainsString('Redis-first aktif. Audit permanen menyusul lewat batch flush.', $html);
        self::assertStringContainsString('data-security-log-monitor-field="worker_scheduled"', $html);
        self::assertStringContainsString('data-security-log-monitor-status', $html);
    }

    public function test_render_panel_shows_fallback_helper_and_disabled_reason(): void
    {
        $html = $this->renderMonitorPanel([
            'feature_enabled' => 0,
            'available' => 0,
            'stream_supported' => 0,
            'worker_scheduled' => 0,
            'backlog_count' => 0,
            'dead_letter_count' => 2,
            'oldest_pending_age_seconds' => 0,
            'last_stream_id' => '',
            'last_enqueue_at' => '',
            'last_enqueue_status' => '',
            'last_enqueue_error' => 'redis unavailable',
            'last_flush_at' => '',
            'last_flush_status' => 'skipped',
            'last_flush_result' => 'redis_unavailable',
            'next_flush_at' => '',
            'live_label' => 'Live MySQL fallback',
            'ingest_label' => 'Ingest direct MySQL',
            'persist_label' => 'Persist direct MySQL',
            'status_label' => 'Live MySQL fallback • Ingest direct MySQL • Persist direct MySQL',
        ]);

        self::assertStringContainsString('Mode fallback aktif. Event tetap aman ditulis ke MySQL langsung.', $html);
        self::assertStringContainsString('Feature flag Redis-first ingest masih nonaktif.', $html);
        self::assertStringContainsString('Live MySQL fallback', $html);
        self::assertStringContainsString('Ingest direct MySQL', $html);
        self::assertStringContainsString('Persist direct MySQL', $html);
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('data-security-log-monitor-disabled-reason', $html);
    }

    /**
     * @param array<string,mixed> $statusSnapshot
     */
    private function renderMonitorPanel(array $statusSnapshot): string
    {
        ob_start();
        \CBT_Admin_Security_Page::render_security_log_redis_monitor_panel($statusSnapshot);

        return (string) ob_get_clean();
    }
}
