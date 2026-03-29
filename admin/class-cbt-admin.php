<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Admin
{
    private const USER_META_PLAIN_PASSWORD = 'cbt_plain_password';
    private const DEFAULT_STUDENT_PHOTO_RELATIVE_PATH = 'public/images/default-student-avatar.svg';

    public static function init(): void
    {
        add_action('admin_menu', [CBT_Admin_Menu::class, 'register_menu']);
        add_action('admin_init', [CBT_Admin_Menu::class, 'redirect_removed_admin_pages']);
        add_action('admin_head', [CBT_Admin_Menu::class, 'render_legacy_security_hash_redirect']);
        add_action('admin_enqueue_scripts', [CBT_Admin_Assets::class, 'enqueue_admin_assets']);
        add_filter('script_loader_tag', [CBT_Admin_Assets::class, 'filter_script_loader_tag'], 10, 3);
        add_action('admin_notices', [CBT_Admin_Cache_Page::class, 'render_runtime_notice']);

        add_action('admin_post_cbt_save_subject', [CBT_Admin_Subjects_Actions::class, 'handle_save_subject']);
        add_action('admin_post_cbt_delete_subject', [CBT_Admin_Subjects_Actions::class, 'handle_delete_subject']);
        add_action('admin_post_cbt_bulk_delete_subjects', [CBT_Admin_Subjects_Actions::class, 'handle_bulk_delete_subjects']);
        add_action('admin_post_cbt_import_subjects', [CBT_Admin_Subjects_Actions::class, 'handle_import_subjects']);
        add_action('admin_post_cbt_download_subject_template', [CBT_Admin_Subjects_Actions::class, 'handle_download_subject_template']);
        add_action('admin_post_cbt_download_subject_template_xlsx', [CBT_Admin_Subjects_Actions::class, 'handle_download_subject_template_xlsx']);

        add_action('admin_post_cbt_save_exam', [CBT_Admin_Exams_Actions::class, 'handle_save_exam']);
        add_action('admin_post_cbt_delete_exam', [CBT_Admin_Exams_Actions::class, 'handle_delete_exam']);
        add_action('admin_post_cbt_save_global_exam_token', [CBT_Admin_Tokens_Actions::class, 'handle_save_global_exam_token']);
        add_action('admin_post_cbt_save_setup_branding', [CBT_Admin_Setup_Actions::class, 'handle_save_setup_branding']);
        add_action('admin_post_cbt_save_setup_security', [CBT_Admin_Security_Actions::class, 'handle_save_setup_security']);
        add_action('admin_post_cbt_save_security_settings', [CBT_Admin_Security_Actions::class, 'handle_save_security_settings']);
        add_action('admin_post_cbt_manage_security_logs', [CBT_Admin_Security_Actions::class, 'handle_manage_security_logs']);
        add_action('admin_post_cbt_simulate_native_security_event', [CBT_Admin_Security_Actions::class, 'handle_simulate_native_security_event']);
        add_action('admin_post_cbt_save_developer_settings', [CBT_Admin_Developer_Actions::class, 'handle_save_settings']);
        add_action('admin_post_cbt_check_developer_dev_server', [CBT_Admin_Developer_Actions::class, 'handle_check_dev_server']);
        add_action('admin_post_cbt_stop_developer_dev_server', [CBT_Admin_Developer_Actions::class, 'handle_stop_dev_server']);
        add_action('admin_post_cbt_run_all_unit_tests', [CBT_Admin_Test_Hub_Actions::class, 'handle_run_all_unit_tests']);
        add_action('admin_post_cbt_save_test_hub_settings', [CBT_Admin_Test_Hub_Actions::class, 'handle_save_settings']);
        add_action('admin_post_cbt_queue_flow_check_job', [CBT_Admin_Test_Hub_Actions::class, 'handle_queue_flow_check_job']);
        add_action('wp_ajax_cbt_sync_exam_builder_selection', [CBT_Admin_Exams_Actions::class, 'handle_sync_exam_builder_selection']);
        add_action('wp_ajax_cbt_clear_exam_builder_selection', [CBT_Admin_Exams_Actions::class, 'handle_clear_exam_builder_selection']);
        add_action('wp_ajax_cbt_start_exam_save_progress', [CBT_Admin_Exams_Actions::class, 'handle_start_exam_save_progress']);
        add_action('wp_ajax_cbt_continue_exam_save_progress', [CBT_Admin_Exams_Actions::class, 'handle_continue_exam_save_progress']);
        add_action('admin_post_cbt_cache_action', [CBT_Admin_Cache_Actions::class, 'handle_cache_action']);
        add_action('admin_post_cbt_check_update_now', [CBT_Admin_Update_Actions::class, 'handle_check_update_now']);
        add_action('admin_post_cbt_install_update_now', [CBT_Admin_Update_Actions::class, 'handle_install_update_now']);
        add_action('admin_post_cbt_reset_database', [CBT_Admin_Maintenance_Actions::class, 'handle_reset_database']);
        add_action('admin_post_cbt_generate_test_dataset', [CBT_Admin_Maintenance_Actions::class, 'handle_generate_test_dataset']);
        add_action('admin_post_cbt_run_unit_test_suite', [CBT_Admin_Test_Hub_Actions::class, 'handle_run_unit_test_suite']);
        add_action('admin_post_cbt_start_load_test', [CBT_Admin_Maintenance_Actions::class, 'handle_start_load_test']);
        add_action('admin_post_cbt_cancel_load_test', [CBT_Admin_Maintenance_Actions::class, 'handle_cancel_load_test']);
        add_action('admin_post_cbt_delete_load_test_job', [CBT_Admin_Maintenance_Actions::class, 'handle_delete_load_test_job']);
        add_action('admin_post_cbt_clear_load_test_jobs', [CBT_Admin_Maintenance_Actions::class, 'handle_clear_load_test_jobs']);
        add_action('admin_post_cbt_download_load_test_artifact', [CBT_Admin_Maintenance_Actions::class, 'handle_download_load_test_artifact']);
        add_action('admin_post_cbt_export_load_test_students_json', [CBT_Admin_Maintenance_Actions::class, 'handle_export_load_test_students_json']);
        add_action('admin_post_cbt_export_load_test_students_csv', [CBT_Admin_Maintenance_Actions::class, 'handle_export_load_test_students_csv']);
        add_action('admin_post_cbt_export_load_test_students_xlsx', [CBT_Admin_Maintenance_Actions::class, 'handle_export_load_test_students_xlsx']);
        add_action('wp_ajax_cbt_load_test_jobs', [CBT_Admin_Maintenance_Actions::class, 'handle_load_test_jobs_ajax']);
        add_action('admin_post_cbt_save_question', [CBT_Admin_Questions_Actions::class, 'handle_save_question']);
        add_action('admin_post_cbt_delete_question', [CBT_Admin_Questions_Actions::class, 'handle_delete_question']);
        add_action('admin_post_cbt_bulk_delete_questions', [CBT_Admin_Questions_Actions::class, 'handle_bulk_delete_questions']);
        add_action('admin_post_cbt_delete_all_import_batch_questions', [CBT_Admin_Questions_Actions::class, 'handle_delete_all_import_batch_questions']);
        add_action('admin_post_cbt_import_questions', [CBT_Admin_Questions_Actions::class, 'handle_import_questions']);
        add_action('admin_post_cbt_download_question_template_word', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word']);
        add_action('admin_post_cbt_download_question_template_word_mc', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word_mc']);
        add_action('admin_post_cbt_download_question_template_word_ma', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word_ma']);
        add_action('admin_post_cbt_download_question_template_word_sa', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word_sa']);
        add_action('admin_post_cbt_download_question_template_word_tf', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word_tf']);
        add_action('admin_post_cbt_download_question_template_word_tfm', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word_tfm']);
        add_action('admin_post_cbt_download_question_template_word_essay', [CBT_Admin_Questions_Actions::class, 'handle_download_question_template_word_essay']);
        add_action('admin_post_cbt_grade_essay', [CBT_Admin_Results_Actions::class, 'handle_grade_essay']);
        add_action('admin_post_cbt_reset_user_login', [CBT_Admin_Results_Actions::class, 'handle_reset_user_login']);
        add_action('admin_post_cbt_reset_attempt', [CBT_Admin_Results_Actions::class, 'handle_reset_attempt']);
        add_action('admin_post_cbt_extend_attempt_time', [CBT_Admin_Results_Actions::class, 'handle_extend_attempt_time']);
        add_action('admin_post_cbt_force_complete_attempt', [CBT_Admin_Results_Actions::class, 'handle_force_complete_attempt']);
        add_action('admin_post_cbt_bulk_reset_attempts', [CBT_Admin_Results_Actions::class, 'handle_bulk_reset_attempts']);
        add_action('admin_post_cbt_bulk_force_complete_attempts', [CBT_Admin_Results_Actions::class, 'handle_bulk_force_complete_attempts']);
        add_action('admin_post_cbt_export_exam_report_pdf', [CBT_Admin_Report_Exam_Actions::class, 'handle_export_exam_report_pdf']);
        add_action('admin_post_cbt_save_exam_incident', [CBT_Admin_Report_Exam_Actions::class, 'handle_save_exam_incident']);
        add_action('admin_post_cbt_update_exam_incident', [CBT_Admin_Report_Exam_Actions::class, 'handle_update_exam_incident']);
        add_action('admin_post_cbt_delete_exam_incident', [CBT_Admin_Report_Exam_Actions::class, 'handle_delete_exam_incident']);
        add_action('admin_post_cbt_print_exam_cards', [CBT_Admin_Exam_Cards_Actions::class, 'handle_print_exam_cards']);

        add_action('admin_post_cbt_import_users', [CBT_Admin_Users_Actions::class, 'handle_import_users']);
        add_action('admin_post_cbt_create_user_manual', [CBT_Admin_Users_Actions::class, 'handle_create_user_manual']);
        add_action('admin_post_cbt_update_user_manual', [CBT_Admin_Users_Actions::class, 'handle_update_user_manual']);
        add_action('admin_post_cbt_delete_user_manual', [CBT_Admin_Users_Actions::class, 'handle_delete_user_manual']);
        add_action('admin_post_cbt_bulk_delete_users', [CBT_Admin_Users_Actions::class, 'handle_bulk_delete_users']);
        add_action('admin_post_cbt_download_user_template', [CBT_Admin_Users_Actions::class, 'handle_download_user_template']);
        add_action('admin_post_cbt_download_user_template_xlsx', [CBT_Admin_Users_Actions::class, 'handle_download_user_template_xlsx']);
    }

    public static function render_exams_page(): void
    {
        CBT_Admin_Exams_Page::render();
    }

    public static function handle_save_exam(): void
    {
        CBT_Admin_Exams_Service::handle_save_exam();
    }

    public static function handle_delete_exam(): void
    {
        CBT_Admin_Exams_Service::handle_delete_exam();
    }

    public static function handle_start_exam_save_progress(): void
    {
        CBT_Admin_Exams_Service::handle_start_exam_save_progress();
    }

    public static function handle_continue_exam_save_progress(): void
    {
        CBT_Admin_Exams_Service::handle_continue_exam_save_progress();
    }

    private static function redirect_exam_with_error(string $message, int $edit_id = 0): void
    {
        $args = self::add_exam_list_state_args([
            'page' => 'cbt-exams',
            'cbt_err' => $message,
        ], self::get_exam_list_state_from_request($_POST));
        if ($edit_id > 0) {
            $args['edit'] = $edit_id;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    /**
     * @return string[]
     */
    /**
     * @return array<int,array{text:string,answer:string}>
     */
    private static function build_minimal_docx_document_xml(array $lines): string
    {
        // Keep template as table layout, but lock widths within printable page area.
        $col_left = 2300;
        $col_right = 7000;

        $build_cell = static function (string $text, int $width, array $options = []): string {
            $is_header = !empty($options['header']);
            $is_bold = $is_header || !empty($options['bold']);
            $is_center = !empty($options['center']);
            $fill_color = isset($options['fill']) ? strtoupper(trim((string) $options['fill'])) : '';
            $text_color = isset($options['text_color']) ? strtoupper(trim((string) $options['text_color'])) : '';

            $safe = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($fill_color === '' && $is_header) {
                $fill_color = 'E9EEF5';
            }

            $cell_fill_xml = $fill_color !== ''
                ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill_color . '"/>'
                : '';

            $paragraph_align_xml = $is_center ? '<w:jc w:val="center"/>' : '';

            $run_prop = '';
            if ($is_bold || $text_color !== '') {
                $run_prop = '<w:rPr>';
                if ($is_bold) {
                    $run_prop .= '<w:b/>';
                }
                if ($text_color !== '') {
                    $run_prop .= '<w:color w:val="' . $text_color . '"/>';
                }
                $run_prop .= '</w:rPr>';
            }

            return
                '<w:tc>'
                . '<w:tcPr>'
                . '<w:tcW w:w="' . (string) $width . '" w:type="dxa"/>'
                . '<w:vAlign w:val="top"/>'
                . $cell_fill_xml
                . '</w:tcPr>'
                . '<w:p>'
                . '<w:pPr><w:spacing w:before="0" w:after="60" w:line="276" w:lineRule="auto"/>' . $paragraph_align_xml . '</w:pPr>'
                . '<w:r>'
                . $run_prop
                . '<w:t xml:space="preserve">' . $safe . '</w:t>'
                . '</w:r>'
                . '</w:p>'
                . '</w:tc>';
        };

        $table_rows = [];
        $table_rows[] =
            '<w:tr>'
            . $build_cell('FIELD', $col_left, ['header' => true])
            . $build_cell('VALUE', $col_right, ['header' => true])
            . '</w:tr>';

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $left = '';
            $right = '';

            if ($line === '') {
                $table_rows[] = '<w:tr>' . $build_cell('', $col_left) . $build_cell('', $col_right) . '</w:tr>';
                continue;
            }

            if ($line === '---') {
                $table_rows[] =
                    '<w:tr>'
                    . $build_cell('', $col_left, ['fill' => 'FFF2CC'])
                    . $build_cell('---', $col_right, ['fill' => 'FFF2CC', 'bold' => true, 'center' => true, 'text_color' => '7F6000'])
                    . '</w:tr>';
                continue;
            }

            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $left = trim((string) ($parts[0] ?? ''));
                $right = ltrim((string) ($parts[1] ?? ''));
            } else {
                $right = $line;
            }

            $table_rows[] =
                '<w:tr>'
                . $build_cell($left, $col_left)
                . $build_cell($right, $col_right)
                . '</w:tr>';
        }

        $table =
            '<w:tbl>'
            . '<w:tblPr>'
            . '<w:tblW w:w="5000" w:type="pct"/>'
            . '<w:jc w:val="center"/>'
            . '<w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar>'
            . '<w:top w:w="60" w:type="dxa"/>'
            . '<w:left w:w="80" w:type="dxa"/>'
            . '<w:bottom w:w="60" w:type="dxa"/>'
            . '<w:right w:w="80" w:type="dxa"/>'
            . '</w:tblCellMar>'
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:left w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:right w:val="single" w:sz="8" w:space="0" w:color="808080"/>'
            . '<w:insideH w:val="single" w:sz="6" w:space="0" w:color="A6A6A6"/>'
            . '<w:insideV w:val="single" w:sz="6" w:space="0" w:color="A6A6A6"/>'
            . '</w:tblBorders>'
            . '</w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="' . (string) $col_left . '"/><w:gridCol w:w="' . (string) $col_right . '"/></w:tblGrid>'
            . implode('', $table_rows)
            . '</w:tbl>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . $table
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            . '</w:body></w:document>';
    }

    public static function handle_import_users(): void
    {
        CBT_Admin_Users_Service::handle_import_users();
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

    public static function handle_download_user_template(): void
    {
        CBT_Admin_Users_Service::handle_download_user_template();
    }
    public static function handle_download_user_template_xlsx(): void
    {
        CBT_Admin_Users_Service::handle_download_user_template_xlsx();
    }
    private static function upsert_user_from_row(array $row, array &$import_lookup = []): string
    {
        $name = sanitize_text_field($row['name'] ?? '');
        $raw_email = trim((string) ($row['email'] ?? ''));
        $email = sanitize_email($raw_email);
        $nisn = preg_replace('/\D+/', '', (string) ($row['nisn'] ?? ''));
        $raw_username = trim((string) ($row['username'] ?? ''));
        $raw_password = trim((string) ($row['password'] ?? ''));
        $raw_combined_username_password = trim((string) ($row['usernamepassword'] ?? ($row['username_password'] ?? '')));

        if (($raw_username === '' || $raw_password === '') && $raw_combined_username_password !== '') {
            $combined_parts = preg_split('/\s+|[:;|]/', $raw_combined_username_password, 2);
            if (is_array($combined_parts) && !empty($combined_parts)) {
                if ($raw_username === '') {
                    $raw_username = trim((string) ($combined_parts[0] ?? ''));
                }
                if ($raw_password === '') {
                    $raw_password = trim((string) ($combined_parts[1] ?? ''));
                }
            }
        }

        $role_raw = strtolower(sanitize_text_field($row['role'] ?? 'siswa'));
        $role = self::map_import_role($role_raw);
        $kode_kelas = sanitize_text_field($row['kode_kelas'] ?? '');
        $kode_ruang = sanitize_text_field($row['kode_ruang'] ?? '');
        $agama = sanitize_text_field($row['agama'] ?? '');
        $foto = esc_url_raw($row['foto'] ?? '');

        if (!is_email($email) && $nisn !== '') {
            $email = sanitize_email($nisn . '@student.sch.id');
        }

        if (!is_email($email)) {
            return 'failed';
        }

        $username = sanitize_user($raw_username, true);
        if ($username === '') {
            $parts = explode('@', $email);
            $username = sanitize_user((string) ($parts[0] ?? ''), true);
        }

        if ($username === '') {
            return 'failed';
        }

        $password = $raw_password !== '' ? (string) $raw_password : wp_generate_password(12, true, true);
        $user_id = self::resolve_user_import_existing_id($email, $username, $import_lookup);

        if ($user_id > 0) {
            $existing_display_name = (string) ($import_lookup['by_id'][$user_id]['display_name'] ?? '');
            $display_name = $name !== '' ? $name : $existing_display_name;
            if ($display_name === '') {
                $display_name = $username;
            }

            $update_result = wp_update_user([
                'ID' => $user_id,
                'display_name' => $display_name,
                'role' => $role,
            ]);
            if (is_wp_error($update_result)) {
                return 'failed';
            }
            self::register_user_import_lookup($import_lookup, $user_id, $email, $username, $display_name);

            if ($raw_password !== '') {
                wp_set_password($password, $user_id);
                update_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, $password);
            }

            if ($kode_kelas !== '') {
                update_user_meta($user_id, 'kode_kelas', $kode_kelas);
            }
            if ($kode_ruang !== '') {
                update_user_meta($user_id, 'kode_ruang', $kode_ruang);
            }
            if ($agama !== '') {
                update_user_meta($user_id, 'agama', $agama);
            }
            if ($foto !== '') {
                update_user_meta($user_id, 'foto', $foto);
            } elseif (self::is_student_role($role)) {
                $existing_foto = trim((string) get_user_meta($user_id, 'foto', true));
                if ($existing_foto === '') {
                    update_user_meta($user_id, 'foto', self::get_default_student_photo_url());
                }
            }
            if ($nisn !== '') {
                update_user_meta($user_id, 'nisn', $nisn);
            }

            return 'updated';
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name !== '' ? $name : $username,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            return 'failed';
        }

        if ($kode_kelas !== '') {
            update_user_meta((int) $user_id, 'kode_kelas', $kode_kelas);
        }
        if ($kode_ruang !== '') {
            update_user_meta((int) $user_id, 'kode_ruang', $kode_ruang);
        }
        if ($agama !== '') {
            update_user_meta((int) $user_id, 'agama', $agama);
        }
        $foto = self::resolve_student_default_photo($role, $foto);
        if ($foto !== '') {
            update_user_meta((int) $user_id, 'foto', $foto);
        }
        if ($nisn !== '') {
            update_user_meta((int) $user_id, 'nisn', $nisn);
        }
        update_user_meta((int) $user_id, self::USER_META_PLAIN_PASSWORD, $password);
        self::register_user_import_lookup($import_lookup, (int) $user_id, $email, $username, $name !== '' ? $name : $username);

        return 'created';
    }

    /**
     * @return array{by_email:array<string,int>,by_login:array<string,int>,by_id:array<int,array{display_name:string}>}
     */
    private static function build_user_import_lookup(array $rows, int $offset, int $target_end): array
    {
        $lookup = [
            'by_email' => [],
            'by_login' => [],
            'by_id' => [],
        ];
        if ($target_end <= $offset) {
            return $lookup;
        }

        $emails = [];
        $logins = [];
        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? (array) $rows[$index] : [];
            $identity = self::extract_user_import_identity($row);
            if (($identity['email'] ?? '') !== '') {
                $email = (string) $identity['email'];
                $emails[self::normalize_user_import_lookup_key($email)] = $email;
            }
            if (($identity['username'] ?? '') !== '') {
                $username = (string) $identity['username'];
                $logins[self::normalize_user_import_lookup_key($username)] = $username;
            }
        }

        if (empty($emails) && empty($logins)) {
            return $lookup;
        }

        global $wpdb;
        $where_clauses = [];
        $params = [];
        if (!empty($emails)) {
            $email_placeholders = implode(',', array_fill(0, count($emails), '%s'));
            $where_clauses[] = "user_email IN ({$email_placeholders})";
            $params = array_merge($params, array_values($emails));
        }
        if (!empty($logins)) {
            $login_placeholders = implode(',', array_fill(0, count($logins), '%s'));
            $where_clauses[] = "user_login IN ({$login_placeholders})";
            $params = array_merge($params, array_values($logins));
        }

        if (empty($where_clauses)) {
            return $lookup;
        }

        $sql = "SELECT ID, user_email, user_login, display_name
                FROM {$wpdb->users}
                WHERE " . implode(' OR ', $where_clauses);
        $prepared_sql = $wpdb->prepare($sql, $params);
        $existing_rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        if (!is_array($existing_rows)) {
            return $lookup;
        }

        foreach ($existing_rows as $existing_row) {
            $row = (array) $existing_row;
            self::register_user_import_lookup(
                $lookup,
                isset($row['ID']) ? (int) $row['ID'] : 0,
                (string) ($row['user_email'] ?? ''),
                (string) ($row['user_login'] ?? ''),
                (string) ($row['display_name'] ?? '')
            );
        }

        return $lookup;
    }

    /**
     * @return array{email:string,username:string}
     */
    private static function extract_user_import_identity(array $row): array
    {
        $raw_email = trim((string) ($row['email'] ?? ''));
        $email = sanitize_email($raw_email);
        $nisn = preg_replace('/\D+/', '', (string) ($row['nisn'] ?? ''));
        if (!is_email($email) && $nisn !== '') {
            $email = sanitize_email($nisn . '@student.sch.id');
        }
        if (!is_email($email)) {
            $email = '';
        }

        $raw_username = trim((string) ($row['username'] ?? ''));
        $raw_combined_username_password = trim((string) ($row['usernamepassword'] ?? ($row['username_password'] ?? '')));
        if ($raw_username === '' && $raw_combined_username_password !== '') {
            $combined_parts = preg_split('/\s+|[:;|]/', $raw_combined_username_password, 2);
            if (is_array($combined_parts) && !empty($combined_parts)) {
                $raw_username = trim((string) ($combined_parts[0] ?? ''));
            }
        }

        $username = sanitize_user($raw_username, true);
        if ($username === '' && $email !== '') {
            $email_parts = explode('@', $email);
            $username = sanitize_user((string) ($email_parts[0] ?? ''), true);
        }
        if ($username === '') {
            $username = '';
        }

        return [
            'email' => $email,
            'username' => $username,
        ];
    }

    private static function resolve_user_import_existing_id(string $email, string $username, array &$lookup): int
    {
        $email_key = self::normalize_user_import_lookup_key($email);
        if ($email_key !== '' && isset($lookup['by_email'][$email_key])) {
            return (int) $lookup['by_email'][$email_key];
        }

        $username_key = self::normalize_user_import_lookup_key($username);
        if ($username_key !== '' && isset($lookup['by_login'][$username_key])) {
            return (int) $lookup['by_login'][$username_key];
        }

        $existing = false;
        if ($email !== '') {
            $existing = get_user_by('email', $email);
        }
        if (!($existing instanceof WP_User) && $username !== '') {
            $existing = get_user_by('login', $username);
        }
        if ($existing instanceof WP_User) {
            $resolved_id = (int) $existing->ID;
            self::register_user_import_lookup(
                $lookup,
                $resolved_id,
                (string) $existing->user_email,
                (string) $existing->user_login,
                (string) $existing->display_name
            );
            return $resolved_id;
        }

        return 0;
    }

    private static function register_user_import_lookup(
        array &$lookup,
        int $user_id,
        string $email,
        string $username,
        string $display_name = ''
    ): void {
        if ($user_id <= 0) {
            return;
        }

        if (!isset($lookup['by_email']) || !is_array($lookup['by_email'])) {
            $lookup['by_email'] = [];
        }
        if (!isset($lookup['by_login']) || !is_array($lookup['by_login'])) {
            $lookup['by_login'] = [];
        }
        if (!isset($lookup['by_id']) || !is_array($lookup['by_id'])) {
            $lookup['by_id'] = [];
        }

        $lookup['by_id'][$user_id] = [
            'display_name' => sanitize_text_field($display_name),
        ];

        $email_key = self::normalize_user_import_lookup_key($email);
        if ($email_key !== '') {
            $lookup['by_email'][$email_key] = $user_id;
        }
        $username_key = self::normalize_user_import_lookup_key($username);
        if ($username_key !== '') {
            $lookup['by_login'][$username_key] = $user_id;
        }
    }

    private static function normalize_user_import_lookup_key(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @return array<int,string>
     */
    private static function get_supported_agama_options(): array
    {
        return [
            'Islam',
            'Kristen Protestan',
            'Katolik',
            'Hindu',
            'Buddha',
            'Khonghucu',
        ];
    }

    private static function normalize_supported_agama(string $agama): string
    {
        $clean = sanitize_text_field($agama);
        if ($clean === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($clean)));
        if (!is_string($normalized)) {
            $normalized = strtolower(trim($clean));
        }

        $aliases = [
            'islam' => 'Islam',
            'muslim' => 'Islam',
            'kristen' => 'Kristen Protestan',
            'protestan' => 'Kristen Protestan',
            'kristen protestan' => 'Kristen Protestan',
            'katolik' => 'Katolik',
            'katholik' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha' => 'Buddha',
            'budha' => 'Buddha',
            'khonghucu' => 'Khonghucu',
            'konghucu' => 'Khonghucu',
        ];
        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return in_array($clean, self::get_supported_agama_options(), true) ? $clean : '';
    }

    private static function map_import_role(string $raw_role): string
    {
        switch ($raw_role) {
            case 'admin':
            case 'administrator':
            case 'admin_cbt':
                return 'administrator';

            case 'guru':
            case 'guru_cbt':
            case 'teacher':
            case 'editor':
                return 'guru_cbt';

            case 'siswa':
            case 'siswa_cbt':
            case 'student':
            case 'subscriber':
            default:
                return 'siswa_cbt';
        }
    }

    private static function is_student_role(string $role): bool
    {
        $normalized = strtolower(trim($role));
        return in_array($normalized, ['siswa', 'siswa_cbt', 'subscriber', 'student'], true);
    }

    private static function get_default_student_photo_url(): string
    {
        return esc_url_raw(CBT_EXAM_SYSTEM_URL . self::DEFAULT_STUDENT_PHOTO_RELATIVE_PATH);
    }

    private static function resolve_student_default_photo(string $role, string $foto): string
    {
        $clean_foto = esc_url_raw(trim($foto));
        if ($clean_foto !== '') {
            return $clean_foto;
        }
        if (!self::is_student_role($role)) {
            return '';
        }

        return self::get_default_student_photo_url();
    }

    /**
     * @param array<string,mixed> $request
     * @return array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string}
     */
    private static function get_exam_list_state_from_request(array $request): array
    {
        $status = isset($request['cbt_exam_status'])
            ? sanitize_key((string) wp_unslash($request['cbt_exam_status']))
            : '';
        if (!in_array($status, ['draft', 'published', 'closed'], true)) {
            $status = '';
        }

        return [
            'per_page' => self::normalize_standard_list_per_page(
                isset($request['cbt_exam_per_page']) ? absint(wp_unslash($request['cbt_exam_per_page'])) : 20
            ),
            'paged' => isset($request['cbt_exam_paged']) ? max(1, absint(wp_unslash($request['cbt_exam_paged']))) : 1,
            'search' => isset($request['cbt_exam_search'])
                ? sanitize_text_field((string) wp_unslash($request['cbt_exam_search']))
                : '',
            'status' => $status,
            'subject_id' => isset($request['cbt_exam_subject']) ? absint(wp_unslash($request['cbt_exam_subject'])) : 0,
            'kelas' => isset($request['cbt_exam_kelas'])
                ? strtoupper(sanitize_text_field((string) wp_unslash($request['cbt_exam_kelas'])))
                : '',
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $state
     * @return array<string,mixed>
     */
    private static function add_exam_list_state_args(array $args, array $state, bool $include_paged = true): array
    {
        $args['cbt_exam_per_page'] = self::normalize_standard_list_per_page((int) ($state['per_page'] ?? 20));
        if ($include_paged) {
            $args['cbt_exam_paged'] = max(1, (int) ($state['paged'] ?? 1));
        }
        if (($state['search'] ?? '') !== '') {
            $args['cbt_exam_search'] = (string) $state['search'];
        }
        if (($state['status'] ?? '') !== '') {
            $args['cbt_exam_status'] = (string) $state['status'];
        }
        if (!empty($state['subject_id'])) {
            $args['cbt_exam_subject'] = (int) $state['subject_id'];
        }
        if (($state['kelas'] ?? '') !== '') {
            $args['cbt_exam_kelas'] = (string) $state['kelas'];
        }

        return $args;
    }

    private static function normalize_standard_list_per_page(int $requested): int
    {
        $allowed = [20, 40, 60, 80, 100];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }

    private static function normalize_exam_builder_question_per_page(int $requested): int
    {
        $allowed = [50, 100, 150, 300];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 50;
    }

    private static function get_distinct_user_meta_values(string $meta_key): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND meta_value IS NOT NULL
               AND TRIM(meta_value) <> ''
             ORDER BY meta_value ASC",
            $meta_key
        );

        $rows = $wpdb->get_col($query);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_text_field', $rows), static function ($value) {
            return $value !== '';
        }));
    }

    private static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    private static function can_manage_subjects(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_subjects');
    }

    private static function can_manage_exams(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_exams');
    }

    private static function can_manage_users(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_users');
    }

    private static function get_exam_builder_selection_transient_key(string $builder_state_key, int $user_id): string
    {
        return 'cbt_exam_sel_' . md5($user_id . '|' . $builder_state_key);
    }

    /**
     * @param int[] $fallback_selected_ids
     * @return int[]
     */
    private static function get_exam_builder_selected_question_ids(string $builder_state_key, int $user_id, array $fallback_selected_ids = []): array
    {
        if ($builder_state_key === '' || $user_id <= 0) {
            return array_values(array_unique(array_filter(array_map('absint', $fallback_selected_ids))));
        }

        $saved_selected_ids = get_transient(self::get_exam_builder_selection_transient_key($builder_state_key, $user_id));
        if (!is_array($saved_selected_ids)) {
            return array_values(array_unique(array_filter(array_map('absint', $fallback_selected_ids))));
        }

        return array_values(array_unique(array_filter(array_map('absint', $saved_selected_ids))));
    }

    /**
     * @param int[] $selected_question_ids
     */
    private static function save_exam_builder_selected_question_ids(string $builder_state_key, int $user_id, array $selected_question_ids): void
    {
        if ($builder_state_key === '' || $user_id <= 0) {
            return;
        }

        set_transient(
            self::get_exam_builder_selection_transient_key($builder_state_key, $user_id),
            array_values(array_unique(array_filter(array_map('absint', $selected_question_ids)))),
            12 * HOUR_IN_SECONDS
        );
    }

    private static function clear_exam_builder_selection_state(string $builder_state_key, int $user_id): void
    {
        if ($builder_state_key === '' || $user_id <= 0) {
            return;
        }

        delete_transient(self::get_exam_builder_selection_transient_key($builder_state_key, $user_id));
    }

    /**
     * @return array{force_fullscreen:int,block_copy_paste:int,log_security_events:int}
     */
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
    private static function split_target_kelas_csv($raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $parts[] = trim((string) $item);
            }
        } else {
            $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', (string) $raw);
            $parts = array_map('trim', explode(',', $raw));
        }
        $items = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized = strtoupper(sanitize_text_field($part));
            if ($normalized === '') {
                continue;
            }
            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }

    private static function normalize_target_kelas_csv($raw): string
    {
        return implode(',', self::split_target_kelas_csv($raw));
    }

    private static function to_datetime_local(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timezone = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        if (!$dt) {
            $timestamp = strtotime($value);
            if (!$timestamp) {
                return '';
            }
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        if (!$dt) {
            return '';
        }

        return $dt->format('Y-m-d\TH:i');
    }

    private static function from_datetime_local(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone);
        if (!$dt) {
            $timestamp = strtotime($value);
            if (!$timestamp) {
                return null;
            }
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string,array<string,int|string>>
     */
}
