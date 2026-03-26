<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Test_Hub_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Test_Hub_Service::can_manage_test_hub()) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Test_Hub_Service::build_unit_test_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/test-hub/page.php';
    }
}
