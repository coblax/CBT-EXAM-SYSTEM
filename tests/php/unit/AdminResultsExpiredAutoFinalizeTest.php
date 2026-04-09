<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsExpiredAutoFinalizeTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_maybe_auto_finalize_expired_attempt_rows_only_completes_expired_in_progress_attempts(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];
    public static array $failingAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        if (in_array($attempt_id, self::$failingAttemptIds, true)) {
            return new WP_Error('auto_finalize_failed', 'Simulated failure.');
        }

        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';

        CBT_REST::$finalizedAttemptIds = [];
        CBT_REST::$failingAttemptIds = [14];

        $method = new ReflectionMethod(CBT_Admin_Results_Service::class, 'maybe_auto_finalize_expired_attempt_rows');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            [
                'id' => 11,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 12,
                'status' => 'in_progress',
                'started_at' => '2999-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 13,
                'status' => 'completed',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 14,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
        ]);

        self::assertSame(4, $result['processed_count']);
        self::assertSame([11], $result['completed_attempt_ids']);
        self::assertSame([11], CBT_REST::$finalizedAttemptIds);
    }
}
