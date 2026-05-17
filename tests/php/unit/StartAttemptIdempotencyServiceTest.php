<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class StartAttemptIdempotencyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-cache.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-start-attempt-idempotency-service.php';
    }

    public function test_sanitize_key_strips_invalid_characters(): void
    {
        $this->assertSame('abc-123', \CBT_Start_Attempt_Idempotency_Service::sanitize_key('abc-123'));
        $this->assertSame('test_key', \CBT_Start_Attempt_Idempotency_Service::sanitize_key('test_key!@#'));
    }

    public function test_sanitize_key_returns_empty_for_empty_input(): void
    {
        $this->assertSame('', \CBT_Start_Attempt_Idempotency_Service::sanitize_key(''));
        $this->assertSame('', \CBT_Start_Attempt_Idempotency_Service::sanitize_key('   '));
    }

    public function test_sanitize_key_truncates_to_max_length(): void
    {
        $longKey = str_repeat('a', 200);
        $result = \CBT_Start_Attempt_Idempotency_Service::sanitize_key($longKey);
        $this->assertSame(96, strlen($result));
    }

    public function test_sanitize_key_allows_hyphens_and_underscores(): void
    {
        $this->assertSame('abc-def_ghi', \CBT_Start_Attempt_Idempotency_Service::sanitize_key('abc-def_ghi'));
    }

    public function test_begin_returns_disabled_for_invalid_inputs(): void
    {
        $result = \CBT_Start_Attempt_Idempotency_Service::begin(0, 1, 'key1');
        $this->assertSame('disabled', $result['mode']);

        $result = \CBT_Start_Attempt_Idempotency_Service::begin(1, 0, 'key1');
        $this->assertSame('disabled', $result['mode']);

        $result = \CBT_Start_Attempt_Idempotency_Service::begin(1, 1, '');
        $this->assertSame('disabled', $result['mode']);
    }

    public function test_begin_returns_claimed_on_fresh_key(): void
    {
        $result = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'fresh-key-abc');
        $this->assertSame('claimed', $result['mode']);
        $this->assertArrayHasKey('claim', $result);
        $this->assertSame(10, $result['claim']['user_id']);
        $this->assertSame(20, $result['claim']['exam_id']);
    }

    public function test_complete_stores_record_for_replay(): void
    {
        $begin = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'complete-test');
        $this->assertSame('claimed', $begin['mode']);

        $response = new \WP_REST_Response(['attempt_id' => 99, 'status' => 'started'], 200);
        \CBT_Start_Attempt_Idempotency_Service::complete($begin['claim'], 'started', $response);

        $record = \CBT_Start_Attempt_Idempotency_Service::get_record(10, 20, 'complete-test');
        $this->assertIsArray($record);
        $this->assertSame('started', $record['state']);
        $this->assertSame(99, $record['attempt_id']);
    }

    public function test_begin_replays_completed_record(): void
    {
        $begin = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'replay-test');
        $this->assertSame('claimed', $begin['mode']);

        $response = new \WP_REST_Response(['attempt_id' => 55], 200);
        \CBT_Start_Attempt_Idempotency_Service::complete($begin['claim'], 'started', $response);

        $replay = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'replay-test');
        $this->assertSame('replay', $replay['mode']);
        $this->assertArrayHasKey('response', $replay);
    }

    public function test_abandon_clears_record_and_releases_lock(): void
    {
        $begin = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'abandon-test');
        $this->assertSame('claimed', $begin['mode']);

        \CBT_Start_Attempt_Idempotency_Service::abandon($begin['claim']);

        $record = \CBT_Start_Attempt_Idempotency_Service::get_record(10, 20, 'abandon-test');
        $this->assertNull($record);
    }

    public function test_complete_with_wp_error_stores_error_response(): void
    {
        $begin = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'error-test');
        $this->assertSame('claimed', $begin['mode']);

        $error = new \WP_Error('exam_ended', 'Exam has ended.', ['status' => 403]);
        \CBT_Start_Attempt_Idempotency_Service::complete($begin['claim'], 'terminal_error', $error);

        $record = \CBT_Start_Attempt_Idempotency_Service::get_record(10, 20, 'error-test');
        $this->assertIsArray($record);
        $this->assertSame('terminal_error', $record['state']);
        $this->assertSame('error', $record['response_kind']);
    }

    public function test_build_replay_response_returns_null_for_null_record(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Idempotency_Service::build_replay_response(null));
    }

    public function test_build_replay_response_returns_wp_error_for_error_kind(): void
    {
        $record = [
            'response_kind' => 'error',
            'response_data' => [
                'code' => 'exam_ended',
                'message' => 'Exam has ended.',
                'data' => ['status' => 403],
            ],
        ];

        $response = \CBT_Start_Attempt_Idempotency_Service::build_replay_response($record);
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame('exam_ended', $response->get_error_code());
    }

    public function test_build_replay_response_returns_rest_response_for_non_200(): void
    {
        $record = [
            'response_kind' => 'payload',
            'response_status' => 202,
            'response_data' => ['queue_ticket' => 'abc'],
        ];

        $response = \CBT_Start_Attempt_Idempotency_Service::build_replay_response($record);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(202, $response->get_status());
    }

    public function test_get_record_returns_null_for_invalid_parameters(): void
    {
        $this->assertNull(\CBT_Start_Attempt_Idempotency_Service::get_record(0, 1, 'key'));
        $this->assertNull(\CBT_Start_Attempt_Idempotency_Service::get_record(1, 0, 'key'));
        $this->assertNull(\CBT_Start_Attempt_Idempotency_Service::get_record(1, 1, ''));
    }

    public function test_complete_with_empty_state_triggers_abandon(): void
    {
        $begin = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'empty-state');
        $this->assertSame('claimed', $begin['mode']);

        $response = new \WP_REST_Response(['attempt_id' => 1], 200);
        \CBT_Start_Attempt_Idempotency_Service::complete($begin['claim'], '', $response);

        $record = \CBT_Start_Attempt_Idempotency_Service::get_record(10, 20, 'empty-state');
        $this->assertNull($record);
    }

    public function test_complete_with_processing_state_triggers_abandon(): void
    {
        $begin = \CBT_Start_Attempt_Idempotency_Service::begin(10, 20, 'processing-state');
        $this->assertSame('claimed', $begin['mode']);

        $response = new \WP_REST_Response(['attempt_id' => 1], 200);
        \CBT_Start_Attempt_Idempotency_Service::complete($begin['claim'], 'processing', $response);

        $record = \CBT_Start_Attempt_Idempotency_Service::get_record(10, 20, 'processing-state');
        $this->assertNull($record);
    }

    public function test_complete_ignores_empty_claim_keys(): void
    {
        \CBT_Start_Attempt_Idempotency_Service::complete([], 'started', ['attempt_id' => 1]);
        \CBT_Start_Attempt_Idempotency_Service::complete(['storage_key' => '', 'lock_key' => ''], 'started', ['attempt_id' => 1]);
        $this->assertTrue(true);
    }

    public function test_abandon_handles_empty_claim_gracefully(): void
    {
        \CBT_Start_Attempt_Idempotency_Service::abandon([]);
        \CBT_Start_Attempt_Idempotency_Service::abandon(['storage_key' => '', 'lock_key' => '']);
        $this->assertTrue(true);
    }
}
