<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-common.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-reset-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-seed-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-load-test-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-load-test-presenter.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-context-builder.php';

final class CBT_Admin_Maintenance_Service
{
    public static function can_manage_maintenance(): bool
    {
        return current_user_can('manage_options');
    }

    public static function get_seed_special_student_username(): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_special_student_username();
    }

    public static function get_seed_special_student_password(): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_special_student_password();
    }

    public static function get_seed_special_admin_username(): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_special_admin_username();
    }

    public static function get_seed_special_admin_password(): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_special_admin_password();
    }

    public static function get_seed_default_password(): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_default_password();
    }

    public static function get_seed_recovery_fixture_exam_title(): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_recovery_fixture_exam_title();
    }

    /**
     * @return array<string,string>
     */
    public static function get_seed_flow_check_fixture_exam_titles(): array
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_flow_check_fixture_exam_titles();
    }

    public static function get_seed_fixture_exam_title(string $fixture_key): string
    {
        return CBT_Admin_Maintenance_Seed_Service::get_seed_fixture_exam_title($fixture_key);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        return CBT_Admin_Maintenance_Context_Builder::build_page_context($query);
    }

    public static function handle_reset_database(): void
    {
        CBT_Admin_Maintenance_Reset_Service::handle_reset_database();
    }

    public static function handle_generate_test_dataset(): void
    {
        CBT_Admin_Maintenance_Seed_Service::handle_generate_test_dataset();
    }

    public static function handle_start_load_test(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_start_load_test();
    }

    public static function handle_cancel_load_test(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_cancel_load_test();
    }

    public static function handle_delete_load_test_job(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_delete_load_test_job();
    }

    public static function handle_clear_load_test_jobs(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_clear_load_test_jobs();
    }

    public static function handle_download_load_test_artifact(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_download_load_test_artifact();
    }

    public static function handle_export_load_test_students_json(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_export_load_test_students_json();
    }

    public static function handle_export_load_test_students_csv(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_export_load_test_students_csv();
    }

    public static function handle_export_load_test_students_xlsx(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_export_load_test_students_xlsx();
    }

    public static function handle_load_test_jobs_ajax(): void
    {
        CBT_Admin_Maintenance_Load_Test_Service::handle_load_test_jobs_ajax();
    }
}