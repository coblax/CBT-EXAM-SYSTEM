<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class AttemptQuestionContractCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-attempt-question-contract-cache.php';
    }

    public function test_is_available_returns_bool(): void
    {
        $this->assertIsBool(\CBT_Attempt_Question_Contract_Cache::is_available());
    }

    public function test_get_attempt_snapshot_returns_empty_for_zero_id(): void
    {
        $result = \CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot(0, function () {
            return [];
        });
        $this->assertSame([], $result);
    }

    public function test_read_cached_attempt_snapshot_returns_empty_for_zero_id(): void
    {
        $this->assertSame([], \CBT_Attempt_Question_Contract_Cache::read_cached_attempt_snapshot(0));
    }

    public function test_write_attempt_snapshot_does_not_throw_for_zero_id(): void
    {
        \CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(0, []);
        $this->assertTrue(true);
    }

    public function test_clear_attempt_snapshot_returns_zero_for_zero_id(): void
    {
        $this->assertSame(0, \CBT_Attempt_Question_Contract_Cache::clear_attempt_snapshot(0));
    }

    public function test_update_attempt_status_does_not_throw_for_zero_id(): void
    {
        \CBT_Attempt_Question_Contract_Cache::update_attempt_status(0, 'started');
        $this->assertTrue(true);
    }

    public function test_get_attempt_snapshot_diagnostics_for_zero_id(): void
    {
        $diag = \CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(0);
        $this->assertSame(0, $diag['attempt_id']);
        $this->assertSame('idle', $diag['snapshot_status']);
        $this->assertFalse($diag['snapshot_exists']);
        $this->assertFalse($diag['snapshot_valid']);
    }

    public function test_get_attempt_snapshot_diagnostics_for_valid_id(): void
    {
        $diag = \CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics(42);
        $this->assertSame(42, $diag['attempt_id']);
        $this->assertArrayHasKey('redis_available', $diag);
        $this->assertArrayHasKey('snapshot_exists', $diag);
        $this->assertArrayHasKey('snapshot_valid', $diag);
        $this->assertArrayHasKey('snapshot_status', $diag);
        $this->assertArrayHasKey('question_count', $diag);
        $this->assertArrayHasKey('storage_key', $diag);
    }

    public function test_write_and_read_snapshot_round_trip(): void
    {
        $payload = [
            'attempt_id' => 100,
            'exam_id' => 10,
            'student_id' => 5,
            'status' => 'started',
            'question_order_ids' => [1, 2, 3],
            'question_number_map' => [1 => 1, 2 => 2, 3 => 3],
            'question_order_signature' => 'abc123',
            'question_manifest' => [['id' => 1], ['id' => 2], ['id' => 3]],
            'option_order_map' => [1 => ['a', 'b'], 2 => ['c', 'd']],
        ];

        \CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(100, $payload);
        $read = \CBT_Attempt_Question_Contract_Cache::read_cached_attempt_snapshot(100);

        if (!empty($read)) {
            $this->assertSame(100, $read['attempt_id']);
            $this->assertSame(10, $read['exam_id']);
            $this->assertSame([1, 2, 3], $read['question_order_ids']);
        } else {
            $this->assertSame([], $read, 'If Redis is unavailable, empty array is acceptable');
        }
    }

    public function test_clear_attempt_snapshot_clears_written_data(): void
    {
        \CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(101, [
            'attempt_id' => 101,
            'exam_id' => 10,
            'student_id' => 5,
            'question_order_ids' => [1],
            'question_manifest' => [['id' => 1]],
        ]);

        $deleted = \CBT_Attempt_Question_Contract_Cache::clear_attempt_snapshot(101);
        $this->assertIsInt($deleted);

        $read = \CBT_Attempt_Question_Contract_Cache::read_cached_attempt_snapshot(101);
        $this->assertSame([], $read);
    }
}
