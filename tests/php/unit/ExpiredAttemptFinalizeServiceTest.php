<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ExpiredAttemptFinalizeServiceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_derive_attempt_state_pending_when_expired(): void
    {
        $this->bootstrapFinalizeScaffold();

        $attempt = [
            'id' => 1,
            'exam_id' => 10,
            'student_id' => 5,
            'status' => 'in_progress',
            'started_at' => '2026-03-24 08:00:00',
            'extra_time_minutes' => 0,
        ];

        // Exam duration 60 min, started 4 hours ago → expired
        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-03-24 12:00:00');

        $state = CBT_Expired_Attempt_Finalize_Service::derive_attempt_state($attempt, 60);

        self::assertTrue($state['finalize_pending']);
        self::assertSame(0, $state['remaining_seconds']);
    }

    #[RunInSeparateProcess]
    public function test_derive_attempt_state_not_pending_when_time_remaining(): void
    {
        $this->bootstrapFinalizeScaffold();

        // Use wp_timezone (Asia/Jakarta) for consistency with the service
        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        $started = $now->modify('-10 minutes');

        $attempt = [
            'id' => 1,
            'exam_id' => 10,
            'student_id' => 5,
            'status' => 'in_progress',
            'started_at' => $started->format('Y-m-d H:i:s'),
            'extra_time_minutes' => 0,
        ];

        $state = CBT_Expired_Attempt_Finalize_Service::derive_attempt_state($attempt, 60);

        self::assertFalse($state['finalize_pending']);
        self::assertGreaterThan(0, $state['remaining_seconds']);
    }

    #[RunInSeparateProcess]
    public function test_derive_attempt_state_respects_extra_time(): void
    {
        $this->bootstrapFinalizeScaffold();

        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        $started = $now->modify('-80 minutes');

        $attempt = [
            'id' => 1,
            'exam_id' => 10,
            'student_id' => 5,
            'status' => 'in_progress',
            'started_at' => $started->format('Y-m-d H:i:s'),
            'extra_time_minutes' => 30,
        ];

        // 60 + 30 extra = 90 min total. Started 80 min ago → 10 min remaining
        $state = CBT_Expired_Attempt_Finalize_Service::derive_attempt_state($attempt, 90);

        self::assertFalse($state['finalize_pending']);
        self::assertGreaterThan(0, $state['remaining_seconds']);
    }

    #[RunInSeparateProcess]
    public function test_derive_attempt_state_completed_not_pending(): void
    {
        $this->bootstrapFinalizeScaffold();

        $attempt = [
            'id' => 1,
            'exam_id' => 10,
            'student_id' => 5,
            'status' => 'completed',
            'started_at' => '2026-03-24 08:00:00',
            'extra_time_minutes' => 0,
        ];

        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-03-24 12:00:00');

        $state = CBT_Expired_Attempt_Finalize_Service::derive_attempt_state($attempt, 60);

        self::assertFalse($state['finalize_pending']);
    }

    #[RunInSeparateProcess]
    public function test_get_current_worker_policy_returns_valid_structure(): void
    {
        $this->bootstrapFinalizeScaffold();

        $policy = CBT_Expired_Attempt_Finalize_Service::get_current_worker_policy();

        self::assertArrayHasKey('level', $policy);
        self::assertArrayHasKey('batch_size', $policy);
        self::assertArrayHasKey('time_budget_seconds', $policy);
        self::assertArrayHasKey('reschedule_delay_seconds', $policy);
        self::assertArrayHasKey('finalize_poll_after_ms', $policy);
        self::assertContains($policy['level'], ['normal', 'busy', 'critical']);
        self::assertGreaterThan(0, $policy['batch_size']);
    }

    #[RunInSeparateProcess]
    public function test_process_batch_returns_zero_when_no_candidates(): void
    {
        $this->bootstrapFinalizeScaffold();
        $this->setupFakeWpdb([]);

        $result = CBT_Expired_Attempt_Finalize_Service::process_batch(0);

        self::assertSame(0, $result['processed_count']);
        self::assertSame(0, $result['completed_count']);
        self::assertFalse($result['has_remaining']);
    }

    private function bootstrapFinalizeScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        if (!class_exists('CBT_Adaptive_Load_Service')) {
            eval('class CBT_Adaptive_Load_Service { public static function get_effective_state(bool $f = false): array { return ["effective_level" => "normal"]; } }');
        }
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';
    }

    private function setupFakeWpdb(array $attempts): void
    {
        $GLOBALS['wpdb'] = new class($attempts) {
            public string $prefix = 'wp_';
            private array $attempts;

            public function __construct(array $attempts) { $this->attempts = $attempts; }
            public function prepare(string $q, ...$a): string { return $q; }
            public function get_var($q): ?string { return null; }
            public function get_results($q, $o = null): array { return $this->attempts; }
            public function get_row($q, $o = null): ?array { return null; }
        };
    }
}
