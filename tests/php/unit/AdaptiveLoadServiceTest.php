<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-gate-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-snapshot-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-snapshot-auto-heal-queue-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-adaptive-load-service.php';

final class AdaptiveLoadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        delete_option('cbt_adaptive_load_state');
        delete_option('cbt_snapshot_auto_heal_queue_state');

        $this->useFakeStartAttemptGateRedis();
        $this->useFakeLoginMetricsRedis();
        $GLOBALS['wpdb'] = new AdaptiveLoadServiceFakeWpdb();
    }

    public function test_tick_escalates_to_busy_when_login_hit_rate_drops_below_threshold(): void
    {
        for ($index = 0; $index < 15; $index++) {
            CBT_Login_Snapshot_Metrics_Service::record_snapshot_success();
        }

        for ($index = 0; $index < 5; $index++) {
            CBT_Login_Snapshot_Metrics_Service::record_canonical_success('expired_or_evicted');
        }

        $state = CBT_Adaptive_Load_Service::tick();
        $payload = CBT_Adaptive_Load_Service::get_frontend_payload();

        self::assertSame('busy', $state['effective_level']);
        self::assertSame('busy', $state['candidate_level']);
        self::assertSame('auto', $state['source']);
        self::assertStringContainsString('Hit rate login snapshot turun', implode(' ', (array) $state['reasons']));
        self::assertSame('busy', $payload['level']);
        self::assertSame(30000, (int) $payload['heartbeat_interval_ms']);
        self::assertSame(20, (int) $payload['admin_snapshot_refresh_seconds']);
    }

    public function test_tick_escalates_to_critical_when_auto_heal_queue_is_heavy(): void
    {
        $this->seedAutoHealQueueDepth(500);

        $state = CBT_Adaptive_Load_Service::tick();
        $policy = CBT_Adaptive_Load_Service::get_policy((string) $state['effective_level']);

        self::assertSame('critical', $state['effective_level']);
        self::assertSame('critical', $state['candidate_level']);
        self::assertStringContainsString('Auto-heal queue menumpuk', implode(' ', (array) $state['reasons']));
        self::assertSame(45000, (int) $policy['heartbeat_interval_ms']);
        self::assertSame(40, (int) $policy['admin_snapshot_refresh_seconds']);
    }

    public function test_tick_holds_level_before_deescalating_even_after_three_clean_ticks(): void
    {
        $this->seedAutoHealQueueDepth(500);
        $initial = CBT_Adaptive_Load_Service::tick();

        self::assertSame('critical', $initial['effective_level']);

        $this->seedAutoHealQueueDepth(0);

        $this->setCurrentTime(1774353660, '2026-03-24 12:01:00');
        CBT_Adaptive_Load_Service::tick();
        $this->setCurrentTime(1774353720, '2026-03-24 12:02:00');
        CBT_Adaptive_Load_Service::tick();
        $this->setCurrentTime(1774353780, '2026-03-24 12:03:00');
        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('critical', $state['effective_level']);
        self::assertSame('normal', $state['candidate_level']);
        self::assertSame(3, (int) $state['clean_ticks']);
        self::assertStringContainsString('Level ditahan di CRITICAL', implode(' ', (array) $state['reasons']));
    }

    public function test_tick_deescalates_after_three_clean_ticks_once_hold_window_has_elapsed(): void
    {
        $this->seedAutoHealQueueDepth(500);
        $initial = CBT_Adaptive_Load_Service::tick();

        self::assertSame('critical', $initial['effective_level']);

        $this->seedAutoHealQueueDepth(0);

        $this->setCurrentTime(1774353901, '2026-03-24 12:05:01');
        CBT_Adaptive_Load_Service::tick();
        $this->setCurrentTime(1774353961, '2026-03-24 12:06:01');
        CBT_Adaptive_Load_Service::tick();
        $this->setCurrentTime(1774354021, '2026-03-24 12:07:01');
        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('normal', $state['effective_level']);
        self::assertSame('normal', $state['candidate_level']);
        self::assertSame('auto', $state['source']);
        self::assertSame(0, (int) $state['clean_ticks']);
    }

    public function test_manual_override_beats_auto_level_until_it_expires(): void
    {
        $this->seedAutoHealQueueDepth(500);
        CBT_Adaptive_Load_Service::tick();

        $overrideState = CBT_Adaptive_Load_Service::set_manual_override('busy', 19);

        self::assertSame('busy', $overrideState['effective_level']);
        self::assertSame('busy', $overrideState['override_level']);
        self::assertSame('manual_override', $overrideState['source']);
        self::assertSame(19, (int) $overrideState['override_user_id']);

        $this->setCurrentTime(1774354200, '2026-03-24 12:10:00');
        $heldState = CBT_Adaptive_Load_Service::tick();

        self::assertSame('busy', $heldState['effective_level']);
        self::assertSame('manual_override', $heldState['source']);

        $this->setCurrentTime(1774354501, '2026-03-24 12:15:01');
        $expiredState = CBT_Adaptive_Load_Service::tick();

        self::assertSame('critical', $expiredState['effective_level']);
        self::assertSame('', $expiredState['override_level']);
        self::assertSame('auto', $expiredState['source']);
    }

    private function useFakeStartAttemptGateRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Gate_Service::class);

        $redisProperty = $reflection->getProperty('gate_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('gate_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('gate_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeLoginMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Login_Snapshot_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setCurrentTime(int $timestamp, string $mysql): void
    {
        $GLOBALS['cbt_test_current_time_timestamp'] = $timestamp;
        $GLOBALS['cbt_test_current_time_mysql'] = $mysql;
    }

    private function seedAutoHealQueueDepth(int $count): void
    {
        $items = [];
        for ($index = 1; $index <= $count; $index++) {
            $key = 'profile_user:' . $index;
            $items[$key] = [
                'key' => $key,
                'type' => 'profile_user',
                'target_id' => $index,
                'reason' => 'meta_changed',
                'source' => 'test',
                'status' => 'queued',
                'attempt_count' => 0,
                'next_attempt_at' => 0,
                'updated_at' => current_time('mysql'),
            ];
        }

        update_option('cbt_snapshot_auto_heal_queue_state', [
            'items' => $items,
        ]);
    }
}

final class AdaptiveLoadServiceFakeWpdb
{
    public string $prefix = 'wp_';

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "\\'", (string) $arg) . "'";
            $query = preg_replace('/%[dfs]/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    /**
     * @param string $prepared
     * @return array<int,string>
     */
    public function get_col($prepared): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT id FROM wp_cbt_exams WHERE title NOT LIKE') !== false) {
            return ['77', '54'];
        }

        return [];
    }

    /**
     * @param string $prepared
     */
    public function get_var($prepared)
    {
        $query = (string) $prepared;

        if (strpos($query, "SELECT COUNT(*) FROM wp_cbt_attempts WHERE status = 'in_progress'") !== false) {
            return 0;
        }

        return 0;
    }
}
