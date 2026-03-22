<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Maintenance_Service
{
    private const USER_META_PLAIN_PASSWORD = 'cbt_plain_password';
    private const TEST_DATA_SEED_CONFIRM_PHRASE = 'GENERATE TEST DATA';
    private const TEST_DATA_SEED_DEFAULT_PASSWORD = 'Skills39';
    private const TEST_DATA_SEED_SPECIAL_USERNAME = 'coblax';
    private const TEST_DATA_SEED_SPECIAL_PASSWORD = '223611';
    private const LOAD_TEST_JOBS_OPTION = 'cbt_load_test_jobs';
    private const LOAD_TEST_RUNTIME_DIRECTORY = 'cbt-load-test';
    private const LOAD_TEST_MAX_JOB_HISTORY = 24;

    public static function can_manage_maintenance(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $seed_success_notice_summary = $notice !== ''
            ? self::parse_test_data_seed_completion_notice($notice)
            : null;

        $seed_presets = self::test_data_seed_presets();
        $selected_seed_preset = isset($query['cbt_seed_preset']) ? sanitize_key((string) wp_unslash((string) $query['cbt_seed_preset'])) : 'small';
        if (!isset($seed_presets[$selected_seed_preset])) {
            $selected_seed_preset = 'small';
        }

        $reset_progress_token = isset($query['cbt_reset_progress_token']) ? sanitize_key((string) wp_unslash((string) $query['cbt_reset_progress_token'])) : '';
        $reset_progress_state = null;
        $reset_progress_total = 0;
        $reset_progress_processed = 0;
        $reset_progress_deleted_users = 0;
        $reset_progress_failed_tables = 0;
        $reset_progress_percent = 0.0;
        $reset_progress_is_running = false;
        $reset_progress_phase_label = '';
        $reset_progress_continue_url = '';
        if ($reset_progress_token !== '') {
            $reset_progress_state = self::get_reset_progress_state_for_current_user($reset_progress_token);
            if (is_array($reset_progress_state)) {
                $reset_progress_total = max(1, isset($reset_progress_state['total_units']) ? (int) $reset_progress_state['total_units'] : 1);
                $reset_progress_processed = max(0, isset($reset_progress_state['processed_units']) ? (int) $reset_progress_state['processed_units'] : 0);
                if ($reset_progress_processed > $reset_progress_total) {
                    $reset_progress_processed = $reset_progress_total;
                }
                $reset_progress_deleted_users = max(0, isset($reset_progress_state['deleted_user_count']) ? (int) $reset_progress_state['deleted_user_count'] : 0);
                $reset_progress_failed_tables = count((array) ($reset_progress_state['failed_tables'] ?? []));
                $reset_phase = sanitize_key((string) ($reset_progress_state['phase'] ?? 'tables'));
                $phase_labels = [
                    'tables' => 'Mengosongkan tabel CBT',
                    'users' => 'Menghapus user CBT',
                    'finalize' => 'Finalisasi reset',
                ];
                $reset_progress_phase_label = $phase_labels[$reset_phase] ?? 'Memproses reset database';
                $reset_progress_percent = $reset_progress_total > 0
                    ? round(((float) $reset_progress_processed / (float) $reset_progress_total) * 100, 2)
                    : 0.0;
                $reset_progress_is_running = $reset_progress_processed < $reset_progress_total;
                $reset_progress_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_reset_database',
                        'cbt_reset_progress_token' => $reset_progress_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi reset database tidak ditemukan atau sudah berakhir. Silakan mulai ulang reset.';
            }
        }
        $reset_progress_status_tone = is_array($reset_progress_state)
            ? ($reset_progress_is_running ? 'running' : 'done')
            : 'idle';
        $reset_progress_status_label = is_array($reset_progress_state)
            ? ($reset_progress_is_running ? 'Sedang berjalan' : 'Selesai')
            : 'Siaga';
        $reset_progress_summary_label = is_array($reset_progress_state)
            ? ((string) $reset_progress_processed . ' / ' . (string) $reset_progress_total)
            : 'Belum ada reset aktif';
        $reset_progress_stage_preview = $reset_progress_phase_label !== '' ? $reset_progress_phase_label : 'Belum ada proses aktif';

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
                    admin_url('admin-post.php')
                );
                $seed_progress_preset = self::normalize_test_data_seed_preset((array) ($seed_progress_state['preset'] ?? []));
                $seed_progress_preset_label = (string) ($seed_progress_preset['label'] ?? $seed_progress_preset_label);
                $selected_seed_preset = sanitize_key((string) ($seed_progress_state['preset_key'] ?? $selected_seed_preset));
                if (!isset($seed_presets[$selected_seed_preset])) {
                    $selected_seed_preset = 'small';
                }
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
        $seed_question_type_labels = self::get_test_data_seed_question_type_labels();
        $seed_presets_view = [];
        foreach ($seed_presets as $preset_key => $preset_meta) {
            $question_type_counts = self::get_test_data_seed_question_type_counts(
                $preset_key,
                (int) ($preset_meta['questions'] ?? 0)
            );
            $preset_meta['question_type_counts'] = $question_type_counts;
            $preset_meta['question_type_summary'] = self::format_test_data_seed_question_type_summary($question_type_counts);
            $seed_presets_view[$preset_key] = $preset_meta;
        }
        $selected_seed_preset_data = $seed_presets_view[$selected_seed_preset];
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

        $load_test_runtime = self::get_load_test_runtime_snapshot();
        $load_test_student_pool = self::get_load_test_student_pool();
        $load_test_exam_catalog = self::get_load_test_exam_catalog();
        $load_test_profile_presets = self::get_load_test_profile_presets();
        $load_test_default_profile = self::normalize_load_test_profile([]);
        $load_test_jobs = self::sync_load_test_jobs();
        $load_test_running_count = 0;
        $load_test_latest_running_exam = '';
        foreach ($load_test_jobs as $load_test_job) {
            if (!in_array((string) ($load_test_job['status'] ?? ''), ['queued', 'running'], true)) {
                continue;
            }

            $load_test_running_count++;
            if ($load_test_latest_running_exam === '') {
                $load_test_latest_running_exam = sanitize_text_field((string) ($load_test_job['exam_title'] ?? ''));
            }
        }
        $load_test_default_base_url = self::get_load_test_base_url_default();

        $hero_live_value = $load_test_running_count > 0
            ? 'Load Test Aktif'
            : ($seed_progress_is_running
                ? 'Seeder Aktif'
                : ($reset_progress_is_running ? 'Reset Aktif' : 'Siaga'));
        $hero_stage_preview = $load_test_running_count > 0
            ? sprintf(
                '%d job k6 berjalan%s',
                $load_test_running_count,
                $load_test_latest_running_exam !== '' ? ' · ' . $load_test_latest_running_exam : ''
            )
            : ($seed_progress_is_running
                ? $seed_progress_stage_preview
                : ($reset_progress_is_running ? $reset_progress_stage_preview : 'Belum ada proses aktif'));

        $requested_maintenance_tab = isset($query['cbt_maintenance_tab'])
            ? sanitize_key((string) wp_unslash((string) $query['cbt_maintenance_tab']))
            : '';
        $allowed_maintenance_tabs = ['reset', 'seed', 'load'];
        $active_maintenance_tab = in_array($requested_maintenance_tab, $allowed_maintenance_tabs, true)
            ? $requested_maintenance_tab
            : '';
        if ($active_maintenance_tab === '') {
            if ($load_test_running_count > 0) {
                $active_maintenance_tab = 'load';
            } elseif ($seed_progress_token !== '' || is_array($seed_progress_state)) {
                $active_maintenance_tab = 'seed';
            } elseif ($reset_progress_token !== '' || is_array($reset_progress_state)) {
                $active_maintenance_tab = 'reset';
            } else {
                $active_maintenance_tab = 'reset';
            }
        }

        return [
            'notice' => $notice,
            'error' => $error,
            'seed_success_notice_summary' => $seed_success_notice_summary,
            'seed_presets' => $seed_presets,
            'selected_seed_preset' => $selected_seed_preset,
            'reset_progress_token' => $reset_progress_token,
            'reset_progress_state' => $reset_progress_state,
            'reset_progress_total' => $reset_progress_total,
            'reset_progress_processed' => $reset_progress_processed,
            'reset_progress_deleted_users' => $reset_progress_deleted_users,
            'reset_progress_failed_tables' => $reset_progress_failed_tables,
            'reset_progress_percent' => $reset_progress_percent,
            'reset_progress_is_running' => $reset_progress_is_running,
            'reset_progress_phase_label' => $reset_progress_phase_label,
            'reset_progress_continue_url' => $reset_progress_continue_url,
            'reset_progress_status_tone' => $reset_progress_status_tone,
            'reset_progress_status_label' => $reset_progress_status_label,
            'reset_progress_summary_label' => $reset_progress_summary_label,
            'reset_progress_stage_preview' => $reset_progress_stage_preview,
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
            'seed_question_type_labels' => $seed_question_type_labels,
            'seed_presets_json' => $seed_presets_json,
            'selected_seed_preset_data' => $selected_seed_preset_data,
            'selected_seed_question_type_counts' => $selected_seed_question_type_counts,
            'selected_seed_question_type_summary' => $selected_seed_question_type_summary,
            'load_test_runtime' => $load_test_runtime,
            'load_test_student_pool' => $load_test_student_pool,
            'load_test_exam_catalog' => $load_test_exam_catalog,
            'load_test_profile_presets' => $load_test_profile_presets,
            'load_test_default_profile' => $load_test_default_profile,
            'load_test_jobs' => $load_test_jobs,
            'load_test_running_count' => $load_test_running_count,
            'load_test_default_base_url' => $load_test_default_base_url,
            'hero_live_value' => $hero_live_value,
            'hero_stage_preview' => $hero_stage_preview,
            'active_maintenance_tab' => $active_maintenance_tab,
            'test_data_seed_confirm_phrase' => self::TEST_DATA_SEED_CONFIRM_PHRASE,
            'test_data_seed_default_password' => self::TEST_DATA_SEED_DEFAULT_PASSWORD,
            'test_data_seed_special_username' => self::TEST_DATA_SEED_SPECIAL_USERNAME,
            'test_data_seed_special_password' => self::TEST_DATA_SEED_SPECIAL_PASSWORD,
        ];
    }


    private static function prepare_runtime_for_bulk_user_import(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');
    }

    public static function handle_reset_database(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_reset_progress_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_reset_progress_token'])) : '';
        if ($token !== '') {
            self::continue_reset_database($token);
        }

        check_admin_referer('cbt_reset_database');

        $confirm_phrase = isset($_POST['confirm_phrase'])
            ? trim((string) sanitize_text_field(wp_unslash($_POST['confirm_phrase'])))
            : '';
        if ($confirm_phrase !== 'RESET CBT') {
            self::redirect_maintenance_page(null, 'Konfirmasi tidak valid. Ketik persis: RESET CBT', 'reset');
        }

        global $wpdb;
        $tables = self::cbt_data_tables($wpdb);
        $user_ids = self::collect_cbt_user_ids_for_reset();
        $token = strtolower((string) wp_generate_password(24, false, false));
        $total_units = count($tables) + max(1, count($user_ids)) + 1; // table reset + user delete + finalize
        $state = [
            'user_id' => get_current_user_id(),
            'started_at' => time(),
            'phase' => 'tables',
            'tables' => $tables,
            'table_index' => 0,
            'failed_tables' => [],
            'foreign_keys_disabled' => 0,
            'user_offset' => 0,
            'users_placeholder_done' => 0,
            'deleted_user_count' => 0,
            'total_units' => max(1, $total_units),
            'processed_units' => 0,
        ];
        $state_saved = set_transient(self::get_reset_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        $users_saved = set_transient(self::get_reset_progress_users_key($token), array_values($user_ids), 12 * HOUR_IN_SECONDS);
        if (!$state_saved || !$users_saved) {
            self::clear_reset_progress_transients($token);
            self::redirect_maintenance_page(null, 'Gagal menyiapkan sesi reset database. Coba ulang lagi.', 'reset');
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'cbt-maintenance',
                'cbt_reset_progress_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    private static function continue_reset_database(string $token): void
    {
        $state = self::get_reset_progress_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_reset_progress_transients($token);
            self::redirect_maintenance_page(null, 'Sesi reset database berakhir. Silakan mulai ulang reset.', 'reset');
        }

        $tables = isset($state['tables']) && is_array($state['tables']) ? array_values((array) $state['tables']) : [];
        $users = get_transient(self::get_reset_progress_users_key($token));
        if (!is_array($users)) {
            $users = [];
        }
        $users = array_values(array_map('intval', $users));

        $phase = sanitize_key((string) ($state['phase'] ?? 'tables'));
        $table_index = max(0, isset($state['table_index']) ? (int) $state['table_index'] : 0);
        $user_offset = max(0, isset($state['user_offset']) ? (int) $state['user_offset'] : 0);
        $users_placeholder_done = !empty($state['users_placeholder_done']) ? 1 : 0;
        $deleted_user_count = max(0, isset($state['deleted_user_count']) ? (int) $state['deleted_user_count'] : 0);
        $failed_tables = [];
        if (isset($state['failed_tables']) && is_array($state['failed_tables'])) {
            foreach ($state['failed_tables'] as $failed_table) {
                $failed_table = str_replace('`', '', (string) $failed_table);
                if ($failed_table !== '') {
                    $failed_tables[$failed_table] = $failed_table;
                }
            }
        }

        $table_total = count($tables);
        if ($table_index > $table_total) {
            $table_index = $table_total;
        }
        $user_total = count($users);
        if ($user_offset > $user_total) {
            $user_offset = $user_total;
        }
        $total_units = max(1, isset($state['total_units']) ? (int) $state['total_units'] : ($table_total + max(1, $user_total) + 1));

        $max_batch_seconds = self::get_reset_progress_max_batch_seconds();
        $table_batch_size = self::get_reset_progress_table_batch_size();
        $user_batch_size = self::get_reset_progress_user_batch_size();
        $batch_started_at = microtime(true);

        global $wpdb;

        if ($phase === 'tables') {
            if (empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
                $state['foreign_keys_disabled'] = 1;
            }

            $processed_tables_this_round = 0;
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
                $phase = 'users';
            }
        }

        if ($phase === 'users' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            if ($user_total <= 0) {
                $users_placeholder_done = 1;
                $phase = 'finalize';
            } else {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $target_offset = min($user_offset + $user_batch_size, $user_total);
                for ($index = $user_offset; $index < $target_offset; $index++) {
                    $user_id = isset($users[$index]) ? (int) $users[$index] : 0;
                    if ($user_id <= 0) {
                        continue;
                    }
                    $deleted = wp_delete_user($user_id);
                    if ($deleted) {
                        $deleted_user_count++;
                    }

                    if (($index - $user_offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                        $target_offset = $index + 1;
                        break;
                    }
                }
                $user_offset = $target_offset;
                if ($user_offset >= $user_total) {
                    $phase = 'finalize';
                }
            }
        }

        if ($phase === 'finalize' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            self::reset_cbt_global_token_options();
            CBT_UI_State::clear_all();
            CBT_Cache::reset_plugin_cache_state();
            self::clear_reset_progress_transients($token);

            if (!empty($failed_tables)) {
                self::redirect_maintenance_page(
                    null,
                    'Sebagian tabel gagal direset: ' . implode(', ', array_values($failed_tables))
                    . '. Data CBT termasuk Bank Soal mungkin belum sepenuhnya bersih. User CBT terhapus: ' . $deleted_user_count . '.',
                    'reset'
                );
            }

            $message = 'Data database CBT berhasil direset, termasuk exam, Bank Soal, question, attempt, dan hasil. User CBT terhapus: ' . $deleted_user_count . '.';
            self::redirect_maintenance_page($message, null, 'reset');
        }

        $user_progress_units = $user_total > 0
            ? min($user_offset, $user_total)
            : ($users_placeholder_done ? 1 : 0);
        $processed_units = $table_index + $user_progress_units;
        if ($processed_units > ($total_units - 1)) {
            $processed_units = $total_units - 1;
        }
        if ($processed_units < 0) {
            $processed_units = 0;
        }

        $state['phase'] = $phase;
        $state['table_index'] = $table_index;
        $state['user_offset'] = $user_offset;
        $state['users_placeholder_done'] = $users_placeholder_done;
        $state['deleted_user_count'] = $deleted_user_count;
        $state['failed_tables'] = array_values($failed_tables);
        $state['total_units'] = $total_units;
        $state['processed_units'] = $processed_units;

        $state_saved = set_transient(self::get_reset_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$state_saved) {
            if (!empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::clear_reset_progress_transients($token);
            self::redirect_maintenance_page(null, 'Gagal menyimpan progres reset database. Silakan mulai ulang reset.', 'reset');
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'cbt-maintenance',
                'cbt_reset_progress_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handle_generate_test_dataset(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_seed_progress_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_seed_progress_token'])) : '';
        if ($token !== '') {
            self::continue_generate_test_dataset($token);
        }

        check_admin_referer('cbt_generate_test_dataset');

        $preset_key = isset($_POST['preset']) ? sanitize_key((string) wp_unslash($_POST['preset'])) : '';
        $presets = self::test_data_seed_presets();
        if (!isset($presets[$preset_key])) {
            self::redirect_maintenance_page(null, 'Pilih preset dataset uji yang valid.', 'seed');
        }

        $confirm_phrase = isset($_POST['confirm_phrase'])
            ? trim((string) sanitize_text_field(wp_unslash($_POST['confirm_phrase'])))
            : '';
        if ($confirm_phrase !== self::TEST_DATA_SEED_CONFIRM_PHRASE) {
            self::redirect_maintenance_page(
                null,
                'Konfirmasi tidak valid. Ketik persis: ' . self::TEST_DATA_SEED_CONFIRM_PHRASE,
                'seed'
            );
        }

        global $wpdb;

        $preset = $presets[$preset_key];
        $tables = self::cbt_data_tables($wpdb);
        $reset_user_ids = self::collect_cbt_user_ids_for_reset();
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
            self::redirect_maintenance_page(null, 'Gagal menyiapkan sesi generate data uji. Coba ulang lagi.', 'seed');
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'cbt-maintenance',
                'cbt_seed_progress_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    private static function continue_generate_test_dataset(string $token): void
    {
        $state = self::get_seed_progress_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_seed_progress_transients($token);
            self::redirect_maintenance_page(null, 'Sesi generate data uji berakhir. Silakan mulai ulang generator.', 'seed');
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

        if ($phase === 'reset_tables') {
            if (empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
                $state['foreign_keys_disabled'] = 1;
            }

            $processed_tables_this_round = 0;
            $table_batch_size = self::get_reset_progress_table_batch_size();
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
                    self::redirect_maintenance_page(
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
                $target_offset = min($reset_user_offset + self::get_reset_progress_user_batch_size(), $reset_user_total);
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
                    self::redirect_maintenance_page(
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
                    self::redirect_maintenance_page(
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
                    self::redirect_maintenance_page(
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

            while ($seed_exam_offset < $exam_total && $processed_exams_this_round < $exam_batch_size) {
                $exam_entry = self::build_test_data_seed_exam_entry(
                    $seed_exam_offset,
                    $subjects,
                    $kelas_codes,
                    (string) ($state['preset_key'] ?? 'small'),
                    $creator_id
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
                    self::redirect_maintenance_page(
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
                    (string) ($state['preset_key'] ?? 'small')
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
                    if ($target_exam_id > 0 && $source_question_id > 0) {
                        if (!isset($target_exam_source_question_ids[$target_exam_id]) || !is_array($target_exam_source_question_ids[$target_exam_id])) {
                            $target_exam_source_question_ids[$target_exam_id] = [];
                        }
                        $target_exam_source_question_ids[$target_exam_id][] = $source_question_id;
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
                    self::redirect_maintenance_page(
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
                $source_question_ids = array_values(array_unique(array_filter(array_map(
                    'absint',
                    (array) ($target_exam_source_question_ids[$target_exam_id] ?? [])
                ))));

                if ($target_exam_id > 0) {
                    if (!empty($source_question_ids)) {
                        $sync_result = CBT_Admin_Exams_Service::sync_exam_questions_from_sources_for_internal_use(
                            $target_exam_id,
                            $source_question_ids,
                            $creator_id
                        );
                        if (is_wp_error($sync_result)) {
                            self::clear_seed_progress_transients($token);
                            self::redirect_maintenance_page(
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

            self::reset_cbt_global_token_options();
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

            self::redirect_maintenance_page($message, null, 'seed');
        }

        $state_saved = set_transient(self::get_seed_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$state_saved) {
            if (!empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::clear_seed_progress_transients($token);
            self::redirect_maintenance_page(null, 'Gagal menyimpan progres generator data uji. Silakan mulai ulang.', 'seed');
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'cbt-maintenance',
                'cbt_seed_progress_token' => $token,
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    private static function redirect_maintenance_page(?string $message = null, ?string $error = null, ?string $tab = null): void
    {
        $args = ['page' => 'cbt-maintenance'];
        if ($tab === null || $tab === '') {
            $requested_tab = isset($_REQUEST['cbt_maintenance_tab'])
                ? sanitize_key((string) wp_unslash($_REQUEST['cbt_maintenance_tab']))
                : '';
            if (in_array($requested_tab, ['reset', 'seed', 'load'], true)) {
                $tab = $requested_tab;
            }
        }
        if ($tab !== null && $tab !== '' && in_array($tab, ['reset', 'seed', 'load'], true)) {
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
     * @return array<string,array{label:string,subjects:int,exams:int,questions:int,students:int,teachers:int,classes:int,rooms:int,include_true_false_matrix:bool}>
     */
    private static function test_data_seed_presets(): array
    {
        return [
            'small' => [
                'label' => 'Small',
                'subjects' => 5,
                'exams' => 10,
                'questions' => 200,
                'students' => 60,
                'teachers' => 6,
                'classes' => 6,
                'rooms' => 3,
                'include_true_false_matrix' => false,
            ],
            'medium' => [
                'label' => 'Medium',
                'subjects' => 10,
                'exams' => 30,
                'questions' => 900,
                'students' => 300,
                'teachers' => 18,
                'classes' => 12,
                'rooms' => 6,
                'include_true_false_matrix' => true,
            ],
            'large' => [
                'label' => 'Large',
                'subjects' => 20,
                'exams' => 80,
                'questions' => 3200,
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
     * @return array{label:string,subjects:int,exams:int,questions:int,students:int,teachers:int,classes:int,rooms:int,include_true_false_matrix:bool}
     */
    private static function normalize_test_data_seed_preset(array $raw): array
    {
        $defaults = self::test_data_seed_presets()['small'];

        return [
            'label' => isset($raw['label']) ? sanitize_text_field((string) $raw['label']) : (string) $defaults['label'],
            'subjects' => max(0, isset($raw['subjects']) ? (int) $raw['subjects'] : (int) $defaults['subjects']),
            'exams' => max(0, isset($raw['exams']) ? (int) $raw['exams'] : (int) $defaults['exams']),
            'questions' => max(0, isset($raw['questions']) ? (int) $raw['questions'] : (int) $defaults['questions']),
            'students' => max(0, isset($raw['students']) ? (int) $raw['students'] : (int) $defaults['students']),
            'teachers' => max(0, isset($raw['teachers']) ? (int) $raw['teachers'] : (int) $defaults['teachers']),
            'classes' => max(0, isset($raw['classes']) ? (int) $raw['classes'] : (int) $defaults['classes']),
            'rooms' => max(0, isset($raw['rooms']) ? (int) $raw['rooms'] : (int) $defaults['rooms']),
            'include_true_false_matrix' => !empty($raw['include_true_false_matrix']),
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
        ];
    }

    /**
     * @return array<string,int>
     */
    private static function get_test_data_seed_question_type_counts(string $preset_key, int $total_questions): array
    {
        $counts = [];
        $cycle = self::test_data_seed_question_type_cycle($preset_key);
        $total_questions = max(0, $total_questions);
        if ($total_questions <= 0 || empty($cycle)) {
            return $counts;
        }

        $cycle_size = count($cycle);
        for ($index = 0; $index < $total_questions; $index++) {
            $question_type = (string) $cycle[$index % $cycle_size];
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
    private static function parse_test_data_seed_completion_notice(string $message): ?array
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
     * @return array{name:string,code:string,description:string}
     */
    private static function build_test_data_seed_subject_entry(int $offset): array
    {
        $number = $offset + 1;
        $label = str_pad((string) $number, 2, '0', STR_PAD_LEFT);

        return [
            'name' => 'TEST Subject ' . $label,
            'code' => 'TST' . $label,
            'description' => 'Dataset uji otomatis untuk subject ' . $label . '.',
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
        if ($limit <= 0 || $offset >= $total) {
            return $rows;
        }

        $end = min($offset + $limit, $total);
        for ($index = $offset; $index < $end; $index++) {
            $agama = !empty($agama_options)
                ? (string) $agama_options[$index % count($agama_options)]
                : '';

            if ($index < $teacher_total) {
                $number = $index + 1;
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
                    'foto' => '',
                    '__seed_kind' => 'teacher',
                ];
                continue;
            }

            $number = ($index - $teacher_total) + 1;
            if ($number === 1) {
                $rows[] = self::build_test_data_seed_special_student_row($kelas_codes, $ruang_codes, $agama_options);
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
     * @param string[] $kelas_codes
     * @param string[] $ruang_codes
     * @param string[] $agama_options
     * @return array<string,string>
     */
    private static function build_test_data_seed_special_student_row(array $kelas_codes, array $ruang_codes, array $agama_options): array
    {
        $agama = in_array('Islam', $agama_options, true)
            ? 'Islam'
            : (!empty($agama_options) ? (string) reset($agama_options) : '');
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
            'foto' => '',
            '__seed_kind' => 'student',
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
        int $creator_id
    ): array {
        $subject_total = count($subjects);
        $subject_entry = $subject_total > 0
            ? (array) $subjects[$offset % $subject_total]
            : [];
        $subject_id = (int) ($subject_entry['id'] ?? 0);
        $subject_name = (string) ($subject_entry['name'] ?? 'TEST Subject');
        $exam_number = $offset + 1;

        $target_kelas = [];
        $kelas_total = count($kelas_codes);
        if ($kelas_total > 0) {
            $span = min($kelas_total, (($offset % 3) + 1));
            $start = $offset % $kelas_total;
            for ($idx = 0; $idx < $span; $idx++) {
                $target_kelas[] = (string) $kelas_codes[($start + $idx) % $kelas_total];
            }
            $target_kelas = array_values(array_unique($target_kelas));
        }
        $special_test_kelas = self::get_test_data_seed_special_student_kelas_code($kelas_codes);
        if ($special_test_kelas !== '') {
            array_unshift($target_kelas, $special_test_kelas);
            $target_kelas = array_values(array_unique($target_kelas));
        }

        $status_slot = $offset % 10;
        if ($status_slot < 5) {
            $status = 'published';
        } elseif ($status_slot < 8) {
            $status = 'draft';
        } else {
            $status = 'closed';
        }

        $schedule = self::build_test_data_seed_exam_schedule($status, $offset);
        $duration_cycle = [45, 60, 75, 90, 120];
        $duration = $duration_cycle[$offset % count($duration_cycle)];
        $kkm_cycle = [65.0, 70.0, 75.0, 80.0, 85.0];
        $kkm_percentage = $kkm_cycle[$offset % count($kkm_cycle)];

        return [
            'subject_id' => $subject_id,
            'subject_name' => $subject_name,
            'title' => sprintf('TEST Exam %03d - %s', $exam_number, $subject_name),
            'description' => sprintf(
                'Dataset uji otomatis preset %s untuk %s. Target kelas: %s.',
                ucfirst($preset_key),
                $subject_name,
                !empty($target_kelas) ? implode(', ', $target_kelas) : 'Semua kelas test'
            ),
            'target_kelas' => implode(',', $target_kelas),
            'duration_minutes' => $duration,
            'kkm_percentage' => $kkm_percentage,
            'total_questions' => 0,
            'randomize_questions' => ($offset % 2 === 0) ? 1 : 0,
            'status' => $status,
            'starts_at' => $schedule['starts_at'],
            'ends_at' => $schedule['ends_at'],
            'created_by' => $creator_id,
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
                ['%d', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%s', '%s', '%s', '%d', '%s'],
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
            ['%d', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
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
        if ($preset_key === 'medium' || $preset_key === 'large') {
            return [
                'multiple_choice',
                'multiple_choice',
                'multiple_choice',
                'multiple_choice',
                'multiple_answer',
                'multiple_answer',
                'true_false',
                'true_false_matrix',
                'short_answer',
                'essay',
            ];
        }

        return [
            'multiple_choice',
            'multiple_choice',
            'multiple_choice',
            'multiple_choice',
            'multiple_choice',
            'multiple_answer',
            'multiple_answer',
            'true_false',
            'short_answer',
            'essay',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $exams
     * @param array<int|string,array<string,mixed>> $bank_exams
     * @return array<string,mixed>
     */
    private static function build_test_data_seed_bank_question_row(int $offset, array $exams, array $bank_exams, string $preset_key): array
    {
        $exam_count = count($exams);
        $exam_entry = $exam_count > 0 ? (array) $exams[$offset % $exam_count] : [];
        $target_exam_id = (int) ($exam_entry['id'] ?? 0);
        $subject_id = (int) ($exam_entry['subject_id'] ?? 0);
        $bank_exam_entry = $subject_id > 0 && isset($bank_exams[$subject_id]) && is_array($bank_exams[$subject_id])
            ? (array) $bank_exams[$subject_id]
            : [];
        $exam_id = (int) ($bank_exam_entry['id'] ?? 0);
        $subject_name = sanitize_text_field((string) ($exam_entry['subject_name'] ?? ($bank_exam_entry['subject_name'] ?? 'TEST Subject')));
        $question_number = $offset + 1;
        $cycle = self::test_data_seed_question_type_cycle($preset_key);
        $exam_question_index = $exam_count > 0 ? intdiv($offset, $exam_count) : $offset;
        $question_type = (string) $cycle[$exam_question_index % max(1, count($cycle))];

        switch ($question_type) {
            case 'multiple_answer':
                $base = ($question_number % 12) + 2;
                return [
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
                    'options' => implode('||', [
                        (string) ($base * 2),
                        (string) (($base * 2) + 1),
                        (string) ($base * 3),
                        (string) (($base * 3) + 1),
                    ]),
                    'correct_answer' => 'A,C',
                    'correct_text' => '',
                    'explanation' => 'Kelipatan yang benar adalah dua nilai yang habis dibagi tanpa sisa.',
                ];

            case 'true_false':
                $left = ($question_number % 21) + 9;
                $right = ($question_number % 8) + 3;
                $is_true = ($question_number % 2) === 0;
                $claim = $is_true ? ($left + $right) : ($left + $right + 1);
                return [
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

            case 'true_false_matrix':
                $base = ($question_number % 17) + 6;
                return [
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
                    'correct_text' => implode("\n", [
                        'Bilangan ' . $base . ' adalah bilangan positif|true',
                        'Hasil ' . $base . ' + 1 sama dengan ' . $base . '|false',
                        'Hasil ' . $base . ' x 2 sama dengan ' . ($base * 2) . '|true',
                    ]),
                    'explanation' => 'Setiap baris dinilai terpisah sebagai benar atau salah.',
                ];

            case 'short_answer':
                $left = ($question_number % 14) + 6;
                $right = ($question_number % 9) + 4;
                return [
                    'exam_id' => $exam_id,
                    'target_exam_id' => $target_exam_id,
                    'question_type' => 'short_answer',
                    'question_text' => sprintf(
                        'Soal %03d (%s). Isi hasil dari %d x %d pada jawaban singkat.',
                        $question_number,
                        $subject_name,
                        $left,
                        $right
                    ),
                    'points' => 1.25,
                    'options' => '',
                    'correct_answer' => '',
                    'correct_text' => (string) ($left * $right),
                    'explanation' => 'Jawaban singkat harus sama persis dengan hasil perkalian yang benar.',
                ];

            case 'essay':
                return [
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

            case 'multiple_choice':
            default:
                $left = ($question_number % 37) + 13;
                $right = ($question_number % 10) + 3;
                $correct = $left + $right;
                return [
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
                    'options' => implode('||', [
                        (string) ($correct + 2),
                        (string) $correct,
                        (string) max(1, $correct - 1),
                        (string) ($correct + 4),
                    ]),
                    'correct_answer' => 'B',
                    'correct_text' => '',
                    'explanation' => 'Jawaban benar adalah hasil penjumlahan kedua bilangan.',
                ];
        }
    }

    /**
     * @return array{status:string,question_id:int}
     */
    private static function insert_test_data_seed_question(array $row): array
    {
        global $wpdb;

        $question_type = self::map_import_question_type((string) ($row['question_type'] ?? ''));
        $question_text = trim(wp_kses_post((string) ($row['question_text'] ?? '')));
        $explanation = trim(wp_kses_post((string) ($row['explanation'] ?? '')));
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
        $correct_answer = (string) ($row['correct_answer'] ?? '');
        $correct_text = (string) ($row['correct_text'] ?? '');
        $options_raw = '';

        if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
            $built = self::build_options_raw_from_import($options_input, $correct_answer, $question_type);
            if ($built === '') {
                return [
                    'status' => 'failed',
                    'question_id' => 0,
                ];
            }
            $options_raw = $built;
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
        if ($question_type === 'true_false' && empty($options_to_insert)) {
            $true_is_correct = (strtolower($correct_text) === 'true') ? 1 : 0;
            $options_to_insert = [
                ['option_text' => 'True', 'is_correct' => $true_is_correct],
                ['option_text' => 'False', 'is_correct' => $true_is_correct ? 0 : 1],
            ];
        }

        foreach ($options_to_insert as $idx => $opt) {
            $wpdb->insert(
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
        }

        if ($question_type !== 'true_false_matrix') {
            self::save_question_type_detail($question_id, $question_type, $correct_text);
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

    private static function cbt_data_tables(wpdb $wpdb): array
    {
        $prefix = $wpdb->prefix;

        return [
            $prefix . 'cbt_answers',
            $prefix . 'cbt_attempts',
            $prefix . 'cbt_security_logs',
            $prefix . 'cbt_options',
            $prefix . 'cbt_question_essay',
            $prefix . 'cbt_question_short_answer',
            $prefix . 'cbt_question_true_false',
            $prefix . 'cbt_question_multiple_answer',
            $prefix . 'cbt_question_multiple_choice',
            $prefix . 'cbt_questions',
            $prefix . 'cbt_exams',
            $prefix . 'cbt_subjects',
        ];
    }

    private static function reset_cbt_global_token_options(): void
    {
        delete_option('cbt_global_exam_token_value');
        delete_option('cbt_global_exam_token_generated_at');
        delete_option('cbt_global_exam_token_refresh_minutes');
        delete_option('cbt_global_exam_token_frontend_auto_apply');
        delete_option(CBT_Admin_Branding_Settings::option_key());
        delete_transient('cbt_exam_priority_window_until');
    }

    /**
     * @return int[]
     */
    private static function collect_cbt_user_ids_for_reset(): array
    {
        $roles_to_purge = ['administrator', 'admin_cbt', 'guru_cbt', 'editor', 'teacher', 'siswa_cbt', 'subscriber', 'student'];
        $user_ids = [];

        foreach ($roles_to_purge as $role) {
            $ids = get_users([
                'role' => $role,
                'fields' => 'ids',
            ]);
            if (!is_array($ids)) {
                continue;
            }

            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $user_ids[$id] = $id;
                }
            }
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && isset($user_ids[$current_user_id])) {
            unset($user_ids[$current_user_id]);
        }

        return array_values($user_ids);
    }

    private static function delete_cbt_users_for_reset(): int
    {
        $user_ids = self::collect_cbt_user_ids_for_reset();
        if (empty($user_ids)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $deleted_count = 0;
        foreach ($user_ids as $user_id) {
            $deleted = wp_delete_user((int) $user_id);
            if ($deleted) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * @return string[]
     */
    private static function get_load_test_profile_presets(): array
    {
        return [
            'smoke_50' => [
                'label' => 'Smoke 50',
                'vus' => 50,
                'iterations' => 1,
                'questions_per_user' => 20,
                'session_start_spread_ms' => 4000,
                'post_start_spread_ms' => 1000,
                'submit_mode' => 'all',
                'enable_batch_submit' => 1,
                'max_duration' => '20m',
            ],
            'load_200' => [
                'label' => 'Load 200',
                'vus' => 200,
                'iterations' => 1,
                'questions_per_user' => 40,
                'session_start_spread_ms' => 10000,
                'post_start_spread_ms' => 3000,
                'submit_mode' => 'all',
                'enable_batch_submit' => 1,
                'max_duration' => '30m',
            ],
            'load_500' => [
                'label' => 'Load 500',
                'vus' => 500,
                'iterations' => 1,
                'questions_per_user' => 60,
                'session_start_spread_ms' => 30000,
                'post_start_spread_ms' => 10000,
                'submit_mode' => 'all',
                'enable_batch_submit' => 1,
                'max_duration' => '40m',
            ],
            'load_1000' => [
                'label' => 'Load 1000',
                'vus' => 1000,
                'iterations' => 1,
                'questions_per_user' => 80,
                'session_start_spread_ms' => 90000,
                'post_start_spread_ms' => 30000,
                'submit_mode' => 'all',
                'enable_batch_submit' => 1,
                'max_duration' => '45m',
            ],
        ];
    }

    private static function get_load_test_default_profile_key(): string
    {
        return 'smoke_50';
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,int|string>
     */
    private static function normalize_load_test_profile(array $request): array
    {
        $presets = self::get_load_test_profile_presets();
        $profile_key = isset($request['profile_preset'])
            ? sanitize_key((string) wp_unslash($request['profile_preset']))
            : self::get_load_test_default_profile_key();
        if (!isset($presets[$profile_key])) {
            $profile_key = self::get_load_test_default_profile_key();
        }

        $base = $presets[$profile_key];
        $submit_mode = isset($request['submit_mode'])
            ? sanitize_key((string) wp_unslash($request['submit_mode']))
            : (string) ($base['submit_mode'] ?? 'all');
        if (!in_array($submit_mode, ['all', 'none'], true)) {
            $submit_mode = (string) ($base['submit_mode'] ?? 'all');
        }

        $enable_batch_submit = isset($request['enable_batch_submit'])
            ? 1
            : (int) ($base['enable_batch_submit'] ?? 1);

        return [
            'profile_preset' => $profile_key,
            'profile_label' => (string) ($base['label'] ?? ucfirst(str_replace('_', ' ', $profile_key))),
            'vus' => max(1, min(5000, isset($request['vus']) ? absint(wp_unslash($request['vus'])) : (int) ($base['vus'] ?? 50))),
            'iterations' => max(1, min(100, isset($request['iterations']) ? absint(wp_unslash($request['iterations'])) : (int) ($base['iterations'] ?? 1))),
            'questions_per_user' => max(0, min(500, isset($request['questions_per_user']) ? absint(wp_unslash($request['questions_per_user'])) : (int) ($base['questions_per_user'] ?? 0))),
            'session_start_spread_ms' => max(0, min(600000, isset($request['session_start_spread_ms']) ? absint(wp_unslash($request['session_start_spread_ms'])) : (int) ($base['session_start_spread_ms'] ?? 0))),
            'post_start_spread_ms' => max(0, min(600000, isset($request['post_start_spread_ms']) ? absint(wp_unslash($request['post_start_spread_ms'])) : (int) ($base['post_start_spread_ms'] ?? 0))),
            'submit_mode' => $submit_mode,
            'enable_batch_submit' => $enable_batch_submit,
            'max_duration' => sanitize_text_field((string) ($base['max_duration'] ?? '45m')),
        ];
    }

    private static function get_load_test_base_url_default(): string
    {
        return untrailingslashit(home_url('/'));
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function normalize_load_test_base_url(array $request): string
    {
        $raw = isset($request['base_url'])
            ? trim((string) wp_unslash($request['base_url']))
            : self::get_load_test_base_url_default();
        $sanitized = untrailingslashit(esc_url_raw($raw));
        if ($sanitized === '' || !preg_match('#^https?://#i', $sanitized)) {
            return self::get_load_test_base_url_default();
        }

        return $sanitized;
    }

    private static function normalize_load_test_token_override(string $token): string
    {
        return CBT_Auth::normalize_exam_token_input($token);
    }

    /**
     * @return array{rows:array<int,array<string,string>>,total_count:int,valid_count:int,missing_password_count:int}
     */
    private static function get_load_test_student_pool(): array
    {
        $users = get_users([
            'role__in' => ['siswa_cbt', 'subscriber', 'student'],
            'orderby' => 'login',
            'order' => 'ASC',
            'number' => -1,
        ]);

        $rows = [];
        $total_count = 0;
        $missing_password_count = 0;

        foreach ((array) $users as $user) {
            if (!($user instanceof WP_User)) {
                continue;
            }

            $total_count++;
            $plain_password = trim((string) get_user_meta($user->ID, self::USER_META_PLAIN_PASSWORD, true));
            if ($plain_password === '') {
                $missing_password_count++;
                continue;
            }

            $nisn = preg_replace('/\D+/', '', (string) get_user_meta($user->ID, 'nisn', true));
            $email = sanitize_email((string) $user->user_email);
            if (!is_email($email) && $nisn !== '') {
                $email = sanitize_email($nisn . '@student.sch.id');
            }
            if (!is_email($email)) {
                $email = sanitize_email((string) $user->user_login . '@example.local');
            }

            $rows[] = [
                'name' => sanitize_text_field((string) $user->display_name),
                'email' => $email,
                'nisn' => $nisn,
                'username' => sanitize_user((string) $user->user_login, true),
                'password' => $plain_password,
                'role' => 'siswa',
                'kode_kelas' => sanitize_text_field((string) get_user_meta($user->ID, 'kode_kelas', true)),
                'kode_ruang' => sanitize_text_field((string) get_user_meta($user->ID, 'kode_ruang', true)),
                'agama' => sanitize_text_field((string) get_user_meta($user->ID, 'agama', true)),
                'foto' => esc_url_raw((string) get_user_meta($user->ID, 'foto', true)),
                'identifier' => sanitize_user((string) $user->user_login, true),
            ];
        }

        return [
            'rows' => $rows,
            'total_count' => $total_count,
            'valid_count' => count($rows),
            'missing_password_count' => $missing_password_count,
        ];
    }

    /**
     * @return array{shell_available:bool,exec_available:bool,proc_open_available:bool,shell_exec_available:bool,k6_path:string,k6_install_mode:string,runner_home:string,runner_home_supported:bool,runner_home_detected:string,base_url:string,runtime_root:string,runtime_root_exists:bool,runtime_root_writable:bool,global_token_meta:array<string,int|string>}
     */
    private static function get_load_test_runtime_snapshot(): array
    {
        $exec_available = function_exists('exec');
        $proc_open_available = function_exists('proc_open');
        $shell_exec_available = function_exists('shell_exec');
        $shell_available = $exec_available || $proc_open_available || $shell_exec_available;
        $k6_path = self::detect_load_test_k6_path();
        $k6_install_mode = 'missing';
        if ($k6_path !== '') {
            $k6_install_mode = (strpos($k6_path, '/snap/') === 0) ? 'snap' : 'native';
        }
        $runner_home_meta = self::get_load_test_runner_home_meta($k6_path);

        $upload = wp_upload_dir();
        $runtime_root = '';
        $runtime_root_exists = false;
        $runtime_root_writable = false;
        if (is_array($upload) && empty($upload['error']) && !empty($upload['basedir'])) {
            $runtime_root = trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY;
            $runtime_root_exists = is_dir($runtime_root);
            $runtime_root_writable = $runtime_root_exists
                ? is_writable($runtime_root)
                : is_writable((string) $upload['basedir']);
        }

        return [
            'shell_available' => $shell_available,
            'exec_available' => $exec_available,
            'proc_open_available' => $proc_open_available,
            'shell_exec_available' => $shell_exec_available,
            'k6_path' => $k6_path,
            'k6_install_mode' => $k6_install_mode,
            'runner_home' => (string) ($runner_home_meta['path'] ?? ''),
            'runner_home_supported' => !empty($runner_home_meta['supported']),
            'runner_home_detected' => (string) ($runner_home_meta['detected'] ?? ''),
            'base_url' => self::get_load_test_base_url_default(),
            'runtime_root' => $runtime_root,
            'runtime_root_exists' => $runtime_root_exists,
            'runtime_root_writable' => $runtime_root_writable,
            'global_token_meta' => CBT_Auth::get_global_exam_token(true),
        ];
    }

    private static function detect_load_test_k6_path(): string
    {
        $native_candidates = [
            '/usr/local/bin/k6',
            '/usr/bin/k6',
        ];
        $snap_candidates = [
            '/snap/bin/k6',
        ];
        if (function_exists('shell_exec')) {
            $command_path = trim((string) shell_exec('command -v k6 2>/dev/null'));
            if ($command_path !== '') {
                if (strpos($command_path, '/snap/') === 0) {
                    $snap_candidates[] = $command_path;
                } else {
                    $native_candidates[] = $command_path;
                }
            }
        }

        $candidates = array_values(array_unique(array_merge($native_candidates, $snap_candidates)));

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array{path:string,supported:bool,detected:string}
     */
    private static function get_load_test_runner_home_meta(string $k6_path): array
    {
        $detected = [];

        $env_home = trim((string) getenv('HOME'));
        if ($env_home !== '') {
            $detected[] = $env_home;
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid(posix_geteuid());
            if (is_array($pw) && !empty($pw['dir'])) {
                $detected[] = (string) $pw['dir'];
            }
        }

        $detected = array_values(array_unique(array_filter(array_map(static function ($path): string {
            return wp_normalize_path((string) $path);
        }, $detected))));

        $is_snap = strpos($k6_path, '/snap/') === 0;
        if ($is_snap) {
            foreach ($detected as $path) {
                if (strpos($path, '/home/') === 0) {
                    return [
                        'path' => $path,
                        'supported' => true,
                        'detected' => $path,
                    ];
                }
            }

            return [
                'path' => '',
                'supported' => false,
                'detected' => !empty($detected) ? (string) $detected[0] : '',
            ];
        }

        return [
            'path' => !empty($detected) ? (string) $detected[0] : '',
            'supported' => true,
            'detected' => !empty($detected) ? (string) $detected[0] : '',
        ];
    }

    /**
     * @return array{all:array<int,array<string,mixed>>,eligible:array<int,array<string,mixed>>,invalid:array<int,array<string,mixed>>}
     */
    private static function get_load_test_exam_catalog(): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id,
                        e.title,
                        e.status,
                        e.starts_at,
                        e.ends_at,
                        e.duration_minutes,
                        e.kkm_percentage,
                        e.target_kelas,
                        s.name AS subject_name,
                        COUNT(q.id) AS question_count
                 FROM {$exam_table} e
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                 LEFT JOIN {$question_table} q
                   ON q.exam_id = e.id
                  AND COALESCE(q.is_active, 1) = 1
                 WHERE e.title NOT LIKE %s
                 GROUP BY e.id, e.title, e.status, e.starts_at, e.ends_at, e.duration_minutes, e.kkm_percentage, e.target_kelas, s.name
                 ORDER BY
                     CASE WHEN e.starts_at IS NULL THEN 1 ELSE 0 END ASC,
                     e.starts_at ASC,
                     e.id ASC",
                'Bank Soal - %'
            ),
            ARRAY_A
        );

        $catalog = [
            'all' => [],
            'eligible' => [],
            'invalid' => [],
        ];
        foreach ((array) $rows as $row) {
            $exam = self::normalize_load_test_exam_catalog_row((array) $row);
            $catalog['all'][] = $exam;
            if (!empty($exam['eligible'])) {
                $catalog['eligible'][] = $exam;
            } else {
                $catalog['invalid'][] = $exam;
            }
        }

        return $catalog;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normalize_load_test_exam_catalog_row(array $row): array
    {
        $status = sanitize_key((string) ($row['status'] ?? 'draft'));
        $starts_at = trim((string) ($row['starts_at'] ?? ''));
        $ends_at = trim((string) ($row['ends_at'] ?? ''));
        $question_count = max(0, (int) ($row['question_count'] ?? 0));
        $now = current_time('mysql');
        $within_schedule = (
            ($starts_at === '' || $starts_at <= $now) &&
            ($ends_at === '' || $ends_at >= $now)
        );

        $reasons = [];
        if ($status !== 'published') {
            $reasons[] = 'Status bukan published';
        }
        if ($starts_at !== '' && $starts_at > $now) {
            $reasons[] = 'Jadwal belum mulai';
        }
        if ($ends_at !== '' && $ends_at < $now) {
            $reasons[] = 'Jadwal sudah berakhir';
        }
        if ($question_count <= 0) {
            $reasons[] = 'Belum ada soal aktif';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => sanitize_text_field((string) ($row['title'] ?? 'Exam')),
            'subject_name' => sanitize_text_field((string) ($row['subject_name'] ?? 'Tanpa Subject')),
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'duration_minutes' => max(1, (int) ($row['duration_minutes'] ?? 0)),
            'kkm_percentage' => self::normalize_maintenance_kkm_percentage((float) ($row['kkm_percentage'] ?? 75.0)),
            'target_kelas' => self::normalize_target_kelas_csv((string) ($row['target_kelas'] ?? '')),
            'target_kelas_list' => self::split_target_kelas_csv((string) ($row['target_kelas'] ?? '')),
            'question_count' => $question_count,
            'within_schedule' => $within_schedule,
            'eligible' => empty($reasons),
            'reject_reasons' => $reasons,
            'schedule_label' => self::format_load_test_exam_schedule_label($starts_at, $ends_at),
        ];
    }

    private static function format_load_test_exam_schedule_label(string $starts_at, string $ends_at): string
    {
        $parts = [];
        if ($starts_at !== '') {
            $parts[] = 'Mulai ' . $starts_at;
        }
        if ($ends_at !== '') {
            $parts[] = 'Selesai ' . $ends_at;
        }

        return !empty($parts) ? implode(' | ', $parts) : 'Tanpa batas jadwal';
    }

    private static function normalize_maintenance_kkm_percentage(float $value): float
    {
        if (!is_finite($value)) {
            return 75.0;
        }

        return round(min(100.0, max(0.0, $value)), 2);
    }

    private static function get_load_test_jobs_option_map(): array
    {
        $raw = get_option(self::LOAD_TEST_JOBS_OPTION, []);
        if (!is_array($raw)) {
            return [];
        }

        $jobs = [];
        foreach ($raw as $job_id => $job) {
            if (!is_array($job)) {
                continue;
            }

            $normalized = self::normalize_load_test_job($job);
            if ($normalized['id'] === '') {
                $normalized['id'] = sanitize_key((string) $job_id);
            }
            if ($normalized['id'] === '') {
                continue;
            }
            $jobs[$normalized['id']] = $normalized;
        }

        return $jobs;
    }

    private static function save_load_test_jobs_option_map(array $jobs): bool
    {
        $jobs = self::prune_load_test_jobs_option_map($jobs);
        return update_option(self::LOAD_TEST_JOBS_OPTION, $jobs, false);
    }

    private static function prune_load_test_jobs_option_map(array $jobs): array
    {
        uasort($jobs, static function (array $left, array $right): int {
            $left_time = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $right_time = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $right_time <=> $left_time;
        });

        $pruned = [];
        $completed_kept = 0;
        foreach ($jobs as $job_id => $job) {
            $status = (string) ($job['status'] ?? 'queued');
            $is_active = in_array($status, ['queued', 'running'], true);
            if ($is_active || $completed_kept < self::LOAD_TEST_MAX_JOB_HISTORY) {
                $pruned[$job_id] = $job;
                if (!$is_active) {
                    $completed_kept++;
                }
            }
        }

        return $pruned;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    public static function normalize_load_test_job(array $job): array
    {
        return [
            'id' => sanitize_key((string) ($job['id'] ?? '')),
            'user_id' => max(0, (int) ($job['user_id'] ?? 0)),
            'status' => sanitize_key((string) ($job['status'] ?? 'queued')),
            'pid' => max(0, (int) ($job['pid'] ?? 0)),
            'exam_id' => max(0, (int) ($job['exam_id'] ?? 0)),
            'exam_title' => sanitize_text_field((string) ($job['exam_title'] ?? 'Exam')),
            'subject_name' => sanitize_text_field((string) ($job['subject_name'] ?? '')),
            'workspace' => isset($job['workspace']) ? wp_normalize_path((string) $job['workspace']) : '',
            'created_at' => sanitize_text_field((string) ($job['created_at'] ?? '')),
            'started_at' => sanitize_text_field((string) ($job['started_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($job['finished_at'] ?? '')),
            'exit_code' => (!array_key_exists('exit_code', $job) || $job['exit_code'] === null || $job['exit_code'] === '') ? null : (int) $job['exit_code'],
            'base_url' => sanitize_text_field((string) ($job['base_url'] ?? '')),
            'token_source' => sanitize_text_field((string) ($job['token_source'] ?? 'global')),
            'manual_token' => sanitize_text_field((string) ($job['manual_token'] ?? '')),
            'student_count' => max(0, (int) ($job['student_count'] ?? 0)),
            'profile' => isset($job['profile']) && is_array($job['profile'])
                ? self::normalize_load_test_profile((array) $job['profile'])
                : self::normalize_load_test_profile([]),
            'command_preview' => sanitize_textarea_field((string) ($job['command_preview'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($job['notes'] ?? '')),
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function sync_load_test_jobs(): array
    {
        $jobs = self::get_load_test_jobs_option_map();
        if (empty($jobs)) {
            return [];
        }

        $changed = false;
        foreach ($jobs as $job_id => $job) {
            $synced = self::sync_single_load_test_job($job);
            if ($synced !== $job) {
                $jobs[$job_id] = $synced;
                $changed = true;
            }
        }

        if ($changed) {
            self::save_load_test_jobs_option_map($jobs);
        }

        uasort($jobs, static function (array $left, array $right): int {
            $left_time = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $right_time = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $right_time <=> $left_time;
        });

        return $jobs;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function sync_single_load_test_job(array $job): array
    {
        $job = self::normalize_load_test_job($job);
        $workspace = (string) ($job['workspace'] ?? '');
        if ($workspace === '' || !is_dir($workspace)) {
            return $job;
        }

        $exit_code_path = wp_normalize_path($workspace . '/exit_code.txt');
        $summary_path = wp_normalize_path($workspace . '/summary.json');
        $pid = (int) ($job['pid'] ?? 0);
        $status = (string) ($job['status'] ?? 'queued');
        $process_running = ($pid > 0) ? self::is_load_test_process_running($pid) : false;
        $exit_code = null;

        if (is_file($exit_code_path)) {
            $raw_exit_code = trim((string) file_get_contents($exit_code_path));
            if ($raw_exit_code !== '' && preg_match('/^-?\d+$/', $raw_exit_code)) {
                $exit_code = (int) $raw_exit_code;
            }
        }

        if ($status === 'cancelled') {
            if ($job['finished_at'] === '') {
                $job['finished_at'] = current_time('mysql');
            }
            if ($exit_code !== null) {
                $job['exit_code'] = $exit_code;
            }

            return $job;
        }

        if ($exit_code !== null) {
            $job['exit_code'] = $exit_code;
            $job['status'] = ($exit_code === 0 && is_file($summary_path)) ? 'success' : 'failed';
            if ($job['finished_at'] === '') {
                $job['finished_at'] = current_time('mysql');
            }
            if ($job['status'] === 'failed') {
                $stderr_tail = self::read_load_test_log_tail($job, 'stderr', 20);
                if (
                    $stderr_tail !== '' &&
                    preg_match('/cannot create user data directory|home directories outside of \\/home/i', $stderr_tail)
                ) {
                    $job['notes'] = trim('Runner k6 gagal start karena binary k6 berasal dari Snap dan user PHP ini tidak memiliki HOME yang valid di bawah /home. Install k6 native/non-snap atau konfigurasi Snap home terlebih dahulu.');
                }
            }

            return $job;
        }

        if ($process_running) {
            if ($status !== 'running') {
                $job['status'] = 'running';
            }

            return $job;
        }

        if (in_array($status, ['queued', 'running'], true)) {
            $job['status'] = is_file($summary_path) ? 'success' : 'failed';
            if ($job['status'] === 'success') {
                $job['exit_code'] = 0;
            }
            if ($job['finished_at'] === '') {
                $job['finished_at'] = current_time('mysql');
            }
            if ($job['status'] === 'failed') {
                $stderr_tail = self::read_load_test_log_tail($job, 'stderr', 20);
                if (
                    $stderr_tail !== '' &&
                    preg_match('/cannot create user data directory|home directories outside of \\/home/i', $stderr_tail)
                ) {
                    $job['notes'] = trim('Runner k6 gagal start karena binary k6 berasal dari Snap dan user PHP ini tidak memiliki HOME yang valid di bawah /home. Install k6 native/non-snap atau konfigurasi Snap home terlebih dahulu.');
                }
            }
        }

        return $job;
    }

    private static function is_load_test_process_running(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $exit_code = 1;
        exec('kill -0 ' . (int) $pid . ' >/dev/null 2>&1', $output, $exit_code);
        return $exit_code === 0;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public static function get_load_test_job_artifacts(array $job): array
    {
        $workspace = wp_normalize_path((string) ($job['workspace'] ?? ''));
        if ($workspace === '') {
            return [];
        }

        return [
            'summary' => [
                'label' => 'Summary JSON',
                'path' => $workspace . '/summary.json',
                'content_type' => 'application/json; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-summary.json'),
            ],
            'stdout' => [
                'label' => 'Stdout Log',
                'path' => $workspace . '/stdout.log',
                'content_type' => 'text/plain; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-stdout.log'),
            ],
            'stderr' => [
                'label' => 'Stderr Log',
                'path' => $workspace . '/stderr.log',
                'content_type' => 'text/plain; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-stderr.log'),
            ],
            'students' => [
                'label' => 'students.json',
                'path' => $workspace . '/students.json',
                'content_type' => 'application/json; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-students.json'),
            ],
            'config' => [
                'label' => 'Config JSON',
                'path' => $workspace . '/config.json',
                'content_type' => 'application/json; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-config.json'),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function read_load_test_job_summary(array $job): array
    {
        $artifacts = self::get_load_test_job_artifacts($job);
        $summary_path = isset($artifacts['summary']['path']) ? (string) $artifacts['summary']['path'] : '';
        if ($summary_path === '' || !is_file($summary_path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($summary_path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $metrics = isset($decoded['metrics']) && is_array($decoded['metrics'])
            ? (array) $decoded['metrics']
            : [];

        return [
            'http_req_failed_rate' => self::extract_load_test_metric_value($metrics, 'http_req_failed', 'rate'),
            'http_req_duration_p95' => self::extract_load_test_metric_value($metrics, 'http_req_duration', 'p(95)'),
            'session_success_rate' => self::extract_load_test_metric_value($metrics, 'exam_session_success', 'rate'),
            'iterations' => self::extract_load_test_metric_value($metrics, 'iterations', 'count'),
        ];
    }

    /**
     * @param array<string,mixed> $metrics
     */
    private static function extract_load_test_metric_value(array $metrics, string $metric_key, string $value_key): ?float
    {
        if (!isset($metrics[$metric_key]) || !is_array($metrics[$metric_key])) {
            return null;
        }

        $metric = (array) $metrics[$metric_key];
        $values = isset($metric['values']) && is_array($metric['values']) ? (array) $metric['values'] : [];
        if (!isset($values[$value_key])) {
            return null;
        }

        $value = $values[$value_key];
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public static function read_load_test_log_tail(array $job, string $artifact_key, int $line_limit = 8): string
    {
        $artifacts = self::get_load_test_job_artifacts($job);
        $path = isset($artifacts[$artifact_key]['path']) ? (string) $artifacts[$artifact_key]['path'] : '';
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || empty($lines)) {
            return '';
        }

        return trim(implode("\n", array_slice($lines, -1 * max(1, $line_limit))));
    }

    public static function get_load_test_status_meta(string $status): array
    {
        switch ($status) {
            case 'running':
                return ['label' => 'Running', 'tone' => 'running'];
            case 'success':
                return ['label' => 'Success', 'tone' => 'done'];
            case 'failed':
                return ['label' => 'Failed', 'tone' => 'danger'];
            case 'cancelled':
                return ['label' => 'Cancelled', 'tone' => 'idle'];
            case 'queued':
            default:
                return ['label' => 'Queued', 'tone' => 'idle'];
        }
    }

    public static function format_load_test_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date('d M Y, H:i:s', $timestamp);
    }

    /**
     * @param array<string,mixed> $job
     */
    public static function get_load_test_job_selection_label(array $job): string
    {
        $job = self::normalize_load_test_job($job);
        $status_meta = self::get_load_test_status_meta((string) ($job['status'] ?? 'queued'));
        $run_at = self::format_load_test_datetime((string) (($job['started_at'] ?? '') !== '' ? $job['started_at'] : ($job['created_at'] ?? '')));

        return trim(
            (string) ($job['exam_title'] ?? 'Exam')
            . ' · '
            . (string) ($status_meta['label'] ?? 'Queued')
            . ' · '
            . $run_at
        );
    }

    private static function build_load_test_command_preview(
        array $profile,
        string $base_url,
        int $exam_id,
        string $exam_token,
        string $k6_path,
        string $workspace
    ): string {
        $command_parts = [
            'cd ' . escapeshellarg($workspace),
            'BASE_URL=' . escapeshellarg($base_url),
            'EXAM_ID=' . (int) $exam_id,
        ];
        if ($exam_token !== '') {
            $command_parts[] = 'EXAM_TOKEN=' . escapeshellarg($exam_token);
        }
        $command_parts[] = 'VUS=' . (int) ($profile['vus'] ?? 0);
        $command_parts[] = 'ITERATIONS=' . (int) ($profile['iterations'] ?? 1);
        $command_parts[] = 'QUESTIONS_PER_USER=' . (int) ($profile['questions_per_user'] ?? 0);
        $command_parts[] = 'SESSION_START_SPREAD_MS=' . (int) ($profile['session_start_spread_ms'] ?? 0);
        $command_parts[] = 'POST_START_SPREAD_MS=' . (int) ($profile['post_start_spread_ms'] ?? 0);
        $command_parts[] = 'SUBMIT_MODE=' . escapeshellarg((string) ($profile['submit_mode'] ?? 'all'));
        $command_parts[] = 'ENABLE_BATCH_SUBMIT=' . (!empty($profile['enable_batch_submit']) ? '1' : '0');
        $command_parts[] = escapeshellarg($k6_path) . ' run --summary-export summary.json cbt_exam_1000_users.js';

        return implode(" \\\n  ", $command_parts);
    }

    private static function ensure_load_test_runtime_root()
    {
        $upload = wp_upload_dir();
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['basedir'])) {
            return new WP_Error('load_test_upload_missing', 'Folder uploads WordPress tidak tersedia untuk runtime load test.');
        }

        $runtime_root = trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY;
        if (!wp_mkdir_p($runtime_root) || !is_dir($runtime_root)) {
            return new WP_Error('load_test_runtime_root_failed', 'Gagal membuat folder runtime load test di uploads.');
        }

        self::protect_load_test_runtime_root($runtime_root);

        return wp_normalize_path($runtime_root);
    }

    private static function protect_load_test_runtime_root(string $runtime_root): void
    {
        $runtime_root = wp_normalize_path($runtime_root);
        if ($runtime_root === '' || !is_dir($runtime_root)) {
            return;
        }

        $index_file = $runtime_root . '/index.php';
        if (!is_file($index_file)) {
            @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        $htaccess_file = $runtime_root . '/.htaccess';
        if (!is_file($htaccess_file)) {
            @file_put_contents(
                $htaccess_file,
                "Order Deny,Allow\nDeny from all\n"
            );
        }
    }

    private static function build_load_test_job_id(int $exam_id): string
    {
        return sanitize_key(
            'lt'
            . gmdate('YmdHis')
            . 'e'
            . max(0, $exam_id)
            . strtolower((string) wp_generate_password(6, false, false))
        );
    }

    /**
     * @param array<int,array<string,string>> $student_rows
     * @param array<string,mixed> $exam
     * @param array<string,int|string> $profile
     */
    private static function prepare_load_test_job_workspace(
        string $runtime_root,
        string $job_id,
        array $student_rows,
        array $exam,
        array $profile,
        string $base_url,
        string $resolved_token,
        string $token_source,
        string $k6_path
    ) {
        $workspace = wp_normalize_path(trailingslashit($runtime_root) . $job_id);
        if (!wp_mkdir_p($workspace) || !is_dir($workspace)) {
            return new WP_Error('load_test_workspace_failed', 'Gagal membuat workspace job load test.');
        }

        @chmod($workspace, 0700);
        $script_source = wp_normalize_path(CBT_EXAM_SYSTEM_PATH . 'performance/load-test/k6/cbt_exam_1000_users.js');
        if (!is_file($script_source)) {
            return new WP_Error('load_test_script_missing', 'Script k6 cbt_exam_1000_users.js tidak ditemukan.');
        }

        $script_target = $workspace . '/cbt_exam_1000_users.js';
        if (!@copy($script_source, $script_target)) {
            return new WP_Error('load_test_script_copy_failed', 'Gagal menyalin script k6 ke workspace runtime.');
        }

        $students_payload = [];
        foreach ($student_rows as $student_row) {
            $students_payload[] = [
                'identifier' => (string) ($student_row['identifier'] ?? ''),
                'password' => (string) ($student_row['password'] ?? ''),
            ];
        }

        $students_json = wp_json_encode($students_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($students_json) || $students_json === '') {
            return new WP_Error('load_test_students_json_failed', 'Gagal membuat students.json untuk runner load test.');
        }

        if (@file_put_contents($workspace . '/students.json', $students_json) === false) {
            return new WP_Error('load_test_students_write_failed', 'Gagal menulis students.json ke workspace load test.');
        }

        $command_preview = self::build_load_test_command_preview(
            $profile,
            $base_url,
            (int) ($exam['id'] ?? 0),
            $resolved_token,
            $k6_path,
            $workspace
        );

        $config = [
            'job_id' => $job_id,
            'created_at' => current_time('mysql'),
            'exam' => [
                'id' => (int) ($exam['id'] ?? 0),
                'title' => (string) ($exam['title'] ?? 'Exam'),
                'subject_name' => (string) ($exam['subject_name'] ?? ''),
            ],
            'profile' => $profile,
            'base_url' => $base_url,
            'exam_token' => $resolved_token,
            'token_source' => $token_source,
            'k6_path' => $k6_path,
            'student_count' => count($student_rows),
            'command_preview' => $command_preview,
        ];

        $config_json = wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($config_json) || @file_put_contents($workspace . '/config.json', $config_json) === false) {
            return new WP_Error('load_test_config_failed', 'Gagal menulis config.json untuk runner load test.');
        }

        $run_script = self::build_load_test_runner_script(
            $workspace,
            $profile,
            $base_url,
            (int) ($exam['id'] ?? 0),
            $resolved_token,
            $k6_path
        );
        if (@file_put_contents($workspace . '/run.sh', $run_script) === false) {
            return new WP_Error('load_test_runner_script_failed', 'Gagal menulis run.sh untuk background runner.');
        }
        @chmod($workspace . '/run.sh', 0700);

        return [
            'workspace' => $workspace,
            'command_preview' => $command_preview,
        ];
    }

    /**
     * @param array<string,int|string> $profile
     */
    private static function build_load_test_runner_script(
        string $workspace,
        array $profile,
        string $base_url,
        int $exam_id,
        string $resolved_token,
        string $k6_path
    ): string {
        $runner_home_meta = self::get_load_test_runner_home_meta($k6_path);
        $home_dir = (string) ($runner_home_meta['path'] ?? '');

        $lines = [
            '#!/bin/sh',
            'cd ' . escapeshellarg($workspace) . ' || exit 1',
            'umask 077',
            ': > stdout.log',
            ': > stderr.log',
            'rm -f exit_code.txt',
            'export PATH=' . escapeshellarg('/snap/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'),
            'export BASE_URL=' . escapeshellarg($base_url),
            'export EXAM_ID=' . escapeshellarg((string) $exam_id),
            'export VUS=' . escapeshellarg((string) ((int) ($profile['vus'] ?? 0))),
            'export ITERATIONS=' . escapeshellarg((string) ((int) ($profile['iterations'] ?? 1))),
            'export QUESTIONS_PER_USER=' . escapeshellarg((string) ((int) ($profile['questions_per_user'] ?? 0))),
            'export SESSION_START_SPREAD_MS=' . escapeshellarg((string) ((int) ($profile['session_start_spread_ms'] ?? 0))),
            'export POST_START_SPREAD_MS=' . escapeshellarg((string) ((int) ($profile['post_start_spread_ms'] ?? 0))),
            'export SUBMIT_MODE=' . escapeshellarg((string) ($profile['submit_mode'] ?? 'all')),
            'export ENABLE_BATCH_SUBMIT=' . escapeshellarg(!empty($profile['enable_batch_submit']) ? '1' : '0'),
            'export MAX_DURATION=' . escapeshellarg((string) ($profile['max_duration'] ?? '45m')),
            'export STRICT_EXAM_ID=' . escapeshellarg('1'),
            'export SKIP_EXAMS_REQUEST=' . escapeshellarg('1'),
        ];

        if ($home_dir !== '') {
            array_splice($lines, 6, 0, ['export HOME=' . escapeshellarg($home_dir)]);
        }

        if ($resolved_token !== '') {
            $lines[] = 'export EXAM_TOKEN=' . escapeshellarg($resolved_token);
        }

        $lines[] = escapeshellarg($k6_path) . ' run --summary-export summary.json cbt_exam_1000_users.js >> stdout.log 2>> stderr.log';
        $lines[] = 'status=$?';
        $lines[] = 'printf \'%s\' "$status" > exit_code.txt';
        $lines[] = 'exit "$status"';

        return implode("\n", $lines) . "\n";
    }

    private static function spawn_load_test_process(string $run_script_path): int
    {
        if (!function_exists('exec')) {
            return 0;
        }

        $output = [];
        $exit_code = 1;
        exec('nohup sh ' . escapeshellarg($run_script_path) . ' >/dev/null 2>&1 & echo $!', $output, $exit_code);
        if ($exit_code !== 0 || empty($output)) {
            return 0;
        }

        return max(0, (int) trim((string) end($output)));
    }

    private static function terminate_load_test_process(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $terminated = false;
        if (function_exists('posix_kill')) {
            $terminated = @posix_kill($pid, SIGTERM);
            usleep(250000);
            if (@posix_kill($pid, 0)) {
                @posix_kill($pid, SIGKILL);
            }
            return $terminated;
        }

        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $exit_code = 1;
        exec('kill ' . (int) $pid . ' >/dev/null 2>&1', $output, $exit_code);
        $terminated = ($exit_code === 0);
        usleep(250000);
        exec('kill -9 ' . (int) $pid . ' >/dev/null 2>&1', $output, $exit_code);

        return $terminated;
    }

    private static function delete_load_test_workspace(string $workspace): bool
    {
        $workspace = wp_normalize_path(trim($workspace));
        if ($workspace === '') {
            return true;
        }
        if (!file_exists($workspace)) {
            return true;
        }

        $upload = wp_upload_dir();
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['basedir'])) {
            return false;
        }

        $runtime_root = wp_normalize_path(trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY);
        $runtime_root = rtrim($runtime_root, '/');
        if ($runtime_root === '' || strpos($workspace, $runtime_root . '/') !== 0) {
            return false;
        }

        if (is_file($workspace)) {
            return @unlink($workspace);
        }

        $entries = @scandir($workspace);
        if (!is_array($entries)) {
            return false;
        }

        $all_removed = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = wp_normalize_path($workspace . '/' . $entry);
            if (is_dir($child)) {
                if (!self::delete_load_test_workspace($child)) {
                    $all_removed = false;
                }
                continue;
            }

            if (file_exists($child) && !@unlink($child)) {
                $all_removed = false;
            }
        }

        if (!@rmdir($workspace)) {
            $all_removed = false;
        }

        return $all_removed;
    }

    /**
     * @return array{removed:int,failed:int}
     */
    private static function clear_load_test_runtime_root_contents(): array
    {
        $upload = wp_upload_dir();
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['basedir'])) {
            return ['removed' => 0, 'failed' => 0];
        }

        $runtime_root = wp_normalize_path(trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY);
        if ($runtime_root === '' || !is_dir($runtime_root)) {
            return ['removed' => 0, 'failed' => 0];
        }

        $entries = @scandir($runtime_root);
        if (!is_array($entries)) {
            return ['removed' => 0, 'failed' => 0];
        }

        $removed = 0;
        $failed = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php' || $entry === '.htaccess') {
                continue;
            }

            $child = wp_normalize_path($runtime_root . '/' . $entry);
            if (self::delete_load_test_workspace($child)) {
                $removed++;
            } else {
                $failed++;
            }
        }

        return [
            'removed' => $removed,
            'failed' => $failed,
        ];
    }

    private static function get_load_test_job_by_id(string $job_id): ?array
    {
        $job_id = sanitize_key($job_id);
        if ($job_id === '') {
            return null;
        }

        $jobs = self::sync_load_test_jobs();
        return isset($jobs[$job_id]) ? (array) $jobs[$job_id] : null;
    }

    public static function handle_start_load_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_start_load_test');
        self::prepare_runtime_for_bulk_user_import();

        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            self::redirect_maintenance_page(null, 'Belum ada bulk students dengan password plain-text yang siap dipakai untuk load test.', 'load');
        }

        $runtime = self::get_load_test_runtime_snapshot();
        if (empty($runtime['shell_available']) || empty($runtime['exec_available'])) {
            self::redirect_maintenance_page(null, 'Shell PHP untuk background runner belum tersedia. Pastikan fungsi exec aktif.', 'load');
        }
        if ((string) ($runtime['k6_path'] ?? '') === '') {
            self::redirect_maintenance_page(null, 'Binary k6 tidak ditemukan pada server ini.', 'load');
        }
        if (
            (string) ($runtime['k6_install_mode'] ?? '') === 'snap' &&
            empty($runtime['runner_home_supported'])
        ) {
            $detected_home = (string) ($runtime['runner_home_detected'] ?? '');
            $detected_copy = $detected_home !== '' ? ' HOME terdeteksi: ' . $detected_home . '.' : '';
            self::redirect_maintenance_page(
                null,
                'Binary k6 yang terdeteksi berasal dari Snap, tetapi user PHP ini tidak punya HOME valid di bawah /home sehingga runner admin akan gagal start.' . $detected_copy . ' Install k6 native/non-snap atau konfigurasi Snap home terlebih dahulu.',
                'load'
            );
        }

        $selected_exam_ids = isset($_POST['exam_ids']) && is_array($_POST['exam_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['exam_ids'])))))
            : [];
        if (empty($selected_exam_ids)) {
            self::redirect_maintenance_page(null, 'Pilih minimal satu exam aktif untuk memulai load test.', 'load');
        }

        $catalog = self::get_load_test_exam_catalog();
        $eligible_map = [];
        foreach ((array) $catalog['eligible'] as $exam_row) {
            $eligible_map[(int) ($exam_row['id'] ?? 0)] = (array) $exam_row;
        }

        $selected_exams = [];
        $invalid_exams = [];
        foreach ($selected_exam_ids as $exam_id) {
            if (!isset($eligible_map[$exam_id])) {
                $invalid_exams[] = '#' . $exam_id;
                continue;
            }
            $selected_exams[$exam_id] = $eligible_map[$exam_id];
        }
        if (!empty($invalid_exams)) {
            self::redirect_maintenance_page(
                null,
                'Ada exam yang tidak valid untuk load test: ' . implode(', ', $invalid_exams) . '. Hanya exam published, aktif, dan punya soal yang bisa dijalankan.',
                'load'
            );
        }

        $profile = self::normalize_load_test_profile($_POST);
        $base_url = self::normalize_load_test_base_url($_POST);
        $manual_token = self::normalize_load_test_token_override(
            isset($_POST['manual_exam_token']) ? (string) wp_unslash($_POST['manual_exam_token']) : ''
        );
        $resolved_token = $manual_token !== ''
            ? $manual_token
            : self::normalize_load_test_token_override((string) (($runtime['global_token_meta']['token'] ?? '')));
        $token_source = $manual_token !== '' ? 'manual' : 'global';

        $runtime_root = self::ensure_load_test_runtime_root();
        if (is_wp_error($runtime_root)) {
            self::redirect_maintenance_page(null, $runtime_root->get_error_message(), 'load');
        }

        $jobs = self::get_load_test_jobs_option_map();
        $started_count = 0;
        $failed_labels = [];
        foreach ($selected_exams as $exam) {
            $job_id = self::build_load_test_job_id((int) ($exam['id'] ?? 0));
            $workspace_result = self::prepare_load_test_job_workspace(
                (string) $runtime_root,
                $job_id,
                (array) $student_pool['rows'],
                $exam,
                $profile,
                $base_url,
                $resolved_token,
                $token_source,
                (string) ($runtime['k6_path'] ?? '')
            );

            if (is_wp_error($workspace_result)) {
                $failed_labels[] = (string) ($exam['title'] ?? ('Exam #' . (int) ($exam['id'] ?? 0)));
                continue;
            }

            $workspace_data = is_array($workspace_result) ? $workspace_result : [];
            $job = self::normalize_load_test_job([
                'id' => $job_id,
                'user_id' => get_current_user_id(),
                'status' => 'queued',
                'pid' => 0,
                'exam_id' => (int) ($exam['id'] ?? 0),
                'exam_title' => (string) ($exam['title'] ?? 'Exam'),
                'subject_name' => (string) ($exam['subject_name'] ?? ''),
                'workspace' => (string) ($workspace_data['workspace'] ?? ''),
                'created_at' => current_time('mysql'),
                'started_at' => current_time('mysql'),
                'base_url' => $base_url,
                'token_source' => $token_source,
                'manual_token' => $manual_token,
                'student_count' => (int) ($student_pool['valid_count'] ?? 0),
                'profile' => $profile,
                'command_preview' => (string) ($workspace_data['command_preview'] ?? ''),
                'notes' => trim(
                    (((int) ($student_pool['valid_count'] ?? 0) < (int) ($profile['vus'] ?? 0))
                        ? 'Jumlah siswa bulk lebih kecil dari target VUs, akun akan di-reuse oleh script k6. '
                        : '')
                    . (((string) ($runtime['k6_install_mode'] ?? '') === 'snap')
                        ? 'Runner memakai binary Snap; untuk hasil paling stabil disarankan install k6 native/non-snap.'
                        : '')
                ),
            ]);

            $pid = self::spawn_load_test_process((string) ($job['workspace'] ?? '') . '/run.sh');
            if ($pid <= 0) {
                $job['status'] = 'failed';
                $job['finished_at'] = current_time('mysql');
                $job['notes'] = trim((string) $job['notes'] . ' Gagal menjalankan background runner shell.');
                $jobs[$job_id] = $job;
                $failed_labels[] = (string) ($exam['title'] ?? ('Exam #' . (int) ($exam['id'] ?? 0)));
                continue;
            }

            $job['status'] = 'running';
            $job['pid'] = $pid;
            $jobs[$job_id] = $job;
            $started_count++;
        }

        self::save_load_test_jobs_option_map($jobs);
        if ($started_count <= 0) {
            self::redirect_maintenance_page(
                null,
                'Tidak ada job load test yang berhasil dimulai. Periksa ketersediaan runner k6 dan izin tulis uploads.',
                'load'
            );
        }

        $message = sprintf('%d job load test dimulai.', $started_count);
        if (!empty($failed_labels)) {
            $message .= ' Sebagian exam gagal start: ' . implode(', ', $failed_labels) . '.';
        }
        if ((int) ($student_pool['valid_count'] ?? 0) < (int) ($profile['vus'] ?? 0)) {
            $message .= ' User siswa lebih sedikit dari target VUs, jadi script akan me-reuse akun.';
        }

        self::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_cancel_load_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_POST['job_id']) ? sanitize_key((string) wp_unslash($_POST['job_id'])) : '';
        check_admin_referer('cbt_cancel_load_test_' . $job_id);

        $jobs = self::get_load_test_jobs_option_map();
        if ($job_id === '' || !isset($jobs[$job_id])) {
            self::redirect_maintenance_page(null, 'Job load test tidak ditemukan.', 'load');
        }

        $job = self::normalize_load_test_job((array) $jobs[$job_id]);
        $pid = (int) ($job['pid'] ?? 0);
        if ($pid > 0) {
            self::terminate_load_test_process($pid);
        }

        $job['status'] = 'cancelled';
        $job['finished_at'] = current_time('mysql');
        $job['notes'] = trim((string) $job['notes'] . ' Dibatalkan dari CBT Maintenance.');
        $jobs[$job_id] = $job;
        self::save_load_test_jobs_option_map($jobs);

        self::redirect_maintenance_page('Job load test berhasil dibatalkan.', null, 'load');
    }

    public static function handle_delete_load_test_job(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_POST['job_id']) ? sanitize_key((string) wp_unslash($_POST['job_id'])) : '';
        check_admin_referer('cbt_delete_load_test_job_' . $job_id);

        $jobs = self::get_load_test_jobs_option_map();
        if ($job_id === '' || !isset($jobs[$job_id])) {
            self::redirect_maintenance_page(null, 'Hasil load test tidak ditemukan.', 'load');
        }

        $job = self::normalize_load_test_job((array) $jobs[$job_id]);
        $pid = (int) ($job['pid'] ?? 0);
        if ($pid > 0 && in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
            self::terminate_load_test_process($pid);
        }

        $workspace_removed = self::delete_load_test_workspace((string) ($job['workspace'] ?? ''));
        unset($jobs[$job_id]);
        self::save_load_test_jobs_option_map($jobs);

        $message = 'Hasil load test berhasil dihapus.';
        if (!$workspace_removed) {
            $message .= ' Workspace job perlu dibersihkan manual.';
        }

        self::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_clear_load_test_jobs(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_load_test_jobs');

        $jobs = self::sync_load_test_jobs();
        $removed_jobs = 0;
        $stopped_jobs = 0;
        $workspace_failures = 0;

        foreach ($jobs as $job) {
            $job = self::normalize_load_test_job((array) $job);
            if ($job['id'] === '') {
                continue;
            }

            $pid = (int) ($job['pid'] ?? 0);
            if ($pid > 0 && in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
                self::terminate_load_test_process($pid);
                $stopped_jobs++;
            }

            if (!self::delete_load_test_workspace((string) ($job['workspace'] ?? ''))) {
                $workspace_failures++;
            }
            $removed_jobs++;
        }

        delete_option(self::LOAD_TEST_JOBS_OPTION);

        $runtime_cleanup = self::clear_load_test_runtime_root_contents();
        $runtime_removed = (int) ($runtime_cleanup['removed'] ?? 0);
        $runtime_failed = (int) ($runtime_cleanup['failed'] ?? 0);

        if ($removed_jobs <= 0 && $runtime_removed <= 0 && $runtime_failed <= 0) {
            self::redirect_maintenance_page(null, 'Tidak ada histori load test yang perlu dihapus.', 'load');
        }

        $message_parts = [];
        if ($removed_jobs > 0) {
            $message_parts[] = sprintf('%d histori load test dihapus.', $removed_jobs);
        }
        if ($stopped_jobs > 0) {
            $message_parts[] = sprintf('%d job aktif dihentikan.', $stopped_jobs);
        }
        if ($runtime_removed > 0) {
            $message_parts[] = sprintf('%d workspace sisa dibersihkan.', $runtime_removed);
        }

        $message = !empty($message_parts)
            ? implode(' ', $message_parts)
            : 'Histori load test berhasil dibersihkan.';

        $needs_manual_cleanup = $runtime_failed > 0 || ($workspace_failures > 0 && $runtime_removed <= 0);
        if ($needs_manual_cleanup) {
            $message .= ' Sebagian workspace masih perlu dibersihkan manual.';
        }

        self::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_download_load_test_artifact(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_GET['job_id']) ? sanitize_key((string) wp_unslash($_GET['job_id'])) : '';
        $artifact_key = isset($_GET['artifact']) ? sanitize_key((string) wp_unslash($_GET['artifact'])) : '';
        check_admin_referer('cbt_download_load_test_artifact_' . $job_id . '_' . $artifact_key);

        $job = self::get_load_test_job_by_id($job_id);
        if (!is_array($job)) {
            wp_die('Job load test tidak ditemukan.');
        }

        $artifacts = self::get_load_test_job_artifacts($job);
        if (!isset($artifacts[$artifact_key]) || !is_array($artifacts[$artifact_key])) {
            wp_die('Artifact load test tidak valid.');
        }

        $artifact = (array) $artifacts[$artifact_key];
        $path = isset($artifact['path']) ? wp_normalize_path((string) $artifact['path']) : '';
        if ($path === '' || !is_file($path)) {
            wp_die('File artifact belum tersedia.');
        }

        nocache_headers();
        header('Content-Type: ' . (string) ($artifact['content_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . (string) ($artifact['filename'] ?? basename($path)) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public static function handle_export_load_test_students_json(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_load_test_students_json');
        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            wp_die('Belum ada bulk students valid yang bisa diexport.');
        }

        $payload = [];
        foreach ((array) $student_pool['rows'] as $row) {
            $payload[] = [
                'identifier' => (string) ($row['identifier'] ?? ''),
                'password' => (string) ($row['password'] ?? ''),
            ];
        }

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            wp_die('Gagal membuat students.json.');
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-load-test-students.json"');
        echo $json;
        exit;
    }

    public static function handle_export_load_test_students_csv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_load_test_students_csv');
        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            wp_die('Belum ada bulk students valid yang bisa diexport.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-load-test-students.csv"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            wp_die('Gagal menulis file CSV.');
        }

        fputcsv($output, ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'foto']);
        foreach ((array) $student_pool['rows'] as $row) {
            fputcsv($output, [
                (string) ($row['name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['nisn'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['password'] ?? ''),
                'siswa',
                (string) ($row['kode_kelas'] ?? ''),
                (string) ($row['kode_ruang'] ?? ''),
                (string) ($row['agama'] ?? ''),
                (string) ($row['foto'] ?? ''),
            ]);
        }
        fclose($output);
        exit;
    }

    public static function handle_export_load_test_students_xlsx(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_load_test_students_xlsx');
        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            wp_die('Belum ada bulk students valid yang bisa diexport.');
        }
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            wp_die('Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [
            ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'foto'],
        ];
        foreach ((array) $student_pool['rows'] as $row) {
            $rows[] = [
                (string) ($row['name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['nisn'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['password'] ?? ''),
                'siswa',
                (string) ($row['kode_kelas'] ?? ''),
                (string) ($row['kode_ruang'] ?? ''),
                (string) ($row['agama'] ?? ''),
                (string) ($row['foto'] ?? ''),
            ];
        }
        $sheet->fromArray($rows, null, 'A1');

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cbt-load-test-students.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public static function handle_load_test_jobs_ajax(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('cbt_load_test_jobs', 'nonce');
        $jobs = self::sync_load_test_jobs();
        $running_count = 0;
        foreach ($jobs as $job) {
            if (in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
                $running_count++;
            }
        }

        wp_send_json_success([
            'html' => CBT_Admin_Maintenance_Page::render_load_test_jobs_markup($jobs),
            'running_count' => $running_count,
            'job_count' => count($jobs),
            'refreshed_at' => current_time('mysql'),
        ]);
    }

    private static function build_user_import_lookup(array $rows, int $offset, int $target_end): array
    {
        return CBT_Admin_Users_Service::build_user_import_lookup($rows, $offset, $target_end);
    }

    private static function upsert_user_from_row(array $row, array &$import_lookup = []): string
    {
        return CBT_Admin_Users_Service::upsert_user_from_row($row, $import_lookup);
    }

    /**
     * @return string[]
     */
    private static function get_supported_agama_options(): array
    {
        return CBT_Admin_Users_Service::get_supported_agama_options();
    }

    /**
     * @return string[]
     */
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

    /**
     * @return array<int,array{statement:string,answer:string}>
     */
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

    private static function save_question_type_detail(int $question_id, string $question_type, string $correct_text): void
    {
        CBT_Admin_Questions_Helper::save_question_type_detail($question_id, $question_type, $correct_text);
    }
}
