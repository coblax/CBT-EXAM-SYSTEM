<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-ui-state.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionMethod;

final class UiStateAttemptNormalizationTest extends TestCase
{
    private function invokeNormalizeAttemptState(int $attemptId, array $payload, array $questionIds): array
    {
        $method = new ReflectionMethod(\CBT_UI_State::class, 'normalize_attempt_state');
        $method->setAccessible(true);

        /** @var array<string,mixed> $normalized */
        $normalized = $method->invoke(null, $attemptId, $payload, $questionIds);

        return $normalized;
    }

    public function test_normalize_attempt_state_rejects_question_ids_outside_attempt_order_and_clamps_index(): void
    {
        $normalized = $this->invokeNormalizeAttemptState(55, [
            'current_index' => 9,
            'doubtful_question_ids' => [101, 999, 102, 101],
        ], [101, 102, 103]);

        self::assertSame([
            'attempt_id' => 55,
            'current_index' => 2,
            'doubtful_question_ids' => [101, 102],
        ], $normalized);
    }

    public function test_normalize_attempt_state_supports_legacy_doubtful_lookup_shape(): void
    {
        $normalized = $this->invokeNormalizeAttemptState(88, [
            'current_index' => -3,
            'doubtful' => [
                '999' => true,
                '102' => 'true',
                '101' => 1,
                '103' => false,
            ],
        ], [101, 102]);

        self::assertSame([
            'attempt_id' => 88,
            'current_index' => 0,
            'doubtful_question_ids' => [102, 101],
        ], $normalized);
    }
}
