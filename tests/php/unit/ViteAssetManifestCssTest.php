<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-frontend.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-assets.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionMethod;

final class ViteAssetManifestCssTest extends TestCase
{
    public function test_frontend_css_files_include_static_import_chunk_css(): void
    {
        $manifest = [
            'src/frontend/main.js' => [
                'file' => 'assets/frontend.js',
                'src' => 'src/frontend/main.js',
                'imports' => ['_math-render.js'],
                'css' => ['assets/frontend.css'],
            ],
            '_math-render.js' => [
                'file' => 'assets/math-render.js',
                'name' => 'math-render',
                'css' => ['assets/math-render.css'],
            ],
        ];

        self::assertSame(
            ['assets/math-render.css', 'assets/frontend.css'],
            $this->invokeStaticMethod(
                \CBT_Frontend::class,
                'get_vite_entry_css_files',
                [$manifest, $manifest['src/frontend/main.js']]
            )
        );
    }

    public function test_admin_css_files_include_nested_import_chunk_css(): void
    {
        $manifest = [
            'src/admin/math-main.js' => [
                'file' => 'assets/admin-math.js',
                'src' => 'src/admin/math-main.js',
                'imports' => ['_bridge.js'],
                'css' => ['assets/admin.css'],
            ],
            '_bridge.js' => [
                'file' => 'assets/bridge.js',
                'imports' => ['_math-render.js'],
            ],
            '_math-render.js' => [
                'file' => 'assets/math-render.js',
                'name' => 'math-render',
                'css' => ['assets/math-render.css'],
            ],
        ];

        self::assertSame(
            ['assets/math-render.css', 'assets/admin.css'],
            $this->invokeStaticMethod(
                \CBT_Admin_Assets::class,
                'get_entry_css_files',
                [$manifest, $manifest['src/admin/math-main.js']]
            )
        );
    }

    public function test_built_frontend_manifest_keeps_math_renderer_lazy(): void
    {
        $manifest_path = dirname(__DIR__, 3) . '/public/build/manifest.json';
        self::assertFileExists($manifest_path);

        $manifest = json_decode((string) file_get_contents($manifest_path), true);
        self::assertIsArray($manifest);
        self::assertArrayHasKey('src/frontend/main.js', $manifest);

        $entry = $manifest['src/frontend/main.js'];
        self::assertIsArray($entry);

        $static_import_names = array_map(
            static function ($import_key) use ($manifest): string {
                if (!is_string($import_key) || !isset($manifest[$import_key]) || !is_array($manifest[$import_key])) {
                    return '';
                }

                return (string) ($manifest[$import_key]['name'] ?? '');
            },
            is_array($entry['imports'] ?? null) ? $entry['imports'] : []
        );

        self::assertNotContains('math-render', $static_import_names);
        self::assertNotContains('frontend-exam-runtime', $static_import_names);

        $dynamic_import_names = array_map(
            static function ($import_key) use ($manifest): string {
                if (!is_string($import_key) || !isset($manifest[$import_key]) || !is_array($manifest[$import_key])) {
                    return '';
                }

                return (string) ($manifest[$import_key]['name'] ?? '');
            },
            is_array($entry['dynamicImports'] ?? null) ? $entry['dynamicImports'] : []
        );

        self::assertContains('frontend-exam-runtime', $dynamic_import_names);
        self::assertContains('frontend-stage-exam', $dynamic_import_names);
        self::assertContains('frontend-stage-result', $dynamic_import_names);
    }

    /**
     * @param array<int,mixed> $arguments
     */
    private function invokeStaticMethod(string $className, string $methodName, array $arguments): mixed
    {
        $method = new ReflectionMethod($className, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $arguments);
    }
}
