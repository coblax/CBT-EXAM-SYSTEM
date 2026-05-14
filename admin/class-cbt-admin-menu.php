<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Menu
{
    public static function register_menu(): void
    {
        add_menu_page(
            'CBT Exams',
            'CBT Exams',
            'cbt_manage_exams',
            'cbt-exams',
            [CBT_Admin_Exams_Page::class, 'render'],
            'dashicons-welcome-learn-more',
            26
        );

        add_submenu_page(
            'cbt-exams',
            'Introduction',
            'Introduction',
            'cbt_manage_exams',
            'cbt-introduction',
            [CBT_Admin_Introduction_Page::class, 'render']
        );

        remove_submenu_page('cbt-exams', 'cbt-exams');

        add_submenu_page(
            'cbt-exams',
            'CBT Exams',
            'CBT Exams',
            'cbt_manage_exams',
            'cbt-exams',
            [CBT_Admin_Exams_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Branding',
            'CBT Branding',
            'cbt_manage_exams',
            'cbt-setup',
            [CBT_Admin_Setup_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Security',
            'CBT Security',
            'cbt_manage_exams',
            'cbt-security',
            [CBT_Admin_Security_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Subjects',
            'CBT Subjects',
            'manage_options',
            'cbt-subjects',
            [CBT_Admin_Subjects_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Users',
            'CBT Users',
            'manage_options',
            'cbt-user-import',
            [CBT_Admin_Users_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Questions',
            'CBT Questions',
            'cbt_manage_questions',
            'cbt-question-bank',
            [CBT_Admin_Questions_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Tokens',
            'CBT Tokens',
            'cbt_manage_exams',
            'cbt-tokens',
            [CBT_Admin_Tokens_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Administrative Documents',
            'CBT Administrative Documents',
            'cbt_manage_users',
            'cbt-exam-cards',
            [CBT_Admin_Exam_Cards_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Results',
            'CBT Results',
            'cbt_view_results',
            'cbt-results',
            [CBT_Admin_Results_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Analytics',
            'CBT Analytics',
            'cbt_view_results',
            'cbt-analytics',
            [CBT_Admin_Analytics_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Report Exam',
            'CBT Report Exam',
            'cbt_view_results',
            'cbt-report-exam',
            [CBT_Admin_Report_Exam_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Test Hub',
            'CBT Test Hub',
            'manage_options',
            'cbt-test-hub',
            [CBT_Admin_Test_Hub_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Cache',
            'CBT Cache',
            'manage_options',
            'cbt-cache',
            [CBT_Admin_Cache_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Update',
            'CBT Update',
            'manage_options',
            'cbt-update',
            [CBT_Admin_Update_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Maintenance',
            'CBT Maintenance',
            'manage_options',
            'cbt-maintenance',
            [CBT_Admin_Maintenance_Page::class, 'render']
        );

        add_submenu_page(
            'cbt-exams',
            'CBT Developer',
            'CBT Developer',
            'manage_options',
            'cbt-developer',
            [CBT_Admin_Developer_Page::class, 'render']
        );
    }

    public static function redirect_removed_admin_pages(): void
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page === '') {
            return;
        }

        $removed_pages = [
            'cbt-questions-mc',
            'cbt-questions-ma',
            'cbt-questions-tf',
            'cbt-questions-sa',
            'cbt-questions-essay',
            'cbt-questions-ordering',
            'cbt-questions-matching',
            'cbt-questions-cloze',
            'cbt-questions-categorization',
            'cbt-questions-table-completion',
        ];

        if (!in_array($page, $removed_pages, true)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-subjects'));
        exit;
    }

    public static function render_legacy_security_hash_redirect(): void
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== 'cbt-setup') {
            return;
        }

        if (!current_user_can('cbt_manage_exams')) {
            return;
        }

        $target_url = admin_url('admin.php?page=cbt-security');
        ?>
        <script>
            (function () {
                var hash = String(window.location.hash || '');
                if (hash !== '#security' && hash !== '#security-log') {
                    return;
                }

                window.location.replace(<?php echo wp_json_encode($target_url); ?> + hash);
            })();
        </script>
        <?php
    }
}
