<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Subjects_Service
{
    public static function can_manage_subjects(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_subjects');
    }

    public static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $editing_id = isset($query['edit']) ? absint(wp_unslash((string) $query['edit'])) : 0;
        $editing = null;

        if ($editing_id > 0) {
            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $editing_id), ARRAY_A);
        }

        $subject_filter_rows = $wpdb->get_results(
            "SELECT id, name
             FROM {$table}
             ORDER BY name ASC",
            ARRAY_A
        );
        $subject_filter_options = [];
        foreach ((array) $subject_filter_rows as $subject_filter_row) {
            $subject_filter_id = (int) ($subject_filter_row['id'] ?? 0);
            $subject_filter_name = trim((string) ($subject_filter_row['name'] ?? ''));
            if ($subject_filter_id <= 0 || $subject_filter_name === '') {
                continue;
            }
            $subject_filter_options[$subject_filter_id] = $subject_filter_name;
        }

        $subject_per_page = isset($query['cbt_subject_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash((string) $query['cbt_subject_per_page'])))
            : 20;
        $subject_filter_id = isset($query['cbt_subject_filter_id']) ? absint(wp_unslash((string) $query['cbt_subject_filter_id'])) : 0;
        if ($subject_filter_id > 0 && !isset($subject_filter_options[$subject_filter_id])) {
            $subject_filter_id = 0;
        }
        $subject_current_page = isset($query['cbt_subject_paged']) ? max(1, absint(wp_unslash((string) $query['cbt_subject_paged']))) : 1;
        $total_subjects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $subject_where_sql = '';
        $subject_where_params = [];
        if ($subject_filter_id > 0) {
            $subject_where_sql = " WHERE id = %d";
            $subject_where_params = [$subject_filter_id];
        }
        $filtered_subject_total = $subject_where_sql !== ''
            ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table}{$subject_where_sql}", ...$subject_where_params))
            : $total_subjects;
        $subject_total_pages = max(1, (int) ceil($filtered_subject_total / $subject_per_page));
        if ($subject_current_page > $subject_total_pages) {
            $subject_current_page = $subject_total_pages;
        }
        $subject_offset = ($subject_current_page - 1) * $subject_per_page;
        $subject_query_params = array_merge($subject_where_params, [$subject_per_page, $subject_offset]);
        $subjects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}{$subject_where_sql} ORDER BY name ASC LIMIT %d OFFSET %d",
                ...$subject_query_params
            ),
            ARRAY_A
        );
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $subject_import_token = isset($query['cbt_subject_import_token']) ? sanitize_key((string) wp_unslash((string) $query['cbt_subject_import_token'])) : '';
        $subject_import_state = null;
        $subject_import_total = 0;
        $subject_import_offset = 0;
        $subject_import_created = 0;
        $subject_import_updated = 0;
        $subject_import_failed = 0;
        $subject_import_progress_percent = 0.0;
        $subject_import_is_running = false;
        $subject_import_continue_url = '';
        if ($subject_import_token !== '') {
            $subject_import_state = self::get_subject_import_state_for_current_user($subject_import_token);
            if (is_array($subject_import_state)) {
                $subject_import_total = max(0, isset($subject_import_state['total']) ? (int) $subject_import_state['total'] : 0);
                $subject_import_offset = max(0, isset($subject_import_state['offset']) ? (int) $subject_import_state['offset'] : 0);
                if ($subject_import_total > 0 && $subject_import_offset > $subject_import_total) {
                    $subject_import_offset = $subject_import_total;
                }
                $subject_import_created = max(0, isset($subject_import_state['created']) ? (int) $subject_import_state['created'] : 0);
                $subject_import_updated = max(0, isset($subject_import_state['updated']) ? (int) $subject_import_state['updated'] : 0);
                $subject_import_failed = max(0, isset($subject_import_state['failed']) ? (int) $subject_import_state['failed'] : 0);
                $subject_import_progress_percent = $subject_import_total > 0
                    ? round(((float) $subject_import_offset / (float) $subject_import_total) * 100, 2)
                    : 0.0;
                $subject_import_is_running = $subject_import_total > 0 && $subject_import_offset < $subject_import_total;
                $subject_import_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_import_subjects',
                        'cbt_subject_import_token' => $subject_import_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi import subject tidak ditemukan atau sudah berakhir. Silakan upload ulang file.';
            }
        }
        $default_subject_tab = 'list';
        if ($total_subjects === 0) {
            $default_subject_tab = 'form';
        }
        if (is_array($subject_import_state)) {
            $default_subject_tab = 'import';
        }
        if (!empty($editing)) {
            $default_subject_tab = 'form';
        }
        $subject_tab_is_forced = !empty($editing) || is_array($subject_import_state);
        $subject_list_query_args = [
            'page' => 'cbt-subjects',
            'cbt_subject_per_page' => $subject_per_page,
        ];
        if ($subject_filter_id > 0) {
            $subject_list_query_args['cbt_subject_filter_id'] = $subject_filter_id;
        }
        $subject_clear_edit_query_args = $subject_list_query_args;
        $subject_clear_edit_query_args['cbt_subject_paged'] = $subject_current_page;
        $subject_clear_edit_url = add_query_arg(
            $subject_clear_edit_query_args,
            admin_url('admin.php')
        );
        $subject_reset_filter_url = admin_url('admin.php?page=cbt-subjects');
        $subject_list_chip_label = $filtered_subject_total === $total_subjects
            ? sprintf('%d total', $total_subjects)
            : sprintf('%d hasil dari %d', $filtered_subject_total, $total_subjects);
        $subject_list_total_label = $filtered_subject_total === $total_subjects
            ? sprintf('Total subject: %d', $total_subjects)
            : sprintf('Total subject: %d dari %d', $filtered_subject_total, $total_subjects);
        $subject_pagination_links = [];
        if ($subject_total_pages > 1) {
            $subject_pagination_links = paginate_links([
                'base' => add_query_arg(
                    array_merge($subject_list_query_args, ['cbt_subject_paged' => '%#%']),
                    admin_url('admin.php')
                ),
                'format' => '',
                'current' => $subject_current_page,
                'total' => $subject_total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }

        return compact(
            'default_subject_tab',
            'editing',
            'error',
            'notice',
            'subject_clear_edit_url',
            'subject_current_page',
            'subject_filter_id',
            'subject_filter_options',
            'subject_import_continue_url',
            'subject_import_created',
            'subject_import_failed',
            'subject_import_is_running',
            'subject_import_offset',
            'subject_import_progress_percent',
            'subject_import_state',
            'subject_import_token',
            'subject_import_total',
            'subject_import_updated',
            'subject_list_chip_label',
            'subject_list_query_args',
            'subject_list_total_label',
            'subject_pagination_links',
            'subject_per_page',
            'subject_reset_filter_url',
            'subject_tab_is_forced',
            'subject_total_pages',
            'subjects',
            'total_subjects'
        ) + [
            'filtered_subject_total' => $filtered_subject_total,
        ];
    }

    public static function normalize_standard_list_per_page(int $requested): int
    {
        $allowed = [20, 40, 60, 80, 100];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }

    public static function prepare_runtime_for_bulk_import(): void
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

    /**
     * @param array<string,mixed> $file
     * @return array{token:string}|WP_Error
     */
    public static function start_import(array $file)
    {
        $tmp_path = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $original_name = isset($file['name']) ? (string) $file['name'] : '';
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error_code !== UPLOAD_ERR_OK || $tmp_path === '') {
            return new WP_Error('subject_upload_failed', 'Upload file gagal.');
        }

        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            return new WP_Error('subject_extension_invalid', 'Format file harus CSV atau XLSX.');
        }

        $rows = ($extension === 'xlsx')
            ? self::parse_subject_xlsx($tmp_path)
            : self::parse_subject_csv($tmp_path);

        if (is_wp_error($rows)) {
            return $rows;
        }
        if (!is_array($rows) || empty($rows)) {
            return new WP_Error('subject_rows_empty', 'Tidak ada data subject yang bisa diproses.');
        }
        $rows_validation = self::validate_subject_import_rows($rows);
        if (is_wp_error($rows_validation)) {
            return $rows_validation;
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $state = [
            'total' => count($rows),
            'offset' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'user_id' => get_current_user_id(),
            'started_at' => time(),
        ];
        $rows_saved = set_transient(self::get_subject_import_rows_key($token), array_values($rows), 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient(self::get_subject_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            self::clear_subject_import_transients($token);
            return new WP_Error('subject_state_failed', 'Gagal menyiapkan sesi import subject.');
        }

        return [
            'token' => $token,
        ];
    }

    public static function save_subject_record(int $id, string $name_raw, string $code_raw, string $description_raw)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $name = self::normalize_subject_name($name_raw);
        $code = self::normalize_subject_code($code_raw);
        $description = self::normalize_subject_description($description_raw);

        if ($name === '') {
            return new WP_Error('subject_name_required', 'Nama mapel wajib diisi.');
        }

        if ($id > 0) {
            $existing_subject = self::find_subject_by_id($id);
            if ($existing_subject === null) {
                return new WP_Error('subject_not_found', 'Subject yang akan diupdate tidak ditemukan.');
            }
        }

        $duplicate_error = self::validate_subject_uniqueness($name, $code, $id);
        if ($duplicate_error !== '') {
            return new WP_Error('subject_duplicate', $duplicate_error);
        }

        $data = [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        ];

        if ($id > 0) {
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            if ($updated === false) {
                return new WP_Error('subject_update_failed', 'Gagal memperbarui subject.');
            }

            return [
                'status' => 'updated',
                'message' => 'Subject updated',
            ];
        }

        $data['created_at'] = current_time('mysql');
        $inserted = $wpdb->insert(
            $table,
            $data,
            ['%s', '%s', '%s', '%s', '%s']
        );
        if (!$inserted) {
            return new WP_Error('subject_create_failed', 'Gagal membuat subject.');
        }

        return [
            'status' => 'created',
            'message' => 'Subject created',
        ];
    }

    /**
     * @return array{status:string,token?:string,message?:string}|WP_Error
     */
    public static function continue_import(string $token)
    {
        $state = self::get_subject_import_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_subject_import_transients($token);
            return new WP_Error('subject_state_expired', 'Sesi import subject berakhir. Silakan upload ulang file.');
        }

        $rows = get_transient(self::get_subject_import_rows_key($token));
        if (!is_array($rows) || empty($rows)) {
            self::clear_subject_import_transients($token);
            return new WP_Error('subject_rows_missing', 'Data batch import subject tidak ditemukan. Silakan upload ulang file.');
        }

        $rows = array_values($rows);
        $total = isset($state['total']) ? (int) $state['total'] : count($rows);
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
        $created = isset($state['created']) ? (int) $state['created'] : 0;
        $updated = isset($state['updated']) ? (int) $state['updated'] : 0;
        $failed = isset($state['failed']) ? (int) $state['failed'] : 0;
        if ($total <= 0 || empty($rows)) {
            self::clear_subject_import_transients($token);
            return new WP_Error('subject_rows_empty', 'Data import subject kosong.');
        }

        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset > $total) {
            $offset = $total;
        }

        $batch_size = self::get_subject_import_batch_size();
        $max_batch_seconds = self::get_subject_import_max_batch_seconds();
        $target_end = min($offset + $batch_size, $total);
        $end = $offset;
        $batch_started_at = microtime(true);

        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? (array) $rows[$index] : [];

            try {
                $result = self::upsert_subject_from_row($row);
            } catch (Throwable $exception) {
                $result = 'failed';
            }

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $failed++;
            }

            $end = $index + 1;
            if (($end - $offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }

        $state['offset'] = max($offset, $end);
        $state['created'] = $created;
        $state['updated'] = $updated;
        $state['failed'] = $failed;

        if ($state['offset'] < $total) {
            $state_saved = set_transient(self::get_subject_import_state_key($token), $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                self::clear_subject_import_transients($token);
                return new WP_Error('subject_state_save_failed', 'Gagal menyimpan progres import subject.');
            }

            return [
                'status' => 'continue',
                'token' => $token,
            ];
        }

        self::clear_subject_import_transients($token);
        if ($created > 0 || $updated > 0) {
            CBT_Cache::invalidate_catalog();
        }

        return [
            'status' => 'complete',
            'message' => sprintf(
                'Import subjects selesai. Total: %d, Created: %d, Updated: %d, Failed: %d',
                $total,
                $created,
                $updated,
                $failed
            ),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_subject_import_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_subject_import_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function parse_subject_csv(string $tmp_path)
    {
        $handle = fopen($tmp_path, 'rb');
        if ($handle === false) {
            return new WP_Error('csv_open_failed', 'Gagal membuka file CSV.');
        }

        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $header = self::normalize_subject_import_header($header);
        $header_check = self::validate_subject_import_header($header);
        if (is_wp_error($header_check)) {
            fclose($handle);
            return $header_check;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        if (empty($rows)) {
            return new WP_Error('csv_no_data', 'Tidak ada data subject di CSV.');
        }

        return $rows;
    }

    private static function parse_subject_xlsx(string $tmp_path)
    {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return new WP_Error(
                'xlsx_library_missing',
                'Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.'
            );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $raw_rows = $sheet->toArray('', false, false, false);
        } catch (Throwable $exception) {
            return new WP_Error('xlsx_read_failed', 'Gagal membaca file XLSX.');
        }

        if (!is_array($raw_rows) || empty($raw_rows)) {
            return new WP_Error('xlsx_empty', 'File XLSX kosong.');
        }

        $header = array_shift($raw_rows);
        if (!is_array($header)) {
            return new WP_Error('xlsx_header_invalid', 'Header XLSX tidak valid.');
        }

        $header = self::normalize_subject_import_header($header);
        $header_check = self::validate_subject_import_header($header);
        if (is_wp_error($header_check)) {
            return $header_check;
        }

        $rows = [];
        foreach ($raw_rows as $data) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            return new WP_Error('xlsx_no_data', 'Tidak ada data subject di XLSX.');
        }

        return $rows;
    }

    /**
     * @param array<int,mixed> $header
     * @return array<int,string>
     */
    private static function normalize_subject_import_header(array $header): array
    {
        return array_map(static function ($item): string {
            $clean = trim((string) $item);
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);
            return strtolower($clean);
        }, $header);
    }

    /**
     * @param array<int,string> $header
     * @return true|WP_Error
     */
    private static function validate_subject_import_header(array $header)
    {
        if (!in_array('name', $header, true)) {
            return new WP_Error('import_header_invalid', 'Header file tidak valid. Kolom name wajib ada.');
        }

        return true;
    }

    private static function upsert_subject_from_row(array $row): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_subjects';
        $name = self::normalize_subject_name((string) ($row['name'] ?? ''));
        $code = self::normalize_subject_code((string) ($row['code'] ?? ''));
        $description = self::normalize_subject_description((string) ($row['description'] ?? ''));

        if ($name === '') {
            return 'failed';
        }

        $existing_by_code = null;
        if ($code !== '') {
            $existing_by_code = self::find_subject_by_code($code);
        }
        $existing_by_name = self::find_subject_by_name($name);

        if ($existing_by_code !== null && $existing_by_name !== null && (int) ($existing_by_code['id'] ?? 0) !== (int) ($existing_by_name['id'] ?? 0)) {
            return 'failed';
        }

        $existing = $existing_by_code ?? $existing_by_name;

        $data = [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing && isset($existing['id'])) {
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            return $updated === false ? 'failed' : 'updated';
        }

        $data['created_at'] = current_time('mysql');
        $inserted = $wpdb->insert(
            $table,
            $data,
            ['%s', '%s', '%s', '%s', '%s']
        );

        return $inserted ? 'created' : 'failed';
    }

    private static function get_subject_import_state_key(string $token): string
    {
        return 'cbt_subject_import_' . $token;
    }

    private static function get_subject_import_rows_key(string $token): string
    {
        return 'cbt_subject_import_rows_' . $token;
    }

    private static function clear_subject_import_transients(string $token): void
    {
        delete_transient(self::get_subject_import_state_key($token));
        delete_transient(self::get_subject_import_rows_key($token));
    }

    private static function normalize_subject_name(string $raw_name): string
    {
        $name = sanitize_text_field($raw_name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return is_string($name) ? $name : '';
    }

    private static function normalize_subject_code(string $raw_code): string
    {
        $code = strtoupper(sanitize_key($raw_code));
        if (strlen($code) > 30) {
            $code = substr($code, 0, 30);
        }

        return $code;
    }

    private static function normalize_subject_description(string $raw_description): string
    {
        return sanitize_textarea_field($raw_description);
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return true|WP_Error
     */
    private static function validate_subject_import_rows(array $rows)
    {
        $seen_names = [];
        $seen_codes = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row_number = $index + 2;
            $name = self::normalize_subject_name((string) ($row['name'] ?? ''));
            $code = self::normalize_subject_code((string) ($row['code'] ?? ''));

            if ($name === '') {
                return new WP_Error('subject_import_name_required', sprintf('Baris %d: kolom name wajib diisi.', $row_number));
            }

            $name_key = strtolower($name);
            if (isset($seen_names[$name_key])) {
                return new WP_Error(
                    'subject_import_duplicate_name',
                    sprintf('Baris %1$d: name `%2$s` duplikat dengan baris %3$d pada file import.', $row_number, $name, (int) $seen_names[$name_key])
                );
            }
            $seen_names[$name_key] = $row_number;

            if ($code === '') {
                continue;
            }

            if (isset($seen_codes[$code])) {
                return new WP_Error(
                    'subject_import_duplicate_code',
                    sprintf('Baris %1$d: code `%2$s` duplikat dengan baris %3$d pada file import.', $row_number, $code, (int) $seen_codes[$code])
                );
            }
            $seen_codes[$code] = $row_number;
        }

        return true;
    }

    private static function validate_subject_uniqueness(string $name, string $code, int $exclude_id = 0): string
    {
        $duplicate_by_name = self::find_subject_by_name($name, $exclude_id);
        if ($duplicate_by_name !== null) {
            return 'Nama subject sudah terdaftar pada subject lain.';
        }

        if ($code === '') {
            return '';
        }

        $duplicate_by_code = self::find_subject_by_code($code, $exclude_id);
        if ($duplicate_by_code !== null) {
            return 'Code subject sudah terdaftar pada subject lain.';
        }

        return '';
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_subject_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cbt_subjects';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, name, code, description FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_subject_by_name(string $name, int $exclude_id = 0): ?array
    {
        if ($name === '') {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cbt_subjects';
        if ($exclude_id > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name, code, description FROM {$table} WHERE name = %s AND id <> %d ORDER BY id ASC LIMIT 1", $name, $exclude_id),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name, code, description FROM {$table} WHERE name = %s ORDER BY id ASC LIMIT 1", $name),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_subject_by_code(string $code, int $exclude_id = 0): ?array
    {
        if ($code === '') {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cbt_subjects';
        if ($exclude_id > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name, code, description FROM {$table} WHERE code = %s AND id <> %d ORDER BY id ASC LIMIT 1", $code, $exclude_id),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name, code, description FROM {$table} WHERE code = %s ORDER BY id ASC LIMIT 1", $code),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }

    private static function get_subject_import_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_subject_import_batch_size', 250);
        if ($batch_size < 25) {
            return 25;
        }
        if ($batch_size > 1000) {
            return 1000;
        }

        return $batch_size;
    }

    private static function get_subject_import_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_subject_import_batch_max_seconds', 10.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }
}
