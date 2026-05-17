<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class AdminMaintenanceContextBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-branding-settings.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-common.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-context-builder.php';
    }

    public function test_allowed_maintenance_tabs_returns_expected(): void
    {
        $tabs = \CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs();
        $this->assertSame(['reset', 'seed', 'load'], $tabs);
    }

    public function test_allowed_maintenance_tabs_contains_reset(): void
    {
        $tabs = \CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs();
        $this->assertContains('reset', $tabs);
    }

    public function test_allowed_maintenance_tabs_contains_seed(): void
    {
        $tabs = \CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs();
        $this->assertContains('seed', $tabs);
    }

    public function test_allowed_maintenance_tabs_contains_load(): void
    {
        $tabs = \CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs();
        $this->assertContains('load', $tabs);
    }

    public function test_allowed_maintenance_tabs_does_not_contain_invalid(): void
    {
        $tabs = \CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs();
        $this->assertNotContains('admin', $tabs);
        $this->assertNotContains('debug', $tabs);
    }
}
