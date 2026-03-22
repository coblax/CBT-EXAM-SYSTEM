<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Maintenance_Actions
{
    public static function handle_reset_database(): void
    {
        CBT_Admin_Maintenance_Service::handle_reset_database();
    }

    public static function handle_generate_test_dataset(): void
    {
        CBT_Admin_Maintenance_Service::handle_generate_test_dataset();
    }

    public static function handle_start_load_test(): void
    {
        CBT_Admin_Maintenance_Service::handle_start_load_test();
    }

    public static function handle_cancel_load_test(): void
    {
        CBT_Admin_Maintenance_Service::handle_cancel_load_test();
    }

    public static function handle_delete_load_test_job(): void
    {
        CBT_Admin_Maintenance_Service::handle_delete_load_test_job();
    }

    public static function handle_clear_load_test_jobs(): void
    {
        CBT_Admin_Maintenance_Service::handle_clear_load_test_jobs();
    }

    public static function handle_download_load_test_artifact(): void
    {
        CBT_Admin_Maintenance_Service::handle_download_load_test_artifact();
    }

    public static function handle_export_load_test_students_json(): void
    {
        CBT_Admin_Maintenance_Service::handle_export_load_test_students_json();
    }

    public static function handle_export_load_test_students_csv(): void
    {
        CBT_Admin_Maintenance_Service::handle_export_load_test_students_csv();
    }

    public static function handle_export_load_test_students_xlsx(): void
    {
        CBT_Admin_Maintenance_Service::handle_export_load_test_students_xlsx();
    }

    public static function handle_load_test_jobs_ajax(): void
    {
        CBT_Admin_Maintenance_Service::handle_load_test_jobs_ajax();
    }
}
