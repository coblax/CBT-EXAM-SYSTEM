<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-common.php';

final class CBT_Admin_Maintenance_Seed_Service
{
    private const TEST_DATA_SEED_CONFIRM_PHRASE = 'GENERATE TEST DATA';
    private const TEST_DATA_SEED_DEFAULT_PASSWORD = 'Skills39';
    private const TEST_DATA_SEED_SPECIAL_USERNAME = 'coblax';
    private const TEST_DATA_SEED_SPECIAL_PASSWORD = '223611';
    private const TEST_DATA_SEED_SPECIAL_ADMIN_USERNAME = 'cbtadmin';
    private const TEST_DATA_SEED_SPECIAL_ADMIN_PASSWORD = '223611';
    private const TEST_DATA_SEED_RECOVERY_FIXTURE_TITLE = 'TEST Recovery Fixture';
    private const TEST_DATA_SEED_SESSION_FIXTURE_TITLE = 'TEST Session Fixture';
    private const TEST_DATA_SEED_SYNC_FIXTURE_TITLE = 'TEST Sync Fixture';
    private const TEST_DATA_SEED_RESULT_FULL_FIXTURE_TITLE = 'TEST Result Fixture [FULL]';
    private const TEST_DATA_SEED_RESULT_RESTRICTED_FIXTURE_TITLE = 'TEST Result Fixture [RESTRICTED]';
    private const TEST_DATA_SEED_RESULT_ESSAY_FIXTURE_TITLE = 'TEST Result Fixture [ESSAY]';
    private const TEST_DATA_SEED_TIMER_FIXTURE_TITLE = 'TEST Timer Fixture';
    private const TEST_DATA_SEED_RUNTIME_FIXTURE_TITLE = 'TEST Runtime Fixture';
    private const TEST_DATA_SEED_SECURITY_FIXTURE_TITLE = 'TEST Security Fixture';
    private const TEST_DATA_SEED_IMPORT_PREVIEW_FIXTURE_TITLE = 'TEST Import Preview Fixture';
    private const TEST_DATA_SEED_SAMPLE_IMAGE_DIRECTORY = 'public/images/test-data';
    private const TEST_DATA_SEED_SHARED_EXAM_TOTAL = 22;
    private const TEST_DATA_SEED_BULK_UPLOAD_FILE_PREFIX = 'cbt-bulk-question-';

    public static function get_seed_special_student_username(): string
    {
        return self::TEST_DATA_SEED_SPECIAL_USERNAME;
    }

    public static function get_seed_special_student_password(): string
    {
        return self::TEST_DATA_SEED_SPECIAL_PASSWORD;
    }

    public static function get_seed_special_admin_username(): string
    {
        return self::TEST_DATA_SEED_SPECIAL_ADMIN_USERNAME;
    }

    public static function get_seed_special_admin_password(): string
    {
        return self::TEST_DATA_SEED_SPECIAL_ADMIN_PASSWORD;
    }

    public static function get_seed_default_password(): string
    {
        return self::TEST_DATA_SEED_DEFAULT_PASSWORD;
    }

    public static function get_seed_recovery_fixture_exam_title(): string
    {
        return self::TEST_DATA_SEED_RECOVERY_FIXTURE_TITLE;
    }

    /**
     * @return array<string,string>
     */
    public static function get_seed_flow_check_fixture_exam_titles(): array
    {
        return [
            'recovery_persistence' => self::TEST_DATA_SEED_RECOVERY_FIXTURE_TITLE,
            'auth_session' => self::TEST_DATA_SEED_SESSION_FIXTURE_TITLE,
            'sync_rest' => self::TEST_DATA_SEED_SYNC_FIXTURE_TITLE,
            'result_full' => self::TEST_DATA_SEED_RESULT_FULL_FIXTURE_TITLE,
            'result_restricted' => self::TEST_DATA_SEED_RESULT_RESTRICTED_FIXTURE_TITLE,
            'result_essay' => self::TEST_DATA_SEED_RESULT_ESSAY_FIXTURE_TITLE,
            'timer_lifecycle' => self::TEST_DATA_SEED_TIMER_FIXTURE_TITLE,
            'question_runtime' => self::TEST_DATA_SEED_RUNTIME_FIXTURE_TITLE,
            'security_log_observability' => self::TEST_DATA_SEED_SECURITY_FIXTURE_TITLE,
            'import_preview' => self::TEST_DATA_SEED_IMPORT_PREVIEW_FIXTURE_TITLE,
        ];
    }

    public static function get_seed_fixture_exam_title(string $fixture_key): string
    {
        $definitions = self::get_seed_flow_check_fixture_exam_titles();
        $normalized = sanitize_key($fixture_key);
        return isset($definitions[$normalized]) ? (string) $definitions[$normalized] : '';
    }

    public static function get_test_data_seed_confirm_phrase(): string
    {
        return self::TEST_DATA_SEED_CONFIRM_PHRASE;
    }

