<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Active_Attempt_Index')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-active-attempt-index.php';
}

if (!class_exists('CBT_Live_Proctoring_Presence')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-live-proctoring-presence.php';
}

final class CBT_Admin_Results_Service
{
    private const TEST_REDIRECT_SIGNAL = '__cbt_admin_results_redirect__';
    private const TEST_AJAX_SIGNAL = '__cbt_admin_results_ajax__';
    private const BULK_JOB_QUERY_ARG = 'cbt_results_bulk_token';
    private const BULK_JOB_TTL = HOUR_IN_SECONDS;
    private const BULK_RESET_BATCH_SIZE = 100;
    private const BULK_FORCE_COMPLETE_BATCH_SIZE = 20;
    private const BULK_JOB_MAX_BATCH_SECONDS = 6.0;
    private const BULK_JOB_FAILURE_SAMPLE_LIMIT = 10;

    public static function can_view_results(): bool
    {
        return current_user_can('cbt_view_results');
    }

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
        global $wpdb;
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();

        $selected_exam_id = isset($query['cbt_exam_id']) ? absint(wp_unslash((string) $query['cbt_exam_id'])) : 0;
        $selected_status = isset($query['cbt_attempt_status']) ? sanitize_key((string) wp_unslash((string) $query['cbt_attempt_status'])) : '';
        $selected_kelas = isset($query['cbt_result_kelas']) ? sanitize_text_field(wp_unslash((string) $query['cbt_result_kelas'])) : '';
        $student_keyword = isset($query['cbt_student_q']) ? sanitize_text_field(wp_unslash((string) $query['cbt_student_q'])) : '';
        $current_page = isset($query['cbt_results_paged']) ? max(1, absint(wp_unslash((string) $query['cbt_results_paged']))) : 1;
        $results_bulk_job_token = isset($query[self::BULK_JOB_QUERY_ARG]) ? sanitize_key((string) wp_unslash((string) $query[self::BULK_JOB_QUERY_ARG])) : '';
        $results_per_page = 20;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($selected_status, $allowed_statuses, true)) {
            $selected_status = '';
        }

        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';

        $exam_filter_where = '1=1 AND title NOT LIKE %s';
        $exam_filter_params = ['Bank Soal - %'];
        if (!$is_admin_scope) {
            $exam_filter_where .= ' AND created_by = %d';
            $exam_filter_params[] = $current_user_id;
        }
        $exam_filter_sql = "SELECT id, title FROM {$exam_table} WHERE {$exam_filter_where} ORDER BY id DESC";
        if (!empty($exam_filter_params)) {
            $exam_filter_sql = $wpdb->prepare($exam_filter_sql, $exam_filter_params);
        }
        $exam_filter_rows = $wpdb->get_results($exam_filter_sql, ARRAY_A);
        $kelas_filter_rows = CBT_Admin_Users_Service::get_distinct_user_meta_values('kode_kelas');

        $selected_kelas = trim($selected_kelas);
        $student_keyword = trim($student_keyword);
        $attempt_base_where_parts = ['1=1'];
        $attempt_base_where_params = [];
        if (!$is_admin_scope) {
            $attempt_base_where_parts[] = 'e.created_by = %d';
            $attempt_base_where_params[] = $current_user_id;
        }
        if ($selected_exam_id > 0) {
            $attempt_base_where_parts[] = 'a.exam_id = %d';
            $attempt_base_where_params[] = $selected_exam_id;
        }
        if ($selected_kelas !== '') {
            $attempt_base_where_parts[] = 'kelas_meta.meta_value = %s';
            $attempt_base_where_params[] = $selected_kelas;
        }
        if ($student_keyword !== '') {
            $student_like = '%' . $wpdb->esc_like($student_keyword) . '%';
            $attempt_base_where_parts[] = '(u.user_login LIKE %s OR nisn_meta.meta_value LIKE %s)';
            $attempt_base_where_params[] = $student_like;
            $attempt_base_where_params[] = $student_like;
        }

        $attempt_where_parts = $attempt_base_where_parts;
        $attempt_where_params = $attempt_base_where_params;
        if ($selected_status !== '') {
            $attempt_where_parts[] = 'a.status = %s';
            $attempt_where_params[] = $selected_status;
        } else {
            $attempt_where_parts[] = "a.status IN ('in_progress', 'completed')";
        }
        $attempt_where = ' WHERE ' . implode(' AND ', $attempt_where_parts);
        $attempts_from_sql = "FROM {$attempt_table} a
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
                              ) nisn_meta ON nisn_meta.user_id = u.ID";

        $attempt_count_sql = "SELECT COUNT(*) {$attempts_from_sql} {$attempt_where}";
        if (!empty($attempt_where_params)) {
            $attempt_count_sql = $wpdb->prepare($attempt_count_sql, $attempt_where_params);
        }
        $total_attempts = (int) $wpdb->get_var($attempt_count_sql);

        $resettable_where_parts = $attempt_base_where_parts;
        $resettable_where_params = $attempt_base_where_params;
        if ($selected_status === 'in_progress') {
            $resettable_where_parts[] = '1 = 0';
        } else {
            $resettable_where_parts[] = "a.status = 'completed'";
        }
        $resettable_where = ' WHERE ' . implode(' AND ', $resettable_where_parts);
        $resettable_count_sql = "SELECT COUNT(*) {$attempts_from_sql} {$resettable_where}";
        if (!empty($resettable_where_params)) {
            $resettable_count_sql = $wpdb->prepare($resettable_count_sql, $resettable_where_params);
        }
        $resettable_attempts_count = (int) $wpdb->get_var($resettable_count_sql);

        $completable_where_parts = $attempt_base_where_parts;
        $completable_where_params = $attempt_base_where_params;
        if ($selected_status === 'completed') {
            $completable_where_parts[] = '1 = 0';
        } else {
            $completable_where_parts[] = "a.status = 'in_progress'";
        }
        $completable_where = ' WHERE ' . implode(' AND ', $completable_where_parts);
        $completable_count_sql = "SELECT COUNT(*) {$attempts_from_sql} {$completable_where}";
        if (!empty($completable_where_params)) {
            $completable_count_sql = $wpdb->prepare($completable_count_sql, $completable_where_params);
        }
        $completable_attempts_count = (int) $wpdb->get_var($completable_count_sql);

        $total_pages = max(1, (int) ceil($total_attempts / $results_per_page));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $results_per_page;

        $attempt_sql = "SELECT a.*,
                               e.title AS exam_title,
                               e.duration_minutes AS exam_duration_minutes,
                               u.display_name AS student_name,
                               u.user_login AS student_username,
                               kelas_meta.meta_value AS student_kelas,
                               nisn_meta.meta_value AS student_nisn,
                               (SELECT COUNT(*) FROM {$answer_table} ans WHERE ans.attempt_id = a.id) AS answer_count,
                               (SELECT COUNT(*) FROM {$question_table} qcount WHERE qcount.exam_id = a.exam_id AND COALESCE(qcount.is_active, 1) = 1) AS question_count,
                               CASE
                                   WHEN COALESCE(a.max_score, 0) > 0 THEN a.max_score
                                   ELSE (SELECT COALESCE(SUM(qpoints.points), 0) FROM {$question_table} qpoints WHERE qpoints.exam_id = a.exam_id AND COALESCE(qpoints.is_active, 1) = 1)
                               END AS total_points,
                               (SELECT COALESCE(SUM(qanswered.points), 0)
                                FROM {$answer_table} ansanswered
                                INNER JOIN {$question_table} qanswered ON qanswered.id = ansanswered.question_id
                                WHERE ansanswered.attempt_id = a.id) AS answered_points,
                               CASE
                                   WHEN a.status = 'completed' THEN COALESCE(a.score, 0)
                                   ELSE (SELECT COALESCE(SUM(anscore.score_awarded), 0) FROM {$answer_table} anscore WHERE anscore.attempt_id = a.id)
                               END AS earned_points
                        FROM {$attempt_table} a
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
                        {$attempt_where}
                        ORDER BY a.id DESC
                        LIMIT %d OFFSET %d";
        $attempt_sql_params = array_merge($attempt_where_params, [$results_per_page, $offset]);
        $attempt_sql = $wpdb->prepare($attempt_sql, $attempt_sql_params);
        $attempts = $wpdb->get_results($attempt_sql, ARRAY_A);
        $attempts = self::overlay_attempt_presence_payloads((array) $attempts);
        $attempt_answer_progress_map = CBT_Admin_Results_Helper::build_attempt_answer_progress_map(
            $attempts,
            $question_table,
            $answer_table,
            $option_table
        );

        $essay_where_parts = ["q.question_type = 'essay'"];
        $essay_where_params = [];
        if (!$is_admin_scope) {
            $essay_where_parts[] = 'ex.created_by = %d';
            $essay_where_params[] = $current_user_id;
        }
        if ($selected_exam_id > 0) {
            $essay_where_parts[] = 'att.exam_id = %d';
            $essay_where_params[] = $selected_exam_id;
        }
        $essay_where = ' WHERE ' . implode(' AND ', $essay_where_parts);
        $essay_sql = "SELECT ans.id AS answer_id,
                             ans.attempt_id,
                             ans.answer_text,
                             ans.score_awarded,
                             q.points,
                             q.question_text,
                             u.display_name,
                             ex.title AS exam_title
                      FROM {$answer_table} ans
                      INNER JOIN {$question_table} q ON q.id = ans.question_id
                      INNER JOIN {$attempt_table} att ON att.id = ans.attempt_id
                      INNER JOIN {$exam_table} ex ON ex.id = att.exam_id
                      INNER JOIN {$wpdb->users} u ON u.ID = att.student_id
                      {$essay_where}
                      ORDER BY ans.id DESC
                      LIMIT 300";
        if (!empty($essay_where_params)) {
            $essay_sql = $wpdb->prepare($essay_sql, $essay_where_params);
        }
        $essay_rows = $wpdb->get_results($essay_sql, ARRAY_A);

        $selected_exam_label = 'Semua exam';
        foreach ($exam_filter_rows as $exam_filter_row) {
            $exam_filter_id = (int) ($exam_filter_row['id'] ?? 0);
            if ($exam_filter_id === $selected_exam_id) {
                $selected_exam_label = (string) ($exam_filter_row['title'] ?? 'Semua exam');
                break;
            }
        }

        $selected_status_label = 'Semua status';
        if ($selected_status === 'in_progress') {
            $selected_status_label = 'In Progress';
        } elseif ($selected_status === 'completed') {
            $selected_status_label = 'Completed';
        }

        $active_filters = [];
        if ($selected_exam_id > 0) {
            $active_filters[] = [
                'label' => 'Exam',
                'value' => $selected_exam_label,
            ];
        }
        if ($selected_kelas !== '') {
            $active_filters[] = [
                'label' => 'Kelas',
                'value' => $selected_kelas,
            ];
        }
        if ($selected_status !== '') {
            $active_filters[] = [
                'label' => 'Status',
                'value' => $selected_status_label,
            ];
        }
        if ($student_keyword !== '') {
            $active_filters[] = [
                'label' => 'Cari',
                'value' => $student_keyword,
            ];
        }
        if (empty($active_filters)) {
            $active_filters[] = [
                'label' => 'Mode',
                'value' => 'Semua data attempts aktif',
                'is_default' => true,
            ];
        }

        $show_exam_column = $selected_exam_id <= 0;

        $results_hero_stats = [
            [
                'label' => 'Attempts',
                'value' => $total_attempts,
            ],
            [
                'label' => 'In Progress',
                'value' => $completable_attempts_count,
            ],
            [
                'label' => 'Completed',
                'value' => $resettable_attempts_count,
            ],
            [
                'label' => 'Essay Review',
                'value' => count((array) $essay_rows),
            ],
        ];
        $results_bulk_job = self::get_results_bulk_job_ui_state_from_query($query);
        $results_bulk_job_active = !empty($results_bulk_job['active']);
        if ($results_bulk_job_token === '' && !empty($results_bulk_job['token'])) {
            $results_bulk_job_token = (string) $results_bulk_job['token'];
        }

        unset($query, $wpdb);

        return get_defined_vars();
    }

    /**
     * @return array{
     *     exam_id:int,
     *     status:string,
     *     kelas:string,
     *     student_keyword:string,
     *     paged:int
     * }
     */
    private static function get_results_page_return_context_from_request(): array
    {
        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        return [
            'exam_id' => $return_exam_id,
            'status' => $return_status,
            'kelas' => $return_kelas,
            'student_keyword' => $return_student_keyword,
            'paged' => $return_paged,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $extra_args
     */
    private static function build_results_page_url(array $context, ?string $message = null, ?string $error = null, array $extra_args = []): string
    {
        $normalized_context = self::normalize_results_bulk_return_context($context);
        $args = ['page' => 'cbt-results'];
        if ((int) ($normalized_context['exam_id'] ?? 0) > 0) {
            $args['cbt_exam_id'] = (int) $normalized_context['exam_id'];
        }
        if ((string) ($normalized_context['status'] ?? '') !== '') {
            $args['cbt_attempt_status'] = (string) $normalized_context['status'];
        }
        if ((string) ($normalized_context['kelas'] ?? '') !== '') {
            $args['cbt_result_kelas'] = (string) $normalized_context['kelas'];
        }
        if ((string) ($normalized_context['student_keyword'] ?? '') !== '') {
            $args['cbt_student_q'] = (string) $normalized_context['student_keyword'];
        }
        if ((int) ($normalized_context['paged'] ?? 1) > 1) {
            $args['cbt_results_paged'] = (int) $normalized_context['paged'];
        }
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        foreach ($extra_args as $extra_key => $extra_value) {
            if (!is_scalar($extra_key) || $extra_key === '') {
                continue;
            }
            if ($extra_value === null || $extra_value === '') {
                continue;
            }
            $args[(string) $extra_key] = $extra_value;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * @param array<string,mixed> $context
     * @return array{
     *     exam_id:int,
     *     status:string,
     *     kelas:string,
     *     student_keyword:string,
     *     paged:int
     * }
     */
    private static function normalize_results_bulk_return_context(array $context): array
    {
        $status = sanitize_key((string) ($context['status'] ?? ''));
        if (!in_array($status, ['in_progress', 'completed'], true)) {
            $status = '';
        }

        return [
            'exam_id' => max(0, (int) ($context['exam_id'] ?? 0)),
            'status' => $status,
            'kelas' => sanitize_text_field((string) ($context['kelas'] ?? '')),
            'student_keyword' => sanitize_text_field((string) ($context['student_keyword'] ?? '')),
            'paged' => max(1, (int) ($context['paged'] ?? 1)),
        ];
    }

    private static function get_results_bulk_job_state_key(string $token): string
    {
        return 'cbt_results_bulk_job_' . sanitize_key($token);
    }

    private static function get_results_bulk_job_active_key(int $user_id): string
    {
        return 'cbt_results_bulk_job_active_' . max(0, $user_id);
    }

    private static function get_results_bulk_job_stop_key(string $token): string
    {
        return 'cbt_results_bulk_job_stop_' . sanitize_key($token);
    }

    private static function get_active_results_bulk_job_token_for_user(int $user_id): string
    {
        $user_id = max(0, $user_id);
        if ($user_id <= 0) {
            return '';
        }

        $token = get_transient(self::get_results_bulk_job_active_key($user_id));
        return is_scalar($token) ? sanitize_key((string) $token) : '';
    }

    private static function clear_active_results_bulk_job_token(int $user_id, string $expected_token = ''): void
    {
        $user_id = max(0, $user_id);
        if ($user_id <= 0) {
            return;
        }

        $lock_key = self::get_results_bulk_job_active_key($user_id);
        $current_token = get_transient($lock_key);
        $current_token = is_scalar($current_token) ? sanitize_key((string) $current_token) : '';
        if ($expected_token !== '' && $current_token !== '' && $current_token !== sanitize_key($expected_token)) {
            return;
        }

        delete_transient($lock_key);
    }

    private static function is_results_bulk_job_stop_requested(string $token): bool
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return false;
        }

        return (bool) get_transient(self::get_results_bulk_job_stop_key($token));
    }

    private static function request_results_bulk_job_stop(string $token): bool
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return false;
        }

        return (bool) set_transient(self::get_results_bulk_job_stop_key($token), 1, self::BULK_JOB_TTL);
    }

    private static function clear_results_bulk_job_stop_request(string $token): void
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return;
        }

        delete_transient(self::get_results_bulk_job_stop_key($token));
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function is_results_bulk_job_terminal(array $state): bool
    {
        $status = sanitize_key((string) ($state['status'] ?? ''));
        return in_array($status, ['completed', 'completed_with_errors', 'failed', 'stopped'], true);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{attempt_id:int,exam_id:int,student_id:int}
     */
    private static function normalize_results_bulk_target_row(array $row): array
    {
        return [
            'attempt_id' => max(0, (int) ($row['attempt_id'] ?? $row['id'] ?? 0)),
            'exam_id' => max(0, (int) ($row['exam_id'] ?? 0)),
            'student_id' => max(0, (int) ($row['student_id'] ?? 0)),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_results_bulk_job_state(string $token): ?array
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_results_bulk_job_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state['token'] = $token;
        $state['mode'] = sanitize_key((string) ($state['mode'] ?? ''));
        $state['status'] = sanitize_key((string) ($state['status'] ?? 'pending'));
        $state['created_by'] = max(0, (int) ($state['created_by'] ?? 0));
        $state['created_at'] = max(0, (int) ($state['created_at'] ?? time()));
        $state['updated_at'] = max(0, (int) ($state['updated_at'] ?? $state['created_at']));
        $state['cursor'] = max(0, (int) ($state['cursor'] ?? 0));
        $state['total'] = max(0, (int) ($state['total'] ?? 0));
        $state['processed_count'] = max(0, (int) ($state['processed_count'] ?? 0));
        $state['success_count'] = max(0, (int) ($state['success_count'] ?? 0));
        $state['failure_count'] = max(0, (int) ($state['failure_count'] ?? 0));
        $state['reset_count'] = max(0, (int) ($state['reset_count'] ?? 0));
        $state['abandoned_count'] = max(0, (int) ($state['abandoned_count'] ?? 0));
        $state['completed_count'] = max(0, (int) ($state['completed_count'] ?? 0));
        $state['last_message'] = sanitize_text_field((string) ($state['last_message'] ?? ''));
        $state['last_detail'] = sanitize_text_field((string) ($state['last_detail'] ?? ''));
        $state['last_error_message'] = sanitize_text_field((string) ($state['last_error_message'] ?? ''));
        $state['return_context'] = self::normalize_results_bulk_return_context(
            is_array($state['return_context'] ?? null) ? (array) $state['return_context'] : []
        );
        $state['failed_attempt_ids_sample'] = array_values(array_filter(array_map('absint', (array) ($state['failed_attempt_ids_sample'] ?? []))));
        $state['affected_exam_ids'] = array_values(array_filter(array_map('absint', (array) ($state['affected_exam_ids'] ?? []))));
        $state['affected_user_ids'] = array_values(array_filter(array_map('absint', (array) ($state['affected_user_ids'] ?? []))));
        $state['affected_attempt_ids'] = array_values(array_filter(array_map('absint', (array) ($state['affected_attempt_ids'] ?? []))));
        $state['stop_requested'] = self::is_results_bulk_job_stop_requested($token);
        $state['target_rows'] = array_values(array_filter(array_map(
            static function ($row): array {
                return self::normalize_results_bulk_target_row(is_array($row) ? $row : []);
            },
            (array) ($state['target_rows'] ?? [])
        ), static function (array $row): bool {
            return $row['attempt_id'] > 0 && $row['exam_id'] > 0 && $row['student_id'] > 0;
        }));
        $state['total'] = max($state['total'], count($state['target_rows']));

        return $state;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_results_bulk_job_state_for_current_user(string $token): ?array
    {
        $state = self::get_results_bulk_job_state($token);
        if (!is_array($state)) {
            return null;
        }

        if ((int) ($state['created_by'] ?? 0) !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function persist_results_bulk_job_state(array $state): bool
    {
        $token = sanitize_key((string) ($state['token'] ?? ''));
        if ($token === '') {
            return false;
        }

        $state['token'] = $token;
        $state['updated_at'] = time();
        $saved = set_transient(self::get_results_bulk_job_state_key($token), $state, self::BULK_JOB_TTL);
        if ($saved && (int) ($state['created_by'] ?? 0) > 0) {
            set_transient(
                self::get_results_bulk_job_active_key((int) $state['created_by']),
                $token,
                self::BULK_JOB_TTL
            );
        }

        return (bool) $saved;
    }

    /**
     * @param array<string,mixed>|null $state
     */
    private static function clear_results_bulk_job_state(string $token, ?array $state = null): void
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return;
        }

        delete_transient(self::get_results_bulk_job_state_key($token));
        self::clear_results_bulk_job_stop_request($token);
        if (is_array($state) && (int) ($state['created_by'] ?? 0) > 0) {
            self::clear_active_results_bulk_job_token((int) ($state['created_by'] ?? 0), $token);
            return;
        }

        self::clear_active_results_bulk_job_token(get_current_user_id(), $token);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function get_results_bulk_job_ui_state_from_query(array $query): array
    {
        $token = isset($query[self::BULK_JOB_QUERY_ARG])
            ? sanitize_key((string) wp_unslash((string) $query[self::BULK_JOB_QUERY_ARG]))
            : '';
        if ($token === '') {
            return [
                'active' => false,
                'token' => '',
            ];
        }

        $state = self::get_results_bulk_job_state_for_current_user($token);
        if (!is_array($state)) {
            return [
                'active' => false,
                'token' => $token,
            ];
        }

        return self::build_results_bulk_job_ui_state($state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_results_bulk_job_ui_state(array $state): array
    {
        $token = sanitize_key((string) ($state['token'] ?? ''));
        $mode = sanitize_key((string) ($state['mode'] ?? ''));
        $processed_count = max(0, (int) ($state['processed_count'] ?? 0));
        $total = max(0, (int) ($state['total'] ?? 0));
        $success_count = max(0, (int) ($state['success_count'] ?? 0));
        $failure_count = max(0, (int) ($state['failure_count'] ?? 0));
        $percent = $total > 0 ? min(100, max(0, ($processed_count / $total) * 100)) : 0.0;
        $status = sanitize_key((string) ($state['status'] ?? 'pending'));
        $stop_requested = !self::is_results_bulk_job_terminal($state) && !empty($state['stop_requested']);
        if ($status === 'completed') {
            $status_label = 'Selesai';
        } elseif ($status === 'completed_with_errors') {
            $status_label = 'Selesai Parsial';
        } elseif ($status === 'failed') {
            $status_label = 'Gagal';
        } elseif ($status === 'stopped') {
            $status_label = 'Dihentikan';
        } elseif ($stop_requested) {
            $status_label = 'Menghentikan';
        } else {
            $status_label = 'Berjalan';
        }
        $status_message = sanitize_text_field((string) ($state['last_message'] ?? ''));
        if ($stop_requested) {
            $status_message = 'Permintaan stop diterima. Batch akan dihentikan setelah chunk aktif selesai.';
        } elseif ($status_message === '') {
            $status_message = $mode === 'force_complete'
                ? 'Paksa complete sedang berjalan.'
                : 'Reset attempt sedang berjalan.';
        }
        $status_detail = sanitize_text_field((string) ($state['last_detail'] ?? ''));
        if ($stop_requested) {
            $status_detail = sprintf(
                '%d dari %d attempt sudah diproses. Tidak ada chunk baru yang akan dimulai.',
                $processed_count,
                $total
            );
        } elseif ($status_detail === '') {
            $status_detail = sprintf('%d dari %d attempt sudah diproses.', $processed_count, $total);
        }

        return [
            'active' => true,
            'ajax_action' => 'cbt_results_bulk_job_tick',
            'ajax_url' => admin_url('admin-ajax.php'),
            'can_stop' => !$stop_requested && !self::is_results_bulk_job_terminal($state),
            'failure_count' => $failure_count,
            'mode' => $mode,
            'mode_label' => $mode === 'force_complete' ? 'Paksa Complete' : 'Reset Sesuai Filter',
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
            'processed_count' => $processed_count,
            'progress_percent' => round($percent, 2),
            'reset_count' => max(0, (int) ($state['reset_count'] ?? 0)),
            'abandoned_count' => max(0, (int) ($state['abandoned_count'] ?? 0)),
            'completed_count' => max(0, (int) ($state['completed_count'] ?? 0)),
            'resume_url' => self::build_results_page_url(
                (array) ($state['return_context'] ?? []),
                null,
                null,
                [self::BULK_JOB_QUERY_ARG => $token]
            ),
            'status' => $status,
            'status_detail' => $status_detail,
            'status_label' => $status_label,
            'status_message' => $status_message,
            'stop_action' => 'cbt_results_bulk_job_stop',
            'stop_nonce' => wp_create_nonce('cbt_results_bulk_job_stop'),
            'stop_requested' => $stop_requested,
            'success_count' => $success_count,
            'token' => $token,
            'total' => $total,
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function start_results_bulk_job(string $mode, array $context): void
    {
        $mode = sanitize_key($mode);
        if (!in_array($mode, ['reset', 'force_complete'], true)) {
            self::dispatch_redirect(self::build_results_page_url($context, null, 'Mode batch results tidak valid.'));
        }

        $current_user_id = get_current_user_id();
        $active_token = self::get_active_results_bulk_job_token_for_user($current_user_id);
        if ($active_token !== '') {
            $active_state = self::get_results_bulk_job_state($active_token);
            if (is_array($active_state) && !self::is_results_bulk_job_terminal($active_state)) {
                self::dispatch_redirect(self::build_results_page_url($context, null, null, [
                    self::BULK_JOB_QUERY_ARG => $active_token,
                ]));
            }

            self::clear_results_bulk_job_state($active_token, $active_state);
        }

        $target_rows = self::query_results_bulk_target_rows($mode, $context);
        if (empty($target_rows)) {
            $error_message = $mode === 'force_complete'
                ? 'Tidak ada attempt in_progress sesuai filter yang bisa dipaksa selesai.'
                : 'Tidak ada attempt completed sesuai filter yang bisa di-reset.';
            self::dispatch_redirect(self::build_results_page_url($context, null, $error_message));
        }

        $state = self::create_results_bulk_job_state($mode, $context, $target_rows);
        if (!self::persist_results_bulk_job_state($state)) {
            self::dispatch_redirect(self::build_results_page_url($context, null, 'Gagal menyiapkan batch results.'));
        }

        self::dispatch_redirect(self::build_results_page_url($context, null, null, [
            self::BULK_JOB_QUERY_ARG => (string) ($state['token'] ?? ''),
        ]));
    }

    private static function prepare_runtime_for_results_bulk_job(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');
    }

    /**
     * @param int[] $base
     * @param int[] $extra
     * @return int[]
     */
    private static function merge_unique_positive_ints(array $base, array $extra): array
    {
        $merged = [];
        foreach (array_merge($base, $extra) as $value) {
            $value = absint($value);
            if ($value > 0) {
                $merged[$value] = $value;
            }
        }

        return array_values($merged);
    }

    /**
     * @param array<string,mixed> $state
     * @param int[] $attempt_ids
     */
    private static function append_results_bulk_failure_sample(array &$state, array $attempt_ids): void
    {
        $sample = array_values(array_filter(array_map('absint', (array) ($state['failed_attempt_ids_sample'] ?? []))));
        foreach ($attempt_ids as $attempt_id) {
            $attempt_id = absint($attempt_id);
            if ($attempt_id <= 0 || in_array($attempt_id, $sample, true)) {
                continue;
            }
            $sample[] = $attempt_id;
            if (count($sample) >= self::BULK_JOB_FAILURE_SAMPLE_LIMIT) {
                break;
            }
        }

        $state['failed_attempt_ids_sample'] = $sample;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array{attempt_id:int,exam_id:int,student_id:int}>
     */
    private static function query_results_bulk_target_rows(string $mode, array $context): array
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $normalized_context = self::normalize_results_bulk_return_context($context);
        $filter_kelas = trim((string) ($normalized_context['kelas'] ?? ''));
        $filter_student_keyword = trim((string) ($normalized_context['student_keyword'] ?? ''));

        $where_parts = ['1=1'];
        $where_params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }
        if ((int) ($normalized_context['exam_id'] ?? 0) > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $where_params[] = (int) $normalized_context['exam_id'];
        }
        if ($filter_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $where_params[] = $filter_kelas;
        }
        if ($filter_student_keyword !== '') {
            $student_like = '%' . $wpdb->esc_like($filter_student_keyword) . '%';
            $where_parts[] = '(u.user_login LIKE %s OR nisn_meta.meta_value LIKE %s)';
            $where_params[] = $student_like;
            $where_params[] = $student_like;
        }
        $where_parts[] = $mode === 'force_complete'
            ? "a.status = 'in_progress'"
            : "a.status = 'completed'";
        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);

        $target_sql = "SELECT a.id AS attempt_id, a.exam_id, a.student_id
                       FROM {$attempt_table} a
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
                       {$where_sql}
                       ORDER BY a.id DESC";
        if (!empty($where_params)) {
            $target_sql = $wpdb->prepare($target_sql, $where_params);
        }

        $rows = $wpdb->get_results($target_sql, ARRAY_A);

        return array_values(array_filter(array_map(static function ($row): array {
            return self::normalize_results_bulk_target_row(is_array($row) ? $row : []);
        }, is_array($rows) ? $rows : []), static function (array $row): bool {
            return $row['attempt_id'] > 0 && $row['exam_id'] > 0 && $row['student_id'] > 0;
        }));
    }

    /**
     * @param array<string,mixed> $context
     * @param array<int,array{attempt_id:int,exam_id:int,student_id:int}> $target_rows
     * @return array<string,mixed>
     */
    private static function create_results_bulk_job_state(string $mode, array $context, array $target_rows): array
    {
        $token = sanitize_key(strtolower((string) wp_generate_password(24, false, false)));
        if ($token === '') {
            $token = sanitize_key(strtolower((string) uniqid('cbtr', true)));
        }

        return [
            'token' => $token,
            'mode' => $mode,
            'status' => 'pending',
            'created_by' => get_current_user_id(),
            'created_at' => time(),
            'updated_at' => time(),
            'return_context' => self::normalize_results_bulk_return_context($context),
            'target_rows' => array_values($target_rows),
            'cursor' => 0,
            'total' => count($target_rows),
            'processed_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'reset_count' => 0,
            'abandoned_count' => 0,
            'completed_count' => 0,
            'failed_attempt_ids_sample' => [],
            'last_error_message' => '',
            'last_message' => $mode === 'force_complete'
                ? 'Batch paksa complete siap dijalankan.'
                : 'Batch reset siap dijalankan.',
            'last_detail' => sprintf('%d attempt masuk antrean.', count($target_rows)),
            'affected_exam_ids' => [],
            'affected_user_ids' => [],
            'affected_attempt_ids' => [],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_results_bulk_job_response(array $state): array
    {
        $ui_state = self::build_results_bulk_job_ui_state($state);
        $response = [
            'can_stop' => !empty($ui_state['can_stop']),
            'complete' => self::is_results_bulk_job_terminal($state),
            'failure_count' => (int) ($ui_state['failure_count'] ?? 0),
            'message' => (string) ($ui_state['status_message'] ?? ''),
            'mode' => (string) ($ui_state['mode'] ?? ''),
            'mode_label' => (string) ($ui_state['mode_label'] ?? ''),
            'processed_count' => (int) ($ui_state['processed_count'] ?? 0),
            'progress_percent' => (float) ($ui_state['progress_percent'] ?? 0),
            'reset_count' => (int) ($ui_state['reset_count'] ?? 0),
            'abandoned_count' => (int) ($ui_state['abandoned_count'] ?? 0),
            'completed_count' => (int) ($ui_state['completed_count'] ?? 0),
            'resume_url' => (string) ($ui_state['resume_url'] ?? ''),
            'status' => (string) ($ui_state['status'] ?? ''),
            'status_detail' => (string) ($ui_state['status_detail'] ?? ''),
            'status_label' => (string) ($ui_state['status_label'] ?? ''),
            'stop_requested' => !empty($ui_state['stop_requested']),
            'success_count' => (int) ($ui_state['success_count'] ?? 0),
            'token' => (string) ($ui_state['token'] ?? ''),
            'total' => (int) ($ui_state['total'] ?? 0),
        ];

        if (self::is_results_bulk_job_terminal($state)) {
            $feedback = self::build_results_bulk_job_terminal_feedback($state);
            $response['final_message'] = (string) ($feedback['message'] ?? '');
            $response['final_error'] = (string) ($feedback['error'] ?? '');
            $response['redirect_url'] = self::build_results_page_url(
                (array) ($state['return_context'] ?? []),
                $response['final_message'] !== '' ? $response['final_message'] : null,
                $response['final_error'] !== '' ? $response['final_error'] : null
            );
        }

        return $response;
    }

    /**
     * @param array<string,mixed> $state
     * @return array{message:string,error:string}
     */
    private static function build_results_bulk_job_terminal_feedback(array $state): array
    {
        $mode = sanitize_key((string) ($state['mode'] ?? ''));
        $status = sanitize_key((string) ($state['status'] ?? ''));
        $success_count = max(0, (int) ($state['success_count'] ?? 0));
        $failure_count = max(0, (int) ($state['failure_count'] ?? 0));
        $message = '';
        $error = '';

        if ($status === 'stopped') {
            if ($mode === 'force_complete') {
                if ($success_count > 0) {
                    $message = sprintf('Batch paksa complete dihentikan. %d attempt sudah selesai diproses sebelum stop.', $success_count);
                }
            } else {
                if ($success_count > 0) {
                    $message = sprintf('Batch reset dihentikan. %d attempt sudah di-reset sebelum stop.', max(0, (int) ($state['reset_count'] ?? $success_count)));
                    $abandoned_count = max(0, (int) ($state['abandoned_count'] ?? 0));
                    if ($abandoned_count > 0) {
                        $message .= ' ' . sprintf('%d attempt in_progress lama sempat ditutup otomatis.', $abandoned_count);
                    }
                }
            }
        } else {
            if ($mode === 'force_complete') {
                if ($success_count > 0) {
                    $message = sprintf('Berhasil memaksa %d attempt in_progress menjadi completed.', $success_count);
                }
            } else {
                if ($success_count > 0) {
                    $message = sprintf('Berhasil reset %d attempt sesuai filter.', max(0, (int) ($state['reset_count'] ?? $success_count)));
                    $abandoned_count = max(0, (int) ($state['abandoned_count'] ?? 0));
                    if ($abandoned_count > 0) {
                        $message .= ' ' . sprintf('%d attempt in_progress lama ditutup otomatis.', $abandoned_count);
                    }
                }
            }
        }

        if ($failure_count > 0) {
            $sample = array_values(array_filter(array_map('absint', (array) ($state['failed_attempt_ids_sample'] ?? []))));
            $error = sprintf('%d attempt gagal diproses.', $failure_count);
            if (!empty($sample)) {
                $sample_labels = array_map(static function (int $attempt_id): string {
                    return '#' . $attempt_id;
                }, array_slice($sample, 0, self::BULK_JOB_FAILURE_SAMPLE_LIMIT));
                $error .= ' Sample: ' . implode(', ', $sample_labels) . '.';
            }
        } elseif ($status === 'stopped' && $success_count <= 0) {
            $error = $mode === 'force_complete'
                ? 'Batch paksa complete dihentikan sebelum ada attempt yang diproses.'
                : 'Batch reset dihentikan sebelum ada attempt yang diproses.';
        } elseif ($success_count <= 0) {
            $error = sanitize_text_field((string) ($state['last_error_message'] ?? ''));
            if ($error === '') {
                $error = $mode === 'force_complete'
                    ? 'Tidak ada attempt yang berhasil dipaksa selesai.'
                    : 'Tidak ada attempt yang berhasil di-reset.';
            }
        }

        return [
            'message' => $message,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function finalize_results_bulk_job_state(array &$state, string $terminal_status = ''): void
    {
        if (max(0, (int) ($state['success_count'] ?? 0)) > 0) {
            CBT_Cache::invalidate_analytics();
            $exam_ids = array_values(array_filter(array_map('absint', (array) ($state['affected_exam_ids'] ?? []))));
            if (!empty($exam_ids)) {
                if (method_exists('CBT_Cache', 'invalidate_analytics_exams')) {
                    CBT_Cache::invalidate_analytics_exams($exam_ids);
                } else {
                    foreach ($exam_ids as $exam_id) {
                        CBT_Cache::invalidate_analytics_exam($exam_id);
                    }
                }
            }
        }

        $success_count = max(0, (int) ($state['success_count'] ?? 0));
        $failure_count = max(0, (int) ($state['failure_count'] ?? 0));
        $terminal_status = sanitize_key($terminal_status);
        if ($terminal_status === 'stopped') {
            $state['status'] = 'stopped';
        } elseif ($success_count > 0 && $failure_count > 0) {
            $state['status'] = 'completed_with_errors';
        } elseif ($success_count > 0) {
            $state['status'] = 'completed';
        } else {
            $state['status'] = 'failed';
        }

        $feedback = self::build_results_bulk_job_terminal_feedback($state);
        $state['last_message'] = (string) ($feedback['message'] ?? $state['last_message'] ?? '');
        $state['last_detail'] = $failure_count > 0
            ? (string) ($feedback['error'] ?? '')
            : sprintf('%d dari %d attempt selesai diproses.', max(0, (int) ($state['processed_count'] ?? 0)), max(0, (int) ($state['total'] ?? 0)));
        if ($success_count <= 0 && (string) ($feedback['error'] ?? '') !== '') {
            $state['last_error_message'] = (string) $feedback['error'];
        }
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|WP_Error
     */
    private static function continue_results_bulk_job_state(array $state)
    {
        $token = sanitize_key((string) ($state['token'] ?? ''));
        $state['status'] = 'running';
        $state['stop_requested'] = self::is_results_bulk_job_stop_requested($token);

        if (!empty($state['stop_requested'])) {
            self::finalize_results_bulk_job_state($state, 'stopped');
            return $state;
        }

        if ((int) ($state['cursor'] ?? 0) >= (int) ($state['total'] ?? 0)) {
            self::finalize_results_bulk_job_state($state);
            return $state;
        }

        if (sanitize_key((string) ($state['mode'] ?? '')) === 'force_complete') {
            $state = self::continue_results_bulk_force_complete_job_state($state);
        } else {
            $state = self::continue_results_bulk_reset_job_state($state);
        }
        if (is_wp_error($state)) {
            return $state;
        }

        $state['stop_requested'] = self::is_results_bulk_job_stop_requested($token);
        if (!empty($state['stop_requested'])) {
            self::finalize_results_bulk_job_state($state, 'stopped');
            return $state;
        }

        if ((int) ($state['cursor'] ?? 0) >= (int) ($state['total'] ?? 0)) {
            self::finalize_results_bulk_job_state($state);
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|WP_Error
     */
    private static function continue_results_bulk_reset_job_state(array $state)
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $cursor = max(0, (int) ($state['cursor'] ?? 0));
        $target_rows = is_array($state['target_rows'] ?? null) ? array_values((array) $state['target_rows']) : [];
        $chunk_rows = array_slice($target_rows, $cursor, self::BULK_RESET_BATCH_SIZE);
        if (empty($chunk_rows)) {
            $state['cursor'] = count($target_rows);
            return $state;
        }

        $chunk_attempt_ids = array_values(array_filter(array_map(static function ($row): int {
            return absint(is_array($row) ? ($row['attempt_id'] ?? 0) : 0);
        }, $chunk_rows)));
        if (empty($chunk_attempt_ids)) {
            $state['cursor'] = min(count($target_rows), $cursor + count($chunk_rows));
            return $state;
        }

        $attempt_ids_sql = implode(',', $chunk_attempt_ids);
        $eligible_rows = $wpdb->get_results(
            "SELECT id AS attempt_id, exam_id, student_id
             FROM {$attempt_table}
             WHERE status = 'completed'
               AND id IN ({$attempt_ids_sql})",
            ARRAY_A
        );
        $eligible_rows = array_values(array_filter(array_map(static function ($row): array {
            return self::normalize_results_bulk_target_row(is_array($row) ? $row : []);
        }, is_array($eligible_rows) ? $eligible_rows : []), static function (array $row): bool {
            return $row['attempt_id'] > 0 && $row['exam_id'] > 0 && $row['student_id'] > 0;
        }));
        $eligible_attempt_ids = array_values(array_filter(array_map(static function (array $row): int {
            return (int) ($row['attempt_id'] ?? 0);
        }, $eligible_rows)));
        $missing_attempt_ids = array_values(array_diff($chunk_attempt_ids, $eligible_attempt_ids));

        $abandoned_attempt_ids = [];
        $abandoned_total = 0;
        if (!empty($eligible_rows)) {
            $pair_clauses = [];
            $pair_params = [];
            foreach ($eligible_rows as $eligible_row) {
                $pair_clauses[] = '(exam_id = %d AND student_id = %d)';
                $pair_params[] = (int) ($eligible_row['exam_id'] ?? 0);
                $pair_params[] = (int) ($eligible_row['student_id'] ?? 0);
            }

            $select_abandoned_sql = "SELECT id
                                     FROM {$attempt_table}
                                     WHERE status = 'in_progress'
                                       AND (" . implode(' OR ', $pair_clauses) . ')';
            $select_abandoned_sql = $wpdb->prepare($select_abandoned_sql, $pair_params);
            $abandoned_attempt_ids = array_values(array_filter(array_map('absint', (array) $wpdb->get_col($select_abandoned_sql))));

            $update_abandoned_sql = $wpdb->prepare(
                "UPDATE {$attempt_table}
                 SET status = 'abandoned',
                     updated_at = %s
                 WHERE status = 'in_progress'
                   AND (" . implode(' OR ', $pair_clauses) . ')',
                array_merge([current_time('mysql')], $pair_params)
            );
            $abandoned_result = $wpdb->query($update_abandoned_sql);
            if ($abandoned_result === false) {
                return new WP_Error('results_bulk_reset_abandon_failed', 'Gagal menutup attempt in_progress lama untuk batch reset.');
            }
            $abandoned_total = is_int($abandoned_result) ? max(0, $abandoned_result) : count($abandoned_attempt_ids);

            $reset_now = current_time('mysql');
            $reset_sql = $wpdb->prepare(
                "UPDATE {$attempt_table}
                 SET status = 'in_progress',
                     score = 0,
                     max_score = 0,
                     finished_at = NULL,
                     duration_seconds = 0,
                     started_at = %s,
                     updated_at = %s
                 WHERE status = 'completed'
                   AND id IN ({$attempt_ids_sql})",
                $reset_now,
                $reset_now
            );
            $reset_result = $wpdb->query($reset_sql);
            if ($reset_result === false) {
                return new WP_Error('results_bulk_reset_failed', 'Gagal melakukan reset attempt untuk batch ini.');
            }

            if (class_exists('CBT_Runtime')) {
                foreach ($abandoned_attempt_ids as $abandoned_attempt_id) {
                    CBT_Runtime::clear_attempt_runtime((int) $abandoned_attempt_id);
                }
                foreach ($eligible_attempt_ids as $eligible_attempt_id) {
                    CBT_Runtime::clear_attempt_runtime((int) $eligible_attempt_id);
                }
            }
            if (class_exists('CBT_Active_Attempt_Index')) {
                foreach ($eligible_rows as $eligible_row) {
                    CBT_Active_Attempt_Index::set_active_attempt([
                        'id' => (int) ($eligible_row['attempt_id'] ?? 0),
                        'exam_id' => (int) ($eligible_row['exam_id'] ?? 0),
                        'student_id' => (int) ($eligible_row['student_id'] ?? 0),
                        'status' => 'in_progress',
                    ]);
                }
            }

            $chunk_affected_attempt_ids = self::merge_unique_positive_ints($eligible_attempt_ids, $abandoned_attempt_ids);
            if (!empty($chunk_affected_attempt_ids)) {
                CBT_Cache::invalidate_attempts($chunk_affected_attempt_ids);
                CBT_UI_State::clear_attempt_states_by_attempt_ids($chunk_affected_attempt_ids);
                $state['affected_attempt_ids'] = self::merge_unique_positive_ints(
                    (array) ($state['affected_attempt_ids'] ?? []),
                    $chunk_affected_attempt_ids
                );
            }

            $chunk_user_ids = array_values(array_filter(array_map(static function (array $row): int {
                return (int) ($row['student_id'] ?? 0);
            }, $eligible_rows)));
            if (!empty($chunk_user_ids)) {
                CBT_Cache::invalidate_users($chunk_user_ids);
                $state['affected_user_ids'] = self::merge_unique_positive_ints(
                    (array) ($state['affected_user_ids'] ?? []),
                    $chunk_user_ids
                );
            }

            $chunk_exam_ids = array_values(array_filter(array_map(static function (array $row): int {
                return (int) ($row['exam_id'] ?? 0);
            }, $eligible_rows)));
            if (!empty($chunk_exam_ids)) {
                $state['affected_exam_ids'] = self::merge_unique_positive_ints(
                    (array) ($state['affected_exam_ids'] ?? []),
                    $chunk_exam_ids
                );
            }
        }

        if (!empty($missing_attempt_ids)) {
            self::append_results_bulk_failure_sample($state, $missing_attempt_ids);
        }

        $state['cursor'] = min(count($target_rows), $cursor + count($chunk_rows));
        $state['processed_count'] = max(0, (int) ($state['processed_count'] ?? 0)) + count($chunk_rows);
        $state['success_count'] = max(0, (int) ($state['success_count'] ?? 0)) + count($eligible_attempt_ids);
        $state['failure_count'] = max(0, (int) ($state['failure_count'] ?? 0)) + count($missing_attempt_ids);
        $state['reset_count'] = max(0, (int) ($state['reset_count'] ?? 0)) + count($eligible_attempt_ids);
        $state['abandoned_count'] = max(0, (int) ($state['abandoned_count'] ?? 0)) + count($abandoned_attempt_ids);
        $state['last_message'] = 'Batch reset sedang berjalan.';
        $state['last_detail'] = sprintf(
            '%d dari %d attempt sudah diproses. Reset berhasil: %d. Attempt lama ditutup: %d.',
            (int) $state['processed_count'],
            (int) ($state['total'] ?? 0),
            (int) $state['reset_count'],
            (int) $state['abandoned_count']
        );

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|WP_Error
     */
    private static function continue_results_bulk_force_complete_job_state(array $state)
    {
        $cursor = max(0, (int) ($state['cursor'] ?? 0));
        $target_rows = is_array($state['target_rows'] ?? null) ? array_values((array) $state['target_rows']) : [];
        $batch_rows = array_slice($target_rows, $cursor, self::BULK_FORCE_COMPLETE_BATCH_SIZE);
        if (empty($batch_rows)) {
            $state['cursor'] = count($target_rows);
            return $state;
        }

        $started_at = microtime(true);
        $processed_rows = [];
        $successful_rows = [];
        $failed_attempt_ids = [];
        foreach ($batch_rows as $batch_index => $batch_row) {
            $row = self::normalize_results_bulk_target_row(is_array($batch_row) ? $batch_row : []);
            if ($row['attempt_id'] <= 0) {
                continue;
            }

            $processed_rows[] = $row;
            $completion_result = CBT_REST::finalize_attempt_completion(
                $row['attempt_id'],
                current_time('mysql'),
                ['defer_invalidation' => true]
            );
            if (is_wp_error($completion_result)) {
                $failed_attempt_ids[] = $row['attempt_id'];
            } else {
                $successful_rows[] = $row;
            }

            if (($batch_index + 1) < count($batch_rows) && (microtime(true) - $started_at) >= self::BULK_JOB_MAX_BATCH_SECONDS) {
                break;
            }
        }

        $processed_count = count($processed_rows);
        if (!empty($successful_rows)) {
            $successful_attempt_ids = array_values(array_filter(array_map(static function (array $row): int {
                return (int) ($row['attempt_id'] ?? 0);
            }, $successful_rows)));
            $successful_user_ids = array_values(array_filter(array_map(static function (array $row): int {
                return (int) ($row['student_id'] ?? 0);
            }, $successful_rows)));
            $successful_exam_ids = array_values(array_filter(array_map(static function (array $row): int {
                return (int) ($row['exam_id'] ?? 0);
            }, $successful_rows)));

            if (!empty($successful_attempt_ids)) {
                CBT_Cache::invalidate_attempts($successful_attempt_ids);
                CBT_UI_State::clear_attempt_states_by_attempt_ids($successful_attempt_ids);
                $state['affected_attempt_ids'] = self::merge_unique_positive_ints(
                    (array) ($state['affected_attempt_ids'] ?? []),
                    $successful_attempt_ids
                );
            }
            if (!empty($successful_user_ids)) {
                CBT_Cache::invalidate_users($successful_user_ids);
                $state['affected_user_ids'] = self::merge_unique_positive_ints(
                    (array) ($state['affected_user_ids'] ?? []),
                    $successful_user_ids
                );
            }
            if (!empty($successful_exam_ids)) {
                $state['affected_exam_ids'] = self::merge_unique_positive_ints(
                    (array) ($state['affected_exam_ids'] ?? []),
                    $successful_exam_ids
                );
            }
        }

        if (!empty($failed_attempt_ids)) {
            self::append_results_bulk_failure_sample($state, $failed_attempt_ids);
            $state['last_error_message'] = sprintf('%d attempt gagal difinalisasi pada batch terakhir.', count($failed_attempt_ids));
        }

        $state['cursor'] = min(count($target_rows), $cursor + $processed_count);
        $state['processed_count'] = max(0, (int) ($state['processed_count'] ?? 0)) + $processed_count;
        $state['success_count'] = max(0, (int) ($state['success_count'] ?? 0)) + count($successful_rows);
        $state['failure_count'] = max(0, (int) ($state['failure_count'] ?? 0)) + count($failed_attempt_ids);
        $state['completed_count'] = max(0, (int) ($state['completed_count'] ?? 0)) + count($successful_rows);
        $state['last_message'] = 'Batch paksa complete sedang berjalan.';
        $state['last_detail'] = sprintf(
            '%d dari %d attempt sudah diproses. Completed berhasil: %d. Gagal: %d.',
            (int) $state['processed_count'],
            (int) ($state['total'] ?? 0),
            (int) $state['completed_count'],
            (int) ($state['failure_count'] ?? 0)
        );

        return $state;
    }

    private static function dispatch_results_bulk_job_ajax(bool $success, array $payload, int $status_code = 200): void
    {
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            $GLOBALS['cbt_test_last_ajax_response'] = [
                'success' => $success,
                'status_code' => $status_code,
                'payload' => $payload,
            ];
            throw new RuntimeException(self::TEST_AJAX_SIGNAL);
        }

        if ($success) {
            wp_send_json_success($payload, $status_code);
        }

        wp_send_json_error($payload, $status_code);
    }

    public static function handle_bulk_job_tick_ajax(): void
    {
        if (!current_user_can('cbt_view_results')) {
            self::dispatch_results_bulk_job_ajax(false, ['message' => 'Unauthorized'], 403);
        }

        check_admin_referer('cbt_results_bulk_job_tick', 'nonce');

        $token = isset($_POST['token']) ? sanitize_key((string) wp_unslash($_POST['token'])) : '';
        $state = self::get_results_bulk_job_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_active_results_bulk_job_token(get_current_user_id(), $token);
            self::dispatch_results_bulk_job_ajax(false, [
                'message' => 'Sesi progress results tidak ditemukan atau sudah berakhir.',
                'redirect_url' => self::build_results_page_url(self::get_results_page_return_context_from_request()),
            ], 404);
        }

        if (self::is_results_bulk_job_terminal($state)) {
            $response = self::build_results_bulk_job_response($state);
            self::clear_results_bulk_job_state($token, $state);
            self::dispatch_results_bulk_job_ajax(true, $response);
        }

        self::prepare_runtime_for_results_bulk_job();
        $continued_state = self::continue_results_bulk_job_state($state);
        if (is_wp_error($continued_state)) {
            $state['status'] = 'failed';
            $state['last_error_message'] = $continued_state->get_error_message();
            self::finalize_results_bulk_job_state($state);
            $response = self::build_results_bulk_job_response($state);
            self::clear_results_bulk_job_state($token, $state);
            self::dispatch_results_bulk_job_ajax(false, $response, 500);
        }

        $state = $continued_state;
        $response = self::build_results_bulk_job_response($state);
        if (self::is_results_bulk_job_terminal($state)) {
            self::clear_results_bulk_job_state($token, $state);
            self::dispatch_results_bulk_job_ajax(true, $response);
        }

        if (!self::persist_results_bulk_job_state($state)) {
            self::clear_results_bulk_job_state($token, $state);
            self::dispatch_results_bulk_job_ajax(false, [
                'message' => 'Gagal menyimpan progres batch results.',
                'redirect_url' => self::build_results_page_url((array) ($state['return_context'] ?? [])),
            ], 500);
        }

        self::dispatch_results_bulk_job_ajax(true, $response);
    }

    public static function handle_bulk_job_stop_ajax(): void
    {
        if (!current_user_can('cbt_view_results')) {
            self::dispatch_results_bulk_job_ajax(false, ['message' => 'Unauthorized'], 403);
        }

        check_admin_referer('cbt_results_bulk_job_stop', 'nonce');

        $token = isset($_POST['token']) ? sanitize_key((string) wp_unslash($_POST['token'])) : '';
        $state = self::get_results_bulk_job_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_active_results_bulk_job_token(get_current_user_id(), $token);
            self::dispatch_results_bulk_job_ajax(false, [
                'message' => 'Sesi progress results tidak ditemukan atau sudah berakhir.',
                'redirect_url' => self::build_results_page_url(self::get_results_page_return_context_from_request()),
            ], 404);
        }

        if (self::is_results_bulk_job_terminal($state)) {
            $response = self::build_results_bulk_job_response($state);
            self::clear_results_bulk_job_state($token, $state);
            self::dispatch_results_bulk_job_ajax(true, $response);
        }

        if (!self::request_results_bulk_job_stop($token)) {
            self::dispatch_results_bulk_job_ajax(false, [
                'message' => 'Gagal mengirim permintaan stop batch results.',
            ], 500);
        }

        $state['stop_requested'] = true;
        $state['last_message'] = 'Permintaan stop diterima. Batch akan dihentikan setelah chunk aktif selesai.';
        $state['last_detail'] = sprintf(
            '%d dari %d attempt sudah diproses. Tidak ada chunk baru yang akan dimulai.',
            max(0, (int) ($state['processed_count'] ?? 0)),
            max(0, (int) ($state['total'] ?? 0))
        );

        self::dispatch_results_bulk_job_ajax(true, self::build_results_bulk_job_response($state));
    }

    /**
     * @param array<int,array<string,mixed>> $attempts
     * @return array<int,array<string,mixed>>
     */
    private static function overlay_attempt_presence_payloads(array $attempts): array
    {
        if (empty($attempts) || !class_exists('CBT_Live_Proctoring_Presence')) {
            return $attempts;
        }

        $in_progress_attempt_ids = [];
        foreach ($attempts as $attempt) {
            if (!is_array($attempt) || strtolower(trim((string) ($attempt['status'] ?? ''))) !== 'in_progress') {
                continue;
            }

            $attempt_id = absint($attempt['id'] ?? 0);
            if ($attempt_id > 0) {
                $in_progress_attempt_ids[$attempt_id] = $attempt_id;
            }
        }

        if (empty($in_progress_attempt_ids)) {
            return $attempts;
        }

        $presence_payloads = CBT_Live_Proctoring_Presence::get_attempt_payloads(array_values($in_progress_attempt_ids));
        if (!is_array($presence_payloads) || empty($presence_payloads)) {
            return $attempts;
        }

        foreach ($attempts as $index => $attempt) {
            if (!is_array($attempt) || strtolower(trim((string) ($attempt['status'] ?? ''))) !== 'in_progress') {
                continue;
            }

            $attempt_id = absint($attempt['id'] ?? 0);
            if ($attempt_id <= 0 || !isset($presence_payloads[$attempt_id]) || !is_array($presence_payloads[$attempt_id])) {
                continue;
            }

            $payload = $presence_payloads[$attempt_id];
            $attempts[$index]['presence_status'] = sanitize_key((string) ($payload['presence_status'] ?? ''));
            $attempts[$index]['presence_last_seen_at'] = trim((string) ($payload['last_seen_at'] ?? ''));
            $attempts[$index]['presence_connection_status'] = strtolower(trim((string) ($payload['connection_status'] ?? '')));
            $attempts[$index]['presence_visibility_state'] = strtolower(trim((string) ($payload['visibility_state'] ?? '')));
            $attempts[$index]['presence_has_focus'] = array_key_exists('has_focus', $payload) && $payload['has_focus'] !== null
                ? (int) $payload['has_focus']
                : null;
            $attempts[$index]['presence_pending_sync_count'] = max(0, (int) ($payload['pending_sync_count'] ?? 0));
            $attempts[$index]['presence_heartbeat_lost_active'] = !empty($payload['heartbeat_lost_active']) ? 1 : 0;
        }

        return $attempts;
    }

    /**
     * @param array<string,mixed> $attempt
     */
    public static function render_attempt_student_presence_monitor(array $attempt): string
    {
        $presence_status = sanitize_key((string) ($attempt['presence_status'] ?? ''));
        if (!in_array($presence_status, ['online', 'stale', 'offline'], true)) {
            $presence_status = '';
        }

        $attempt_status = strtolower(trim((string) ($attempt['status'] ?? '')));
        $presence_last_seen_at = trim((string) ($attempt['presence_last_seen_at'] ?? ''));
        $presence_connection_status = strtolower(trim((string) ($attempt['presence_connection_status'] ?? '')));
        $presence_visibility_state = strtolower(trim((string) ($attempt['presence_visibility_state'] ?? '')));
        $presence_has_focus = array_key_exists('presence_has_focus', $attempt) && $attempt['presence_has_focus'] !== null
            ? (int) $attempt['presence_has_focus']
            : -1;
        $presence_pending_sync_count = max(0, (int) ($attempt['presence_pending_sync_count'] ?? 0));
        $presence_heartbeat_lost_active = !empty($attempt['presence_heartbeat_lost_active']);
        $presence_indicators = [];

        if ($presence_pending_sync_count > 0) {
            $presence_indicators[] = 'Sync ' . $presence_pending_sync_count;
        }
        if ($presence_visibility_state === 'hidden') {
            $presence_indicators[] = 'Hidden';
        }
        if ($presence_has_focus === 0) {
            $presence_indicators[] = 'Focus Off';
        }
        if ($presence_heartbeat_lost_active) {
            $presence_indicators[] = 'Heartbeat';
        }
        if ($presence_connection_status !== '' && $presence_connection_status !== 'online') {
            $presence_indicators[] = 'Conn ' . strtoupper(str_replace('_', ' ', $presence_connection_status));
        }

        $has_presence_monitor = $attempt_status === 'in_progress'
            && ($presence_status !== '' || $presence_last_seen_at !== '' || !empty($presence_indicators));
        if (!$has_presence_monitor) {
            return '';
        }

        $presence_status_label = $presence_status === 'online'
            ? 'Online'
            : ($presence_status === 'stale' ? 'Stale' : ($presence_status === 'offline' ? 'Offline' : ''));

        ob_start();
        ?>
        <div class="cbt-results-student-monitor" aria-label="Monitoring live siswa">
            <?php if ($presence_status !== ''): ?>
                <span class="cbt-results-student-monitor-badge is-<?php echo esc_attr($presence_status); ?>"><?php echo esc_html($presence_status_label); ?></span>
            <?php endif; ?>
            <?php if ($presence_last_seen_at !== ''): ?>
                <span class="cbt-results-student-monitor-meta">
                    <strong>Seen:</strong> <?php echo esc_html($presence_last_seen_at); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($presence_indicators)): ?>
                <div class="cbt-results-student-monitor-chips">
                    <?php foreach ($presence_indicators as $presence_indicator): ?>
                        <span class="cbt-results-student-monitor-chip"><?php echo esc_html($presence_indicator); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function handle_grade_essay(): void
    {
        if (!current_user_can('cbt_grade_essay')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_grade_essay');

        global $wpdb;

        $answer_id = isset($_POST['answer_id']) ? absint($_POST['answer_id']) : 0;
        $score_awarded = isset($_POST['score_awarded']) ? (float) wp_unslash($_POST['score_awarded']) : 0;

        if ($answer_id > 0) {
            $answer_table = $wpdb->prefix . 'cbt_answers';
            $attempt_table = $wpdb->prefix . 'cbt_attempts';
            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';

            $is_admin_scope = self::is_admin_scope();

            if ($is_admin_scope) {
                $answer = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT a.attempt_id, att.student_id, att.exam_id, q.points
                         FROM {$answer_table} a
                         INNER JOIN {$question_table} q ON q.id = a.question_id
                         INNER JOIN {$attempt_table} att ON att.id = a.attempt_id
                         WHERE a.id = %d AND q.question_type = 'essay'",
                        $answer_id
                    ),
                    ARRAY_A
                );
            } else {
                $answer = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT a.attempt_id, att.student_id, att.exam_id, q.points
                         FROM {$answer_table} a
                         INNER JOIN {$question_table} q ON q.id = a.question_id
                         INNER JOIN {$attempt_table} att ON att.id = a.attempt_id
                         INNER JOIN {$exam_table} ex ON ex.id = att.exam_id
                         WHERE a.id = %d AND q.question_type = 'essay' AND ex.created_by = %d",
                        $answer_id,
                        get_current_user_id()
                    ),
                    ARRAY_A
                );
            }

            if ($answer) {
                $max_points = (float) $answer['points'];
                $score_awarded = max(0, min($score_awarded, $max_points));

                $wpdb->update(
                    $answer_table,
                    [
                        'is_correct' => 0,
                        'score_awarded' => $score_awarded,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $answer_id],
                    ['%d', '%f', '%s'],
                    ['%d']
                );

                $attempt_id = (int) $answer['attempt_id'];
                $total_score = (float) $wpdb->get_var(
                    $wpdb->prepare("SELECT COALESCE(SUM(score_awarded),0) FROM {$answer_table} WHERE attempt_id = %d", $attempt_id)
                );

                $wpdb->update(
                    $attempt_table,
                    [
                        'score' => $total_score,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $attempt_id],
                    ['%f', '%s'],
                    ['%d']
                );

                CBT_Cache::invalidate_attempt($attempt_id);
                CBT_Cache::invalidate_user((int) ($answer['student_id'] ?? 0));
                CBT_Cache::invalidate_analytics();
                CBT_Cache::invalidate_analytics_exam((int) ($answer['exam_id'] ?? 0));
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-results&cbt_msg=' . rawurlencode('Essay score updated')));
        exit;
    }

    /**
     * @return array{
     *     page:string,
     *     hash:string,
     *     exam_id:int,
     *     status:string,
     *     kelas:string,
     *     student_keyword:string,
     *     paged:int
     * }
     */
    private static function get_attempt_action_return_context_from_request(): array
    {
        $return_page = isset($_POST['return_page']) ? sanitize_key((string) wp_unslash($_POST['return_page'])) : 'cbt-results';
        if (!in_array($return_page, ['cbt-results', 'cbt-setup', 'cbt-security'], true)) {
            $return_page = 'cbt-results';
        }

        $return_hash = isset($_POST['return_hash']) ? sanitize_key((string) wp_unslash($_POST['return_hash'])) : '';
        if (!in_array($return_hash, ['branding', 'security', 'security-log'], true)) {
            $return_hash = '';
        }

        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        return [
            'page' => $return_page,
            'hash' => $return_hash,
            'exam_id' => $return_exam_id,
            'status' => $return_status,
            'kelas' => $return_kelas,
            'student_keyword' => $return_student_keyword,
            'paged' => $return_paged,
        ];
    }

    /**
     * @param array{
     *     page:string,
     *     hash:string,
     *     exam_id:int,
     *     status:string,
     *     kelas:string,
     *     student_keyword:string,
     *     paged:int
     * } $context
     */
    private static function redirect_with_attempt_action_return(array $context, ?string $message = null, ?string $error = null): void
    {
        $page = isset($context['page']) ? (string) $context['page'] : 'cbt-results';
        if ($page === 'cbt-setup' || $page === 'cbt-security') {
            $target_page = $page === 'cbt-security' ? 'cbt-security' : 'cbt-setup';
            if ($target_page === 'cbt-setup' && in_array((string) ($context['hash'] ?? ''), ['security', 'security-log'], true)) {
                $target_page = 'cbt-security';
            }

            $args = ['page' => $target_page];
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            $redirect_url = add_query_arg($args, admin_url('admin.php'));
            $hash = self::setup_tab_hash_suffix((string) ($context['hash'] ?? ''));
            if ($hash !== '') {
                $redirect_url .= $hash;
            }

            self::dispatch_redirect($redirect_url);
        }

        $args = ['page' => 'cbt-results'];
        if (!empty($context['exam_id'])) {
            $args['cbt_exam_id'] = (int) $context['exam_id'];
        }
        if (!empty($context['status'])) {
            $args['cbt_attempt_status'] = (string) $context['status'];
        }
        if (!empty($context['kelas'])) {
            $args['cbt_result_kelas'] = (string) $context['kelas'];
        }
        if (!empty($context['student_keyword'])) {
            $args['cbt_student_q'] = (string) $context['student_keyword'];
        }
        if (!empty($context['paged']) && (int) $context['paged'] > 1) {
            $args['cbt_results_paged'] = (int) $context['paged'];
        }
        if ($message !== null && $message !== '') {
            $args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $args['cbt_err'] = $error;
        }

        self::dispatch_redirect(add_query_arg($args, admin_url('admin.php')));
    }

    private static function dispatch_redirect(string $url): void
    {
        wp_safe_redirect($url);
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            throw new RuntimeException(self::TEST_REDIRECT_SIGNAL);
        }

        exit;
    }

    private static function setup_tab_hash_suffix(string $tab_name): string
    {
        $tab_name = sanitize_key($tab_name);
        if ($tab_name === 'security') {
            return '#security';
        }

        if ($tab_name === 'security-log') {
            return '#security-log';
        }

        return '';
    }

    public static function handle_extend_attempt_time(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $extra_minutes = isset($_POST['extra_minutes']) ? absint($_POST['extra_minutes']) : 0;
        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_exam_id, $return_status, $return_kelas, $return_student_keyword, $return_paged): void {
            $args = ['page' => 'cbt-results'];
            if ($return_exam_id > 0) {
                $args['cbt_exam_id'] = $return_exam_id;
            }
            if ($return_status !== '') {
                $args['cbt_attempt_status'] = $return_status;
            }
            if ($return_kelas !== '') {
                $args['cbt_result_kelas'] = $return_kelas;
            }
            if ($return_student_keyword !== '') {
                $args['cbt_student_q'] = $return_student_keyword;
            }
            if ($return_paged > 1) {
                $args['cbt_results_paged'] = $return_paged;
            }
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            self::dispatch_redirect(add_query_arg($args, admin_url('admin.php')));
        };

        if ($attempt_id <= 0) {
            $redirect_with(null, 'Attempt tidak valid.');
        }

        check_admin_referer('cbt_extend_attempt_time_' . $attempt_id);

        if ($extra_minutes <= 0) {
            $redirect_with(null, 'Tambahan waktu minimal 1 menit.');
        }

        $extra_minutes = min(180, $extra_minutes);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();

        if ($is_admin_scope) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status, a.started_at, a.question_order, a.option_order, a.extra_time_minutes, e.duration_minutes
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status, a.started_at, a.question_order, a.option_order, a.extra_time_minutes, e.duration_minutes
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d AND e.created_by = %d",
                    $attempt_id,
                    get_current_user_id()
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            $redirect_with(null, 'Attempt tidak ditemukan atau tidak bisa diakses.');
        }

        if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
            $redirect_with(null, 'Hanya attempt dengan status in_progress yang bisa ditambah waktunya.');
        }

        $student_id = (int) ($attempt['student_id'] ?? 0);
        $current_extra_minutes = max(0, (int) ($attempt['extra_time_minutes'] ?? 0));
        $updated_extra_minutes = $current_extra_minutes + $extra_minutes;
        $updated_at = current_time('mysql');

        $updated = $wpdb->update(
            $attempt_table,
            [
                'extra_time_minutes' => $updated_extra_minutes,
                'updated_at' => $updated_at,
            ],
            ['id' => $attempt_id],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            $redirect_with(null, 'Gagal menambahkan waktu attempt.');
        }

        $base_duration_minutes = max(1, (int) ($attempt['duration_minutes'] ?? 0));
        $effective_duration_minutes = $base_duration_minutes + $updated_extra_minutes;

        if (CBT_Runtime::is_ready()) {
            CBT_Runtime::ensure_attempt_state(
                [
                    'id' => (int) ($attempt['id'] ?? 0),
                    'exam_id' => (int) ($attempt['exam_id'] ?? 0),
                    'student_id' => $student_id,
                    'status' => (string) ($attempt['status'] ?? 'in_progress'),
                    'started_at' => (string) ($attempt['started_at'] ?? ''),
                    'question_order' => (string) ($attempt['question_order'] ?? ''),
                    'option_order' => (string) ($attempt['option_order'] ?? ''),
                ],
                $effective_duration_minutes
            );
        }

        CBT_Cache::invalidate_attempt($attempt_id);
        if ($student_id > 0) {
            CBT_Cache::invalidate_user($student_id);
        }

        $redirect_with(
            sprintf(
                'Berhasil menambah %d menit. Total durasi attempt sekarang %d menit.',
                $extra_minutes,
                $effective_duration_minutes
            )
        );
    }

    public static function handle_reset_attempt(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $return_exam_id = isset($_POST['cbt_exam_id']) ? absint($_POST['cbt_exam_id']) : 0;
        $return_status = isset($_POST['cbt_attempt_status']) ? sanitize_key((string) wp_unslash($_POST['cbt_attempt_status'])) : '';
        $return_kelas = isset($_POST['cbt_result_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_result_kelas'])) : '';
        $return_student_keyword = isset($_POST['cbt_student_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_student_q'])) : '';
        $return_paged = isset($_POST['cbt_results_paged']) ? max(1, absint(wp_unslash($_POST['cbt_results_paged']))) : 1;
        $allowed_statuses = ['in_progress', 'completed'];
        if (!in_array($return_status, $allowed_statuses, true)) {
            $return_status = '';
        }

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_exam_id, $return_status, $return_kelas, $return_student_keyword, $return_paged): void {
            $args = ['page' => 'cbt-results'];
            if ($return_exam_id > 0) {
                $args['cbt_exam_id'] = $return_exam_id;
            }
            if ($return_status !== '') {
                $args['cbt_attempt_status'] = $return_status;
            }
            if ($return_kelas !== '') {
                $args['cbt_result_kelas'] = $return_kelas;
            }
            if ($return_student_keyword !== '') {
                $args['cbt_student_q'] = $return_student_keyword;
            }
            if ($return_paged > 1) {
                $args['cbt_results_paged'] = $return_paged;
            }
            if ($message !== null && $message !== '') {
                $args['cbt_msg'] = $message;
            }
            if ($error !== null && $error !== '') {
                $args['cbt_err'] = $error;
            }

            self::dispatch_redirect(add_query_arg($args, admin_url('admin.php')));
        };

        if ($attempt_id <= 0) {
            $redirect_with(null, 'Attempt tidak valid.');
        }

        check_admin_referer('cbt_reset_attempt_' . $attempt_id);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();

        if ($is_admin_scope) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status
                     FROM {$attempt_table} a
                     WHERE a.id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d AND e.created_by = %d",
                    $attempt_id,
                    get_current_user_id()
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            $redirect_with(null, 'Attempt tidak ditemukan atau tidak bisa diakses.');
        }

        if ((string) ($attempt['status'] ?? '') !== 'completed') {
            $redirect_with(null, 'Hanya attempt dengan status completed yang bisa di-reset.');
        }

        $now = current_time('mysql');
        $abandoned_attempt_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$attempt_table}
                 WHERE exam_id = %d
                   AND student_id = %d
                   AND status = 'in_progress'
                   AND id <> %d",
                (int) $attempt['exam_id'],
                (int) $attempt['student_id'],
                $attempt_id
            )
        );
        $abandoned_attempt_ids = array_values(array_filter(array_map('absint', is_array($abandoned_attempt_ids) ? $abandoned_attempt_ids : [])));

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$attempt_table}
                 SET status = 'abandoned', updated_at = %s
                 WHERE exam_id = %d
                   AND student_id = %d
                   AND status = 'in_progress'
                   AND id <> %d",
                $now,
                (int) $attempt['exam_id'],
                (int) $attempt['student_id'],
                $attempt_id
            )
        );

        $updated = $wpdb->update(
            $attempt_table,
            [
                'status' => 'in_progress',
                'score' => 0,
                'max_score' => 0,
                'finished_at' => null,
                'duration_seconds' => 0,
                'started_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $attempt_id],
            null,
            ['%d']
        );

        if ($updated === false) {
            $redirect_with(null, 'Gagal melakukan reset attempt.');
        }

        if (class_exists('CBT_Runtime')) {
            foreach ($abandoned_attempt_ids as $abandoned_attempt_id) {
                CBT_Runtime::clear_attempt_runtime($abandoned_attempt_id);
            }
            CBT_Runtime::clear_attempt_runtime($attempt_id);
        }
        if (!empty($abandoned_attempt_ids)) {
            CBT_Cache::invalidate_attempts($abandoned_attempt_ids);
            CBT_UI_State::clear_attempt_states_by_attempt_ids($abandoned_attempt_ids);
        }
        if (class_exists('CBT_Active_Attempt_Index')) {
            CBT_Active_Attempt_Index::set_active_attempt([
                'id' => $attempt_id,
                'exam_id' => (int) ($attempt['exam_id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'status' => 'in_progress',
            ]);
        }
        CBT_Cache::invalidate_attempt($attempt_id);
        CBT_Cache::invalidate_user((int) ($attempt['student_id'] ?? 0));
        CBT_Cache::invalidate_analytics();
        CBT_Cache::invalidate_analytics_exam((int) ($attempt['exam_id'] ?? 0));
        CBT_UI_State::clear_attempt_state((int) ($attempt['student_id'] ?? 0), $attempt_id);

        $redirect_with('Attempt berhasil di-reset. Siswa dapat lanjut ujian kembali.');
    }

    public static function handle_reset_user_login(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $return_context = self::get_attempt_action_return_context_from_request();

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_context): void {
            self::redirect_with_attempt_action_return($return_context, $message, $error);
        };

        if ($attempt_id <= 0) {
            $redirect_with(null, 'Attempt tidak valid.');
        }

        check_admin_referer('cbt_reset_user_login_' . $attempt_id);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();

        if ($is_admin_scope) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id
                     FROM {$attempt_table} a
                     WHERE a.id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d AND e.created_by = %d",
                    $attempt_id,
                    get_current_user_id()
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            $redirect_with(null, 'Attempt tidak ditemukan atau tidak bisa diakses.');
        }

        $student_id = (int) ($attempt['student_id'] ?? 0);
        if ($student_id <= 0) {
            $redirect_with(null, 'Student pada attempt ini tidak valid.');
        }

        $cleared = CBT_Auth::clear_login_session($student_id);
        if (!$cleared) {
            $redirect_with(null, 'Gagal mereset sesi login siswa.');
        }

        CBT_Cache::invalidate_user($student_id);
        $return_page = (string) ($return_context['page'] ?? '');
        $action_source = in_array($return_page, ['cbt-setup', 'cbt-security'], true) ? 'must_watch_panel' : 'admin_reset_user_login';
        CBT_Security_Log::record_attempt_event((int) ($attempt['id'] ?? 0), 'admin_reset_login', [
            'actor_user_id' => get_current_user_id(),
            'source' => $action_source,
        ]);

        $redirect_with('Login siswa berhasil di-reset. Browser lama akan diminta login ulang dan siswa bisa login kembali.');
    }

    public static function handle_force_complete_attempt(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        $attempt_id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $return_context = self::get_attempt_action_return_context_from_request();

        $redirect_with = static function (?string $message = null, ?string $error = null) use ($return_context): void {
            self::redirect_with_attempt_action_return($return_context, $message, $error);
        };

        if ($attempt_id <= 0) {
            $redirect_with(null, 'Attempt tidak valid.');
        }

        check_admin_referer('cbt_force_complete_attempt_' . $attempt_id);

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();

        if ($is_admin_scope) {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status
                     FROM {$attempt_table} a
                     WHERE a.id = %d",
                    $attempt_id
                ),
                ARRAY_A
            );
        } else {
            $attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id, a.exam_id, a.student_id, a.status
                     FROM {$attempt_table} a
                     INNER JOIN {$exam_table} e ON e.id = a.exam_id
                     WHERE a.id = %d AND e.created_by = %d",
                    $attempt_id,
                    get_current_user_id()
                ),
                ARRAY_A
            );
        }

        if (!$attempt) {
            $redirect_with(null, 'Attempt tidak ditemukan atau tidak bisa diakses.');
        }

        if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
            $redirect_with(null, 'Hanya attempt dengan status in_progress yang bisa dipaksa selesai.');
        }

        $finished_at = current_time('mysql');
        $completion_result = CBT_REST::finalize_attempt_completion($attempt_id, $finished_at);
        if (is_wp_error($completion_result)) {
            $redirect_with(null, 'Gagal memaksa attempt selesai. Coba ulang lagi.');
        }

        CBT_Security_Log::record_attempt_event($attempt_id, 'admin_force_complete', [
            'actor_user_id' => get_current_user_id(),
            'source' => 'must_watch_panel',
        ]);

        $redirect_with('Attempt berhasil dipaksa selesai dari panel Must Watch.');
    }

    public static function handle_bulk_reset_attempts(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_reset_attempts');

        $return_context = self::get_results_page_return_context_from_request();
        if ((string) ($return_context['status'] ?? '') === 'in_progress') {
            self::dispatch_redirect(self::build_results_page_url(
                $return_context,
                null,
                'Filter status in_progress tidak memiliki attempt completed untuk di-reset.'
            ));
        }

        self::start_results_bulk_job('reset', $return_context);
    }

    public static function handle_bulk_force_complete_attempts(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_force_complete_attempts');

        $return_context = self::get_results_page_return_context_from_request();
        if ((string) ($return_context['status'] ?? '') === 'completed') {
            self::dispatch_redirect(self::build_results_page_url(
                $return_context,
                null,
                'Filter status completed tidak memiliki attempt in_progress untuk dipaksa selesai.'
            ));
        }

        self::start_results_bulk_job('force_complete', $return_context);
    }

}
