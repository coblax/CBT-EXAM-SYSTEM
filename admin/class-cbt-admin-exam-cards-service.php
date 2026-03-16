<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exam_Cards_Service
{
    private const USER_META_PLAIN_PASSWORD = 'cbt_plain_password';
    private const DEFAULT_STUDENT_PHOTO_RELATIVE_PATH = 'public/images/default-student-avatar.svg';

    public static function can_manage_users(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_users');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $selected_kelas = isset($query['cbt_card_kelas']) ? sanitize_text_field(wp_unslash((string) $query['cbt_card_kelas'])) : '';
        $selected_ruang = isset($query['cbt_card_ruang']) ? sanitize_text_field(wp_unslash((string) $query['cbt_card_ruang'])) : '';
        $search = isset($query['cbt_card_q']) ? sanitize_text_field(wp_unslash((string) $query['cbt_card_q'])) : '';

        $kelas_options = self::get_distinct_user_meta_values('kode_kelas');
        $ruang_options = self::get_distinct_user_meta_values('kode_ruang');
        $schedule_count = count(self::get_exam_card_schedule_rows($selected_kelas));
        $active_filter_count = 0;
        if ($selected_kelas !== '') {
            $active_filter_count++;
        }
        if ($selected_ruang !== '') {
            $active_filter_count++;
        }
        if ($search !== '') {
            $active_filter_count++;
        }

        $reset_url = add_query_arg(
            [
                'page' => 'cbt-exam-cards',
            ],
            admin_url('admin.php')
        );

        return compact(
            'active_filter_count',
            'error',
            'kelas_options',
            'notice',
            'reset_url',
            'ruang_options',
            'schedule_count',
            'search',
            'selected_kelas',
            'selected_ruang'
        );
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>|WP_Error
     */
    public static function build_print_context(array $source)
    {
        $selected_kelas = isset($source['cbt_card_kelas']) ? trim(sanitize_text_field(wp_unslash((string) $source['cbt_card_kelas']))) : '';
        $selected_ruang = isset($source['cbt_card_ruang']) ? trim(sanitize_text_field(wp_unslash((string) $source['cbt_card_ruang']))) : '';
        $search = isset($source['cbt_card_q']) ? trim(sanitize_text_field(wp_unslash((string) $source['cbt_card_q']))) : '';

        $redirect_args = [
            'cbt_card_kelas' => $selected_kelas,
            'cbt_card_ruang' => $selected_ruang,
            'cbt_card_q' => $search,
        ];

        $students = self::get_exam_card_students($search, $selected_kelas, $selected_ruang);
        if (empty($students)) {
            return new WP_Error('exam_cards_empty', 'Tidak ada siswa sesuai filter untuk dicetak.', ['redirect_args' => $redirect_args]);
        }

        foreach ($students as $idx => $student) {
            $student_id = (int) ($student['id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $existing_password = trim((string) ($student['password'] ?? ''));
            if ($existing_password !== '') {
                $students[$idx]['password'] = $existing_password;
                continue;
            }

            $generated_password = self::generate_exam_card_password();
            wp_set_password($generated_password, $student_id);
            update_user_meta($student_id, self::USER_META_PLAIN_PASSWORD, $generated_password);
            $students[$idx]['password'] = $generated_password;
        }

        $schedule_rows = self::get_exam_card_schedule_rows($selected_kelas);
        $schedule_items = [];
        foreach ($schedule_rows as $schedule_row) {
            $schedule_items[] = self::format_exam_card_schedule_line((array) $schedule_row);
        }

        $branding = CBT_Admin_Branding_Settings::get_print_context();
        $exam_program_name = trim((string) ($branding['exam_program_name'] ?? ''));
        $school_name = trim((string) ($branding['school_name'] ?? ''));
        $school_motto = trim((string) ($branding['school_motto'] ?? ''));
        $school_npsn = trim((string) ($branding['school_npsn'] ?? ''));
        if ($school_name === '') {
            $school_name = trim((string) get_bloginfo('name'));
        }
        if ($school_name === '') {
            $school_name = 'CBT Exam';
        }

        $card_program_title = $exam_program_name !== '' ? $exam_program_name : 'Ujian CBT';
        $school_logo_1_url = (string) ($branding['logo_1_url'] ?? '');
        $school_logo_2_url = (string) ($branding['logo_2_url'] ?? '');
        $printed_at = current_time('d M Y H:i');
        $kelas_label = $selected_kelas !== '' ? $selected_kelas : 'Semua Kelas';
        $ruang_label = $selected_ruang !== '' ? $selected_ruang : 'Semua Ruang';
        $student_total = count($students);

        $back_args = [
            'page' => 'cbt-exam-cards',
            'cbt_card_kelas' => $selected_kelas,
        ];
        if ($selected_ruang !== '') {
            $back_args['cbt_card_ruang'] = $selected_ruang;
        }
        if ($search !== '') {
            $back_args['cbt_card_q'] = $search;
        }
        $back_url = add_query_arg($back_args, admin_url('admin.php'));

        return compact(
            'back_url',
            'card_program_title',
            'kelas_label',
            'printed_at',
            'ruang_label',
            'schedule_items',
            'school_logo_1_url',
            'school_logo_2_url',
            'school_motto',
            'school_name',
            'school_npsn',
            'student_total',
            'students'
        );
    }

    /**
     * @return array<int,array{id:int,username:string,name:string,nisn:string,kelas:string,agama:string,ruang:string,foto:string,password:string}>
     */
    public static function get_exam_card_students(string $search, string $kode_kelas, string $kode_ruang): array
    {
        $args = [
            'role__in' => ['siswa_cbt', 'subscriber'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all',
        ];

        $search = trim($search);
        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $meta_query = [];
        $kode_kelas = trim($kode_kelas);
        $kode_ruang = trim($kode_ruang);
        if ($kode_kelas !== '') {
            $meta_query[] = [
                'key' => 'kode_kelas',
                'value' => $kode_kelas,
                'compare' => '=',
            ];
        }
        if ($kode_ruang !== '') {
            $meta_query[] = [
                'key' => 'kode_ruang',
                'value' => $kode_ruang,
                'compare' => '=',
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
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

            $role = isset($user->roles[0]) ? (string) $user->roles[0] : '';
            $foto = esc_url_raw((string) get_user_meta($user_id, 'foto', true));
            if ($foto === '' && self::is_student_role($role)) {
                $foto = self::get_default_student_photo_url();
            }

            $rows[] = [
                'id' => $user_id,
                'username' => (string) $user->user_login,
                'name' => $display_name,
                'nisn' => trim((string) get_user_meta($user_id, 'nisn', true)),
                'kelas' => trim((string) get_user_meta($user_id, 'kode_kelas', true)),
                'agama' => trim((string) get_user_meta($user_id, 'agama', true)),
                'ruang' => trim((string) get_user_meta($user_id, 'kode_ruang', true)),
                'foto' => $foto,
                'password' => trim((string) get_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, true)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_exam_card_schedule_rows(string $kelas): array
    {
        global $wpdb;

        $kelas_normalized = strtoupper(sanitize_text_field(trim($kelas)));
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, starts_at, ends_at, duration_minutes, target_kelas
                 FROM {$exam_table}
                 WHERE status = %s
                 ORDER BY starts_at ASC, id ASC",
                'published'
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $schedules = [];
        foreach ($rows as $row) {
            $exam = is_array($row) ? $row : [];
            $target_kelas = self::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
            if ($kelas_normalized !== '' && !empty($target_kelas) && !in_array($kelas_normalized, $target_kelas, true)) {
                continue;
            }
            $schedules[] = $exam;
        }

        return $schedules;
    }

    /**
     * @return array{title:string,day:string,time:string,duration:string}
     */
    public static function format_exam_card_schedule_line(array $exam): array
    {
        $title = trim(sanitize_text_field((string) ($exam['title'] ?? '')));
        if ($title === '') {
            $title = 'Exam';
        }

        $day_label = self::format_exam_card_day_label((string) ($exam['starts_at'] ?? ''), (string) ($exam['ends_at'] ?? ''));
        $duration_label = self::format_exam_card_duration_label(
            isset($exam['duration_minutes']) ? (int) $exam['duration_minutes'] : 0,
            (string) ($exam['starts_at'] ?? ''),
            (string) ($exam['ends_at'] ?? '')
        );
        $start_time_label = self::format_exam_card_time_only((string) ($exam['starts_at'] ?? ''));
        $end_time_label = self::format_exam_card_end_time(
            (string) ($exam['starts_at'] ?? ''),
            (string) ($exam['ends_at'] ?? ''),
            isset($exam['duration_minutes']) ? (int) $exam['duration_minutes'] : 0
        );

        if ($start_time_label !== '-' && $end_time_label !== '-') {
            $time_label = $start_time_label . ' - ' . $end_time_label;
        } elseif ($start_time_label !== '-') {
            $time_label = $start_time_label;
        } elseif ($end_time_label !== '-') {
            $time_label = $end_time_label;
        } else {
            $time_label = 'Jadwal belum diatur';
        }

        return [
            'title' => $title,
            'day' => $day_label,
            'time' => $time_label,
            'duration' => $duration_label,
        ];
    }

    public static function generate_exam_card_password(): string
    {
        try {
            return (string) random_int(100000, 999999);
        } catch (Throwable $exception) {
            return str_pad((string) wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }
    }

    /**
     * @return string[]
     */
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

    private static function format_exam_card_day_label(string $starts_at, string $ends_at): string
    {
        $start_ts = strtotime(trim($starts_at));
        $end_ts = strtotime(trim($ends_at));
        $active_ts = false;

        if ($start_ts !== false) {
            $active_ts = $start_ts;
        } elseif ($end_ts !== false) {
            $active_ts = $end_ts;
        }
        if ($active_ts === false) {
            return '-';
        }

        $weekday = self::translate_exam_card_weekday((string) wp_date('l', $active_ts));
        $date_label = self::format_exam_card_indonesian_date((int) $active_ts);

        return $weekday . ' , ' . $date_label;
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

    private static function format_exam_card_duration_label(int $duration_minutes, string $starts_at, string $ends_at): string
    {
        if ($duration_minutes > 0) {
            return (string) $duration_minutes . ' menit';
        }

        $start_ts = strtotime(trim($starts_at));
        $end_ts = strtotime(trim($ends_at));
        if ($start_ts !== false && $end_ts !== false && $end_ts > $start_ts) {
            $minutes = (int) round(($end_ts - $start_ts) / 60);
            if ($minutes > 0) {
                return (string) $minutes . ' menit';
            }
        }

        return '-';
    }

    private static function format_exam_card_time_only(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return sanitize_text_field($value);
        }

        return wp_date('H:i', $timestamp);
    }

    private static function format_exam_card_end_time(string $starts_at, string $ends_at, int $duration_minutes): string
    {
        $start_ts = strtotime(trim($starts_at));
        if ($start_ts !== false && $duration_minutes > 0) {
            return wp_date('H:i', $start_ts + ($duration_minutes * 60));
        }

        $end_ts = strtotime(trim($ends_at));
        if ($end_ts !== false) {
            return wp_date('H:i', $end_ts);
        }

        return '-';
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

    private static function is_student_role(string $role): bool
    {
        $normalized = strtolower(trim($role));
        return in_array($normalized, ['siswa', 'siswa_cbt', 'subscriber', 'student'], true);
    }

    private static function get_default_student_photo_url(): string
    {
        return esc_url_raw(CBT_EXAM_SYSTEM_URL . self::DEFAULT_STUDENT_PHOTO_RELATIVE_PATH);
    }

    private static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }
}
