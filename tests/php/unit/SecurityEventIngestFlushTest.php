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
                $property->setValue(null, false);
            } elseif (str_contains($prop, 'error')) {
                $property->setValue(null, '');
            } else {
                $property->setValue(null, null);
            }
        }
    }
}
