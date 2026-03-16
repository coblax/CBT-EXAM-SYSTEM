<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Report_Exam_Page
{
    public static function render(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Report_Exam_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/report-exam/page.php';
    }
}
