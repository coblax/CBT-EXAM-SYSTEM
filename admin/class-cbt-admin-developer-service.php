<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Developer_Service
{
    private const OPTION_KEY = 'cbt_frontend_dev_settings';
    private const FRONTEND_PAGE_OPTION = 'cbt_exam_system_frontend_page_id';
    private const VITE_ENTRY = 'src/frontend/main.js';
    private const VITE_MANIFEST_RELATIVE = 'public/build/manifest.json';
    private const VITE_DEV_WRAPPER_RELATIVE = 'bin/cbt-vite-dev';
    private const BUILD_WATCH_WRAPPER_RELATIVE = 'bin/cbt-vite-build-watch';
    private const DEV_SERVER_DEFAULT_URL = 'http://127.0.0.1:5173';
    private const DEV_SERVER_HEALTH_TRANSIENT_PREFIX = 'cbt_dev_server_health_';
    private const DEV_SERVER_HEALTH_TTL = 15;
    private const DEV_SERVER_AUTOSTART_WAIT_US = 2000000;
    private const FRONTEND_STORAGE_PREFIX = 'cbt_exam_frontend_';
    private const FRONTEND_AUTH_SESSION_STORAGE_KEY = 'cbt_exam_frontend_auth_v1';
    private const FRONTEND_ATTEMPT_UI_SESSION_PREFIX = 'cbt_exam_frontend_attempt_ui_v1_';
    private const FRONTEND_QUESTION_CACHE_SESSION_PREFIX = 'cbt_exam_frontend_question_cache_v2_';
    private const FRONTEND_QUESTION_CACHE_META_LOCAL_STORAGE_PREFIX = 'cbt_exam_frontend_question_cache_meta_v2_';
    private const FRONTEND_QUESTION_CACHE_ITEM_LOCAL_STORAGE_PREFIX = 'cbt_exam_frontend_question_cache_item_v2_';
    private const FRONTEND_INDEXED_DB_NAME = 'cbt_exam_frontend_cache_v2';
    private const FRONTEND_DOUBTFUL_SESSION_PREFIX = 'cbt_exam_frontend_doubtful_v1_';
    private const FRONTEND_DIAGNOSTICS_REQUESTS_KEY = 'cbt_exam_frontend_debug_rest_v1';
    private const FRONTEND_DIAGNOSTICS_SNAPSHOT_KEY = 'cbt_exam_frontend_debug_snapshot_v1';
    private const FRONTEND_DIAGNOSTICS_SYNC_KEY = 'cbt_exam_frontend_debug_sync_v1';
    private const FRONTEND_DIAGNOSTICS_TIMELINE_KEY = 'cbt_exam_frontend_debug_timeline_v1';
    private const FRONTEND_DIAGNOSTICS_SCENARIO_KEY = 'cbt_exam_frontend_debug_scenarios_v1';
    private const FRONTEND_DIAGNOSTICS_ERRORS_KEY = 'cbt_exam_frontend_debug_errors_v1';
    private const FRONTEND_DIAGNOSTICS_STATE_KEY = 'cbt_exam_frontend_debug_state_v1';
    private const FRONTEND_DIAGNOSTICS_RENDER_STATS_KEY = 'cbt_exam_frontend_debug_render_stats_v1';
    private const FRONTEND_DIAGNOSTICS_ACTION_TRAIL_KEY = 'cbt_exam_frontend_debug_action_trail_v1';
    private const FRONTEND_DIAGNOSTICS_COMMAND_KEY = 'cbt_exam_frontend_debug_command_v1';
    private const FRONTEND_DIAGNOSTICS_MAX_ENTRIES = 50;
    private const FRONTEND_DIAGNOSTICS_TIMELINE_MAX_ENTRIES = 150;

    public static function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function option_key(): string
    {
        return self::OPTION_KEY;
    }

    /**
     * @return array{mode:string,dev_server_url:string,last_health_status:string,last_health_message:string,last_health_checked_at:int}
     */
    public static function get_settings(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $mode = isset($raw['mode']) ? self::sanitize_mode((string) $raw['mode']) : 'build';
        $dev_server_url = isset($raw['dev_server_url'])
            ? self::normalize_dev_server_url((string) $raw['dev_server_url'])
            : self::DEV_SERVER_DEFAULT_URL;
        if ($dev_server_url === '') {
            $dev_server_url = self::DEV_SERVER_DEFAULT_URL;
        }

        $last_health_status = isset($raw['last_health_status']) ? sanitize_key((string) $raw['last_health_status']) : 'unknown';
        if (!in_array($last_health_status, ['ok', 'failed', 'unknown'], true)) {
            $last_health_status = 'unknown';
        }

        $last_health_message = isset($raw['last_health_message'])
            ? sanitize_text_field((string) $raw['last_health_message'])
            : '';
        $last_health_checked_at = isset($raw['last_health_checked_at']) ? (int) $raw['last_health_checked_at'] : 0;

        return [
            'mode' => $mode,
            'dev_server_url' => $dev_server_url,
            'last_health_status' => $last_health_status,
            'last_health_message' => $last_health_message,
            'last_health_checked_at' => max(0, $last_health_checked_at),
        ];
    }

    public static function has_constant_override(): bool
    {
        return self::get_constant_override_url() !== '';
    }

    public static function get_constant_override_url(): string
    {
        if (!defined('CBT_EXAM_FRONTEND_DEV_SERVER')) {
            return '';
        }

        return self::normalize_dev_server_url((string) constant('CBT_EXAM_FRONTEND_DEV_SERVER'));
    }

    /**
     * @return array{mode:string,source:string,label:string,is_dev:bool,is_constant_override:bool,dev_server_url:string}
     */
    public static function resolve_frontend_asset_source(): array
    {
        $constant_override_url = self::get_constant_override_url();
        if ($constant_override_url !== '') {
            return [
                'mode' => 'dev',
                'source' => 'constant',
                'label' => 'Constant Override',
                'is_dev' => true,
                'is_constant_override' => true,
                'dev_server_url' => $constant_override_url,
            ];
        }

        $settings = self::get_settings();
        if ($settings['mode'] === 'dev' && $settings['dev_server_url'] !== '') {
            return [
                'mode' => 'dev',
                'source' => 'admin',
                'label' => 'Vite Dev Server',
                'is_dev' => true,
                'is_constant_override' => false,
                'dev_server_url' => $settings['dev_server_url'],
            ];
        }

        if ($settings['mode'] === 'stable') {
            return [
                'mode' => 'stable',
                'source' => 'stable',
                'label' => 'Stable Test Mode',
                'is_dev' => false,
                'is_constant_override' => false,
                'dev_server_url' => '',
            ];
        }

        return [
            'mode' => 'build',
            'source' => 'build',
            'label' => 'Production Build',
            'is_dev' => false,
            'is_constant_override' => false,
            'dev_server_url' => '',
        ];
    }

    /**
     * @return array{enabled:bool,status_label:string,reason:string,audience:string}
     */
    public static function resolve_frontend_debug_context(): array
    {
        $asset_source = self::resolve_frontend_asset_source();
        $can_manage = self::can_manage();

        if (!$can_manage) {
            return [
                'enabled' => false,
                'status_label' => 'INACTIVE',
                'reason' => 'Admin only',
                'audience' => 'manage_options only',
            ];
        }

        $mode = isset($asset_source['mode']) ? (string) $asset_source['mode'] : 'build';

        if (!empty($asset_source['is_dev']) || $mode === 'stable') {
            return [
                'enabled' => true,
                'status_label' => 'ACTIVE',
                'reason' => isset($asset_source['label']) && is_string($asset_source['label']) && $asset_source['label'] !== ''
                    ? $asset_source['label']
                    : 'Vite Dev Server',
                'audience' => 'manage_options only',
            ];
        }

        return [
            'enabled' => false,
            'status_label' => 'INACTIVE',
            'reason' => 'Production Build',
            'audience' => 'manage_options only',
        ];
    }

    /**
     * @return array{enabled:bool,status_label:string,reason:string,audience:string}
     */
    public static function resolve_frontend_diagnostics_context(): array
    {
        $asset_source = self::resolve_frontend_asset_source();
        $can_manage = self::can_manage();

        if (!$can_manage) {
            return [
                'enabled' => false,
                'status_label' => 'INACTIVE',
                'reason' => 'Admin only',
                'audience' => 'manage_options only',
            ];
        }

        $mode = isset($asset_source['mode']) ? (string) $asset_source['mode'] : 'build';

        if (!empty($asset_source['is_dev']) || $mode === 'stable') {
            return [
                'enabled' => true,
                'status_label' => 'ACTIVE',
                'reason' => isset($asset_source['label']) && is_string($asset_source['label']) && $asset_source['label'] !== ''
                    ? $asset_source['label']
                    : 'Vite Dev Server',
                'audience' => 'Browser admin saat ini',
            ];
        }

        return [
            'enabled' => false,
            'status_label' => 'INACTIVE',
            'reason' => 'Production Build',
            'audience' => 'Browser admin saat ini',
        ];
    }

    /**
     * @return array{available:bool,can_autostart:bool,reason:string,wrapper_path:string,log_file:string,pid_file:string,running:bool}
     */
    public static function get_dev_server_launcher_status(?string $url = null): array
    {
        $wrapper_path = CBT_EXAM_SYSTEM_PATH . self::VITE_DEV_WRAPPER_RELATIVE;
        $paths = self::get_dev_server_launcher_paths();

        $normalized_url = self::normalize_dev_server_url((string) ($url ?? ''));
        $available = file_exists($wrapper_path) && is_file($wrapper_path) && is_executable($wrapper_path);
        $can_autostart = $available && ($normalized_url === '' || self::is_local_dev_server_target($normalized_url));
        $reason = 'Wrapper script siap dipakai.';
        $running = false;

        if (!$available) {
            $reason = 'Wrapper script belum tersedia atau belum executable.';
        } elseif ($normalized_url !== '' && !$can_autostart) {
            $reason = 'Auto-start hanya dijalankan untuk dev server host lokal.';
        } else {
            $running = self::is_wrapper_running($wrapper_path);
        }

        return [
            'available' => $available,
            'can_autostart' => $can_autostart,
            'reason' => $reason,
            'wrapper_path' => $wrapper_path,
            'log_file' => $paths['log_file'],
            'pid_file' => $paths['pid_file'],
            'running' => $running,
        ];
    }

    /**
     * @return array{available:bool,can_autostart:bool,reason:string,wrapper_path:string,log_file:string,pid_file:string,running:bool}
     */
    public static function get_build_watch_launcher_status(): array
    {
        $wrapper_path = CBT_EXAM_SYSTEM_PATH . self::BUILD_WATCH_WRAPPER_RELATIVE;
        $paths = self::get_build_watch_launcher_paths();
        $available = file_exists($wrapper_path) && is_file($wrapper_path) && is_executable($wrapper_path);
        $running = $available ? self::is_wrapper_running($wrapper_path) : false;
        $reason = $available ? 'Wrapper script build watch siap dipakai.' : 'Wrapper script build watch belum tersedia atau belum executable.';
        $can_autostart = $available;
        if ($available) {
            $build_output_access = self::get_build_output_access_status();
            $reason = $build_output_access['message'];
            $can_autostart = $build_output_access['status'] !== 'blocked';
        }

        return [
            'available' => $available,
            'can_autostart' => $can_autostart,
            'reason' => $reason,
            'wrapper_path' => $wrapper_path,
            'log_file' => $paths['log_file'],
            'pid_file' => $paths['pid_file'],
            'running' => $running,
        ];
    }

    /**
     * @return array{health:array{status:string,message:string,checked_at:int,url:string,http_code:int},launcher:array{attempted:bool,started:bool,message:string}}
     */
    public static function ensure_dev_server_available(string $url): array
    {
        $health = self::get_dev_server_health(true, $url, true);
        $launcher = [
            'attempted' => false,
            'started' => false,
            'message' => '',
        ];

        if ($health['status'] === 'ok') {
            return [
                'health' => $health,
                'launcher' => $launcher,
            ];
        }

        $launcher = self::attempt_start_dev_server($url);
        if ($launcher['attempted']) {
            usleep(self::DEV_SERVER_AUTOSTART_WAIT_US);
            $health = self::get_dev_server_health(true, $url, true);
        }

        return [
            'health' => $health,
            'launcher' => $launcher,
        ];
    }

    /**
     * @return array{attempted:bool,stopped:bool,message:string}
     */
    public static function stop_dev_server(): array
    {
        $launcher = self::get_dev_server_launcher_status();
        if (!$launcher['available']) {
            return [
                'attempted' => false,
                'stopped' => false,
                'message' => $launcher['reason'],
            ];
        }

        $command = escapeshellarg($launcher['wrapper_path']) . ' stop';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH);
        if (!is_resource($process)) {
            return [
                'attempted' => true,
                'stopped' => false,
                'message' => 'Gagal menjalankan wrapper script stop dev server.',
            ];
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            return [
                'attempted' => true,
                'stopped' => false,
                'message' => $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Wrapper script stop mengembalikan status gagal.'),
            ];
        }

        if (strpos($stdout, 'STOPPED:') === 0) {
            self::reset_dev_server_health_snapshot('Vite dev server dihentikan.');
            return [
                'attempted' => true,
                'stopped' => true,
                'message' => 'Vite dev server dihentikan otomatis.',
            ];
        }

        if ($stdout === 'STOPPED') {
            self::reset_dev_server_health_snapshot('Vite dev server sudah tidak berjalan.');
            return [
                'attempted' => true,
                'stopped' => true,
                'message' => 'Vite dev server sudah tidak berjalan.',
            ];
        }

        return [
            'attempted' => true,
            'stopped' => false,
            'message' => $stdout !== '' ? $stdout : 'Wrapper script stop selesai tanpa status yang dikenali.',
        ];
    }

    /**
     * @return array{attempted:bool,started:bool,message:string}
     */
    public static function ensure_build_watch_available(): array
    {
        $launcher = self::get_build_watch_launcher_status();
        if (!$launcher['available']) {
            return [
                'attempted' => false,
                'started' => false,
                'message' => $launcher['reason'],
            ];
        }

        $build_output_access = self::get_build_output_access_status();
        if ($build_output_access['status'] === 'blocked') {
            return [
                'attempted' => false,
                'started' => false,
                'message' => $build_output_access['message'],
            ];
        }

        if ($build_output_access['status'] === 'preparable') {
            $prepare_result = self::prepare_build_output_for_runtime();
            if (!$prepare_result['ok']) {
                return [
                    'attempted' => false,
                    'started' => false,
                    'message' => $prepare_result['message'],
                ];
            }
        }

        $command = escapeshellarg($launcher['wrapper_path']) . ' start';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH);
        if (!is_resource($process)) {
            return [
                'attempted' => true,
                'started' => false,
                'message' => 'Gagal menjalankan wrapper script build watch.',
            ];
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            return [
                'attempted' => true,
                'started' => false,
                'message' => $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Wrapper script build watch mengembalikan status gagal.'),
            ];
        }

        if (strpos($stdout, 'STARTED:') === 0) {
            return [
                'attempted' => true,
                'started' => true,
                'message' => 'Build watch dijalankan di background.',
            ];
        }

        if (strpos($stdout, 'ALREADY_RUNNING:') === 0) {
            return [
                'attempted' => true,
                'started' => true,
                'message' => 'Build watch sudah berjalan.',
            ];
        }

        return [
            'attempted' => true,
            'started' => false,
            'message' => $stdout !== '' ? $stdout : 'Wrapper script build watch selesai tanpa status yang dikenali.',
        ];
    }

    /**
     * @return array{attempted:bool,stopped:bool,message:string}
     */
    public static function stop_build_watch(): array
    {
        $launcher = self::get_build_watch_launcher_status();
        if (!$launcher['available']) {
            return [
                'attempted' => false,
                'stopped' => false,
                'message' => $launcher['reason'],
            ];
        }

        $command = escapeshellarg($launcher['wrapper_path']) . ' stop';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH);
        if (!is_resource($process)) {
            return [
                'attempted' => true,
                'stopped' => false,
                'message' => 'Gagal menjalankan wrapper script stop build watch.',
            ];
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            return [
                'attempted' => true,
                'stopped' => false,
                'message' => $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Wrapper script stop build watch mengembalikan status gagal.'),
            ];
        }

        if (strpos($stdout, 'STOPPED:') === 0) {
            return [
                'attempted' => true,
                'stopped' => true,
                'message' => 'Build watch dihentikan otomatis.',
            ];
        }

        if ($stdout === 'STOPPED') {
            return [
                'attempted' => true,
                'stopped' => true,
                'message' => 'Build watch sudah tidak berjalan.',
            ];
        }

        return [
            'attempted' => true,
            'stopped' => false,
            'message' => $stdout !== '' ? $stdout : 'Wrapper script stop build watch selesai tanpa status yang dikenali.',
        ];
    }

    /**
     * @return array{status:string,message:string,checked_at:int,url:string,http_code:int}
     */
    public static function get_dev_server_health(bool $force = false, ?string $url = null, bool $persist = true): array
    {
        $settings = self::get_settings();
        $target_url = self::normalize_dev_server_url($url !== null ? $url : $settings['dev_server_url']);

        if ($target_url === '') {
            return [
                'status' => 'unknown',
                'message' => 'URL dev server belum diisi.',
                'checked_at' => 0,
                'url' => '',
                'http_code' => 0,
            ];
        }

        $transient_key = self::health_transient_key($target_url);
        if (!$force) {
            $cached = get_transient($transient_key);
            if (is_array($cached)) {
                return self::normalize_health_snapshot($cached, $target_url);
            }
        }

        $health_url = trailingslashit($target_url) . '@vite/client';
        $response = wp_remote_get($health_url, [
            'timeout' => 3,
            'redirection' => 0,
        ]);

        $snapshot = [
            'status' => 'unknown',
            'message' => '',
            'checked_at' => time(),
            'url' => $target_url,
            'http_code' => 0,
        ];

        if (is_wp_error($response)) {
            $snapshot['status'] = 'failed';
            $snapshot['message'] = $response->get_error_message();
        } else {
            $status_code = (int) wp_remote_retrieve_response_code($response);
            $snapshot['http_code'] = $status_code;

            if ($status_code >= 200 && $status_code < 300) {
                $snapshot['status'] = 'ok';
                $snapshot['message'] = 'Vite dev server aktif dan merespons.';
            } else {
                $snapshot['status'] = 'failed';
                $snapshot['message'] = 'Dev server merespons HTTP ' . $status_code . '.';
            }
        }

        set_transient($transient_key, $snapshot, self::DEV_SERVER_HEALTH_TTL);

        if ($persist) {
            self::persist_health_snapshot($snapshot);
        }

        return $snapshot;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';

        $settings = self::get_settings();
        $resolved_source = self::resolve_frontend_asset_source();
        $dev_server_health = [
            'status' => $settings['last_health_status'],
            'message' => $settings['last_health_message'],
            'checked_at' => $settings['last_health_checked_at'],
            'url' => $resolved_source['is_dev'] ? $resolved_source['dev_server_url'] : $settings['dev_server_url'],
            'http_code' => 0,
        ];

        if ($resolved_source['is_dev'] && $resolved_source['dev_server_url'] !== '') {
            $dev_server_health = self::get_dev_server_health(false, $resolved_source['dev_server_url'], true);
        }

        $build_manifest = self::get_build_manifest_status();
        $dev_server_launcher = self::get_dev_server_launcher_status($resolved_source['is_dev'] ? $resolved_source['dev_server_url'] : $settings['dev_server_url']);
        $build_watch_launcher = self::get_build_watch_launcher_status();
        $frontend_page_url = self::get_frontend_page_url();
        $frontend_debug = self::resolve_frontend_debug_context();
        $frontend_diagnostics = self::resolve_frontend_diagnostics_context();
        $constant_override_url = self::get_constant_override_url();
        $storage_debug_config = self::get_storage_debug_config();

        return compact(
            'build_manifest',
            'build_watch_launcher',
            'constant_override_url',
            'dev_server_health',
            'dev_server_launcher',
            'error',
            'frontend_debug',
            'frontend_diagnostics',
            'frontend_page_url',
            'notice',
            'resolved_source',
            'settings',
            'storage_debug_config'
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{mode:string,dev_server_url:string,last_health_status:string,last_health_message:string,last_health_checked_at:int}
     */
    public static function sanitize_settings_input(array $raw): array
    {
        $settings = self::get_settings();

        $mode = isset($raw['mode']) ? self::sanitize_mode((string) $raw['mode']) : $settings['mode'];
        $dev_server_url = isset($raw['dev_server_url'])
            ? self::normalize_dev_server_url((string) $raw['dev_server_url'])
            : $settings['dev_server_url'];

        return [
            'mode' => $mode,
            'dev_server_url' => $dev_server_url !== '' ? $dev_server_url : self::DEV_SERVER_DEFAULT_URL,
            'last_health_status' => $settings['last_health_status'],
            'last_health_message' => $settings['last_health_message'],
            'last_health_checked_at' => $settings['last_health_checked_at'],
        ];
    }

    /**
     * @param array{mode:string,dev_server_url:string,last_health_status:string,last_health_message:string,last_health_checked_at:int} $settings
     */
    public static function save_settings(array $settings): void
    {
        update_option(self::OPTION_KEY, $settings, false);
    }

    public static function developer_page_url(array $extra_args = []): string
    {
        $args = array_merge(['page' => 'cbt-developer'], $extra_args);
        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @return array{
     *   prefix:string,
     *   auth_session_key:string,
     *   attempt_ui_prefix:string,
     *   doubtful_prefix:string,
     *   question_cache_session_prefix:string,
     *   question_cache_meta_prefix:string,
     *   question_cache_item_prefix:string,
     *   indexed_db_name:string,
     *   diagnostics_requests_key:string,
     *   diagnostics_snapshot_key:string,
     *   diagnostics_sync_key:string,
     *   diagnostics_timeline_key:string,
     *   diagnostics_scenario_key:string,
     *   diagnostics_errors_key:string,
     *   diagnostics_state_key:string,
     *   diagnostics_render_stats_key:string,
     *   diagnostics_action_trail_key:string,
     *   diagnostics_command_key:string,
     *   diagnostics_max_entries:int,
     *   diagnostics_timeline_max_entries:int
     * }
     */
    public static function get_storage_debug_config(): array
    {
        return [
            'prefix' => self::FRONTEND_STORAGE_PREFIX,
            'auth_session_key' => self::FRONTEND_AUTH_SESSION_STORAGE_KEY,
            'attempt_ui_prefix' => self::FRONTEND_ATTEMPT_UI_SESSION_PREFIX,
            'doubtful_prefix' => self::FRONTEND_DOUBTFUL_SESSION_PREFIX,
            'question_cache_session_prefix' => self::FRONTEND_QUESTION_CACHE_SESSION_PREFIX,
            'question_cache_meta_prefix' => self::FRONTEND_QUESTION_CACHE_META_LOCAL_STORAGE_PREFIX,
            'question_cache_item_prefix' => self::FRONTEND_QUESTION_CACHE_ITEM_LOCAL_STORAGE_PREFIX,
            'indexed_db_name' => self::FRONTEND_INDEXED_DB_NAME,
            'diagnostics_requests_key' => self::FRONTEND_DIAGNOSTICS_REQUESTS_KEY,
            'diagnostics_snapshot_key' => self::FRONTEND_DIAGNOSTICS_SNAPSHOT_KEY,
            'diagnostics_sync_key' => self::FRONTEND_DIAGNOSTICS_SYNC_KEY,
            'diagnostics_timeline_key' => self::FRONTEND_DIAGNOSTICS_TIMELINE_KEY,
            'diagnostics_scenario_key' => self::FRONTEND_DIAGNOSTICS_SCENARIO_KEY,
            'diagnostics_errors_key' => self::FRONTEND_DIAGNOSTICS_ERRORS_KEY,
            'diagnostics_state_key' => self::FRONTEND_DIAGNOSTICS_STATE_KEY,
            'diagnostics_render_stats_key' => self::FRONTEND_DIAGNOSTICS_RENDER_STATS_KEY,
            'diagnostics_action_trail_key' => self::FRONTEND_DIAGNOSTICS_ACTION_TRAIL_KEY,
            'diagnostics_command_key' => self::FRONTEND_DIAGNOSTICS_COMMAND_KEY,
            'diagnostics_max_entries' => self::FRONTEND_DIAGNOSTICS_MAX_ENTRIES,
            'diagnostics_timeline_max_entries' => self::FRONTEND_DIAGNOSTICS_TIMELINE_MAX_ENTRIES,
        ];
    }

    /**
     * @return array{exists:bool,message:string,entry_file:string,css_files:array<int,string>}
     */
    public static function get_build_manifest_status(): array
    {
        $manifest_path = CBT_EXAM_SYSTEM_PATH . self::VITE_MANIFEST_RELATIVE;
        if (!file_exists($manifest_path)) {
            return [
                'exists' => false,
                'message' => 'Manifest build frontend belum ditemukan.',
                'entry_file' => '',
                'css_files' => [],
            ];
        }

        $contents = file_get_contents($manifest_path);
        if (!is_string($contents) || $contents === '') {
            return [
                'exists' => false,
                'message' => 'Manifest build frontend kosong atau gagal dibaca.',
                'entry_file' => '',
                'css_files' => [],
            ];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [
                'exists' => false,
                'message' => 'Manifest build frontend tidak valid.',
                'entry_file' => '',
                'css_files' => [],
            ];
        }

        $entry = self::get_manifest_entry($decoded, self::VITE_ENTRY);
        if ($entry === null || empty($entry['file']) || !is_string($entry['file'])) {
            return [
                'exists' => false,
                'message' => 'Entry frontend utama tidak ditemukan di manifest.',
                'entry_file' => '',
                'css_files' => [],
            ];
        }

        $css_files = [];
        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                if (is_string($css_file) && $css_file !== '') {
                    $css_files[] = $css_file;
                }
            }
        }

        return [
            'exists' => true,
            'message' => 'Manifest build frontend siap dipakai.',
            'entry_file' => $entry['file'],
            'css_files' => array_values(array_unique($css_files)),
        ];
    }

    public static function get_frontend_page_url(): string
    {
        $page_id = (int) get_option(self::FRONTEND_PAGE_OPTION, 0);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        $page = get_page_by_path('cbt-ujian', OBJECT, 'page');
        if ($page instanceof WP_Post) {
            $permalink = get_permalink($page);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        return '';
    }

    private static function sanitize_mode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        if ($normalized === 'dev') {
            return 'dev';
        }
        if ($normalized === 'stable') {
            return 'stable';
        }
        return 'build';
    }

    private static function get_tmp_dir(): string
    {
        $tmp_dir = rtrim((string) sys_get_temp_dir(), '/');
        return $tmp_dir !== '' ? $tmp_dir : '/tmp';
    }

    /**
     * @return array{log_file:string,pid_file:string,cache_dir:string}
     */
    private static function get_dev_server_launcher_paths(): array
    {
        $tmp_dir = self::get_tmp_dir();
        $user_label = self::get_runtime_user_label();

        return [
            'log_file' => $tmp_dir . '/cbt-vite-dev-' . $user_label . '.log',
            'pid_file' => $tmp_dir . '/cbt-vite-dev-' . $user_label . '.pid',
            'cache_dir' => $tmp_dir . '/cbt-vite-cache-' . $user_label . '/dev',
        ];
    }

    /**
     * @return array{log_file:string,pid_file:string,cache_dir:string}
     */
    private static function get_build_watch_launcher_paths(): array
    {
        $tmp_dir = self::get_tmp_dir();
        $user_label = self::get_runtime_user_label();

        return [
            'log_file' => $tmp_dir . '/cbt-vite-build-watch-' . $user_label . '.log',
            'pid_file' => $tmp_dir . '/cbt-vite-build-watch-' . $user_label . '.pid',
            'cache_dir' => $tmp_dir . '/cbt-vite-cache-' . $user_label . '/build-watch',
        ];
    }

    private static function get_runtime_user_label(): string
    {
        $label = '';
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $user_info = posix_getpwuid(posix_geteuid());
            if (is_array($user_info) && !empty($user_info['name']) && is_string($user_info['name'])) {
                $label = $user_info['name'];
            }
        }

        if ($label === '') {
            $env_user = getenv('USER');
            if (is_string($env_user) && trim($env_user) !== '') {
                $label = trim($env_user);
            }
        }

        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', $label);
        $sanitized = is_string($sanitized) ? trim($sanitized, '-') : '';

        return $sanitized !== '' ? $sanitized : 'process';
    }

    private static function normalize_dev_server_url(string $url): string
    {
        $normalized = trim($url);
        if ($normalized === '') {
            return '';
        }

        $parts = wp_parse_url($normalized);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host = isset($parts['host']) ? trim((string) $parts['host']) : '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : 0;
        $path = isset($parts['path']) ? '/' . ltrim((string) $parts['path'], '/') : '';

        $rebuilt = $scheme . '://' . $host;
        if ($port > 0) {
            $rebuilt .= ':' . $port;
        }
        if ($path !== '/') {
            $rebuilt .= rtrim($path, '/');
        }

        return rtrim($rebuilt, '/');
    }

    private static function health_transient_key(string $url): string
    {
        return self::DEV_SERVER_HEALTH_TRANSIENT_PREFIX . md5($url);
    }

    /**
     * @return array{status:string,message:string}
     */
    private static function get_build_output_access_status(): array
    {
        $public_dir = CBT_EXAM_SYSTEM_PATH . 'public';
        $build_dir = CBT_EXAM_SYSTEM_PATH . 'public/build';
        $runtime_user = self::get_runtime_user_label();

        return self::inspect_build_output_access($public_dir, $build_dir, $runtime_user);
    }

    /**
     * @return array{status:string,message:string}
     */
    private static function inspect_build_output_access(string $public_dir, string $build_dir, string $runtime_user): array
    {
        if (!is_dir($public_dir)) {
            return [
                'status' => 'blocked',
                'message' => 'Folder public belum tersedia untuk stable build.',
            ];
        }

        if (!is_dir($build_dir)) {
            if (is_writable($public_dir)) {
                return [
                    'status' => 'preparable',
                    'message' => 'Folder public/build akan dibuat saat start oleh proses ' . $runtime_user . '.',
                ];
            }

            return [
                'status' => 'blocked',
                'message' => 'Folder public/build belum tersedia dan parent folder public tidak writable oleh proses ' . $runtime_user . '.',
            ];
        }

        $assets_dir = $build_dir . '/assets';
        if (is_writable($build_dir) && (!is_dir($assets_dir) || is_writable($assets_dir))) {
            return [
                'status' => 'ok',
                'message' => 'Folder build writable.',
            ];
        }

        if (is_writable($public_dir)) {
            return [
                'status' => 'preparable',
                'message' => 'Folder public/build akan disiapkan ulang saat start oleh proses ' . $runtime_user . '.',
            ];
        }

        return [
            'status' => 'blocked',
            'message' => 'Folder public/build belum writable oleh proses ' . $runtime_user . ' dan parent folder public juga tidak writable.',
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private static function prepare_build_output_for_runtime(?string $public_dir = null, ?string $build_dir = null, ?string $runtime_user = null): array
    {
        $public_dir = is_string($public_dir) && $public_dir !== '' ? $public_dir : (CBT_EXAM_SYSTEM_PATH . 'public');
        $build_dir = is_string($build_dir) && $build_dir !== '' ? $build_dir : (CBT_EXAM_SYSTEM_PATH . 'public/build');
        $runtime_user = is_string($runtime_user) && $runtime_user !== '' ? $runtime_user : self::get_runtime_user_label();
        $access = self::inspect_build_output_access($public_dir, $build_dir, $runtime_user);

        if ($access['status'] === 'ok') {
            return [
                'ok' => true,
                'message' => 'Folder build sudah writable.',
            ];
        }

        if ($access['status'] === 'blocked') {
            return [
                'ok' => false,
                'message' => $access['message'],
            ];
        }

        if (is_dir($build_dir)) {
            $backup_dir = $public_dir . '/.cbt-build-backup-' . $runtime_user . '-' . gmdate('YmdHis');
            if (!@rename($build_dir, $backup_dir)) {
                return [
                    'ok' => false,
                    'message' => 'Folder public/build tidak bisa disiapkan ulang otomatis oleh proses ' . $runtime_user . '.',
                ];
            }
        }

        if (!is_dir($build_dir) && !wp_mkdir_p($build_dir)) {
            return [
                'ok' => false,
                'message' => 'Folder public/build gagal dibuat ulang untuk stable build.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Folder public/build berhasil disiapkan ulang untuk proses ' . $runtime_user . '.',
        ];
    }

    /**
     * @return array{attempted:bool,started:bool,message:string}
     */
    private static function attempt_start_dev_server(string $url): array
    {
        $launcher = self::get_dev_server_launcher_status($url);
        if (!$launcher['available']) {
            return [
                'attempted' => false,
                'started' => false,
                'message' => $launcher['reason'],
            ];
        }

        if (!$launcher['can_autostart']) {
            return [
                'attempted' => false,
                'started' => false,
                'message' => $launcher['reason'],
            ];
        }

        $command = escapeshellarg($launcher['wrapper_path']) . ' start';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH);
        if (!is_resource($process)) {
            return [
                'attempted' => true,
                'started' => false,
                'message' => 'Gagal menjalankan wrapper script dev server.',
            ];
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            return [
                'attempted' => true,
                'started' => false,
                'message' => $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Wrapper script mengembalikan status gagal.'),
            ];
        }

        if (strpos($stdout, 'STARTED:') === 0) {
            return [
                'attempted' => true,
                'started' => true,
                'message' => 'Auto-start Vite dijalankan di background.',
            ];
        }

        if (strpos($stdout, 'ALREADY_RUNNING:') === 0) {
            return [
                'attempted' => true,
                'started' => true,
                'message' => 'Vite dev server sudah berjalan.',
            ];
        }

        return [
            'attempted' => true,
            'started' => false,
            'message' => $stdout !== '' ? $stdout : 'Wrapper script selesai tanpa status yang dikenali.',
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array{status:string,message:string,checked_at:int,url:string,http_code:int}
     */
    private static function normalize_health_snapshot(array $snapshot, string $fallback_url): array
    {
        $status = isset($snapshot['status']) ? sanitize_key((string) $snapshot['status']) : 'unknown';
        if (!in_array($status, ['ok', 'failed', 'unknown'], true)) {
            $status = 'unknown';
        }

        return [
            'status' => $status,
            'message' => isset($snapshot['message']) ? sanitize_text_field((string) $snapshot['message']) : '',
            'checked_at' => isset($snapshot['checked_at']) ? max(0, (int) $snapshot['checked_at']) : 0,
            'url' => isset($snapshot['url']) && is_string($snapshot['url']) && $snapshot['url'] !== ''
                ? $snapshot['url']
                : $fallback_url,
            'http_code' => isset($snapshot['http_code']) ? (int) $snapshot['http_code'] : 0,
        ];
    }

    /**
     * @param array{status:string,message:string,checked_at:int,url:string,http_code:int} $snapshot
     */
    private static function persist_health_snapshot(array $snapshot): void
    {
        $settings = self::get_settings();
        $settings['last_health_status'] = $snapshot['status'];
        $settings['last_health_message'] = $snapshot['message'];
        $settings['last_health_checked_at'] = $snapshot['checked_at'];
        update_option(self::OPTION_KEY, $settings, false);
    }

    public static function reset_dev_server_health_snapshot(string $message = ''): void
    {
        $settings = self::get_settings();
        if ($settings['dev_server_url'] !== '') {
            delete_transient(self::health_transient_key($settings['dev_server_url']));
        }

        $settings['last_health_status'] = 'unknown';
        $settings['last_health_message'] = trim($message);
        $settings['last_health_checked_at'] = time();

        update_option(self::OPTION_KEY, $settings, false);
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>|null
     */
    private static function get_manifest_entry(array $manifest, string $entry_key): ?array
    {
        if (isset($manifest[$entry_key]) && is_array($manifest[$entry_key])) {
            return $manifest[$entry_key];
        }

        foreach ($manifest as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if ($key === basename($entry_key)) {
                return $value;
            }

            if (!empty($value['src']) && is_string($value['src']) && ltrim($value['src'], './') === ltrim($entry_key, './')) {
                return $value;
            }
        }

        return null;
    }

    private static function is_local_dev_server_target(string $url): bool
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(trim($host));
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return true;
        }

        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (is_string($site_host) && $site_host !== '' && strtolower($site_host) === $host) {
            return true;
        }

        if (!empty($_SERVER['SERVER_ADDR']) && is_string($_SERVER['SERVER_ADDR']) && strtolower((string) $_SERVER['SERVER_ADDR']) === $host) {
            return true;
        }

        return false;
    }

    private static function is_wrapper_running(string $wrapper_path): bool
    {
        $command = escapeshellarg($wrapper_path) . ' status';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, CBT_EXAM_SYSTEM_PATH);
        if (!is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        $stdout = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0 && $stderr !== '') {
            return false;
        }

        return strpos($stdout, 'RUNNING:') === 0;
    }
}
