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
        $active_maintenance_tab = isset($context['active_maintenance_tab']) ? (string) $context['active_maintenance_tab'] : 'reset';
        $active_tab_context = isset($context['active_tab_context']) && is_array($context['active_tab_context'])
            ? (array) $context['active_tab_context']
            : [];
        $context['active_tab_markup'] = self::render_tab_panel_markup($active_maintenance_tab, $active_tab_context);
        $context['maintenance_tab_urls'] = self::build_tab_urls($_GET);

        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/maintenance/page.php';
    }

    /**
     * @param array<string,array<string,mixed>> $jobs
     */
    public static function render_load_test_jobs_markup(array $jobs): string
    {
        return CBT_Admin_Maintenance_Load_Test_Presenter::render_jobs_markup($jobs);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function render_tab_panel_markup(string $tab, array $context): string
    {
        if (!in_array($tab, CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs(), true)) {
            $tab = 'reset';
        }

        if ($tab === 'load') {
            $context['load_test_jobs_html'] = self::render_load_test_jobs_markup(
                isset($context['load_test_jobs']) && is_array($context['load_test_jobs'])
                    ? (array) $context['load_test_jobs']
                    : []
            );
        }

        extract($context, EXTR_SKIP);

        ob_start();
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/maintenance/partials/' . $tab . '-panel.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,string>
     */
    private static function build_tab_urls(array $query): array
    {
        $args = [
            'page' => 'cbt-maintenance',
        ];

        $query_map = [
            'cbt_msg' => 'sanitize_text_field',
            'cbt_err' => 'sanitize_text_field',
            'cbt_reset_progress_token' => 'sanitize_key',
            'cbt_seed_progress_token' => 'sanitize_key',
            'cbt_seed_preset' => 'sanitize_key',
        ];

        foreach ($query_map as $key => $sanitizer) {
            if (!isset($query[$key])) {
                continue;
            }

            $args[$key] = $sanitizer((string) wp_unslash((string) $query[$key]));
        }

        $tab_urls = [];
        foreach (CBT_Admin_Maintenance_Context_Builder::allowed_maintenance_tabs() as $tab) {
            $tab_urls[$tab] = add_query_arg(
                array_merge($args, ['cbt_maintenance_tab' => $tab]),
                admin_url('admin.php')
            );
        }

        return $tab_urls;
    }
}
