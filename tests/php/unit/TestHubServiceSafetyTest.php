<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class TestHubServiceSafetyTest extends TestCase
{
    private string $projectRoot = '';
    private string $uploadRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-test-hub-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-maintenance-seed-service.php';

        $this->projectRoot = dirname(__DIR__, 3);
        $this->uploadRoot = sys_get_temp_dir() . '/cbt-test-hub-service-safety-uploads-' . getmypid();
        $GLOBALS['cbt_test_wp_upload_dir'] = $this->uploadRoot;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $this->removeDirectoryIfExists($this->projectRoot . '/playwright-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/test-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/coverage');
        $this->removeDirectoryIfExists($this->uploadRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryIfExists($this->projectRoot . '/playwright-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/test-results');
        $this->removeDirectoryIfExists($this->projectRoot . '/coverage');
        $this->removeDirectoryIfExists($this->uploadRoot);

        parent::tearDown();
    }

    public function test_settings_are_sanitized_saved_and_exported_to_runner_environment(): void
    {
        $settings = \CBT_Admin_Test_Hub_Service::sanitize_settings_input([
            'e2e_base_url' => "  http://localhost/wordpress  \n",
            'e2e_frontend_url' => "\thttp://localhost/wordpress/ujian  ",
            'ignored' => ['array input must be ignored'],
        ]);

        self::assertSame([
            'e2e_base_url' => 'http://localhost/wordpress',
            'e2e_frontend_url' => 'http://localhost/wordpress/ujian',
        ], $settings);

        \CBT_Admin_Test_Hub_Service::save_settings($settings);
        self::assertSame($settings, \CBT_Admin_Test_Hub_Service::get_settings());

        $environment = $this->invokePrivate('build_runner_environment');

        self::assertSame('http://localhost/wordpress', $environment['CBT_E2E_BASE_URL'] ?? '');
        self::assertSame('http://localhost/wordpress', $environment['CBT_E2E_WP_BASE_URL'] ?? '');
        self::assertSame('http://localhost/wordpress/ujian', $environment['CBT_E2E_FRONTEND_URL'] ?? '');
        self::assertStringEndsWith('/.playwright-browsers', (string) ($environment['PLAYWRIGHT_BROWSERS_PATH'] ?? ''));
        self::assertStringContainsString('/playwright-results/admin', (string) ($environment['PLAYWRIGHT_OUTPUT_DIR'] ?? ''));
    }

    public function test_settings_and_normalizers_tolerate_non_scalar_input_without_warning(): void
    {
        $settings = \CBT_Admin_Test_Hub_Service::sanitize_settings_input([
            'e2e_base_url' => ['http://bad.local'],
            'e2e_frontend_url' => new \stdClass(),
        ]);

        self::assertSame('', $settings['e2e_base_url']);
        self::assertSame('', $settings['e2e_frontend_url']);
        self::assertSame('recovery_persistence', \CBT_Admin_Test_Hub_Service::normalize_unit_test_tab(['bad']));
        self::assertSame('unit_tests', \CBT_Admin_Test_Hub_Service::normalize_unit_test_scope(['smoke_tests']));
    }

    public function test_context_sanitizes_query_and_exposes_consistent_tab_shape(): void
    {
        set_transient('cbt_global_unit_test_run_result_globaltoken', [
            'type' => 'global_unit_tests',
            'success' => true,
            'executed_at' => 123,
            'label' => 'Global <b>Run</b>',
            'summary' => [
                'passed_count' => 7,
                'failed_count' => 0,
                'total_count' => 7,
            ],
            'tabs' => [],
        ], 900);

        $context = \CBT_Admin_Test_Hub_Service::build_unit_test_context([
            'cbt_msg' => "  <b>Saved</b>\nOK  ",
            'cbt_err' => "<script>alert(1)</script> Broken",
            'cbt_unit_test_tab' => 'sync_rest',
            'cbt_checklist_scope' => 'smoke_tests',
            'cbt_global_unit_run_token' => 'globaltoken',
        ]);

        self::assertSame('Saved OK', $context['notice']);
        self::assertSame('alert(1) Broken', $context['error']);
        self::assertSame('sync_rest', $context['active_unit_test_tab']);
        self::assertSame('smoke_tests', $context['active_checklist_scope']);
        self::assertTrue((bool) $context['global_unit_run_available']);
        self::assertSame(7, (int) $context['global_unit_run_summary']['passed_count']);
        self::assertGreaterThan(0, (int) $context['unit_test_area_count']);
        self::assertGreaterThan(0, (int) $context['unit_test_total_checklist_items']);

        foreach ((array) $context['unit_test_tabs'] as $tabKey => $tab) {
            self::assertIsString($tabKey);
            self::assertArrayHasKey('unit_tests', $tab);
            self::assertArrayHasKey('smoke_tests', $tab);
            self::assertArrayHasKey('runners', $tab);
            self::assertArrayHasKey('unit_tests', $tab['runners']);
            self::assertArrayHasKey('smoke_tests', $tab['runners']);

            foreach (['unit_tests', 'smoke_tests'] as $scope) {
                foreach ((array) $tab[$scope] as $index => $item) {
                    self::assertSame((int) $index, (int) ($item['item_index'] ?? -1));
                    self::assertNotSame('', (string) ($item['detail_id'] ?? ''));
                    self::assertNotEmpty((array) ($item['process_steps'] ?? []));
                    self::assertIsBool((bool) ($item['can_run_task'] ?? false));
                }
            }
        }
    }

    public function test_runner_command_labels_used_by_checklist_items_are_defined(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');

        foreach ($tabs as $tabKey => $tab) {
            foreach (['unit_tests', 'smoke_tests'] as $scope) {
                $commands = isset($runners[$tabKey][$scope]['commands']) && is_array($runners[$tabKey][$scope]['commands'])
                    ? (array) $runners[$tabKey][$scope]['commands']
                    : [];
                $labels = [];
                foreach ($commands as $command) {
                    if (is_array($command) && trim((string) ($command['label'] ?? '')) !== '') {
                        $labels[(string) $command['label']] = true;
                    }
                }

                foreach ((array) ($tab[$scope] ?? []) as $item) {
                    $runnerCommands = $this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]);
                    foreach ($runnerCommands as $runnerCommand) {
                        self::assertArrayHasKey(
                            $runnerCommand,
                            $labels,
                            sprintf('Runner command "%s" referenced by %s/%s is not defined.', $runnerCommand, (string) $tabKey, $scope)
                        );
                    }
                }
            }
        }
    }

    public function test_every_runner_command_is_referenced_by_a_checklist_item(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');

        foreach ($runners as $tabKey => $runner) {
            foreach (['unit_tests', 'smoke_tests'] as $scope) {
                $definedLabels = [];
                foreach ((array) ($runner[$scope]['commands'] ?? []) as $commandDefinition) {
                    if (!is_array($commandDefinition)) {
                        continue;
                    }

                    $label = trim((string) ($commandDefinition['label'] ?? ''));
                    if ($label !== '') {
                        $definedLabels[$label] = true;
                    }
                }

                $referencedLabels = [];
                foreach ((array) ($tabs[$tabKey][$scope] ?? []) as $itemDefinition) {
                    if (!is_array($itemDefinition)) {
                        continue;
                    }

                    foreach ($this->invokePrivate('normalize_unit_test_note_list', [$itemDefinition['runner_commands'] ?? []]) as $runnerCommand) {
                        $referencedLabels[$runnerCommand] = true;
                    }
                }

                foreach (array_keys($definedLabels) as $label) {
                    self::assertArrayHasKey(
                        $label,
                        $referencedLabels,
                        sprintf('Runner command "%s" in %s/%s is stale because no checklist item references it.', $label, (string) $tabKey, $scope)
                    );
                }
            }
        }
    }

    public function test_runner_definitions_are_current_safe_and_resolvable(): void
    {
        $expectedTabs = [
            'recovery_persistence',
            'sync_rest',
            'auth_session',
            'timer_lifecycle',
            'question_runtime',
            'result_scoring',
            'import_preview',
            'security_log_observability',
            'supervisor_proctoring',
            'exam_preflight_availability',
            'scoring_grading',
            'update_health',
            'cache_redis',
            'submit_finalization',
            'security_event_pipeline',
            'admin_exam_management',
            'attempt_runtime_envelope',
            'app_shell_bootstrap',
            'login_student_profile',
            'developer_setup_tooling',
        ];
        $tabsWithSmokeTests = [
            'recovery_persistence',
            'sync_rest',
            'auth_session',
            'timer_lifecycle',
            'question_runtime',
            'result_scoring',
            'import_preview',
            'security_log_observability',
        ];
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');

        self::assertSame($expectedTabs, array_keys((array) $tabs));
        self::assertSame($expectedTabs, array_keys((array) $runners));

        $displayText = implode("\n", $this->collectDisplayText([$tabs, $runners]));
        self::assertStringContainsString('Result & Export', $displayText);
        self::assertStringNotContainsString('Result & Scoring', $displayText);
        self::assertStringNotContainsString('Result & Scoring flow check', (string) file_get_contents($this->projectRoot . '/tests/e2e/result-scoring.spec.js'));
        self::assertStringNotContainsString('suiteTitle: \'Result & Scoring\'', (string) file_get_contents($this->projectRoot . '/tests/e2e/run-result-scoring-flow.mjs'));

        foreach ($expectedTabs as $tabKey) {
            $tab = (array) ($tabs[$tabKey] ?? []);
            $runner = (array) ($runners[$tabKey] ?? []);
            self::assertNotSame('', trim((string) ($tab['label'] ?? '')), $tabKey . ' label must be set.');
            self::assertNotSame('', trim((string) ($tab['summary'] ?? '')), $tabKey . ' summary must be set.');

            $scopesToCheck = in_array($tabKey, $tabsWithSmokeTests, true)
                ? ['unit_tests', 'smoke_tests']
                : ['unit_tests'];
            foreach ($scopesToCheck as $scope) {
                self::assertNotEmpty((array) ($tab[$scope] ?? []), $tabKey . '/' . $scope . ' checklist must not be empty.');
                self::assertNotEmpty((array) ($runner[$scope]['commands'] ?? []), $tabKey . '/' . $scope . ' runner commands must not be empty.');

                $seenCommandLabels = [];
                foreach ((array) ($runner[$scope]['commands'] ?? []) as $commandDefinition) {
                    self::assertIsArray($commandDefinition);
                    $label = trim((string) ($commandDefinition['label'] ?? ''));
                    $command = trim((string) ($commandDefinition['command'] ?? ''));
                    self::assertNotSame('', $label, $tabKey . '/' . $scope . ' command label must be set.');
                    self::assertArrayNotHasKey($label, $seenCommandLabels, $tabKey . '/' . $scope . ' command label must be unique.');
                    $seenCommandLabels[$label] = true;
                    $this->assertRunnerCommandIsSafeAndResolvable($tabKey, $scope, $label, $command);
                }
            }
        }
    }

    public function test_unit_test_inventory_discovers_all_phpunit_and_vitest_files(): void
    {
        $inventory = $this->invokePrivate('get_unit_test_inventory');
        $inventoryPaths = array_map(static fn (array $item): string => (string) ($item['path'] ?? ''), (array) $inventory);
        sort($inventoryPaths);

        $expectedPaths = [];
        foreach ([
            glob($this->projectRoot . '/tests/php/unit/*Test.php') ?: [],
            glob($this->projectRoot . '/tests/js/unit/*.test.js') ?: [],
        ] as $matches) {
            foreach ($matches as $path) {
                $expectedPaths[] = str_replace($this->projectRoot . '/', '', wp_normalize_path((string) $path));
            }
        }
        $expectedPaths = array_values(array_unique($expectedPaths));
        sort($expectedPaths);

        self::assertSame($expectedPaths, $inventoryPaths);
        self::assertNotEmpty($inventory);

        foreach ((array) $inventory as $item) {
            self::assertNotSame('', (string) ($item['id'] ?? ''));
            self::assertContains((string) ($item['type'] ?? ''), ['php', 'js']);
            self::assertContains((string) ($item['mapping_status'] ?? ''), ['curated', 'auto_mapped']);
            self::assertNotSame('', (string) ($item['mapped_tab_label'] ?? ''));
            self::assertNotSame('', (string) ($item['runner_label'] ?? ''));
            self::assertNotSame('', (string) ($item['command'] ?? ''));
        }
    }

    public function test_unit_test_inventory_commands_are_safe_and_resolvable(): void
    {
        foreach ((array) $this->invokePrivate('get_unit_test_inventory') as $item) {
            $label = (string) ($item['runner_label'] ?? 'Inventory Command');
            $command = (string) ($item['command'] ?? '');
            self::assertMatchesRegularExpression(
                '/^(?:\.\/node_modules\/\.bin\/vitest run |vendor\/bin\/phpunit -c phpunit\.xml\.dist )/',
                $command,
                $label . ' must use an allowed unit runner prefix.'
            );
            self::assertDoesNotMatchRegularExpression(
                '/(?:^|[\s;&|])(?:rm|rmdir|git\s+(?:push|tag|reset|clean|checkout)|npm\s+run\s+release|node\s+bin\/cbt-release\.mjs|wp\s+db|mysql|truncate)\b/i',
                $command,
                $label . ' command must not contain destructive operations.'
            );
            self::assertFileExists($this->projectRoot . '/' . (string) ($item['path'] ?? ''));
            self::assertContains((string) ($item['path'] ?? ''), $this->extractRunnerCommandTargetFiles($command));
        }
    }

    public function test_global_unit_run_queue_uses_complete_inventory_one_file_per_command(): void
    {
        $inventory = $this->invokePrivate('get_unit_test_inventory');
        $queue = $this->invokePrivate('build_global_unit_run_command_queue');

        self::assertCount(count((array) $inventory), (array) $queue);

        $inventoryPaths = array_map(static fn (array $item): string => (string) ($item['path'] ?? ''), (array) $inventory);
        $queuePaths = [];
        foreach ((array) $queue as $commandDefinition) {
            self::assertIsArray($commandDefinition);
            $inventoryFile = isset($commandDefinition['inventory_file']) && is_array($commandDefinition['inventory_file'])
                ? (array) $commandDefinition['inventory_file']
                : [];
            $queuePath = (string) ($inventoryFile['path'] ?? '');
            self::assertNotSame('', $queuePath);
            self::assertSame([$queuePath], $this->extractRunnerCommandTargetFiles((string) ($commandDefinition['command'] ?? '')));
            $queuePaths[] = $queuePath;
        }

        sort($inventoryPaths);
        sort($queuePaths);
        self::assertSame($inventoryPaths, $queuePaths);
    }

    public function test_inventory_marks_curated_files_and_auto_maps_new_files(): void
    {
        $inventory = $this->invokePrivate('get_unit_test_inventory');
        $byPath = [];
        foreach ((array) $inventory as $item) {
            $byPath[(string) ($item['path'] ?? '')] = $item;
        }

        self::assertSame('curated', (string) ($byPath['tests/php/unit/UpdateHealthServiceTest.php']['mapping_status'] ?? ''));
        self::assertSame('update_health', (string) ($byPath['tests/php/unit/UpdateHealthServiceTest.php']['mapped_tab'] ?? ''));

        self::assertSame('curated', (string) ($byPath['tests/php/unit/IncidentReportTest.php']['mapping_status'] ?? ''));
        self::assertSame('security_event_pipeline', (string) ($byPath['tests/php/unit/IncidentReportTest.php']['mapped_tab'] ?? ''));
        self::assertNotSame('', (string) ($byPath['tests/php/unit/IncidentReportTest.php']['command'] ?? ''));
    }

    public function test_flow_check_runner_wrappers_reference_seeded_fixtures_and_specs(): void
    {
        $seedFixtures = \CBT_Admin_Maintenance_Seed_Service::get_seed_flow_check_fixture_exam_titles();
        $wrapperFiles = glob($this->projectRoot . '/tests/e2e/run-*-flow.mjs');
        self::assertNotFalse($wrapperFiles);
        self::assertNotEmpty($wrapperFiles);

        $checkedWrappers = [];
        foreach ($wrapperFiles as $wrapperPath) {
            $source = (string) file_get_contents((string) $wrapperPath);
            if (!str_contains($source, 'runFlowSuite({')) {
                continue;
            }

            $relativeWrapperPath = substr((string) $wrapperPath, strlen($this->projectRoot) + 1);
            self::assertMatchesRegularExpression(
                '/suiteTitle:\s*[\'"]([^\'"]+)[\'"]/',
                $source,
                $relativeWrapperPath . ' must declare a suite title.'
            );
            self::assertMatchesRegularExpression(
                '/specRelativePath:\s*[\'"]([^\'"]+)[\'"]/',
                $source,
                $relativeWrapperPath . ' must declare a Playwright spec path.'
            );
            self::assertMatchesRegularExpression(
                '/fixtureKey:\s*[\'"]([^\'"]+)[\'"]/',
                $source,
                $relativeWrapperPath . ' must declare the fixture key used by preflight reset.'
            );

            preg_match('/specRelativePath:\s*[\'"]([^\'"]+)[\'"]/', $source, $specMatch);
            preg_match('/fixtureKey:\s*[\'"]([^\'"]+)[\'"]/', $source, $fixtureMatch);
            $specRelativePath = (string) ($specMatch[1] ?? '');
            $fixtureKey = (string) ($fixtureMatch[1] ?? '');

            self::assertFileExists(
                $this->projectRoot . '/' . $specRelativePath,
                $relativeWrapperPath . ' references a missing Playwright spec.'
            );
            self::assertArrayHasKey(
                $fixtureKey,
                $seedFixtures,
                $relativeWrapperPath . ' uses a fixture key that CBT Maintenance bulk seed does not create.'
            );
            self::assertStringContainsString(
                'test.skip(!baseURL',
                (string) file_get_contents($this->projectRoot . '/' . $specRelativePath),
                $specRelativePath . ' must keep the environment guard used by Flow Check.'
            );

            $checkedWrappers[] = $relativeWrapperPath;
        }

        sort($checkedWrappers);
        self::assertSame([
            'tests/e2e/run-auth-session-flow.mjs',
            'tests/e2e/run-import-preview-flow.mjs',
            'tests/e2e/run-new-question-types-flow.mjs',
            'tests/e2e/run-question-runtime-flow.mjs',
            'tests/e2e/run-result-scoring-flow.mjs',
            'tests/e2e/run-security-log-flow.mjs',
            'tests/e2e/run-sync-rest-flow.mjs',
            'tests/e2e/run-timer-lifecycle-flow.mjs',
        ], $checkedWrappers);
    }

    public function test_current_question_types_have_runtime_sync_import_and_result_coverage(): void
    {
        $questionTypes = [
            'multiple_choice',
            'multiple_answer',
            'true_false',
            'true_false_matrix',
            'short_answer',
            'essay',
            'ordering',
            'matching',
            'cloze_dropdown',
            'categorization',
            'table_completion',
        ];

        $coverageFilesByArea = [
            'Question Runtime' => [
                'tests/js/unit/question-render.test.js',
                'tests/js/unit/question-inputs.test.js',
                'tests/js/unit/question-state-manager.test.js',
                'tests/js/unit/question-cache-recovery.test.js',
            ],
            'Sync & REST' => [
                'tests/php/unit/RestSyncValidationTest.php',
                'tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php',
                'tests/php/unit/ExamQuestionDeliverySnapshotTest.php',
            ],
            'Import & Preview' => [
                'tests/php/unit/QuestionsImportPreviewTest.php',
            ],
            'Result & Export' => [
                'tests/js/unit/review-stage.test.js',
                'tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php',
                'tests/php/unit/AdminResultsHelperObjectMapProgressTest.php',
                'tests/php/unit/AdminReportExamRowsTest.php',
            ],
        ];

        foreach ($coverageFilesByArea as $area => $files) {
            $content = '';
            foreach ($files as $file) {
                $path = $this->projectRoot . '/' . $file;
                self::assertFileExists($path, $area . ' coverage file must exist: ' . $file);
                $content .= "\n" . (string) file_get_contents($path);
            }

            foreach ($questionTypes as $questionType) {
                self::assertStringContainsString(
                    $questionType,
                    $content,
                    $area . ' must retain coverage for question type ' . $questionType . '.'
                );
            }
        }
    }

    public function test_recovery_persistence_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $recoveryTab = (array) ($tabs['recovery_persistence'] ?? []);
        $recoveryRunners = (array) ($runners['recovery_persistence'] ?? []);

        self::assertSame('Recovery & Persistence', (string) ($recoveryTab['label'] ?? ''));
        self::assertStringContainsString('object-map answer', (string) ($recoveryTab['summary'] ?? ''));
        self::assertStringContainsString('pending autosave', (string) ($recoveryTab['summary'] ?? ''));
        self::assertStringContainsString('remote ui_state', (string) ($recoveryTab['summary'] ?? ''));
        self::assertStringContainsString('cache attempt', (string) ($recoveryTab['summary'] ?? ''));

        $unitCommands = (array) ($recoveryRunners['unit_tests']['commands'] ?? []);
        self::assertSame(
            ['Vitest Recovery', 'PHPUnit Recovery'],
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $unitCommands),
            'Recovery & Persistence unit runner labels should stay deterministic and reviewable.'
        );
        self::assertSame(
            [
                'tests/js/unit/attempt-ui-state.test.js',
                'tests/js/unit/question-cache-recovery.test.js',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Recovery Vitest runner must keep attempt UI and question cache restore coverage together.'
        );
        self::assertSame(
            [
                'tests/php/unit/UiStateAttemptNormalizationTest.php',
                'tests/php/unit/UiStateRecoveryPersistenceTest.php',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[1]['command'] ?? '')),
            'Recovery PHPUnit runner must keep normalizer and persistence coverage together.'
        );

        $expectedSmokeLabels = [
            'Playwright Recovery Refresh',
            'Playwright Recovery Reopen',
            'Playwright Recovery Non-Attempt Cache',
            'Playwright Recovery Finish Failure',
            'Playwright Recovery Corrupt Snapshot',
            'Playwright Recovery Admin Cache',
            'Playwright Recovery Conflict Resolver',
            'Playwright Recovery Object Map Restore',
        ];
        $smokeCommands = (array) ($recoveryRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Recovery & Persistence smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($recoveryTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ($expectedSmokeLabels as $expectedSmokeLabel) {
            self::assertArrayHasKey(
                $expectedSmokeLabel,
                $checklistRunnerLabels,
                'Recovery & Persistence checklist must expose smoke command ' . $expectedSmokeLabel . '.'
            );
        }

        $questionCacheContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/question-cache-recovery.test.js');
        self::assertStringContainsString('preserves object-map answers and structured metadata during cache recovery', $questionCacheContent);
        self::assertStringContainsString('preserves object-map pending autosave state during cache recovery', $questionCacheContent);
        self::assertStringContainsString('rejects cached snapshots whose embedded attempt id does not match the requested attempt', $questionCacheContent);
        foreach (['pendingAnswerBatchByQuestion', 'pendingAnswerBatchOrder', 'lastSubmittedPayloadByQuestion'] as $autosaveNeedle) {
            self::assertStringContainsString(
                $autosaveNeedle,
                $questionCacheContent,
                'Recovery question cache tests must retain pending autosave snapshot coverage for ' . $autosaveNeedle . '.'
            );
        }
        foreach (['matching', 'cloze_dropdown', 'categorization', 'table_completion'] as $questionType) {
            self::assertStringContainsString(
                $questionType,
                $questionCacheContent,
                'Recovery question cache tests must retain structured metadata coverage for ' . $questionType . '.'
            );
        }

        $attemptUiContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/attempt-ui-state.test.js');
        foreach (['prefers the local snapshot', 'prefers the remote snapshot', 'prefers the newer snapshot'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $attemptUiContent);
        }

        $phpRecoveryContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/UiStateRecoveryPersistenceTest.php');
        foreach (['save_attempt_state', 'invalidate_namespace', 'CBT_UI_State'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $phpRecoveryContent);
        }

        $newTypesSpec = (string) file_get_contents($this->projectRoot . '/tests/e2e/new-question-types.spec.js');
        self::assertStringContainsString('Student runtime restores object-map answers for new question types', $newTypesSpec);
    }

    public function test_auth_session_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $authTab = (array) ($tabs['auth_session'] ?? []);
        $authRunners = (array) ($runners['auth_session'] ?? []);

        self::assertSame('Auth & Session', (string) ($authTab['label'] ?? ''));
        self::assertStringContainsString('REST login validation', (string) ($authTab['summary'] ?? ''));
        self::assertStringContainsString('persisted token guard', (string) ($authTab['summary'] ?? ''));
        self::assertStringContainsString('login snapshot freshness', (string) ($authTab['summary'] ?? ''));
        self::assertStringContainsString('bootstrap session', (string) ($authTab['summary'] ?? ''));

        $unitCommands = (array) ($authRunners['unit_tests']['commands'] ?? []);
        self::assertSame(
            ['Vitest Auth Session', 'PHPUnit Auth Session', 'PHPUnit Login Input Guard', 'PHPUnit Login Snapshot Freshness'],
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $unitCommands),
            'Auth & Session unit runner labels should stay deterministic and reviewable.'
        );
        self::assertSame(
            [
                'tests/js/unit/auth-session.test.js',
                'tests/js/unit/bootstrap-session.test.js',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Auth Vitest runner must keep persisted auth and bootstrap recovery coverage together.'
        );
        self::assertSame(
            [
                'tests/php/unit/AuthTokenNormalizationTest.php',
                'tests/php/unit/AuthSessionLifecycleTest.php',
                'tests/php/unit/AuthSessionRestGuardTest.php',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[1]['command'] ?? '')),
            'Auth PHPUnit runner must keep token normalization, session lifecycle, and REST ownership guard coverage together.'
        );
        self::assertSame(
            ['tests/php/unit/RestLoginInputValidationTest.php'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[2]['command'] ?? '')),
            'Login input guard runner must stay isolated from the real CBT_Auth class scaffold.'
        );
        self::assertSame(
            ['tests/php/unit/LoginSnapshotFreshnessServiceTest.php'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[3]['command'] ?? '')),
            'Login snapshot freshness runner must stay focused on Redis/profile snapshot refresh.'
        );

        $expectedSmokeLabels = [
            'Playwright Auth Second Browser Revoke',
            'Playwright Auth Logout Rotate',
            'Playwright Auth Cross User Forbidden',
            'Playwright Auth Reopen Resume',
            'Playwright Auth Dual Browser Invalidate',
            'Playwright Auth Valid Resume Bootstrap',
        ];
        $smokeCommands = (array) ($authRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Auth & Session smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($authTab['unit_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ((array) ($authTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach (array_merge(
            ['Vitest Auth Session', 'PHPUnit Auth Session', 'PHPUnit Login Input Guard', 'PHPUnit Login Snapshot Freshness'],
            $expectedSmokeLabels
        ) as $expectedLabel) {
            self::assertArrayHasKey(
                $expectedLabel,
                $checklistRunnerLabels,
                'Auth & Session checklist must expose runner command ' . $expectedLabel . '.'
            );
        }

        $authSessionContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/auth-session.test.js');
        foreach (['does not overwrite a newer stored token', 'malformed persisted tokens', 'normalizePersistedToken', 'storage access is unavailable'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $authSessionContent);
        }

        $bootstrapContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/bootstrap-session.test.js');
        foreach (['clears the session path', 'rejected as missing by the server', 'persisted finish receipt'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $bootstrapContent);
        }

        $lifecycleContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/AuthSessionLifecycleTest.php');
        foreach (['session_revoked', 'canonical_fallback', 'Login_Auth_Snapshot_Cache', 'session_takeover_stale'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $lifecycleContent);
        }

        $loginInputContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/RestLoginInputValidationTest.php');
        foreach (['object_identifier', 'retry_after', 'disallowed_user_agent'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $loginInputContent);
        }

        $freshnessContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/LoginSnapshotFreshnessServiceTest.php');
        foreach (['test_tick_skips_exam_with_active_preflight_job', 'test_tick_limits_batch_size_to_150_users_per_exam', 'CBT_Login_Snapshot_Freshness_Service::tick'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $freshnessContent);
        }

        $authSpec = (string) file_get_contents($this->projectRoot . '/tests/e2e/auth-session.spec.js');
        foreach ([
            'Auth Flow: second browser login revokes previous session',
            'Auth Flow: logout then login rotates session token',
            'Auth Flow: cross-user attempt access is forbidden',
            'Auth Flow: valid session bootstrap can resume without auth guard',
        ] as $smokeTitle) {
            self::assertStringContainsString($smokeTitle, $authSpec);
        }
    }

    public function test_timer_lifecycle_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $timerTab = (array) ($tabs['timer_lifecycle'] ?? []);
        $timerRunners = (array) ($runners['timer_lifecycle'] ?? []);

        self::assertSame('Timer & Lifecycle', (string) ($timerTab['label'] ?? ''));
        self::assertStringContainsString('adaptive heartbeat', (string) ($timerTab['summary'] ?? ''));
        self::assertStringContainsString('heartbeat-lost signal', (string) ($timerTab['summary'] ?? ''));
        self::assertStringContainsString('page lifecycle listener', (string) ($timerTab['summary'] ?? ''));

        $unitCommands = (array) ($timerRunners['unit_tests']['commands'] ?? []);
        self::assertCount(1, $unitCommands, 'Timer & Lifecycle unit runner should stay as one scoped Vitest command.');
        self::assertSame('Vitest Timer & Lifecycle', (string) ($unitCommands[0]['label'] ?? ''));
        self::assertSame(
            [
                'tests/js/unit/session-lifecycle.test.js',
                'tests/js/unit/session-heartbeat.test.js',
                'tests/js/unit/lifecycle.test.js',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Timer & Lifecycle unit runner must keep timer, heartbeat, and page lifecycle listener coverage together.'
        );

        $expectedSmokeLabels = [
            'Playwright Timer Near Timeout',
            'Playwright Timer Resume Extra Time',
            'Playwright Timer Heartbeat Stable',
            'Playwright Timer Timeout No Zombie',
            'Playwright Timer Logout Safe',
        ];
        $smokeCommands = (array) ($timerRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Timer & Lifecycle smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($timerTab['unit_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ((array) ($timerTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach (array_merge(['Vitest Timer & Lifecycle'], $expectedSmokeLabels) as $expectedLabel) {
            self::assertArrayHasKey(
                $expectedLabel,
                $checklistRunnerLabels,
                'Timer & Lifecycle checklist must expose runner command ' . $expectedLabel . '.'
            );
        }

        $lifecycleContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/session-lifecycle.test.js');
        foreach ([
            'clamps timer payload safely and ignores mismatched attempts',
            'ignores tiny timer drift',
            'counts down to zero and triggers finish transition once',
            'keeps local auth state when server logout fails',
        ] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $lifecycleContent);
        }

        $heartbeatContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/session-heartbeat.test.js');
        foreach ([
            'updates the heartbeat interval to 30 seconds',
            'updates the heartbeat interval to 45 seconds',
            'marks heartbeat lost after three online failures',
            'clears heartbeat lost warning after the next successful heartbeat',
        ] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $heartbeatContent);
        }

        $pageLifecycleContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/lifecycle.test.js');
        foreach ([
            'uses non-force ui_state flushes for visible, blur, focus, and online transitions',
            'keeps force + keepalive flushes for hidden, pagehide, and beforeunload',
            'deduplicates visible, focus, and online reconnect retries within one browser burst',
            'visibilitychange',
            'pagehide',
        ] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $pageLifecycleContent);
        }

        $timerSpec = (string) file_get_contents($this->projectRoot . '/tests/e2e/timer-lifecycle.spec.js');
        foreach ([
            'Timer Flow: near-timeout countdown transitions cleanly to result',
            'Timer Flow: resume keeps timer synced after extra time update',
            'Timer Flow: heartbeat keeps exam stage stable',
            'Timer Flow: natural timeout leaves no timer zombie on reopen',
            'Timer Flow: logout is safe during active and loading exam lifecycle',
        ] as $smokeTitle) {
            self::assertStringContainsString($smokeTitle, $timerSpec);
        }
    }

    public function test_question_runtime_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $runtimeTab = (array) ($tabs['question_runtime'] ?? []);
        $runtimeRunners = (array) ($runners['question_runtime'] ?? []);

        self::assertSame('Question Runtime', (string) ($runtimeTab['label'] ?? ''));
        self::assertStringContainsString('semua 11 tipe soal', (string) ($runtimeTab['summary'] ?? ''));

        $unitCommands = (array) ($runtimeRunners['unit_tests']['commands'] ?? []);
        self::assertCount(1, $unitCommands, 'Question Runtime unit runner should stay as one scoped Vitest command.');
        self::assertSame('Vitest Question Runtime', (string) ($unitCommands[0]['label'] ?? ''));
        self::assertSame(
            [
                'tests/js/unit/question-render.test.js',
                'tests/js/unit/question-inputs.test.js',
                'tests/js/unit/question-state-manager.test.js',
                'tests/js/unit/question-navigation.test.js',
                'tests/js/unit/question-runtime-manager.test.js',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Question Runtime unit runner must keep the render/input/state/navigation/runtime safety net together.'
        );

        $expectedSmokeLabels = [
            'Playwright Runtime Mixed Isolation',
            'Playwright Runtime Doubtful Persist',
            'Playwright Runtime Boundary Navigation',
            'Playwright Runtime Randomized Option Resume',
            'Playwright Runtime Doubtful Revision',
            'Playwright Runtime Rapid Navigation',
            'Playwright New Types Runtime Restore',
        ];
        $smokeCommands = (array) ($runtimeRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Question Runtime smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($runtimeTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ($expectedSmokeLabels as $expectedSmokeLabel) {
            self::assertArrayHasKey(
                $expectedSmokeLabel,
                $checklistRunnerLabels,
                'Question Runtime checklist must expose smoke command ' . $expectedSmokeLabel . '.'
            );
        }

        $structuredRuntimeItem = null;
        foreach ((array) ($runtimeTab['smoke_tests'] ?? []) as $item) {
            $runnerCommands = $this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]);
            if (in_array('Playwright New Types Runtime Restore', $runnerCommands, true)) {
                $structuredRuntimeItem = (array) $item;
                break;
            }
        }

        self::assertIsArray($structuredRuntimeItem);
        self::assertStringContainsString('object-map', (string) ($structuredRuntimeItem['description'] ?? ''));
        self::assertContains('tests/e2e/new-question-types.spec.js', (array) ($structuredRuntimeItem['evidence'] ?? []));
        self::assertContains('src/frontend/app/questions/renderer.js', (array) ($structuredRuntimeItem['evidence'] ?? []));

        $questionRenderContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/question-render.test.js');
        self::assertStringContainsString('renders all supported question types with their runtime controls', $questionRenderContent);
        foreach ([
            'multiple_choice',
            'multiple_answer',
            'true_false',
            'true_false_matrix',
            'short_answer',
            'essay',
            'ordering',
            'matching',
            'cloze_dropdown',
            'categorization',
            'table_completion',
        ] as $questionType) {
            self::assertStringContainsString(
                $questionType,
                $questionRenderContent,
                'Question Runtime render test must retain coverage for ' . $questionType . '.'
            );
        }
        self::assertStringContainsString('rendered-question', $questionRenderContent, 'TF Matrix statements must keep rich renderer coverage.');

        $newTypesSpec = (string) file_get_contents($this->projectRoot . '/tests/e2e/new-question-types.spec.js');
        foreach (['matching', 'cloze_dropdown', 'categorization', 'table_completion'] as $questionType) {
            self::assertStringContainsString($questionType, $newTypesSpec, 'Structured runtime smoke must keep coverage for ' . $questionType . '.');
        }
    }

    public function test_import_preview_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $importTab = (array) ($tabs['import_preview'] ?? []);
        $importRunners = (array) ($runners['import_preview'] ?? []);

        self::assertSame('Import & Preview', (string) ($importTab['label'] ?? ''));
        self::assertStringContainsString('template dinamis', (string) ($importTab['summary'] ?? ''));
        self::assertStringContainsString('structured question types', (string) ($importTab['summary'] ?? ''));

        $unitCommands = (array) ($importRunners['unit_tests']['commands'] ?? []);
        self::assertSame(
            ['PHPUnit Import & Preview', 'PHPUnit Manual Compact Authoring', 'Vitest Import & Preview'],
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $unitCommands),
            'Import & Preview unit runner labels should stay deterministic and reviewable.'
        );
        self::assertSame(
            [
                'tests/php/unit/QuestionsImportPreviewTest.php',
                'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                'tests/php/unit/QuestionsHelperShortAnswerTest.php',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Import & Preview PHPUnit runner must keep parser, preview rendering, and manual validation coverage together.'
        );
        self::assertSame(
            ['tests/php/unit/AdminQuestionManualCompactAuthoringTest.php'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[1]['command'] ?? '')),
            'Manual compact authoring runner must stay focused on count controls and server-side count guards.'
        );
        self::assertSame(
            [
                'tests/js/unit/math-render.test.js',
                'tests/js/unit/math-authoring.test.js',
                'tests/js/unit/review-stage.test.js',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[2]['command'] ?? '')),
            'Import & Preview Vitest runner must keep math render, authoring, and review parity coverage together.'
        );

        $expectedSmokeLabels = [
            'Playwright Import Admin Preview',
            'Playwright Import No Explanation V2',
            'Playwright Import Admin Review Parity',
            'Playwright Import Preview Linebreak',
            'Playwright Import Rich Preview Review Parity',
            'Playwright Import Linebreak Review Parity',
            'Playwright Import Equation Math Parity',
            'Playwright Import Essay Equation Parity',
            'Playwright Import Invalid Failure List',
            'Playwright Authoring MC Empty Correct',
            'Playwright Authoring MA Empty Correct',
            'Playwright Authoring TFM Validation',
            'Playwright Authoring Equation MC',
            'Playwright Authoring Equation Essay',
            'Playwright Authoring Equation TFM',
            'Playwright New Types Manual Authoring',
            'Playwright New Types DOCX Import',
            'Playwright New Types Template Controls',
        ];
        $smokeCommands = (array) ($importRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Import & Preview smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($importTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ($expectedSmokeLabels as $expectedSmokeLabel) {
            self::assertArrayHasKey(
                $expectedSmokeLabel,
                $checklistRunnerLabels,
                'Import & Preview checklist must expose smoke command ' . $expectedSmokeLabel . '.'
            );
        }

        $unitChecklistRunnerLabels = [];
        foreach ((array) ($importTab['unit_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $unitChecklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach (['PHPUnit Import & Preview', 'PHPUnit Manual Compact Authoring', 'Vitest Import & Preview'] as $expectedUnitLabel) {
            self::assertArrayHasKey(
                $expectedUnitLabel,
                $unitChecklistRunnerLabels,
                'Import & Preview checklist must expose unit command ' . $expectedUnitLabel . '.'
            );
        }

        $structuredSmokeLabels = [
            'Playwright New Types Manual Authoring',
            'Playwright New Types DOCX Import',
            'Playwright New Types Template Controls',
        ];
        $newTypesSpec = (string) file_get_contents($this->projectRoot . '/tests/e2e/new-question-types.spec.js');
        foreach ($structuredSmokeLabels as $structuredSmokeLabel) {
            self::assertArrayHasKey($structuredSmokeLabel, $checklistRunnerLabels);
        }
        foreach (['matching', 'cloze_dropdown', 'categorization', 'table_completion'] as $questionType) {
            self::assertStringContainsString($questionType, $newTypesSpec, 'Structured import smoke must keep coverage for ' . $questionType . '.');
        }

        $importUnitContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/QuestionsImportPreviewTest.php');
        foreach ([
            'option_count',
            'input_count',
            'statement_count',
            'item_count',
            'pair_count',
            'dropdown_count',
            'dropdown_option_count',
            'category_count',
            'categorization_item_count',
            'table_rows',
            'table_cols',
        ] as $templateParameter) {
            self::assertStringContainsString(
                $templateParameter,
                $importUnitContent,
                'Import & Preview unit coverage must retain dynamic template parameter ' . $templateParameter . '.'
            );
        }

        $manualCompactContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/AdminQuestionManualCompactAuthoringTest.php');
        foreach ([
            'test_manual_question_type_tabs_cover_current_question_types',
            'test_manual_compact_count_controls_keep_expected_bounds',
            'test_manual_submit_uses_active_counts_and_omits_hidden_rows',
            'test_backend_detail_readers_clamp_manual_count_fields_server_side',
            'test_import_template_download_url_uses_only_active_type_parameters',
        ] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $manualCompactContent);
        }
    }

    public function test_sync_rest_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $syncTab = (array) ($tabs['sync_rest'] ?? []);
        $syncRunners = (array) ($runners['sync_rest'] ?? []);

        self::assertSame('Sync & REST', (string) ($syncTab['label'] ?? ''));
        self::assertStringContainsString('object-map answer sync', (string) ($syncTab['summary'] ?? ''));
        self::assertStringContainsString('autosave feedback', (string) ($syncTab['summary'] ?? ''));
        self::assertStringContainsString('delivery snapshot', (string) ($syncTab['summary'] ?? ''));

        $unitCommands = (array) ($syncRunners['unit_tests']['commands'] ?? []);
        self::assertSame(
            ['Vitest Sync & REST', 'Vitest Answer Sync', 'Vitest UI Sync', 'PHPUnit REST Sync'],
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $unitCommands),
            'Sync & REST unit runner labels should stay deterministic and reviewable.'
        );
        self::assertSame(
            ['tests/js/unit/sync-rest.test.js'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Sync & REST Vitest runner must keep answer sync coverage focused.'
        );
        self::assertSame(
            ['tests/js/unit/answer-sync.test.js'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[1]['command'] ?? '')),
            'Answer Sync runner must keep autosave feedback and object-map batch coverage focused.'
        );
        self::assertSame(
            ['tests/js/unit/attempt-ui-sync.test.js'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[2]['command'] ?? '')),
            'Sync & REST UI sync runner must keep attempt ui sync coverage focused.'
        );
        self::assertSame(
            [
                'tests/php/unit/RestSyncValidationTest.php',
                'tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php',
                'tests/php/unit/ExamQuestionDeliverySnapshotTest.php',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[3]['command'] ?? '')),
            'Sync & REST PHPUnit runner must keep validation, submission context, and delivery snapshot coverage together.'
        );

        $expectedSmokeLabels = [
            'Playwright Sync End To End',
            'Playwright Sync Offline Retry',
            'Playwright Sync Pending Finish Lock',
            'Playwright Sync Cross User Forbidden',
            'Playwright Sync Reopen Flush',
            'Playwright Sync Batch Equivalence',
            'Playwright Sync Finish After Retry',
        ];
        $smokeCommands = (array) ($syncRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Sync & REST smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($syncTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ($expectedSmokeLabels as $expectedSmokeLabel) {
            self::assertArrayHasKey(
                $expectedSmokeLabel,
                $checklistRunnerLabels,
                'Sync & REST checklist must expose smoke command ' . $expectedSmokeLabel . '.'
            );
        }

        $unitChecklistRunnerLabels = [];
        foreach ((array) ($syncTab['unit_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $unitChecklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach (['Vitest Sync & REST', 'Vitest Answer Sync', 'Vitest UI Sync', 'PHPUnit REST Sync'] as $expectedUnitLabel) {
            self::assertArrayHasKey(
                $expectedUnitLabel,
                $unitChecklistRunnerLabels,
                'Sync & REST checklist must expose unit command ' . $expectedUnitLabel . '.'
            );
        }

        $answerSyncContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/answer-sync.test.js');
        foreach (['object-map answer payload', 'submit_answers_batch', 'lastSubmittedPayloadByQuestion', 'sparse autosave snapshots'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $answerSyncContent);
        }

        $submissionContextContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php');
        self::assertStringContainsString('test_submit_answers_batch_internal_accepts_object_map_answers_for_new_question_types', $submissionContextContent);
        foreach (['matching', 'cloze_dropdown', 'categorization', 'table_completion'] as $questionType) {
            self::assertStringContainsString(
                $questionType,
                $submissionContextContent,
                'Sync & REST submission context tests must retain object-map coverage for ' . $questionType . '.'
            );
        }
    }

    public function test_result_export_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $resultTab = (array) ($tabs['result_scoring'] ?? []);
        $resultRunners = (array) ($runners['result_scoring'] ?? []);

        self::assertSame('Result & Export', (string) ($resultTab['label'] ?? ''));
        self::assertStringContainsString('object-map', (string) ($resultTab['summary'] ?? ''));
        self::assertStringContainsString('restricted result', (string) ($resultTab['summary'] ?? ''));
        self::assertStringContainsString('export report print-ready', (string) ($resultTab['summary'] ?? ''));

        $unitCommands = (array) ($resultRunners['unit_tests']['commands'] ?? []);
        self::assertSame(
            ['Vitest Result & Export', 'Vitest Result Review', 'PHPUnit Result Payload', 'PHPUnit Result & Export'],
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $unitCommands),
            'Result & Export unit runner labels should stay deterministic and reviewable.'
        );
        self::assertSame(
            [
                'tests/js/unit/result-stage.test.js',
                'tests/js/unit/finish-flow.test.js',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Result & Export Vitest runner must keep result stage and finish flow coverage together.'
        );
        self::assertSame(
            ['tests/js/unit/review-stage.test.js'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[1]['command'] ?? '')),
            'Result & Export review runner must stay focused on review rendering.'
        );
        self::assertSame(
            ['tests/php/unit/ResultPayloadHelpersTest.php'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[2]['command'] ?? '')),
            'Result payload focused runner should stay small for quick diagnostics.'
        );
        self::assertSame(
            [
                'tests/php/unit/ResultPayloadHelpersTest.php',
                'tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php',
                'tests/php/unit/AdminResultsHelperObjectMapProgressTest.php',
                'tests/php/unit/AdminReportExamRowsTest.php',
            ],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[3]['command'] ?? '')),
            'Result & Export PHPUnit runner must keep payload, object-map progress, and report export coverage together.'
        );

        $expectedSmokeLabels = [
            'Playwright Result Objective Pass',
            'Playwright Result Essay Pending',
            'Playwright Result Restricted Mode',
            'Playwright Result Essay Regrade',
            'Playwright Result Pending No Final Pass',
            'Playwright Result Refresh Reopen',
        ];
        $smokeCommands = (array) ($resultRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Result & Export smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($resultTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ($expectedSmokeLabels as $expectedSmokeLabel) {
            self::assertArrayHasKey(
                $expectedSmokeLabel,
                $checklistRunnerLabels,
                'Result & Export checklist must expose smoke command ' . $expectedSmokeLabel . '.'
            );
        }

        $reviewContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/review-stage.test.js');
        $submissionContextContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php');
        $progressContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/AdminResultsHelperObjectMapProgressTest.php');
        foreach (['status: \'incorrect\'', 'cbt-review-status is-wrong', 'Salah'] as $reviewStatusNeedle) {
            self::assertStringContainsString(
                $reviewStatusNeedle,
                $reviewContent,
                'Result review tests must keep incorrect status alias coverage for ' . $reviewStatusNeedle . '.'
            );
        }
        foreach (['matching', 'cloze_dropdown', 'categorization', 'table_completion'] as $questionType) {
            self::assertStringContainsString($questionType, $reviewContent, 'Result review tests must retain object-map coverage for ' . $questionType . '.');
            self::assertStringContainsString($questionType, $submissionContextContent, 'Result payload tests must retain object-map coverage for ' . $questionType . '.');
            self::assertStringContainsString($questionType, $progressContent, 'Admin progress tests must retain object-map coverage for ' . $questionType . '.');
        }
        self::assertStringContainsString('restricted', (string) file_get_contents($this->projectRoot . '/tests/js/unit/result-stage.test.js'));
        self::assertStringContainsString('restricted', (string) file_get_contents($this->projectRoot . '/tests/js/unit/finish-flow.test.js'));
        self::assertStringContainsString('fallback', (string) file_get_contents($this->projectRoot . '/tests/php/unit/AdminReportExamRowsTest.php'));
    }

    public function test_security_log_observability_tab_keeps_current_safe_runner_matrix(): void
    {
        $tabs = $this->invokePrivate('get_unit_test_tab_definitions');
        $runners = $this->invokePrivate('get_unit_test_runner_definitions');
        $securityTab = (array) ($tabs['security_log_observability'] ?? []);
        $securityRunners = (array) ($runners['security_log_observability'] ?? []);

        self::assertSame('Security Log & Observability', (string) ($securityTab['label'] ?? ''));
        self::assertStringContainsString('live Redis', (string) ($securityTab['summary'] ?? ''));
        self::assertStringContainsString('fallback MySQL', (string) ($securityTab['summary'] ?? ''));
        self::assertStringContainsString('live roster', (string) ($securityTab['summary'] ?? ''));
        self::assertStringContainsString('native bridge', (string) ($securityTab['summary'] ?? ''));

        $unitCommands = (array) ($securityRunners['unit_tests']['commands'] ?? []);
        self::assertSame(
            ['Vitest Security Log', 'Vitest Native Bridge', 'PHPUnit Security Log', 'PHPUnit Native Security Event'],
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $unitCommands),
            'Security Log & Observability unit runner labels should stay deterministic and reviewable.'
        );
        self::assertSame(
            ['tests/js/unit/security-manager.test.js'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[0]['command'] ?? '')),
            'Security unit runner must keep frontend security event coverage focused.'
        );
        self::assertSame(
            ['tests/js/unit/native-bridge.test.js'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[1]['command'] ?? '')),
            'Native bridge runner must stay focused on the official app bridge.'
        );
        self::assertSame(
            ['tests/php/unit/SecurityLogObservabilityTest.php'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[2]['command'] ?? '')),
            'Security PHP runner must stay focused on log, Redis/live, fallback, and roster observability.'
        );
        self::assertSame(
            ['tests/php/unit/RestNativeSecurityEventTest.php'],
            $this->extractRunnerCommandTargetFiles((string) ($unitCommands[3]['command'] ?? '')),
            'Native security event runner must stay focused on REST endpoint validation.'
        );

        $expectedSmokeLabels = [
            'Playwright Security Frontend Log Visible',
            'Playwright Security Admin Follow Up',
            'Playwright Security Must Watch Order',
            'Playwright Security Multi Event Aggregate',
            'Playwright Security Live Roster',
            'Playwright Security Refresh Persistence',
            'Playwright Security Native Direct API',
            'Playwright Security Native Tool',
        ];
        $smokeCommands = (array) ($securityRunners['smoke_tests']['commands'] ?? []);
        self::assertSame(
            $expectedSmokeLabels,
            array_map(static fn (array $command): string => (string) ($command['label'] ?? ''), $smokeCommands),
            'Security Log & Observability smoke runner labels should stay deterministic and reviewable.'
        );

        $checklistRunnerLabels = [];
        foreach ((array) ($securityTab['smoke_tests'] ?? []) as $item) {
            foreach ($this->invokePrivate('normalize_unit_test_note_list', [$item['runner_commands'] ?? []]) as $runnerCommand) {
                $checklistRunnerLabels[$runnerCommand] = true;
            }
        }
        foreach ($expectedSmokeLabels as $expectedSmokeLabel) {
            self::assertArrayHasKey(
                $expectedSmokeLabel,
                $checklistRunnerLabels,
                'Security Log & Observability checklist must expose smoke command ' . $expectedSmokeLabel . '.'
            );
        }

        $securityUnitContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/SecurityLogObservabilityTest.php');
        foreach ([
            'test_get_must_watch_attempts_prefers_live_counters_when_available',
            'test_get_must_watch_attempts_supplements_live_counters_with_mysql_and_dedupes_attempt_id',
            'test_get_must_watch_attempts_overlays_live_presence_snapshot',
            'setLiveSecurityRedisUnavailable',
            'native_supported_event_definitions',
        ] as $coverageNeedle) {
            self::assertStringContainsString(
                $coverageNeedle,
                $securityUnitContent,
                'Security Log & Observability unit coverage must retain ' . $coverageNeedle . '.'
            );
        }

        $nativeRestContent = (string) file_get_contents($this->projectRoot . '/tests/php/unit/RestNativeSecurityEventTest.php');
        foreach (['native_security_event', 'invalid_native_app', 'native_event_requires_native_endpoint'] as $coverageNeedle) {
            self::assertStringContainsString($coverageNeedle, $nativeRestContent);
        }

        $nativeBridgeContent = (string) file_get_contents($this->projectRoot . '/tests/js/unit/native-bridge.test.js');
        foreach (['malformed persisted token', 'not-a-string-token', 'endpoints: {}'] as $coverageNeedle) {
            self::assertStringContainsString(
                $coverageNeedle,
                $nativeBridgeContent,
                'Native bridge unit coverage must retain malformed token guard ' . $coverageNeedle . '.'
            );
        }

        $securitySpec = (string) file_get_contents($this->projectRoot . '/tests/e2e/security-log-observability.spec.js');
        foreach ([
            'Security Flow: frontend clipboard event appears in observability panel',
            'Security Flow: live roster shows active attempt grouped by exam kelas dan ruang',
            'Security Flow: native direct API event appears in observability panel',
            'Security Flow: native tab sample request and simulate tool create visible native log',
        ] as $smokeTitle) {
            self::assertStringContainsString($smokeTitle, $securitySpec);
        }
    }

    /**
     * @dataProvider unitTestCaseCountProvider
     */
    public function test_unit_test_case_count_parser_handles_runner_output_variants(string $stdout, string $stderr, int $exitCode, array $expected): void
    {
        self::assertSame($expected, $this->invokePrivate('extract_unit_test_case_counts', [$stdout, $stderr, $exitCode]));
    }

    public static function unitTestCaseCountProvider(): array
    {
        return [
            'vitest pass fail summary' => [
                " Test Files  1 failed | 2 passed (3)\n Tests  1 failed | 7 passed (8)",
                '',
                1,
                ['passed' => 7, 'failed' => 1, 'total' => 8],
            ],
            'phpunit ok summary' => [
                'OK (12 tests, 88 assertions)',
                '',
                0,
                ['passed' => 12, 'failed' => 0, 'total' => 12],
            ],
            'phpunit failures and errors summary' => [
                'Tests: 5, Assertions: 10, Failures: 1, Errors: 1.',
                '',
                1,
                ['passed' => 3, 'failed' => 2, 'total' => 5],
            ],
            'phpunit skipped summary excludes skipped from pass count' => [
                'Tests: 10, Assertions: 20, Skipped: 3.',
                '',
                0,
                ['passed' => 7, 'failed' => 0, 'total' => 7],
            ],
            'unknown successful command falls back to one pass' => [
                'all clear',
                '',
                0,
                ['passed' => 1, 'failed' => 0, 'total' => 1],
            ],
            'unknown failed command falls back to one failure' => [
                '',
                'shell failed',
                2,
                ['passed' => 0, 'failed' => 1, 'total' => 1],
            ],
        ];
    }

    public function test_failure_summary_prioritizes_actionable_lines_and_truncates_noise(): void
    {
        $summary = $this->invokePrivate('summarize_unit_test_failure_output', [
            "\033[31mRunning 4 tests using 1 worker\033[0m\n[1/4] setup\nExpected value to be true but received false",
            "attachment #1: trace\nTimed out 20000ms waiting for locator\nError: final assertion exploded",
        ]);

        self::assertSame('Error: final assertion exploded', $summary);

        $long = str_repeat('x', 260);
        $truncated = $this->invokePrivate('truncate_unit_test_failure_summary', [$long, 40]);
        self::assertSame(40, strlen($truncated));
        self::assertStringEndsWith('...', $truncated);
    }

    public function test_flow_job_status_meta_covers_all_terminal_and_interrupted_states(): void
    {
        self::assertSame(['label' => 'Queued', 'tone' => 'idle'], $this->invokePrivate('flow_job_status_meta_for_job', [null]));
        self::assertSame(['label' => 'Running', 'tone' => 'planned'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'running']]));
        self::assertSame(['label' => 'Cancelling', 'tone' => 'planned'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'cancelling']]));
        self::assertSame(['label' => 'Passed', 'tone' => 'done'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'passed']]));
        self::assertSame(['label' => 'Cancelled', 'tone' => 'idle'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'cancelled']]));
        self::assertSame(['label' => 'Failed', 'tone' => 'danger'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'failed']]));
        self::assertSame(['label' => 'Stale', 'tone' => 'danger'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'failed', 'failure_kind' => 'stale']]));
        self::assertSame(['label' => 'Interrupted', 'tone' => 'danger'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'failed', 'failure_summary' => 'Job background terputus sebelum selesai']]));
        self::assertSame(['label' => 'Unknown', 'tone' => 'idle'], $this->invokePrivate('flow_job_status_meta_for_job', [['status' => 'mystery']]));
        self::assertFalse((bool) $this->invokePrivate('is_active_flow_job_status', ['mystery']));
        self::assertFalse((bool) $this->invokePrivate('is_terminal_flow_job_status', ['mystery']));
    }

    public function test_unit_checklist_items_do_not_default_to_queued_flow_status(): void
    {
        $item = $this->invokePrivate('build_unit_test_checklist_item_context', [
            'sync_rest',
            'unit_tests',
            0,
            [
                'label' => 'Unit sync runner',
                'status' => 'ready',
                'runner_commands' => ['Vitest Sync & REST'],
            ],
            null,
            null,
            null,
        ]);

        self::assertNull($item['async_job']);
        self::assertSame('', (string) ($item['async_status'] ?? ''));
        self::assertSame('', (string) ($item['async_status_label'] ?? ''));
        self::assertFalse((bool) ($item['is_job_active'] ?? true));
        self::assertTrue((bool) ($item['can_run_task'] ?? false));
        self::assertFalse((bool) ($item['can_cancel_job'] ?? true));
        self::assertFalse((bool) ($item['can_retry_job'] ?? true));
        self::assertFalse((bool) ($item['can_clear_job'] ?? true));
        self::assertSame('Run Task', (string) ($item['run_button_label'] ?? ''));
    }

    public function test_flow_job_lookup_keeps_latest_sorted_job_per_item_and_ignores_invalid_items(): void
    {
        $jobs = [
            ['job_id' => 'new', 'tab' => 'sync_rest', 'scope' => 'smoke_tests', 'item_index' => 2, 'created_at' => 200, 'status' => 'passed'],
            ['job_id' => 'old', 'tab' => 'sync_rest', 'scope' => 'smoke_tests', 'item_index' => 2, 'created_at' => 100, 'status' => 'failed'],
            ['job_id' => 'invalid', 'tab' => 'sync_rest', 'scope' => 'smoke_tests', 'item_index' => -1, 'created_at' => 300, 'status' => 'running'],
            ['job_id' => 'unit', 'tab' => 'auth_session', 'scope' => 'unit_tests', 'item_index' => 0, 'created_at' => 250, 'status' => 'queued'],
        ];

        $lookup = $this->invokePrivate('build_latest_flow_job_lookup', [$jobs]);

        self::assertSame('new', $this->invokePrivate('resolve_latest_flow_job_for_item', [$lookup, 'sync_rest', 'smoke_tests', 2])['job_id'] ?? '');
        self::assertSame('unit', $this->invokePrivate('resolve_latest_flow_job_for_item', [$lookup, 'auth_session', 'unit_tests', 0])['job_id'] ?? '');
        self::assertNull($this->invokePrivate('resolve_latest_flow_job_for_item', [$lookup, 'sync_rest', 'smoke_tests', 99]));
        self::assertTrue((bool) $this->invokePrivate('has_active_flow_jobs', [$lookup]));
        self::assertFalse((bool) $this->invokePrivate('has_running_flow_jobs_only', [$lookup]));
    }

    public function test_next_queued_flow_job_id_uses_fifo_and_ignores_blank_job_ids(): void
    {
        $jobs = [
            ['job_id' => '', 'status' => 'queued', 'created_at' => 1],
            ['job_id' => 'flow-unknown', 'status' => 'mystery', 'created_at' => 2],
            ['job_id' => 'flow-newer', 'status' => 'queued', 'created_at' => 30],
            ['job_id' => 'flow-running', 'status' => 'running', 'created_at' => 5],
            ['job_id' => 'flow-older', 'status' => 'queued', 'created_at' => 10],
            ['job_id' => 'flow-failed', 'status' => 'failed', 'created_at' => 1],
        ];

        self::assertSame('flow-older', $this->invokePrivate('resolve_next_queued_flow_job_id', [$jobs]));
    }

    public function test_flow_job_storage_rejects_empty_job_id_and_ignores_malformed_json_jobs(): void
    {
        self::assertFalse((bool) $this->invokePrivate('write_flow_check_job', [[
            'job_id' => '',
            'status' => 'queued',
            'created_at' => time(),
        ]]));
        self::assertFileDoesNotExist($this->flowJobDirectory() . '/.json');

        $jobDir = $this->flowJobDirectory();
        @mkdir($jobDir, 0777, true);
        file_put_contents($jobDir . '/malformed.json', wp_json_encode([
            'status' => 'queued',
            'created_at' => time(),
        ]));

        self::assertSame([], $this->invokePrivate('read_flow_check_jobs'));
    }

    public function test_start_flow_check_job_process_launches_worker_with_env_job_file(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open tidak tersedia di environment test ini.');
        }
        if (!function_exists('shell_exec') || trim((string) @shell_exec('command -v node 2>/dev/null')) === '') {
            self::markTestSkipped('Node.js tidak tersedia di PATH environment test ini.');
        }

        $jobId = 'flow-php-start-worker';
        $job = [
            'job_id' => $jobId,
            'tab' => 'sync_rest',
            'scope' => 'smoke_tests',
            'item_index' => 0,
            'item_label' => 'PHP starts worker smoke',
            'status' => 'queued',
            'created_at' => time(),
            'started_at' => 0,
            'finished_at' => 0,
            'heartbeat_at' => 0,
            'worker_pid' => 0,
            'active_child_pid' => 0,
            'cancel_requested_at' => 0,
            'command' => 'node -e "console.log(\'php-start-ok\')"',
            'commands' => [
                [
                    'label' => 'Tiny PHP-start command',
                    'command' => 'node -e "console.log(\'php-start-ok\')"',
                ],
            ],
            'results' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'failure_kind' => '',
            'failure_summary' => '',
            'log_path' => $this->flowJobDirectory() . '/' . $jobId . '.log',
        ];

        self::assertTrue((bool) $this->invokePrivate('write_flow_check_job', [$job]));
        self::assertTrue((bool) $this->invokePrivate('start_flow_check_job_process', [$jobId]));

        $jobPath = (string) $this->invokePrivate('flow_job_file_path', [$jobId]);
        $persisted = $this->waitForFlowJobStatus($jobPath, ['passed', 'failed'], 8.0);

        self::assertSame('passed', (string) ($persisted['status'] ?? ''));
        self::assertSame(0, (int) ($persisted['exit_code'] ?? -1));
        self::assertStringContainsString('php-start-ok', (string) ($persisted['stdout'] ?? ''));
        self::assertGreaterThan(0, (int) ($persisted['worker_pid'] ?? 0));
        self::assertStringStartsWith(
            wp_normalize_path($this->flowJobDirectory()) . '/',
            wp_normalize_path((string) ($persisted['log_path'] ?? ''))
        );
        self::assertFileExists((string) ($persisted['log_path'] ?? ''));
    }

    public function test_stale_running_flow_job_is_marked_failed_and_persisted(): void
    {
        $now = time();
        $job = [
            'job_id' => 'flow-stale',
            'tab' => 'sync_rest',
            'scope' => 'smoke_tests',
            'item_index' => 0,
            'item_label' => 'Sync stale flow',
            'status' => 'running',
            'created_at' => $now - 3000,
            'started_at' => $now - 1800,
            'finished_at' => 0,
            'heartbeat_at' => $now - 300,
            'worker_pid' => 0,
            'command' => 'node tests/e2e/run-sync-rest-flow.mjs',
            'commands' => [],
            'results' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'failure_kind' => '',
            'failure_summary' => '',
            'log_path' => '',
        ];

        self::assertTrue((bool) $this->invokePrivate('write_flow_check_job', [$job]));

        $jobs = $this->invokePrivate('read_flow_check_jobs');
        $normalized = $jobs[0] ?? [];

        self::assertSame('flow-stale', (string) ($normalized['job_id'] ?? ''));
        self::assertSame('failed', (string) ($normalized['status'] ?? ''));
        self::assertSame('stale', (string) ($normalized['failure_kind'] ?? ''));
        self::assertStringContainsString('heartbeat job sudah stale', (string) ($normalized['stderr'] ?? ''));

        $jobPath = $this->invokePrivate('flow_job_file_path', ['flow-stale']);
        $persisted = json_decode((string) file_get_contents((string) $jobPath), true);
        self::assertSame('failed', (string) ($persisted['status'] ?? ''));
    }

    public function test_running_flow_job_with_missing_worker_pid_after_grace_is_marked_interrupted(): void
    {
        $now = time();
        $job = [
            'job_id' => 'flow-missing-worker-pid',
            'tab' => 'sync_rest',
            'scope' => 'smoke_tests',
            'item_index' => 0,
            'item_label' => 'Sync missing pid flow',
            'status' => 'running',
            'created_at' => $now - 90,
            'started_at' => $now - 60,
            'finished_at' => 0,
            'heartbeat_at' => $now,
            'worker_pid' => 0,
            'active_child_pid' => 0,
            'command' => 'node tests/e2e/run-sync-rest-flow.mjs',
            'commands' => [],
            'results' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'failure_kind' => '',
            'failure_summary' => '',
            'log_path' => '',
        ];

        self::assertTrue((bool) $this->invokePrivate('write_flow_check_job', [$job]));

        $jobs = $this->invokePrivate('read_flow_check_jobs');
        $normalized = $jobs[0] ?? [];

        self::assertSame('flow-missing-worker-pid', (string) ($normalized['job_id'] ?? ''));
        self::assertSame('failed', (string) ($normalized['status'] ?? ''));
        self::assertSame('interrupted', (string) ($normalized['failure_kind'] ?? ''));
        self::assertStringContainsString('worker PID kosong terlalu lama', (string) ($normalized['stderr'] ?? ''));

        $jobPath = $this->invokePrivate('flow_job_file_path', ['flow-missing-worker-pid']);
        $persisted = json_decode((string) file_get_contents((string) $jobPath), true);
        self::assertSame('failed', (string) ($persisted['status'] ?? ''));
        self::assertSame('interrupted', (string) ($persisted['failure_kind'] ?? ''));
    }

    public function test_cancelling_flow_job_without_live_process_is_marked_cancelled_and_persisted(): void
    {
        $now = time();
        $job = [
            'job_id' => 'flow-cancelling-missing',
            'tab' => 'sync_rest',
            'scope' => 'smoke_tests',
            'item_index' => 0,
            'item_label' => 'Sync cancelled flow',
            'status' => 'cancelling',
            'created_at' => $now - 60,
            'started_at' => $now - 50,
            'finished_at' => 0,
            'heartbeat_at' => $now - 10,
            'worker_pid' => 999999,
            'active_child_pid' => 999998,
            'cancel_requested_at' => $now - 5,
            'command' => 'node tests/e2e/run-sync-rest-flow.mjs',
            'commands' => [],
            'results' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'failure_kind' => '',
            'failure_summary' => '',
            'log_path' => '',
        ];

        self::assertTrue((bool) $this->invokePrivate('write_flow_check_job', [$job]));

        $jobs = $this->invokePrivate('read_flow_check_jobs');
        $normalized = $jobs[0] ?? [];

        self::assertSame('flow-cancelling-missing', (string) ($normalized['job_id'] ?? ''));
        self::assertSame('cancelled', (string) ($normalized['status'] ?? ''));
        self::assertSame('cancelled', (string) ($normalized['failure_kind'] ?? ''));
        self::assertStringContainsString('dibatalkan', (string) ($normalized['failure_summary'] ?? ''));

        $jobPath = $this->invokePrivate('flow_job_file_path', ['flow-cancelling-missing']);
        $persisted = json_decode((string) file_get_contents((string) $jobPath), true);
        self::assertSame('cancelled', (string) ($persisted['status'] ?? ''));
    }

    public function test_flow_job_run_results_normalize_result_list_and_fallback_output(): void
    {
        $job = [
            'item_label' => 'Runtime smoke',
            'command' => 'node smoke.js',
            'status' => 'failed',
            'exit_code' => 1,
            'stdout' => str_repeat('a', 13000),
            'stderr' => '',
            'failure_summary' => 'Timed out waiting for frontend shell',
        ];

        $fallbackResults = $this->invokePrivate('build_flow_job_run_results', [$job]);
        self::assertCount(1, $fallbackResults);
        self::assertSame('Runtime smoke', $fallbackResults[0]['label']);
        self::assertFalse((bool) $fallbackResults[0]['success']);
        self::assertStringContainsString('[output truncated]', (string) $fallbackResults[0]['stdout']);

        $job['results'] = [
            [
                'label' => 'Command A',
                'command' => 'phpunit',
                'success' => true,
                'exit_code' => 0,
                'stdout' => 'OK',
                'stderr' => '',
            ],
            'invalid result',
        ];
        $listedResults = $this->invokePrivate('build_flow_job_run_results', [$job]);
        self::assertCount(1, $listedResults);
        self::assertSame('Command A', $listedResults[0]['label']);
        self::assertTrue((bool) $listedResults[0]['success']);
    }

    public function test_artifact_path_removal_is_limited_to_plugin_or_test_hub_upload_roots(): void
    {
        $outsidePath = sys_get_temp_dir() . '/cbt-test-hub-outside-' . getmypid();
        @mkdir($outsidePath, 0777, true);
        file_put_contents($outsidePath . '/should-stay.txt', 'safe');

        try {
            self::assertFalse((bool) $this->invokePrivate('remove_test_artifact_path', [$outsidePath]));
            self::assertFileExists($outsidePath . '/should-stay.txt');

            $uploadArtifact = $this->uploadRoot . '/cbt-test-hub/playwright-results/job';
            @mkdir($uploadArtifact, 0777, true);
            file_put_contents($uploadArtifact . '/trace.zip', 'trace');

            self::assertTrue((bool) $this->invokePrivate('remove_test_artifact_path', [$this->uploadRoot . '/cbt-test-hub/playwright-results']));
            self::assertDirectoryDoesNotExist($this->uploadRoot . '/cbt-test-hub/playwright-results');
        } finally {
            $this->removeDirectoryIfExists($outsidePath);
        }
    }

    public function test_flow_job_artifact_context_truncates_log_preview_and_ignores_path_traversal(): void
    {
        $jobId = 'flow-artifact-preview';
        $jobDir = $this->flowJobDirectory();
        $artifactDir = $jobDir . '/' . $jobId . '-artifacts';
        @mkdir($artifactDir, 0777, true);
        $logLines = [];
        for ($index = 1; $index <= 450; $index += 1) {
            $logLines[] = 'line-' . $index . ' <script>alert(' . $index . ')</script>';
        }
        file_put_contents($jobDir . '/' . $jobId . '.log', implode("\n", $logLines));
        @mkdir($artifactDir . '/screenshots', 0777, true);
        file_put_contents($artifactDir . '/screenshots/failure.txt', 'failure evidence');
        $outsidePath = sys_get_temp_dir() . '/cbt-test-hub-outside-log-' . getmypid() . '.log';
        file_put_contents($outsidePath, 'outside log must be ignored');

        try {
            $context = $this->invokePrivate('build_flow_job_artifact_context', [[
                'job_id' => $jobId,
                'status' => 'failed',
                'log_path' => $outsidePath,
                'stdout' => '<script>alert(999)</script> stdout',
                'stderr' => '',
                'failure_summary' => '',
            ]]);

            self::assertTrue((bool) ($context['has_any'] ?? false));
            self::assertSame($jobId . '.log', (string) ($context['log']['name'] ?? ''));
            self::assertTrue((bool) ($context['log']['truncated'] ?? false));
            self::assertStringContainsString('line-450', (string) ($context['log']['preview'] ?? ''));
            self::assertStringNotContainsString('outside log must be ignored', (string) ($context['log']['preview'] ?? ''));
            self::assertStringContainsString('<script>alert(999)</script> stdout', (string) ($context['output_preview']['preview'] ?? ''));
            self::assertCount(1, (array) ($context['artifacts'] ?? []));
            self::assertStringNotContainsString('absolute_path', wp_json_encode($context));
        } finally {
            @unlink($outsidePath);
        }
    }

    private function assertRunnerCommandIsSafeAndResolvable(string $tabKey, string $scope, string $label, string $command): void
    {
        $context = $tabKey . '/' . $scope . '/' . $label;
        self::assertNotSame('', $command, $context . ' command must be set.');
        self::assertMatchesRegularExpression(
            '/^(?:\.\/node_modules\/\.bin\/vitest run |vendor\/bin\/phpunit -c phpunit\.xml\.dist |node tests\/e2e\/run-[a-z0-9-]+\.mjs(?:\s|$))/',
            $command,
            $context . ' command must use an allowed runner prefix.'
        );
        self::assertDoesNotMatchRegularExpression(
            '/(?:^|[\s;&|])(?:rm|rmdir|git\s+(?:push|tag|reset|clean|checkout)|npm\s+run\s+release|node\s+bin\/cbt-release\.mjs|wp\s+db|mysql|truncate)\b/i',
            $command,
            $context . ' command must not contain destructive operations.'
        );

        $targetFiles = $this->extractRunnerCommandTargetFiles($command);
        self::assertNotEmpty($targetFiles, $context . ' command must point to at least one test file.');
        foreach ($targetFiles as $targetFile) {
            self::assertFileExists($this->projectRoot . '/' . $targetFile, $context . ' target file missing: ' . $targetFile);
        }

        if (preg_match('/--grep\s+"([^"]+)"/', $command, $grepMatch) === 1) {
            $specPath = $this->resolvePlaywrightSpecPathFromCommand($command);
            self::assertNotSame('', $specPath, $context . ' Playwright command with --grep must resolve a spec file.');
            self::assertFileExists($this->projectRoot . '/' . $specPath, $context . ' spec file missing: ' . $specPath);
            self::assertContains(
                (string) $grepMatch[1],
                $this->extractPlaywrightTestTitles($this->projectRoot . '/' . $specPath),
                $context . ' --grep does not match an executable test title in ' . $specPath . '.'
            );
        }
    }

    /**
     * @return string[]
     */
    private function extractRunnerCommandTargetFiles(string $command): array
    {
        preg_match_all('/(?:^|\s)(tests\/(?:js\/unit|php\/unit|e2e)\/[A-Za-z0-9_.\/-]+\.(?:js|php|mjs))/', $command, $matches);

        return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    }

    private function resolvePlaywrightSpecPathFromCommand(string $command): string
    {
        if (preg_match('/node\s+(tests\/e2e\/run-[A-Za-z0-9-]+\.mjs)/', $command, $runnerMatch) !== 1) {
            return '';
        }

        $runnerPath = $this->projectRoot . '/' . (string) $runnerMatch[1];
        if (!is_file($runnerPath)) {
            return '';
        }

        $runnerSource = (string) file_get_contents($runnerPath);
        if (preg_match('/specRelativePath:\s*[\'"]([^\'"]+)[\'"]/', $runnerSource, $specMatch) === 1) {
            return (string) $specMatch[1];
        }
        if (preg_match('/\[\s*[\'"]test[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $runnerSource, $specMatch) === 1) {
            return (string) $specMatch[1];
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function extractPlaywrightTestTitles(string $specPath): array
    {
        $source = is_file($specPath) ? (string) file_get_contents($specPath) : '';
        preg_match_all('/\btest(?:\.(?:only|skip|fixme))?\s*\(\s*([\'"])(.*?)\1/s', $source, $matches);

        return array_values(array_unique(array_map('strval', $matches[2] ?? [])));
    }

    /**
     * @return string[]
     */
    private function collectDisplayText(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string) $value];
        }
        if (!is_array($value)) {
            return [];
        }

        $texts = [];
        foreach ($value as $key => $childValue) {
            if (in_array((string) $key, ['command', 'evidence'], true)) {
                continue;
            }
            array_push($texts, ...$this->collectDisplayText($childValue));
        }

        return $texts;
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

    private function flowJobDirectory(): string
    {
        return (string) $this->invokePrivate('flow_job_directory_path');
    }

    /**
     * @param string[] $statuses
     * @return array<string,mixed>
     */
    private function waitForFlowJobStatus(string $jobPath, array $statuses, float $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $lastPayload = [];

        do {
            if (is_file($jobPath)) {
                $decoded = json_decode((string) file_get_contents($jobPath), true);
                if (is_array($decoded)) {
                    $lastPayload = $decoded;
                    if (in_array((string) ($decoded['status'] ?? ''), $statuses, true)) {
                        return $decoded;
                    }
                }
            }

            usleep(100000);
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for flow job status. Last payload: ' . wp_json_encode($lastPayload));
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
