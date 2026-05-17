<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class TestHubActionHandlersTest extends TestCase
{
    private string $projectRoot = '';
    private string $uploadRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-test-hub-service.php';

        $this->projectRoot = dirname(__DIR__, 3);
        $this->uploadRoot = sys_get_temp_dir() . '/cbt-test-hub-action-uploads-' . getmypid();
        $GLOBALS['cbt_test_wp_upload_dir'] = $this->uploadRoot;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $_POST = [];

        $this->removeDirectoryIfExists($this->projectRoot . '/playwright-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/test-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/coverage');
        $this->removeDirectoryIfExists($this->uploadRoot);
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_REQUEST = [];
        $this->removeDirectoryIfExists($this->projectRoot . '/playwright-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/test-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/coverage');
        $this->removeDirectoryIfExists($this->uploadRoot);

        parent::tearDown();
    }

    public function test_public_action_handlers_reject_unauthorized_users_before_redirect(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;

        $this->assertUnauthorized(static function (): void {
            $_POST = [
                'cbt_unit_test_tab' => 'sync_rest',
                'cbt_checklist_scope' => 'smoke_tests',
                'e2e_base_url' => 'http://localhost/wordpress',
            ];
            \CBT_Admin_Test_Hub_Service::handle_save_settings();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = [
                'cbt_unit_test_tab' => 'sync_rest',
                'cbt_checklist_scope' => 'smoke_tests',
            ];
            \CBT_Admin_Test_Hub_Service::handle_clear_test_artifacts();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = [
                'cbt_unit_test_tab' => 'sync_rest',
                'cbt_checklist_scope' => 'smoke_tests',
            ];
            \CBT_Admin_Test_Hub_Service::handle_queue_flow_check_job();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = [
                'cbt_unit_test_tab' => 'sync_rest',
                'cbt_checklist_scope' => 'smoke_tests',
            ];
            \CBT_Admin_Test_Hub_Service::handle_refresh_runner_health();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = [
                'cbt_unit_test_tab' => 'sync_rest',
                'cbt_checklist_scope' => 'smoke_tests',
            ];
            \CBT_Admin_Test_Hub_Service::handle_check_e2e_readiness();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = ['cbt_flow_job_id' => 'flow-any'];
            \CBT_Admin_Test_Hub_Service::handle_retry_flow_check_job();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = ['cbt_flow_job_id' => 'flow-any'];
            \CBT_Admin_Test_Hub_Service::handle_cancel_flow_check_job();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = ['cbt_flow_job_id' => 'flow-any'];
            \CBT_Admin_Test_Hub_Service::handle_clear_flow_check_job();
        });

        $this->assertUnauthorized(static function (): void {
            $_POST = [
                'cbt_unit_test_tab' => 'sync_rest',
                'cbt_checklist_scope' => 'smoke_tests',
            ];
            \CBT_Admin_Test_Hub_Service::handle_repair_stuck_flow_check_jobs();
        });

        $this->assertUnauthorized(static function (): void {
            $_REQUEST = [
                'cbt_flow_job_id' => 'flow-any',
                'cbt_artifact_key' => 'artifact-key',
            ];
            \CBT_Admin_Test_Hub_Service::handle_download_test_hub_artifact();
        });
    }

    public function test_build_save_settings_action_result_saves_urls_and_redirect_args(): void
    {
        $result = $this->invokePrivate('build_save_settings_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'e2e_base_url' => "  http://localhost/wordpress  \n",
            'e2e_frontend_url' => "\thttp://localhost/wordpress/ujian  ",
        ]]);

        self::assertSame('sync_rest', $result['tab']);
        self::assertSame('smoke_tests', $result['scope']);
        self::assertSame('Pengaturan Playwright E2E berhasil disimpan.', $result['message']);
        self::assertSame('', $result['error']);
        self::assertSame([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ], $result['settings']);
        self::assertSame($result['settings'], \CBT_Admin_Test_Hub_Service::get_settings());
        self::assertStringContainsString('page=cbt-test-hub', (string) $result['redirect_url']);
        self::assertStringContainsString('cbt_unit_test_tab=sync_rest', (string) $result['redirect_url']);
        self::assertStringContainsString('cbt_checklist_scope=smoke_tests', (string) $result['redirect_url']);
        self::assertStringContainsString('cbt_msg=', (string) $result['redirect_url']);
    }

    public function test_start_global_unit_run_action_result_queues_batch_without_running_commands(): void
    {
        $result = $this->invokePrivate('build_start_global_unit_run_action_result', [[
            'cbt_unit_test_tab' => 'recovery_persistence',
            'cbt_checklist_scope' => 'unit_tests',
        ]]);

        self::assertSame('', $result['error']);
        self::assertStringContainsString('bertahap', (string) $result['message']);
        self::assertArrayHasKey('cbt_global_unit_run_token', $result['redirect_args']);

        $token = sanitize_key((string) $result['redirect_args']['cbt_global_unit_run_token']);
        self::assertNotSame('', $token);

        $state = get_transient('cbt_global_unit_test_run_state_' . $token);
        self::assertIsArray($state);
        self::assertSame('global_unit_tests_state', $state['type']);
        self::assertSame('queued', $state['status']);
        self::assertGreaterThan(0, (int) $state['total_commands']);
        self::assertSame(0, (int) $state['processed_commands']);
    }

    public function test_continue_global_unit_run_action_result_processes_one_command_and_finalizes(): void
    {
        $token = 'globalstep';
        set_transient('cbt_global_unit_test_run_state_' . $token, [
            'type' => 'global_unit_tests_state',
            'token' => $token,
            'status' => 'queued',
            'tab' => 'recovery_persistence',
            'scope' => 'unit_tests',
            'queue' => [
                [
                    'tab' => 'recovery_persistence',
                    'runner_label' => 'Run Tiny Suite',
                    'runner_description' => 'Tiny safe command for unit coverage.',
                    'label' => 'Tiny PHPUnit Output',
                    'command' => 'php -r ' . escapeshellarg('echo "OK (3 tests, 9 assertions)\n";'),
                ],
            ],
            'tabs' => [],
            'success' => true,
            'passed_count' => 0,
            'failed_count' => 0,
            'processed_commands' => 0,
            'total_commands' => 1,
            'current_label' => '',
            'started_at' => time(),
            'updated_at' => time(),
        ], 900);

        $result = $this->invokePrivate('build_continue_global_unit_run_action_result', [[
            'cbt_unit_test_tab' => 'recovery_persistence',
            'cbt_checklist_scope' => 'unit_tests',
            'cbt_global_unit_run_token' => $token,
        ]]);

        self::assertSame('', $result['error']);
        self::assertStringContainsString('Semua runner unit test global berhasil dijalankan.', (string) $result['message']);

        $finished = get_transient('cbt_global_unit_test_run_result_' . $token);
        self::assertIsArray($finished);
        self::assertSame('global_unit_tests', $finished['type']);
        self::assertSame('completed', $finished['status']);
        self::assertSame(1, (int) $finished['processed_commands']);
        self::assertSame(1, (int) $finished['total_commands']);
        self::assertSame(3, (int) $finished['summary']['passed_count']);
        self::assertFalse((bool) get_transient('cbt_global_unit_test_run_state_' . $token));
    }

    public function test_async_request_detector_accepts_fetch_header_or_form_marker(): void
    {
        $_REQUEST = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        self::assertFalse((bool) $this->invokePrivate('is_test_hub_async_request'));

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        self::assertTrue((bool) $this->invokePrivate('is_test_hub_async_request'));

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $_REQUEST = ['cbt_test_hub_async' => '1'];
        self::assertTrue((bool) $this->invokePrivate('is_test_hub_async_request'));

        $_REQUEST = ['cbt_test_hub_async' => '0'];
        self::assertFalse((bool) $this->invokePrivate('is_test_hub_async_request'));
    }

    public function test_clear_artifacts_action_result_reports_empty_state(): void
    {
        $result = $this->invokePrivate('build_clear_test_artifacts_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]]);

        self::assertSame('Belum ada artefak test yang perlu dibersihkan.', $result['message']);
        self::assertSame('', $result['error']);
    }

    public function test_clear_artifacts_action_result_deletes_existing_targets(): void
    {
        $testResults = $this->projectRoot . '/test-results';
        mkdir($testResults, 0777, true);
        file_put_contents($testResults . '/summary.json', '{}');

        $result = $this->invokePrivate('build_clear_test_artifacts_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]]);

        self::assertStringContainsString('Artefak test berhasil dibersihkan:', $result['message']);
        self::assertContains('Test Results', $result['deleted_labels']);
        self::assertDirectoryDoesNotExist($testResults);
    }

    public function test_clear_artifacts_action_result_blocks_when_flow_job_is_active(): void
    {
        $testResults = $this->projectRoot . '/test-results';
        mkdir($testResults, 0777, true);
        file_put_contents($testResults . '/summary.json', '{}');
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-active-cleanup',
            'status' => 'queued',
        ]));

        $result = $this->invokePrivate('build_clear_test_artifacts_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]]);

        self::assertSame('', $result['message']);
        self::assertStringContainsString('queued, running, atau cancelling', $result['error']);
        self::assertFileExists($testResults . '/summary.json');
    }

    public function test_refresh_runner_health_action_result_saves_ready_snapshot(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);

        $result = $this->invokePrivate('build_refresh_runner_health_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], $this->readyHealthProbeOverrides()]);
        $snapshot = \CBT_Admin_Test_Hub_Service::get_runner_health_snapshot();

        self::assertSame('sync_rest', $result['tab']);
        self::assertSame('smoke_tests', $result['scope']);
        self::assertSame('ready', (string) ($result['runner_health']['overall_status'] ?? ''));
        self::assertStringContainsString('Runner Health siap', (string) $result['message']);
        self::assertSame('ready', (string) ($snapshot['overall_status'] ?? ''));
        self::assertGreaterThanOrEqual(8, count((array) ($snapshot['checks'] ?? [])));
    }

    public function test_refresh_runner_health_action_result_reports_blocked_when_required_check_fails(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => '',
            'e2e_frontend_url' => '',
        ]);

        $overrides = $this->readyHealthProbeOverrides();
        $overrides['node_version'] = [
            'success' => true,
            'stdout' => 'v18.19.0',
            'stderr' => '',
            'exit_code' => 0,
        ];

        $result = $this->invokePrivate('build_refresh_runner_health_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], $overrides]);

        self::assertSame('blocked', (string) ($result['runner_health']['overall_status'] ?? ''));
        self::assertStringContainsString('BLOCKED', (string) $result['message']);
    }

    public function test_e2e_readiness_reports_blocked_when_settings_are_empty(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => '',
            'e2e_frontend_url' => '',
        ]);

        $result = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], [
            'admin_seed_user_exists' => true,
            'admin_seed_username' => 'cbtadmin',
            'fixture_catalog' => $this->readyFixtureCatalogOverride(),
        ]]);

        self::assertSame('blocked', (string) ($result['e2e_readiness']['overall_status'] ?? ''));
        self::assertStringContainsString('BLOCKED', (string) $result['message']);
        self::assertSame('blocked', (string) (\CBT_Admin_Test_Hub_Service::get_e2e_readiness_snapshot()['overall_status'] ?? ''));
    }

    public function test_e2e_readiness_reports_blocked_for_login_404_or_missing_marker(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);

        $notFound = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'wordpress_login_response' => [
                'code' => 404,
                'body' => '<h1>404 Not Found</h1>',
                'final_url' => 'http://localhost/wordpress/wp-login.php',
            ],
        ])]);
        $missingMarker = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'wordpress_login_response' => [
                'code' => 200,
                'body' => '<html>No login form here</html>',
                'final_url' => 'http://localhost/wordpress/wp-login.php',
            ],
        ])]);

        self::assertSame('blocked', (string) ($notFound['e2e_readiness']['overall_status'] ?? ''));
        self::assertStringContainsString('HTTP 404', (string) ($notFound['e2e_readiness']['checks'][1]['detail'] ?? ''));
        self::assertNotEmpty((array) ($notFound['e2e_readiness']['suggestions'] ?? []));
        self::assertSame('blocked', (string) ($missingMarker['e2e_readiness']['overall_status'] ?? ''));
        self::assertStringContainsString('Marker: id="user_login"', (string) ($missingMarker['e2e_readiness']['checks'][1]['detail'] ?? ''));
    }

    public function test_e2e_readiness_frontend_fallback_warning_and_missing_marker_blocked(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => '',
        ]);

        $fallback = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'cbt_frontend_response' => [
                'code' => 200,
                'body' => '<div id="cbt-exam-app"></div><script>var CBTExamFrontendConfig = {};</script>',
                'final_url' => 'http://localhost/wordpress',
            ],
        ])]);

        self::assertSame('warning', (string) ($fallback['e2e_readiness']['overall_status'] ?? ''));
        self::assertSame('warning', (string) ($fallback['e2e_readiness']['checks'][0]['status'] ?? ''));
        self::assertSame('http://localhost/wordpress', (string) ($fallback['e2e_readiness']['checks'][2]['url'] ?? ''));

        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);
        $missingMarker = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'cbt_frontend_response' => [
                'code' => 200,
                'body' => '<main>WordPress page without shortcode</main>',
                'final_url' => 'http://localhost/wordpress/ujian',
            ],
        ])]);

        self::assertSame('blocked', (string) ($missingMarker['e2e_readiness']['overall_status'] ?? ''));
        self::assertStringContainsString('shortcode/frontend CBT', (string) ($missingMarker['e2e_readiness']['checks'][2]['message'] ?? ''));
    }

    public function test_e2e_readiness_accepts_current_frontend_boot_shell_marker(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);

        $result = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'cbt_frontend_response' => [
                'code' => 200,
                'body' => '<div id="cbt-exam-app"></div><script>var CBTExamFrontendConfig = {};</script>',
                'final_url' => 'http://localhost/wordpress/ujian',
            ],
        ])]);

        self::assertSame('ready', (string) ($result['e2e_readiness']['overall_status'] ?? ''));
        self::assertSame('ready', (string) ($result['e2e_readiness']['checks'][2]['status'] ?? ''));
        self::assertStringContainsString('id="cbt-exam-app"', (string) ($result['e2e_readiness']['checks'][2]['detail'] ?? ''));
    }

    public function test_e2e_readiness_frontend_timeout_uses_local_shortcode_fallback_as_warning(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress',
        ]);

        $result = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'cbt_frontend_response' => [
                'code' => 0,
                'body' => '',
                'error' => 'cURL error 28: Connection timed out after 3002 milliseconds',
                'final_url' => 'http://localhost/wordpress',
            ],
            'frontend_local_shortcode_detected' => true,
        ])]);

        self::assertSame('warning', (string) ($result['e2e_readiness']['overall_status'] ?? ''));
        self::assertSame('warning', (string) ($result['e2e_readiness']['checks'][2]['status'] ?? ''));
        self::assertStringContainsString('HTTP check public dari server belum stabil', (string) ($result['e2e_readiness']['checks'][2]['message'] ?? ''));
        self::assertStringContainsString('Local fallback', (string) ($result['e2e_readiness']['checks'][2]['detail'] ?? ''));
        self::assertStringNotContainsString('"status":"blocked"', wp_json_encode($result['e2e_readiness']['checks'][2] ?? []));
    }

    public function test_e2e_readiness_seed_and_fixture_failures_block_environment(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);

        $result = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], array_merge($this->readyE2EReadinessProbeOverrides(), [
            'admin_seed_user_exists' => false,
            'fixture_catalog' => [
                'users' => [
                    'primary_student' => true,
                ],
                'fixtures' => [
                    'import_preview' => true,
                ],
            ],
        ])]);

        self::assertSame('blocked', (string) ($result['e2e_readiness']['overall_status'] ?? ''));
        self::assertSame('blocked', (string) ($result['e2e_readiness']['checks'][3]['status'] ?? ''));
        self::assertStringContainsString('Missing users: admin_seed', (string) ($result['e2e_readiness']['checks'][4]['detail'] ?? ''));
        self::assertStringContainsString('question_runtime', (string) ($result['e2e_readiness']['checks'][4]['detail'] ?? ''));
    }

    public function test_e2e_readiness_reports_ready_when_all_checks_pass(): void
    {
        \CBT_Admin_Test_Hub_Service::save_settings([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ]);

        $result = $this->invokePrivate('build_check_e2e_readiness_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], $this->readyE2EReadinessProbeOverrides()]);
        $snapshot = \CBT_Admin_Test_Hub_Service::get_e2e_readiness_snapshot();

        self::assertSame('ready', (string) ($result['e2e_readiness']['overall_status'] ?? ''));
        self::assertStringContainsString('E2E Readiness siap', (string) $result['message']);
        self::assertSame('ready', (string) ($snapshot['overall_status'] ?? ''));
        self::assertCount(5, (array) ($snapshot['checks'] ?? []));
    }

    public function test_queue_flow_check_action_result_rejects_unit_scope(): void
    {
        $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'unit_tests',
        ], false]);

        self::assertSame('', $result['message']);
        self::assertStringContainsString('Queue async hanya tersedia', $result['error']);
    }

    public function test_queue_flow_check_action_result_rejects_invalid_item_index(): void
    {
        $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_checklist_item_index' => '9999',
        ], false]);

        self::assertSame('', $result['message']);
        self::assertStringContainsString('Task flow check yang dipilih tidak valid', $result['error']);
    }

    public function test_queue_flow_check_action_result_writes_single_job_without_starting_worker(): void
    {
        $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_checklist_item_index' => '0',
        ], false]);
        $jobs = $this->readFlowJobs();

        self::assertSame('', $result['error']);
        self::assertSame(1, (int) $result['queued_count']);
        self::assertStringContainsString('Flow check berhasil diantrikan', $result['message']);
        self::assertCount(1, $jobs);
        self::assertSame('queued', (string) $jobs[0]['status']);
        self::assertSame('sync_rest', (string) $jobs[0]['tab']);
        self::assertSame('smoke_tests', (string) $jobs[0]['scope']);
        self::assertSame(0, (int) $jobs[0]['item_index']);
        self::assertStringContainsString('run-sync-rest-flow.mjs', (string) $jobs[0]['command']);
        self::assertSame(0, (int) $jobs[0]['started_at']);
    }

    public function test_queue_flow_check_action_result_queues_all_eligible_and_skips_active_jobs(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-existing-sync-rest-0',
            'item_index' => 0,
            'status' => 'running',
            'started_at' => time(),
            'heartbeat_at' => time(),
        ]));

        $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], false]);
        $jobs = $this->readFlowJobs();

        self::assertSame('', $result['error']);
        self::assertGreaterThan(1, (int) $result['queued_count']);
        self::assertNotEmpty($result['skipped_labels']);
        self::assertStringContainsString('Beberapa task dilewati', $result['message']);
        self::assertGreaterThanOrEqual(((int) $result['queued_count']) + 1, count($jobs));
    }

    public function test_every_flow_check_item_across_tabs_queues_single_safe_job_without_starting_worker(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');

        foreach ($tabs as $tabKey => $tab) {
            $smokeItems = array_values((array) ($tab['smoke_tests'] ?? []));
            if (empty($smokeItems)) {
                continue;
            }

            foreach ($smokeItems as $itemIndex => $itemDefinition) {
                $this->removeDirectoryIfExists($this->flowResultsRoot());

                $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
                    'cbt_unit_test_tab' => (string) $tabKey,
                    'cbt_checklist_scope' => 'smoke_tests',
                    'cbt_checklist_item_index' => (string) $itemIndex,
                ], false]);
                $jobs = $this->readFlowJobs();
                $context = (string) $tabKey . '/smoke_tests#' . (string) $itemIndex . ' ' . (string) ($itemDefinition['label'] ?? '');

                self::assertSame('', (string) ($result['error'] ?? ''), $context . ' should queue without an action error.');
                self::assertSame(1, (int) ($result['queued_count'] ?? 0), $context . ' should queue exactly one job.');
                self::assertCount(1, $jobs, $context . ' should persist exactly one job file.');

                $job = $jobs[0];
                self::assertSame((string) $tabKey, (string) ($job['tab'] ?? ''), $context . ' job tab mismatch.');
                self::assertSame('smoke_tests', (string) ($job['scope'] ?? ''), $context . ' job scope mismatch.');
                self::assertSame($itemIndex, (int) ($job['item_index'] ?? -1), $context . ' job item index mismatch.');
                self::assertSame('queued', (string) ($job['status'] ?? ''), $context . ' job should remain queued in unit tests.');
                self::assertSame(0, (int) ($job['started_at'] ?? -1), $context . ' should not start the worker in unit tests.');

                $expectedLabels = $this->invokePrivate('normalize_unit_test_note_list', [$itemDefinition['runner_commands'] ?? []]);
                $actualLabels = array_values(array_map(
                    static fn (array $command): string => (string) ($command['label'] ?? ''),
                    (array) ($job['commands'] ?? [])
                ));
                self::assertSame($expectedLabels, $actualLabels, $context . ' job commands should match checklist runner labels.');
                self::assertMatchesRegularExpression(
                    '/^node tests\/e2e\/run-[a-z0-9-]+\.mjs --grep "/',
                    (string) ($job['command'] ?? ''),
                    $context . ' should queue a targeted Playwright flow command.'
                );
            }
        }
    }

    public function test_queue_all_flow_check_items_for_every_tab_creates_one_job_per_smoke_item(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');

        foreach ($tabs as $tabKey => $tab) {
            $this->removeDirectoryIfExists($this->flowResultsRoot());

            $smokeItems = array_values((array) ($tab['smoke_tests'] ?? []));
            if (empty($smokeItems)) {
                continue;
            }
            $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
                'cbt_unit_test_tab' => (string) $tabKey,
                'cbt_checklist_scope' => 'smoke_tests',
            ], false]);
            $jobs = $this->readFlowJobs();
            $context = (string) $tabKey . '/smoke_tests queue all';

            self::assertSame('', (string) ($result['error'] ?? ''), $context . ' should queue without an action error.');
            self::assertSame(count($smokeItems), (int) ($result['queued_count'] ?? -1), $context . ' queued count should match smoke item count.');
            self::assertSame([], (array) ($result['skipped_labels'] ?? []), $context . ' should not skip fresh smoke items.');
            self::assertCount(count($smokeItems), $jobs, $context . ' should persist one job per smoke item.');

            $queuedIndexes = array_map(static fn (array $job): int => (int) ($job['item_index'] ?? -1), $jobs);
            sort($queuedIndexes);
            self::assertSame(range(0, count($smokeItems) - 1), $queuedIndexes, $context . ' should queue every smoke item index exactly once.');
        }
    }

    public function test_queue_flow_check_action_result_reports_no_eligible_items_when_all_are_active(): void
    {
        $firstResult = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], false]);

        self::assertGreaterThan(0, (int) ($firstResult['queued_count'] ?? 0));

        $secondResult = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ], false]);

        self::assertSame('', $secondResult['message']);
        self::assertStringContainsString('Task flow check sudah sedang berjalan', $secondResult['error']);
        self::assertNotEmpty($secondResult['skipped_labels']);
    }

    public function test_queue_flow_check_action_result_requeues_item_after_missing_worker_pid_job_is_normalized(): void
    {
        $now = time();
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-orphan-running-without-pid',
            'item_index' => 0,
            'status' => 'running',
            'created_at' => $now - 90,
            'started_at' => $now - 60,
            'heartbeat_at' => $now,
            'worker_pid' => 0,
            'active_child_pid' => 0,
        ]));

        $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_checklist_item_index' => '0',
        ], false]);
        $jobs = $this->readFlowJobs();
        $jobsById = [];
        foreach ($jobs as $job) {
            $jobsById[(string) ($job['job_id'] ?? '')] = $job;
        }

        self::assertSame('', (string) ($result['error'] ?? ''));
        self::assertSame(1, (int) ($result['queued_count'] ?? 0));
        self::assertCount(2, $jobs);
        self::assertSame('failed', (string) ($jobsById['flow-orphan-running-without-pid']['status'] ?? ''));
        self::assertSame('interrupted', (string) ($jobsById['flow-orphan-running-without-pid']['failure_kind'] ?? ''));
        self::assertStringContainsString('worker PID kosong terlalu lama', (string) ($jobsById['flow-orphan-running-without-pid']['stderr'] ?? ''));
    }

    public function test_queue_flow_check_action_result_preserves_fifo_start_candidate_when_existing_job_is_queued(): void
    {
        $now = time();
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-existing-old-queued',
            'item_index' => 4,
            'status' => 'queued',
            'created_at' => $now - 30,
        ]));

        $result = $this->invokePrivate('build_queue_flow_check_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_checklist_item_index' => '0',
        ], false]);
        $jobs = $this->readFlowJobs();

        self::assertSame('', (string) ($result['error'] ?? ''));
        self::assertSame(1, (int) ($result['queued_count'] ?? 0));
        self::assertCount(2, $jobs);
        self::assertSame(
            'flow-existing-old-queued',
            (string) $this->invokePrivate('resolve_next_queued_flow_job_id', [$jobs]),
            'Flow Check worker start candidate must remain the oldest queued job, not the newest job created by this action.'
        );
    }

    public function test_retry_flow_check_action_result_creates_new_job_for_terminal_job(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-retry-source',
            'item_index' => 0,
            'status' => 'failed',
            'finished_at' => time(),
            'failure_summary' => 'First run failed.',
        ]));

        $result = $this->invokePrivate('build_retry_flow_check_job_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_flow_job_id' => 'flow-retry-source',
        ], false]);
        $jobs = $this->readFlowJobs();
        $newJobs = array_values(array_filter($jobs, static fn(array $job): bool => (string) ($job['job_id'] ?? '') !== 'flow-retry-source'));

        self::assertSame('', $result['error']);
        self::assertStringContainsString('Retry flow check berhasil diantrikan', (string) $result['message']);
        self::assertCount(1, $newJobs);
        self::assertSame('queued', (string) ($newJobs[0]['status'] ?? ''));
        self::assertSame('flow-retry-source', (string) ($newJobs[0]['retry_of_job_id'] ?? ''));
    }

    public function test_retry_flow_check_action_result_rejects_active_job(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-retry-active',
            'status' => 'running',
            'started_at' => time(),
            'heartbeat_at' => time(),
        ]));

        $result = $this->invokePrivate('build_retry_flow_check_job_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_flow_job_id' => 'flow-retry-active',
        ], false]);

        self::assertSame('', $result['message']);
        self::assertStringContainsString('belum bisa di-retry', (string) $result['error']);
    }

    public function test_cancel_flow_check_action_result_marks_queued_job_cancelled(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-cancel-queued',
            'status' => 'queued',
        ]));

        $result = $this->invokePrivate('build_cancel_flow_check_job_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_flow_job_id' => 'flow-cancel-queued',
        ], false]);
        $jobs = $this->readFlowJobs();

        self::assertSame('', $result['error']);
        self::assertSame('cancelled', (string) ($result['job']['status'] ?? ''));
        self::assertSame('cancelled', (string) ($jobs[0]['status'] ?? ''));
        self::assertGreaterThan(0, (int) ($jobs[0]['cancel_requested_at'] ?? 0));
    }

    public function test_cancel_flow_check_action_result_marks_running_job_cancelling_without_process_kill_in_unit_test(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-cancel-running',
            'status' => 'running',
            'started_at' => time(),
            'heartbeat_at' => time(),
            'worker_pid' => getmypid(),
        ]));

        $result = $this->invokePrivate('build_cancel_flow_check_job_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_flow_job_id' => 'flow-cancel-running',
        ], false]);

        self::assertSame('', $result['error']);
        self::assertSame('cancelling', (string) ($result['job']['status'] ?? ''));
        self::assertGreaterThan(0, (int) ($result['job']['cancel_requested_at'] ?? 0));
        self::assertSame('cancelled', (string) ($result['job']['failure_kind'] ?? ''));
    }

    public function test_clear_flow_check_action_result_deletes_terminal_jobs_and_safe_artifacts_for_item(): void
    {
        $artifactRoot = $this->flowJobDirectory() . '/flow-clear-source-artifacts';
        mkdir($artifactRoot, 0777, true);
        file_put_contents($artifactRoot . '/trace.zip', 'trace');
        $logPath = $this->flowJobDirectory() . '/flow-clear-source.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }
        file_put_contents($logPath, 'log');

        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-clear-source',
            'item_index' => 0,
            'status' => 'failed',
            'finished_at' => time(),
            'log_path' => $logPath,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-clear-old',
            'item_index' => 0,
            'status' => 'cancelled',
            'finished_at' => time() - 20,
        ]));

        $result = $this->invokePrivate('build_clear_flow_check_job_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_flow_job_id' => 'flow-clear-source',
        ]]);

        self::assertSame('', $result['error']);
        self::assertSame(2, (int) $result['deleted_count']);
        self::assertStringContainsString('berhasil dibersihkan', (string) $result['message']);
        self::assertFileDoesNotExist($this->flowJobDirectory() . '/flow-clear-source.json');
        self::assertDirectoryDoesNotExist($artifactRoot);
        self::assertFileDoesNotExist($logPath);
    }

    public function test_clear_flow_check_action_result_rejects_active_job_for_same_item(): void
    {
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-clear-terminal',
            'item_index' => 0,
            'status' => 'failed',
            'finished_at' => time(),
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-clear-active',
            'item_index' => 0,
            'status' => 'cancelling',
            'created_at' => time() + 10,
            'worker_pid' => getmypid(),
        ]));

        $result = $this->invokePrivate('build_clear_flow_check_job_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_flow_job_id' => 'flow-clear-terminal',
        ]]);

        self::assertSame('', $result['message']);
        self::assertStringContainsString('masih aktif', (string) $result['error']);
        self::assertFileExists($this->flowJobDirectory() . '/flow-clear-terminal.json');
    }

    public function test_repair_stuck_flow_check_jobs_marks_stale_interrupted_and_cancelled_without_touching_queued_or_terminal(): void
    {
        $now = time();
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-repair-stale',
            'item_index' => 0,
            'status' => 'running',
            'created_at' => $now - 300,
            'started_at' => $now - 250,
            'heartbeat_at' => $now - 200,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-repair-no-pid',
            'item_index' => 1,
            'status' => 'running',
            'created_at' => $now - 80,
            'started_at' => $now - 70,
            'heartbeat_at' => $now,
            'worker_pid' => 0,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-repair-cancelling',
            'item_index' => 2,
            'status' => 'cancelling',
            'created_at' => $now - 40,
            'started_at' => $now - 35,
            'heartbeat_at' => $now,
            'worker_pid' => 999999,
            'active_child_pid' => 999998,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-repair-queued',
            'item_index' => 3,
            'status' => 'queued',
            'created_at' => $now - 10,
        ]));
        $this->writeFlowJob($this->baseJob([
            'job_id' => 'flow-repair-passed',
            'item_index' => 4,
            'status' => 'passed',
            'created_at' => $now - 5,
            'finished_at' => $now - 4,
        ]));

        $result = $this->invokePrivate('build_repair_stuck_flow_check_jobs_action_result', [[
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
        ]]);
        $jobsById = [];
        foreach ($this->readFlowJobs() as $job) {
            $jobsById[(string) ($job['job_id'] ?? '')] = $job;
        }

        self::assertSame('', (string) $result['error']);
        self::assertStringContainsString('3 repaired, 1 still active, 1 terminal', (string) $result['message']);
        self::assertSame(3, (int) ($result['repair_summary']['repaired_count'] ?? 0));
        self::assertSame('failed', (string) ($jobsById['flow-repair-stale']['status'] ?? ''));
        self::assertSame('stale', (string) ($jobsById['flow-repair-stale']['failure_kind'] ?? ''));
        self::assertSame('failed', (string) ($jobsById['flow-repair-no-pid']['status'] ?? ''));
        self::assertSame('interrupted', (string) ($jobsById['flow-repair-no-pid']['failure_kind'] ?? ''));
        self::assertStringContainsString('worker PID kosong terlalu lama', (string) ($jobsById['flow-repair-no-pid']['stderr'] ?? ''));
        self::assertSame('cancelled', (string) ($jobsById['flow-repair-cancelling']['status'] ?? ''));
        self::assertSame('queued', (string) ($jobsById['flow-repair-queued']['status'] ?? ''));
        self::assertSame('passed', (string) ($jobsById['flow-repair-passed']['status'] ?? ''));
    }

    public function test_download_artifact_result_resolves_only_safe_context_keys(): void
    {
        $jobId = 'flow-download-safe';
        $jobDir = $this->flowJobDirectory();
        $artifactRoot = $jobDir . '/' . $jobId . '-artifacts';
        mkdir($artifactRoot, 0777, true);
        file_put_contents($jobDir . '/' . $jobId . '.log', "first\nsecond");
        file_put_contents($artifactRoot . '/trace.zip', 'trace');
        $outsidePath = sys_get_temp_dir() . '/cbt-test-hub-download-outside-' . getmypid() . '.log';
        file_put_contents($outsidePath, 'outside');

        try {
            $this->writeFlowJob($this->baseJob([
                'job_id' => $jobId,
                'status' => 'failed',
                'finished_at' => time(),
                'log_path' => $outsidePath,
            ]));
            $job = $this->invokePrivate('find_flow_check_job_by_id', [$jobId]);
            $context = $this->invokePrivate('build_flow_job_artifact_context', [$job]);

            self::assertTrue((bool) ($context['has_any'] ?? false));
            self::assertSame($jobId . '.log', (string) ($context['log']['name'] ?? ''));
            self::assertStringContainsString('first', (string) ($context['log']['preview'] ?? ''));
            self::assertCount(1, (array) ($context['artifacts'] ?? []));

            $logKey = (string) ($context['log']['key'] ?? '');
            $artifactKey = (string) ($context['artifacts'][0]['key'] ?? '');
            $logResult = $this->invokePrivate('build_download_test_hub_artifact_result', [[
                'cbt_flow_job_id' => $jobId,
                'cbt_artifact_key' => $logKey,
            ]]);
            $artifactResult = $this->invokePrivate('build_download_test_hub_artifact_result', [[
                'cbt_flow_job_id' => $jobId,
                'cbt_artifact_key' => $artifactKey,
            ]]);
            $badResult = $this->invokePrivate('build_download_test_hub_artifact_result', [[
                'cbt_flow_job_id' => $jobId,
                'cbt_artifact_key' => 'not-a-real-key',
            ]]);

            self::assertTrue((bool) ($logResult['success'] ?? false));
            self::assertSame($jobDir . '/' . $jobId . '.log', (string) ($logResult['file_path'] ?? ''));
            self::assertTrue((bool) ($artifactResult['success'] ?? false));
            self::assertSame($artifactRoot . '/trace.zip', (string) ($artifactResult['file_path'] ?? ''));
            self::assertFalse((bool) ($badResult['success'] ?? true));
        } finally {
            @unlink($outsidePath);
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

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readFlowJobs(): array
    {
        return (array) $this->invokePrivate('read_flow_check_jobs');
    }

    private function flowResultsRoot(): string
    {
        return (string) $this->invokePrivate('playwright_results_root_path');
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
            'job_id' => 'flow-test-' . md5(serialize($overrides)),
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
            'log_path' => $this->flowJobDirectory() . '/flow-test.log',
        ], $overrides);
    }

    /**
     * @return array<string,mixed>
     */
    private function readyHealthProbeOverrides(): array
    {
        return [
            'proc_open_available' => true,
            'shell' => [
                'success' => true,
                'stdout' => '/usr/bin/bash',
                'stderr' => '',
                'exit_code' => 0,
            ],
            'node_version' => [
                'success' => true,
                'stdout' => 'v20.11.1',
                'stderr' => '',
                'exit_code' => 0,
            ],
            'npm_version' => [
                'success' => true,
                'stdout' => '10.2.4',
                'stderr' => '',
                'exit_code' => 0,
            ],
            'playwright_installed' => true,
            'chromium_installed' => true,
            'job_directory_ready' => true,
            'e2e_base_url_response' => [
                'code' => 200,
                'error' => '',
            ],
            'e2e_frontend_url_response' => [
                'code' => 200,
                'error' => '',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readyE2EReadinessProbeOverrides(): array
    {
        return [
            'wordpress_login_response' => [
                'code' => 200,
                'body' => '<form id="loginform"><input id="user_login" /></form>',
                'final_url' => 'http://localhost/wordpress/wp-login.php',
            ],
            'cbt_frontend_response' => [
                'code' => 200,
                'body' => '<div id="cbt-exam-app"></div><script>var CBTExamFrontendConfig = {};</script>',
                'final_url' => 'http://localhost/wordpress/ujian',
            ],
            'admin_seed_user_exists' => true,
            'admin_seed_username' => 'cbtadmin',
            'fixture_catalog' => $this->readyFixtureCatalogOverride(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readyFixtureCatalogOverride(): array
    {
        return [
            'users' => [
                'primary_student' => true,
                'admin_seed' => true,
            ],
            'fixtures' => [
                'import_preview' => true,
                'question_runtime' => true,
                'result_full' => true,
                'result_essay' => true,
                'result_restricted' => true,
            ],
        ];
    }

    private function assertUnauthorized(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected wp_die Unauthorized exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unauthorized', $exception->getMessage());
        }
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
