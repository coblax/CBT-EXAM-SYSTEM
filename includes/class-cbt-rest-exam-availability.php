<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Exam_Availability_Helpers
{
    /**
     * @return array<string,mixed>
     */
    private static function get_exams_payload(int $user_id, string $role): array
    {
        if (self::is_student_role($role) && CBT_Exam_Availability_Cache::is_available()) {
            return CBT_Exam_Availability_Cache::get_student_snapshot(
                $user_id,
                static function () use ($user_id, $role): array {
                    return self::build_exams_payload($user_id, $role);
                }
            );
        }

        return CBT_Cache::remember(
            'rest:exams:user:' . $user_id . ':role:' . strtolower((string) $role),
            15,
            [CBT_Cache::namespace_catalog(), CBT_Cache::namespace_user($user_id)],
            static function () use ($user_id, $role): array {
                return self::build_exams_payload($user_id, $role);
            }
        );
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}
     */
    public static function build_student_exam_availability_snapshot_payload(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [
                'items' => [],
                'current_user' => null,
            ];
        }

        $batch_payloads = self::build_batch_student_exam_availability_snapshot_payloads([$user_id]);
        return isset($batch_payloads[$user_id]) && is_array($batch_payloads[$user_id])
            ? $batch_payloads[$user_id]
            : [
                'items' => [],
                'current_user' => null,
            ];
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array{items:array<int,array<string,mixed>>,current_user:array<string,mixed>|null}>
     */
    public static function build_batch_student_exam_availability_snapshot_payloads(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_unique(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        self::prime_student_availability_user_caches($user_ids);

        $student_now = current_time('mysql');
        $server_timezone = wp_timezone_string();
        $exam_rows = self::fetch_published_exam_rows_for_availability();
        $latest_attempts_by_user = self::fetch_latest_attempts_by_user_and_exam($user_ids);
        $payloads = [];

        foreach ($user_ids as $user_id) {
            $profile_snapshot = self::get_live_user_profile($user_id);
            $student_kelas = self::normalize_kelas_code((string) ($profile_snapshot['kode_kelas'] ?? ''));
            $latest_attempt_by_exam = isset($latest_attempts_by_user[$user_id]) && is_array($latest_attempts_by_user[$user_id])
                ? $latest_attempts_by_user[$user_id]
                : [];

            $payloads[$user_id] = [
                'items' => self::build_student_exam_availability_items(
                    $exam_rows,
                    $student_kelas,
                    $student_now,
                    $server_timezone,
                    $latest_attempt_by_exam
                ),
                'current_user' => self::build_student_exam_current_user_payload($user_id, 'student', $profile_snapshot),
            ];
        }

        return $payloads;
    }
    /**
     * @return array<string,mixed>
     */
    private static function build_exams_payload(int $user_id, string $role): array
    {
        if (self::is_student_role($role)) {
            $batch_payloads = self::build_batch_student_exam_availability_snapshot_payloads([$user_id]);
            return isset($batch_payloads[$user_id]) && is_array($batch_payloads[$user_id])
                ? $batch_payloads[$user_id]
                : [
                    'items' => [],
                    'current_user' => null,
                ];
        }

        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';

        $where = '1=1';
        $params = [];

        if ($role === 'guru' || $role === 'teacher') {
            $where .= ' AND created_by = %d';
            $params[] = $user_id;
        }

                $sql = "SELECT
                    e.id,
                    e.subject_id,
                    e.title,
                    e.duration_minutes,
                    e.kkm_percentage,
                    e.total_questions,
                    e.randomize_questions,
                    e.show_student_result,
                    e.enable_calculator,
                    e.status,
                    e.starts_at,
                    e.ends_at,
                    e.target_kelas,
                    e.created_by,
                    e.created_at,
                    e.updated_at,
                    s.name AS subject_name,
                    s.code AS subject_code,
                    COALESCE(NULLIF(e.total_questions, 0), 0) AS question_count
                FROM {$exam_table} e
                LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                WHERE {$where}
                ORDER BY e.created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = array_map(static function ($row): array {
                $item = (array) $row;
                $item['kkm_percentage'] = self::normalize_exam_kkm_percentage((float) ($item['kkm_percentage'] ?? 75.0));
                $item['show_student_result'] = self::normalize_show_student_result($item['show_student_result'] ?? 1);
                $item['enable_calculator'] = self::normalize_enable_calculator($item['enable_calculator'] ?? 1);
                return $item;
            }, (array) $wpdb->get_results($sql, ARRAY_A));

        $current_user_payload = null;
        $current_user = get_user_by('id', $user_id);
        if ($current_user instanceof WP_User) {
            $profile_snapshot = self::get_live_user_profile($user_id);
            $current_user_payload = [
                'user_id' => $user_id,
                'role' => $role,
                'display_name' => (string) $current_user->display_name,
                'username' => (string) $current_user->user_login,
                'email' => (string) $current_user->user_email,
                'kode_kelas' => (string) ($profile_snapshot['kode_kelas'] ?? ''),
                'kode_ruang' => (string) ($profile_snapshot['kode_ruang'] ?? ''),
                'agama' => (string) ($profile_snapshot['agama'] ?? ''),
                'foto' => (string) ($profile_snapshot['foto'] ?? ''),
            ];
        }

        return [
            'items' => $rows ?: [],
            'current_user' => $current_user_payload,
        ];
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    private static function get_live_user_profile(int $user_id): array
    {
        return CBT_Student_Profile_Cache::get_snapshot($user_id);
    }

    private static function get_live_user_kelas(int $user_id): string
    {
        $profile = self::get_live_user_profile($user_id);
        return self::normalize_kelas_code((string) ($profile['kode_kelas'] ?? ''));
    }

    /**
     * @param int[] $user_ids
     */
    private static function prime_student_availability_user_caches(array $user_ids): void
    {
        if (empty($user_ids)) {
            return;
        }

        if (function_exists('cache_users')) {
            cache_users($user_ids);
        }

        if (function_exists('update_meta_cache')) {
            update_meta_cache('user', $user_ids);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetch_published_exam_rows_for_availability(): array
    {
        static $request_cache = null;
        $catalog_entry = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        $catalog_version = max(1, (int) ($catalog_entry['version'] ?? 1));

        if (
            is_array($request_cache)
            && (int) ($request_cache['catalog_version'] ?? 0) === $catalog_version
            && isset($request_cache['items'])
            && is_array($request_cache['items'])
        ) {
            return $request_cache['items'];
        }

        $catalog = CBT_Cache::remember(
            'rest:exams:availability:base_catalog',
            self::AVAILABILITY_BASE_CATALOG_TTL,
            [CBT_Cache::namespace_catalog()],
            static function (): array {
                global $wpdb;

                $exam_table = $wpdb->prefix . 'cbt_exams';
                $subject_table = $wpdb->prefix . 'cbt_subjects';
                $question_table = $wpdb->prefix . 'cbt_questions';
                $question_count_subquery = "SELECT exam_id, COUNT(*) AS question_count
                    FROM {$question_table}
                    WHERE COALESCE(is_active, 1) = 1
                    GROUP BY exam_id";
                $sql = "SELECT
                            e.id,
                            e.subject_id,
                            e.title,
                            e.duration_minutes,
                            e.kkm_percentage,
                            e.total_questions,
                            e.randomize_questions,
                            e.show_student_result,
                            e.enable_calculator,
                            e.status,
                            e.starts_at,
                            e.ends_at,
                            e.target_kelas,
                            e.created_by,
                            e.created_at,
                            e.updated_at,
                            s.name AS subject_name,
                            s.code AS subject_code,
                            COALESCE(qc.question_count, 0) AS question_count
                        FROM {$exam_table} e
                        LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                        LEFT JOIN ({$question_count_subquery}) qc ON qc.exam_id = e.id
                        WHERE e.status = 'published'
                        ORDER BY e.created_at DESC";

                return array_map(static function ($row): array {
                    $item = (array) $row;
                    $item['kkm_percentage'] = self::normalize_exam_kkm_percentage((float) ($item['kkm_percentage'] ?? 75.0));
                    $item['show_student_result'] = self::normalize_show_student_result($item['show_student_result'] ?? 1);
                    $item['enable_calculator'] = self::normalize_enable_calculator($item['enable_calculator'] ?? 1);
                    return $item;
                }, (array) $wpdb->get_results($sql, ARRAY_A));
            }
        );

        $request_cache = [
            'catalog_version' => $catalog_version,
            'items' => is_array($catalog) ? $catalog : [],
        ];

        return $request_cache['items'];
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function fetch_latest_attempts_by_user_and_exam(array $user_ids): array
    {
        global $wpdb;

        $user_ids = array_values(array_filter(array_unique(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $placeholders = implode(', ', array_fill(0, count($user_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT a.student_id, a.exam_id, a.id, a.status, a.score, a.max_score, a.started_at, a.finished_at, a.extra_time_minutes
             FROM {$attempt_table} a
             WHERE a.student_id IN ({$placeholders})
               AND a.status IN ('in_progress', 'completed')
             ORDER BY a.student_id ASC, a.exam_id ASC, FIELD(a.status, 'in_progress', 'completed'), a.id DESC",
            ...$user_ids
        );
        $rows = (array) $wpdb->get_results($query, ARRAY_A);
        $attempts_by_user = [];

        foreach ($rows as $attempt_row) {
            $attempt_row = (array) $attempt_row;
            $student_id = (int) ($attempt_row['student_id'] ?? 0);
            if ($student_id <= 0 && count($user_ids) === 1) {
                $student_id = (int) $user_ids[0];
            }

            $exam_id = (int) ($attempt_row['exam_id'] ?? 0);
            if ($student_id <= 0 || $exam_id <= 0) {
                continue;
            }

            if (!isset($attempts_by_user[$student_id])) {
                $attempts_by_user[$student_id] = [];
            }

            if (!isset($attempts_by_user[$student_id][$exam_id])) {
                $attempts_by_user[$student_id][$exam_id] = $attempt_row;
            }
        }

        return $attempts_by_user;
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     * @param array<int,array<string,mixed>> $latest_attempt_by_exam
     * @return array<int,array<string,mixed>>
     */
    private static function build_student_exam_availability_items(
        array $exam_rows,
        string $student_kelas,
        string $student_now,
        string $server_timezone,
        array $latest_attempt_by_exam
    ): array {
        $items = array_map(static function ($row) use ($student_kelas, $student_now, $server_timezone, $latest_attempt_by_exam): array {
            $item = (array) $row;
            $now_ts = strtotime($student_now);
            $start_ts = !empty($item['starts_at']) ? strtotime((string) $item['starts_at']) : false;
            $end_ts = !empty($item['ends_at']) ? strtotime((string) $item['ends_at']) : false;

            $within_schedule = (
                (empty($item['starts_at']) || (string) $item['starts_at'] <= $student_now) &&
                (empty($item['ends_at']) || (string) $item['ends_at'] >= $student_now)
            );
            $class_allowed = self::exam_allows_student_class($item, $student_kelas);

            $schedule_reason = 'in_range';
            if ($start_ts !== false && $now_ts !== false && $start_ts > $now_ts) {
                $schedule_reason = 'not_started';
            } elseif ($end_ts !== false && $now_ts !== false && $end_ts < $now_ts) {
                $schedule_reason = 'ended';
            }

            $availability_reason = 'ok';
            if (!$class_allowed) {
                $availability_reason = 'class_mismatch';
            } elseif (!$within_schedule) {
                $availability_reason = $schedule_reason;
            }

            $item['is_within_schedule'] = $within_schedule ? 1 : 0;
            $item['is_class_allowed'] = $class_allowed ? 1 : 0;
            $item['is_available_now'] = ($within_schedule && $class_allowed) ? 1 : 0;
            $item['availability_reason'] = $availability_reason;
            $item['server_now'] = $student_now;
            $item['server_timezone'] = $server_timezone;

            $exam_id = (int) ($item['id'] ?? 0);
            $latest_attempt = ($exam_id > 0 && isset($latest_attempt_by_exam[$exam_id]))
                ? (array) $latest_attempt_by_exam[$exam_id]
                : null;
            $item['latest_attempt_finalize_pending'] = 0;
            $item['latest_attempt_ui_state'] = '';
            $item['latest_attempt_poll_after_ms'] = 0;
            $item['latest_attempt_expired_at'] = '';
            if ($latest_attempt) {
                $show_student_result = self::normalize_show_student_result($item['show_student_result'] ?? 1);
                $attempt_score = (float) ($latest_attempt['score'] ?? 0);
                $attempt_max_score = (float) ($latest_attempt['max_score'] ?? 0);
                $attempt_percentage = $attempt_max_score > 0
                    ? round(($attempt_score / $attempt_max_score) * 100, 2)
                    : 0.0;
                $attempt_pass_meta = self::build_result_pass_meta($attempt_score, $attempt_max_score, (float) ($item['kkm_percentage'] ?? 75.0));
                $item['latest_attempt_id'] = (int) ($latest_attempt['id'] ?? 0);
                $item['latest_attempt_status'] = (string) ($latest_attempt['status'] ?? '');
                $item['latest_attempt_score'] = $attempt_score;
                $item['latest_attempt_max_score'] = $attempt_max_score;
                $item['latest_attempt_percentage'] = $attempt_percentage;
                $item['latest_attempt_passing_score'] = $attempt_pass_meta['passing_score'];
                $item['latest_attempt_is_passed'] = $attempt_pass_meta['is_passed'];
                $item['latest_attempt_pass_label'] = $attempt_pass_meta['pass_label'];
                $item['latest_attempt_result_tone'] = $attempt_pass_meta['result_tone'];
                $item['latest_attempt_started_at'] = (string) ($latest_attempt['started_at'] ?? '');
                $item['latest_attempt_finished_at'] = (string) ($latest_attempt['finished_at'] ?? '');
                if (
                    (string) ($item['latest_attempt_status'] ?? '') === 'in_progress'
                    && class_exists('CBT_Expired_Attempt_Finalize_Service')
                ) {
                    $latest_attempt_for_finalize = $latest_attempt;
                    $latest_attempt_for_finalize['exam_id'] = $exam_id;
                    $finalize_state = CBT_Expired_Attempt_Finalize_Service::maybe_schedule_for_attempt(
                        $latest_attempt_for_finalize,
                        self::resolve_attempt_duration_minutes(
                            $latest_attempt_for_finalize,
                            (int) ($item['duration_minutes'] ?? 0)
                        ),
                        max(0, (int) ($item['created_by'] ?? 0))
                    );
                    if (!empty($finalize_state['finalize_pending'])) {
                        $item['latest_attempt_finalize_pending'] = 1;
                        $item['latest_attempt_ui_state'] = 'finalizing';
                        $item['latest_attempt_poll_after_ms'] = max(
                            250,
                            (int) ($finalize_state['finalize_poll_after_ms'] ?? CBT_Expired_Attempt_Finalize_Service::get_default_poll_after_ms())
                        );
                        $item['latest_attempt_expired_at'] = (string) ($finalize_state['expired_at'] ?? '');
                    }
                }
                if ($show_student_result !== 1 && (string) ($item['latest_attempt_status'] ?? '') === 'completed') {
                    $item['latest_attempt_score'] = 0.0;
                    $item['latest_attempt_max_score'] = 0.0;
                    $item['latest_attempt_percentage'] = 0.0;
                    $item['latest_attempt_passing_score'] = 0.0;
                    $item['latest_attempt_is_passed'] = 0;
                    $item['latest_attempt_pass_label'] = '';
                    $item['latest_attempt_result_tone'] = '';
                }
            } else {
                $item['latest_attempt_id'] = 0;
                $item['latest_attempt_status'] = '';
                $item['latest_attempt_score'] = 0.0;
                $item['latest_attempt_max_score'] = 0.0;
                $item['latest_attempt_percentage'] = 0.0;
                $item['latest_attempt_passing_score'] = self::calculate_result_passing_score(0.0, (float) ($item['kkm_percentage'] ?? 75.0));
                $item['latest_attempt_is_passed'] = 0;
                $item['latest_attempt_pass_label'] = 'TIDAK LULUS';
                $item['latest_attempt_result_tone'] = 'fail';
                $item['latest_attempt_started_at'] = '';
                $item['latest_attempt_finished_at'] = '';
            }

            return $item;
        }, $exam_rows);

        return self::filter_and_sort_student_exam_availability_items($items, $student_now);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private static function filter_and_sort_student_exam_availability_items(array $items, string $student_now): array
    {
        $now_ts = self::parse_student_exam_timestamp($student_now);
        $decorated = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $visibility = self::classify_student_exam_list_item($item, $now_ts);
            if (empty($visibility['visible'])) {
                continue;
            }

            $decorated[] = [
                'bucket' => (int) ($visibility['bucket'] ?? 99),
                'created_at_ts' => self::parse_student_exam_timestamp((string) ($item['created_at'] ?? '')),
                'id' => (int) ($item['id'] ?? 0),
                'item' => $item,
            ];
        }

        usort($decorated, static function (array $left, array $right): int {
            $left_bucket = (int) ($left['bucket'] ?? 99);
            $right_bucket = (int) ($right['bucket'] ?? 99);
            if ($left_bucket !== $right_bucket) {
                return $left_bucket <=> $right_bucket;
            }

            $left_created_at_ts = (int) ($left['created_at_ts'] ?? 0);
            $right_created_at_ts = (int) ($right['created_at_ts'] ?? 0);
            if ($left_created_at_ts !== $right_created_at_ts) {
                return $right_created_at_ts <=> $left_created_at_ts;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return array_values(array_map(static function (array $entry): array {
            return (array) ($entry['item'] ?? []);
        }, $decorated));
    }

    /**
     * @param array<string,mixed> $item
     * @return array{visible:bool,bucket:int}
     */
    private static function classify_student_exam_list_item(array $item, int $now_ts): array
    {
        $status = sanitize_key((string) ($item['status'] ?? 'draft'));
        $question_count = max(0, (int) ($item['question_count'] ?? 0));
        $has_target_kelas = self::student_exam_item_has_target_kelas($item);
        $class_allowed = (int) ($item['is_class_allowed'] ?? 0) === 1;

        if ($status !== 'published' || $question_count <= 0 || !$has_target_kelas || !$class_allowed) {
            return [
                'visible' => false,
                'bucket' => 99,
            ];
        }

        $latest_attempt_status = sanitize_key((string) ($item['latest_attempt_status'] ?? ''));
        $latest_attempt_finalizing = (int) ($item['latest_attempt_finalize_pending'] ?? 0) === 1;
        if ($latest_attempt_finalizing || $latest_attempt_status === 'in_progress') {
            return [
                'visible' => true,
                'bucket' => 0,
            ];
        }

        if ((int) ($item['is_available_now'] ?? 0) === 1) {
            return [
                'visible' => true,
                'bucket' => 1,
            ];
        }

        $availability_reason = sanitize_key((string) ($item['availability_reason'] ?? ''));
        if ($availability_reason === 'not_started' && self::student_exam_item_is_upcoming_within_window($item, $now_ts)) {
            return [
                'visible' => true,
                'bucket' => 2,
            ];
        }

        if ($latest_attempt_status === 'completed') {
            return [
                'visible' => true,
                'bucket' => 3,
            ];
        }

        return [
            'visible' => false,
            'bucket' => 99,
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function student_exam_item_has_target_kelas(array $item): bool
    {
        return !empty(self::parse_exam_target_kelas((string) ($item['target_kelas'] ?? '')));
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function student_exam_item_is_upcoming_within_window(array $item, int $now_ts): bool
    {
        $start_ts = self::parse_student_exam_timestamp((string) ($item['starts_at'] ?? ''));
        if ($start_ts <= 0 || $now_ts <= 0 || $start_ts <= $now_ts) {
            return false;
        }

        return ($start_ts - $now_ts) <= self::student_exam_upcoming_window_seconds();
    }

    private static function student_exam_upcoming_window_seconds(): int
    {
        return 86400;
    }

    private static function parse_student_exam_timestamp(string $value): int
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : (int) $timestamp;
    }

    /**
     * @param array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string} $profile_snapshot
     * @return array<string,mixed>|null
     */
    private static function build_student_exam_current_user_payload(int $user_id, string $role, array $profile_snapshot): ?array
    {
        $current_user = get_user_by('id', $user_id);
        if (!($current_user instanceof WP_User)) {
            return null;
        }

        return [
            'user_id' => $user_id,
            'role' => $role,
            'display_name' => (string) $current_user->display_name,
            'username' => (string) $current_user->user_login,
            'email' => (string) $current_user->user_email,
            'kode_kelas' => (string) ($profile_snapshot['kode_kelas'] ?? ''),
            'kode_ruang' => (string) ($profile_snapshot['kode_ruang'] ?? ''),
            'agama' => (string) ($profile_snapshot['agama'] ?? ''),
            'foto' => (string) ($profile_snapshot['foto'] ?? ''),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private static function append_global_token_meta_to_exam_items(array $items): array
    {
        $global_token_meta = CBT_Auth::get_global_exam_token(true);
        $requires_token = trim((string) ($global_token_meta['token'] ?? '')) !== '' ? 1 : 0;
        $token_frontend_auto_apply = (int) ($global_token_meta['frontend_auto_apply'] ?? 0);
        $token_auto_value = ($requires_token === 1 && $token_frontend_auto_apply === 1)
            ? strtoupper(trim((string) ($global_token_meta['token'] ?? '')))
            : '';
        $token_refresh_minutes = (int) ($global_token_meta['refresh_minutes'] ?? 0);
        $token_next_refresh_at = (int) ($global_token_meta['next_refresh_at'] ?? 0);

        return array_map(static function ($row) use ($requires_token, $token_frontend_auto_apply, $token_auto_value, $token_refresh_minutes, $token_next_refresh_at): array {
            $item = (array) $row;
            $item['requires_token'] = $requires_token;
            $item['token_frontend_auto_apply'] = $token_frontend_auto_apply;
            $item['token_input_required'] = ($requires_token === 1 && $token_frontend_auto_apply !== 1) ? 1 : 0;
            $item['token_auto_value'] = $token_auto_value;
            $item['token_refresh_minutes'] = $token_refresh_minutes;
            $item['token_next_refresh_at'] = $token_next_refresh_at;
            unset($item['exam_token']);
            return $item;
        }, $items);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
}
