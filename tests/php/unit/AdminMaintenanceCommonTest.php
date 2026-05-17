<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class AdminMaintenanceCommonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-branding-settings.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-common.php';
    }

    public function test_get_reset_progress_table_batch_size_returns_positive(): void
    {
        $size = \CBT_Admin_Maintenance_Common::get_reset_progress_table_batch_size();
        $this->assertGreaterThanOrEqual(1, $size);
        $this->assertLessThanOrEqual(10, $size);
    }

    public function test_get_reset_progress_user_batch_size_returns_in_range(): void
    {
        $size = \CBT_Admin_Maintenance_Common::get_reset_progress_user_batch_size();
        $this->assertGreaterThanOrEqual(20, $size);
        $this->assertLessThanOrEqual(500, $size);
    }

    public function test_get_reset_progress_max_batch_seconds_returns_in_range(): void
    {
        $seconds = \CBT_Admin_Maintenance_Common::get_reset_progress_max_batch_seconds();
        $this->assertGreaterThanOrEqual(2.0, $seconds);
        $this->assertLessThanOrEqual(25.0, $seconds);
    }

    public function test_cbt_data_tables_returns_cbt_prefixed_tables(): void
    {
        if (!class_exists('wpdb')) {
            $this->markTestSkipped('wpdb not available in this test environment.');
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            $this->markTestSkipped('Global $wpdb not initialized.');
        }

        $tables = \CBT_Admin_Maintenance_Common::cbt_data_tables($wpdb);
        $this->assertIsArray($tables);
        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $this->assertStringContainsString('cbt_', $table);
        }
    }

    public function test_is_async_request_returns_false_by_default(): void
    {
        $this->assertFalse(\CBT_Admin_Maintenance_Common::is_async_request());
    }

    public function test_reset_cbt_global_token_options_does_not_throw(): void
    {
        \CBT_Admin_Maintenance_Common::reset_cbt_global_token_options();
        $this->assertTrue(true);
    }

    public function test_collect_cbt_user_ids_for_reset_returns_array(): void
    {
        $ids = \CBT_Admin_Maintenance_Common::collect_cbt_user_ids_for_reset();
        $this->assertIsArray($ids);
    }

    public function test_redirect_maintenance_page_args_throws_in_phpunit(): void
    {
        $this->expectException(\RuntimeException::class);
        \CBT_Admin_Maintenance_Common::redirect_maintenance_page_args(['page' => 'cbt-maintenance']);
    }

    public function test_redirect_maintenance_page_stores_redirect_url(): void
    {
        try {
            \CBT_Admin_Maintenance_Common::redirect_maintenance_page('Test message', null, 'reset');
        } catch (\RuntimeException $e) {
            // Expected in PHPUnit env.
        }
        $this->assertStringContainsString('page=cbt-maintenance', $GLOBALS['cbt_test_last_redirect'] ?? '');
        $this->assertStringContainsString('cbt_msg=', $GLOBALS['cbt_test_last_redirect'] ?? '');
    }

    public function test_redirect_with_error_includes_error_param(): void
    {
        try {
            \CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Some error', 'seed');
        } catch (\RuntimeException $e) {
            // Expected in PHPUnit env.
        }
        $this->assertStringContainsString('cbt_err=', $GLOBALS['cbt_test_last_redirect'] ?? '');
    }

    public function test_redirect_with_tab_includes_tab_param(): void
    {
        try {
            \CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, null, 'load');
        } catch (\RuntimeException $e) {
            // Expected in PHPUnit env.
        }
        $this->assertStringContainsString('cbt_maintenance_tab=load', $GLOBALS['cbt_test_last_redirect'] ?? '');
    }

    public function test_redirect_rejects_invalid_tab(): void
    {
        try {
            \CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, null, 'invalid');
        } catch (\RuntimeException $e) {
            // Expected in PHPUnit env.
        }
        $this->assertStringNotContainsString('cbt_maintenance_tab=invalid', $GLOBALS['cbt_test_last_redirect'] ?? '');
    }
}
