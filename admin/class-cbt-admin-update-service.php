<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Update_Job_Service')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-update-job-service.php';
}

final class CBT_Admin_Update_Service
{
    private const TEST_AJAX_SIGNAL = 'cbt_update_ajax_response';

    public static function can_manage_updates(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $release_state = CBT_Update_Release_Helper::get_cached_release_state();
        $status = is_array($release_state) ? (string) ($release_state['status'] ?? 'not_checked') : 'not_checked';
        $manifest = is_array($release_state) && isset($release_state['manifest']) && is_array($release_state['manifest'])
            ? $release_state['manifest']
            : [];
        $release = is_array($release_state) && isset($release_state['release']) && is_array($release_state['release'])
            ? $release_state['release']
            : [];
        $preflight = is_array($release_state) && isset($release_state['preflight']) && is_array($release_state['preflight'])
            ? $release_state['preflight']
            : ['status' => 'blocked', 'items' => [], 'has_blocked' => false];
        $current_version = CBT_Update_Release_Helper::current_version();
        $remote_version = isset($manifest['version']) ? (string) $manifest['version'] : '';
        $has_checked_state = is_array($release_state);
        $has_update = $remote_version !== '' && version_compare($remote_version, $current_version, '>');
        $can_install = $has_update && (string) ($preflight['status'] ?? 'blocked') !== 'blocked' && $status !== 'check_failed';
        $status_meta = self::status_meta($status);
        $preflight_meta = self::preflight_meta((string) ($preflight['status'] ?? 'blocked'));
        $release_message = is_array($release_state) ? (string) ($release_state['error_message'] ?? '') : '';
        $selected_job_token = isset($query['cbt_update_token']) ? sanitize_key((string) wp_unslash((string) $query['cbt_update_token'])) : '';
        $selected_job = $selected_job_token !== '' ? CBT_Update_Job_Service::get_job($selected_job_token) : CBT_Update_Job_Service::get_active_job();

        return [
            'notice' => $notice,
            'error' => $error,
            'current_version' => $current_version,
            'remote_version' => $remote_version,
            'has_update' => $has_update,
            'has_checked_state' => $has_checked_state,
            'can_install' => $can_install,
            'status' => $status,
            'status_meta' => $status_meta,
            'preflight_meta' => $preflight_meta,
            'release_state' => $release_state,
            'release' => $release,
            'manifest' => $manifest,
            'preflight' => $preflight,
            'check_action_url' => admin_url('admin-post.php'),
            'install_action_url' => admin_url('admin-post.php'),
            'page_url' => self::page_url(),
            'update_operation_nonce' => wp_create_nonce('cbt_update_operation'),
            'update_operation_ajax_action' => 'cbt_update_operation',
            'selected_update_job' => is_array($selected_job) ? CBT_Update_Job_Service::response_for_job($selected_job, 'status') : null,
            'update_history' => CBT_Update_Job_Service::get_history(),
            'update_backups' => CBT_Update_Backup_Service::get_backups(),
            'repo_label' => CBT_Update_Release_Helper::repository_slug(),
            'repo_url' => CBT_Update_Release_Helper::repository_url(),
            'release_url' => isset($release['html_url']) && is_string($release['html_url']) && $release['html_url'] !== ''
                ? $release['html_url']
                : ($status === 'no_release' ? CBT_Update_Release_Helper::releases_html_url() : CBT_Update_Release_Helper::latest_release_html_url()),
            'checked_at_label' => self::format_datetime(is_array($release_state) ? (int) ($release_state['checked_at'] ?? 0) : 0),
            'published_at_label' => self::format_datetime_string((string) ($manifest['published_at'] ?? ($release['published_at'] ?? ''))),
            'changelog' => isset($manifest['changelog']) ? (string) $manifest['changelog'] : '',
            'release_message' => $release_message,
        ];
    }

