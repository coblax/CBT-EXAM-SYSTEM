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
            ['status' => 'manual'],
            ['status' => 'unanswered'],
        ]);

        self::assertSame([
            'total_questions' => 4,
            'answered_questions' => 3,
            'correct_questions' => 1,
            'wrong_questions' => 1,
            'manual_questions' => 1,
            'unanswered_questions' => 1,
        ], $summary);
    }
}
