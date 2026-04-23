<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Admin_Branding_Settings')) {
    require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-branding-settings.php';
}

final class CBT_Admin_Maintenance_Common
{
    private const TEST_REDIRECT_SIGNAL = '__cbt_admin_maintenance_redirect__';

    /**
     * @return string[]
     */
    private static function allowed_maintenance_tabs(): array
    {
        return ['reset', 'seed', 'load'];
    }

    public static function prepare_runtime_for_bulk_user_import(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');
    }

    public static function redirect_maintenance_page(?string $message = null, ?string $error = null, ?string $tab = null): void
    {
        $args = ['page' => 'cbt-maintenance'];
        if ($tab === null || $tab === '') {
            $requested_tab = isset($_REQUEST['cbt_maintenance_tab'])
                ? sanitize_key((string) wp_unslash($_REQUEST['cbt_maintenance_tab']))
                : '';
            if (in_array($requested_tab, self::allowed_maintenance_tabs(), true)) {
                $tab = $requested_tab;
            }
        }
        if ($tab !== null && $tab !== '' && in_array($tab, self::allowed_maintenance_tabs(), true)) {
            $args['cbt_maintenance_tab'] = $tab;
        }
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        $location = add_query_arg($args, admin_url('admin.php'));
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            $GLOBALS['cbt_test_last_redirect'] = (string) $location;
            throw new RuntimeException(self::TEST_REDIRECT_SIGNAL);
        }

        wp_safe_redirect($location);
        exit;
    }

    /**
     * @return string[]
     */
    public static function cbt_data_tables(wpdb $wpdb): array
    {
        $prefix = $wpdb->prefix;

        return [
            $prefix . 'cbt_answers',
            $prefix . 'cbt_attempts',
            $prefix . 'cbt_security_logs',
            $prefix . 'cbt_exam_incidents',
            $prefix . 'cbt_student_cohort_index',
            $prefix . 'cbt_options',
            $prefix . 'cbt_question_essay',
            $prefix . 'cbt_question_short_answer',
            $prefix . 'cbt_question_true_false',
            $prefix . 'cbt_question_multiple_answer',
            $prefix . 'cbt_question_multiple_choice',
            $prefix . 'cbt_questions',
            $prefix . 'cbt_exams',
            $prefix . 'cbt_subjects',
        ];
    }

    public static function get_reset_progress_table_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_reset_progress_table_batch_size', 2);
        if ($batch_size < 1) {
            return 1;
        }
        if ($batch_size > 10) {
            return 10;
        }

        return $batch_size;
    }

    public static function get_reset_progress_user_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_reset_progress_user_batch_size', 140);
        if ($batch_size < 20) {
            return 20;
        }
        if ($batch_size > 500) {
            return 500;
        }

        return $batch_size;
    }

    public static function get_reset_progress_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_reset_progress_batch_max_seconds', 8.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    public static function reset_cbt_global_token_options(): void
    {
        delete_option('cbt_global_exam_token_value');
        delete_option('cbt_global_exam_token_generated_at');
        delete_option('cbt_global_exam_token_refresh_minutes');
        delete_option('cbt_global_exam_token_frontend_auto_apply');
        delete_option(CBT_Admin_Branding_Settings::option_key());
        delete_transient('cbt_exam_priority_window_until');
    }

    /**
     * @return int[]
     */
    public static function collect_cbt_user_ids_for_reset(): array
    {
        $roles_to_purge = ['administrator', 'admin_cbt', 'guru_cbt', 'editor', 'teacher', 'siswa_cbt', 'subscriber', 'student'];
        $user_ids = [];

        foreach ($roles_to_purge as $role) {
            $ids = get_users([
                'role' => $role,
                'fields' => 'ids',
            ]);
            if (!is_array($ids)) {
                continue;
            }

            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $user_ids[$id] = $id;
                }
            }
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && isset($user_ids[$current_user_id])) {
            unset($user_ids[$current_user_id]);
        }

        return array_values($user_ids);
    }
}
