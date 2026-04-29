<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Users_Actions
{
    public static function handle_import_users(): void
    {
        CBT_Admin_Users_Service::handle_import_users();
    }

    public static function handle_preview_import_users(): void
    {
        CBT_Admin_Users_Service::handle_preview_import_users();
    }

    public static function handle_run_previewed_import_users(): void
    {
        CBT_Admin_Users_Service::handle_run_previewed_import_users();
    }

    public static function handle_create_user_manual(): void
    {
        CBT_Admin_Users_Service::handle_create_user_manual();
    }

    public static function handle_update_user_manual(): void
    {
        CBT_Admin_Users_Service::handle_update_user_manual();
    }

    public static function handle_delete_user_manual(): void
    {
        CBT_Admin_Users_Service::handle_delete_user_manual();
    }

    public static function handle_bulk_delete_users(): void
    {
        CBT_Admin_Users_Service::handle_bulk_delete_users();
    }

    public static function handle_download_user_template(): void
    {
        CBT_Admin_Users_Service::handle_download_user_template();
    }

    public static function handle_download_user_template_xlsx(): void
    {
        CBT_Admin_Users_Service::handle_download_user_template_xlsx();
    }

    public static function handle_import_student_subject_choices(): void
    {
        CBT_Admin_Users_Service::handle_import_student_subject_choices();
    }

    public static function handle_preview_import_student_subject_choices(): void
    {
        CBT_Admin_Users_Service::handle_preview_import_student_subject_choices();
    }

    public static function handle_run_previewed_student_subject_choices(): void
    {
        CBT_Admin_Users_Service::handle_run_previewed_student_subject_choices();
    }

    public static function handle_download_student_subject_choices_template(): void
    {
        CBT_Admin_Users_Service::handle_download_student_subject_choices_template();
    }

    public static function handle_download_student_subject_choices_template_xlsx(): void
    {
        CBT_Admin_Users_Service::handle_download_student_subject_choices_template_xlsx();
    }
}
