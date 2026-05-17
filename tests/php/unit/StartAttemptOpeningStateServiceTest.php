<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class StartAttemptOpeningStateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-cache.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-start-attempt-opening-state-service.php';
    }

    public function test_write_state_returns_null_for_invalid_user(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::write_state(0, 1, 'resume_lookup', 'resume_index_miss'));
    }

    public function test_write_state_returns_null_for_invalid_exam(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::write_state(1, 0, 'resume_lookup', 'resume_index_miss'));
    }

    public function test_write_state_returns_null_for_invalid_state(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::write_state(1, 1, 'invalid_state', 'resume_index_miss'));
    }

    public function test_write_state_returns_null_for_invalid_reason(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::write_state(1, 1, 'resume_lookup', 'invalid_reason'));
    }

    public function test_write_state_stores_and_returns_valid_record(): void
    {
        $record = \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'resume_lookup', 'resume_index_miss');
        $this->assertIsArray($record);
        $this->assertSame('resume_lookup', $record['opening_state']);
        $this->assertSame('resume_index_miss', $record['opening_reason']);
    }

    public function test_get_state_returns_stored_record(): void
    {
        \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'queue_waiting', 'queue_admission_wait');
        $record = \CBT_Start_Attempt_Opening_State_Service::get_state(10, 20);
        $this->assertIsArray($record);
        $this->assertSame('queue_waiting', $record['opening_state']);
    }

    public function test_get_state_returns_null_for_invalid_input(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::get_state(0, 1));
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::get_state(1, 0));
    }

    public function test_get_state_returns_null_when_no_record(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::get_state(999, 999));
    }

    public function test_clear_removes_stored_state(): void
    {
        \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'ready', 'attempt_ready');
        $this->assertIsArray(\CBT_Start_Attempt_Opening_State_Service::get_state(10, 20));

        \CBT_Start_Attempt_Opening_State_Service::clear(10, 20);
        $this->assertNull(\CBT_Start_Attempt_Opening_State_Service::get_state(10, 20));
    }

    public function test_clear_handles_invalid_input_gracefully(): void
    {
        \CBT_Start_Attempt_Opening_State_Service::clear(0, 1);
        \CBT_Start_Attempt_Opening_State_Service::clear(1, 0);
        $this->assertTrue(true);
    }

    public function test_write_state_preserves_context_fields(): void
    {
        $record = \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'attempt_created', 'attempt_ready', [
            'attempt_id' => 42,
            'queue_ticket' => 'abc-123',
            'resume_source' => 'index',
        ]);
        $this->assertSame(42, $record['attempt_id']);
        $this->assertSame('abc-123', $record['queue_ticket']);
        $this->assertSame('index', $record['resume_source']);
    }

    public function test_write_state_updates_existing_record(): void
    {
        \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'resume_lookup', 'resume_index_miss');
        $updated = \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'ready', 'attempt_ready', [
            'attempt_id' => 55,
        ]);
        $this->assertSame('ready', $updated['opening_state']);
        $this->assertSame(55, $updated['attempt_id']);
    }

    public function test_build_pending_context_returns_fallback_when_no_record(): void
    {
        $context = \CBT_Start_Attempt_Opening_State_Service::build_pending_context(999, 999, [
            'retry_after_ms' => 3000,
        ]);
        $this->assertIsArray($context);
        $this->assertArrayHasKey('opening_state', $context);
        $this->assertArrayHasKey('retry_after_ms', $context);
        $this->assertSame(3000, $context['retry_after_ms']);
    }

    public function test_build_pending_context_returns_cached_state_when_pending(): void
    {
        \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'queue_waiting', 'queue_admission_wait', [
            'retry_after_ms' => 5000,
        ]);

        $context = \CBT_Start_Attempt_Opening_State_Service::build_pending_context(10, 20);
        $this->assertSame('queue_waiting', $context['opening_state']);
        $this->assertSame(5000, $context['retry_after_ms']);
    }

    public function test_normalized_record_includes_wait_age_seconds(): void
    {
        $record = \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, 'resume_lookup', 'resume_index_miss');
        $this->assertArrayHasKey('wait_age_seconds', $record);
        $this->assertGreaterThanOrEqual(0, $record['wait_age_seconds']);
    }

    public function test_all_allowed_states_are_accepted(): void
    {
        $states = [
            'resume_lookup' => 'resume_index_miss',
            'queue_waiting' => 'queue_admission_wait',
            'attempt_creating' => 'attempt_insert_in_progress',
            'attempt_created' => 'attempt_ready',
            'bootstrap_session' => 'session_snapshot_pending',
            'bootstrap_questions' => 'question_window_pending',
            'attempt_finalizing' => 'attempt_finalizing',
            'ready' => 'attempt_ready',
            'completed' => 'attempt_completed',
            'terminal_error' => 'forbidden',
        ];

        foreach ($states as $state => $reason) {
            $record = \CBT_Start_Attempt_Opening_State_Service::write_state(10, 20, $state, $reason);
            $this->assertIsArray($record, "State '{$state}' should be accepted");
            $this->assertSame($state, $record['opening_state']);
        }
    }
}
