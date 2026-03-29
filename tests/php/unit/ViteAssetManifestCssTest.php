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
