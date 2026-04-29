<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-gate-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-snapshot-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-entry-flow-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-snapshot-auto-heal-queue-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-adaptive-load-service.php';

final class AdaptiveLoadServiceTest extends TestCase
{
    private AdaptiveLoadRuntimeRedisClient $runtimeRedis;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        delete_option('cbt_adaptive_load_state');
        delete_option('cbt_snapshot_auto_heal_queue_state');
        CBT_Cache::delete('adaptive_load_signals:v1', []);

        $this->useFakeStartAttemptGateRedis();
        $this->useFakeLoginMetricsRedis();
        $this->useFakeStartAttemptMetricsRedis();
        $this->useFakeEntryFlowMetricsRedis();
        $this->useFakeRuntimeRedis();
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

    public function test_tick_escalates_to_busy_when_start_attempt_p95_is_slow(): void
    {
        for ($index = 0; $index < 10; $index++) {
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_response_ready', 6500);
        }

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('busy', $state['effective_level']);
        self::assertStringContainsString('p95 start attempt naik', implode(' ', (array) $state['reasons']));
    }

    public function test_tick_escalates_to_critical_when_start_attempt_status_p95_is_slow(): void
    {
        for ($index = 0; $index < 10; $index++) {
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_status_response_ready', 10000);
        }

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('critical', $state['effective_level']);
        self::assertStringContainsString('p95 start attempt status naik', implode(' ', (array) $state['reasons']));
    }

    public function test_tick_ignores_start_attempt_metrics_when_sample_is_too_small(): void
    {
        for ($index = 0; $index < 9; $index++) {
            CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_response_ready', 30000);
        }

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('normal', $state['effective_level']);
    }

    public function test_tick_escalates_to_busy_when_login_to_exam_list_p95_is_slow(): void
    {
        for ($index = 0; $index < 10; $index++) {
            CBT_Entry_Flow_Metrics_Service::record_flow('login_to_exam_list', 4200);
        }

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('busy', $state['effective_level']);
        self::assertStringContainsString('login ke daftar exam', implode(' ', (array) $state['reasons']));
    }

    public function test_tick_escalates_to_critical_when_start_to_first_question_p95_is_slow(): void
    {
        for ($index = 0; $index < 10; $index++) {
            CBT_Entry_Flow_Metrics_Service::record_flow('start_to_first_question', 30000);
        }

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('critical', $state['effective_level']);
        self::assertStringContainsString('mulai ujian ke soal pertama', implode(' ', (array) $state['reasons']));
    }

    public function test_tick_ignores_entry_flow_metrics_when_sample_is_too_small(): void
    {
        for ($index = 0; $index < 9; $index++) {
            CBT_Entry_Flow_Metrics_Service::record_flow('resume_to_first_question', 60000);
        }

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('normal', $state['effective_level']);
    }

    public function test_tick_uses_runtime_active_user_count_without_timestampadd_query(): void
    {
        $this->seedRuntimeActiveAttempts(450);
        $wpdb = $GLOBALS['wpdb'];

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('busy', $state['effective_level']);
        self::assertSame(450, (int) ($state['signals']['active_attempt_count'] ?? 0));
        self::assertStringContainsString('Attempt aktif tinggi', implode(' ', (array) $state['reasons']));
        self::assertSame(0, $wpdb->timestampAddQueryCount);
    }

    public function test_tick_runtime_active_count_uses_default_window(): void
    {
        $key = $this->runtimeKey('active_attempts');
        $this->runtimeRedis->zAdd($key, time(), '1');
        $this->runtimeRedis->zAdd($key, time() - 7201, '2');
        $wpdb = $GLOBALS['wpdb'];

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame(1, (int) ($state['signals']['active_attempt_count'] ?? 0));
        self::assertSame(0, $wpdb->timestampAddQueryCount);
    }

    public function test_tick_falls_back_to_db_active_attempt_count_when_runtime_unavailable(): void
    {
        $this->markRuntimeUnavailable();
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->activeAttemptCount = 450;

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('busy', $state['effective_level']);
        self::assertSame(450, (int) ($state['signals']['active_attempt_count'] ?? 0));
        self::assertSame(1, $wpdb->timestampAddQueryCount);
    }

    public function test_active_runtime_window_is_clamped(): void
    {
        $reflection = new ReflectionClass(CBT_Adaptive_Load_Service::class);
        $method = $reflection->getMethod('normalize_active_runtime_window_seconds');
        $method->setAccessible(true);

        self::assertSame(300, $method->invoke(null, 10));
        self::assertSame(7200, $method->invoke(null, 7200));
        self::assertSame(172800, $method->invoke(null, 999999));
    }

