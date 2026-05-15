<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RuntimeBufferFlushIntegrityTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_buffer_entries_stores_answers_in_redis(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 1, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 0];
        $entries = [
            ['question_id' => 100, 'selected_option_ids' => '[1]', 'answer_text' => '', 'is_correct' => 1, 'score_awarded' => 2.0, 'answered_at' => '2026-03-24 10:05:00', 'clear' => 0, 'answer' => 1],
            ['question_id' => 200, 'selected_option_ids' => '[2]', 'answer_text' => '', 'is_correct' => 0, 'score_awarded' => 0.0, 'answered_at' => '2026-03-24 10:06:00', 'clear' => 0, 'answer' => 2],
        ];

        $result = CBT_Runtime::buffer_entries($attempt, 60, $entries);

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(2, $result['buffered']);
        self::assertGreaterThanOrEqual(0, $result['pending_count']);
    }

    #[RunInSeparateProcess]
    public function test_buffer_entries_returns_zero_when_redis_unavailable(): void
    {
        $GLOBALS['cbt_test_redis_should_fail_connect'] = true;
        $this->bootstrapRuntimeScaffold();

        // Force Runtime to re-attempt connection with the fail flag active
        $reflection = new ReflectionClass(CBT_Runtime::class);
        $redisProp = $reflection->getProperty('redis');
        $redisProp->setAccessible(true);
        $redisProp->setValue(null, false);
        $attemptedProp = $reflection->getProperty('redis_connection_attempted');
        $attemptedProp->setAccessible(true);
        $attemptedProp->setValue(null, true);

        $attempt = ['id' => 1, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 0];
        $entries = [
            ['question_id' => 100, 'selected_option_ids' => '[1]', 'answer_text' => '', 'is_correct' => 1, 'score_awarded' => 2.0, 'answered_at' => '2026-03-24 10:05:00', 'clear' => 0, 'answer' => 1],
        ];

        $result = CBT_Runtime::buffer_entries($attempt, 60, $entries);

        self::assertSame(0, $result['runtime_used']);
        self::assertSame(0, $result['buffered']);
    }

    #[RunInSeparateProcess]
    public function test_ensure_attempt_state_creates_meta_in_redis(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 7, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 5];

        $result = CBT_Runtime::ensure_attempt_state($attempt, 60);

        self::assertTrue($result);
        self::assertTrue(CBT_Runtime::has_attempt_state(7));
    }

    #[RunInSeparateProcess]
    public function test_has_attempt_state_returns_false_for_nonexistent(): void
    {
        $this->bootstrapRuntimeScaffold();

        self::assertFalse(CBT_Runtime::has_attempt_state(999));
    }

    #[RunInSeparateProcess]
    public function test_get_attempt_meta_returns_correct_data(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 3, 'exam_id' => 15, 'student_id' => 8, 'status' => 'in_progress', 'started_at' => '2026-03-24 09:00:00', 'extra_time_minutes' => 10];
        CBT_Runtime::ensure_attempt_state($attempt, 90);

        $meta = CBT_Runtime::get_attempt_meta(3, $found);

        self::assertTrue($found);
        self::assertSame(3, $meta['attempt_id']);
        self::assertSame(15, $meta['exam_id']);
        self::assertSame(8, $meta['student_id']);
        self::assertSame('in_progress', $meta['status']);
        self::assertSame(90, $meta['duration_minutes']);
        self::assertSame(10, $meta['extra_time_minutes']);
    }

    #[RunInSeparateProcess]
    public function test_buffer_entries_with_clear_flag(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 2, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 0];
        $entries_initial = [
            ['question_id' => 100, 'selected_option_ids' => '[1]', 'answer_text' => '', 'is_correct' => 1, 'score_awarded' => 2.0, 'answered_at' => '2026-03-24 10:05:00', 'clear' => 0, 'answer' => 1],
        ];
        CBT_Runtime::buffer_entries($attempt, 60, $entries_initial);

        $entries_clear = [
            ['question_id' => 100, 'selected_option_ids' => '', 'answer_text' => '', 'is_correct' => null, 'score_awarded' => 0.0, 'answered_at' => '2026-03-24 10:06:00', 'clear' => 1, 'answer' => null],
        ];
        $result = CBT_Runtime::buffer_entries($attempt, 60, $entries_clear);

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(1, $result['buffered']);
    }

    #[RunInSeparateProcess]
    public function test_buffer_entries_deduplicates_same_question(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 4, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 0];
        $entries = [
            ['question_id' => 100, 'selected_option_ids' => '[1]', 'answer_text' => '', 'is_correct' => 1, 'score_awarded' => 2.0, 'answered_at' => '2026-03-24 10:05:00', 'clear' => 0, 'answer' => 1],
            ['question_id' => 100, 'selected_option_ids' => '[2]', 'answer_text' => '', 'is_correct' => 0, 'score_awarded' => 0.0, 'answered_at' => '2026-03-24 10:06:00', 'clear' => 0, 'answer' => 2],
        ];

        $result = CBT_Runtime::buffer_entries($attempt, 60, $entries);

        self::assertSame(1, $result['runtime_used']);
        // Only the last entry for question 100 should be stored
        self::assertSame(1, $result['buffered']);
    }

    #[RunInSeparateProcess]
    public function test_buffer_entries_rejects_invalid_attempt_id(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 0, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 0];
        $entries = [
            ['question_id' => 100, 'selected_option_ids' => '[1]', 'answer_text' => '', 'is_correct' => 1, 'score_awarded' => 2.0, 'answered_at' => '2026-03-24 10:05:00', 'clear' => 0, 'answer' => 1],
        ];

        $result = CBT_Runtime::buffer_entries($attempt, 60, $entries);

        self::assertSame(0, $result['runtime_used']);
        self::assertSame(0, $result['buffered']);
    }

    #[RunInSeparateProcess]
    public function test_buffer_entries_skips_invalid_question_ids(): void
    {
        $this->bootstrapRuntimeScaffold();

        $attempt = ['id' => 5, 'exam_id' => 10, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'extra_time_minutes' => 0];
        $entries = [
            ['question_id' => 0, 'selected_option_ids' => '[1]', 'answer_text' => '', 'is_correct' => 1, 'score_awarded' => 2.0, 'answered_at' => '2026-03-24 10:05:00', 'clear' => 0, 'answer' => 1],
            ['question_id' => -1, 'selected_option_ids' => '[2]', 'answer_text' => '', 'is_correct' => 0, 'score_awarded' => 0.0, 'answered_at' => '2026-03-24 10:06:00', 'clear' => 0, 'answer' => 2],
        ];

        $result = CBT_Runtime::buffer_entries($attempt, 60, $entries);

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(0, $result['buffered']);
    }

    private function bootstrapRuntimeScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';

        if (!class_exists('CBT_Live_Proctoring_Presence')) {
            eval('class CBT_Live_Proctoring_Presence { public static function is_available(): bool { return false; } }');
        }
        if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
            eval('class CBT_Live_Attempt_Roster_Index { public static function is_available(): bool { return false; } }');
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
    }

    private function resetRuntimeRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);
        foreach (['redis', 'redis_connection_attempted', 'last_connection_error', 'cached_prefix'] as $prop) {
            if (!$reflection->hasProperty($prop)) {
                continue;
            }
            $property = $reflection->getProperty($prop);
            $property->setAccessible(true);
            if ($prop === 'redis_connection_attempted') {
                $property->setValue(null, false);
            } elseif ($prop === 'last_connection_error' || $prop === 'cached_prefix') {
                $property->setValue(null, $prop === 'cached_prefix' ? null : '');
            } else {
                $property->setValue(null, null);
            }
        }
    }
}
