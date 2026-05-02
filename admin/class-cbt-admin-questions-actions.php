<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Questions_Actions
{
    public static function handle_save_question(): void
    {
        CBT_Admin_Questions_Service::handle_save_question();
    }

    public static function handle_delete_question(): void
    {
        CBT_Admin_Questions_Service::handle_delete_question();
    }

    public static function handle_bulk_delete_questions(): void
    {
        CBT_Admin_Questions_Service::handle_bulk_delete_questions();
    }

    public static function handle_delete_all_import_batch_questions(): void
    {
        CBT_Admin_Questions_Service::handle_delete_all_import_batch_questions();
    }

    public static function handle_import_questions(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_import_questions();
    }

    public static function handle_download_question_template_word(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word();
    }

    public static function handle_download_question_template_word_mc(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_mc();
    }

    public static function handle_download_question_template_word_ma(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_ma();
    }

    public static function handle_download_question_template_word_sa(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_sa();
    }

    public static function handle_download_question_template_word_tf(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_tf();
    }

    public static function handle_download_question_template_word_tfm(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_tfm();
    }

    public static function handle_download_question_template_word_essay(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_essay();
    }

    public static function handle_download_question_template_word_ordering(): void
    {
        CBT_Admin_Questions_Import_Helper::handle_download_question_template_word_ordering();
    }
}