    public function test_tick_reuses_cached_signals_within_cache_ttl(): void
    {
        $this->seedRuntimeActiveAttempts(450);
        $wpdb = $GLOBALS['wpdb'];

        $firstState = CBT_Adaptive_Load_Service::tick();
        $secondState = CBT_Adaptive_Load_Service::tick();

        self::assertSame('busy', $firstState['effective_level']);
        self::assertSame(450, (int) ($secondState['signals']['active_attempt_count'] ?? 0));
        self::assertSame(1, $wpdb->examIdsQueryCount);
        self::assertSame(0, $wpdb->timestampAddQueryCount);
    }

    public function test_tick_refreshes_cached_signals_after_cache_ttl(): void
    {
        $this->seedRuntimeActiveAttempts(10);
        $wpdb = $GLOBALS['wpdb'];

        $firstState = CBT_Adaptive_Load_Service::tick();

        $this->setCurrentTime(1774353631, '2026-03-24 12:00:31');
        $this->seedRuntimeActiveAttempts(450);
        $secondState = CBT_Adaptive_Load_Service::tick();

        self::assertSame(10, (int) ($firstState['signals']['active_attempt_count'] ?? 0));
        self::assertSame(450, (int) ($secondState['signals']['active_attempt_count'] ?? 0));
        self::assertSame(2, $wpdb->examIdsQueryCount);
        self::assertSame(0, $wpdb->timestampAddQueryCount);
    }

    public function test_signals_cache_ttl_is_clamped(): void
    {
        $reflection = new ReflectionClass(CBT_Adaptive_Load_Service::class);
        $method = $reflection->getMethod('normalize_signals_cache_ttl');
        $method->setAccessible(true);

        self::assertSame(5, $method->invoke(null, 1));
        self::assertSame(30, $method->invoke(null, 30));
        self::assertSame(120, $method->invoke(null, 999));
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

    public function test_tick_repairs_far_future_auto_hold_before_deescalating(): void
    {
        update_option('cbt_adaptive_load_state', [
            'effective_level' => 'critical',
            'candidate_level' => 'busy',
            'source' => 'auto',
            'reasons' => ['Level ditahan di CRITICAL untuk mencegah flap.'],
            'last_evaluated_at' => '2026-03-24 11:59:00',
            'hold_until' => '2026-04-22 08:29:46',
            'override_level' => '',
            'override_expires_at' => '',
            'override_user_id' => 0,
            'clean_ticks' => 3,
            'signals' => [],
        ]);

        $state = CBT_Adaptive_Load_Service::tick();

        self::assertSame('normal', $state['candidate_level']);
        self::assertSame('normal', $state['effective_level']);
        self::assertSame('', $state['hold_until']);
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

    private function useFakeStartAttemptMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Start_Attempt_Metrics_Service::class);

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

    private function useFakeEntryFlowMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Entry_Flow_Metrics_Service::class);

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

    private function useFakeRuntimeRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $this->runtimeRedis = new AdaptiveLoadRuntimeRedisClient();
        $redisProperty->setValue(null, $this->runtimeRedis);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');

        $cachedPrefixProperty = $reflection->getProperty('cached_prefix');
        $cachedPrefixProperty->setAccessible(true);
        $cachedPrefixProperty->setValue(null, null);
    }

    private function markRuntimeUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Runtime test unavailable.');
    }

    private function seedRuntimeActiveAttempts(int $count): void
    {
        $key = $this->runtimeKey('active_attempts');
        $score = time();
        for ($attemptId = 1; $attemptId <= $count; $attemptId++) {
            $this->runtimeRedis->zAdd($key, $score, (string) $attemptId);
        }
    }

    private function runtimeKey(string $suffix): string
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);
        $method = $reflection->getMethod('prefixed_key');
        $method->setAccessible(true);

        return (string) $method->invoke(null, $suffix);
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

final class AdaptiveLoadRuntimeRedisClient extends CBT_Test_Redis_Client
{
    public function zCard($key): int
    {
        $items = $GLOBALS['cbt_test_redis_zsets'][(string) $key] ?? [];
        return is_array($items) ? count($items) : 0;
    }

    public function zCount($key, $start, $end): int
    {
        $items = $GLOBALS['cbt_test_redis_zsets'][(string) $key] ?? [];
        if (!is_array($items) || empty($items)) {
            return 0;
        }

        $min = is_numeric($start) ? (float) $start : 0.0;
        $count = 0;
        foreach ($items as $score) {
            if ((float) $score >= $min) {
                $count++;
            }
        }

        return $count;
    }
}

final class AdaptiveLoadServiceFakeWpdb
{
    public string $prefix = 'wp_';

    public int $activeAttemptCount = 0;

    public int $examIdsQueryCount = 0;

    public int $timestampAddQueryCount = 0;

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
            $this->examIdsQueryCount++;
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

        if (
            strpos($query, 'FROM wp_cbt_attempts a') !== false
            && strpos($query, 'TIMESTAMPADD(MINUTE') !== false
        ) {
            $this->timestampAddQueryCount++;
            return $this->activeAttemptCount;
        }

        return 0;
    }
}
