<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exam_Cards_Actions
{
    public static function handle_print_exam_cards(): void
    {
        if (!CBT_Admin_Exam_Cards_Service::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_print_exam_cards');

        $context = CBT_Admin_Exam_Cards_Service::build_print_context($_POST);
        if (is_wp_error($context)) {
            $error_data = $context->get_error_data();
            $redirect_args = is_array($error_data) && isset($error_data['redirect_args']) && is_array($error_data['redirect_args'])
                ? $error_data['redirect_args']
                : [];
            self::redirect_exam_cards_page($redirect_args + ['cbt_err' => $context->get_error_message()]);
        }

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        extract($context, EXTR_SKIP);
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/exam-cards/print.php';
        exit;
    }

    private static function redirect_exam_cards_page(array $args = []): void
    {
        $redirect_args = array_merge(['page' => 'cbt-exam-cards'], $args);
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
