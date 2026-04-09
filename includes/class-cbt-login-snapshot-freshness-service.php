<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-login-auth-snapshot-cache.php';
}

if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
    require_once __DIR__ . '/class-cbt-exam-availability-auto-warm-service.php';
}

if (!class_exists('CBT_Exam_Preflight_Service')) {
    require_once __DIR__ . '/class-cbt-exam-preflight-service.php';
}

final class CBT_Login_Snapshot_Freshness_Service
{
    public const CRON_HOOK = 'cbt_login_snapshot_freshness_tick';

    private const CRON_SCHEDULE = 'cbt_login_snapshot_freshness_every_minute';
    private const LOCK_KEY = 'login_snapshot_freshness';
    private const LOCK_TTL = 45;
    private const OPTION_STATE = 'cbt_login_snapshot_freshness_state';
    private const LOOKAHEAD_SECONDS = 7200;
    private const BUFFER_SECONDS = 3600;
    private const BATCH_SIZE = 150;
    private const MAX_EXAM_BATCHES_PER_TICK = 2;
    private const TICK_BUDGET_SECONDS = 4.0;

    public static function init(): void
    {
        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        if (function_exists('add_action')) {
            add_action(self::CRON_HOOK, [self::class, 'handle_cron_tick']);
        }

        self::maybe_restore_tick_event();
    }

    public static function activate(): void
    {
        self::maybe_restore_tick_event();
    }

