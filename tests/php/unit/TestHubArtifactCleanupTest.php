<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class TestHubArtifactCleanupTest extends TestCase
{
    private string $artifactRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-test-hub-service.php';

        $this->artifactRoot = dirname(__DIR__, 3) . '/playwright-results';
        $this->removeDirectoryIfExists($this->artifactRoot);
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/test-results');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/coverage');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/.phpunit.cache');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-auth');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-result');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-sync');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-timer');
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/playwright-results');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/test-results');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/coverage');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/.phpunit.cache');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-auth');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-result');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-sync');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-timer');

        parent::tearDown();
    }

    public function test_build_unit_test_context_exposes_artifact_cleanup_targets(): void
    {
        $playwrightResults = dirname(__DIR__, 3) . '/playwright-results';
        $testResults = dirname(__DIR__, 3) . '/test-results';
        mkdir($playwrightResults, 0777, true);
        mkdir($testResults, 0777, true);
        file_put_contents($playwrightResults . '/sample.txt', 'trace');
        file_put_contents($testResults . '/summary.json', '{}');

        $context = \CBT_Admin_Test_Hub_Service::build_unit_test_context([]);

        self::assertArrayHasKey('test_artifact_cleanup', $context);
        self::assertTrue((bool) $context['test_artifact_cleanup']['has_existing']);
        self::assertSame(2, (int) $context['test_artifact_cleanup']['existing_count']);

        $targets = (array) $context['test_artifact_cleanup']['targets'];
        self::assertSame('Playwright Results', (string) $targets[0]['label']);
        self::assertTrue((bool) $targets[0]['exists']);
    }

    public function test_remove_test_artifact_path_deletes_known_directory_tree(): void
    {
        $path = dirname(__DIR__, 3) . '/playwright-results/admin-jobs/demo';
        mkdir($path, 0777, true);
        file_put_contents($path . '/output.txt', 'artifact');

        $method = new \ReflectionMethod(\CBT_Admin_Test_Hub_Service::class, 'remove_test_artifact_path');
        $method->setAccessible(true);
        $result = $method->invoke(null, dirname(__DIR__, 3) . '/playwright-results');

        self::assertTrue((bool) $result);
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 3) . '/playwright-results');
    }

    public function test_remove_test_artifact_path_treats_empty_root_as_clean_when_parent_not_writable(): void
    {
        $root = dirname(__DIR__, 3) . '/playwright-results';
        $path = $root . '/blocked-root/demo';
        mkdir($path, 0777, true);
        file_put_contents($path . '/output.txt', 'artifact');

        chmod($root, 0555);

        try {
            $method = new \ReflectionMethod(\CBT_Admin_Test_Hub_Service::class, 'remove_test_artifact_path');
            $method->setAccessible(true);
            $result = $method->invoke(null, $root . '/blocked-root');

            self::assertTrue((bool) $result);
            self::assertDirectoryExists($root . '/blocked-root');
            self::assertSame([], array_values(array_diff(scandir($root . '/blocked-root') ?: [], ['.', '..'])));
        } finally {
            chmod($root, 0777);
        }
    }

    public function test_build_unit_test_context_marks_output_playwright_target_when_debug_artifacts_exist(): void
    {
        $outputPlaywright = dirname(__DIR__, 3) . '/output/playwright-sync';
        mkdir($outputPlaywright, 0777, true);
        file_put_contents($outputPlaywright . '/.last-run.json', '{"status":"ok"}');

        $context = \CBT_Admin_Test_Hub_Service::build_unit_test_context([]);
        $targets = (array) $context['test_artifact_cleanup']['targets'];
        $outputTarget = null;

        foreach ($targets as $target) {
            if ((string) ($target['label'] ?? '') === 'Output Playwright Artifacts') {
                $outputTarget = (array) $target;
                break;
            }
        }

        self::assertNotNull($outputTarget);
        self::assertTrue((bool) ($outputTarget['exists'] ?? false));
    }

    private function removeDirectoryIfExists(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = (string) $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }
}
