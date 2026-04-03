<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsPresenceMonitoringTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_overlay_attempt_presence_payloads_applies_snapshot_only_to_in_progress_attempts(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-proctoring-presence.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';

        $this->useFakePresenceRedis();
        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-04-03 05:58:00');

        CBT_Live_Proctoring_Presence::update_attempt_presence(
            [
                'id' => 7,
                'exam_id' => 22,
                'student_id' => 12,
                'status' => 'in_progress',
            ],
            [
                'last_seen_at' => '2026-04-03 05:57:21',
                'connection_status' => 'degraded',
                'visibility_state' => 'hidden',
                'has_focus' => 0,
                'pending_sync_count' => 3,
                'heartbeat_lost_active' => 1,
            ]
        );

        $attempts = [
            [
                'id' => 7,
                'exam_id' => 22,
                'student_id' => 12,
                'status' => 'in_progress',
                'student_name' => 'Siswa Test 0002',
            ],
            [
                'id' => 8,
                'exam_id' => 22,
                'student_id' => 13,
                'status' => 'completed',
                'student_name' => 'COBLAX',
            ],
        ];

        $overlayMethod = new ReflectionMethod(CBT_Admin_Results_Service::class, 'overlay_attempt_presence_payloads');
        $overlayMethod->setAccessible(true);
        /** @var array<int,array<string,mixed>> $overlayedAttempts */
        $overlayedAttempts = $overlayMethod->invoke(null, $attempts);

        self::assertSame('online', $overlayedAttempts[0]['presence_status']);
        self::assertSame('2026-04-03 05:57:21', $overlayedAttempts[0]['presence_last_seen_at']);
        self::assertSame('degraded', $overlayedAttempts[0]['presence_connection_status']);
        self::assertSame('hidden', $overlayedAttempts[0]['presence_visibility_state']);
        self::assertSame(0, $overlayedAttempts[0]['presence_has_focus']);
        self::assertSame(3, $overlayedAttempts[0]['presence_pending_sync_count']);
        self::assertSame(1, $overlayedAttempts[0]['presence_heartbeat_lost_active']);
        self::assertArrayNotHasKey('presence_status', $overlayedAttempts[1]);
        self::assertArrayNotHasKey('presence_last_seen_at', $overlayedAttempts[1]);
    }

    #[RunInSeparateProcess]
    public function test_overlay_attempt_presence_payloads_keeps_rows_unchanged_when_presence_redis_unavailable(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-proctoring-presence.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';

        $this->setPresenceRedisUnavailable();

        $attempts = [
            [
                'id' => 9,
                'exam_id' => 22,
                'student_id' => 14,
                'status' => 'in_progress',
                'student_name' => 'COBLAX',
            ],
        ];

        $overlayMethod = new ReflectionMethod(CBT_Admin_Results_Service::class, 'overlay_attempt_presence_payloads');
        $overlayMethod->setAccessible(true);
        /** @var array<int,array<string,mixed>> $overlayedAttempts */
        $overlayedAttempts = $overlayMethod->invoke(null, $attempts);

        self::assertSame($attempts, $overlayedAttempts);
    }

    #[RunInSeparateProcess]
    public function test_render_attempt_student_presence_monitor_outputs_compact_live_monitoring(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';

        $html = CBT_Admin_Results_Service::render_attempt_student_presence_monitor([
            'status' => 'in_progress',
            'presence_status' => 'online',
            'presence_last_seen_at' => '2026-04-03 05:58:26',
            'presence_connection_status' => 'degraded',
            'presence_visibility_state' => 'hidden',
            'presence_has_focus' => 0,
            'presence_pending_sync_count' => 3,
            'presence_heartbeat_lost_active' => 1,
        ]);

        self::assertStringContainsString('>Online<', $html);
        self::assertStringContainsString('Seen:', $html);
        self::assertStringContainsString('2026-04-03 05:58:26', $html);
        self::assertStringContainsString('Sync 3', $html);
        self::assertStringContainsString('Hidden', $html);
        self::assertStringContainsString('Focus Off', $html);
        self::assertStringContainsString('Heartbeat', $html);
        self::assertStringContainsString('Conn DEGRADED', $html);
        self::assertStringNotContainsString('Clipboard diblokir', $html);
        self::assertStringNotContainsString('Pindah tab / aplikasi', $html);
    }

    #[RunInSeparateProcess]
    public function test_render_attempt_student_presence_monitor_stays_hidden_for_completed_rows_or_empty_snapshot(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';

        $completedHtml = CBT_Admin_Results_Service::render_attempt_student_presence_monitor([
            'status' => 'completed',
            'presence_status' => 'online',
            'presence_last_seen_at' => '2026-04-03 05:58:26',
            'presence_connection_status' => 'online',
        ]);
        $emptyHtml = CBT_Admin_Results_Service::render_attempt_student_presence_monitor([
            'status' => 'in_progress',
            'presence_status' => '',
            'presence_last_seen_at' => '',
            'presence_connection_status' => '',
            'presence_visibility_state' => '',
            'presence_has_focus' => null,
            'presence_pending_sync_count' => 0,
            'presence_heartbeat_lost_active' => 0,
        ]);

        self::assertSame('', $completedHtml);
        self::assertSame('', $emptyHtml);
    }

    private function useFakePresenceRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Live_Proctoring_Presence::class);

        $redisProperty = $reflection->getProperty('presence_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('presence_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('presence_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setPresenceRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Live_Proctoring_Presence::class);

        $redisProperty = $reflection->getProperty('presence_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('presence_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('presence_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in results presence test');
    }
}
