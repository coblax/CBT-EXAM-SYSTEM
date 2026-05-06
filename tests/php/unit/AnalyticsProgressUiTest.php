<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AnalyticsProgressUiTest extends TestCase
{
    private string $viewSource = '';
    private string $assetSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/analytics/page.php');
        $this->assetSource = (string) file_get_contents(dirname(__DIR__, 3) . '/src/admin/analytics-main.js');
    }

    public function test_analytics_page_declares_local_progress_and_refresh_hooks(): void
    {
        foreach ([
            'data-cbt-analytics-root',
            'data-cbt-analytics-refresh-area="notices"',
            'data-cbt-analytics-progress',
            'data-cbt-analytics-progress-percent',
            'data-cbt-analytics-progress-fill',
            'role="progressbar"',
            'tanpa reload halaman global',
            'data-cbt-analytics-local-form',
            'data-cbt-analytics-local-link',
            'data-cbt-analytics-progress-profile="filter"',
            'data-cbt-analytics-progress-profile="reset"',
            'data-cbt-analytics-progress-profile="all"',
            'data-cbt-analytics-progress-profile="exam"',
            'data-cbt-analytics-progress-profile="page"',
            'data-cbt-analytics-progress-profile="benchmark"',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_analytics_javascript_updates_area_locally_without_global_reload_fallbacks(): void
    {
        foreach ([
            'startAnalyticsProgress',
            'completeAnalyticsProgress',
            'replaceAnalyticsRoot',
            'executeAnalyticsScripts',
            'runAnalyticsLocalAction',
            'bindAnalyticsLocalUi();',
            'cbt:analytics:local-refresh',
            'Gagal memperbarui area Analytics',
            'window.fetch',
            'window.history.pushState',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }

        self::assertStringNotContainsString('window.location.href =', $this->viewSource);
        self::assertStringNotContainsString('window.location.assign', $this->viewSource);
        self::assertStringNotContainsString('location.reload', $this->viewSource);
        self::assertStringNotContainsString('form.submit()', $this->viewSource);
        self::assertStringNotContainsString('requestSubmit', $this->viewSource);
        self::assertStringNotContainsString('onchange="this.form.submit', $this->viewSource);
    }

    public function test_analytics_chart_asset_can_rerender_after_local_refresh(): void
    {
        foreach ([
            'Chart.getChart',
            'existingChart.destroy',
            'window.CBTAdminAnalyticsCharts',
            'initAnalyticsCharts',
            'cbt:analytics:local-refresh',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->assetSource);
        }
    }
}
