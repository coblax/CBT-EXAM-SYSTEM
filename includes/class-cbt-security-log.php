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
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 50;
    private const MUST_WATCH_DEFAULT_LIMIT = 5;
    private const MUST_WATCH_MAX_LIMIT = 10;
    private const MUST_WATCH_SCORE_THRESHOLD = 6;
    private const MUST_WATCH_HIGH_RISK_THRESHOLD = 10;

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
            'idle_detected' => [
                'label' => 'Idle saat ujian',
                'severity' => 'warning',
                'message' => 'Peserta tidak menunjukkan aktivitas pada halaman ujian selama ambang waktu yang ditentukan.',
            ],
            'admin_reset_login' => [
                'label' => 'Reset login admin',
                'severity' => 'info',
                'message' => 'Pengawas atau admin mereset login siswa dari panel attempt.',
            ],
            'admin_force_complete' => [
                'label' => 'Paksa selesai admin',
                'severity' => 'info',
                'message' => 'Pengawas atau admin memaksa attempt selesai dari panel Must Watch.',
            ],
            'native_task_switch' => [
                'label' => 'Task switch native',
                'severity' => 'warning',
                'message' => 'Aplikasi ujian native kehilangan fokus karena perpindahan task atau window.',
            ],
            'native_app_backgrounded' => [
                'label' => 'App native di-background',
                'severity' => 'warning',
                'message' => 'Aplikasi ujian native berpindah ke background saat attempt masih berlangsung.',
            ],
            'native_multi_window' => [
                'label' => 'Multi-window native',
                'severity' => 'warning',
                'message' => 'Aplikasi ujian native mendeteksi mode multi-window atau split-screen saat ujian berjalan.',
            ],
            'native_overlay_detected' => [
                'label' => 'Overlay native terdeteksi',
                'severity' => 'warning',
                'message' => 'Aplikasi ujian native mendeteksi overlay atau jendela lain di atas shell ujian.',
            ],
            'native_kiosk_escape' => [
                'label' => 'Keluar kiosk native',
                'severity' => 'critical',
                'message' => 'Aplikasi ujian native mendeteksi percobaan keluar dari mode kiosk atau lock task.',
            ],
            'native_shell_closed' => [
                'label' => 'Shell native ditutup',
                'severity' => 'critical',
                'message' => 'Shell aplikasi ujian native ditutup saat attempt masih berlangsung.',
            ],
        ];
    }

    /**
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function native_event_definitions(): array
    {
        $native = [];
        foreach (self::event_definitions() as $event_type => $definition) {
            if (self::is_native_event_type($event_type)) {
                $native[$event_type] = $definition;
            }
        }

        return $native;
    }

    /**
     * Native direct API v1 memakai event CBT yang sudah ada agar label, severity, dan scoring tetap konsisten.
     *
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function native_supported_event_definitions(): array
    {
        $definitions = self::event_definitions();
        $supported_types = [
            'tab_hidden',
            'window_blur',
            'page_leave',
            'fullscreen_exit',
            'clipboard_blocked',
            'idle_detected',
        ];
        $supported = [];

        foreach ($supported_types as $event_type) {
            if (isset($definitions[$event_type])) {
                $supported[$event_type] = $definitions[$event_type];
            }
        }

        return $supported;
    }

    public static function is_native_event_type(string $event_type): bool
    {
        return strpos(sanitize_key($event_type), 'native_') === 0;
    }

    /**
     * @return array<string,string>
     */
    public static function native_app_labels(): array
    {
        return [
            'android_webview' => 'Android WebView',
            'windows_cefsharp' => 'Windows CEFSharp',
        ];
    }

    public static function native_app_source(string $native_app): string
    {
        $native_app = sanitize_key($native_app);

        switch ($native_app) {
            case 'android_webview':
                return 'android_webview_shell';
            case 'windows_cefsharp':
                return 'windows_cefsharp_shell';
            default:
                return '';
        }
    }

    public static function native_app_label(string $native_app): string
    {
        $native_app = sanitize_key($native_app);
        $labels = self::native_app_labels();

        return $labels[$native_app] ?? '';
    }

    public static function get_event_risk_weight(string $event_type): int
    {
        $event_type = sanitize_key($event_type);
        $weights = self::must_watch_event_weights();

        return (int) ($weights[$event_type] ?? 0);
    }

    public static function must_watch_score_threshold(): int
    {
        return self::MUST_WATCH_SCORE_THRESHOLD;
    }

    public static function must_watch_high_risk_threshold(): int
    {
        return self::MUST_WATCH_HIGH_RISK_THRESHOLD;
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
            $context = self::decode_context_json((string) ($row['context_json'] ?? ''));
            $student_display_name = trim((string) ($row['student_display_name'] ?? ''));
            $student_login = trim((string) ($row['student_login'] ?? ''));
            $student_kode_kelas = trim(sanitize_text_field((string) ($row['student_kode_kelas'] ?? '')));
            $student_kode_ruang = trim(sanitize_text_field((string) ($row['student_kode_ruang'] ?? '')));
            $exam_id = (int) ($row['exam_id'] ?? 0);
            $device_type = self::normalize_device_type((string) ($context['device_type'] ?? ''));
            $device_platform = self::normalize_device_platform((string) ($context['device_platform'] ?? ''));
            $viewport_width = isset($context['viewport_width']) ? absint($context['viewport_width']) : 0;
            $viewport_height = isset($context['viewport_height']) ? absint($context['viewport_height']) : 0;
            $student_name = $student_display_name !== ''
                ? $student_display_name
                : ($student_login !== '' ? $student_login : ('User #' . (int) ($row['student_id'] ?? 0)));

            if ($device_type === '' && self::is_admin_event_type($event_type)) {
                $device_type = 'server';
            }

            $device_type = $device_type !== '' ? $device_type : 'unknown';
            $device_label = self::device_type_label($device_type);
            $device_platform_label = self::device_platform_label($device_platform);
            $device_summary_parts = [$device_label];
            if ($device_platform_label !== '') {
                $device_summary_parts[] = $device_platform_label;
            }
            if ($viewport_width > 0 && $viewport_height > 0) {
                $device_summary_parts[] = $viewport_width . 'x' . $viewport_height;
            }

            $row['event_label'] = $definition['label'] ?? ucwords(str_replace('_', ' ', $event_type));
            $row['severity'] = $definition['severity'] ?? (string) ($row['severity'] ?? 'info');
            $row['student_name'] = $student_name;
            $row['student_kode_kelas'] = $student_kode_kelas;
            $row['student_kode_ruang'] = $student_kode_ruang;
            $row['device_type'] = $device_type;
            $row['device_label'] = $device_label;
            $row['device_platform'] = $device_platform;
            $row['device_platform_label'] = $device_platform_label;
            $row['device_summary'] = implode(' • ', $device_summary_parts);
            $row['message_display'] = self::build_message_display(
                (string) ($row['message'] ?? ($definition['message'] ?? '')),
                $device_type,
                $row['device_summary'],
                $context,
                $event_type
            );
            $row['context'] = $context;
            $row['exam_title'] = trim((string) ($row['exam_title'] ?? '')) !== ''
                ? (string) $row['exam_title']
                : ($exam_id > 0 ? ('Exam #' . $exam_id) : '-');

            return $row;
        }, $rows);
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_must_watch_attempts(int $limit = self::MUST_WATCH_DEFAULT_LIMIT, array $filters = []): array
    {
        self::maybe_prune_expired_logs();

        global $wpdb;

        $limit = max(1, min(self::MUST_WATCH_MAX_LIMIT, absint($limit)));
        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        $risk_weights = self::must_watch_event_weights();
        $tracked_events = array_keys(array_filter($risk_weights, static function ($weight): bool {
            return (int) $weight > 0;
        }));

        if (empty($tracked_events)) {
            return [];
        }

        $table = self::get_table_name($wpdb);
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $users_table = $wpdb->users;
        $usermeta_table = $wpdb->usermeta;
        $event_placeholders = implode(',', array_fill(0, count($tracked_events), '%s'));
        $where_parts = ["a.status = 'in_progress'", "l.event_type IN ({$event_placeholders})"];
        $params = $tracked_events;

        if ($teacher_id > 0) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $teacher_id;
        }

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
             INNER JOIN {$attempt_table} a ON a.id = l.attempt_id
             INNER JOIN {$exam_table} e ON e.id = l.exam_id
             LEFT JOIN {$users_table} u ON u.ID = l.student_id
             WHERE " . implode(' AND ', $where_parts) . "
             ORDER BY l.occurred_at DESC, l.id DESC",
            $params
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $definitions = self::event_definitions();
        $aggregated = [];

        foreach ($rows as $row) {
            $attempt_id = (int) ($row['attempt_id'] ?? 0);
            if ($attempt_id <= 0) {
                continue;
            }

            $event_type = sanitize_key((string) ($row['event_type'] ?? ''));
            $event_weight = isset($risk_weights[$event_type]) ? (int) $risk_weights[$event_type] : 0;
            if ($event_weight <= 0) {
                continue;
            }

            $context = self::decode_context_json((string) ($row['context_json'] ?? ''));
            $student_display_name = trim((string) ($row['student_display_name'] ?? ''));
            $student_login = trim((string) ($row['student_login'] ?? ''));
            $student_name = $student_display_name !== ''
                ? $student_display_name
                : ($student_login !== '' ? $student_login : ('User #' . (int) ($row['student_id'] ?? 0)));
            $student_kode_kelas = trim(sanitize_text_field((string) ($row['student_kode_kelas'] ?? '')));
            $student_kode_ruang = trim(sanitize_text_field((string) ($row['student_kode_ruang'] ?? '')));
            $device_type = self::normalize_device_type((string) ($context['device_type'] ?? ''));
            $device_platform = self::normalize_device_platform((string) ($context['device_platform'] ?? ''));
            if ($device_type === '') {
                $device_type = 'unknown';
            }

            $device_label = self::device_type_label($device_type);
            $device_platform_label = self::device_platform_label($device_platform);
            $viewport_width = isset($context['viewport_width']) ? absint($context['viewport_width']) : 0;
            $viewport_height = isset($context['viewport_height']) ? absint($context['viewport_height']) : 0;
            $device_summary_parts = [$device_label];
            if ($device_platform_label !== '') {
                $device_summary_parts[] = $device_platform_label;
            }
            if ($viewport_width > 0 && $viewport_height > 0) {
                $device_summary_parts[] = $viewport_width . 'x' . $viewport_height;
            }

            if (!isset($aggregated[$attempt_id])) {
                $aggregated[$attempt_id] = [
                    'attempt_id' => $attempt_id,
                    'exam_id' => (int) ($row['exam_id'] ?? 0),
                    'student_id' => (int) ($row['student_id'] ?? 0),
                    'student_name' => $student_name,
                    'student_login' => $student_login,
                    'student_kode_kelas' => $student_kode_kelas,
                    'student_kode_ruang' => $student_kode_ruang,
                    'exam_title' => trim((string) ($row['exam_title'] ?? '')) !== ''
                        ? (string) $row['exam_title']
                        : ('Exam #' . (int) ($row['exam_id'] ?? 0)),
                    'risk_score' => 0,
                    'event_total' => 0,
                    'session_revoked_count' => 0,
                    'last_event_at' => '',
                    'last_device_type' => 'unknown',
                    'last_device_label' => 'Unknown',
                    'last_device_summary' => 'Unknown',
                    'event_counts' => [],
                ];
            }

            $aggregated[$attempt_id]['risk_score'] += $event_weight;
            $aggregated[$attempt_id]['event_total'] += 1;

            if ($event_type === 'session_revoked') {
                $aggregated[$attempt_id]['session_revoked_count'] += 1;
            }

            if (
                $aggregated[$attempt_id]['last_event_at'] === ''
                || strcmp((string) $row['occurred_at'], (string) $aggregated[$attempt_id]['last_event_at']) > 0
            ) {
                $aggregated[$attempt_id]['last_event_at'] = (string) ($row['occurred_at'] ?? '');
                $aggregated[$attempt_id]['last_device_type'] = $device_type;
                $aggregated[$attempt_id]['last_device_label'] = $device_label !== '' ? $device_label : 'Unknown';
                $aggregated[$attempt_id]['last_device_summary'] = implode(' • ', $device_summary_parts);
            }

            if (!isset($aggregated[$attempt_id]['event_counts'][$event_type])) {
                $aggregated[$attempt_id]['event_counts'][$event_type] = [
                    'event_type' => $event_type,
                    'label' => (string) ($definitions[$event_type]['label'] ?? ucwords(str_replace('_', ' ', $event_type))),
                    'count' => 0,
                    'score' => 0,
                    'last_at' => '',
                ];
            }

            $aggregated[$attempt_id]['event_counts'][$event_type]['count'] += 1;
            $aggregated[$attempt_id]['event_counts'][$event_type]['score'] += $event_weight;
            if (
                $aggregated[$attempt_id]['event_counts'][$event_type]['last_at'] === ''
                || strcmp((string) $row['occurred_at'], (string) $aggregated[$attempt_id]['event_counts'][$event_type]['last_at']) > 0
            ) {
                $aggregated[$attempt_id]['event_counts'][$event_type]['last_at'] = (string) ($row['occurred_at'] ?? '');
            }
        }

        $must_watch_attempts = [];

        foreach ($aggregated as $attempt) {
            $has_session_revoked = (int) ($attempt['session_revoked_count'] ?? 0) > 0;
            $risk_score = (int) ($attempt['risk_score'] ?? 0);
            $event_total = (int) ($attempt['event_total'] ?? 0);

            if (!$has_session_revoked && ($risk_score < self::MUST_WATCH_SCORE_THRESHOLD || $event_total < 2)) {
                continue;
            }

            $attempt['risk_tone'] = $risk_score >= self::MUST_WATCH_HIGH_RISK_THRESHOLD ? 'high-risk' : 'watch';
            $attempt['risk_label'] = $attempt['risk_tone'] === 'high-risk' ? 'High Risk' : 'Must Watch';
            $primary_event = self::build_must_watch_primary_event((array) ($attempt['event_counts'] ?? []));
            $attempt['primary_event_type'] = (string) ($primary_event['event_type'] ?? '');
            $attempt['primary_event_label'] = (string) ($primary_event['label'] ?? '');
            $attempt['top_indicators'] = self::build_must_watch_indicators((array) ($attempt['event_counts'] ?? []));
            unset($attempt['event_counts']);
            $must_watch_attempts[] = $attempt;
        }

        if (empty($must_watch_attempts)) {
            return [];
        }

        usort($must_watch_attempts, static function (array $left, array $right): int {
            $left_score = (int) ($left['risk_score'] ?? 0);
            $right_score = (int) ($right['risk_score'] ?? 0);
            if ($left_score !== $right_score) {
                return $right_score <=> $left_score;
            }

            return strcmp((string) ($right['last_event_at'] ?? ''), (string) ($left['last_event_at'] ?? ''));
        });

        return array_slice($must_watch_attempts, 0, $limit);
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
     * @return array<string,int>
     */
    private static function must_watch_event_weights(): array
    {
        return [
            'session_revoked' => 3,
            'page_leave' => 5,
            'fullscreen_exit' => 4,
            'tab_hidden' => 3,
            'idle_detected' => 2,
            'clipboard_blocked' => 2,
            'window_blur' => 2,
            'admin_reset_login' => 0,
            'admin_force_complete' => 0,
            'native_task_switch' => 3,
            'native_app_backgrounded' => 4,
            'native_multi_window' => 4,
            'native_overlay_detected' => 4,
            'native_kiosk_escape' => 5,
            'native_shell_closed' => 5,
        ];
    }

    /**
     * @param array<string,array{event_type:string,label:string,count:int,score:int,last_at:string}> $event_counts
     * @return string[]
     */
    private static function build_must_watch_indicators(array $event_counts): array
    {
        if (empty($event_counts)) {
            return [];
        }

        $items = self::sort_must_watch_event_items($event_counts);
        $items = array_slice($items, 0, 3);

        return array_values(array_map(static function (array $item): string {
            $count = max(1, (int) ($item['count'] ?? 0));
            $label = trim((string) ($item['label'] ?? 'Event'));
            return $count . 'x ' . $label;
        }, $items));
    }

    /**
     * @param array<string,array{event_type:string,label:string,count:int,score:int,last_at:string}> $event_counts
     * @return array{event_type:string,label:string}
     */
    private static function build_must_watch_primary_event(array $event_counts): array
    {
        if (empty($event_counts)) {
            return [
                'event_type' => '',
                'label' => '',
            ];
        }

        $items = self::sort_must_watch_event_items($event_counts);
        $primary = $items[0] ?? [];

        return [
            'event_type' => sanitize_key((string) ($primary['event_type'] ?? '')),
            'label' => trim((string) ($primary['label'] ?? '')),
        ];
    }

    /**
     * @param array<string,array{event_type:string,label:string,count:int,score:int,last_at:string}> $event_counts
     * @return array<int,array{event_type:string,label:string,count:int,score:int,last_at:string}>
     */
    private static function sort_must_watch_event_items(array $event_counts): array
    {
        if (empty($event_counts)) {
            return [];
        }

        $items = array_values($event_counts);
        usort($items, static function (array $left, array $right): int {
            $left_score = (int) ($left['score'] ?? 0);
            $right_score = (int) ($right['score'] ?? 0);
            if ($left_score !== $right_score) {
                return $right_score <=> $left_score;
            }

            $left_count = (int) ($left['count'] ?? 0);
            $right_count = (int) ($right['count'] ?? 0);
            if ($left_count !== $right_count) {
                return $right_count <=> $left_count;
            }

            return strcmp((string) ($right['last_at'] ?? ''), (string) ($left['last_at'] ?? ''));
        });

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private static function decode_context_json(string $context_json): array
    {
        if ($context_json === '') {
            return [];
        }

        $decoded = json_decode($context_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalize_device_type(string $device_type): string
    {
        $device_type = sanitize_key($device_type);
        if (!in_array($device_type, ['desktop', 'mobile', 'tablet', 'server', 'unknown'], true)) {
            return '';
        }

        return $device_type;
    }

    private static function normalize_device_platform(string $device_platform): string
    {
        $device_platform = sanitize_key($device_platform);
        if (!in_array($device_platform, ['android', 'ios', 'windows', 'macos', 'linux', 'chromeos', 'unknown'], true)) {
            return '';
        }

        return $device_platform;
    }

    private static function device_type_label(string $device_type): string
    {
        switch ($device_type) {
            case 'desktop':
                return 'Desktop';
            case 'mobile':
                return 'Mobile';
            case 'tablet':
                return 'Tablet';
            case 'server':
                return 'Server';
            default:
                return 'Unknown';
        }
    }

    private static function device_platform_label(string $device_platform): string
    {
        switch ($device_platform) {
            case 'android':
                return 'Android';
            case 'ios':
                return 'iOS';
            case 'windows':
                return 'Windows';
            case 'macos':
                return 'macOS';
            case 'linux':
                return 'Linux';
            case 'chromeos':
                return 'ChromeOS';
            default:
                return '';
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function build_message_display(
        string $base_message,
        string $device_type,
        string $device_summary,
        array $context,
        string $event_type
    ): string {
        $parts = [];
        $base_message = trim($base_message);
        if ($base_message !== '') {
            $parts[] = $base_message;
        }

        $device_summary = trim($device_summary);
        if ($device_summary !== '' && $device_type !== 'unknown') {
            $parts[] = 'Diakses dari: ' . $device_summary . '.';
        }

        $source_label = self::security_context_source_label((string) ($context['source'] ?? ''), $event_type);
        if ($source_label !== '') {
            $parts[] = 'Sumber deteksi: ' . $source_label . '.';
        }

        $native_app_label = self::native_app_label((string) ($context['native_app'] ?? ''));
        if ($native_app_label !== '') {
            $parts[] = 'Native app: ' . $native_app_label . '.';
        }

        $warning_code = trim((string) ($context['warning_code'] ?? ''));
        $warning_message = trim((string) ($context['warning_message'] ?? ''));
        if ($warning_code !== '' || $warning_message !== '') {
            $warning_copy = 'Warning native: ';
            if ($warning_message !== '') {
                $warning_copy .= $warning_message;
                if ($warning_code !== '') {
                    $warning_copy .= ' [' . $warning_code . ']';
                }
            } else {
                $warning_copy .= $warning_code;
            }

            $parts[] = $warning_copy . '.';
        }

        $occurred_at_client = trim((string) ($context['occurred_at_client'] ?? ''));
        if ($occurred_at_client !== '') {
            $parts[] = 'Waktu client: ' . $occurred_at_client . '.';
        }

        return implode(' ', $parts);
    }

    private static function security_context_source_label(string $source, string $event_type): string
    {
        $source = sanitize_key($source);
        if ($source === '') {
            return '';
        }

        $labels = [
            'visibilitychange' => 'Visibility API',
            'blur' => 'Window blur',
            'fullscreenchange' => 'Fullscreen API',
            'idle_timer' => 'Timer idle',
            'pagehide' => 'Page lifecycle',
            'beforeunload' => 'Before unload',
            'admin_reset_user_login' => 'Panel admin',
            'admin_force_complete_attempt' => 'Panel Must Watch',
            'must_watch_panel' => 'Panel Must Watch',
            'android_webview_shell' => 'Android WebView Shell',
            'windows_cefsharp_shell' => 'Windows CEFSharp Shell',
            'native_test_tool' => 'Native test tool',
            'copy' => 'Shortcut atau menu copy',
            'cut' => 'Shortcut atau menu cut',
            'paste' => 'Shortcut atau menu paste',
            'insertfrompaste' => 'Paste ke input',
            'insertfrompasteasquotation' => 'Paste kutipan ke input',
            'deletebycut' => 'Cut dari input',
        ];

        if (isset($labels[$source])) {
            return $labels[$source];
        }

        if ($event_type === 'session_revoked' && $source === 'api') {
            return 'Validasi sesi API';
        }

        return ucwords(str_replace('_', ' ', $source));
    }

    private static function is_admin_event_type(string $event_type): bool
    {
        return strpos(sanitize_key($event_type), 'admin_') === 0;
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
