<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-frontend.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionMethod;

final class FrontendServiceWorkerTest extends TestCase
{
    public function test_service_worker_config_is_enabled_for_student_build_and_stable_modes(): void
    {
        $manifest = $this->sampleManifest();

        $buildConfig = $this->invokeFrontendMethod('build_service_worker_frontend_config', [
            [
                'mode' => 'build',
                'label' => 'Production Build',
                'is_dev' => false,
            ],
            'student',
            $manifest,
        ]);
        $stableConfig = $this->invokeFrontendMethod('build_service_worker_frontend_config', [
            [
                'mode' => 'stable',
                'label' => 'Stable Test Mode',
                'is_dev' => false,
            ],
            'student',
            $manifest,
        ]);

        self::assertSame(1, $buildConfig['enabled']);
        self::assertStringContainsString('cbt_exam_sw=student', $buildConfig['url']);
        self::assertSame('/wordpress/cbt-ujian/', $buildConfig['scope']);
        self::assertNotSame('', $buildConfig['build_id']);
        self::assertSame(1, $stableConfig['enabled']);
    }

    public function test_service_worker_config_is_disabled_for_dev_and_supervisor_modes(): void
    {
        $manifest = $this->sampleManifest();

        $devConfig = $this->invokeFrontendMethod('build_service_worker_frontend_config', [
            [
                'mode' => 'dev',
                'label' => 'Vite Dev Server',
                'is_dev' => true,
            ],
            'student',
            $manifest,
        ]);
        $supervisorConfig = $this->invokeFrontendMethod('build_service_worker_frontend_config', [
            [
                'mode' => 'build',
                'label' => 'Production Build',
                'is_dev' => false,
            ],
            'supervisor',
            $manifest,
        ]);

        self::assertSame(0, $devConfig['enabled']);
        self::assertSame('', $devConfig['url']);
        self::assertSame(0, $supervisorConfig['enabled']);
        self::assertSame('', $supervisorConfig['scope']);
    }

    public function test_service_worker_precache_includes_student_chunks_and_excludes_admin_supervisor(): void
    {
        $paths = $this->invokeFrontendMethod('get_service_worker_precache_asset_paths', [
            $this->sampleManifest(),
        ]);

        self::assertContains('assets/frontend-core.js', $paths);
        self::assertContains('assets/frontend.css', $paths);
        self::assertContains('assets/font.woff2', $paths);
        self::assertContains('assets/runtime.js', $paths);
        self::assertContains('assets/student-shell.js', $paths);
        self::assertContains('assets/login.js', $paths);
        self::assertContains('assets/confirm.js', $paths);
        self::assertContains('assets/exam.js', $paths);
        self::assertContains('assets/exam.css', $paths);
        self::assertContains('assets/result.js', $paths);
        self::assertContains('assets/legacy-runtime.js', $paths);
        self::assertContains('assets/exam-runtime.js', $paths);
        self::assertNotContains('assets/supervisor-runtime.js', $paths);
        self::assertNotContains('assets/admin-math.js', $paths);
    }

    public function test_service_worker_script_contains_scope_shell_build_id_and_precache_assets(): void
    {
        $script = $this->invokeFrontendMethod('render_service_worker_script', [
            $this->sampleManifest(),
        ]);

        self::assertStringContainsString("var BUILD_ID = \"", $script);
        self::assertStringContainsString("var SCOPE_PATH = \"/wordpress/cbt-ujian/\"", $script);
        self::assertStringContainsString("var SHELL_URL = \"http://localhost/wordpress/cbt-ujian/\"", $script);
        self::assertStringContainsString('assets/frontend-core.js', $script);
        self::assertStringContainsString('assets/legacy-runtime.js', $script);
        self::assertStringNotContainsString('submit_answers_batch', $script);
        self::assertStringNotContainsString('finish_exam', $script);
    }

    /**
     * @param array<int,mixed> $arguments
     */
    private function invokeFrontendMethod(string $methodName, array $arguments): mixed
    {
        $method = new ReflectionMethod(\CBT_Frontend::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $arguments);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function sampleManifest(): array
    {
        return [
            'src/frontend/main.js' => [
                'file' => 'assets/frontend-core.js',
                'src' => 'src/frontend/main.js',
                'imports' => ['_frontend-shell.js'],
                'dynamicImports' => ['src/frontend/app/runtime.js', 'src/frontend/app/supervisor/runtime.js'],
                'css' => ['assets/frontend.css'],
                'assets' => ['assets/font.woff2'],
            ],
            '_frontend-shell.js' => [
                'file' => 'assets/frontend-shell.js',
                'name' => 'frontend-shell',
                'dynamicImports' => ['_frontend-stage-login.js', '_frontend-stage-confirm.js'],
            ],
            'src/frontend/app/runtime.js' => [
                'file' => 'assets/runtime.js',
                'src' => 'src/frontend/app/runtime.js',
                'imports' => ['_frontend-student-shell.js'],
            ],
            '_frontend-student-shell.js' => [
                'file' => 'assets/student-shell.js',
                'name' => 'frontend-student-shell',
                'dynamicImports' => [
                    '_frontend-stage-exam.js',
                    '_frontend-stage-result.js',
                    'src/frontend/app/legacy-runtime.js',
                ],
            ],
            '_frontend-stage-login.js' => [
                'file' => 'assets/login.js',
                'name' => 'frontend-stage-login',
            ],
            '_frontend-stage-confirm.js' => [
                'file' => 'assets/confirm.js',
                'name' => 'frontend-stage-confirm',
            ],
            '_frontend-stage-exam.js' => [
                'file' => 'assets/exam.js',
                'name' => 'frontend-stage-exam',
                'css' => ['assets/exam.css'],
            ],
            '_frontend-stage-result.js' => [
                'file' => 'assets/result.js',
                'name' => 'frontend-stage-result',
            ],
            'src/frontend/app/legacy-runtime.js' => [
                'file' => 'assets/legacy-runtime.js',
                'src' => 'src/frontend/app/legacy-runtime.js',
                'dynamicImports' => ['_frontend-exam-runtime.js'],
            ],
            '_frontend-exam-runtime.js' => [
                'file' => 'assets/exam-runtime.js',
                'name' => 'frontend-exam-runtime',
            ],
            'src/frontend/app/supervisor/runtime.js' => [
                'file' => 'assets/supervisor-runtime.js',
                'src' => 'src/frontend/app/supervisor/runtime.js',
                'name' => 'frontend-supervisor-runtime',
            ],
            'src/admin/math-main.js' => [
                'file' => 'assets/admin-math.js',
                'src' => 'src/admin/math-main.js',
                'name' => 'admin-math',
            ],
        ];
    }
}
