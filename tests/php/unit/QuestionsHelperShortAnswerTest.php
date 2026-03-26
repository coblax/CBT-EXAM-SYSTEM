<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';

use CbtExamSystem\Tests\TestCase;

final class QuestionsHelperShortAnswerTest extends TestCase
{
    public function test_normalize_short_answer_values_trims_values_and_preserves_order(): void
    {
        $values = \CBT_Admin_Questions_Helper::normalize_short_answer_values("  Satu  \nDua\nSatu ");

        self::assertSame(['Satu', 'Dua', 'Satu'], $values);
    }

    public function test_normalize_short_answer_values_limits_to_eight_items(): void
    {
        $values = \CBT_Admin_Questions_Helper::normalize_short_answer_values(
            'A||B||C||D||E||F||G||H||I||J'
        );

        self::assertCount(8, $values);
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], $values);
    }
}
