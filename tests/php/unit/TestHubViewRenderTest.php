<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class TestHubViewRenderTest extends TestCase
{
    private string $projectRoot = '';
    private string $uploadRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-test-hub-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-test-hub-page.php';

        $this->projectRoot = dirname(__DIR__, 3);
        $this->uploadRoot = sys_get_temp_dir() . '/cbt-test-hub-view-uploads-' . getmypid();
        $GLOBALS['cbt_test_wp_upload_dir'] = $this->uploadRoot;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $_GET = [];

        $this->removeDirectoryIfExists($this->projectRoot . '/playwright-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/test-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/coverage');
        $this->removeDirectoryIfExists($this->uploadRoot);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $this->removeDirectoryIfExists($this->projectRoot . '/playwright-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/test-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/coverage');
        $this->removeDirectoryIfExists($this->uploadRoot);

        parent::tearDown();
    }

    public function test_view_renders_settings_form_with_saved_base_and_frontend_urls(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringContainsString('class="wrap cbt-test-hub-page"', $html);
        self::assertStringContainsString('name="action" value="cbt_save_test_hub_settings"', $html);
        self::assertStringContainsString('name="e2e_base_url"', $html);
        self::assertStringContainsString('value="http://localhost/wordpress"', $html);
        self::assertStringContainsString('name="e2e_frontend_url"', $html);
        self::assertStringContainsString('value="http://localhost/wordpress/ujian"', $html);
        self::assertStringContainsString('nonce-cbt_save_test_hub_settings', $html);
        self::assertStringContainsString('data-test-hub-root data-test-hub-refresh-url=', $html);
        self::assertStringContainsString('data-test-hub-async-url=', $html);
        self::assertStringContainsString('data-has-active-flow-jobs=', $html);
        self::assertStringContainsString('data-has-active-global-unit-run=', $html);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="settings"', $html);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,settings" data-loading-label="Menyimpan..."', $html);
        self::assertStringContainsString("formData.set('cbt_test_hub_async', '1');", $html);
        self::assertStringContainsString('canonicalRefreshUrl', $html);
        self::assertStringContainsString('canonicalAsyncUrl', $html);
        self::assertStringContainsString('requestPageHtml(asyncUrl', $html);
        self::assertStringContainsString('Menyimpan settings...', $html);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="global-unit-run"', $html);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,global-unit-run,unit-inventory,checklist" data-loading-label="Menjalankan..."', $html);
        self::assertStringContainsString('Menjalankan semua unit test...', $html);
        self::assertStringContainsString('secara bertahap agar aman dari timeout Cloudflare', $html);
        self::assertStringContainsString('hasActiveGlobalUnitRun', $html);
        self::assertStringContainsString('scheduleGlobalUnitRunStep', $html);
        self::assertStringContainsString('runNextGlobalUnitStep', $html);
        self::assertStringContainsString('@keyframes cbt-test-hub-progress', $html);
        self::assertStringContainsString('cbt-test-hub-loading-progress', $html);
        self::assertStringContainsString('data-loading-progress', $html);
        self::assertStringContainsString('resolveProgressProfile', $html);
        self::assertStringContainsString('startLoadingProgress', $html);
        self::assertStringContainsString('aria-valuenow', $html);
        self::assertMatchesRegularExpression('/\\.cbt-test-hub-loading-status\\s*\\{[^}]*width:\\s*100%;[^}]*max-width:\\s*none;[^}]*box-sizing:\\s*border-box;/s', $html);
        self::assertMatchesRegularExpression('/\\.cbt-test-hub-loading-progress\\s*\\{[^}]*width:\\s*100%;/s', $html);
        self::assertStringContainsString('Gagal memperbarui area ini tanpa reload global.', $html);
        self::assertStringContainsString('Response bukan halaman CBT Test Hub', $html);
        self::assertStringContainsString('extractResponseError', $html);
    }

    public function test_view_renders_active_global_unit_run_batch_state(): void
    {
        set_transient('cbt_global_unit_test_run_state_batchtoken', [
            'type' => 'global_unit_tests_state',
            'token' => 'batchtoken',
            'status' => 'running',
            'tab' => 'recovery_persistence',
            'scope' => 'unit_tests',
            'queue' => [
                [
                    'tab' => 'recovery_persistence',
                    'runner_label' => 'Run Recovery',
                    'runner_description' => 'Recovery runner.',
                    'label' => 'Command A',
                    'command' => 'php -v',
                ],
                [
                    'tab' => 'sync_rest',
                    'runner_label' => 'Run Sync',
                    'runner_description' => 'Sync runner.',
                    'label' => 'Command B',
                    'command' => 'php -v',
                ],
            ],
            'tabs' => [],
            'success' => true,
            'passed_count' => 4,
            'failed_count' => 0,
            'processed_commands' => 1,
            'total_commands' => 2,
            'current_label' => 'Command B',
            'started_at' => time(),
            'updated_at' => time(),
        ], 900);

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'recovery_persistence',
            'cbt_checklist_scope' => 'unit_tests',
            'cbt_global_unit_run_token' => 'batchtoken',
        ]);

        self::assertStringContainsString('data-has-active-global-unit-run="1"', $html);
        self::assertStringContainsString('name="cbt_global_unit_run_token" value="batchtoken"', $html);
        self::assertStringContainsString('Run All Sedang Berjalan', $html);
        self::assertStringContainsString('Memproses bertahap: 1 / 2 command selesai. Sedang: Command B', $html);
        self::assertStringContainsString('50% . 1/2 command', $html);
        self::assertStringContainsString('Run global sedang berjalan bertahap. Area ini auto-refresh lokal tanpa reload global.', $html);
    }

    public function test_view_renders_runner_health_panel_and_refresh_action(): void
    {
        update_option('cbt_test_hub_runner_health_v1', [
            'checked_at' => 1700000000,
            'overall_status' => 'warning',
            'checks' => [
                [
                    'key' => 'node',
                    'label' => 'Node.js >= 20',
                    'status' => 'ready',
                    'message' => 'Node.js memenuhi minimum versi.',
                    'detail' => 'v20.11.1',
                ],
                [
                    'key' => 'playwright_chromium',
                    'label' => 'Playwright Chromium',
                    'status' => 'warning',
                    'message' => 'Folder browser Playwright belum berisi Chromium.',
                    'detail' => '<script>alert(1)</script>',
                ],
            ],
        ], false);

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringContainsString('Runner Health', $html);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="runner-health"', $html);
        self::assertStringContainsString('data-cbt-test-hub-collapsible="runner-health"', $html);
        self::assertStringContainsString('data-cbt-test-hub-collapsible-default="collapsed"', $html);
        self::assertStringContainsString('data-cbt-test-hub-collapse-toggle', $html);
        self::assertStringContainsString('id="cbt-test-hub-runner-health-detail"', $html);
        self::assertStringContainsString('name="action" value="cbt_refresh_test_hub_health"', $html);
        self::assertStringContainsString('data-refresh-areas="banners,runner-health,checklist"', $html);
        self::assertStringContainsString('Memeriksa Runner Health...', $html);
        self::assertStringContainsString('Refresh Runner Health', $html);
        self::assertStringContainsString('Tampilkan detail', $html);
        self::assertStringContainsString('bindCollapsibleHealthPanels', $html);
        self::assertStringContainsString('cbt_test_hub_collapsible_v1_', $html);
        self::assertMatchesRegularExpression('/>\s*Warning\s*</', $html);
        self::assertStringContainsString('Node.js &gt;= 20', $html);
        self::assertStringContainsString('Folder browser Playwright belum berisi Chromium.', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('alert(1)', $html);
    }

    public function test_view_renders_e2e_readiness_panel_and_escapes_details(): void
    {
        update_option('cbt_test_hub_e2e_readiness_v1', [
            'checked_at' => 1700000100,
            'overall_status' => 'blocked',
            'checks' => [
                [
                    'key' => 'wordpress_login',
                    'label' => 'WordPress Login',
                    'status' => 'blocked',
                    'message' => 'WordPress login belum siap.',
                    'detail' => 'HTTP 404 Body: <script>alert(7)</script>',
                    'url' => 'http://localhost/wordpress/wp-login.php',
                ],
                [
                    'key' => 'fixture_catalog',
                    'label' => 'Fixture Catalog',
                    'status' => 'ready',
                    'message' => 'Fixture catalog utama tersedia.',
                    'detail' => 'Required fixtures OK.',
                    'url' => '',
                ],
            ],
            'suggestions' => [
                'Coba E2E Base URL: http://localhost',
                '<script>alert(8)</script> http://127.0.0.1',
            ],
        ], false);

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringContainsString('E2E Readiness', $html);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="e2e-readiness"', $html);
        self::assertStringContainsString('data-cbt-test-hub-collapsible="e2e-readiness"', $html);
        self::assertStringContainsString('id="cbt-test-hub-e2e-readiness-detail"', $html);
        self::assertStringContainsString('name="action" value="cbt_check_test_hub_e2e_readiness"', $html);
        self::assertStringContainsString('data-refresh-areas="banners,e2e-readiness"', $html);
        self::assertStringContainsString('Mengecek E2E Readiness...', $html);
        self::assertStringContainsString('Check E2E Readiness', $html);
        self::assertMatchesRegularExpression('/>\s*Blocked\s*</', $html);
        self::assertStringContainsString('WordPress Login', $html);
        self::assertStringContainsString('http://localhost/wordpress/wp-login.php', $html);
        self::assertStringContainsString('URL Doctor Suggestions', $html);
        self::assertStringContainsString('Coba E2E Base URL: http://localhost', $html);
        self::assertStringNotContainsString('<script>alert(7)</script>', $html);
        self::assertStringNotContainsString('<script>alert(8)</script>', $html);
        self::assertStringContainsString('alert(7)', $html);
        self::assertStringContainsString('alert(8)', $html);
    }

    public function test_cleanup_button_state_tracks_artifact_and_active_job_status(): void
    {
        $emptyHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);
        self::assertStringContainsString('Belum ada artefak test yang perlu dibersihkan.', $emptyHtml);
        self::assertMatchesRegularExpression('/class="button cbt-test-hub-danger-button"[^>]*disabled="disabled"/s', $emptyHtml);

        $testResults = $this->projectRoot . '/test-results';
        mkdir($testResults, 0777, true);
        file_put_contents($testResults . '/summary.json', '{}');
        $availableHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);
        self::assertStringContainsString('Test Results', $availableHtml);
        self::assertStringContainsString('Tersedia', $availableHtml);
        self::assertDoesNotMatchRegularExpression('/class="button cbt-test-hub-danger-button"[^>]*disabled="disabled"/s', $availableHtml);

        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-running-cleanup-lock',
            'item_index' => 0,
            'status' => 'running',
            'created_at' => time() + 10,
            'started_at' => time(),
            'heartbeat_at' => time(),
        ]));
        $lockedHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);
        self::assertStringContainsString('Cleanup sementara dikunci', $lockedHtml);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="artifacts"', $lockedHtml);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="checklist"', $lockedHtml);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,artifacts,checklist" data-loading-label="Membersihkan..."', $lockedHtml);
        self::assertStringContainsString('Area Flow Check akan diperbarui otomatis selama masih ada job aktif.', $lockedHtml);
        self::assertStringNotContainsString('window.location.reload', $lockedHtml);
        self::assertMatchesRegularExpression('/class="button cbt-test-hub-danger-button"[^>]*disabled="disabled"/s', $lockedHtml);
    }

    public function test_view_renders_active_tabs_and_scope_specific_actions(): void
    {
        $smokeHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertMatchesRegularExpression('/class="cbt-test-hub-subtab is-active"[^>]*data-unit-test-tab="sync_rest"[^>]*aria-selected="true"/s', $smokeHtml);
        self::assertMatchesRegularExpression('/class="cbt-test-hub-subpanel is-active"[^>]*data-unit-test-panel="sync_rest"/s', $smokeHtml);
        self::assertMatchesRegularExpression('/class="cbt-test-hub-checklist-tab is-active"[^>]*data-checklist-tab="smoke_tests"[^>]*aria-selected="true"/s', $smokeHtml);
        self::assertStringContainsString('name="action" value="cbt_queue_flow_check_job"', $smokeHtml);
        self::assertStringContainsString('data-cbt-test-hub-refresh-area="panel-sync_rest"', $smokeHtml);
        self::assertStringContainsString('data-cbt-test-hub-async-form', $smokeHtml);
        self::assertStringContainsString('data-refresh-areas="banners,artifacts,panel-sync_rest"', $smokeHtml);

        $unitHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'unit_tests',
        ]);

        self::assertMatchesRegularExpression('/class="cbt-test-hub-checklist-tab is-active"[^>]*data-checklist-tab="unit_tests"[^>]*aria-selected="true"/s', $unitHtml);
        self::assertStringContainsString('name="action" value="cbt_run_unit_test_suite"', $unitHtml);
        self::assertStringContainsString('data-refresh-areas="banners,panel-sync_rest"', $unitHtml);
    }

    public function test_item_run_task_forms_refresh_only_their_task_area(): void
    {
        $unitHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'unit_tests',
        ]);

        self::assertStringContainsString('data-cbt-test-hub-refresh-area="task-sync_rest-unit-0"', $unitHtml);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,task-sync_rest-unit-0" data-loading-label="Menjalankan..."', $unitHtml);
        self::assertStringContainsString('form.requestSubmit(this)', $unitHtml);
        self::assertStringContainsString('Menjalankan task...', $unitHtml);

        $flowHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringContainsString('data-cbt-test-hub-refresh-area="task-sync_rest-smoke-0"', $flowHtml);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,artifacts,task-sync_rest-smoke-0" data-loading-label="Queueing..."', $flowHtml);
        self::assertStringContainsString('Memproses flow task...', $flowHtml);
        self::assertStringNotContainsString('window.location.reload', $flowHtml);
    }

    public function test_view_renders_flow_job_status_labels(): void
    {
        $now = time();
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-queued',
            'item_index' => 0,
            'status' => 'queued',
            'created_at' => $now + 6,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-running',
            'item_index' => 1,
            'status' => 'running',
            'created_at' => $now + 5,
            'started_at' => $now,
            'heartbeat_at' => $now,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-cancelling',
            'item_index' => 2,
            'status' => 'cancelling',
            'created_at' => $now + 4,
            'started_at' => $now,
            'heartbeat_at' => $now,
            'worker_pid' => getmypid(),
            'cancel_requested_at' => $now,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-passed',
            'item_index' => 3,
            'status' => 'passed',
            'created_at' => $now + 3,
            'finished_at' => $now,
            'exit_code' => 0,
            'results' => [
                [
                    'label' => 'Playwright Sync Pending Finish Lock',
                    'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: finish remains locked informatively while pending sync exists"',
                    'success' => true,
                    'exit_code' => 0,
                    'stdout' => 'passed',
                    'stderr' => '',
                    'failure_summary' => '',
                ],
            ],
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-failed',
            'item_index' => 4,
            'status' => 'failed',
            'created_at' => $now + 2,
            'finished_at' => $now,
            'exit_code' => 1,
            'failure_summary' => 'Expected sync guard to reject cross-user attempt.',
            'stderr' => 'failed',
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-stale',
            'item_index' => 5,
            'status' => 'failed',
            'created_at' => $now + 1,
            'finished_at' => $now,
            'exit_code' => 1,
            'failure_kind' => 'stale',
            'failure_summary' => 'Job background stale karena runner tidak lagi memberi heartbeat dalam batas aman.',
            'stderr' => 'heartbeat job sudah stale',
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-interrupted',
            'item_index' => 6,
            'status' => 'failed',
            'created_at' => $now,
            'finished_at' => $now,
            'exit_code' => 1,
            'failure_kind' => 'interrupted',
            'failure_summary' => 'Job background terputus sebelum flow check selesai dijalankan.',
            'stderr' => 'process worker sudah tidak aktif',
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-status-cancelled',
            'tab' => 'auth_session',
            'item_index' => 0,
            'status' => 'cancelled',
            'created_at' => $now - 1,
            'finished_at' => $now,
            'exit_code' => 1,
            'failure_kind' => 'cancelled',
            'failure_summary' => 'Job flow check dibatalkan dari CBT Test Hub.',
        ]));

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        foreach (['Queued', 'Running', 'Cancelling', 'Passed', 'Failed', 'Stale', 'Interrupted', 'Cancelled'] as $label) {
            self::assertMatchesRegularExpression('/>\s*' . preg_quote($label, '/') . '\s*</', $html);
        }
        self::assertStringContainsString('1 queued, 1 running, 1 cancelling, 1 passed, 3 failed.', $html);
        self::assertStringContainsString('Progress flow: 4 / 7 job selesai (57%).', $html);
        self::assertStringContainsString('aria-valuenow="57"', $html);
        self::assertStringContainsString('name="action" value="cbt_cancel_flow_check_job"', $html);
        self::assertStringContainsString('name="action" value="cbt_retry_flow_check_job"', $html);
        self::assertStringContainsString('name="action" value="cbt_clear_flow_check_job"', $html);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,artifacts,task-sync_rest-smoke-0" data-loading-label="Cancelling..."', $html);
        self::assertMatchesRegularExpression('/>\s*Cancel\s*</', $html);
        self::assertMatchesRegularExpression('/>\s*Retry\s*</', $html);
        self::assertMatchesRegularExpression('/>\s*Clear\s*</', $html);
    }

    public function test_view_escapes_runner_failure_summary_and_async_preview(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-escaped-output',
            'item_index' => 0,
            'status' => 'failed',
            'finished_at' => time(),
            'exit_code' => 1,
            'failure_kind' => 'interrupted',
            'failure_summary' => '<script>alert(1)</script> Timed out',
            'stderr' => '<script>alert(2)</script> stderr',
            'stdout' => '<script>alert(3)</script> stdout',
        ]));

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<script>alert(2)</script>', $html);
        self::assertStringNotContainsString('<script>alert(3)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Timed out', $html);
        self::assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt; stderr', $html);
        self::assertStringContainsString('&lt;script&gt;alert(3)&lt;/script&gt; stdout', $html);
    }

    public function test_view_renders_artifact_viewer_and_repair_stuck_jobs_button_state(): void
    {
        $jobDir = $this->flowJobDirectory();
        $artifactRoot = $jobDir . '/flow-view-artifacts-artifacts';
        mkdir($artifactRoot, 0777, true);
        file_put_contents($jobDir . '/flow-view-artifacts.log', "first log line\n<script>alert(4)</script>");
        file_put_contents($artifactRoot . '/trace.zip', 'trace');

        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-view-artifacts',
            'item_index' => 0,
            'status' => 'failed',
            'finished_at' => time(),
            'exit_code' => 1,
            'failure_summary' => '<script>alert(5)</script> Failure',
            'stdout' => '<script>alert(6)</script> stdout',
            'log_path' => $jobDir . '/flow-view-artifacts.log',
        ]));

        $terminalHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringContainsString('Log &amp; Artifacts', $terminalHtml);
        self::assertStringContainsString('Download Log', $terminalHtml);
        self::assertStringContainsString('trace.zip', $terminalHtml);
        self::assertStringContainsString('name="action" value="cbt_repair_stuck_flow_check_jobs"', $terminalHtml);
        self::assertStringContainsString('data-cbt-test-hub-async-form data-refresh-areas="banners,artifacts,checklist" data-loading-label="Repairing..."', $terminalHtml);
        self::assertMatchesRegularExpression('/>\s*Repair Stuck Jobs\s*</', $terminalHtml);
        self::assertMatchesRegularExpression('/name="action" value="cbt_repair_stuck_flow_check_jobs"(?:(?!<\\/form>).)*disabled="disabled"/s', $terminalHtml);
        self::assertStringNotContainsString('<script>alert(4)</script>', $terminalHtml);
        self::assertStringContainsString('&lt;script&gt;alert(4)&lt;/script&gt;', $terminalHtml);
        self::assertStringContainsString('&lt;script&gt;alert(6)&lt;/script&gt; stdout', $terminalHtml);

        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-view-running-repair',
            'item_index' => 1,
            'status' => 'running',
            'created_at' => time() + 10,
            'started_at' => time(),
            'heartbeat_at' => time(),
        ]));

        $activeHtml = $this->renderHub([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]);

        self::assertStringContainsString('Repair status: 1 active', $activeHtml);
        self::assertDoesNotMatchRegularExpression('/name="action" value="cbt_repair_stuck_flow_check_jobs"(?:(?!<\\/form>).)*disabled="disabled"/s', $activeHtml);
    }

    public function test_page_render_authorized_outputs_shell_and_unauthorized_dies(): void
    {
        $authorizedHtml = $this->renderPage([
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'unit_tests',
        ]);

        self::assertStringContainsString('class="wrap cbt-test-hub-page"', $authorizedHtml);
        self::assertStringContainsString('Unit Test Hub', $authorizedHtml);

        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');
        $this->renderPage();
    }

    public function test_view_renders_unit_test_inventory_with_file_result_details(): void
    {
        set_transient('cbt_unit_test_run_result_inventorytoken', [
            'tab' => 'security_log_observability',
            'scope' => 'unit_tests',
            'item_index' => null,
            'item_label' => 'IncidentReportTest.php',
            'inventory_file' => [
                'id' => 'php-incident',
                'type' => 'php',
                'path' => 'tests/php/unit/IncidentReportTest.php',
                'basename' => 'IncidentReportTest.php',
                'mapped_tab' => 'security_log_observability',
                'mapped_tab_label' => 'Security Log & Observability',
                'mapping_status' => 'auto_mapped',
            ],
            'label' => 'Run Unit Test File',
            'description' => 'Unit inventory result.',
            'success' => false,
            'executed_at' => time(),
            'commands' => [
                [
                    'label' => 'PHPUnit IncidentReportTest.php',
                    'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/IncidentReportTest.php',
                    'success' => false,
                    'exit_code' => 1,
                    'stdout' => '<script>alert(1)</script> stdout',
                    'stderr' => '<script>alert(2)</script> stderr',
                    'failure_summary' => '<script>alert(3)</script> failed',
                    'inventory_file' => [
                        'path' => 'tests/php/unit/IncidentReportTest.php',
                    ],
                ],
            ],
        ], 900);

        $html = $this->renderHub([
            'cbt_unit_test_tab' => 'security_log_observability',
            'cbt_checklist_scope' => 'unit_tests',
            'cbt_test_run_token' => 'inventorytoken',
        ]);

        self::assertStringContainsString('Unit Test Inventory', $html);
        self::assertStringContainsString('data-cbt-test-hub-collapsible="unit-inventory"', $html);
        self::assertStringContainsString('data-unit-inventory-pagination', $html);
        self::assertStringContainsString('data-unit-inventory-list data-page-size="25"', $html);
        self::assertStringContainsString('data-unit-inventory-page-next', $html);
        self::assertStringContainsString('tests/php/unit/IncidentReportTest.php', $html);
        self::assertStringContainsString('Auto-mapped', $html);
        self::assertStringContainsString('Run File', $html);
        self::assertStringContainsString('Latest Failed', $html);
        self::assertStringContainsString('&lt;script&gt;alert(3)&lt;/script&gt; failed', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; stdout', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<script>alert(2)</script>', $html);
        self::assertStringNotContainsString('<script>alert(3)</script>', $html);
    }

    /**
     * @param array<string,mixed> $query
     */
    private function renderHub(array $query = []): string
    {
        $context = \CBT_Admin_Test_Hub_Service::build_unit_test_context($query);
        ob_start();
        extract($context, EXTR_SKIP);
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/test-hub/page.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $get
     */
    private function renderPage(array $get = []): string
    {
        $_GET = $get;
        ob_start();
        try {
            \CBT_Admin_Test_Hub_Page::render();
            return (string) ob_get_clean();
        } catch (\Throwable $throwable) {
            ob_end_clean();
            throw $throwable;
        }
    }

    /**
     * @param array<int,mixed> $args
     */
    private function invokePrivate(string $methodName, array $args = []): mixed
    {
        $method = new \ReflectionMethod(\CBT_Admin_Test_Hub_Service::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $args);
    }

    private function writeFlowJob(array $job): void
    {
        self::assertTrue((bool) $this->invokePrivate('write_flow_check_job', [$job]));
    }

    private function flowJobDirectory(): string
    {
        return (string) $this->invokePrivate('flow_job_directory_path');
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function baseJob(array $overrides = []): array
    {
        return array_merge([
            'job_id' => 'flow-view-' . md5(serialize($overrides)),
            'tab' => 'sync_rest',
            'scope' => 'smoke_tests',
            'item_index' => 0,
            'item_label' => 'Playwright Sync End To End',
            'status' => 'queued',
            'created_at' => time(),
            'started_at' => 0,
            'finished_at' => 0,
            'heartbeat_at' => 0,
            'worker_pid' => 0,
            'active_child_pid' => 0,
            'cancel_requested_at' => 0,
            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: start load submit finish result end to end"',
            'commands' => [
                [
                    'label' => 'Playwright Sync End To End',
                    'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: start load submit finish result end to end"',
                ],
            ],
            'results' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'failure_kind' => '',
            'failure_summary' => '',
            'log_path' => $this->flowJobDirectory() . '/flow-view.log',
        ], $overrides);
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
