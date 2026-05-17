<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class RedisPipelineHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-redis-pipeline-helper.php';
    }

    public function test_write_setex_results_returns_empty_for_empty_operations(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        $results = \CBT_Redis_Pipeline_Helper::write_setex_results($redis, []);
        $this->assertSame([], $results);
    }

    public function test_write_setex_results_writes_single_operation(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        $results = \CBT_Redis_Pipeline_Helper::write_setex_results($redis, [
            ['key' => 'test:pipeline:1', 'ttl' => 300, 'value' => 'hello'],
        ]);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]);
    }

    public function test_write_setex_results_writes_multiple_operations(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        $results = \CBT_Redis_Pipeline_Helper::write_setex_results($redis, [
            ['key' => 'test:pipeline:a', 'ttl' => 60, 'value' => 'alpha'],
            ['key' => 'test:pipeline:b', 'ttl' => 120, 'value' => 'beta'],
            ['key' => 'test:pipeline:c', 'ttl' => 180, 'value' => 'gamma'],
        ]);
        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    public function test_write_setex_results_enforces_minimum_ttl(): void
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        $results = \CBT_Redis_Pipeline_Helper::write_setex_results($redis, [
            ['key' => 'test:pipeline:min', 'ttl' => 0, 'value' => 'data'],
        ]);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]);
    }

    public function test_write_setex_results_falls_back_to_direct_when_pipeline_disabled(): void
    {
        $GLOBALS['cbt_test_redis_pipeline_disabled'] = true;
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        $results = \CBT_Redis_Pipeline_Helper::write_setex_results($redis, [
            ['key' => 'test:pipeline:fallback', 'ttl' => 60, 'value' => 'direct'],
        ]);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]);
    }
}
