<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ResultPayloadHelpersTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_result_submission_summary_and_pass_meta_stay_consistent(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $summaryMethod = new ReflectionMethod('CBT_REST', 'build_result_submission_summary');
        $summaryMethod->setAccessible(true);

        $derivedSummary = $summaryMethod->invoke(null, [
            'correct_questions' => 3,
            'wrong_questions' => 2,
            'manual_questions' => 1,
            'unanswered_questions' => 4,
        ]);
        self::assertSame([
            'total_questions' => 10,
            'answered_questions' => 6,
            'pending_manual_questions' => 1,
        ], $derivedSummary);

        $clampedSummary = $summaryMethod->invoke(null, [
            'total_questions' => 5,
            'answered_questions' => 9,
            'pending_manual_questions' => 2,
        ]);
        self::assertSame([
            'total_questions' => 5,
            'answered_questions' => 5,
            'pending_manual_questions' => 2,
        ], $clampedSummary);

        $passMetaMethod = new ReflectionMethod('CBT_REST', 'build_result_pass_meta');
        $passMetaMethod->setAccessible(true);

        $passMeta = $passMetaMethod->invoke(null, 74.99, 100.0, 75.0);
        self::assertSame([
            'kkm_percentage' => 75.0,
            'passing_score' => 75.0,
            'is_passed' => 0,
            'pass_label' => 'TIDAK LULUS',
            'result_tone' => 'fail',
        ], $passMeta);

        $zeroMaxMeta = $passMetaMethod->invoke(null, 0.0, 0.0, 0.0);
        self::assertSame(1, $zeroMaxMeta['is_passed']);
        self::assertSame('LULUS', $zeroMaxMeta['pass_label']);
        self::assertSame('pass', $zeroMaxMeta['result_tone']);
    }

    #[RunInSeparateProcess]
    public function test_summarize_review_items_counts_pending_manual_and_unanswered_correctly(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $summaryMethod = new ReflectionMethod('CBT_REST', 'summarize_review_items');
        $summaryMethod->setAccessible(true);

        $summary = $summaryMethod->invoke(null, [
            ['status' => 'correct'],
            ['status' => 'wrong'],
            ['status' => 'graded'],
            ['status' => 'manual'],
            ['status' => 'unanswered'],
        ]);

        self::assertSame([
            'total_questions' => 5,
            'answered_questions' => 4,
            'correct_questions' => 1,
            'wrong_questions' => 1,
            'graded_questions' => 1,
            'manual_questions' => 1,
            'unanswered_questions' => 1,
        ], $summary);
    }

    #[RunInSeparateProcess]
    public function test_result_submission_summary_handles_all_zero_questions(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $summaryMethod = new ReflectionMethod('CBT_REST', 'build_result_submission_summary');
        $summaryMethod->setAccessible(true);

        $summary = $summaryMethod->invoke(null, [
            'correct_questions' => 0,
            'wrong_questions' => 0,
            'manual_questions' => 0,
            'unanswered_questions' => 0,
        ]);

        self::assertSame(0, $summary['total_questions']);
        self::assertSame(0, $summary['answered_questions']);
        self::assertSame(0, $summary['pending_manual_questions']);
    }

    #[RunInSeparateProcess]
    public function test_result_pass_meta_boundary_at_exact_kkm_percentage(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $passMetaMethod = new ReflectionMethod('CBT_REST', 'build_result_pass_meta');
        $passMetaMethod->setAccessible(true);

        $exactPass = $passMetaMethod->invoke(null, 75.0, 100.0, 75.0);
        self::assertSame(1, $exactPass['is_passed']);
        self::assertSame('LULUS', $exactPass['pass_label']);
        self::assertSame('pass', $exactPass['result_tone']);

        $justBelow = $passMetaMethod->invoke(null, 74.99, 100.0, 75.0);
        self::assertSame(0, $justBelow['is_passed']);
        self::assertSame('TIDAK LULUS', $justBelow['pass_label']);
    }

    #[RunInSeparateProcess]
    public function test_summarize_review_items_handles_empty_items(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $summaryMethod = new ReflectionMethod('CBT_REST', 'summarize_review_items');
        $summaryMethod->setAccessible(true);

        $summary = $summaryMethod->invoke(null, []);

        self::assertSame(0, $summary['total_questions']);
        self::assertSame(0, $summary['answered_questions']);
        self::assertSame(0, $summary['unanswered_questions']);
    }
}
