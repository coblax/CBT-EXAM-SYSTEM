<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class DeveloperProgressUiTest extends TestCase
{
    private string $viewSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/developer/page.php');
    }

    public function test_developer_page_declares_local_progress_and_refresh_areas(): void
    {
        foreach ([
            'data-cbt-developer-root',
            'data-cbt-developer-refresh-area="notices"',
            'data-cbt-developer-refresh-area="hero"',
            'data-cbt-developer-refresh-area="frontend-mode"',
            'data-cbt-developer-refresh-area="runtime-status"',
            'data-cbt-developer-progress',
            'data-cbt-developer-progress-percent',
            'data-cbt-developer-progress-fill',
            'role="progressbar"',
            'tanpa reload halaman global',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_developer_server_actions_are_wired_for_scoped_local_updates(): void
    {
        foreach ([
            'data-cbt-developer-local-form',
            'data-cbt-developer-progress-profile="settings"',
            'data-cbt-developer-progress-profile="check"',
            'data-cbt-developer-progress-profile="stop"',
            'startDeveloperProgress',
            'completeDeveloperProgress',
            'replaceDeveloperRefreshAreas',
            'runDeveloperLocalForm',
            'bindDeveloperLocalActions();',
            'cbt:developer:local-refresh',
            'Gagal memperbarui area Developer tanpa reload global',
            'window.fetch',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_developer_javascript_avoids_global_reload_fallbacks(): void
    {
        self::assertStringNotContainsString('window.location.href =', $this->viewSource);
        self::assertStringNotContainsString('window.location.assign', $this->viewSource);
        self::assertStringNotContainsString('location.reload', $this->viewSource);
        self::assertStringNotContainsString('form.submit()', $this->viewSource);
        self::assertStringNotContainsString('requestSubmit', $this->viewSource);
    }
}
