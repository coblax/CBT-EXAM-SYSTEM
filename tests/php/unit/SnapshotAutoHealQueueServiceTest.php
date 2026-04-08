<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-snapshot-auto-heal-queue-service.php';

final class SnapshotAutoHealQueueServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        cbt_test_register_user([
            'ID' => 11,
            'display_name' => 'Salsa',
            'roles' => ['student'],
            'user_email' => 'salsa@example.com',
            'user_login' => 'salsa',
            'user_pass' => 'secret',
        ]);
        update_user_meta(11, 'kode_kelas', 'XI-A');
        update_user_meta(11, 'kode_ruang', 'R1');
        update_user_meta(11, 'agama', 'Islam');
        update_user_meta(11, 'foto', 'https://example.com/salsa.jpg');
        update_user_meta(11, 'jenis_kelamin', 'Perempuan');
        update_user_meta(11, 'nisn', '20260011');

        $this->useFakeProfileRedis();
    }

    public function test_maybe_enqueue_dedupes_same_target_and_exposes_queue_state(): void
    {
        $first = CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('profile_user', 11, 'meta_changed', 'invalidation');
        $second = CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('profile_user', 11, 'meta_changed', 'diagnostics');

        $state = CBT_Snapshot_Auto_Heal_Queue_Service::get_state();
        $targetState = CBT_Snapshot_Auto_Heal_Queue_Service::get_target_repair_state('profile_user', 11);

        self::assertTrue($first['enqueued']);
        self::assertTrue($second['enqueued']);
        self::assertSame(1, $state['queued_count']);
        self::assertCount(1, (array) ($state['items'] ?? []));
        self::assertTrue($targetState['queued']);
        self::assertSame('queued_auto_heal', $targetState['status']);
        self::assertSame('diagnostics', $targetState['source']);
        self::assertSame('meta_changed', $targetState['reason']);
    }

    public function test_tick_auto_heals_profile_job_and_clears_queue(): void
    {
        CBT_Student_Profile_Cache::warm_snapshot(11);
        CBT_Student_Profile_Cache::handle_user_meta_change(2, 11, 'kode_kelas', 'XI-A');

        CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('profile_user', 11, 'meta_changed', 'invalidation');
        $state = CBT_Snapshot_Auto_Heal_Queue_Service::tick();
        $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics(11);

        self::assertSame(0, $state['queued_count']);
        self::assertSame(1, $state['last_success_count']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame('XI-A', $diagnostics['preview']['kode_kelas']);
        self::assertFalse(CBT_Snapshot_Auto_Heal_Queue_Service::get_target_repair_state('profile_user', 11)['queued']);
    }

    public function test_tick_retries_then_fails_after_max_retries_for_unavailable_target(): void
    {
        CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('profile_user', 11, 'meta_changed', 'diagnostics');
        $this->setProfileRedisUnavailable();

        $firstTick = CBT_Snapshot_Auto_Heal_Queue_Service::tick();
        $firstState = CBT_Snapshot_Auto_Heal_Queue_Service::get_state();

        self::assertSame(1, $firstTick['queued_count']);
        self::assertSame(0, $firstTick['last_failed_count']);
        self::assertSame(1, (int) ($firstState['items']['profile_user:11']['attempt_count'] ?? 0));

        $GLOBALS['cbt_test_current_time_timestamp'] += MINUTE_IN_SECONDS + 1;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:01:01';
        CBT_Snapshot_Auto_Heal_Queue_Service::tick();

        $secondState = CBT_Snapshot_Auto_Heal_Queue_Service::get_state();
        self::assertSame(2, (int) ($secondState['items']['profile_user:11']['attempt_count'] ?? 0));
        self::assertGreaterThan(
            (int) $GLOBALS['cbt_test_current_time_timestamp'],
            (int) ($secondState['items']['profile_user:11']['next_attempt_at'] ?? 0)
        );

        $GLOBALS['cbt_test_current_time_timestamp'] += (5 * MINUTE_IN_SECONDS) + 1;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:06:02';
        $thirdTick = CBT_Snapshot_Auto_Heal_Queue_Service::tick();
        $finalState = CBT_Snapshot_Auto_Heal_Queue_Service::get_state();
        $history = array_values((array) ($finalState['history'] ?? []));
        $lastHistory = end($history);

        self::assertSame(0, $thirdTick['queued_count']);
        self::assertSame(1, $thirdTick['last_failed_count']);
        self::assertCount(0, (array) ($finalState['items'] ?? []));
        self::assertIsArray($lastHistory);
        self::assertSame('failed', $lastHistory['status']);
    }

    private function useFakeProfileRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setProfileRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }
}
