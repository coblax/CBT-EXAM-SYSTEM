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

    public function test_reset_dev_server_health_snapshot_handles_missing_settings_gracefully(): void
    {
        delete_option('cbt_developer_settings');

        \CBT_Admin_Developer_Service::reset_dev_server_health_snapshot('no_settings');

        $settings = \CBT_Admin_Developer_Service::get_settings();
        self::assertSame('unknown', $settings['last_health_status']);
        self::assertSame('no_settings', $settings['last_health_message']);
    }

    public function test_save_settings_persists_and_retrieves_values_correctly(): void
    {
        \CBT_Admin_Developer_Service::save_settings([
            'mode' => 'dev',
            'dev_server_url' => 'http://127.0.0.1:5173',
            'last_health_status' => 'ok',
            'last_health_message' => 'alive',
            'last_health_checked_at' => 100,
        ]);

        $settings = \CBT_Admin_Developer_Service::get_settings();
        self::assertSame('dev', $settings['mode']);
        self::assertSame('http://127.0.0.1:5173', $settings['dev_server_url']);
        self::assertSame('ok', $settings['last_health_status']);
    }

    public function test_get_settings_returns_default_structure_when_no_option_exists(): void
    {
        delete_option('cbt_developer_settings');

        $settings = \CBT_Admin_Developer_Service::get_settings();

        self::assertIsArray($settings);
        self::assertArrayHasKey('mode', $settings);
        self::assertArrayHasKey('last_health_status', $settings);
    }
}
