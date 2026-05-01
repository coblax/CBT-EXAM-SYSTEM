<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Results_Actions
{
    public static function handle_grade_essay(): void
    {
        CBT_Admin_Results_Service::handle_grade_essay();
    }

    public static function handle_bulk_grade_essay(): void
    {
        CBT_Admin_Results_Service::handle_bulk_grade_essay();
    }

    public static function handle_essay_questions_ajax(): void
    {
        CBT_Admin_Results_Service::handle_essay_questions_ajax();
    }

    public static function handle_reset_user_login(): void
    {
        CBT_Admin_Results_Service::handle_reset_user_login();
    }

    public static function handle_extend_attempt_time(): void
    {
        CBT_Admin_Results_Service::handle_extend_attempt_time();
    }

    public static function handle_reset_attempt(): void
    {
        CBT_Admin_Results_Service::handle_reset_attempt();
    }

    public static function handle_force_complete_attempt(): void
    {
        CBT_Admin_Results_Service::handle_force_complete_attempt();
    }

    public static function handle_bulk_reset_attempts(): void
    {
        CBT_Admin_Results_Service::handle_bulk_reset_attempts();
    }

    public static function handle_bulk_force_complete_attempts(): void
    {
        CBT_Admin_Results_Service::handle_bulk_force_complete_attempts();
    }

    public static function handle_bulk_job_tick_ajax(): void
    {
        CBT_Admin_Results_Service::handle_bulk_job_tick_ajax();
    }

    public static function handle_bulk_job_stop_ajax(): void
    {
        CBT_Admin_Results_Service::handle_bulk_job_stop_ajax();
    }
}
