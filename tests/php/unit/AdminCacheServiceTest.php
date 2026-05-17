<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class AdminCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-cache.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-cache-service.php';
    }

    public function test_cache_readiness_meta_ready(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_readiness_meta('ready');
        $this->assertSame('Ready', $meta['label']);
        $this->assertArrayHasKey('accent', $meta);
        $this->assertArrayHasKey('background', $meta);
    }

    public function test_cache_readiness_meta_partial(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_readiness_meta('partial');
        $this->assertSame('Partial', $meta['label']);
    }

    public function test_cache_readiness_meta_fallback(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_readiness_meta('fallback');
        $this->assertSame('Fallback', $meta['label']);
    }

    public function test_cache_readiness_meta_unknown_defaults_to_fallback(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_readiness_meta('unknown');
        $this->assertSame('Fallback', $meta['label']);
    }

    public function test_cache_probe_meta_passed(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_probe_meta('passed');
        $this->assertSame('Passed', $meta['label']);
    }

    public function test_cache_probe_meta_failed(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_probe_meta('failed');
        $this->assertSame('Failed', $meta['label']);
    }

    public function test_cache_probe_meta_skipped(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_probe_meta('skipped');
        $this->assertSame('Skipped', $meta['label']);
    }

    public function test_cache_server_probe_meta_reachable(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_server_probe_meta('reachable');
        $this->assertSame('Reachable', $meta['label']);
    }

    public function test_cache_server_probe_meta_unreachable(): void
    {
        $meta = \CBT_Admin_Cache_Service::cache_server_probe_meta('unreachable');
        $this->assertSame('Unreachable', $meta['label']);
    }

    public function test_cache_boolean_label_true(): void
    {
        $this->assertSame('Yes', \CBT_Admin_Cache_Service::cache_boolean_label(true));
    }

    public function test_cache_boolean_label_false(): void
    {
        $this->assertSame('No', \CBT_Admin_Cache_Service::cache_boolean_label(false));
    }

    public function test_cache_scalar_label_with_value(): void
    {
        $this->assertSame('test', \CBT_Admin_Cache_Service::cache_scalar_label('test'));
    }

    public function test_cache_scalar_label_empty(): void
    {
        $this->assertSame('-', \CBT_Admin_Cache_Service::cache_scalar_label(''));
    }

    public function test_cache_next_steps_returns_array(): void
    {
        $steps = \CBT_Admin_Cache_Service::cache_next_steps([]);
        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
    }

    public function test_cache_next_steps_always_has_verification_step(): void
    {
        $steps = \CBT_Admin_Cache_Service::cache_next_steps([
            'wp_cache_enabled' => true,
            'object_cache_dropin_present' => true,
            'object_cache_active' => true,
            'backend_hint' => 'redis',
            'redis_config' => ['host' => '127.0.0.1', 'port' => 6379, 'database' => '1', 'prefix' => 'wp_'],
            'server_probe' => ['status' => 'reachable'],
            'probe' => ['status' => 'passed'],
        ]);
        $lastStep = end($steps);
        $this->assertStringContainsString('Verifikasi ulang', $lastStep);
    }

    public function test_cache_readiness_summary_ready(): void
    {
        $summary = \CBT_Admin_Cache_Service::cache_readiness_summary(['readiness' => 'ready']);
        $this->assertStringContainsString('Redis object cache', $summary);
    }

    public function test_cache_readiness_summary_partial(): void
    {
        $summary = \CBT_Admin_Cache_Service::cache_readiness_summary(['readiness' => 'partial']);
        $this->assertStringContainsString('Sebagian konfigurasi', $summary);
    }

    public function test_cache_readiness_summary_fallback(): void
    {
        $summary = \CBT_Admin_Cache_Service::cache_readiness_summary(['readiness' => 'fallback']);
        $this->assertStringContainsString('transient fallback', $summary);
    }
}
