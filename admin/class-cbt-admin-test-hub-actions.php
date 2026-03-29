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
}
