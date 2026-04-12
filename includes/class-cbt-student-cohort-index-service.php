<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

final class CBT_Student_Cohort_Index_Service
{
    public const CRON_HOOK = 'cbt_student_cohort_index_rebuild_tick';

    private const TABLE_SUFFIX = 'cbt_student_cohort_index';
    private const OPTION_ENABLED = 'cbt_student_cohort_index_enabled';
    private const OPTION_LAST_REBUILD = 'cbt_student_cohort_index_last_rebuild';
    private const OPTION_REBUILD_STATE = 'cbt_student_cohort_index_rebuild_state';
    private const CRON_SCHEDULE = 'cbt_student_cohort_index_every_minute';
    private const LOCK_KEY = 'student_cohort_index_rebuild';
    private const LOCK_TTL = 45;
    private const DEFAULT_REBUILD_BATCH_SIZE = 500;
    private const IMPORTANT_META_KEYS = ['kode_kelas', 'kode_ruang', 'nisn', 'agama'];
    private const STUDENT_ROLES = ['student', 'siswa', 'siswa_cbt', 'subscriber'];

    private static ?bool $table_available = null;
    private static ?array $health_summary_cache = null;
    private static bool $hooks_registered = false;

    public static function init(): void
    {
        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        if (function_exists('add_action')) {
            add_action(self::CRON_HOOK, [self::class, 'handle_cron_tick']);
        }

        self::maybe_restore_rebuild_event();

        if (self::$hooks_registered || !function_exists('add_action')) {
            return;
        }

        add_action('user_register', [self::class, 'handle_user_changed'], 20, 1);
        add_action('profile_update', [self::class, 'handle_user_changed'], 20, 1);
        add_action('set_user_role', [self::class, 'handle_user_role_changed'], 20, 3);
        add_action('add_user_role', [self::class, 'handle_user_role_changed'], 20, 2);
        add_action('remove_user_role', [self::class, 'handle_user_role_changed'], 20, 2);
        add_action('delete_user', [self::class, 'handle_user_deleted'], 20, 1);
        add_action('deleted_user', [self::class, 'handle_user_deleted'], 20, 1);
        add_action('added_user_meta', [self::class, 'handle_user_meta_changed'], 20, 4);
        add_action('updated_user_meta', [self::class, 'handle_user_meta_changed'], 20, 4);
        add_action('deleted_user_meta', [self::class, 'handle_user_meta_changed'], 20, 4);

        self::$hooks_registered = true;
    }

    public static function activate(): void
    {
        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        self::reset_availability_cache();
        if (get_option(self::OPTION_ENABLED, null) === null) {
            add_option(self::OPTION_ENABLED, '1');
        }

        if (self::is_available()) {
            self::start_rebuild('activate');
        }
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'CBT Student Cohort Index Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_tick(): void
    {
        self::tick();
    }

    public static function reset_availability_cache(): void
    {
        self::$table_available = null;
        self::$health_summary_cache = null;
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
            user_id BIGINT UNSIGNED NOT NULL,
            is_student TINYINT(1) NOT NULL DEFAULT 0,
            user_login VARCHAR(60) NOT NULL DEFAULT '',
            display_name VARCHAR(250) NOT NULL DEFAULT '',
            user_email VARCHAR(100) NOT NULL DEFAULT '',
            nisn VARCHAR(64) NOT NULL DEFAULT '',
            kode_kelas VARCHAR(64) NOT NULL DEFAULT '',
            kode_ruang VARCHAR(64) NOT NULL DEFAULT '',
            agama VARCHAR(64) NOT NULL DEFAULT '',
            updated_at DATETIME NULL,
            indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_student_kelas_user (is_student, kode_kelas, user_id),
            KEY idx_student_kelas_ruang_user (is_student, kode_kelas, kode_ruang, user_id),
            KEY idx_student_ruang_user (is_student, kode_ruang, user_id),
            KEY idx_nisn (nisn),
            KEY idx_user_login (user_login)
        ) {$charset};";
    }

    public static function is_enabled(): bool
    {
        $enabled = (string) get_option(self::OPTION_ENABLED, '1');
        return $enabled !== '0' && $enabled !== 'false';
    }

