<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Developer_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Developer_Service::can_manage()) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Developer_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/developer/page.php';
    }
}
