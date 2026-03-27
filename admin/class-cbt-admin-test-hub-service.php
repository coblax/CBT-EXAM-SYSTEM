<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Test_Hub_Service
{
    private const UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX = 'cbt_unit_test_run_result_';
    private const GLOBAL_UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX = 'cbt_global_unit_test_run_result_';
    private const UNIT_TEST_RUN_RESULT_TTL = 15 * MINUTE_IN_SECONDS;
    private const SETTINGS_OPTION = 'cbt_test_hub_settings_v1';
    private const FLOW_JOB_DIRECTORY_RELATIVE = 'playwright-results/admin-jobs';
    private const FLOW_JOB_HEARTBEAT_TIMEOUT = 90;
    private const FLOW_JOB_MAX_RUNTIME = 20 * MINUTE_IN_SECONDS;

    public static function can_manage_test_hub(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @return array{e2e_base_url:string}
     */
    public static function get_settings(): array
    {
        $raw = get_option(self::SETTINGS_OPTION, []);
        return self::sanitize_settings_input(is_array($raw) ? $raw : []);
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{e2e_base_url:string}
     */
    public static function sanitize_settings_input(array $raw): array
    {
        $base_url = isset($raw['e2e_base_url']) ? esc_url_raw((string) $raw['e2e_base_url']) : '';

        return [
            'e2e_base_url' => is_string($base_url) ? trim($base_url) : '',
        ];
    }

    /**
     * @param array{e2e_base_url:string} $settings
     */
    public static function save_settings(array $settings): void
    {
        update_option(self::SETTINGS_OPTION, $settings, false);
    }

    public static function test_hub_page_url(array $extra_args = []): string
    {
        return add_query_arg(array_merge(['page' => 'cbt-test-hub'], $extra_args), admin_url('admin.php'));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_unit_test_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $test_hub_settings = self::get_settings();
        $unit_test_tabs = self::get_unit_test_tab_definitions();
        $unit_test_runners = self::get_unit_test_runner_definitions();
        $active_unit_test_tab = self::normalize_unit_test_tab(isset($query['cbt_unit_test_tab']) ? $query['cbt_unit_test_tab'] : '');
        $active_checklist_scope = self::normalize_unit_test_scope(isset($query['cbt_checklist_scope']) ? $query['cbt_checklist_scope'] : '');
        $active_unit_test_panel = isset($unit_test_tabs[$active_unit_test_tab]) ? (array) $unit_test_tabs[$active_unit_test_tab] : [];
        $unit_test_run_token = isset($query['cbt_test_run_token']) ? sanitize_key(wp_unslash((string) $query['cbt_test_run_token'])) : '';
        $global_unit_run_token = isset($query['cbt_global_unit_run_token']) ? sanitize_key(wp_unslash((string) $query['cbt_global_unit_run_token'])) : '';
        $unit_test_run_result = null;
        $global_unit_run_result = null;
        self::maybe_start_next_flow_job();
        $flow_jobs = self::read_flow_check_jobs();
        $latest_flow_jobs = self::build_latest_flow_job_lookup($flow_jobs);
        if ($unit_test_run_token !== '') {
            $unit_test_run_result = get_transient(self::UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $unit_test_run_token);
            if (!is_array($unit_test_run_result)) {
                $unit_test_run_result = null;
            }
        }
        if ($global_unit_run_token !== '') {
            $global_unit_run_result = get_transient(self::GLOBAL_UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $global_unit_run_token);
            if (!is_array($global_unit_run_result) || (string) ($global_unit_run_result['type'] ?? '') !== 'global_unit_tests') {
                $global_unit_run_result = null;
            }
        }
        $global_unit_run_summary = self::build_global_unit_run_summary($global_unit_run_result);
        $unit_test_area_count = count($unit_test_tabs);
        $unit_test_total_checklist_items = 0;

        foreach ($unit_test_tabs as $unit_test_tab_key => $unit_test_tab) {
            $runner_scopes = [];
            $tab_runner_definitions = isset($unit_test_runners[$unit_test_tab_key]) && is_array($unit_test_runners[$unit_test_tab_key])
                ? (array) $unit_test_runners[$unit_test_tab_key]
                : [];
            foreach (['unit_tests', 'smoke_tests'] as $runner_scope) {
                $runner_scopes[$runner_scope] = self::build_unit_test_runner_context(
                    isset($tab_runner_definitions[$runner_scope]) && is_array($tab_runner_definitions[$runner_scope])
                        ? (array) $tab_runner_definitions[$runner_scope]
                        : null
                );
            }
            $unit_test_tabs[$unit_test_tab_key]['runners'] = $runner_scopes;

            foreach (['unit_tests', 'smoke_tests'] as $list_key) {
                $items = isset($unit_test_tab[$list_key]) && is_array($unit_test_tab[$list_key]) ? (array) $unit_test_tab[$list_key] : [];
                $unit_test_total_checklist_items += count($items);

                foreach ($items as $item_index => $item) {
                    $flow_job = $list_key === 'smoke_tests'
                        ? self::resolve_latest_flow_job_for_item($latest_flow_jobs, (string) $unit_test_tab_key, (string) $list_key, (int) $item_index)
                        : null;
                    $items[$item_index] = self::build_unit_test_checklist_item_context(
                        (string) $unit_test_tab_key,
                        (string) $list_key,
                        (int) $item_index,
                        is_array($item) ? $item : [],
                        is_array($unit_test_run_result) ? $unit_test_run_result : null,
                        is_array($global_unit_run_result) ? $global_unit_run_result : null,
                        $flow_job
                    );
                }

                $unit_test_tabs[$unit_test_tab_key][$list_key] = $items;
            }
        }

        $active_unit_test_panel = isset($unit_test_tabs[$active_unit_test_tab]) ? (array) $unit_test_tabs[$active_unit_test_tab] : [];

        return [
            'notice' => $notice,
            'error' => $error,
            'test_hub_settings' => $test_hub_settings,
            'unit_test_tabs' => $unit_test_tabs,
            'active_unit_test_tab' => $active_unit_test_tab,
            'active_checklist_scope' => $active_checklist_scope,
            'active_unit_test_panel' => $active_unit_test_panel,
            'unit_test_run_result' => $unit_test_run_result,
            'global_unit_run_result' => $global_unit_run_result,
            'global_unit_run_summary' => $global_unit_run_summary,
            'global_unit_run_available' => !empty($global_unit_run_result),
            'global_unit_run_token' => $global_unit_run_token,
            'unit_test_area_count' => $unit_test_area_count,
            'unit_test_total_checklist_items' => $unit_test_total_checklist_items,
            'has_active_flow_jobs' => self::has_active_flow_jobs($latest_flow_jobs),
        ];
    }

    /**
     * @param array<string,mixed>|null $result
     * @return array{passed_count:int,failed_count:int,total_count:int,executed_at:int,success:bool,label:string}
     */
    private static function build_global_unit_run_summary(?array $result): array
    {
        $summary = [
            'passed_count' => 0,
            'failed_count' => 0,
            'total_count' => 0,
            'executed_at' => 0,
            'success' => false,
            'label' => 'Run All Unit Tests',
        ];

        if (empty($result) || !is_array($result)) {
            return $summary;
        }

        $raw_summary = isset($result['summary']) && is_array($result['summary']) ? (array) $result['summary'] : [];
        $summary['passed_count'] = max(0, (int) ($raw_summary['passed_count'] ?? 0));
        $summary['failed_count'] = max(0, (int) ($raw_summary['failed_count'] ?? 0));
        $summary['total_count'] = max(0, (int) ($raw_summary['total_count'] ?? ($summary['passed_count'] + $summary['failed_count'])));
        $summary['executed_at'] = max(0, (int) ($result['executed_at'] ?? 0));
        $summary['success'] = !empty($result['success']);
        $summary['label'] = trim((string) ($result['label'] ?? 'Run All Unit Tests'));

        return $summary;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function build_unit_test_runner_context(?array $runner_definition): ?array
    {
        if (empty($runner_definition) || !is_array($runner_definition)) {
            return null;
        }

        $available = function_exists('proc_open');
        $reason = $available ? '' : 'Runner test membutuhkan fungsi proc_open yang aktif di PHP.';

        return [
            'label' => (string) ($runner_definition['label'] ?? 'Run Tests'),
            'description' => (string) ($runner_definition['description'] ?? ''),
            'available' => $available,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function get_unit_test_runner_definitions(): array
    {
        return [
            'recovery_persistence' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Recovery & Persistence',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Recovery & Persistence.',
                    'commands' => [
                        [
                            'label' => 'Vitest Recovery',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/attempt-ui-state.test.js tests/js/unit/question-cache-recovery.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Recovery',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/UiStateAttemptNormalizationTest.php tests/php/unit/UiStateRecoveryPersistenceTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Recovery & Persistence',
                    'description' => 'Mengantrekan seluruh skenario Playwright Recovery & Persistence ke background job secara granular per item checklist. Flow ini akan skip/fail sesuai kondisi host dan preflight recovery.',
                    'commands' => [
                        [
                            'label' => 'Playwright Recovery Refresh',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: refresh restores current question, answer, and doubtful state"',
                        ],
                        [
                            'label' => 'Playwright Recovery Reopen',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: close and reopen resumes the same attempt state"',
                        ],
                        [
                            'label' => 'Playwright Recovery Non-Attempt Cache',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: non-attempt cache cleanup keeps active attempt state safe"',
                        ],
                        [
                            'label' => 'Playwright Recovery Finish Failure',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: failed finish request keeps progress safe after reopen"',
                        ],
                        [
                            'label' => 'Playwright Recovery Corrupt Snapshot',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: corrupt local snapshot falls back without blank state"',
                        ],
                        [
                            'label' => 'Playwright Recovery Admin Cache',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: admin-side cache invalidation outside attempt preserves active state"',
                        ],
                        [
                            'label' => 'Playwright Recovery Conflict Resolver',
                            'command' => 'node tests/e2e/run-recovery-flow.mjs --grep "Recovery Flow: remote snapshot wins when local snapshot becomes stale conflict"',
                        ],
                    ],
                ],
            ],
            'sync_rest' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Sync & REST',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Sync & REST.',
                    'commands' => [
                        [
                            'label' => 'Vitest Sync & REST',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/sync-rest.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest UI Sync',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/attempt-ui-sync.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit REST Sync',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RestSyncValidationTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Sync & REST',
                    'description' => 'Mengantrekan skenario Playwright Sync & REST ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Sync End To End',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: start load submit finish result end to end"',
                        ],
                        [
                            'label' => 'Playwright Sync Offline Retry',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: offline answers retry automatically when back online"',
                        ],
                        [
                            'label' => 'Playwright Sync Pending Finish Lock',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: finish remains locked informatively while pending sync exists"',
                        ],
                        [
                            'label' => 'Playwright Sync Cross User Forbidden',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: cross-user attempt request is forbidden"',
                        ],
                        [
                            'label' => 'Playwright Sync Reopen Flush',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: pending sync flushes after reopen"',
                        ],
                        [
                            'label' => 'Playwright Sync Batch Equivalence',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: batch fallback and normal submit produce equivalent answer rows"',
                        ],
                        [
                            'label' => 'Playwright Sync Finish After Retry',
                            'command' => 'node tests/e2e/run-sync-rest-flow.mjs --grep "Sync Flow: finish with pending sync resolves to correct result after retry"',
                        ],
                    ],
                ],
            ],
            'auth_session' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Auth & Session',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Auth & Session.',
                    'commands' => [
                        [
                            'label' => 'Vitest Auth Session',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/auth-session.test.js tests/js/unit/bootstrap-session.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Auth Session',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AuthTokenNormalizationTest.php tests/php/unit/AuthSessionLifecycleTest.php tests/php/unit/AuthSessionRestGuardTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Auth & Session',
                    'description' => 'Mengantrekan skenario Playwright Auth & Session ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Auth Second Browser Revoke',
                            'command' => 'node tests/e2e/run-auth-session-flow.mjs --grep "Auth Flow: second browser login revokes previous session"',
                        ],
                        [
                            'label' => 'Playwright Auth Logout Rotate',
                            'command' => 'node tests/e2e/run-auth-session-flow.mjs --grep "Auth Flow: logout then login rotates session token"',
                        ],
                        [
                            'label' => 'Playwright Auth Cross User Forbidden',
                            'command' => 'node tests/e2e/run-auth-session-flow.mjs --grep "Auth Flow: cross-user attempt access is forbidden"',
                        ],
                        [
                            'label' => 'Playwright Auth Reopen Resume',
                            'command' => 'node tests/e2e/run-auth-session-flow.mjs --grep "Auth Flow: reopen resumes attempt with valid session"',
                        ],
                        [
                            'label' => 'Playwright Auth Dual Browser Invalidate',
                            'command' => 'node tests/e2e/run-auth-session-flow.mjs --grep "Auth Flow: dual browser relogin invalidates old session deterministically"',
                        ],
                        [
                            'label' => 'Playwright Auth Valid Resume Bootstrap',
                            'command' => 'node tests/e2e/run-auth-session-flow.mjs --grep "Auth Flow: valid session bootstrap can resume without auth guard"',
                        ],
                    ],
                ],
            ],
            'timer_lifecycle' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Timer & Lifecycle',
                    'description' => 'Menjalankan suite JS yang saat ini dipetakan ke Checklist Unit Test untuk Timer & Lifecycle.',
                    'commands' => [
                        [
                            'label' => 'Vitest Timer & Lifecycle',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/session-lifecycle.test.js tests/js/unit/session-heartbeat.test.js --reporter=verbose',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Timer & Lifecycle',
                    'description' => 'Mengantrekan skenario Playwright Timer & Lifecycle ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Timer Near Timeout',
                            'command' => 'node tests/e2e/run-timer-lifecycle-flow.mjs --grep "Timer Flow: near-timeout countdown transitions cleanly to result"',
                        ],
                        [
                            'label' => 'Playwright Timer Resume Extra Time',
                            'command' => 'node tests/e2e/run-timer-lifecycle-flow.mjs --grep "Timer Flow: resume keeps timer synced after extra time update"',
                        ],
                        [
                            'label' => 'Playwright Timer Heartbeat Stable',
                            'command' => 'node tests/e2e/run-timer-lifecycle-flow.mjs --grep "Timer Flow: heartbeat keeps exam stage stable"',
                        ],
                        [
                            'label' => 'Playwright Timer Timeout No Zombie',
                            'command' => 'node tests/e2e/run-timer-lifecycle-flow.mjs --grep "Timer Flow: natural timeout leaves no timer zombie on reopen"',
                        ],
                        [
                            'label' => 'Playwright Timer Logout Safe',
                            'command' => 'node tests/e2e/run-timer-lifecycle-flow.mjs --grep "Timer Flow: logout is safe during active and loading exam lifecycle"',
                        ],
                    ],
                ],
            ],
            'question_runtime' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Question Runtime',
                    'description' => 'Menjalankan suite JS yang saat ini dipetakan ke Checklist Unit Test untuk Question Runtime.',
                    'commands' => [
                        [
                            'label' => 'Vitest Question Runtime',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/question-inputs.test.js tests/js/unit/question-state-manager.test.js tests/js/unit/question-navigation.test.js tests/js/unit/question-runtime-manager.test.js --reporter=verbose',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Question Runtime',
                    'description' => 'Mengantrekan skenario Playwright Question Runtime ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Runtime Mixed Isolation',
                            'command' => 'node tests/e2e/run-question-runtime-flow.mjs --grep "Runtime Flow: mixed question answers stay isolated"',
                        ],
                        [
                            'label' => 'Playwright Runtime Doubtful Persist',
                            'command' => 'node tests/e2e/run-question-runtime-flow.mjs --grep "Runtime Flow: doubtful persists across navigation"',
                        ],
                        [
                            'label' => 'Playwright Runtime Boundary Navigation',
                            'command' => 'node tests/e2e/run-question-runtime-flow.mjs --grep "Runtime Flow: boundary navigation clamps safely"',
                        ],
                        [
                            'label' => 'Playwright Runtime Randomized Option Resume',
                            'command' => 'node tests/e2e/run-question-runtime-flow.mjs --grep "Runtime Flow: randomize options keeps mapped answer after refresh"',
                        ],
                        [
                            'label' => 'Playwright Runtime Doubtful Revision',
                            'command' => 'node tests/e2e/run-question-runtime-flow.mjs --grep "Runtime Flow: doubtful and answer revisions remain consistent"',
                        ],
                        [
                            'label' => 'Playwright Runtime Rapid Navigation',
                            'command' => 'node tests/e2e/run-question-runtime-flow.mjs --grep "Runtime Flow: rapid navigation does not swap adjacent payloads"',
                        ],
                    ],
                ],
            ],
            'result_scoring' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Result & Scoring',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Result & Scoring.',
                    'commands' => [
                        [
                            'label' => 'Vitest Result & Scoring',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/result-stage.test.js tests/js/unit/finish-flow.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Result Payload',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ResultPayloadHelpersTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Result & Scoring',
                    'description' => 'Mengantrekan skenario Playwright Result & Scoring ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Result Objective Pass',
                            'command' => 'node tests/e2e/run-result-scoring-flow.mjs --grep "Result Flow: objective exam shows score percentage and pass label"',
                        ],
                        [
                            'label' => 'Playwright Result Essay Pending',
                            'command' => 'node tests/e2e/run-result-scoring-flow.mjs --grep "Result Flow: essay pending shows temporary result state"',
                        ],
                        [
                            'label' => 'Playwright Result Restricted Mode',
                            'command' => 'node tests/e2e/run-result-scoring-flow.mjs --grep "Result Flow: restricted exam hides score and review"',
                        ],
                        [
                            'label' => 'Playwright Result Essay Regrade',
                            'command' => 'node tests/e2e/run-result-scoring-flow.mjs --grep "Result Flow: admin regrade updates essay result consistently"',
                        ],
                        [
                            'label' => 'Playwright Result Pending No Final Pass',
                            'command' => 'node tests/e2e/run-result-scoring-flow.mjs --grep "Result Flow: high-point essay pending does not imply final pass state"',
                        ],
                        [
                            'label' => 'Playwright Result Refresh Reopen',
                            'command' => 'node tests/e2e/run-result-scoring-flow.mjs --grep "Result Flow: full and restricted result stay consistent after refresh and reopen"',
                        ],
                    ],
                ],
            ],
            'import_preview' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Import & Preview',
                    'description' => 'Menjalankan suite PHP yang saat ini dipetakan ke Checklist Unit Test untuk Import & Preview.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Import & Preview',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/QuestionsImportPreviewTest.php tests/php/unit/QuestionsHelperPreviewRenderingTest.php tests/php/unit/QuestionsHelperShortAnswerTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Import & Preview',
                    'description' => 'Mengantrekan skenario Playwright Import & Preview ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Import Admin Preview',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: rich DOCX import renders in admin preview"',
                        ],
                        [
                            'label' => 'Playwright Import Legacy Compatible',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: legacy DOCX without explanation still imports successfully"',
                        ],
                        [
                            'label' => 'Playwright Import Admin Review Parity',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: admin preview and student review show the same imported rich question"',
                        ],
                        [
                            'label' => 'Playwright Import Preview Linebreak',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: line-break DOCX stays readable in admin preview"',
                        ],
                        [
                            'label' => 'Playwright Import Rich Preview Review Parity',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: rich import stays consistent between admin preview and student review"',
                        ],
                        [
                            'label' => 'Playwright Import Linebreak Review Parity',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: line-break review stays consistent after finish"',
                        ],
                        [
                            'label' => 'Playwright Import Invalid Failure List',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: invalid DOCX import shows precise failure list"',
                        ],
                        [
                            'label' => 'Playwright Authoring MC Empty Correct',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: manual MC save blocks empty correct option"',
                        ],
                        [
                            'label' => 'Playwright Authoring MA Empty Correct',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: manual MA save blocks checked empty option"',
                        ],
                        [
                            'label' => 'Playwright Authoring TFM Validation',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: manual TF matrix save blocks numbering gap and duplicate statement"',
                        ],
                    ],
                ],
            ],
            'security_log_observability' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Security Log & Observability',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Security Log & Observability.',
                    'commands' => [
                        [
                            'label' => 'Vitest Security Log',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/security-manager.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Native Bridge',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/native-bridge.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Security Log',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/SecurityLogObservabilityTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Native Security Event',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RestNativeSecurityEventTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Security Log & Observability',
                    'description' => 'Mengantrekan skenario Playwright Security Log & Observability ke background job secara granular per item checklist.',
                    'commands' => [
                        [
                            'label' => 'Playwright Security Frontend Log Visible',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: frontend clipboard event appears in observability panel"',
                        ],
                        [
                            'label' => 'Playwright Security Admin Follow Up',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: admin reset login creates follow-up security log entry"',
                        ],
                        [
                            'label' => 'Playwright Security Must Watch Order',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: must-watch ordering prioritizes the higher-risk attempt"',
                        ],
                        [
                            'label' => 'Playwright Security Multi Event Aggregate',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: multiple events on one attempt stay aggregated with stable indicators"',
                        ],
                        [
                            'label' => 'Playwright Security Refresh Persistence',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: frontend event remains visible after admin refresh"',
                        ],
                        [
                            'label' => 'Playwright Security Native Direct API',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: native direct API event appears in observability panel"',
                        ],
                        [
                            'label' => 'Playwright Security Native Tool',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: native tab sample request and simulate tool create visible native log"',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle_run_unit_test_suite(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        $tab = self::normalize_unit_test_tab(isset($_POST['cbt_unit_test_tab']) ? wp_unslash((string) $_POST['cbt_unit_test_tab']) : '');
        $scope = self::normalize_unit_test_scope(isset($_POST['cbt_checklist_scope']) ? wp_unslash((string) $_POST['cbt_checklist_scope']) : '');
        $item_index_requested = array_key_exists('cbt_checklist_item_index', $_POST);
        check_admin_referer('cbt_test_hub_runner_' . $tab);

        if ($scope === 'smoke_tests') {
            self::redirect_test_hub_after_run($tab, null, '', 'Checklist Flow Check sekarang dijalankan di background. Gunakan action flow check untuk mengantrekan job.', $scope);
        }

        $runners = self::get_unit_test_runner_definitions();
        if (empty($runners[$tab]) || !is_array($runners[$tab])) {
            self::redirect_test_hub_after_run($tab, null, '', 'Runner test untuk tab ini belum tersedia.');
        }

        if (!function_exists('proc_open')) {
            self::redirect_test_hub_after_run($tab, null, '', 'Runner test membutuhkan fungsi proc_open yang aktif di PHP.');
        }

        $runner = isset($runners[$tab][$scope]) && is_array($runners[$tab][$scope]) ? (array) $runners[$tab][$scope] : [];
        if (empty($runner)) {
            self::redirect_test_hub_after_run($tab, null, '', 'Runner test untuk checklist ini belum tersedia.', $scope);
        }
        $commands = isset($runner['commands']) && is_array($runner['commands']) ? $runner['commands'] : [];
        $item_index = null;
        $item_label = '';
        if ($item_index_requested) {
            $item_index = self::normalize_unit_test_item_index(
                isset($_POST['cbt_checklist_item_index']) ? wp_unslash((string) $_POST['cbt_checklist_item_index']) : '',
                $tab,
                $scope
            );
            if ($item_index === null) {
                self::redirect_test_hub_after_run($tab, null, '', 'Task checklist yang dipilih tidak valid atau sudah tidak tersedia.', $scope);
            }

            $checklist_items = self::get_unit_test_checklist_items($tab, $scope);
            $item_definition = isset($checklist_items[$item_index]) && is_array($checklist_items[$item_index])
                ? (array) $checklist_items[$item_index]
                : [];
            $item_label = trim((string) ($item_definition['label'] ?? ''));
            $commands = self::filter_runner_commands_for_item($commands, $item_definition);
            if (empty($commands)) {
                self::redirect_test_hub_after_run($tab, null, '', 'Task checklist ini belum memiliki runner khusus yang bisa dijalankan.', $scope);
            }
        }
        if (empty($commands)) {
            self::redirect_test_hub_after_run($tab, null, '', 'Runner test untuk checklist ini belum memiliki command yang bisa dijalankan.', $scope);
        }

        $results = [];
        $success = true;

        foreach ($commands as $command_definition) {
            $command = is_array($command_definition) ? (string) ($command_definition['command'] ?? '') : '';
            if ($command === '') {
                continue;
            }

            $results[] = self::run_unit_test_command(
                (string) ($command_definition['label'] ?? 'Test Command'),
                $command,
                self::build_runner_environment()
            );

            if (empty($results[count($results) - 1]['success'])) {
                $success = false;
            }
        }

        if (empty($results)) {
            self::redirect_test_hub_after_run($tab, null, '', 'Runner test tidak menghasilkan command yang bisa dijalankan.', $scope);
        }

        $token = strtolower((string) wp_generate_password(12, false, false));
        $result_payload = [
            'tab' => $tab,
            'scope' => $scope,
            'item_index' => $item_index,
            'item_label' => $item_label,
            'label' => (string) ($runner['label'] ?? 'Run Tests'),
            'description' => (string) ($runner['description'] ?? ''),
            'success' => $success,
            'executed_at' => time(),
            'commands' => $results,
        ];
        set_transient(self::UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $token, $result_payload, self::UNIT_TEST_RUN_RESULT_TTL);

        $message = $success
            ? ($item_label !== ''
                ? 'Task checklist berhasil dijalankan: ' . $item_label
                : 'Suite test untuk ' . str_replace('_', ' ', $tab) . ' berhasil dijalankan.')
            : '';
        $error = $success
            ? ''
            : ($item_label !== ''
                ? 'Ada command test yang gagal pada task checklist ini. Periksa output runner di panel hasil.'
                : 'Ada command test yang gagal. Periksa output runner di panel hasil.');

        self::redirect_test_hub_after_run($tab, $token, $message, $error, $scope);
    }

    public static function handle_run_all_unit_tests(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_run_all_unit_tests');
        $tab = self::normalize_unit_test_tab(isset($_POST['cbt_unit_test_tab']) ? wp_unslash((string) $_POST['cbt_unit_test_tab']) : '');
        $scope = self::normalize_unit_test_scope(isset($_POST['cbt_checklist_scope']) ? wp_unslash((string) $_POST['cbt_checklist_scope']) : '');

        if (!function_exists('proc_open')) {
            self::redirect_test_hub_after_global_run($tab, $scope, null, '', 'Runner test membutuhkan fungsi proc_open yang aktif di PHP.');
        }

        $runner_definitions = self::get_unit_test_runner_definitions();
        $tab_definitions = self::get_unit_test_tab_definitions();
        $environment = self::build_runner_environment();
        $tab_results = [];
        $success = true;
        $passed_count = 0;
        $failed_count = 0;

        foreach (array_keys($tab_definitions) as $tab_key) {
            $tab_key = self::normalize_unit_test_tab((string) $tab_key);
            $runner = isset($runner_definitions[$tab_key]['unit_tests']) && is_array($runner_definitions[$tab_key]['unit_tests'])
                ? (array) $runner_definitions[$tab_key]['unit_tests']
                : [];
            $commands = isset($runner['commands']) && is_array($runner['commands']) ? array_values((array) $runner['commands']) : [];

            if (empty($commands)) {
                continue;
            }

            $results = [];
            $tab_success = true;
            foreach ($commands as $command_definition) {
                $command = is_array($command_definition) ? (string) ($command_definition['command'] ?? '') : '';
                if ($command === '') {
                    continue;
                }

                $result = self::run_unit_test_command(
                    (string) ($command_definition['label'] ?? 'Test Command'),
                    $command,
                    $environment
                );
                $results[] = $result;
                $case_counts = isset($result['test_case_counts']) && is_array($result['test_case_counts'])
                    ? (array) $result['test_case_counts']
                    : [];
                $passed_count += max(0, (int) ($case_counts['passed'] ?? 0));
                $failed_count += max(0, (int) ($case_counts['failed'] ?? 0));
                if (empty($result['success'])) {
                    $tab_success = false;
                    $success = false;
                }
            }

            if (empty($results)) {
                continue;
            }

            $tab_results[$tab_key] = [
                'tab' => $tab_key,
                'scope' => 'unit_tests',
                'label' => (string) ($runner['label'] ?? 'Run Tests'),
                'description' => (string) ($runner['description'] ?? ''),
                'success' => $tab_success,
                'commands' => $results,
            ];
        }

        if (empty($tab_results)) {
            self::redirect_test_hub_after_global_run($tab, $scope, null, '', 'Runner global unit test belum memiliki command yang bisa dijalankan.');
        }

        $token = strtolower((string) wp_generate_password(12, false, false));
        $result_payload = [
            'type' => 'global_unit_tests',
            'success' => $success,
            'executed_at' => time(),
            'label' => 'Run All Unit Tests',
            'summary' => [
                'passed_count' => $passed_count,
                'failed_count' => $failed_count,
                'total_count' => $passed_count + $failed_count,
            ],
            'tabs' => $tab_results,
        ];
        set_transient(self::GLOBAL_UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $token, $result_payload, self::UNIT_TEST_RUN_RESULT_TTL);

        $message = $success
            ? 'Semua runner unit test global berhasil dijalankan.'
            : '';
        $error = $success
            ? ''
            : 'Ada runner unit test yang gagal pada run global. Periksa ringkasan pass/fail dan output per area.';

        self::redirect_test_hub_after_global_run($tab, $scope, $token, $message, $error);
    }

    public static function handle_queue_flow_check_job(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        $tab = self::normalize_unit_test_tab(isset($_POST['cbt_unit_test_tab']) ? wp_unslash((string) $_POST['cbt_unit_test_tab']) : '');
        $scope = self::normalize_unit_test_scope(isset($_POST['cbt_checklist_scope']) ? wp_unslash((string) $_POST['cbt_checklist_scope']) : '');
        $item_index_requested = array_key_exists('cbt_checklist_item_index', $_POST);
        check_admin_referer('cbt_test_hub_runner_' . $tab);

        if (!function_exists('proc_open')) {
            self::redirect_test_hub_after_run($tab, null, '', 'Flow check membutuhkan fungsi proc_open yang aktif di PHP.', $scope);
        }

        if ($scope !== 'smoke_tests') {
            self::redirect_test_hub_after_run($tab, null, '', 'Queue async hanya tersedia untuk Checklist Flow Check.', $scope);
        }

        $runners = self::get_unit_test_runner_definitions();
        $runner = isset($runners[$tab][$scope]) && is_array($runners[$tab][$scope]) ? (array) $runners[$tab][$scope] : [];
        $commands = isset($runner['commands']) && is_array($runner['commands']) ? (array) $runner['commands'] : [];
        if (empty($runner) || empty($commands)) {
            self::redirect_test_hub_after_run($tab, null, '', 'Runner flow check untuk checklist ini belum tersedia.', $scope);
        }

        $checklist_items = self::get_unit_test_checklist_items($tab, $scope);
        $latest_jobs = self::build_latest_flow_job_lookup(self::read_flow_check_jobs());
        $has_running_jobs = self::has_running_flow_jobs_only($latest_jobs);
        $requested_item_index = null;
        if ($item_index_requested) {
            $requested_item_index = self::normalize_unit_test_item_index(
                isset($_POST['cbt_checklist_item_index']) ? wp_unslash((string) $_POST['cbt_checklist_item_index']) : '',
                $tab,
                $scope
            );
            if ($requested_item_index === null) {
                self::redirect_test_hub_after_run($tab, null, '', 'Task flow check yang dipilih tidak valid atau sudah tidak tersedia.', $scope);
            }
        }
        $queued_count = 0;
        $skipped_labels = [];
        $queued_job_ids = [];

        foreach ($checklist_items as $index => $item_definition) {
            if ($requested_item_index !== null && (int) $index !== $requested_item_index) {
                continue;
            }

            if (!is_array($item_definition)) {
                continue;
            }

            $item_label = trim((string) ($item_definition['label'] ?? ''));
            $item_commands = self::filter_runner_commands_for_item($commands, $item_definition);
            if (empty($item_commands)) {
                if ($item_label !== '') {
                    $skipped_labels[] = $item_label;
                }
                continue;
            }

            $latest_job = self::resolve_latest_flow_job_for_item($latest_jobs, $tab, $scope, (int) $index);
            if (!empty($latest_job) && in_array((string) ($latest_job['status'] ?? ''), ['queued', 'running'], true)) {
                if ($item_label !== '') {
                    $skipped_labels[] = $item_label;
                }
                continue;
            }

            $job = self::create_flow_check_job($tab, $scope, (int) $index, $item_definition, $item_commands);
            self::write_flow_check_job($job);
            $queued_job_ids[] = (string) ($job['job_id'] ?? '');
            $queued_count += 1;
            $latest_jobs[self::flow_job_lookup_key($tab, $scope, (int) $index)] = $job;
        }

        if ($queued_count <= 0) {
            $error = !empty($skipped_labels)
                ? 'Task flow check sudah sedang berjalan atau belum punya runner: ' . implode(', ', $skipped_labels)
                : 'Tidak ada task flow check yang bisa diantrikan.';
            self::redirect_test_hub_after_run($tab, null, '', $error, $scope);
        }

        if (!$has_running_jobs) {
            self::start_flow_check_job_process((string) $queued_job_ids[0]);
        }

        $message = $queued_count === 1
            ? 'Flow check berhasil diantrikan di background.'
            : $queued_count . ' task flow check berhasil diantrikan di background.';
        if (!empty($skipped_labels)) {
            $message .= ' Beberapa task dilewati karena masih queued/running: ' . implode(', ', $skipped_labels) . '.';
        }

        wp_safe_redirect(self::test_hub_page_url([
            'cbt_unit_test_tab' => $tab,
            'cbt_checklist_scope' => $scope,
            'cbt_msg' => $message,
        ]));
        exit;
    }

    public static function handle_save_settings(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_test_hub_settings');
        $tab = self::normalize_unit_test_tab(isset($_POST['cbt_unit_test_tab']) ? wp_unslash((string) $_POST['cbt_unit_test_tab']) : '');
        $scope = self::normalize_unit_test_scope(isset($_POST['cbt_checklist_scope']) ? wp_unslash((string) $_POST['cbt_checklist_scope']) : '');

        $settings = self::sanitize_settings_input([
            'e2e_base_url' => isset($_POST['e2e_base_url']) ? wp_unslash((string) $_POST['e2e_base_url']) : '',
        ]);

        self::save_settings($settings);

        wp_safe_redirect(self::test_hub_page_url([
            'cbt_unit_test_tab' => $tab,
            'cbt_checklist_scope' => $scope,
            'cbt_msg' => 'Pengaturan Playwright E2E berhasil disimpan.',
        ]));
        exit;
    }

    /**
     * @return array{label:string,command:string,success:bool,exit_code:int,stdout:string,stderr:string,failure_summary:string,test_case_counts:array{passed:int,failed:int,total:int}}
     */
    private static function run_unit_test_command(string $label, string $command, array $environment = []): array
    {
        $wrapped_command = '/bin/bash -lc ' . escapeshellarg($command);
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($wrapped_command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH, $environment);
        if (!is_resource($process)) {
            return [
                'label' => $label,
                'command' => $command,
                'success' => false,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Gagal memulai proses runner test.',
                'failure_summary' => 'Gagal memulai proses runner test.',
                'test_case_counts' => [
                    'passed' => 0,
                    'failed' => 1,
                    'total' => 1,
                ],
            ];
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);
        $test_case_counts = self::extract_unit_test_case_counts($stdout, $stderr, (int) $exit_code);

        return [
            'label' => $label,
            'command' => $command,
            'success' => $exit_code === 0,
            'exit_code' => (int) $exit_code,
            'stdout' => self::truncate_unit_test_output($stdout),
            'stderr' => self::truncate_unit_test_output($stderr),
            'failure_summary' => $exit_code === 0 ? '' : self::summarize_unit_test_failure_output($stdout, $stderr),
            'test_case_counts' => $test_case_counts,
        ];
    }

    /**
     * @return array{passed:int,failed:int,total:int}
     */
    private static function extract_unit_test_case_counts(string $stdout, string $stderr, int $exit_code): array
    {
        $combined = trim($stdout . "\n" . $stderr);
        $passed = 0;
        $failed = 0;

        if (preg_match('/^\s*Tests\s+(.+)$/mi', $combined, $matches)) {
            $vitest_summary = (string) ($matches[1] ?? '');
            if (preg_match('/(\d+)\s+passed/i', $vitest_summary, $pass_matches)) {
                $passed = (int) $pass_matches[1];
            }
            if (preg_match('/(\d+)\s+failed/i', $vitest_summary, $fail_matches)) {
                $failed = (int) $fail_matches[1];
            }
            if ($passed > 0 || $failed > 0) {
                return [
                    'passed' => $passed,
                    'failed' => $failed,
                    'total' => $passed + $failed,
                ];
            }
        }

        if (preg_match('/OK\s+\((\d+)\s+tests?/i', $combined, $matches)) {
            $passed = (int) $matches[1];
            return [
                'passed' => $passed,
                'failed' => 0,
                'total' => $passed,
            ];
        }

        if (preg_match('/Tests:\s*(\d+),\s*Assertions:\s*\d+(.*)/i', $combined, $matches)) {
            $total = (int) $matches[1];
            $defect_summary = (string) ($matches[2] ?? '');
            $failed = 0;
            if (preg_match('/Failures:\s*(\d+)/i', $defect_summary, $failure_matches)) {
                $failed += (int) $failure_matches[1];
            }
            if (preg_match('/Errors:\s*(\d+)/i', $defect_summary, $error_matches)) {
                $failed += (int) $error_matches[1];
            }
            if ($failed > 0) {
                $passed = max(0, $total - $failed);
                return [
                    'passed' => $passed,
                    'failed' => $failed,
                    'total' => $passed + $failed,
                ];
            }

            return [
                'passed' => max(0, $total),
                'failed' => 0,
                'total' => max(0, $total),
            ];
        }

        if (preg_match('/Tests:\s*(\d+),\s*Assertions:\s*\d+,\s*Skipped:\s*(\d+)/i', $combined, $matches)) {
            $total = max(0, (int) $matches[1]);
            $skipped = max(0, (int) $matches[2]);
            $passed = max(0, $total - $skipped);
            return [
                'passed' => $passed,
                'failed' => 0,
                'total' => $passed,
            ];
        }

        return [
            'passed' => $exit_code === 0 ? 1 : 0,
            'failed' => $exit_code === 0 ? 0 : 1,
            'total' => 1,
        ];
    }

    private static function truncate_unit_test_output(string $output, int $max_length = 12000): string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $output));
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) <= $max_length) {
            return $normalized;
        }

        return substr($normalized, 0, $max_length) . "\n\n...[output truncated]";
    }

    private static function summarize_unit_test_failure_output(string $stdout, string $stderr): string
    {
        $priority_patterns = [
            '/^Error:/i',
            '/\bTimed out\b/i',
            '/\bERR_/i',
            '/\bfailed\b/i',
            '/\bexpected\b/i',
            '/\bExecutable doesn\'t exist\b/i',
        ];

        $candidates = array_merge(
            self::normalize_unit_test_output_lines($stderr),
            self::normalize_unit_test_output_lines($stdout)
        );

        foreach ($priority_patterns as $pattern) {
            foreach ($candidates as $line) {
                if (preg_match($pattern, $line)) {
                    return self::truncate_unit_test_failure_summary($line);
                }
            }
        }

        foreach ($candidates as $line) {
            return self::truncate_unit_test_failure_summary($line);
        }

        return 'Runner gagal tanpa ringkasan error yang jelas. Buka detail output untuk melihat log lengkap.';
    }

    /**
     * @return string[]
     */
    private static function normalize_unit_test_output_lines(string $output): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $output);
        $raw_lines = explode("\n", $normalized);
        $lines = [];

        foreach ($raw_lines as $line) {
            $clean = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', (string) $line);
            $clean = trim((string) $clean);
            if ($clean === '') {
                continue;
            }
            if (preg_match('/^Running \d+ test/i', $clean)) {
                continue;
            }
            if (preg_match('/^\[\d+\/\d+\]/', $clean)) {
                continue;
            }
            if (preg_match('/^\d+ (passed|failed|skipped)/i', $clean)) {
                continue;
            }
            if (preg_match('/^attachment #/i', $clean)) {
                continue;
            }

            $lines[] = $clean;
        }

        return $lines;
    }

    private static function truncate_unit_test_failure_summary(string $line, int $max_length = 220): string
    {
        if (strlen($line) <= $max_length) {
            return $line;
        }

        return substr($line, 0, $max_length - 3) . '...';
    }

    /**
     * @return array<string,string>
     */
    private static function build_runner_environment(): array
    {
        $environment = [];

        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $environment[$key] = (string) $value;
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && !isset($environment[$key]) && is_scalar($value)) {
                $environment[$key] = (string) $value;
            }
        }

        $settings = self::get_settings();
        if ($settings['e2e_base_url'] !== '') {
            $environment['CBT_E2E_BASE_URL'] = $settings['e2e_base_url'];
        }
        $environment['PLAYWRIGHT_BROWSERS_PATH'] = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/.playwright-browsers';
        $environment['PLAYWRIGHT_OUTPUT_DIR'] = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/playwright-results/admin';

        return $environment;
    }

    private static function redirect_test_hub_after_run(string $tab, ?string $token, string $message, string $error, string $scope = 'unit_tests'): void
    {
        $args = [
            'page' => 'cbt-test-hub',
            'cbt_unit_test_tab' => $tab,
            'cbt_checklist_scope' => self::normalize_unit_test_scope($scope),
        ];

        if ($token !== null && $token !== '') {
            $args['cbt_test_run_token'] = $token;
        }
        if ($message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== '') {
            $args['cbt_err'] = $error;
        }

        wp_safe_redirect(self::test_hub_page_url($args));
        exit;
    }

    private static function redirect_test_hub_after_global_run(string $tab, string $scope, ?string $token, string $message, string $error): void
    {
        $args = [
            'page' => 'cbt-test-hub',
            'cbt_unit_test_tab' => self::normalize_unit_test_tab($tab),
            'cbt_checklist_scope' => self::normalize_unit_test_scope($scope),
        ];

        if ($token !== null && $token !== '') {
            $args['cbt_global_unit_run_token'] = $token;
        }
        if ($message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== '') {
            $args['cbt_err'] = $error;
        }

        wp_safe_redirect(self::test_hub_page_url($args));
        exit;
    }

    private static function flow_job_directory_path(): string
    {
        return rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/' . self::FLOW_JOB_DIRECTORY_RELATIVE;
    }

    private static function flow_job_file_path(string $job_id): string
    {
        return self::flow_job_directory_path() . '/' . sanitize_file_name($job_id) . '.json';
    }

    private static function flow_job_log_path(string $job_id): string
    {
        return self::flow_job_directory_path() . '/' . sanitize_file_name($job_id) . '.log';
    }

    private static function ensure_flow_job_directory(): void
    {
        $root_directory = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/playwright-results';
        wp_mkdir_p($root_directory);
        wp_mkdir_p(self::flow_job_directory_path());

        foreach ([$root_directory, self::flow_job_directory_path()] as $directory_path) {
            if (is_dir($directory_path)) {
                @chmod($directory_path, 0777);
            }
        }
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function write_flow_check_job(array $job): void
    {
        self::ensure_flow_job_directory();
        $encoded = wp_json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $job_path = self::flow_job_file_path((string) ($job['job_id'] ?? ''));
        file_put_contents($job_path, $encoded);
        if (is_file($job_path)) {
            @chmod($job_path, 0666);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_flow_check_job_from_path(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function read_flow_check_jobs(): array
    {
        $directory = self::flow_job_directory_path();
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.json');
        if (!is_array($files) || empty($files)) {
            return [];
        }

        $jobs = [];
        foreach ($files as $file_path) {
            $job = self::read_flow_check_job_from_path((string) $file_path);
            if (!is_array($job)) {
                continue;
            }

            $job = self::normalize_flow_check_job_runtime_state($job);
            $jobs[] = $job;
        }

        usort($jobs, static function (array $left, array $right): int {
            return ((int) ($right['created_at'] ?? 0)) <=> ((int) ($left['created_at'] ?? 0));
        });

        return $jobs;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function normalize_flow_check_job_runtime_state(array $job): array
    {
        $status = self::normalize_flow_job_status((string) ($job['status'] ?? 'queued'));
        if ($status !== 'running') {
            return $job;
        }

        $now = time();
        $started_at = (int) ($job['started_at'] ?? 0);
        $heartbeat_at = (int) ($job['heartbeat_at'] ?? 0);
        $worker_pid = (int) ($job['worker_pid'] ?? 0);

        $heartbeat_is_stale = $heartbeat_at > 0 && ($now - $heartbeat_at) > self::FLOW_JOB_HEARTBEAT_TIMEOUT;
        $runtime_is_excessive = $started_at > 0 && ($now - $started_at) > self::FLOW_JOB_MAX_RUNTIME;
        $process_missing = $worker_pid > 0 && !self::is_flow_job_process_running($worker_pid);

        if (!$heartbeat_is_stale && !$runtime_is_excessive && !$process_missing) {
            return $job;
        }

        $reasons = [];
        if ($process_missing) {
            $reasons[] = 'process worker sudah tidak aktif';
        }
        if ($heartbeat_is_stale) {
            $reasons[] = 'heartbeat job sudah stale';
        }
        if ($runtime_is_excessive) {
            $reasons[] = 'runtime job melewati batas aman';
        }

        $failure_kind = ($heartbeat_is_stale || $runtime_is_excessive) ? 'stale' : 'interrupted';

        $job['status'] = 'failed';
        $job['finished_at'] = $now;
        $job['exit_code'] = 1;
        $job['failure_kind'] = $failure_kind;
        $job['failure_summary'] = $failure_kind === 'stale'
            ? 'Job background stale karena runner tidak lagi memberi heartbeat dalam batas aman.'
            : 'Job background terputus sebelum flow check selesai dijalankan.';
        $existing_stderr = trim((string) ($job['stderr'] ?? ''));
        $stale_message = 'Job async ditandai gagal otomatis karena ' . implode(', ', $reasons) . '.';
        $job['stderr'] = $existing_stderr === '' ? $stale_message : ($existing_stderr . PHP_EOL . PHP_EOL . $stale_message);

        self::write_flow_check_job($job);

        return $job;
    }

    private static function is_flow_job_process_running(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            return true;
        }

        if (function_exists('shell_exec')) {
            $output = shell_exec('ps -p ' . (int) $pid . ' -o pid=');
            return is_string($output) && trim($output) !== '';
        }

        return true;
    }

    private static function flow_job_lookup_key(string $tab, string $scope, int $item_index): string
    {
        return $tab . '|' . self::normalize_unit_test_scope($scope) . '|' . $item_index;
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @return array<string,array<string,mixed>>
     */
    private static function build_latest_flow_job_lookup(array $jobs): array
    {
        $lookup = [];

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $tab = self::normalize_unit_test_tab($job['tab'] ?? '');
            $scope = self::normalize_unit_test_scope($job['scope'] ?? 'smoke_tests');
            $item_index = isset($job['item_index']) ? (int) $job['item_index'] : -1;
            if ($item_index < 0) {
                continue;
            }

            $key = self::flow_job_lookup_key($tab, $scope, $item_index);
            if (isset($lookup[$key])) {
                continue;
            }

            $lookup[$key] = $job;
        }

        return $lookup;
    }

    /**
     * @param array<string,array<string,mixed>> $latest_jobs
     * @return array<string,mixed>|null
     */
    private static function resolve_latest_flow_job_for_item(array $latest_jobs, string $tab, string $scope, int $item_index): ?array
    {
        $key = self::flow_job_lookup_key($tab, $scope, $item_index);
        return isset($latest_jobs[$key]) && is_array($latest_jobs[$key]) ? $latest_jobs[$key] : null;
    }

    /**
     * @param array<string,array<string,mixed>> $latest_jobs
     */
    private static function has_active_flow_jobs(array $latest_jobs): bool
    {
        foreach ($latest_jobs as $job) {
            $status = self::normalize_flow_job_status((string) ($job['status'] ?? ''));
            if (in_array($status, ['queued', 'running'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,array<string,mixed>> $latest_jobs
     */
    private static function has_running_flow_jobs_only(array $latest_jobs): bool
    {
        foreach ($latest_jobs as $job) {
            if (self::normalize_flow_job_status((string) ($job['status'] ?? '')) === 'running') {
                return true;
            }
        }

        return false;
    }

    private static function normalize_flow_job_status(string $status): string
    {
        $status = sanitize_key($status);
        if (in_array($status, ['queued', 'running', 'passed', 'failed'], true)) {
            return $status;
        }

        return 'queued';
    }

    /**
     * @param array<string,mixed>|null $job
     * @return array{label:string,tone:string}
     */
    private static function flow_job_status_meta_for_job(?array $job): array
    {
        if (!is_array($job) || empty($job)) {
            return ['label' => 'Queued', 'tone' => 'idle'];
        }

        $status = self::normalize_flow_job_status((string) ($job['status'] ?? 'queued'));
        if ($status === 'failed') {
            $failure_kind = sanitize_key((string) ($job['failure_kind'] ?? ''));
            $stderr = strtolower((string) ($job['stderr'] ?? ''));
            $failure_summary = strtolower((string) ($job['failure_summary'] ?? ''));

            if ($failure_kind === 'stale') {
                return ['label' => 'Stale', 'tone' => 'danger'];
            }

            if ($failure_kind === 'interrupted') {
                return ['label' => 'Interrupted', 'tone' => 'danger'];
            }

            if (
                strpos($stderr, 'heartbeat job sudah stale') !== false
                || strpos($stderr, 'runtime job melewati batas aman') !== false
                || strpos($failure_summary, 'job background stale') !== false
            ) {
                return ['label' => 'Stale', 'tone' => 'danger'];
            }

            if (
                strpos($stderr, 'process worker sudah tidak aktif') !== false
                || strpos($failure_summary, 'job background terputus') !== false
                || strpos($failure_summary, 'host dimatikan') !== false
            ) {
                return ['label' => 'Interrupted', 'tone' => 'danger'];
            }
        }

        switch ($status) {
            case 'running':
                return ['label' => 'Running', 'tone' => 'planned'];
            case 'passed':
                return ['label' => 'Passed', 'tone' => 'done'];
            case 'failed':
                return ['label' => 'Failed', 'tone' => 'danger'];
            default:
                return ['label' => 'Queued', 'tone' => 'idle'];
        }
    }

    /**
     * @param array<string,mixed> $item_definition
     * @param array<int,array<string,mixed>> $item_commands
     * @return array<string,mixed>
     */
    private static function create_flow_check_job(string $tab, string $scope, int $item_index, array $item_definition, array $item_commands): array
    {
        $job_id = 'flow-' . strtolower((string) wp_generate_password(16, false, false));
        $now = time();

        return [
            'job_id' => $job_id,
            'tab' => $tab,
            'scope' => self::normalize_unit_test_scope($scope),
            'item_index' => $item_index,
            'item_label' => trim((string) ($item_definition['label'] ?? '')),
            'status' => 'queued',
            'created_at' => $now,
            'started_at' => 0,
            'finished_at' => 0,
            'heartbeat_at' => 0,
            'worker_pid' => 0,
            'command' => isset($item_commands[0]['command']) ? (string) $item_commands[0]['command'] : '',
            'commands' => array_values($item_commands),
            'results' => [],
            'stdout' => '',
            'stderr' => '',
            'exit_code' => 0,
            'failure_kind' => '',
            'failure_summary' => '',
            'log_path' => self::flow_job_log_path($job_id),
        ];
    }

    private static function maybe_start_next_flow_job(): void
    {
        $jobs = self::read_flow_check_jobs();
        if (empty($jobs)) {
            return;
        }

        $has_running_job = false;
        $next_queued_job_id = '';
        $next_queued_created_at = 0;

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $status = self::normalize_flow_job_status((string) ($job['status'] ?? ''));
            if ($status === 'running') {
                $has_running_job = true;
                break;
            }
            if ($status === 'queued') {
                $created_at = (int) ($job['created_at'] ?? 0);
                if ($next_queued_job_id === '' || $created_at < $next_queued_created_at || $next_queued_created_at <= 0) {
                    $next_queued_job_id = (string) ($job['job_id'] ?? '');
                    $next_queued_created_at = $created_at;
                }
            }
        }

        if ($has_running_job || $next_queued_job_id === '') {
            return;
        }

        self::start_flow_check_job_process($next_queued_job_id);
    }

    private static function start_flow_check_job_process(string $job_id): void
    {
        if ($job_id === '' || !function_exists('proc_open')) {
            return;
        }

        $environment = self::build_runner_environment();
        $environment['CBT_FLOW_JOB_ID'] = $job_id;
        $environment['CBT_FLOW_JOB_FILE'] = self::flow_job_file_path($job_id);

        $command = '/bin/bash -lc ' . escapeshellarg('node tests/e2e/run-flow-check-job.mjs --job-id=' . escapeshellarg($job_id) . ' > /dev/null 2>&1 &');
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH, $environment);
        if (!is_resource($process)) {
            return;
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }

    /**
     * @param array<string,mixed>|null $flow_job
     * @return array<int,array<string,mixed>>
     */
    private static function build_flow_job_run_results(?array $flow_job): array
    {
        if (empty($flow_job) || !is_array($flow_job)) {
            return [];
        }

        $results = isset($flow_job['results']) && is_array($flow_job['results']) ? (array) $flow_job['results'] : [];
        $normalized = [];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $normalized[] = [
                'label' => (string) ($result['label'] ?? 'Flow Check Command'),
                'command' => (string) ($result['command'] ?? ''),
                'success' => !empty($result['success']),
                'exit_code' => (int) ($result['exit_code'] ?? 1),
                'stdout' => self::truncate_unit_test_output((string) ($result['stdout'] ?? '')),
                'stderr' => self::truncate_unit_test_output((string) ($result['stderr'] ?? '')),
                'failure_summary' => (string) ($result['failure_summary'] ?? ''),
            ];
        }

        if (empty($normalized)) {
            $stdout = self::truncate_unit_test_output((string) ($flow_job['stdout'] ?? ''));
            $stderr = self::truncate_unit_test_output((string) ($flow_job['stderr'] ?? ''));
            $failure_summary = (string) ($flow_job['failure_summary'] ?? '');

            if ($stdout !== '' || $stderr !== '' || $failure_summary !== '') {
                $normalized[] = [
                    'label' => (string) ($flow_job['item_label'] ?? 'Flow Check Command'),
                    'command' => (string) ($flow_job['command'] ?? ''),
                    'success' => self::normalize_flow_job_status((string) ($flow_job['status'] ?? 'failed')) === 'passed',
                    'exit_code' => (int) ($flow_job['exit_code'] ?? 1),
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'failure_summary' => $failure_summary,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_unit_test_checklist_items(string $tab, string $scope): array
    {
        $definitions = self::get_unit_test_tab_definitions();
        if (empty($definitions[$tab]) || !is_array($definitions[$tab])) {
            return [];
        }

        $items = isset($definitions[$tab][$scope]) && is_array($definitions[$tab][$scope])
            ? (array) $definitions[$tab][$scope]
            : [];

        return array_values($items);
    }

    private static function normalize_unit_test_item_index($raw_index, string $tab, string $scope): ?int
    {
        if ($raw_index === '' || $raw_index === null) {
            return null;
        }

        if (!is_scalar($raw_index)) {
            return null;
        }

        $normalized = filter_var((string) $raw_index, FILTER_VALIDATE_INT);
        if ($normalized === false) {
            return null;
        }

        $index = (int) $normalized;
        if ($index < 0) {
            return null;
        }

        $items = self::get_unit_test_checklist_items($tab, $scope);

        return isset($items[$index]) ? $index : null;
    }

    /**
     * @param array<int,array<string,mixed>> $commands
     * @param array<string,mixed> $item_definition
     * @return array<int,array<string,mixed>>
     */
    private static function filter_runner_commands_for_item(array $commands, array $item_definition): array
    {
        $runner_labels = self::normalize_unit_test_note_list($item_definition['runner_commands'] ?? []);
        if (empty($runner_labels)) {
            return [];
        }

        $allowed_labels = array_fill_keys($runner_labels, true);
        $filtered = [];
        foreach ($commands as $command_definition) {
            if (!is_array($command_definition)) {
                continue;
            }

            $command_label = trim((string) ($command_definition['label'] ?? ''));
            if ($command_label === '' || !isset($allowed_labels[$command_label])) {
                continue;
            }

            $filtered[] = $command_definition;
        }

        return $filtered;
    }

    /**
     * @return array<string,string>
     */
    private static function unit_test_status_meta(string $status): array
    {
        switch (sanitize_key($status)) {
            case 'ready':
                return [
                    'label' => 'Ready',
                    'tone' => 'done',
                ];
            case 'blocked':
                return [
                    'label' => 'Blocked',
                    'tone' => 'danger',
                ];
            case 'planned':
                return [
                    'label' => 'Planned',
                    'tone' => 'planned',
                ];
            default:
                return [
                    'label' => 'Draft',
                    'tone' => 'idle',
                ];
        }
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function unit_test_checklist_item(string $label, string $status = 'planned', array $meta = []): array
    {
        $item = [
            'label' => $label,
            'status' => sanitize_key($status),
        ];

        foreach (['description', 'process_steps', 'evidence', 'runner_commands', 'open_by_default'] as $meta_key) {
            if (array_key_exists($meta_key, $meta)) {
                $item[$meta_key] = $meta[$meta_key];
            }
        }

        return $item;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed>|null $unit_test_run_result
     * @param array<string,mixed>|null $flow_job
     * @return array<string,mixed>
     */
    private static function build_unit_test_checklist_item_context(string $tab_key, string $list_key, int $item_index, array $item, ?array $unit_test_run_result, ?array $global_unit_run_result = null, ?array $flow_job = null): array
    {
        $status = sanitize_key((string) ($item['status'] ?? 'draft'));
        $description = isset($item['description']) ? trim((string) $item['description']) : '';
        $process_steps = self::normalize_unit_test_note_list($item['process_steps'] ?? []);
        $evidence = self::normalize_unit_test_note_list($item['evidence'] ?? []);
        $runner_commands = self::normalize_unit_test_note_list($item['runner_commands'] ?? []);

        if ($description === '') {
            $description = self::default_unit_test_checklist_description($list_key, $status);
        }
        if (empty($process_steps)) {
            $process_steps = self::default_unit_test_process_steps($list_key, $status);
        }

        $run_results = $list_key === 'smoke_tests'
            ? self::build_flow_job_run_results($flow_job)
            : self::resolve_unit_test_item_run_results($tab_key, $list_key, $runner_commands, $unit_test_run_result, $global_unit_run_result);
        $has_runner_output = !empty($run_results);
        $failed_run_results = array_values(array_filter(
            $run_results,
            static function ($run_result): bool {
                return is_array($run_result) && empty($run_result['success']);
            }
        ));
        $has_failed_run_results = !empty($failed_run_results);
        $job_status = $list_key === 'smoke_tests' && !empty($flow_job) ? self::normalize_flow_job_status((string) ($flow_job['status'] ?? 'queued')) : '';
        $job_status_meta = $job_status !== '' ? self::flow_job_status_meta_for_job($flow_job) : null;
        $async_output_preview = $list_key === 'smoke_tests' ? self::build_flow_job_output_preview($flow_job) : '';
        $should_surface_async_preview = in_array((string) ($job_status_meta['label'] ?? ''), ['Interrupted', 'Stale'], true) && $async_output_preview !== '';

        $item['detail_id'] = sanitize_html_class($tab_key . '-' . $list_key . '-' . $item_index);
        $item['item_index'] = $item_index;
        $item['description'] = $description;
        $item['process_steps'] = $process_steps;
        $item['evidence'] = $evidence;
        $item['runner_commands'] = $runner_commands;
        $item['run_results'] = $run_results;
        $item['failed_run_results'] = $failed_run_results;
        $item['has_failed_run_results'] = $has_failed_run_results;
        $item['has_runner'] = !empty($runner_commands);
        $item['async_job'] = $flow_job;
        $item['async_status'] = $job_status;
        $item['async_status_label'] = is_array($job_status_meta) ? (string) $job_status_meta['label'] : '';
        $item['async_status_tone'] = is_array($job_status_meta) ? (string) $job_status_meta['tone'] : 'idle';
        $item['async_output_preview'] = $should_surface_async_preview ? $async_output_preview : '';
        $item['is_job_active'] = in_array($job_status, ['queued', 'running'], true);
        $item['can_run_task'] = !empty($runner_commands) && !in_array($job_status, ['queued', 'running'], true);
        $item['run_button_label'] = in_array($job_status, ['queued', 'running'], true)
            ? ($job_status === 'running' ? 'Running...' : 'Queued...')
            : 'Run Task';
        $item['detail_hint'] = $has_runner_output
            ? 'Runner terbaru tersedia'
            : (
                in_array($job_status, ['queued', 'running'], true)
                    ? 'Flow check sedang diproses di background'
                    : ($status === 'ready' ? 'Coverage awal sudah ditautkan' : 'Backlog dan proses verifikasi')
            );
        $item['detail_open'] = !empty($item['open_by_default']) || $should_surface_async_preview || $has_failed_run_results;

        return $item;
    }

    /**
     * @param array<string,mixed>|null $flow_job
     */
    private static function build_flow_job_output_preview(?array $flow_job): string
    {
        if (!is_array($flow_job) || empty($flow_job)) {
            return '';
        }

        $candidates = [
            trim((string) ($flow_job['failure_summary'] ?? '')),
            trim((string) ($flow_job['stderr'] ?? '')),
            trim((string) ($flow_job['stdout'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $lines = preg_split("/\\r\\n|\\n|\\r/", $candidate);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                return self::truncate_unit_test_failure_summary($line, 260);
            }
        }

        return '';
    }

    /**
     * @param mixed $raw_list
     * @return string[]
     */
    private static function normalize_unit_test_note_list($raw_list): array
    {
        if (is_string($raw_list)) {
            $trimmed = trim($raw_list);
            return $trimmed === '' ? [] : [$trimmed];
        }

        if (!is_array($raw_list)) {
            return [];
        }

        $normalized = [];
        foreach ($raw_list as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return $normalized;
    }

    private static function default_unit_test_checklist_description(string $list_key, string $status): string
    {
        if ($list_key === 'smoke_tests') {
            return 'Checkpoint untuk memverifikasi flow nyata di browser atau admin. Detail ini dipakai sebagai panduan saat flow check belum diotomatisasi.';
        }

        if ($status === 'ready') {
            return 'Checklist ini sudah punya coverage awal. Gunakan detail di bawah untuk melihat proses verifikasi, evidence file, dan hasil runner terbaru yang terhubung.';
        }

        if ($status === 'blocked') {
            return 'Checklist ini tertahan. Detail di bawah dipakai untuk menunjukkan gap implementasi dan target verifikasi berikutnya.';
        }

        return 'Checklist ini masih backlog. Detail di bawah dipakai untuk mengarahkan target file, proses verifikasi, dan evidence yang perlu disiapkan.';
    }

    /**
     * @return string[]
     */
    private static function default_unit_test_process_steps(string $list_key, string $status): array
    {
        if ($list_key === 'smoke_tests') {
            return [
                'Jalankan flow manual di browser atau admin sesuai skenario item ini.',
                'Cocokkan hasil aktual dengan state atau output yang diharapkan.',
                'Naikkan status setelah flow check stabil dan punya bukti verifikasi.',
            ];
        }

        if ($status === 'ready') {
            return [
                'Tinjau file test atau target code yang ditautkan pada bagian evidence.',
                'Jalankan runner tab ini untuk melihat hasil eksekusi terbaru.',
                'Bandingkan hasil runner dengan status checklist sebelum mengubah coverage plan.',
            ];
        }

        return [
            'Tentukan target file, assertion, dan mode test yang paling tepat untuk item ini.',
            'Tambahkan suite JS, PHP, atau integration yang relevan ke backlog implementasi.',
            'Naikkan status ke Ready setelah coverage dasar sudah ada dan bisa dijalankan ulang.',
        ];
    }

    /**
     * @param string[] $runner_commands
     * @param array<string,mixed>|null $unit_test_run_result
     * @return array<int,array<string,mixed>>
     */
    private static function resolve_unit_test_item_run_results(string $tab_key, string $list_key, array $runner_commands, ?array $unit_test_run_result, ?array $global_unit_run_result = null): array
    {
        if (empty($runner_commands)) {
            return [];
        }

        $commands = self::resolve_unit_test_result_commands($tab_key, $list_key, $unit_test_run_result);
        if (empty($commands) && !empty($global_unit_run_result) && is_array($global_unit_run_result)) {
            $commands = self::resolve_unit_test_result_commands($tab_key, $list_key, $global_unit_run_result);
        }
        if (empty($commands)) {
            return [];
        }

        $command_lookup = [];
        foreach ($commands as $command_result) {
            if (!is_array($command_result)) {
                continue;
            }

            $command_label = trim((string) ($command_result['label'] ?? ''));
            if ($command_label === '') {
                continue;
            }

            $command_lookup[$command_label] = $command_result;
        }

        $matched_results = [];
        foreach ($runner_commands as $command_label) {
            if (!isset($command_lookup[$command_label]) || !is_array($command_lookup[$command_label])) {
                continue;
            }

            $matched_results[] = $command_lookup[$command_label];
        }

        return $matched_results;
    }

    /**
     * @param array<string,mixed>|null $unit_test_run_result
     * @return array<int,array<string,mixed>>
     */
    private static function resolve_unit_test_result_commands(string $tab_key, string $list_key, ?array $unit_test_run_result): array
    {
        if (empty($unit_test_run_result) || !is_array($unit_test_run_result)) {
            return [];
        }

        $normalized_scope = self::normalize_unit_test_scope($list_key);
        $result_type = (string) ($unit_test_run_result['type'] ?? '');
        if ($result_type === 'global_unit_tests') {
            if ($normalized_scope !== 'unit_tests') {
                return [];
            }

            $tabs = isset($unit_test_run_result['tabs']) && is_array($unit_test_run_result['tabs'])
                ? (array) $unit_test_run_result['tabs']
                : [];
            $tab_result = isset($tabs[$tab_key]) && is_array($tabs[$tab_key]) ? (array) $tabs[$tab_key] : [];
            return isset($tab_result['commands']) && is_array($tab_result['commands'])
                ? array_values((array) $tab_result['commands'])
                : [];
        }

        if ((string) ($unit_test_run_result['tab'] ?? '') !== $tab_key) {
            return [];
        }

        if (self::normalize_unit_test_scope($unit_test_run_result['scope'] ?? 'unit_tests') !== $normalized_scope) {
            return [];
        }

        return isset($unit_test_run_result['commands']) && is_array($unit_test_run_result['commands'])
            ? array_values((array) $unit_test_run_result['commands'])
            : [];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function get_unit_test_tab_definitions(): array
    {
        $tabs = [
            'recovery_persistence' => [
                'label' => 'Recovery & Persistence',
                'summary' => 'Checkpoint utama untuk memastikan progress kerja siswa pulih kembali setelah refresh atau reopen, sementara snapshot lokal dan cache attempt tetap tahan banting.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Resolver local vs remote snapshot memilih state attempt yang paling aman dan paling baru.', 'ready', [
                        'description' => 'Resolver restore frontend sekarang punya coverage formal untuk local-preferred, remote-preferred, dan fallback freshness saat question cache tidak bisa memutus konflik.',
                        'process_steps' => [
                            'Vitest memverifikasi snapshot lokal dipilih saat payload cache mendukung index lokal.',
                            'Vitest memverifikasi snapshot remote dipilih saat hanya index remote yang punya payload cache valid.',
                            'Vitest memverifikasi fallback freshness memilih snapshot dengan updated_at terbaru saat cache tidak bisa menjadi tie-breaker.',
                        ],
                        'evidence' => [
                            'Target frontend: src/frontend/app/storage/attempt-ui-state.js',
                            'tests/js/unit/attempt-ui-state.test.js',
                        ],
                        'runner_commands' => ['Vitest Recovery'],
                    ]),
                    self::unit_test_checklist_item('Current question dan doubtful ids dipulihkan kembali dengan indeks yang tetap valid setelah refresh atau reopen.', 'ready', [
                        'description' => 'Coverage awal sudah ada di frontend restore path dan backend normalizer, sehingga current question yang tidak valid serta doubtful ids lama bisa dipetakan ulang dengan aman.',
                        'process_steps' => [
                            'Vitest memverifikasi restore snapshot tetap mengarah ke current question yang valid dan doubtful ids lama masih terbaca.',
                            'PHPUnit memverifikasi normalizer backend menyaring question id di luar order attempt dan clamp current_index.',
                            'Jalankan runner Recovery untuk melihat hasil terbaru dari dua sisi tersebut.',
                        ],
                        'evidence' => [
                            'tests/js/unit/attempt-ui-state.test.js',
                            'tests/php/unit/UiStateAttemptNormalizationTest.php',
                            'includes/class-cbt-ui-state.php',
                        ],
                        'runner_commands' => ['Vitest Recovery', 'PHPUnit Recovery'],
                    ]),
                    self::unit_test_checklist_item('Attempt UI persistence tetap aman saat tab ditutup, browser dibuka ulang, atau sesi dilanjutkan kembali.', 'ready', [
                        'description' => 'Suite frontend saat ini sudah memverifikasi persist-read-clear snapshot attempt UI, sehingga jalur storage dasar untuk reopen dan restore tidak lagi hanya asumsi.',
                        'process_steps' => [
                            'Vitest menulis snapshot ke storage attempt UI, membaca ulang payload, lalu membersihkannya untuk memastikan roundtrip stabil.',
                            'Verifikasi dilakukan pada jalur storage yang dipakai restore state sebelum flow reopen attempt berjalan.',
                            'Gunakan hasil runner terbaru untuk memastikan roundtrip masih lolos setelah perubahan storage berikutnya.',
                        ],
                        'evidence' => [
                            'tests/js/unit/attempt-ui-state.test.js',
                            'src/frontend/app/storage/attempt-ui-state.js',
                        ],
                        'runner_commands' => ['Vitest Recovery'],
                    ]),
                    self::unit_test_checklist_item('Cache durability dan cleanup tidak menghapus state attempt aktif secara prematur.', 'ready', [
                        'description' => 'Coverage backend sekarang memastikan invalidation namespace non-UI tidak ikut menghapus snapshot attempt yang tersimpan di namespace ui_state.',
                        'process_steps' => [
                            'PHPUnit menyimpan attempt state aktif lalu menjalankan invalidation namespace non-UI seperti catalog dan analytics.',
                            'Setelah cleanup non-UI dijalankan, snapshot attempt dibaca ulang untuk memastikan nilainya tetap identik.',
                            'Runner Recovery menjadi bukti bahwa invalidation non-UI tidak menyapu state attempt aktif.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UiStateRecoveryPersistenceTest.php',
                            'includes/class-cbt-cache.php',
                            'includes/class-cbt-ui-state.php',
                        ],
                        'runner_commands' => ['PHPUnit Recovery'],
                    ]),
                    self::unit_test_checklist_item('Revision mismatch hanya menolak state yang tidak kompatibel sambil mempertahankan jawaban yang masih valid.', 'ready', [
                        'description' => 'Coverage restore cache sekarang membedakan snapshot kompatibel versus mismatch revision atau order signature, sehingga jawaban valid bisa digabung hanya saat snapshot memang share revision yang sama.',
                        'process_steps' => [
                            'Vitest memverifikasi snapshot yang share revision digabung sehingga jawaban valid dari dua sumber tetap dipertahankan.',
                            'Vitest memverifikasi snapshot stale ditolak saat revision dan order signature berubah, lalu snapshot baru menjadi sumber restore tunggal.',
                            'Fixture restore ini berjalan lewat readPersistedQuestionCache agar menembak jalur recovery yang benar-benar dipakai runtime.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-cache-recovery.test.js',
                            'src/frontend/app/storage/question-cache.js',
                        ],
                        'runner_commands' => ['Vitest Recovery'],
                    ]),
                    self::unit_test_checklist_item('Storage corrupt atau JSON invalid fallback aman ke null tanpa melempar error ke runtime restore.', 'ready', [
                        'description' => 'Vitest sudah menutup kasus JSON corrupt agar restore path tidak melempar error dan langsung fallback ke null secara aman.',
                        'process_steps' => [
                            'Vitest menulis payload storage yang invalid lalu memanggil restore path untuk memastikan hasilnya null.',
                            'Verifikasi dilakukan tanpa bergantung ke browser nyata sehingga regress parsing bisa cepat tertangkap.',
                            'Jalankan runner Recovery untuk mengecek hasil terbaru suite ini.',
                        ],
                        'evidence' => [
                            'tests/js/unit/attempt-ui-state.test.js',
                            'src/frontend/app/storage/attempt-ui-state.js',
                        ],
                        'runner_commands' => ['Vitest Recovery'],
                    ]),
                    self::unit_test_checklist_item('Legacy doubtful state tetap termigrasi ke doubtful_question_ids saat snapshot baru belum tersedia.', 'ready', [
                        'description' => 'Coverage awal sudah ada untuk dua bentuk legacy doubtful state: fallback frontend saat snapshot baru belum tersedia dan normalisasi backend pada struktur lookup lama.',
                        'process_steps' => [
                            'Vitest memverifikasi legacy doubtful state di storage frontend tetap dibaca sebagai doubtful_question_ids.',
                            'PHPUnit memverifikasi normalizer backend membaca bentuk lookup lama seperti [questionId => true].',
                            'Gunakan runner Recovery untuk memastikan dua jalur ini tetap lolos bersama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/attempt-ui-state.test.js',
                            'tests/php/unit/UiStateAttemptNormalizationTest.php',
                            'includes/class-cbt-ui-state.php',
                        ],
                        'runner_commands' => ['Vitest Recovery', 'PHPUnit Recovery'],
                    ]),
                    self::unit_test_checklist_item('current_question_id yang invalid dinormalisasi ulang ke index valid di question order aktif.', 'ready', [
                        'description' => 'Normalisasi sudah ditutup oleh test frontend dan backend, jadi current_question_id yang drift tidak lagi dibiarkan merusak restore flow.',
                        'process_steps' => [
                            'Vitest memverifikasi mapping ulang current_question_id invalid ke question order aktif di client restore path.',
                            'PHPUnit memverifikasi current_index backend ikut diklem agar tetap sinkron saat state dibaca ulang.',
                            'Hasil runner terbaru dipakai sebagai bukti status Ready item ini.',
                        ],
                        'evidence' => [
                            'tests/js/unit/attempt-ui-state.test.js',
                            'tests/php/unit/UiStateAttemptNormalizationTest.php',
                        ],
                        'runner_commands' => ['Vitest Recovery', 'PHPUnit Recovery'],
                    ]),
                    self::unit_test_checklist_item('[Integration] save_attempt_state() menolak question id yang tidak termasuk order attempt.', 'ready', [
                        'description' => 'Suite PHP recovery sekarang memanggil save_attempt_state() dengan order attempt terbatas dan membuktikan question id di luar order dibuang sebelum snapshot dipersist.',
                        'process_steps' => [
                            'PHPUnit menyiapkan fake attempt order di wpdb stub lalu memanggil save_attempt_state() secara langsung.',
                            'Assertion memverifikasi doubtful ids di luar order attempt dibuang, current_index diklem, dan snapshot valid tetap tersimpan.',
                            'get_attempt_state() dibaca ulang untuk memastikan hasil persist konsisten dengan payload normalize yang diharapkan.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UiStateRecoveryPersistenceTest.php',
                            'includes/class-cbt-ui-state.php',
                        ],
                        'runner_commands' => ['PHPUnit Recovery'],
                    ]),
                    self::unit_test_checklist_item('Snapshot attempt aktif tidak ikut hilang saat cleanup cache non-UI dijalankan.', 'ready', [
                        'description' => 'Coverage recovery backend sekarang secara eksplisit memverifikasi snapshot attempt aktif tetap ada setelah namespace cache non-UI dibersihkan.',
                        'process_steps' => [
                            'PHPUnit menyimpan snapshot attempt aktif di namespace ui_state.',
                            'Namespace catalog dan analytics di-invalidasi untuk meniru cleanup cache non-UI.',
                            'Snapshot attempt dibaca ulang dan registry ui_state dicek tetap berisi entry aktif.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UiStateRecoveryPersistenceTest.php',
                            'includes/class-cbt-cache.php',
                            'includes/class-cbt-ui-state.php',
                        ],
                        'runner_commands' => ['PHPUnit Recovery'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Jawab beberapa soal, refresh browser, lalu verifikasi posisi soal dan jawaban tetap pulih.', 'ready', [
                        'description' => 'Flow check Playwright sekarang login dengan akun seed coblax / 223611, membuka TEST Recovery Fixture, menjawab dua soal, lalu refresh untuk memastikan current question, pilihan jawaban, dan doubtful state tetap pulih.',
                        'process_steps' => [
                            'Runner membuka frontend dari E2E Base URL, login memakai akun seed recovery, lalu memilih TEST Recovery Fixture.',
                            'Skenario menjawab dua soal pilihan ganda, menandai soal aktif sebagai doubtful, lalu refresh browser.',
                            'Setelah reload, shell exam harus kembali di soal yang sama, pilihan tetap tercentang, dan tombol doubtful tetap aktif.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'tests/e2e/helpers/recovery-browser.js',
                            'tests/e2e/helpers/recovery-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Recovery Refresh'],
                    ]),
                    self::unit_test_checklist_item('Tutup tab, buka kembali, lalu resume attempt yang sama dengan state yang konsisten.', 'ready', [
                        'description' => 'Flow check ini menangkap storage browser aktif, menutup tab, lalu membuka context baru dengan storage yang direhidrasi untuk memastikan attempt yang sama langsung pulih tanpa kehilangan soal aktif atau jawaban.',
                        'process_steps' => [
                            'Runner mempersiapkan attempt aktif pada TEST Recovery Fixture hingga current question berada di soal kedua.',
                            'Storage browser ditangkap, tab ditutup, lalu context baru dibuka dengan sessionStorage dan localStorage yang direstorasi.',
                            'Exam shell harus langsung pulih pada soal dan jawaban yang sama, termasuk doubtful state yang sebelumnya aktif.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'tests/e2e/helpers/recovery-browser.js',
                        ],
                        'runner_commands' => ['Playwright Recovery Reopen'],
                    ]),
                    self::unit_test_checklist_item('Bersihkan cache area non-attempt lalu pastikan state attempt aktif tetap aman.', 'ready', [
                        'description' => 'Flow check ini memakai helper backend untuk invalidate catalog dan analytics tanpa menyentuh attempt namespace, lalu memastikan reload tidak menghapus progress recovery yang sedang berjalan.',
                        'process_steps' => [
                            'Runner memulai attempt recovery dan menyimpan current question, jawaban, serta doubtful state yang aktif.',
                            'Helper backend meng-invalidate cache non-attempt seperti catalog dan analytics.',
                            'Setelah reload, current question, pilihan jawaban, dan doubtful state harus tetap identik dengan sebelum invalidasi.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'tests/e2e/helpers/recovery-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Recovery Non-Attempt Cache'],
                    ]),
                    self::unit_test_checklist_item('Simulasikan finish gagal lalu reopen untuk memastikan progress tetap aman.', 'ready', [
                        'description' => 'Flow check ini memblok sekali request finish_exam via Playwright route interception, memastikan attempt tidak berpindah ke result, lalu reload untuk membuktikan progress tetap bisa dipulihkan dengan aman.',
                        'process_steps' => [
                            'Runner mempersiapkan attempt recovery yang sudah berisi jawaban dan current question aktif.',
                            'Request finish_exam pertama dipaksa gagal dengan response 503 agar jalur error finish dijalankan secara nyata.',
                            'Setelah reload, attempt harus kembali ke exam shell yang sama tanpa kehilangan jawaban atau posisi soal.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'src/frontend/app/exam/finish-flow.js',
                        ],
                        'runner_commands' => ['Playwright Recovery Finish Failure'],
                    ]),
                    self::unit_test_checklist_item('Corrupt manual snapshot lokal lalu reload untuk memastikan app tetap recover tanpa blank state.', 'ready', [
                        'description' => 'Flow check ini merusak sessionStorage attempt snapshot menjadi JSON invalid, lalu memaksa restore bergantung ke remote ui_state agar halaman tetap kembali ke shell exam normal tanpa blank state.',
                        'process_steps' => [
                            'Runner menyiapkan attempt aktif dan memastikan remote ui_state sudah dipersist untuk soal aktif yang sama.',
                            'Snapshot lokal di sessionStorage dirusak menjadi JSON invalid untuk mensimulasikan storage corrupt.',
                            'Reload harus tetap berhasil memulihkan exam shell dan current question dari remote state tanpa blank state atau crash restore.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'tests/e2e/helpers/recovery-browser.js',
                            'tests/e2e/helpers/recovery-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Recovery Corrupt Snapshot'],
                    ]),
                    self::unit_test_checklist_item('Attempt aktif tetap aman setelah invalidate cache area lain dijalankan dari admin.', 'ready', [
                        'description' => 'Flow check ini menguji invalidasi cache yang lebih lebar dari sisi admin, termasuk exam dan user namespace, tanpa menyentuh attempt namespace atau ui_state yang aktif.',
                        'process_steps' => [
                            'Runner mempersiapkan attempt recovery aktif dengan jawaban dan doubtful state yang sudah sinkron.',
                            'Helper backend meng-invalidate catalog, exam, user, dan analytics namespace untuk meniru aksi admin cache yang lebih luas.',
                            'Reload harus tetap memulihkan attempt aktif yang sama tanpa kehilangan jawaban atau posisi soal.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'tests/e2e/helpers/recovery-fixture.php',
                            'admin/class-cbt-admin-cache-actions.php',
                        ],
                        'runner_commands' => ['Playwright Recovery Admin Cache'],
                    ]),
                    self::unit_test_checklist_item('Restore state setelah reopen tetap memilih snapshot paling aman saat local dan remote berbeda.', 'ready', [
                        'description' => 'Flow check ini membuat konflik sengaja: snapshot lokal dibuat stale dan question cache dibersihkan, sementara remote ui_state diperbarui lebih baru. Restore harus memilih remote snapshot yang paling aman.',
                        'process_steps' => [
                            'Runner menyiapkan attempt aktif lalu mengubah snapshot lokal menjadi current_index lama dengan updated_at yang dibuat lebih tua.',
                            'Question cache lokal dibersihkan agar resolver tidak menjadikan cache sebagai tie-breaker dan terpaksa memakai freshness snapshot.',
                            'Remote ui_state diperbarui lewat helper backend, lalu reload harus memulihkan current question dan doubtful state dari snapshot remote yang lebih baru.',
                        ],
                        'evidence' => [
                            'tests/e2e/recovery-persistence.spec.js',
                            'tests/e2e/helpers/recovery-browser.js',
                            'tests/e2e/helpers/recovery-fixture.php',
                            'src/frontend/app/storage/attempt-ui-state.js',
                        ],
                        'runner_commands' => ['Playwright Recovery Conflict Resolver'],
                    ]),
                ],
            ],
            'sync_rest' => [
                'label' => 'Sync & REST',
                'summary' => 'Area untuk memetakan retry policy, pending queue, blocking reason, dan kestabilan kontrak endpoint yang menopang lifecycle attempt frontend.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Submit jawaban single dan batch menghasilkan efek yang setara untuk kasus yang sama.', 'ready', [
                        'description' => 'Suite sync runtime sekarang membandingkan path submit batch versus fallback legacy single submit untuk set jawaban yang sama, lalu memastikan state autosave akhirnya identik.',
                        'process_steps' => [
                            'Vitest menjalankan satu manager yang berhasil via submit_answers_batch dan satu manager lain yang dipaksa fallback ke submit_answer.',
                            'State akhir seperti lastSubmittedPayloadByQuestion, pending batch, dan blocking reason dibandingkan langsung agar efek submit tetap setara.',
                            'Log panggilan API ikut diverifikasi untuk memastikan path fallback benar-benar menembak submit_answer satu per satu.',
                        ],
                        'evidence' => [
                            'tests/js/unit/sync-rest.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Sync & REST'],
                    ]),
                    self::unit_test_checklist_item('Payload invalid untuk attempt_id atau answers menghasilkan error code dan pesan yang tepat.', 'ready', [
                        'description' => 'Validasi REST ringan sekarang dikunci lewat PHPUnit untuk jalur submit_answer dan submit_answers_batch agar payload invalid tetap mengembalikan invalid_payload dengan status 400.',
                        'process_steps' => [
                            'PHPUnit memanggil public handler submit_answer dengan attempt_id dan question_id kosong.',
                            'PHPUnit memanggil public handler submit_answers_batch dengan attempt_id kosong dan answers kosong.',
                            'Error code dan error data diverifikasi agar kontrak invalid payload tidak berubah diam-diam.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestSyncValidationTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit REST Sync'],
                    ]),
                    self::unit_test_checklist_item('Finish lock hanya berlaku untuk attempt in_progress dan tetap aman saat finalisasi dipanggil ulang.', 'ready', [
                        'description' => 'Guard finish sekarang punya coverage PHP untuk memastikan attempt yang sudah completed langsung ditolak tanpa mencoba lock, sementara attempt in_progress yang tidak bisa mendapat finish lock mengembalikan attempt_finish_locked dengan aman.',
                        'process_steps' => [
                            'PHPUnit menyiapkan fake attempt completed lalu memanggil finish_exam untuk memastikan hasilnya attempt_closed.',
                            'Pada attempt in_progress, finish lock utama dibuat kosong dan fallback lock dibuat gagal supaya handler mengembalikan attempt_finish_locked.',
                            'Assertion juga memastikan fallback lock tidak dicoba untuk attempt completed, sehingga lock hanya relevan pada in_progress.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestSyncValidationTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit REST Sync'],
                    ]),
                    self::unit_test_checklist_item('Offline, pending sync, dan retry policy menghasilkan blocking reason yang konsisten.', 'ready', [
                        'description' => 'Coverage sync runtime sekarang mengunci transisi blocking reason utama saat pending sync bertemu status online/offline dan finish lock.',
                        'process_steps' => [
                            'Vitest mengantrekan jawaban lalu memverifikasi pending_sync pada kondisi online normal.',
                            'Status koneksi diubah ke offline untuk memastikan blocking reason berpindah ke offline_pending_sync.',
                            'Finish lock dan retry state dipadukan untuk memastikan reason berpindah konsisten ke finish_wait_online dan finish_pending_sync.',
                        ],
                        'evidence' => [
                            'tests/js/unit/sync-rest.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Sync & REST'],
                    ]),
                    self::unit_test_checklist_item('Response shape endpoint utama tetap stabil untuk ui_state, submit, finish, dan result.', 'ready', [
                        'description' => 'Contract-level smoke untuk shape response inti sekarang dikunci lewat PHPUnit pada shell ui_state, formatter submit, dan payload restricted result yang menjadi dasar shape endpoint frontend.',
                        'process_steps' => [
                            'PHPUnit memverifikasi save_ui_state tetap mengembalikan shell response preferences dan attempt_state.',
                            'Formatter private format_submission_response_item dipanggil via Reflection untuk mengunci key submit response yang dipakai frontend.',
                            'Formatter private build_restricted_student_result_payload dipanggil via Reflection untuk memastikan shape payload result restricted tetap stabil.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestSyncValidationTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit REST Sync'],
                    ]),
                    self::unit_test_checklist_item('Partial success batch submit hanya apply item yang sukses, sementara item gagal tetap direqueue.', 'ready', [
                        'description' => 'Runtime fallback sekarang punya coverage formal untuk partial legacy success: item yang sudah sukses tetap di-apply, sedangkan item yang gagal tetap kembali ke antrean.',
                        'process_steps' => [
                            'Vitest memaksa submit_answers_batch gagal ke fallback legacy, lalu membuat submit_answer pertama sukses dan submit kedua gagal.',
                            'Assertion memverifikasi lastSubmittedPayloadByQuestion hanya berisi item sukses pertama.',
                            'Pending batch dan pendingSyncCount diverifikasi tetap memuat item yang gagal agar retry berikutnya tidak kehilangan jawaban.',
                        ],
                        'evidence' => [
                            'tests/js/unit/sync-rest.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Sync & REST'],
                    ]),
                    self::unit_test_checklist_item('Fallback dari batch ke legacy submit hanya aktif untuk error yang memang retryable.', 'ready', [
                        'description' => 'Coverage fallback sekarang memastikan only-allow list error batch tetap sempit: runtime_buffer_unavailable dan error server tertentu bisa fallback, sementara network retryable tetap masuk jalur retry biasa.',
                        'process_steps' => [
                            'Vitest memverifikasi runtime_buffer_unavailable, 503, dan 429 mengembalikan keputusan fallback true.',
                            'Vitest juga memverifikasi network error seperti failed to fetch tidak ikut memicu legacy fallback.',
                            'Policy ini menjaga retry offline dan fallback legacy tidak tercampur di jalur yang salah.',
                        ],
                        'evidence' => [
                            'tests/js/unit/sync-rest.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Sync & REST'],
                    ]),
                    self::unit_test_checklist_item('[Integration] finish_exam bersifat idempotent saat dipanggil ulang pada attempt yang sama.', 'ready', [
                        'description' => 'REST finish sekarang punya guard formal untuk repeated submit pada attempt yang sudah completed: handler tidak lagi jatuh ke attempt_closed, tetapi mengembalikan payload completed yang stabil tanpa mencoba finish lock ulang.',
                        'process_steps' => [
                            'PHPUnit menyiapkan fake attempt completed dengan score, finished_at, dan meta exam yang sudah ada.',
                            'finish_exam dipanggil ulang pada attempt yang sama untuk memastikan response tetap completed dan pass meta tetap konsisten.',
                            'Assertion juga memastikan runtime lock dan fallback lock tidak disentuh pada jalur idempotent tersebut.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestSyncValidationTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit REST Sync'],
                    ]),
                    self::unit_test_checklist_item('[Integration] Snapshot ui_state lama tidak menimpa snapshot yang lebih baru.', 'ready', [
                        'description' => 'Manager ui_state sync sekarang punya coverage untuk request in-flight yang kembali dengan snapshot lama, sehingga response stale tidak lagi menimpa snapshot lokal yang lebih baru.',
                        'process_steps' => [
                            'Vitest memulai flush ui_state, lalu mengganti snapshot lokal ke versi yang lebih baru sebelum response server dikembalikan.',
                            'Response dengan attempt_state stale diverifikasi tidak dipersist kembali ke storage lokal.',
                            'Kasus pembanding dengan response yang memang lebih baru juga diuji agar sync tetap menerima snapshot remote yang valid.',
                        ],
                        'evidence' => [
                            'tests/js/unit/attempt-ui-sync.test.js',
                            'src/frontend/app/core/attempt-ui-sync.js',
                        ],
                        'runner_commands' => ['Vitest UI Sync'],
                    ]),
                    self::unit_test_checklist_item('Blocking reason tetap benar saat kombinasi offline, pending batch, dan finish lock terjadi bersamaan.', 'ready', [
                        'description' => 'Kombinasi state yang paling rawan regress sekarang ikut ditutup: pending batch yang masih ada, koneksi offline, dan finish lock aktif harus menghasilkan blocking reason yang dapat diprediksi.',
                        'process_steps' => [
                            'Vitest mengantrekan jawaban lalu memaksa koneksi menjadi offline sambil menjaga pending batch tetap ada.',
                            'Finish lock diaktifkan untuk mengubah konteks dari pending biasa ke jalur finalisasi exam.',
                            'Assertion memverifikasi urutan reason finish_wait_online lalu finish_pending_sync saat koneksi kembali online.',
                        ],
                        'evidence' => [
                            'tests/js/unit/sync-rest.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Sync & REST'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Start attempt -> ambil questions -> submit answer -> finish -> get result berjalan end-to-end.', 'ready', [
                        'description' => 'Flow check ini menutup jalur utama Sync & REST dari mulai attempt, submit jawaban, finish, sampai result shell muncul tanpa intervensi helper selain preflight reset.',
                        'process_steps' => [
                            'Runner login sebagai siswa seed lalu membuka TEST Sync Fixture.',
                            'Skenario menjawab dua soal, menunggu jawaban tersinkron ke backend, lalu melakukan finish exam.',
                            'Result shell harus tampil sebagai bukti jalur sync, finish, dan result tetap utuh end-to-end.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                            'tests/e2e/helpers/e2e-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Sync End To End'],
                    ]),
                    self::unit_test_checklist_item('Kerjakan soal saat offline lalu online kembali untuk memeriksa retry otomatis.', 'ready', [
                        'description' => 'Flow check ini memakai offline toggle browser sungguhan untuk memastikan jawaban tertahan saat offline lalu otomatis ter-flush ketika koneksi kembali.',
                        'process_steps' => [
                            'Runner memulai attempt sync lalu mematikan koneksi browser.',
                            'Satu jawaban dikirim saat offline sehingga footer sync harus berubah ke state pending/offline.',
                            'Setelah koneksi diaktifkan kembali, helper backend memverifikasi row jawaban akhirnya tersimpan ke attempt yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'tests/e2e/helpers/flow-utils.js',
                        ],
                        'runner_commands' => ['Playwright Sync Offline Retry'],
                    ]),
                    self::unit_test_checklist_item('Paksa pending sync saat finish dan pastikan lock state tetap informatif.', 'ready', [
                        'description' => 'Flow check ini memastikan jalur finish tidak langsung lompat ke result ketika masih ada sync yang tertahan; UI harus tetap tinggal di exam shell dengan indicator sync yang jelas.',
                        'process_steps' => [
                            'Runner menyiapkan pending sync dengan menjawab soal saat koneksi browser offline.',
                            'Finish dipicu pada soal terakhir untuk menekan jalur lock finish.',
                            'Exam shell harus tetap terlihat dan footer sync harus tetap menunjukkan kondisi offline/pending secara informatif.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Playwright Sync Pending Finish Lock'],
                    ]),
                    self::unit_test_checklist_item('Request dengan attempt milik user lain ditolak dengan benar.', 'ready', [
                        'description' => 'Flow check ini membuka dua sesi siswa berbeda lalu memverifikasi endpoint questions tidak mengizinkan attempt milik user lain dibaca ulang dari browser kedua.',
                        'process_steps' => [
                            'Siswa utama menyiapkan attempt aktif pada TEST Sync Fixture.',
                            'Siswa kedua login di browser/context lain dengan token yang valid miliknya sendiri.',
                            'Request questions memakai attempt siswa utama harus ditolak dengan status 403 dan code forbidden.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['Playwright Sync Cross User Forbidden'],
                    ]),
                    self::unit_test_checklist_item('Pending sync tertahan, browser ditutup, lalu reopen dan flush lanjut otomatis.', 'ready', [
                        'description' => 'Flow check ini menangkap storage browser pada kondisi pending sync, lalu membuka context baru untuk memastikan antrean lama tetap dilanjutkan setelah reopen.',
                        'process_steps' => [
                            'Runner membuat pending sync saat offline lalu menangkap localStorage dan sessionStorage aktif.',
                            'Context baru dibuka dengan storage yang direhidrasi setelah koneksi kembali online.',
                            'Helper backend menunggu sampai row jawaban akhirnya tersimpan, membuktikan flush tetap lanjut setelah reopen.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Sync Reopen Flush'],
                    ]),
                    self::unit_test_checklist_item('Batch submit dan single submit menghasilkan efek data yang setara untuk set jawaban yang sama.', 'ready', [
                        'description' => 'Flow check ini menjalankan dua ronde: submit normal dan submit dengan batch dipaksa fallback ke legacy single submit, lalu memastikan row backend yang dihasilkan tetap setara.',
                        'process_steps' => [
                            'Run pertama memakai path batch normal untuk mengirim dua jawaban.',
                            'Fixture di-reset, lalu endpoint submit_answers_batch dipaksa gagal agar fallback single submit aktif.',
                            'Jumlah row jawaban backend pada kedua ronde harus tetap setara untuk qid yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Playwright Sync Batch Equivalence'],
                    ]),
                    self::unit_test_checklist_item('Finish saat ada pending sync tetap berujung ke result yang benar setelah retry selesai.', 'ready', [
                        'description' => 'Flow check ini menahan finish saat offline, memulihkan koneksi, menunggu retry menyelesaikan jawaban, lalu melakukan finish ulang sampai result benar-benar tampil.',
                        'process_steps' => [
                            'Runner membuat pending sync dan memicu finish saat koneksi offline.',
                            'Exam shell harus tetap tertahan sampai koneksi kembali dan retry selesai menulis jawaban ke backend.',
                            'Finish ulang setelah sync selesai harus mengantarkan user ke result shell normal.',
                        ],
                        'evidence' => [
                            'tests/e2e/sync-rest.spec.js',
                            'tests/e2e/helpers/flow-utils.js',
                        ],
                        'runner_commands' => ['Playwright Sync Finish After Retry'],
                    ]),
                ],
            ],
            'auth_session' => [
                'label' => 'Auth & Session',
                'summary' => 'Area untuk menjaga login, token, session aktif, revoke, dan bootstrap session tetap konsisten saat siswa memulai atau melanjutkan attempt.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Single active login session enforcement tetap konsisten saat user login berulang.', 'ready', [
                        'description' => 'Coverage PHP sekarang membuktikan login kedua memang diblok saat active session masih segar, lalu kembali diizinkan setelah touched_at lewat dari TTL session aktif.',
                        'process_steps' => [
                            'PHPUnit menyiapkan user stub dengan active session yang baru disentuh lalu memanggil CBT_Auth::login() untuk memastikan hasilnya session_already_active.',
                            'Touched time kemudian dibuat stale agar login berikutnya kembali berhasil dan menghasilkan token baru.',
                            'Verifikasi ini menembak CBT_Auth asli, bukan helper buatan terpisah.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AuthSessionLifecycleTest.php',
                            'includes/class-cbt-auth.php',
                        ],
                        'runner_commands' => ['PHPUnit Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Revoked session dan token mismatch langsung ditolak tanpa membuka akses attempt.', 'ready', [
                        'description' => 'JWT dengan session key lama sekarang punya coverage formal: verify_request_token() harus mengembalikan session_revoked saat active session meta sudah berganti.',
                        'process_steps' => [
                            'PHPUnit membangkitkan token valid dengan session key lama, lalu mengganti active session di user meta.',
                            'verify_request_token() dipanggil melalui request bearer token sungguhan.',
                            'Hasilnya diverifikasi berupa WP_Error session_revoked sebelum jalur akses attempt mana pun dibuka.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AuthSessionLifecycleTest.php',
                            'includes/class-cbt-auth.php',
                        ],
                        'runner_commands' => ['PHPUnit Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Logout current session hanya membersihkan sesi yang benar dan tidak merusak sesi user lain.', 'ready', [
                        'description' => 'Coverage logout sekarang memastikan hanya token yang membawa session key aktif yang boleh membersihkan login session, sementara token stale tidak boleh menghapus sesi terbaru.',
                        'process_steps' => [
                            'PHPUnit mereset login session lalu memanggil logout_current_session() dengan bearer token yang cocok untuk memastikan sesi dibersihkan.',
                            'Session baru kemudian dibuat lagi dan token lama dipakai ulang agar logout_current_session() mengembalikan false.',
                            'Meta active session diverifikasi tetap utuh setelah request dengan token stale tersebut.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AuthSessionLifecycleTest.php',
                            'includes/class-cbt-auth.php',
                        ],
                        'runner_commands' => ['PHPUnit Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Bootstrap dan auth session recovery memulihkan sesi valid tanpa melewati guard keamanan.', 'ready', [
                        'description' => 'Bootstrap frontend sekarang punya coverage untuk persisted session valid, baik saat resume attempt berhasil maupun saat hanya memulihkan sesi confirm tanpa attempt aktif.',
                        'process_steps' => [
                            'Vitest memberi persisted token, user, dan selected exam ke bootstrap manager lalu memaksa jalur resume berhasil.',
                            'Skenario kedua membiarkan resume gagal agar bootstrap tetap masuk confirm, persist auth lagi, dan start heartbeat tanpa error.',
                            'Retry lifecycle bootstrap juga diverifikasi agar jalur resume/session tetap konsisten.',
                        ],
                        'evidence' => [
                            'tests/js/unit/bootstrap-session.test.js',
                            'src/frontend/app/core/bootstrap-session.js',
                        ],
                        'runner_commands' => ['Vitest Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Forbidden access antar user atau antar attempt tetap ditolak di jalur session yang sensitif.', 'ready', [
                        'description' => 'Guard REST lintas user sekarang ikut dikunci: attempt yang dimiliki user lain harus langsung menghasilkan forbidden walaupun role dan token request valid.',
                        'process_steps' => [
                            'PHPUnit menyiapkan request student valid dengan current_user_id berbeda dari student_id attempt di wpdb stub.',
                            'CBT_REST::finish_exam() dipanggil agar jalur sensitif berbasis session tetap terkena guard kepemilikan attempt.',
                            'Handler harus mengembalikan forbidden 403 sebelum lock atau finalisasi attempt berjalan.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AuthSessionRestGuardTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Persisted auth payload tanpa user_id atau role dianggap invalid dan tidak dipakai untuk bootstrap.', 'ready', [
                        'description' => 'Normalizer auth session frontend sudah punya coverage untuk menolak payload persist yang tidak punya user_id valid atau role yang terisi.',
                        'process_steps' => [
                            'Vitest memanggil normalizePersistedUser() dengan payload valid untuk baseline.',
                            'Payload dengan user_id kosong atau role kosong dipastikan menghasilkan null.',
                            'readPersistedAuthSession() juga diverifikasi mengembalikan null untuk snapshot storage yang malformed.',
                        ],
                        'evidence' => [
                            'tests/js/unit/auth-session.test.js',
                            'src/frontend/app/core/auth-session.js',
                        ],
                        'runner_commands' => ['Vitest Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Storage auth yang tidak tersedia tidak memutus bootstrap flow dan tidak melempar error.', 'ready', [
                        'description' => 'Storage failure sekarang ditutup dari dua sisi: auth session manager tidak melempar saat getItem/setItem/removeItem gagal, dan bootstrap tetap aman saat tidak ada persisted session yang bisa dipakai.',
                        'process_steps' => [
                            'Vitest menyiapkan storage yang selalu throw untuk memastikan persistAuthSession(), readPersistedAuthSession(), dan clearPersistedAuthSession() tidak melempar.',
                            'Bootstrap manager kemudian diverifikasi tetap render aman saat tidak ada persisted session yang tersedia.',
                            'Coverage ini menjaga private mode atau storage blocked tidak menjatuhkan app di startup.',
                        ],
                        'evidence' => [
                            'tests/js/unit/auth-session.test.js',
                            'tests/js/unit/bootstrap-session.test.js',
                            'src/frontend/app/core/auth-session.js',
                        ],
                        'runner_commands' => ['Vitest Auth Session'],
                    ]),
                    self::unit_test_checklist_item('[PHP Unit] clear_login_session() gagal bila session_key yang diberikan tidak cocok.', 'ready', [
                        'description' => 'Public guard clear_login_session() sekarang punya assertion eksplisit untuk session key mismatch, jadi reset session tidak bisa menghapus active session yang bukan miliknya.',
                        'process_steps' => [
                            'PHPUnit membuat active session sungguhan via reset_login_session().',
                            'clear_login_session() dipanggil dengan session key yang salah dan harus mengembalikan false.',
                            'Meta active session dibaca ulang untuk memastikan nilainya tidak berubah.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AuthSessionLifecycleTest.php',
                            'includes/class-cbt-auth.php',
                        ],
                        'runner_commands' => ['PHPUnit Auth Session'],
                    ]),
                    self::unit_test_checklist_item('[PHP Unit] verify_request_token() mencatat event session_revoked saat token valid tetapi session aktif sudah berganti.', 'ready', [
                        'description' => 'Selain menolak token stale, verify_request_token() sekarang juga punya coverage logging agar event session_revoked benar-benar dicatat untuk investigasi security/session.',
                        'process_steps' => [
                            'PHPUnit menyiapkan token valid dengan session lama lalu mengganti active session meta.',
                            'Stub CBT_Security_Log menangkap event yang dikirim oleh verify_request_token().',
                            'Assertion memverifikasi event_type session_revoked dan user_id yang benar ikut tercatat.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AuthSessionLifecycleTest.php',
                            'includes/class-cbt-auth.php',
                        ],
                        'runner_commands' => ['PHPUnit Auth Session'],
                    ]),
                    self::unit_test_checklist_item('Bootstrap hanya memulihkan sesi valid; sesi revoked langsung dibersihkan dan logout.', 'ready', [
                        'description' => 'Bootstrap manager sekarang punya coverage untuk jalur gagal yang mewakili revoked session: saat loadExams gagal sementara token masih aktif, fullLogout() harus dipanggil dan error session ditampilkan.',
                        'process_steps' => [
                            'Vitest menyiapkan persisted session valid lalu membuat loadExams() melempar pesan session revoked.',
                            'Bootstrap manager harus memanggil fullLogout(), membersihkan state busy, dan menyimpan pesan error ke state.',
                            'Render akhir diverifikasi tetap berjalan agar UI tidak nyangkut di shell confirm yang lama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/bootstrap-session.test.js',
                            'src/frontend/app/core/bootstrap-session.js',
                        ],
                        'runner_commands' => ['Vitest Auth Session'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Login dari browser kedua lalu pastikan sesi lama tidak lagi bisa dipakai.', 'ready', [
                        'description' => 'Flow check ini memverifikasi active session meta berpindah ketika browser kedua login, lalu token lama dari browser pertama benar-benar ditolak sebagai session_revoked.',
                        'process_steps' => [
                            'Browser pertama login memakai akun seed session dan menunggu state confirm terbuka.',
                            'Session aktif dibuat stale agar login browser kedua bisa merotasi session key secara deterministik.',
                            'Request session dari browser pertama harus ditolak 401 dengan code session_revoked.',
                        ],
                        'evidence' => [
                            'tests/e2e/auth-session.spec.js',
                            'tests/e2e/helpers/e2e-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Auth Second Browser Revoke'],
                    ]),
                    self::unit_test_checklist_item('Logout lalu login kembali untuk memastikan session key dan token benar-benar berganti.', 'ready', [
                        'description' => 'Flow check ini membandingkan auth payload persisted sebelum dan sesudah logout untuk membuktikan token serta session key memang berganti, bukan sekadar reuse nilai lama.',
                        'process_steps' => [
                            'Siswa login pertama kali dan auth payload tersimpan dari localStorage.',
                            'Logout frontend dijalankan dari UI yang sama.',
                            'Login ulang harus menghasilkan token dan session_key baru yang berbeda dari sesi pertama.',
                        ],
                        'evidence' => [
                            'tests/e2e/auth-session.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Auth Logout Rotate'],
                    ]),
                    self::unit_test_checklist_item('Coba akses attempt dengan user lain dan pastikan ditolak dengan status yang tepat.', 'ready', [
                        'description' => 'Flow check ini memakai dua user seed berbeda untuk membuktikan attempt milik siswa utama tidak bisa diakses ulang lewat browser siswa kedua.',
                        'process_steps' => [
                            'Siswa utama membuat attempt aktif pada TEST Session Fixture.',
                            'Siswa kedua login dari context lain memakai kredensialnya sendiri.',
                            'Request questions ke attempt siswa utama harus ditolak 403 forbidden.',
                        ],
                        'evidence' => [
                            'tests/e2e/auth-session.spec.js',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['Playwright Auth Cross User Forbidden'],
                    ]),
                    self::unit_test_checklist_item('Resume attempt dengan sesi valid setelah reopen dan pastikan bootstrap tetap aman.', 'ready', [
                        'description' => 'Flow check ini menangkap storage auth + exam, menutup page, lalu membuka context baru untuk memastikan shell exam yang sama bisa bootstrap ulang dengan sesi valid.',
                        'process_steps' => [
                            'Siswa login dan membuat attempt aktif di fixture session.',
                            'Storage browser ditangkap ketika exam shell masih aktif.',
                            'Context baru dengan storage yang direhidrasi harus langsung kembali ke shell exam yang sama tanpa jatuh ke login guard.',
                        ],
                        'evidence' => [
                            'tests/e2e/auth-session.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Auth Reopen Resume'],
                    ]),
                    self::unit_test_checklist_item('Login cepat dari dua browser memastikan sesi lama benar-benar tidak bisa dipakai lagi.', 'ready', [
                        'description' => 'Flow check ini menutup race condition paling umum: browser pertama tetap terbuka ketika browser kedua login cepat sesudah session pertama dibuat stale.',
                        'process_steps' => [
                            'Browser pertama login dan menyimpan token aktif.',
                            'Session aktif di-age lewat helper backend, lalu browser kedua langsung login ulang.',
                            'Browser pertama harus kehilangan validitas token secara deterministik saat memanggil endpoint session.',
                        ],
                        'evidence' => [
                            'tests/e2e/auth-session.spec.js',
                            'tests/e2e/helpers/e2e-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Auth Dual Browser Invalidate'],
                    ]),
                    self::unit_test_checklist_item('Sesi valid bisa bootstrap dan resume attempt tanpa melewati guard auth.', 'ready', [
                        'description' => 'Flow check ini memastikan sesi yang memang valid tetap bisa dipakai untuk bootstrap ulang dan resume attempt tanpa false positive auth guard.',
                        'process_steps' => [
                            'Siswa membuat attempt aktif dan storage browser aktif ditangkap.',
                            'Context baru dibuka dengan token dan storage sesi yang masih valid.',
                            'Session endpoint harus membalas 200 dan shell exam harus tampil lagi pada attempt yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/auth-session.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Auth Valid Resume Bootstrap'],
                    ]),
                ],
            ],
            'timer_lifecycle' => [
                'label' => 'Timer & Lifecycle',
                'summary' => 'Area untuk memastikan countdown, heartbeat, timeout, dan transisi stage exam tetap sinkron saat runtime berubah cepat.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Timer payload diklem dengan aman dan tidak menimpa attempt yang tidak cocok.', 'ready', [
                        'description' => 'Coverage lifecycle sekarang mengunci dua guard utama sekaligus: payload timer untuk attempt lain harus diabaikan, dan payload negatif untuk attempt aktif harus diklem ke nol secara aman.',
                        'process_steps' => [
                            'Vitest memanggil applyAttemptTimerPayload() dengan attempt_id yang tidak cocok lalu memastikan remainingSeconds tidak berubah.',
                            'Payload berikutnya dikirim dengan remaining_seconds negatif pada attempt aktif.',
                            'State harus terklem ke nol, label timer diperbarui, dan jalur finish dipicu tanpa menimpa attempt lain.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Heartbeat state sync memperbarui timer dan snapshot runtime tanpa merusak attempt aktif.', 'ready', [
                        'description' => 'Heartbeat manager sekarang punya coverage untuk payload session yang sudah sinkron: timer payload diterapkan, revision awal diset, dan refresh runtime tidak dipicu bila count serta order signature masih cocok.',
                        'process_steps' => [
                            'Vitest menjalankan heartbeat dengan attempt aktif, question count lokal yang sama, dan order signature yang cocok.',
                            'applyAttemptTimerPayload() harus menerima payload timer dari session, sementara setQuestionRevision() hanya men-seed revision awal.',
                            'refreshAttemptQuestionRevision() diverifikasi tidak terpanggil sehingga attempt aktif tidak disentuh ulang tanpa alasan.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-heartbeat.test.js',
                            'src/frontend/app/core/session-heartbeat.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Timeout dan finish transition memindahkan stage exam ke status akhir dengan bersih.', 'ready', [
                        'description' => 'Countdown lifecycle sekarang punya coverage untuk transisi waktu habis: timer turun ke nol, interval berhenti, dan handleFinish(true) hanya dipanggil sekali.',
                        'process_steps' => [
                            'Vitest memulai timer dengan remainingSeconds = 1.',
                            'Fake timer dimajukan sampai countdown menyentuh nol lalu melewati tick finish berikutnya.',
                            'Assertion memastikan remainingSeconds nol, timerId bersih, dan handleFinish() hanya menerima satu panggilan forced finish.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Lifecycle cleanup menghapus timer, interval, dan listener saat stage berubah.', 'ready', [
                        'description' => 'clearAuthenticatedFrontendState() sekarang punya coverage untuk cleanup besar saat stage keluar dari exam: timer, heartbeat, sync runtime, dan snapshot persisted semuanya dibersihkan.',
                        'process_steps' => [
                            'Vitest memulai timer lalu memanggil clearAuthenticatedFrontendState() dengan stage tujuan login.',
                            'Cleanup dependency seperti stopSessionHeartbeat(), clearAutoSaveRuntimeState(), clearAttemptUiSyncRuntimeState(), dan clearPersistedAuthSession() diverifikasi terpanggil.',
                            'Attempt id, selected exam, remainingSeconds, dan state calculator juga diverifikasi kembali ke baseline login.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Reopen atau resume setelah update timer tetap mempertahankan remaining time yang valid.', 'ready', [
                        'description' => 'Lifecycle manager sekarang punya coverage untuk payload resume yang membawa lompatan timer besar, sehingga remainingSeconds valid bisa disinkronkan ulang tanpa memicu jalur finish atau drift kecil palsu.',
                        'process_steps' => [
                            'Vitest menyiapkan remainingSeconds lama lalu mengirim payload timer baru dengan delta besar pada attempt aktif.',
                            'Manager harus menerima nilai baru dan memperbarui label timer yang dirender.',
                            'Kasus ini menjadi dasar untuk resume atau extra time yang datang dari server setelah reopen.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('applyAttemptTimerPayload() mengabaikan payload timer untuk attempt lain yang tidak cocok.', 'ready', [
                        'description' => 'Guard attempt_id pada applyAttemptTimerPayload() sekarang punya assertion eksplisit terpisah agar payload lintas attempt tidak pernah mengubah timer aktif.',
                        'process_steps' => [
                            'Vitest memanggil applyAttemptTimerPayload() memakai attempt_id yang berbeda dari attempt aktif.',
                            'remainingSeconds dan label timer diverifikasi tetap sama seperti sebelum payload datang.',
                            'Guard ini dipisah eksplisit karena regression lintas attempt sering muncul saat heartbeat membawa payload dari session lama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Selisih timer kecil tidak memicu overwrite state yang tidak perlu atau render berlebih.', 'ready', [
                        'description' => 'Jalur anti-noise timer drift sekarang ditutup: selisih kurang dari dua detik tidak boleh mengubah remainingSeconds atau memanggil update label lagi.',
                        'process_steps' => [
                            'Vitest menyiapkan remainingSeconds lalu mengirim payload timer dengan drift satu detik.',
                            'remainingSeconds diverifikasi tetap sama.',
                            'formatSeconds dan side effect render label diverifikasi tidak terpanggil untuk drift kecil ini.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Heartbeat paralel reuse request aktif dan tidak melakukan fetch ganda.', 'ready', [
                        'description' => 'Heartbeat runtime sekarang punya coverage concurrency: panggilan run() kedua saat request pertama masih aktif harus mengembalikan promise yang sama dan tidak memanggil apiRequest lagi.',
                        'process_steps' => [
                            'Vitest membuat deferred promise untuk apiRequest session.',
                            'manager.run() dipanggil dua kali sebelum promise pertama selesai.',
                            'Assertion memverifikasi kedua pemanggilan mengembalikan promise yang sama dan apiRequest hanya dipanggil satu kali.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-heartbeat.test.js',
                            'src/frontend/app/core/session-heartbeat.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Disable calculator dari heartbeat membersihkan runtime kalkulator dan notice tetap konsisten.', 'ready', [
                        'description' => 'Heartbeat sekarang punya coverage untuk perubahan enable_calculator dari server: runtime kalkulator harus dibersihkan, exam payload diperbarui, dan notice user tetap konsisten.',
                        'process_steps' => [
                            'Vitest menjalankan heartbeat dengan exam aktif yang sebelumnya mengizinkan kalkulator.',
                            'Payload session mematikan kalkulator sehingga clearCalculatorRuntimeState() harus terpanggil dan state.exams diperbarui.',
                            'Notice Kalkulator dinonaktifkan oleh guru untuk exam ini diverifikasi muncul dan render heartbeat availability ikut dipanggil.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-heartbeat.test.js',
                            'src/frontend/app/core/session-heartbeat.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Cleanup lifecycle memastikan timer, interval, dan listener tidak tertinggal setelah stage berubah.', 'ready', [
                        'description' => 'resetExamSession() dan heartbeat stop sekarang bersama-sama menutup jalur cleanup saat stage sudah bergeser: timer berhenti, fullscreen keluar, calculator state bersih, dan snapshot attempt lama dihapus saat sudah bukan di stage exam.',
                        'process_steps' => [
                            'Vitest memulai timer lalu memanggil resetExamSession() ketika stage sudah result.',
                            'Assertion memverifikasi timerId kembali nol, calculator state dibersihkan, dan exitFullscreenSilently() terpanggil.',
                            'Persisted attempt UI state serta question cache juga diverifikasi ikut dibersihkan untuk attempt lama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/session-lifecycle.test.js',
                            'tests/js/unit/session-heartbeat.test.js',
                            'src/frontend/app/core/session-lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Biarkan exam aktif sampai hampir timeout lalu verifikasi countdown dan transisi hasil tetap benar.', 'ready', [
                        'description' => 'Flow check ini menggeser started_at attempt agar sisa waktu tinggal beberapa detik, lalu memastikan countdown alami benar-benar menutup exam ke result.',
                        'process_steps' => [
                            'Runner memulai TEST Timer Fixture lalu helper backend menggeser timer ke ambang timeout.',
                            'Page direload agar payload timer terbaru diambil dari session server.',
                            'Countdown harus turun ke nol dan result shell harus muncul tanpa intervensi manual.',
                        ],
                        'evidence' => [
                            'tests/e2e/timer-lifecycle.spec.js',
                            'tests/e2e/helpers/e2e-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Timer Near Timeout'],
                    ]),
                    self::unit_test_checklist_item('Resume attempt setelah server mengubah timer atau extra time dan pastikan sisa waktu tetap sinkron.', 'ready', [
                        'description' => 'Flow check ini mengubah remaining time dan extra time dari backend lalu membuka ulang context browser untuk memastikan timer tetap sinkron saat resume.',
                        'process_steps' => [
                            'Runner memulai attempt timer lalu helper backend mengatur remaining_seconds dan extra_time_minutes baru.',
                            'Storage browser aktif ditangkap dan dipakai untuk reopen context baru.',
                            'Context baru harus kembali menampilkan timer yang tersinkron ulang, bukan nilai stale dari snapshot lama.',
                        ],
                        'evidence' => [
                            'tests/e2e/timer-lifecycle.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Timer Resume Extra Time'],
                    ]),
                    self::unit_test_checklist_item('Biarkan heartbeat aktif saat exam berjalan untuk memastikan stage tidak meloncat atau reset.', 'ready', [
                        'description' => 'Flow check ini membiarkan satu siklus heartbeat penuh berjalan saat exam aktif untuk membuktikan stage exam tidak tiba-tiba kembali ke login, confirm, atau result.',
                        'process_steps' => [
                            'Runner membuka attempt aktif pada TEST Timer Fixture.',
                            'Browser dibiarkan idle secukupnya agar session heartbeat sempat berjalan minimal satu kali.',
                            'Setelah itu exam shell dan timer harus tetap tampil stabil.',
                        ],
                        'evidence' => [
                            'tests/e2e/timer-lifecycle.spec.js',
                            'src/frontend/app/core/session-heartbeat.js',
                        ],
                        'runner_commands' => ['Playwright Timer Heartbeat Stable'],
                    ]),
                    self::unit_test_checklist_item('Timeout alami memindahkan stage ke finish/result tanpa meninggalkan timer zombie.', 'ready', [
                        'description' => 'Flow check ini memastikan attempt yang sudah timeout tetap aman saat reopen: result shell terlihat, sedangkan timer aktif tidak muncul lagi.',
                        'process_steps' => [
                            'Runner menggeser timer sampai hampir habis lalu menunggu result shell muncul.',
                            'Storage browser ditangkap setelah timeout selesai.',
                            'Context baru dibuka dan harus langsung menampilkan result tanpa timer exam aktif yang tertinggal.',
                        ],
                        'evidence' => [
                            'tests/e2e/timer-lifecycle.spec.js',
                            'tests/e2e/helpers/flow-utils.js',
                        ],
                        'runner_commands' => ['Playwright Timer Timeout No Zombie'],
                    ]),
                    self::unit_test_checklist_item('Logout saat shell exam masih loading dan saat exam aktif sama-sama aman dan tidak meninggalkan state kotor.', 'ready', [
                        'description' => 'Flow check ini memverifikasi logout dari exam aktif tetap mengembalikan app ke login bersih, lalu login ulang masih bisa membuka exam tanpa residu lifecycle lama.',
                        'process_steps' => [
                            'Runner membuka attempt aktif lalu memicu logout dari shell exam.',
                            'UI harus kembali ke form login tanpa state exam tertinggal.',
                            'Login ulang pada fixture yang sama harus tetap bisa membuka shell exam secara normal.',
                        ],
                        'evidence' => [
                            'tests/e2e/timer-lifecycle.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Timer Logout Safe'],
                    ]),
                ],
            ],
            'question_runtime' => [
                'label' => 'Question Runtime',
                'summary' => 'Area untuk menjaga input jawaban, question state, navigation, doubtful flag, dan runtime soal tetap sinkron pada semua tipe soal.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Answer normalization per tipe soal tetap konsisten untuk pilihan, text, dan payload yang kompleks.', 'ready', [
                        'description' => 'Coverage question state sekarang memverifikasi payload jawaban dari runtime tetap ternormalisasi untuk multiple choice, multiple answer, true/false matrix, short answer, dan essay meski state lokal sudah mengandung data kotor.',
                        'process_steps' => [
                            'Vitest menyiapkan state.answers untuk lima tipe soal dengan kombinasi duplicate option id, matrix key ilegal, short answer kosong, dan text yang masih punya whitespace.',
                            'questionAnswerPayload() dipanggil langsung pada tiap tipe soal agar payload yang keluar merepresentasikan kontrak sync runtime sesungguhnya.',
                            'Assertion memastikan hanya opsi valid yang lolos, key matrix disaring, short answer dipetakan ke input_a, dan text dipangkas konsisten.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-state-manager.test.js',
                            'src/frontend/app/exam/question-state.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Question state dan doubtful flag tidak hilang saat jawaban berubah atau soal diganti.', 'ready', [
                        'description' => 'Coverage gabungan navigation dan input manager sekarang memastikan jawaban yang direvisi tetap tersimpan, sementara doubtful flag tetap utuh saat user pindah soal lalu kembali lagi.',
                        'process_steps' => [
                            'Vitest menyalakan doubtful pada soal aktif via handleAction(toggle-doubtful).',
                            'Input jawaban soal lain lalu diubah melalui answer input manager untuk memastikan perubahan jawaban tidak menyapu doubtful state yang sudah ada.',
                            'User kemudian digeser ke soal lain dan kembali lagi; assertion memverifikasi doubtful serta jawaban revisi tetap ada.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-navigation.test.js',
                            'tests/js/unit/question-inputs.test.js',
                            'src/frontend/app/exam/navigation.js',
                            'src/frontend/app/exam/answer-inputs.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Navigation index dan current question selalu mengarah ke soal yang valid.', 'ready', [
                        'description' => 'goToQuestion() sekarang punya coverage clamp eksplisit sehingga loncatan ke indeks terlalu besar atau negatif tetap berakhir di soal yang valid tanpa merusak state aktif.',
                        'process_steps' => [
                            'Vitest menavigasi dari currentIndex awal ke indeks jauh di luar range.',
                            'Navigation manager harus mengantrekan flush jawaban soal aktif, meng-clamp target ke indeks terakhir, lalu menggeser kembali ke indeks pertama saat diberi angka negatif.',
                            'Assertion memverifikasi currentIndex, active window, dan per-question state tetap konsisten sepanjang lompatan itu.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-navigation.test.js',
                            'src/frontend/app/exam/navigation.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Question render dan runtime tetap sinkron untuk rich content, option order, dan metadata soal.', 'ready', [
                        'description' => 'applyQuestionsResponse() sekarang punya coverage untuk menjaga rich text payload, urutan option, question_number dari manifest, dan answered state tetap sejajar saat window soal diperbarui.',
                        'process_steps' => [
                            'Vitest membangun response questions yang membawa rich HTML, option order non-trivial, question_manifest, answered_question_ids, dan existing_answers_map.',
                            'applyQuestionsResponse() dijalankan langsung pada runtime manager dengan state aktif.',
                            'Assertion memverifikasi payload question tetap membawa rich content, question_number diwarisi dari manifest, option order tidak berubah, dan state runtime ikut diperbarui.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-runtime-manager.test.js',
                            'src/frontend/app/exam/question-runtime.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Local state per soal tidak bocor ke soal lain saat user navigasi cepat.', 'ready', [
                        'description' => 'Coverage input dan navigation sekarang membuktikan perubahan satu soal hanya mengubah qid yang tepat, sementara state soal tetangga tetap utuh walau user lompat cepat antar indeks.',
                        'process_steps' => [
                            'Vitest mengubah input text pada satu qid dengan state awal beberapa soal yang sudah terjawab.',
                            'Navigation lalu dilompatkan cepat ke indeks lain dan kembali lagi.',
                            'Assertion memastikan state.answers dan answeredQuestionLookup soal lain tetap sama seperti sebelum aksi.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-inputs.test.js',
                            'tests/js/unit/question-navigation.test.js',
                            'src/frontend/app/exam/answer-inputs.js',
                            'src/frontend/app/exam/navigation.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Multiple answer melakukan dedupe pilihan dan uncheck terakhir menghapus answeredQuestionLookup.', 'ready', [
                        'description' => 'Input manager sekarang punya coverage sekaligus patch normalisasi: state multiple answer yang sudah terlanjur duplikat akan didedupe, dan uncheck terakhir benar-benar mengosongkan answeredQuestionLookup.',
                        'process_steps' => [
                            'Vitest menyiapkan state.answers multiple answer dengan option id ganda.',
                            'handleChangeTarget() dijalankan pada checkbox yang sama dalam kondisi checked lalu unchecked.',
                            'Assertion memverifikasi selected array menjadi unik dan answeredQuestionLookup hilang saat pilihan terakhir dibersihkan.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-inputs.test.js',
                            'src/frontend/app/exam/answer-inputs.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Mirrored short answer inputs sinkron tanpa loop value atau overwrite yang salah.', 'ready', [
                        'description' => 'Short answer mirror sekarang punya coverage untuk dua node input yang mewakili key yang sama: value disalin ke node pasangannya tanpa menyentuh state soal lain atau membuat overwrite balik yang salah.',
                        'process_steps' => [
                            'Vitest merender dua input short answer untuk qid dan short-key yang sama, plus satu input milik soal lain.',
                            'handleInputTarget() dipanggil pada input sumber dengan value baru lalu dengan value kosong.',
                            'Assertion memverifikasi mirror selalu ikut tersinkron, answeredQuestionLookup turun saat kosong, dan state soal lain tidak berubah.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-inputs.test.js',
                            'src/frontend/app/exam/answer-inputs.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Revision-safe restore memetakan jawaban berdasarkan option_key, bukan option_id.', 'ready', [
                        'description' => 'Question state manager sekarang punya coverage untuk memulihkan jawaban setelah option id berubah, asalkan option_key tetap sama.',
                        'process_steps' => [
                            'Vitest menangkap preserved answer dari soal lama yang memakai option id lama.',
                            'Question lookup kemudian diganti ke versi baru dengan option id berbeda tetapi option_key yang sama.',
                            'restoreRevisionSafeLocalAnswers() dijalankan dan assertion memastikan jawaban dipetakan ke option id baru yang tepat.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-state-manager.test.js',
                            'src/frontend/app/exam/question-state.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('Order signature conflict memblokir restore yang tidak aman sebelum state diterapkan.', 'ready', [
                        'description' => 'Persisted question cache sekarang punya coverage untuk signature mismatch: order/window/payload runtime baru tidak diterapkan, tetapi answer-only restore yang masih aman tetap boleh digabungkan.',
                        'process_steps' => [
                            'Vitest menyiapkan runtime state lama lalu memberikan snapshot cache dengan questionOrderSignature baru yang tidak cocok.',
                            'applyPersistedQuestionCache() dipanggil dengan expectedQuestionOrderSignature lama.',
                            'Assertion memastikan questionOrderIds dan questionPayloadById lama tetap aktif, sementara jawaban yang aman masih dapat dipulihkan ke state lokal.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-runtime-manager.test.js',
                            'src/frontend/app/exam/question-runtime.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                    self::unit_test_checklist_item('State lokal satu soal tidak bocor ke soal lain saat navigasi cepat dan load window bergeser.', 'ready', [
                        'description' => 'Coverage runtime sekarang menutup dua sisi sekaligus: restore jawaban yang tertunda hanya diterapkan saat question window yang relevan benar-benar loaded, dan lompatan navigation tidak menukar state antar soal.',
                        'process_steps' => [
                            'Vitest menyimpan preserved answer untuk soal yang belum ada di question window lalu menjalankan restore dengan mode defer.',
                            'Saat soal tersebut muncul di loaded questions, applyPendingRevisionSafeAnswersForLoadedQuestions() dipanggil untuk memastikan hanya qid yang tepat yang dipulihkan.',
                            'Navigation test pendamping memastikan perpindahan indeks cepat tetap menjaga jawaban soal lain apa adanya.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-state-manager.test.js',
                            'tests/js/unit/question-navigation.test.js',
                            'src/frontend/app/exam/question-state.js',
                            'src/frontend/app/exam/navigation.js',
                        ],
                        'runner_commands' => ['Vitest Question Runtime'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Jawab beberapa tipe soal berbeda secara bergantian lalu cek state tiap soal tetap terisolasi.', 'ready', [
                        'description' => 'Flow check ini memakai fixture runtime campuran dan mengisi multiple answer, short answer, serta essay secara bergantian untuk memastikan row backend tetap terpisah per qid.',
                        'process_steps' => [
                            'Runner memulai TEST Runtime Fixture dan mengambil metadata qid question dari helper backend.',
                            'Beberapa tipe soal dijawab satu per satu lewat UI browser.',
                            'Backend harus menyimpan row jawaban pada qid yang berbeda tanpa saling menimpa.',
                        ],
                        'evidence' => [
                            'tests/e2e/question-runtime.spec.js',
                            'tests/e2e/helpers/e2e-fixture.php',
                        ],
                        'runner_commands' => ['Playwright Runtime Mixed Isolation'],
                    ]),
                    self::unit_test_checklist_item('Toggle doubtful, pindah soal, lalu kembali lagi untuk memastikan flag dan jawaban tetap utuh.', 'ready', [
                        'description' => 'Flow check ini menandai soal aktif sebagai doubtful, pindah ke soal berikutnya, lalu kembali untuk memastikan flag serta jawaban yang sudah dipilih tidak hilang.',
                        'process_steps' => [
                            'Satu jawaban dipilih pada soal aktif fixture runtime.',
                            'Tombol doubtful diaktifkan, lalu user maju ke soal lain dan kembali lagi.',
                            'Setelah kembali, flag doubtful dan pilihan jawaban harus masih aktif.',
                        ],
                        'evidence' => [
                            'tests/e2e/question-runtime.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Runtime Doubtful Persist'],
                    ]),
                    self::unit_test_checklist_item('Coba navigasi ke batas awal dan akhir daftar soal untuk memastikan current index tidak keluar jalur.', 'ready', [
                        'description' => 'Flow check ini menekan navigation grid hingga soal terakhir lalu kembali ke awal untuk membuktikan current index tetap terclamp pada range yang valid.',
                        'process_steps' => [
                            'Runner menghitung jumlah nav button runtime yang tersedia.',
                            'User lompat ke soal terakhir lalu kembali ke soal pertama melalui navigation grid.',
                            'Indicator nomor soal harus kembali ke 1 tanpa overflow atau shell kosong.',
                        ],
                        'evidence' => [
                            'tests/e2e/question-runtime.spec.js',
                            'src/frontend/app/exam/navigation.js',
                        ],
                        'runner_commands' => ['Playwright Runtime Boundary Navigation'],
                    ]),
                    self::unit_test_checklist_item('Randomize options lalu refresh dan resume memastikan jawaban benar tetap tersimpan ke opsi yang tepat.', 'ready', [
                        'description' => 'Flow check ini memilih opsi pada soal yang option order-nya diacak, lalu reload untuk memastikan option_id yang sama tetap tercentang setelah resume.',
                        'process_steps' => [
                            'Runner menjawab satu soal pilihan ganda yang memakai randomized options.',
                            'Option id yang terpilih dicatat dari browser runtime.',
                            'Setelah reload, option dengan id yang sama harus tetap checked.',
                        ],
                        'evidence' => [
                            'tests/e2e/question-runtime.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Runtime Randomized Option Resume'],
                    ]),
                    self::unit_test_checklist_item('Toggle doubtful dan revisi jawaban beberapa tipe soal tetap konsisten saat maju-mundur antar soal.', 'ready', [
                        'description' => 'Flow check ini merevisi jawaban setelah doubtful aktif dan setelah user bolak-balik antar soal, lalu memastikan state akhir yang dipersist adalah revisi terakhir.',
                        'process_steps' => [
                            'Runner memilih jawaban awal, menyalakan doubtful, lalu pindah dan kembali ke soal tersebut.',
                            'Jawaban direvisi ke pilihan baru.',
                            'State akhir harus tetap memuat doubtful aktif sekaligus jawaban revisi terakhir yang benar.',
                        ],
                        'evidence' => [
                            'tests/e2e/question-runtime.spec.js',
                            'src/frontend/app/exam/question-state.js',
                        ],
                        'runner_commands' => ['Playwright Runtime Doubtful Revision'],
                    ]),
                    self::unit_test_checklist_item('Navigasi cepat maju-mundur tidak menukar payload antar soal yang berdekatan.', 'ready', [
                        'description' => 'Flow check ini menjawab dua soal berdekatan sambil lompat cepat maju-mundur untuk memastikan backend tetap menerima qid yang benar pada tiap payload.',
                        'process_steps' => [
                            'Runner menjawab soal pertama lalu langsung pindah ke soal kedua dan menjawab lagi.',
                            'User maju-mundur cepat beberapa kali sebelum sync tenang.',
                            'Row backend yang tersimpan harus tetap mengacu ke dua qid yang tepat, bukan tertukar.',
                        ],
                        'evidence' => [
                            'tests/e2e/question-runtime.spec.js',
                            'tests/e2e/helpers/flow-utils.js',
                        ],
                        'runner_commands' => ['Playwright Runtime Rapid Navigation'],
                    ]),
                ],
            ],
            'result_scoring' => [
                'label' => 'Result & Scoring',
                'summary' => 'Fokus pada stabilitas pass/fail, KKM, restricted result, pending essay, dan drift score antara snapshot review dengan nilai attempt tersimpan.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('resolveResultPassMeta menghitung pass/fail, KKM, dan passing score secara konsisten.', 'ready', [
                        'description' => 'Coverage pass/fail result sekarang ditutup dari dua sisi: renderer frontend memvalidasi label dan KKM yang dirender, sementara helper backend memastikan meta lulus tetap konsisten untuk score, max_score, dan KKM yang sama.',
                        'process_steps' => [
                            'Vitest merender stage result dengan score, max_score, dan KKM nol/non-nol untuk memastikan pass label dan angka batas lulus yang muncul sesuai state hasil.',
                            'PHPUnit memanggil build_result_pass_meta() via Reflection agar perhitungan passing_score, is_passed, pass_label, dan result_tone terkunci di helper backend.',
                            'Kombinasi ini menjaga parity antara meta hasil yang dihitung backend dan yang ditampilkan frontend.',
                        ],
                        'evidence' => [
                            'tests/js/unit/result-stage.test.js',
                            'tests/php/unit/ResultPayloadHelpersTest.php',
                            'src/frontend/app/stages/result.js',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring', 'PHPUnit Result Payload'],
                    ]),
                    self::unit_test_checklist_item('buildResultBreakdown menormalkan segment benar, salah, kosong, dan manual review.', 'ready', [
                        'description' => 'Renderer result sekarang punya coverage markup untuk breakdown progress sehingga segment empty dan pending manual tetap terbentuk benar dari review summary yang berbeda-beda.',
                        'process_steps' => [
                            'Vitest merender result stage dengan review summary kosong untuk memaksa jalur empty segment.',
                            'Skenario lain merender result dengan manual_questions > 0 agar segment pending dan kartu menunggu koreksi ikut muncul.',
                            'Assertion dilakukan terhadap markup legend dan copy yang dibentuk oleh buildResultBreakdown().',
                        ],
                        'evidence' => [
                            'tests/js/unit/result-stage.test.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('Restricted result mode tidak membocorkan score, review item, atau detail hasil.', 'ready', [
                        'description' => 'Jalur restricted sekarang dikunci di frontend result stage dan finish flow: score, review item, dan review detail tidak boleh bocor ketika show_student_result dimatikan atau result_view_mode restricted.',
                        'process_steps' => [
                            'Vitest merender result stage dalam mode restricted dan memastikan markup score akhir maupun review jawaban tidak ikut tampil.',
                            'Skenario finish flow restricted kemudian menyelesaikan attempt dan memverifikasi state.result yang dipakai UI sudah dibersihkan dari detail score serta review.',
                            'State daftar exam juga diverifikasi tidak menyimpan nilai terbaru saat restricted mode aktif.',
                        ],
                        'evidence' => [
                            'tests/js/unit/result-stage.test.js',
                            'tests/js/unit/finish-flow.test.js',
                            'src/frontend/app/stages/result.js',
                            'src/frontend/app/exam/finish-flow.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('Result payload tetap konsisten saat ada score drift antara stored attempt dan review snapshot.', 'ready', [
                        'description' => 'Finish flow sekarang punya coverage untuk drift: nilai awal dari finish_exam boleh berbeda, tetapi payload final yang dipakai UI harus mengikuti snapshot review terbaru dari endpoint result.',
                        'process_steps' => [
                            'Vitest menyimulasikan finish_exam yang mengembalikan score lama.',
                            'Endpoint result kemudian mengembalikan attempt.score dan review summary baru yang sudah direkalkulasi.',
                            'Assertion memastikan state.result dan daftar exam memakai nilai drift-corrected yang sama, bukan nilai finish payload yang stale.',
                        ],
                        'evidence' => [
                            'tests/js/unit/finish-flow.test.js',
                            'src/frontend/app/exam/finish-flow.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('Submission summary tetap akurat untuk total, answered, unanswered, dan pending manual.', 'ready', [
                        'description' => 'Helper backend summary sekarang punya coverage untuk menghitung total_questions, answered_questions, dan pending_manual_questions baik dari review summary lengkap maupun dari hitungan turunan saat answered_questions tidak tersedia.',
                        'process_steps' => [
                            'PHPUnit memanggil build_result_submission_summary() dengan summary lengkap dan summary turunan melalui Reflection.',
                            'Kasus answered_questions yang melebihi total_questions juga diverifikasi agar diklem aman.',
                            'Summarize_review_items() diuji terpisah untuk memastikan sumber review backend mengisi correct, wrong, manual, dan unanswered dengan benar.',
                        ],
                        'evidence' => [
                            'tests/php/unit/ResultPayloadHelpersTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit Result Payload'],
                    ]),
                    self::unit_test_checklist_item('buildResultBreakdown() menghasilkan segment empty saat semua count bernilai nol.', 'ready', [
                        'description' => 'Jalur empty segment pada result stage sekarang punya assertion eksplisit, jadi result tanpa jawaban atau tanpa review summary tidak runtuh menjadi progress bar kosong tanpa label.',
                        'process_steps' => [
                            'Vitest merender result stage dengan review_summary semua nol.',
                            'Markup hasil harus mengandung label BELUM ADA JAWABAN pada legend progress.',
                            'Ini memastikan fallback empty segment tetap konsisten saat belum ada data jawaban yang bisa dihitung.',
                        ],
                        'evidence' => [
                            'tests/js/unit/result-stage.test.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('Restricted result mengosongkan score, review_items, dan review_summary saat mode hasil dibatasi.', 'ready', [
                        'description' => 'Finish flow sekarang menormalkan payload restricted sebelum result stage dipasang ke state, sehingga score, review_items, dan review_summary benar-benar kosong dan tidak cuma disembunyikan di UI.',
                        'process_steps' => [
                            'Vitest menjalankan maybeFinalizeLockedExam() dengan endpoint result yang mengembalikan restricted payload sekaligus score dan review detail.',
                            'Manager harus mengubah payload final menjadi score nol, review_items kosong, dan review_summary null.',
                            'State result serta item exam list diverifikasi sama-sama memakai versi yang sudah disanitasi itu.',
                        ],
                        'evidence' => [
                            'tests/js/unit/finish-flow.test.js',
                            'src/frontend/app/exam/finish-flow.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('syncCompletedExamIntoList() menghormati show_student_result = 0 dan tidak membocorkan nilai ke daftar exam.', 'ready', [
                        'description' => 'Sinkronisasi daftar exam setelah finish sekarang punya coverage restricted khusus: latest_attempt_score, latest_attempt_pass_label, dan result tone tidak boleh ikut terisi saat exam menyembunyikan hasil siswa.',
                        'process_steps' => [
                            'Vitest menyelesaikan finish flow restricted untuk exam yang ada di state.exams.',
                            'syncCompletedExamIntoList() terpicu lewat completeExamWithResult().',
                            'Assertion memverifikasi latest_attempt_score tetap nol dan field pass/tone tetap kosong pada exam list.',
                        ],
                        'evidence' => [
                            'tests/js/unit/finish-flow.test.js',
                            'src/frontend/app/exam/finish-flow.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('[Integration] Drift antara stored attempt dan recalculated review tetap menghasilkan payload final yang konsisten.', 'ready', [
                        'description' => 'Walau levelnya lintas endpoint, coverage drift sekarang sudah menembak jalur finish -> result sungguhan di finish flow manager dengan dua payload berbeda, sehingga payload final yang sampai ke state result dan exam list tetap konsisten.',
                        'process_steps' => [
                            'Vitest mensimulasikan respons finish_exam dengan nilai lama lalu respons result dengan review snapshot yang sudah terkoreksi.',
                            'Manager menyatukan kedua payload itu lewat buildFinishedResultPayload() dan completeExamWithResult().',
                            'Assertion memastikan state.result dan latest_attempt_* di daftar exam mengacu ke snapshot review terbaru yang sama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/finish-flow.test.js',
                            'src/frontend/app/exam/finish-flow.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                    self::unit_test_checklist_item('Pending essay tidak memberi label lulus atau gagal final yang salah sebelum koreksi selesai.', 'ready', [
                        'description' => 'Result stage sekarang punya coverage untuk essay pending: UI harus menampilkan kartu menunggu koreksi dan menandai hasil sebagai sementara, bukan memberi kesan final sekalipun pass label dasar masih ada di payload.',
                        'process_steps' => [
                            'Vitest merender result stage dengan review_items essay berstatus manual dan pending_manual_questions > 0.',
                            'Markup harus mengandung pesan Menunggu Koreksi, jumlah soal esai pending, serta note bahwa hasil masih sementara.',
                            'Assertion ini memastikan user tidak diarahkan ke interpretasi final sebelum koreksi guru selesai.',
                        ],
                        'evidence' => [
                            'tests/js/unit/result-stage.test.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Vitest Result & Scoring'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Finish exam objektif penuh lalu cek score, percentage, dan pass label.', 'ready', [
                        'description' => 'Flow check ini menutup jalur hasil objektif penuh dan memverifikasi result shell menampilkan status lulus/gagal, score, dan percentage yang terlihat oleh siswa.',
                        'process_steps' => [
                            'Runner login ke TEST Result Fixture [FULL], menjawab minimal satu soal, lalu melakukan finish.',
                            'Result shell harus tampil setelah finish tanpa restricted guard.',
                            'Elemen score dan label pass/fail harus terlihat pada result wrap.',
                        ],
                        'evidence' => [
                            'tests/e2e/result-scoring.spec.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Playwright Result Objective Pass'],
                    ]),
                    self::unit_test_checklist_item('Exam dengan essay pending tetap menampilkan status sementara yang benar.', 'ready', [
                        'description' => 'Flow check ini memastikan exam essay yang belum dinilai tetap menandai hasil sebagai sementara dan menunggu koreksi, bukan score final.',
                        'process_steps' => [
                            'Runner menyelesaikan TEST Result Fixture [ESSAY] dengan jawaban essay.',
                            'Result shell dibuka segera setelah finish.',
                            'UI harus menampilkan teks menunggu koreksi dan indikator hasil sementara.',
                        ],
                        'evidence' => [
                            'tests/e2e/result-scoring.spec.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Playwright Result Essay Pending'],
                    ]),
                    self::unit_test_checklist_item('Exam dengan show_student_result nonaktif tampil dalam mode restricted.', 'ready', [
                        'description' => 'Flow check ini membuka fixture restricted dan memastikan siswa hanya melihat result shell terbatas tanpa score maupun review jawaban.',
                        'process_steps' => [
                            'Runner menyelesaikan TEST Result Fixture [RESTRICTED].',
                            'Result shell dibuka pada mode hasil dibatasi.',
                            'Card restricted harus muncul dan elemen score tidak boleh dirender.',
                        ],
                        'evidence' => [
                            'tests/e2e/result-scoring.spec.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Playwright Result Restricted Mode'],
                    ]),
                    self::unit_test_checklist_item('Ubah koreksi essay dari admin lalu cek hasil ikut berubah konsisten.', 'ready', [
                        'description' => 'Flow check ini memakai admin UI grading sungguhan untuk mengubah score essay, lalu memastikan hasil siswa ikut berubah setelah reopen result.',
                        'process_steps' => [
                            'Siswa menyelesaikan fixture essay dan terlebih dahulu melihat hasil sementara.',
                            'Admin login ke wp-admin lalu memberi nilai essay dari tab Essay pada halaman Results.',
                            'Siswa membuka ulang hasil dan harus melihat state hasil yang sudah tidak pending lagi.',
                        ],
                        'evidence' => [
                            'tests/e2e/result-scoring.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                        ],
                        'runner_commands' => ['Playwright Result Essay Regrade'],
                    ]),
                    self::unit_test_checklist_item('Essay pending dengan poin besar tetap tampil sebagai hasil sementara tanpa label final yang menyesatkan.', 'ready', [
                        'description' => 'Flow check ini memastikan essay dengan bobot besar tidak langsung menyesatkan siswa ke status final lulus/gagal ketika koreksi manual belum selesai.',
                        'process_steps' => [
                            'Runner menyelesaikan fixture essay yang masih pending manual scoring.',
                            'Result pending card harus tampil.',
                            'Teks hasil harus tetap menekankan sifat sementara dan tidak menonjolkan label final pass/fail.',
                        ],
                        'evidence' => [
                            'tests/e2e/result-scoring.spec.js',
                            'src/frontend/app/stages/result.js',
                        ],
                        'runner_commands' => ['Playwright Result Pending No Final Pass'],
                    ]),
                    self::unit_test_checklist_item('Restricted dan full result tetap sesuai setting setelah refresh dan reopen halaman hasil.', 'ready', [
                        'description' => 'Flow check ini membuka ulang hasil full dan restricted setelah refresh/reopen untuk memastikan mode tampilan tidak drift antara dua jenis exam berbeda.',
                        'process_steps' => [
                            'Runner menyelesaikan fixture full result, menangkap storage, lalu reopen context untuk membuka hasil yang sama lagi.',
                            'Score full result harus tetap terlihat setelah reopen.',
                            'Fixture restricted kemudian diselesaikan dan saat hasil dibuka ulang harus tetap berada pada mode restricted tanpa membocorkan score.',
                        ],
                        'evidence' => [
                            'tests/e2e/result-scoring.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Result Refresh Reopen'],
                    ]),
                ],
            ],
            'import_preview' => [
                'label' => 'Import & Preview',
                'summary' => 'Fokus ke parser DOCX, field pembahasan, gambar, tabel, normalisasi opsi, dan parity tampilan preview admin terhadap frontend.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Parser DOCX dan type mapping menerima format soal yang diizinkan secara konsisten.', 'ready', [
                        'description' => 'Parser import sekarang punya coverage formal untuk alias question type yang didukung, sehingga variasi seperti MCQ, TF, dan True False Matrix tetap dimap ke type internal yang benar.',
                        'process_steps' => [
                            'PHPUnit memanggil map_import_question_type() dengan beberapa alias umum dan format ber-spasi atau tanda hubung.',
                            'Semua hasil mapping diverifikasi terhadap slug type final yang dipakai saat import.',
                            'Coverage ini menjaga parser DOCX dan form import tetap konsisten saat variasi label type bertambah.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Explanation, image, dan table handling tetap utuh saat masuk dari import ke rich content soal.', 'ready', [
                        'description' => 'Rich content hasil import sekarang dikunci dari dua sisi: parser DOCX memisahkan pembahasan dan gambar ke field explanation, sementara renderer preview mempertahankan tabel dan blok gambar di HTML hasil render.',
                        'process_steps' => [
                            'PHPUnit memanggil parser DOCX block dengan pembahasan multi-line dan marker gambar untuk memastikan explanation terisi tanpa mencemari question_text.',
                            'Preview helper kemudian dirender dengan payload tabel dan data image untuk memastikan markup tetap utuh setelah render_editor_html().',
                            'Assertion memverifikasi tabel, gambar, dan pembahasan tidak hilang di jalur rich content admin.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Normalisasi opsi dan jawaban tetap benar untuk multiple answer, true/false, dan variasi input lain.', 'ready', [
                        'description' => 'Normalisasi input import sekarang punya coverage untuk dua jalur sensitif: multiple answer yang berbasis label opsi, dan true/false DOCX yang memakai kata seperti salah atau false.',
                        'process_steps' => [
                            'PHPUnit memverifikasi build_options_raw_from_import() tetap menandai opsi benar berdasarkan teks untuk multiple answer.',
                            'Kasus tanpa jawaban benar pada multiple_answer diverifikasi tetap ditolak agar import tidak menghasilkan payload ambigu.',
                            'Parser DOCX untuk forced type true_false diuji dengan jawaban teks seperti salah agar correct_answer dinormalisasi ke false.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Preview admin dan frontend review membaca spacer, image block, dan table fallback secara konsisten.', 'ready', [
                        'description' => 'Renderer rich text sekarang punya coverage untuk spacer kosong, data image, dan fallback line break sehingga payload HTML yang sama tetap aman dipakai di preview admin dan review yang membaca stored HTML tersebut.',
                        'process_steps' => [
                            'PHPUnit memanggil render_editor_html() dengan spacer paragraph, data image, dan plain text multi-line.',
                            'Output diverifikasi tetap memuat cbt-rich-spacer, data image, dan fallback <br /> saat markup eksplisit tidak ada.',
                            'Card preview admin juga dirender untuk memastikan stored HTML itu tetap terbaca utuh di panel preview.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Pembahasan hanya tampil bila explanation benar-benar berisi konten.', 'ready', [
                        'description' => 'Preview helper sekarang punya guard formal agar section Pembahasan hanya dirender bila explanation mengandung teks atau gambar yang valid.',
                        'process_steps' => [
                            'PHPUnit merender preview card dengan explanation kosong yang hanya berisi nbsp spacer.',
                            'Skenario kedua memakai explanation berisi tabel dan gambar.',
                            'Assertion memverifikasi label Pembahasan hilang pada payload kosong dan muncul pada payload yang memang berisi konten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('PEMBAHASAN: multi-line plus gambar masuk ke explanation, bukan ke question_text.', 'ready', [
                        'description' => 'Parser DOCX block sekarang punya coverage eksplisit untuk field PEMBAHASAN yang diikuti beberapa baris teks dan gambar, sehingga semua bagian tersebut masuk ke explanation saja.',
                        'process_steps' => [
                            'PHPUnit memberi block DOCX dengan QUESTION, PEMBAHASAN, dua baris pembahasan, lalu marker __IMG__.',
                            'Parser private parse_docx_multiple_choice_block() dipanggil via Reflection.',
                            'Assertion memverifikasi explanation berisi dua baris pembahasan plus img, sementara question_text tidak ikut memuat konten tersebut.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Alias EXPLANATION: dan key-only line diparse konsisten oleh parser DOCX.', 'ready', [
                        'description' => 'Normalizer baris DOCX sekarang punya coverage untuk marker key-only seperti EXPLANATION dan ANSWER yang nilainya muncul di baris berikutnya.',
                        'process_steps' => [
                            'PHPUnit memanggil normalize_docx_extracted_lines() dengan QUESTION, EXPLANATION, opsi, dan ANSWER yang semuanya ditulis key-only.',
                            'Hasil normalisasi diverifikasi berubah menjadi key-value line yang tetap dikenali parser.',
                            'Block hasil normalisasi kemudian diparse untuk memastikan explanation benar-benar terisi dan tidak jatuh ke question_text.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('build_options_raw_from_import() untuk multiple choice tanpa jawaban benar fallback ke opsi pertama saja.', 'ready', [
                        'description' => 'Helper build_options_raw_from_import() sekarang punya assertion formal bahwa multiple choice tanpa kunci benar tetap menghasilkan satu opsi benar yang dipaksa ke opsi pertama.',
                        'process_steps' => [
                            'PHPUnit memanggil build_options_raw_from_import() untuk multiple_choice tanpa correct_answer.',
                            'Output raw options diverifikasi menandai opsi pertama sebagai benar dan sisanya salah.',
                            'Kasus ini menjaga import template lama tidak menghasilkan soal tanpa kunci.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('render_editor_html() mempertahankan data image, spacer, dan fallback line break.', 'ready', [
                        'description' => 'Renderer rich text sekarang dikunci agar tiga perilaku penting tetap hidup: data URI image tidak dibuang, spacer paragraph dinormalisasi ke cbt-rich-spacer, dan teks multi-line tanpa markup diubah ke <br /> fallback.',
                        'process_steps' => [
                            'PHPUnit memanggil render_editor_html() dengan rich HTML yang memuat spacer dan data image.',
                            'Kasus kedua memberi teks biasa multi-line untuk memaksa fallback line break.',
                            'Output dari kedua jalur diverifikasi langsung pada string HTML hasil render.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Preview admin dan review siswa tetap konsisten untuk tabel, gambar, dan enter antar blok.', 'ready', [
                        'description' => 'Stored rich HTML untuk pertanyaan dan pembahasan sekarang punya coverage render yang mempertahankan tabel, blok gambar, dan line break antar blok, sehingga payload yang sama tetap aman untuk admin preview dan jalur review yang membaca HTML tersimpan.',
                        'process_steps' => [
                            'PHPUnit merender preview card dengan question_text berbentuk tabel dan explanation yang memuat tabel, spacer, serta data image.',
                            'Preview output diverifikasi masih memuat markup tabel pada question dan explanation.',
                            'Fallback line break juga diuji terpisah agar enter antar blok teks tidak hilang saat rich HTML dibaca kembali.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Validator manual dan DOCX menolak jawaban benar yang menunjuk pilihan kosong serta pilihan duplikat secara konsisten.', 'ready', [
                        'description' => 'Helper validasi authoring sekarang mengunci dua guard inti untuk MC dan MA: pilihan minimal tetap dijaga, dan referensi jawaban benar ke opsi kosong tidak lagi jatuh ke error generik.',
                        'process_steps' => [
                            'PHPUnit memanggil validate_choice_options() untuk kasus MC dan MA dengan referensi jawaban benar ke opsi kosong.',
                            'Parser DOCX diuji lagi pada blok yang menandai jawaban benar ke opsi yang tidak ada atau duplikat.',
                            'Assertion memverifikasi pesan error spesifik tetap konsisten antara helper dan parser DOCX.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperShortAnswerTest.php',
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Validator manual dan DOCX untuk TF Matrix menjaga gap numbering, statement kosong, dan statement duplikat.', 'ready', [
                        'description' => 'True/False Matrix sekarang memakai helper validasi yang sama untuk memastikan nomor pernyataan tetap kontigu, statement kosong ditolak dari source payload, dan duplikasi statement tidak lolos.',
                        'process_steps' => [
                            'PHPUnit memanggil validate_true_false_matrix_items() untuk gap numbering dan statement kosong pada source rows.',
                            'Parser DOCX diuji lagi untuk gap numbering, key tanpa statement, dan duplicate statement.',
                            'Assertion memastikan alasan gagal tetap spesifik dan tidak turun ke generic parse failure.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperShortAnswerTest.php',
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Short Answer tetap positional: placeholder dan key dijaga ketat, tetapi jawaban antar input boleh sama.', 'ready', [
                        'description' => 'Hardening Short Answer sekarang eksplisit mempertahankan model positional: key placeholder wajib cocok, tetapi dua input berbeda tetap boleh punya jawaban valid yang sama setelah normalisasi.',
                        'process_steps' => [
                            'PHPUnit memanggil validate_short_answer_definition() dengan dua jawaban yang ternormalisasi sama untuk input A dan B.',
                            'Parser DOCX tetap diuji untuk mismatch key dan duplicate placeholder.',
                            'Assertion memastikan duplicate normalized answers tidak dianggap invalid selama key dan jumlah input tetap benar.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperShortAnswerTest.php',
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('State import DOCX menyimpan failure terstruktur agar UI admin bisa menampilkan blok, tipe, preview, dan pesan spesifik.', 'ready', [
                        'description' => 'Jalur import sekarang menormalkan recent_failures ke entry terstruktur, sehingga panel import bisa menampilkan alasan gagal yang mudah discan tanpa kehilangan metadata blok dan preview.',
                        'process_steps' => [
                            'PHPUnit memanggil normalize_question_import_failure_entries() dengan payload structured dan legacy string.',
                            'describe_docx_block_failure() diverifikasi mengembalikan alasan spesifik untuk gap TF Matrix dan mismatch key Short Answer.',
                            'Assertion memverifikasi field block_number, question_type, question_preview, message, dan formatted tetap terisi konsisten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                            'admin/class-cbt-admin-questions-import-helper.php',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Import satu DOCX dengan gambar, tabel, dan PEMBAHASAN lalu cek hasil preview.', 'ready', [
                        'description' => 'Flow check ini mengimpor fixture DOCX rich-content ke Bank Soal subject target, menyinkronkannya ke TEST Import Preview Fixture, lalu memverifikasi admin preview berhasil membaca soal impor tersebut.',
                        'process_steps' => [
                            'Admin login ke wp-admin lalu mengunggah file tests/e2e/fixtures/import-preview/rich-content.docx pada subject fixture import preview.',
                            'Import dibiarkan selesai sampai progress state berubah menjadi selesai diproses.',
                            'Helper backend menyinkronkan bank question terbaru ke TEST Import Preview Fixture, lalu admin preview harus memuat marker soal impor yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/rich-content.docx',
                            'tests/e2e/helpers/admin-browser.js',
                        ],
                        'runner_commands' => ['Playwright Import Admin Preview'],
                    ]),
                    self::unit_test_checklist_item('Import DOCX lama tanpa PEMBAHASAN tetap berhasil dan tidak merusak parsing.', 'ready', [
                        'description' => 'Flow check ini menguji fixture DOCX legacy tanpa field explanation untuk memastikan jalur import admin tetap selesai dan preview masih memuat soal yang dihasilkan.',
                        'process_steps' => [
                            'Admin mengunggah tests/e2e/fixtures/import-preview/legacy-no-pembahasan.docx lewat tab Import Questions.',
                            'Progress import ditunggu sampai selesai diproses tanpa notice error.',
                            'Preview exam fixture setelah sync harus tetap memuat marker legacy yang baru diimpor.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/legacy-no-pembahasan.docx',
                        ],
                        'runner_commands' => ['Playwright Import Legacy Compatible'],
                    ]),
                    self::unit_test_checklist_item('Bandingkan satu soal kaya gambar atau tabel di admin preview dan review siswa.', 'ready', [
                        'description' => 'Flow check ini mengambil marker soal impor yang sama dari preview admin dan review siswa untuk memastikan satu pertanyaan hasil import tetap terbaca konsisten pada dua jalur UI yang berbeda.',
                        'process_steps' => [
                            'Fixture rich-content diimpor dan disinkronkan ke TEST Import Preview Fixture.',
                            'Admin preview dibuka untuk memastikan marker soal hasil impor sudah hadir.',
                            'Siswa menyelesaikan fixture yang sama, lalu result review harus memuat marker soal impor yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'admin/views/exams/preview.php',
                            'src/frontend/app/stages/review.js',
                        ],
                        'runner_commands' => ['Playwright Import Admin Review Parity'],
                    ]),
                    self::unit_test_checklist_item('Cek enter antar gambar dan blok rich text tetap terbaca konsisten di preview.', 'ready', [
                        'description' => 'Flow check ini memakai fixture line-break untuk memastikan preview admin tetap dapat menampilkan marker multi-baris hasil import DOCX tanpa collapse ke satu blok kosong.',
                        'process_steps' => [
                            'Admin mengunggah tests/e2e/fixtures/import-preview/image-linebreak.docx ke subject fixture.',
                            'Setelah sync ke TEST Import Preview Fixture, admin preview dibuka ulang.',
                            'Preview harus memuat marker baris pertama dan kedua dari konten yang sama sebagai bukti rich text multi-line tetap terbaca.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/image-linebreak.docx',
                        ],
                        'runner_commands' => ['Playwright Import Preview Linebreak'],
                    ]),
                    self::unit_test_checklist_item('Import DOCX dengan gambar, tabel, dan pembahasan lalu bandingkan hasil admin preview vs review siswa.', 'ready', [
                        'description' => 'Flow check ini membuka admin preview dan review siswa untuk fixture rich import yang sama, lalu mencocokkan marker unik pertanyaan hasil impor pada kedua sisi.',
                        'process_steps' => [
                            'Fixture rich-content diimpor dan disinkronkan ke exam preview siswa.',
                            'Admin preview memverifikasi marker rich-content sudah terbaca dari exam fixture.',
                            'Result review siswa setelah finish exam harus menampilkan marker rich-content yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/rich-content.docx',
                        ],
                        'runner_commands' => ['Playwright Import Rich Preview Review Parity'],
                    ]),
                    self::unit_test_checklist_item('Soal kaya gambar dengan enter antar gambar tetap turun baris di preview dan review.', 'ready', [
                        'description' => 'Flow check ini memverifikasi fixture line-break tetap menjaga marker multi-baris yang sama di preview admin maupun review siswa setelah finish exam.',
                        'process_steps' => [
                            'Fixture image-linebreak diimpor lalu disinkronkan ke TEST Import Preview Fixture.',
                            'Admin preview harus memuat dua marker baris dari soal impor yang sama.',
                            'Setelah siswa menyelesaikan exam fixture, result review harus tetap memuat kedua marker baris tersebut.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/image-linebreak.docx',
                        ],
                        'runner_commands' => ['Playwright Import Linebreak Review Parity'],
                    ]),
                    self::unit_test_checklist_item('Import DOCX invalid menampilkan daftar failure yang spesifik per blok.', 'ready', [
                        'description' => 'Flow check ini mengunggah fixture DOCX invalid campuran dan memastikan panel import admin menampilkan metadata blok, tipe soal, preview singkat, dan pesan error spesifik untuk tiap blok yang gagal.',
                        'process_steps' => [
                            'Admin login ke wp-admin lalu mengunggah tests/e2e/fixtures/import-preview/invalid-hardening.docx.',
                            'Import dibiarkan selesai sampai state progress berubah menjadi selesai diproses.',
                            'Daftar Gagal import terbaru harus memuat blok MC, Short Answer, dan TF Matrix dengan alasan gagal masing-masing.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/invalid-hardening.docx',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Import Invalid Failure List'],
                    ]),
                    self::unit_test_checklist_item('Save manual MC ditahan saat jawaban benar menunjuk pilihan kosong.', 'ready', [
                        'description' => 'Flow check ini memakai form question manual di wp-admin untuk memastikan guard client-side authoring tetap memblokir MC ketika pilihan yang dipilih sebagai jawaban benar belum diisi.',
                        'process_steps' => [
                            'Admin membuka Form Question, memilih subject fixture, lalu mengisi soal MC dengan pilihan 1, 3, dan 4 saja.',
                            'Dropdown Jawaban Benar dipilih ke Pilihan 2 yang kosong.',
                            'Submit harus memunculkan alert spesifik dan form tetap tidak tersimpan.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Authoring MC Empty Correct'],
                    ]),
                    self::unit_test_checklist_item('Save manual Multiple Answer ditahan saat checkbox benar menandai pilihan kosong.', 'ready', [
                        'description' => 'Flow check ini memastikan guard authoring MA tetap menolak kondisi ketika admin mencentang opsi kosong sebagai jawaban benar, sehingga payload tidak sempat jatuh ke validasi generik.',
                        'process_steps' => [
                            'Admin mengisi soal MA dengan pilihan 1, 2, dan 4, lalu mencentang pilihan 1 dan 3 sebagai benar.',
                            'Pilihan 3 dibiarkan kosong untuk memicu guard.',
                            'Submit harus memunculkan alert spesifik dan form tetap berada di panel manual.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Authoring MA Empty Correct'],
                    ]),
                    self::unit_test_checklist_item('Save manual TF Matrix ditahan saat numbering loncat atau pernyataan duplikat.', 'ready', [
                        'description' => 'Flow check ini menguji dua guard manual TF Matrix dalam satu sesi: numbering gap saat statement diisi loncat, lalu duplicate statement setelah numbering dibuat kontigu.',
                        'process_steps' => [
                            'Admin mengisi Pernyataan 1 dan 3 untuk memicu gap numbering.',
                            'Setelah alert pertama, Pernyataan 2 diisi dengan teks yang sama seperti Pernyataan 1.',
                            'Submit kedua harus memunculkan alert duplicate statement dan form tetap tidak tersimpan.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Authoring TFM Validation'],
                    ]),
                ],
            ],
            'security_log_observability' => [
                'label' => 'Security Log & Observability',
                'summary' => 'Area untuk memastikan event security tercatat rapi, severity tetap benar, must-watch aggregation stabil, dan context insiden mudah ditelusuri.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Security event recording menyimpan event yang relevan ke attempt yang tepat.', 'ready', [
                        'description' => 'Jalur logging utama sekarang punya coverage formal untuk record_attempt_event() dan record_latest_student_attempt_event(), termasuk severity bawaan dan context yang disimpan ke row log.',
                        'process_steps' => [
                            'PHPUnit menyiapkan fake wpdb dengan attempt aktif yang bisa ditemukan baik lewat attempt_id maupun student_id.',
                            'record_attempt_event() dan record_latest_student_attempt_event() dipanggil dengan event clipboard dan admin_force_complete.',
                            'Inserted row diverifikasi untuk attempt_id, severity, message default, dan context_json hasil normalisasi.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Severity mapping dan message normalization tetap konsisten saat event type bertambah.', 'ready', [
                        'description' => 'Event definition dan message display sekarang ditutup lewat coverage yang memastikan severity bawaan event dipakai saat insert, sementara get_recent_logs() tetap membangun pesan observability yang kaya device dan source.',
                        'process_steps' => [
                            'PHPUnit memverifikasi inserted row untuk clipboard_blocked memakai severity warning dan admin_force_complete memakai severity info.',
                            'Fake recent logs kemudian dibaca lewat get_recent_logs() untuk memastikan message_display tetap mengandung device summary dan source label yang benar.',
                            'Coverage ini menjaga mapping severity/message tidak berubah diam-diam saat event baru ditambahkan.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Must-watch aggregation dan risk score tidak double count atau kehilangan indikator penting.', 'ready', [
                        'description' => 'Aggregator must-watch sekarang punya coverage formal untuk risk score, event_total, session_revoked_count, dan top indicators pada dua attempt dengan kombinasi event yang sama.',
                        'process_steps' => [
                            'PHPUnit menyiapkan empat row log yang membentuk dua attempt dengan score tie yang sama.',
                            'get_must_watch_attempts() dipanggil untuk memaksa jalur agregasi, primary event, dan top indicators.',
                            'Assertion memverifikasi risk_score 10, event_total 2, dan indikator 1x Sesi dicabut serta 1x Clipboard diblokir tidak terduplikasi.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Admin force action seperti reset login dan force complete tetap tercatat dengan source yang jelas.', 'ready', [
                        'description' => 'Event admin sekarang punya coverage untuk source/context yang ikut masuk ke log sehingga aksi operator tetap bisa ditelusuri dari panel observability.',
                        'process_steps' => [
                            'PHPUnit merekam event admin_force_complete lewat latest student attempt.',
                            'Context source must_watch_panel dan note operator diverifikasi tetap masuk ke context_json.',
                            'Recent log admin_reset_login juga diverifikasi memetakan source ke label Panel admin pada message_display.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Observability timeline menyimpan context yang cukup untuk recovery dan investigasi insiden.', 'ready', [
                        'description' => 'Recent log formatter sekarang ditutup agar context penting seperti source, device type, platform, dan viewport tetap hadir pada output message_display dan device_summary.',
                        'process_steps' => [
                            'PHPUnit menyiapkan row recent log clipboard_blocked dengan source copy, desktop, windows, dan viewport lengkap.',
                            'get_recent_logs() dipanggil untuk membentuk message_display dan device_summary.',
                            'Assertion memverifikasi pesan akhir memuat source label, device summary, dan fallback server context untuk event admin.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Clipboard blocked dan fullscreen exit mengikuti debounce agar tidak spam event untuk aksi yang sama.', 'ready', [
                        'description' => 'Security manager frontend sekarang punya coverage untuk dua jalur anti-spam: fullscreen exit suppression sesudah silent exit, dan clipboard block yang selalu mengirim debounce hint ke logger.',
                        'process_steps' => [
                            'Vitest memasang manager security dengan event emitter document/window sederhana dan fake timer.',
                            'fullscreenchange dipicu dua kali, dengan silent exit di antaranya, untuk memastikan log kedua tersupresi sampai window debounce lewat.',
                            'handleBlockedClipboardAction() diverifikasi memanggil logger dengan debounceMs 1500 sekaligus mem-block event browser.',
                        ],
                        'evidence' => [
                            'tests/js/unit/security-manager.test.js',
                            'src/frontend/app/exam/security.js',
                        ],
                        'runner_commands' => ['Vitest Security Log'],
                    ]),
                    self::unit_test_checklist_item('Bridge resmi native mengembalikan token, attempt aktif, dan endpoint hanya saat sesi exam valid.', 'ready', [
                        'description' => 'Frontend sekarang mengekspor CBTNativeBridge.getSecuritySnapshot() agar Android WebView dan Windows CEFSharp bisa membaca auth snapshot resmi tanpa mengandalkan struktur sessionStorage mentah.',
                        'process_steps' => [
                            'Vitest memasang native bridge dengan state exam aktif dan endpoint builder yang deterministik.',
                            'Snapshot diverifikasi memuat token, attemptId, selectedExamId, dan nativeSecurityEvent endpoint saat stage exam.',
                            'Skenario login/non-exam diverifikasi mengembalikan ok=0, token kosong, dan tetap mempertahankan property bridge native yang sudah ada.',
                        ],
                        'evidence' => [
                            'tests/js/unit/native-bridge.test.js',
                            'src/frontend/app/core/native-bridge.js',
                            'src/frontend/app/runtime.js',
                        ],
                        'runner_commands' => ['Vitest Native Bridge'],
                    ]),
                    self::unit_test_checklist_item('[PHP Unit] Logging disabled short-circuit tanpa write ke database log.', 'ready', [
                        'description' => 'Guard global logging sekarang punya assertion eksplisit: saat log_security_events dimatikan, record_attempt_event() harus berhenti sebelum insert ke database dilakukan.',
                        'process_steps' => [
                            'PHPUnit mematikan option cbt_setup_security.log_security_events.',
                            'record_attempt_event() dipanggil pada attempt valid dan hasilnya harus false.',
                            'Fake wpdb diverifikasi tidak menerima inserted row sama sekali.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Endpoint native security memvalidasi native app dan memaksa source canonical dari backend.', 'ready', [
                        'description' => 'REST native sekarang punya coverage untuk payload valid, rejection native_app invalid, dan guard bahwa native event tidak boleh lewat endpoint browser biasa.',
                        'process_steps' => [
                            'PHPUnit memanggil native_security_event dengan payload JSON dan fake attempt aktif milik siswa yang benar.',
                            'Context yang direkam diverifikasi memakai source canonical windows_cefsharp_shell, bukan source mentah dari client.',
                            'Skenario native_app invalid dan native event yang dikirim ke security_event biasa diverifikasi ditolak dengan error code yang eksplisit.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestNativeSecurityEventTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit Native Security Event'],
                    ]),
                    self::unit_test_checklist_item('[PHP Unit] Unknown event type punya fallback aman atau ditolak eksplisit sesuai kebijakan final.', 'ready', [
                        'description' => 'Kebijakan final di backend adalah reject eksplisit. Coverage sekarang mengunci bahwa event_type yang tidak punya definition tidak boleh ditulis ke security log.',
                        'process_steps' => [
                            'PHPUnit menyalakan logging lalu memanggil record_attempt_event() dengan unknown_event_type.',
                            'Return value harus false.',
                            'Fake wpdb diverifikasi tetap tidak menerima insert baru untuk event yang tidak dikenal.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('[PHP Unit] Must-watch aggregation tidak double count dan urutan tetap stabil saat skor sama.', 'ready', [
                        'description' => 'Tie-break must-watch sekarang dikunci agar dua attempt dengan score sama tetap diurutkan berdasarkan last_event_at terbaru, tanpa membuat count atau score membengkak.',
                        'process_steps' => [
                            'PHPUnit menyiapkan dua attempt dengan kombinasi session_revoked + clipboard_blocked yang identik.',
                            'Timestamp attempt kedua dibuat lebih baru agar tie-break bisa diamati secara tegas.',
                            'Assertion memverifikasi attempt terbaru muncul dulu, tetapi risk_score dan event_total di kedua attempt tetap sama dan tidak terduplikasi.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                    self::unit_test_checklist_item('Admin reset login dan force complete tercatat dengan source dan context yang benar.', 'ready', [
                        'description' => 'Aksi admin yang bersifat destruktif atau intervensi sekarang punya coverage untuk source normalization dan context persistence, sehingga panel observability tetap bisa membedakan reset login dan force complete.',
                        'process_steps' => [
                            'PHPUnit merekam admin_force_complete dengan source must_watch_panel pada inserted row.',
                            'Recent logs fake untuk admin_reset_login dibaca agar message_display menampilkan source Panel admin.',
                            'Context tambahan seperti note operator dipastikan tidak hilang saat dinormalisasi ke JSON.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SecurityLogObservabilityTest.php',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['PHPUnit Security Log'],
                    ]),
                ],
                'smoke_tests' => [
                    self::unit_test_checklist_item('Picu event security saat attempt aktif lalu pastikan log muncul di panel observability.', 'ready', [
                        'description' => 'Flow check ini memicu clipboard_blocked dari browser siswa lalu memverifikasi row log yang sama muncul di panel CBT Security > Security Log lewat UI admin.',
                        'process_steps' => [
                            'Runner menyalakan logging security dan blok clipboard untuk TEST Security Fixture.',
                            'Siswa memulai attempt aktif lalu memicu aksi copy yang diblok.',
                            'Admin membuka panel observability dan harus melihat row event Clipboard diblokir untuk siswa serta attempt yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'tests/e2e/helpers/frontend-browser.js',
                        ],
                        'runner_commands' => ['Playwright Security Frontend Log Visible'],
                    ]),
                    self::unit_test_checklist_item('Gunakan admin reset login atau force complete dan verifikasi event follow-up ikut tercatat.', 'ready', [
                        'description' => 'Flow check ini menggunakan tombol Reset Login pada kartu Must Watch lalu memastikan follow-up event admin_reset_login ikut masuk ke histori observability.',
                        'process_steps' => [
                            'Siswa memicu cukup banyak event frontend untuk membuat attempt masuk Must Watch.',
                            'Admin membuka CBT Security > Security Log dan menjalankan action Reset Login dari kartu Must Watch.',
                            'Histori log harus memuat row Reset login admin sebagai event follow-up baru.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'admin/class-cbt-admin-security-page.php',
                        ],
                        'runner_commands' => ['Playwright Security Admin Follow Up'],
                    ]),
                    self::unit_test_checklist_item('Cek urutan dan indicator must-watch setelah beberapa event berbeda masuk ke attempt yang sama.', 'ready', [
                        'description' => 'Flow check ini membuat dua attempt dengan skor risiko berbeda, lalu memverifikasi mode sort berdasarkan skor menempatkan attempt berisiko lebih tinggi di kartu pertama Must Watch.',
                        'process_steps' => [
                            'Runner menghasilkan kombinasi event security berbeda pada dua siswa seed di TEST Security Fixture.',
                            'Admin membuka panel Must Watch lalu mengubah sorting ke Skor tertinggi.',
                            'Kartu pertama harus menunjukkan siswa dengan kombinasi event dan skor risiko paling tinggi.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'admin/class-cbt-admin-security-page.php',
                        ],
                        'runner_commands' => ['Playwright Security Must Watch Order'],
                    ]),
                    self::unit_test_checklist_item('Picu beberapa event berbeda pada attempt yang sama lalu cek urutan dan indikator must-watch tetap benar.', 'ready', [
                        'description' => 'Flow check ini menumpuk beberapa event frontend pada attempt yang sama lalu memeriksa kartu Must Watch tetap mengagregasi indikator tanpa memecah attempt menjadi kartu terpisah.',
                        'process_steps' => [
                            'Runner memicu clipboard_blocked beberapa kali dan page_leave pada attempt yang sama.',
                            'Admin membuka panel Must Watch untuk attempt tersebut.',
                            'Kartu yang muncul harus tetap satu attempt yang sama sambil menampilkan lebih dari satu indikator event.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'includes/class-cbt-security-log.php',
                        ],
                        'runner_commands' => ['Playwright Security Multi Event Aggregate'],
                    ]),
                    self::unit_test_checklist_item('Event security dari frontend tetap terbaca di panel observability setelah refresh admin.', 'ready', [
                        'description' => 'Flow check ini memverifikasi row observability tidak hilang setelah panel admin direfresh, sehingga histori frontend yang baru masuk tetap bisa ditelusuri ulang.',
                        'process_steps' => [
                            'Runner memicu satu event frontend pada attempt aktif.',
                            'Admin memastikan row event terlihat di panel observability.',
                            'Panel admin direfresh dan row yang sama harus tetap terlihat sesudah reload.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'admin/views/security/page.php',
                        ],
                        'runner_commands' => ['Playwright Security Refresh Persistence'],
                    ]),
                    self::unit_test_checklist_item('Native direct API bisa menulis security log yang muncul normal di observability panel.', 'ready', [
                        'description' => 'Flow ini memulai attempt siswa, membaca snapshot auth resmi dari frontend, lalu mengirim event CBT existing langsung ke endpoint native security event dengan bearer token siswa.',
                        'process_steps' => [
                            'Siswa membuka TEST Security Fixture dan bridge native dibaca dari browser untuk mendapatkan token, attemptId, dan endpoint native.',
                            'Runner mengirim POST native_security_event langsung dari konteks frontend memakai warning task_switch untuk Windows CEFSharp.',
                            'Admin membuka Security Log dan memastikan row Pindah tab / aplikasi muncul untuk attempt yang sama lengkap dengan detail native.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'src/frontend/app/core/native-bridge.js',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['Playwright Security Native Direct API'],
                    ]),
                    self::unit_test_checklist_item('Tab Native menampilkan sample request dan simulator admin menulis log sample ke observability.', 'ready', [
                        'description' => 'Flow ini memverifikasi tab Native pada CBT Security berfungsi sebagai pusat spec + tool: sample request tampil, lalu simulator admin menambahkan fullscreen_exit ke log dan menaikkan skor Must Watch.',
                        'process_steps' => [
                            'Siswa memulai attempt dan memicu satu clipboard blocked sebagai baseline skor observability.',
                            'Admin membuka tab Native, memeriksa sample JSON/cURL/snippet Android serta CEFSharp, lalu mensimulasikan fullscreen_exit untuk attempt yang sama.',
                            'Panel Security Log harus menampilkan row baru dan kartu Must Watch siswa naik ke skor total 6.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'admin/views/setup/page.php',
                            'admin/class-cbt-admin-security-actions.php',
                        ],
                        'runner_commands' => ['Playwright Security Native Tool'],
                    ]),
                ],
            ],
        ];

        foreach ($tabs as $tab_key => $tab) {
            $status_meta = self::unit_test_status_meta((string) ($tab['status'] ?? 'draft'));
            $tabs[$tab_key]['status_label'] = $status_meta['label'];
            $tabs[$tab_key]['status_tone'] = $status_meta['tone'];

            foreach (['unit_tests', 'smoke_tests'] as $list_key) {
                $items = isset($tab[$list_key]) && is_array($tab[$list_key]) ? (array) $tab[$list_key] : [];
                foreach ($items as $item_index => $item) {
                    $item_status_meta = self::unit_test_status_meta((string) ($item['status'] ?? 'draft'));
                    $items[$item_index]['status_label'] = $item_status_meta['label'];
                    $items[$item_index]['status_tone'] = $item_status_meta['tone'];
                }
                $tabs[$tab_key][$list_key] = $items;
            }
        }

        return $tabs;
    }

    public static function normalize_unit_test_tab($raw_tab): string
    {
        $tab = sanitize_key((string) $raw_tab);
        $definitions = self::get_unit_test_tab_definitions();

        return isset($definitions[$tab]) ? $tab : 'recovery_persistence';
    }

    public static function normalize_unit_test_scope($raw_scope): string
    {
        $scope = sanitize_key((string) $raw_scope);

        return $scope === 'smoke_tests' ? 'smoke_tests' : 'unit_tests';
    }
}
