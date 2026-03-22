<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Results_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Results_Service::can_view_results()) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Results_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/results/page.php';
    }
}
