<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Update_Actions
{
    public static function handle_check_update_now(): void
    {
        CBT_Admin_Update_Service::handle_check_update_now();
    }

    public static function handle_install_update_now(): void
    {
        CBT_Admin_Update_Service::handle_install_update_now();
    }

    public static function handle_update_operation_ajax(): void
    {
        CBT_Admin_Update_Service::handle_update_operation_ajax();
    }
}