    public static function deactivate(): void
    {
        self::clear_tick_event();
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
                'display' => 'CBT Login Snapshot Freshness Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_tick(): void
    {
        self::tick();
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_state(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        return self::normalize_state(is_array($state) ? $state : []);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_summary(): array
    {
        $state = self::get_state();

        return [
            'last_tick_at' => (string) ($state['last_tick_at'] ?? ''),
            'last_message' => (string) ($state['last_message'] ?? ''),
            'last_exam_batch_count' => max(0, (int) ($state['last_exam_batch_count'] ?? 0)),
            'last_refreshed_user_count' => max(0, (int) ($state['last_refreshed_user_count'] ?? 0)),
            'last_refreshed_success_count' => max(0, (int) ($state['last_refreshed_success_count'] ?? 0)),
            'last_skipped_exam_count' => max(0, (int) ($state['last_skipped_exam_count'] ?? 0)),
            'window_exam_count' => max(0, (int) ($state['window_exam_count'] ?? 0)),
            'cursor_exam_id' => max(0, (int) ($state['cursor_exam_id'] ?? 0)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, ['source' => 'login_snapshot_freshness'])) {
            return self::get_state();
        }

        try {
            $state = self::get_state();
            $eligible_exams = self::fetch_window_exam_rows();
            $state['window_exam_count'] = count($eligible_exams);

            if (empty($eligible_exams)) {
                $state['last_tick_at'] = current_time('mysql');
                $state['last_exam_batch_count'] = 0;
                $state['last_refreshed_user_count'] = 0;
                $state['last_refreshed_success_count'] = 0;
                $state['last_skipped_exam_count'] = 0;
                $state['last_message'] = 'Tidak ada exam window yang memerlukan freshness login snapshot.';
                $state = self::normalize_state($state);
                self::save_state($state);
                self::clear_tick_event();
                return $state;
            }

            $ordered_exams = self::order_exams_round_robin($eligible_exams, (int) ($state['cursor_exam_id'] ?? 0));
            $preflight_jobs = class_exists('CBT_Exam_Preflight_Service')
                ? CBT_Exam_Preflight_Service::get_jobs_state()
                : [];
            $now_ts = (int) current_time('timestamp');
            $deadline = microtime(true) + self::TICK_BUDGET_SECONDS;
            $processed_exam_batches = 0;
            $refreshed_user_count = 0;
            $refreshed_success_count = 0;
            $skipped_exam_count = 0;
            $last_exam_id = 0;

            foreach ($ordered_exams as $exam_row) {
                if ($processed_exam_batches >= self::MAX_EXAM_BATCHES_PER_TICK || microtime(true) >= $deadline) {
                    break;
                }

                $exam_id = (int) ($exam_row['id'] ?? 0);
                if ($exam_id <= 0) {
                    continue;
                }

                $processed_exam_batches++;
                $last_exam_id = $exam_id;

                if (self::is_exam_handled_by_preflight($exam_id, $preflight_jobs)) {
                    $skipped_exam_count++;
                    continue;
                }

                $target_student_ids = CBT_Exam_Availability_Auto_Warm_Service::get_target_student_ids_for_exam($exam_row);
                if (empty($target_student_ids)) {
                    continue;
                }

                $required_ttl_seconds = self::build_required_ttl_seconds($exam_row, $now_ts);
                $freshness_map = CBT_Login_Auth_Snapshot_Cache::get_user_snapshot_freshness_map($target_student_ids, $required_ttl_seconds);
                $refresh_user_ids = [];
                foreach ($freshness_map as $user_id => $freshness) {
                    if (!empty($freshness['eligible_for_refresh'])) {
                        $refresh_user_ids[] = (int) $user_id;
                    }
                }

                if (empty($refresh_user_ids)) {
                    continue;
                }

                $refresh_user_ids = array_slice($refresh_user_ids, 0, self::BATCH_SIZE);
                $results = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results($refresh_user_ids, 'freshness_runner');
                $refreshed_user_count += count($refresh_user_ids);
                foreach ($refresh_user_ids as $user_id) {
                    if (!empty($results[$user_id]['ready'])) {
                        $refreshed_success_count++;
                    }
                }
            }

            $state['cursor_exam_id'] = $last_exam_id;
            $state['last_tick_at'] = current_time('mysql');
            $state['last_exam_batch_count'] = $processed_exam_batches;
            $state['last_refreshed_user_count'] = $refreshed_user_count;
            $state['last_refreshed_success_count'] = $refreshed_success_count;
            $state['last_skipped_exam_count'] = $skipped_exam_count;
            $state['last_message'] = sprintf(
                'Freshness login snapshot memeriksa %d exam window. Refresh %d siswa (%d sukses), skip %d exam.',
                $processed_exam_batches,
                $refreshed_user_count,
                $refreshed_success_count,
                $skipped_exam_count
            );
            $state = self::normalize_state($state);
            self::save_state($state);
            self::ensure_tick_event();

            return $state;
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetch_window_exam_rows(): array
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return [];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $sql = "SELECT
                    e.id,
                    e.title,
                    e.status,
                    e.starts_at,
                    e.ends_at,
                    e.duration_minutes,
                    e.target_kelas,
                    s.name AS subject_name
                FROM {$exam_table} e
                LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                WHERE e.status = 'published'
                ORDER BY e.starts_at ASC, e.id ASC";

        $rows = (array) $wpdb->get_results($sql, ARRAY_A);
        $now_ts = (int) current_time('timestamp');
        $max_start_ts = $now_ts + self::LOOKAHEAD_SECONDS;

        return array_values(array_filter(array_map(static function ($row): array {
            return is_array($row) ? $row : [];
        }, $rows), static function (array $exam_row) use ($now_ts, $max_start_ts): bool {
            $starts_at = self::mysql_to_timestamp((string) ($exam_row['starts_at'] ?? ''));
            $ends_at = self::mysql_to_timestamp((string) ($exam_row['ends_at'] ?? ''));

            if ($ends_at > 0 && $ends_at < $now_ts) {
                return false;
            }

            if ($starts_at > 0 && $starts_at > $max_start_ts) {
                return false;
            }

            return sanitize_key((string) ($exam_row['status'] ?? 'draft')) === 'published';
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     * @return array<int,array<string,mixed>>
     */
    private static function order_exams_round_robin(array $exam_rows, int $cursor_exam_id): array
    {
        if (empty($exam_rows)) {
            return [];
        }

        $exam_ids = array_values(array_map(static function (array $exam_row): int {
            return (int) ($exam_row['id'] ?? 0);
        }, $exam_rows));
        $cursor_index = array_search($cursor_exam_id, $exam_ids, true);
        if ($cursor_index === false) {
            return $exam_rows;
        }

        $next_index = ($cursor_index + 1) % count($exam_rows);
        return array_merge(
            array_slice($exam_rows, $next_index),
            array_slice($exam_rows, 0, $next_index)
        );
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     */
    private static function is_exam_handled_by_preflight(int $exam_id, array $jobs): bool
    {
        if ($exam_id <= 0 || !isset($jobs[$exam_id]) || !is_array($jobs[$exam_id])) {
            return false;
        }

        $job = (array) $jobs[$exam_id];
        $status = sanitize_key((string) ($job['status'] ?? 'inactive'));
        return !in_array($status, ['completed', 'failed', 'inactive', 'stopped'], true);
    }

    private static function build_required_ttl_seconds(array $exam_row, int $now_ts): int
    {
        $starts_at_ts = self::mysql_to_timestamp((string) ($exam_row['starts_at'] ?? ''));
        $ends_at_ts = self::mysql_to_timestamp((string) ($exam_row['ends_at'] ?? ''));
        $duration_minutes = max(1, (int) ($exam_row['duration_minutes'] ?? 60));

        if ($ends_at_ts <= 0) {
            $reference_start = $starts_at_ts > 0 ? $starts_at_ts : $now_ts;
            $ends_at_ts = $reference_start + ($duration_minutes * MINUTE_IN_SECONDS);
        }

        return max(0, ($ends_at_ts - $now_ts) + self::BUFFER_SECONDS);
    }

    private static function mysql_to_timestamp(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? (int) $timestamp : 0;
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalize_state(array $state): array
    {
        return [
            'cursor_exam_id' => absint($state['cursor_exam_id'] ?? 0),
            'window_exam_count' => max(0, (int) ($state['window_exam_count'] ?? 0)),
            'last_tick_at' => sanitize_text_field((string) ($state['last_tick_at'] ?? '')),
            'last_message' => sanitize_text_field((string) ($state['last_message'] ?? '')),
            'last_exam_batch_count' => max(0, (int) ($state['last_exam_batch_count'] ?? 0)),
            'last_refreshed_user_count' => max(0, (int) ($state['last_refreshed_user_count'] ?? 0)),
            'last_refreshed_success_count' => max(0, (int) ($state['last_refreshed_success_count'] ?? 0)),
            'last_skipped_exam_count' => max(0, (int) ($state['last_skipped_exam_count'] ?? 0)),
        ];
    }

    private static function save_state(array $state): void
    {
        update_option(self::OPTION_STATE, self::normalize_state($state), false);
    }

    private static function maybe_restore_tick_event(): void
    {
        $state = self::get_state();
        if ((int) ($state['window_exam_count'] ?? 0) > 0 || trim((string) ($state['last_tick_at'] ?? '')) === '') {
            self::ensure_tick_event();
        }
    }

    private static function ensure_tick_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    private static function clear_tick_event(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
