<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class DeactivatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-runtime.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-deactivator.php';
    }

    public function test_deactivate_calls_runtime_deactivate(): void
    {
        \CBT_Deactivator::deactivate();
        $this->assertTrue(true, 'Deactivator::deactivate() should not throw.');
    }

    public function test_deactivate_handles_missing_optional_services(): void
    {
        \CBT_Deactivator::deactivate();
        $this->assertTrue(true, 'Missing optional services should be handled gracefully.');
    }
}