    public static function get_test_data_seed_default_password(): string
    {
        return self::TEST_DATA_SEED_DEFAULT_PASSWORD;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_seed_status_context(array $query, string &$notice, string &$error): array
    {
        return self::build_seed_progress_context($query, $notice, $error);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_seed_context(array $query, string &$notice, string &$error): array
    {
        $seed_presets = self::test_data_seed_presets();
        $selected_seed_preset = isset($query['cbt_seed_preset']) ? sanitize_key((string) wp_unslash((string) $query['cbt_seed_preset'])) : 'small';
        if (!isset($seed_presets[$selected_seed_preset])) {
            $selected_seed_preset = 'small';
        }

        $progress_context = self::build_seed_progress_context($query, $notice, $error);
        $seed_progress_state = isset($progress_context['seed_progress_state']) && is_array($progress_context['seed_progress_state'])
            ? $progress_context['seed_progress_state']
            : null;

        if (is_array($seed_progress_state)) {
            $selected_seed_preset = sanitize_key((string) ($seed_progress_state['preset_key'] ?? $selected_seed_preset));
            if (!isset($seed_presets[$selected_seed_preset])) {
                $selected_seed_preset = 'small';
            }
        }

        $seed_question_type_labels = self::get_test_data_seed_question_type_labels();
        $seed_exam_profile_labels = self::get_test_data_seed_exam_profile_labels();
        $seed_presets_view = [];

        foreach ($seed_presets as $preset_key => $preset_meta) {
            $exam_profile_counts = self::get_test_data_seed_exam_profile_counts(
                $preset_key,
                (int) ($preset_meta['exams'] ?? 0)
            );
            $preset_meta['exam_profile_counts'] = $exam_profile_counts;
            $preset_meta['exam_profile_summary'] = self::format_test_data_seed_exam_profile_summary($exam_profile_counts);
            $question_type_counts = self::get_test_data_seed_question_type_counts(
                $preset_key,
                (int) ($preset_meta['questions'] ?? 0),
                (int) ($preset_meta['exams'] ?? 0)
            );
            $preset_meta['question_type_counts'] = $question_type_counts;
            $preset_meta['question_type_summary'] = self::format_test_data_seed_question_type_summary($question_type_counts);
            $seed_presets_view[$preset_key] = $preset_meta;
        }

        $selected_seed_preset_data = $seed_presets_view[$selected_seed_preset];
        $selected_seed_exam_profile_counts = isset($selected_seed_preset_data['exam_profile_counts']) && is_array($selected_seed_preset_data['exam_profile_counts'])
            ? (array) $selected_seed_preset_data['exam_profile_counts']
            : [];
        $selected_seed_exam_profile_summary = isset($selected_seed_preset_data['exam_profile_summary'])
            ? (string) $selected_seed_preset_data['exam_profile_summary']
            : self::format_test_data_seed_exam_profile_summary($selected_seed_exam_profile_counts);
        $selected_seed_question_type_counts = isset($selected_seed_preset_data['question_type_counts']) && is_array($selected_seed_preset_data['question_type_counts'])
            ? (array) $selected_seed_preset_data['question_type_counts']
            : [];
        $selected_seed_question_type_summary = isset($selected_seed_preset_data['question_type_summary'])
            ? (string) $selected_seed_preset_data['question_type_summary']
            : self::format_test_data_seed_question_type_summary($selected_seed_question_type_counts);
        $seed_presets_json = wp_json_encode($seed_presets_view);
        if (!is_string($seed_presets_json) || $seed_presets_json === '') {
            $seed_presets_json = '{}';
        }

        return array_merge($progress_context, [
            'seed_presets' => $seed_presets,
            'selected_seed_preset' => $selected_seed_preset,
            'seed_question_type_labels' => $seed_question_type_labels,
            'seed_exam_profile_labels' => $seed_exam_profile_labels,
            'seed_presets_json' => $seed_presets_json,
            'selected_seed_preset_data' => $selected_seed_preset_data,
            'selected_seed_exam_profile_counts' => $selected_seed_exam_profile_counts,
            'selected_seed_exam_profile_summary' => $selected_seed_exam_profile_summary,
            'selected_seed_question_type_counts' => $selected_seed_question_type_counts,
            'selected_seed_question_type_summary' => $selected_seed_question_type_summary,
            'test_data_seed_confirm_phrase' => self::TEST_DATA_SEED_CONFIRM_PHRASE,
            'test_data_seed_default_password' => self::TEST_DATA_SEED_DEFAULT_PASSWORD,
            'test_data_seed_special_username' => self::TEST_DATA_SEED_SPECIAL_USERNAME,
            'test_data_seed_special_password' => self::TEST_DATA_SEED_SPECIAL_PASSWORD,
        ]);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function build_seed_progress_context(array $query, string &$notice, string &$error): array
    {
        $seed_presets = self::test_data_seed_presets();
        $selected_seed_preset = isset($query['cbt_seed_preset']) ? sanitize_key((string) wp_unslash((string) $query['cbt_seed_preset'])) : 'small';
        if (!isset($seed_presets[$selected_seed_preset])) {
            $selected_seed_preset = 'small';
        }

        $seed_progress_token = isset($query['cbt_seed_progress_token']) ? sanitize_key((string) wp_unslash((string) $query['cbt_seed_progress_token'])) : '';
        $seed_progress_state = null;
        $seed_progress_total = 0;
        $seed_progress_processed = 0;
        $seed_progress_percent = 0.0;
        $seed_progress_is_running = false;
        $seed_progress_phase_label = '';
        $seed_progress_continue_url = '';
        $seed_progress_preset_label = (string) $seed_presets[$selected_seed_preset]['label'];
        $seed_progress_synced_users = 0;
        $seed_progress_created_questions = 0;
        $seed_progress_synced_exam_questions = 0;
        $seed_progress_deleted_users = 0;
        $seed_progress_failed_user_deletes = 0;
        $seed_progress_activity_detail = '';

        if ($seed_progress_token !== '') {
            $seed_progress_state = self::get_seed_progress_state_for_current_user($seed_progress_token);
            if (is_array($seed_progress_state)) {
                $seed_progress_total = max(1, (int) ($seed_progress_state['total_units'] ?? 1));
                $seed_progress_processed = max(0, (int) ($seed_progress_state['processed_units'] ?? 0));
                if ($seed_progress_processed > $seed_progress_total) {
                    $seed_progress_processed = $seed_progress_total;
                }

                $seed_progress_phase = sanitize_key((string) ($seed_progress_state['phase'] ?? 'reset_tables'));
                $phase_labels = self::get_test_data_seed_phase_labels();
                $seed_progress_phase_label = $phase_labels[$seed_progress_phase] ?? 'Memproses dataset uji';
                $seed_progress_percent = $seed_progress_total > 0
                    ? round(((float) $seed_progress_processed / (float) $seed_progress_total) * 100, 2)
                    : 0.0;
                $seed_progress_is_running = $seed_progress_processed < $seed_progress_total;
                $seed_progress_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_generate_test_dataset',
                        'cbt_seed_progress_token' => $seed_progress_token,
                    ],
                    admin_url('admin-ajax.php')
                );
                $seed_progress_preset = self::normalize_test_data_seed_preset((array) ($seed_progress_state['preset'] ?? []));
                $seed_progress_preset_label = (string) ($seed_progress_preset['label'] ?? $seed_progress_preset_label);
                $seed_progress_synced_users = max(0, (int) ($seed_progress_state['seed_user_created_count'] ?? 0))
                    + max(0, (int) ($seed_progress_state['seed_user_updated_count'] ?? 0));
                $seed_progress_created_questions = max(
                    0,
                    (int) ($seed_progress_state['seed_bank_question_created_count'] ?? ($seed_progress_state['seed_question_created_count'] ?? 0))
                );
                $seed_progress_synced_exam_questions = array_sum(
                    array_map(
                        'intval',
                        (array) ($seed_progress_state['synced_exam_question_counts'] ?? [])
                    )
                );
                $seed_progress_deleted_users = max(0, (int) ($seed_progress_state['deleted_user_count'] ?? 0));
                $seed_progress_failed_user_deletes = max(0, (int) ($seed_progress_state['failed_user_delete_count'] ?? 0));
                $seed_progress_activity_detail = self::describe_test_data_seed_phase_progress($seed_progress_state);
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi generate data uji tidak ditemukan atau sudah berakhir. Silakan mulai ulang generator.';
            }
        }

        $seed_progress_status_tone = is_array($seed_progress_state)
            ? ($seed_progress_is_running ? 'running' : 'done')
            : 'idle';
        $seed_progress_status_label = is_array($seed_progress_state)
            ? ($seed_progress_is_running ? 'Sedang berjalan' : 'Selesai')
            : 'Siaga';
        $seed_progress_summary_label = is_array($seed_progress_state)
            ? ((string) $seed_progress_processed . ' / ' . (string) $seed_progress_total)
            : 'Belum ada generator aktif';
        $seed_progress_stage_preview = $seed_progress_phase_label !== '' ? $seed_progress_phase_label : 'Belum ada proses aktif';

        return [
            'seed_progress_token' => $seed_progress_token,
            'seed_progress_state' => $seed_progress_state,
            'seed_progress_total' => $seed_progress_total,
            'seed_progress_processed' => $seed_progress_processed,
            'seed_progress_percent' => $seed_progress_percent,
            'seed_progress_is_running' => $seed_progress_is_running,
            'seed_progress_phase_label' => $seed_progress_phase_label,
            'seed_progress_continue_url' => $seed_progress_continue_url,
            'seed_progress_preset_label' => $seed_progress_preset_label,
            'seed_progress_synced_users' => $seed_progress_synced_users,
            'seed_progress_created_questions' => $seed_progress_created_questions,
            'seed_progress_synced_exam_questions' => $seed_progress_synced_exam_questions,
            'seed_progress_deleted_users' => $seed_progress_deleted_users,
            'seed_progress_failed_user_deletes' => $seed_progress_failed_user_deletes,
            'seed_progress_activity_detail' => $seed_progress_activity_detail,
            'seed_progress_status_tone' => $seed_progress_status_tone,
            'seed_progress_status_label' => $seed_progress_status_label,
            'seed_progress_summary_label' => $seed_progress_summary_label,
            'seed_progress_stage_preview' => $seed_progress_stage_preview,
        ];
    }
public static function handle_generate_test_dataset(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        CBT_Admin_Maintenance_Common::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_seed_progress_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_seed_progress_token'])) : '';
        if ($token !== '') {
            self::continue_generate_test_dataset($token);
        }

        check_admin_referer('cbt_generate_test_dataset');

        $preset_key = isset($_POST['preset']) ? sanitize_key((string) wp_unslash($_POST['preset'])) : '';
        $presets = self::test_data_seed_presets();
        if (!isset($presets[$preset_key])) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Pilih preset dataset uji yang valid.', 'seed');
        }

        $confirm_phrase = isset($_POST['confirm_phrase'])
            ? trim((string) sanitize_text_field(wp_unslash($_POST['confirm_phrase'])))
            : '';
        if ($confirm_phrase !== self::TEST_DATA_SEED_CONFIRM_PHRASE) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Konfirmasi tidak valid. Ketik persis: ' . self::TEST_DATA_SEED_CONFIRM_PHRASE,
                'seed'
            );
        }

        global $wpdb;

        $preset = $presets[$preset_key];
        $tables = CBT_Admin_Maintenance_Common::cbt_data_tables($wpdb);
        $reset_user_ids = CBT_Admin_Maintenance_Common::collect_cbt_user_ids_for_reset();
        $kelas_codes = self::build_test_data_seed_codes('KELAS_TEST_', (int) ($preset['classes'] ?? 0));
        $ruang_codes = self::build_test_data_seed_codes('RUANG_TEST_', (int) ($preset['rooms'] ?? 0));
        $seed_user_total = (int) ($preset['teachers'] ?? 0) + (int) ($preset['students'] ?? 0);
        $reset_user_total = count($reset_user_ids);
        $bank_exam_total = (int) ($preset['subjects'] ?? 0);
        $sync_exam_total = (int) ($preset['exams'] ?? 0);
        $total_units = count($tables)
            + max(1, $reset_user_total)
            + (int) ($preset['subjects'] ?? 0)
            + $seed_user_total
            + $bank_exam_total
            + (int) ($preset['exams'] ?? 0)
            + (int) ($preset['questions'] ?? 0)
            + $sync_exam_total
            + 1;

        $token = strtolower((string) wp_generate_password(24, false, false));
        $state = [
            'user_id' => get_current_user_id(),
            'started_at' => time(),
            'phase' => 'reset_tables',
            'preset_key' => $preset_key,
            'preset' => $preset,
            'tables' => $tables,
            'table_index' => 0,
            'failed_tables' => [],
            'foreign_keys_disabled' => 0,
            'reset_user_total' => $reset_user_total,
            'reset_user_offset' => 0,
            'reset_users_placeholder_done' => 0,
            'deleted_user_count' => 0,
            'failed_user_delete_count' => 0,
            'kelas_codes' => $kelas_codes,
            'ruang_codes' => $ruang_codes,
            'seed_subject_offset' => 0,
            'seed_subject_created_count' => 0,
            'seed_subject_updated_count' => 0,
            'seed_subject_failed_count' => 0,
            'subjects' => [],
            'seed_bank_exam_offset' => 0,
            'seed_bank_exam_created_count' => 0,
            'seed_bank_exam_failed_count' => 0,
            'bank_exams' => [],
            'seed_bulk_upload_cleanup_done' => 0,
            'seed_bulk_upload_cleanup_count' => 0,
            'bulk_image_source_map' => [],
            'seed_user_offset' => 0,
            'seed_user_created_count' => 0,
            'seed_user_updated_count' => 0,
            'seed_user_failed_count' => 0,
            'seed_teacher_processed' => 0,
            'seed_student_processed' => 0,
            'seed_exam_offset' => 0,
            'seed_exam_created_count' => 0,
            'seed_exam_updated_count' => 0,
            'seed_exam_failed_count' => 0,
            'exams' => [],
            'seed_bank_question_offset' => 0,
            'seed_bank_question_created_count' => 0,
            'seed_bank_question_failed_count' => 0,
            'seed_question_offset' => 0,
            'seed_question_created_count' => 0,
            'seed_question_failed_count' => 0,
            'question_type_counts' => [],
            'exam_question_counts' => [],
            'target_exam_source_question_ids' => [],
            'subject_source_question_ids' => [],
            'question_type_source_question_ids' => [],
            'sync_exam_offset' => 0,
            'sync_exam_synced_count' => 0,
            'synced_exam_question_counts' => [],
            'total_units' => max(1, $total_units),
            'processed_units' => 0,
        ];

        $state_saved = set_transient(self::get_seed_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        $users_saved = set_transient(self::get_seed_progress_users_key($token), array_values($reset_user_ids), 12 * HOUR_IN_SECONDS);
        if (!$state_saved || !$users_saved) {
            self::clear_seed_progress_transients($token);
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Gagal menyiapkan sesi generate data uji. Coba ulang lagi.', 'seed');
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page_args([
            'page' => 'cbt-maintenance',
            'cbt_maintenance_tab' => 'seed',
            'cbt_seed_progress_token' => $token,
        ]);
    }

    private static function continue_generate_test_dataset(string $token): void
    {
        $state = self::get_seed_progress_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_seed_progress_transients($token);
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Sesi generate data uji berakhir. Silakan mulai ulang generator.', 'seed');
        }

        $preset = self::normalize_test_data_seed_preset((array) ($state['preset'] ?? []));
        $reset_users = get_transient(self::get_seed_progress_users_key($token));
        if (!is_array($reset_users)) {
            $reset_users = [];
        }
        $reset_users = array_values(array_map('intval', $reset_users));

        $phase = sanitize_key((string) ($state['phase'] ?? 'reset_tables'));
        $tables = isset($state['tables']) && is_array($state['tables']) ? array_values((array) $state['tables']) : [];
        $table_total = count($tables);
        $table_index = max(0, min($table_total, (int) ($state['table_index'] ?? 0)));
        $reset_user_total = max(0, isset($state['reset_user_total']) ? (int) $state['reset_user_total'] : count($reset_users));
        $reset_user_offset = max(0, min($reset_user_total, (int) ($state['reset_user_offset'] ?? 0)));
        $reset_users_placeholder_done = !empty($state['reset_users_placeholder_done']) ? 1 : 0;
        $deleted_user_count = max(0, (int) ($state['deleted_user_count'] ?? 0));
        $failed_user_delete_count = max(0, (int) ($state['failed_user_delete_count'] ?? 0));
        $seed_subject_offset = max(0, min((int) ($preset['subjects'] ?? 0), (int) ($state['seed_subject_offset'] ?? 0)));
        $seed_subject_created_count = max(0, (int) ($state['seed_subject_created_count'] ?? 0));
        $seed_subject_updated_count = max(0, (int) ($state['seed_subject_updated_count'] ?? 0));
        $seed_subject_failed_count = max(0, (int) ($state['seed_subject_failed_count'] ?? 0));
        $subjects = isset($state['subjects']) && is_array($state['subjects']) ? array_values((array) $state['subjects']) : [];
        $seed_bank_exam_offset = max(0, min((int) ($preset['subjects'] ?? 0), (int) ($state['seed_bank_exam_offset'] ?? 0)));
        $seed_bank_exam_created_count = max(0, (int) ($state['seed_bank_exam_created_count'] ?? 0));
        $seed_bank_exam_failed_count = max(0, (int) ($state['seed_bank_exam_failed_count'] ?? 0));
        $bank_exams = isset($state['bank_exams']) && is_array($state['bank_exams']) ? (array) $state['bank_exams'] : [];
        $seed_bulk_upload_cleanup_done = !empty($state['seed_bulk_upload_cleanup_done']) ? 1 : 0;
        $seed_bulk_upload_cleanup_count = max(0, (int) ($state['seed_bulk_upload_cleanup_count'] ?? 0));
        $bulk_image_source_map = isset($state['bulk_image_source_map']) && is_array($state['bulk_image_source_map'])
            ? (array) $state['bulk_image_source_map']
            : [];
        $seed_user_total = (int) ($preset['teachers'] ?? 0) + (int) ($preset['students'] ?? 0);
        $seed_user_offset = max(0, min($seed_user_total, (int) ($state['seed_user_offset'] ?? 0)));
        $seed_user_created_count = max(0, (int) ($state['seed_user_created_count'] ?? 0));
        $seed_user_updated_count = max(0, (int) ($state['seed_user_updated_count'] ?? 0));
        $seed_user_failed_count = max(0, (int) ($state['seed_user_failed_count'] ?? 0));
        $seed_teacher_processed = max(0, (int) ($state['seed_teacher_processed'] ?? 0));
        $seed_student_processed = max(0, (int) ($state['seed_student_processed'] ?? 0));
        $seed_exam_offset = max(0, min((int) ($preset['exams'] ?? 0), (int) ($state['seed_exam_offset'] ?? 0)));
        $seed_exam_created_count = max(0, (int) ($state['seed_exam_created_count'] ?? 0));
        $seed_exam_updated_count = max(0, (int) ($state['seed_exam_updated_count'] ?? 0));
        $seed_exam_failed_count = max(0, (int) ($state['seed_exam_failed_count'] ?? 0));
        $exams = isset($state['exams']) && is_array($state['exams']) ? array_values((array) $state['exams']) : [];
        $seed_bank_question_offset = max(0, min((int) ($preset['questions'] ?? 0), (int) ($state['seed_bank_question_offset'] ?? ($state['seed_question_offset'] ?? 0))));
        $seed_bank_question_created_count = max(0, (int) ($state['seed_bank_question_created_count'] ?? ($state['seed_question_created_count'] ?? 0)));
        $seed_bank_question_failed_count = max(0, (int) ($state['seed_bank_question_failed_count'] ?? ($state['seed_question_failed_count'] ?? 0)));
        $seed_question_offset = max(0, min((int) ($preset['questions'] ?? 0), (int) ($state['seed_question_offset'] ?? 0)));
        $seed_question_created_count = max(0, (int) ($state['seed_question_created_count'] ?? 0));
        $seed_question_failed_count = max(0, (int) ($state['seed_question_failed_count'] ?? 0));
        $question_type_counts = isset($state['question_type_counts']) && is_array($state['question_type_counts'])
            ? (array) $state['question_type_counts']
            : [];
        $exam_question_counts = isset($state['exam_question_counts']) && is_array($state['exam_question_counts'])
            ? (array) $state['exam_question_counts']
            : [];
        $target_exam_source_question_ids = isset($state['target_exam_source_question_ids']) && is_array($state['target_exam_source_question_ids'])
            ? (array) $state['target_exam_source_question_ids']
            : [];
        $subject_source_question_ids = isset($state['subject_source_question_ids']) && is_array($state['subject_source_question_ids'])
            ? (array) $state['subject_source_question_ids']
            : [];
        $question_type_source_question_ids = isset($state['question_type_source_question_ids']) && is_array($state['question_type_source_question_ids'])
            ? (array) $state['question_type_source_question_ids']
            : [];
        $sync_exam_offset = max(0, min((int) ($preset['exams'] ?? 0), (int) ($state['sync_exam_offset'] ?? 0)));
        $sync_exam_synced_count = max(0, (int) ($state['sync_exam_synced_count'] ?? 0));
        $synced_exam_question_counts = isset($state['synced_exam_question_counts']) && is_array($state['synced_exam_question_counts'])
            ? (array) $state['synced_exam_question_counts']
            : [];
        $kelas_codes = isset($state['kelas_codes']) && is_array($state['kelas_codes'])
            ? array_values(array_filter(array_map('sanitize_text_field', (array) $state['kelas_codes'])))
            : [];
        $ruang_codes = isset($state['ruang_codes']) && is_array($state['ruang_codes'])
            ? array_values(array_filter(array_map('sanitize_text_field', (array) $state['ruang_codes'])))
            : [];
        $failed_tables = [];
        if (isset($state['failed_tables']) && is_array($state['failed_tables'])) {
            foreach ($state['failed_tables'] as $failed_table) {
                $failed_table = str_replace('`', '', (string) $failed_table);
                if ($failed_table !== '') {
                    $failed_tables[$failed_table] = $failed_table;
                }
            }
        }

        $total_units = max(1, (int) ($state['total_units'] ?? 1));
        $max_batch_seconds = self::get_test_data_seed_max_batch_seconds();
        $batch_started_at = microtime(true);

        global $wpdb;

        if (!$seed_bulk_upload_cleanup_done) {
            $seed_bulk_upload_cleanup_count = self::cleanup_test_data_seed_bulk_upload_files();
            $seed_bulk_upload_cleanup_done = 1;
        }

        if ($phase === 'reset_tables') {
            if (empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
                $state['foreign_keys_disabled'] = 1;
            }

            $processed_tables_this_round = 0;
            $table_batch_size = CBT_Admin_Maintenance_Common::get_reset_progress_table_batch_size();
            while ($table_index < $table_total && $processed_tables_this_round < $table_batch_size) {
                $safe_table = str_replace('`', '', (string) $tables[$table_index]);
                if ($safe_table !== '') {
                    $truncated = $wpdb->query("TRUNCATE TABLE `{$safe_table}`");
                    if ($truncated === false) {
                        $deleted = $wpdb->query("DELETE FROM `{$safe_table}`");
                        if ($deleted === false) {
                            $failed_tables[$safe_table] = $safe_table;
                        } else {
                            $wpdb->query("ALTER TABLE `{$safe_table}` AUTO_INCREMENT = 1");
                        }
                    }
                }

                $table_index++;
                $processed_tables_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($table_index >= $table_total) {
                if (!empty($state['foreign_keys_disabled'])) {
                    $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
                }
                $state['foreign_keys_disabled'] = 0;

                if (!empty($failed_tables)) {
                    self::clear_seed_progress_transients($token);
                    CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                        null,
                        'Generator data uji dibatalkan karena sebagian tabel gagal direset: ' . implode(', ', array_values($failed_tables)),
                        'seed'
                    );
                }

                $phase = 'reset_users';
            }
        }

        if ($phase === 'reset_users' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            if ($reset_user_total <= 0) {
                $reset_users_placeholder_done = 1;
                $phase = 'seed_subjects';
            } else {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $target_offset = min($reset_user_offset + CBT_Admin_Maintenance_Common::get_reset_progress_user_batch_size(), $reset_user_total);
                for ($index = $reset_user_offset; $index < $target_offset; $index++) {
                    $user_id = isset($reset_users[$index]) ? (int) $reset_users[$index] : 0;
                    if ($user_id <= 0) {
                        continue;
                    }

                    $deleted = wp_delete_user($user_id);
                    if ($deleted) {
                        $deleted_user_count++;
                    } else {
                        $failed_user_delete_count++;
                    }

                    if (($index - $reset_user_offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                        $target_offset = $index + 1;
                        break;
                    }
                }
                $reset_user_offset = $target_offset;
                if ($reset_user_offset >= $reset_user_total) {
                    $phase = 'seed_subjects';
                }
            }
        }

        if ($phase === 'seed_subjects' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $subject_total = (int) ($preset['subjects'] ?? 0);
            $subject_batch_size = self::get_test_data_seed_batch_size('subjects');
            $processed_subjects_this_round = 0;

            while ($seed_subject_offset < $subject_total && $processed_subjects_this_round < $subject_batch_size) {
                $subject_entry = self::build_test_data_seed_subject_entry($seed_subject_offset);
                $upserted_subject = self::upsert_test_data_seed_subject($subject_entry);
                $subject_id = (int) ($upserted_subject['id'] ?? 0);
                $subject_status = (string) ($upserted_subject['status'] ?? 'failed');

                if ($subject_id > 0) {
                    $subjects[$seed_subject_offset] = [
                        'id' => $subject_id,
                        'name' => (string) ($upserted_subject['name'] ?? $subject_entry['name']),
                        'code' => (string) ($upserted_subject['code'] ?? $subject_entry['code']),
                        'image_bucket' => sanitize_key((string) ($subject_entry['image_bucket'] ?? self::build_test_data_seed_subject_image_bucket($seed_subject_offset))),
                    ];
                    if ($subject_status === 'updated') {
                        $seed_subject_updated_count++;
                    } else {
                        $seed_subject_created_count++;
                    }
                } else {
                    $seed_subject_failed_count++;
                }

                $seed_subject_offset++;
                $processed_subjects_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($seed_subject_offset >= $subject_total) {
                ksort($subjects);
                $subjects = array_values($subjects);
                if ($seed_subject_failed_count > 0 || count($subjects) < $subject_total) {
                    self::clear_seed_progress_transients($token);
                    CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                        null,
                        'Generator data uji berhenti karena ada subject yang gagal dibuat atau disinkronkan.',
                        'seed'
                    );
                }
                $phase = 'seed_users';
            }
        }

        if ($phase === 'seed_users' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $user_batch_size = self::get_test_data_seed_batch_size('users');
            $seed_rows = self::build_test_data_seed_user_rows($preset, $seed_user_offset, $user_batch_size, $kelas_codes, $ruang_codes);
            $lookup = self::build_user_import_lookup($seed_rows, 0, count($seed_rows));
            $cache_invalidation_prev = null;
            if (function_exists('wp_suspend_cache_invalidation')) {
                $cache_invalidation_prev = wp_suspend_cache_invalidation(true);
            }

            foreach ($seed_rows as $row) {
                $seed_kind = sanitize_key((string) ($row['__seed_kind'] ?? 'student'));
                unset($row['__seed_kind']);

                try {
                    $result = self::upsert_user_from_row($row, $lookup);
                } catch (Throwable $exception) {
                    $result = 'failed';
                }

                if ($result === 'created') {
                    $seed_user_created_count++;
                } elseif ($result === 'updated') {
                    $seed_user_updated_count++;
                } else {
                    $seed_user_failed_count++;
                }

                if ($seed_kind === 'teacher') {
                    $seed_teacher_processed++;
                } else {
                    $seed_student_processed++;
                }

                $seed_user_offset++;

                if (($seed_user_offset % 2) === 0 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($cache_invalidation_prev !== null && function_exists('wp_suspend_cache_invalidation')) {
                wp_suspend_cache_invalidation((bool) $cache_invalidation_prev);
            }

            if ($seed_user_offset >= $seed_user_total) {
                if (
                    $seed_user_failed_count > 0
                    || $seed_teacher_processed < (int) ($preset['teachers'] ?? 0)
                    || $seed_student_processed < (int) ($preset['students'] ?? 0)
                ) {
                    self::clear_seed_progress_transients($token);
                    CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                        null,
                        'Generator data uji berhenti karena ada user guru/siswa yang gagal dibuat atau disinkronkan.',
                        'seed'
                    );
                }
                $phase = 'seed_bank_exams';
            }
        }

        if ($phase === 'seed_bank_exams' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $bank_exam_total = (int) ($preset['subjects'] ?? 0);
            $bank_exam_batch_size = self::get_test_data_seed_batch_size('subjects');
            $processed_bank_exams_this_round = 0;
            $creator_id = isset($state['user_id']) ? (int) $state['user_id'] : get_current_user_id();

            while ($seed_bank_exam_offset < $bank_exam_total && $processed_bank_exams_this_round < $bank_exam_batch_size) {
                $subject_entry = isset($subjects[$seed_bank_exam_offset]) ? (array) $subjects[$seed_bank_exam_offset] : [];
                $subject_id = (int) ($subject_entry['id'] ?? 0);
                $subject_name = sanitize_text_field((string) ($subject_entry['name'] ?? ''));

                $bank_exam_id = CBT_Admin_Questions_Helper::ensure_subject_question_bank_exam($subject_id, true, $creator_id);
                if ($bank_exam_id > 0) {
                    $bank_exams[$subject_id] = [
                        'id' => $bank_exam_id,
                        'subject_id' => $subject_id,
                        'subject_name' => $subject_name,
                        'title' => 'Bank Soal - ' . $subject_name,
                        'image_bucket' => sanitize_key((string) ($subject_entry['image_bucket'] ?? '')),
                    ];
                    $seed_bank_exam_created_count++;
                } else {
                    $seed_bank_exam_failed_count++;
                }

                $seed_bank_exam_offset++;
                $processed_bank_exams_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($seed_bank_exam_offset >= $bank_exam_total) {
                if ($seed_bank_exam_failed_count > 0 || count($bank_exams) < $bank_exam_total) {
                    self::clear_seed_progress_transients($token);
                    CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                        null,
                        'Generator data uji berhenti karena ada bank soal per mapel yang gagal disiapkan.',
                        'seed'
                    );
                }
                $phase = 'seed_exams';
            }
        }

        if ($phase === 'seed_exams' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $exam_total = (int) ($preset['exams'] ?? 0);
            $exam_batch_size = self::get_test_data_seed_batch_size('exams');
            $processed_exams_this_round = 0;
            $creator_id = isset($state['user_id']) ? (int) $state['user_id'] : get_current_user_id();
            $exam_profiles = self::build_test_data_seed_exam_profiles((string) ($state['preset_key'] ?? 'small'), $exam_total);

            while ($seed_exam_offset < $exam_total && $processed_exams_this_round < $exam_batch_size) {
                $exam_profile = isset($exam_profiles[$seed_exam_offset]) && is_array($exam_profiles[$seed_exam_offset])
                    ? (array) $exam_profiles[$seed_exam_offset]
                    : self::build_test_data_seed_exam_profile_definition('mixed');
                $exam_entry = self::build_test_data_seed_exam_entry(
                    $seed_exam_offset,
                    $subjects,
                    $kelas_codes,
                    (string) ($state['preset_key'] ?? 'small'),
                    $creator_id,
                    $exam_profile
                );
                $upserted_exam = self::upsert_test_data_seed_exam($exam_entry);
                $exam_id = (int) ($upserted_exam['id'] ?? 0);
                $exam_status = (string) ($upserted_exam['status'] ?? 'failed');

                if ($exam_id > 0) {
                    $exams[$seed_exam_offset] = [
                        'id' => $exam_id,
                        'subject_id' => (int) ($upserted_exam['subject_id'] ?? ($exam_entry['subject_id'] ?? 0)),
                        'subject_name' => (string) ($upserted_exam['subject_name'] ?? ($exam_entry['subject_name'] ?? '')),
                        'title' => (string) ($upserted_exam['title'] ?? ($exam_entry['title'] ?? '')),
                        'target_kelas' => (string) ($upserted_exam['target_kelas'] ?? ($exam_entry['target_kelas'] ?? '')),
                        'seed_profile' => (string) ($exam_entry['seed_profile'] ?? 'mixed'),
                        'seed_profile_label' => (string) ($exam_entry['seed_profile_label'] ?? 'MIXED'),
                        'seed_profile_question_type' => (string) ($exam_entry['seed_profile_question_type'] ?? ''),
                        'seed_image_bucket' => (string) ($exam_entry['seed_image_bucket'] ?? ''),
                    ];
                    if ($exam_status === 'updated') {
                        $seed_exam_updated_count++;
                    } else {
                        $seed_exam_created_count++;
                    }
                } else {
                    $seed_exam_failed_count++;
                }

                $seed_exam_offset++;
                $processed_exams_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($seed_exam_offset >= $exam_total) {
                ksort($exams);
                $exams = array_values($exams);
                if ($seed_exam_failed_count > 0 || count($exams) < $exam_total) {
                    self::clear_seed_progress_transients($token);
                    CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                        null,
                        'Generator data uji berhenti karena ada exam yang gagal dibuat atau disinkronkan.',
                        'seed'
                    );
                }
                $phase = 'seed_bank_questions';
            }
        }

        if ($phase === 'seed_bank_questions' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $question_total = (int) ($preset['questions'] ?? 0);
            $question_batch_size = self::get_test_data_seed_batch_size('questions');
            $processed_questions_this_round = 0;

            while ($seed_bank_question_offset < $question_total && $processed_questions_this_round < $question_batch_size) {
                $question_row = self::build_test_data_seed_bank_question_row(
                    $seed_bank_question_offset,
                    $exams,
                    $bank_exams,
                    (string) ($state['preset_key'] ?? 'small'),
                    $bulk_image_source_map
                );

                try {
                    $result = self::insert_test_data_seed_question($question_row);
                } catch (Throwable $exception) {
                    $result = [
                        'status' => 'failed',
                        'question_id' => 0,
                    ];
                }

                if (($result['status'] ?? 'failed') === 'created') {
                    $seed_bank_question_created_count++;
                    $question_type = sanitize_key((string) ($question_row['question_type'] ?? ''));
                    $target_exam_id = absint($question_row['target_exam_id'] ?? 0);
                    $source_question_id = absint($result['question_id'] ?? 0);
                    if ($question_type !== '') {
                        $question_type_counts[$question_type] = isset($question_type_counts[$question_type])
                            ? ((int) $question_type_counts[$question_type] + 1)
                            : 1;
                    }
                    $subject_id = absint($question_row['subject_id'] ?? 0);
                    if ($target_exam_id > 0 && $source_question_id > 0) {
                        if (!isset($target_exam_source_question_ids[$target_exam_id]) || !is_array($target_exam_source_question_ids[$target_exam_id])) {
                            $target_exam_source_question_ids[$target_exam_id] = [];
                        }
                        $target_exam_source_question_ids[$target_exam_id][] = $source_question_id;
                    }
                    if ($subject_id > 0 && $source_question_id > 0) {
                        if (!isset($subject_source_question_ids[$subject_id]) || !is_array($subject_source_question_ids[$subject_id])) {
                            $subject_source_question_ids[$subject_id] = [];
                        }
                        $subject_source_question_ids[$subject_id][] = $source_question_id;
                    }
                    if ($question_type !== '' && $source_question_id > 0) {
                        if (!isset($question_type_source_question_ids[$question_type]) || !is_array($question_type_source_question_ids[$question_type])) {
                            $question_type_source_question_ids[$question_type] = [];
                        }
                        $question_type_source_question_ids[$question_type][] = $source_question_id;
                    }
                } else {
                    $seed_bank_question_failed_count++;
                }

                $seed_bank_question_offset++;
                $processed_questions_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($seed_bank_question_offset >= $question_total) {
                if ($seed_bank_question_failed_count > 0 || $seed_bank_question_created_count < $question_total) {
                    self::clear_seed_progress_transients($token);
                    CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                        null,
                        'Generator data uji berhenti karena ada bank question yang gagal dibuat.',
                        'seed'
                    );
                }
                $phase = 'sync_exam_questions';
            }
        }

        if ($phase === 'sync_exam_questions' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $exam_total = count($exams);
            $sync_exam_batch_size = self::get_test_data_seed_batch_size('exams');
            $processed_sync_this_round = 0;
            $creator_id = isset($state['user_id']) ? (int) ($state['user_id']) : get_current_user_id();

            while ($sync_exam_offset < $exam_total && $processed_sync_this_round < $sync_exam_batch_size) {
                $exam_entry = isset($exams[$sync_exam_offset]) ? (array) $exams[$sync_exam_offset] : [];
                $target_exam_id = (int) ($exam_entry['id'] ?? 0);
                $source_question_ids = self::build_test_data_seed_sync_source_question_ids_for_exam(
                    $exam_entry,
                    $exams,
                    $target_exam_source_question_ids,
                    $subject_source_question_ids,
                    $question_type_source_question_ids,
                    (string) ($state['preset_key'] ?? 'small')
                );

                if ($target_exam_id > 0) {
                    if (!empty($source_question_ids)) {
                        $sync_result = CBT_Admin_Exams_Service::sync_exam_questions_from_sources_for_internal_use(
                            $target_exam_id,
                            $source_question_ids,
                            $creator_id
                        );
                        if (is_wp_error($sync_result)) {
                            self::clear_seed_progress_transients($token);
                            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                                null,
                                'Generator data uji berhenti karena sinkronisasi soal exam gagal: ' . $sync_result->get_error_message(),
                                'seed'
                            );
                        }

                        $synced_exam_question_counts[$target_exam_id] = (int) $sync_result;
                    } else {
                        $synced_exam_question_counts[$target_exam_id] = 0;
                    }
                    $sync_exam_synced_count++;
                }

                $sync_exam_offset++;
                $processed_sync_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($sync_exam_offset >= $exam_total) {
                $phase = 'finalize';
            }
        }

        $state['phase'] = $phase;
        $state['table_index'] = $table_index;
        $state['reset_user_total'] = $reset_user_total;
        $state['reset_user_offset'] = $reset_user_offset;
        $state['reset_users_placeholder_done'] = $reset_users_placeholder_done;
        $state['deleted_user_count'] = $deleted_user_count;
        $state['failed_user_delete_count'] = $failed_user_delete_count;
        $state['failed_tables'] = array_values($failed_tables);
        $state['seed_subject_offset'] = $seed_subject_offset;
        $state['seed_subject_created_count'] = $seed_subject_created_count;
        $state['seed_subject_updated_count'] = $seed_subject_updated_count;
        $state['seed_subject_failed_count'] = $seed_subject_failed_count;
        $state['subjects'] = array_values($subjects);
        $state['seed_bank_exam_offset'] = $seed_bank_exam_offset;
        $state['seed_bank_exam_created_count'] = $seed_bank_exam_created_count;
        $state['seed_bank_exam_failed_count'] = $seed_bank_exam_failed_count;
        $state['bank_exams'] = $bank_exams;
        $state['seed_bulk_upload_cleanup_done'] = $seed_bulk_upload_cleanup_done;
        $state['seed_bulk_upload_cleanup_count'] = $seed_bulk_upload_cleanup_count;
        $state['bulk_image_source_map'] = $bulk_image_source_map;
        $state['seed_user_offset'] = $seed_user_offset;
        $state['seed_user_created_count'] = $seed_user_created_count;
        $state['seed_user_updated_count'] = $seed_user_updated_count;
        $state['seed_user_failed_count'] = $seed_user_failed_count;
        $state['seed_teacher_processed'] = $seed_teacher_processed;
        $state['seed_student_processed'] = $seed_student_processed;
        $state['seed_exam_offset'] = $seed_exam_offset;
        $state['seed_exam_created_count'] = $seed_exam_created_count;
        $state['seed_exam_updated_count'] = $seed_exam_updated_count;
        $state['seed_exam_failed_count'] = $seed_exam_failed_count;
        $state['exams'] = array_values($exams);
        $state['seed_bank_question_offset'] = $seed_bank_question_offset;
        $state['seed_bank_question_created_count'] = $seed_bank_question_created_count;
        $state['seed_bank_question_failed_count'] = $seed_bank_question_failed_count;
        $state['seed_question_offset'] = $seed_bank_question_offset;
        $state['seed_question_created_count'] = $seed_bank_question_created_count;
        $state['seed_question_failed_count'] = $seed_bank_question_failed_count;
        $state['question_type_counts'] = $question_type_counts;
        $state['exam_question_counts'] = $exam_question_counts;
        $state['target_exam_source_question_ids'] = $target_exam_source_question_ids;
        $state['subject_source_question_ids'] = $subject_source_question_ids;
        $state['question_type_source_question_ids'] = $question_type_source_question_ids;
        $state['sync_exam_offset'] = $sync_exam_offset;
        $state['sync_exam_synced_count'] = $sync_exam_synced_count;
        $state['synced_exam_question_counts'] = $synced_exam_question_counts;
        $state['total_units'] = $total_units;
        $state['processed_units'] = self::calculate_test_data_seed_processed_units($state);

        if ($phase === 'finalize' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            $exam_table = $wpdb->prefix . 'cbt_exams';
            foreach ($synced_exam_question_counts as $exam_id => $count) {
                $exam_id = (int) $exam_id;
                if ($exam_id <= 0) {
                    continue;
                }

                $wpdb->update(
                    $exam_table,
                    [
                        'total_questions' => max(0, (int) $count),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $exam_id],
                    ['%d', '%s'],
                    ['%d']
                );
            }

            CBT_Admin_Maintenance_Common::reset_cbt_global_token_options();
            CBT_UI_State::clear_all();
            CBT_Cache::reset_plugin_cache_state();
            self::clear_seed_progress_transients($token);

            $message = sprintf(
                'Dataset uji preset %s selesai. Subject: %d, Exam: %d, Bank Question: %d, Synced Question: %d, Guru: %d, Siswa: %d. Password default: %s. Akun test khusus: %s / %s.',
                (string) ($preset['label'] ?? ucfirst((string) ($state['preset_key'] ?? 'small'))),
                (int) ($preset['subjects'] ?? 0),
                (int) ($preset['exams'] ?? 0),
                (int) ($seed_bank_question_created_count),
                (int) array_sum(array_map('intval', $synced_exam_question_counts)),
                (int) ($preset['teachers'] ?? 0),
                (int) ($preset['students'] ?? 0),
                self::TEST_DATA_SEED_DEFAULT_PASSWORD,
                self::TEST_DATA_SEED_SPECIAL_USERNAME,
                self::TEST_DATA_SEED_SPECIAL_PASSWORD
            );
            if ($failed_user_delete_count > 0) {
                $message .= ' User lama yang gagal dihapus: ' . $failed_user_delete_count . '.';
            }

            CBT_Admin_Maintenance_Common::redirect_maintenance_page($message, null, 'seed');
        }

        $state_saved = set_transient(self::get_seed_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$state_saved) {
            if (!empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::clear_seed_progress_transients($token);
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Gagal menyimpan progres generator data uji. Silakan mulai ulang.', 'seed');
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page_args([
            'page' => 'cbt-maintenance',
            'cbt_maintenance_tab' => 'seed',
            'cbt_seed_progress_token' => $token,
        ]);
    }

    private static function redirect_maintenance_page(?string $message = null, ?string $error = null, ?string $tab = null): void
    {
        $args = ['page' => 'cbt-maintenance'];
        if ($tab === null || $tab === '') {
            $requested_tab = isset($_REQUEST['cbt_maintenance_tab'])
                ? sanitize_key((string) wp_unslash($_REQUEST['cbt_maintenance_tab']))
                : '';
            if (in_array($requested_tab, self::allowed_maintenance_tabs(), true)) {
                $tab = $requested_tab;
            }
        }
        if ($tab !== null && $tab !== '' && in_array($tab, self::allowed_maintenance_tabs(), true)) {
            $args['cbt_maintenance_tab'] = $tab;
        }
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return string[]
     */
    private static function allowed_maintenance_tabs(): array
    {
        return ['reset', 'seed', 'load'];
    }

    private static function get_reset_progress_state_key(string $token): string
    {
        return 'cbt_reset_progress_' . $token;
    }

    private static function get_reset_progress_users_key(string $token): string
    {
        return 'cbt_reset_progress_users_' . $token;
    }

    private static function clear_reset_progress_transients(string $token): void
    {
        delete_transient(self::get_reset_progress_state_key($token));
        delete_transient(self::get_reset_progress_users_key($token));
    }

    private static function get_reset_progress_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_reset_progress_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function get_reset_progress_table_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_reset_progress_table_batch_size', 2);
        if ($batch_size < 1) {
            return 1;
        }
        if ($batch_size > 10) {
            return 10;
        }

        return $batch_size;
    }

    private static function get_reset_progress_user_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_reset_progress_user_batch_size', 140);
        if ($batch_size < 20) {
            return 20;
        }
        if ($batch_size > 500) {
            return 500;
        }

        return $batch_size;
    }

    private static function get_reset_progress_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_reset_progress_batch_max_seconds', 8.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function get_seed_progress_state_key(string $token): string
    {
        return 'cbt_seed_progress_' . $token;
    }

    private static function get_seed_progress_users_key(string $token): string
    {
        return 'cbt_seed_progress_users_' . $token;
    }

    private static function clear_seed_progress_transients(string $token): void
    {
        delete_transient(self::get_seed_progress_state_key($token));
        delete_transient(self::get_seed_progress_users_key($token));
    }

    private static function get_seed_progress_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_seed_progress_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    /**
     * @return array<string,array{label:string,subjects:int,exams:int,questions:int,questions_per_type:int,students:int,teachers:int,classes:int,rooms:int,include_true_false_matrix:bool}>
     */
    private static function test_data_seed_presets(): array
    {
        $question_type_total = count(self::get_test_data_seed_all_question_types());

        return [
            'small' => [
                'label' => 'Small',
                'subjects' => 5,
                'exams' => self::TEST_DATA_SEED_SHARED_EXAM_TOTAL,
                'questions' => 60 * $question_type_total,
                'questions_per_type' => 60,
                'students' => 60,
                'teachers' => 6,
                'classes' => 6,
                'rooms' => 3,
                'include_true_false_matrix' => true,
            ],
            'medium' => [
                'label' => 'Medium',
                'subjects' => 5,
                'exams' => self::TEST_DATA_SEED_SHARED_EXAM_TOTAL,
                'questions' => 100 * $question_type_total,
                'questions_per_type' => 100,
                'students' => 300,
                'teachers' => 18,
                'classes' => 12,
                'rooms' => 6,
                'include_true_false_matrix' => true,
            ],
            'large' => [
                'label' => 'Large',
                'subjects' => 5,
                'exams' => self::TEST_DATA_SEED_SHARED_EXAM_TOTAL,
                'questions' => 200 * $question_type_total,
                'questions_per_type' => 200,
                'students' => 1200,
                'teachers' => 48,
                'classes' => 24,
                'rooms' => 12,
                'include_true_false_matrix' => true,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{label:string,subjects:int,exams:int,questions:int,questions_per_type:int,students:int,teachers:int,classes:int,rooms:int,include_true_false_matrix:bool}
     */
    private static function normalize_test_data_seed_preset(array $raw): array
    {
        $defaults = self::test_data_seed_presets()['small'];
        $question_type_total = max(1, count(self::get_test_data_seed_all_question_types()));
        $questions_per_type = isset($raw['questions_per_type'])
            ? max(0, (int) $raw['questions_per_type'])
            : max(0, (int) floor(((int) ($raw['questions'] ?? $defaults['questions'])) / $question_type_total));
        if ($questions_per_type <= 0) {
            $questions_per_type = (int) $defaults['questions_per_type'];
        }
        $question_total = isset($raw['questions'])
            ? max(0, (int) $raw['questions'])
            : ($questions_per_type * $question_type_total);

        return [
            'label' => isset($raw['label']) ? sanitize_text_field((string) $raw['label']) : (string) $defaults['label'],
            'subjects' => max(0, isset($raw['subjects']) ? (int) $raw['subjects'] : (int) $defaults['subjects']),
            'exams' => max(0, isset($raw['exams']) ? (int) $raw['exams'] : (int) $defaults['exams']),
            'questions' => $question_total,
            'questions_per_type' => $questions_per_type,
            'students' => max(0, isset($raw['students']) ? (int) $raw['students'] : (int) $defaults['students']),
            'teachers' => max(0, isset($raw['teachers']) ? (int) $raw['teachers'] : (int) $defaults['teachers']),
            'classes' => max(0, isset($raw['classes']) ? (int) $raw['classes'] : (int) $defaults['classes']),
            'rooms' => max(0, isset($raw['rooms']) ? (int) $raw['rooms'] : (int) $defaults['rooms']),
            'include_true_false_matrix' => !empty($raw['include_true_false_matrix']),
        ];
    }

    /**
     * @return string[]
     */
    private static function get_test_data_seed_all_question_types(): array
    {
        return [
            'multiple_choice',
            'multiple_answer',
            'true_false',
            'true_false_matrix',
            'short_answer',
            'essay',
            'ordering',
            'matching',
            'cloze_dropdown',
            'categorization',
            'table_completion',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function get_test_data_seed_question_type_labels(): array
    {
        return [
            'multiple_choice' => 'Multiple Choice',
            'multiple_answer' => 'Multiple Answer',
            'true_false' => 'True/False',
            'true_false_matrix' => 'True/False Matrix',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
            'ordering' => 'Ordering',
            'matching' => 'Matching',
            'cloze_dropdown' => 'Cloze Dropdown',
            'categorization' => 'Categorization',
            'table_completion' => 'Table Completion',
        ];
    }

    /**
     * @return array<string,array{profile:string,label:string,suffix:string,question_type:string}>
     */
    private static function get_test_data_seed_exam_profile_definitions(): array
    {
        $labels = self::get_test_data_seed_question_type_labels();
        $definitions = [];
        foreach (self::get_test_data_seed_all_question_types() as $question_type) {
            $label = strtoupper((string) ($labels[$question_type] ?? str_replace('_', ' ', $question_type)));
            $definitions[$question_type] = [
                'profile' => 'type_all_' . $question_type,
                'label' => $label,
                'suffix' => '[' . $label . ']',
                'question_type' => $question_type,
            ];
        }

        $definitions['mixed'] = [
            'profile' => 'mixed_50',
            'label' => 'MIXED 50%',
            'suffix' => '[MIXED 50%]',
            'question_type' => '',
        ];
        $definitions['mixed_50'] = $definitions['mixed'];

        return $definitions;
    }

    /**
     * @return array<string,string>
     */
    private static function get_test_data_seed_exam_profile_labels(): array
    {
        $definitions = self::get_test_data_seed_exam_profile_definitions();
        $labels = [];
        foreach ($definitions as $definition) {
            $profile_key = sanitize_key((string) ($definition['profile'] ?? ''));
            if ($profile_key === '') {
                continue;
            }
            $labels[$profile_key] = (string) ($definition['label'] ?? strtoupper(str_replace('_', ' ', $profile_key)));
        }

        return $labels;
    }

    /**
     * @return string[]
     */
    private static function get_test_data_seed_image_bucket_keys(): array
    {
        return [
            'biology',
            'chemistry',
            'computer_science',
            'economics',
            'engineering',
            'geography',
            'history',
            'mathematics',
            'music',
            'physics',
        ];
    }

    private static function build_test_data_seed_subject_image_bucket(int $offset): string
    {
        $buckets = self::get_test_data_seed_image_bucket_keys();
        if (empty($buckets)) {
            return 'mathematics';
        }

        return (string) $buckets[$offset % count($buckets)];
    }

    private static function format_test_data_seed_image_bucket_label(string $bucket): string
    {
        $bucket = sanitize_key($bucket);
        if ($bucket === '') {
            return 'General';
        }

        return ucwords(str_replace('_', ' ', $bucket));
    }

    /**
     * @return array<string,array<int,string>>
     */
    private static function get_test_data_seed_sample_images(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $base_dir = trailingslashit(CBT_EXAM_SYSTEM_PATH . self::TEST_DATA_SEED_SAMPLE_IMAGE_DIRECTORY);
        $extensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

        foreach (self::get_test_data_seed_image_bucket_keys() as $bucket) {
            $bucket_dir = $base_dir . $bucket;
            $files = [];
            if (is_dir($bucket_dir)) {
                foreach ($extensions as $extension) {
                    $matches = glob($bucket_dir . '/*.' . $extension);
                    if (is_array($matches) && !empty($matches)) {
                        $files = array_merge($files, $matches);
                    }
                }
            }

            $files = array_values(array_unique(array_filter(array_map('strval', $files))));
            usort($files, [self::class, 'compare_test_data_seed_asset_paths']);
            $cache[$bucket] = $files;
        }

        return $cache;
    }

    private static function compare_test_data_seed_asset_paths(string $left, string $right): int
    {
        $left_name = (string) pathinfo($left, PATHINFO_FILENAME);
        $right_name = (string) pathinfo($right, PATHINFO_FILENAME);
        $left_match = [];
        $right_match = [];
        $left_index = preg_match('/(\d+)$/', $left_name, $left_match) === 1 ? (int) $left_match[1] : PHP_INT_MAX;
        $right_index = preg_match('/(\d+)$/', $right_name, $right_match) === 1 ? (int) $right_match[1] : PHP_INT_MAX;

        if ($left_index === $right_index) {
            return strnatcasecmp($left_name, $right_name);
        }

        return $left_index <=> $right_index;
    }

    private static function resolve_test_data_seed_sample_image_path(string $bucket, int $position): string
    {
        $images = self::get_test_data_seed_sample_images();
        $bucket = sanitize_key($bucket);
        $candidate_list = isset($images[$bucket]) && is_array($images[$bucket]) ? array_values($images[$bucket]) : [];

        if (empty($candidate_list)) {
            foreach ($images as $fallback_list) {
                if (is_array($fallback_list) && !empty($fallback_list)) {
                    $candidate_list = array_values($fallback_list);
                    break;
                }
            }
        }

        if (empty($candidate_list)) {
            return '';
        }

        $index = $position > 0 ? (($position - 1) % count($candidate_list)) : 0;

        return (string) ($candidate_list[$index] ?? '');
    }

    private static function resolve_test_data_seed_sample_image_src(string $bucket, int $position, array &$source_map): string
    {
        $asset_path = self::resolve_test_data_seed_sample_image_path($bucket, $position);
        if ($asset_path === '' || !is_string($asset_path)) {
            return '';
        }

        $asset_key = str_replace('\\', '/', str_replace(CBT_EXAM_SYSTEM_PATH, '', $asset_path));
        $asset_key = ltrim($asset_key, '/');
        if ($asset_key !== '' && isset($source_map[$asset_key]) && is_string($source_map[$asset_key])) {
            return (string) $source_map[$asset_key];
        }

        $plugin_asset_url = self::build_test_data_seed_plugin_asset_url($asset_key);
        if ($plugin_asset_url !== '') {
            $source_map[$asset_key] = $plugin_asset_url;

            return $plugin_asset_url;
        }

        $binary = @file_get_contents($asset_path);
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        $src = self::store_test_data_seed_image_and_get_url($binary, basename($asset_path));
        if ($src !== '' && $asset_key !== '') {
            $source_map[$asset_key] = $src;
        }

        return $src;
    }

    private static function build_test_data_seed_plugin_asset_url(string $asset_key): string
    {
        $asset_key = ltrim(str_replace('\\', '/', trim($asset_key)), '/');
        if ($asset_key === '' || str_contains($asset_key, '..') || !defined('CBT_EXAM_SYSTEM_URL')) {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $asset_key), static function (string $segment): bool {
            return $segment !== '';
        }));
        if (empty($segments)) {
            return '';
        }

        return esc_url_raw(trailingslashit(CBT_EXAM_SYSTEM_URL) . implode('/', array_map('rawurlencode', $segments)));
    }

    private static function store_test_data_seed_image_and_get_url(string $binary, string $filename): string
    {
        if ($binary === '') {
            return '';
        }

        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'png';
        }

        $safe_ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if ($safe_ext === '') {
            $safe_ext = 'png';
        }

        $upload_name = self::TEST_DATA_SEED_BULK_UPLOAD_FILE_PREFIX . wp_generate_password(10, false, false) . '.' . $safe_ext;
        $upload = wp_upload_bits($upload_name, null, $binary);
        if (is_array($upload) && empty($upload['error']) && !empty($upload['url'])) {
            return esc_url_raw((string) $upload['url']);
        }

        $mime = self::guess_test_data_seed_image_mime_from_extension($safe_ext);

        return strlen($binary) <= 65536
            ? 'data:' . $mime . ';base64,' . base64_encode($binary)
            : '';
    }

    private static function guess_test_data_seed_image_mime_from_extension(string $ext): string
    {
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'gif':
                return 'image/gif';
            case 'webp':
                return 'image/webp';
            case 'bmp':
                return 'image/bmp';
            case 'svg':
                return 'image/svg+xml';
            case 'png':
            default:
                return 'image/png';
        }
    }

    private static function cleanup_test_data_seed_bulk_upload_files(): int
    {
        $upload_dir = wp_upload_dir();
        $base_dir = isset($upload_dir['basedir']) ? (string) $upload_dir['basedir'] : '';
        if ($base_dir === '' || !is_dir($base_dir)) {
            return 0;
        }

        $deleted_count = 0;
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base_dir, $flags),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
        } catch (Throwable $exception) {
            return 0;
        }

        foreach ($iterator as $file_info) {
            if (!$file_info instanceof SplFileInfo || !$file_info->isFile()) {
                continue;
            }

            $basename = $file_info->getBasename();
            if (strpos($basename, self::TEST_DATA_SEED_BULK_UPLOAD_FILE_PREFIX) !== 0) {
                continue;
            }

            $path = $file_info->getPathname();
            if ($path !== '' && @unlink($path)) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * @return array{
     *     key:string,
     *     rich:bool,
     *     stem_blocks:string[],
     *     option_blocks:string[],
     *     explanation_blocks:string[],
     *     image_count_total:int,
     *     image_distribution:string,
     *     stem_image_count:int,
     *     option_image_count:int,
     *     stem_list:string,
     *     stem_equation:string,
     *     stem_table:bool,
     *     option_list:string,
     *     option_equation:string,
     *     option_table:bool,
     *     explanation_list:string,
     *     explanation_equation:string
     * }
     */
    private static function resolve_test_data_seed_rich_recipe(string $question_type, int $question_number): array
    {
        $question_type = sanitize_key($question_type);
        $supports_rich_options = in_array($question_type, ['multiple_choice', 'multiple_answer'], true);
        $slot = max(0, (($question_number - 1) % 10));
        $recipe = [
            'key' => 'plain',
            'rich' => false,
            'stem_blocks' => ['plain'],
            'option_blocks' => $supports_rich_options ? ['plain'] : [],
            'explanation_blocks' => ['plain'],
            'image_count_total' => 0,
            'image_distribution' => 'none',
            'stem_image_count' => 0,
            'option_image_count' => 0,
            'stem_list' => 'none',
            'stem_equation' => 'none',
            'stem_table' => false,
            'option_list' => 'none',
            'option_equation' => 'none',
            'option_table' => false,
            'explanation_list' => 'none',
            'explanation_equation' => 'none',
        ];

        switch ($slot) {
            case 1:
                $recipe = [
                    'key' => 'rich_stem_bullet_image',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'bullet', 'image'],
                    'option_blocks' => $supports_rich_options ? ['plain'] : [],
                    'explanation_blocks' => ['plain', 'bullet'],
                    'image_count_total' => 1,
                    'image_distribution' => 'stem-only',
                    'stem_image_count' => 1,
                    'option_image_count' => 0,
                    'stem_list' => 'bullet',
                    'stem_equation' => 'none',
                    'stem_table' => false,
                    'option_list' => 'none',
                    'option_equation' => 'none',
                    'option_table' => false,
                    'explanation_list' => 'numbered',
                    'explanation_equation' => 'none',
                ];
                break;

            case 2:
                $recipe = [
                    'key' => 'rich_stem_numbered_equation',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'numbered', 'equation', 'image', 'table'],
                    'option_blocks' => $supports_rich_options ? ['plain', 'equation', 'mini-table'] : [],
                    'explanation_blocks' => ['plain'],
                    'image_count_total' => 1,
                    'image_distribution' => 'stem-only',
                    'stem_image_count' => 1,
                    'option_image_count' => 0,
                    'stem_list' => 'numbered',
                    'stem_equation' => 'block',
                    'stem_table' => true,
                    'option_list' => 'none',
                    'option_equation' => 'inline',
                    'option_table' => true,
                    'explanation_list' => 'none',
                    'explanation_equation' => 'inline',
                ];
                break;

            case 3:
                $recipe = [
                    'key' => 'rich_multi_image_stem',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'image', 'bullet', 'equation', 'table'],
                    'option_blocks' => $supports_rich_options ? ['plain', 'bullet', 'equation'] : [],
                    'explanation_blocks' => ['plain', 'numbered'],
                    'image_count_total' => 2,
                    'image_distribution' => 'multi-image-stem',
                    'stem_image_count' => 2,
                    'option_image_count' => 0,
                    'stem_list' => 'bullet',
                    'stem_equation' => 'inline',
                    'stem_table' => true,
                    'option_list' => 'bullet',
                    'option_equation' => 'inline',
                    'option_table' => false,
                    'explanation_list' => 'numbered',
                    'explanation_equation' => 'none',
                ];
                break;

            case 5:
                $recipe = [
                    'key' => 'rich_option_image_focus',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'numbered'],
                    'option_blocks' => $supports_rich_options ? ['plain', 'image', 'numbered', 'equation', 'mini-table'] : [],
                    'explanation_blocks' => ['plain', 'bullet'],
                    'image_count_total' => 1,
                    'image_distribution' => 'option-only',
                    'stem_image_count' => 0,
                    'option_image_count' => $supports_rich_options ? 1 : 0,
                    'stem_list' => 'numbered',
                    'stem_equation' => 'none',
                    'stem_table' => false,
                    'option_list' => 'numbered',
                    'option_equation' => 'inline',
                    'option_table' => true,
                    'explanation_list' => 'bullet',
                    'explanation_equation' => 'none',
                ];
                break;

            case 6:
                $recipe = [
                    'key' => 'rich_stem_option_split',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'image', 'table'],
                    'option_blocks' => $supports_rich_options ? ['plain', 'image', 'bullet', 'equation'] : [],
                    'explanation_blocks' => ['plain', 'equation'],
                    'image_count_total' => 2,
                    'image_distribution' => 'stem+option',
                    'stem_image_count' => 1,
                    'option_image_count' => $supports_rich_options ? 1 : 0,
                    'stem_list' => 'none',
                    'stem_equation' => 'inline',
                    'stem_table' => true,
                    'option_list' => 'bullet',
                    'option_equation' => 'block',
                    'option_table' => false,
                    'explanation_list' => 'none',
                    'explanation_equation' => 'block',
                ];
                break;

            case 7:
                $recipe = [
                    'key' => 'rich_full_stack',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'image', 'bullet', 'equation', 'table'],
                    'option_blocks' => $supports_rich_options ? ['plain', 'numbered', 'equation', 'mini-table'] : [],
                    'explanation_blocks' => ['plain', 'numbered', 'equation'],
                    'image_count_total' => 3,
                    'image_distribution' => 'multi-image-stem',
                    'stem_image_count' => 3,
                    'option_image_count' => 0,
                    'stem_list' => 'bullet',
                    'stem_equation' => 'block',
                    'stem_table' => true,
                    'option_list' => 'numbered',
                    'option_equation' => 'inline',
                    'option_table' => true,
                    'explanation_list' => 'numbered',
                    'explanation_equation' => 'inline',
                ];
                break;

            case 9:
                $recipe = [
                    'key' => 'rich_equation_focus',
                    'rich' => true,
                    'stem_blocks' => ['plain', 'numbered', 'equation', 'table'],
                    'option_blocks' => $supports_rich_options ? ['plain', 'bullet', 'equation', 'mini-table'] : [],
                    'explanation_blocks' => ['plain', 'numbered', 'equation'],
                    'image_count_total' => 0,
                    'image_distribution' => 'none',
                    'stem_image_count' => 0,
                    'option_image_count' => 0,
                    'stem_list' => 'numbered',
                    'stem_equation' => 'block',
                    'stem_table' => true,
                    'option_list' => 'bullet',
                    'option_equation' => 'inline',
                    'option_table' => true,
                    'explanation_list' => 'numbered',
                    'explanation_equation' => 'block',
                ];
                break;
        }

        if (!$supports_rich_options) {
            if ((int) $recipe['option_image_count'] > 0) {
                $recipe['stem_image_count'] = (int) $recipe['stem_image_count'] + (int) $recipe['option_image_count'];
                $recipe['option_image_count'] = 0;
                $recipe['image_distribution'] = (int) $recipe['stem_image_count'] > 1 ? 'multi-image-stem' : 'stem-only';
            }
            $recipe['option_blocks'] = [];
            $recipe['option_list'] = 'none';
            $recipe['option_equation'] = 'none';
            $recipe['option_table'] = false;
        }

        return $recipe;
    }

    private static function build_test_data_seed_math_html(
        string $source,
        string $display_mode = 'inline',
        string $fallback = ''
    ): string {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        $display_mode = strtolower(trim($display_mode)) === 'block' ? 'block' : 'inline';
        $tag_name = $display_mode === 'block' ? 'div' : 'span';
        $class_name = $display_mode === 'block' ? 'cbt-math cbt-math-block' : 'cbt-math';
        $fallback = trim($fallback) !== '' ? trim($fallback) : $source;

        return sprintf(
            '<%1$s class="%2$s" data-cbt-math="%3$s" data-cbt-math-display="%4$s">%5$s</%1$s>',
            $tag_name,
            esc_attr($class_name),
            esc_attr($source),
            esc_attr($display_mode),
            esc_html($fallback)
        );
    }

    /**
     * @param string[] $items
     */
    private static function build_test_data_seed_rich_list_html(
        array $items,
        string $list_type = 'bullet',
        string $heading = ''
    ): string {
        $items = array_values(array_filter(array_map(static function ($item): string {
            return is_scalar($item) ? trim((string) $item) : '';
        }, $items), static function (string $item): bool {
            return $item !== '';
        }));
        if (empty($items)) {
            return '';
        }

        $tag_name = strtolower(trim($list_type)) === 'numbered' ? 'ol' : 'ul';
        $heading_markup = trim($heading) !== ''
            ? '<p><strong>' . esc_html(trim($heading)) . '</strong></p>'
            : '';
        $list_items = '';
        foreach ($items as $item) {
            $list_items .= '<li>' . $item . '</li>';
        }

        return '<div class="cbt-rich-list-wrap">'
            . $heading_markup
            . '<' . $tag_name . '>' . $list_items . '</' . $tag_name . '>'
            . '</div>';
    }

    /**
     * @param array<string,mixed> $context
     * @return string[]
     */
    private static function build_test_data_seed_rich_list_items(
        string $area,
        string $question_type,
        int $question_number,
        string $subject_name,
        array $context = [],
        string $option_key = '',
        string $option_text = ''
    ): array {
        $question_type = sanitize_key($question_type);
        $area = sanitize_key($area);
        $left = (int) ($context['left'] ?? (($question_number % 37) + 13));
        $right = (int) ($context['right'] ?? (($question_number % 10) + 3));
        $base = (int) ($context['base'] ?? (($question_number % 12) + 2));
        $claim = (int) ($context['claim'] ?? ($left + $right));
        $input_count = (int) ($context['input_count'] ?? ((($question_number - 1) % 8) + 1));
        $option_value = is_numeric($option_text) ? (int) $option_text : 0;
        $option_remainder = $base > 0 ? ($option_value % $base) : 0;

        if ($area === 'option') {
            if ($question_type === 'multiple_answer') {
                return [
                    'Nilai kandidat <strong>' . esc_html($option_text) . '</strong> untuk opsi ' . esc_html($option_key) . '.',
                    'Sisa bagi terhadap ' . esc_html((string) $base) . ' adalah <strong>' . esc_html((string) $option_remainder) . '</strong>.',
                ];
            }

            return [
                'Opsi ' . esc_html($option_key) . ' membawa nilai <strong>' . esc_html($option_text) . '</strong>.',
                'Bandingkan nilai ini dengan hasil operasi pada stem.',
            ];
        }

        if ($area === 'explanation') {
            switch ($question_type) {
                case 'multiple_answer':
                    return [
                        'Periksa semua opsi yang habis dibagi bilangan dasar.',
                        'Jangan berhenti setelah menemukan satu jawaban yang benar.',
                        'Validasi ulang dengan pola kelipatan yang konsisten.',
                    ];
                case 'true_false':
                    return [
                        'Hitung dulu nilai sebenarnya dari operasi di stem.',
                        'Bandingkan dengan klaim yang tertulis.',
                    ];
                case 'true_false_matrix':
                    return [
                        'Nilai setiap pernyataan secara mandiri.',
                        'Fokus pada operasi dasar yang disebutkan pada stem.',
                    ];
                case 'short_answer':
                    return [
                        'Isi placeholder dari kiri ke kanan tanpa menambah kalimat lain.',
                        'Pastikan jumlah isian cocok dengan jumlah placeholder.',
                    ];
                case 'essay':
                    return [
                        'Buka jawaban dengan ide utama yang jelas.',
                        'Uraikan langkah kerja secara runtut.',
                        'Tutup dengan kesimpulan yang konsisten.',
                    ];
                case 'multiple_choice':
                default:
                    return [
                        'Jumlahkan kedua bilangan sesuai urutan soal.',
                        'Bandingkan hasilnya dengan seluruh opsi yang tersedia.',
                        'Pilih satu jawaban yang paling tepat.',
                    ];
            }
        }

        switch ($question_type) {
            case 'multiple_answer':
                return [
                    'Bilangan dasar kelipatan: <strong>' . esc_html((string) $base) . '</strong>.',
                    'Pilih semua opsi yang habis dibagi bilangan dasar tersebut.',
                    'Jumlah jawaban benar dapat lebih dari satu.',
                ];
            case 'true_false':
                return [
                    'Hitung hasil operasi penjumlahan sebelum memilih jawaban.',
                    'Bandingkan nilai aktual dengan klaim pada stem.',
                    'Model klaim: ' . self::build_test_data_seed_math_html(
                        $left . ' + ' . $right . ' = ' . $claim,
                        'inline',
                        $left . ' + ' . $right . ' = ' . $claim
                    ) . '.',
                ];
            case 'true_false_matrix':
                return [
                    'Evaluasi tiap pernyataan secara terpisah.',
                    'Fokus pada bilangan dasar <strong>' . esc_html((string) $base) . '</strong> dan turunannya.',
                    'Gunakan pola operasi sederhana untuk menentukan benar atau salah.',
                ];
            case 'short_answer':
                return [
                    'Mapel konteks: <strong>' . esc_html($subject_name) . '</strong>.',
                    'Jumlah placeholder aktif: <strong>' . esc_html((string) $input_count) . '</strong>.',
                    'Isi jawaban singkat tanpa menambahkan unit atau kalimat lain.',
                ];
            case 'essay':
                return [
                    'Gunakan konsep inti dari topik <strong>' . esc_html($subject_name) . '</strong>.',
                    'Jelaskan langkah penyelesaian secara runtut.',
                    'Pastikan kesimpulan selaras dengan uraian sebelumnya.',
                ];
            case 'multiple_choice':
            default:
                return [
                    'Bilangan pertama: <strong>' . esc_html((string) $left) . '</strong>.',
                    'Bilangan kedua: <strong>' . esc_html((string) $right) . '</strong>.',
                    'Operasi yang dipakai adalah penjumlahan.',
                ];
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function build_test_data_seed_rich_equation_html(
        string $area,
        string $question_type,
        int $question_number,
        array $context = [],
        string $display_mode = 'inline',
        string $option_key = '',
        string $option_text = ''
    ): string {
        $question_type = sanitize_key($question_type);
        $area = sanitize_key($area);
        $left = (int) ($context['left'] ?? (($question_number % 37) + 13));
        $right = (int) ($context['right'] ?? (($question_number % 10) + 3));
        $base = (int) ($context['base'] ?? (($question_number % 12) + 2));
        $claim = (int) ($context['claim'] ?? ($left + $right));
        $correct = (int) ($context['correct'] ?? ($left + $right));
        $input_count = (int) ($context['input_count'] ?? ((($question_number - 1) % 8) + 1));
        $option_value = is_numeric($option_text) ? (int) $option_text : 0;
        $option_remainder = $base > 0 ? ($option_value % $base) : 0;
        $source = '';
        $fallback = '';

        if ($area === 'option') {
            if ($question_type === 'multiple_answer') {
                $source = $option_value . ' \\bmod ' . $base . ' = ' . $option_remainder;
                $fallback = $option_value . ' mod ' . $base . ' = ' . $option_remainder;
            } else {
                $source = '\\text{opsi } ' . strtoupper($option_key) . ' = ' . $option_value;
                $fallback = 'Opsi ' . strtoupper($option_key) . ' = ' . $option_value;
            }

            return self::build_test_data_seed_math_html($source, $display_mode, $fallback);
        }

        if ($area === 'explanation') {
            switch ($question_type) {
                case 'multiple_answer':
                    $source = '\\text{kelipatan dari } ' . $base;
                    $fallback = 'Kelipatan dari ' . $base;
                    break;
                case 'true_false':
                    $source = $left . ' + ' . $right . ' = ' . ($left + $right);
                    $fallback = $left . ' + ' . $right . ' = ' . ($left + $right);
                    break;
                case 'true_false_matrix':
                    $source = $base . ' + 1 \\neq ' . $base;
                    $fallback = $base . ' + 1 != ' . $base;
                    break;
                case 'short_answer':
                    $source = '\\text{isi}_1 \\rightarrow \\text{isi}_{' . max(1, $input_count) . '}';
                    $fallback = 'Isi 1 -> Isi ' . max(1, $input_count);
                    break;
                case 'essay':
                    $source = '\\text{konsep} + \\text{langkah} + \\text{kesimpulan}';
                    $fallback = 'konsep + langkah + kesimpulan';
                    break;
                case 'multiple_choice':
                default:
                    $source = $left . ' + ' . $right . ' = ' . $correct;
                    $fallback = $left . ' + ' . $right . ' = ' . $correct;
                    break;
            }

            return self::build_test_data_seed_math_html($source, $display_mode, $fallback);
        }

        switch ($question_type) {
            case 'multiple_answer':
                $source = 'n \\bmod ' . $base . ' = 0';
                $fallback = 'n mod ' . $base . ' = 0';
                break;
            case 'true_false':
                $source = $left . ' + ' . $right . ' = ' . $claim;
                $fallback = $left . ' + ' . $right . ' = ' . $claim;
                break;
            case 'true_false_matrix':
                $source = $base . ' \\times 2 = ' . ($base * 2);
                $fallback = $base . ' x 2 = ' . ($base * 2);
                break;
            case 'short_answer':
                if ($input_count <= 1) {
                    $source = '\\text{INPUT}_1 = ' . $left . ' \\times ' . $right;
                    $fallback = 'INPUT_1 = ' . $left . ' x ' . $right;
                } else {
                    $source = '\\text{INPUT}_1 \\rightarrow \\text{INPUT}_{' . max(1, $input_count) . '}';
                    $fallback = 'INPUT_1 -> INPUT_' . max(1, $input_count);
                }
                break;
            case 'essay':
                $source = '\\text{data} \\rightarrow \\text{analisis} \\rightarrow \\text{kesimpulan}';
                $fallback = 'data -> analisis -> kesimpulan';
                break;
            case 'multiple_choice':
            default:
                $source = $left . ' + ' . $right;
                $fallback = $left . ' + ' . $right;
                break;
        }

        return self::build_test_data_seed_math_html($source, $display_mode, $fallback);
    }

    private static function build_test_data_seed_stem_paragraph_html(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $escaped = esc_html($text);
        if (function_exists('wpautop')) {
            return trim((string) wpautop($escaped));
        }

        return '<p>' . $escaped . '</p>';
    }

    private static function build_test_data_seed_rich_image_html(string $src, string $alt, string $caption = ''): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }

        $caption_markup = $caption !== ''
            ? '<figcaption class="cbt-rich-image-caption">' . esc_html($caption) . '</figcaption>'
            : '';

        return '<figure class="cbt-rich-image-figure">'
            . '<img class="cbt-rich-image" src="' . esc_attr($src) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async" />'
            . $caption_markup
            . '</figure>';
    }

    /**
     * @param array<string,string> $source_map
     */
    private static function build_test_data_seed_rich_images_html(
        string $bucket,
        int $question_number,
        int $image_count,
        string $subject_name,
        string $area,
        array &$source_map
    ): string {
        $image_count = max(0, $image_count);
        if ($image_count <= 0) {
            return '';
        }

        $parts = [];
        $base_offset = $area === 'option' ? 200 : 0;
        for ($idx = 0; $idx < $image_count; $idx++) {
            $image_src = self::resolve_test_data_seed_sample_image_src(
                $bucket,
                $question_number + $base_offset + $idx,
                $source_map
            );
            if ($image_src === '') {
                continue;
            }

            $parts[] = self::build_test_data_seed_rich_image_html(
                $image_src,
                sprintf(
                    'Ilustrasi %s %s %03d',
                    $area === 'option' ? 'opsi' : $subject_name,
                    $area === 'option' ? $subject_name : 'soal',
                    $question_number
                ),
                $area === 'option'
                    ? 'Referensi visual opsi'
                    : 'Ilustrasi ' . self::format_test_data_seed_image_bucket_label($bucket)
            );
        }

        if (empty($parts)) {
            return '';
        }

        return '<div class="cbt-rich-image-stack">' . implode('', $parts) . '</div>';
    }

    private static function wrap_test_data_seed_rich_table(string $table_html): string
    {
        $table_html = trim($table_html);
        if ($table_html === '') {
            return '';
        }

        return '<div class="cbt-rich-table-wrap">' . $table_html . '</div>';
    }

    /**
     * @param string[] $headers
     * @param array<int,array<int|string>> $rows
     */
    private static function build_test_data_seed_html_table(array $headers, array $rows, string $caption = ''): string
    {
        if (empty($headers) || empty($rows)) {
            return '';
        }

        $head_cells = '';
        foreach ($headers as $header) {
            $head_cells .= '<th scope="col">' . esc_html((string) $header) . '</th>';
        }

        $body_rows = '';
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }

            $body_cells = '';
            foreach ($row as $cell) {
                $body_cells .= '<td>' . esc_html((string) $cell) . '</td>';
            }

            if ($body_cells !== '') {
                $body_rows .= '<tr>' . $body_cells . '</tr>';
            }
        }

        if ($body_rows === '') {
            return '';
        }

        $caption_markup = $caption !== ''
            ? '<caption>' . esc_html($caption) . '</caption>'
            : '';

        return '<table class="cbt-rich-content-table">'
            . $caption_markup
            . '<thead><tr>' . $head_cells . '</tr></thead>'
            . '<tbody>' . $body_rows . '</tbody>'
            . '</table>';
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function build_test_data_seed_rich_table_html(
        string $question_type,
        int $question_number,
        string $subject_name,
        array $context = []
    ): string {
        $headers = ['Keterangan', 'Nilai'];
        $rows = [];
        $caption = 'Data pendukung soal uji.';

        switch (sanitize_key($question_type)) {
            case 'multiple_choice':
                $rows = [
                    ['Bilangan pertama', (string) ($context['left'] ?? (($question_number % 37) + 13))],
                    ['Bilangan kedua', (string) ($context['right'] ?? (($question_number % 10) + 3))],
                    ['Operasi', 'Penjumlahan'],
                ];
                $caption = 'Perhatikan data penjumlahan berikut.';
                break;

            case 'multiple_answer':
                $rows = [
                    ['Bilangan dasar', (string) ($context['base'] ?? (($question_number % 12) + 2))],
                    ['Instruksi', 'Pilih semua kelipatan yang tepat'],
                    ['Jumlah opsi', (string) ($context['option_count'] ?? 4)],
                ];
                $caption = 'Gunakan data dasar berikut untuk menyeleksi kelipatan.';
                break;

            case 'true_false':
                $rows = [
                    ['Angka pertama', (string) ($context['left'] ?? (($question_number % 21) + 9))],
                    ['Angka kedua', (string) ($context['right'] ?? (($question_number % 8) + 3))],
                    ['Nilai klaim', (string) ($context['claim'] ?? (($question_number % 21) + 12))],
                ];
                $caption = 'Bandingkan data berikut dengan pernyataan pada soal.';
                break;

            case 'true_false_matrix':
                $rows = [
                    ['Bilangan dasar', (string) ($context['base'] ?? (($question_number % 17) + 6))],
                    ['Fokus evaluasi', 'Positif, penjumlahan, dan perkalian'],
                    ['Jenis jawaban', 'Benar atau Salah'],
                ];
                $caption = 'Gunakan konteks berikut untuk menilai setiap pernyataan.';
                break;

            case 'short_answer':
                $rows = [
                    ['Mapel', $subject_name],
                    ['Jumlah isian', (string) ($context['input_count'] ?? ((($question_number - 1) % 8) + 1))],
                    ['Format jawaban', 'Isian singkat berurutan'],
                ];
                $caption = 'Isi semua kotak sesuai urutan data pendukung berikut.';
                break;

            case 'essay':
                $rows = [
                    ['Topik', $subject_name],
                    ['Fokus', 'Konsep utama dan langkah kerja'],
                    ['Output', 'Penjelasan runtut dan kesimpulan'],
                ];
                $caption = 'Gunakan poin konteks berikut untuk menyusun jawaban essay.';
                break;
        }

        return self::wrap_test_data_seed_rich_table(
            self::build_test_data_seed_html_table($headers, $rows, $caption)
        );
    }

    private static function build_test_data_seed_rich_option_table_html(
        string $question_type,
        string $option_key,
        string $option_text,
        int $question_number
    ): string {
        $rows = [];
        if (sanitize_key($question_type) === 'multiple_answer') {
            $rows = [
                ['Key', $option_key],
                ['Angka', $option_text],
                ['Cek', 'Kelipatan kandidat'],
            ];
        } else {
            $rows = [
                ['Key', $option_key],
                ['Nilai', $option_text],
                ['Soal', 'No. ' . $question_number],
            ];
        }

        return '<div class="cbt-rich-table-wrap cbt-rich-table-wrap--compact">'
            . self::build_test_data_seed_html_table(
                ['Field', 'Isi'],
                $rows,
                ''
            )
            . '</div>';
    }

    private static function build_test_data_seed_rich_option_html(
        string $option_text,
        string $src,
        string $headline,
        string $caption = '',
        string $detail_html = ''
    ): string {
        $media_markup = $src !== ''
            ? '<span class="cbt-rich-option-media"><img src="' . esc_attr($src) . '" alt="' . esc_attr($headline) . '" loading="lazy" decoding="async" /></span>'
            : '';
        $caption_markup = $caption !== ''
            ? '<span class="cbt-rich-option-caption">' . esc_html($caption) . '</span>'
            : '';
        $detail_markup = trim($detail_html) !== ''
            ? $detail_html
            : '';

        return '<div class="cbt-rich-option-card">'
            . $media_markup
            . '<span class="cbt-rich-option-copy">'
            . '<span class="cbt-rich-option-title">' . esc_html($headline) . '</span>'
            . '<span class="cbt-rich-option-text">' . esc_html($option_text) . '</span>'
            . $detail_markup
            . $caption_markup
            . '</span>'
            . '</div>';
    }

    /**
     * @param string[] $plain_options
     * @param string[] $correct_keys
     * @param array{
     *     option_image_count:int,
     *     option_list:string,
     *     option_equation:string,
     *     option_table:bool
     * } $rich_recipe
     * @param array<string,mixed> $context
     * @param array<string,string> $source_map
     */
    private static function build_test_data_seed_rich_option_payload(
        array $plain_options,
        array $correct_keys,
        string $question_type,
        string $image_bucket,
        int $question_number,
        array $rich_recipe,
        array $context,
        array &$source_map
    ): string {
        if (empty($plain_options)) {
            return '';
        }

        $entries = [];
        foreach (array_values($plain_options) as $idx => $plain_option) {
            $option_key = chr(65 + $idx);
            $src = $idx < max(0, (int) ($rich_recipe['option_image_count'] ?? 0))
                ? self::resolve_test_data_seed_sample_image_src($image_bucket, $question_number + $idx + 1, $source_map)
                : '';
            $detail_parts = [];
            if (!empty($rich_recipe['option_table'])) {
                $detail_parts[] = self::build_test_data_seed_rich_option_table_html(
                    $question_type,
                    $option_key,
                    (string) $plain_option,
                    $question_number
                );
            }
            if ((string) ($rich_recipe['option_list'] ?? 'none') !== 'none') {
                $detail_parts[] = self::build_test_data_seed_rich_list_html(
                    self::build_test_data_seed_rich_list_items(
                        'option',
                        $question_type,
                        $question_number,
                        '',
                        $context,
                        $option_key,
                        (string) $plain_option
                    ),
                    (string) $rich_recipe['option_list'],
                    'Catatan opsi'
                );
            }
            if ((string) ($rich_recipe['option_equation'] ?? 'none') !== 'none') {
                $detail_parts[] = self::build_test_data_seed_rich_equation_html(
                    'option',
                    $question_type,
                    $question_number,
                    $context,
                    (string) $rich_recipe['option_equation'],
                    $option_key,
                    (string) $plain_option
                );
            }
            $entries[] = [
                'option_text' => self::build_test_data_seed_rich_option_html(
                    (string) $plain_option,
                    $src,
                    sprintf('Pilihan %s', $option_key),
                    $question_type === 'multiple_answer'
                        ? 'Periksa apakah opsi ini memenuhi semua syarat soal.'
                        : 'Gunakan data mini berikut untuk membaca pilihan jawaban.',
                    implode('', array_values(array_filter($detail_parts, static function ($part): bool {
                        return is_string($part) && trim($part) !== '';
                    })))
                ),
                'is_correct' => in_array($option_key, $correct_keys, true) ? 1 : 0,
            ];
        }

        $encoded = wp_json_encode($entries);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * @param array{
     *     explanation_list:string,
     *     explanation_equation:string,
     *     rich:bool
     * } $rich_recipe
     * @param array<string,mixed> $context
     */
    private static function decorate_test_data_seed_explanation_html(
        string $base_text,
        string $question_type,
        int $question_number,
        string $subject_name,
        array $rich_recipe,
        array $context = []
    ): string {
        if (empty($rich_recipe['rich'])) {
            return $base_text;
        }

        $parts = [];
        $base_markup = self::build_test_data_seed_stem_paragraph_html($base_text);
        if ($base_markup !== '') {
            $parts[] = $base_markup;
        }

        if ((string) ($rich_recipe['explanation_list'] ?? 'none') !== 'none') {
            $parts[] = self::build_test_data_seed_rich_list_html(
                self::build_test_data_seed_rich_list_items(
                    'explanation',
                    $question_type,
                    $question_number,
                    $subject_name,
                    $context
                ),
                (string) $rich_recipe['explanation_list'],
                'Panduan baca'
            );
        }

        if ((string) ($rich_recipe['explanation_equation'] ?? 'none') !== 'none') {
            $parts[] = self::build_test_data_seed_rich_equation_html(
                'explanation',
                $question_type,
                $question_number,
                $context,
                (string) $rich_recipe['explanation_equation']
            );
        }

        $parts = array_values(array_filter($parts, static function ($part): bool {
            return is_string($part) && trim($part) !== '';
        }));

        return !empty($parts) ? implode('', $parts) : $base_text;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function decorate_test_data_seed_true_false_matrix_correct_text(
        string $correct_text,
        int $question_number,
        array $context = []
    ): string {
        $items = self::normalize_true_false_matrix_config($correct_text);
        if (empty($items)) {
            return $correct_text;
        }

        $base = (int) ($context['base'] ?? (($question_number % 17) + 6));
        $sources = [
            [$base . ' > 0', $base . ' > 0'],
            [$base . ' + 1 \\neq ' . $base, $base . ' + 1 != ' . $base],
            [$base . ' \\times 2 = ' . ($base * 2), $base . ' x 2 = ' . ($base * 2)],
        ];
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $statement = trim((string) ($item['text'] ?? ''));
            $answer = ((string) ($item['answer'] ?? 'false') === 'true') ? 'true' : 'false';
            if ($statement === '') {
                continue;
            }

            $math_pair = $sources[$index % count($sources)];
            $statement_html = '<strong>Pernyataan ' . esc_html((string) ($index + 1)) . '.</strong> '
                . esc_html($statement)
                . ' '
                . self::build_test_data_seed_math_html(
                    (string) $math_pair[0],
                    'inline',
                    (string) $math_pair[1]
                );

            $lines[] = $statement_html . '|' . $answer;
        }

        return !empty($lines) ? implode("\n", $lines) : $correct_text;
    }

    /**
     * @param array{
     *     explanation_list:string,
     *     explanation_equation:string,
     *     rich:bool
     * } $rich_recipe
     * @param array<string,mixed> $context
     */
    private static function decorate_test_data_seed_essay_rubric_html(
        string $correct_text,
        int $question_number,
        string $subject_name,
        array $rich_recipe,
        array $context = []
    ): string {
        if (empty($rich_recipe['rich'])) {
            return $correct_text;
        }

        $parts = [];
        $base_markup = self::build_test_data_seed_stem_paragraph_html($correct_text);
        if ($base_markup !== '') {
            $parts[] = $base_markup;
        }

        if ((string) ($rich_recipe['explanation_list'] ?? 'none') !== 'none') {
            $parts[] = self::build_test_data_seed_rich_list_html(
                self::build_test_data_seed_rich_list_items(
                    'explanation',
                    'essay',
                    $question_number,
                    $subject_name,
                    $context
                ),
                (string) $rich_recipe['explanation_list'],
                'Rubrik ringkas'
            );
        }

        if ((string) ($rich_recipe['explanation_equation'] ?? 'none') !== 'none') {
            $parts[] = self::build_test_data_seed_rich_equation_html(
                'explanation',
                'essay',
                $question_number,
                $context,
                (string) $rich_recipe['explanation_equation']
            );
        }

        $parts = array_values(array_filter($parts, static function ($part): bool {
            return is_string($part) && trim($part) !== '';
        }));

        return !empty($parts) ? implode('', $parts) : $correct_text;
    }

    /**
     * @param array<string,mixed> $row
     * @param array{
     *     rich:bool,
     *     stem_image_count:int,
     *     stem_list:string,
     *     stem_equation:string,
     *     stem_table:bool,
     *     option_image_count:int,
     *     option_list:string,
     *     option_equation:string,
     *     option_table:bool,
     *     explanation_list:string,
     *     explanation_equation:string
     * } $rich_recipe
     * @param array<string,mixed> $context
     * @param array<string,string> $source_map
     * @return array<string,mixed>
     */
    private static function decorate_test_data_seed_question_row_with_rich_content(
        array $row,
        int $question_number,
        string $subject_name,
        string $image_bucket,
        array $rich_recipe,
        array $context,
        array &$source_map
    ): array {
        $question_type = sanitize_key((string) ($row['question_type'] ?? ''));
        $supports_rich_options = in_array($question_type, ['multiple_choice', 'multiple_answer'], true);
        if (empty($rich_recipe['rich'])) {
            return $row;
        }

        $bucket = sanitize_key($image_bucket);
        if ($bucket === '') {
            $bucket = 'mathematics';
        }

        $stem_parts = [];
        if ((int) ($rich_recipe['stem_image_count'] ?? 0) > 0) {
            $stem_parts[] = self::build_test_data_seed_rich_images_html(
                $bucket,
                $question_number,
                (int) $rich_recipe['stem_image_count'],
                $subject_name,
                'stem',
                $source_map
            );
        }

        $stem_parts[] = self::build_test_data_seed_stem_paragraph_html((string) ($row['question_text'] ?? ''));

        if ((string) ($rich_recipe['stem_list'] ?? 'none') !== 'none') {
            $stem_parts[] = self::build_test_data_seed_rich_list_html(
                self::build_test_data_seed_rich_list_items(
                    'stem',
                    $question_type,
                    $question_number,
                    $subject_name,
                    $context
                ),
                (string) $rich_recipe['stem_list'],
                'Petunjuk baca'
            );
        }

        if ((string) ($rich_recipe['stem_equation'] ?? 'none') !== 'none') {
            $stem_parts[] = self::build_test_data_seed_rich_equation_html(
                'stem',
                $question_type,
                $question_number,
                $context,
                (string) $rich_recipe['stem_equation']
            );
        }

        if (!empty($rich_recipe['stem_table'])) {
            $table_markup = self::build_test_data_seed_rich_table_html(
                $question_type,
                $question_number,
                $subject_name,
                $context
            );
            if ($table_markup !== '') {
                $stem_parts[] = $table_markup;
            }
        }

        $stem_parts = array_values(array_filter($stem_parts, static function ($part): bool {
            return is_string($part) && trim($part) !== '';
        }));
        if (!empty($stem_parts)) {
            $row['question_text'] = implode('', $stem_parts);
        }

        $row['explanation'] = self::decorate_test_data_seed_explanation_html(
            (string) ($row['explanation'] ?? ''),
            $question_type,
            $question_number,
            $subject_name,
            $rich_recipe,
            $context
        );

        if ($question_type === 'true_false_matrix') {
            $row['correct_text'] = self::decorate_test_data_seed_true_false_matrix_correct_text(
                (string) ($row['correct_text'] ?? ''),
                $question_number,
                $context
            );
        } elseif ($question_type === 'essay') {
            $row['correct_text'] = self::decorate_test_data_seed_essay_rubric_html(
                (string) ($row['correct_text'] ?? ''),
                $question_number,
                $subject_name,
                $rich_recipe,
                $context
            );
        }

        if (
            $supports_rich_options
            && (
                (int) ($rich_recipe['option_image_count'] ?? 0) > 0
                || (string) ($rich_recipe['option_list'] ?? 'none') !== 'none'
                || (string) ($rich_recipe['option_equation'] ?? 'none') !== 'none'
                || !empty($rich_recipe['option_table'])
            )
        ) {
            $plain_options = isset($context['plain_options']) && is_array($context['plain_options'])
                ? array_values(array_map('strval', (array) $context['plain_options']))
                : [];
            $correct_keys = isset($context['correct_keys']) && is_array($context['correct_keys'])
                ? array_values(array_map('strval', (array) $context['correct_keys']))
                : [];

            $options_payload = self::build_test_data_seed_rich_option_payload(
                $plain_options,
                $correct_keys,
                $question_type,
                $bucket,
                $question_number,
                $rich_recipe,
                $context,
                $source_map
            );

            if ($options_payload !== '') {
                $row['options_payload'] = $options_payload;
            }
        }

        return $row;
    }

    /**
     * @return array{profile:string,label:string,suffix:string,question_type:string}
     */
    private static function build_test_data_seed_exam_profile_definition(string $definition_key): array
    {
        $definitions = self::get_test_data_seed_exam_profile_definitions();
        if (isset($definitions[$definition_key]) && is_array($definitions[$definition_key])) {
            return $definitions[$definition_key];
        }

        return $definitions['mixed'];
    }

    /**
     * @return string[]
     */
    private static function get_test_data_seed_active_full_question_types(string $preset_key): array
    {
        return self::get_test_data_seed_all_question_types();
    }

    private static function preset_supports_test_data_seed_true_false_matrix(string $preset_key): bool
    {
        $presets = self::test_data_seed_presets();
        if (!isset($presets[$preset_key]) || !is_array($presets[$preset_key])) {
            return false;
        }

        return !empty($presets[$preset_key]['include_true_false_matrix']);
    }

    private static function get_test_data_seed_full_exam_repeat_count(string $preset_key): int
    {
        return 1;
    }

    /**
     * @return array<int,array{profile:string,label:string,suffix:string,question_type:string}>
     */
    private static function build_test_data_seed_fixed_exam_profiles(): array
    {
        $profiles = [];
        foreach (self::get_test_data_seed_fixed_exam_fixture_definitions('') as $fixture_definition) {
            if (!is_array($fixture_definition)) {
                continue;
            }

            $profile = sanitize_key((string) ($fixture_definition['seed_profile'] ?? ''));
            if ($profile === '') {
                $profile = 'fixed_fixture';
            }

            $label = sanitize_text_field((string) ($fixture_definition['seed_profile_label'] ?? strtoupper(str_replace('_', ' ', $profile))));
            $suffix = '[' . $label . ']';
            $profiles[] = [
                'profile' => $profile,
                'label' => $label,
                'suffix' => $suffix,
                'question_type' => sanitize_key((string) ($fixture_definition['seed_profile_question_type'] ?? '')),
            ];
        }

        return $profiles;
    }

    /**
     * @return array<int,array{profile:string,label:string,suffix:string,question_type:string}>
     */
    private static function build_test_data_seed_exam_profiles(string $preset_key, int $total_exams): array
    {
        $profiles = [];
        $total_exams = max(0, $total_exams);
        if ($total_exams <= 0) {
            return $profiles;
        }

        $fixed_profiles = self::build_test_data_seed_fixed_exam_profiles();
        $fixed_total = min($total_exams, count($fixed_profiles));
        for ($index = 0; $index < $fixed_total; $index++) {
            $profiles[] = $fixed_profiles[$index];
        }

        foreach (self::get_test_data_seed_all_question_types() as $question_type) {
            if (count($profiles) >= $total_exams) {
                return $profiles;
            }
            $profiles[] = self::build_test_data_seed_exam_profile_definition($question_type);
        }

        if (count($profiles) < $total_exams) {
            $profiles[] = self::build_test_data_seed_exam_profile_definition('mixed_50');
        }

        while (count($profiles) < $total_exams) {
            $profiles[] = self::build_test_data_seed_exam_profile_definition('mixed_50');
        }

        return array_slice($profiles, 0, $total_exams);
    }

    /**
     * @return array<string,int>
     */
    private static function get_test_data_seed_exam_profile_counts(string $preset_key, int $total_exams): array
    {
        $counts = [];
        foreach (self::build_test_data_seed_exam_profiles($preset_key, $total_exams) as $profile) {
            $profile_key = sanitize_key((string) ($profile['profile'] ?? 'mixed'));
            if ($profile_key === '') {
                continue;
            }
            if (!isset($counts[$profile_key])) {
                $counts[$profile_key] = 0;
            }
            $counts[$profile_key] += 1;
        }

        return $counts;
    }

    /**
     * @param array<string,int> $exam_profile_counts
     */
    private static function format_test_data_seed_exam_profile_summary(
        array $exam_profile_counts,
        string $prefix = 'Komposisi profil exam: ',
        int $max_items = 0
    ): string {
        $labels = self::get_test_data_seed_exam_profile_labels();
        $parts = [];
        foreach ($exam_profile_counts as $profile_key => $profile_count) {
            $count = (int) $profile_count;
            if ($count <= 0) {
                continue;
            }

            $label = isset($labels[$profile_key])
                ? (string) $labels[$profile_key]
                : strtoupper(str_replace('_', ' ', (string) $profile_key));
            $parts[] = $label . ' ' . number_format_i18n($count);
            if ($max_items > 0 && count($parts) >= $max_items) {
                break;
            }
        }

        if (empty($parts)) {
            return 'Belum ada exam yang akan dibuat dari preset ini.';
        }

        return $prefix . implode(', ', $parts) . '.';
    }

    /**
     * @return array<string,int>
     */
    private static function get_test_data_seed_question_type_counts(string $preset_key, int $total_questions, int $exam_total = 0): array
    {
        $counts = [];
        $total_questions = max(0, $total_questions);
        $question_types = self::get_test_data_seed_all_question_types();
        if ($total_questions <= 0 || empty($question_types)) {
            return $counts;
        }

        $question_type_total = count($question_types);
        $questions_per_type = self::get_test_data_seed_questions_per_type($preset_key, $total_questions);
        for ($index = 0; $index < $total_questions; $index++) {
            $type_index = $questions_per_type > 0 ? intdiv($index, $questions_per_type) : ($index % $question_type_total);
            if ($type_index >= $question_type_total) {
                $type_index = $index % $question_type_total;
            }
            $question_type = (string) ($question_types[$type_index] ?? '');
            if ($question_type === '') {
                continue;
            }
            if (!isset($counts[$question_type])) {
                $counts[$question_type] = 0;
            }
            $counts[$question_type] += 1;
        }

        return $counts;
    }

    private static function get_test_data_seed_questions_per_type(string $preset_key, int $total_questions = 0): int
    {
        $presets = self::test_data_seed_presets();
        if (isset($presets[$preset_key]['questions_per_type'])) {
            return max(0, (int) $presets[$preset_key]['questions_per_type']);
        }

        $question_type_total = max(1, count(self::get_test_data_seed_all_question_types()));
        return max(0, (int) floor(max(0, $total_questions) / $question_type_total));
    }

    private static function resolve_test_data_seed_question_type_for_exam_entry(
        array $exam_entry,
        int $question_offset,
        int $exam_count,
        string $preset_key
    ): string {
        $profile_question_type = sanitize_key((string) ($exam_entry['seed_profile_question_type'] ?? ($exam_entry['question_type'] ?? '')));
        if ($profile_question_type !== '') {
            return $profile_question_type;
        }

        $cycle = self::test_data_seed_question_type_cycle($preset_key);
        $cycle_size = count($cycle);
        if ($cycle_size <= 0) {
            return '';
        }

        $exam_question_index = $exam_count > 0 ? intdiv($question_offset, $exam_count) : $question_offset;

        return (string) $cycle[$exam_question_index % $cycle_size];
    }

    /**
     * @param array<string,int> $question_type_counts
     */
    private static function format_test_data_seed_question_type_summary(
        array $question_type_counts,
        string $prefix = 'Tipe soal yang dibuat: ',
        int $max_items = 0
    ): string
    {
        $labels = self::get_test_data_seed_question_type_labels();
        $parts = [];
        foreach ($question_type_counts as $question_type => $question_count) {
            $count = (int) $question_count;
            if ($count <= 0) {
                continue;
            }

            $label = isset($labels[$question_type])
                ? (string) $labels[$question_type]
                : ucwords(str_replace('_', ' ', (string) $question_type));
            $parts[] = $label . ' ' . number_format_i18n($count);
            if ($max_items > 0 && count($parts) >= $max_items) {
                break;
            }
        }

        if (empty($parts)) {
            return 'Belum ada soal yang akan dibuat dari preset ini.';
        }

        return $prefix . implode(', ', $parts) . '.';
    }

    private static function describe_test_data_seed_phase_progress(array $state): string
    {
        $phase = sanitize_key((string) ($state['phase'] ?? 'reset_tables'));
        $preset = self::normalize_test_data_seed_preset((array) ($state['preset'] ?? []));

        switch ($phase) {
            case 'reset_tables':
                $tables = isset($state['tables']) && is_array($state['tables']) ? array_values((array) $state['tables']) : [];
                $table_total = count($tables);
                $table_index = max(0, min($table_total, (int) ($state['table_index'] ?? 0)));
                if ($table_total <= 0) {
                    return 'Menyiapkan reset tabel CBT.';
                }
                if ($table_index >= $table_total) {
                    return 'Semua tabel CBT sudah selesai dikosongkan.';
                }

                return sprintf(
                    'Reset tabel %s dari %s. Berikutnya: %s.',
                    number_format_i18n($table_index + 1),
                    number_format_i18n($table_total),
                    self::format_test_data_seed_table_label((string) $tables[$table_index])
                );

            case 'reset_users':
                $user_total = max(0, (int) ($state['reset_user_total'] ?? 0));
                $user_offset = max(0, min($user_total, (int) ($state['reset_user_offset'] ?? 0)));
                if ($user_total <= 0) {
                    return 'Tidak ada user lama yang perlu dihapus.';
                }

                return sprintf(
                    'User lama terhapus %s dari %s.',
                    number_format_i18n($user_offset),
                    number_format_i18n($user_total)
                );

            case 'seed_subjects':
                $subject_total = max(0, (int) ($preset['subjects'] ?? 0));
                $subject_offset = max(0, min($subject_total, (int) ($state['seed_subject_offset'] ?? 0)));
                $subject_created_count = max(0, (int) ($state['seed_subject_created_count'] ?? 0));
                $subject_updated_count = max(0, (int) ($state['seed_subject_updated_count'] ?? 0));

                return sprintf(
                    'Subject tersinkron %s dari %s. Baru %s, update %s.',
                    number_format_i18n($subject_offset),
                    number_format_i18n($subject_total),
                    number_format_i18n($subject_created_count),
                    number_format_i18n($subject_updated_count)
                );

            case 'seed_users':
                $teacher_total = max(0, (int) ($preset['teachers'] ?? 0));
                $student_total = max(0, (int) ($preset['students'] ?? 0));
                $user_total = $teacher_total + $student_total;
                $user_offset = max(0, min($user_total, (int) ($state['seed_user_offset'] ?? 0)));
                $teacher_processed = max(0, min($teacher_total, (int) ($state['seed_teacher_processed'] ?? 0)));
                $student_processed = max(0, min($student_total, (int) ($state['seed_student_processed'] ?? 0)));

                return sprintf(
                    'User tersinkron %s dari %s. Guru %s/%s, siswa %s/%s.',
                    number_format_i18n($user_offset),
                    number_format_i18n($user_total),
                    number_format_i18n($teacher_processed),
                    number_format_i18n($teacher_total),
                    number_format_i18n($student_processed),
                    number_format_i18n($student_total)
                );

            case 'seed_bank_exams':
                $bank_exam_total = max(0, (int) ($preset['subjects'] ?? 0));
                $bank_exam_offset = max(0, min($bank_exam_total, (int) ($state['seed_bank_exam_offset'] ?? 0)));

                return sprintf(
                    'Bank soal per mapel siap %s dari %s.',
                    number_format_i18n($bank_exam_offset),
                    number_format_i18n($bank_exam_total)
                );

            case 'seed_exams':
                $exam_total = max(0, (int) ($preset['exams'] ?? 0));
                $exam_offset = max(0, min($exam_total, (int) ($state['seed_exam_offset'] ?? 0)));
                $exam_created_count = max(0, (int) ($state['seed_exam_created_count'] ?? 0));
                $exam_updated_count = max(0, (int) ($state['seed_exam_updated_count'] ?? 0));

                return sprintf(
                    'Exam tersinkron %s dari %s. Baru %s, update %s.',
                    number_format_i18n($exam_offset),
                    number_format_i18n($exam_total),
                    number_format_i18n($exam_created_count),
                    number_format_i18n($exam_updated_count)
                );

            case 'seed_bank_questions':
            case 'seed_questions':
                $question_total = max(0, (int) ($preset['questions'] ?? 0));
                $question_offset = max(0, min(
                    $question_total,
                    (int) ($state['seed_bank_question_offset'] ?? ($state['seed_question_offset'] ?? 0))
                ));
                $question_type_counts = isset($state['question_type_counts']) && is_array($state['question_type_counts'])
                    ? (array) $state['question_type_counts']
                    : [];
                $detail = sprintf(
                    'Bank question dibuat %s dari %s.',
                    number_format_i18n($question_offset),
                    number_format_i18n($question_total)
                );
                if (!empty($question_type_counts)) {
                    $detail .= ' ' . self::format_test_data_seed_question_type_summary($question_type_counts, 'Distribusi sementara: ', 3);
                }

                return $detail;

            case 'sync_exam_questions':
                $exam_total = max(0, (int) ($preset['exams'] ?? 0));
                $sync_exam_offset = max(0, min($exam_total, (int) ($state['sync_exam_offset'] ?? 0)));
                $synced_exam_question_counts = isset($state['synced_exam_question_counts']) && is_array($state['synced_exam_question_counts'])
                    ? (array) $state['synced_exam_question_counts']
                    : [];

                return sprintf(
                    'Sinkronisasi exam uji %s dari %s. Soal ujian aktif %s.',
                    number_format_i18n($sync_exam_offset),
                    number_format_i18n($exam_total),
                    number_format_i18n(array_sum(array_map('intval', $synced_exam_question_counts)))
                );

            case 'finalize':
                return 'Merapikan hasil akhir, reset token global, dan membersihkan cache CBT.';

            default:
                return 'Generator sedang memproses dataset uji.';
        }
    }

    private static function format_test_data_seed_table_label(string $table_name): string
    {
        global $wpdb;

        $clean = str_replace('`', '', sanitize_text_field($table_name));
        if ($clean === '') {
            return 'Tabel CBT';
        }

        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        if ($prefix !== '' && strpos($clean, $prefix) === 0) {
            $clean = substr($clean, strlen($prefix));
        }
        if (strpos($clean, 'cbt_') === 0) {
            $clean = substr($clean, 4);
        }

        $clean = trim(str_replace('_', ' ', $clean));

        return $clean !== '' ? ucwords($clean) : 'Tabel CBT';
    }

    /**
     * @return array{preset:string,subjects:int,exams:int,questions:int,bank_questions:int,synced_questions:int,teachers:int,students:int,default_password:string,special_username:string,special_password:string,extra_note:string}|null
     */
    public static function parse_test_data_seed_completion_notice(string $message): ?array
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $matched = preg_match(
            '/^Dataset uji preset\s+(.+?)\s+selesai\.\s+Subject:\s*(\d+),\s*Exam:\s*(\d+),\s*Bank Question:\s*(\d+),\s*Synced Question:\s*(\d+),\s*Guru:\s*(\d+),\s*Siswa:\s*(\d+)\.\s+Password default:\s*([^\.]+)\.\s+Akun test khusus:\s*([^\s\/]+)\s*\/\s*([^\.\s]+)\.\s*(.*)$/i',
            $message,
            $matches
        );
        if ($matched !== 1) {
            $matched = preg_match(
                '/^Dataset uji preset\s+(.+?)\s+selesai\.\s+Subject:\s*(\d+),\s*Exam:\s*(\d+),\s*Bank Question:\s*(\d+),\s*Synced Question:\s*(\d+),\s*Guru:\s*(\d+),\s*Siswa:\s*(\d+)\.\s+Password default:\s*([^\.]+)\.\s+Akun test khusus:\s*([^\s\/]+)\s*\/\s*([^\.\s]+)\.$/i',
                $message,
                $matches
            );
            if ($matched !== 1) {
                $matched = preg_match(
                    '/^Dataset uji preset\s+(.+?)\s+selesai\.\s+Subject:\s*(\d+),\s*Exam:\s*(\d+),\s*Question:\s*(\d+),\s*Guru:\s*(\d+),\s*Siswa:\s*(\d+)\.\s+Password default:\s*([^\.]+)\.\s+Akun test khusus:\s*([^\s\/]+)\s*\/\s*([^\.\s]+)\.\s*(.*)$/i',
                    $message,
                    $matches
                );
                if ($matched !== 1) {
                    $matched = preg_match(
                        '/^Dataset uji preset\s+(.+?)\s+selesai\.\s+Subject:\s*(\d+),\s*Exam:\s*(\d+),\s*Question:\s*(\d+),\s*Guru:\s*(\d+),\s*Siswa:\s*(\d+)\.\s+Password default:\s*([^\.]+)\.\s+Akun test khusus:\s*([^\s\/]+)\s*\/\s*([^\.\s]+)\.$/i',
                        $message,
                        $matches
                    );
                    if ($matched !== 1) {
                        return null;
                    }
                    $matches[10] = '';
                }

                $bank_questions = max(0, (int) ($matches[4] ?? 0));

                return [
                    'preset' => sanitize_text_field((string) ($matches[1] ?? '')),
                    'subjects' => max(0, (int) ($matches[2] ?? 0)),
                    'exams' => max(0, (int) ($matches[3] ?? 0)),
                    'questions' => $bank_questions,
                    'bank_questions' => $bank_questions,
                    'synced_questions' => $bank_questions,
                    'teachers' => max(0, (int) ($matches[5] ?? 0)),
                    'students' => max(0, (int) ($matches[6] ?? 0)),
                    'default_password' => sanitize_text_field((string) ($matches[7] ?? '')),
                    'special_username' => sanitize_user((string) ($matches[8] ?? ''), true),
                    'special_password' => sanitize_text_field((string) ($matches[9] ?? '')),
                    'extra_note' => isset($matches[10])
                        ? sanitize_text_field((string) $matches[10])
                        : '',
                ];
            }
            $matches[10] = '';
        }

        $bank_questions = max(0, (int) ($matches[4] ?? 0));
        $synced_questions = max(0, (int) ($matches[5] ?? 0));

        return [
            'preset' => sanitize_text_field((string) ($matches[1] ?? '')),
            'subjects' => max(0, (int) ($matches[2] ?? 0)),
            'exams' => max(0, (int) ($matches[3] ?? 0)),
            'questions' => $bank_questions,
            'bank_questions' => $bank_questions,
            'synced_questions' => $synced_questions,
            'teachers' => max(0, (int) ($matches[6] ?? 0)),
            'students' => max(0, (int) ($matches[7] ?? 0)),
            'default_password' => sanitize_text_field((string) ($matches[8] ?? '')),
            'special_username' => sanitize_user((string) ($matches[9] ?? ''), true),
            'special_password' => sanitize_text_field((string) ($matches[10] ?? '')),
            'extra_note' => isset($matches[10])
                ? sanitize_text_field((string) ($matches[11] ?? ''))
                : '',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function get_test_data_seed_phase_labels(): array
    {
        return [
            'reset_tables' => 'Mengosongkan tabel CBT',
            'reset_users' => 'Menghapus user CBT',
            'seed_subjects' => 'Membuat subject uji',
            'seed_users' => 'Membuat user guru dan siswa',
            'seed_bank_exams' => 'Menyiapkan bank soal per mapel',
            'seed_exams' => 'Membuat exam uji',
            'seed_bank_questions' => 'Membuat root question di bank soal',
            'seed_questions' => 'Membuat root question di bank soal',
            'sync_exam_questions' => 'Menyinkronkan soal ke exam uji',
            'finalize' => 'Finalisasi dataset uji',
        ];
    }

    private static function get_test_data_seed_batch_size(string $phase): int
    {
        switch ($phase) {
            case 'subjects':
                $batch_size = (int) apply_filters('cbt_test_data_seed_subject_batch_size', 12);
                if ($batch_size < 2) {
                    return 2;
                }
                if ($batch_size > 40) {
                    return 40;
                }
                return $batch_size;

            case 'users':
                $batch_size = (int) apply_filters('cbt_test_data_seed_user_batch_size', 160);
                if ($batch_size < 20) {
                    return 20;
                }
                if ($batch_size > 300) {
                    return 300;
                }
                return $batch_size;

            case 'exams':
                $batch_size = (int) apply_filters('cbt_test_data_seed_exam_batch_size', 14);
                if ($batch_size < 2) {
                    return 2;
                }
                if ($batch_size > 40) {
                    return 40;
                }
                return $batch_size;

            case 'questions':
            default:
                $batch_size = (int) apply_filters('cbt_test_data_seed_question_batch_size', 120);
                if ($batch_size < 20) {
                    return 20;
                }
                if ($batch_size > 250) {
                    return 250;
                }
                return $batch_size;
        }
    }

    private static function get_test_data_seed_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_test_data_seed_batch_max_seconds', 8.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    /**
     * @return string[]
     */
    private static function build_test_data_seed_codes(string $prefix, int $count): array
    {
        $codes = [];
        if ($count <= 0) {
            return $codes;
        }

        $digits = max(2, strlen((string) $count));
        for ($number = 1; $number <= $count; $number++) {
            $codes[] = $prefix . str_pad((string) $number, $digits, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    /**
     * @return array{name:string,code:string,description:string,image_bucket:string}
     */
    private static function build_test_data_seed_subject_entry(int $offset): array
    {
        $number = $offset + 1;
        $label = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $image_bucket = self::build_test_data_seed_subject_image_bucket($offset);

        return [
            'name' => 'TEST Subject ' . $label,
            'code' => 'TST' . $label,
            'description' => 'Dataset uji otomatis untuk subject ' . $label . '.',
            'image_bucket' => $image_bucket,
        ];
    }

    /**
     * @param array{name:string,code:string,description:string} $entry
     * @return array{id:int,status:string,name:string,code:string}
     */
    private static function upsert_test_data_seed_subject(array $entry): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $name = sanitize_text_field((string) ($entry['name'] ?? ''));
        $code = strtoupper(sanitize_key((string) ($entry['code'] ?? '')));
        $description = sanitize_textarea_field((string) ($entry['description'] ?? ''));
        if ($name === '' || $code === '') {
            return [
                'id' => 0,
                'status' => 'failed',
                'name' => $name,
                'code' => $code,
            ];
        }

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE code = %s ORDER BY id ASC LIMIT 1", $code)
        );

        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $table,
                [
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existing_id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            return [
                'id' => $updated === false ? 0 : $existing_id,
                'status' => $updated === false ? 'failed' : 'updated',
                'name' => $name,
                'code' => $code,
            ];
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        return [
            'id' => $inserted ? (int) $wpdb->insert_id : 0,
            'status' => $inserted ? 'created' : 'failed',
            'name' => $name,
            'code' => $code,
        ];
    }

    /**
     * @param array{label:string,subjects:int,exams:int,questions:int,students:int,teachers:int,classes:int,rooms:int,include_true_false_matrix:bool} $preset
     * @param string[] $kelas_codes
     * @param string[] $ruang_codes
     * @return array<int,array<string,string>>
     */
    private static function build_test_data_seed_user_rows(array $preset, int $offset, int $limit, array $kelas_codes, array $ruang_codes): array
    {
        $rows = [];
        $teacher_total = max(0, (int) ($preset['teachers'] ?? 0));
        $student_total = max(0, (int) ($preset['students'] ?? 0));
        $total = $teacher_total + $student_total;
        $agama_options = self::get_supported_agama_options();
        $jenis_kelamin_options = self::get_supported_jenis_kelamin_options();
        if ($limit <= 0 || $offset >= $total) {
            return $rows;
        }

        $end = min($offset + $limit, $total);
        for ($index = $offset; $index < $end; $index++) {
            $agama = !empty($agama_options)
                ? (string) $agama_options[$index % count($agama_options)]
                : '';
            $jenis_kelamin = !empty($jenis_kelamin_options)
                ? (string) $jenis_kelamin_options[$index % count($jenis_kelamin_options)]
                : '';

            if ($index < $teacher_total) {
                $number = $index + 1;
                if ($number === 1) {
                    $rows[] = self::build_test_data_seed_special_admin_row($ruang_codes, $agama_options, $jenis_kelamin_options);
                    continue;
                }
                $username = sprintf('test_guru_%04d', $number);
                $rows[] = [
                    'name' => sprintf('Guru Test %03d', $number),
                    'email' => $username . '@example.local',
                    'username' => $username,
                    'password' => self::TEST_DATA_SEED_DEFAULT_PASSWORD,
                    'role' => 'guru',
                    'kode_kelas' => '',
                    'kode_ruang' => !empty($ruang_codes) ? (string) $ruang_codes[($number - 1) % count($ruang_codes)] : '',
                    'agama' => $agama,
                    'jenis_kelamin' => $jenis_kelamin,
                    'foto' => '',
                    '__seed_kind' => 'teacher',
                ];
                continue;
            }

            $number = ($index - $teacher_total) + 1;
            if ($number === 1) {
                $rows[] = self::build_test_data_seed_special_student_row($kelas_codes, $ruang_codes, $agama_options, $jenis_kelamin_options);
                continue;
            }

            $username = sprintf('test_siswa_%04d', $number);
            $rows[] = [
                'name' => sprintf('Siswa Test %04d', $number),
                'email' => $username . '@example.local',
                'nisn' => sprintf('88%08d', $number),
                'username' => $username,
                'password' => self::TEST_DATA_SEED_DEFAULT_PASSWORD,
                'role' => 'siswa',
                'kode_kelas' => !empty($kelas_codes) ? (string) $kelas_codes[($number - 1) % count($kelas_codes)] : '',
                'kode_ruang' => !empty($ruang_codes) ? (string) $ruang_codes[($number - 1) % count($ruang_codes)] : '',
                'agama' => $agama,
                'jenis_kelamin' => $jenis_kelamin,
                'foto' => '',
                '__seed_kind' => 'student',
            ];
        }

        return $rows;
    }

    /**
     * @param string[] $kelas_codes
     * @param string[] $ruang_codes
     * @param string[] $agama_options
     * @return array<string,string>
     */
    private static function get_test_data_seed_special_student_kelas_code(array $kelas_codes): string
    {
        return !empty($kelas_codes) ? sanitize_text_field((string) $kelas_codes[0]) : '';
    }

    /**
     * @param string[] $ruang_codes
     * @param string[] $agama_options
     * @param string[] $jenis_kelamin_options
     * @return array<string,string>
     */
    private static function build_test_data_seed_special_admin_row(array $ruang_codes, array $agama_options, array $jenis_kelamin_options): array
    {
        $agama = !empty($agama_options) ? (string) reset($agama_options) : '';
        $jenis_kelamin = !empty($jenis_kelamin_options) ? (string) reset($jenis_kelamin_options) : '';

        return [
            'name' => 'CBT ADMIN',
            'email' => self::TEST_DATA_SEED_SPECIAL_ADMIN_USERNAME . '@example.local',
            'username' => self::TEST_DATA_SEED_SPECIAL_ADMIN_USERNAME,
            'password' => self::TEST_DATA_SEED_SPECIAL_ADMIN_PASSWORD,
            'role' => 'administrator',
            'kode_kelas' => '',
            'kode_ruang' => !empty($ruang_codes) ? (string) $ruang_codes[0] : '',
            'agama' => $agama,
            'jenis_kelamin' => $jenis_kelamin,
            'foto' => '',
            '__seed_kind' => 'teacher',
        ];
    }

    /**
     * @param string[] $kelas_codes
     * @param string[] $ruang_codes
     * @param string[] $agama_options
     * @param string[] $jenis_kelamin_options
     * @return array<string,string>
     */
    private static function build_test_data_seed_special_student_row(array $kelas_codes, array $ruang_codes, array $agama_options, array $jenis_kelamin_options): array
    {
        $agama = in_array('Islam', $agama_options, true)
            ? 'Islam'
            : (!empty($agama_options) ? (string) reset($agama_options) : '');
        $jenis_kelamin = !empty($jenis_kelamin_options) ? (string) reset($jenis_kelamin_options) : '';
        $special_kelas = self::get_test_data_seed_special_student_kelas_code($kelas_codes);

        return [
            'name' => strtoupper(self::TEST_DATA_SEED_SPECIAL_USERNAME),
            'email' => self::TEST_DATA_SEED_SPECIAL_USERNAME . '@example.local',
            'nisn' => '8899000001',
            'username' => self::TEST_DATA_SEED_SPECIAL_USERNAME,
            'password' => self::TEST_DATA_SEED_SPECIAL_PASSWORD,
            'role' => 'siswa',
            'kode_kelas' => $special_kelas,
            'kode_ruang' => !empty($ruang_codes) ? (string) $ruang_codes[0] : '',
            'agama' => $agama,
            'jenis_kelamin' => $jenis_kelamin,
            'foto' => '',
            '__seed_kind' => 'student',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_test_data_seed_fixed_exam_fixture_definitions(string $special_test_kelas): array
    {
        $target_kelas = $special_test_kelas !== '' ? $special_test_kelas : '';

        return [
            0 => [
                'title' => self::TEST_DATA_SEED_RECOVERY_FIXTURE_TITLE,
                'description' => sprintf(
                    'Fixture recovery deterministik untuk flow check Playwright. Exam ini aktif, khusus kelas %s, randomize_questions = 0, dan dipakai akun seed %s.',
                    $target_kelas !== '' ? $target_kelas : 'seed class',
                    self::TEST_DATA_SEED_SPECIAL_USERNAME
                ),
                'target_kelas' => $target_kelas,
                'duration_minutes' => 60,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'recovery_fixture',
                'seed_profile_label' => 'RECOVERY FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            1 => [
                'title' => self::TEST_DATA_SEED_SESSION_FIXTURE_TITLE,
                'description' => 'Fixture deterministik untuk flow check Auth & Session. Exam ini aktif, memakai soal objektif, dan difokuskan untuk validasi bootstrap serta revoke session.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 45,
                'kkm_percentage' => 70.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'session_fixture',
                'seed_profile_label' => 'SESSION FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            2 => [
                'title' => self::TEST_DATA_SEED_SYNC_FIXTURE_TITLE,
                'description' => 'Fixture deterministik untuk flow check Sync & REST. Exam ini aktif, memakai soal objektif, dan dijadikan dasar skenario offline, pending sync, dan finish lock.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 60,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'sync_fixture',
                'seed_profile_label' => 'SYNC FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            3 => [
                'title' => self::TEST_DATA_SEED_RESULT_FULL_FIXTURE_TITLE,
                'description' => 'Fixture hasil penuh untuk flow check Result & Scoring. Nilai siswa ditampilkan penuh setelah finish agar score, percentage, dan pass label mudah diverifikasi.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 45,
                'kkm_percentage' => 70.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'result_full_fixture',
                'seed_profile_label' => 'RESULT FULL FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            4 => [
                'title' => self::TEST_DATA_SEED_RESULT_RESTRICTED_FIXTURE_TITLE,
                'description' => 'Fixture hasil restricted untuk flow check Result & Scoring. Hasil tetap bisa dibuka, tetapi score dan review siswa dibatasi oleh setting exam.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 45,
                'kkm_percentage' => 70.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'seed_profile' => 'result_restricted_fixture',
                'seed_profile_label' => 'RESULT RESTRICTED FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            5 => [
                'title' => self::TEST_DATA_SEED_RESULT_ESSAY_FIXTURE_TITLE,
                'description' => 'Fixture essay untuk flow check Result & Scoring. Exam ini sengaja memakai soal essay agar pending manual scoring dan regrade admin bisa diverifikasi.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 60,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'result_essay_fixture',
                'seed_profile_label' => 'RESULT ESSAY FIXTURE',
                'seed_profile_question_type' => 'essay',
            ],
            6 => [
                'title' => self::TEST_DATA_SEED_TIMER_FIXTURE_TITLE,
                'description' => 'Fixture timer untuk flow check Timer & Lifecycle. Durasi dibuat pendek agar skenario countdown, timeout, dan extra time bisa dipercepat secara aman.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 5,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 1,
                'seed_profile' => 'timer_fixture',
                'seed_profile_label' => 'TIMER FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            7 => [
                'title' => self::TEST_DATA_SEED_RUNTIME_FIXTURE_TITLE,
                'description' => 'Fixture runtime campuran untuk flow check Question Runtime. Exam ini aktif, randomize opsi, dan menyediakan kombinasi tipe soal untuk menguji navigasi serta isolasi state.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 75,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 1,
                'show_student_result' => 1,
                'enable_calculator' => 1,
                'seed_profile' => 'runtime_fixture',
                'seed_profile_label' => 'RUNTIME FIXTURE',
                'seed_profile_question_type' => '',
            ],
            8 => [
                'title' => self::TEST_DATA_SEED_SECURITY_FIXTURE_TITLE,
                'description' => 'Fixture security untuk flow check observability. Exam ini dipakai memicu event fullscreen, clipboard, blur, idle, dan follow-up admin action pada attempt aktif.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 60,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'security_fixture',
                'seed_profile_label' => 'SECURITY FIXTURE',
                'seed_profile_question_type' => 'multiple_choice',
            ],
            9 => [
                'title' => self::TEST_DATA_SEED_IMPORT_PREVIEW_FIXTURE_TITLE,
                'description' => 'Fixture import preview untuk flow check DOCX. Exam ini menjadi target parity check antara preview admin, preview exam, dan review siswa.',
                'target_kelas' => $target_kelas,
                'duration_minutes' => 60,
                'kkm_percentage' => 75.0,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 0,
                'seed_profile' => 'import_preview_fixture',
                'seed_profile_label' => 'IMPORT PREVIEW FIXTURE',
                'seed_profile_question_type' => '',
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $subjects
     * @param string[] $kelas_codes
     * @return array<string,mixed>
     */
    private static function build_test_data_seed_exam_entry(
        int $offset,
        array $subjects,
        array $kelas_codes,
        string $preset_key,
        int $creator_id,
        array $exam_profile
    ): array {
        $subject_total = count($subjects);
        $subject_entry = $subject_total > 0
            ? (array) $subjects[$offset % $subject_total]
            : [];
        $subject_id = (int) ($subject_entry['id'] ?? 0);
        $subject_name = (string) ($subject_entry['name'] ?? 'TEST Subject');
        $subject_image_bucket = sanitize_key((string) ($subject_entry['image_bucket'] ?? ''));
        $exam_number = $offset + 1;

        $target_kelas = array_values(array_unique(array_filter(array_map(
            static function ($kelas_code): string {
                return sanitize_text_field((string) $kelas_code);
            },
            $kelas_codes
        ))));
        $special_test_kelas = self::get_test_data_seed_special_student_kelas_code($kelas_codes);
        if ($special_test_kelas !== '') {
            array_unshift($target_kelas, $special_test_kelas);
            $target_kelas = array_values(array_unique($target_kelas));
        }

        $status = 'published';
        $profile_label = sanitize_text_field((string) ($exam_profile['label'] ?? 'MIXED'));
        $profile_suffix = sanitize_text_field((string) ($exam_profile['suffix'] ?? '[MIXED]'));
        $profile_key = sanitize_key((string) ($exam_profile['profile'] ?? 'mixed'));
        $profile_question_type = sanitize_key((string) ($exam_profile['question_type'] ?? ''));

        $schedule = self::build_test_data_seed_exam_schedule($status, $offset);
        $duration_cycle = [45, 60, 75, 90, 120];
        $duration = $duration_cycle[$offset % count($duration_cycle)];
        $kkm_cycle = [65.0, 70.0, 75.0, 80.0, 85.0];
        $kkm_percentage = $kkm_cycle[$offset % count($kkm_cycle)];
        $fixed_fixture_definitions = self::get_test_data_seed_fixed_exam_fixture_definitions($special_test_kelas);
        if (isset($fixed_fixture_definitions[$offset]) && is_array($fixed_fixture_definitions[$offset])) {
            $fixture_definition = (array) $fixed_fixture_definitions[$offset];
            $fixture_target_kelas = self::normalize_target_kelas_csv((string) ($fixture_definition['target_kelas'] ?? ''));

            return [
                'subject_id' => $subject_id,
                'subject_name' => $subject_name,
                'title' => sanitize_text_field((string) ($fixture_definition['title'] ?? '')),
                'description' => wp_kses_post((string) ($fixture_definition['description'] ?? '')),
                'target_kelas' => $fixture_target_kelas,
                'duration_minutes' => max(1, (int) ($fixture_definition['duration_minutes'] ?? 60)),
                'kkm_percentage' => self::normalize_maintenance_kkm_percentage((float) ($fixture_definition['kkm_percentage'] ?? 75.0)),
                'total_questions' => 0,
                'randomize_questions' => !empty($fixture_definition['randomize_questions']) ? 1 : 0,
                'randomize_options' => !empty($fixture_definition['randomize_options']) ? 1 : 0,
                'show_student_result' => !isset($fixture_definition['show_student_result']) || !empty($fixture_definition['show_student_result']) ? 1 : 0,
                'enable_calculator' => !empty($fixture_definition['enable_calculator']) ? 1 : 0,
                'status' => $status,
                'starts_at' => $schedule['starts_at'],
                'ends_at' => $schedule['ends_at'],
                'created_by' => $creator_id,
                'seed_profile' => sanitize_key((string) ($fixture_definition['seed_profile'] ?? 'mixed')),
                'seed_profile_label' => sanitize_text_field((string) ($fixture_definition['seed_profile_label'] ?? 'MIXED')),
                'seed_profile_question_type' => sanitize_key((string) ($fixture_definition['seed_profile_question_type'] ?? '')),
                'seed_image_bucket' => $subject_image_bucket !== '' ? $subject_image_bucket : self::build_test_data_seed_subject_image_bucket($offset),
            ];
        }

        $question_type_labels = self::get_test_data_seed_question_type_labels();
        $display_question_type = $profile_question_type !== ''
            ? (string) ($question_type_labels[$profile_question_type] ?? ucwords(str_replace('_', ' ', $profile_question_type)))
            : ($profile_label !== '' ? $profile_label : 'tipe soal');

        $dynamic_title = $profile_key === 'mixed_50'
            ? 'TEST Type Exam - Mixed 50%'
            : sprintf('TEST Type Exam - %s', $display_question_type);
        $dynamic_description = $profile_key === 'mixed_50'
            ? sprintf(
                'Dataset uji otomatis preset %s. Exam Mixed 50%% berisi separuh soal dari setiap tipe soal.',
                ucfirst($preset_key)
            )
            : sprintf(
                'Dataset uji otomatis preset %s. Exam ini berisi semua soal tipe %s dari seluruh subject bank soal.',
                ucfirst($preset_key),
                $display_question_type
            );

        return [
            'subject_id' => $subject_id,
            'subject_name' => $subject_name,
            'title' => sanitize_text_field($dynamic_title),
            'description' => wp_kses_post($dynamic_description . ' Target kelas mencakup semua kelas test: ' . (!empty($target_kelas) ? implode(', ', $target_kelas) : 'Semua kelas test') . '.'),
            'target_kelas' => implode(',', $target_kelas),
            'duration_minutes' => $duration,
            'kkm_percentage' => $kkm_percentage,
            'total_questions' => 0,
            'randomize_questions' => ($offset % 2 === 0) ? 1 : 0,
            'randomize_options' => ($offset % 3 === 0) ? 1 : 0,
            'show_student_result' => 1,
            'enable_calculator' => ($offset % 4 === 0) ? 1 : 0,
            'status' => $status,
            'starts_at' => $schedule['starts_at'],
            'ends_at' => $schedule['ends_at'],
            'created_by' => $creator_id,
            'seed_profile' => $profile_key !== '' ? $profile_key : 'mixed',
            'seed_profile_label' => $profile_label !== '' ? $profile_label : 'MIXED',
            'seed_profile_question_type' => $profile_question_type,
            'seed_image_bucket' => $subject_image_bucket !== '' ? $subject_image_bucket : self::build_test_data_seed_subject_image_bucket($offset),
        ];
    }

    /**
     * @return array{starts_at:?string,ends_at:?string}
     */
    private static function build_test_data_seed_exam_schedule(string $status, int $offset): array
    {
        $now = current_time('timestamp', true);
        $timezone = wp_timezone();
        $starts_at = null;
        $ends_at = null;

        if ($status === 'published') {
            $starts_at = wp_date('Y-m-d H:i:s', $now - (($offset % 5) + 1) * HOUR_IN_SECONDS, $timezone);
            $ends_at = wp_date('Y-m-d H:i:s', $now + (($offset % 4) + 2) * DAY_IN_SECONDS, $timezone);
        } elseif ($status === 'closed') {
            $starts_at = wp_date('Y-m-d H:i:s', $now - (($offset % 5) + 2) * DAY_IN_SECONDS, $timezone);
            $ends_at = wp_date('Y-m-d H:i:s', $now - (($offset % 6) + 1) * HOUR_IN_SECONDS, $timezone);
        }

        return [
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
        ];
    }

    /**
     * @param array<string,mixed> $entry
     * @return array{id:int,status:string,subject_id:int,subject_name:string,title:string,target_kelas:string}
     */
    private static function upsert_test_data_seed_exam(array $entry): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_exams';
        $subject_id = (int) ($entry['subject_id'] ?? 0);
        $title = sanitize_text_field((string) ($entry['title'] ?? ''));
        $description = wp_kses_post((string) ($entry['description'] ?? ''));
        $target_kelas = self::normalize_target_kelas_csv((string) ($entry['target_kelas'] ?? ''));
        if ($subject_id <= 0 || $title === '') {
            return [
                'id' => 0,
                'status' => 'failed',
                'subject_id' => $subject_id,
                'subject_name' => sanitize_text_field((string) ($entry['subject_name'] ?? '')),
                'title' => $title,
                'target_kelas' => $target_kelas,
            ];
        }

        $data = [
            'subject_id' => $subject_id,
            'title' => $title,
            'description' => $description,
            'target_kelas' => $target_kelas,
            'duration_minutes' => max(1, (int) ($entry['duration_minutes'] ?? 60)),
            'kkm_percentage' => self::normalize_maintenance_kkm_percentage((float) ($entry['kkm_percentage'] ?? 75.0)),
            'total_questions' => max(0, (int) ($entry['total_questions'] ?? 0)),
            'randomize_questions' => !empty($entry['randomize_questions']) ? 1 : 0,
            'randomize_options' => !empty($entry['randomize_options']) ? 1 : 0,
            'show_student_result' => !isset($entry['show_student_result']) || !empty($entry['show_student_result']) ? 1 : 0,
            'enable_calculator' => !empty($entry['enable_calculator']) ? 1 : 0,
            'status' => sanitize_key((string) ($entry['status'] ?? 'draft')),
            'starts_at' => $entry['starts_at'] ?? null,
            'ends_at' => $entry['ends_at'] ?? null,
            'created_by' => max(0, (int) ($entry['created_by'] ?? 0)),
            'updated_at' => current_time('mysql'),
        ];

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE subject_id = %d AND title = %s ORDER BY id ASC LIMIT 1",
                $subject_id,
                $title
            )
        );

        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $existing_id],
                ['%d', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );

            return [
                'id' => $updated === false ? 0 : $existing_id,
                'status' => $updated === false ? 'failed' : 'updated',
                'subject_id' => $subject_id,
                'subject_name' => sanitize_text_field((string) ($entry['subject_name'] ?? '')),
                'title' => $title,
                'target_kelas' => $target_kelas,
            ];
        }

        $data['created_at'] = current_time('mysql');
        $inserted = $wpdb->insert(
            $table,
            $data,
            ['%d', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return [
            'id' => $inserted ? (int) $wpdb->insert_id : 0,
            'status' => $inserted ? 'created' : 'failed',
            'subject_id' => $subject_id,
            'subject_name' => sanitize_text_field((string) ($entry['subject_name'] ?? '')),
            'title' => $title,
            'target_kelas' => $target_kelas,
        ];
    }

    /**
     * @return string[]
     */
    private static function test_data_seed_question_type_cycle(string $preset_key): array
    {
        return self::get_test_data_seed_all_question_types();
    }

    /**
     * @param array<int,array<string,mixed>> $exams
     * @param array<int|string,array<string,mixed>> $bank_exams
     * @param array<string,string> $bulk_image_source_map
     * @return array<string,mixed>
     */
    private static function resolve_test_data_seed_bank_question_type(int $offset, string $preset_key, int $total_questions = 0): array
    {
        $question_types = self::get_test_data_seed_all_question_types();
        $question_type_total = count($question_types);
        if ($question_type_total <= 0) {
            return [
                'question_type' => 'multiple_choice',
                'type_index' => 0,
                'type_question_index' => max(0, $offset),
                'questions_per_type' => 0,
            ];
        }

        $questions_per_type = self::get_test_data_seed_questions_per_type($preset_key, $total_questions);
        if ($questions_per_type <= 0) {
            $questions_per_type = 1;
        }

        $type_index = intdiv(max(0, $offset), $questions_per_type);
        $type_question_index = max(0, $offset) % $questions_per_type;
        if ($type_index >= $question_type_total) {
            $type_index = max(0, $offset) % $question_type_total;
            $type_question_index = intdiv(max(0, $offset), $question_type_total);
        }

        return [
            'question_type' => (string) ($question_types[$type_index] ?? 'multiple_choice'),
            'type_index' => $type_index,
            'type_question_index' => $type_question_index,
            'questions_per_type' => $questions_per_type,
        ];
    }

    /**
     * @param array<int|string,array<string,mixed>> $bank_exams
     * @return array<int,array<string,mixed>>
     */
    private static function get_test_data_seed_sorted_bank_exam_entries(array $bank_exams): array
    {
        $entries = [];
        foreach ($bank_exams as $entry) {
            if (!is_array($entry) || (int) ($entry['id'] ?? 0) <= 0 || (int) ($entry['subject_id'] ?? 0) <= 0) {
                continue;
            }
            $entries[] = $entry;
        }

        usort($entries, static function (array $left, array $right): int {
            return ((int) ($left['subject_id'] ?? 0)) <=> ((int) ($right['subject_id'] ?? 0));
        });

        return $entries;
    }

    /**
     * @param array<int,array<string,mixed>> $exams
     * @return array<string,mixed>
     */
    private static function resolve_test_data_seed_exam_entry_for_question_type(string $question_type, array $exams): array
    {
        $question_type = sanitize_key($question_type);
        foreach ($exams as $exam_entry) {
            if (!is_array($exam_entry)) {
                continue;
            }
            if (sanitize_key((string) ($exam_entry['seed_profile'] ?? '')) !== 'type_all_' . $question_type) {
                continue;
            }

            return $exam_entry;
        }

        return [];
    }

    private static function build_test_data_seed_bank_question_row(
        int $offset,
        array $exams,
        array $bank_exams,
        string $preset_key,
        array &$bulk_image_source_map
    ): array {
        $presets = self::test_data_seed_presets();
        $question_total = (int) ($presets[$preset_key]['questions'] ?? 0);
        $type_context = self::resolve_test_data_seed_bank_question_type($offset, $preset_key, $question_total);
        $question_type = sanitize_key((string) ($type_context['question_type'] ?? 'multiple_choice'));
        $type_question_index = max(0, (int) ($type_context['type_question_index'] ?? $offset));
        $target_exam_entry = self::resolve_test_data_seed_exam_entry_for_question_type($question_type, $exams);
        $target_exam_id = (int) ($target_exam_entry['id'] ?? 0);
        $bank_exam_entries = self::get_test_data_seed_sorted_bank_exam_entries($bank_exams);
        $bank_exam_entry = !empty($bank_exam_entries)
            ? (array) $bank_exam_entries[$type_question_index % count($bank_exam_entries)]
            : [];
        if (empty($bank_exam_entry)) {
            $fallback_exam_entry = self::resolve_test_data_seed_bank_question_exam_entry($offset, $exams);
            $subject_id = (int) ($fallback_exam_entry['subject_id'] ?? 0);
            $bank_exam_entry = $subject_id > 0 && isset($bank_exams[$subject_id]) && is_array($bank_exams[$subject_id])
                ? (array) $bank_exams[$subject_id]
                : [];
        }
        $subject_id = (int) ($bank_exam_entry['subject_id'] ?? 0);
        $exam_id = (int) ($bank_exam_entry['id'] ?? 0);
        $subject_name = sanitize_text_field((string) ($bank_exam_entry['subject_name'] ?? 'TEST Subject'));
        $image_bucket = sanitize_key((string) ($bank_exam_entry['image_bucket'] ?? ($target_exam_entry['seed_image_bucket'] ?? '')));
        $question_number = $offset + 1;
        $variant_number = $type_question_index + 1;
        $rich_recipe = self::resolve_test_data_seed_rich_recipe($question_type, $question_number);
        $row = [];
        $context = [];

        switch ($question_type) {
            case 'multiple_answer':
                $base = ($question_number % 12) + 2;
                $option_count = 3 + (($variant_number - 1) % 10);
                $desired_correct_count = 2 + (($variant_number - 1) % 3);
                $correct_count = max(2, min($option_count - 1, $desired_correct_count));
                $correct_positions = [];
                $position_seed = $question_number;
                while (count($correct_positions) < $correct_count) {
                    $position = (($position_seed * 3) + (count($correct_positions) * 2)) % $option_count;
                    if (!in_array($position, $correct_positions, true)) {
                        $correct_positions[] = $position;
                    }
                    $position_seed++;
                }
                sort($correct_positions);

                $options = [];
                $correct_keys = [];
                for ($idx = 0; $idx < $option_count; $idx++) {
                    $is_correct = in_array($idx, $correct_positions, true);
                    $multiplier = $idx + 2;
                    $options[] = (string) ($is_correct ? ($base * $multiplier) : (($base * $multiplier) + 1));
                    if ($is_correct) {
                        $correct_keys[] = chr(65 + $idx);
                    }
                }

                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'multiple_answer',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Pilih semua kelipatan dari %d.',
                        $question_number,
                        $subject_name,
                        $base
                    ),
                    'points' => 1.50,
                    'options' => implode('||', $options),
                    'correct_answer' => implode(',', $correct_keys),
                    'correct_text' => '',
                    'explanation' => sprintf(
                        'Kelipatan dari %d adalah semua pilihan yang habis dibagi tanpa sisa oleh angka tersebut.',
                        $base
                    ),
                ];
                $context = [
                    'base' => $base,
                    'option_count' => $option_count,
                    'correct_count' => $correct_count,
                    'plain_options' => $options,
                    'correct_keys' => $correct_keys,
                ];
                break;

            case 'true_false':
                $left = ($question_number % 21) + 9;
                $right = ($question_number % 8) + 3;
                $is_true = ($question_number % 2) === 0;
                $claim = $is_true ? ($left + $right) : ($left + $right + 1);
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'true_false',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Pernyataan: %d + %d = %d.',
                        $question_number,
                        $subject_name,
                        $left,
                        $right,
                        $claim
                    ),
                    'points' => 1.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => $is_true ? 'true' : 'false',
                    'explanation' => 'Periksa hasil operasi penjumlahan pada pernyataan tersebut.',
                ];
                $context = [
                    'left' => $left,
                    'right' => $right,
                    'claim' => $claim,
                    'is_true' => $is_true,
                ];
                break;

            case 'true_false_matrix':
                $base = ($question_number % 17) + 6;
                $statement_count = 2 + (($variant_number - 1) % 9);
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'true_false_matrix',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Tentukan Benar/Salah untuk setiap pernyataan berikut.',
                        $question_number,
                        $subject_name
                    ),
                    'points' => 2.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => self::build_test_data_seed_true_false_matrix_payload($base, $statement_count),
                    'explanation' => 'Setiap baris dinilai terpisah sebagai benar atau salah.',
                ];
                $context = [
                    'base' => $base,
                    'statement_count' => $statement_count,
                ];
                break;

            case 'short_answer':
                $short_answer_entry = self::build_test_data_seed_short_answer_entry($question_number, $subject_name);
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'short_answer',
                    'question_text' => (string) $short_answer_entry['question_text'],
                    'points' => 1.25,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => (string) $short_answer_entry['correct_text'],
                    'explanation' => (string) $short_answer_entry['explanation'],
                ];
                $context = [
                    'input_count' => (($question_number - 1) % 8) + 1,
                ];
                break;

            case 'ordering':
                $item_count = 2 + (($variant_number - 1) % 11);
                $ordering_items = [];
                for ($idx = 1; $idx <= $item_count; $idx++) {
                    $ordering_items[] = sprintf('Tahap %d proses %s nomor %03d', $idx, strtolower($subject_name), $question_number);
                }
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'ordering',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Susun tahapan berikut sesuai urutan benar dari tahap awal sampai akhir.',
                        $question_number,
                        $subject_name
                    ),
                    'points' => 2.00,
                    'options' => implode('||', $ordering_items),
                    'correct_answer' => '',
                    'correct_text' => '',
                    'explanation' => 'Urutan benar mengikuti nomor tahap yang meningkat dari awal sampai akhir.',
                ];
                $context = [
                    'item_count' => $item_count,
                    'plain_options' => $ordering_items,
                ];
                break;

            case 'matching':
                $pair_count = 2 + (($variant_number - 1) % 11);
                $matching_items = [];
                for ($idx = 1; $idx <= $pair_count; $idx++) {
                    $value = (($question_number + $idx) % 9) + 2;
                    $matching_items[] = [
                        'position' => $idx,
                        'item_key' => (string) $idx,
                        'prompt_text' => sprintf('Konsep %d: %d x %d', $idx, $value, $idx + 1),
                        'option_text' => sprintf('Hasil %d untuk konsep %d', $value * ($idx + 1), $idx),
                    ];
                }
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'matching',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Cocokkan setiap konsep di kiri dengan hasil yang tepat.',
                        $question_number,
                        $subject_name
                    ),
                    'points' => 2.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_matching_payload($matching_items),
                    'explanation' => 'Setiap pasangan dinilai partial berdasarkan pilihan kanan yang sesuai.',
                    'matching_items' => $matching_items,
                ];
                $context = [
                    'pair_count' => $pair_count,
                ];
                break;

            case 'cloze_dropdown':
                $cloze_definition = self::build_test_data_seed_cloze_dropdown_definition($variant_number, $question_number);
                $blank_count = (int) ($cloze_definition['blank_count'] ?? 1);
                $dropdown_option_count = (int) ($cloze_definition['dropdown_option_count'] ?? 2);
                $cloze_blanks = isset($cloze_definition['blanks']) && is_array($cloze_definition['blanks'])
                    ? $cloze_definition['blanks']
                    : [];
                $cloze_sentences = isset($cloze_definition['sentences']) && is_array($cloze_definition['sentences'])
                    ? $cloze_definition['sentences']
                    : [];
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'cloze_dropdown',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Lengkapi teks berikut: %s',
                        $question_number,
                        $subject_name,
                        implode(' ', $cloze_sentences)
                    ),
                    'points' => 2.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_cloze_dropdown_payload($cloze_blanks),
                    'explanation' => 'Setiap dropdown dinilai partial sesuai opsi yang benar.',
                    'cloze_blanks' => $cloze_blanks,
                ];
                $context = [
                    'blank_count' => $blank_count,
                    'dropdown_option_count' => $dropdown_option_count,
                ];
                break;

            case 'categorization':
                $categorization_definition = self::build_test_data_seed_categorization_definition($variant_number, $question_number);
                $categories = isset($categorization_definition['categories']) && is_array($categorization_definition['categories'])
                    ? $categorization_definition['categories']
                    : [];
                $categorization_items = isset($categorization_definition['items']) && is_array($categorization_definition['items'])
                    ? $categorization_definition['items']
                    : [];
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'categorization',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Kelompokkan setiap item ke kategori yang tepat.',
                        $question_number,
                        $subject_name
                    ),
                    'points' => 2.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_categorization_payload($categories, $categorization_items),
                    'explanation' => 'Setiap item dinilai partial berdasarkan kategori yang dipilih.',
                    'categorization_categories' => $categories,
                    'categorization_items' => $categorization_items,
                ];
                $context = [
                    'category_count' => count($categories),
                    'item_count' => count($categorization_items),
                ];
                break;

            case 'table_completion':
                $table_definition = self::build_test_data_seed_table_completion_definition($variant_number);
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'table_completion',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Lengkapi tabel kota, negara, dan benua berikut.',
                        $question_number,
                        $subject_name
                    ),
                    'points' => 3.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => CBT_Admin_Questions_Helper::build_table_completion_payload($table_definition),
                    'explanation' => 'Sel teks dan dropdown pada tabel dinilai partial per sel jawaban.',
                    'table_completion' => $table_definition,
                ];
                $context = [
                    'row_count' => (int) ($table_definition['row_count'] ?? 2),
                    'column_count' => (int) ($table_definition['column_count'] ?? 2),
                ];
                break;

            case 'essay':
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'essay',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Jelaskan langkah penyelesaian soal cerita sederhana yang melibatkan konsep %s.',
                        $question_number,
                        $subject_name,
                        strtolower($subject_name)
                    ),
                    'points' => 5.00,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => 'Rubrik: jawaban memuat konsep utama, langkah kerja runtut, dan kesimpulan yang konsisten.',
                    'explanation' => 'Penilaian essay mengikuti rubrik langkah, konsep, dan kesimpulan.',
                ];
                break;

            case 'multiple_choice':
            default:
                $left = ($question_number % 37) + 13;
                $right = ($question_number % 10) + 3;
                $correct = $left + $right;
                $option_count = 3 + (($variant_number - 1) % 3);
                $correct_index = ($variant_number - 1) % $option_count;
                $options = [];
                for ($idx = 0; $idx < $option_count; $idx++) {
                    if ($idx === $correct_index) {
                        $options[] = (string) $correct;
                        continue;
                    }
                    $delta = $idx < $correct_index ? -($correct_index - $idx + 1) : ($idx - $correct_index + 1);
                    $options[] = (string) max(1, $correct + $delta);
                }
                $correct_key = chr(65 + $correct_index);
                $correct_keys = [$correct_key];
                $row = [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'multiple_choice',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Berapakah hasil %d + %d?',
                        $question_number,
                        $subject_name,
                        $left,
                        $right
                    ),
                    'points' => 1.00,
                    'options' => implode('||', $options),
                    'correct_answer' => $correct_key,
                    'correct_text' => '',
                    'explanation' => 'Jawaban benar adalah hasil penjumlahan kedua bilangan.',
                ];
                $context = [
                    'left' => $left,
                    'right' => $right,
                    'correct' => $correct,
                    'option_count' => $option_count,
                    'plain_options' => $options,
                    'correct_keys' => $correct_keys,
                ];
                break;
        }

        $row['subject_id'] = $subject_id;

        return self::decorate_test_data_seed_question_row_with_rich_content(
            $row,
            $question_number,
            $subject_name,
            $image_bucket,
            $rich_recipe,
            $context,
            $bulk_image_source_map
        );
    }

    /**
     * @param array<int|string,array<string,mixed>> $exams
     * @return array<string,mixed>
     */
    private static function resolve_test_data_seed_bank_question_exam_entry(int $offset, array $exams): array
    {
        $subject_exam_groups = [];
        foreach ($exams as $exam_entry) {
            if (!is_array($exam_entry)) {
                continue;
            }

            $subject_id = (int) ($exam_entry['subject_id'] ?? 0);
            if ($subject_id <= 0) {
                continue;
            }

            if (!isset($subject_exam_groups[$subject_id])) {
                $subject_exam_groups[$subject_id] = [];
            }

            $subject_exam_groups[$subject_id][] = $exam_entry;
        }

        if (empty($subject_exam_groups)) {
            $first_exam_entry = reset($exams);
            return is_array($first_exam_entry) ? $first_exam_entry : [];
        }

        $subject_ids = array_values(array_map('intval', array_keys($subject_exam_groups)));
        sort($subject_ids);
        $subject_total = count($subject_ids);
        $subject_index = $subject_total > 0 ? ($offset % $subject_total) : 0;
        $subject_id = (int) ($subject_ids[$subject_index] ?? 0);
        $subject_exam_entries = isset($subject_exam_groups[$subject_id]) && is_array($subject_exam_groups[$subject_id])
            ? array_values($subject_exam_groups[$subject_id])
            : [];

        if (empty($subject_exam_entries)) {
            return [];
        }

        $exam_rotation_offset = intdiv(max(0, $offset), max(1, $subject_total));
        $exam_index = $exam_rotation_offset % count($subject_exam_entries);
        $exam_entry = $subject_exam_entries[$exam_index] ?? [];

        return is_array($exam_entry) ? $exam_entry : [];
    }

    private static function build_test_data_seed_true_false_matrix_payload(int $base, int $statement_count): string
    {
        $statement_count = min(10, max(2, $statement_count));
        $lines = [];

        for ($idx = 1; $idx <= $statement_count; $idx++) {
            $value = $base + $idx;
            switch (($idx - 1) % 4) {
                case 0:
                    $lines[] = sprintf('Bilangan %d lebih besar dari 0|true', $value);
                    break;
                case 1:
                    $lines[] = sprintf('Hasil %d + %d sama dengan %d|true', $base, $idx, $base + $idx);
                    break;
                case 2:
                    $lines[] = sprintf('Hasil %d x %d sama dengan %d|false', $base, $idx + 1, ($base * ($idx + 1)) + 1);
                    break;
                default:
                    $lines[] = sprintf('Bilangan %d lebih kecil dari %d|false', $value, $base);
                    break;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{blank_count:int,dropdown_option_count:int,blanks:array<int,array<string,mixed>>,sentences:string[]}
     */
    private static function build_test_data_seed_cloze_dropdown_definition(int $variant_number, int $question_number): array
    {
        $blank_count = 1 + (($variant_number - 1) % 8);
        $dropdown_option_count = 2 + (intdiv(max(0, $variant_number - 1), 8) % 5);
        $blanks = [];
        $sentences = [];

        for ($blank = 1; $blank <= $blank_count; $blank++) {
            $correct_position = (($variant_number + $blank - 2) % $dropdown_option_count) + 1;
            $options = [];
            for ($option = 1; $option <= $dropdown_option_count; $option++) {
                $expected = (($question_number + ($blank * 7)) % 40) + 10;
                if ($option === $correct_position) {
                    $option_text = sprintf('Nilai %d', $expected);
                } else {
                    $direction = $option < $correct_position ? -1 : 1;
                    $distance = abs($option - $correct_position) + $blank + 1;
                    $option_text = sprintf('Nilai %d', $expected + ($direction * $distance));
                }
                $options[] = [
                    'option_key' => chr(64 + $option),
                    'option_text' => $option_text,
                    'is_correct' => $option === $correct_position ? 1 : 0,
                    'option_order' => $option,
                ];
            }

            $blanks[] = [
                'blank_key' => (string) $blank,
                'blank_position' => $blank,
                'options' => $options,
            ];
            $sentences[] = sprintf('Nilai untuk bagian %d adalah [DROPDOWN_%d].', $blank, $blank);
        }

        return [
            'blank_count' => $blank_count,
            'dropdown_option_count' => $dropdown_option_count,
            'blanks' => $blanks,
            'sentences' => $sentences,
        ];
    }

    /**
     * @return array{categories:array<int,array<string,mixed>>,items:array<int,array<string,mixed>>}
     */
    private static function build_test_data_seed_categorization_definition(int $variant_number, int $question_number): array
    {
        $category_count = 2 + (($variant_number - 1) % 7);
        $item_count = 2 + (($variant_number - 1) % 23);
        $category_labels = [
            'Mamalia',
            'Reptil',
            'Aves',
            'Amfibi',
            'Ikan',
            'Serangga',
            'Benda Padat',
            'Benda Cair',
        ];
        $categories = [];
        for ($idx = 1; $idx <= $category_count; $idx++) {
            $categories[] = [
                'category_index' => $idx,
                'option_text' => (string) ($category_labels[$idx - 1] ?? ('Kategori ' . $idx)),
                'is_correct' => 0,
            ];
        }

        $items = [];
        for ($idx = 1; $idx <= $item_count; $idx++) {
            $category_index = (($idx - 1) % $category_count) + 1;
            $category_label = (string) ($categories[$category_index - 1]['option_text'] ?? ('Kategori ' . $category_index));
            $items[] = [
                'position' => $idx,
                'item_key' => (string) $idx,
                'item_text' => sprintf('%s - contoh %02d soal %03d', $category_label, $idx, $question_number),
                'correct_category_index' => $category_index,
            ];
        }

        return [
            'categories' => $categories,
            'items' => $items,
        ];
    }

    /**
     * @return array{row_count:int,column_count:int,cells:array<int,array<string,mixed>>}
     */
    private static function build_test_data_seed_table_completion_definition(int $variant_number): array
    {
        $row_count = 2 + (($variant_number - 1) % 7);
        $column_count = 2 + (($variant_number - 1) % 5);
        $cells = [];
        $answer_count = 0;

        for ($row = 1; $row <= $row_count; $row++) {
            for ($column = 1; $column <= $column_count; $column++) {
                if ($row === 1 || $column === 1 || $answer_count >= 24) {
                    $cells[] = [
                        'cell_key' => null,
                        'row_position' => $row,
                        'column_position' => $column,
                        'cell_type' => 'static',
                        'cell_text' => $row === 1
                            ? sprintf('Kolom %d', $column)
                            : sprintf('Baris %d', $row),
                        'correct_text' => '',
                        'options' => [],
                    ];
                    continue;
                }

                $answer_count++;
                $cell_key = chr(64 + $column) . $row;
                $is_dropdown = (($answer_count + $variant_number) % 2) === 0;
                if (!$is_dropdown) {
                    $cells[] = [
                        'cell_key' => $cell_key,
                        'row_position' => $row,
                        'column_position' => $column,
                        'cell_type' => 'text',
                        'cell_text' => sprintf('Isi teks %s', $cell_key),
                        'correct_text' => sprintf('Jawaban %s', $cell_key),
                        'options' => [],
                    ];
                    continue;
                }

                $option_count = 2 + (($variant_number + $answer_count - 2) % 5);
                $correct_position = (($variant_number + $row + $column - 3) % $option_count) + 1;
                $options = [];
                for ($option = 1; $option <= $option_count; $option++) {
                    $options[] = [
                        'option_key' => chr(64 + $option),
                        'option_text' => $option === $correct_position
                            ? sprintf('Kunci %s', $cell_key)
                            : sprintf('Distraktor %s-%d', $cell_key, $option),
                        'is_correct' => $option === $correct_position ? 1 : 0,
                        'option_order' => $option,
                    ];
                }

                $cells[] = [
                    'cell_key' => $cell_key,
                    'row_position' => $row,
                    'column_position' => $column,
                    'cell_type' => 'dropdown',
                    'cell_text' => sprintf('Pilih opsi %s', $cell_key),
                    'correct_text' => '',
                    'options' => $options,
                ];
            }
        }

        return [
            'row_count' => $row_count,
            'column_count' => $column_count,
            'cells' => $cells,
        ];
    }

    /**
     * @return array{question_text:string,correct_text:string,explanation:string}
     */
    private static function build_test_data_seed_short_answer_entry(int $question_number, string $subject_name): array
    {
        $input_count = (($question_number - 1) % 8) + 1;
        $answers = [];
        $question_text = '';
        $explanation = '';

        switch ($input_count) {
            case 1:
                $left = ($question_number % 14) + 6;
                $right = ($question_number % 9) + 4;
                $answers = [(string) ($left * $right)];
                $question_text = sprintf(
                    'Soal %03d (%s). Hasil dari %d x %d adalah [INPUT_1], lalu tulis tanpa satuan.',
                    $question_number,
                    $subject_name,
                    $left,
                    $right
                );
                $explanation = 'Jawaban singkat diisi dengan hasil perkalian yang tepat tanpa tambahan teks lain.';
                break;

            case 2:
                $base = ($question_number % 18) + 10;
                $answers = [
                    (string) ($base + 2),
                    (string) ($base + 4),
                ];
                $question_text = sprintf(
                    'Soal %03d (%s). Lengkapi dua bilangan genap setelah %d, yaitu [INPUT_1] dan [INPUT_2], dengan urutan dari kecil ke besar.',
                    $question_number,
                    $subject_name,
                    $base
                );
                $explanation = 'Setiap isian mengikuti urutan dua bilangan genap setelah angka dasar.';
                break;

            case 3:
                $base = ($question_number % 8) + 3;
                $answers = [
                    (string) ($base * 2),
                    (string) ($base * 3),
                    (string) ($base * 4),
                ];
                $question_text = sprintf(
                    'Soal %03d (%s). Lengkapi tiga kelipatan awal dari %d setelah angka dasar: [INPUT_1], [INPUT_2], dan [INPUT_3], disusun berurutan.',
                    $question_number,
                    $subject_name,
                    $base
                );
                $explanation = 'Jawaban diisi dengan tiga kelipatan berurutan dari bilangan dasar yang diberikan.';
                break;

            case 4:
                $base = ($question_number % 21) + 20;
                $answers = [
                    (string) ($base + 1),
                    (string) ($base + 2),
                    (string) ($base + 3),
                    (string) ($base + 4),
                ];
                $question_text = sprintf(
                    'Soal %03d (%s). Empat bilangan berurutan setelah %d adalah [INPUT_1], [INPUT_2], [INPUT_3], dan [INPUT_4], tanpa ada angka yang terlewat.',
                    $question_number,
                    $subject_name,
                    $base
                );
                $explanation = 'Isi semua kotak dengan empat bilangan berikutnya secara berurutan.';
                break;

            case 5:
                $answers = ['a', 'i', 'u', 'e', 'o'];
                $question_text = sprintf(
                    'Soal %03d (%s). Lengkapi urutan huruf vokal bahasa Indonesia: [INPUT_1], [INPUT_2], [INPUT_3], [INPUT_4], dan [INPUT_5], sesuai urutan standar.',
                    $question_number,
                    $subject_name
                );
                $explanation = 'Gunakan urutan huruf vokal standar a, i, u, e, o.';
                break;

            case 6:
                $base = (($question_number % 6) + 4);
                $answers = [
                    (string) ($base * 1),
                    (string) ($base * 2),
                    (string) ($base * 3),
                    (string) ($base * 4),
                    (string) ($base * 5),
                    (string) ($base * 6),
                ];
                $question_text = sprintf(
                    'Soal %03d (%s). Lengkapi enam kelipatan berurutan dari %d: [INPUT_1], [INPUT_2], [INPUT_3], [INPUT_4], [INPUT_5], dan [INPUT_6].',
                    $question_number,
                    $subject_name,
                    $base
                );
                $explanation = 'Isi semua kotak dengan enam kelipatan pertama dari angka dasar secara berurutan.';
                break;

            case 7:
                $answers = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
                $question_text = sprintf(
                    'Soal %03d (%s). Lengkapi urutan tujuh hari dalam satu minggu: [INPUT_1], [INPUT_2], [INPUT_3], [INPUT_4], [INPUT_5], [INPUT_6], dan [INPUT_7].',
                    $question_number,
                    $subject_name
                );
                $explanation = 'Gunakan urutan nama hari yang umum dipakai dalam bahasa Indonesia.';
                break;

            case 8:
            default:
                $start = ((($question_number % 5) + 1) * 2);
                $answers = [
                    (string) $start,
                    (string) ($start + 2),
                    (string) ($start + 4),
                    (string) ($start + 6),
                    (string) ($start + 8),
                    (string) ($start + 10),
                    (string) ($start + 12),
                    (string) ($start + 14),
                ];
                $question_text = sprintf(
                    'Soal %03d (%s). Lengkapi delapan bilangan genap berurutan mulai dari %d: [INPUT_1], [INPUT_2], [INPUT_3], [INPUT_4], [INPUT_5], [INPUT_6], [INPUT_7], dan [INPUT_8].',
                    $question_number,
                    $subject_name,
                    $start
                );
                $explanation = 'Isi dengan delapan bilangan genap berurutan tanpa ada angka yang terlewat.';
                break;
        }

        return [
            'question_text' => $question_text,
            'correct_text' => implode('||', $answers),
            'explanation' => $explanation,
        ];
    }

    /**
     * @return array{status:string,question_id:int}
     */
    private static function insert_test_data_seed_question(array $row): array
    {
        global $wpdb;

        $question_type = self::map_import_question_type((string) ($row['question_type'] ?? ''));
        $question_text = trim(CBT_Admin_Questions_Helper::sanitize_editor_html((string) ($row['question_text'] ?? '')));
        $explanation = trim(CBT_Admin_Questions_Helper::sanitize_editor_html((string) ($row['explanation'] ?? '')));
        $exam_id = absint($row['exam_id'] ?? 0);
        if ($question_type === '' || $question_text === '' || $exam_id <= 0) {
            return [
                'status' => 'failed',
                'question_id' => 0,
            ];
        }

        $points = isset($row['points']) && $row['points'] !== '' ? (float) $row['points'] : 1.0;
        $points = max(0, $points);
        $options_input = (string) ($row['options'] ?? '');
        $options_payload = trim((string) ($row['options_payload'] ?? ''));
        $correct_answer = (string) ($row['correct_answer'] ?? '');
        $correct_text = (string) ($row['correct_text'] ?? '');
        $options_raw = '';
        $matching_items = [];
        $cloze_blanks = [];
        $categorization_categories = [];
        $categorization_items = [];
        $table_completion_definition = [];

        if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
            if ($options_payload !== '') {
                $options_raw = $options_payload;
            } else {
                $built = self::build_options_raw_from_import($options_input, $correct_answer, $question_type);
                if ($built === '') {
                    return [
                        'status' => 'failed',
                        'question_id' => 0,
                    ];
                }
                $options_raw = $built;
            }
            $correct_text = '';
        } elseif ($question_type === 'true_false') {
            $normalized = strtolower(trim($correct_answer !== '' ? $correct_answer : $correct_text));
            $correct_text = in_array($normalized, ['false', '0', 'f', 'no', 'tidak'], true) ? 'false' : 'true';
            $options_raw = '';
        } elseif ($question_type === 'true_false_matrix') {
            $correct_text = self::normalize_true_false_matrix_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
            if ($correct_text === '' || count(self::normalize_true_false_matrix_config($correct_text)) < 2) {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $options_raw = '';
        } elseif ($question_type === 'short_answer') {
            $correct_text = self::normalize_short_answer_payload((string) ($correct_text !== '' ? $correct_text : $correct_answer));
            if ($correct_text === '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $options_raw = '';
        } elseif ($question_type === 'ordering') {
            $options_raw = CBT_Admin_Questions_Import_Helper::build_ordering_options_raw_from_import($options_input);
            if ($options_raw === '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $correct_text = '';
        } elseif ($question_type === 'matching') {
            $matching_items = isset($row['matching_items']) && is_array($row['matching_items'])
                ? CBT_Admin_Questions_Helper::normalize_matching_items($row['matching_items'])
                : [];
            if (CBT_Admin_Questions_Helper::validate_matching_items($matching_items) !== '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $correct_text = CBT_Admin_Questions_Helper::build_matching_payload($matching_items);
            $options_raw = '';
        } elseif ($question_type === 'cloze_dropdown') {
            $cloze_blanks = isset($row['cloze_blanks']) && is_array($row['cloze_blanks'])
                ? CBT_Admin_Questions_Helper::normalize_cloze_dropdown_blanks($row['cloze_blanks'])
                : [];
            if (CBT_Admin_Questions_Helper::validate_cloze_dropdown_definition($question_text, $cloze_blanks) !== '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $correct_text = CBT_Admin_Questions_Helper::build_cloze_dropdown_payload($cloze_blanks);
            $options_raw = '';
        } elseif ($question_type === 'categorization') {
            $categorization_categories = isset($row['categorization_categories']) && is_array($row['categorization_categories'])
                ? CBT_Admin_Questions_Helper::normalize_categorization_categories($row['categorization_categories'])
                : [];
            $categorization_items = isset($row['categorization_items']) && is_array($row['categorization_items'])
                ? CBT_Admin_Questions_Helper::normalize_categorization_items($row['categorization_items'])
                : [];
            if (CBT_Admin_Questions_Helper::validate_categorization_definition($categorization_categories, $categorization_items) !== '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $correct_text = CBT_Admin_Questions_Helper::build_categorization_payload($categorization_categories, $categorization_items);
            $options_raw = '';
        } elseif ($question_type === 'table_completion') {
            $table_completion_definition = isset($row['table_completion']) && is_array($row['table_completion'])
                ? CBT_Admin_Questions_Helper::normalize_table_completion_definition($row['table_completion'])
                : [];
            if (CBT_Admin_Questions_Helper::validate_table_completion_definition($table_completion_definition) !== '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $correct_text = CBT_Admin_Questions_Helper::build_table_completion_payload($table_completion_definition);
            $options_raw = '';
        } elseif ($question_type === 'essay') {
            $correct_text = trim($correct_text !== '' ? $correct_text : $correct_answer);
            if ($correct_text === '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $options_raw = '';
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'cbt_questions',
            [
                'exam_id' => $exam_id,
                'question_text' => $question_text,
                'question_type' => $question_type,
                'points' => $points,
                'correct_text' => $correct_text !== '' ? $correct_text : null,
                'explanation' => $explanation !== '' ? $explanation : null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
        );
        if (!$inserted) {
            return [
                'status' => 'failed',
                'question_id' => 0,
            ];
        }

        $question_id = (int) $wpdb->insert_id;
        $options_to_insert = self::parse_options($options_raw);
        if ($question_type === 'matching') {
            $options_to_insert = array_map(static function (array $item): array {
                return [
                    'option_text' => (string) ($item['option_text'] ?? ''),
                    'is_correct' => 0,
                ];
            }, $matching_items);
        } elseif ($question_type === 'categorization') {
            $options_to_insert = array_map(static function (array $category): array {
                return [
                    'option_text' => (string) ($category['option_text'] ?? ''),
                    'is_correct' => 0,
                ];
            }, $categorization_categories);
        } elseif ($question_type === 'ordering') {
            foreach ($options_to_insert as $idx => $opt) {
                $options_to_insert[$idx]['is_correct'] = 0;
            }
        }
        if ($question_type === 'true_false' && empty($options_to_insert)) {
            $true_is_correct = (strtolower($correct_text) === 'true') ? 1 : 0;
            $options_to_insert = [
                ['option_text' => 'True', 'is_correct' => $true_is_correct],
                ['option_text' => 'False', 'is_correct' => $true_is_correct ? 0 : 1],
            ];
        }

        $inserted_option_ids = [];
        foreach ($options_to_insert as $idx => $opt) {
            $inserted_option = $wpdb->insert(
                $wpdb->prefix . 'cbt_options',
                [
                    'question_id' => $question_id,
                    'option_key' => chr(65 + $idx),
                    'option_text' => (string) ($opt['option_text'] ?? ''),
                    'is_correct' => (int) ($opt['is_correct'] ?? 0),
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%d', '%s']
            );
            if ($inserted_option !== false) {
                $inserted_option_ids[] = (int) $wpdb->insert_id;
            }
        }

        if ($question_type !== 'true_false_matrix') {
            $detail_context = ['ordered_option_ids' => $inserted_option_ids];
            if ($question_type === 'matching') {
                $matching_detail_items = [];
                foreach ($matching_items as $idx => $matching_item) {
                    $option_id = (int) ($inserted_option_ids[$idx] ?? 0);
                    if ($option_id <= 0) {
                        continue;
                    }
                    $matching_detail_items[] = [
                        'position' => (int) ($matching_item['position'] ?? ($idx + 1)),
                        'item_key' => (string) ($matching_item['item_key'] ?? ($idx + 1)),
                        'prompt_text' => (string) ($matching_item['prompt_text'] ?? ''),
                        'correct_option_id' => $option_id,
                    ];
                }
                $detail_context['matching_items'] = $matching_detail_items;
            }
            if ($question_type === 'cloze_dropdown') {
                $detail_context['cloze_blanks'] = $cloze_blanks;
            }
            if ($question_type === 'categorization') {
                $categorization_detail_items = [];
                foreach ($categorization_items as $idx => $categorization_item) {
                    $category_index = (int) ($categorization_item['correct_category_index'] ?? 0);
                    $option_id = $category_index > 0 ? (int) ($inserted_option_ids[$category_index - 1] ?? 0) : 0;
                    if ($option_id <= 0) {
                        continue;
                    }
                    $categorization_detail_items[] = [
                        'position' => (int) ($categorization_item['position'] ?? ($idx + 1)),
                        'item_key' => (string) ($categorization_item['item_key'] ?? ($idx + 1)),
                        'item_text' => (string) ($categorization_item['item_text'] ?? ''),
                        'correct_option_id' => $option_id,
                    ];
                }
                $detail_context['categorization_items'] = $categorization_detail_items;
            }
            if ($question_type === 'table_completion') {
                $detail_context['table_completion'] = $table_completion_definition;
            }
            self::save_question_type_detail($question_id, $question_type, $correct_text, $detail_context);
        }

        return [
            'status' => 'created',
            'question_id' => $question_id,
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function calculate_test_data_seed_processed_units(array $state): int
    {
        $preset = self::normalize_test_data_seed_preset((array) ($state['preset'] ?? []));
        $table_total = isset($state['tables']) && is_array($state['tables']) ? count((array) $state['tables']) : 0;
        $table_units = min($table_total, max(0, (int) ($state['table_index'] ?? 0)));

        $reset_user_total = max(0, (int) ($state['reset_user_total'] ?? 0));
        $reset_user_offset = max(0, (int) ($state['reset_user_offset'] ?? 0));
        $reset_user_units = $reset_user_total > 0
            ? min($reset_user_offset, $reset_user_total)
            : (!empty($state['reset_users_placeholder_done']) ? 1 : 0);

        $subject_units = min(max(0, (int) ($state['seed_subject_offset'] ?? 0)), (int) ($preset['subjects'] ?? 0));
        $seed_user_total = (int) ($preset['teachers'] ?? 0) + (int) ($preset['students'] ?? 0);
        $seed_user_units = min(max(0, (int) ($state['seed_user_offset'] ?? 0)), $seed_user_total);
        $bank_exam_units = min(max(0, (int) ($state['seed_bank_exam_offset'] ?? 0)), (int) ($preset['subjects'] ?? 0));
        $exam_units = min(max(0, (int) ($state['seed_exam_offset'] ?? 0)), (int) ($preset['exams'] ?? 0));
        $question_units = min(
            max(0, (int) ($state['seed_bank_question_offset'] ?? ($state['seed_question_offset'] ?? 0))),
            (int) ($preset['questions'] ?? 0)
        );
        $sync_exam_units = min(max(0, (int) ($state['sync_exam_offset'] ?? 0)), (int) ($preset['exams'] ?? 0));

        $processed = $table_units + $reset_user_units + $subject_units + $seed_user_units + $bank_exam_units + $exam_units + $question_units + $sync_exam_units;
        $max_before_finalize = max(0, (int) ($state['total_units'] ?? 1) - 1);
        if ($processed > $max_before_finalize) {
            $processed = $max_before_finalize;
        }

        return max(0, $processed);
    }

    /**
     * @param array<string,mixed> $exam_entry
     * @param array<int|string,array<string,mixed>> $exams
     * @param array<int|string,array<int,int|string>> $target_exam_source_question_ids
     * @param array<int|string,array<int,int|string>> $subject_source_question_ids
     * @return array<int,int>
     */
    private static function build_test_data_seed_sync_source_question_ids_for_exam(
        array $exam_entry,
        array $exams,
        array $target_exam_source_question_ids,
        array $subject_source_question_ids,
        array $question_type_source_question_ids = [],
        string $preset_key = 'small'
    ): array {
        $target_exam_id = (int) ($exam_entry['id'] ?? 0);
        $profile = sanitize_key((string) ($exam_entry['seed_profile'] ?? ''));
        $profile_question_type = sanitize_key((string) ($exam_entry['seed_profile_question_type'] ?? ''));
        $question_type_source_ids = [];
        foreach ($question_type_source_question_ids as $question_type => $ids) {
            $question_type = sanitize_key((string) $question_type);
            if ($question_type === '' || !is_array($ids)) {
                continue;
            }
            $question_type_source_ids[$question_type] = array_values(array_unique(array_filter(array_map('absint', $ids))));
        }

        if (str_starts_with($profile, 'type_all_') && $profile_question_type !== '') {
            return $question_type_source_ids[$profile_question_type] ?? [];
        }

        if ($profile === 'mixed_50') {
            $selected = [];
            foreach (self::get_test_data_seed_all_question_types() as $question_type) {
                $ids = $question_type_source_ids[$question_type] ?? [];
                if (empty($ids)) {
                    continue;
                }
                $limit = max(1, (int) floor(count($ids) / 2));
                $selected = array_merge($selected, array_slice($ids, 0, $limit));
            }

            return array_values(array_unique(array_filter(array_map('absint', $selected))));
        }

        if ($profile_question_type !== '' && isset($question_type_source_ids[$profile_question_type])) {
            return array_slice($question_type_source_ids[$profile_question_type], 0, min(20, count($question_type_source_ids[$profile_question_type])));
        }

        if (in_array($profile, ['runtime_fixture', 'import_preview_fixture'], true) && !empty($question_type_source_ids)) {
            $selected = [];
            foreach (self::get_test_data_seed_all_question_types() as $question_type) {
                $ids = $question_type_source_ids[$question_type] ?? [];
                if (empty($ids)) {
                    continue;
                }
                $selected = array_merge($selected, array_slice($ids, 0, min(4, count($ids))));
            }

            if (!empty($selected)) {
                return array_values(array_unique(array_filter(array_map('absint', $selected))));
            }
        }

        $fallback_source_ids = array_values(array_unique(array_filter(array_map(
            'absint',
            (array) ($target_exam_source_question_ids[$target_exam_id] ?? [])
        ))));
        $subject_id = (int) ($exam_entry['subject_id'] ?? 0);
        $subject_source_ids = $subject_id > 0
            ? array_values(array_unique(array_filter(array_map(
                'absint',
                (array) ($subject_source_question_ids[$subject_id] ?? [])
            ))))
            : [];

        if (empty($subject_source_ids)) {
            return $fallback_source_ids;
        }

        $target_count = max(1, (int) ceil(count($subject_source_ids) / 2));
        if (count($subject_source_ids) <= $target_count) {
            return $subject_source_ids;
        }

        $same_subject_exam_ids = [];
        foreach ($exams as $candidate_exam_entry) {
            if (!is_array($candidate_exam_entry) || (int) ($candidate_exam_entry['subject_id'] ?? 0) !== $subject_id) {
                continue;
            }

            $candidate_exam_id = (int) ($candidate_exam_entry['id'] ?? 0);
            if ($candidate_exam_id > 0) {
                $same_subject_exam_ids[] = $candidate_exam_id;
            }
        }

        $subject_exam_index = array_search($target_exam_id, $same_subject_exam_ids, true);
        if ($subject_exam_index === false) {
            $subject_exam_index = 0;
        }

        $subject_exam_total = max(1, count($same_subject_exam_ids));
        $subject_source_total = count($subject_source_ids);
        $start_offset = (int) floor(($subject_exam_index * $subject_source_total) / $subject_exam_total);
        $selected_source_ids = [];

        for ($offset = 0; $offset < $target_count; $offset++) {
            $selected_source_ids[] = (int) $subject_source_ids[($start_offset + $offset) % $subject_source_total];
        }

        return array_values(array_unique(array_filter($selected_source_ids)));
    }

    private static function normalize_maintenance_kkm_percentage(float $value): float
    {
        if (!is_finite($value)) {
            return 75.0;
        }

        return round(min(100.0, max(0.0, $value)), 2);
    }

    private static function build_user_import_lookup(array $rows, int $offset, int $target_end): array
    {
        return CBT_Admin_Users_Service::build_user_import_lookup($rows, $offset, $target_end);
    }

    private static function upsert_user_from_row(array $row, array &$import_lookup = []): string
    {
        return CBT_Admin_Users_Service::upsert_user_from_row($row, $import_lookup);
    }

    private static function get_supported_agama_options(): array
    {
        return CBT_Admin_Users_Service::get_supported_agama_options();
    }

    private static function get_supported_jenis_kelamin_options(): array
    {
        return CBT_Admin_Users_Service::get_supported_jenis_kelamin_options();
    }

    private static function split_target_kelas_csv($raw): array
    {
        return CBT_Admin_Exams_Service::split_target_kelas_csv($raw);
    }

    private static function normalize_target_kelas_csv($raw): string
    {
        return CBT_Admin_Exams_Service::normalize_target_kelas_csv($raw);
    }

    private static function normalize_true_false_matrix_payload(string $raw): string
    {
        return CBT_Admin_Questions_Helper::normalize_true_false_matrix_payload($raw);
    }

    private static function normalize_true_false_matrix_config(string $raw): array
    {
        return CBT_Admin_Questions_Helper::normalize_true_false_matrix_config($raw);
    }

    private static function normalize_short_answer_payload(string $raw): string
    {
        return CBT_Admin_Questions_Helper::normalize_short_answer_payload($raw);
    }

    private static function build_options_raw_from_import(string $options_input, string $correct_answer, string $question_type): string
    {
        return CBT_Admin_Questions_Import_Helper::build_options_raw_from_import($options_input, $correct_answer, $question_type);
    }

    private static function map_import_question_type(string $raw): string
    {
        return CBT_Admin_Questions_Import_Helper::map_import_question_type($raw);
    }

    private static function parse_options(string $options_raw): array
    {
        return CBT_Admin_Questions_Helper::parse_options($options_raw);
    }

    private static function save_question_type_detail(int $question_id, string $question_type, string $correct_text, array $context = []): void
    {
        CBT_Admin_Questions_Helper::save_question_type_detail($question_id, $question_type, $correct_text, $context);
    }

}
