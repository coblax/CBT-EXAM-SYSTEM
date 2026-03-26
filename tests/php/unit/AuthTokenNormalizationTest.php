<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

use CbtExamSystem\Tests\TestCase;

final class AuthTokenNormalizationTest extends TestCase
{
    public function test_normalize_exam_token_input_uppercases_and_strips_invalid_characters(): void
    {
        $normalized = \CBT_Auth::normalize_exam_token_input(' ab-c 123 io ');

        self::assertSame('ABC123', $normalized);
    }

    public function test_normalize_exam_token_input_truncates_to_expected_length(): void
    {
        $normalized = \CBT_Auth::normalize_exam_token_input('ABCDEFGH123');

        self::assertSame('ABCDEF', $normalized);
    }
}
