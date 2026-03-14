<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Security_Log
{
    private const SETUP_SECURITY_OPTION = 'cbt_setup_security';
    private const TABLE_SUFFIX = 'cbt_security_logs';
    private const OPTION_LAST_PRUNED_AT = 'cbt_security_logs_last_pruned_at';
    private const LOG_RETENTION_DAYS = 30;
    private const PRUNE_INTERVAL_SECONDS = DAY_IN_SECONDS;
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 50;

    /**
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function event_definitions(): array
    {
        return [
            'fullscreen_exit' => [
                'label' => 'Keluar fullscreen',
                'severity' => 'warning',
                'message' => 'Peserta keluar dari mode fullscreen saat ujian berlangsung.',
            ],
            'tab_hidden' => [
                'label' => 'Pindah tab / aplikasi',
                'severity' => 'warning',
                'message' => 'Peserta berpindah tab atau aplikasi saat ujian berlangsung.',
            ],
            'window_blur' => [
                'label' => 'Fokus window berpindah',
                'severity' => 'warning',
                'message' => 'Window ujian kehilangan fokus saat attempt masih berlangsung.',
            ],
            'page_leave' => [
                'label' => 'Meninggalkan halaman',
                'severity' => 'warning',
                'message' => 'Peserta me-refresh, menutup, atau meninggalkan halaman ujian.',
            ],
            'session_revoked' => [
                'label' => 'Sesi dicabut',
                'severity' => 'critical',
                'message' => 'Sesi login attempt ini dicabut karena tidak lagi cocok dengan sesi aktif.',
            ],
            'clipboard_blocked' => [
                'label' => 'Clipboard diblokir',
                'severity' => 'warning',
                'message' => 'Peserta mencoba melakukan copy, cut, atau paste saat ujian berlangsung.',
            ],
            'admin_reset_login' => [
                'label' => 'Reset login admin',
                'severity' => 'info',
                'message' => 'Pengawas atau admin mereset login siswa dari panel attempt.',
            ],
        ];
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
            attempt_id BIGINT UNSIGNED NOT NULL,
            exam_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            student_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            event_type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context_json LONGTEXT NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_attempt_occurred_at (attempt_id, occurred_at),
            KEY idx_student_occurred_at (student_id, occurred_at),
            KEY idx_event_occurred_at (event_type, occurred_at),
            KEY idx_occurred_at (occurred_at)
        ) {$charset};";
    }

    public static function is_logging_enabled(): bool
    {
        $raw = get_option(self::SETUP_SECURITY_OPTION, []);
        if (!is_array($raw)) {
            return false;
        }

        return !empty($raw['log_security_events']);
    }

    public static function maybe_prune_expired_logs(): void
    {
        $last_pruned_at = (int) get_option(self::OPTION_LAST_PRUNED_AT, 0);
        if ($last_pruned_at > 0 && ($last_pruned_at + self::PRUNE_INTERVAL_SECONDS) > time()) {
            return;
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $cutoff_timestamp = current_time('timestamp') - (self::LOG_RETENTION_DAYS * DAY_IN_SECONDS);
        $cutoff = wp_date('Y-m-d H:i:s', $cutoff_timestamp, wp_timezone());

        try {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE occurred_at < %s",
                    $cutoff
                )
            );
        } catch (Throwable $exception) {
            return;
        }

        update_option(self::OPTION_LAST_PRUNED_AT, time(), false);
    }

    public static function record_attempt_event(int $attempt_id, string $event_type, array $context = []): bool
    {
        if (!self::is_logging_enabled()) {
            return false;
        }

        $attempt = self::get_attempt_context($attempt_id);
        if (!$attempt) {
            return false;
        }

        return self::insert_log($attempt, $event_type, $context);
    }

    public static function record_latest_student_attempt_event(int $student_id, string $event_type, array $context = []): bool
    {
        if (!self::is_logging_enabled()) {
            return false;
        }

        $attempt = self::get_latest_attempt_context_for_student($student_id);
        if (!$attempt) {
            return false;
        }

        return self::insert_log($attempt, $event_type, $context);
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_recent_logs(int $limit = self::DEFAULT_LIMIT, array $filters = []): array
    {
        self::maybe_prune_expired_logs();

        global $wpdb;

        $limit = max(1, min(self::MAX_LIMIT, absint($limit)));
        $table = self::get_table_name($wpdb);
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $users_table = $wpdb->users;
        $usermeta_table = $wpdb->usermeta;
        $where = [];
        $params = [];

        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        if ($teacher_id > 0) {
            $where[] = 'e.created_by = %d';
            $params[] = $teacher_id;
        }

        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }

        $params[] = $limit;
        $query = $wpdb->prepare(
            "SELECT l.id,
                    l.attempt_id,
                    l.exam_id,
                    l.student_id,
                    l.event_type,
                    l.severity,
                    l.message,
                    l.context_json,
                    l.occurred_at,
                    l.created_at,
                    u.display_name AS student_display_name,
                    u.user_login AS student_login,
                    (
                        SELECT um.meta_value
                        FROM {$usermeta_table} um
                        WHERE um.user_id = l.student_id
                          AND um.meta_key = 'kode_kelas'
                        ORDER BY um.umeta_id DESC
                        LIMIT 1
                    ) AS student_kode_kelas,
                    (
                        SELECT um.meta_value
                        FROM {$usermeta_table} um
                        WHERE um.user_id = l.student_id
                          AND um.meta_key = 'kode_ruang'
                        ORDER BY um.umeta_id DESC
                        LIMIT 1
                    ) AS student_kode_ruang,
                    e.title AS exam_title
             FROM {$table} l
             LEFT JOIN {$users_table} u ON u.ID = l.student_id
             LEFT JOIN {$exam_table} e ON e.id = l.exam_id
             {$where_sql}
             ORDER BY l.occurred_at DESC, l.id DESC
             LIMIT %d",
            $params
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $definitions = self::event_definitions();

        return array_map(static function ($row) use ($definitions): array {
            $event_type = sanitize_key((string) ($row['event_type'] ?? ''));
            $definition = $definitions[$event_type] ?? null;
            $student_display_name = trim((string) ($row['student_display_name'] ?? ''));
            $student_login = trim((string) ($row['student_login'] ?? ''));
            $student_kode_kelas = trim(sanitize_text_field((string) ($row['student_kode_kelas'] ?? '')));
            $student_kode_ruang = trim(sanitize_text_field((string) ($row['student_kode_ruang'] ?? '')));
            $exam_id = (int) ($row['exam_id'] ?? 0);
            $student_name = $student_display_name !== ''
                ? $student_display_name
                : ($student_login !== '' ? $student_login : ('User #' . (int) ($row['student_id'] ?? 0)));

            $row['event_label'] = $definition['label'] ?? ucwords(str_replace('_', ' ', $event_type));
            $row['severity'] = $definition['severity'] ?? (string) ($row['severity'] ?? 'info');
            $row['student_name'] = $student_name;
            $row['student_kode_kelas'] = $student_kode_kelas;
            $row['student_kode_ruang'] = $student_kode_ruang;
            $row['exam_title'] = trim((string) ($row['exam_title'] ?? '')) !== ''
                ? (string) $row['exam_title']
                : ($exam_id > 0 ? ('Exam #' . $exam_id) : '-');

            return $row;
        }, $rows);
    }

    /**
     * @param int[] $log_ids
     * @param array{teacher_id?:int} $filters
     */
    public static function delete_logs(array $log_ids, array $filters = []): int
    {
        $target_ids = array_values(array_unique(array_filter(array_map('absint', $log_ids))));
        if (empty($target_ids)) {
            return 0;
        }

        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        if ($teacher_id > 0) {
            $target_ids = self::get_deletable_log_ids_for_teacher($target_ids, $teacher_id);
            if (empty($target_ids)) {
                return 0;
            }
        }

        return self::delete_log_ids($target_ids);
    }

    /**
     * @param array{teacher_id?:int} $filters
     */
    public static function delete_all_logs(array $filters = []): int
    {
        global $wpdb;

        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        if ($teacher_id > 0) {
            $target_ids = self::get_deletable_log_ids_for_teacher([], $teacher_id);
            if (empty($target_ids)) {
                return 0;
            }

            return self::delete_log_ids($target_ids);
        }

        $table = self::get_table_name($wpdb);

        try {
            $deleted = $wpdb->query("DELETE FROM {$table}");
        } catch (Throwable $exception) {
            return 0;
        }

        return is_numeric($deleted) ? max(0, (int) $deleted) : 0;
    }

    private static function insert_log(array $attempt, string $event_type, array $context = []): bool
    {
        $event_type = sanitize_key($event_type);
        $definition = self::event_definitions()[$event_type] ?? null;
        if (!$definition) {
            return false;
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $context_json = wp_json_encode(self::normalize_context($context));
        $occurred_at = current_time('mysql');

        try {
            $inserted = $wpdb->insert(
                $table,
                [
                    'attempt_id' => (int) ($attempt['id'] ?? 0),
                    'exam_id' => (int) ($attempt['exam_id'] ?? 0),
                    'student_id' => (int) ($attempt['student_id'] ?? 0),
                    'event_type' => $event_type,
                    'severity' => (string) $definition['severity'],
                    'message' => (string) $definition['message'],
                    'context_json' => is_string($context_json) ? $context_json : '{}',
                    'occurred_at' => $occurred_at,
                    'created_at' => $occurred_at,
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        } catch (Throwable $exception) {
            return false;
        }

        if ($inserted === false) {
            return false;
        }

        self::maybe_prune_expired_logs();

        return true;
    }

    /**
     * @param int[] $target_ids
     */
    private static function delete_log_ids(array $target_ids): int
    {
        global $wpdb;

        $target_ids = array_values(array_unique(array_filter(array_map('absint', $target_ids))));
        if (empty($target_ids)) {
            return 0;
        }

        $table = self::get_table_name($wpdb);
        $deleted_total = 0;

        foreach (array_chunk($target_ids, 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));

            try {
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$table} WHERE id IN ({$placeholders})",
                        $chunk
                    )
                );
            } catch (Throwable $exception) {
                continue;
            }

            if (is_numeric($deleted)) {
                $deleted_total += max(0, (int) $deleted);
            }
        }

        return $deleted_total;
    }

    /**
     * @param int[] $target_ids
     * @return int[]
     */
    private static function get_deletable_log_ids_for_teacher(array $target_ids, int $teacher_id): array
    {
        $teacher_id = absint($teacher_id);
        if ($teacher_id <= 0) {
            return [];
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where_sql = '';
        $params = [$teacher_id];

        if (!empty($target_ids)) {
            $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
            $where_sql = " AND l.id IN ({$placeholders})";
            $params = array_merge($params, $target_ids);
        }

        $query = $wpdb->prepare(
            "SELECT l.id
             FROM {$table} l
             INNER JOIN {$exam_table} e ON e.id = l.exam_id
             WHERE e.created_by = %d{$where_sql}",
            $params
        );

        $rows = $wpdb->get_col($query);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $rows))));
    }

    /**
     * @return array{id:int,exam_id:int,student_id:int,status:string}|null
     */
    private static function get_attempt_context(int $attempt_id): ?array
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return null;
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );

        return is_array($attempt) ? $attempt : null;
    }

    /**
     * @return array{id:int,exam_id:int,student_id:int,status:string}|null
     */
    private static function get_latest_attempt_context_for_student(int $student_id): ?array
    {
        $student_id = absint($student_id);
        if ($student_id <= 0) {
            return null;
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status
                 FROM {$attempt_table}
                 WHERE student_id = %d
                   AND status = 'in_progress'
                 ORDER BY id DESC
                 LIMIT 1",
                $student_id
            ),
            ARRAY_A
        );

        return is_array($attempt) ? $attempt : null;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function normalize_context(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = self::normalize_context($value);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $normalized[$key] = $value;
                continue;
            }

            if ($value === null) {
                $normalized[$key] = '';
                continue;
            }

            $normalized[$key] = sanitize_text_field((string) $value);
        }

        return $normalized;
    }
}
