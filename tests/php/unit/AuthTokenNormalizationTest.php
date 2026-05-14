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

    public function test_get_global_exam_token_does_not_rewrite_canonical_integer_settings_on_read(): void
    {
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_refresh_minutes'] = '15';
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_frontend_auto_apply'] = '0';
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_value'] = 'ABC123';
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_generated_at'] = (string) (time() - 60);

        $meta = \CBT_Auth::get_global_exam_token(false);

        self::assertSame(15, $meta['refresh_minutes']);
        self::assertSame(0, $meta['frontend_auto_apply']);
        self::assertSame('15', $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_refresh_minutes']);
        self::assertSame('0', $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_frontend_auto_apply']);
    }

    public function test_get_global_exam_token_canonicalizes_invalid_integer_settings_on_read(): void
    {
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_refresh_minutes'] = '7';
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_frontend_auto_apply'] = 'enabled';
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_value'] = 'ABC123';
        $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_generated_at'] = (string) (time() - 60);

        $meta = \CBT_Auth::get_global_exam_token(false);

        self::assertSame(15, $meta['refresh_minutes']);
        self::assertSame(0, $meta['frontend_auto_apply']);
        self::assertSame('15', $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_refresh_minutes']);
        self::assertSame('0', $GLOBALS['cbt_test_wp_options']['cbt_global_exam_token_frontend_auto_apply']);
    }
}
