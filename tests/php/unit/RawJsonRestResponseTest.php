<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class RawJsonRestResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-raw-json-rest-response.php';
    }

    public function test_get_raw_json_returns_stored_json(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"foo":"bar"}', null, 200);
        $this->assertSame('{"foo":"bar"}', $response->get_raw_json());
    }

    public function test_get_data_returns_explicit_data_when_set(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"a":1}', ['explicit' => true], 200);
        $this->assertSame(['explicit' => true], $response->get_data());
    }

    public function test_get_data_decodes_raw_json_when_data_is_null(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"decoded":true}', null, 200);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertTrue($data['decoded']);
    }

    public function test_get_data_returns_null_for_invalid_json(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('not valid json', null, 200);
        $this->assertNull($response->get_data());
    }

    public function test_get_data_returns_null_for_empty_raw_json(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('', null, 200);
        $this->assertNull($response->get_data());
    }

    public function test_offset_exists_checks_decoded_data(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"key":"val"}', null, 200);
        $this->assertTrue($response->offsetExists('key'));
        $this->assertFalse($response->offsetExists('missing'));
    }

    public function test_offset_get_returns_value(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"name":"test"}', null, 200);
        $this->assertSame('test', $response->offsetGet('name'));
        $this->assertNull($response->offsetGet('missing'));
    }

    public function test_offset_set_modifies_data(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"a":1}', null, 200);
        $response->offsetSet('b', 2);
        $this->assertSame(2, $response->offsetGet('b'));
    }

    public function test_offset_set_with_null_key_appends(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('[]', [], 200);
        $response->offsetSet(null, 'appended');
        $data = $response->get_data();
        $this->assertContains('appended', $data);
    }

    public function test_offset_unset_removes_key(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{"remove":"me"}', null, 200);
        $response->offsetUnset('remove');
        $this->assertFalse($response->offsetExists('remove'));
    }

    public function test_from_payload_creates_instance(): void
    {
        $instance = \CBT_Raw_JSON_REST_Response::from_payload(['test' => 1], 201, ['X-Custom' => 'val']);
        $this->assertInstanceOf(\CBT_Raw_JSON_REST_Response::class, $instance);
        $this->assertSame(201, $instance->get_status());
        $this->assertSame(['test' => 1], $instance->get_data());
    }

    public function test_from_payload_returns_null_for_unencodable_data(): void
    {
        $resource = fopen('php://memory', 'r');
        $result = \CBT_Raw_JSON_REST_Response::from_payload($resource);
        fclose($resource);
        $this->assertNull($result);
    }

    public function test_get_status_returns_status_code(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{}', null, 404);
        $this->assertSame(404, $response->get_status());
    }

    public function test_headers_are_preserved(): void
    {
        $response = new \CBT_Raw_JSON_REST_Response('{}', null, 200, ['X-CBT' => 'snapshot']);
        $this->assertSame(['X-CBT' => 'snapshot'], $response->get_headers());
    }
}
