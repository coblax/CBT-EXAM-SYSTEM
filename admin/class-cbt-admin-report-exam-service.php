<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Report_Exam_Service
{
    private const DEFAULT_STUDENT_PHOTO_RELATIVE_PATH = 'public/images/default-student-avatar.svg';

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
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $selected_exam_id = isset($query['cbt_exam_id']) ? absint($query['cbt_exam_id']) : 0;
        $selected_kelas = isset($query['cbt_result_kelas']) ? sanitize_text_field(wp_unslash((string) $query['cbt_result_kelas'])) : '';
        $incident_context = self::get_report_incident_context_from_request($query);
        $selected_incident_exam_id = (int) ($incident_context['exam_id'] ?? 0);
        $selected_incident_kelas = (string) ($incident_context['kelas'] ?? '');
        $selected_incident_ruang = (string) ($incident_context['ruang'] ?? '');
        $selected_incident_edit_id = (int) ($incident_context['edit_id'] ?? 0);
        $active_report_tab = self::normalize_report_exam_tab(isset($query['cbt_report_tab']) ? (string) wp_unslash($query['cbt_report_tab']) : '');

        $role_options = self::report_supervisor_role_options();
        $supervisor_inputs = [];
        for ($idx = 1; $idx <= 3; $idx++) {
            $name_key = 'supervisor_' . $idx . '_name';
            $nip_key = 'supervisor_' . $idx . '_nip';
            $role_key = 'supervisor_' . $idx . '_role';
            $supervisor_inputs[$idx] = [
                'name' => isset($query[$name_key]) ? trim(sanitize_text_field(wp_unslash((string) $query[$name_key]))) : '',
                'nip' => isset($query[$nip_key]) ? trim(sanitize_text_field(wp_unslash((string) $query[$nip_key]))) : '',
                'role' => isset($query[$role_key]) ? self::normalize_report_supervisor_role((string) wp_unslash($query[$role_key])) : '',
            ];
        }

        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';

        $exam_filter_rows = self::get_accessible_exam_filter_rows($is_admin_scope, $current_user_id);
        $kelas_filter_rows = self::get_distinct_user_meta_values('kode_kelas');
        $incident_exam = self::get_accessible_exam_row($selected_incident_exam_id, $is_admin_scope, $current_user_id);
        if ($selected_incident_exam_id > 0 && empty($incident_exam)) {
            $selected_incident_exam_id = 0;
            $selected_incident_kelas = '';
            $selected_incident_ruang = '';
            $selected_incident_edit_id = 0;
            $incident_context = [
                'exam_id' => 0,
                'kelas' => '',
                'ruang' => '',
                'edit_id' => 0,
            ];
        }

        $incident_kelas_options = !empty($incident_exam)
            ? self::get_report_incident_exam_kelas_options($incident_exam, $kelas_filter_rows)
            : [];
        if ($selected_incident_kelas !== '' && !in_array($selected_incident_kelas, $incident_kelas_options, true)) {
            $selected_incident_kelas = '';
            $incident_context['kelas'] = '';
        }

        $incident_base_student_rows = !empty($incident_exam)
            ? self::get_report_incident_student_rows($incident_exam, $selected_incident_kelas)
            : [];
        $incident_ruang_options = self::get_report_incident_ruang_options($incident_base_student_rows);
        if ($selected_incident_ruang !== '' && !in_array($selected_incident_ruang, $incident_ruang_options, true)) {
            $selected_incident_ruang = '';
            $incident_context['ruang'] = '';
        }

        $incident_student_rows = !empty($incident_exam)
            ? self::get_report_incident_student_rows($incident_exam, $selected_incident_kelas, $selected_incident_ruang)
            : [];
        $incident_current_staff = self::get_report_incident_current_staff_row($current_user_id);
        $incident_scope_filters = self::get_report_incident_scope_filters($is_admin_scope, $current_user_id);
        $incident_rows = $selected_incident_exam_id > 0
            ? CBT_Incident_Report::get_rows($selected_incident_exam_id, $selected_incident_kelas, $selected_incident_ruang, $incident_scope_filters)
            : [];

        $editing_incident = [];
        if ($selected_incident_edit_id > 0) {
            $editing_incident = CBT_Incident_Report::get_row($selected_incident_edit_id, $incident_scope_filters);
            $edit_exam_id = (int) ($editing_incident['exam_id'] ?? 0);
            $edit_student_id = (int) ($editing_incident['student_id'] ?? 0);
            if (
                empty($editing_incident)
                || $selected_incident_exam_id <= 0
                || $edit_exam_id !== $selected_incident_exam_id
                || empty(self::get_report_incident_student_row_by_id($edit_student_id, $incident_student_rows))
            ) {
                $editing_incident = [];
                $selected_incident_edit_id = 0;
                $incident_context['edit_id'] = 0;
            }
        }

        $incident_form_staff_user_id = (int) ($incident_current_staff['id'] ?? 0);
        $incident_form_student_id = !empty($editing_incident) ? (int) ($editing_incident['student_id'] ?? 0) : 0;
        $incident_form_type = !empty($editing_incident) ? (string) ($editing_incident['incident_type'] ?? '') : '';
        $incident_form_notes = !empty($editing_incident) ? trim((string) ($editing_incident['notes'] ?? '')) : '';
        $incident_form_note_options = CBT_Incident_Report::incident_note_options_for_type($incident_form_type);
        $incident_form_note_value = '';
        $incident_form_note_custom = '';
        if ($incident_form_notes !== '') {
            if (in_array($incident_form_notes, $incident_form_note_options, true)) {
                $incident_form_note_value = $incident_form_notes;
            } else {
                $incident_form_note_value = CBT_Incident_Report::custom_note_option_value();
                $incident_form_note_custom = $incident_form_notes;
            }
        }

        $incident_selected_student_row = self::get_report_incident_student_row_by_id($incident_form_student_id, $incident_student_rows);
        $incident_student_placeholder_photo = self::resolve_student_default_photo('siswa_cbt', '');
        $incident_student_picker_name = !empty($incident_selected_student_row['name'])
            ? (string) $incident_selected_student_row['name']
            : (!empty($incident_student_rows) ? 'Pilih peserta' : 'Tidak ada peserta sesuai filter');
        $incident_student_picker_meta_parts = [];
        if (!empty($incident_selected_student_row['kelas'])) {
            $incident_student_picker_meta_parts[] = 'K: ' . (string) $incident_selected_student_row['kelas'];
        }
        if (!empty($incident_selected_student_row['ruang'])) {
            $incident_student_picker_meta_parts[] = 'R: ' . (string) $incident_selected_student_row['ruang'];
        }
        $incident_student_picker_meta = !empty($incident_student_picker_meta_parts)
            ? implode(' • ', $incident_student_picker_meta_parts)
            : (!empty($incident_student_rows) ? 'Foto dan identitas peserta akan tampil di sini.' : 'Coba ubah filter exam, kelas, atau ruang untuk memuat peserta.');
        $incident_student_picker_photo = !empty($incident_selected_student_row['foto'])
            ? (string) $incident_selected_student_row['foto']
            : $incident_student_placeholder_photo;
        $is_editing_incident = !empty($editing_incident);
        $incident_reset_url = add_query_arg(
            [
                'page' => 'cbt-report-exam',
                'cbt_report_tab' => 'incident-report',
            ],
            admin_url('admin.php')
        );

        $selected_exam_label = 'Belum dipilih';
        foreach ($exam_filter_rows as $exam_filter_row) {
            if ((int) ($exam_filter_row['id'] ?? 0) === $selected_exam_id) {
                $selected_exam_label = (string) ($exam_filter_row['title'] ?? 'Belum dipilih');
                break;
            }
        }

        $reset_url = add_query_arg(
            [
                'page' => 'cbt-report-exam',
                'cbt_report_tab' => 'filter-export-report',
            ],
            admin_url('admin.php')
        );

        return compact(
            'active_report_tab',
            'error',
            'exam_filter_rows',
            'is_admin_scope',
            'incident_context',
            'incident_current_staff',
            'incident_exam',
            'incident_form_note_custom',
            'incident_form_note_options',
            'incident_form_note_value',
            'incident_form_staff_user_id',
            'incident_form_student_id',
            'incident_form_type',
            'incident_kelas_options',
            'incident_reset_url',
            'incident_rows',
            'incident_ruang_options',
            'incident_selected_student_row',
            'incident_student_picker_meta',
            'incident_student_picker_name',
            'incident_student_picker_photo',
            'incident_student_placeholder_photo',
            'incident_student_rows',
            'is_editing_incident',
            'kelas_filter_rows',
            'notice',
            'reset_url',
            'role_options',
            'selected_exam_id',
            'selected_exam_label',
            'selected_incident_edit_id',
            'selected_incident_exam_id',
            'selected_incident_kelas',
            'selected_incident_ruang',
            'selected_kelas',
            'supervisor_inputs'
        ) + [
            'editing_incident' => $editing_incident,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>|WP_Error
     */
    public static function build_print_context(array $source)
    {
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $exam_id = isset($source['cbt_exam_id']) ? absint(wp_unslash((string) $source['cbt_exam_id'])) : 0;
        $selected_kelas = isset($source['cbt_result_kelas']) ? trim(sanitize_text_field(wp_unslash((string) $source['cbt_result_kelas']))) : '';

        $supervisor_inputs = [];
        for ($idx = 1; $idx <= 3; $idx++) {
            $supervisor_inputs[$idx] = self::extract_report_supervisor_input('supervisor_' . $idx, $source);
        }
        $supervisor_1 = $supervisor_inputs[1];
        $redirect_args = self::build_export_redirect_args($source, $exam_id, $selected_kelas, $supervisor_inputs);

        if ($exam_id <= 0) {
            return new WP_Error('report_exam_required', 'Exam wajib dipilih.', ['redirect_args' => $redirect_args]);
        }

        $exam = self::get_accessible_exam_row($exam_id, $is_admin_scope, $current_user_id);
        if (empty($exam)) {
            return new WP_Error('report_exam_invalid', 'Exam tidak ditemukan atau tidak bisa diakses.', ['redirect_args' => $redirect_args]);
        }

        if ($supervisor_1['name'] === '' || $supervisor_1['nip'] === '' || $supervisor_1['role'] === '') {
            return new WP_Error('report_supervisor_required', 'Data Petugas 1 wajib diisi lengkap (Nama, NIP, Jabatan).', ['redirect_args' => $redirect_args]);
        }

        $supervisors = [$supervisor_1];
        foreach ([2, 3] as $idx) {
            $supervisor = (array) ($supervisor_inputs[$idx] ?? []);
            $has_any = (($supervisor['name'] ?? '') !== '' || ($supervisor['nip'] ?? '') !== '' || ($supervisor['role'] ?? '') !== '');
            if ($has_any && (($supervisor['name'] ?? '') === '' || ($supervisor['nip'] ?? '') === '' || ($supervisor['role'] ?? '') === '')) {
                return new WP_Error('report_supervisor_partial', 'Jika data Petugas ' . $idx . ' diisi, semua field Petugas ' . $idx . ' wajib lengkap.', ['redirect_args' => $redirect_args]);
            }
            if ($has_any) {
                $supervisors[] = $supervisor;
            }
        }

        $report_rows = self::get_exam_report_rows($exam_id, $selected_kelas, $is_admin_scope, $current_user_id);
        $incident_report_rows = self::get_exam_report_incident_rows(
            $exam_id,
            $selected_kelas,
            self::get_report_incident_scope_filters($is_admin_scope, $current_user_id)
        );
        $registered_student_total = count($report_rows);
        $present_student_total = count(array_filter($report_rows, static function (array $row): bool {
            return !empty($row['is_present']);
        }));
        $absent_student_total = max(0, $registered_student_total - $present_student_total);

        $branding = CBT_Admin_Branding_Settings::get_print_context();
        $site_name = trim((string) ($branding['school_name'] ?? ''));
        $site_motto = trim((string) ($branding['school_motto'] ?? ''));
        $site_npsn = trim((string) ($branding['school_npsn'] ?? ''));
        $site_address = trim((string) ($branding['school_address'] ?? ''));
        $site_village = trim((string) ($branding['school_village'] ?? ''));
        $site_district_city_ln = trim((string) ($branding['school_district_city_ln'] ?? ''));
        $site_regency_country_ln = trim((string) ($branding['school_regency_country_ln'] ?? ''));
        $site_regency_country_ln_is_city = !empty($branding['school_regency_country_ln_is_city']);
        $site_province_abroad_ln = trim((string) ($branding['school_province_abroad_ln'] ?? ''));
        $site_province_abroad_ln_is_foreign = !empty($branding['school_province_abroad_ln_is_foreign']);
        if ($site_name === '') {
            $site_name = trim((string) get_bloginfo('name'));
        }
        if ($site_name === '') {
            $site_name = 'CBT Exam';
        }

        $exam_program_name = trim((string) ($branding['exam_program_name'] ?? ''));
        $report_program_title = $exam_program_name !== '' ? $exam_program_name : 'Ujian CBT';
        $report_logo_1_url = (string) ($branding['logo_1_url'] ?? '');
        $report_logo_2_url = (string) ($branding['logo_2_url'] ?? '');
        $report_header_address_line = self::build_report_header_address_line($site_address);
        $report_header_region_line = self::build_report_header_region_line(
            $site_village,
            $site_district_city_ln,
            $site_regency_country_ln,
            $site_province_abroad_ln,
            $site_regency_country_ln_is_city,
            $site_province_abroad_ln_is_foreign
        );

        $subject_label = trim((string) ($exam['subject_name'] ?? ''));
        $exam_start_raw = trim((string) ($exam['starts_at'] ?? ''));
        $exam_day_label = '';
        $exam_date_label = '';
        $exam_start_time_label = '';
        if ($exam_start_raw !== '' && $exam_start_raw !== '0000-00-00 00:00:00') {
            $exam_timestamp = strtotime($exam_start_raw);
            if ($exam_timestamp !== false) {
                $timezone = wp_timezone();
                $exam_day_label = self::translate_exam_card_weekday((string) wp_date('l', $exam_timestamp, $timezone));
                $exam_date_label = self::format_exam_card_indonesian_date((int) $exam_timestamp);
                $exam_start_time_label = wp_date('H:i', $exam_timestamp, $timezone);
            }
        }

        $signature_role_labels = self::build_report_supervisor_role_labels($supervisors);
        $back_args = [
            'page' => 'cbt-report-exam',
            'cbt_report_tab' => 'filter-export-report',
            'cbt_exam_id' => $exam_id,
        ];
        if ($selected_kelas !== '') {
            $back_args['cbt_result_kelas'] = $selected_kelas;
        }
        $back_url = add_query_arg($back_args, admin_url('admin.php'));

        return compact(
            'absent_student_total',
            'back_url',
            'exam',
            'exam_date_label',
            'exam_day_label',
            'exam_start_time_label',
            'incident_report_rows',
            'present_student_total',
            'registered_student_total',
            'report_header_address_line',
            'report_header_region_line',
            'report_logo_1_url',
            'report_logo_2_url',
            'report_program_title',
            'report_rows',
            'selected_kelas',
            'signature_role_labels',
            'site_motto',
            'site_name',
            'site_npsn',
            'subject_label',
            'supervisors'
        );
    }

    public static function normalize_report_exam_tab(string $raw): string
    {
        $tab = sanitize_key($raw);
        if (!in_array($tab, ['incident-report', 'filter-export-report'], true)) {
            return 'incident-report';
        }

        return $tab;
    }

    /**
     * @param array<string,mixed> $source
     * @return array{exam_id:int,kelas:string,ruang:string,edit_id:int}
     */
    public static function get_report_incident_context_from_request(array $source): array
    {
        $exam_id = isset($source['cbt_incident_exam_id']) ? absint(wp_unslash((string) $source['cbt_incident_exam_id'])) : 0;
        $kelas = isset($source['cbt_incident_kelas']) ? strtoupper(sanitize_text_field(wp_unslash((string) $source['cbt_incident_kelas']))) : '';
        $ruang = isset($source['cbt_incident_ruang']) ? strtoupper(sanitize_text_field(wp_unslash((string) $source['cbt_incident_ruang']))) : '';
        $edit_id = isset($source['cbt_incident_edit_id']) ? absint(wp_unslash((string) $source['cbt_incident_edit_id'])) : 0;

        return [
            'exam_id' => $exam_id,
            'kelas' => $kelas,
            'ruang' => $ruang,
            'edit_id' => $edit_id,
        ];
    }

    /**
     * @return array{id:int,name:string,role_label:string,label:string}
     */
    public static function get_report_incident_current_staff_row(int $current_user_id): array
    {
        if ($current_user_id <= 0) {
            return [];
        }

        $user = get_userdata($current_user_id);
        if (!($user instanceof WP_User)) {
            return [];
        }

        $role_label = self::get_report_incident_staff_role_label((array) $user->roles);
        if ($role_label === '' && !current_user_can('cbt_view_results')) {
            return [];
        }

        $display_name = trim((string) $user->display_name);
        if ($display_name === '') {
            $display_name = (string) $user->user_login;
        }

        return [
            'id' => $current_user_id,
            'name' => $display_name,
            'role_label' => $role_label,
            'label' => $display_name . ($role_label !== '' ? ' • ' . $role_label : ''),
        ];
    }

    /**
     * @param string[] $roles
     */
    private static function get_report_incident_staff_role_label(array $roles): string
    {
        $normalized_roles = array_map(static function ($role): string {
            return strtolower(trim((string) $role));
        }, $roles);

        foreach ($normalized_roles as $role) {
            if (in_array($role, ['administrator', 'admin_cbt'], true)) {
                return 'Admin';
            }
            if (in_array($role, ['guru_cbt', 'editor'], true)) {
                return 'Guru';
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $exam
     * @param string[] $global_kelas_options
     * @return string[]
     */
    public static function get_report_incident_exam_kelas_options(array $exam, array $global_kelas_options): array
    {
        $target_kelas = self::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
        if (!empty($target_kelas)) {
            return $target_kelas;
        }

        $kelas_options = [];
        foreach ($global_kelas_options as $kelas_option) {
            $normalized = strtoupper(sanitize_text_field((string) $kelas_option));
            if ($normalized === '') {
                continue;
            }
            $kelas_options[$normalized] = $normalized;
        }

        $kelas_options = array_values($kelas_options);
        sort($kelas_options, SORT_NATURAL | SORT_FLAG_CASE);

        return $kelas_options;
    }

    /**
     * @param array<string,mixed> $exam
     * @return array<int,array{id:int,name:string,username:string,kelas:string,ruang:string,label:string,foto:string}>
     */
    public static function get_report_incident_student_rows(array $exam, string $selected_kelas = '', string $selected_ruang = ''): array
    {
        if (empty($exam) || empty($exam['id'])) {
            return [];
        }

        $selected_kelas = strtoupper(trim(sanitize_text_field($selected_kelas)));
        $selected_ruang = strtoupper(trim(sanitize_text_field($selected_ruang)));
        $target_kelas = self::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
        if ($selected_kelas !== '' && !empty($target_kelas) && !in_array($selected_kelas, $target_kelas, true)) {
            return [];
        }

        $args = [
            'role__in' => ['siswa_cbt', 'subscriber'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all',
        ];

        $meta_query = [];
        if ($selected_kelas !== '') {
            $meta_query[] = [
                'key' => 'kode_kelas',
                'value' => $selected_kelas,
                'compare' => '=',
            ];
        } elseif (count($target_kelas) === 1) {
            $meta_query[] = [
                'key' => 'kode_kelas',
                'value' => (string) $target_kelas[0],
                'compare' => '=',
            ];
        } elseif (count($target_kelas) > 1) {
            $or_meta_query = ['relation' => 'OR'];
            foreach ($target_kelas as $kelas_code) {
                $or_meta_query[] = [
                    'key' => 'kode_kelas',
                    'value' => (string) $kelas_code,
                    'compare' => '=',
                ];
            }
            $meta_query[] = $or_meta_query;
        }

        if ($selected_ruang !== '') {
            $meta_query[] = [
                'key' => 'kode_ruang',
                'value' => $selected_ruang,
                'compare' => '=',
            ];
        }

        if (!empty($meta_query)) {
            if (count($meta_query) === 1) {
                $single_meta_query = $meta_query[0];
                $args['meta_query'] = isset($single_meta_query['key']) ? [$single_meta_query] : $single_meta_query;
            } else {
                $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
            }
        }

        $users = get_users($args);
        if (!is_array($users)) {
            return [];
        }

        $rows = [];
        foreach ($users as $user) {
            if (!($user instanceof WP_User)) {
                continue;
            }

            $user_id = (int) $user->ID;
            if ($user_id <= 0) {
                continue;
            }

            $display_name = trim((string) $user->display_name);
            if ($display_name === '') {
                $display_name = (string) $user->user_login;
            }

            $kelas = strtoupper(trim((string) get_user_meta($user_id, 'kode_kelas', true)));
            if ($selected_kelas !== '' && $kelas !== $selected_kelas) {
                continue;
            }
            if (!empty($target_kelas) && $kelas !== '' && !in_array($kelas, $target_kelas, true)) {
                continue;
            }

            $ruang = strtoupper(trim((string) get_user_meta($user_id, 'kode_ruang', true)));
            if ($selected_ruang !== '' && $ruang !== $selected_ruang) {
                continue;
            }

            $role = isset($user->roles[0]) ? (string) $user->roles[0] : '';
            $foto = self::resolve_student_default_photo($role, (string) get_user_meta($user_id, 'foto', true));
            $label_parts = [$display_name];
            if ($kelas !== '') {
                $label_parts[] = 'K: ' . $kelas;
            }
            if ($ruang !== '') {
                $label_parts[] = 'R: ' . $ruang;
            }

            $rows[] = [
                'id' => $user_id,
                'name' => $display_name,
                'username' => (string) $user->user_login,
                'kelas' => $kelas,
                'ruang' => $ruang,
                'label' => implode(' • ', $label_parts),
                'foto' => $foto,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $kelas_compare = strnatcasecmp((string) ($left['kelas'] ?? ''), (string) ($right['kelas'] ?? ''));
            if ($kelas_compare !== 0) {
                return $kelas_compare;
            }

            return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<int,array{id:int,name:string,username:string,kelas:string,ruang:string,label:string,foto:string}> $student_rows
     * @return array<int,string>
     */
    public static function get_report_incident_ruang_options(array $student_rows): array
    {
        $ruang_options = [];
        foreach ($student_rows as $student_row) {
            $ruang = strtoupper(trim((string) ($student_row['ruang'] ?? '')));
            if ($ruang === '') {
                continue;
            }
            $ruang_options[$ruang] = $ruang;
        }

        $ruang_options = array_values($ruang_options);
        sort($ruang_options, SORT_NATURAL | SORT_FLAG_CASE);

        return $ruang_options;
    }

    /**
     * @param array<int,array{id:int,name:string,username:string,kelas:string,ruang:string,label:string,foto:string}> $student_rows
     * @return array{id:int,name:string,username:string,kelas:string,ruang:string,label:string,foto:string}
     */
    public static function get_report_incident_student_row_by_id(int $student_id, array $student_rows): array
    {
        foreach ($student_rows as $student_row) {
            if ((int) ($student_row['id'] ?? 0) === $student_id) {
                return $student_row;
            }
        }

        return [];
    }

    public static function format_report_incident_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timezone = wp_timezone();
        $datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        if (!$datetime) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return $value;
            }
            $datetime = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        return $datetime->format('Y-m-d H:i');
    }

    /**
     * @return array{teacher_id?:int}
     */
    public static function get_report_incident_scope_filters(bool $is_admin_scope, int $current_user_id): array
    {
        return $is_admin_scope ? [] : ['teacher_id' => $current_user_id];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>|WP_Error
     */
    public static function resolve_report_incident_submission(array $source, bool $is_admin_scope, int $current_user_id)
    {
        $context = self::get_report_incident_context_from_request($source);
        $exam_id = (int) ($context['exam_id'] ?? 0);
        if ($exam_id <= 0) {
            return new WP_Error('incident_exam_required', 'Exam wajib dipilih.');
        }

        $exam = self::get_accessible_exam_row($exam_id, $is_admin_scope, $current_user_id);
        if (empty($exam)) {
            return new WP_Error('incident_exam_invalid', 'Exam tidak ditemukan atau tidak bisa diakses.');
        }

        $selected_kelas = (string) ($context['kelas'] ?? '');
        $incident_kelas_options = self::get_report_incident_exam_kelas_options($exam, self::get_distinct_user_meta_values('kode_kelas'));
        if ($selected_kelas !== '' && !in_array($selected_kelas, $incident_kelas_options, true)) {
            return new WP_Error('incident_kelas_invalid', 'Filter kelas incident tidak valid untuk exam ini.');
        }

        $selected_ruang = (string) ($context['ruang'] ?? '');
        $incident_ruang_options = self::get_report_incident_ruang_options(self::get_report_incident_student_rows($exam, $selected_kelas));
        if ($selected_ruang !== '' && !in_array($selected_ruang, $incident_ruang_options, true)) {
            return new WP_Error('incident_ruang_invalid', 'Filter ruang incident tidak valid untuk exam ini.');
        }

        $student_rows = self::get_report_incident_student_rows($exam, $selected_kelas, $selected_ruang);
        $staff_row = self::get_report_incident_current_staff_row($current_user_id);
        if (empty($staff_row)) {
            return new WP_Error('incident_staff_invalid', 'Akun yang sedang login tidak valid sebagai petugas incident report.');
        }

        $student_id = isset($source['student_id']) ? absint(wp_unslash((string) $source['student_id'])) : 0;
        $student_row = self::get_report_incident_student_row_by_id($student_id, $student_rows);
        if ($student_id <= 0 || empty($student_row)) {
            return new WP_Error('incident_student_invalid', 'Peserta tidak valid atau tidak termasuk target exam saat ini.');
        }

        $incident_type = CBT_Incident_Report::normalize_incident_type(isset($source['incident_type']) ? (string) wp_unslash($source['incident_type']) : '');
        if ($incident_type === '') {
            return new WP_Error('incident_type_required', 'Jenis insiden wajib dipilih.');
        }

        $notes_selection = isset($source['notes']) ? trim(sanitize_text_field(wp_unslash((string) $source['notes']))) : '';
        $notes_custom = isset($source['notes_custom']) ? trim(sanitize_textarea_field(wp_unslash((string) $source['notes_custom']))) : '';
        if ($notes_selection === '') {
            return new WP_Error('incident_notes_required', 'Keterangan wajib dipilih.');
        }

        $notes = $notes_selection;
        if ($notes_selection === CBT_Incident_Report::custom_note_option_value()) {
            if ($notes_custom === '') {
                return new WP_Error('incident_notes_custom_required', 'Keterangan manual wajib diisi saat memilih opsi lainnya.');
            }
            $notes = $notes_custom;
        } elseif (!CBT_Incident_Report::is_valid_incident_note($incident_type, $notes_selection)) {
            return new WP_Error('incident_notes_invalid', 'Keterangan tidak valid untuk jenis insiden yang dipilih.');
        }

        return [
            'context' => $context,
            'exam' => $exam,
            'student' => $student_row,
            'staff' => $staff_row,
            'incident_type' => $incident_type,
            'notes' => $notes,
        ];
    }

    /**
     * @return array{name:string,nip:string,role:string}
     */
    public static function extract_report_supervisor_input(string $prefix, array $source): array
    {
        $name_key = $prefix . '_name';
        $nip_key = $prefix . '_nip';
        $role_key = $prefix . '_role';

        return [
            'name' => isset($source[$name_key]) ? trim(sanitize_text_field(wp_unslash((string) $source[$name_key]))) : '',
            'nip' => isset($source[$nip_key]) ? trim(sanitize_text_field(wp_unslash((string) $source[$nip_key]))) : '',
            'role' => isset($source[$role_key]) ? self::normalize_report_supervisor_role((string) wp_unslash($source[$role_key])) : '',
        ];
    }

    /**
     * @return string[]
     */
    public static function report_supervisor_role_options(): array
    {
        return ['Pengawas', 'Teknisi Ruang', 'Proktor'];
    }

    public static function normalize_report_supervisor_role(string $raw): string
    {
        $role = trim(sanitize_text_field($raw));
        if ($role === '') {
            return '';
        }

        foreach (self::report_supervisor_role_options() as $option) {
            if (strcasecmp($role, $option) === 0) {
                return $option;
            }
        }

        return '';
    }

    /**
     * @param array<int,array{name:string,nip:string,role:string}> $supervisors
     * @return array<int,string>
     */
    public static function build_report_supervisor_role_labels(array $supervisors): array
    {
        $totals = [];
        foreach ($supervisors as $supervisor) {
            $role = trim((string) ($supervisor['role'] ?? ''));
            if ($role === '') {
                continue;
            }
            if (!isset($totals[$role])) {
                $totals[$role] = 0;
            }
            $totals[$role]++;
        }

        $seen = [];
        $labels = [];
        foreach ($supervisors as $idx => $supervisor) {
            $role = trim((string) ($supervisor['role'] ?? ''));
            if ($role === '') {
                $labels[$idx] = 'Petugas ' . ((int) $idx + 1);
                continue;
            }

            $total_for_role = isset($totals[$role]) ? (int) $totals[$role] : 0;
            if ($total_for_role <= 1) {
                $labels[$idx] = $role;
                continue;
            }

            if (!isset($seen[$role])) {
                $seen[$role] = 0;
            }
            $seen[$role]++;
            $labels[$idx] = $role . ' ' . (string) $seen[$role];
        }

        return $labels;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_accessible_exam_filter_rows(bool $is_admin_scope, int $current_user_id): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $sql = "SELECT id, title FROM {$exam_table} WHERE 1=1 AND title NOT LIKE %s";
        $params = ['Bank Soal - %'];
        if (!$is_admin_scope) {
            $sql .= ' AND created_by = %d';
            $params[] = $current_user_id;
        }
        $sql .= ' ORDER BY id DESC';
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_accessible_exam_row(int $exam_id, bool $is_admin_scope, int $current_user_id): array
    {
        if ($exam_id <= 0) {
            return [];
        }

        global $wpdb;
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        if ($is_admin_scope) {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT e.id, e.title, e.starts_at, e.target_kelas, s.name AS subject_name
                     FROM {$exam_table} e
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     WHERE e.id = %d
                       AND e.title NOT LIKE %s",
                    $exam_id,
                    'Bank Soal - %'
                ),
                ARRAY_A
            );
        } else {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT e.id, e.title, e.starts_at, e.target_kelas, s.name AS subject_name
                     FROM {$exam_table} e
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     WHERE e.id = %d
                       AND e.title NOT LIKE %s
                       AND e.created_by = %d",
                    $exam_id,
                    'Bank Soal - %',
                    $current_user_id
                ),
                ARRAY_A
            );
        }

        return is_array($exam) ? $exam : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_exam_report_rows(
        int $exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        if ($exam_id <= 0) {
            return [];
        }

        $exam = self::get_accessible_exam_row($exam_id, $is_admin_scope, $current_user_id);
        if (empty($exam)) {
            return [];
        }

        $target_student_rows = self::get_report_incident_student_rows($exam, $selected_kelas);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $question_table = $wpdb->prefix . 'cbt_questions';

        $latest_attempt_subquery = "SELECT student_id, MAX(id) AS latest_attempt_id
                                    FROM {$attempt_table}
                                    WHERE exam_id = %d
                                      AND status IN ('in_progress', 'completed')
                                    GROUP BY student_id";

        $selected_kelas = trim($selected_kelas);
        $where_parts = ['a.exam_id = %d'];
        $params = [$exam_id, $exam_id];

        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $current_user_id;
        }
        if ($selected_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $params[] = $selected_kelas;
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $sql = "SELECT a.id,
                       a.student_id,
                       a.status,
                       a.score,
                       a.max_score,
                       u.display_name AS student_name,
                       COALESCE(kelas_meta.meta_value, '') AS student_kelas,
                       COALESCE(nisn_meta.meta_value, '') AS student_nisn,
                       COALESCE(anscore.total_score_awarded, 0) AS answer_score_awarded,
                       COALESCE(qtotal.total_points, 0) AS exam_total_points
                FROM {$attempt_table} a
                INNER JOIN ({$latest_attempt_subquery}) latest ON latest.latest_attempt_id = a.id
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'kode_kelas'
                    GROUP BY user_id
                ) kelas_meta ON kelas_meta.user_id = u.ID
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'nisn'
                    GROUP BY user_id
                ) nisn_meta ON nisn_meta.user_id = u.ID
                LEFT JOIN (
                    SELECT attempt_id, COALESCE(SUM(score_awarded), 0) AS total_score_awarded
                    FROM {$answer_table}
                    GROUP BY attempt_id
                ) anscore ON anscore.attempt_id = a.id
                LEFT JOIN (
                    SELECT exam_id, COALESCE(SUM(points), 0) AS total_points
                    FROM {$question_table}
                    WHERE COALESCE(is_active, 1) = 1
                    GROUP BY exam_id
                ) qtotal ON qtotal.exam_id = a.exam_id
                {$where_sql}
                ORDER BY COALESCE(kelas_meta.meta_value, '') ASC, u.display_name ASC, a.id DESC";

        $prepared_sql = $wpdb->prepare($sql, $params);
        $raw_rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        if (!is_array($raw_rows)) {
            return [];
        }

        $attempt_rows_by_student = [];
        foreach ($raw_rows as $raw_row) {
            $row = (array) $raw_row;
            $student_id = (int) ($row['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $status = (string) ($row['status'] ?? '');
            $attempt_score = (float) ($row['score'] ?? 0);
            $answer_score_awarded = (float) ($row['answer_score_awarded'] ?? 0);
            $has_completed_score = ($status === 'completed')
                && array_key_exists('score', $row)
                && $row['score'] !== null
                && $row['score'] !== '';

            $earned_points = $has_completed_score ? $attempt_score : $answer_score_awarded;
            $total_points = (float) ($row['max_score'] ?? 0);
            if ($total_points <= 0) {
                $total_points = (float) ($row['exam_total_points'] ?? 0);
            }

            $nilai = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0.0;
            $attempt_rows_by_student[$student_id] = [
                'student_id' => $student_id,
                'nisn' => (string) ($row['student_nisn'] ?? ''),
                'nama' => (string) ($row['student_name'] ?? ''),
                'kelas' => (string) ($row['student_kelas'] ?? ''),
                'nilai_display' => number_format($nilai, 2),
            ];
        }

        $rows = [];
        $seen_student_ids = [];
        foreach ($target_student_rows as $student_row) {
            $student_id = (int) ($student_row['id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $attempt_row = $attempt_rows_by_student[$student_id] ?? null;
            $student_name = trim((string) ($student_row['name'] ?? ''));
            if ($student_name === '' && is_array($attempt_row)) {
                $student_name = trim((string) ($attempt_row['nama'] ?? ''));
            }

            $student_kelas = trim((string) ($student_row['kelas'] ?? ''));
            if ($student_kelas === '' && is_array($attempt_row)) {
                $student_kelas = trim((string) ($attempt_row['kelas'] ?? ''));
            }

            $student_nisn = trim((string) get_user_meta($student_id, 'nisn', true));
            if ($student_nisn === '' && is_array($attempt_row)) {
                $student_nisn = trim((string) ($attempt_row['nisn'] ?? ''));
            }

            $rows[] = [
                'student_id' => $student_id,
                'nisn' => $student_nisn,
                'nama' => $student_name,
                'kelas' => $student_kelas,
                'is_present' => is_array($attempt_row),
                'nilai_display' => is_array($attempt_row) ? (string) ($attempt_row['nilai_display'] ?? '0.00') : 'Belum ujian',
            ];
            $seen_student_ids[$student_id] = true;
        }

        foreach ($attempt_rows_by_student as $student_id => $attempt_row) {
            if (isset($seen_student_ids[$student_id])) {
                continue;
            }

            $rows[] = [
                'student_id' => $student_id,
                'nisn' => trim((string) ($attempt_row['nisn'] ?? '')),
                'nama' => trim((string) ($attempt_row['nama'] ?? '')),
                'kelas' => trim((string) ($attempt_row['kelas'] ?? '')),
                'is_present' => true,
                'nilai_display' => (string) ($attempt_row['nilai_display'] ?? '0.00'),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $kelas_compare = strnatcasecmp((string) ($left['kelas'] ?? ''), (string) ($right['kelas'] ?? ''));
            if ($kelas_compare !== 0) {
                return $kelas_compare;
            }

            return strnatcasecmp((string) ($left['nama'] ?? ''), (string) ($right['nama'] ?? ''));
        });

        $normalized_rows = [];
        $no = 1;
        foreach ($rows as $row) {
            $normalized_rows[] = [
                'no' => $no++,
                'nisn' => (string) (($row['nisn'] ?? '') !== '' ? $row['nisn'] : '-'),
                'nama' => (string) (($row['nama'] ?? '') !== '' ? $row['nama'] : '-'),
                'kelas' => (string) (($row['kelas'] ?? '') !== '' ? $row['kelas'] : '-'),
                'is_present' => !empty($row['is_present']),
                'nilai_display' => (string) (($row['nilai_display'] ?? '') !== '' ? $row['nilai_display'] : '-'),
            ];
        }

        return $normalized_rows;
    }

    /**
     * @param array{teacher_id?:int} $scope_filters
     * @return array<int,array{no:int,nisn:string,nama:string,keterangan:string}>
     */
    public static function get_exam_report_incident_rows(
        int $exam_id,
        string $selected_kelas,
        array $scope_filters = []
    ): array {
        if ($exam_id <= 0) {
            return [];
        }

        $incident_rows = CBT_Incident_Report::get_rows($exam_id, $selected_kelas, '', $scope_filters);
        if (empty($incident_rows)) {
            return [];
        }

        $grouped_rows = [];
        foreach ($incident_rows as $incident_row) {
            $student_id = (int) ($incident_row['student_id'] ?? 0);
            $incident_id = (int) ($incident_row['id'] ?? 0);
            $group_key = $student_id > 0 ? 'student_' . $student_id : 'incident_' . $incident_id;

            if (!isset($grouped_rows[$group_key])) {
                $student_nisn = $student_id > 0 ? trim((string) get_user_meta($student_id, 'nisn', true)) : '';
                $student_name = trim((string) ($incident_row['student_name_snapshot'] ?? ''));
                if ($student_name === '') {
                    $student_name = '-';
                }

                $grouped_rows[$group_key] = [
                    'nisn' => $student_nisn !== '' ? $student_nisn : '-',
                    'nama' => $student_name,
                    'keterangan_lines' => [],
                ];
            }

            $incident_type_label = trim((string) ($incident_row['incident_type_label'] ?? ''));
            $incident_notes = trim((string) ($incident_row['notes'] ?? ''));
            if ($incident_type_label !== '' && $incident_notes !== '') {
                $description = $incident_type_label . ' - ' . $incident_notes;
            } elseif ($incident_type_label !== '') {
                $description = $incident_type_label;
            } elseif ($incident_notes !== '') {
                $description = $incident_notes;
            } else {
                $description = '-';
            }

            $grouped_rows[$group_key]['keterangan_lines'][] = $description;
        }

        $grouped_rows = array_values($grouped_rows);
        usort($grouped_rows, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['nama'] ?? ''), (string) ($right['nama'] ?? ''));
        });

        $rows = [];
        $no = 1;
        foreach ($grouped_rows as $grouped_row) {
            $keterangan_lines = array_values(array_filter(array_map(
                static fn($line): string => trim((string) $line),
                (array) ($grouped_row['keterangan_lines'] ?? [])
            ), static fn(string $line): bool => $line !== ''));

            $rows[] = [
                'no' => $no++,
                'nisn' => (string) ($grouped_row['nisn'] ?? '-'),
                'nama' => (string) ($grouped_row['nama'] ?? '-'),
                'keterangan' => !empty($keterangan_lines) ? implode("\n", $keterangan_lines) : '-',
            ];
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    public static function get_distinct_user_meta_values(string $meta_key): array
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

    public static function resolve_student_default_photo(string $role, string $foto): string
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

    private static function build_export_redirect_args(array $source, int $exam_id, string $selected_kelas, array $supervisor_inputs): array
    {
        return [
            'cbt_report_tab' => self::normalize_report_exam_tab(isset($source['cbt_report_tab']) ? (string) wp_unslash($source['cbt_report_tab']) : 'filter-export-report'),
            'cbt_exam_id' => $exam_id,
            'cbt_result_kelas' => $selected_kelas,
            'supervisor_1_name' => (string) ($supervisor_inputs[1]['name'] ?? ''),
            'supervisor_1_nip' => (string) ($supervisor_inputs[1]['nip'] ?? ''),
            'supervisor_1_role' => (string) ($supervisor_inputs[1]['role'] ?? ''),
            'supervisor_2_name' => (string) ($supervisor_inputs[2]['name'] ?? ''),
            'supervisor_2_nip' => (string) ($supervisor_inputs[2]['nip'] ?? ''),
            'supervisor_2_role' => (string) ($supervisor_inputs[2]['role'] ?? ''),
            'supervisor_3_name' => (string) ($supervisor_inputs[3]['name'] ?? ''),
            'supervisor_3_nip' => (string) ($supervisor_inputs[3]['nip'] ?? ''),
            'supervisor_3_role' => (string) ($supervisor_inputs[3]['role'] ?? ''),
        ];
    }

    private static function build_report_header_address_line(string $site_address): string
    {
        if ($site_address === '') {
            return 'Alamat: -';
        }

        $raw_address_lines = preg_split('/\r\n|\r|\n/', $site_address);
        if (!is_array($raw_address_lines)) {
            return 'Alamat: -';
        }

        $normalized_address_lines = [];
        foreach ($raw_address_lines as $raw_address_line) {
            $normalized_address_line = trim((string) $raw_address_line);
            if ($normalized_address_line !== '') {
                $normalized_address_lines[] = $normalized_address_line;
            }
        }

        if (empty($normalized_address_lines)) {
            return 'Alamat: -';
        }

        return 'Alamat: ' . implode(', ', $normalized_address_lines);
    }

    private static function build_report_header_region_line(
        string $site_village,
        string $site_district_city_ln,
        string $site_regency_country_ln,
        string $site_province_abroad_ln,
        bool $site_regency_country_ln_is_city,
        bool $site_province_abroad_ln_is_foreign
    ): string {
        $regencyCountryLabel = $site_regency_country_ln_is_city ? 'Kota' : 'Kabupaten';
        $normalizedRegencyCountry = CBT_Admin_Branding_Settings::normalize_regency_country_value($site_regency_country_ln);
        $provinceAbroadLabel = $site_province_abroad_ln_is_foreign ? 'Luar Negeri' : 'Provinsi';
        $normalizedProvinceAbroad = CBT_Admin_Branding_Settings::normalize_province_abroad_value($site_province_abroad_ln);

        return sprintf(
            'Desa: %s, Kecamatan: %s, %s: %s, %s: %s',
            $site_village !== '' ? $site_village : '-',
            $site_district_city_ln !== '' ? $site_district_city_ln : '-',
            $regencyCountryLabel,
            $normalizedRegencyCountry !== '' ? $normalizedRegencyCountry : '-',
            $provinceAbroadLabel,
            $normalizedProvinceAbroad !== '' ? $normalizedProvinceAbroad : '-'
        );
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

    private static function translate_exam_card_weekday(string $weekday): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[$weekday] ?? sanitize_text_field($weekday);
    }

    private static function format_exam_card_indonesian_date(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        $month_map = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $day = (int) wp_date('j', $timestamp);
        $month = (int) wp_date('n', $timestamp);
        $year = (int) wp_date('Y', $timestamp);
        $month_label = $month_map[$month] ?? (string) wp_date('F', $timestamp);

        return (string) $day . ' ' . $month_label . ' ' . (string) $year;
    }
}
