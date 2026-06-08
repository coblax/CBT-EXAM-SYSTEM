<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Security_Live_Counters')) {
    require_once __DIR__ . '/class-cbt-security-live-counters.php';
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once __DIR__ . '/class-cbt-student-profile-cache.php';
}

if (!class_exists('CBT_Live_Proctoring_Presence')) {
    require_once __DIR__ . '/class-cbt-live-proctoring-presence.php';
}

if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
    require_once __DIR__ . '/class-cbt-live-attempt-roster-index.php';
}

if (!class_exists('CBT_Security_Event_Ingest')) {
    require_once __DIR__ . '/class-cbt-security-event-ingest.php';
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
    private const FULLSCREEN_EXIT_REPEAT_THRESHOLD = 3;
    private const TAB_HIDDEN_REPEAT_THRESHOLD = 3;
    private const WINDOW_BLUR_REPEAT_THRESHOLD = 3;

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
            'forbidden_process_detected' => [
                'label' => 'Aplikasi terlarang terdeteksi',
                'severity' => 'warning',
                'message' => 'Native Windows mendeteksi aplikasi terlarang aktif saat ujian berlangsung.',
            ],
            'forbidden_process_terminated' => [
                'label' => 'Aplikasi terlarang ditutup',
                'severity' => 'warning',
                'message' => 'Native Windows mendeteksi lalu menutup aplikasi terlarang saat ujian berlangsung.',
            ],
            'forbidden_process_active' => [
                'label' => 'Aplikasi terlarang masih aktif',
                'severity' => 'warning',
                'message' => 'Native Windows gagal menutup aplikasi terlarang, sehingga aplikasi masih aktif saat ujian berlangsung.',
            ],
            'task_manager_blocked' => [
                'label' => 'Task Manager diblok',
                'severity' => 'warning',
                'message' => 'Native Windows mendeteksi akses ke Task Manager dan memblokirnya saat ujian berlangsung.',
            ],
            'exit_blocked' => [
                'label' => 'Keluar diblokir',
                'severity' => 'warning',
                'message' => 'Native Windows mendeteksi percobaan keluar dari shell ujian dan memblokirnya.',
            ],
            'page_leave' => [
                'label' => 'Meninggalkan halaman',
                'severity' => 'warning',
                'message' => 'Peserta menutup atau meninggalkan halaman ujian.',
            ],
            'page_refresh' => [
                'label' => 'Refresh halaman',
                'severity' => 'info',
                'message' => 'Peserta me-refresh halaman ujian saat attempt masih berlangsung.',
            ],
            'session_revoked' => [
                'label' => 'Sesi dicabut',
                'severity' => 'critical',
                'message' => 'Sesi login attempt ini dicabut karena tidak lagi cocok dengan sesi aktif.',
            ],
            'session_takeover_stale' => [
                'label' => 'Sesi dipindahkan otomatis',
                'severity' => 'info',
                'message' => 'Login baru mengambil alih sesi lama yang sudah tidak aktif.',
            ],
            'clipboard_blocked' => [
                'label' => 'Clipboard diblokir',
                'severity' => 'warning',
                'message' => 'Peserta mencoba melakukan copy, cut, atau paste saat ujian berlangsung.',
            ],
            'print_attempt' => [
                'label' => 'Percobaan print',
                'severity' => 'warning',
                'message' => 'Peserta mencoba membuka dialog print atau mencetak halaman ujian saat attempt masih berlangsung.',
            ],
            'screenshot_key_detected' => [
                'label' => 'Tombol screenshot terdeteksi',
                'severity' => 'warning',
                'message' => 'Browser menangkap indikasi tombol atau shortcut screenshot saat ujian berlangsung.',
            ],
            'context_menu_blocked' => [
                'label' => 'Context menu diblok',
                'severity' => 'warning',
                'message' => 'Peserta mencoba membuka context menu atau klik kanan saat ujian berlangsung.',
            ],
            'devtools_shortcut_blocked' => [
                'label' => 'Shortcut DevTools diblok',
                'severity' => 'warning',
                'message' => 'Peserta mencoba membuka DevTools atau Inspect lewat shortcut keyboard saat ujian berlangsung.',
            ],
            'view_source_blocked' => [
                'label' => 'View source diblok',
                'severity' => 'warning',
                'message' => 'Peserta mencoba membuka source halaman ujian lewat shortcut keyboard saat attempt masih berlangsung.',
            ],
            'save_page_blocked' => [
                'label' => 'Simpan halaman diblok',
                'severity' => 'warning',
                'message' => 'Peserta mencoba menyimpan halaman ujian lewat shortcut keyboard saat attempt masih berlangsung.',
            ],
            'idle_detected' => [
                'label' => 'Idle saat ujian',
                'severity' => 'warning',
                'message' => 'Peserta tidak menunjukkan aktivitas pada halaman ujian selama ambang waktu yang ditentukan.',
            ],
            'heartbeat_lost' => [
                'label' => 'Heartbeat session hilang',
                'severity' => 'warning',
                'message' => 'Frontend mendeteksi heartbeat session gagal berulang saat ujian berlangsung.',
            ],
            'fullscreen_exit_repeat' => [
                'label' => 'Keluar fullscreen berulang',
                'severity' => 'critical',
                'message' => 'Peserta berulang kali keluar dari mode fullscreen saat ujian berlangsung.',
            ],
            'tab_hidden_repeat' => [
                'label' => 'Pindah tab berulang',
                'severity' => 'warning',
                'message' => 'Peserta berulang kali berpindah tab atau aplikasi saat ujian berlangsung.',
            ],
            'window_blur_repeat' => [
                'label' => 'Blur window berulang',
                'severity' => 'warning',
                'message' => 'Window ujian berulang kali kehilangan fokus saat attempt masih berlangsung.',
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
        return self::native_supported_event_definitions_for_app('');
    }

    /**
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function native_supported_event_definitions_for_app(string $native_app): array
    {
        $definitions = self::event_definitions();
        $supported = [];
        $native_app = sanitize_key($native_app);

        if ($native_app === 'android_webview') {
            $supported_types = self::android_native_event_type_names();
        } elseif ($native_app === 'windows_cefsharp') {
            $supported_types = self::windows_native_event_type_names();
        } else {
            $supported_types = array_values(array_unique(array_merge(
                self::android_native_event_type_names(),
                self::windows_native_event_type_names()
            )));
        }

        foreach ($supported_types as $event_type) {
            if (isset($definitions[$event_type])) {
                $supported[$event_type] = $definitions[$event_type];
            }
        }

        return $supported;
    }

    /**
     * Katalog event browser/CBT saat ini dipisah dari native API supaya event browser seperti refresh halaman
     * bisa punya label dan skor sendiri tanpa otomatis ikut menjadi event native yang diterima endpoint direct API.
     *
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function browser_supported_event_definitions(): array
    {
        $definitions = self::event_definitions();
        $supported_types = [
            'tab_hidden',
            'window_blur',
            'page_leave',
            'page_refresh',
            'fullscreen_exit',
            'clipboard_blocked',
            'print_attempt',
            'screenshot_key_detected',
            'context_menu_blocked',
            'devtools_shortcut_blocked',
            'view_source_blocked',
            'save_page_blocked',
            'idle_detected',
            'heartbeat_lost',
        ];
        $supported = [];

        foreach ($supported_types as $event_type) {
            if (isset($definitions[$event_type])) {
                $supported[$event_type] = $definitions[$event_type];
            }
        }

        return $supported;
    }

    /**
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function android_native_supported_event_definitions(): array
    {
        return self::native_supported_event_definitions_for_app('android_webview');
    }

    /**
     * @return array<string,array{label:string,severity:string,message:string}>
     */
    public static function windows_native_supported_event_definitions(): array
    {
        return self::native_supported_event_definitions_for_app('windows_cefsharp');
    }

    public static function is_native_event_type(string $event_type): bool
    {
        $event_type = sanitize_key($event_type);

        return strpos($event_type, 'native_') === 0
            || in_array($event_type, self::windows_native_event_type_names(), true);
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

    /**
     * @return string[]
     */
    public static function android_native_event_type_names(): array
    {
        return [
            'tab_hidden',
            'window_blur',
            'page_leave',
            'fullscreen_exit',
            'clipboard_blocked',
            'idle_detected',
        ];
    }

    /**
     * @return string[]
     */
    public static function windows_native_event_type_names(): array
    {
        return array_values(array_unique(array_merge(
            self::android_native_event_type_names(),
            [
                'forbidden_process_detected',
                'forbidden_process_terminated',
                'forbidden_process_active',
                'task_manager_blocked',
                'exit_blocked',
            ]
        )));
    }

    public static function get_event_risk_weight(string $event_type): float
    {
        $event_type = sanitize_key($event_type);
        $weights = self::must_watch_event_weights();

        return (float) ($weights[$event_type] ?? 0);
    }

    public static function format_risk_score($score): string
    {
        $safe_score = max(0, (float) $score);
        return number_format_i18n($safe_score, self::risk_score_precision($safe_score));
    }

    public static function format_risk_score_raw($score): string
    {
        $safe_score = max(0, (float) $score);
        return number_format($safe_score, self::risk_score_precision($safe_score), '.', '');
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
            ingest_id CHAR(26) NULL,
            event_type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context_json LONGTEXT NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_ingest_id (ingest_id),
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

        return self::record_attempt_event_for_context($attempt, $event_type, $context);
    }

    /**
     * @param array<string,mixed> $attempt
     */
    public static function record_attempt_event_for_context(array $attempt, string $event_type, array $context = []): bool
    {
        if (!self::is_logging_enabled()) {
            return false;
        }

        if ((int) ($attempt['id'] ?? 0) <= 0) {
            return false;
        }

        return self::record_event_for_attempt_context($attempt, $event_type, $context);
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

        return self::record_attempt_event_for_context($attempt, $event_type, $context);
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

        return self::hydrate_security_log_rows($rows);
    }

    /**
     * @param int[] $attempt_ids
     * @param array{teacher_id?:int,limit?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_attempt_timeline_map(array $attempt_ids, array $filters = []): array
    {
        self::maybe_prune_expired_logs();

        $attempt_ids = array_values(array_unique(array_filter(array_map('absint', $attempt_ids))));
        $timeline_map = [];
        foreach ($attempt_ids as $attempt_id) {
            $timeline_map[$attempt_id] = self::empty_attempt_timeline();
        }
        if (empty($attempt_ids)) {
            return [];
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $users_table = $wpdb->users;
        $limit_per_attempt = max(1, min(100, absint($filters['limit'] ?? 100)));
        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        $placeholders = implode(',', array_fill(0, count($attempt_ids), '%d'));
        $where = ["l.attempt_id IN ({$placeholders})"];
        $params = $attempt_ids;

        if ($teacher_id > 0) {
            $where[] = 'e.created_by = %d';
            $params[] = $teacher_id;
        }

        $query = "SELECT l.id,
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
                         e.title AS exam_title,
                         e.created_by AS exam_created_by
                  FROM {$table} l
                  LEFT JOIN {$users_table} u ON u.ID = l.student_id
                  LEFT JOIN {$exam_table} e ON e.id = l.exam_id
                  WHERE " . implode(' AND ', $where) . '
                  ORDER BY l.attempt_id ASC, l.occurred_at ASC, l.id ASC';

        $rows = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        if (!is_array($rows)) {
            return $timeline_map;
        }

        $hydrated_rows = self::hydrate_security_log_rows($rows);
        $rows_by_attempt = [];
        foreach ($hydrated_rows as $row) {
            $attempt_id = (int) ($row['attempt_id'] ?? 0);
            if (!isset($timeline_map[$attempt_id])) {
                continue;
            }
            if (
                $teacher_id > 0
                && isset($row['exam_created_by'])
                && (int) ($row['exam_created_by'] ?? 0) > 0
                && (int) ($row['exam_created_by'] ?? 0) !== $teacher_id
            ) {
                continue;
            }
            if (!isset($rows_by_attempt[$attempt_id])) {
                $rows_by_attempt[$attempt_id] = [];
            }
            $rows_by_attempt[$attempt_id][] = $row;
        }

        foreach ($timeline_map as $attempt_id => $empty_timeline) {
            $attempt_rows = (array) ($rows_by_attempt[$attempt_id] ?? []);
            $raw_count = count($attempt_rows);
            if ($raw_count > $limit_per_attempt) {
                $attempt_rows = array_slice($attempt_rows, -$limit_per_attempt);
            }
            $timeline_map[$attempt_id] = self::build_attempt_timeline_from_rows($attempt_rows, $raw_count > $limit_per_attempt);
        }

        return $timeline_map;
    }

    /**
     * @param array{teacher_id?:int,limit?:int} $filters
     * @return array<string,mixed>
     */
    public static function get_attempt_timeline(int $attempt_id, array $filters = []): array
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return self::empty_attempt_timeline();
        }

        $map = self::get_attempt_timeline_map([$attempt_id], $filters);
        return isset($map[$attempt_id]) && is_array($map[$attempt_id])
            ? $map[$attempt_id]
            : self::empty_attempt_timeline();
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function hydrate_security_log_rows(array $rows): array
    {
        $rows = self::hydrate_student_security_profile_fields($rows);
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
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function build_attempt_timeline_from_rows(array $rows, bool $truncated = false): array
    {
        if (empty($rows)) {
            $empty = self::empty_attempt_timeline();
            $empty['summary']['truncated'] = $truncated;
            return $empty;
        }

        $event_counts = [];
        $items = [];
        $summary = [
            'total_events' => 0,
            'grouped_items' => 0,
            'warning_count' => 0,
            'critical_count' => 0,
            'info_count' => 0,
            'risk_score' => 0.0,
            'risk_score_label' => '0',
            'risk_tone' => 'normal',
            'risk_label' => 'Normal',
            'first_event_at' => '',
            'last_event_at' => '',
            'truncated' => $truncated,
        ];

        foreach ($rows as $row) {
            $event_type = sanitize_key((string) ($row['event_type'] ?? ''));
            if ($event_type === '') {
                continue;
            }

            $severity = sanitize_key((string) ($row['severity'] ?? 'info'));
            if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
                $severity = 'info';
            }
            $occurred_at = (string) ($row['occurred_at'] ?? $row['created_at'] ?? '');
            $event_weight = self::get_event_risk_weight($event_type);
            $event_label = (string) ($row['event_label'] ?? ucwords(str_replace('_', ' ', $event_type)));

            $summary['total_events']++;
            $summary['risk_score'] += $event_weight;
            if ($severity === 'critical') {
                $summary['critical_count']++;
            } elseif ($severity === 'warning') {
                $summary['warning_count']++;
            } else {
                $summary['info_count']++;
            }
            if ($summary['first_event_at'] === '') {
                $summary['first_event_at'] = $occurred_at;
            }
            $summary['last_event_at'] = $occurred_at;

            if (!isset($event_counts[$event_type])) {
                $event_counts[$event_type] = [
                    'event_type' => $event_type,
                    'label' => $event_label,
                    'severity' => $severity,
                    'count' => 0,
                    'risk_score' => 0.0,
                    'last_occurred_at' => '',
                ];
            }
            $event_counts[$event_type]['count']++;
            $event_counts[$event_type]['risk_score'] += $event_weight;
            $event_counts[$event_type]['last_occurred_at'] = $occurred_at;

            $last_index = count($items) - 1;
            if ($last_index >= 0 && (string) ($items[$last_index]['event_type'] ?? '') === $event_type) {
                $items[$last_index]['count']++;
                $items[$last_index]['last_occurred_at'] = $occurred_at;
                $items[$last_index]['occurred_at'] = $occurred_at;
                $items[$last_index]['total_risk_weight'] += $event_weight;
                $items[$last_index]['message_display'] = (string) ($row['message_display'] ?? $items[$last_index]['message_display']);
                $items[$last_index]['device_summary'] = (string) ($row['device_summary'] ?? $items[$last_index]['device_summary']);
                continue;
            }

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'event_type' => $event_type,
                'event_label' => $event_label,
                'severity' => $severity,
                'message_display' => (string) ($row['message_display'] ?? $row['message'] ?? ''),
                'device_type' => (string) ($row['device_type'] ?? 'unknown'),
                'device_summary' => (string) ($row['device_summary'] ?? $row['device_label'] ?? 'Unknown'),
                'occurred_at' => $occurred_at,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'first_occurred_at' => $occurred_at,
                'last_occurred_at' => $occurred_at,
                'count' => 1,
                'risk_weight' => $event_weight,
                'total_risk_weight' => $event_weight,
            ];
        }

        $summary['grouped_items'] = count($items);
        $summary['risk_score'] = max(0.0, (float) $summary['risk_score']);
        $summary['risk_score_label'] = self::format_risk_score($summary['risk_score']);
        if ($summary['risk_score'] >= self::MUST_WATCH_HIGH_RISK_THRESHOLD) {
            $summary['risk_tone'] = 'high-risk';
            $summary['risk_label'] = 'High Risk';
        } elseif ($summary['risk_score'] >= self::MUST_WATCH_SCORE_THRESHOLD) {
            $summary['risk_tone'] = 'watch';
            $summary['risk_label'] = 'Must Watch';
        }

        $top_indicators = array_values($event_counts);
        usort($top_indicators, static function (array $left, array $right): int {
            $count_compare = (int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0);
            if ($count_compare !== 0) {
                return $count_compare;
            }
            return (float) ($right['risk_score'] ?? 0.0) <=> (float) ($left['risk_score'] ?? 0.0);
        });
        $summary['top_indicators'] = array_slice($top_indicators, 0, 5);

        return [
            'summary' => $summary,
            'event_counts' => $event_counts,
            'items' => $items,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_attempt_timeline(): array
    {
        return [
            'summary' => [
                'total_events' => 0,
                'grouped_items' => 0,
                'warning_count' => 0,
                'critical_count' => 0,
                'info_count' => 0,
                'risk_score' => 0.0,
                'risk_score_label' => '0',
                'risk_tone' => 'normal',
                'risk_label' => 'Normal',
                'first_event_at' => '',
                'last_event_at' => '',
                'truncated' => false,
                'top_indicators' => [],
            ],
            'event_counts' => [],
            'items' => [],
        ];
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_must_watch_attempts(int $limit = self::MUST_WATCH_DEFAULT_LIMIT, array $filters = []): array
    {
        self::maybe_prune_expired_logs();
        $limit = max(1, min(self::MUST_WATCH_MAX_LIMIT, absint($limit)));
        $redis_attempts = self::get_must_watch_attempts_from_live_counters($limit, $filters);
        if (count($redis_attempts) >= $limit) {
            return self::overlay_presence_on_must_watch_attempts(array_slice($redis_attempts, 0, $limit));
        }

        $mysql_attempts = self::get_must_watch_attempts_from_logs($limit, $filters);
        if (empty($redis_attempts)) {
            return self::overlay_presence_on_must_watch_attempts($mysql_attempts);
        }

        $merged = [];
        foreach ($redis_attempts as $attempt) {
            $attempt_id = (int) ($attempt['attempt_id'] ?? 0);
            if ($attempt_id > 0) {
                $merged[$attempt_id] = $attempt;
            }
        }

        foreach ($mysql_attempts as $attempt) {
            $attempt_id = (int) ($attempt['attempt_id'] ?? 0);
            if ($attempt_id <= 0 || isset($merged[$attempt_id])) {
                continue;
            }

            $merged[$attempt_id] = $attempt;
        }

        $must_watch_attempts = array_values($merged);
        self::sort_must_watch_attempt_rows($must_watch_attempts);

        return self::overlay_presence_on_must_watch_attempts(array_slice($must_watch_attempts, 0, $limit));
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    private static function get_must_watch_attempts_from_live_counters(int $limit, array $filters): array
    {
        if (!class_exists('CBT_Security_Live_Counters') || !CBT_Security_Live_Counters::is_available()) {
            return [];
        }

        $aggregated = [];
        foreach (CBT_Security_Live_Counters::get_active_attempt_payloads($filters) as $attempt) {
            $attempt_id = (int) ($attempt['attempt_id'] ?? 0);
            if ($attempt_id <= 0) {
                continue;
            }

            $aggregated[$attempt_id] = [
                'attempt_id' => $attempt_id,
                'exam_id' => (int) ($attempt['exam_id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'student_name' => sanitize_text_field((string) ($attempt['student_name'] ?? '')),
                'student_login' => sanitize_user((string) ($attempt['student_login'] ?? ''), true),
                'student_kode_kelas' => sanitize_text_field((string) ($attempt['student_kode_kelas'] ?? '')),
                'student_kode_ruang' => sanitize_text_field((string) ($attempt['student_kode_ruang'] ?? '')),
                'exam_title' => sanitize_text_field((string) ($attempt['exam_title'] ?? '')),
                'risk_score' => max(0.0, (float) ($attempt['risk_score'] ?? 0.0)),
                'event_total' => max(0, (int) ($attempt['event_total'] ?? 0)),
                'session_revoked_count' => max(0, (int) ($attempt['session_revoked_count'] ?? 0)),
                'last_event_at' => trim((string) ($attempt['last_event_at'] ?? '')),
                'last_device_type' => sanitize_key((string) ($attempt['last_device_type'] ?? 'unknown')),
                'last_device_label' => sanitize_text_field((string) ($attempt['last_device_label'] ?? 'Unknown')),
                'last_device_summary' => sanitize_text_field((string) ($attempt['last_device_summary'] ?? 'Unknown')),
                'event_counts' => is_array($attempt['event_counts'] ?? null) ? $attempt['event_counts'] : [],
            ];
        }

        return self::finalize_must_watch_attempts($aggregated, $limit);
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    private static function get_must_watch_attempts_from_logs(int $limit, array $filters): array
    {
        global $wpdb;

        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        $risk_weights = self::must_watch_event_weights();
        $tracked_events = array_keys(array_filter($risk_weights, static function ($weight): bool {
            return (float) $weight > 0;
        }));

        if (empty($tracked_events)) {
            return [];
        }

        $table = self::get_table_name($wpdb);
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $users_table = $wpdb->users;
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
            $event_weight = isset($risk_weights[$event_type]) ? (float) $risk_weights[$event_type] : 0.0;
            if ($event_weight <= 0) {
                continue;
            }

            $context = self::decode_context_json((string) ($row['context_json'] ?? ''));
            $student_display_name = trim((string) ($row['student_display_name'] ?? ''));
            $student_login = trim((string) ($row['student_login'] ?? ''));
            $student_name = $student_display_name !== ''
                ? $student_display_name
                : ($student_login !== '' ? $student_login : ('User #' . (int) ($row['student_id'] ?? 0)));
            $device_summary = self::build_device_summary_from_context($context, $event_type);

            if (!isset($aggregated[$attempt_id])) {
                $aggregated[$attempt_id] = [
                    'attempt_id' => $attempt_id,
                    'exam_id' => (int) ($row['exam_id'] ?? 0),
                    'student_id' => (int) ($row['student_id'] ?? 0),
                    'student_name' => $student_name,
                    'student_login' => $student_login,
                    'student_kode_kelas' => '',
                    'student_kode_ruang' => '',
                    'exam_title' => trim((string) ($row['exam_title'] ?? '')) !== ''
                        ? (string) $row['exam_title']
                        : ('Exam #' . (int) ($row['exam_id'] ?? 0)),
                    'risk_score' => 0.0,
                    'event_total' => 0,
                    'session_revoked_count' => 0,
                    'last_event_at' => '',
                    'last_device_type' => (string) ($device_summary['device_type'] ?? 'unknown'),
                    'last_device_label' => (string) ($device_summary['device_label'] ?? 'Unknown'),
                    'last_device_summary' => (string) ($device_summary['device_summary'] ?? 'Unknown'),
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
                $aggregated[$attempt_id]['last_device_type'] = (string) ($device_summary['device_type'] ?? 'unknown');
                $aggregated[$attempt_id]['last_device_label'] = (string) ($device_summary['device_label'] ?? 'Unknown');
                $aggregated[$attempt_id]['last_device_summary'] = (string) ($device_summary['device_summary'] ?? 'Unknown');
            }

            if (!isset($aggregated[$attempt_id]['event_counts'][$event_type])) {
                $aggregated[$attempt_id]['event_counts'][$event_type] = [
                    'event_type' => $event_type,
                    'label' => (string) ($definitions[$event_type]['label'] ?? ucwords(str_replace('_', ' ', $event_type))),
                    'count' => 0,
                    'score' => 0.0,
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

        return self::hydrate_student_security_profile_fields(
            self::finalize_must_watch_attempts($aggregated, $limit)
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function hydrate_student_security_profile_fields(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $student_ids = [];
        foreach ($rows as $row) {
            $student_id = absint($row['student_id'] ?? 0);
            if ($student_id > 0) {
                $student_ids[$student_id] = $student_id;
            }
        }

        $profiles = self::load_student_security_profile_fields(array_values($student_ids));
        foreach ($rows as $index => $row) {
            $student_id = absint($row['student_id'] ?? 0);
            $profile = $student_id > 0 && isset($profiles[$student_id]) && is_array($profiles[$student_id])
                ? $profiles[$student_id]
                : [];

            $rows[$index]['student_kode_kelas'] = sanitize_text_field((string) (
                $profile['kode_kelas']
                ?? $row['student_kode_kelas']
                ?? ''
            ));
            $rows[$index]['student_kode_ruang'] = sanitize_text_field((string) (
                $profile['kode_ruang']
                ?? $row['student_kode_ruang']
                ?? ''
            ));
        }

        return $rows;
    }

    /**
     * @param int[] $student_ids
     * @return array<int,array{kode_kelas:string,kode_ruang:string}>
     */
    private static function load_student_security_profile_fields(array $student_ids): array
    {
        $student_ids = array_values(array_unique(array_filter(array_map('absint', $student_ids))));
        if (empty($student_ids)) {
            return [];
        }

        global $wpdb;

        $usermeta_table = $wpdb->usermeta;
        $meta_keys = ['kode_kelas', 'kode_ruang'];
        $user_placeholders = implode(',', array_fill(0, count($student_ids), '%d'));
        $meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $params = array_merge($student_ids, $meta_keys);

        $query = $wpdb->prepare(
            "SELECT um.user_id, um.meta_key, um.meta_value
             FROM {$usermeta_table} um
             INNER JOIN (
                 SELECT user_id, meta_key, MAX(umeta_id) AS latest_umeta_id
                 FROM {$usermeta_table}
                 WHERE user_id IN ({$user_placeholders})
                   AND meta_key IN ({$meta_placeholders})
                 GROUP BY user_id, meta_key
             ) latest ON latest.latest_umeta_id = um.umeta_id",
            $params
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $profiles = [];
        foreach ($rows as $row) {
            $student_id = absint($row['user_id'] ?? 0);
            $meta_key = (string) ($row['meta_key'] ?? '');
            if ($student_id <= 0 || !in_array($meta_key, $meta_keys, true)) {
                continue;
            }

            if (!isset($profiles[$student_id])) {
                $profiles[$student_id] = [
                    'kode_kelas' => '',
                    'kode_ruang' => '',
                ];
            }

            $field = $meta_key === 'kode_kelas' ? 'kode_kelas' : 'kode_ruang';
            $profiles[$student_id][$field] = sanitize_text_field((string) ($row['meta_value'] ?? ''));
        }

        return $profiles;
    }

    /**
     * @param array<int,array<string,mixed>> $aggregated
     * @return array<int,array<string,mixed>>
     */
    private static function finalize_must_watch_attempts(array $aggregated, int $limit): array
    {
        $must_watch_attempts = [];

        foreach ($aggregated as $attempt) {
            $has_session_revoked = (int) ($attempt['session_revoked_count'] ?? 0) > 0;
            $risk_score = (float) ($attempt['risk_score'] ?? 0.0);
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

        self::sort_must_watch_attempt_rows($must_watch_attempts);

        return array_slice($must_watch_attempts, 0, $limit);
    }

    /**
     * @param array<int,array<string,mixed>> $attempts
     */
    private static function sort_must_watch_attempt_rows(array &$attempts): void
    {
        usort($attempts, static function (array $left, array $right): int {
            $left_score = (float) ($left['risk_score'] ?? 0.0);
            $right_score = (float) ($right['risk_score'] ?? 0.0);
            if (abs($left_score - $right_score) > 0.0001) {
                return $right_score <=> $left_score;
            }

            return strcmp((string) ($right['last_event_at'] ?? ''), (string) ($left['last_event_at'] ?? ''));
        });
    }

    /**
     * @param array<int,array<string,mixed>> $attempts
     * @return array<int,array<string,mixed>>
     */
    private static function overlay_presence_on_must_watch_attempts(array $attempts): array
    {
        if (empty($attempts)) {
            return [];
        }

        $attempt_ids = [];
        foreach ($attempts as $index => $attempt) {
            $attempts[$index] = array_merge(self::must_watch_presence_defaults(), $attempt);
            $attempt_id = (int) ($attempt['attempt_id'] ?? 0);
            if ($attempt_id > 0) {
                $attempt_ids[] = $attempt_id;
            }

            if (
                class_exists('CBT_Live_Proctoring_Presence')
                && isset($attempt['risk_tone'])
                && (string) ($attempt['risk_tone'] ?? '') !== ''
            ) {
                CBT_Live_Proctoring_Presence::sync_risk_tone($attempt_id, (string) $attempt['risk_tone']);
            }

            if (
                class_exists('CBT_Live_Attempt_Roster_Index')
                && $attempt_id > 0
                && isset($attempt['risk_tone'])
            ) {
                CBT_Live_Attempt_Roster_Index::sync_risk_summary(
                    $attempt_id,
                    (string) ($attempt['risk_tone'] ?? ''),
                    isset($attempt['risk_score']) ? (float) $attempt['risk_score'] : null
                );
            }
        }

        if (!class_exists('CBT_Live_Proctoring_Presence') || !CBT_Live_Proctoring_Presence::is_available()) {
            return $attempts;
        }

        $presence_payloads = CBT_Live_Proctoring_Presence::get_attempt_payloads($attempt_ids);
        foreach ($attempts as $index => $attempt) {
            $attempt_id = (int) ($attempt['attempt_id'] ?? 0);
            $presence = $presence_payloads[$attempt_id] ?? null;
            if (!is_array($presence)) {
                continue;
            }

            $attempts[$index]['presence_status'] = (string) ($presence['presence_status'] ?? '');
            $attempts[$index]['presence_last_seen_at'] = (string) ($presence['last_seen_at'] ?? '');
            $attempts[$index]['presence_connection_status'] = (string) ($presence['connection_status'] ?? '');
            $attempts[$index]['presence_visibility_state'] = (string) ($presence['visibility_state'] ?? '');
            $attempts[$index]['presence_has_focus'] = (int) ($presence['has_focus'] ?? 0);
            $attempts[$index]['presence_pending_sync_count'] = max(0, (int) ($presence['pending_sync_count'] ?? 0));
            $attempts[$index]['presence_heartbeat_lost_active'] = (int) ($presence['heartbeat_lost_active'] ?? 0);
        }

        return $attempts;
    }

    /**
     * @return array<string,mixed>
     */
    private static function must_watch_presence_defaults(): array
    {
        return [
            'presence_status' => '',
            'presence_last_seen_at' => '',
            'presence_connection_status' => '',
            'presence_visibility_state' => '',
            'presence_has_focus' => null,
            'presence_pending_sync_count' => 0,
            'presence_heartbeat_lost_active' => 0,
        ];
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

    private static function record_event_for_attempt_context(array $attempt, string $event_type, array $context = []): bool
    {
        $event_type = sanitize_key($event_type);
        if ($event_type === 'page_refresh' && self::maybe_promote_recent_page_leave_to_refresh($attempt, $context)) {
            return true;
        }

        $payload = self::build_event_payload($attempt, $event_type, $context);
        if ($payload === null) {
            return false;
        }

        $persisted = false;
        $persist_mode = 'mysql';

        if (class_exists('CBT_Security_Event_Ingest') && CBT_Security_Event_Ingest::is_available()) {
            $persisted = CBT_Security_Event_Ingest::enqueue_event_payload($payload);
            if ($persisted) {
                $persist_mode = 'redis_first';
            }
        }

        if (!$persisted) {
            $persisted = self::persist_event_payload($payload);
            if (!$persisted) {
                return false;
            }
        }

        $live_event_count = self::record_live_counter_event_from_payload($attempt, $payload);
        self::maybe_record_repeat_event($attempt, $event_type, $context, $live_event_count, $persist_mode);

        return true;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private static function build_event_payload(array $attempt, string $event_type, array $context = [], ?string $occurred_at = null): ?array
    {
        $event_type = sanitize_key($event_type);
        $definition = self::event_definitions()[$event_type] ?? null;
        if (!$definition) {
            return null;
        }

        $normalized_context = self::normalize_context($context);
        $context_json = wp_json_encode($normalized_context);
        $occurred_at = $occurred_at !== null && trim($occurred_at) !== ''
            ? sanitize_text_field($occurred_at)
            : current_time('mysql');

        return [
            'attempt_id' => (int) ($attempt['id'] ?? 0),
            'exam_id' => (int) ($attempt['exam_id'] ?? 0),
            'student_id' => (int) ($attempt['student_id'] ?? 0),
            'event_type' => $event_type,
            'severity' => (string) $definition['severity'],
            'message' => (string) $definition['message'],
            'context' => $normalized_context,
            'context_json' => is_string($context_json) ? $context_json : '{}',
            'occurred_at' => $occurred_at,
            'created_at' => $occurred_at,
        ];
    }

    private static function insert_log(array $attempt, string $event_type, array $context = [], ?string &$occurred_at = null): bool
    {
        $payload = self::build_event_payload($attempt, $event_type, $context, $occurred_at);
        if ($payload === null) {
            return false;
        }

        $occurred_at = (string) ($payload['occurred_at'] ?? current_time('mysql'));
        return self::persist_event_payload($payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function persist_ingested_event_payload(array $payload, string $ingest_id = ''): bool
    {
        return self::persist_event_payload($payload, $ingest_id);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function persist_event_payload(array $payload, string $ingest_id = ''): bool
    {
        global $wpdb;

        $table = self::get_table_name($wpdb);
        $ingest_id = trim($ingest_id);
        $data = [
            'attempt_id' => (int) ($payload['attempt_id'] ?? 0),
            'exam_id' => (int) ($payload['exam_id'] ?? 0),
            'student_id' => (int) ($payload['student_id'] ?? 0),
            'ingest_id' => $ingest_id !== '' ? $ingest_id : null,
            'event_type' => sanitize_key((string) ($payload['event_type'] ?? '')),
            'severity' => sanitize_key((string) ($payload['severity'] ?? 'info')),
            'message' => sanitize_text_field((string) ($payload['message'] ?? '')),
            'context_json' => is_string($payload['context_json'] ?? null) ? (string) $payload['context_json'] : '{}',
            'occurred_at' => sanitize_text_field((string) ($payload['occurred_at'] ?? current_time('mysql'))),
            'created_at' => sanitize_text_field((string) ($payload['created_at'] ?? current_time('mysql'))),
        ];
        $format = ['%d', '%d', '%d', $ingest_id !== '' ? '%s' : null, '%s', '%s', '%s', '%s', '%s', '%s'];

        try {
            $inserted = $wpdb->insert(
                $table,
                $data,
                $format
            );
        } catch (Throwable $exception) {
            return false;
        }

        if ($inserted === false) {
            $last_error = property_exists($wpdb, 'last_error') ? (string) ($wpdb->last_error ?? '') : '';
            if ($ingest_id !== '' && stripos($last_error, 'duplicate') !== false) {
                return true;
            }

            return false;
        }

        self::maybe_prune_expired_logs();

        return true;
    }

    private static function record_live_counter_event(array $attempt, string $event_type, array $context, string $occurred_at): int
    {
        $payload = [
            'event_type' => $event_type,
            'context' => self::normalize_context($context),
            'occurred_at' => $occurred_at,
        ];

        return self::record_live_counter_event_from_payload($attempt, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function record_live_counter_event_from_payload(array $attempt, array $payload): int
    {
        if (!class_exists('CBT_Security_Live_Counters') || !CBT_Security_Live_Counters::is_available()) {
            return 0;
        }

        if (strtolower((string) ($attempt['status'] ?? '')) !== 'in_progress') {
            return 0;
        }

        $event_type = sanitize_key((string) ($payload['event_type'] ?? ''));
        $event_weight = self::get_event_risk_weight($event_type);
        if ($event_weight <= 0) {
            return 0;
        }

        $definition = self::event_definitions()[$event_type] ?? null;
        if (!is_array($definition)) {
            return 0;
        }

        $result = CBT_Security_Live_Counters::record_event(
            $attempt,
            $event_type,
            $event_weight,
            (string) ($definition['label'] ?? ucwords(str_replace('_', ' ', $event_type))),
            sanitize_text_field((string) ($payload['occurred_at'] ?? current_time('mysql'))),
            self::build_live_counter_summary_snapshot(
                $attempt,
                is_array($payload['context'] ?? null) ? (array) $payload['context'] : []
            )
        );

        self::sync_presence_risk_tone_from_score(
            (int) ($attempt['id'] ?? 0),
            (float) ($result['risk_score'] ?? 0.0)
        );

        return max(0, (int) ($result['count'] ?? 0));
    }

    private static function sync_presence_risk_tone_from_score(int $attempt_id, float $risk_score): void
    {
        if ($attempt_id <= 0) {
            return;
        }

        $risk_tone = self::presence_risk_tone_from_score($risk_score);

        if (class_exists('CBT_Live_Proctoring_Presence')) {
            CBT_Live_Proctoring_Presence::sync_risk_tone($attempt_id, $risk_tone);
        }

        if (class_exists('CBT_Live_Attempt_Roster_Index')) {
            CBT_Live_Attempt_Roster_Index::sync_risk_summary($attempt_id, $risk_tone, $risk_score);
        }
    }

    private static function presence_risk_tone_from_score(float $risk_score): string
    {
        if ($risk_score >= self::MUST_WATCH_HIGH_RISK_THRESHOLD) {
            return 'high-risk';
        }

        if ($risk_score > 0.0) {
            return 'watch';
        }

        return '';
    }

    private static function maybe_record_repeat_event(
        array $attempt,
        string $event_type,
        array $context = [],
        int $live_event_count = 0,
        string $persist_mode = 'mysql'
    ): void
    {
        $repeat_config = self::repeat_threshold_config($event_type);
        if ($repeat_config === null) {
            return;
        }

        $attempt_id = absint($attempt['id'] ?? 0);
        if ($attempt_id <= 0) {
            return;
        }

        $derived_event = (string) $repeat_config['derived_event'];
        $count_context_key = (string) $repeat_config['count_context_key'];
        $threshold = (int) ($repeat_config['threshold'] ?? 0);
        $source = (string) ($repeat_config['source'] ?? '');

        $use_live_counter = class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::is_available();
        if ($use_live_counter) {
            if (CBT_Security_Live_Counters::has_derived_event($attempt_id, $derived_event)) {
                return;
            }

            $event_count = $live_event_count;
        } else {
            if (self::attempt_has_event_type($attempt_id, $derived_event)) {
                return;
            }

            $event_count = self::count_attempt_event_type($attempt_id, $event_type);
        }

        if ($event_count < $threshold) {
            return;
        }

        $repeat_context = self::normalize_context(array_merge(
            $context,
            [
                'source' => $source,
                'threshold' => $threshold,
                $count_context_key => $event_count,
                'trigger_event' => $event_type,
            ]
        ));

        $payload = self::build_event_payload($attempt, $derived_event, $repeat_context);
        if ($payload === null) {
            return;
        }

        $persisted = false;
        if ($persist_mode === 'redis_first' && class_exists('CBT_Security_Event_Ingest') && CBT_Security_Event_Ingest::is_available()) {
            $persisted = CBT_Security_Event_Ingest::enqueue_event_payload($payload);
        }

        if (!$persisted) {
            $persisted = self::persist_event_payload($payload);
        }

        if (!$persisted) {
            return;
        }

        if ($use_live_counter) {
            CBT_Security_Live_Counters::mark_derived_event($attempt_id, $derived_event);
            self::record_live_counter_event_from_payload($attempt, $payload);
        }
    }

    /**
     * @return array{derived_event:string,threshold:int,source:string,count_context_key:string}|null
     */
    private static function repeat_threshold_config(string $event_type): ?array
    {
        $event_type = sanitize_key($event_type);

        switch ($event_type) {
            case 'fullscreen_exit':
                return [
                    'derived_event' => 'fullscreen_exit_repeat',
                    'threshold' => self::FULLSCREEN_EXIT_REPEAT_THRESHOLD,
                    'source' => 'fullscreen_repeat_threshold',
                    'count_context_key' => 'fullscreen_exit_count',
                ];
            case 'tab_hidden':
                return [
                    'derived_event' => 'tab_hidden_repeat',
                    'threshold' => self::TAB_HIDDEN_REPEAT_THRESHOLD,
                    'source' => 'tab_hidden_repeat_threshold',
                    'count_context_key' => 'tab_hidden_count',
                ];
            case 'window_blur':
                return [
                    'derived_event' => 'window_blur_repeat',
                    'threshold' => self::WINDOW_BLUR_REPEAT_THRESHOLD,
                    'source' => 'window_blur_repeat_threshold',
                    'count_context_key' => 'window_blur_count',
                ];
        }

        return null;
    }

    private static function count_attempt_event_type(int $attempt_id, string $event_type): int
    {
        $attempt_id = absint($attempt_id);
        $event_type = sanitize_key($event_type);
        if ($attempt_id <= 0 || $event_type === '') {
            return 0;
        }

        global $wpdb;

        if (!method_exists($wpdb, 'get_var')) {
            return 0;
        }

        $table = self::get_table_name($wpdb);
        try {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$table}
                     WHERE attempt_id = %d
                       AND event_type = %s",
                    $attempt_id,
                    $event_type
                )
            );
        } catch (Throwable $exception) {
            return 0;
        }

        return max(0, (int) $count);
    }

    private static function attempt_has_event_type(int $attempt_id, string $event_type): bool
    {
        $attempt_id = absint($attempt_id);
        $event_type = sanitize_key($event_type);
        if ($attempt_id <= 0 || $event_type === '') {
            return false;
        }

        global $wpdb;

        if (!method_exists($wpdb, 'get_row')) {
            return false;
        }

        $table = self::get_table_name($wpdb);

        try {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$table}
                     WHERE attempt_id = %d
                       AND event_type = %s
                     ORDER BY occurred_at DESC, id DESC
                     LIMIT 1",
                    $attempt_id,
                    $event_type
                ),
                ARRAY_A
            );
        } catch (Throwable $exception) {
            return false;
        }

        return is_array($row) && (int) ($row['id'] ?? 0) > 0;
    }

    private static function maybe_promote_recent_page_leave_to_refresh(array $attempt, array $context = []): bool
    {
        $definition = self::event_definitions()['page_refresh'] ?? null;
        if (!$definition) {
            return false;
        }

        global $wpdb;

        $table = self::get_table_name($wpdb);
        $recent_leave = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, context_json
                 FROM {$table}
                 WHERE attempt_id = %d
                   AND event_type = %s
                 ORDER BY occurred_at DESC, id DESC
                 LIMIT 1",
                (int) ($attempt['id'] ?? 0),
                'page_leave'
            ),
            ARRAY_A
        );

        if (!is_array($recent_leave) || (int) ($recent_leave['id'] ?? 0) <= 0) {
            return false;
        }

        $existing_context = self::decode_context_json((string) ($recent_leave['context_json'] ?? ''));
        $refresh_context = self::normalize_context($context);

        if (
            !isset($refresh_context['unload_source'])
            && !empty($existing_context['source'])
        ) {
            $refresh_context['unload_source'] = (string) $existing_context['source'];
        }

        if (empty($refresh_context['source'])) {
            $refresh_context['source'] = 'reload_resume';
        }

        $merged_context = array_merge($existing_context, $refresh_context);
        $context_json = wp_json_encode(self::normalize_context($merged_context));
        $log_id = (int) ($recent_leave['id'] ?? 0);

        try {
            if (method_exists($wpdb, 'update')) {
                $updated = $wpdb->update(
                    $table,
                    [
                        'event_type' => 'page_refresh',
                        'severity' => (string) $definition['severity'],
                        'message' => (string) $definition['message'],
                        'context_json' => is_string($context_json) ? $context_json : '{}',
                    ],
                    [
                        'id' => $log_id,
                    ],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table}
                         SET event_type = %s,
                             severity = %s,
                             message = %s,
                             context_json = %s
                         WHERE id = %d",
                        'page_refresh',
                        (string) $definition['severity'],
                        (string) $definition['message'],
                        is_string($context_json) ? $context_json : '{}',
                        $log_id
                    )
                );
            }
        } catch (Throwable $exception) {
            return false;
        }

        if ($updated === false) {
            return false;
        }

        if (class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::is_available()) {
            CBT_Security_Live_Counters::promote_event(
                (int) ($attempt['id'] ?? 0),
                'page_leave',
                self::get_event_risk_weight('page_leave'),
                'page_refresh',
                self::get_event_risk_weight('page_refresh'),
                (string) ($definition['label'] ?? 'Refresh halaman')
            );
        }

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
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function build_live_counter_summary_snapshot(array $attempt, array $context = []): array
    {
        $attempt_id = absint($attempt['id'] ?? 0);
        $student_id = absint($attempt['student_id'] ?? 0);
        $device_summary = self::build_device_summary_from_context($context, sanitize_key((string) ($context['event_type'] ?? '')));

        if ($attempt_id > 0 && class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::has_attempt_summary($attempt_id)) {
            return [
                'last_device_type' => (string) ($device_summary['device_type'] ?? 'unknown'),
                'last_device_label' => (string) ($device_summary['device_label'] ?? 'Unknown'),
                'last_device_summary' => (string) ($device_summary['device_summary'] ?? 'Unknown'),
            ];
        }

        $user = $student_id > 0 ? get_user_by('id', $student_id) : false;
        $exam_meta = self::get_exam_live_meta((int) ($attempt['exam_id'] ?? 0));
        $student_kode_kelas = $student_id > 0 ? sanitize_text_field((string) get_user_meta($student_id, 'kode_kelas', true)) : '';
        $student_kode_ruang = $student_id > 0 ? sanitize_text_field((string) get_user_meta($student_id, 'kode_ruang', true)) : '';

        return [
            'teacher_id' => (int) ($attempt['teacher_id'] ?? ($exam_meta['teacher_id'] ?? 0)),
            'student_name' => $user instanceof WP_User
                ? ($user->display_name !== '' ? (string) $user->display_name : (string) $user->user_login)
                : '',
            'student_login' => $user instanceof WP_User ? (string) $user->user_login : '',
            'student_kode_kelas' => $student_kode_kelas,
            'student_kode_ruang' => $student_kode_ruang,
            'exam_title' => sanitize_text_field((string) ($attempt['exam_title'] ?? ($exam_meta['exam_title'] ?? ''))),
            'last_device_type' => (string) ($device_summary['device_type'] ?? 'unknown'),
            'last_device_label' => (string) ($device_summary['device_label'] ?? 'Unknown'),
            'last_device_summary' => (string) ($device_summary['device_summary'] ?? 'Unknown'),
        ];
    }

    /**
     * @return array{teacher_id:int,exam_title:string}
     */
    private static function get_exam_live_meta(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [
                'teacher_id' => 0,
                'exam_title' => '',
            ];
        }

        static $cache = [];
        if (isset($cache[$exam_id])) {
            return $cache[$exam_id];
        }

        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        try {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, title, created_by
                     FROM {$exam_table}
                     WHERE id = %d
                     LIMIT 1",
                    $exam_id
                ),
                ARRAY_A
            );
        } catch (Throwable $exception) {
            $row = null;
        }

        $cache[$exam_id] = [
            'teacher_id' => is_array($row) ? absint($row['created_by'] ?? 0) : 0,
            'exam_title' => is_array($row) ? sanitize_text_field((string) ($row['title'] ?? '')) : '',
        ];

        return $cache[$exam_id];
    }

    /**
     * @param array<string,mixed> $context
     * @return array{device_type:string,device_label:string,device_summary:string}
     */
    private static function build_device_summary_from_context(array $context, string $event_type = ''): array
    {
        $device_type = self::normalize_device_type((string) ($context['device_type'] ?? ''));
        $device_platform = self::normalize_device_platform((string) ($context['device_platform'] ?? ''));
        if ($device_type === '' && self::is_admin_event_type($event_type)) {
            $device_type = 'server';
        }
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

        return [
            'device_type' => $device_type,
            'device_label' => $device_label !== '' ? $device_label : 'Unknown',
            'device_summary' => implode(' • ', $device_summary_parts),
        ];
    }

    /**
     * @return array<string,float>
     */
    private static function must_watch_event_weights(): array
    {
        return [
            'session_revoked' => 3,
            'page_leave' => 5,
            'page_refresh' => 0.5,
            'fullscreen_exit' => 4,
            'fullscreen_exit_repeat' => 5,
            'tab_hidden_repeat' => 4,
            'tab_hidden' => 3,
            'idle_detected' => 2,
            'clipboard_blocked' => 2,
            'print_attempt' => 3,
            'screenshot_key_detected' => 3,
            'context_menu_blocked' => 1,
            'devtools_shortcut_blocked' => 4,
            'view_source_blocked' => 4,
            'save_page_blocked' => 3,
            'heartbeat_lost' => 2,
            'window_blur_repeat' => 3,
            'window_blur' => 2,
            'forbidden_process_detected' => 3,
            'forbidden_process_terminated' => 3,
            'forbidden_process_active' => 5,
            'task_manager_blocked' => 4,
            'exit_blocked' => 5,
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
     * @param array<string,array{event_type:string,label:string,count:int,score:float,last_at:string}> $event_counts
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
     * @param array<string,array{event_type:string,label:string,count:int,score:float,last_at:string}> $event_counts
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
     * @param array<string,array{event_type:string,label:string,count:int,score:float,last_at:string}> $event_counts
     * @return array<int,array{event_type:string,label:string,count:int,score:float,last_at:string}>
     */
    private static function sort_must_watch_event_items(array $event_counts): array
    {
        if (empty($event_counts)) {
            return [];
        }

        $items = array_values($event_counts);
        usort($items, static function (array $left, array $right): int {
            $left_score = (float) ($left['score'] ?? 0.0);
            $right_score = (float) ($right['score'] ?? 0.0);
            if (abs($left_score - $right_score) > 0.0001) {
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

        $unload_source_label = self::security_context_source_label((string) ($context['unload_source'] ?? ''), $event_type);
        if ($event_type === 'page_refresh' && $unload_source_label !== '') {
            $parts[] = 'Sumber unload awal: ' . $unload_source_label . '.';
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

        if ($event_type === 'print_attempt') {
            $print_blocked = array_key_exists('blocked', $context) ? ((int) ($context['blocked'] ?? 0) === 1) : null;
            if ($print_blocked !== null) {
                $parts[] = 'Diblokir: ' . ($print_blocked ? 'Ya' : 'Tidak') . '.';
            }
        }

        if ($event_type === 'screenshot_key_detected') {
            $key = sanitize_text_field((string) ($context['key'] ?? ''));
            $code = sanitize_text_field((string) ($context['code'] ?? ''));
            $platform_hint = sanitize_text_field((string) ($context['platform_hint'] ?? ''));
            $modifiers = [];

            if (!empty($context['ctrl_key'])) {
                $modifiers[] = 'Ctrl';
            }
            if (!empty($context['meta_key'])) {
                $modifiers[] = 'Meta';
            }
            if (!empty($context['shift_key'])) {
                $modifiers[] = 'Shift';
            }
            if (!empty($context['alt_key'])) {
                $modifiers[] = 'Alt';
            }

            if ($key !== '' && $code !== '') {
                $parts[] = 'Key: ' . $key . ' (' . $code . ').';
            } elseif ($key !== '') {
                $parts[] = 'Key: ' . $key . '.';
            } elseif ($code !== '') {
                $parts[] = 'Code: ' . $code . '.';
            }
            if (!empty($modifiers)) {
                $parts[] = 'Modifier: ' . implode('+', $modifiers) . '.';
            }
            if ($platform_hint !== '') {
                $parts[] = 'Platform hint: ' . $platform_hint . '.';
            }

            $screenshot_blocked = array_key_exists('blocked', $context) ? ((int) ($context['blocked'] ?? 0) === 1) : null;
            if ($screenshot_blocked !== null) {
                $parts[] = 'Diblokir: ' . ($screenshot_blocked ? 'Ya' : 'Tidak') . '.';
            }
        }

        if ($event_type === 'heartbeat_lost') {
            $failure_count = max(0, absint($context['failure_count'] ?? 0));
            $last_error_code = trim((string) ($context['last_error_code'] ?? ''));
            $visibility_state = trim((string) ($context['visibility_state'] ?? ''));
            $has_focus = array_key_exists('has_focus', $context)
                ? ((int) ($context['has_focus'] ?? 0) === 1)
                : null;

            if ($failure_count > 0) {
                $parts[] = sprintf('Heartbeat gagal %dx berturut-turut.', $failure_count);
            }
            if ($last_error_code !== '') {
                $parts[] = 'Kode error terakhir: ' . sanitize_key($last_error_code) . '.';
            }
            if ($visibility_state !== '') {
                $parts[] = 'Visibility: ' . sanitize_key($visibility_state) . '.';
            }
            if ($has_focus !== null) {
                $parts[] = 'Fokus dokumen: ' . ($has_focus ? 'Ya' : 'Tidak') . '.';
            }
        }

        if ($event_type === 'fullscreen_exit_repeat') {
            $fullscreen_exit_count = max(0, absint($context['fullscreen_exit_count'] ?? 0));
            $threshold = max(0, absint($context['threshold'] ?? 0));
            if ($fullscreen_exit_count > 0 && $threshold > 0) {
                $parts[] = sprintf('Keluar fullscreen tercatat %dx (ambang %d).', $fullscreen_exit_count, $threshold);
            }
        }

        if ($event_type === 'tab_hidden_repeat') {
            $tab_hidden_count = max(0, absint($context['tab_hidden_count'] ?? 0));
            $threshold = max(0, absint($context['threshold'] ?? 0));
            if ($tab_hidden_count > 0 && $threshold > 0) {
                $parts[] = sprintf('Pindah tab tercatat %dx (ambang %d).', $tab_hidden_count, $threshold);
            }
        }

        if ($event_type === 'window_blur_repeat') {
            $window_blur_count = max(0, absint($context['window_blur_count'] ?? 0));
            $threshold = max(0, absint($context['threshold'] ?? 0));
            if ($window_blur_count > 0 && $threshold > 0) {
                $parts[] = sprintf('Blur window tercatat %dx (ambang %d).', $window_blur_count, $threshold);
            }
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
            'reload_resume' => 'Resume setelah refresh',
            'admin_reset_user_login' => 'Panel admin',
            'admin_force_complete_attempt' => 'Panel Must Watch',
            'must_watch_panel' => 'Panel Must Watch',
            'android_webview_shell' => 'Android WebView Shell',
            'windows_cefsharp_shell' => 'Windows CEFSharp Shell',
            'native_test_tool' => 'Native test tool',
            'copy' => 'Shortcut atau menu copy',
            'cut' => 'Shortcut atau menu cut',
            'paste' => 'Shortcut atau menu paste',
            'print_shortcut' => 'Shortcut print',
            'beforeprint' => 'Browser print lifecycle',
            'printscreen_key' => 'Tombol PrintScreen',
            'screenshot_key' => 'Shortcut screenshot',
            'macos_screenshot_shortcut' => 'Shortcut screenshot macOS',
            'chromeos_screenshot_shortcut' => 'Shortcut screenshot ChromeOS',
            'chromeos_partial_screenshot_shortcut' => 'Shortcut screenshot parsial ChromeOS',
            'contextmenu' => 'Klik kanan / context menu',
            'devtools_toggle_shortcut' => 'Shortcut buka/tutup DevTools',
            'devtools_console_shortcut' => 'Shortcut Console DevTools',
            'devtools_inspect_shortcut' => 'Shortcut Inspect Element',
            'view_source_shortcut' => 'Shortcut View Source',
            'save_page_shortcut' => 'Shortcut Save Page',
            'session_heartbeat' => 'Session heartbeat',
            'fullscreen_repeat_threshold' => 'Agregasi fullscreen berulang',
            'tab_hidden_repeat_threshold' => 'Agregasi pindah tab berulang',
            'window_blur_repeat_threshold' => 'Agregasi blur berulang',
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

    private static function risk_score_precision(float $score): int
    {
        if (abs($score - round($score)) < 0.0001) {
            return 0;
        }

        if (abs($score - round($score, 1)) < 0.0001) {
            return 1;
        }

        return 2;
    }
}