    public static function is_available(): bool
    {
        if (!self::is_enabled()) {
            return false;
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb) {
            return false;
        }

        if (self::$table_available !== null) {
            return self::$table_available;
        }

        $table = self::get_table_name($wpdb);
        try {
            if (!method_exists($wpdb, 'get_var')) {
                self::$table_available = false;
                return false;
            }

            $found = $wpdb->get_var(self::prepare_sql($wpdb, 'SHOW TABLES LIKE %s', [$table]));
            self::$table_available = ((string) $found === $table);
        } catch (Throwable $throwable) {
            self::$table_available = false;
        }

        return self::$table_available;
    }

    public static function is_ready(): bool
    {
        $health = self::get_health_summary();
        return !empty($health['ready']);
    }

    /**
     * @return array{success:bool,message:string,state:array<string,mixed>}
     */
    public static function start_rebuild(string $source = 'admin'): array
    {
        if (!self::is_available()) {
            return [
                'success' => false,
                'message' => 'Student Cohort Index belum tersedia. Jalankan migrasi database terlebih dahulu.',
                'state' => self::get_rebuild_state(),
            ];
        }

        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'start_rebuild'])) {
            return [
                'success' => false,
                'message' => 'Rebuild Student Cohort Index sedang diproses. Coba lagi beberapa saat lagi.',
                'state' => self::get_rebuild_state(),
            ];
        }

        try {
            $now = current_time('mysql');
            $state = self::build_rebuild_state([
                'active' => true,
                'status' => 'active',
                'source' => sanitize_key($source) !== '' ? sanitize_key($source) : 'admin',
                'cursor_user_id' => 0,
                'total_users' => self::count_indexable_users(),
                'processed_total' => 0,
                'last_batch_processed' => 0,
                'started_at' => $now,
                'updated_at' => $now,
                'last_run_at' => '',
                'finished_at' => '',
                'last_message' => 'Rebuild Student Cohort Index dimulai.',
            ]);
            self::save_rebuild_state($state);
            self::ensure_rebuild_event();

            return [
                'success' => true,
                'message' => 'Rebuild Student Cohort Index dimulai. Proses berjalan bertahap di background.',
                'state' => self::get_rebuild_state(),
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(int $batch_size = self::DEFAULT_REBUILD_BATCH_SIZE, string $source = 'cron'): array
    {
        if (!self::is_available()) {
            self::clear_rebuild_event();
            return self::get_rebuild_state();
        }

        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'tick'])) {
            return self::get_rebuild_state();
        }

        try {
            $state = self::get_rebuild_state();
            if (empty($state['active']) && self::should_auto_start_rebuild($state)) {
                $state = self::build_rebuild_state([
                    'active' => true,
                    'status' => 'active',
                    'source' => sanitize_key($source),
                    'cursor_user_id' => 0,
                    'total_users' => self::count_indexable_users(),
                    'processed_total' => 0,
                    'last_batch_processed' => 0,
                    'started_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'last_message' => 'Rebuild Student Cohort Index dimulai otomatis.',
                ]);
            }

            if (empty($state['active'])) {
                self::clear_rebuild_event();
                return $state;
            }

            $cursor = max(0, (int) ($state['cursor_user_id'] ?? 0));
            if (max(0, (int) ($state['total_users'] ?? 0)) <= 0) {
                $state['total_users'] = self::count_indexable_users();
            }
            $result = self::rebuild_batch($batch_size, $cursor);
            $processed_total = max(0, (int) ($state['processed_total'] ?? 0)) + max(0, (int) ($result['processed'] ?? 0));
            $now = current_time('mysql');

            $state['cursor_user_id'] = max(0, (int) ($result['next_cursor_user_id'] ?? $cursor));
            $state['processed_total'] = $processed_total;
            $state['last_batch_processed'] = max(0, (int) ($result['processed'] ?? 0));
            $state['last_run_at'] = $now;
            $state['updated_at'] = $now;

            if (!empty($result['done'])) {
                $state['active'] = false;
                $state['status'] = 'completed';
                $state['finished_at'] = $now;
                $state['last_message'] = sprintf(
                    'Rebuild Student Cohort Index selesai. Total batch processed %d user.',
                    $processed_total
                );
                self::save_rebuild_state($state);
                self::clear_rebuild_event();
                return self::get_rebuild_state();
            }

            $state['active'] = true;
            $state['status'] = 'active';
            $state['last_message'] = sprintf(
                'Rebuild Student Cohort Index berjalan. Batch terakhir %d user, cursor #%d.',
                max(0, (int) ($result['processed'] ?? 0)),
                max(0, (int) ($state['cursor_user_id'] ?? 0))
            );
            self::save_rebuild_state($state);
            self::ensure_rebuild_event();

            return self::get_rebuild_state();
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    public static function upsert_user(int $user_id): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !self::is_available()) {
            return false;
        }

        global $wpdb;
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            self::delete_user($user_id);
            return false;
        }

        $now = current_time('mysql');
        $data = [
            'user_id' => $user_id,
            'is_student' => self::is_student_user($user) ? 1 : 0,
            'user_login' => self::limit_string(sanitize_user((string) $user->user_login, true), 60),
            'display_name' => self::limit_string(sanitize_text_field((string) ($user->display_name ?: $user->user_login)), 250),
            'user_email' => self::limit_string(sanitize_email((string) $user->user_email), 100),
            'nisn' => self::normalize_meta_value((string) get_user_meta($user_id, 'nisn', true), false),
            'kode_kelas' => self::normalize_meta_value((string) get_user_meta($user_id, 'kode_kelas', true), true),
            'kode_ruang' => self::normalize_meta_value((string) get_user_meta($user_id, 'kode_ruang', true), true),
            'agama' => self::normalize_meta_value((string) get_user_meta($user_id, 'agama', true), false),
            'updated_at' => $now,
            'indexed_at' => $now,
        ];

        try {
            $result = $wpdb->replace(
                self::get_table_name($wpdb),
                $data,
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            self::$health_summary_cache = null;
            return $result !== false;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    public static function delete_user(int $user_id): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !self::is_available()) {
            return false;
        }

        global $wpdb;
        try {
            if (method_exists($wpdb, 'delete')) {
                $deleted = $wpdb->delete(self::get_table_name($wpdb), ['user_id' => $user_id], ['%d']) !== false;
                self::$health_summary_cache = null;
                return $deleted;
            }

            $deleted = $wpdb->query(self::prepare_sql($wpdb, 'DELETE FROM ' . self::get_table_name($wpdb) . ' WHERE user_id = %d', [$user_id])) !== false;
            self::$health_summary_cache = null;
            return $deleted;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{available:bool,ready:bool,fallback_required:bool,source:string,user_ids:int[],rows:array<int,array<string,mixed>>,total:int}
     */
    public static function query_students(array $filters = []): array
    {
        $empty = [
            'available' => self::is_available(),
            'ready' => false,
            'fallback_required' => true,
            'source' => 'cohort_index',
            'user_ids' => [],
            'rows' => [],
            'total' => 0,
        ];

        if (!self::is_ready()) {
            return $empty;
        }

        global $wpdb;
        $where = self::build_student_where_clause($wpdb, $filters);
        $limit = max(0, (int) ($filters['limit'] ?? 0));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $params = $where['params'];
        $table = self::get_table_name($wpdb);
        $sql = "SELECT user_id, is_student, user_login, display_name, user_email, nisn, kode_kelas, kode_ruang, agama, updated_at, indexed_at
                FROM {$table}
                WHERE {$where['sql']}
                ORDER BY display_name ASC, user_id ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $limit;
            $params[] = $offset;
        }

        try {
            if (!method_exists($wpdb, 'get_results')) {
                return $empty;
            }

            $rows = $wpdb->get_results(self::prepare_sql($wpdb, $sql, $params), ARRAY_A);
        } catch (Throwable $throwable) {
            return $empty;
        }

        $rows = array_values(array_filter(array_map([self::class, 'normalize_index_row'], is_array($rows) ? $rows : [])));
        $user_ids = array_values(array_unique(array_filter(array_map(static function (array $row): int {
            return absint($row['user_id'] ?? 0);
        }, $rows))));

        return [
            'available' => true,
            'ready' => true,
            'fallback_required' => false,
            'source' => 'cohort_index',
            'user_ids' => $user_ids,
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{available:bool,ready:bool,fallback_required:bool,total:int}
     */
    public static function count_students(array $filters = []): array
    {
        $empty = [
            'available' => self::is_available(),
            'ready' => false,
            'fallback_required' => true,
            'total' => 0,
        ];

        if (!self::is_ready()) {
            return $empty;
        }

        global $wpdb;
        $where = self::build_student_where_clause($wpdb, $filters);
        $table = self::get_table_name($wpdb);

        try {
            if (!method_exists($wpdb, 'get_var')) {
                return $empty;
            }

            $total = $wpdb->get_var(self::prepare_sql(
                $wpdb,
                "SELECT COUNT(*) FROM {$table} WHERE {$where['sql']}",
                $where['params']
            ));
        } catch (Throwable $throwable) {
            return $empty;
        }

        return [
            'available' => true,
            'ready' => true,
            'fallback_required' => false,
            'total' => max(0, (int) $total),
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return int[]
     */
    public static function resolve_target_student_ids_for_exam(array $exam_row): array
    {
        $target_kelas = self::parse_target_kelas((string) ($exam_row['target_kelas'] ?? ''));
        if (empty($target_kelas) || !self::is_ready()) {
            return [];
        }

        $result = self::query_students([
            'kelas_values' => $target_kelas,
            'limit' => 0,
        ]);

        $user_ids = array_values(array_filter(array_map('absint', (array) ($result['user_ids'] ?? []))));
        sort($user_ids, SORT_NUMERIC);

        return $user_ids;
    }

    public static function count_target_students_for_exam(array $exam_row): int
    {
        $target_kelas = self::parse_target_kelas((string) ($exam_row['target_kelas'] ?? ''));
        if (empty($target_kelas) || !self::is_ready()) {
            return 0;
        }

        $result = self::count_students([
            'kelas_values' => $target_kelas,
        ]);

        return empty($result['fallback_required']) ? max(0, (int) ($result['total'] ?? 0)) : 0;
    }

    /**
     * @return array{available:bool,ready:bool,fallback_required:bool,kelas:string[],ruang:string[]}
     */
    public static function get_filter_options(): array
    {
        $empty = [
            'available' => self::is_available(),
            'ready' => false,
            'fallback_required' => true,
            'kelas' => [],
            'ruang' => [],
        ];

        if (!self::is_ready()) {
            return $empty;
        }

        global $wpdb;
        $table = self::get_table_name($wpdb);
        try {
            if (!method_exists($wpdb, 'get_col')) {
                return $empty;
            }

            $kelas = $wpdb->get_col("SELECT DISTINCT kode_kelas FROM {$table} WHERE is_student = 1 AND kode_kelas <> '' ORDER BY kode_kelas ASC");
            $ruang = $wpdb->get_col("SELECT DISTINCT kode_ruang FROM {$table} WHERE is_student = 1 AND kode_ruang <> '' ORDER BY kode_ruang ASC");
        } catch (Throwable $throwable) {
            return $empty;
        }

        return [
            'available' => true,
            'ready' => true,
            'fallback_required' => false,
            'kelas' => array_values(array_filter(array_map([self::class, 'normalize_filter_value'], is_array($kelas) ? $kelas : []))),
            'ruang' => array_values(array_filter(array_map([self::class, 'normalize_filter_value'], is_array($ruang) ? $ruang : []))),
        ];
    }

    /**
     * @return array{available:bool,processed:int,next_cursor_user_id:int,done:bool,last_indexed_at:string}
     */
    public static function rebuild_batch(int $limit = 500, int $cursor_user_id = 0): array
    {
        $limit = max(1, min(5000, $limit));
        $cursor_user_id = max(0, $cursor_user_id);
        $result = [
            'available' => self::is_available(),
            'processed' => 0,
            'next_cursor_user_id' => $cursor_user_id,
            'done' => true,
            'last_indexed_at' => '',
        ];

        if (empty($result['available'])) {
            return $result;
        }

        global $wpdb;
        $users_table = isset($wpdb->users) ? (string) $wpdb->users : ($wpdb->prefix . 'users');
        try {
            if (!method_exists($wpdb, 'get_col')) {
                return $result;
            }

            $ids = $wpdb->get_col(self::prepare_sql(
                $wpdb,
                "SELECT ID FROM {$users_table} WHERE ID > %d ORDER BY ID ASC LIMIT %d",
                [$cursor_user_id, $limit]
            ));
        } catch (Throwable $throwable) {
            return $result;
        }

        $ids = array_values(array_filter(array_map('absint', is_array($ids) ? $ids : [])));
        foreach ($ids as $user_id) {
            self::upsert_user($user_id);
            $result['processed']++;
            $result['next_cursor_user_id'] = $user_id;
        }

        $result['done'] = count($ids) < $limit;
        $result['last_indexed_at'] = current_time('mysql');
        update_option(self::OPTION_LAST_REBUILD, [
            'processed' => $result['processed'],
            'cursor_user_id' => $result['next_cursor_user_id'],
            'done' => $result['done'],
            'last_indexed_at' => $result['last_indexed_at'],
        ], false);

        return $result;
    }

    /**
     * @return array{available:bool,processed:int,next_cursor_user_id:int,done:bool,last_indexed_at:string}
     */
    public static function rebuild_next_batch(int $limit = 500): array
    {
        $last = get_option(self::OPTION_LAST_REBUILD, []);
        $last = is_array($last) ? $last : [];
        $cursor = !empty($last['done'])
            ? 0
            : max(0, (int) ($last['cursor_user_id'] ?? 0));

        return self::rebuild_batch($limit, $cursor);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_rebuild_state(): array
    {
        $state = get_option(self::OPTION_REBUILD_STATE, []);
        return self::build_rebuild_state(is_array($state) ? $state : []);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_health_summary(): array
    {
        if (self::$health_summary_cache !== null) {
            return self::$health_summary_cache;
        }

        $summary = [
            'enabled' => self::is_enabled(),
            'available' => false,
            'ready' => false,
            'status' => 'fallback',
            'label' => 'Fallback',
            'indexed_total' => 0,
            'student_total' => 0,
            'non_student_total' => 0,
            'last_indexed_at' => '',
            'last_rebuild' => get_option(self::OPTION_LAST_REBUILD, []),
            'rebuild_state' => self::get_rebuild_state(),
        ];

        if (empty($summary['enabled']) || !self::is_available()) {
            self::$health_summary_cache = $summary;
            return $summary;
        }

        global $wpdb;
        $table = self::get_table_name($wpdb);
        try {
            if (!method_exists($wpdb, 'get_row')) {
                self::$health_summary_cache = $summary;
                return $summary;
            }

            $row = $wpdb->get_row(
                "SELECT COUNT(*) AS indexed_total,
                        SUM(CASE WHEN is_student = 1 THEN 1 ELSE 0 END) AS student_total,
                        SUM(CASE WHEN is_student = 0 THEN 1 ELSE 0 END) AS non_student_total,
                        MAX(indexed_at) AS last_indexed_at
                 FROM {$table}",
                ARRAY_A
            );
        } catch (Throwable $throwable) {
            self::$health_summary_cache = $summary;
            return $summary;
        }
        $row = is_array($row) ? $row : [];

        $indexed_total = max(0, (int) ($row['indexed_total'] ?? 0));
        $student_total = max(0, (int) ($row['student_total'] ?? 0));
        $summary['available'] = true;
        $summary['indexed_total'] = $indexed_total;
        $summary['student_total'] = $student_total;
        $summary['non_student_total'] = max(0, (int) ($row['non_student_total'] ?? max(0, $indexed_total - $student_total)));
        $summary['last_indexed_at'] = trim((string) ($row['last_indexed_at'] ?? ''));

        if ($student_total > 0) {
            $summary['ready'] = true;
            $summary['status'] = 'ready';
            $summary['label'] = 'Ready';
        } elseif ($indexed_total > 0) {
            $summary['status'] = 'building';
            $summary['label'] = 'Building';
        } else {
            $summary['status'] = 'building';
            $summary['label'] = 'Building';
        }

        self::$health_summary_cache = $summary;
        return $summary;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_rebuild_state(array $state): array
    {
        $status = sanitize_key((string) ($state['status'] ?? 'idle'));
        if (!in_array($status, ['idle', 'active', 'completed', 'failed'], true)) {
            $status = 'idle';
        }
        $active = !empty($state['active']) || $status === 'active';
        if ($active) {
            $status = 'active';
        }

        $next_run_ts = self::next_rebuild_timestamp();

        return [
            'active' => $active,
            'status' => $status,
            'source' => sanitize_key((string) ($state['source'] ?? '')),
            'cursor_user_id' => max(0, (int) ($state['cursor_user_id'] ?? 0)),
            'total_users' => max(0, (int) ($state['total_users'] ?? 0)),
            'processed_total' => max(0, (int) ($state['processed_total'] ?? 0)),
            'last_batch_processed' => max(0, (int) ($state['last_batch_processed'] ?? 0)),
            'started_at' => sanitize_text_field((string) ($state['started_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($state['updated_at'] ?? '')),
            'last_run_at' => sanitize_text_field((string) ($state['last_run_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($state['finished_at'] ?? '')),
            'last_message' => sanitize_text_field((string) ($state['last_message'] ?? '')),
            'scheduled' => $next_run_ts > 0,
            'next_run_at' => $next_run_ts > 0 ? self::format_timestamp($next_run_ts) : '',
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_rebuild_state(array $state): void
    {
        update_option(self::OPTION_REBUILD_STATE, self::build_rebuild_state($state), false);
        self::$health_summary_cache = null;
    }

    private static function count_indexable_users(): int
    {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'get_var')) {
            return 0;
        }

        $users_table = isset($wpdb->users) ? (string) $wpdb->users : ($wpdb->prefix . 'users');
        try {
            return max(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$users_table}"));
        } catch (Throwable $throwable) {
            return 0;
        }
    }

    private static function should_auto_start_rebuild(array $state): bool
    {
        if (!self::is_enabled() || !self::is_available()) {
            return false;
        }

        $status = sanitize_key((string) ($state['status'] ?? 'idle'));
        if ($status === 'completed') {
            return false;
        }

        $health = self::get_health_summary_without_rebuild_state();
        return !empty($health['available']) && empty($health['ready']);
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_health_summary_without_rebuild_state(): array
    {
        $cached = self::$health_summary_cache;
        self::$health_summary_cache = null;
        $summary = self::get_health_summary();
        self::$health_summary_cache = $cached;
        unset($summary['rebuild_state']);
        return $summary;
    }

    private static function maybe_restore_rebuild_event(): void
    {
        if (!self::is_enabled()) {
            self::clear_rebuild_event();
            return;
        }

        $state = self::get_rebuild_state();
        if (!empty($state['active']) || self::should_auto_start_rebuild($state)) {
            self::ensure_rebuild_event();
            return;
        }

        self::clear_rebuild_event();
    }

    private static function ensure_rebuild_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    private static function clear_rebuild_event(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function next_rebuild_timestamp(): int
    {
        if (!function_exists('wp_next_scheduled')) {
            return 0;
        }

        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        return is_numeric($timestamp) ? max(0, (int) $timestamp) : 0;
    }

    private static function format_timestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        if (function_exists('wp_date')) {
            return wp_date('Y-m-d H:i:s', $timestamp);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    public static function handle_user_changed(int $user_id): void
    {
        self::upsert_user($user_id);
    }

    public static function handle_user_role_changed(int $user_id): void
    {
        self::upsert_user($user_id);
    }

    public static function handle_user_deleted(int $user_id): void
    {
        self::delete_user($user_id);
    }

    public static function handle_user_meta_changed($meta_id, $user_id, $meta_key, $meta_value = null): void
    {
        $meta_key = sanitize_key((string) $meta_key);
        if ($meta_key === '' || !in_array($meta_key, self::IMPORTANT_META_KEYS, true)) {
            return;
        }

        self::upsert_user(absint($user_id));
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{sql:string,params:array<int,mixed>}
     */
    private static function build_student_where_clause(wpdb $wpdb, array $filters): array
    {
        $where = ['is_student = 1'];
        $params = [];

        $kelas_values = [];
        if (isset($filters['kelas_values']) && is_array($filters['kelas_values'])) {
            $kelas_values = array_values(array_filter(array_map([self::class, 'normalize_filter_value'], $filters['kelas_values'])));
        }
        $kelas = self::normalize_filter_value((string) ($filters['kelas'] ?? ''));
        if ($kelas !== '') {
            $kelas_values[] = $kelas;
        }
        $kelas_values = array_values(array_unique($kelas_values));
        if (!empty($kelas_values)) {
            $where[] = 'kode_kelas IN (' . implode(',', array_fill(0, count($kelas_values), '%s')) . ')';
            foreach ($kelas_values as $value) {
                $params[] = $value;
            }
        }

        $ruang = self::normalize_filter_value((string) ($filters['ruang'] ?? ''));
        if ($ruang !== '') {
            $where[] = 'kode_ruang = %s';
            $params[] = $ruang;
        }

        $search = trim(sanitize_text_field((string) ($filters['search'] ?? '')));
        if ($search !== '') {
            $like = '%' . self::esc_like($wpdb, strtolower($search)) . '%';
            $where[] = '(LOWER(display_name) LIKE %s OR LOWER(user_login) LIKE %s OR LOWER(user_email) LIKE %s OR LOWER(nisn) LIKE %s OR LOWER(kode_kelas) LIKE %s OR LOWER(kode_ruang) LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        return [
            'sql' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    /**
     * @param array<string,mixed>|object $row
     * @return array<string,mixed>|null
     */
    private static function normalize_index_row($row): ?array
    {
        $row = is_object($row) ? get_object_vars($row) : (array) $row;
        $user_id = absint($row['user_id'] ?? 0);
        if ($user_id <= 0) {
            return null;
        }

        return [
            'user_id' => $user_id,
            'is_student' => (int) ($row['is_student'] ?? 0),
            'user_login' => sanitize_user((string) ($row['user_login'] ?? ''), true),
            'display_name' => sanitize_text_field((string) ($row['display_name'] ?? '')),
            'user_email' => sanitize_email((string) ($row['user_email'] ?? '')),
            'nisn' => self::normalize_meta_value((string) ($row['nisn'] ?? ''), false),
            'kode_kelas' => self::normalize_filter_value((string) ($row['kode_kelas'] ?? '')),
            'kode_ruang' => self::normalize_filter_value((string) ($row['kode_ruang'] ?? '')),
            'agama' => self::normalize_meta_value((string) ($row['agama'] ?? ''), false),
            'updated_at' => sanitize_text_field((string) ($row['updated_at'] ?? '')),
            'indexed_at' => sanitize_text_field((string) ($row['indexed_at'] ?? '')),
        ];
    }

    private static function is_student_user(WP_User $user): bool
    {
        $roles = isset($user->roles) && is_array($user->roles) ? array_map('strtolower', $user->roles) : [];
        foreach (self::STUDENT_ROLES as $student_role) {
            if (in_array($student_role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_meta_value(string $value, bool $uppercase): string
    {
        $value = trim(sanitize_text_field($value));
        if ($uppercase) {
            $value = strtoupper($value);
        }

        return self::limit_string($value, 64);
    }

    private static function normalize_filter_value(string $value): string
    {
        return self::normalize_meta_value($value, true);
    }

    /**
     * @return string[]
     */
    private static function parse_target_kelas(string $raw): array
    {
        if (class_exists('CBT_Admin_Exams_Service') && method_exists('CBT_Admin_Exams_Service', 'split_target_kelas_csv')) {
            return array_values(array_filter(array_map('strval', CBT_Admin_Exams_Service::split_target_kelas_csv($raw))));
        }

        $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $raw);
        $values = [];
        foreach (explode(',', $raw) as $part) {
            $normalized = self::normalize_filter_value($part);
            if ($normalized !== '') {
                $values[$normalized] = $normalized;
            }
        }

        return array_values($values);
    }

    private static function limit_string(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }

    /**
     * @param mixed[] $params
     */
    private static function prepare_sql(wpdb $wpdb, string $sql, array $params = [])
    {
        if (empty($params) || !method_exists($wpdb, 'prepare')) {
            return $sql;
        }

        return call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params));
    }

    private static function esc_like(wpdb $wpdb, string $value): string
    {
        if (method_exists($wpdb, 'esc_like')) {
            return $wpdb->esc_like($value);
        }

        return addcslashes($value, '_%\\');
    }
}
