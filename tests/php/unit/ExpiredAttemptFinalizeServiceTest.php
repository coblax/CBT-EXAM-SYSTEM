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
    public function test_get_current_worker_policy_follows_adaptive_load_level(): void
    {
        $GLOBALS['cbt_test_adaptive_level'] = 'critical';
        $this->bootstrapFinalizeScaffold();

        $critical = CBT_Expired_Attempt_Finalize_Service::get_current_worker_policy();
        self::assertSame('critical', $critical['level']);
        self::assertSame(2, $critical['batch_size']);
        self::assertSame(10000, $critical['finalize_poll_after_ms']);

        $GLOBALS['cbt_test_adaptive_level'] = 'busy';
        $busy = CBT_Expired_Attempt_Finalize_Service::get_current_worker_policy();
        self::assertSame('busy', $busy['level']);
        self::assertSame(5, $busy['batch_size']);
        self::assertSame(5000, $busy['finalize_poll_after_ms']);
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

    #[RunInSeparateProcess]
    public function test_process_batch_finalizes_expired_candidate_and_releases_locks(): void
    {
        $this->bootstrapFinalizeScaffold();
        $attempt = $this->expiredAttemptRow(101);
        $this->setupFakeWpdb([$attempt]);

        $result = CBT_Expired_Attempt_Finalize_Service::process_batch(0, [
            'level' => 'normal',
            'batch_size' => 10,
            'time_budget_seconds' => 3,
            'reschedule_delay_seconds' => 5,
        ]);

        self::assertSame(1, $result['processed_count']);
        self::assertSame(1, $result['completed_count']);
        self::assertFalse($result['has_remaining']);
        self::assertSame([101], CBT_REST::$finalizedAttemptIds);
        self::assertTrue(CBT_Cache::acquire_lock('results_expired_auto_finalize:0', 30));
        CBT_Cache::release_lock('results_expired_auto_finalize:0');
        self::assertTrue(CBT_Cache::acquire_lock('finish_attempt:101', 30));
        CBT_Cache::release_lock('finish_attempt:101');
    }

    #[RunInSeparateProcess]
    public function test_process_batch_skips_attempt_that_completed_before_reload(): void
    {
        $this->bootstrapFinalizeScaffold();
        $candidate = $this->expiredAttemptRow(102);
        $fresh = array_merge($candidate, ['status' => 'completed']);
        $this->setupFakeWpdb([$candidate], [$fresh]);

        $result = CBT_Expired_Attempt_Finalize_Service::process_batch(0, [
            'level' => 'normal',
            'batch_size' => 10,
            'time_budget_seconds' => 3,
            'reschedule_delay_seconds' => 5,
        ]);

        self::assertSame(1, $result['processed_count']);
        self::assertSame(0, $result['completed_count']);
        self::assertSame([], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_maybe_auto_finalize_attempt_rows_respects_time_budget_after_first_item(): void
    {
        $this->bootstrapFinalizeScaffold();
        $GLOBALS['cbt_test_finalize_sleep_us'] = 2000;
        $first = $this->expiredAttemptRow(201);
        $second = $this->expiredAttemptRow(202);
        $this->setupFakeWpdb([$first, $second]);

        $result = CBT_Expired_Attempt_Finalize_Service::maybe_auto_finalize_attempt_rows([$first, $second], [
            'time_budget_seconds' => 0.001,
        ]);

        self::assertSame(1, $result['processed_count']);
        self::assertSame([201], $result['completed_attempt_ids']);
        self::assertSame([201], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_maybe_schedule_for_attempt_throttles_repeated_expired_signal(): void
    {
        $this->bootstrapFinalizeScaffold();
        $this->setupFakeWpdb([]);
        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-03-24 12:00:00');

        $attempt = $this->expiredAttemptRow(301);

        $first = CBT_Expired_Attempt_Finalize_Service::maybe_schedule_for_attempt($attempt, 60, 9);
        $second = CBT_Expired_Attempt_Finalize_Service::maybe_schedule_for_attempt($attempt, 60, 9);

        self::assertTrue($first['finalize_pending']);
        self::assertFalse($first['throttled']);
        self::assertFalse($first['scheduled']);
        self::assertSame(9, $first['created_by_user_id']);
        self::assertTrue($second['finalize_pending']);
        self::assertTrue($second['throttled']);
        self::assertFalse($second['scheduled']);
    }

    private function bootstrapFinalizeScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        if (!class_exists('CBT_Adaptive_Load_Service')) {
            eval('class CBT_Adaptive_Load_Service { public static function get_effective_state(bool $f = false): array { return ["effective_level" => (string) ($GLOBALS["cbt_test_adaptive_level"] ?? "normal")]; } }');
        }
        if (!class_exists('CBT_REST')) {
            eval('class CBT_REST { public static array $finalizedAttemptIds = []; public static function finalize_attempt_completion(int $attemptId, $request = null, array $options = []) { if (!empty($GLOBALS["cbt_test_finalize_sleep_us"])) { usleep((int) $GLOBALS["cbt_test_finalize_sleep_us"]); } self::$finalizedAttemptIds[] = $attemptId; return ["attempt_id" => $attemptId, "completed" => true]; } }');
        }
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';
    }

    private function setupFakeWpdb(array $attempts, ?array $reloadAttempts = null): void
    {
        $GLOBALS['wpdb'] = new class($attempts, $reloadAttempts) {
            public string $prefix = 'wp_';
            private array $attempts;
            private array $reloadAttempts;
            private int $resultsCallCount = 0;

            public function __construct(array $attempts, ?array $reloadAttempts)
            {
                $this->attempts = $attempts;
                $this->reloadAttempts = $reloadAttempts ?? $attempts;
            }

            public function prepare(string $q, ...$a): string
            {
                if (count($a) === 1 && is_array($a[0])) {
                    $a = $a[0];
                }

                foreach ($a as $arg) {
                    $replacement = is_numeric($arg) ? (string) $arg : "'" . str_replace("'", "''", (string) $arg) . "'";
                    $q = preg_replace('/%d|%s/', $replacement, $q, 1) ?? $q;
                }

                return $q;
            }

            public function get_var($q): ?string { return null; }

            public function get_results($q, $o = null): array
            {
                $this->resultsCallCount++;
                return $this->resultsCallCount === 1 ? $this->attempts : [];
            }

            public function get_row($q, $o = null): ?array
            {
                if (!preg_match('/WHERE a\\.id = (\\d+)/', (string) $q, $matches)) {
                    return null;
                }

                $attemptId = (int) $matches[1];
                foreach ($this->reloadAttempts as $attempt) {
                    if ((int) ($attempt['id'] ?? 0) === $attemptId) {
                        return $attempt;
                    }
                }

                return null;
            }
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function expiredAttemptRow(int $attemptId): array
    {
        return [
            'id' => $attemptId,
            'exam_id' => 10,
            'student_id' => 5,
            'status' => 'in_progress',
            'started_at' => '2026-03-24 08:00:00',
            'deadline_at' => '2026-03-24 09:00:00',
            'extra_time_minutes' => 0,
            'exam_duration_minutes' => 60,
        ];
    }
}
