<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class LiveProctoringPresenceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_presence_snapshot_reads_back_live_state_and_derives_online_stale_offline(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-proctoring-presence.php';
        $this->useFakePresenceRedis();

        CBT_Live_Proctoring_Presence::update_attempt_presence(
            [
                'id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'status' => 'in_progress',
            ],
            [
                'last_seen_at' => '2026-03-24 12:00:00',
                'connection_status' => 'online',
                'visibility_state' => 'hidden',
                'has_focus' => 0,
                'pending_sync_count' => 3,
                'heartbeat_lost_active' => 1,
                'risk_tone' => 'watch',
            ]
        );

        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-03-24 12:00:30');
        $payloads = CBT_Live_Proctoring_Presence::get_attempt_payloads([114]);
        self::assertArrayHasKey(114, $payloads);
        self::assertSame('online', $payloads[114]['presence_status']);
        self::assertSame('online', $payloads[114]['connection_status']);
        self::assertSame('hidden', $payloads[114]['visibility_state']);
        self::assertSame(0, $payloads[114]['has_focus']);
        self::assertSame(3, $payloads[114]['pending_sync_count']);
        self::assertSame(1, $payloads[114]['heartbeat_lost_active']);
        self::assertSame('watch', $payloads[114]['risk_tone']);

        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-03-24 12:01:10');
        $payloads = CBT_Live_Proctoring_Presence::get_attempt_payloads([114]);
        self::assertSame('stale', $payloads[114]['presence_status']);

        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-03-24 12:01:40');
        $payloads = CBT_Live_Proctoring_Presence::get_attempt_payloads([114]);
        self::assertSame('offline', $payloads[114]['presence_status']);
    }

    #[RunInSeparateProcess]
    public function test_clear_attempt_runtime_clears_presence_snapshot(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-proctoring-presence.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        $this->useFakePresenceRedis();
        $this->useFakeRuntimeRedis();

        CBT_Live_Proctoring_Presence::update_attempt_presence(
            [
                'id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'status' => 'in_progress',
            ],
            [
                'last_seen_at' => '2026-03-24 12:00:00',
                'connection_status' => 'online',
            ]
        );

        self::assertArrayHasKey(114, CBT_Live_Proctoring_Presence::get_attempt_payloads([114]));

        CBT_Runtime::clear_attempt_runtime(114);

        self::assertSame([], CBT_Live_Proctoring_Presence::get_attempt_payloads([114]));
    }

    #[RunInSeparateProcess]
    public function test_clear_all_clears_every_active_presence_snapshot(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-proctoring-presence.php';
        $this->useFakePresenceRedis();

        CBT_Live_Proctoring_Presence::update_attempt_presence(
            [
                'id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'status' => 'in_progress',
            ],
            [
                'last_seen_at' => '2026-03-24 12:00:00',
                'connection_status' => 'online',
            ]
        );

        CBT_Live_Proctoring_Presence::update_attempt_presence(
            [
                'id' => 115,
                'exam_id' => 501,
                'student_id' => 72,
                'status' => 'in_progress',
            ],
            [
                'last_seen_at' => '2026-03-24 12:00:10',
                'connection_status' => 'online',
            ]
        );

        self::assertCount(2, CBT_Live_Proctoring_Presence::get_attempt_payloads());

        CBT_Live_Proctoring_Presence::clear_all();

        self::assertSame([], CBT_Live_Proctoring_Presence::get_attempt_payloads());
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

    private function useFakeRuntimeRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}
