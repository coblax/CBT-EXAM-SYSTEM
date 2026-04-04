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
