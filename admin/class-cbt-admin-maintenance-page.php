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
