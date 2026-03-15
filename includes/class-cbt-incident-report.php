<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Incident_Report
{
    private const TABLE_SUFFIX = 'cbt_exam_incidents';
    private const CUSTOM_NOTE_OPTION = '__custom__';

    /**
     * @return array<string,string>
     */
    public static function incident_type_definitions(): array
    {
        return [
            'attendance' => 'Kehadiran',
            'cheating' => 'Pelanggaran / Kecurangan',
            'room_activity' => 'Aktivitas di Ruangan',
            'technical_issue' => 'Gangguan Teknis (CBT)',
            'participant_condition' => 'Kondisi Peserta',
            'other' => 'Lainnya / Umum',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function legacy_incident_type_map(): array
    {
        return [
            'late' => 'attendance',
            'absent' => 'attendance',
            'cheating' => 'cheating',
            'technical_issue' => 'technical_issue',
            'left_room' => 'room_activity',
            'sick' => 'participant_condition',
            'other' => 'other',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function legacy_incident_note_labels(): array
    {
        return [
            'late' => 'Terlambat hadir',
            'absent' => 'Tidak hadir',
            'cheating' => 'Kecurangan',
            'technical_issue' => 'Gangguan teknis',
            'left_room' => 'Keluar ruangan',
            'sick' => 'Sakit',
            'other' => 'Lainnya',
        ];
    }

    /**
     * @return array<string,string[]>
     */
    public static function incident_note_definitions(): array
    {
        return [
            'attendance' => [
                'Terlambat hadir',
                'Tidak hadir',
                'Masuk setelah ujian dimulai',
                'Pulang sebelum selesai',
                'Tidak login ke sistem',
                'Login tetapi tidak mengerjakan',
                'Hadir tetapi terlambat login',
            ],
            'cheating' => [
                'Kecurangan',
                'Menggunakan HP',
                'Membuka aplikasi lain',
                'Membuka browser lain / tab lain',
                'Bekerja sama dengan peserta lain',
                'Melihat jawaban peserta lain',
                'Memberi jawaban ke peserta lain',
                'Membawa catatan / contekan',
                'Membawa perangkat elektronik tambahan',
                'Mengambil foto soal',
                'Menggunakan akun peserta lain',
            ],
            'room_activity' => [
                'Keluar ruangan',
                'Izin ke toilet',
                'Pindah tempat duduk',
                'Berbicara dengan peserta lain',
                'Diperingatkan pengawas',
                'Mengganggu peserta lain',
                'Terlihat mencurigakan',
            ],
            'technical_issue' => [
                'Gangguan teknis',
                'Komputer bermasalah',
                'Internet terputus',
                'Aplikasi error',
                'Sistem logout otomatis',
                'Login gagal',
                'Layar freeze / hang',
                'Browser crash',
                'Tidak bisa submit ujian',
                'Soal tidak muncul',
            ],
            'participant_condition' => [
                'Sakit',
                'Pusing / tidak sehat',
                'Peserta panik / tidak bisa melanjutkan',
                'Peserta menangis / stres',
                'Peserta meminta izin keluar',
                'Peserta tidak dapat melanjutkan ujian',
            ],
            'other' => [
                'Catatan umum',
                'Kondisi lain di luar kategori',
            ],
        ];
    }

    public static function custom_note_option_value(): string
    {
        return self::CUSTOM_NOTE_OPTION;
    }

    public static function custom_note_option_label(): string
    {
        return 'Lainnya / tulis manual';
    }

    public static function normalize_incident_type(string $raw): string
    {
        $type = sanitize_key($raw);
        $definitions = self::incident_type_definitions();
        $legacy_map = self::legacy_incident_type_map();

        if (isset($definitions[$type])) {
            return $type;
        }

        return isset($legacy_map[$type]) ? (string) $legacy_map[$type] : '';
    }

    public static function incident_type_label(string $type): string
    {
        $normalized = self::normalize_incident_type($type);
        $definitions = self::incident_type_definitions();

        return $normalized !== '' && isset($definitions[$normalized])
            ? (string) $definitions[$normalized]
            : 'Lainnya';
    }

    /**
     * @return string[]
     */
    public static function incident_note_options_for_type(string $type): array
    {
        $normalized = self::normalize_incident_type($type);
        $definitions = self::incident_note_definitions();

        return isset($definitions[$normalized]) ? array_values((array) $definitions[$normalized]) : [];
    }

    public static function is_valid_incident_note(string $type, string $note): bool
    {
        $note = trim($note);
        if ($note === '') {
            return false;
        }

        return in_array($note, self::incident_note_options_for_type($type), true);
    }

    public static function get_note_for_display(string $raw_type, string $note): string
    {
        $note = trim($note);
        if ($note !== '') {
            return $note;
        }

        $legacy_type = sanitize_key($raw_type);
        $legacy_notes = self::legacy_incident_note_labels();

        return isset($legacy_notes[$legacy_type]) ? (string) $legacy_notes[$legacy_type] : '';
    }

    public static function get_table_name(?wpdb $wpdb = null): string
    {
        if (!($wpdb instanceof wpdb)) {
            global $wpdb;
        }

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function get_create_table_sql(wpdb $wpdb): string
    {
        $charset = $wpdb->get_charset_collate();
        $table = self::get_table_name($wpdb);

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exam_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            incident_type VARCHAR(50) NOT NULL,
            incident_at DATETIME NOT NULL,
            notes TEXT NULL,
            staff_user_id BIGINT UNSIGNED NOT NULL,
            student_name_snapshot VARCHAR(190) NOT NULL,
            student_kelas_snapshot VARCHAR(120) NULL,
            student_ruang_snapshot VARCHAR(120) NULL,
            staff_name_snapshot VARCHAR(190) NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            updated_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_exam_incident_at (exam_id, incident_at),
            KEY idx_student_incident_at (student_id, incident_at),
            KEY idx_incident_type (incident_type),
            KEY idx_incident_at (incident_at)
        ) {$charset};";
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_rows(int $exam_id, string $selected_kelas = '', string $selected_ruang = '', array $filters = []): array
    {
        if ($exam_id <= 0) {
            return [];
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $selected_kelas = trim(sanitize_text_field($selected_kelas));
        $selected_ruang = trim(sanitize_text_field($selected_ruang));
        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;

        $where_parts = ['i.exam_id = %d'];
        $params = [$exam_id];

        if ($selected_kelas !== '') {
            $where_parts[] = 'COALESCE(i.student_kelas_snapshot, \'\') = %s';
            $params[] = $selected_kelas;
        }

        if ($selected_ruang !== '') {
            $where_parts[] = 'COALESCE(i.student_ruang_snapshot, \'\') = %s';
            $params[] = $selected_ruang;
        }

        if ($teacher_id > 0) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $teacher_id;
        }

        $query = $wpdb->prepare(
            "SELECT i.id,
                    i.exam_id,
                    i.student_id,
                    i.incident_type,
                    i.incident_at,
                    i.notes,
                    i.staff_user_id,
                    i.student_name_snapshot,
                    i.student_kelas_snapshot,
                    i.student_ruang_snapshot,
                    i.staff_name_snapshot,
                    i.created_by,
                    i.updated_by,
                    i.created_at,
                    i.updated_at
             FROM {$table} i
             INNER JOIN {$exam_table} e ON e.id = i.exam_id
             WHERE " . implode(' AND ', $where_parts) . "
             ORDER BY i.incident_at DESC, i.id DESC",
            $params
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function ($row): array {
            $raw_incident_type = (string) ($row['incident_type'] ?? '');
            $incident_type = self::normalize_incident_type($raw_incident_type);
            $row['incident_type'] = $incident_type;
            $row['incident_type_label'] = self::incident_type_label($incident_type);
            $row['notes'] = self::get_note_for_display($raw_incident_type, (string) ($row['notes'] ?? ''));
            $row['student_name_snapshot'] = trim((string) ($row['student_name_snapshot'] ?? ''));
            $row['student_kelas_snapshot'] = trim((string) ($row['student_kelas_snapshot'] ?? ''));
            $row['student_ruang_snapshot'] = trim((string) ($row['student_ruang_snapshot'] ?? ''));
            $row['staff_name_snapshot'] = trim((string) ($row['staff_name_snapshot'] ?? ''));

            return $row;
        }, $rows);
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<string,mixed>
     */
    public static function get_row(int $incident_id, array $filters = []): array
    {
        if ($incident_id <= 0) {
            return [];
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;

        $where_parts = ['i.id = %d'];
        $params = [$incident_id];

        if ($teacher_id > 0) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $teacher_id;
        }

        $query = $wpdb->prepare(
            "SELECT i.id,
                    i.exam_id,
                    i.student_id,
                    i.incident_type,
                    i.incident_at,
                    i.notes,
                    i.staff_user_id,
                    i.student_name_snapshot,
                    i.student_kelas_snapshot,
                    i.student_ruang_snapshot,
                    i.staff_name_snapshot,
                    i.created_by,
                    i.updated_by,
                    i.created_at,
                    i.updated_at
             FROM {$table} i
             INNER JOIN {$exam_table} e ON e.id = i.exam_id
             WHERE " . implode(' AND ', $where_parts) . "
             LIMIT 1",
            $params
        );

        $row = $wpdb->get_row($query, ARRAY_A);
        if (!is_array($row)) {
            return [];
        }

        $raw_incident_type = (string) ($row['incident_type'] ?? '');
        $incident_type = self::normalize_incident_type($raw_incident_type);
        $row['incident_type'] = $incident_type;
        $row['incident_type_label'] = self::incident_type_label($incident_type);
        $row['notes'] = self::get_note_for_display($raw_incident_type, (string) ($row['notes'] ?? ''));
        $row['student_name_snapshot'] = trim((string) ($row['student_name_snapshot'] ?? ''));
        $row['student_kelas_snapshot'] = trim((string) ($row['student_kelas_snapshot'] ?? ''));
        $row['student_ruang_snapshot'] = trim((string) ($row['student_ruang_snapshot'] ?? ''));
        $row['staff_name_snapshot'] = trim((string) ($row['staff_name_snapshot'] ?? ''));

        return $row;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function insert(array $data): int|false
    {
        global $wpdb;

        $table = self::get_table_name($wpdb);
        $payload = self::normalize_payload($data);
        $result = $wpdb->insert($table, $payload, self::payload_formats($payload));
        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function update(int $incident_id, array $data): bool
    {
        if ($incident_id <= 0) {
            return false;
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $payload = self::normalize_payload($data);
        $updated = $wpdb->update(
            $table,
            $payload,
            ['id' => $incident_id],
            self::payload_formats($payload),
            ['%d']
        );

        return $updated !== false;
    }

    public static function delete(int $incident_id): bool
    {
        if ($incident_id <= 0) {
            return false;
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $deleted = $wpdb->delete($table, ['id' => $incident_id], ['%d']);

        return $deleted !== false;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function normalize_payload(array $data): array
    {
        $payload = [];
        $allowed_columns = [
            'exam_id',
            'student_id',
            'incident_type',
            'incident_at',
            'notes',
            'staff_user_id',
            'student_name_snapshot',
            'student_kelas_snapshot',
            'student_ruang_snapshot',
            'staff_name_snapshot',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];

        foreach ($allowed_columns as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $payload[$column] = $data[$column];
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return string[]
     */
    private static function payload_formats(array $payload): array
    {
        $formats = [];

        foreach (array_keys($payload) as $column) {
            if (in_array($column, ['exam_id', 'student_id', 'staff_user_id', 'created_by', 'updated_by'], true)) {
                $formats[] = '%d';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
