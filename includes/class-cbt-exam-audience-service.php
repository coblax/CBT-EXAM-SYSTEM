<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Exam_Audience_Service
{
    public const MAX_SUBJECT_CHOICES = 3;

    private const TABLE_SUFFIX = 'cbt_student_subject_choices';
    private const STUDENT_ROLES = ['student', 'siswa', 'siswa_cbt', 'subscriber'];

    /**
     * @return array<string,string>
     */
    public static function get_supported_agama_options(): array
    {
        return [
            'Islam' => 'Islam',
            'Kristen' => 'Kristen',
            'Katolik' => 'Katolik',
            'Hindu' => 'Hindu',
            'Buddha' => 'Buddha',
            'Konghucu' => 'Konghucu',
            'Lainnya' => 'Lainnya',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function get_supported_gender_options(): array
    {
        return [
            'Laki-laki' => 'Laki-laki',
            'Perempuan' => 'Perempuan',
        ];
    }

    public static function get_table_name(wpdb $wpdb): string
    {
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function get_create_table_sql(wpdb $wpdb): string
    {
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = self::get_table_name($wpdb);

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            choice_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_subject (user_id, subject_id),
            UNIQUE KEY uniq_user_order (user_id, choice_order),
            KEY idx_user_order (user_id, choice_order),
            KEY idx_subject_user (subject_id, user_id)
        ) {$charset};";
    }

    public static function normalize_kelas_code(string $value): string
    {
        return strtoupper(trim(sanitize_text_field($value)));
    }

    /**
     * @return string[]
     */
    public static function parse_target_kelas(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $raw);
        $values = [];
        foreach (explode(',', $raw) as $part) {
            $normalized = self::normalize_kelas_code((string) $part);
            if ($normalized !== '') {
                $values[$normalized] = $normalized;
            }
        }

        return array_values($values);
    }

    public static function normalize_agama(string $value): string
    {
        $value = trim(sanitize_text_field($value));
        if ($value === '') {
            return '';
        }

        $aliases = [
            'islam' => 'Islam',
            'muslim' => 'Islam',
            'kristen' => 'Kristen',
            'protestan' => 'Kristen',
            'kristen protestan' => 'Kristen',
            'katolik' => 'Katolik',
            'katholik' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha' => 'Buddha',
            'budha' => 'Buddha',
            'konghucu' => 'Konghucu',
            'khonghucu' => 'Konghucu',
            'lainnya' => 'Lainnya',
            'lain' => 'Lainnya',
        ];
        $key = strtolower($value);

        return $aliases[$key] ?? '';
    }

    public static function normalize_gender(string $value): string
    {
        $value = strtolower(trim(sanitize_text_field($value)));
        if ($value === '') {
            return '';
        }

        if (in_array($value, ['l', 'lk', 'laki', 'laki laki', 'laki-laki', 'pria', 'male'], true)) {
            return 'Laki-laki';
        }
        if (in_array($value, ['p', 'pr', 'perempuan', 'wanita', 'female'], true)) {
            return 'Perempuan';
        }

        return '';
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    public static function normalize_target_values($raw, string $type): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[,\n\r;|]+/', (string) $raw) ?: [];
        }

        $values = [];
        foreach ($parts as $part) {
            $text = is_scalar($part) ? (string) $part : '';
            $normalized = $type === 'gender'
                ? self::normalize_gender($text)
                : self::normalize_agama($text);
            if ($normalized !== '') {
                $values[$normalized] = $normalized;
            }
        }

        return array_values($values);
    }

    /**
     * @param mixed $raw
     */
    public static function normalize_target_csv($raw, string $type): string
    {
        return implode(',', self::normalize_target_values($raw, $type));
    }

    /**
     * @return string[]
     */
    public static function get_exam_target_agama(array $exam): array
    {
        return self::normalize_target_values((string) ($exam['target_agama'] ?? ''), 'agama');
    }

    /**
     * @return string[]
     */
    public static function get_exam_target_gender(array $exam): array
    {
        return self::normalize_target_values((string) ($exam['target_jenis_kelamin'] ?? ''), 'gender');
    }

    /**
     * @return array{allowed:bool,reason:string,details:array<string,mixed>}
     */
    public static function evaluate_exam_for_student(array $exam, int $user_id, array $profile = []): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::audience_result(false, 'invalid_student');
        }

        if (empty($profile)) {
            $profile = self::get_student_profile($user_id);
        }

        $target_kelas = self::parse_target_kelas((string) ($exam['target_kelas'] ?? ''));
        $student_kelas = self::normalize_kelas_code((string) ($profile['kode_kelas'] ?? ''));
        if (!empty($target_kelas) && ($student_kelas === '' || !in_array($student_kelas, $target_kelas, true))) {
            return self::audience_result(false, 'class_mismatch', [
                'student_kelas' => $student_kelas,
                'target_kelas' => $target_kelas,
            ]);
        }

        $target_agama = self::get_exam_target_agama($exam);
        $student_agama = self::normalize_agama((string) ($profile['agama'] ?? ''));
        if (!empty($target_agama) && ($student_agama === '' || !in_array($student_agama, $target_agama, true))) {
            return self::audience_result(false, 'agama_mismatch', [
                'student_agama' => $student_agama,
                'target_agama' => $target_agama,
            ]);
        }

        $target_gender = self::get_exam_target_gender($exam);
        $student_gender = self::normalize_gender((string) ($profile['jenis_kelamin'] ?? ''));
        if (!empty($target_gender) && ($student_gender === '' || !in_array($student_gender, $target_gender, true))) {
            return self::audience_result(false, 'gender_mismatch', [
                'student_jenis_kelamin' => $student_gender,
                'target_jenis_kelamin' => $target_gender,
            ]);
        }

        if ((int) ($exam['restrict_to_subject_choice'] ?? 0) === 1) {
            $subject_id = absint($exam['subject_id'] ?? 0);
            if ($subject_id <= 0 || !self::user_has_subject_choice($user_id, $subject_id)) {
                return self::audience_result(false, 'subject_choice_mismatch', [
                    'subject_id' => $subject_id,
                    'choice_subject_ids' => self::get_student_subject_choice_ids($user_id),
                ]);
            }
        }

        return self::audience_result(true, 'ok', [
            'student_kelas' => $student_kelas,
            'student_agama' => $student_agama,
            'student_jenis_kelamin' => $student_gender,
        ]);
    }

    /**
     * @return array{allowed:bool,reason:string,details:array<string,mixed>}
     */
    private static function audience_result(bool $allowed, string $reason, array $details = []): array
    {
        return [
            'allowed' => $allowed,
            'reason' => sanitize_key($reason),
            'details' => $details,
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function get_student_profile(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [
                'kode_kelas' => '',
                'kode_ruang' => '',
                'agama' => '',
                'jenis_kelamin' => '',
                'nisn' => '',
            ];
        }

        if (class_exists('CBT_Student_Profile_Cache') && method_exists('CBT_Student_Profile_Cache', 'get_snapshot')) {
            $snapshot = CBT_Student_Profile_Cache::get_snapshot($user_id);
            if (is_array($snapshot)) {
                return [
                    'kode_kelas' => (string) ($snapshot['kode_kelas'] ?? ''),
                    'kode_ruang' => (string) ($snapshot['kode_ruang'] ?? ''),
                    'agama' => (string) ($snapshot['agama'] ?? ''),
                    'jenis_kelamin' => (string) ($snapshot['jenis_kelamin'] ?? ''),
                    'nisn' => (string) ($snapshot['nisn'] ?? ''),
                ];
            }
        }

        return [
            'kode_kelas' => (string) get_user_meta($user_id, 'kode_kelas', true),
            'kode_ruang' => (string) get_user_meta($user_id, 'kode_ruang', true),
            'agama' => (string) get_user_meta($user_id, 'agama', true),
            'jenis_kelamin' => (string) get_user_meta($user_id, 'jenis_kelamin', true),
            'nisn' => (string) get_user_meta($user_id, 'nisn', true),
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function get_student_profile_direct(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::get_student_profile(0);
        }

        return [
            'kode_kelas' => (string) get_user_meta($user_id, 'kode_kelas', true),
            'kode_ruang' => (string) get_user_meta($user_id, 'kode_ruang', true),
            'agama' => (string) get_user_meta($user_id, 'agama', true),
            'jenis_kelamin' => (string) get_user_meta($user_id, 'jenis_kelamin', true),
            'nisn' => (string) get_user_meta($user_id, 'nisn', true),
        ];
    }

    public static function user_has_subject_choice(int $user_id, int $subject_id): bool
    {
        $user_id = absint($user_id);
        $subject_id = absint($subject_id);
        if ($user_id <= 0 || $subject_id <= 0) {
            return false;
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_var')) {
            return false;
        }

        $table = self::get_table_name($wpdb);
        try {
            $found = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND subject_id = %d",
                $user_id,
                $subject_id
            ));
        } catch (Throwable $throwable) {
            return false;
        }

        return $found > 0;
    }

    /**
     * @return int[]
     */
    public static function get_student_subject_choice_ids(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [];
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_col')) {
            return [];
        }

        $table = self::get_table_name($wpdb);
        try {
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT subject_id FROM {$table} WHERE user_id = %d ORDER BY choice_order ASC, subject_id ASC",
                $user_id
            ));
        } catch (Throwable $throwable) {
            return [];
        }

        return array_values(array_filter(array_map('absint', is_array($ids) ? $ids : [])));
    }

    /**
     * @return true|WP_Error
     */
    public static function set_student_subject_choices(int $user_id, array $subject_ids)
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', 'User tidak valid.');
        }

        $subject_ids = self::normalize_subject_choice_ids($subject_ids);
        if (count($subject_ids) > self::MAX_SUBJECT_CHOICES) {
            return new WP_Error('too_many_subject_choices', 'Mapel pilihan maksimal 3.');
        }
        if (!self::subject_ids_exist($subject_ids)) {
            return new WP_Error('invalid_subject_choice', 'Ada mapel pilihan yang tidak ditemukan.');
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb) {
            return new WP_Error('database_unavailable', 'Database tidak tersedia.');
        }

        $table = self::get_table_name($wpdb);
        $now = current_time('mysql');

        try {
            $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
            $order = 1;
            foreach ($subject_ids as $subject_id) {
                $wpdb->insert(
                    $table,
                    [
                        'user_id' => $user_id,
                        'subject_id' => $subject_id,
                        'choice_order' => $order,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%d', '%d', '%s', '%s']
                );
                $order++;
            }
        } catch (Throwable $throwable) {
            return new WP_Error('subject_choice_save_failed', 'Gagal menyimpan mapel pilihan siswa.');
        }

        self::invalidate_student_audience_caches($user_id);

        return true;
    }

    public static function clear_student_subject_choices(int $user_id): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb) {
            return false;
        }

        try {
            $cleared = $wpdb->delete(self::get_table_name($wpdb), ['user_id' => $user_id], ['%d']) !== false;
            if ($cleared) {
                self::invalidate_student_audience_caches($user_id);
            }
            return $cleared;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    /**
     * @return int[]
     */
    public static function normalize_subject_choice_ids(array $subject_ids): array
    {
        $normalized = [];
        foreach ($subject_ids as $subject_id) {
            $subject_id = absint($subject_id);
            if ($subject_id <= 0) {
                continue;
            }
            $normalized[$subject_id] = $subject_id;
        }

        return array_values($normalized);
    }

    private static function subject_ids_exist(array $subject_ids): bool
    {
        $subject_ids = self::normalize_subject_choice_ids($subject_ids);
        if (empty($subject_ids)) {
            return true;
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_var')) {
            return false;
        }

        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $placeholders = implode(',', array_fill(0, count($subject_ids), '%d'));
        try {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$subject_table} WHERE id IN ({$placeholders})",
                ...$subject_ids
            ));
        } catch (Throwable $throwable) {
            return false;
        }

        return $count === count($subject_ids);
    }

    /**
     * @return array<int,array{id:int,name:string,code:string,label:string}>
     */
    public static function get_subject_options(): array
    {
        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $table = $wpdb->prefix . 'cbt_subjects';
        try {
            $rows = $wpdb->get_results("SELECT id, name, code FROM {$table} ORDER BY name ASC, code ASC, id ASC", ARRAY_A);
        } catch (Throwable $throwable) {
            return [];
        }

        $options = [];
        foreach ((array) $rows as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            $code = sanitize_text_field((string) ($row['code'] ?? ''));
            $options[] = [
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'label' => $name . ($code !== '' ? ' (' . $code . ')' : ''),
            ];
        }

        return $options;
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array<int,string>>
     */
    public static function get_student_subject_choice_label_map(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_unique(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $choice_table = self::get_table_name($wpdb);
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        try {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT c.user_id, c.choice_order, s.name, s.code
                 FROM {$choice_table} c
                 INNER JOIN {$subject_table} s ON s.id = c.subject_id
                 WHERE c.user_id IN ({$placeholders})
                 ORDER BY c.user_id ASC, c.choice_order ASC, s.name ASC",
                ...$user_ids
            ), ARRAY_A);
        } catch (Throwable $throwable) {
            return [];
        }

        $map = [];
        foreach ((array) $rows as $row) {
            $user_id = absint($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            $code = sanitize_text_field((string) ($row['code'] ?? ''));
            $label = $name . ($code !== '' ? ' (' . $code . ')' : '');
            if ($label === '') {
                continue;
            }
            if (!isset($map[$user_id])) {
                $map[$user_id] = [];
            }
            $map[$user_id][] = $label;
        }

        return $map;
    }

    public static function resolve_subject_identifier(string $value): int
    {
        $value = trim(sanitize_text_field($value));
        if ($value === '') {
            return 0;
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_var')) {
            return 0;
        }

        $table = $wpdb->prefix . 'cbt_subjects';
        try {
            if (ctype_digit($value)) {
                $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d LIMIT 1", (int) $value));
                if ($id > 0) {
                    return $id;
                }
            }

            $code_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE UPPER(code) = UPPER(%s) ORDER BY id ASC LIMIT 1",
                $value
            ));
            if ($code_id > 0) {
                return $code_id;
            }

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE LOWER(name) = LOWER(%s) ORDER BY id ASC LIMIT 1",
                $value
            ));
        } catch (Throwable $throwable) {
            return 0;
        }
    }

    /**
     * @return int[]
     */
    public static function get_target_student_ids_for_exam(array $exam): array
    {
        $target_kelas = self::parse_target_kelas((string) ($exam['target_kelas'] ?? ''));
        if (empty($target_kelas)) {
            return [];
        }

        $filters = [
            'kelas_values' => $target_kelas,
            'agama_values' => self::get_exam_target_agama($exam),
            'jenis_kelamin_values' => self::get_exam_target_gender($exam),
            'limit' => 0,
        ];

        $user_ids = [];
        if (class_exists('CBT_Student_Cohort_Index_Service') && method_exists('CBT_Student_Cohort_Index_Service', 'query_students')) {
            $result = CBT_Student_Cohort_Index_Service::query_students($filters);
            if (empty($result['fallback_required'])) {
                $user_ids = array_values(array_filter(array_map('absint', (array) ($result['user_ids'] ?? []))));
            }
        }

        // Merge canonical users so a stale/partial cohort index cannot hide eligible students.
        $user_ids = array_values(array_unique(array_merge($user_ids, self::get_target_student_ids_via_users($exam))));

        if ((int) ($exam['restrict_to_subject_choice'] ?? 0) === 1) {
            $user_ids = self::filter_user_ids_by_subject_choice($user_ids, absint($exam['subject_id'] ?? 0));
        }

        sort($user_ids, SORT_NUMERIC);
        return array_values(array_unique($user_ids));
    }

    /**
     * @return int[]
     */
    private static function get_target_student_ids_via_users(array $exam): array
    {
        $users = get_users([
            'number' => 0,
            'fields' => 'ids',
        ]);
        if (!is_array($users)) {
            return [];
        }

        $ids = [];
        foreach ($users as $user) {
            $user_id = absint(is_object($user) ? ($user->ID ?? 0) : (is_array($user) ? ($user['ID'] ?? 0) : $user));
            if ($user_id <= 0) {
                continue;
            }

            $wp_user = get_user_by('id', $user_id);
            if (!($wp_user instanceof WP_User) || !self::is_student_user($wp_user)) {
                continue;
            }

            $result = self::evaluate_exam_for_student($exam, $user_id, [
                'kode_kelas' => (string) get_user_meta($user_id, 'kode_kelas', true),
                'kode_ruang' => (string) get_user_meta($user_id, 'kode_ruang', true),
                'agama' => (string) get_user_meta($user_id, 'agama', true),
                'jenis_kelamin' => (string) get_user_meta($user_id, 'jenis_kelamin', true),
                'nisn' => (string) get_user_meta($user_id, 'nisn', true),
            ]);
            if (!empty($result['allowed'])) {
                $ids[$user_id] = $user_id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param int[] $user_ids
     * @return int[]
     */
    public static function filter_user_ids_by_subject_choice(array $user_ids, int $subject_id): array
    {
        $user_ids = array_values(array_filter(array_unique(array_map('absint', $user_ids))));
        $subject_id = absint($subject_id);
        if (empty($user_ids) || $subject_id <= 0) {
            return [];
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_col')) {
            return array_values(array_filter($user_ids, static function (int $user_id) use ($subject_id): bool {
                return self::user_has_subject_choice($user_id, $subject_id);
            }));
        }

        $table = self::get_table_name($wpdb);
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        try {
            $matched = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$table} WHERE subject_id = %d AND user_id IN ({$placeholders})",
                ...array_merge([$subject_id], $user_ids)
            ));
        } catch (Throwable $throwable) {
            return [];
        }

        return array_values(array_filter(array_map('absint', is_array($matched) ? $matched : [])));
    }

    /**
     * @return array{summary:array<string,int>,reason_counts:array<string,int>,matched:array<int,array<string,mixed>>,excluded:array<int,array<string,mixed>>}
     */
    public static function build_exam_audience_preview(array $exam, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $target_kelas = self::parse_target_kelas((string) ($exam['target_kelas'] ?? ''));
        $rows = [];
        if (!empty($target_kelas) && class_exists('CBT_Student_Cohort_Index_Service')) {
            $result = CBT_Student_Cohort_Index_Service::query_students([
                'kelas_values' => $target_kelas,
                'limit' => 0,
            ]);
            if (empty($result['fallback_required'])) {
                $rows = array_values(array_filter((array) ($result['rows'] ?? []), 'is_array'));
            }
        }

        if (empty($rows)) {
            $rows = self::get_student_rows_via_users($target_kelas);
        }

        $matched = [];
        $excluded = [];
        $reason_counts = [];
        foreach ($rows as $row) {
            $user_id = absint($row['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }
            $result = self::evaluate_exam_for_student($exam, $user_id, $row);
            $reason = (string) ($result['reason'] ?? 'unknown');
            $reason_counts[$reason] = ($reason_counts[$reason] ?? 0) + 1;
            $item = [
                'user_id' => $user_id,
                'name' => sanitize_text_field((string) ($row['display_name'] ?? '')),
                'username' => sanitize_user((string) ($row['user_login'] ?? ''), true),
                'kelas' => self::normalize_kelas_code((string) ($row['kode_kelas'] ?? '')),
                'reason' => $reason,
            ];
            if (!empty($result['allowed'])) {
                if (count($matched) < $limit) {
                    $matched[] = $item;
                }
            } elseif (count($excluded) < $limit) {
                $excluded[] = $item;
            }
        }

        $matched_total = (int) ($reason_counts['ok'] ?? 0);
        $total = array_sum($reason_counts);

        return [
            'summary' => [
                'total_candidates' => $total,
                'matched' => $matched_total,
                'excluded' => max(0, $total - $matched_total),
            ],
            'reason_counts' => $reason_counts,
            'matched' => $matched,
            'excluded' => $excluded,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function diagnose_student_exams(int $user_id, array $filters = []): array
    {
        $user_id = absint($user_id);
        $user = $user_id > 0 ? get_user_by('id', $user_id) : null;
        $is_student = $user instanceof WP_User && self::is_student_user($user);
        $profile = $user_id > 0 ? self::get_student_profile_direct($user_id) : self::get_student_profile(0);
        $choice_labels_map = $user_id > 0 ? self::get_student_subject_choice_label_map([$user_id]) : [];
        $choice_labels = isset($choice_labels_map[$user_id]) && is_array($choice_labels_map[$user_id])
            ? array_values($choice_labels_map[$user_id])
            : [];

        $diagnosis = [
            'student' => [
                'user_id' => $user_id,
                'username' => $user instanceof WP_User ? (string) $user->user_login : '',
                'name' => $user instanceof WP_User ? (string) $user->display_name : '',
                'is_student' => $is_student,
                'profile' => $profile,
                'subject_choices' => $choice_labels,
            ],
            'summary' => [
                'total' => 0,
                'can_start' => 0,
                'blocked' => 0,
                'in_progress' => 0,
                'completed' => 0,
            ],
            'items' => [],
            'message' => '',
        ];

        if (!$is_student) {
            $diagnosis['message'] = 'User ini bukan role siswa, sehingga tidak memiliki daftar exam siswa.';
            return $diagnosis;
        }

        $exams = self::get_exam_rows_for_diagnosis($filters);
        if (empty($exams)) {
            $diagnosis['message'] = 'Belum ada exam yang bisa dianalisis.';
            return $diagnosis;
        }

        $attempts = self::get_latest_attempts_for_student($user_id, array_map(static function (array $exam): int {
            return absint($exam['id'] ?? 0);
        }, $exams));
        $now = self::diagnosis_now_timestamp();
        $items = [];
        foreach ($exams as $exam) {
            $exam_id = absint($exam['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $audience = self::evaluate_exam_for_student($exam, $user_id, $profile);
            $schedule_state = self::diagnose_exam_schedule_state($exam, $now);
            $attempt = $attempts[$exam_id] ?? null;
            $attempt_state = self::diagnose_attempt_state(is_array($attempt) ? $attempt : null);
            $item = self::build_diagnosis_item($exam, $audience, $schedule_state, $attempt_state);
            $items[] = $item;
        }

        $summary = [
            'total' => count($items),
            'can_start' => 0,
            'blocked' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];
        foreach ($items as $item) {
            if (!empty($item['can_start_now'])) {
                $summary['can_start']++;
            } else {
                $summary['blocked']++;
            }
            if (($item['attempt_state'] ?? '') === 'in_progress') {
                $summary['in_progress']++;
            }
            if (($item['attempt_state'] ?? '') === 'completed') {
                $summary['completed']++;
            }
        }

        $diagnosis['summary'] = $summary;
        $diagnosis['items'] = $items;
        $diagnosis['message'] = $summary['can_start'] > 0
            ? sprintf('%d exam bisa dikerjakan atau dilanjutkan.', (int) $summary['can_start'])
            : 'Tidak ada exam yang bisa dikerjakan saat ini.';

        return $diagnosis;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_exam_rows_for_diagnosis(array $filters): array
    {
        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $where = ['1=1'];
        $params = [];
        $exam_id = absint($filters['exam_id'] ?? 0);
        if ($exam_id > 0) {
            $where[] = 'e.id = %d';
            $params[] = $exam_id;
        }
        $include_question_banks = !empty($filters['include_question_banks']);
        if (!$include_question_banks) {
            $where[] = 'e.title NOT LIKE %s';
            $params[] = 'Bank Soal - %';
        }

        $sql = "SELECT e.id, e.subject_id, e.title, e.status, e.target_kelas, e.target_agama,
                       e.target_jenis_kelamin, e.restrict_to_subject_choice, e.starts_at, e.ends_at,
                       e.duration_minutes, s.name AS subject_name, s.code AS subject_code
                FROM {$exam_table} e
                LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY COALESCE(e.starts_at, e.created_at) DESC, e.id DESC';

        try {
            $rows = !empty($params)
                ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A)
                : $wpdb->get_results($sql, ARRAY_A);
        } catch (Throwable $throwable) {
            return [];
        }

        $rows = array_values(array_filter((array) $rows, 'is_array'));
        if (!$include_question_banks) {
            $rows = array_values(array_filter($rows, static function (array $row): bool {
                return !self::is_bank_exam_title((string) ($row['title'] ?? ''));
            }));
        }

        return $rows;
    }

    private static function is_bank_exam_title(string $exam_title): bool
    {
        return stripos($exam_title, 'Bank Soal - ') === 0;
    }

    /**
     * @param int[] $exam_ids
     * @return array<int,array<string,mixed>>
     */
    private static function get_latest_attempts_for_student(int $user_id, array $exam_ids): array
    {
        $user_id = absint($user_id);
        $exam_ids = array_values(array_filter(array_unique(array_map('absint', $exam_ids))));
        if ($user_id <= 0 || empty($exam_ids)) {
            return [];
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $placeholders = implode(',', array_fill(0, count($exam_ids), '%d'));
        try {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, exam_id, status, started_at, finished_at
                 FROM {$attempt_table}
                 WHERE student_id = %d AND exam_id IN ({$placeholders})
                 ORDER BY exam_id ASC, id DESC",
                ...array_merge([$user_id], $exam_ids)
            ), ARRAY_A);
        } catch (Throwable $throwable) {
            return [];
        }

        $map = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $exam_id = absint($row['exam_id'] ?? 0);
            if ($exam_id > 0 && !isset($map[$exam_id])) {
                $map[$exam_id] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array{state:string,label:string}
     */
    private static function diagnose_exam_schedule_state(array $exam, int $now): array
    {
        $starts_at = self::diagnosis_datetime_timestamp((string) ($exam['starts_at'] ?? ''));
        $ends_at = self::diagnosis_datetime_timestamp((string) ($exam['ends_at'] ?? ''));
        if ($starts_at > 0 && $starts_at > $now) {
            return ['state' => 'future', 'label' => 'Belum mulai'];
        }
        if ($ends_at > 0 && $ends_at < $now) {
            return ['state' => 'ended', 'label' => 'Jadwal selesai'];
        }

        return ['state' => 'active', 'label' => 'Aktif sekarang'];
    }

    /**
     * @return array{state:string,label:string,attempt_id:int}
     */
    private static function diagnose_attempt_state(?array $attempt): array
    {
        if (empty($attempt)) {
            return ['state' => 'none', 'label' => 'Belum ada attempt', 'attempt_id' => 0];
        }

        $status = sanitize_key((string) ($attempt['status'] ?? ''));
        $attempt_id = absint($attempt['id'] ?? 0);
        if ($status === 'in_progress') {
            return ['state' => 'in_progress', 'label' => 'Sedang berjalan', 'attempt_id' => $attempt_id];
        }
        if ($status === 'completed') {
            return ['state' => 'completed', 'label' => 'Sudah selesai', 'attempt_id' => $attempt_id];
        }

        return ['state' => $status !== '' ? $status : 'unknown', 'label' => $status !== '' ? $status : 'Tidak diketahui', 'attempt_id' => $attempt_id];
    }

    /**
     * @param array{allowed:bool,reason:string,details:array<string,mixed>} $audience
     * @param array{state:string,label:string} $schedule_state
     * @param array{state:string,label:string,attempt_id:int} $attempt_state
     * @return array<string,mixed>
     */
    private static function build_diagnosis_item(array $exam, array $audience, array $schedule_state, array $attempt_state): array
    {
        $status = sanitize_key((string) ($exam['status'] ?? ''));
        $audience_reason = sanitize_key((string) ($audience['reason'] ?? 'unknown'));
        $can_start = false;
        $primary_reason = 'ok';
        $tone = 'success';

        if ($status !== 'published') {
            $primary_reason = 'exam_not_published';
            $tone = 'neutral';
        } elseif ($schedule_state['state'] === 'future') {
            $primary_reason = 'exam_not_started';
            $tone = 'warning';
        } elseif ($schedule_state['state'] === 'ended') {
            $primary_reason = 'exam_ended';
            $tone = 'neutral';
        } elseif (empty($audience['allowed'])) {
            $primary_reason = $audience_reason;
            $tone = 'danger';
        } elseif ($attempt_state['state'] === 'completed') {
            $primary_reason = 'attempt_completed';
            $tone = 'neutral';
        } elseif ($attempt_state['state'] === 'in_progress') {
            $primary_reason = 'attempt_in_progress';
            $tone = 'success';
            $can_start = true;
        } else {
            $can_start = true;
        }

        return [
            'exam_id' => absint($exam['id'] ?? 0),
            'title' => sanitize_text_field((string) ($exam['title'] ?? '')),
            'status' => $status,
            'subject_label' => self::format_subject_label($exam),
            'schedule_state' => (string) ($schedule_state['state'] ?? 'unknown'),
            'schedule_label' => (string) ($schedule_state['label'] ?? ''),
            'audience_reason' => $audience_reason,
            'attempt_state' => (string) ($attempt_state['state'] ?? 'none'),
            'attempt_label' => (string) ($attempt_state['label'] ?? ''),
            'attempt_id' => absint($attempt_state['attempt_id'] ?? 0),
            'can_start_now' => $can_start,
            'primary_reason' => sanitize_key($primary_reason),
            'message' => self::diagnosis_reason_message($primary_reason),
            'tone' => sanitize_key($tone),
        ];
    }

    private static function format_subject_label(array $exam): string
    {
        $name = sanitize_text_field((string) ($exam['subject_name'] ?? ''));
        $code = sanitize_text_field((string) ($exam['subject_code'] ?? ''));
        if ($name === '' && $code === '') {
            return '-';
        }

        return $name . ($code !== '' ? ' (' . $code . ')' : '');
    }

    public static function diagnosis_reason_message(string $reason): string
    {
        $messages = [
            'ok' => 'Siswa eligible dan exam bisa dikerjakan.',
            'class_mismatch' => 'Kelas siswa tidak masuk target exam.',
            'agama_mismatch' => 'Agama siswa tidak sesuai filter exam.',
            'gender_mismatch' => 'Jenis kelamin siswa tidak sesuai filter exam.',
            'subject_choice_mismatch' => 'Mapel pilihan siswa tidak memuat mapel exam.',
            'exam_not_published' => 'Exam belum published atau sudah ditutup.',
            'exam_not_started' => 'Jadwal exam belum mulai.',
            'exam_ended' => 'Jadwal exam sudah selesai.',
            'attempt_in_progress' => 'Attempt sedang berjalan; siswa dapat melanjutkan exam.',
            'attempt_completed' => 'Attempt siswa sudah selesai.',
            'invalid_student' => 'Data siswa tidak valid.',
        ];

        return $messages[sanitize_key($reason)] ?? 'Tidak eligible berdasarkan aturan exam.';
    }

    private static function diagnosis_now_timestamp(): int
    {
        if (function_exists('current_time')) {
            return (int) current_time('timestamp');
        }

        return time();
    }

    private static function diagnosis_datetime_timestamp(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? (int) $timestamp : 0;
    }

    /**
     * @param string[] $target_kelas
     * @return array<int,array<string,mixed>>
     */
    private static function get_student_rows_via_users(array $target_kelas): array
    {
        $target_map = !empty($target_kelas) ? array_fill_keys($target_kelas, true) : [];
        $users = get_users(['number' => 0]);
        if (!is_array($users)) {
            return [];
        }

        $rows = [];
        foreach ($users as $user) {
            if (!($user instanceof WP_User) || !self::is_student_user($user)) {
                continue;
            }
            $user_id = (int) $user->ID;
            $profile = self::get_student_profile($user_id);
            $kelas = self::normalize_kelas_code((string) ($profile['kode_kelas'] ?? ''));
            if (!empty($target_map) && ($kelas === '' || !isset($target_map[$kelas]))) {
                continue;
            }
            $rows[] = [
                'user_id' => $user_id,
                'user_login' => (string) $user->user_login,
                'display_name' => (string) $user->display_name,
                'user_email' => (string) $user->user_email,
                'nisn' => (string) ($profile['nisn'] ?? ''),
                'kode_kelas' => $kelas,
                'kode_ruang' => (string) ($profile['kode_ruang'] ?? ''),
                'agama' => (string) ($profile['agama'] ?? ''),
                'jenis_kelamin' => (string) ($profile['jenis_kelamin'] ?? ''),
            ];
        }

        return $rows;
    }

    private static function is_student_user(WP_User $user): bool
    {
        $roles = isset($user->roles) && is_array($user->roles) ? array_map('strtolower', $user->roles) : [];
        foreach (self::STUDENT_ROLES as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    private static function invalidate_student_audience_caches(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }
        if (class_exists('CBT_Cache')) {
            CBT_Cache::invalidate_user($user_id);
        }
        if (class_exists('CBT_Exam_Availability_Cache') && method_exists('CBT_Exam_Availability_Cache', 'clear_student_snapshot')) {
            CBT_Exam_Availability_Cache::clear_student_snapshot($user_id);
        }
    }
}