    public static function handle_check_update_now(): void
    {
        if (!self::can_manage_updates()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_check_update_now');

        $job = CBT_Update_Job_Service::start_check('admin_post');
        $redirect_args = ['page' => 'cbt-update'];
        $redirect_args['cbt_update_token'] = (string) ($job['token'] ?? '');
        $redirect_args['cbt_msg'] = 'Job cek update dibuat. Progress akan berjalan dari panel CBT Update.';

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_install_update_now(): void
    {
        if (!self::can_manage_updates()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_install_update_now');

        $job = CBT_Update_Job_Service::start_install('admin_post');
        if (is_wp_error($job)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-update',
                'cbt_err' => $job->get_error_message(),
            ], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-update',
            'cbt_update_token' => (string) ($job['token'] ?? ''),
            'cbt_msg' => 'Job install update dibuat. Progress akan berjalan dari panel CBT Update.',
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_update_operation_ajax(): void
    {
        if (!self::can_manage_updates()) {
            self::dispatch_update_operation_ajax(false, ['message' => 'Unauthorized'], 403);
        }

        if (function_exists('check_ajax_referer')) {
            check_ajax_referer('cbt_update_operation', 'nonce');
        } else {
            check_admin_referer('cbt_update_operation', 'nonce');
        }

        $operation = isset($_POST['operation']) ? sanitize_key((string) wp_unslash($_POST['operation'])) : '';
        $token = isset($_POST['token']) ? sanitize_key((string) wp_unslash($_POST['token'])) : '';

        switch ($operation) {
            case 'start_check':
                $job = CBT_Update_Job_Service::start_check('ajax');
                self::dispatch_update_operation_ajax(true, CBT_Update_Job_Service::response_for_job($job, $operation));
                return;

            case 'start_install':
                $job = CBT_Update_Job_Service::start_install('ajax');
                if (is_wp_error($job)) {
                    self::dispatch_update_operation_ajax(false, ['message' => $job->get_error_message()], 400);
                }
                self::dispatch_update_operation_ajax(true, CBT_Update_Job_Service::response_for_job($job, $operation));
                return;

            case 'start_rollback':
                $backup_id = isset($_POST['backup_id']) ? sanitize_key((string) wp_unslash($_POST['backup_id'])) : '';
                $job = CBT_Update_Job_Service::start_rollback($backup_id, 'ajax');
                if (is_wp_error($job)) {
                    self::dispatch_update_operation_ajax(false, ['message' => $job->get_error_message()], 400);
                }
                self::dispatch_update_operation_ajax(true, CBT_Update_Job_Service::response_for_job($job, $operation));
                return;

            case 'tick':
                $job = CBT_Update_Job_Service::tick($token);
                self::dispatch_update_operation_ajax(true, CBT_Update_Job_Service::response_for_job($job, $operation));
                return;

            case 'status':
                $job = CBT_Update_Job_Service::get_job($token);
                if (!is_array($job)) {
                    self::dispatch_update_operation_ajax(false, ['message' => 'Job update tidak ditemukan.'], 404);
                }
                self::dispatch_update_operation_ajax(true, CBT_Update_Job_Service::response_for_job($job, $operation));
                return;

            case 'clear_finished_job':
                $cleared = CBT_Update_Job_Service::clear_finished_job($token);
                self::dispatch_update_operation_ajax($cleared, [
                    'operation' => $operation,
                    'token' => $token,
                    'status' => $cleared ? 'completed' : 'failed',
                    'complete' => true,
                    'progress_percent' => 100,
                    'status_label' => $cleared ? 'Cleared' : 'Failed',
                    'message' => $cleared ? 'Job update selesai dibersihkan.' : 'Job update belum selesai atau tidak ditemukan.',
                    'detail' => [],
                    'history' => CBT_Update_Job_Service::get_history(),
                    'backups' => CBT_Update_Backup_Service::get_backups(),
                    'redirect_url' => self::page_url(),
                ], $cleared ? 200 : 400);
                return;

            default:
                self::dispatch_update_operation_ajax(false, ['message' => 'Operasi update tidak dikenal.'], 400);
        }
    }

    public static function page_url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => 'cbt-update'], $args), admin_url('admin.php'));
    }

    /**
     * @return array<string,string>
     */
    public static function status_meta(string $status): array
    {
        switch ($status) {
            case 'up_to_date':
                return [
                    'label' => 'Up to Date',
                    'tone' => 'ok',
                    'description' => 'Plugin lokal sudah sama atau lebih baru dari release terbaru GitHub.',
                ];
            case 'available':
                return [
                    'label' => 'Update Available',
                    'tone' => 'info',
                    'description' => 'Release baru tersedia dan preflight siap untuk install manual.',
                ];
            case 'no_release':
                return [
                    'label' => 'No Release Yet',
                    'tone' => 'muted',
                    'description' => 'Repo sumber belum punya GitHub Release resmi yang bisa dipakai updater v1.',
                ];
            case 'preflight_blocked':
                return [
                    'label' => 'Preflight Blocked',
                    'tone' => 'warning',
                    'description' => 'Release baru tersedia, tetapi checklist preflight masih memblokir install.',
                ];
            case 'check_failed':
                return [
                    'label' => 'Check Failed',
                    'tone' => 'danger',
                    'description' => 'Status release terbaru belum bisa diambil dari GitHub.',
                ];
            default:
                return [
                    'label' => 'Not Checked',
                    'tone' => 'muted',
                    'description' => 'Belum ada status release yang disimpan. Jalankan cek update manual terlebih dahulu.',
                ];
        }
    }

    /**
     * @return array<string,string>
     */
    public static function preflight_meta(string $status): array
    {
        switch ($status) {
            case 'ok':
                return [
                    'label' => 'OK',
                    'tone' => 'ok',
                ];
            case 'warning':
                return [
                    'label' => 'Warning',
                    'tone' => 'warning',
                ];
            default:
                return [
                    'label' => 'Blocked',
                    'tone' => 'danger',
                ];
        }
    }

    private static function dispatch_update_operation_ajax(bool $success, array $payload, int $status_code = 200): void
    {
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            $GLOBALS['cbt_test_last_ajax_response'] = [
                'success' => $success,
                'status_code' => $status_code,
                'payload' => $payload,
            ];
            throw new RuntimeException(self::TEST_AJAX_SIGNAL);
        }

        if ($success) {
            wp_send_json_success($payload, $status_code);
        }

        wp_send_json_error($payload, $status_code);
    }

    private static function format_datetime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'Belum pernah dicek';
        }

        return wp_date('d M Y H:i', $timestamp);
    }

    private static function format_datetime_string(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date('d M Y H:i', $timestamp);
    }
}
