<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Subjects_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Subjects_Service::can_manage_subjects()) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Subjects_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/subjects/page.php';
    }
}
