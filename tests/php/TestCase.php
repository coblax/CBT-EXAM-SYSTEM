<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \cbt_test_reset_wordpress_storage();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        \Mockery::close();
        \cbt_test_reset_wordpress_storage();
        parent::tearDown();
    }
}
