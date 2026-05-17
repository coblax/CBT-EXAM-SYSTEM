<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class SecurityEventIngestFlushTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_enqueue_event_writes_to_redis_stream(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $payload = [
            'attempt_id' => 42,
            'exam_id' => 10,
            'student_id' => 5,
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'Tab hidden during exam',
            'occurred_at' => '2026-03-24 12:00:00',
        ];

        $result = CBT_Security_Event_Ingest::enqueue_event_payload($payload);

        self::assertTrue($result);
        self::assertGreaterThan(0, $this->getStreamLength());
    }

    #[RunInSeparateProcess]
    public function test_enqueue_event_returns_false_when_feature_disabled(): void
    {
        $this->bootstrapIngestScaffold();
        // Feature NOT enabled

        $payload = [
            'attempt_id' => 42,
            'exam_id' => 10,
            'student_id' => 5,
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'Tab hidden',
            'occurred_at' => '2026-03-24 12:00:00',
        ];

        $result = CBT_Security_Event_Ingest::enqueue_event_payload($payload);

        self::assertFalse($result);
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_persists_events_to_db(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();
        $this->setupFakeSecurityLogPersist(true);

        $initialCount = $this->getStreamLength();

        // Enqueue 3 events
        for ($i = 1; $i <= 3; $i++) {
            CBT_Security_Event_Ingest::enqueue_event_payload([
                'attempt_id' => 42,
                'exam_id' => 10,
                'student_id' => 5,
                'event_type' => 'tab_hidden',
                'severity' => 'warning',
                'message' => 'Event ' . $i,
                'occurred_at' => '2026-03-24 12:0' . $i . ':00',
            ]);
        }

        self::assertSame($initialCount + 3, $this->getStreamLength());

        $result = CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');

        self::assertGreaterThanOrEqual(3, $result['persisted']);
        self::assertSame(0, $result['dead_lettered']);
        self::assertSame(0, $result['failed']);
        self::assertSame(0, $result['backlog_count']);
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_moves_to_dead_letter_after_retry_limit(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();
        $this->setupFakeSecurityLogPersist(false); // Always fail persist

        CBT_Security_Event_Ingest::enqueue_event_payload([
            'attempt_id' => 42,
            'exam_id' => 10,
            'student_id' => 5,
            'event_type' => 'fullscreen_exit',
            'severity' => 'warning',
            'message' => 'Will fail persist',
            'occurred_at' => '2026-03-24 12:00:00',
        ]);

        // Flush 6 times to exceed retry limit (5)
        for ($i = 0; $i < 6; $i++) {
            CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');
        }

        $result = CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');

        // After exceeding retry limit, event should be in dead letter
        self::assertSame(0, $result['backlog_count']);
        self::assertGreaterThan(0, $result['dead_letter_count']);
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_respects_time_budget(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();
        $this->setupFakeSecurityLogPersist(true);

        // Enqueue many events
        for ($i = 1; $i <= 20; $i++) {
            CBT_Security_Event_Ingest::enqueue_event_payload([
                'attempt_id' => 42,
                'exam_id' => 10,
                'student_id' => 5,
                'event_type' => 'tab_hidden',
                'severity' => 'warning',
                'message' => 'Event ' . $i,
                'occurred_at' => '2026-03-24 12:00:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }

        // Flush with very tight budget — should process some but not all
        $result = CBT_Security_Event_Ingest::flush_batch(20, 0.001, 'test');

        // At least 1 should be processed, but likely not all 20
        self::assertGreaterThanOrEqual(1, $result['processed']);
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_returns_idle_when_stream_empty(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $result = CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');

        self::assertSame(0, $result['processed']);
        self::assertSame(0, $result['persisted']);
        self::assertSame(0, $result['backlog_count']);
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_skips_and_records_status_when_ingest_unavailable(): void
    {
        $this->bootstrapIngestScaffold();

        $result = CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');
        $status = get_option('cbt_security_ingest_status', []);

        self::assertSame(0, $result['processed']);
        self::assertSame(0, $result['persisted']);
        self::assertSame('skipped', (string) ($status['last_flush_status'] ?? ''));
        self::assertSame('redis_unavailable', (string) ($status['last_flush_result'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_moves_invalid_json_payload_to_dead_letter(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $redis = $this->getRedis();
        $redis->xAdd('cbt_security_ingest:events', '*', [
            'ingest_id' => 'invalid-json-1',
            'attempt_id' => '42',
            'exam_id' => '10',
            'student_id' => '5',
            'event_type' => 'tab_hidden',
            'occurred_at' => '2026-03-24 12:00:00',
            'payload_json' => '{not valid json',
        ]);

        $result = CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');

        self::assertSame(1, $result['processed']);
        self::assertSame(0, $result['persisted']);
        self::assertSame(1, $result['dead_lettered']);
        self::assertSame(0, $result['backlog_count']);
        self::assertGreaterThanOrEqual(1, $result['dead_letter_count']);
    }

    #[RunInSeparateProcess]
    public function test_invalid_json_dead_letter_keeps_reason_and_event_fields(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $redis = $this->getRedis();
        $redis->xAdd('cbt_security_ingest:events', '2000-0', [
            'ingest_id' => 'invalid-json-detail',
            'attempt_id' => '84',
            'exam_id' => '12',
            'student_id' => '6',
            'event_type' => 'clipboard_blocked',
            'occurred_at' => '2026-03-24 12:00:00',
            'payload_json' => '{"broken"',
        ]);

        CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');

        $deadEntries = $redis->xRange('cbt_security_ingest:dead', '-', '+', 10);
        self::assertCount(1, $deadEntries);
        $dead = reset($deadEntries);
        self::assertIsArray($dead);
        self::assertSame('2000-0', (string) ($dead['stream_id'] ?? ''));
        self::assertSame('invalid_payload_json', (string) ($dead['reason'] ?? ''));
        self::assertSame('0', (string) ($dead['retry_count'] ?? ''));
        self::assertSame('invalid-json-detail', (string) ($dead['ingest_id'] ?? ''));
        self::assertSame('clipboard_blocked', (string) ($dead['event_type'] ?? ''));
        self::assertSame('84', (string) ($dead['attempt_id'] ?? ''));
        self::assertSame('{"broken"', (string) ($dead['payload_json'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_reports_lock_busy_without_draining_stream(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        CBT_Security_Event_Ingest::enqueue_event_payload([
            'attempt_id' => 42,
            'exam_id' => 10,
            'student_id' => 5,
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'Queued while lock busy',
            'occurred_at' => '2026-03-24 12:00:00',
        ]);
        self::assertTrue(CBT_Cache::acquire_lock('security_event_ingest_flush', 20, ['source' => 'test-lock']));

        $result = CBT_Security_Event_Ingest::flush_batch(10, 5.0, 'test');

        self::assertSame(0, $result['processed']);
        self::assertSame(0, $result['persisted']);
        self::assertSame(1, $result['backlog_count']);
        self::assertSame(1, $this->getStreamLength());

        CBT_Cache::release_lock('security_event_ingest_flush');
    }

    #[RunInSeparateProcess]
    public function test_micro_drain_skips_when_backlog_small(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $result = CBT_Security_Event_Ingest::maybe_micro_drain();

        self::assertSame(0, $result['drained']);
        self::assertSame(1, $result['skipped']);
        self::assertSame('backlog_small', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_micro_drain_skips_when_flush_lock_busy(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();
        $this->setupFakeSecurityLogPersist(true);
        $this->enqueueSecurityEvents(51);
        self::assertTrue(CBT_Cache::acquire_lock('security_event_ingest_flush', 20, ['source' => 'test-lock']));

        $result = CBT_Security_Event_Ingest::maybe_micro_drain();

        self::assertSame(0, $result['drained']);
        self::assertSame(1, $result['skipped']);
        self::assertSame('lock_busy', $result['reason']);
        self::assertSame(51, $this->getStreamLength());

        CBT_Cache::release_lock('security_event_ingest_flush');
    }

    #[RunInSeparateProcess]
    public function test_micro_drain_flushes_first_chunk_when_backlog_is_large(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();
        $this->setupFakeSecurityLogPersist(true);
        $this->enqueueSecurityEvents(51);

        $result = CBT_Security_Event_Ingest::maybe_micro_drain();

        self::assertSame(50, $result['drained']);
        self::assertSame(0, $result['skipped']);
        self::assertSame('', $result['reason']);
        self::assertSame(1, $this->getStreamLength());
    }

    #[RunInSeparateProcess]
    public function test_micro_drain_reports_redis_unavailable_when_feature_disabled(): void
    {
        $this->bootstrapIngestScaffold();

        $result = CBT_Security_Event_Ingest::maybe_micro_drain();

        self::assertSame(0, $result['drained']);
        self::assertSame(1, $result['skipped']);
        self::assertSame('redis_unavailable', $result['reason']);
    }

    #[RunInSeparateProcess]
    public function test_get_status_snapshot_returns_valid_structure(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $status = CBT_Security_Event_Ingest::get_status_snapshot();

        self::assertArrayHasKey('feature_enabled', $status);
        self::assertArrayHasKey('available', $status);
        self::assertArrayHasKey('ingest_mode', $status);
        self::assertArrayHasKey('backlog_count', $status);
        self::assertArrayHasKey('dead_letter_count', $status);
        self::assertSame(1, $status['feature_enabled']);
    }

    #[RunInSeparateProcess]
    public function test_status_snapshot_reports_oldest_pending_age(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        $payload = [
            'attempt_id' => 42,
            'exam_id' => 10,
            'student_id' => 5,
            'ingest_id' => 'oldest-pending-1',
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'Oldest pending',
            'occurred_at' => '2026-03-24 12:00:00',
        ];
        $encoded = wp_json_encode($payload);
        self::assertIsString($encoded);

        $redis = $this->getRedis();
        $redis->xAdd('cbt_security_ingest:events', '1000-0', [
            'ingest_id' => 'oldest-pending-1',
            'attempt_id' => '42',
            'exam_id' => '10',
            'student_id' => '5',
            'event_type' => 'tab_hidden',
            'occurred_at' => '2026-03-24 12:00:00',
            'payload_json' => $encoded,
        ]);

        $status = CBT_Security_Event_Ingest::get_status_snapshot();

        self::assertSame(1, $status['backlog_count']);
        self::assertGreaterThan(0, $status['oldest_pending_age_seconds']);
    }

    #[RunInSeparateProcess]
    public function test_enqueue_event_records_failed_status_when_stream_write_returns_empty_id(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();
        $GLOBALS['cbt_test_redis_fail_stream_keys'] = ['cbt_security_ingest:events'];

        $result = CBT_Security_Event_Ingest::enqueue_event_payload([
            'attempt_id' => 42,
            'exam_id' => 10,
            'student_id' => 5,
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'Stream write failure',
            'occurred_at' => '2026-03-24 12:00:00',
        ]);
        $status = get_option('cbt_security_ingest_status', []);

        self::assertFalse($result);
        self::assertSame('failed', (string) ($status['last_enqueue_status'] ?? ''));
        self::assertSame('Redis stream enqueue returned empty stream id.', (string) ($status['last_enqueue_error'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_is_feature_enabled_reads_from_option(): void
    {
        $this->bootstrapIngestScaffold();

        self::assertFalse(CBT_Security_Event_Ingest::is_feature_enabled());

        $this->enableFeature();

        self::assertTrue(CBT_Security_Event_Ingest::is_feature_enabled());
    }

    #[RunInSeparateProcess]
    public function test_enqueue_generates_unique_ingest_id(): void
    {
        $this->bootstrapIngestScaffold();
        $this->enableFeature();

        CBT_Security_Event_Ingest::enqueue_event_payload([
            'attempt_id' => 1,
            'exam_id' => 1,
            'student_id' => 1,
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'First',
            'occurred_at' => '2026-03-24 12:00:00',
        ]);
        CBT_Security_Event_Ingest::enqueue_event_payload([
            'attempt_id' => 1,
            'exam_id' => 1,
            'student_id' => 1,
            'event_type' => 'tab_hidden',
            'severity' => 'warning',
            'message' => 'Second',
            'occurred_at' => '2026-03-24 12:00:01',
        ]);

        $redis = $this->getRedis();
        $entries = $redis->xRange('cbt_security_ingest:events', '-', '+', 10);
        $ingest_ids = [];
        foreach ($entries as $fields) {
            $ingest_ids[] = (string) ($fields['ingest_id'] ?? '');
        }

        self::assertCount(2, $ingest_ids);
        self::assertNotSame($ingest_ids[0], $ingest_ids[1]);
        self::assertNotEmpty($ingest_ids[0]);
        self::assertNotEmpty($ingest_ids[1]);
    }

    private function bootstrapIngestScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-event-ingest.php';

        // Reset Redis connection
        $this->resetRedisConnection(CBT_Security_Live_Counters::class, ['live_redis', 'live_redis_connection_attempted', 'live_redis_last_connection_error']);
    }

    private function enableFeature(): void
    {
        update_option('cbt_setup_security', [
            'security_redis_first_ingest' => 1,
            'log_security_events' => 1,
        ]);
    }

    private function setupFakeSecurityLogPersist(bool $success): void
    {
        if (!class_exists('CBT_Security_Log')) {
            $successStr = $success ? 'true' : 'false';
            eval("class CBT_Security_Log { public static function persist_ingested_event_payload(array \$p, string \$id = ''): bool { return {$successStr}; } }");
        }
    }

    private function getStreamLength(): int
    {
        $redis = $this->getRedis();
        return $redis->xLen('cbt_security_ingest:events');
    }

    private function enqueueSecurityEvents(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            CBT_Security_Event_Ingest::enqueue_event_payload([
                'attempt_id' => 42,
                'exam_id' => 10,
                'student_id' => 5,
                'event_type' => 'tab_hidden',
                'severity' => 'warning',
                'message' => 'Event ' . $i,
                'occurred_at' => '2026-03-24 12:00:' . str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT),
            ]);
        }
    }

    private function getRedis(): Redis
    {
        $reflection = new ReflectionClass(CBT_Security_Live_Counters::class);
        $prop = $reflection->getProperty('live_redis');
        $prop->setAccessible(true);
        $redis = $prop->getValue(null);
        if (!$redis instanceof Redis) {
            // Force connection
            CBT_Security_Live_Counters::is_available();
            $redis = $prop->getValue(null);
        }
        return $redis;
    }

    private function resetRedisConnection(string $class, array $properties): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($properties as $prop) {
            if (!$reflection->hasProperty($prop)) {
                continue;
            }
            $property = $reflection->getProperty($prop);
            $property->setAccessible(true);
            if (str_contains($prop, 'attempted')) {
                $property->setValue(null, true);
            } elseif (str_contains($prop, 'error')) {
                $property->setValue(null, '');
            } else {
                $property->setValue(null, new CBT_Test_Redis_Client());
            }
        }
    }
}
