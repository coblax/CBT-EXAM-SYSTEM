<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Update_Backup_Service')) {
    require_once __DIR__ . '/class-cbt-update-backup-service.php';
}
if (!class_exists('CBT_Update_Health_Service')) {
    require_once __DIR__ . '/class-cbt-update-health-service.php';
}

final class CBT_Update_Job_Service
{
    private const OPTION_JOBS = 'cbt_update_jobs_v1';
    private const OPTION_ACTIVE_JOB = 'cbt_update_active_job_v1';
    private const OPTION_HISTORY = 'cbt_update_history_v1';
    private const MAX_JOBS = 8;
    private const MAX_HISTORY = 20;

    /**
     * @return array<string,mixed>
     */
    public static function start_check(string $source = 'ajax'): array
    {
        $job = self::build_job([
            'type' => 'check',
            'source' => $source,
            'stage' => 'fetch_release',
            'message' => 'Cek update masuk antrean.',
            'progress_percent' => 5,
        ]);

        self::save_job($job);
        return $job;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function start_install(string $source = 'ajax')
    {
        $state = CBT_Update_Release_Helper::get_release_state(true);
        $ready = CBT_Update_Release_Helper::validate_install_ready($state);
        if (is_wp_error($ready)) {
            return $ready;
        }

        $manifest = isset($state['manifest']) && is_array($state['manifest']) ? $state['manifest'] : [];
        $job = self::build_job([
            'type' => 'install',
            'source' => $source,
            'stage' => 'download',
            'message' => 'Update siap diunduh.',
            'progress_percent' => 10,
            'source_version' => CBT_Update_Release_Helper::current_version(),
            'target_version' => (string) ($manifest['version'] ?? ''),
            'manifest' => $manifest,
            'release_state' => $state,
        ]);

        self::save_job($job);
        return $job;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function start_rollback(string $backup_id, string $source = 'ajax')
    {
        $backup = CBT_Update_Backup_Service::get_backup($backup_id);
        if (!is_array($backup)) {
            return new WP_Error('backup_not_found', 'Backup rollback tidak ditemukan.');
        }

        $valid = CBT_Update_Backup_Service::validate_backup_for_rollback($backup);
        if (is_wp_error($valid)) {
            return $valid;
        }

        $version = (string) ($backup['version'] ?? '');
        $manifest = [
            'version' => $version,
            'tag' => 'rollback-' . $version,
            'download_url' => (string) ($backup['path'] ?? ''),
            'sha256' => (string) ($backup['sha256'] ?? ''),
            'requires_php' => '',
            'requires_wp' => '',
            'tested_up_to' => '',
            'changelog' => 'Rollback dari backup lokal.',
        ];

        $job = self::build_job([
            'type' => 'rollback',
            'source' => $source,
            'stage' => 'rollback',
            'message' => 'Rollback siap dijalankan.',
            'progress_percent' => 15,
            'source_version' => CBT_Update_Release_Helper::current_version(),
            'target_version' => $version,
            'manifest' => $manifest,
            'package_path' => (string) ($backup['path'] ?? ''),
            'backup' => $backup,
            'detail' => [
                'rollback_backup_id' => (string) ($backup['id'] ?? ''),
            ],
        ]);

        self::save_job($job);
        return $job;
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(string $token): array
    {
        $job = self::get_job($token);
        if (!is_array($job)) {
            return self::missing_job_response($token);
        }

        if (in_array((string) ($job['status'] ?? ''), ['completed', 'failed', 'failed_health'], true)) {
            return $job;
        }

        $type = (string) ($job['type'] ?? 'check');
        if ($type === 'check') {
            return self::tick_check($job);
        }
        if ($type === 'rollback') {
            return self::tick_rollback($job);
        }

        return self::tick_install($job);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_job(string $token): ?array
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return null;
        }

        $jobs = self::get_jobs();
        return isset($jobs[$token]) && is_array($jobs[$token]) ? self::normalize_job($jobs[$token]) : null;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_jobs(): array
    {
        $jobs = get_option(self::OPTION_JOBS, []);
        if (!is_array($jobs)) {
            return [];
        }

        $normalized = [];
        foreach ($jobs as $token => $job) {
            if (!is_array($job)) {
                continue;
            }
            $job = self::normalize_job($job);
            if ($job['token'] !== '') {
                $normalized[$job['token']] = $job;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_active_job(): ?array
    {
        $token = sanitize_key((string) get_option(self::OPTION_ACTIVE_JOB, ''));
        return $token !== '' ? self::get_job($token) : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_history(): array
    {
        $history = get_option(self::OPTION_HISTORY, []);
        if (!is_array($history)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($entry): ?array {
            return is_array($entry) ? $entry : null;
        }, $history)));
    }

    public static function clear_finished_job(string $token): bool
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return false;
        }

        $jobs = self::get_jobs();
        if (!isset($jobs[$token])) {
            return false;
        }

        $status = (string) ($jobs[$token]['status'] ?? '');
        if (!in_array($status, ['completed', 'failed', 'failed_health'], true)) {
            return false;
        }

        unset($jobs[$token]);
        self::save_jobs($jobs);
        if ((string) get_option(self::OPTION_ACTIVE_JOB, '') === $token) {
            delete_option(self::OPTION_ACTIVE_JOB);
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public static function response_for_job(array $job, string $operation = 'status'): array
    {
        $job = self::normalize_job($job);
        $complete = in_array($job['status'], ['completed', 'failed', 'failed_health'], true);

        return [
            'operation' => sanitize_key($operation),
            'token' => $job['token'],
            'status' => $job['status'],
            'stage' => $job['stage'],
            'complete' => $complete,
            'progress_percent' => max(0, min(100, (int) ($job['progress_percent'] ?? 0))),
            'status_label' => self::status_label($job),
            'message' => (string) ($job['message'] ?? ''),
            'detail' => is_array($job['detail'] ?? null) ? $job['detail'] : [],
            'history' => self::get_history(),
            'backups' => CBT_Update_Backup_Service::get_backups(),
            'redirect_url' => admin_url('admin.php?page=cbt-update&cbt_update_token=' . rawurlencode($job['token'])),
        ];
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private static function build_job(array $overrides): array
    {
        $token = sanitize_key((string) ($overrides['token'] ?? ''));
        if ($token === '') {
            $token = self::generate_token();
        }

        $now_ts = time();
        $now = wp_date('Y-m-d H:i:s', $now_ts, wp_timezone());

        return self::normalize_job(array_merge([
            'token' => $token,
            'type' => 'check',
            'source' => 'ajax',
            'status' => 'running',
            'stage' => 'fetch_release',
            'progress_percent' => 0,
            'message' => '',
            'detail' => [],
            'manifest' => [],
            'release_state' => [],
            'package_path' => '',
            'source_version' => CBT_Update_Release_Helper::current_version(),
            'target_version' => '',
            'backup' => [],
            'created_at' => $now,
            'updated_at' => $now,
            'created_at_ts' => $now_ts,
            'updated_at_ts' => $now_ts,
            'started_by' => get_current_user_id(),
        ], $overrides));
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function tick_check(array $job): array
    {
        try {
            $state = CBT_Update_Release_Helper::get_release_state(true);
            $job['release_state'] = $state;
            $job['manifest'] = isset($state['manifest']) && is_array($state['manifest']) ? $state['manifest'] : [];
            $job['target_version'] = (string) ($job['manifest']['version'] ?? '');
            return self::complete_job($job, 'Cek update selesai.');
        } catch (Throwable $throwable) {
            return self::fail_job($job, 'fetch_release', $throwable->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function tick_install(array $job): array
    {
        $stage = (string) ($job['stage'] ?? 'download');
        if ((string) ($job['status'] ?? '') === 'reload_required') {
            $job['status'] = 'running';
            $stage = 'post_health';
        }

        switch ($stage) {
            case 'download':
                return self::stage_download($job);
            case 'validate':
                return self::stage_validate($job, 'backup');
            case 'backup':
                return self::stage_backup($job);
            case 'install':
                return self::stage_install($job);
            case 'post_health':
                return self::stage_post_health($job);
            case 'cleanup':
                return self::stage_cleanup($job);
            default:
                return self::fail_job($job, $stage, 'Stage update tidak dikenal.');
        }
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function tick_rollback(array $job): array
    {
        $stage = (string) ($job['stage'] ?? 'rollback');
        if ((string) ($job['status'] ?? '') === 'reload_required') {
            $job['status'] = 'running';
            $stage = 'post_health';
        }

        switch ($stage) {
            case 'rollback':
                return self::stage_rollback($job);
            case 'post_health':
                return self::stage_post_health($job);
            case 'cleanup':
                return self::stage_cleanup($job);
            default:
                return self::fail_job($job, $stage, 'Stage rollback tidak dikenal.');
        }
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_download(array $job): array
    {
        $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
        $download_url = (string) ($manifest['download_url'] ?? '');
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $package_path = download_url($download_url);
        if (is_wp_error($package_path)) {
            return self::fail_job($job, 'download', 'Gagal mengunduh package update: ' . $package_path->get_error_message());
        }

        $job['package_path'] = (string) $package_path;
        $job['stage'] = 'validate';
        $job['progress_percent'] = 30;
        $job['message'] = 'Package update berhasil diunduh.';
        return self::save_job($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_validate(array $job, string $next_stage): array
    {
        $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
        $package_path = (string) ($job['package_path'] ?? '');
        $validation = CBT_Update_Release_Helper::validate_downloaded_package(
            $package_path,
            (string) ($manifest['sha256'] ?? ''),
            (string) ($manifest['version'] ?? '')
        );
        if (is_wp_error($validation)) {
            return self::fail_job($job, 'validate', $validation->get_error_message());
        }

        $actual_hash = hash_file('sha256', $package_path);
        $job['detail'] = array_merge(is_array($job['detail'] ?? null) ? $job['detail'] : [], [
            'actual_sha256' => is_string($actual_hash) ? strtolower($actual_hash) : '',
            'package_size' => file_exists($package_path) ? (filesize($package_path) ?: 0) : 0,
        ]);
        $job['stage'] = $next_stage;
        $job['progress_percent'] = 45;
        $job['message'] = 'Package update lolos validasi checksum dan struktur.';
        return self::save_job($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_backup(array $job): array
    {
        $backup = CBT_Update_Backup_Service::create_backup((string) ($job['token'] ?? ''), (string) ($job['source_version'] ?? ''));
        if (is_wp_error($backup)) {
            return self::fail_job($job, 'backup', $backup->get_error_message());
        }

        $job['backup'] = $backup;
        $job['stage'] = 'install';
        $job['progress_percent'] = 60;
        $job['message'] = 'Backup versi lokal berhasil dibuat.';
        return self::save_job($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_install(array $job): array
    {
        $result = self::run_plugin_upgrade($job);
        if (is_wp_error($result)) {
            return self::fail_job($job, 'install', $result->get_error_message());
        }

        $job['status'] = 'reload_required';
        $job['stage'] = 'post_health';
        $job['progress_percent'] = 82;
        $job['message'] = 'Install update selesai. Halaman admin perlu reload sebelum health check.';
        return self::save_job($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_rollback(array $job): array
    {
        $backup = is_array($job['backup'] ?? null) ? $job['backup'] : [];
        $valid = CBT_Update_Backup_Service::validate_backup_for_rollback($backup);
        if (is_wp_error($valid)) {
            return self::fail_job($job, 'rollback', $valid->get_error_message());
        }

        $result = self::run_plugin_upgrade($job);
        if (is_wp_error($result)) {
            return self::fail_job($job, 'rollback', $result->get_error_message());
        }

        $job['status'] = 'reload_required';
        $job['stage'] = 'post_health';
        $job['progress_percent'] = 82;
        $job['message'] = 'Rollback selesai. Halaman admin perlu reload sebelum health check.';
        return self::save_job($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_post_health(array $job): array
    {
        $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
        $health = CBT_Update_Health_Service::run($manifest, $job);
        $job['detail'] = array_merge(is_array($job['detail'] ?? null) ? $job['detail'] : [], [
            'health' => $health,
        ]);

        if (empty($health['ok'])) {
            $job['status'] = 'failed_health';
            $job['stage'] = 'post_health';
            $job['progress_percent'] = 90;
            $job['message'] = (string) ($health['message'] ?? 'Post-update health check gagal.');
            $job = self::save_job($job);
            self::append_history($job);
            return $job;
        }

        $job['stage'] = 'cleanup';
        $job['progress_percent'] = 94;
        $job['message'] = 'Post-update health check selesai.';
        return self::save_job($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function stage_cleanup(array $job): array
    {
        $package_path = (string) ($job['package_path'] ?? '');
        $backup_path = (string) ($job['backup']['path'] ?? '');
        if ($package_path !== '' && $package_path !== $backup_path && file_exists($package_path)) {
            @unlink($package_path);
        }

        CBT_Update_Release_Helper::clear_cached_release_state();
        return self::complete_job($job, 'Update CBT selesai.');
    }

    /**
     * @param array<string,mixed> $job
     * @return true|WP_Error
     */
    private static function run_plugin_upgrade(array $job)
    {
        $package_path = (string) ($job['package_path'] ?? '');
        if ($package_path === '' || !file_exists($package_path)) {
            return new WP_Error('package_missing', 'Package update tidak ditemukan saat install.');
        }

        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        }

        $previous_updates = get_site_transient('update_plugins');
        try {
            $updates = is_object($previous_updates) ? clone $previous_updates : (object) [];
            if (!isset($updates->response) || !is_array($updates->response)) {
                $updates->response = [];
            }

            $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
            $updates->response[CBT_Update_Release_Helper::plugin_basename()] = CBT_Update_Release_Helper::build_update_response_object($manifest, $package_path);
            set_site_transient('update_plugins', $updates);

            $skin = class_exists('Automatic_Upgrader_Skin') ? new Automatic_Upgrader_Skin() : null;
            $upgrader = $skin instanceof WP_Upgrader_Skin ? new Plugin_Upgrader($skin) : new Plugin_Upgrader();
            $result = $upgrader->upgrade(CBT_Update_Release_Helper::plugin_basename());
        } catch (Throwable $throwable) {
            $result = new WP_Error('install_failed', $throwable->getMessage());
        } finally {
            if (is_object($previous_updates)) {
                set_site_transient('update_plugins', $previous_updates);
            } else {
                delete_site_transient('update_plugins');
            }
        }

        if (is_wp_error($result)) {
            return $result;
        }
        if ($result === false) {
            return new WP_Error('install_failed', 'WordPress upgrader tidak menyelesaikan update plugin.');
        }

        return true;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function complete_job(array $job, string $message): array
    {
        $job['status'] = 'completed';
        $job['stage'] = 'cleanup';
        $job['progress_percent'] = 100;
        $job['message'] = $message;
        $job = self::save_job($job);
        self::append_history($job);
        return $job;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function fail_job(array $job, string $stage, string $message): array
    {
        $job['status'] = 'failed';
        $job['stage'] = sanitize_key($stage);
        $job['message'] = sanitize_text_field($message);
        $job['detail'] = array_merge(is_array($job['detail'] ?? null) ? $job['detail'] : [], [
            'error_stage' => sanitize_key($stage),
            'error_message' => sanitize_text_field($message),
        ]);
        $job = self::save_job($job);
        self::append_history($job);
        return $job;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function save_job(array $job): array
    {
        $job = self::normalize_job($job);
        $job['updated_at_ts'] = time();
        $job['updated_at'] = wp_date('Y-m-d H:i:s', (int) $job['updated_at_ts'], wp_timezone());

        $jobs = self::get_jobs();
        $jobs[$job['token']] = $job;
        uasort($jobs, static function (array $left, array $right): int {
            return (int) ($right['updated_at_ts'] ?? 0) <=> (int) ($left['updated_at_ts'] ?? 0);
        });
        $jobs = array_slice($jobs, 0, self::MAX_JOBS, true);

        self::save_jobs($jobs);
        update_option(self::OPTION_ACTIVE_JOB, $job['token'], false);
        return $job;
    }

    /**
     * @param array<string,array<string,mixed>> $jobs
     */
    private static function save_jobs(array $jobs): void
    {
        update_option(self::OPTION_JOBS, $jobs, false);
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function append_history(array $job): void
    {
        $job = self::normalize_job($job);
        $detail = is_array($job['detail'] ?? null) ? $job['detail'] : [];
        $history = self::get_history();
        array_unshift($history, [
            'token' => $job['token'],
            'type' => $job['type'],
            'status' => $job['status'],
            'source_version' => $job['source_version'],
            'target_version' => $job['target_version'],
            'user_id' => (int) ($job['started_by'] ?? 0),
            'created_at' => $job['created_at'],
            'finished_at' => wp_date('Y-m-d H:i:s', time(), wp_timezone()),
            'checksum' => (string) ($detail['actual_sha256'] ?? ($job['manifest']['sha256'] ?? '')),
            'backup_file' => (string) ($job['backup']['file_name'] ?? ''),
            'message' => (string) ($job['message'] ?? ''),
        ]);

        update_option(self::OPTION_HISTORY, array_slice($history, 0, self::MAX_HISTORY), false);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function normalize_job(array $job): array
    {
        $detail = isset($job['detail']) && is_array($job['detail']) ? $job['detail'] : [];
        $manifest = isset($job['manifest']) && is_array($job['manifest']) ? $job['manifest'] : [];
        $release_state = isset($job['release_state']) && is_array($job['release_state']) ? $job['release_state'] : [];
        $backup = isset($job['backup']) && is_array($job['backup']) ? $job['backup'] : [];

        $status = sanitize_key((string) ($job['status'] ?? 'running'));
        if (!in_array($status, ['running', 'paused', 'reload_required', 'completed', 'failed', 'failed_health'], true)) {
            $status = 'running';
        }

        return [
            'token' => sanitize_key((string) ($job['token'] ?? '')),
            'type' => sanitize_key((string) ($job['type'] ?? 'check')),
            'source' => sanitize_key((string) ($job['source'] ?? 'ajax')),
            'status' => $status,
            'stage' => sanitize_key((string) ($job['stage'] ?? 'fetch_release')),
            'progress_percent' => max(0, min(100, (int) ($job['progress_percent'] ?? 0))),
            'message' => sanitize_text_field((string) ($job['message'] ?? '')),
            'detail' => $detail,
            'manifest' => $manifest,
            'release_state' => $release_state,
            'package_path' => (string) ($job['package_path'] ?? ''),
            'source_version' => sanitize_text_field((string) ($job['source_version'] ?? '')),
            'target_version' => sanitize_text_field((string) ($job['target_version'] ?? '')),
            'backup' => $backup,
            'created_at' => sanitize_text_field((string) ($job['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($job['updated_at'] ?? '')),
            'created_at_ts' => max(0, (int) ($job['created_at_ts'] ?? 0)),
            'updated_at_ts' => max(0, (int) ($job['updated_at_ts'] ?? 0)),
            'started_by' => max(0, (int) ($job['started_by'] ?? 0)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function missing_job_response(string $token): array
    {
        return self::normalize_job([
            'token' => sanitize_key($token),
            'status' => 'failed',
            'stage' => 'status',
            'progress_percent' => 0,
            'message' => 'Job update tidak ditemukan.',
        ]);
    }

    private static function status_label(array $job): string
    {
        $status = (string) ($job['status'] ?? 'running');
        return match ($status) {
            'completed' => 'Completed',
            'failed' => 'Failed',
            'failed_health' => 'Health Failed',
            'reload_required' => 'Reload Required',
            'paused' => 'Paused',
            default => 'Running',
        };
    }

    private static function generate_token(): string
    {
        if (function_exists('wp_generate_password')) {
            return sanitize_key(wp_generate_password(20, false, false));
        }

        return sanitize_key(bin2hex(random_bytes(10)));
    }
}
