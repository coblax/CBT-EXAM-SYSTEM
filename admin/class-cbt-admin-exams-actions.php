<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exams_Actions
{
    public static function handle_save_exam(): void
    {
        CBT_Admin_Exams_Service::handle_save_exam();
    }

    public static function handle_delete_exam(): void
    {
        CBT_Admin_Exams_Service::handle_delete_exam();
    }

    public static function handle_warm_exam_delivery_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_warm_exam_delivery_snapshot();
    }

    public static function handle_warm_bulk_exam_delivery_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_warm_bulk_exam_delivery_snapshots();
    }

    public static function handle_clear_exam_delivery_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_clear_exam_delivery_snapshot();
    }

    public static function handle_clear_bulk_exam_delivery_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_clear_bulk_exam_delivery_snapshots();
    }

    public static function handle_warm_exam_submission_context_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_warm_exam_submission_context_snapshot();
    }

    public static function handle_warm_bulk_exam_submission_context_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_warm_bulk_exam_submission_context_snapshots();
    }

    public static function handle_clear_exam_submission_context_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_clear_exam_submission_context_snapshot();
    }

    public static function handle_clear_bulk_exam_submission_context_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_clear_bulk_exam_submission_context_snapshots();
    }

    public static function handle_start_exam_availability_auto_warm(): void
    {
        CBT_Admin_Exams_Service::handle_start_exam_availability_auto_warm();
    }

    public static function handle_stop_exam_availability_auto_warm(): void
    {
        CBT_Admin_Exams_Service::handle_stop_exam_availability_auto_warm();
    }

    public static function handle_start_exam_preflight(): void
    {
        CBT_Admin_Exams_Service::handle_start_exam_preflight();
    }

    public static function handle_clean_exam_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_clean_exam_snapshots();
    }

    public static function handle_warm_student_exam_availability_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_warm_student_exam_availability_snapshot();
    }

    public static function handle_clear_student_exam_availability_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_clear_student_exam_availability_snapshot();
    }

    public static function handle_warm_bulk_student_exam_availability_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_warm_bulk_student_exam_availability_snapshots();
    }

    public static function handle_clear_bulk_student_exam_availability_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_clear_bulk_student_exam_availability_snapshots();
    }

    public static function handle_warm_student_profile_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_warm_student_profile_snapshot();
    }

    public static function handle_clear_student_profile_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_clear_student_profile_snapshot();
    }

    public static function handle_warm_bulk_student_profile_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_warm_bulk_student_profile_snapshots();
    }

    public static function handle_clear_bulk_student_profile_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_clear_bulk_student_profile_snapshots();
    }

    public static function handle_warm_student_login_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_warm_student_login_snapshot();
    }

    public static function handle_clear_student_login_snapshot(): void
    {
        CBT_Admin_Exams_Service::handle_clear_student_login_snapshot();
    }

    public static function handle_warm_bulk_student_login_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_warm_bulk_student_login_snapshots();
    }

    public static function handle_clear_bulk_student_login_snapshots(): void
    {
        CBT_Admin_Exams_Service::handle_clear_bulk_student_login_snapshots();
    }

    public static function handle_sync_exam_builder_selection(): void
    {
        CBT_Admin_Exams_Service::handle_sync_exam_builder_selection();
    }

    public static function handle_clear_exam_builder_selection(): void
    {
        CBT_Admin_Exams_Service::handle_clear_exam_builder_selection();
    }

    public static function handle_start_exam_save_progress(): void
    {
        CBT_Admin_Exams_Service::handle_start_exam_save_progress();
    }

    public static function handle_continue_exam_save_progress(): void
    {
        CBT_Admin_Exams_Service::handle_continue_exam_save_progress();
    }
}
