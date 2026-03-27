<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Setup_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Setup_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        wp_enqueue_media();

        $context = CBT_Admin_Setup_Service::build_branding_page_context($_GET);
        $cbt_admin_view_mode = 'branding';
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/setup/page.php';
    }

    /**
     * @param array<int,array<string,mixed>> $must_watch_attempts
     */
    public static function render_security_log_must_watch_panel(array $must_watch_attempts): void
    {
        CBT_Admin_Security_Page::render_security_log_must_watch_panel($must_watch_attempts);
    }
}
