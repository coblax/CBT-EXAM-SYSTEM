<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class UpdateHealthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-release-helper.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-health-service.php';
    }

    public function test_run_returns_expected_structure(): void
    {
        $result = \CBT_Update_Health_Service::run([]);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsArray($result['items']);
    }

    public function test_run_items_contain_required_keys(): void
    {
        $result = \CBT_Update_Health_Service::run([]);
        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('message', $item);
        }
    }

    public function test_run_with_version_manifest(): void
    {
        $result = \CBT_Update_Health_Service::run(['version' => '99.0.0']);
        $this->assertIsArray($result);
        $versionItem = null;
        foreach ($result['items'] as $item) {
            if ($item['key'] === 'active_version') {
                $versionItem = $item;
                break;
            }
        }
        $this->assertNotNull($versionItem);
    }

    public function test_status_is_ok_or_failed(): void
    {
        $result = \CBT_Update_Health_Service::run([]);
        $this->assertContains($result['status'], ['ok', 'failed']);
        $this->assertIsBool($result['ok']);
    }

    public function test_each_item_status_is_ok_or_failed(): void
    {
        $result = \CBT_Update_Health_Service::run([]);
        foreach ($result['items'] as $item) {
            $this->assertContains($item['status'], ['ok', 'failed']);
        }
    }
}
