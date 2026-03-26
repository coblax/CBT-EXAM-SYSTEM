<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Maintenance_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Maintenance_Service::can_manage_maintenance()) {
            wp_die('Unauthorized');
        }

        $requested_tab = isset($_GET['cbt_maintenance_tab'])
            ? sanitize_key((string) wp_unslash($_GET['cbt_maintenance_tab']))
            : '';
        if ($requested_tab === 'unit_test') {
            $redirect_args = ['page' => 'cbt-test-hub'];
            $active_unit_test_tab = CBT_Admin_Test_Hub_Service::normalize_unit_test_tab(
                isset($_GET['cbt_unit_test_tab']) ? wp_unslash((string) $_GET['cbt_unit_test_tab']) : ''
            );
            if ($active_unit_test_tab !== '') {
                $redirect_args['cbt_unit_test_tab'] = $active_unit_test_tab;
            }
            if (isset($_GET['cbt_test_run_token'])) {
                $redirect_args['cbt_test_run_token'] = sanitize_key(wp_unslash((string) $_GET['cbt_test_run_token']));
            }
            if (isset($_GET['cbt_msg'])) {
                $redirect_args['cbt_msg'] = sanitize_text_field(wp_unslash((string) $_GET['cbt_msg']));
            }
            if (isset($_GET['cbt_err'])) {
                $redirect_args['cbt_err'] = sanitize_text_field(wp_unslash((string) $_GET['cbt_err']));
            }

            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $context = CBT_Admin_Maintenance_Service::build_page_context($_GET);
        $context['load_test_jobs_html'] = self::render_load_test_jobs_markup(
            isset($context['load_test_jobs']) && is_array($context['load_test_jobs'])
                ? (array) $context['load_test_jobs']
                : []
        );

        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/maintenance/page.php';
    }

    /**
     * @param array<string,array<string,mixed>> $jobs
     */
    public static function render_load_test_jobs_markup(array $jobs): string
    {
        ob_start();

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/maintenance/partials/load-test-jobs.php';

        return (string) ob_get_clean();
    }
}
