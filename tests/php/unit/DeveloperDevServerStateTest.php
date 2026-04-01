<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class DeveloperDevServerStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-developer-service.php';
    }

    public function test_reset_dev_server_health_snapshot_clears_transient_and_persists_unknown_status(): void
    {
        \CBT_Admin_Developer_Service::save_settings([
            'mode' => 'dev',
            'dev_server_url' => 'http://127.0.0.1:5173',
            'last_health_status' => 'ok',
            'last_health_message' => 'alive',
            'last_health_checked_at' => 123,
        ]);

        set_transient('cbt_dev_server_health_' . md5('http://127.0.0.1:5173'), ['status' => 'ok'], 15);

        \CBT_Admin_Developer_Service::reset_dev_server_health_snapshot('stopped');

        $settings = \CBT_Admin_Developer_Service::get_settings();

        self::assertSame('unknown', $settings['last_health_status']);
        self::assertSame('stopped', $settings['last_health_message']);
        self::assertGreaterThan(0, $settings['last_health_checked_at']);
        self::assertFalse(get_transient('cbt_dev_server_health_' . md5('http://127.0.0.1:5173')));
    }
}
