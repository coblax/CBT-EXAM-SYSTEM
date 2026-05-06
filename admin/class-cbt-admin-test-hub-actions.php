<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Test_Hub_Actions
{
    public static function handle_clear_test_artifacts(): void
    {
        CBT_Admin_Test_Hub_Service::handle_clear_test_artifacts();
    }

    public static function handle_save_settings(): void
    {
        CBT_Admin_Test_Hub_Service::handle_save_settings();
    }

    public static function handle_refresh_runner_health(): void
    {
        CBT_Admin_Test_Hub_Service::handle_refresh_runner_health();
    }

    public static function handle_check_e2e_readiness(): void
    {
        CBT_Admin_Test_Hub_Service::handle_check_e2e_readiness();
    }

    public static function handle_run_unit_test_suite(): void
    {
        CBT_Admin_Test_Hub_Service::handle_run_unit_test_suite();
    }

    public static function handle_run_all_unit_tests(): void
    {
        CBT_Admin_Test_Hub_Service::handle_run_all_unit_tests();
    }

    public static function handle_queue_flow_check_job(): void
    {
        CBT_Admin_Test_Hub_Service::handle_queue_flow_check_job();
    }

    public static function handle_retry_flow_check_job(): void
    {
        CBT_Admin_Test_Hub_Service::handle_retry_flow_check_job();
    }

    public static function handle_cancel_flow_check_job(): void
    {
        CBT_Admin_Test_Hub_Service::handle_cancel_flow_check_job();
    }

    public static function handle_clear_flow_check_job(): void
    {
        CBT_Admin_Test_Hub_Service::handle_clear_flow_check_job();
    }

    public static function handle_repair_stuck_flow_check_jobs(): void
    {
        CBT_Admin_Test_Hub_Service::handle_repair_stuck_flow_check_jobs();
    }

    public static function handle_download_test_hub_artifact(): void
    {
        CBT_Admin_Test_Hub_Service::handle_download_test_hub_artifact();
    }
}
