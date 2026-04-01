<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class DeveloperBuildOutputAccessTest extends TestCase
{
    private string $tmpRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-developer-service.php';

        $this->tmpRoot = sys_get_temp_dir() . '/cbt-dev-build-access-' . uniqid('', true);
        mkdir($this->tmpRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryIfExists($this->tmpRoot);
        parent::tearDown();
    }

    public function test_inspect_build_output_access_reports_preparable_when_parent_public_is_writable(): void
    {
        [$publicDir, $buildDir] = $this->makeBuildStructure();
        chmod($publicDir, 0777);
        chmod($buildDir, 0555);
        chmod($buildDir . '/assets', 0555);

        $method = new \ReflectionMethod(\CBT_Admin_Developer_Service::class, 'inspect_build_output_access');
        $method->setAccessible(true);
        $result = $method->invoke(null, $publicDir, $buildDir, 'www-data');

        self::assertSame('preparable', $result['status']);
        self::assertStringContainsString('akan disiapkan ulang', $result['message']);
    }

    public function test_inspect_build_output_access_reports_blocked_when_public_parent_is_not_writable(): void
    {
        [$publicDir, $buildDir] = $this->makeBuildStructure();
        chmod($publicDir, 0555);
        chmod($buildDir, 0555);
        chmod($buildDir . '/assets', 0555);

        $method = new \ReflectionMethod(\CBT_Admin_Developer_Service::class, 'inspect_build_output_access');
        $method->setAccessible(true);
        $result = $method->invoke(null, $publicDir, $buildDir, 'www-data');

        self::assertSame('blocked', $result['status']);
        self::assertStringContainsString('tidak writable', $result['message']);
    }

    public function test_prepare_build_output_for_runtime_rotates_non_writable_build_directory(): void
    {
        [$publicDir, $buildDir] = $this->makeBuildStructure();
        file_put_contents($buildDir . '/assets/demo.txt', 'artifact');
        chmod($publicDir, 0777);
        chmod($buildDir, 0555);
        chmod($buildDir . '/assets', 0555);

        $method = new \ReflectionMethod(\CBT_Admin_Developer_Service::class, 'prepare_build_output_for_runtime');
        $method->setAccessible(true);
        $result = $method->invoke(null, $publicDir, $buildDir, 'www-data');

        self::assertTrue((bool) $result['ok']);
        self::assertDirectoryExists($buildDir);
        self::assertNotEmpty(glob($publicDir . '/.cbt-build-backup-www-data-*'));
    }

    private function makeBuildStructure(): array
    {
        $publicDir = $this->tmpRoot . '/public';
        $buildDir = $publicDir . '/build';
        mkdir($buildDir . '/assets', 0777, true);

        return [$publicDir, $buildDir];
    }

    private function removeDirectoryIfExists(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @chmod($path, 0777);
            @unlink($path);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = (string) $item->getPathname();
            @chmod($itemPath, 0777);
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @chmod($path, 0777);
        @rmdir($path);
    }
}
