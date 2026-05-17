<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Test_Hub_Service
{
    private const UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX = 'cbt_unit_test_run_result_';
    private const GLOBAL_UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX = 'cbt_global_unit_test_run_result_';
    private const GLOBAL_UNIT_TEST_RUN_STATE_TRANSIENT_PREFIX = 'cbt_global_unit_test_run_state_';
    private const UNIT_TEST_RUN_RESULT_TTL = 15 * MINUTE_IN_SECONDS;
    private const GLOBAL_UNIT_TEST_RUN_STATE_TTL = 60 * MINUTE_IN_SECONDS;
    private const SETTINGS_OPTION = 'cbt_test_hub_settings_v1';
    private const RUNNER_HEALTH_OPTION = 'cbt_test_hub_runner_health_v1';
    private const E2E_READINESS_OPTION = 'cbt_test_hub_e2e_readiness_v1';
    private const FLOW_JOB_DIRECTORY_RELATIVE = 'playwright-results/admin-jobs';
    private const FLOW_JOB_HEARTBEAT_TIMEOUT = 90;
    private const FLOW_JOB_MAX_RUNTIME = 20 * MINUTE_IN_SECONDS;
    private const FLOW_JOB_WORKER_PID_GRACE_SECONDS = 30;
    private const FLOW_JOB_LOG_PREVIEW_MAX_BYTES = 65536;
    private const FLOW_JOB_LOG_PREVIEW_MAX_LINES = 400;
    private const FLOW_JOB_ARTIFACT_LIST_LIMIT = 50;
    private const E2E_READINESS_TIMEOUT = 8;
    private const UNIT_TEST_ACTION_TIME_LIMIT = 180;

    public static function can_manage_test_hub(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @return array{e2e_base_url:string,e2e_frontend_url:string}
     */
    public static function get_settings(): array
    {
        $raw = get_option(self::SETTINGS_OPTION, []);
        return self::sanitize_settings_input(is_array($raw) ? $raw : []);
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{e2e_base_url:string,e2e_frontend_url:string}
     */
    public static function sanitize_settings_input(array $raw): array
    {
        $raw_base_url = isset($raw['e2e_base_url']) && is_scalar($raw['e2e_base_url']) ? (string) $raw['e2e_base_url'] : '';
        $raw_frontend_url = isset($raw['e2e_frontend_url']) && is_scalar($raw['e2e_frontend_url']) ? (string) $raw['e2e_frontend_url'] : '';
        $base_url = esc_url_raw($raw_base_url);
        $frontend_url = esc_url_raw($raw_frontend_url);

        return [
            'e2e_base_url' => is_string($base_url) ? trim($base_url) : '',
            'e2e_frontend_url' => is_string($frontend_url) ? trim($frontend_url) : '',
        ];
    }

    /**
     * @param array{e2e_base_url:string,e2e_frontend_url?:string} $settings
     */
    public static function save_settings(array $settings): void
    {
        update_option(self::SETTINGS_OPTION, $settings, false);
    }

    /**
     * @return array{checked_at:int,overall_status:string,checks:array<int,array<string,string>>}
     */
    public static function get_runner_health_snapshot(): array
    {
        $raw = get_option(self::RUNNER_HEALTH_OPTION, []);
        return self::normalize_runner_health_snapshot(is_array($raw) ? $raw : []);
    }

    /**
     * @return array{checked_at:int,overall_status:string,checks:array<int,array<string,string>>,suggestions:array<int,string>}
     */
    public static function get_e2e_readiness_snapshot(): array
    {
        $raw = get_option(self::E2E_READINESS_OPTION, []);
        return self::normalize_e2e_readiness_snapshot(is_array($raw) ? $raw : []);
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private static function save_runner_health_snapshot(array $snapshot): void
    {
        update_option(self::RUNNER_HEALTH_OPTION, self::normalize_runner_health_snapshot($snapshot), false);
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private static function save_e2e_readiness_snapshot(array $snapshot): void
    {
        update_option(self::E2E_READINESS_OPTION, self::normalize_e2e_readiness_snapshot($snapshot), false);
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array{checked_at:int,overall_status:string,checks:array<int,array<string,string>>}
     */
    private static function normalize_runner_health_snapshot(array $snapshot): array
    {
        $checks = [];
        foreach ((array) ($snapshot['checks'] ?? []) as $check) {
            if (!is_array($check)) {
                continue;
            }

            $status = sanitize_key((string) ($check['status'] ?? 'warning'));
            if (!in_array($status, ['ready', 'warning', 'blocked'], true)) {
                $status = 'warning';
            }

            $checks[] = [
                'key' => sanitize_key((string) ($check['key'] ?? 'check')),
                'label' => sanitize_text_field((string) ($check['label'] ?? 'Check')),
                'status' => $status,
                'message' => sanitize_text_field((string) ($check['message'] ?? '')),
                'detail' => sanitize_text_field((string) ($check['detail'] ?? '')),
            ];
        }

        $overall = sanitize_key((string) ($snapshot['overall_status'] ?? 'unknown'));
        if (!in_array($overall, ['ready', 'warning', 'blocked', 'unknown'], true)) {
            $overall = 'unknown';
        }

        if (!empty($checks)) {
            $overall = self::resolve_runner_health_overall_status($checks);
        }

        return [
            'checked_at' => max(0, (int) ($snapshot['checked_at'] ?? 0)),
            'overall_status' => $overall,
            'checks' => $checks,
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array{checked_at:int,overall_status:string,checks:array<int,array<string,string>>,suggestions:array<int,string>}
     */
    private static function normalize_e2e_readiness_snapshot(array $snapshot): array
    {
        $checks = [];
        foreach ((array) ($snapshot['checks'] ?? []) as $check) {
            if (!is_array($check)) {
                continue;
            }

            $status = sanitize_key((string) ($check['status'] ?? 'warning'));
            if (!in_array($status, ['ready', 'warning', 'blocked'], true)) {
                $status = 'warning';
            }

            $checks[] = [
                'key' => sanitize_key((string) ($check['key'] ?? 'check')),
                'label' => sanitize_text_field((string) ($check['label'] ?? 'Check')),
                'status' => $status,
                'message' => sanitize_text_field((string) ($check['message'] ?? '')),
                'detail' => sanitize_text_field((string) ($check['detail'] ?? '')),
                'url' => esc_url_raw((string) ($check['url'] ?? '')),
            ];
        }

        $suggestions = [];
        foreach ((array) ($snapshot['suggestions'] ?? []) as $suggestion) {
            if (!is_scalar($suggestion)) {
                continue;
            }
            $suggestion = sanitize_text_field((string) $suggestion);
            if ($suggestion !== '') {
                $suggestions[] = $suggestion;
            }
        }

        $overall = sanitize_key((string) ($snapshot['overall_status'] ?? 'unknown'));
        if (!in_array($overall, ['ready', 'warning', 'blocked', 'unknown'], true)) {
            $overall = 'unknown';
        }
        if (!empty($checks)) {
            $overall = self::resolve_runner_health_overall_status($checks);
        }

        return [
            'checked_at' => max(0, (int) ($snapshot['checked_at'] ?? 0)),
            'overall_status' => $overall,
            'checks' => $checks,
            'suggestions' => array_values(array_unique($suggestions)),
        ];
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    private static function resolve_runner_health_overall_status(array $checks): string
    {
        $has_warning = false;
        foreach ($checks as $check) {
            $status = sanitize_key((string) ($check['status'] ?? 'warning'));
            if ($status === 'blocked') {
                return 'blocked';
            }
            if ($status === 'warning') {
                $has_warning = true;
            }
        }

        return $has_warning ? 'warning' : 'ready';
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
        $runner_health = self::get_runner_health_snapshot();
        $e2e_readiness = self::get_e2e_readiness_snapshot();
        $unit_test_tabs = self::get_unit_test_tab_definitions();
        $unit_test_runners = self::get_unit_test_runner_definitions();
        $unit_test_inventory = self::get_unit_test_inventory();
        $active_unit_test_tab = self::normalize_unit_test_tab(isset($query['cbt_unit_test_tab']) ? $query['cbt_unit_test_tab'] : '');
        $active_checklist_scope = self::normalize_unit_test_scope(isset($query['cbt_checklist_scope']) ? $query['cbt_checklist_scope'] : '');
        $active_unit_test_panel = isset($unit_test_tabs[$active_unit_test_tab]) ? (array) $unit_test_tabs[$active_unit_test_tab] : [];
        $unit_test_run_token = isset($query['cbt_test_run_token']) ? sanitize_key(wp_unslash((string) $query['cbt_test_run_token'])) : '';
        $global_unit_run_token = isset($query['cbt_global_unit_run_token']) ? sanitize_key(wp_unslash((string) $query['cbt_global_unit_run_token'])) : '';
        $unit_test_run_result = null;
        $global_unit_run_result = null;
        $global_unit_run_state = null;
        self::maybe_start_next_flow_job();
        $flow_jobs = self::read_flow_check_jobs();
        $latest_flow_jobs = self::build_latest_flow_job_lookup($flow_jobs);
        $test_artifact_cleanup = self::build_test_artifact_cleanup_context($latest_flow_jobs);
        $flow_job_repair = self::build_flow_job_repair_context($flow_jobs);
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
            if ($global_unit_run_result === null) {
                $global_unit_run_state = self::get_global_unit_run_state($global_unit_run_token);
                if (is_array($global_unit_run_state)) {
                    $global_unit_run_result = self::build_global_unit_run_result_from_state($global_unit_run_state);
                }
            }
        }
        $global_unit_run_summary = self::build_global_unit_run_summary($global_unit_run_result);
        $unit_test_inventory_context = self::build_unit_test_inventory_context(
            $unit_test_inventory,
            is_array($unit_test_run_result) ? $unit_test_run_result : null,
            is_array($global_unit_run_result) ? $global_unit_run_result : null
        );
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
            'runner_health' => $runner_health,
            'e2e_readiness' => $e2e_readiness,
            'unit_test_tabs' => $unit_test_tabs,
            'active_unit_test_tab' => $active_unit_test_tab,
            'active_checklist_scope' => $active_checklist_scope,
            'active_unit_test_panel' => $active_unit_test_panel,
            'unit_test_run_result' => $unit_test_run_result,
            'global_unit_run_result' => $global_unit_run_result,
            'global_unit_run_state' => $global_unit_run_state,
            'global_unit_run_summary' => $global_unit_run_summary,
            'global_unit_run_available' => !empty($global_unit_run_result),
            'global_unit_run_active' => is_array($global_unit_run_state) && self::is_active_global_unit_run_state($global_unit_run_state),
            'global_unit_run_token' => $global_unit_run_token,
            'unit_test_inventory' => $unit_test_inventory_context['items'],
            'unit_test_inventory_summary' => $unit_test_inventory_context['summary'],
            'unit_test_area_count' => $unit_test_area_count,
            'unit_test_total_checklist_items' => $unit_test_total_checklist_items,
            'has_active_flow_jobs' => self::has_active_flow_jobs($latest_flow_jobs),
            'test_artifact_cleanup' => $test_artifact_cleanup,
            'flow_job_repair' => $flow_job_repair,
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
            'status' => 'idle',
            'processed_commands' => 0,
            'total_commands' => 0,
            'progress_percent' => 0,
            'current_label' => '',
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
        $summary['status'] = sanitize_key((string) ($result['status'] ?? 'completed'));
        if (!in_array($summary['status'], ['queued', 'running', 'completed', 'failed', 'idle'], true)) {
            $summary['status'] = 'completed';
        }
        $summary['processed_commands'] = max(0, (int) ($result['processed_commands'] ?? 0));
        $summary['total_commands'] = max(0, (int) ($result['total_commands'] ?? 0));
        $summary['current_label'] = sanitize_text_field((string) ($result['current_label'] ?? ''));
        $summary['progress_percent'] = $summary['total_commands'] > 0
            ? max(0, min(100, (int) round(($summary['processed_commands'] / $summary['total_commands']) * 100)))
            : ($summary['status'] === 'completed' ? 100 : 0);

        return $summary;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_global_unit_run_state(string $token): ?array
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::GLOBAL_UNIT_TEST_RUN_STATE_TRANSIENT_PREFIX . $token);
        if (!is_array($state) || (string) ($state['type'] ?? '') !== 'global_unit_tests_state') {
            return null;
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function is_active_global_unit_run_state(array $state): bool
    {
        $status = sanitize_key((string) ($state['status'] ?? 'queued'));
        return in_array($status, ['queued', 'running'], true)
            && (int) ($state['processed_commands'] ?? 0) < (int) ($state['total_commands'] ?? 0);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_global_unit_run_state(array $state): void
    {
        $token = sanitize_key((string) ($state['token'] ?? ''));
        if ($token === '') {
            return;
        }

        set_transient(self::GLOBAL_UNIT_TEST_RUN_STATE_TRANSIENT_PREFIX . $token, $state, self::GLOBAL_UNIT_TEST_RUN_STATE_TTL);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function build_global_unit_run_command_queue(): array
    {
        $inventory = self::get_unit_test_inventory();
        $queue = [];

        foreach ($inventory as $item) {
            if (!is_array($item) || empty($item['runnable'])) {
                continue;
            }

            $command = trim((string) ($item['command'] ?? ''));
            if ($command === '') {
                continue;
            }

            $queue[] = [
                'tab' => (string) ($item['run_tab'] ?? self::normalize_unit_test_tab((string) ($item['mapped_tab'] ?? ''))),
                'runner_label' => 'Run All Unit Tests',
                'runner_description' => 'Menjalankan seluruh file unit test yang ditemukan otomatis oleh CBT Test Hub.',
                'label' => (string) ($item['runner_label'] ?? 'Unit Test File'),
                'command' => $command,
                'inventory_file' => self::unit_test_inventory_file_meta($item),
                'mapping_status' => (string) ($item['mapping_status'] ?? 'auto_mapped'),
            ];
        }

        return $queue;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_unit_test_inventory(): array
    {
        $curated_map = self::build_curated_unit_test_file_map();
        $tab_definitions = self::get_unit_test_tab_definitions();
        $files = [];

        foreach (['tests/php/unit/*Test.php', 'tests/js/unit/*.test.js'] as $pattern) {
            $matched = glob(rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/' . $pattern);
            if (is_array($matched)) {
                $files = array_merge($files, $matched);
            }
        }

        $items = [];
        foreach (array_values(array_unique($files)) as $absolute_path) {
            $relative_path = self::normalize_unit_test_relative_path((string) $absolute_path);
            if ($relative_path === '') {
                continue;
            }

            $type = str_starts_with($relative_path, 'tests/js/unit/') ? 'js' : 'php';
            $basename = basename($relative_path);
            $curated = isset($curated_map[$relative_path]) && is_array($curated_map[$relative_path])
                ? (array) $curated_map[$relative_path]
                : [];
            $guess = empty($curated) ? self::guess_unit_test_inventory_area($relative_path, $basename) : [];
            $mapped_tab = !empty($curated)
                ? self::normalize_unit_test_tab((string) ($curated['tab'] ?? ''))
                : (string) ($guess['tab'] ?? 'general_unclassified');
            $run_tab = isset($tab_definitions[$mapped_tab]) ? $mapped_tab : 'recovery_persistence';
            $area_label = isset($tab_definitions[$mapped_tab]) && is_array($tab_definitions[$mapped_tab])
                ? (string) ($tab_definitions[$mapped_tab]['label'] ?? $mapped_tab)
                : (string) ($guess['label'] ?? 'General / Unclassified');
            $mapping_status = !empty($curated) ? 'curated' : 'auto_mapped';
            $runner_label = !empty($curated)
                ? (string) ($curated['runner_label'] ?? ($type === 'js' ? 'Vitest Unit File' : 'PHPUnit Unit File'))
                : ($type === 'js' ? 'Vitest ' . $basename : 'PHPUnit ' . preg_replace('/Test\.php$/', '', $basename));

            $items[] = [
                'id' => sanitize_key($type . '-' . substr(md5($relative_path), 0, 12)),
                'type' => $type,
                'path' => $relative_path,
                'basename' => $basename,
                'mapped_tab' => $mapped_tab,
                'mapped_tab_label' => $area_label,
                'run_tab' => $run_tab,
                'mapping_status' => $mapping_status,
                'mapping_status_label' => $mapping_status === 'curated' ? 'Curated' : 'Auto-mapped',
                'runner_label' => $runner_label,
                'curated_runner_label' => (string) ($curated['runner_label'] ?? ''),
                'command' => self::build_unit_test_inventory_command($relative_path, $type),
                'runnable' => true,
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $area = strcmp((string) ($left['mapped_tab_label'] ?? ''), (string) ($right['mapped_tab_label'] ?? ''));
            if ($area !== 0) {
                return $area;
            }

            return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        return $items;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_unit_test_inventory_item(string $id): ?array
    {
        $id = sanitize_key($id);
        if ($id === '') {
            return null;
        }

        foreach (self::get_unit_test_inventory() as $item) {
            if ((string) ($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function build_curated_unit_test_file_map(): array
    {
        $runner_definitions = self::get_unit_test_runner_definitions();
        $map = [];

        foreach ($runner_definitions as $tab_key => $runner_by_scope) {
            if (!is_array($runner_by_scope)) {
                continue;
            }

            $unit_runner = isset($runner_by_scope['unit_tests']) && is_array($runner_by_scope['unit_tests'])
                ? (array) $runner_by_scope['unit_tests']
                : [];
            foreach ((array) ($unit_runner['commands'] ?? []) as $command_definition) {
                if (!is_array($command_definition)) {
                    continue;
                }

                $command = (string) ($command_definition['command'] ?? '');
                $label = (string) ($command_definition['label'] ?? 'Unit Test Command');
                foreach (self::extract_unit_test_files_from_command($command) as $relative_path) {
                    if (!isset($map[$relative_path])) {
                        $map[$relative_path] = [
                            'tab' => (string) $tab_key,
                            'runner_label' => $label,
                            'command' => $command,
                        ];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @return string[]
     */
    private static function extract_unit_test_files_from_command(string $command): array
    {
        preg_match_all('/(?:^|\s)(tests\/(?:js\/unit|php\/unit)\/[A-Za-z0-9_.\/-]+\.(?:js|php))/', $command, $matches);

        return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    }

    private static function normalize_unit_test_relative_path(string $path): string
    {
        $normalized = wp_normalize_path($path);
        $root = rtrim(wp_normalize_path(CBT_EXAM_SYSTEM_PATH), '/') . '/';
        if (str_starts_with($normalized, $root)) {
            $normalized = substr($normalized, strlen($root));
        }

        if (!preg_match('#^tests/(?:php/unit/[^/]+Test\.php|js/unit/[^/]+\.test\.js)$#', $normalized)) {
            return '';
        }

        return $normalized;
    }

    private static function build_unit_test_inventory_command(string $relative_path, string $type): string
    {
        return $type === 'js'
            ? './node_modules/.bin/vitest run ' . $relative_path . ' --reporter=verbose'
            : 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never ' . $relative_path;
    }

    /**
     * @return array{tab:string,label:string}
     */
    private static function guess_unit_test_inventory_area(string $relative_path, string $basename): array
    {
        $needle = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', ' ', $relative_path . ' ' . $basename));
        $rules = [
            'submit_finalization' => ['finish exam', 'finalize', 'expired attempt', 'submit watchlist', 'submit flow', 'entry flow', 'durable answer', 'answer sync batch', 'confirm result'],
            'security_event_pipeline' => ['security event', 'security ingest', 'security live counter', 'security user agent', 'incident report', 'security roster', 'security must watch', 'security redis monitor', 'security context', 'security logging', 'security shortcut', 'fullscreen state', 'idle detection'],
            'admin_exam_management' => ['admin exams', 'admin question', 'admin tokens', 'admin analytics', 'admin cache', 'admin ui helper', 'exams service validation', 'analytics charts'],
            'attempt_runtime_envelope' => ['runtime attempt', 'runtime buffer', 'active attempt index', 'snapshot auto heal', 'adaptive load', 'rest attempt envelope', 'rest question delivery', 'rest session presence', 'rest live profile', 'raw json rest', 'exam runtime loader', 'exam session', 'exam stage', 'stage runtime', 'render cycle', 'sync lifecycle', 'readonly api', 'lazy math', 'exam start attempt snapshot'],
            'app_shell_bootstrap' => ['app bootstrap', 'app shell', 'app meta', 'app events', 'bootstrap student', 'browser storage', 'config security', 'service worker', 'frontend diagnostics', 'debug manager', 'format test', 'html test', 'calculator', 'legacy handoff', 'ui preferences', 'frontend service worker', 'vite asset'],
            'login_student_profile' => ['login auth snapshot', 'login rate limit', 'login readiness', 'student profile', 'student cohort', 'exam audience', 'exam cards', 'doubtful state', 'api client', 'heartbeat lost'],
            'developer_setup_tooling' => ['activator', 'deactivator', 'maintenance', 'load test', 'branding', 'setup branding', 'setup security', 'developer', 'dev server', 'build output', 'progress ui', 'subjects service', 'users service', 'users progress', 'subjects progress', 'questions progress', 'analytics progress', 'ordering question', 'flow job worker', 'test hub'],
            'security_log_observability' => ['security', 'native', 'incident', 'user agent', 'must watch', 'observability'],
            'supervisor_proctoring' => ['supervisor', 'proctor', 'roster', 'presence'],
            'update_health' => ['update', 'release', 'backup', 'health'],
            'cache_redis' => ['cache', 'redis', 'snapshot', 'pipeline', 'cohort', 'profile', 'metrics'],
            'exam_preflight_availability' => ['preflight', 'availability', 'gate', 'start attempt', 'audience', 'exam start'],
            'scoring_grading' => ['scoring', 'grading', 'essay ai'],
            'result_scoring' => ['result', 'report', 'finish', 'export', 'score'],
            'import_preview' => ['import', 'preview', 'authoring', 'question helper', 'questions helper', 'template'],
            'auth_session' => ['auth', 'login', 'token', 'session'],
            'timer_lifecycle' => ['timer', 'heartbeat', 'lifecycle', 'idle', 'fullscreen'],
            'question_runtime' => ['question', 'navigation', 'render', 'input', 'ordering', 'runtime'],
            'sync_rest' => ['sync', 'rest', 'submit', 'answer', 'api client', 'raw json'],
            'recovery_persistence' => ['recovery', 'persistence', 'durable', 'doubtful', 'storage'],
        ];

        $tab_definitions = self::get_unit_test_tab_definitions();
        foreach ($rules as $tab => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($needle, $keyword)) {
                    return [
                        'tab' => $tab,
                        'label' => isset($tab_definitions[$tab]) && is_array($tab_definitions[$tab])
                            ? (string) ($tab_definitions[$tab]['label'] ?? $tab)
                            : $tab,
                    ];
                }
            }
        }

        return [
            'tab' => 'general_unclassified',
            'label' => 'General / Unclassified',
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,string>
     */
    private static function unit_test_inventory_file_meta(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'type' => (string) ($item['type'] ?? ''),
            'path' => (string) ($item['path'] ?? ''),
            'basename' => (string) ($item['basename'] ?? ''),
            'mapped_tab' => (string) ($item['mapped_tab'] ?? ''),
            'mapped_tab_label' => (string) ($item['mapped_tab_label'] ?? ''),
            'mapping_status' => (string) ($item['mapping_status'] ?? ''),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $inventory
     * @return array{items:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    private static function build_unit_test_inventory_context(array $inventory, ?array $unit_test_run_result, ?array $global_unit_run_result): array
    {
        $items = [];
        $summary = [
            'total_count' => 0,
            'php_count' => 0,
            'js_count' => 0,
            'curated_count' => 0,
            'auto_mapped_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($inventory as $item) {
            if (!is_array($item)) {
                continue;
            }

            $summary['total_count']++;
            $type = (string) ($item['type'] ?? '');
            if ($type === 'php') {
                $summary['php_count']++;
            } elseif ($type === 'js') {
                $summary['js_count']++;
            }

            $mapping_status = (string) ($item['mapping_status'] ?? 'auto_mapped');
            if ($mapping_status === 'curated') {
                $summary['curated_count']++;
            } else {
                $summary['auto_mapped_count']++;
            }

            $run_results = self::resolve_unit_test_inventory_run_results($item, $unit_test_run_result, $global_unit_run_result);
            $failed_run_results = array_values(array_filter($run_results, static function ($run_result): bool {
                return is_array($run_result) && empty($run_result['success']);
            }));
            if (!empty($failed_run_results)) {
                $summary['failed_count']++;
            }

            $item['run_results'] = $run_results;
            $item['failed_run_results'] = $failed_run_results;
            $item['has_failed_run_results'] = !empty($failed_run_results);
            $item['mapping_status_tone'] = $mapping_status === 'curated' ? 'done' : 'planned';
            $item['type_label'] = $type === 'js' ? 'Vitest' : 'PHPUnit';
            $items[] = $item;
        }

        return [
            'items' => $items,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<int,array<string,mixed>>
     */
    private static function resolve_unit_test_inventory_run_results(array $item, ?array $unit_test_run_result, ?array $global_unit_run_result): array
    {
        $results = [];
        foreach ([$unit_test_run_result, $global_unit_run_result] as $result_payload) {
            if (!is_array($result_payload)) {
                continue;
            }

            foreach (self::flatten_unit_test_run_result_commands($result_payload) as $command_result) {
                if (self::unit_test_command_result_matches_inventory_item($command_result, $item)) {
                    $results[] = $command_result;
                }
            }
        }

        return $results;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function flatten_unit_test_run_result_commands(array $result_payload): array
    {
        $commands = [];
        foreach ((array) ($result_payload['commands'] ?? []) as $command_result) {
            if (is_array($command_result)) {
                $commands[] = $command_result;
            }
        }

        foreach ((array) ($result_payload['tabs'] ?? []) as $tab_result) {
            if (!is_array($tab_result)) {
                continue;
            }
            foreach ((array) ($tab_result['commands'] ?? []) as $command_result) {
                if (is_array($command_result)) {
                    $commands[] = $command_result;
                }
            }
        }

        return $commands;
    }

    /**
     * @param array<string,mixed> $command_result
     * @param array<string,mixed> $item
     */
    private static function unit_test_command_result_matches_inventory_item(array $command_result, array $item): bool
    {
        $path = (string) ($item['path'] ?? '');
        if ($path === '') {
            return false;
        }

        $inventory_file = isset($command_result['inventory_file']) && is_array($command_result['inventory_file'])
            ? (array) $command_result['inventory_file']
            : [];
        if ((string) ($inventory_file['path'] ?? '') === $path) {
            return true;
        }

        return in_array($path, self::extract_unit_test_files_from_command((string) ($command_result['command'] ?? '')), true);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_global_unit_run_result_from_state(array $state): array
    {
        return [
            'type' => 'global_unit_tests',
            'status' => sanitize_key((string) ($state['status'] ?? 'queued')),
            'success' => !empty($state['success']),
            'executed_at' => max(0, (int) ($state['finished_at'] ?? ($state['updated_at'] ?? 0))),
            'label' => 'Run All Unit Tests',
            'processed_commands' => max(0, (int) ($state['processed_commands'] ?? 0)),
            'total_commands' => max(0, (int) ($state['total_commands'] ?? 0)),
            'current_label' => sanitize_text_field((string) ($state['current_label'] ?? '')),
            'summary' => [
                'passed_count' => max(0, (int) ($state['passed_count'] ?? 0)),
                'failed_count' => max(0, (int) ($state['failed_count'] ?? 0)),
                'total_count' => max(0, (int) ($state['passed_count'] ?? 0)) + max(0, (int) ($state['failed_count'] ?? 0)),
            ],
            'tabs' => isset($state['tabs']) && is_array($state['tabs']) ? (array) $state['tabs'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_start_global_unit_run_action_result(array $post): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));

        if (!function_exists('proc_open')) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Runner test membutuhkan fungsi proc_open yang aktif di PHP.');
        }

        $queue = self::build_global_unit_run_command_queue();
        if (empty($queue)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Runner global unit test belum memiliki command yang bisa dijalankan.');
        }

        $token = strtolower((string) wp_generate_password(12, false, false));
        $now = time();
        $state = [
            'type' => 'global_unit_tests_state',
            'token' => $token,
            'status' => 'queued',
            'tab' => $tab,
            'scope' => $scope,
            'queue' => $queue,
            'tabs' => [],
            'success' => true,
            'passed_count' => 0,
            'failed_count' => 0,
            'processed_commands' => 0,
            'total_commands' => count($queue),
            'current_label' => '',
            'started_at' => $now,
            'updated_at' => $now,
        ];
        self::save_global_unit_run_state($state);

        return self::build_test_hub_action_result(
            $tab,
            $scope,
            'Run All Unit Tests dimulai bertahap. Test Hub akan memproses satu command per request agar tidak kena timeout Cloudflare.',
            '',
            ['cbt_global_unit_run_token' => $token]
        );
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_continue_global_unit_run_action_result(array $post): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $token = sanitize_key(self::read_action_scalar($post, 'cbt_global_unit_run_token'));

        if ($token === '') {
            return self::build_start_global_unit_run_action_result($post);
        }

        $state = self::get_global_unit_run_state($token);
        if (!is_array($state)) {
            $existing_result = get_transient(self::GLOBAL_UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $token);
            if (is_array($existing_result) && (string) ($existing_result['type'] ?? '') === 'global_unit_tests') {
                return self::build_test_hub_action_result($tab, $scope, 'Run All Unit Tests sudah selesai.', '', ['cbt_global_unit_run_token' => $token]);
            }

            return self::build_test_hub_action_result($tab, $scope, '', 'Token Run All Unit Tests sudah kedaluwarsa atau tidak ditemukan.');
        }

        $queue = isset($state['queue']) && is_array($state['queue']) ? array_values((array) $state['queue']) : [];
        $processed = max(0, (int) ($state['processed_commands'] ?? 0));
        $total = count($queue);
        if ($total <= 0 || $processed >= $total) {
            return self::finalize_global_unit_run_state($state, $tab, $scope, $token);
        }

        $command_definition = isset($queue[$processed]) && is_array($queue[$processed]) ? (array) $queue[$processed] : [];
        $command = (string) ($command_definition['command'] ?? '');
        $label = (string) ($command_definition['label'] ?? 'Test Command');
        $tab_key = self::normalize_unit_test_tab((string) ($command_definition['tab'] ?? $tab));
        $state['status'] = 'running';
        $state['current_label'] = $label;
        $state['updated_at'] = time();
        self::save_global_unit_run_state($state);

        $result = self::run_unit_test_command($label, $command, self::build_runner_environment());
        if (isset($command_definition['inventory_file']) && is_array($command_definition['inventory_file'])) {
            $result['inventory_file'] = (array) $command_definition['inventory_file'];
        }
        if (isset($command_definition['mapping_status'])) {
            $result['mapping_status'] = sanitize_key((string) $command_definition['mapping_status']);
        }
        $tabs = isset($state['tabs']) && is_array($state['tabs']) ? (array) $state['tabs'] : [];
        if (!isset($tabs[$tab_key]) || !is_array($tabs[$tab_key])) {
            $tabs[$tab_key] = [
                'tab' => $tab_key,
                'scope' => 'unit_tests',
                'label' => (string) ($command_definition['runner_label'] ?? 'Run Tests'),
                'description' => (string) ($command_definition['runner_description'] ?? ''),
                'success' => true,
                'commands' => [],
            ];
        }
        $tabs[$tab_key]['commands'][] = $result;
        if (empty($result['success'])) {
            $tabs[$tab_key]['success'] = false;
            $state['success'] = false;
        }

        $case_counts = isset($result['test_case_counts']) && is_array($result['test_case_counts'])
            ? (array) $result['test_case_counts']
            : [];
        $state['passed_count'] = max(0, (int) ($state['passed_count'] ?? 0)) + max(0, (int) ($case_counts['passed'] ?? 0));
        $state['failed_count'] = max(0, (int) ($state['failed_count'] ?? 0)) + max(0, (int) ($case_counts['failed'] ?? 0));
        $state['processed_commands'] = $processed + 1;
        $state['total_commands'] = $total;
        $state['tabs'] = $tabs;
        $state['updated_at'] = time();
        $state['current_label'] = $state['processed_commands'] >= $total ? '' : $label;

        if ((int) $state['processed_commands'] >= $total) {
            return self::finalize_global_unit_run_state($state, $tab, $scope, $token);
        }

        self::save_global_unit_run_state($state);
        return self::build_test_hub_action_result(
            $tab,
            $scope,
            sprintf('Run All Unit Tests berjalan: %d/%d command selesai.', (int) $state['processed_commands'], $total),
            '',
            ['cbt_global_unit_run_token' => $token]
        );
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function finalize_global_unit_run_state(array $state, string $tab, string $scope, string $token): array
    {
        $state['status'] = 'completed';
        $state['finished_at'] = time();
        $state['updated_at'] = time();
        $state['current_label'] = '';

        $result_payload = self::build_global_unit_run_result_from_state($state);
        $result_payload['status'] = 'completed';
        $result_payload['executed_at'] = (int) ($state['finished_at'] ?? time());
        set_transient(self::GLOBAL_UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $token, $result_payload, self::UNIT_TEST_RUN_RESULT_TTL);
        delete_transient(self::GLOBAL_UNIT_TEST_RUN_STATE_TRANSIENT_PREFIX . $token);

        $message = !empty($result_payload['success'])
            ? 'Semua runner unit test global berhasil dijalankan.'
            : '';
        $error = !empty($result_payload['success'])
            ? ''
            : 'Ada runner unit test yang gagal pada run global. Periksa ringkasan pass/fail dan output per area.';

        return self::build_test_hub_action_result($tab, $scope, $message, $error, ['cbt_global_unit_run_token' => $token]);
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
                        [
                            'label' => 'Playwright Recovery Object Map Restore',
                            'command' => 'node tests/e2e/run-new-question-types-flow.mjs --grep "Student runtime restores object-map answers for new question types"',
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
                            'label' => 'Vitest Answer Sync',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/answer-sync.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest UI Sync',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/attempt-ui-sync.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit REST Sync',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RestSyncValidationTest.php tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php tests/php/unit/ExamQuestionDeliverySnapshotTest.php',
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
                        [
                            'label' => 'PHPUnit Login Input Guard',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RestLoginInputValidationTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Login Snapshot Freshness',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/LoginSnapshotFreshnessServiceTest.php',
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
                            'command' => './node_modules/.bin/vitest run tests/js/unit/session-lifecycle.test.js tests/js/unit/session-heartbeat.test.js tests/js/unit/lifecycle.test.js --reporter=verbose',
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
                            'command' => './node_modules/.bin/vitest run tests/js/unit/question-render.test.js tests/js/unit/question-inputs.test.js tests/js/unit/question-state-manager.test.js tests/js/unit/question-navigation.test.js tests/js/unit/question-runtime-manager.test.js --reporter=verbose',
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
                        [
                            'label' => 'Playwright New Types Runtime Restore',
                            'command' => 'node tests/e2e/run-new-question-types-flow.mjs --grep "Student runtime restores object-map answers for new question types"',
                        ],
                    ],
                ],
            ],
            'result_scoring' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Result & Export',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Result & Export.',
                    'commands' => [
                        [
                            'label' => 'Vitest Result & Export',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/result-stage.test.js tests/js/unit/finish-flow.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Result Review',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/review-stage.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Result Payload',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ResultPayloadHelpersTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Result & Export',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ResultPayloadHelpersTest.php tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php tests/php/unit/AdminResultsHelperObjectMapProgressTest.php tests/php/unit/AdminReportExamRowsTest.php',
                        ],
                    ],
                ],
                'smoke_tests' => [
                    'label' => 'Queue Checklist Flow Check Result & Export',
                    'description' => 'Mengantrekan skenario Playwright Result & Export ke background job secara granular per item checklist.',
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
                    'description' => 'Menjalankan suite PHP dan JS yang saat ini dipetakan ke Checklist Unit Test untuk Import & Preview.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Import & Preview',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/QuestionsImportPreviewTest.php tests/php/unit/QuestionsHelperPreviewRenderingTest.php tests/php/unit/QuestionsHelperShortAnswerTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Manual Compact Authoring',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminQuestionManualCompactAuthoringTest.php',
                        ],
                        [
                            'label' => 'Vitest Import & Preview',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/math-render.test.js tests/js/unit/math-authoring.test.js tests/js/unit/review-stage.test.js --reporter=verbose',
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
                            'label' => 'Playwright Import No Explanation V2',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: DOCX v2 without explanation still imports successfully"',
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
                            'label' => 'Playwright Import Equation Math Parity',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: DOCX equation multiple choice keeps the same math signature in admin preview, exam, and review"',
                        ],
                        [
                            'label' => 'Playwright Import Essay Equation Parity',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: DOCX equation essay rubric keeps the same math signature in admin preview and student review"',
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
                        [
                            'label' => 'Playwright Authoring Equation MC',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: manual equation multiple choice stays consistent in preview, exam, and review"',
                        ],
                        [
                            'label' => 'Playwright Authoring Equation Essay',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: manual equation essay rubric supports quicktags path and stays consistent in preview and review"',
                        ],
                        [
                            'label' => 'Playwright Authoring Equation TFM',
                            'command' => 'node tests/e2e/run-import-preview-flow.mjs --grep "Import Flow: manual TF matrix equation and quick template stay consistent in preview and review"',
                        ],
                        [
                            'label' => 'Playwright New Types Manual Authoring',
                            'command' => 'node tests/e2e/run-new-question-types-flow.mjs --grep "Admin manual authoring saves and reopens compact structured forms"',
                        ],
                        [
                            'label' => 'Playwright New Types DOCX Import',
                            'command' => 'node tests/e2e/run-new-question-types-flow.mjs --grep "DOCX import accepts all new structured question types"',
                        ],
                        [
                            'label' => 'Playwright New Types Template Controls',
                            'command' => 'node tests/e2e/run-new-question-types-flow.mjs --grep "Import template controls expose structured parameters for new types"',
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
                            'label' => 'Playwright Security Live Roster',
                            'command' => 'node tests/e2e/run-security-log-flow.mjs --grep "Security Flow: live roster shows active attempt grouped by exam kelas dan ruang"',
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
            'supervisor_proctoring' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Supervisor & Proctoring',
                    'description' => 'Menjalankan suite JS dan PHP yang saat ini dipetakan ke Checklist Unit Test untuk Supervisor & Proctoring.',
                    'commands' => [
                        [
                            'label' => 'Vitest Supervisor',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/supervisor-runtime.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Supervisor Dashboard',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/SupervisorDashboardServiceTest.php tests/php/unit/SupervisorRestRoutesTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Roster & Presence',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/LiveAttemptRosterIndexTest.php tests/php/unit/LiveProctoringPresenceTest.php',
                        ],
                    ],
                ],
            ],
            'exam_preflight_availability' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Exam Preflight & Availability',
                    'description' => 'Menjalankan suite PHP yang saat ini dipetakan ke Checklist Unit Test untuk Exam Preflight & Availability.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Preflight Service',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ExamPreflightServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Availability Snapshot',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ExamAvailabilitySnapshotTest.php tests/php/unit/ExamAvailabilityAutoWarmServiceTest.php tests/php/unit/RestExamAvailabilitySnapshotTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Gate & Start Attempt',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/StartAttemptGateServiceTest.php tests/php/unit/StartAttemptGateBucketTest.php tests/php/unit/StartAttemptOpeningStateServiceTest.php tests/php/unit/StartAttemptIdempotencyServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Start Attempt Metrics & Index',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/StartAttemptMetricsServiceTest.php tests/php/unit/RestStartAttemptActiveIndexTest.php',
                        ],
                    ],
                ],
            ],
            'scoring_grading' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Scoring & Grading',
                    'description' => 'Menjalankan suite PHP yang saat ini dipetakan ke Checklist Unit Test untuk Scoring & Grading Engine.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Scoring All Types',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ScoringAllQuestionTypesTest.php',
                        ],
                        [
                            'label' => 'PHPUnit AI Grading',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/EssayAIGradingServiceTest.php tests/php/unit/AdminResultsBulkEssayGradingTest.php tests/php/unit/AdminResultsBulkJobServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Submission Context',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/QuestionSubmissionContextSnapshotTest.php tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php tests/php/unit/QuestionsSyncObjectMapAnswerTest.php',
                        ],
                    ],
                ],
            ],
            'update_health' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Update & Health',
                    'description' => 'Menjalankan suite PHP yang saat ini dipetakan ke Checklist Unit Test untuk Plugin Update & Health.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Update Job',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/UpdateJobServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Update Backup',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/UpdateBackupServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Update Health',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/UpdateHealthServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Release Helper',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/UpdateReleaseHelperTest.php',
                        ],
                    ],
                ],
            ],
            'cache_redis' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Cache & Redis',
                    'description' => 'Menjalankan suite PHP yang saat ini dipetakan ke Checklist Unit Test untuk Cache & Redis Infrastructure.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Cache Namespace',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/CacheNamespaceConsistencyTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Redis Pipeline',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RedisPipelineHelperTest.php tests/php/unit/PluginRedisResetServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Attempt Cache',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AttemptQuestionContractCacheTest.php tests/php/unit/AttemptRuntimeSnapshotCacheTest.php tests/php/unit/AttemptRuntimeSnapshotServiceTest.php',
                        ],
                    ],
                ],
            ],
            'submit_finalization' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Submit & Finalization',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk Submit & Finalization Flow.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Finish Idempotency',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/FinishExamIdempotencyAndRecoveryTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Expired Finalize',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ExpiredAttemptFinalizeServiceTest.php tests/php/unit/AdminResultsExpiredAutoFinalizeTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Submit Watchlist & Presence',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminResultsSubmitWatchlistTest.php tests/php/unit/AdminResultsPresenceMonitoringTest.php tests/php/unit/AdminResultsResetRuntimeCleanupTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Flow Metrics',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/SubmitFlowMetricsServiceTest.php tests/php/unit/RestSubmitFlowMetricTest.php tests/php/unit/EntryFlowMetricsServiceTest.php tests/php/unit/RestEntryFlowMetricTest.php',
                        ],
                        [
                            'label' => 'Vitest Finish Flow',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/finish-flow.test.js tests/js/unit/finish-flow-recovery.test.js tests/js/unit/confirm-result-runtime.test.js tests/js/unit/result-stage.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Durable Answer Queue',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/durable-answer-queue.test.js tests/js/unit/answer-sync-batch.test.js --reporter=verbose',
                        ],
                    ],
                ],
            ],
            'security_event_pipeline' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Security Event Pipeline',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk Security Event Pipeline.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Security Ingest',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/SecurityEventIngestTest.php tests/php/unit/SecurityEventIngestFlushTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Security Counters & Guard',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/SecurityLiveCountersTest.php tests/php/unit/SecurityUserAgentGuardTest.php tests/php/unit/AuthUserAgentGuardTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Auth Answer Sync & Incident',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AuthAnswerSyncTokenTest.php tests/php/unit/IncidentReportTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Security Admin Render',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminSecurityObservabilityRestTest.php tests/php/unit/AdminSecurityLiveRosterRenderTest.php tests/php/unit/AdminSecurityMustWatchRenderTest.php tests/php/unit/AdminSecurityServiceLiveRosterTest.php tests/php/unit/AdminSecurityRedisMonitorRenderTest.php',
                        ],
                        [
                            'label' => 'Vitest Security Frontend',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/security-context.test.js tests/js/unit/security-logging.test.js tests/js/unit/exam-security.test.js tests/js/unit/app-events-security-shortcuts.test.js tests/js/unit/fullscreen-state.test.js tests/js/unit/idle-detection.test.js --reporter=verbose',
                        ],
                    ],
                ],
            ],
            'admin_exam_management' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Admin Exam Management',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk Admin Exam Management.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Exams CRUD & Helper',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ExamsServiceValidationTest.php tests/php/unit/AdminExamsHelperTest.php tests/php/unit/AdminExamsQuestionPrintContextTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Exams Snapshot',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminExamsSnapshotContextTest.php tests/php/unit/AdminExamsSnapshotActionsTest.php tests/php/unit/AdminExamsSnapshotRenderTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Question Guards & Token',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminQuestionDeleteGuardTest.php tests/php/unit/AdminTokensServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Analytics & Cache Admin',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminAnalyticsInsightDisplayTest.php tests/php/unit/AdminCacheServiceTest.php tests/php/unit/AdminCacheLoginSnapshotActionsTest.php tests/php/unit/AdminUiHelperRenderTest.php',
                        ],
                        [
                            'label' => 'Vitest Analytics Charts',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/admin-analytics-charts.test.js --reporter=verbose',
                        ],
                    ],
                ],
            ],
            'attempt_runtime_envelope' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Attempt Runtime & Envelope',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk Attempt Runtime & Envelope.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Runtime Envelope & Buffer',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RuntimeAttemptEnvelopeTest.php tests/php/unit/RuntimeBufferFlushIntegrityTest.php tests/php/unit/RawJsonRestResponseTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Active Attempt Index',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ActiveAttemptIndexTest.php tests/php/unit/ActiveAttemptIndexStaleTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Snapshot & Load',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/SnapshotAutoHealQueueServiceTest.php tests/php/unit/AdaptiveLoadServiceTest.php tests/php/unit/ExamStartAttemptSnapshotTest.php',
                        ],
                        [
                            'label' => 'PHPUnit REST Envelope & Delivery',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/RestAttemptEnvelopeLivePathTest.php tests/php/unit/RestQuestionDeliverySnapshotTest.php tests/php/unit/RestSessionPresenceSnapshotTest.php tests/php/unit/RestLiveProfileSnapshotTest.php',
                        ],
                        [
                            'label' => 'Vitest Runtime Loader & Stage',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/exam-runtime-loader.test.js tests/js/unit/exam-session.test.js tests/js/unit/exam-stage.test.js tests/js/unit/stage-runtime-manager.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Render & Sync Bridge',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/render-cycle.test.js tests/js/unit/sync-lifecycle-bridge.test.js tests/js/unit/readonly-api-cache.test.js tests/js/unit/lazy-math.test.js --reporter=verbose',
                        ],
                    ],
                ],
            ],
            'app_shell_bootstrap' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit App Shell & Bootstrap',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk App Shell & Bootstrap.',
                    'commands' => [
                        [
                            'label' => 'Vitest App Bootstrap & Shell',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/app-bootstrap.test.js tests/js/unit/app-shell.test.js tests/js/unit/app-meta.test.js tests/js/unit/bootstrap-student-shell.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest App Events & Auth Stages',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/app-events-rich-zoom.test.js tests/js/unit/app-events-session-recovery.test.js tests/js/unit/auth-stages.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Browser & Config',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/browser-storage.test.js tests/js/unit/config.test.js tests/js/unit/config-security.test.js tests/js/unit/ui-preferences.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Service Worker & Diagnostics',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/service-worker-registration.test.js tests/js/unit/frontend-diagnostics.test.js tests/js/unit/debug-manager.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'Vitest Utilities',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/format.test.js tests/js/unit/html.test.js tests/js/unit/calculator.test.js tests/js/unit/legacy-handoff.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Frontend & Vite',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/FrontendServiceWorkerTest.php tests/php/unit/ViteAssetManifestCssTest.php',
                        ],
                    ],
                ],
            ],
            'login_student_profile' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Login & Student Profile',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk Login & Student Profile.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Login Auth & Rate Limit',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/LoginAuthSnapshotCacheTest.php tests/php/unit/LoginRateLimitAndSessionTest.php tests/php/unit/LoginReadinessWarmQueueServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Student Profile & Cohort',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/StudentProfileSnapshotTest.php tests/php/unit/StudentCohortIndexServiceTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Exam Audience & Cards',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ExamAudienceEvaluationTest.php tests/php/unit/ExamAudienceServiceTest.php tests/php/unit/ExamCardsServiceTest.php tests/php/unit/ExamCardsProgressUiTest.php',
                        ],
                        [
                            'label' => 'Vitest Session & API Client',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/doubtful-state.test.js tests/js/unit/api-client.test.js tests/js/unit/session-heartbeat-lost.test.js --reporter=verbose',
                        ],
                    ],
                ],
            ],
            'developer_setup_tooling' => [
                'unit_tests' => [
                    'label' => 'Run Checklist Unit Developer & Setup Tooling',
                    'description' => 'Menjalankan suite PHP dan JS yang dipetakan ke Checklist Unit Test untuk Developer & Setup Tooling.',
                    'commands' => [
                        [
                            'label' => 'PHPUnit Plugin Lifecycle',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/ActivatorDeactivatorLifecycleTest.php tests/php/unit/DeactivatorTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Maintenance Tools',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/AdminMaintenanceCommonTest.php tests/php/unit/AdminMaintenanceContextBuilderTest.php tests/php/unit/AdminMaintenanceLoadTestCancelTest.php tests/php/unit/MaintenanceLoadTestStudentPoolTest.php tests/php/unit/MaintenanceModularizationTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Setup & Branding',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/BrandingRegionLabelTest.php tests/php/unit/SetupBrandingProgressUiTest.php tests/php/unit/SetupSecurityConfigTest.php tests/php/unit/SetupSecurityProgressUiTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Developer Tools',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/DeveloperBuildOutputAccessTest.php tests/php/unit/DeveloperDevServerStateTest.php tests/php/unit/DeveloperProgressUiTest.php',
                        ],
                        [
                            'label' => 'PHPUnit Admin Modules',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/OrderingQuestionTypeTest.php tests/php/unit/AnalyticsProgressUiTest.php tests/php/unit/QuestionsProgressUiTest.php tests/php/unit/SubjectsProgressUiTest.php tests/php/unit/SubjectsServiceValidationTest.php tests/php/unit/UsersProgressUiTest.php tests/php/unit/UsersServicePhotoImportTest.php tests/php/unit/UsersServiceValidationTest.php',
                        ],
                        [
                            'label' => 'Vitest Flow Job Worker',
                            'command' => './node_modules/.bin/vitest run tests/js/unit/flow-job-worker.test.js --reporter=verbose',
                        ],
                        [
                            'label' => 'PHPUnit Test Hub Meta',
                            'command' => 'vendor/bin/phpunit -c phpunit.xml.dist --testdox --colors=never tests/php/unit/TestHubServiceSafetyTest.php tests/php/unit/TestHubActionHandlersTest.php tests/php/unit/TestHubViewRenderTest.php tests/php/unit/TestHubArtifactCleanupTest.php',
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

        $tab = self::normalize_unit_test_tab(self::read_action_scalar($_POST, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($_POST, 'cbt_checklist_scope'));
        $item_index_requested = array_key_exists('cbt_checklist_item_index', $_POST);
        check_admin_referer('cbt_test_hub_runner_' . $tab);
        self::extend_test_hub_action_time_limit();

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

        $inventory_test_id = sanitize_key(self::read_action_scalar($_POST, 'cbt_inventory_test_id'));
        if ($inventory_test_id !== '') {
            $inventory_item = self::get_unit_test_inventory_item($inventory_test_id);
            if (!is_array($inventory_item) || empty($inventory_item['runnable'])) {
                self::redirect_test_hub_after_run($tab, null, '', 'File unit test inventory yang dipilih tidak valid atau sudah tidak tersedia.');
            }

            $result = self::run_unit_test_command(
                (string) ($inventory_item['runner_label'] ?? 'Unit Test File'),
                (string) ($inventory_item['command'] ?? ''),
                self::build_runner_environment()
            );
            $result['inventory_file'] = self::unit_test_inventory_file_meta($inventory_item);
            $result['mapping_status'] = (string) ($inventory_item['mapping_status'] ?? 'auto_mapped');
            $success = !empty($result['success']);
            $token = strtolower((string) wp_generate_password(12, false, false));
            $result_payload = [
                'tab' => (string) ($inventory_item['run_tab'] ?? $tab),
                'scope' => 'unit_tests',
                'item_index' => null,
                'item_label' => (string) ($inventory_item['basename'] ?? 'Unit Test File'),
                'inventory_file' => self::unit_test_inventory_file_meta($inventory_item),
                'label' => 'Run Unit Test File',
                'description' => 'Menjalankan satu file unit test dari Unit Test Inventory.',
                'success' => $success,
                'executed_at' => time(),
                'commands' => [$result],
            ];
            set_transient(self::UNIT_TEST_RUN_RESULT_TRANSIENT_PREFIX . $token, $result_payload, self::UNIT_TEST_RUN_RESULT_TTL);

            self::redirect_test_hub_after_run(
                (string) ($inventory_item['run_tab'] ?? $tab),
                $token,
                $success ? 'File unit test berhasil dijalankan: ' . (string) ($inventory_item['basename'] ?? '') : '',
                $success ? '' : 'File unit test gagal. Periksa output runner di panel Unit Test Inventory.',
                'unit_tests'
            );
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
        self::extend_test_hub_action_time_limit();

        $token = sanitize_key(self::read_action_scalar($_POST, 'cbt_global_unit_run_token'));
        $result = $token === ''
            ? self::build_start_global_unit_run_action_result($_POST)
            : self::build_continue_global_unit_run_action_result($_POST);

        self::redirect_test_hub_action_result($result);
    }

    public static function handle_queue_flow_check_job(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        $tab = self::normalize_unit_test_tab(self::read_action_scalar($_POST, 'cbt_unit_test_tab'));
        check_admin_referer('cbt_test_hub_runner_' . $tab);

        self::redirect_test_hub_action_result(self::build_queue_flow_check_action_result($_POST));
    }

    public static function handle_refresh_runner_health(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_refresh_test_hub_health');
        self::redirect_test_hub_action_result(self::build_refresh_runner_health_action_result($_POST));
    }

    public static function handle_check_e2e_readiness(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_check_test_hub_e2e_readiness');
        self::redirect_test_hub_action_result(self::build_check_e2e_readiness_action_result($_POST));
    }

    public static function handle_retry_flow_check_job(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_test_hub_flow_job_action');
        self::redirect_test_hub_action_result(self::build_retry_flow_check_job_action_result($_POST));
    }

    public static function handle_cancel_flow_check_job(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_test_hub_flow_job_action');
        self::redirect_test_hub_action_result(self::build_cancel_flow_check_job_action_result($_POST));
    }

    public static function handle_clear_flow_check_job(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_test_hub_flow_job_action');
        self::redirect_test_hub_action_result(self::build_clear_flow_check_job_action_result($_POST));
    }

    public static function handle_repair_stuck_flow_check_jobs(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_repair_stuck_flow_check_jobs');
        self::redirect_test_hub_action_result(self::build_repair_stuck_flow_check_jobs_action_result($_POST));
    }

    public static function handle_download_test_hub_artifact(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        $request = $_REQUEST;
        $job_id = sanitize_file_name(self::read_action_scalar($request, 'cbt_flow_job_id'));
        check_admin_referer('cbt_test_hub_artifact_' . $job_id);

        $result = self::build_download_test_hub_artifact_result($request);
        if (empty($result['success']) || empty($result['file_path']) || !is_string($result['file_path'])) {
            wp_die((string) ($result['error'] ?? 'Artifact Test Hub tidak ditemukan.'));
        }

        $file_path = (string) $result['file_path'];
        $download_name = (string) ($result['download_name'] ?? basename($file_path));
        $content_type = (string) ($result['content_type'] ?? 'application/octet-stream');

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"');
        header('Content-Length: ' . (string) filesize($file_path));
        readfile($file_path);
        exit;
    }

    public static function handle_save_settings(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_test_hub_settings');
        self::redirect_test_hub_action_result(self::build_save_settings_action_result($_POST));
    }

    public static function handle_clear_test_artifacts(): void
    {
        if (!self::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_test_artifacts');
        self::redirect_test_hub_action_result(self::build_clear_test_artifacts_action_result($_POST));
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_save_settings_action_result(array $post): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));

        $settings = self::sanitize_settings_input([
            'e2e_base_url' => self::read_action_scalar($post, 'e2e_base_url'),
            'e2e_frontend_url' => self::read_action_scalar($post, 'e2e_frontend_url'),
        ]);

        self::save_settings($settings);

        $result = self::build_test_hub_action_result($tab, $scope, 'Pengaturan Playwright E2E berhasil disimpan.', '');
        $result['settings'] = $settings;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_clear_test_artifacts_action_result(array $post): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $latest_flow_jobs = self::build_latest_flow_job_lookup(self::read_flow_check_jobs());

        if (self::has_active_flow_jobs($latest_flow_jobs)) {
            return self::build_test_hub_action_result(
                $tab,
                $scope,
                '',
                'Bersihkan artefak test diblokir sementara karena masih ada flow check background yang queued, running, atau cancelling.'
            );
        }

        $cleanup = self::build_test_artifact_cleanup_context($latest_flow_jobs);
        $targets = isset($cleanup['targets']) && is_array($cleanup['targets']) ? (array) $cleanup['targets'] : [];
        $deleted_labels = [];
        $failed_labels = [];

        foreach ($targets as $target) {
            if (!is_array($target) || empty($target['exists'])) {
                continue;
            }

            $target_paths = self::resolve_test_artifact_target_paths($target);
            $label = isset($target['label']) ? (string) $target['label'] : (isset($target_paths[0]) ? (string) $target_paths[0] : '');
            if (empty($target_paths)) {
                continue;
            }

            $target_success = true;
            foreach ($target_paths as $path) {
                if (!self::test_artifact_target_has_contents((string) $path)) {
                    continue;
                }

                if (!self::remove_test_artifact_path((string) $path)) {
                    $target_success = false;
                    break;
                }
            }

            if ($target_success) {
                $deleted_labels[] = $label;
            } else {
                $failed_labels[] = $label;
            }
        }

        if (empty($deleted_labels) && empty($failed_labels)) {
            return self::build_test_hub_action_result(
                $tab,
                $scope,
                'Belum ada artefak test yang perlu dibersihkan.',
                ''
            );
        }

        $message = '';
        $error = '';
        if (!empty($deleted_labels)) {
            $message = 'Artefak test berhasil dibersihkan: ' . implode(', ', $deleted_labels) . '.';
        }
        if (!empty($failed_labels)) {
            $error = 'Sebagian artefak test belum bisa dihapus: ' . implode(', ', $failed_labels) . '. Periksa permission folder atau file yang sedang dipakai proses lain.';
        }

        $result = self::build_test_hub_action_result($tab, $scope, $message, $error);
        $result['deleted_labels'] = $deleted_labels;
        $result['failed_labels'] = $failed_labels;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_refresh_runner_health_action_result(array $post, array $probe_overrides = []): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $snapshot = self::build_runner_health_snapshot(self::get_settings(), $probe_overrides);
        self::save_runner_health_snapshot($snapshot);

        $status = (string) ($snapshot['overall_status'] ?? 'unknown');
        $message = $status === 'ready'
            ? 'Runner Health siap. Semua check penting lolos.'
            : 'Runner Health diperbarui dengan status ' . strtoupper($status) . '.';

        $result = self::build_test_hub_action_result($tab, $scope, $message, '');
        $result['runner_health'] = $snapshot;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_check_e2e_readiness_action_result(array $post, array $probe_overrides = []): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $snapshot = self::build_e2e_readiness_snapshot(self::get_settings(), $probe_overrides);
        self::save_e2e_readiness_snapshot($snapshot);

        $status = (string) ($snapshot['overall_status'] ?? 'unknown');
        $message = $status === 'ready'
            ? 'E2E Readiness siap. URL, seed user, dan fixture utama lolos.'
            : 'E2E Readiness diperbarui dengan status ' . strtoupper($status) . '.';

        $result = self::build_test_hub_action_result($tab, $scope, $message, '');
        $result['e2e_readiness'] = $snapshot;

        return $result;
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $probe_overrides
     * @return array{checked_at:int,overall_status:string,checks:array<int,array<string,string>>,suggestions:array<int,string>}
     */
    private static function build_e2e_readiness_snapshot(array $settings, array $probe_overrides = []): array
    {
        $base_url = self::normalize_e2e_absolute_url((string) ($settings['e2e_base_url'] ?? ''));
        $frontend_url = self::normalize_e2e_absolute_url((string) ($settings['e2e_frontend_url'] ?? ''));
        $effective_frontend_url = $frontend_url !== '' ? $frontend_url : $base_url;
        $checks = [];

        $settings_status = 'ready';
        $settings_message = 'E2E Base URL dan Frontend URL sudah diset.';
        $settings_detail = 'Base: ' . ($base_url !== '' ? $base_url : '-') . ' | Frontend: ' . ($frontend_url !== '' ? $frontend_url : 'fallback ke Base URL');
        if ($base_url === '') {
            $settings_status = 'blocked';
            $settings_message = 'E2E Base URL belum diisi.';
            $settings_detail = 'Simpan E2E Base URL sebelum menjalankan Playwright E2E.';
        } elseif ($frontend_url === '') {
            $settings_status = 'warning';
            $settings_message = 'E2E Frontend URL kosong; frontend akan dicek memakai Base URL.';
        }
        $checks[] = self::e2e_readiness_check('test_hub_settings', 'Test Hub Settings', $settings_status, $settings_message, $settings_detail, $base_url);

        if ($base_url !== '') {
            $checks[] = self::build_e2e_marker_http_check(
                'wordpress_login',
                'WordPress Login',
                self::join_e2e_url($base_url, 'wp-login.php'),
                ['id="user_login"'],
                'WordPress login siap untuk Playwright.',
                'WordPress login belum siap. Pastikan E2E Base URL mengarah ke root WordPress.',
                $probe_overrides
            );
        } else {
            $checks[] = self::e2e_readiness_check('wordpress_login', 'WordPress Login', 'blocked', 'WordPress login belum bisa dicek karena Base URL kosong.', '', '');
        }

        if ($effective_frontend_url !== '') {
            $checks[] = self::build_e2e_marker_http_check(
                'cbt_frontend',
                'CBT Frontend',
                $effective_frontend_url,
                ['id="cbt-login-form"', 'id="cbt-exam-app"', 'CBTExamFrontendConfig'],
                'Frontend CBT siap untuk Playwright.',
                'Frontend CBT belum siap. Pastikan URL memuat shortcode/frontend CBT.',
                $probe_overrides
            );
        } else {
            $checks[] = self::e2e_readiness_check('cbt_frontend', 'CBT Frontend', 'blocked', 'Frontend CBT belum bisa dicek karena URL kosong.', '', '');
        }

        $checks[] = self::build_e2e_admin_seed_user_check($probe_overrides);
        $checks[] = self::build_e2e_fixture_catalog_check($probe_overrides);

        $suggestions = self::build_e2e_url_suggestions($base_url);

        return self::normalize_e2e_readiness_snapshot([
            'checked_at' => time(),
            'overall_status' => self::resolve_runner_health_overall_status($checks),
            'checks' => $checks,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $probe_overrides
     * @return array{checked_at:int,overall_status:string,checks:array<int,array<string,string>>}
     */
    private static function build_runner_health_snapshot(array $settings, array $probe_overrides = []): array
    {
        $checks = [];
        $proc_open_available = array_key_exists('proc_open_available', $probe_overrides)
            ? (bool) $probe_overrides['proc_open_available']
            : function_exists('proc_open');
        $checks[] = self::runner_health_check(
            'proc_open',
            'PHP proc_open',
            $proc_open_available ? 'ready' : 'blocked',
            $proc_open_available ? 'proc_open tersedia.' : 'proc_open tidak tersedia di PHP.',
            $proc_open_available ? '' : 'Runner command tidak bisa dimulai dari admin tanpa proc_open.'
        );

        $shell_result = self::runner_health_probe_shell_command('shell', 'command -v bash || command -v sh', $probe_overrides, $proc_open_available);
        $checks[] = self::runner_health_check(
            'shell',
            'Shell Runner',
            !empty($shell_result['success']) ? 'ready' : 'blocked',
            !empty($shell_result['success']) ? 'Shell tersedia.' : 'Shell runner tidak ditemukan.',
            trim((string) ($shell_result['stdout'] ?? $shell_result['stderr'] ?? ''))
        );

        $node_result = self::runner_health_probe_shell_command('node_version', 'node --version', $probe_overrides, $proc_open_available);
        $node_version = self::extract_semver_major((string) ($node_result['stdout'] ?? ''));
        $node_ready = !empty($node_result['success']) && $node_version >= 20;
        $checks[] = self::runner_health_check(
            'node',
            'Node.js >= 20',
            $node_ready ? 'ready' : 'blocked',
            $node_ready ? 'Node.js memenuhi minimum versi.' : 'Node.js tidak tersedia atau versinya di bawah 20.',
            trim((string) ($node_result['stdout'] ?? $node_result['stderr'] ?? ''))
        );

        $npm_result = self::runner_health_probe_shell_command('npm_version', 'npm --version', $probe_overrides, $proc_open_available);
        $checks[] = self::runner_health_check(
            'npm',
            'npm',
            !empty($npm_result['success']) ? 'ready' : 'warning',
            !empty($npm_result['success']) ? 'npm tersedia.' : 'npm tidak terdeteksi.',
            trim((string) ($npm_result['stdout'] ?? $npm_result['stderr'] ?? ''))
        );

        $playwright_installed = array_key_exists('playwright_installed', $probe_overrides)
            ? (bool) $probe_overrides['playwright_installed']
            : (is_dir(rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/node_modules/@playwright/test') || is_dir(rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/node_modules/playwright'));
        $checks[] = self::runner_health_check(
            'playwright_package',
            'Playwright Package',
            $playwright_installed ? 'ready' : 'blocked',
            $playwright_installed ? 'Package Playwright terpasang.' : 'Package Playwright belum ditemukan di node_modules.',
            $playwright_installed ? '' : 'Jalankan npm install sebelum flow-check.'
        );

        $browsers_path = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/.playwright-browsers';
        $chromium_installed = array_key_exists('chromium_installed', $probe_overrides)
            ? (bool) $probe_overrides['chromium_installed']
            : self::directory_has_contents($browsers_path);
        $checks[] = self::runner_health_check(
            'playwright_chromium',
            'Playwright Chromium',
            $chromium_installed ? 'ready' : 'warning',
            $chromium_installed ? 'Browser Playwright tersedia.' : 'Folder browser Playwright belum berisi Chromium.',
            $chromium_installed ? $browsers_path : 'Jalankan npm run playwright:install:chromium bila flow-check butuh browser lokal.'
        );

        $job_directory_ready = array_key_exists('job_directory_ready', $probe_overrides)
            ? (bool) $probe_overrides['job_directory_ready']
            : self::ensure_flow_job_directory();
        $checks[] = self::runner_health_check(
            'job_directory',
            'Job Directory',
            $job_directory_ready ? 'ready' : 'blocked',
            $job_directory_ready ? 'Direktori job writable.' : 'Direktori job tidak writable.',
            self::flow_job_directory_path()
        );

        $base_url = trim((string) ($settings['e2e_base_url'] ?? ''));
        $checks[] = self::build_runner_health_url_check('e2e_base_url', 'E2E Base URL', $base_url, true, $probe_overrides);
        $frontend_url = trim((string) ($settings['e2e_frontend_url'] ?? ''));
        if ($frontend_url !== '') {
            $checks[] = self::build_runner_health_url_check('e2e_frontend_url', 'E2E Frontend URL', $frontend_url, false, $probe_overrides);
        }

        return self::normalize_runner_health_snapshot([
            'checked_at' => time(),
            'overall_status' => self::resolve_runner_health_overall_status($checks),
            'checks' => $checks,
        ]);
    }

    /**
     * @return array<string,string>
     */
    private static function runner_health_check(string $key, string $label, string $status, string $message, string $detail = ''): array
    {
        $status = sanitize_key($status);
        if (!in_array($status, ['ready', 'warning', 'blocked'], true)) {
            $status = 'warning';
        }

        return [
            'key' => sanitize_key($key),
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function e2e_readiness_check(string $key, string $label, string $status, string $message, string $detail = '', string $url = ''): array
    {
        $status = sanitize_key($status);
        if (!in_array($status, ['ready', 'warning', 'blocked'], true)) {
            $status = 'warning';
        }

        return [
            'key' => sanitize_key($key),
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
            'url' => $url,
        ];
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array<string,string>
     */
    private static function build_e2e_marker_http_check(string $key, string $label, string $url, array $markers, string $ready_message, string $failed_message, array $probe_overrides): array
    {
        $marker_labels = [];
        foreach ($markers as $marker) {
            $marker = trim((string) $marker);
            if ($marker !== '') {
                $marker_labels[] = $marker;
            }
        }
        $marker_detail = implode(' / ', $marker_labels);
        $response = self::fetch_e2e_readiness_url($key, $url, $probe_overrides);
        if (!empty($response['error'])) {
            if ($key === 'cbt_frontend') {
                $fallback_check = self::build_e2e_frontend_local_fallback_check($url, (string) $response['error'], $probe_overrides);
                if ($fallback_check !== null) {
                    return $fallback_check;
                }
            }
            return self::e2e_readiness_check(
                $key,
                $label,
                'blocked',
                $label . ' tidak bisa diakses dari server.',
                (string) $response['error'],
                $url
            );
        }

        $code = (int) ($response['code'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $final_url = (string) ($response['final_url'] ?? $url);
        $has_marker = false;
        foreach ($marker_labels as $marker) {
            if (strpos($body, $marker) !== false) {
                $has_marker = true;
                break;
            }
        }
        $detail = 'Target: ' . $url . ' | Final: ' . ($final_url !== '' ? $final_url : '-') . ' | HTTP ' . (string) $code . ' | Marker: ' . $marker_detail;

        if ($code >= 200 && $code < 300 && $has_marker) {
            return self::e2e_readiness_check($key, $label, 'ready', $ready_message, $detail, $url);
        }

        $excerpt = self::excerpt_e2e_readiness_body($body);
        if ($excerpt !== '') {
            $detail .= ' | Body: ' . $excerpt;
        }

        if ($key === 'cbt_frontend') {
            $fallback_check = self::build_e2e_frontend_local_fallback_check($url, $detail, $probe_overrides);
            if ($fallback_check !== null) {
                return $fallback_check;
            }
        }

        return self::e2e_readiness_check(
            $key,
            $label,
            'blocked',
            $failed_message,
            $detail,
            $url
        );
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array<string,string>|null
     */
    private static function build_e2e_frontend_local_fallback_check(string $url, string $failed_detail, array $probe_overrides): ?array
    {
        if (array_key_exists('frontend_local_shortcode_detected', $probe_overrides)) {
            if (empty($probe_overrides['frontend_local_shortcode_detected'])) {
                return null;
            }

            $detail = trim($failed_detail);
            $detail .= ($detail !== '' ? ' | ' : '') . 'Local fallback: frontend CBT terdeteksi dari override test.';
            return self::e2e_readiness_check(
                'cbt_frontend',
                'CBT Frontend',
                'warning',
                'Frontend CBT terdeteksi dari konfigurasi WordPress, tetapi HTTP check public dari server belum stabil.',
                $detail,
                $url
            );
        }

        $fallback_detail = self::detect_local_e2e_frontend_page_detail($url);
        if ($fallback_detail === '') {
            return null;
        }

        $detail = trim($failed_detail);
        $detail .= ($detail !== '' ? ' | ' : '') . 'Local fallback: ' . $fallback_detail;

        return self::e2e_readiness_check(
            'cbt_frontend',
            'CBT Frontend',
            'warning',
            'Frontend CBT terdeteksi dari konfigurasi WordPress, tetapi HTTP check public dari server belum stabil.',
            $detail,
            $url
        );
    }

    private static function detect_local_e2e_frontend_page_detail(string $url): string
    {
        if (!self::is_same_site_e2e_url($url)) {
            return '';
        }

        $page_id = 0;
        if (function_exists('url_to_postid')) {
            $page_id = (int) url_to_postid($url);
        }
        if ($page_id <= 0 && self::is_home_e2e_url($url)) {
            $page_id = (int) get_option('page_on_front', 0);
        }

        $student_page_id = (int) get_option('cbt_exam_system_frontend_page_id', 0);
        $supervisor_page_id = (int) get_option('cbt_exam_system_supervisor_page_id', 0);
        if ($page_id <= 0) {
            if ($student_page_id > 0) {
                $page_id = $student_page_id;
            } elseif ($supervisor_page_id > 0) {
                $page_id = $supervisor_page_id;
            }
        }
        if ($page_id <= 0 || !function_exists('get_post_field')) {
            return '';
        }

        $content = (string) get_post_field('post_content', $page_id);
        $has_student_shortcode = function_exists('has_shortcode')
            ? has_shortcode($content, 'cbt_exam_frontend')
            : strpos($content, '[cbt_exam_frontend') !== false;
        $has_supervisor_shortcode = function_exists('has_shortcode')
            ? has_shortcode($content, 'cbt_exam_supervisor_frontend')
            : strpos($content, '[cbt_exam_supervisor_frontend') !== false;
        $is_configured_page = ($student_page_id > 0 && $page_id === $student_page_id)
            || ($supervisor_page_id > 0 && $page_id === $supervisor_page_id);

        if (!$has_student_shortcode && !$has_supervisor_shortcode && !$is_configured_page) {
            return '';
        }

        $reason = $has_supervisor_shortcode ? '[cbt_exam_supervisor_frontend]' : '[cbt_exam_frontend]';
        if (!$has_student_shortcode && !$has_supervisor_shortcode && $is_configured_page) {
            $reason = 'canonical frontend page option';
        }

        return 'page #' . (string) $page_id . ' memuat ' . $reason . '.';
    }

    private static function is_same_site_e2e_url(string $url): bool
    {
        $target_host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($target_host === '') {
            return false;
        }

        foreach ([home_url('/'), site_url('/')] as $site_url) {
            $site_host = strtolower((string) parse_url((string) $site_url, PHP_URL_HOST));
            if ($site_host !== '' && $site_host === $target_host) {
                return true;
            }
        }

        return false;
    }

    private static function is_home_e2e_url(string $url): bool
    {
        $target_path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        $home_path = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
        return $target_path === $home_path || ($target_path === '' && $home_path === '');
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array{code:int,body:string,final_url:string,error:string}
     */
    private static function fetch_e2e_readiness_url(string $key, string $url, array $probe_overrides): array
    {
        $override_key = $key . '_response';
        if (isset($probe_overrides[$override_key]) && is_array($probe_overrides[$override_key])) {
            $override = (array) $probe_overrides[$override_key];
            $body = $override['body'] ?? '';
            $final_url = $override['final_url'] ?? '';
            $error = $override['error'] ?? '';
            return [
                'code' => (int) ($override['code'] ?? 0),
                'body' => is_scalar($body) ? (string) $body : '',
                'final_url' => is_scalar($final_url) && (string) $final_url !== '' ? (string) $final_url : $url,
                'error' => is_scalar($error) ? (string) $error : '',
            ];
        }

        $remote = wp_remote_get($url, [
            'timeout' => self::E2E_READINESS_TIMEOUT,
            'redirection' => 3,
        ]);
        if (is_wp_error($remote)) {
            return [
                'code' => 0,
                'body' => '',
                'final_url' => $url,
                'error' => $remote->get_error_message(),
            ];
        }

        return [
            'code' => function_exists('wp_remote_retrieve_response_code') ? (int) wp_remote_retrieve_response_code($remote) : 0,
            'body' => function_exists('wp_remote_retrieve_body') ? (string) wp_remote_retrieve_body($remote) : '',
            'final_url' => self::extract_e2e_readiness_final_url(is_array($remote) ? $remote : [], $url),
            'error' => '',
        ];
    }

    /**
     * @param array<string,mixed> $remote
     */
    private static function extract_e2e_readiness_final_url(array $remote, string $fallback): string
    {
        if (isset($remote['url']) && is_scalar($remote['url']) && (string) $remote['url'] !== '') {
            return (string) $remote['url'];
        }

        if (isset($remote['filename']) && is_scalar($remote['filename']) && (string) $remote['filename'] !== '') {
            return $fallback;
        }

        return $fallback;
    }

    private static function excerpt_e2e_readiness_body(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($body)) ?? '');
        if ($text === '') {
            return '';
        }

        if (strlen($text) <= 260) {
            return $text;
        }

        return substr($text, 0, 257) . '...';
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array<string,string>
     */
    private static function build_e2e_admin_seed_user_check(array $probe_overrides): array
    {
        if (array_key_exists('admin_seed_user_exists', $probe_overrides)) {
            $exists = (bool) $probe_overrides['admin_seed_user_exists'];
            $username = is_scalar($probe_overrides['admin_seed_username'] ?? '') ? (string) $probe_overrides['admin_seed_username'] : 'admin_seed';
            return self::e2e_readiness_check(
                'admin_seed_user',
                'Admin Seed User',
                $exists ? 'ready' : 'blocked',
                $exists ? 'Admin seed user tersedia.' : 'Admin seed user belum ditemukan.',
                'Username: ' . $username,
                ''
            );
        }

        $username = '';
        if (class_exists('CBT_Admin_Maintenance_Service') && method_exists('CBT_Admin_Maintenance_Service', 'get_seed_special_admin_username')) {
            $username = (string) CBT_Admin_Maintenance_Service::get_seed_special_admin_username();
        } elseif (class_exists('CBT_Admin_Maintenance_Seed_Service') && method_exists('CBT_Admin_Maintenance_Seed_Service', 'get_seed_special_admin_username')) {
            $username = (string) CBT_Admin_Maintenance_Seed_Service::get_seed_special_admin_username();
        }

        $user = $username !== '' && function_exists('get_user_by') ? get_user_by('login', $username) : false;

        return self::e2e_readiness_check(
            'admin_seed_user',
            'Admin Seed User',
            $user ? 'ready' : 'blocked',
            $user ? 'Admin seed user tersedia.' : 'Admin seed user belum ditemukan.',
            $username !== '' ? 'Username: ' . $username : 'Maintenance seed service belum tersedia.',
            ''
        );
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array<string,string>
     */
    private static function build_e2e_fixture_catalog_check(array $probe_overrides): array
    {
        $required_users = ['primary_student', 'admin_seed'];
        $required_fixtures = ['import_preview', 'question_runtime', 'result_full', 'result_essay', 'result_restricted'];
        if (isset($probe_overrides['fixture_catalog']) && is_array($probe_overrides['fixture_catalog'])) {
            $catalog = (array) $probe_overrides['fixture_catalog'];
            $users = isset($catalog['users']) && is_array($catalog['users']) ? (array) $catalog['users'] : [];
            $fixtures = isset($catalog['fixtures']) && is_array($catalog['fixtures']) ? (array) $catalog['fixtures'] : [];
            $missing_users = array_values(array_filter($required_users, static fn(string $key): bool => empty($users[$key])));
            $missing_fixtures = array_values(array_filter($required_fixtures, static fn(string $key): bool => empty($fixtures[$key])));
        } else {
            $missing_users = self::missing_e2e_fixture_users($required_users);
            $missing_fixtures = self::missing_e2e_fixture_exams($required_fixtures);
        }

        if (empty($missing_users) && empty($missing_fixtures)) {
            return self::e2e_readiness_check(
                'fixture_catalog',
                'Fixture Catalog',
                'ready',
                'Fixture catalog utama tersedia.',
                'Required users: ' . implode(', ', $required_users) . ' | Required fixtures: ' . implode(', ', $required_fixtures),
                ''
            );
        }

        return self::e2e_readiness_check(
            'fixture_catalog',
            'Fixture Catalog',
            'blocked',
            'Fixture catalog belum lengkap. Jalankan CBT Maintenance > Generate Data Uji.',
            'Missing users: ' . (empty($missing_users) ? '-' : implode(', ', $missing_users)) . ' | Missing fixtures: ' . (empty($missing_fixtures) ? '-' : implode(', ', $missing_fixtures)),
            ''
        );
    }

    /**
     * @param array<int,string> $required_users
     * @return array<int,string>
     */
    private static function missing_e2e_fixture_users(array $required_users): array
    {
        $missing = [];
        foreach ($required_users as $user_key) {
            $username = '';
            if ($user_key === 'admin_seed') {
                if (class_exists('CBT_Admin_Maintenance_Service') && method_exists('CBT_Admin_Maintenance_Service', 'get_seed_special_admin_username')) {
                    $username = (string) CBT_Admin_Maintenance_Service::get_seed_special_admin_username();
                }
            } elseif ($user_key === 'primary_student') {
                if (class_exists('CBT_Admin_Maintenance_Service') && method_exists('CBT_Admin_Maintenance_Service', 'get_seed_special_student_username')) {
                    $username = (string) CBT_Admin_Maintenance_Service::get_seed_special_student_username();
                }
            }

            if ($username === '' || !function_exists('get_user_by') || !get_user_by('login', $username)) {
                $missing[] = $user_key;
            }
        }

        return $missing;
    }

    /**
     * @param array<int,string> $fixture_keys
     * @return array<int,string>
     */
    private static function missing_e2e_fixture_exams(array $fixture_keys): array
    {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return $fixture_keys;
        }

        $missing = [];
        foreach ($fixture_keys as $fixture_key) {
            $title = '';
            if (class_exists('CBT_Admin_Maintenance_Service') && method_exists('CBT_Admin_Maintenance_Service', 'get_seed_fixture_exam_title')) {
                $title = (string) CBT_Admin_Maintenance_Service::get_seed_fixture_exam_title($fixture_key);
            }
            if ($title === '') {
                $missing[] = $fixture_key;
                continue;
            }

            $table = $wpdb->prefix . 'cbt_exams';
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE title = %s", $title));
            if ($count <= 0) {
                $missing[] = $fixture_key;
            }
        }

        return $missing;
    }

    /**
     * @return array<int,string>
     */
    private static function build_e2e_url_suggestions(string $base_url): array
    {
        $suggestions = [];
        foreach ([home_url('/'), site_url('/'), 'http://localhost', 'http://127.0.0.1'] as $candidate) {
            $candidate = self::normalize_e2e_absolute_url((string) $candidate);
            if ($candidate !== '') {
                $suggestions[] = 'Coba E2E Base URL: ' . $candidate;
            }
        }

        $parent = self::parent_e2e_base_url($base_url);
        if ($parent !== '') {
            $suggestions[] = 'Jika URL sekarang 404, coba parent path: ' . $parent;
        }

        return array_values(array_unique($suggestions));
    }

    private static function normalize_e2e_absolute_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $url = esc_url_raw($url);
        if (!is_string($url) || $url === '') {
            return '';
        }

        return rtrim($url, '/');
    }

    private static function join_e2e_url(string $base_url, string $path): string
    {
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }

    private static function parent_e2e_base_url(string $base_url): string
    {
        $base_url = self::normalize_e2e_absolute_url($base_url);
        if ($base_url === '') {
            return '';
        }

        $parts = wp_parse_url($base_url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        array_pop($segments);
        $parent_path = implode('/', $segments);
        $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
        $parent = (string) $parts['scheme'] . '://' . (string) $parts['host'] . $port;
        if ($parent_path !== '') {
            $parent .= '/' . $parent_path;
        }

        return self::normalize_e2e_absolute_url($parent);
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array{success:bool,stdout:string,stderr:string,exit_code:int}
     */
    private static function runner_health_probe_shell_command(string $key, string $command, array $probe_overrides, bool $proc_open_available): array
    {
        if (isset($probe_overrides[$key]) && is_array($probe_overrides[$key])) {
            $override = (array) $probe_overrides[$key];
            return [
                'success' => !empty($override['success']),
                'stdout' => is_scalar($override['stdout'] ?? '') ? (string) $override['stdout'] : '',
                'stderr' => is_scalar($override['stderr'] ?? '') ? (string) $override['stderr'] : '',
                'exit_code' => (int) ($override['exit_code'] ?? (!empty($override['success']) ? 0 : 1)),
            ];
        }

        if (!$proc_open_available) {
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'proc_open tidak tersedia.',
                'exit_code' => 1,
            ];
        }

        return self::run_test_hub_shell_command($command);
    }

    /**
     * @return array{success:bool,stdout:string,stderr:string,exit_code:int}
     */
    private static function run_test_hub_shell_command(string $command): array
    {
        $wrapped_command = '/bin/bash -lc ' . escapeshellarg($command);
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($wrapped_command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH, self::build_runner_environment());
        if (!is_resource($process)) {
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => 'Gagal memulai shell command.',
                'exit_code' => 1,
            ];
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = (int) proc_close($process);

        return [
            'success' => $exit_code === 0,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exit_code,
        ];
    }

    private static function extract_semver_major(string $version): int
    {
        if (preg_match('/(\d+)/', $version, $matches)) {
            return max(0, (int) $matches[1]);
        }

        return 0;
    }

    private static function directory_has_contents(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
        return $iterator->valid();
    }

    /**
     * @param array<string,mixed> $probe_overrides
     * @return array<string,string>
     */
    private static function build_runner_health_url_check(string $key, string $label, string $url, bool $required, array $probe_overrides): array
    {
        if ($url === '') {
            return self::runner_health_check(
                $key,
                $label,
                $required ? 'blocked' : 'warning',
                $required ? $label . ' belum diisi.' : $label . ' kosong dan akan dilewati.',
                ''
            );
        }

        $override_key = $key . '_response';
        if (isset($probe_overrides[$override_key]) && is_array($probe_overrides[$override_key])) {
            $response = (array) $probe_overrides[$override_key];
            $code = (int) ($response['code'] ?? 0);
            $error = is_scalar($response['error'] ?? '') ? (string) $response['error'] : '';
        } else {
            $remote = wp_remote_get($url, [
                'timeout' => self::E2E_READINESS_TIMEOUT,
                'redirection' => 2,
            ]);
            if (is_wp_error($remote)) {
                $code = 0;
                $error = $remote->get_error_message();
            } else {
                $code = function_exists('wp_remote_retrieve_response_code') ? (int) wp_remote_retrieve_response_code($remote) : 0;
                $error = '';
            }
        }

        if ($error !== '') {
            return self::runner_health_check($key, $label, 'warning', $label . ' belum bisa diakses dari server.', $error);
        }

        if ($code >= 200 && $code < 500) {
            return self::runner_health_check($key, $label, 'ready', $label . ' merespons HTTP ' . $code . '.', $url);
        }

        return self::runner_health_check($key, $label, 'warning', $label . ' merespons tidak ideal.', $url . ' HTTP ' . $code);
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_queue_flow_check_action_result(array $post, bool $start_worker = true): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $item_index_requested = array_key_exists('cbt_checklist_item_index', $post);

        if (!function_exists('proc_open')) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Flow check membutuhkan fungsi proc_open yang aktif di PHP.');
        }

        if ($scope !== 'smoke_tests') {
            return self::build_test_hub_action_result($tab, $scope, '', 'Queue async hanya tersedia untuk Checklist Flow Check.');
        }

        $runners = self::get_unit_test_runner_definitions();
        $runner = isset($runners[$tab][$scope]) && is_array($runners[$tab][$scope]) ? (array) $runners[$tab][$scope] : [];
        $commands = isset($runner['commands']) && is_array($runner['commands']) ? (array) $runner['commands'] : [];
        if (empty($runner) || empty($commands)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Runner flow check untuk checklist ini belum tersedia.');
        }

        $checklist_items = self::get_unit_test_checklist_items($tab, $scope);
        $latest_jobs = self::build_latest_flow_job_lookup(self::read_flow_check_jobs());
        $has_running_jobs = self::has_running_flow_jobs_only($latest_jobs);
        $requested_item_index = null;
        if ($item_index_requested) {
            $requested_item_index = self::normalize_unit_test_item_index(
                self::read_action_scalar($post, 'cbt_checklist_item_index'),
                $tab,
                $scope
            );
            if ($requested_item_index === null) {
                return self::build_test_hub_action_result($tab, $scope, '', 'Task flow check yang dipilih tidak valid atau sudah tidak tersedia.');
            }
        }

        $queued_count = 0;
        $skipped_labels = [];
        $storage_failed_labels = [];
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
            if (!empty($latest_job) && self::is_active_flow_job_status((string) ($latest_job['status'] ?? ''))) {
                if ($item_label !== '') {
                    $skipped_labels[] = $item_label;
                }
                continue;
            }

            $job = self::create_flow_check_job($tab, $scope, (int) $index, $item_definition, $item_commands);
            if (!self::write_flow_check_job($job)) {
                if ($item_label !== '') {
                    $storage_failed_labels[] = $item_label;
                }
                continue;
            }
            $queued_job_ids[] = (string) ($job['job_id'] ?? '');
            $queued_count += 1;
            $latest_jobs[self::flow_job_lookup_key($tab, $scope, (int) $index)] = $job;
        }

        if ($queued_count <= 0) {
            if (!empty($storage_failed_labels)) {
                $result = self::build_test_hub_action_result(
                    $tab,
                    $scope,
                    '',
                    'Task flow check gagal disimpan ke storage background. Periksa permission direktori write untuk runner web: ' . implode(', ', $storage_failed_labels)
                );
                $result['storage_failed_labels'] = $storage_failed_labels;
                return $result;
            }

            $error = !empty($skipped_labels)
                ? 'Task flow check sudah sedang berjalan atau belum punya runner: ' . implode(', ', $skipped_labels)
                : 'Tidak ada task flow check yang bisa diantrikan.';
            $result = self::build_test_hub_action_result($tab, $scope, '', $error);
            $result['skipped_labels'] = $skipped_labels;
            return $result;
        }

        $worker_start_failed = false;
        if ($start_worker && !$has_running_jobs) {
            $next_job_id = self::resolve_next_queued_flow_job_id(self::read_flow_check_jobs());
            $worker_start_failed = $next_job_id === '' || !self::start_flow_check_job_process($next_job_id);
        }

        if ($worker_start_failed) {
            $result = self::build_test_hub_action_result(
                $tab,
                $scope,
                '',
                'Flow check tersimpan, tetapi worker background gagal dimulai. Periksa PATH Node.js dan permission direktori runner web.'
            );
            $result['queued_count'] = $queued_count;
            $result['queued_job_ids'] = $queued_job_ids;
            $result['skipped_labels'] = $skipped_labels;
            $result['storage_failed_labels'] = $storage_failed_labels;
            $result['worker_start_failed'] = true;
            return $result;
        }

        $message = $queued_count === 1
            ? 'Flow check berhasil diantrikan di background.'
            : $queued_count . ' task flow check berhasil diantrikan di background.';
        if (!empty($skipped_labels)) {
            $message .= ' Beberapa task dilewati karena masih queued/running/cancelling: ' . implode(', ', $skipped_labels) . '.';
        }
        if (!empty($storage_failed_labels)) {
            $message .= ' Beberapa task gagal disimpan ke storage background: ' . implode(', ', $storage_failed_labels) . '.';
        }

        $result = self::build_test_hub_action_result($tab, $scope, $message, '');
        $result['queued_count'] = $queued_count;
        $result['queued_job_ids'] = $queued_job_ids;
        $result['skipped_labels'] = $skipped_labels;
        $result['storage_failed_labels'] = $storage_failed_labels;
        $result['worker_start_failed'] = false;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_retry_flow_check_job_action_result(array $post, bool $start_worker = true): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $job_id = sanitize_text_field(self::read_action_scalar($post, 'cbt_flow_job_id'));
        $job = self::find_flow_check_job_by_id($job_id);
        if (empty($job)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Job flow check tidak ditemukan.');
        }

        $tab = self::normalize_unit_test_tab((string) ($job['tab'] ?? $tab));
        $scope = self::normalize_unit_test_scope((string) ($job['scope'] ?? $scope));
        $item_index = (int) ($job['item_index'] ?? -1);
        $status = self::normalize_flow_job_status((string) ($job['status'] ?? 'queued'));
        if (!self::is_terminal_flow_job_status($status)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Job flow check masih aktif dan belum bisa di-retry.');
        }

        $latest_jobs = self::build_latest_flow_job_lookup(self::read_flow_check_jobs());
        $latest_job = self::resolve_latest_flow_job_for_item($latest_jobs, $tab, $scope, $item_index);
        if (!empty($latest_job) && self::is_active_flow_job_status((string) ($latest_job['status'] ?? ''))) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Task flow check ini masih punya job aktif.');
        }

        $checklist_items = self::get_unit_test_checklist_items($tab, $scope);
        $item_definition = isset($checklist_items[$item_index]) && is_array($checklist_items[$item_index])
            ? (array) $checklist_items[$item_index]
            : [];
        if (empty($item_definition)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Definisi task flow check tidak ditemukan untuk retry.');
        }

        $runners = self::get_unit_test_runner_definitions();
        $runner = isset($runners[$tab][$scope]) && is_array($runners[$tab][$scope]) ? (array) $runners[$tab][$scope] : [];
        $commands = isset($runner['commands']) && is_array($runner['commands']) ? (array) $runner['commands'] : [];
        $item_commands = self::filter_runner_commands_for_item($commands, $item_definition);
        if (empty($item_commands)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Task flow check ini belum memiliki runner khusus untuk retry.');
        }

        $new_job = self::create_flow_check_job($tab, $scope, $item_index, $item_definition, $item_commands);
        $new_job['retry_of_job_id'] = $job_id;
        if (!self::write_flow_check_job($new_job)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Job retry gagal disimpan ke storage background.');
        }

        $running_jobs = self::build_latest_flow_job_lookup(self::read_flow_check_jobs());
        if ($start_worker && !self::has_running_flow_jobs_only($running_jobs)) {
            $next_job_id = self::resolve_next_queued_flow_job_id(self::read_flow_check_jobs());
            if ($next_job_id !== '') {
                self::start_flow_check_job_process($next_job_id);
            }
        }

        $result = self::build_test_hub_action_result($tab, $scope, 'Retry flow check berhasil diantrikan di background.', '');
        $result['job'] = $new_job;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_cancel_flow_check_job_action_result(array $post, bool $terminate_processes = true): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $job_id = sanitize_text_field(self::read_action_scalar($post, 'cbt_flow_job_id'));
        $job = self::find_flow_check_job_by_id($job_id);
        if (empty($job)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Job flow check tidak ditemukan.');
        }

        $tab = self::normalize_unit_test_tab((string) ($job['tab'] ?? $tab));
        $scope = self::normalize_unit_test_scope((string) ($job['scope'] ?? $scope));
        $status = self::normalize_flow_job_status((string) ($job['status'] ?? 'queued'));
        if (!in_array($status, ['queued', 'running', 'cancelling'], true)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Job flow check sudah selesai dan tidak perlu dibatalkan.');
        }

        $now = time();
        $job['cancel_requested_at'] = max($now, (int) ($job['cancel_requested_at'] ?? 0));
        $job['failure_kind'] = 'cancelled';
        $job['failure_summary'] = 'Job flow check dibatalkan dari CBT Test Hub.';

        if ($status === 'queued') {
            $job['status'] = 'cancelled';
            $job['finished_at'] = $now;
            $job['exit_code'] = 1;
            self::write_flow_check_job($job);

            $result = self::build_test_hub_action_result($tab, $scope, 'Job flow check queued berhasil dibatalkan.', '');
            $result['job'] = $job;

            return $result;
        }

        $pids = array_values(array_unique(array_filter([
            (int) ($job['active_child_pid'] ?? 0),
            (int) ($job['worker_pid'] ?? 0),
        ], static function (int $pid): bool {
            return $pid > 0;
        })));
        $has_running_process = false;
        $sent_signal = false;

        foreach ($pids as $pid) {
            $is_running = self::is_flow_job_process_running((int) $pid);
            $has_running_process = $has_running_process || $is_running;
            if ($terminate_processes && $is_running) {
                $sent_signal = self::terminate_flow_job_process((int) $pid) || $sent_signal;
            }
        }

        if (!$terminate_processes || $has_running_process || $sent_signal) {
            $job['status'] = 'cancelling';
            $job['exit_code'] = 1;
            self::write_flow_check_job($job);

            $result = self::build_test_hub_action_result($tab, $scope, 'Permintaan cancel flow check sudah dicatat.', '');
            $result['job'] = $job;

            return $result;
        }

        $job['status'] = 'cancelled';
        $job['finished_at'] = $now;
        $job['exit_code'] = 1;
        self::write_flow_check_job($job);

        $result = self::build_test_hub_action_result($tab, $scope, 'Job flow check berhasil dibatalkan.', '');
        $result['job'] = $job;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_clear_flow_check_job_action_result(array $post): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $job_id = sanitize_text_field(self::read_action_scalar($post, 'cbt_flow_job_id'));
        $job = self::find_flow_check_job_by_id($job_id);
        if (empty($job)) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Job flow check tidak ditemukan.');
        }

        $tab = self::normalize_unit_test_tab((string) ($job['tab'] ?? $tab));
        $scope = self::normalize_unit_test_scope((string) ($job['scope'] ?? $scope));
        $item_index = (int) ($job['item_index'] ?? -1);
        $jobs = self::read_flow_check_jobs();
        $deleted_count = 0;

        foreach ($jobs as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $candidate_matches = self::normalize_unit_test_tab((string) ($candidate['tab'] ?? '')) === $tab
                && self::normalize_unit_test_scope((string) ($candidate['scope'] ?? '')) === $scope
                && (int) ($candidate['item_index'] ?? -1) === $item_index;
            if (!$candidate_matches) {
                continue;
            }

            $candidate_status = self::normalize_flow_job_status((string) ($candidate['status'] ?? 'queued'));
            if (self::is_active_flow_job_status($candidate_status)) {
                return self::build_test_hub_action_result($tab, $scope, '', 'Job flow check masih aktif dan belum bisa dibersihkan.');
            }
        }

        foreach ($jobs as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $candidate_matches = self::normalize_unit_test_tab((string) ($candidate['tab'] ?? '')) === $tab
                && self::normalize_unit_test_scope((string) ($candidate['scope'] ?? '')) === $scope
                && (int) ($candidate['item_index'] ?? -1) === $item_index;
            if (!$candidate_matches || !self::is_terminal_flow_job_status((string) ($candidate['status'] ?? 'queued'))) {
                continue;
            }

            if (self::remove_flow_check_job_files($candidate)) {
                $deleted_count += 1;
            }
        }

        if ($deleted_count <= 0) {
            return self::build_test_hub_action_result($tab, $scope, '', 'Tidak ada job flow check terminal yang bisa dibersihkan.');
        }

        $result = self::build_test_hub_action_result($tab, $scope, $deleted_count . ' job flow check berhasil dibersihkan.', '');
        $result['deleted_count'] = $deleted_count;

        return $result;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function build_repair_stuck_flow_check_jobs_action_result(array $post): array
    {
        $tab = self::normalize_unit_test_tab(self::read_action_scalar($post, 'cbt_unit_test_tab'));
        $scope = self::normalize_unit_test_scope(self::read_action_scalar($post, 'cbt_checklist_scope'));
        $summary = self::repair_stuck_flow_check_jobs(self::read_flow_check_jobs(false));

        $repaired_count = (int) ($summary['repaired_count'] ?? 0);
        $still_active_count = (int) ($summary['still_active_count'] ?? 0);
        $terminal_count = (int) ($summary['terminal_count'] ?? 0);
        $message = sprintf(
            'Repair Stuck Jobs selesai: %d repaired, %d still active, %d terminal.',
            $repaired_count,
            $still_active_count,
            $terminal_count
        );

        $result = self::build_test_hub_action_result($tab, $scope, $message, '');
        $result['repair_summary'] = $summary;

        return $result;
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private static function build_download_test_hub_artifact_result(array $request): array
    {
        $job_id = sanitize_file_name(self::read_action_scalar($request, 'cbt_flow_job_id'));
        $artifact_key = sanitize_text_field(self::read_action_scalar($request, 'cbt_artifact_key'));
        if ($job_id === '' || $artifact_key === '') {
            return [
                'success' => false,
                'error' => 'Parameter artifact Test Hub tidak lengkap.',
            ];
        }

        $job = self::find_flow_check_job_by_id($job_id);
        if (empty($job)) {
            return [
                'success' => false,
                'error' => 'Job flow check tidak ditemukan.',
            ];
        }

        $file = self::resolve_flow_job_artifact_file_by_key($job, $artifact_key);
        if (empty($file) || empty($file['absolute_path']) || !is_string($file['absolute_path']) || !is_file($file['absolute_path']) || !is_readable($file['absolute_path'])) {
            return [
                'success' => false,
                'error' => 'Artifact Test Hub tidak ditemukan atau tidak aman untuk diunduh.',
            ];
        }

        return [
            'success' => true,
            'error' => '',
            'file_path' => (string) $file['absolute_path'],
            'download_name' => (string) ($file['download_name'] ?? basename((string) $file['absolute_path'])),
            'content_type' => (string) ($file['content_type'] ?? 'application/octet-stream'),
            'file' => $file,
        ];
    }

    /**
     * @param array<string,mixed> $post
     */
    private static function read_action_scalar(array $post, string $key): string
    {
        if (!array_key_exists($key, $post) || !is_scalar($post[$key])) {
            return '';
        }

        return (string) wp_unslash((string) $post[$key]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_test_hub_action_result(string $tab, string $scope, string $message, string $error, array $extra_args = []): array
    {
        $normalized_tab = self::normalize_unit_test_tab($tab);
        $normalized_scope = self::normalize_unit_test_scope($scope);
        $args = array_merge([
            'page' => 'cbt-test-hub',
            'cbt_unit_test_tab' => $normalized_tab,
            'cbt_checklist_scope' => $normalized_scope,
        ], $extra_args);

        if ($message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== '') {
            $args['cbt_err'] = $error;
        }

        return [
            'tab' => $normalized_tab,
            'scope' => $normalized_scope,
            'message' => $message,
            'error' => $error,
            'redirect_args' => $args,
            'redirect_url' => self::test_hub_page_url($args),
        ];
    }

    /**
     * @param array<string,mixed> $result
     */
    private static function redirect_test_hub_action_result(array $result): void
    {
        if (self::is_test_hub_async_request()) {
            self::send_test_hub_async_response($result);
        }

        $redirect_url = isset($result['redirect_url']) && is_string($result['redirect_url'])
            ? $result['redirect_url']
            : self::test_hub_page_url();

        wp_safe_redirect($redirect_url);
        exit;
    }

    private static function is_test_hub_async_request(): bool
    {
        $requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && is_scalar($_SERVER['HTTP_X_REQUESTED_WITH'])
            ? strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH'])
            : '';

        if ($requested_with === 'xmlhttprequest') {
            return true;
        }

        return isset($_REQUEST['cbt_test_hub_async'])
            && is_scalar($_REQUEST['cbt_test_hub_async'])
            && (string) wp_unslash((string) $_REQUEST['cbt_test_hub_async']) === '1';
    }

    /**
     * @param array<string,mixed> $result
     */
    private static function send_test_hub_async_response(array $result): void
    {
        $query = isset($result['redirect_args']) && is_array($result['redirect_args'])
            ? (array) $result['redirect_args']
            : ['page' => 'cbt-test-hub'];
        $query['page'] = 'cbt-test-hub';

        foreach ($query as $key => $value) {
            if (!is_scalar($value)) {
                unset($query[$key]);
                continue;
            }
            $query[$key] = (string) $value;
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        }

        $context = self::build_unit_test_context($query);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/test-hub/page.php';
        exit;
    }

    /**
     * @return array{label:string,command:string,success:bool,exit_code:int,stdout:string,stderr:string,failure_summary:string,test_case_counts:array{passed:int,failed:int,total:int}}
     */
    private static function run_unit_test_command(string $label, string $command, array $environment = []): array
    {
        self::extend_test_hub_action_time_limit();
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

    private static function extend_test_hub_action_time_limit(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (!function_exists('set_time_limit')) {
            return;
        }

        $current_limit = (int) ini_get('max_execution_time');
        if ($current_limit > 0 && $current_limit >= self::UNIT_TEST_ACTION_TIME_LIMIT) {
            return;
        }

        @set_time_limit(self::UNIT_TEST_ACTION_TIME_LIMIT);
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
            $environment['CBT_E2E_WP_BASE_URL'] = $settings['e2e_base_url'];
        }
        if ($settings['e2e_frontend_url'] !== '') {
            $environment['CBT_E2E_FRONTEND_URL'] = $settings['e2e_frontend_url'];
        }
        $results_root = self::playwright_results_root_path();
        $environment['PLAYWRIGHT_BROWSERS_PATH'] = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/.playwright-browsers';
        $environment['PLAYWRIGHT_OUTPUT_DIR'] = $results_root . '/admin';

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

    private static function redirect_test_hub_with_notice(string $tab, string $scope, string $message, string $error): void
    {
        $args = [
            'page' => 'cbt-test-hub',
            'cbt_unit_test_tab' => self::normalize_unit_test_tab($tab),
            'cbt_checklist_scope' => self::normalize_unit_test_scope($scope),
        ];

        if ($message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== '') {
            $args['cbt_err'] = $error;
        }

        wp_safe_redirect(self::test_hub_page_url($args));
        exit;
    }

    /**
     * @param array<string,array<string,mixed>> $latest_flow_jobs
     * @return array{targets:array<int,array<string,mixed>>,existing_count:int,has_existing:bool,has_active_jobs:bool}
     */
    private static function build_test_artifact_cleanup_context(array $latest_flow_jobs): array
    {
        $targets = [];
        $existing_count = 0;

        foreach (self::get_test_artifact_cleanup_targets() as $target) {
            $absolute_paths = self::resolve_test_artifact_target_paths($target);
            $primary_path = isset($absolute_paths[0]) ? (string) $absolute_paths[0] : '';
            $exists = false;
            foreach ($absolute_paths as $candidate_path) {
                if (self::test_artifact_target_has_contents((string) $candidate_path)) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $existing_count += 1;
            }

            $target['absolute_path'] = $primary_path;
            $target['absolute_paths'] = $absolute_paths;
            $target['exists'] = $exists;
            $targets[] = $target;
        }

        return [
            'targets' => $targets,
            'existing_count' => $existing_count,
            'has_existing' => $existing_count > 0,
            'has_active_jobs' => self::has_active_flow_jobs($latest_flow_jobs),
        ];
    }

    /**
     * @return array<int,array{label:string,relative_path:string,description:string}>
     */
    private static function get_test_artifact_cleanup_targets(): array
    {
        $targets = [
            [
                'label' => 'Playwright Results',
                'absolute_path' => self::playwright_results_root_path(),
                'description' => 'Trace, screenshot, video, admin-jobs log, dan artefak run browser.',
            ],
            [
                'label' => 'Test Results',
                'relative_path' => 'test-results',
                'description' => 'Ringkasan hasil runner tambahan yang dibuat saat QA atau smoke test.',
            ],
            [
                'label' => 'Coverage Report',
                'relative_path' => 'coverage',
                'description' => 'HTML report dan file output coverage hasil run lokal.',
            ],
            [
                'label' => 'PHPUnit Cache',
                'relative_path' => '.phpunit.cache',
                'description' => 'Cache run PHPUnit lokal untuk percepatan test developer.',
            ],
            [
                'label' => 'Output Playwright Artifacts',
                'absolute_paths' => [
                    rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/output/playwright',
                    rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/output/playwright-auth',
                    rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/output/playwright-result',
                    rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/output/playwright-sync',
                    rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/output/playwright-timer',
                ],
                'description' => 'Screenshot, snapshot text, dan bukti debug browser yang disimpan di folder output/playwright*.',
            ],
        ];

        $legacy_playwright_root = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/playwright-results';
        if (wp_normalize_path($legacy_playwright_root) !== wp_normalize_path(self::playwright_results_root_path())) {
            $targets[] = [
                'label' => 'Playwright Results (Legacy)',
                'absolute_path' => $legacy_playwright_root,
                'description' => 'Artefak Playwright lama yang masih tersimpan di root plugin dari run CLI/lokal.',
            ];
        }

        return $targets;
    }

    /**
     * @param array<string,mixed> $target
     * @return array<int,string>
     */
    private static function resolve_test_artifact_target_paths(array $target): array
    {
        $resolved = [];

        if (isset($target['absolute_paths']) && is_array($target['absolute_paths'])) {
            foreach ((array) $target['absolute_paths'] as $absolute_path) {
                $absolute_path = is_string($absolute_path) ? trim($absolute_path) : '';
                if ($absolute_path !== '') {
                    $resolved[] = $absolute_path;
                }
            }
        }

        if (empty($resolved)) {
            $absolute_path = isset($target['absolute_path']) ? (string) $target['absolute_path'] : '';
            if ($absolute_path === '') {
                $relative_path = isset($target['relative_path']) ? (string) $target['relative_path'] : '';
                $absolute_path = self::plugin_relative_path($relative_path);
            }

            if ($absolute_path !== '') {
                $resolved[] = $absolute_path;
            }
        }

        return array_values(array_unique($resolved));
    }

    private static function plugin_relative_path(string $relative_path): string
    {
        $relative_path = trim(str_replace('\\', '/', $relative_path), '/');
        if ($relative_path === '') {
            return '';
        }

        return rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/' . $relative_path;
    }

    private static function remove_test_artifact_path(string $path): bool
    {
        $normalized_path = wp_normalize_path($path);
        $plugin_root = wp_normalize_path(rtrim(CBT_EXAM_SYSTEM_PATH, '/\\'));
        $uploads_root = '';
        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir();
            $basedir = is_array($uploads) ? (string) ($uploads['basedir'] ?? '') : '';
            if ($basedir !== '') {
                $uploads_root = wp_normalize_path(rtrim($basedir, '/\\') . '/cbt-test-hub');
            }
        }

        $allowed_roots = array_filter([$plugin_root, $uploads_root], static function ($root): bool {
            return is_string($root) && $root !== '';
        });

        $is_allowed = false;
        foreach ($allowed_roots as $root) {
            if ($normalized_path === $root || str_starts_with($normalized_path, $root . '/')) {
                $is_allowed = true;
                break;
            }
        }

        if ($normalized_path === '' || !$is_allowed) {
            return false;
        }

        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path) || is_link($path)) {
            return @unlink($path);
        }

        if (!is_dir($path)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item_path = (string) $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                if (!@rmdir($item_path)) {
                    return false;
                }
                continue;
            }

            if (!@unlink($item_path)) {
                return false;
            }
        }

        if (@rmdir($path)) {
            return true;
        }

        clearstatcache(true, $path);

        return self::is_empty_directory($path);
    }

    private static function test_artifact_target_has_contents(string $path): bool
    {
        if ($path === '' || !file_exists($path)) {
            return false;
        }

        if (is_file($path) || is_link($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return false;
        }

        return !self::is_empty_directory($path);
    }

    private static function is_empty_directory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
        return !$iterator->valid();
    }

    private static function flow_job_directory_path(): string
    {
        return self::playwright_results_root_path() . '/admin-jobs';
    }

    private static function flow_job_file_path(string $job_id): string
    {
        return self::flow_job_directory_path() . '/' . sanitize_file_name($job_id) . '.json';
    }

    private static function flow_job_log_path(string $job_id): string
    {
        return self::flow_job_directory_path() . '/' . sanitize_file_name($job_id) . '.log';
    }

    private static function playwright_results_root_path(): string
    {
        $candidate_paths = [];

        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir();
            $basedir = is_array($uploads) ? (string) ($uploads['basedir'] ?? '') : '';
            if ($basedir !== '') {
                $candidate_paths[] = rtrim($basedir, '/\\') . '/cbt-test-hub/playwright-results';
            }
        }

        $candidate_paths[] = rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/playwright-results';

        foreach ($candidate_paths as $candidate_path) {
            if (self::ensure_directory_is_writable($candidate_path)) {
                return $candidate_path;
            }
        }

        return $candidate_paths[0] ?? (rtrim(CBT_EXAM_SYSTEM_PATH, '/\\') . '/playwright-results');
    }

    private static function ensure_directory_is_writable(string $directory_path): bool
    {
        if ($directory_path === '') {
            return false;
        }

        wp_mkdir_p($directory_path);
        if (!is_dir($directory_path)) {
            return false;
        }

        @chmod($directory_path, 0777);

        return is_writable($directory_path);
    }

    private static function ensure_flow_job_directory(): bool
    {
        $root_directory = self::playwright_results_root_path();
        $job_directory = self::flow_job_directory_path();

        $root_ready = self::ensure_directory_is_writable($root_directory);
        $job_ready = self::ensure_directory_is_writable($job_directory);

        return $root_ready && $job_ready && is_dir($job_directory) && is_writable($job_directory);
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function write_flow_check_job(array $job): bool
    {
        if (!self::ensure_flow_job_directory()) {
            return false;
        }

        $raw_job_id = isset($job['job_id']) && is_scalar($job['job_id']) ? trim((string) $job['job_id']) : '';
        if ($raw_job_id === '') {
            return false;
        }
        $job_id = sanitize_file_name($raw_job_id);
        if ($job_id === '') {
            return false;
        }
        $job['job_id'] = $job_id;

        $encoded = wp_json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $job_path = self::flow_job_file_path($job_id);
        $bytes_written = file_put_contents($job_path, $encoded);
        if (is_file($job_path)) {
            @chmod($job_path, 0666);
        }

        clearstatcache(true, $job_path);

        return $bytes_written !== false && is_file($job_path);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_flow_check_job_by_id(string $job_id): ?array
    {
        $job_id = sanitize_file_name($job_id);
        if ($job_id === '') {
            return null;
        }

        foreach (self::read_flow_check_jobs() as $job) {
            if (is_array($job) && (string) ($job['job_id'] ?? '') === $job_id) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function remove_flow_check_job_files(array $job): bool
    {
        $job_id = sanitize_file_name((string) ($job['job_id'] ?? ''));
        if ($job_id === '') {
            return false;
        }

        $paths = [
            self::flow_job_file_path($job_id),
            self::flow_job_log_path($job_id),
            self::flow_job_directory_path() . '/' . $job_id . '-artifacts',
        ];
        $custom_log_path = isset($job['log_path']) && is_scalar($job['log_path']) ? (string) $job['log_path'] : '';
        if ($custom_log_path !== '') {
            $paths[] = $custom_log_path;
        }

        $success = true;
        foreach (array_values(array_unique($paths)) as $path) {
            if ($path === '' || !file_exists($path)) {
                continue;
            }
            $success = self::remove_test_artifact_path((string) $path) && $success;
        }

        return $success;
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @return array{repaired_count:int,still_active_count:int,terminal_count:int,repaired_jobs:array<int,string>}
     */
    private static function repair_stuck_flow_check_jobs(array $jobs): array
    {
        $summary = [
            'repaired_count' => 0,
            'still_active_count' => 0,
            'terminal_count' => 0,
            'repaired_jobs' => [],
        ];
        $now = time();

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $raw_job_id = isset($job['job_id']) && is_scalar($job['job_id']) ? trim((string) $job['job_id']) : '';
            if ($raw_job_id === '') {
                continue;
            }

            $job_id = sanitize_file_name($raw_job_id);
            if ($job_id === '') {
                continue;
            }

            $status = self::normalize_flow_job_status((string) ($job['status'] ?? 'queued'));
            if (self::is_terminal_flow_job_status($status)) {
                $summary['terminal_count'] += 1;
                continue;
            }

            if ($status === 'queued') {
                $summary['still_active_count'] += 1;
                continue;
            }

            if ($status === 'cancelling') {
                $worker_pid = (int) ($job['worker_pid'] ?? 0);
                $active_child_pid = (int) ($job['active_child_pid'] ?? 0);
                $worker_running = $worker_pid > 0 && self::is_flow_job_process_running($worker_pid);
                $child_running = $active_child_pid > 0 && self::is_flow_job_process_running($active_child_pid);
                if ($worker_running || $child_running) {
                    $summary['still_active_count'] += 1;
                    continue;
                }

                $job['status'] = 'cancelled';
                $job['finished_at'] = $now;
                $job['exit_code'] = 1;
                $job['failure_kind'] = 'cancelled';
                $job['failure_summary'] = 'Job flow check dibatalkan dari CBT Test Hub saat repair stuck jobs.';
                self::write_flow_check_job($job);
                $summary['repaired_count'] += 1;
                $summary['repaired_jobs'][] = $job_id;
                continue;
            }

            $stuck_state = self::resolve_running_flow_job_stuck_state($job, $now);
            if (empty($stuck_state['is_stuck'])) {
                $summary['still_active_count'] += 1;
                continue;
            }

            $failure_kind = (string) ($stuck_state['failure_kind'] ?? 'interrupted');
            $reasons = isset($stuck_state['reasons']) && is_array($stuck_state['reasons']) ? (array) $stuck_state['reasons'] : [];
            $job['status'] = 'failed';
            $job['finished_at'] = $now;
            $job['exit_code'] = 1;
            $job['failure_kind'] = $failure_kind;
            $job['failure_summary'] = $failure_kind === 'stale'
                ? 'Job background stale karena runner tidak lagi memberi heartbeat dalam batas aman.'
                : 'Job background terputus sebelum flow check selesai dijalankan.';
            $repair_message = 'Repair Stuck Jobs menandai job gagal karena ' . implode(', ', $reasons) . '.';
            $existing_stderr = trim((string) ($job['stderr'] ?? ''));
            $job['stderr'] = $existing_stderr === '' ? $repair_message : ($existing_stderr . PHP_EOL . PHP_EOL . $repair_message);
            self::write_flow_check_job($job);
            $summary['repaired_count'] += 1;
            $summary['repaired_jobs'][] = $job_id;
        }

        return $summary;
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @return array{active_count:int,terminal_count:int,queued_count:int,running_count:int,cancelling_count:int,has_active_jobs:bool}
     */
    private static function build_flow_job_repair_context(array $jobs): array
    {
        $queued_count = 0;
        $running_count = 0;
        $cancelling_count = 0;
        $terminal_count = 0;

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $status = self::normalize_flow_job_status((string) ($job['status'] ?? 'queued'));
            if ($status === 'queued') {
                $queued_count += 1;
            } elseif ($status === 'running') {
                $running_count += 1;
            } elseif ($status === 'cancelling') {
                $cancelling_count += 1;
            } elseif (self::is_terminal_flow_job_status($status)) {
                $terminal_count += 1;
            }
        }

        $active_count = $queued_count + $running_count + $cancelling_count;

        return [
            'active_count' => $active_count,
            'terminal_count' => $terminal_count,
            'queued_count' => $queued_count,
            'running_count' => $running_count,
            'cancelling_count' => $cancelling_count,
            'has_active_jobs' => $active_count > 0,
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array{is_stuck:bool,failure_kind:string,reasons:array<int,string>}
     */
    private static function resolve_running_flow_job_stuck_state(array $job, int $now): array
    {
        $started_at = (int) ($job['started_at'] ?? 0);
        $heartbeat_at = (int) ($job['heartbeat_at'] ?? 0);
        $worker_pid = (int) ($job['worker_pid'] ?? 0);

        $heartbeat_is_stale = $heartbeat_at > 0 && ($now - $heartbeat_at) > self::FLOW_JOB_HEARTBEAT_TIMEOUT;
        $runtime_is_excessive = $started_at > 0 && ($now - $started_at) > self::FLOW_JOB_MAX_RUNTIME;
        $process_missing = $worker_pid > 0 && !self::is_flow_job_process_running($worker_pid);
        $worker_pid_missing_too_long = $worker_pid <= 0
            && $started_at > 0
            && ($now - $started_at) > self::FLOW_JOB_WORKER_PID_GRACE_SECONDS;

        $reasons = [];
        if ($process_missing) {
            $reasons[] = 'process worker sudah tidak aktif';
        }
        if ($worker_pid_missing_too_long) {
            $reasons[] = 'worker PID kosong terlalu lama';
        }
        if ($heartbeat_is_stale) {
            $reasons[] = 'heartbeat job sudah stale';
        }
        if ($runtime_is_excessive) {
            $reasons[] = 'runtime job melewati batas aman';
        }

        return [
            'is_stuck' => !empty($reasons),
            'failure_kind' => ($heartbeat_is_stale || $runtime_is_excessive) ? 'stale' : 'interrupted',
            'reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed>|null $job
     * @return array<string,mixed>
     */
    private static function build_flow_job_artifact_context(?array $job): array
    {
        $empty = [
            'has_any' => false,
            'log' => null,
            'output_preview' => null,
            'artifacts' => [],
            'artifact_count' => 0,
        ];

        if (!is_array($job) || empty($job)) {
            return $empty;
        }

        $job_id = sanitize_file_name((string) ($job['job_id'] ?? ''));
        if ($job_id === '') {
            return $empty;
        }

        $log = self::resolve_flow_job_log_artifact($job);
        $output_preview = self::build_flow_job_inline_output_preview($job);
        $artifacts = self::collect_flow_job_artifact_files($job);

        return [
            'has_any' => !empty($log) || !empty($output_preview) || !empty($artifacts),
            'log' => $log,
            'output_preview' => $output_preview,
            'artifacts' => $artifacts,
            'artifact_count' => count($artifacts),
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>|null
     */
    private static function resolve_flow_job_log_artifact(array $job, bool $include_absolute = false): ?array
    {
        $job_id = sanitize_file_name((string) ($job['job_id'] ?? ''));
        if ($job_id === '') {
            return null;
        }

        $log_paths = [self::flow_job_log_path($job_id)];
        $custom_log_path = isset($job['log_path']) && is_scalar($job['log_path']) ? (string) $job['log_path'] : '';
        if ($custom_log_path !== '') {
            $log_paths[] = $custom_log_path;
        }

        foreach (array_values(array_unique($log_paths)) as $log_path) {
            $meta = self::build_flow_job_artifact_file_meta($job_id, (string) $log_path, 'log', $include_absolute);
            if (empty($meta)) {
                continue;
            }

            $preview = self::read_flow_job_log_preview((string) ($meta['absolute_path'] ?? $log_path));
            if (!$include_absolute) {
                unset($meta['absolute_path']);
            }
            $meta['preview'] = $preview['preview'];
            $meta['truncated'] = $preview['truncated'];
            $meta['line_count'] = $preview['line_count'];

            return $meta;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>|null
     */
    private static function build_flow_job_inline_output_preview(array $job): ?array
    {
        $blocks = [];
        $failure_summary = trim((string) ($job['failure_summary'] ?? ''));
        $stdout = trim((string) ($job['stdout'] ?? ''));
        $stderr = trim((string) ($job['stderr'] ?? ''));

        if ($failure_summary !== '') {
            $blocks[] = "Failure Summary\n" . $failure_summary;
        }
        if ($stdout !== '') {
            $blocks[] = "Stdout\n" . $stdout;
        }
        if ($stderr !== '') {
            $blocks[] = "Stderr\n" . $stderr;
        }
        if (empty($blocks)) {
            return null;
        }

        $preview = self::build_text_preview(implode("\n\n", $blocks));

        return [
            'label' => 'Output Snapshot',
            'preview' => $preview['preview'],
            'truncated' => $preview['truncated'],
            'line_count' => $preview['line_count'],
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<int,array<string,mixed>>
     */
    private static function collect_flow_job_artifact_files(array $job, bool $include_absolute = false): array
    {
        $job_id = sanitize_file_name((string) ($job['job_id'] ?? ''));
        if ($job_id === '') {
            return [];
        }

        $artifact_dir = self::flow_job_directory_path() . '/' . $job_id . '-artifacts';
        $safe_artifact_dir = self::normalize_safe_flow_artifact_path($artifact_dir, true);
        if ($safe_artifact_dir === '' || !is_dir($safe_artifact_dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($safe_artifact_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $meta = self::build_flow_job_artifact_file_meta($job_id, (string) $item->getPathname(), 'artifact', $include_absolute);
            if (!empty($meta)) {
                if (!$include_absolute) {
                    unset($meta['absolute_path']);
                }
                $files[] = $meta;
            }
        }

        usort($files, static function (array $left, array $right): int {
            return strcmp((string) ($left['relative_path'] ?? ''), (string) ($right['relative_path'] ?? ''));
        });

        return array_slice($files, 0, self::FLOW_JOB_ARTIFACT_LIST_LIMIT);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>|null
     */
    private static function resolve_flow_job_artifact_file_by_key(array $job, string $artifact_key): ?array
    {
        $artifact_key = sanitize_text_field($artifact_key);
        if ($artifact_key === '') {
            return null;
        }

        $log = self::resolve_flow_job_log_artifact($job, true);
        if (is_array($log) && (string) ($log['key'] ?? '') === $artifact_key) {
            return $log;
        }

        foreach (self::collect_flow_job_artifact_files($job, true) as $artifact) {
            if (is_array($artifact) && (string) ($artifact['key'] ?? '') === $artifact_key) {
                return $artifact;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function build_flow_job_artifact_file_meta(string $job_id, string $path, string $type, bool $include_absolute = false): ?array
    {
        $safe_path = self::normalize_safe_flow_artifact_path($path, false);
        if ($safe_path === '' || !is_file($safe_path) || !is_readable($safe_path)) {
            return null;
        }

        $relative_path = self::relative_flow_job_artifact_path($safe_path);
        if ($relative_path === '') {
            return null;
        }

        $size = filesize($safe_path);
        $updated_at = filemtime($safe_path);
        $key = self::flow_job_artifact_key($job_id, $relative_path);
        $download_name = sanitize_file_name($job_id . '-' . basename($safe_path));

        $meta = [
            'type' => $type,
            'key' => $key,
            'name' => basename($safe_path),
            'relative_path' => $relative_path,
            'size' => $size === false ? 0 : (int) $size,
            'updated_at' => $updated_at === false ? 0 : (int) $updated_at,
            'download_name' => $download_name,
            'content_type' => $type === 'log' ? 'text/plain; charset=utf-8' : 'application/octet-stream',
            'download_url' => add_query_arg([
                'action' => 'cbt_download_test_hub_artifact',
                'cbt_flow_job_id' => $job_id,
                'cbt_artifact_key' => $key,
                '_wpnonce' => wp_create_nonce('cbt_test_hub_artifact_' . $job_id),
            ], admin_url('admin-post.php')),
        ];

        if ($include_absolute) {
            $meta['absolute_path'] = $safe_path;
        }

        return $meta;
    }

    private static function normalize_safe_flow_artifact_path(string $path, bool $allow_directory): string
    {
        $path = trim($path);
        if ($path === '' || !file_exists($path)) {
            return '';
        }

        if ($allow_directory && !is_dir($path)) {
            return '';
        }
        if (!$allow_directory && !is_file($path)) {
            return '';
        }

        $real_path = realpath($path);
        $root_path = realpath(self::flow_job_directory_path());
        if (!is_string($real_path) || !is_string($root_path) || $real_path === '' || $root_path === '') {
            return '';
        }

        $normalized_path = wp_normalize_path($real_path);
        $normalized_root = rtrim(wp_normalize_path($root_path), '/');
        if ($normalized_path !== $normalized_root && !str_starts_with($normalized_path, $normalized_root . '/')) {
            return '';
        }

        return $real_path;
    }

    private static function relative_flow_job_artifact_path(string $safe_path): string
    {
        $root_path = realpath(self::flow_job_directory_path());
        if (!is_string($root_path) || $root_path === '') {
            return '';
        }

        $normalized_path = wp_normalize_path($safe_path);
        $normalized_root = rtrim(wp_normalize_path($root_path), '/');
        if ($normalized_path === $normalized_root || !str_starts_with($normalized_path, $normalized_root . '/')) {
            return '';
        }

        return ltrim(substr($normalized_path, strlen($normalized_root)), '/');
    }

    private static function flow_job_artifact_key(string $job_id, string $relative_path): string
    {
        return substr(hash('sha256', sanitize_file_name($job_id) . '|' . wp_normalize_path($relative_path)), 0, 24);
    }

    /**
     * @return array{preview:string,truncated:bool,line_count:int}
     */
    private static function read_flow_job_log_preview(string $path): array
    {
        $safe_path = self::normalize_safe_flow_artifact_path($path, false);
        if ($safe_path === '' || !is_readable($safe_path)) {
            return [
                'preview' => '',
                'truncated' => false,
                'line_count' => 0,
            ];
        }

        $size = filesize($safe_path);
        $size = $size === false ? 0 : (int) $size;
        $offset = max(0, $size - self::FLOW_JOB_LOG_PREVIEW_MAX_BYTES);
        $raw = file_get_contents($safe_path, false, null, $offset);
        if (!is_string($raw)) {
            $raw = '';
        }

        $preview = self::build_text_preview($raw, $size > self::FLOW_JOB_LOG_PREVIEW_MAX_BYTES);

        return $preview;
    }

    /**
     * @return array{preview:string,truncated:bool,line_count:int}
     */
    private static function build_text_preview(string $content, bool $already_truncated = false): array
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $content));
        if ($normalized === '') {
            return [
                'preview' => '',
                'truncated' => $already_truncated,
                'line_count' => 0,
            ];
        }

        $truncated = $already_truncated;
        if (strlen($normalized) > self::FLOW_JOB_LOG_PREVIEW_MAX_BYTES) {
            $normalized = substr($normalized, -self::FLOW_JOB_LOG_PREVIEW_MAX_BYTES);
            $truncated = true;
        }

        $lines = preg_split("/\n/", $normalized);
        $lines = is_array($lines) ? $lines : [];
        if (count($lines) > self::FLOW_JOB_LOG_PREVIEW_MAX_LINES) {
            $lines = array_slice($lines, -self::FLOW_JOB_LOG_PREVIEW_MAX_LINES);
            $truncated = true;
        }

        $preview = trim(implode("\n", $lines));
        if ($truncated && $preview !== '') {
            $preview = "[preview truncated to last " . self::FLOW_JOB_LOG_PREVIEW_MAX_LINES . " lines / " . self::FLOW_JOB_LOG_PREVIEW_MAX_BYTES . " bytes]\n" . $preview;
        }

        return [
            'preview' => $preview,
            'truncated' => $truncated,
            'line_count' => count($lines),
        ];
    }

    private static function mark_flow_check_job_failed(string $job_id, string $failure_summary, string $stderr = '', string $failure_kind = 'interrupted'): void
    {
        $job_path = self::flow_job_file_path($job_id);
        $job = self::read_flow_check_job_from_path($job_path);
        if (!is_array($job)) {
            return;
        }

        $job['status'] = 'failed';
        $job['finished_at'] = time();
        $job['exit_code'] = 1;
        $job['failure_kind'] = $failure_kind;
        $job['failure_summary'] = $failure_summary;
        if ($stderr !== '') {
            $existing_stderr = trim((string) ($job['stderr'] ?? ''));
            $job['stderr'] = $existing_stderr === '' ? $stderr : ($existing_stderr . PHP_EOL . PHP_EOL . $stderr);
        }

        self::write_flow_check_job($job);
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
        if (!is_array($decoded)) {
            return null;
        }

        $raw_job_id = isset($decoded['job_id']) && is_scalar($decoded['job_id']) ? trim((string) $decoded['job_id']) : '';
        if ($raw_job_id === '') {
            return null;
        }
        $job_id = sanitize_file_name($raw_job_id);
        if ($job_id === '') {
            return null;
        }
        $decoded['job_id'] = $job_id;

        return $decoded;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function read_flow_check_jobs(bool $normalize_runtime = true): array
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

            if ($normalize_runtime) {
                $job = self::normalize_flow_check_job_runtime_state($job);
            }
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
        if ($status === 'cancelling') {
            $worker_pid = (int) ($job['worker_pid'] ?? 0);
            $active_child_pid = (int) ($job['active_child_pid'] ?? 0);
            $worker_running = $worker_pid > 0 && self::is_flow_job_process_running($worker_pid);
            $child_running = $active_child_pid > 0 && self::is_flow_job_process_running($active_child_pid);
            if ($worker_running || $child_running) {
                return $job;
            }

            $job['status'] = 'cancelled';
            $job['finished_at'] = time();
            $job['exit_code'] = 1;
            $job['failure_kind'] = 'cancelled';
            $job['failure_summary'] = 'Job flow check dibatalkan dari CBT Test Hub.';
            self::write_flow_check_job($job);

            return $job;
        }

        if ($status !== 'running') {
            return $job;
        }

        $now = time();
        $stuck_state = self::resolve_running_flow_job_stuck_state($job, $now);
        if (empty($stuck_state['is_stuck'])) {
            return $job;
        }

        $reasons = isset($stuck_state['reasons']) && is_array($stuck_state['reasons']) ? (array) $stuck_state['reasons'] : [];
        $failure_kind = (string) ($stuck_state['failure_kind'] ?? 'interrupted');

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

    private static function terminate_flow_job_process(int $pid): bool
    {
        if ($pid <= 0 || !self::is_flow_job_process_running($pid)) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            if (function_exists('exec')) {
                @exec('taskkill /F /PID ' . (int) $pid . ' 2>NUL');
                return true;
            }

            return false;
        }

        if (function_exists('exec')) {
            @exec('kill -TERM ' . (int) $pid . ' 2>/dev/null');
            return true;
        }

        return false;
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
            if (self::is_active_flow_job_status($status)) {
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
            if (in_array(self::normalize_flow_job_status((string) ($job['status'] ?? '')), ['running', 'cancelling'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_flow_job_status(string $status): string
    {
        $status = sanitize_key($status);
        if (in_array($status, ['queued', 'running', 'cancelling', 'passed', 'failed', 'cancelled'], true)) {
            return $status;
        }

        return 'unknown';
    }

    private static function is_active_flow_job_status(string $status): bool
    {
        return in_array(self::normalize_flow_job_status($status), ['queued', 'running', 'cancelling'], true);
    }

    private static function is_terminal_flow_job_status(string $status): bool
    {
        return in_array(self::normalize_flow_job_status($status), ['passed', 'failed', 'cancelled'], true);
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
            case 'queued':
                return ['label' => 'Queued', 'tone' => 'idle'];
            case 'cancelling':
                return ['label' => 'Cancelling', 'tone' => 'planned'];
            case 'running':
                return ['label' => 'Running', 'tone' => 'planned'];
            case 'passed':
                return ['label' => 'Passed', 'tone' => 'done'];
            case 'cancelled':
                return ['label' => 'Cancelled', 'tone' => 'idle'];
            case 'failed':
                return ['label' => 'Failed', 'tone' => 'danger'];
            default:
                return ['label' => 'Unknown', 'tone' => 'idle'];
        }
    }

    /**
     * @param array<string,mixed> $item_definition
     * @param array<int,array<string,mixed>> $item_commands
     * @return array<string,mixed>
     */
    private static function create_flow_check_job(string $tab, string $scope, int $item_index, array $item_definition, array $item_commands): array
    {
        $now = time();
        $job_seed = strtolower((string) wp_generate_password(16, false, false));
        $job_hash = substr(md5($tab . '|' . $scope . '|' . $item_index . '|' . $now . '|' . microtime(true) . '|' . $job_seed), 0, 8);
        $job_id = 'flow-' . $job_seed . '-' . $job_hash;

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
            'active_child_pid' => 0,
            'cancel_requested_at' => 0,
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

        $latest_jobs = self::build_latest_flow_job_lookup($jobs);
        if (self::has_running_flow_jobs_only($latest_jobs)) {
            return;
        }

        $next_queued_job_id = self::resolve_next_queued_flow_job_id($jobs);
        if ($next_queued_job_id === '') {
            return;
        }

        self::start_flow_check_job_process($next_queued_job_id);
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     */
    private static function resolve_next_queued_flow_job_id(array $jobs): string
    {
        $next_job_id = '';
        $next_created_at = 0;

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            if (self::normalize_flow_job_status((string) ($job['status'] ?? '')) !== 'queued') {
                continue;
            }

            $raw_job_id = isset($job['job_id']) && is_scalar($job['job_id']) ? trim((string) $job['job_id']) : '';
            if ($raw_job_id === '') {
                continue;
            }

            $job_id = sanitize_file_name($raw_job_id);
            if ($job_id === '') {
                continue;
            }

            $created_at = (int) ($job['created_at'] ?? 0);
            if ($next_job_id === '' || $created_at < $next_created_at || $next_created_at <= 0) {
                $next_job_id = $job_id;
                $next_created_at = $created_at;
            }
        }

        return $next_job_id;
    }

    private static function start_flow_check_job_process(string $job_id): bool
    {
        if ($job_id === '' || !function_exists('proc_open')) {
            return false;
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
            self::mark_flow_check_job_failed(
                $job_id,
                'Worker background gagal dimulai dari PHP web.',
                'proc_open tidak berhasil membuat worker background untuk flow check.',
                'interrupted'
            );
            return false;
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exit_code = proc_close($process);
        if ($exit_code !== 0) {
            self::mark_flow_check_job_failed(
                $job_id,
                'Worker background gagal dimulai dari shell web.',
                'Shell background untuk flow check keluar dengan exit code ' . (int) $exit_code . '.',
                'interrupted'
            );
            return false;
        }

        return true;
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
        $has_flow_job = $list_key === 'smoke_tests' && !empty($flow_job) && is_array($flow_job);
        $job_status = $has_flow_job ? self::normalize_flow_job_status((string) ($flow_job['status'] ?? 'queued')) : '';
        $job_status_meta = $job_status !== '' ? self::flow_job_status_meta_for_job($flow_job) : null;
        $async_output_preview = $list_key === 'smoke_tests' ? self::build_flow_job_output_preview($flow_job) : '';
        $should_surface_async_preview = in_array((string) ($job_status_meta['label'] ?? ''), ['Interrupted', 'Stale'], true) && $async_output_preview !== '';
        $artifact_context = $list_key === 'smoke_tests' ? self::build_flow_job_artifact_context($flow_job) : [
            'has_any' => false,
            'log' => null,
            'output_preview' => null,
            'artifacts' => [],
            'artifact_count' => 0,
        ];

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
        $item['async_job'] = $has_flow_job ? $flow_job : null;
        $item['async_status'] = $job_status;
        $item['async_status_label'] = is_array($job_status_meta) ? (string) $job_status_meta['label'] : '';
        $item['async_status_tone'] = is_array($job_status_meta) ? (string) $job_status_meta['tone'] : 'idle';
        $item['async_output_preview'] = $should_surface_async_preview ? $async_output_preview : '';
        $item['artifact_context'] = $artifact_context;
        $item['is_job_active'] = $job_status !== '' && self::is_active_flow_job_status($job_status);
        $item['can_run_task'] = !empty($runner_commands) && empty($item['is_job_active']);
        $item['can_cancel_job'] = $job_status !== '' && in_array($job_status, ['queued', 'running', 'cancelling'], true);
        $item['can_retry_job'] = $job_status !== '' && self::is_terminal_flow_job_status($job_status);
        $item['can_clear_job'] = $job_status !== '' && self::is_terminal_flow_job_status($job_status);
        $item['run_button_label'] = !empty($item['is_job_active'])
            ? ($job_status === 'running' ? 'Running...' : ($job_status === 'cancelling' ? 'Cancelling...' : 'Queued...'))
            : 'Run Task';
        $item['detail_hint'] = $has_runner_output
            ? 'Runner terbaru tersedia'
            : (
                !empty($item['is_job_active'])
                    ? 'Flow check sedang diproses di background'
                    : ($status === 'ready' ? 'Coverage awal sudah ditautkan' : 'Backlog dan proses verifikasi')
            );
        $item['detail_open'] = !empty($item['open_by_default']) || $should_surface_async_preview || $has_failed_run_results || !empty($artifact_context['has_any']);

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
                'summary' => 'Checkpoint utama untuk memastikan progress kerja siswa, object-map answer, pending autosave, doubtful state, snapshot lokal, remote ui_state, dan cache attempt tetap pulih setelah refresh atau reopen.',
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
                            'Vitest juga memastikan snapshot corrupt dengan attempt_id berbeda dari attempt aktif ditolak total.',
                            'Fixture restore ini berjalan lewat readPersistedQuestionCache agar menembak jalur recovery yang benar-benar dipakai runtime.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-cache-recovery.test.js',
                            'src/frontend/app/storage/question-cache.js',
                        ],
                        'runner_commands' => ['Vitest Recovery'],
                    ]),
                    self::unit_test_checklist_item('Object-map answer dan metadata structured question tetap utuh saat cache recovery.', 'ready', [
                        'description' => 'Question cache recovery sekarang punya coverage untuk Matching, Cloze Dropdown, Categorization, dan Table Completion agar jawaban object-map serta metadata dropdown/tabel tidak berubah menjadi scalar atau hilang saat restore.',
                        'process_steps' => [
                            'Vitest menulis snapshot structured question ke session storage dengan answer object-map.',
                            'readPersistedQuestionCache() dipanggil seperti jalur runtime restore.',
                            'Assertion memverifikasi answer map, matching_meta, cloze_dropdown_meta, categorization_meta, dan table_completion_meta tetap utuh.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-cache-recovery.test.js',
                            'src/frontend/app/storage/question-cache.js',
                        ],
                        'runner_commands' => ['Vitest Recovery'],
                    ]),
                    self::unit_test_checklist_item('Pending autosave object-map tetap dipulihkan dari snapshot cache.', 'ready', [
                        'description' => 'Question cache recovery sekarang ikut mengunci state autosave batch agar jawaban object-map yang belum sempat tersubmit tidak berubah atau hilang setelah refresh/reopen.',
                        'process_steps' => [
                            'Vitest menulis snapshot question cache dengan pending_answer_batch_by_question berisi answer object-map.',
                            'readPersistedQuestionCache() memulihkan pendingAnswerBatchByQuestion, pendingAnswerBatchOrder, lastSubmittedPayloadByQuestion, dan blocking reason.',
                            'Assertion memverifikasi order pending disaring dari key invalid/duplikat tanpa mengubah object-map answer.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-cache-recovery.test.js',
                            'src/frontend/app/storage/question-cache.js',
                            'src/frontend/app/exam/answer-sync.js',
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
                    self::unit_test_checklist_item('Structured object-map answers tetap restore setelah reload runtime siswa.', 'ready', [
                        'description' => 'Flow check ini memakai fixture tipe soal baru untuk memastikan object-map answer dari Matching, Cloze Dropdown, Categorization, dan Table Completion tersimpan ke backend lalu kembali terpilih setelah reload.',
                        'process_steps' => [
                            'Siswa membuka exam fixture tipe soal baru dan mengisi dropdown/input structured.',
                            'Runner memverifikasi answer_text backend berisi object-map lengkap untuk tipe baru.',
                            'Halaman direload, lalu dropdown/input structured harus terisi kembali sesuai jawaban sebelum reload.',
                        ],
                        'evidence' => [
                            'tests/e2e/new-question-types.spec.js',
                            'tests/e2e/run-new-question-types-flow.mjs',
                            'src/frontend/app/storage/question-cache.js',
                        ],
                        'runner_commands' => ['Playwright Recovery Object Map Restore'],
                    ]),
                ],
            ],
            'sync_rest' => [
                'label' => 'Sync & REST',
                'summary' => 'Area untuk memetakan retry policy, pending queue, autosave feedback, object-map answer sync, delivery snapshot, blocking reason, dan kestabilan kontrak endpoint yang menopang lifecycle attempt frontend.',
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
                    self::unit_test_checklist_item('Autosave batch menjaga payload object-map dan feedback status per soal tetap benar.', 'ready', [
                        'description' => 'Answer sync runtime sekarang ikut dijalankan dari tab Sync & REST agar object-map answer Matching/Cloze/Categorization/Table Completion tidak rusak saat masuk antrean autosave batch.',
                        'process_steps' => [
                            'Vitest mengantrekan jawaban object-map dan memaksa flush ke submit_answers_batch.',
                            'Body request diverifikasi masih membawa object map utuh sebagai answer, bukan array/string yang salah.',
                            'State autosave juga dicek bersih setelah submit sukses dan signature payload tersimpan untuk feedback Tersimpan.',
                        ],
                        'evidence' => [
                            'tests/js/unit/answer-sync.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Answer Sync'],
                    ]),
                    self::unit_test_checklist_item('Restore autosave dari snapshot sparse atau cache corrupt tidak membuat runtime sync crash.', 'ready', [
                        'description' => 'Answer sync runtime sekarang punya guard untuk snapshot autosave yang tidak lengkap dan state question payload yang kosong, sehingga recovery tetap aman saat cache lokal rusak sebagian.',
                        'process_steps' => [
                            'Vitest memanggil restoreQuestionAutoSaveState() dengan snapshot yang hanya berisi error sync.',
                            'lastSubmittedPayloadByQuestion, pendingAnswerBatchByQuestion, dan pendingAnswerBatchOrder harus fallback ke struktur kosong yang aman.',
                            'initializeSubmittedPayloadCache() dan queueLoadedQuestionAnswersForFlush() juga diverifikasi tidak crash ketika questionPayloadById kosong.',
                        ],
                        'evidence' => [
                            'tests/js/unit/answer-sync.test.js',
                            'src/frontend/app/exam/answer-sync.js',
                        ],
                        'runner_commands' => ['Vitest Answer Sync'],
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
                'summary' => 'Area untuk menjaga REST login validation, persisted token guard, session aktif, revoke, login snapshot freshness, dan bootstrap session tetap konsisten saat siswa memulai atau melanjutkan attempt.',
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
                    self::unit_test_checklist_item('REST login menolak payload non-scalar, overlong, dan rate-limit tanpa memanggil auth utama.', 'ready', [
                        'description' => 'Endpoint login sekarang punya runner khusus untuk menjaga payload object/array/overlong tidak sampai memanggil CBT_Auth::login(), sekaligus mengunci retry_after dan user-agent restriction.',
                        'process_steps' => [
                            'PHPUnit memanggil login route harness dengan identifier object, password array, dan identifier terlalu panjang.',
                            'Assertion memastikan response invalid_payload/400 dan auth utama belum dipanggil.',
                            'Test yang sama mengunci retry_after setelah beberapa kegagalan dan forbidden user-agent untuk siswa.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestLoginInputValidationTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit Login Input Guard'],
                    ]),
                    self::unit_test_checklist_item('Login snapshot freshness refresh per exam window tanpa menabrak preflight aktif.', 'ready', [
                        'description' => 'Service freshness login snapshot sekarang masuk tab Auth supaya cache login Redis tetap relevan: window exam di-refresh, batch besar dibatasi, dan exam dengan preflight aktif di-skip.',
                        'process_steps' => [
                            'PHPUnit menyiapkan user siswa dan fake Redis profile/login snapshot.',
                            'tick() dijalankan untuk memverifikasi cursor round-robin, snapshot ready, dan skip saat preflight job aktif.',
                            'Batch besar diverifikasi berhenti di batas 150 user per exam agar refresh tidak terlalu agresif.',
                        ],
                        'evidence' => [
                            'tests/php/unit/LoginSnapshotFreshnessServiceTest.php',
                            'includes/class-cbt-login-snapshot-freshness-service.php',
                            'includes/class-cbt-login-auth-snapshot-cache.php',
                        ],
                        'runner_commands' => ['PHPUnit Login Snapshot Freshness'],
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
                    self::unit_test_checklist_item('Persisted token yang malformed ditolak sebelum bootstrap session dipakai ulang.', 'ready', [
                        'description' => 'Normalizer auth session frontend sekarang menolak token persist non-string, kosong, atau terlalu panjang agar storage yang korup/manual edit tidak bisa membuka jalur bootstrap dengan token palsu.',
                        'process_steps' => [
                            'Vitest memanggil normalizePersistedToken() untuk token valid, object, dan token overlong.',
                            'Storage auth dengan token object dibaca ulang lewat readPersistedAuthSession().',
                            'Assertion memastikan hasil restore null sehingga bootstrap session tidak memakai payload malformed.',
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
                'summary' => 'Area untuk memastikan countdown, adaptive heartbeat, heartbeat-lost signal, page lifecycle listener, timeout, dan transisi stage exam tetap sinkron saat runtime berubah cepat.',
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
                    self::unit_test_checklist_item('Adaptive heartbeat dan heartbeat-lost signal tetap aman saat koneksi berubah.', 'ready', [
                        'description' => 'Heartbeat manager sekarang mengunci dua jalur runtime yang paling sering berubah: interval adaptif dari server dan warning heartbeat lost setelah kegagalan online beruntun.',
                        'process_steps' => [
                            'Vitest mengirim payload adaptive load busy dan critical lalu memverifikasi interval heartbeat berubah ke nilai yang benar.',
                            'Kegagalan network beruntun saat browser online harus menaikkan heartbeat_lost satu kali dan mencatat security event.',
                            'Heartbeat sukses berikutnya harus membersihkan warning heartbeat lost tanpa merusak stage exam.',
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
                    self::unit_test_checklist_item('Page lifecycle listener melakukan flush yang tepat saat visible, hidden, blur, pagehide, dan beforeunload.', 'ready', [
                        'description' => 'Lifecycle manager sekarang ikut dijalankan dari runner Timer agar flush normal dan force keepalive pada event browser tidak lepas dari checklist ini.',
                        'process_steps' => [
                            'Vitest memicu blur, focus, online, dan visibility visible lalu memastikan flush ui_state non-force tetap dipakai.',
                            'Vitest memicu visibility hidden, pagehide, dan beforeunload lalu memastikan force keepalive flush dipakai.',
                            'Coverage ini menjaga transisi browser/tab tetap aman tanpa menjalankan Playwright berat dari unit runner.',
                        ],
                        'evidence' => [
                            'tests/js/unit/lifecycle.test.js',
                            'src/frontend/app/core/lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Reconnect burst dari visible, focus, dan online tidak menjadwalkan retry/heartbeat berulang.', 'ready', [
                        'description' => 'Lifecycle listener sekarang punya coverage untuk burst event browser yang sering muncul saat tab kembali aktif, sehingga retry sync dan heartbeat hanya dipicu sekali dalam window dedupe.',
                        'process_steps' => [
                            'Vitest memicu visibility visible, focus, dan online secara berurutan dalam satu burst.',
                            'triggerPendingSyncLifecycleRetry() dan runSessionHeartbeat() diverifikasi hanya terpanggil sekali dari event visible.',
                            'Event online tetap memperbarui status koneksi, tetapi membawa triggerRetry=false karena retry sudah dilakukan pada burst yang sama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/lifecycle.test.js',
                            'src/frontend/app/core/lifecycle.js',
                        ],
                        'runner_commands' => ['Vitest Timer & Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Cleanup lifecycle memastikan timer, interval, dan runtime state tidak tertinggal setelah stage berubah.', 'ready', [
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
                'summary' => 'Area untuk menjaga input jawaban, question state, navigation, doubtful flag, dan runtime soal tetap sinkron pada semua 11 tipe soal.',
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
                    self::unit_test_checklist_item('Renderer siswa menampilkan kontrol dasar untuk semua 11 tipe soal.', 'ready', [
                        'description' => 'Question render sekarang punya matrix test untuk MC, MA, True/False, TF Matrix, Short Answer, Essay, Ordering, Matching, Cloze Dropdown, Categorization, dan Table Completion agar tipe lama dan tipe baru sama-sama terjaga.',
                        'process_steps' => [
                            'Vitest merender stem/input tiap tipe soal memakai stored answer yang sudah ada.',
                            'Assertion memverifikasi action control utama, selected/restored value, dan rich renderer TF Matrix tetap muncul.',
                            'Coverage ini menjaga runtime siswa agar tidak kehilangan control saat renderer diubah untuk tipe tertentu.',
                        ],
                        'evidence' => [
                            'tests/js/unit/question-render.test.js',
                            'src/frontend/app/exam/question-render.js',
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
                    self::unit_test_checklist_item('Structured object-map answer untuk tipe baru restore stabil setelah reload.', 'ready', [
                        'description' => 'Flow check ini memakai Matching, Cloze Dropdown, Categorization, dan Table Completion untuk memastikan object-map answer tetap tersimpan, restore, dan tidak rusak saat siswa reload.',
                        'process_steps' => [
                            'Runner menyinkronkan bank soal structured ke fixture runtime.',
                            'Siswa menjawab Matching, Cloze Dropdown, Categorization, dan Table Completion lewat UI.',
                            'Setelah reload, dropdown/input pada tiap tipe harus memuat jawaban yang sama dan row answer backend tetap berupa object-map.',
                        ],
                        'evidence' => [
                            'tests/e2e/new-question-types.spec.js',
                            'tests/e2e/helpers/frontend-browser.js',
                            'src/frontend/app/questions/renderer.js',
                        ],
                        'runner_commands' => ['Playwright New Types Runtime Restore'],
                    ]),
                ],
            ],
            'result_scoring' => [
                'label' => 'Result & Export',
                'summary' => 'Fokus pada stabilitas pass/fail, review siswa object-map, progress admin, restricted result, pending essay, dan export report print-ready.',
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
                        'runner_commands' => ['Vitest Result & Export', 'PHPUnit Result Payload'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                    self::unit_test_checklist_item('Review payload object-map memuat rows Matching, Cloze, Categorization, dan Table Completion.', 'ready', [
                        'description' => 'Result payload backend sekarang punya coverage untuk empat tipe object-map agar submitted/correct text, status wrong, dan skor parsial tidak hilang di review siswa.',
                        'process_steps' => [
                            'PHPUnit memanggil build_result_payload() dengan fixture matching, cloze dropdown, categorization, dan table completion.',
                            'Assertion memeriksa review_items berisi row detail per item/cell, label submitted/correct, dan skor parsial.',
                            'Snapshot submission context tetap dipakai sebagai sumber kunci server-side tanpa mengubah payload delivery siswa.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php',
                            'includes/class-cbt-rest.php',
                        ],
                        'runner_commands' => ['PHPUnit Result & Export'],
                    ]),
                    self::unit_test_checklist_item('Review renderer menampilkan semua 11 tipe soal dan meng-escape nilai Table Completion.', 'ready', [
                        'description' => 'Frontend review sekarang punya coverage minimal untuk MC, MA, TF, TF Matrix, Short Answer, Essay, Ordering, Matching, Cloze Dropdown, Categorization, dan Table Completion, termasuk alias status incorrect sebagai salah.',
                        'process_steps' => [
                            'Vitest merender review_items semua tipe utama.',
                            'Object-map rows diverifikasi menampilkan jawaban siswa, kunci, dan status match/mismatch.',
                            'Status incorrect diverifikasi tetap tampil sebagai label Salah dengan class is-wrong.',
                            'Nilai Table Completion yang menyerupai script harus tampil sebagai teks escaped.',
                        ],
                        'evidence' => [
                            'tests/js/unit/review-stage.test.js',
                            'src/frontend/app/stages/review.js',
                        ],
                        'runner_commands' => ['Vitest Result Review'],
                    ]),
                    self::unit_test_checklist_item('Progress admin mempertahankan preview object-map dan skor parsial.', 'ready', [
                        'description' => 'Admin Results Helper sekarang punya coverage khusus agar jawaban object-map kosong dianggap unanswered, skor parsial tetap terlihat sebagai wrong, dan label opsi dipakai saat tersedia.',
                        'process_steps' => [
                            'PHPUnit membangun progress item untuk matching, categorization, dan table completion.',
                            'Assertion memeriksa status, score_awarded, serta answer_preview yang dipakai di tabel progress admin.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AdminResultsHelperObjectMapProgressTest.php',
                            'admin/class-cbt-admin-results-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Result & Export'],
                    ]),
                    self::unit_test_checklist_item('Report export print-ready memakai score final atau fallback SUM(score_awarded) dengan aman.', 'ready', [
                        'description' => 'Service report exam sekarang punya coverage untuk nilai completed, nilai in-progress dari akumulasi score_awarded, peserta absen, filter kelas, dan peserta hadir yang tidak ada di target list.',
                        'process_steps' => [
                            'PHPUnit memanggil get_exam_report_rows() dengan fake query report.',
                            'Completed attempt harus memakai attempt.score, in-progress memakai answer_score_awarded/exam_total_points, dan peserta tanpa attempt tampil Belum ujian.',
                            'Siswa yang punya attempt tetapi tidak ada di daftar target tetap dimasukkan sebagai hadir agar export tidak kehilangan data.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AdminReportExamRowsTest.php',
                            'admin/class-cbt-admin-report-exam-service.php',
                            'admin/views/report-exam/print.php',
                        ],
                        'runner_commands' => ['PHPUnit Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                        'runner_commands' => ['Vitest Result & Export'],
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
                'summary' => 'Fokus ke parser DOCX, field pembahasan, gambar, tabel, template dinamis, structured question types, dan parity tampilan preview admin terhadap frontend.',
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
                    self::unit_test_checklist_item('Form manual compact punya kontrol jumlah, tab tipe soal, dan server-side count guard yang selaras.', 'ready', [
                        'description' => 'Safety net ringan ini membaca source form manual dan backend reader agar kontrol Jumlah + Expand tidak drift dari batas tipe soal yang sekarang dipakai plugin.',
                        'process_steps' => [
                            'PHPUnit memeriksa semua 11 tab tipe soal manual tetap dirender sebagai tab input.',
                            'Kontrol count MC/MA, TF Matrix, Short Answer, Ordering, Matching, Cloze, Categorization, dan Table Completion diverifikasi punya batas min/max yang benar.',
                            'JS submit dan backend reader harus sama-sama memakai count aktif sehingga baris tersembunyi tidak ikut disimpan.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AdminQuestionManualCompactAuthoringTest.php',
                            'admin/views/questions/page.php',
                            'admin/class-cbt-admin-questions-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Manual Compact Authoring'],
                    ]),
                    self::unit_test_checklist_item('Download template hanya membawa parameter struktur untuk tipe import yang sedang aktif.', 'ready', [
                        'description' => 'Kontrol template dinamis sekarang punya guard source agar parameter dari tab import lain tidak ikut masuk ke URL download saat kontrolnya tersembunyi.',
                        'process_steps' => [
                            'PHPUnit membaca source form import dan memastikan templateParams selalu dimulai dari question_count.',
                            'Kontrol dinamis diverifikasi disembunyikan serta disabled ketika tidak relevan dengan tipe import aktif.',
                            'Parameter tambahan seperti option_count, pair_count, atau table_rows baru ditambahkan setelah guard inactive return early.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AdminQuestionManualCompactAuthoringTest.php',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['PHPUnit Manual Compact Authoring'],
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
                    self::unit_test_checklist_item('Wrapper math import baru tetap lolos sanitizer preview dan review frontend sebelum dirender KaTeX.', 'ready', [
                        'description' => 'Parity math butuh dua guard unit: admin preview tidak boleh membuang atribut data-cbt-math dari import baru, dan review frontend tetap mempertahankan wrapper math itu sebelum enhancer KaTeX dijalankan.',
                        'process_steps' => [
                            'PHPUnit memverifikasi render_editor_html() tetap mempertahankan class cbt-math, data-cbt-math, dan data-cbt-math-display pada payload rich HTML.',
                            'Vitest merender review section dengan stem, opsi, dan pembahasan yang memuat wrapper math dari import baru.',
                            'Assertion memastikan wrapper math tidak hilang sebelum tahap render KaTeX, sehingga surface admin dan frontend membaca kontrak HTML yang sama.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsHelperPreviewRenderingTest.php',
                            'tests/js/unit/review-stage.test.js',
                            'tests/js/unit/math-render.test.js',
                            'src/shared/math-render.js',
                            'src/frontend/app/stages/review.js',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview', 'Vitest Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('Equation editor manual membangun wrapper inline/block, membaca wrapper existing, dan memvalidasi preview KaTeX sebelum insert.', 'ready', [
                        'description' => 'Unit authoring math mengunci kontrak markup manual agar modal Equation menghasilkan wrapper yang sama dengan jalur import, sekaligus menjaga mode edit existing dan preview invalid state sebelum tombol insert aktif.',
                        'process_steps' => [
                            'Vitest memanggil buildEquationHtml() untuk mode inline dan block lalu memverifikasi class cbt-math, data-cbt-math, dan data-cbt-math-display yang dihasilkan.',
                            'Wrapper existing dibaca dari markup textarea lewat parseEquationWrapperMarkup() dan findEquationWrapperRange() untuk memastikan mode edit mengganti wrapper lama, bukan menyisipkan markup duplikat.',
                            'renderEquationPreview() diuji dengan source valid dan invalid agar insert hanya aktif saat KaTeX bisa dirender dengan aman.',
                        ],
                        'evidence' => [
                            'tests/js/unit/math-authoring.test.js',
                            'src/admin/math-authoring.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Vitest Import & Preview'],
                    ]),
                    self::unit_test_checklist_item('TF Matrix manual tetap dibatasi ke text plus equation sambil menjaga duplicate compare berbasis plain text.', 'ready', [
                        'description' => 'Phase 2 authoring TF Matrix perlu dua pagar: preview ringan hanya boleh menyisakan text dan wrapper math, dan helper backend tetap membandingkan signature duplicate dari plain text walau statement memuat markup equation.',
                        'process_steps' => [
                            'Vitest memanggil sanitizeTfMatrixPreviewHtml() untuk memastikan markup non-math tidak dipertahankan pada preview ringan statement.',
                            'PHPUnit memverifikasi sanitize_lightweight_math_html() membuang HTML lain tetapi tetap mempertahankan wrapper cbt-math yang valid.',
                            'PHPUnit juga memverifikasi duplicate statement tetap terdeteksi ketika salah satu row dibungkus wrapper math dengan markup berbeda tetapi plain text yang sama.',
                        ],
                        'evidence' => [
                            'tests/js/unit/math-authoring.test.js',
                            'tests/php/unit/QuestionsHelperShortAnswerTest.php',
                            'src/admin/math-authoring.js',
                            'admin/class-cbt-admin-questions-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Import & Preview', 'Vitest Import & Preview'],
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
                    self::unit_test_checklist_item('Import DOCX v2 tanpa PEMBAHASAN tetap berhasil dan tidak merusak parsing.', 'ready', [
                        'description' => 'Flow check ini menguji fixture DOCX v2 tanpa field explanation untuk memastikan jalur import admin tetap selesai dan preview masih memuat soal yang dihasilkan.',
                        'process_steps' => [
                            'Admin mengunggah tests/e2e/fixtures/import-preview/essay-no-pembahasan-v2.docx lewat tab Import Questions.',
                            'Progress import ditunggu sampai selesai diproses tanpa notice error.',
                            'Preview exam fixture setelah sync harus tetap memuat marker soal tanpa PEMBAHASAN yang baru diimpor.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/essay-no-pembahasan-v2.docx',
                        ],
                        'runner_commands' => ['Playwright Import No Explanation V2'],
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
                    self::unit_test_checklist_item('Equation DOCX multiple choice menjaga signature math yang sama di admin preview, exam, dan review hasil.', 'ready', [
                        'description' => 'Flow parity math ini tidak hanya mengecek ada KaTeX, tetapi juga membandingkan signature wrapper math yang sama antar-surface untuk stem soal, opsi, dan pembahasan hasil import DOCX.',
                        'process_steps' => [
                            'Admin mengimpor fixture native-equation-mc.docx lalu preview exam dibuka hingga wrapper math dan KaTeX muncul.',
                            'Siswa membuka shell ujian, lompat ke soal equation terakhir, lalu runtime exam dibaca untuk mengambil signature data-cbt-math yang aktif.',
                            'Setelah finish exam, review hasil dibandingkan lagi dengan signature admin preview untuk memastikan source math dan mode display tetap identik.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/native-equation-mc.docx',
                            'src/shared/math-render.js',
                        ],
                        'runner_commands' => ['Playwright Import Equation Math Parity'],
                    ]),
                    self::unit_test_checklist_item('Equation DOCX essay menjaga signature math yang sama di admin preview dan review hasil siswa.', 'ready', [
                        'description' => 'Flow parity ini menutup jalur rubrik essay agar wrapper math pada konten Acuan/Rubrik tetap identik antara preview admin dan review hasil siswa setelah import baru.',
                        'process_steps' => [
                            'Admin mengimpor fixture native-equation-essay.docx dan membuka preview card rubrik essay yang baru disinkronkan.',
                            'Signature wrapper math pada preview admin dibaca dari atribut data-cbt-math dan data-cbt-math-display.',
                            'Siswa menyelesaikan exam fixture, lalu review hasil dibandingkan dengan signature admin preview untuk memastikan rubrik essay memakai source math yang sama.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/fixtures/import-preview/native-equation-essay.docx',
                            'src/shared/math-render.js',
                        ],
                        'runner_commands' => ['Playwright Import Essay Equation Parity'],
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
                    self::unit_test_checklist_item('Equation manual pada stem MC, opsi, dan pembahasan tetap konsisten dari admin preview ke exam dan review hasil.', 'ready', [
                        'description' => 'Flow authoring ini menutup Phase 1 pada jalur multiple choice: admin menyisipkan equation lewat modal Visual editor, mengedit equation stem yang sudah ada, lalu memastikan KaTeX muncul konsisten di preview admin, runtime exam, dan review hasil.',
                        'process_steps' => [
                            'Admin membuka form question manual, membuat soal MC baru, lalu memakai tombol Equation pada stem, opsi benar, dan pembahasan.',
                            'Equation stem yang sudah tersisip kemudian diedit ulang untuk memastikan mode update mengganti wrapper lama, bukan menambah duplikasi.',
                            'Setelah sync ke fixture exam, preview admin, shell exam siswa, dan review hasil semuanya harus menampilkan KaTeX pada stem, opsi, dan pembahasan.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'src/admin/math-authoring.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Authoring Equation MC'],
                    ]),
                    self::unit_test_checklist_item('Equation manual pada rubrik essay mendukung jalur Text/Quicktags dan tetap konsisten di preview serta review.', 'ready', [
                        'description' => 'Flow ini mengunci jalur manual equation untuk essay rubric di mode Text/Quicktags, supaya authoring yang tidak memakai Visual editor tetap bisa menyisipkan wrapper math dan dirender KaTeX di semua surface penting.',
                        'process_steps' => [
                            'Admin membuka form essay, berpindah ke mode Text pada rubric editor, lalu menyisipkan equation block lewat modal Equation.',
                            'Soal yang tersimpan disinkronkan ke fixture exam dan preview admin harus menampilkan KaTeX pada rubrik.',
                            'Siswa mengerjakan dan menyelesaikan exam, lalu review hasil harus tetap memuat KaTeX pada pasangan Acuan/Rubrik.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'src/admin/math-authoring.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Authoring Equation Essay'],
                    ]),
                    self::unit_test_checklist_item('Statement TF Matrix manual menerima equation dari template cepat dan tetap konsisten di preview serta review.', 'ready', [
                        'description' => 'Flow ini menutup Phase 2 dan Phase 3 untuk TF Matrix: statement lightweight field memakai modal yang sama, template cepat mengisi source LaTeX, lalu hasilnya harus tetap muncul sebagai KaTeX di preview admin dan review hasil siswa.',
                        'process_steps' => [
                            'Admin membuka form TF Matrix, mengisi statement pertama, lalu memakai tombol Equation dan template Pecahan pada field lightweight tersebut.',
                            'Statement kedua diisi teks biasa untuk memastikan matrix masih tervalidasi normal dan submit berhasil.',
                            'Setelah sync dan finish exam, preview admin serta review hasil harus sama-sama memuat KaTeX pada statement matrix yang diberi equation.',
                        ],
                        'evidence' => [
                            'tests/e2e/import-preview.spec.js',
                            'tests/e2e/helpers/admin-browser.js',
                            'src/admin/math-authoring.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright Authoring Equation TFM'],
                    ]),
                    self::unit_test_checklist_item('Manual authoring structured types menyimpan dan reopen compact form dengan jumlah aktif benar.', 'ready', [
                        'description' => 'Flow ini mengunci form manual untuk Matching, Cloze Dropdown, Categorization, dan Table Completion agar jumlah aktif/compact form tidak menyembunyikan data tersimpan.',
                        'process_steps' => [
                            'Admin membuat empat tipe structured baru dari form manual.',
                            'Setiap soal dibuka ulang dari edit page.',
                            'Field jumlah aktif dan nilai yang tersimpan harus sesuai data yang dibuat.',
                        ],
                        'evidence' => [
                            'tests/e2e/new-question-types.spec.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright New Types Manual Authoring'],
                    ]),
                    self::unit_test_checklist_item('DOCX import structured types menerima Matching, Cloze, Categorization, dan Table Completion.', 'ready', [
                        'description' => 'Flow ini memastikan parser DOCX menerima format structured question baru dan menyimpan detailnya ke bank soal tanpa fallback ke tipe lama.',
                        'process_steps' => [
                            'Admin mengunggah DOCX yang berisi empat tipe structured baru.',
                            'Import preview harus menampilkan semua marker soal.',
                            'Bank soal hasil import harus punya question_type yang benar untuk tiap marker.',
                        ],
                        'evidence' => [
                            'tests/e2e/new-question-types.spec.js',
                            'tests/php/unit/QuestionsImportPreviewTest.php',
                        ],
                        'runner_commands' => ['Playwright New Types DOCX Import'],
                    ]),
                    self::unit_test_checklist_item('Template import structured types menampilkan kontrol jumlah sesuai parameter aktif.', 'ready', [
                        'description' => 'Flow ini memastikan download template untuk Matching, Cloze Dropdown, Categorization, dan Table Completion membawa query parameter struktur yang dipilih admin.',
                        'process_steps' => [
                            'Admin membuka tab import tiap tipe structured.',
                            'Kontrol jumlah/ukuran struktur diubah dari default.',
                            'URL download template harus memuat parameter aktif seperti pair_count, dropdown_count, category_count, table_rows, dan table_cols.',
                        ],
                        'evidence' => [
                            'tests/e2e/new-question-types.spec.js',
                            'admin/views/questions/page.php',
                        ],
                        'runner_commands' => ['Playwright New Types Template Controls'],
                    ]),
                ],
            ],
            'security_log_observability' => [
                'label' => 'Security Log & Observability',
                'summary' => 'Area untuk memastikan event security tercatat rapi, severity tetap benar, live Redis/fallback MySQL ingest aman, live roster aktif, native bridge terhubung, must-watch aggregation stabil, dan context insiden mudah ditelusuri.',
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
                    self::unit_test_checklist_item('Native bridge menolak persisted token malformed sebelum snapshot dibuka ke app shell.', 'ready', [
                        'description' => 'Native bridge sekarang menormalisasi token seperti auth session: token persist non-string, kosong, atau terlalu panjang tidak boleh berubah menjadi string palsu di snapshot native.',
                        'process_steps' => [
                            'Vitest menyiapkan state exam aktif tanpa token state dan persisted auth dengan token object.',
                            'getSecuritySnapshot() dipanggil lewat CBTNativeBridge seperti app native asli.',
                            'Snapshot harus ok=0, token kosong, dan endpoint nativeSecurityEvent tidak dibuka.',
                        ],
                        'evidence' => [
                            'tests/js/unit/native-bridge.test.js',
                            'src/frontend/app/core/native-bridge.js',
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
                    self::unit_test_checklist_item('Live roster menampilkan attempt aktif berdasarkan exam, kelas, dan ruang.', 'ready', [
                        'description' => 'Flow check ini memastikan panel Live Roster tetap relevan dengan proctoring sekarang: attempt aktif muncul lengkap dengan exam, kelas, ruang, status koneksi, dan shortcut Results.',
                        'process_steps' => [
                            'Siswa memulai attempt pada TEST Security Fixture agar presence aktif masuk ke roster.',
                            'Admin membuka CBT Security > Security Log dan melihat section Live Roster di atas Must Watch.',
                            'Row siswa harus menampilkan exam, kode kelas, kode ruang, status Online, Seen, dan tombol Buka Results.',
                        ],
                        'evidence' => [
                            'tests/e2e/security-log-observability.spec.js',
                            'includes/class-cbt-live-proctoring-presence.php',
                            'admin/views/security/page.php',
                        ],
                        'runner_commands' => ['Playwright Security Live Roster'],
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
            'supervisor_proctoring' => [
                'label' => 'Supervisor & Proctoring',
                'summary' => 'Area untuk memastikan dashboard supervisor, live roster, presence heartbeat, security timeline, token gate, attendance, action required, dan reset login tetap aman dan sinkron.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Overview payload memakai teacher scope dan menjaga tab berat tetap lazy.', 'ready', [
                        'description' => 'Dashboard supervisor sekarang punya coverage untuk payload overview: scope teacher yang membatasi data, lazy loading pada tab berat, dan filter options exam/kelas/ruang tetap terisi.',
                        'process_steps' => [
                            'PHPUnit memanggil build_dashboard_payload() dengan role teacher dan filter exam, kelas, student_keyword.',
                            'Assertion memverifikasi is_admin_scope false, teacher_scope_user_id sesuai, dan semua section payload hadir.',
                            'Tab berat seperti monitoring_attempts diverifikasi tidak dihidrasi (total 0) saat overview.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SupervisorDashboardServiceTest.php',
                            'includes/class-cbt-supervisor-dashboard-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Supervisor Dashboard'],
                    ]),
                    self::unit_test_checklist_item('Live roster tab menghidrasi hanya context roster dan menjaga tab lain tetap kosong.', 'ready', [
                        'description' => 'Tab live_roster sekarang punya coverage untuk memastikan hanya data roster yang dihidrasi, sementara must_watch, monitoring, dan security_log tetap kosong.',
                        'process_steps' => [
                            'PHPUnit memanggil build_dashboard_payload() dengan tab live_roster.',
                            'Assertion memverifikasi roster items terisi dengan nama siswa yang benar.',
                            'Tab must_watch, monitoring_attempts, dan security_log diverifikasi tetap kosong.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SupervisorDashboardServiceTest.php',
                            'includes/class-cbt-supervisor-dashboard-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Supervisor Dashboard'],
                    ]),
                    self::unit_test_checklist_item('Presence snapshot membedakan online, stale, dan offline berdasarkan TTL last_seen.', 'ready', [
                        'description' => 'Presence manager sekarang punya coverage untuk tiga status koneksi: online dalam 60 detik, stale setelah 60 detik, dan offline setelah 90 detik.',
                        'process_steps' => [
                            'PHPUnit memperbarui presence attempt lalu membaca payload pada tiga waktu berbeda.',
                            'Assertion memverifikasi presence_status berubah dari online ke stale ke offline sesuai threshold.',
                            'Metadata visibility_state, has_focus, pending_sync_count, dan heartbeat_lost_active tetap terbaca konsisten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/LiveProctoringPresenceTest.php',
                            'includes/class-cbt-live-proctoring-presence.php',
                        ],
                        'runner_commands' => ['PHPUnit Roster & Presence'],
                    ]),
                    self::unit_test_checklist_item('Roster index mengelompokkan attempt per exam × kelas × ruang dengan counter derivasi yang benar.', 'ready', [
                        'description' => 'Live roster index sekarang punya coverage untuk grouping dan derived counters: active_total, online_total, offline_total, watch_total, hidden_total, dan heartbeat_total.',
                        'process_steps' => [
                            'PHPUnit menyinkronkan dua attempt ke roster index lalu membaca grouped payloads.',
                            'Assertion memverifikasi satu group dengan counter active 2, online 1, offline 1, watch 1.',
                            'sync_risk_summary() dan clear_attempt() diverifikasi menjaga konsistensi group membership.',
                        ],
                        'evidence' => [
                            'tests/php/unit/LiveAttemptRosterIndexTest.php',
                            'includes/class-cbt-live-attempt-roster-index.php',
                        ],
                        'runner_commands' => ['PHPUnit Roster & Presence'],
                    ]),
                    self::unit_test_checklist_item('REST supervisor menolak role student dan meneruskan scope teacher ke service.', 'ready', [
                        'description' => 'Route REST supervisor sekarang punya coverage untuk guard role: student ditolak, teacher diteruskan ke service dengan scope yang benar, dan attempt detail membutuhkan attempt_id.',
                        'process_steps' => [
                            'PHPUnit memverifikasi supervisor dashboard menolak role student dengan WP_Error.',
                            'Teacher request diteruskan ke build_dashboard_payload() dan hasilnya dikembalikan sebagai JSON.',
                            'Reset login dan attempt detail diverifikasi membutuhkan attempt_id dan meneruskan scope.',
                        ],
                        'evidence' => [
                            'tests/php/unit/SupervisorRestRoutesTest.php',
                            'includes/class-cbt-rest-supervisor.php',
                        ],
                        'runner_commands' => ['PHPUnit Supervisor Dashboard'],
                    ]),
                    self::unit_test_checklist_item('Frontend supervisor menormalisasi persen, cache key, dan merender security timeline.', 'ready', [
                        'description' => 'Runtime JS supervisor sekarang punya coverage untuk normalisasi persen dari API, cache key yang stabil terhadap urutan query, dan rendering security timeline dengan severity tone.',
                        'process_steps' => [
                            'Vitest memverifikasi normalizeSupervisorPercentValue() memakai angka langsung atau fallback parsing label Indonesia.',
                            'buildSupervisorDashboardCacheKey() diverifikasi stabil saat urutan query object berubah.',
                            'renderSupervisorSecurityTimelineSection() diverifikasi merender grouped summary dan empty state.',
                        ],
                        'evidence' => [
                            'tests/js/unit/supervisor-runtime.test.js',
                            'src/frontend/app/supervisor/runtime.js',
                        ],
                        'runner_commands' => ['Vitest Supervisor'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'exam_preflight_availability' => [
                'label' => 'Exam Preflight & Availability',
                'summary' => 'Area untuk memastikan gate service, availability snapshot, auto-warm queue, start attempt idempotency, opening state, dan metrics tetap menjaga akses ujian yang aman dan terukur.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Gate service menolak attempt di luar jadwal dan saat slot capacity penuh.', 'ready', [
                        'description' => 'Gate service sekarang punya coverage untuk dua guard utama: jadwal exam (sebelum starts_at / setelah ends_at) dan bucket capacity yang habis.',
                        'process_steps' => [
                            'PHPUnit memverifikasi gate reject attempt saat waktu di luar jadwal.',
                            'Bucket token dihabiskan lalu attempt berikutnya diverifikasi ditolak.',
                            'Attempt dalam jadwal dan dengan token tersedia diverifikasi diterima.',
                        ],
                        'evidence' => [
                            'tests/php/unit/StartAttemptGateServiceTest.php',
                            'tests/php/unit/StartAttemptGateBucketTest.php',
                            'includes/class-cbt-start-attempt-gate-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Gate & Start Attempt'],
                    ]),
                    self::unit_test_checklist_item('Availability snapshot dan auto-warm queue memproses exam berdasar jadwal terdekat.', 'ready', [
                        'description' => 'Availability cache sekarang punya coverage untuk snapshot readiness dan auto-warm ordering: exam dengan jadwal terdekat diproses lebih dulu, dan invalidation hanya berlaku untuk exam yang diubah.',
                        'process_steps' => [
                            'PHPUnit memverifikasi snapshot availability menghasilkan payload yang benar per exam.',
                            'Auto-warm queue diverifikasi memproses exam berurutan berdasarkan starts_at terdekat.',
                            'REST endpoint availability diverifikasi mengembalikan snapshot yang konsisten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/ExamAvailabilitySnapshotTest.php',
                            'tests/php/unit/ExamAvailabilityAutoWarmServiceTest.php',
                            'tests/php/unit/RestExamAvailabilitySnapshotTest.php',
                        ],
                        'runner_commands' => ['PHPUnit Availability Snapshot'],
                    ]),
                    self::unit_test_checklist_item('Preflight service memvalidasi kelas target, token gate, dan snapshot freshness.', 'ready', [
                        'description' => 'Preflight service sekarang punya coverage untuk validasi multi-layer: kelas target siswa, token gate status, dan freshness snapshot cache.',
                        'process_steps' => [
                            'PHPUnit memverifikasi preflight menolak siswa dari kelas yang tidak ditargetkan.',
                            'Token gate diverifikasi memblokir attempt saat gate tertutup.',
                            'Snapshot freshness diverifikasi tidak memakai data stale.',
                        ],
                        'evidence' => [
                            'tests/php/unit/ExamPreflightServiceTest.php',
                            'includes/class-cbt-exam-preflight-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Preflight Service'],
                    ]),
                    self::unit_test_checklist_item('Start attempt idempotency mencegah duplikasi attempt untuk student + exam yang sama.', 'ready', [
                        'description' => 'Idempotency service sekarang punya coverage untuk mencegah race condition: request paralel untuk student + exam yang sama hanya menghasilkan satu attempt.',
                        'process_steps' => [
                            'PHPUnit memverifikasi lock idempotency mencegah attempt kedua saat lock masih aktif.',
                            'Lock yang expired diverifikasi membiarkan attempt baru dibuat.',
                            'Attempt yang sudah ada diverifikasi dikembalikan tanpa membuat duplikat.',
                        ],
                        'evidence' => [
                            'tests/php/unit/StartAttemptIdempotencyServiceTest.php',
                            'includes/class-cbt-start-attempt-idempotency-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Gate & Start Attempt'],
                    ]),
                    self::unit_test_checklist_item('Opening state dan metrics mencatat waktu gate, queue depth, dan start attempt latency.', 'ready', [
                        'description' => 'Opening state dan metrics service sekarang punya coverage untuk recording diagnostics: gate wait time, queue depth snapshot, dan start attempt p95 latency.',
                        'process_steps' => [
                            'PHPUnit memverifikasi opening state menyimpan snapshot gate saat attempt dimulai.',
                            'Metrics service diverifikasi mencatat latency dan queue depth per exam.',
                            'REST start attempt index diverifikasi mengembalikan active index yang konsisten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/StartAttemptOpeningStateServiceTest.php',
                            'tests/php/unit/StartAttemptMetricsServiceTest.php',
                            'tests/php/unit/RestStartAttemptActiveIndexTest.php',
                        ],
                        'runner_commands' => ['PHPUnit Start Attempt Metrics & Index'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'scoring_grading' => [
                'label' => 'Scoring & Grading',
                'summary' => 'Area untuk memastikan auto-grading semua tipe soal, AI essay grading (OpenAI/Gemini), submission context snapshot, dan bulk grading job tetap menghasilkan skor yang akurat.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Auto-grading menghasilkan skor yang benar untuk semua 11 tipe soal.', 'ready', [
                        'description' => 'Scoring engine sekarang punya coverage matrix untuk MC, MA, TF, Ordering, Short Answer, TF Matrix, Matching, Cloze Dropdown, Categorization, Table Completion, dan Essay.',
                        'process_steps' => [
                            'PHPUnit memverifikasi jawaban benar menghasilkan skor penuh untuk MC, MA, TF, dan Ordering.',
                            'Partial scoring diverifikasi untuk TF Matrix, Matching, Cloze, Categorization, dan Table Completion.',
                            'Essay diverifikasi mengembalikan null is_correct dan skor nol (pending manual).',
                        ],
                        'evidence' => [
                            'tests/php/unit/ScoringAllQuestionTypesTest.php',
                            'includes/class-cbt-rest-scoring.php',
                        ],
                        'runner_commands' => ['PHPUnit Scoring All Types'],
                    ]),
                    self::unit_test_checklist_item('AI essay grading mengirim payload rubric yang konsisten dan menangani timeout.', 'ready', [
                        'description' => 'Essay AI grading service sekarang punya coverage untuk payload OpenAI/Gemini: rubric prompt, timeout handling, rate-limit retry, dan score extraction dari respons AI.',
                        'process_steps' => [
                            'PHPUnit memverifikasi payload prompt berisi rubric, jawaban siswa, dan instruksi skor.',
                            'Timeout dan rate-limit diverifikasi menghasilkan error yang aman tanpa crash.',
                            'Score extraction dari respons AI diverifikasi menghasilkan angka dalam range yang valid.',
                        ],
                        'evidence' => [
                            'tests/php/unit/EssayAIGradingServiceTest.php',
                            'includes/class-cbt-essay-ai-grading-service.php',
                        ],
                        'runner_commands' => ['PHPUnit AI Grading'],
                    ]),
                    self::unit_test_checklist_item('Bulk essay grading job memproses batch tanpa melewatkan attempt atau menimpa skor manual.', 'ready', [
                        'description' => 'Bulk grading job sekarang punya coverage untuk batch processing: attempt diproses berurutan, skor manual yang sudah ada tidak ditimpa, dan progress tracking konsisten.',
                        'process_steps' => [
                            'PHPUnit memverifikasi job memproses batch attempt dengan AI grading.',
                            'Attempt yang sudah dinilai manual diverifikasi di-skip.',
                            'Progress counter dan completion state diverifikasi konsisten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AdminResultsBulkEssayGradingTest.php',
                            'tests/php/unit/AdminResultsBulkJobServiceTest.php',
                        ],
                        'runner_commands' => ['PHPUnit AI Grading'],
                    ]),
                    self::unit_test_checklist_item('Submission context snapshot menyimpan jawaban per tipe soal dengan struktur yang benar.', 'ready', [
                        'description' => 'Submission context cache sekarang punya coverage untuk semua tipe soal: jawaban disimpan dengan metadata yang cukup untuk scoring dan review, termasuk object-map untuk tipe structured.',
                        'process_steps' => [
                            'PHPUnit memverifikasi snapshot menyimpan jawaban MC, MA, TF, Short Answer, dan Essay.',
                            'Object-map untuk Matching, Cloze, Categorization, dan Table Completion diverifikasi lengkap.',
                            'REST endpoint submission context diverifikasi mengembalikan snapshot yang konsisten.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionSubmissionContextSnapshotTest.php',
                            'tests/php/unit/RestQuestionSubmissionContextSnapshotTest.php',
                        ],
                        'runner_commands' => ['PHPUnit Submission Context'],
                    ]),
                    self::unit_test_checklist_item('Object-map answer sync memetakan jawaban Matching, Cloze, Categorization, dan Table Completion tanpa kehilangan data.', 'ready', [
                        'description' => 'Sync helper sekarang punya coverage untuk object-map answer: pair_index, dropdown_index, category mapping, dan cell coordinates disinkronkan dengan benar antara frontend dan backend.',
                        'process_steps' => [
                            'PHPUnit memverifikasi sync answer untuk Matching memetakan pair_index ke option_id.',
                            'Cloze dropdown diverifikasi menyimpan dropdown_index dan selected_option.',
                            'Categorization dan Table Completion diverifikasi menjaga cell coordinates.',
                        ],
                        'evidence' => [
                            'tests/php/unit/QuestionsSyncObjectMapAnswerTest.php',
                            'admin/class-cbt-admin-questions-sync-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Submission Context'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'update_health' => [
                'label' => 'Update & Health',
                'summary' => 'Area untuk memastikan update job state machine, backup ZIP, health check, release manifest, preflight validation, dan rollback tetap aman dan atomic.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Release manifest normalisasi dan fetch state menandai update yang tersedia atau tidak.', 'ready', [
                        'description' => 'Release helper sekarang punya coverage untuk manifest parsing: version wajib ada, download URL di-default, dan fetch state membedakan available vs no_release.',
                        'process_steps' => [
                            'PHPUnit memverifikasi normalize_manifest() memakai default untuk missing download URL.',
                            'Manifest tanpa version diverifikasi ditolak.',
                            'fetch_latest_release_state() diverifikasi menandai available saat release valid dan no_release saat kosong.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UpdateReleaseHelperTest.php',
                            'includes/class-cbt-update-release-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Release Helper'],
                    ]),
                    self::unit_test_checklist_item('Preflight validation memblokir checksum missing, unofficial URL, dan version mismatch.', 'ready', [
                        'description' => 'Preflight check sekarang punya coverage untuk tiga guard kritis: checksum harus ada, package URL harus official, dan downloaded package harus lolos checksum verification.',
                        'process_steps' => [
                            'PHPUnit memverifikasi preflight blocked saat checksum missing.',
                            'Unofficial package URL diverifikasi ditolak.',
                            'Downloaded package dengan checksum mismatch diverifikasi ditolak.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UpdateReleaseHelperTest.php',
                            'includes/class-cbt-update-release-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Release Helper'],
                    ]),
                    self::unit_test_checklist_item('ZIP package structure validation menolak invalid root dan path traversal.', 'ready', [
                        'description' => 'Package validator sekarang punya coverage untuk keamanan ZIP: root directory harus valid, path traversal (../) ditolak, dan bootstrap version harus cocok.',
                        'process_steps' => [
                            'PHPUnit memverifikasi invalid root directory ditolak.',
                            'Path traversal dalam ZIP diverifikasi ditolak.',
                            'Bootstrap version mismatch diverifikasi ditolak.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UpdateReleaseHelperTest.php',
                            'includes/class-cbt-update-release-helper.php',
                        ],
                        'runner_commands' => ['PHPUnit Release Helper'],
                    ]),
                    self::unit_test_checklist_item('Update job lifecycle: start install, tick progress, dan complete with history.', 'ready', [
                        'description' => 'Update job sekarang punya coverage untuk lifecycle lengkap: start_install membuat job tanpa download, check_job_tick memajukan progress, dan completion menulis history.',
                        'process_steps' => [
                            'PHPUnit memverifikasi start_install() membuat lightweight job.',
                            'check_job_tick() diverifikasi memajukan state dan menulis history saat selesai.',
                            'Rollback diverifikasi menolak backup yang tidak dikenal.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UpdateReleaseHelperTest.php',
                            'tests/php/unit/UpdateJobServiceTest.php',
                        ],
                        'runner_commands' => ['PHPUnit Update Job', 'PHPUnit Update Backup', 'PHPUnit Release Helper'],
                    ]),
                    self::unit_test_checklist_item('Health check mendeteksi version mismatch dan missing database tables.', 'ready', [
                        'description' => 'Health service sekarang punya coverage untuk diagnostics: version mismatch antara file dan database dilaporkan sebagai failure, dan missing question detail tables terdeteksi.',
                        'process_steps' => [
                            'PHPUnit memverifikasi health check melaporkan version mismatch sebagai failure.',
                            'Missing question detail tables diverifikasi terdeteksi dan dilaporkan.',
                            'Schema item diverifikasi melacak semua question detail tables yang diharapkan.',
                        ],
                        'evidence' => [
                            'tests/php/unit/UpdateHealthServiceTest.php',
                            'tests/php/unit/UpdateReleaseHelperTest.php',
                            'includes/class-cbt-update-health-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Update Health', 'PHPUnit Release Helper'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'cache_redis' => [
                'label' => 'Cache & Redis',
                'summary' => 'Area untuk memastikan namespace invalidation, pipeline batching, Redis reset progress, attempt snapshot cache, dan lock/release tetap konsisten dan aman.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Namespace invalidation hanya menghapus cache yang matching dan menjaga namespace lain tetap utuh.', 'ready', [
                        'description' => 'Cache namespace sekarang punya coverage untuk invalidation presisi: remember() menyimpan value, invalidate_namespace() menaikkan version, dan cached value lama tidak terbaca lagi.',
                        'process_steps' => [
                            'PHPUnit memverifikasi remember() meng-cache value pada panggilan pertama.',
                            'invalidate_namespace() diverifikasi menaikkan version dan membuat cache lama invalid.',
                            'Namespace lain dan UI state cache diverifikasi juga bisa di-invalidate secara terpisah.',
                        ],
                        'evidence' => [
                            'tests/php/unit/CacheNamespaceConsistencyTest.php',
                            'includes/class-cbt-cache.php',
                        ],
                        'runner_commands' => ['PHPUnit Cache Namespace'],
                    ]),
                    self::unit_test_checklist_item('Pipeline helper batching dan Redis reset progress tracking berjalan aman.', 'ready', [
                        'description' => 'Pipeline helper sekarang punya coverage untuk batching commands, dan Redis reset service punya coverage untuk progress tracking: 0% → chunks → 100%.',
                        'process_steps' => [
                            'PHPUnit memverifikasi pipeline helper membatasi chunk size.',
                            'Redis reset service diverifikasi melacak progress per batch.',
                            'Completion state diverifikasi konsisten setelah semua batch selesai.',
                        ],
                        'evidence' => [
                            'tests/php/unit/RedisPipelineHelperTest.php',
                            'tests/php/unit/PluginRedisResetServiceTest.php',
                            'includes/class-cbt-redis-pipeline-helper.php',
                            'includes/class-cbt-plugin-redis-reset-service.php',
                        ],
                        'runner_commands' => ['PHPUnit Redis Pipeline'],
                    ]),
                    self::unit_test_checklist_item('Attempt question contract cache dan runtime snapshot tetap sinkron dan bisa di-invalidate.', 'ready', [
                        'description' => 'Attempt cache sekarang punya coverage untuk contract cache dan runtime snapshot: data disimpan per attempt, dibaca konsisten, dan bisa di-invalidate tanpa merusak attempt lain.',
                        'process_steps' => [
                            'PHPUnit memverifikasi contract cache menyimpan dan membaca question payload per attempt.',
                            'Runtime snapshot diverifikasi menyimpan state lengkap dan bisa dibaca kembali.',
                            'Invalidation per attempt diverifikasi tidak merusak cache attempt lain.',
                        ],
                        'evidence' => [
                            'tests/php/unit/AttemptQuestionContractCacheTest.php',
                            'tests/php/unit/AttemptRuntimeSnapshotCacheTest.php',
                            'tests/php/unit/AttemptRuntimeSnapshotServiceTest.php',
                        ],
                        'runner_commands' => ['PHPUnit Attempt Cache'],
                    ]),
                    self::unit_test_checklist_item('Lock acquire dan release menjaga mutual exclusion pada operasi cache kritis.', 'ready', [
                        'description' => 'Cache lock sekarang punya coverage untuk acquire dan release: lock berhasil pada percobaan pertama, gagal saat sudah di-hold, dan release membebaskan lock untuk consumer berikutnya.',
                        'process_steps' => [
                            'PHPUnit memverifikasi acquire_lock() berhasil pada panggilan pertama.',
                            'Panggilan kedua saat lock aktif diverifikasi gagal.',
                            'release_lock() diverifikasi membebaskan lock sehingga acquire berikutnya berhasil.',
                        ],
                        'evidence' => [
                            'tests/php/unit/CacheNamespaceConsistencyTest.php',
                            'includes/class-cbt-cache.php',
                        ],
                        'runner_commands' => ['PHPUnit Cache Namespace'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'submit_finalization' => [
                'label' => 'Submit & Finalization',
                'summary' => 'Area untuk memastikan finish exam idempotency, expired auto-finalize, submit watchlist, presence monitoring, runtime cleanup, durable answer queue, dan flow metrics tetap menjaga integritas data ujian.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Finish exam idempotency mencegah duplikasi finalisasi dan memulihkan dari kegagalan submit.', 'ready', [
                        'description' => 'Finish exam service sekarang punya coverage untuk idempotency: request paralel hanya menghasilkan satu finalisasi, dan kegagalan mid-process bisa dipulihkan tanpa kehilangan data.',
                        'evidence' => ['tests/php/unit/FinishExamIdempotencyAndRecoveryTest.php'],
                        'runner_commands' => ['PHPUnit Finish Idempotency'],
                    ]),
                    self::unit_test_checklist_item('Expired auto-finalize menyelesaikan attempt yang melewati batas waktu tanpa duplikasi.', 'ready', [
                        'description' => 'Expired finalize service sekarang punya coverage untuk auto-finalize attempt yang expired: batch processing, status update, dan skor kalkulasi tanpa menimpa attempt yang sudah selesai.',
                        'evidence' => ['tests/php/unit/ExpiredAttemptFinalizeServiceTest.php', 'tests/php/unit/AdminResultsExpiredAutoFinalizeTest.php'],
                        'runner_commands' => ['PHPUnit Expired Finalize'],
                    ]),
                    self::unit_test_checklist_item('Submit watchlist dan presence monitoring melacak attempt yang butuh perhatian operator.', 'ready', [
                        'description' => 'Watchlist dan presence monitoring sekarang punya coverage untuk tracking attempt bermasalah: submit pending, recovery failed, dan runtime cleanup saat reset.',
                        'evidence' => ['tests/php/unit/AdminResultsSubmitWatchlistTest.php', 'tests/php/unit/AdminResultsPresenceMonitoringTest.php', 'tests/php/unit/AdminResultsResetRuntimeCleanupTest.php'],
                        'runner_commands' => ['PHPUnit Submit Watchlist & Presence'],
                    ]),
                    self::unit_test_checklist_item('Flow metrics mencatat latency submit dan entry untuk diagnostik performa.', 'ready', [
                        'description' => 'Flow metrics service sekarang punya coverage untuk recording latency: submit flow p95, entry flow timing, dan REST endpoint metrics yang konsisten.',
                        'evidence' => ['tests/php/unit/SubmitFlowMetricsServiceTest.php', 'tests/php/unit/RestSubmitFlowMetricTest.php', 'tests/php/unit/EntryFlowMetricsServiceTest.php', 'tests/php/unit/RestEntryFlowMetricTest.php'],
                        'runner_commands' => ['PHPUnit Flow Metrics'],
                    ]),
                    self::unit_test_checklist_item('Frontend finish flow state machine menangani transisi dan recovery dari kegagalan.', 'ready', [
                        'description' => 'Finish flow JS sekarang punya coverage untuk state machine lengkap: transisi dari exam ke result, recovery dari kegagalan network, dan confirm result runtime.',
                        'evidence' => ['tests/js/unit/finish-flow.test.js', 'tests/js/unit/finish-flow-recovery.test.js', 'tests/js/unit/confirm-result-runtime.test.js', 'tests/js/unit/result-stage.test.js'],
                        'runner_commands' => ['Vitest Finish Flow'],
                    ]),
                    self::unit_test_checklist_item('Durable answer queue mempertahankan jawaban saat offline dan batch sync saat online.', 'ready', [
                        'description' => 'Durable queue sekarang punya coverage untuk offline persistence: jawaban disimpan ke IndexedDB, batch sync saat koneksi pulih, dan ordering terjaga.',
                        'evidence' => ['tests/js/unit/durable-answer-queue.test.js', 'tests/js/unit/answer-sync-batch.test.js'],
                        'runner_commands' => ['Vitest Durable Answer Queue'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'security_event_pipeline' => [
                'label' => 'Security Event Pipeline',
                'summary' => 'Area untuk memastikan ingest event keamanan, flush pipeline, live counters, user-agent guard, answer sync token, incident report, dan admin security render tetap menjaga audit trail yang lengkap.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Security event ingest menerima event dan flush ke storage dengan batching yang benar.', 'ready', [
                        'description' => 'Ingest service sekarang punya coverage untuk event buffering: event diterima ke Redis buffer, flush memindahkan ke MySQL dalam batch, dan tidak ada event yang hilang.',
                        'evidence' => ['tests/php/unit/SecurityEventIngestTest.php', 'tests/php/unit/SecurityEventIngestFlushTest.php'],
                        'runner_commands' => ['PHPUnit Security Ingest'],
                    ]),
                    self::unit_test_checklist_item('Live counters dan user-agent guard mendeteksi anomali secara real-time.', 'ready', [
                        'description' => 'Live counters sekarang punya coverage untuk real-time anomaly detection: event count per attempt, user-agent guard memblokir bot, dan auth-level filtering.',
                        'evidence' => ['tests/php/unit/SecurityLiveCountersTest.php', 'tests/php/unit/SecurityUserAgentGuardTest.php', 'tests/php/unit/AuthUserAgentGuardTest.php'],
                        'runner_commands' => ['PHPUnit Security Counters & Guard'],
                    ]),
                    self::unit_test_checklist_item('Answer sync token dan incident report menjaga integritas jawaban dan audit trail.', 'ready', [
                        'description' => 'Token validasi sync jawaban sekarang punya coverage untuk HMAC verification, dan incident report punya coverage untuk generating laporan insiden keamanan.',
                        'evidence' => ['tests/php/unit/AuthAnswerSyncTokenTest.php', 'tests/php/unit/IncidentReportTest.php'],
                        'runner_commands' => ['PHPUnit Auth Answer Sync & Incident'],
                    ]),
                    self::unit_test_checklist_item('Admin security render menampilkan live roster, must-watch, dan Redis monitor dengan benar.', 'ready', [
                        'description' => 'Admin security panel sekarang punya coverage untuk rendering: live roster table, must-watch list, observability REST endpoint, dan Redis monitor dashboard.',
                        'evidence' => ['tests/php/unit/AdminSecurityObservabilityRestTest.php', 'tests/php/unit/AdminSecurityLiveRosterRenderTest.php', 'tests/php/unit/AdminSecurityMustWatchRenderTest.php', 'tests/php/unit/AdminSecurityServiceLiveRosterTest.php', 'tests/php/unit/AdminSecurityRedisMonitorRenderTest.php'],
                        'runner_commands' => ['PHPUnit Security Admin Render'],
                    ]),
                    self::unit_test_checklist_item('Frontend security context, logging, exam guard, shortcut blocking, fullscreen, dan idle detection bekerja sinkron.', 'ready', [
                        'description' => 'Frontend security sekarang punya coverage untuk semua layer: context initialization, client-side logging, exam-level guards, keyboard shortcut blocking, fullscreen enforcement, dan idle detection.',
                        'evidence' => ['tests/js/unit/security-context.test.js', 'tests/js/unit/security-logging.test.js', 'tests/js/unit/exam-security.test.js', 'tests/js/unit/app-events-security-shortcuts.test.js', 'tests/js/unit/fullscreen-state.test.js', 'tests/js/unit/idle-detection.test.js'],
                        'runner_commands' => ['Vitest Security Frontend'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'admin_exam_management' => [
                'label' => 'Admin Exam Management',
                'summary' => 'Area untuk memastikan CRUD exam validation, snapshot context/render, question print layout, question delete guard, token management, analytics insight, cache admin, dan UI helper tetap konsisten.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Exams CRUD validation dan helper memvalidasi input dan menyiapkan context yang benar.', 'ready', [
                        'description' => 'Exams service sekarang punya coverage untuk validasi input CRUD, helper functions, dan question print layout context.',
                        'evidence' => ['tests/php/unit/ExamsServiceValidationTest.php', 'tests/php/unit/AdminExamsHelperTest.php', 'tests/php/unit/AdminExamsQuestionPrintContextTest.php'],
                        'runner_commands' => ['PHPUnit Exams CRUD & Helper'],
                    ]),
                    self::unit_test_checklist_item('Exams snapshot context, actions, dan render menampilkan state exam yang akurat.', 'ready', [
                        'description' => 'Exam snapshot sekarang punya coverage untuk context builder, action handlers, dan template rendering yang konsisten.',
                        'evidence' => ['tests/php/unit/AdminExamsSnapshotContextTest.php', 'tests/php/unit/AdminExamsSnapshotActionsTest.php', 'tests/php/unit/AdminExamsSnapshotRenderTest.php'],
                        'runner_commands' => ['PHPUnit Exams Snapshot'],
                    ]),
                    self::unit_test_checklist_item('Question delete guard dan token service menjaga integritas data soal dan akses.', 'ready', [
                        'description' => 'Delete guard sekarang punya coverage untuk mencegah penghapusan soal yang sedang digunakan, dan token service mengelola akses exam.',
                        'evidence' => ['tests/php/unit/AdminQuestionDeleteGuardTest.php', 'tests/php/unit/AdminTokensServiceTest.php'],
                        'runner_commands' => ['PHPUnit Question Guards & Token'],
                    ]),
                    self::unit_test_checklist_item('Analytics insight, cache admin, login snapshot actions, dan UI helper render bekerja konsisten.', 'ready', [
                        'description' => 'Admin module sekarang punya coverage untuk analytics display, cache management, login snapshot actions, dan UI helper rendering.',
                        'evidence' => ['tests/php/unit/AdminAnalyticsInsightDisplayTest.php', 'tests/php/unit/AdminCacheServiceTest.php', 'tests/php/unit/AdminCacheLoginSnapshotActionsTest.php', 'tests/php/unit/AdminUiHelperRenderTest.php'],
                        'runner_commands' => ['PHPUnit Analytics & Cache Admin'],
                    ]),
                    self::unit_test_checklist_item('Frontend analytics charts merender visualisasi data yang benar.', 'ready', [
                        'description' => 'Analytics charts JS sekarang punya coverage untuk chart rendering dengan data yang benar.',
                        'evidence' => ['tests/js/unit/admin-analytics-charts.test.js'],
                        'runner_commands' => ['Vitest Analytics Charts'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'attempt_runtime_envelope' => [
                'label' => 'Attempt Runtime & Envelope',
                'summary' => 'Area untuk memastikan attempt envelope normalization, buffer flush integrity, active attempt index, snapshot auto-heal, adaptive load, REST delivery, runtime loader, stage manager, dan render cycle tetap menjaga data runtime yang konsisten.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Runtime envelope normalization dan buffer flush menjaga integritas data attempt.', 'ready', [
                        'description' => 'Runtime envelope sekarang punya coverage untuk normalization payload, buffer flush integrity, dan raw JSON response optimization.',
                        'evidence' => ['tests/php/unit/RuntimeAttemptEnvelopeTest.php', 'tests/php/unit/RuntimeBufferFlushIntegrityTest.php', 'tests/php/unit/RawJsonRestResponseTest.php'],
                        'runner_commands' => ['PHPUnit Runtime Envelope & Buffer'],
                    ]),
                    self::unit_test_checklist_item('Active attempt index menjaga konsistensi read/write dan membersihkan stale entries.', 'ready', [
                        'description' => 'Active attempt index sekarang punya coverage untuk read/write consistency dan stale cleanup tanpa mengganggu attempt aktif.',
                        'evidence' => ['tests/php/unit/ActiveAttemptIndexTest.php', 'tests/php/unit/ActiveAttemptIndexStaleTest.php'],
                        'runner_commands' => ['PHPUnit Active Attempt Index'],
                    ]),
                    self::unit_test_checklist_item('Snapshot auto-heal, adaptive load, dan start attempt snapshot bekerja bersama dengan aman.', 'ready', [
                        'description' => 'Auto-heal sekarang punya coverage untuk memperbaiki corrupt snapshots, adaptive load menyesuaikan throughput, dan start attempt snapshot builder menyiapkan data awal.',
                        'evidence' => ['tests/php/unit/SnapshotAutoHealQueueServiceTest.php', 'tests/php/unit/AdaptiveLoadServiceTest.php', 'tests/php/unit/ExamStartAttemptSnapshotTest.php'],
                        'runner_commands' => ['PHPUnit Snapshot & Load'],
                    ]),
                    self::unit_test_checklist_item('REST envelope live path, question delivery, session presence, dan profile snapshot mengembalikan data yang konsisten.', 'ready', [
                        'description' => 'REST endpoints sekarang punya coverage untuk live envelope path, question delivery snapshot, session presence snapshot, dan live profile snapshot.',
                        'evidence' => ['tests/php/unit/RestAttemptEnvelopeLivePathTest.php', 'tests/php/unit/RestQuestionDeliverySnapshotTest.php', 'tests/php/unit/RestSessionPresenceSnapshotTest.php', 'tests/php/unit/RestLiveProfileSnapshotTest.php'],
                        'runner_commands' => ['PHPUnit REST Envelope & Delivery'],
                    ]),
                    self::unit_test_checklist_item('Frontend runtime loader, exam session, exam stage, dan stage runtime manager mengelola transisi dengan benar.', 'ready', [
                        'description' => 'Frontend runtime sekarang punya coverage untuk loader state machine, session management, stage transitions, dan runtime orchestration.',
                        'evidence' => ['tests/js/unit/exam-runtime-loader.test.js', 'tests/js/unit/exam-session.test.js', 'tests/js/unit/exam-stage.test.js', 'tests/js/unit/stage-runtime-manager.test.js'],
                        'runner_commands' => ['Vitest Runtime Loader & Stage'],
                    ]),
                    self::unit_test_checklist_item('Render cycle, sync lifecycle bridge, readonly API cache, dan lazy math bekerja efisien.', 'ready', [
                        'description' => 'Frontend infra sekarang punya coverage untuk render cycle optimization, sync bridge, read-only API cache layer, dan lazy math renderer loading.',
                        'evidence' => ['tests/js/unit/render-cycle.test.js', 'tests/js/unit/sync-lifecycle-bridge.test.js', 'tests/js/unit/readonly-api-cache.test.js', 'tests/js/unit/lazy-math.test.js'],
                        'runner_commands' => ['Vitest Render & Sync Bridge'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'app_shell_bootstrap' => [
                'label' => 'App Shell & Bootstrap',
                'summary' => 'Area untuk memastikan app bootstrap sequence, shell rendering, student shell, app events, auth stages, browser storage, config management, service worker, diagnostics, utilities, dan frontend asset pipeline tetap stabil.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('App bootstrap, shell layout, meta management, dan student shell initialization bekerja berurutan.', 'ready', [
                        'description' => 'App initialization sekarang punya coverage untuk bootstrap sequence, shell rendering, metadata management, dan student-specific shell initialization.',
                        'evidence' => ['tests/js/unit/app-bootstrap.test.js', 'tests/js/unit/app-shell.test.js', 'tests/js/unit/app-meta.test.js', 'tests/js/unit/bootstrap-student-shell.test.js'],
                        'runner_commands' => ['Vitest App Bootstrap & Shell'],
                    ]),
                    self::unit_test_checklist_item('App events (rich zoom, session recovery) dan auth stages mengelola interaksi pengguna.', 'ready', [
                        'description' => 'App events sekarang punya coverage untuk rich zoom/pinch, session recovery events, dan authentication stage flow.',
                        'evidence' => ['tests/js/unit/app-events-rich-zoom.test.js', 'tests/js/unit/app-events-session-recovery.test.js', 'tests/js/unit/auth-stages.test.js'],
                        'runner_commands' => ['Vitest App Events & Auth Stages'],
                    ]),
                    self::unit_test_checklist_item('Browser storage, config management, config security, dan UI preferences menyimpan state dengan aman.', 'ready', [
                        'description' => 'Storage dan config sekarang punya coverage untuk browser storage abstraction, configuration management, security config validation, dan UI preferences persistence.',
                        'evidence' => ['tests/js/unit/browser-storage.test.js', 'tests/js/unit/config.test.js', 'tests/js/unit/config-security.test.js', 'tests/js/unit/ui-preferences.test.js'],
                        'runner_commands' => ['Vitest Browser & Config'],
                    ]),
                    self::unit_test_checklist_item('Service worker registration, frontend diagnostics, dan debug manager bekerja tanpa mengganggu runtime.', 'ready', [
                        'description' => 'Infra tools sekarang punya coverage untuk service worker lifecycle, diagnostic tools, dan debug manager console tools.',
                        'evidence' => ['tests/js/unit/service-worker-registration.test.js', 'tests/js/unit/frontend-diagnostics.test.js', 'tests/js/unit/debug-manager.test.js'],
                        'runner_commands' => ['Vitest Service Worker & Diagnostics'],
                    ]),
                    self::unit_test_checklist_item('Format utilities, HTML sanitization, calculator tool, dan legacy handoff bekerja konsisten.', 'ready', [
                        'description' => 'Utility functions sekarang punya coverage untuk format helpers, HTML sanitization, calculator runtime, dan legacy API handoff layer.',
                        'evidence' => ['tests/js/unit/format.test.js', 'tests/js/unit/html.test.js', 'tests/js/unit/calculator.test.js', 'tests/js/unit/legacy-handoff.test.js'],
                        'runner_commands' => ['Vitest Utilities'],
                    ]),
                    self::unit_test_checklist_item('Service worker registration PHP dan Vite asset manifest CSS parsing bekerja benar.', 'ready', [
                        'description' => 'Backend frontend infra sekarang punya coverage untuk SW registration/versioning dan Vite asset manifest parsing.',
                        'evidence' => ['tests/php/unit/FrontendServiceWorkerTest.php', 'tests/php/unit/ViteAssetManifestCssTest.php'],
                        'runner_commands' => ['PHPUnit Frontend & Vite'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'login_student_profile' => [
                'label' => 'Login & Student Profile',
                'summary' => 'Area untuk memastikan login auth snapshot, rate-limit, readiness warm queue, student profile, cohort index, exam audience evaluation, exam cards, dan session state tetap menjaga akses siswa yang aman.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Login auth snapshot cache, rate-limit, dan readiness warm queue menjaga akses login yang aman.', 'ready', [
                        'description' => 'Login service sekarang punya coverage untuk auth snapshot caching, rate-limit enforcement, dan readiness warm queue untuk pre-warm login data.',
                        'evidence' => ['tests/php/unit/LoginAuthSnapshotCacheTest.php', 'tests/php/unit/LoginRateLimitAndSessionTest.php', 'tests/php/unit/LoginReadinessWarmQueueServiceTest.php'],
                        'runner_commands' => ['PHPUnit Login Auth & Rate Limit'],
                    ]),
                    self::unit_test_checklist_item('Student profile snapshot dan cohort index mengelompokkan siswa dengan benar.', 'ready', [
                        'description' => 'Student profile sekarang punya coverage untuk snapshot generation dan cohort index grouping per kelas/ruang.',
                        'evidence' => ['tests/php/unit/StudentProfileSnapshotTest.php', 'tests/php/unit/StudentCohortIndexServiceTest.php'],
                        'runner_commands' => ['PHPUnit Student Profile & Cohort'],
                    ]),
                    self::unit_test_checklist_item('Exam audience evaluation, audience service, exam cards, dan progress UI menampilkan ujian yang relevan.', 'ready', [
                        'description' => 'Audience system sekarang punya coverage untuk evaluation logic, service management, exam cards payload, dan progress UI rendering.',
                        'evidence' => ['tests/php/unit/ExamAudienceEvaluationTest.php', 'tests/php/unit/ExamAudienceServiceTest.php', 'tests/php/unit/ExamCardsServiceTest.php', 'tests/php/unit/ExamCardsProgressUiTest.php'],
                        'runner_commands' => ['PHPUnit Exam Audience & Cards'],
                    ]),
                    self::unit_test_checklist_item('Frontend doubtful state, API client error handling, dan heartbeat lost detection bekerja sinkron.', 'ready', [
                        'description' => 'Frontend session sekarang punya coverage untuk doubtful/ragu-ragu state management, API client error handling, dan heartbeat lost detection.',
                        'evidence' => ['tests/js/unit/doubtful-state.test.js', 'tests/js/unit/api-client.test.js', 'tests/js/unit/session-heartbeat-lost.test.js'],
                        'runner_commands' => ['Vitest Session & API Client'],
                    ]),
                ],
                'smoke_tests' => [],
            ],
            'developer_setup_tooling' => [
                'label' => 'Developer & Setup Tooling',
                'summary' => 'Area untuk memastikan plugin activator/deactivator, maintenance tools, load test pool, branding, setup wizard, developer tools, dan admin module progress UIs tetap menjaga kualitas development workflow.',
                'status' => 'ready',
                'unit_tests' => [
                    self::unit_test_checklist_item('Plugin activator dan deactivator lifecycle mengelola state plugin dengan aman.', 'ready', [
                        'description' => 'Plugin lifecycle sekarang punya coverage untuk activation sequence, deactivation cleanup, dan state management.',
                        'evidence' => ['tests/php/unit/ActivatorDeactivatorLifecycleTest.php', 'tests/php/unit/DeactivatorTest.php'],
                        'runner_commands' => ['PHPUnit Plugin Lifecycle'],
                    ]),
                    self::unit_test_checklist_item('Maintenance tools, context builder, load test cancel, dan student pool bekerja konsisten.', 'ready', [
                        'description' => 'Maintenance module sekarang punya coverage untuk common utilities, context builder, load test cancel logic, student pool management, dan modularization.',
                        'evidence' => ['tests/php/unit/AdminMaintenanceCommonTest.php', 'tests/php/unit/AdminMaintenanceContextBuilderTest.php', 'tests/php/unit/AdminMaintenanceLoadTestCancelTest.php', 'tests/php/unit/MaintenanceLoadTestStudentPoolTest.php', 'tests/php/unit/MaintenanceModularizationTest.php'],
                        'runner_commands' => ['PHPUnit Maintenance Tools'],
                    ]),
                    self::unit_test_checklist_item('Setup wizard branding, security config, dan progress UIs menampilkan langkah yang benar.', 'ready', [
                        'description' => 'Setup wizard sekarang punya coverage untuk branding region label, setup branding progress, security configuration, dan security progress UI.',
                        'evidence' => ['tests/php/unit/BrandingRegionLabelTest.php', 'tests/php/unit/SetupBrandingProgressUiTest.php', 'tests/php/unit/SetupSecurityConfigTest.php', 'tests/php/unit/SetupSecurityProgressUiTest.php'],
                        'runner_commands' => ['PHPUnit Setup & Branding'],
                    ]),
                    self::unit_test_checklist_item('Developer build output access, dev server state, dan developer progress UI bekerja di environment development.', 'ready', [
                        'description' => 'Developer tools sekarang punya coverage untuk build output access, dev server state detection, dan developer progress UI rendering.',
                        'evidence' => ['tests/php/unit/DeveloperBuildOutputAccessTest.php', 'tests/php/unit/DeveloperDevServerStateTest.php', 'tests/php/unit/DeveloperProgressUiTest.php'],
                        'runner_commands' => ['PHPUnit Developer Tools'],
                    ]),
                    self::unit_test_checklist_item('Admin module progress UIs, service validations, dan photo import bekerja untuk semua modul.', 'ready', [
                        'description' => 'Admin modules sekarang punya coverage untuk ordering question type, analytics/questions/subjects/users progress UIs, subjects/users service validation, dan user photo import.',
                        'evidence' => ['tests/php/unit/OrderingQuestionTypeTest.php', 'tests/php/unit/AnalyticsProgressUiTest.php', 'tests/php/unit/QuestionsProgressUiTest.php', 'tests/php/unit/SubjectsProgressUiTest.php', 'tests/php/unit/SubjectsServiceValidationTest.php', 'tests/php/unit/UsersProgressUiTest.php', 'tests/php/unit/UsersServicePhotoImportTest.php', 'tests/php/unit/UsersServiceValidationTest.php'],
                        'runner_commands' => ['PHPUnit Admin Modules'],
                    ]),
                    self::unit_test_checklist_item('Flow job worker process mengelola lifecycle worker dengan aman.', 'ready', [
                        'description' => 'Flow job worker sekarang punya coverage untuk worker process lifecycle management.',
                        'evidence' => ['tests/js/unit/flow-job-worker.test.js'],
                        'runner_commands' => ['Vitest Flow Job Worker'],
                    ]),
                    self::unit_test_checklist_item('Test Hub meta-tests memverifikasi integritas safety, action handlers, view render, dan artifact cleanup.', 'ready', [
                        'description' => 'Test Hub meta-tests memverifikasi bahwa Test Hub itu sendiri bekerja dengan benar: safety test memeriksa runner definitions, action handlers memastikan queue/retry/cancel flow check, view render memastikan template output, dan artifact cleanup memastikan file management.',
                        'evidence' => ['tests/php/unit/TestHubServiceSafetyTest.php', 'tests/php/unit/TestHubActionHandlersTest.php', 'tests/php/unit/TestHubViewRenderTest.php', 'tests/php/unit/TestHubArtifactCleanupTest.php'],
                        'runner_commands' => ['PHPUnit Test Hub Meta'],
                    ]),
                ],
                'smoke_tests' => [],
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
        $tab = sanitize_key(is_scalar($raw_tab) ? (string) $raw_tab : '');
        $definitions = self::get_unit_test_tab_definitions();

        return isset($definitions[$tab]) ? $tab : 'recovery_persistence';
    }

    public static function normalize_unit_test_scope($raw_scope): string
    {
        $scope = sanitize_key(is_scalar($raw_scope) ? (string) $raw_scope : '');

        return $scope === 'smoke_tests' ? 'smoke_tests' : 'unit_tests';
    }
}
