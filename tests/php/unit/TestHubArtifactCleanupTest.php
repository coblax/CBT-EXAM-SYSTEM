<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class TestHubArtifactCleanupTest extends TestCase
{
    private string $artifactRoot = '';
    private string $uploadRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-test-hub-service.php';

        $this->uploadRoot = sys_get_temp_dir() . '/cbt-test-hub-artifact-uploads-' . getmypid();
        $GLOBALS['cbt_test_wp_upload_dir'] = $this->uploadRoot;
        $this->artifactRoot = $this->uploadRoot . '/cbt-test-hub/playwright-results';
        $this->removeDirectoryIfExists($this->artifactRoot);
        $this->removeDirectoryIfExists($this->uploadRoot);
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/test-results');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/coverage');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-auth');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-result');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-sync');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-timer');
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryIfExists($this->artifactRoot);
        $this->removeDirectoryIfExists($this->uploadRoot);
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/test-results');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/coverage');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-auth');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-result');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-sync');
        $this->removeDirectoryIfExists(dirname(__DIR__, 3) . '/output/playwright-timer');

        parent::tearDown();
    }

    public function test_build_unit_test_context_exposes_artifact_cleanup_targets(): void
    {
        $playwrightResults = $this->artifactRoot;
        $testResults = dirname(__DIR__, 3) . '/test-results';
        mkdir($playwrightResults, 0777, true);
        mkdir($testResults, 0777, true);
        file_put_contents($playwrightResults . '/sample.txt', 'trace');
        file_put_contents($testResults . '/summary.json', '{}');

        $context = \CBT_Admin_Test_Hub_Service::build_unit_test_context([]);

        self::assertArrayHasKey('test_artifact_cleanup', $context);
        self::assertTrue((bool) $context['test_artifact_cleanup']['has_existing']);
        self::assertGreaterThanOrEqual(2, (int) $context['test_artifact_cleanup']['existing_count']);

        $targets = (array) $context['test_artifact_cleanup']['targets'];
        self::assertTrue($this->cleanupTargetExists($targets, 'Playwright Results'));
        self::assertTrue($this->cleanupTargetExists($targets, 'Test Results'));
    }

    public function test_remove_test_artifact_path_deletes_known_directory_tree(): void
    {
        $path = $this->artifactRoot . '/admin-jobs/demo';
        mkdir($path, 0777, true);
        file_put_contents($path . '/output.txt', 'artifact');

        $method = new \ReflectionMethod(\CBT_Admin_Test_Hub_Service::class, 'remove_test_artifact_path');
        $method->setAccessible(true);
        $result = $method->invoke(null, $this->artifactRoot);

        self::assertTrue((bool) $result);
        self::assertDirectoryDoesNotExist($this->artifactRoot);
    }

    public function test_remove_test_artifact_path_treats_empty_root_as_clean_when_parent_not_writable(): void
    {
        $root = $this->artifactRoot;
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

    /**
     * @param array<int,mixed> $targets
     */
    private function cleanupTargetExists(array $targets, string $label): bool
    {
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            if ((string) ($target['label'] ?? '') === $label) {
                return !empty($target['exists']);
            }
        }

        return false;
    }
}
