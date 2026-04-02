<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Active_Attempt_Index')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-active-attempt-index.php';
}

final class CBT_Admin_Results_Service
{
    private const TEST_REDIRECT_SIGNAL = '__cbt_admin_results_redirect__';

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

        unset($query, $wpdb);

        return get_defined_vars();
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
                        'score_awarded' => $score_awarded,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $answer_id],
                    ['%f', '%s'],
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

        if ($return_status === 'in_progress') {
            $redirect_with(null, 'Filter status in_progress tidak memiliki attempt completed untuk di-reset.');
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $filter_kelas = trim($return_kelas);
        $filter_student_keyword = trim($return_student_keyword);

        $where_parts = ['1=1'];
        $where_params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }
        if ($return_exam_id > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $where_params[] = $return_exam_id;
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
        $where_parts[] = "a.status = 'completed'";
        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);

        $target_sql = "SELECT a.id, a.exam_id, a.student_id
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

        $target_rows = $wpdb->get_results($target_sql, ARRAY_A);
        if (empty($target_rows)) {
            $redirect_with(null, 'Tidak ada attempt completed sesuai filter yang bisa di-reset.');
        }

        $target_attempt_ids = [];
        $target_pairs = [];
        $affected_user_ids = [];
        foreach ((array) $target_rows as $target_row) {
            $attempt_id = (int) ($target_row['id'] ?? 0);
            $exam_id = (int) ($target_row['exam_id'] ?? 0);
            $student_id = (int) ($target_row['student_id'] ?? 0);
            if ($attempt_id <= 0 || $exam_id <= 0 || $student_id <= 0) {
                continue;
            }

            $target_attempt_ids[$attempt_id] = $attempt_id;
            $affected_user_ids[$student_id] = $student_id;
            $pair_key = $exam_id . ':' . $student_id;
            $existing_target_attempt_id = isset($target_pairs[$pair_key]['target_attempt_id'])
                ? (int) ($target_pairs[$pair_key]['target_attempt_id'] ?? 0)
                : 0;
            $target_pairs[$pair_key] = [
                'exam_id' => $exam_id,
                'student_id' => $student_id,
                'target_attempt_id' => max($attempt_id, $existing_target_attempt_id),
            ];
        }
        if (empty($target_attempt_ids)) {
            $redirect_with(null, 'Tidak ada attempt valid yang bisa di-reset.');
        }

        $now = current_time('mysql');
        $abandoned_total = 0;
        $abandoned_attempt_ids = [];
        foreach ($target_pairs as $pair) {
            $pair_attempt_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$attempt_table}
                     WHERE exam_id = %d
                       AND student_id = %d
                       AND status = 'in_progress'",
                    (int) ($pair['exam_id'] ?? 0),
                    (int) ($pair['student_id'] ?? 0)
                )
            );
            if (is_array($pair_attempt_ids)) {
                foreach ($pair_attempt_ids as $pair_attempt_id) {
                    $safe_attempt_id = absint($pair_attempt_id);
                    if ($safe_attempt_id > 0) {
                        $abandoned_attempt_ids[$safe_attempt_id] = $safe_attempt_id;
                    }
                }
            }

            $affected = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$attempt_table}
                     SET status = 'abandoned', updated_at = %s
                     WHERE exam_id = %d
                       AND student_id = %d
                       AND status = 'in_progress'",
                    $now,
                    (int) ($pair['exam_id'] ?? 0),
                    (int) ($pair['student_id'] ?? 0)
                )
            );
            if (is_int($affected) && $affected > 0) {
                $abandoned_total += $affected;
            }
        }

        $reset_total = 0;
        $attempt_id_chunks = array_chunk(array_values($target_attempt_ids), 200);
        foreach ($attempt_id_chunks as $attempt_id_chunk) {
            $clean_chunk = array_values(array_filter(array_map('absint', (array) $attempt_id_chunk)));
            if (empty($clean_chunk)) {
                continue;
            }

            $attempt_ids_sql = implode(',', $clean_chunk);
            $reset_sql = $wpdb->prepare(
                "UPDATE {$attempt_table}
                 SET status = 'in_progress',
                     score = 0,
                     max_score = 0,
                     finished_at = NULL,
                     duration_seconds = 0,
                     started_at = %s,
                     updated_at = %s
                 WHERE id IN ({$attempt_ids_sql})
                   AND status = 'completed'",
                $now,
                $now
            );
            $affected = $wpdb->query($reset_sql);
            if ($affected === false) {
                $redirect_with(null, 'Gagal melakukan reset attempt secara massal.');
            }
            if (is_int($affected) && $affected > 0) {
                $reset_total += $affected;
            }
        }

        if ($reset_total <= 0) {
            $redirect_with(null, 'Tidak ada attempt yang berhasil di-reset.');
        }

        if (class_exists('CBT_Runtime')) {
            foreach (array_values($abandoned_attempt_ids) as $abandoned_attempt_id) {
                CBT_Runtime::clear_attempt_runtime((int) $abandoned_attempt_id);
            }
            foreach (array_values($target_attempt_ids) as $target_attempt_id) {
                CBT_Runtime::clear_attempt_runtime((int) $target_attempt_id);
            }
        }
        if (class_exists('CBT_Active_Attempt_Index')) {
            foreach ($target_pairs as $pair) {
                CBT_Active_Attempt_Index::set_active_attempt([
                    'id' => (int) ($pair['target_attempt_id'] ?? 0),
                    'exam_id' => (int) ($pair['exam_id'] ?? 0),
                    'student_id' => (int) ($pair['student_id'] ?? 0),
                    'status' => 'in_progress',
                ]);
            }
        }
        $affected_attempt_ids = array_values(array_unique(array_merge(
            array_values($target_attempt_ids),
            array_values($abandoned_attempt_ids)
        )));
        CBT_Cache::invalidate_attempts($affected_attempt_ids);
        CBT_Cache::invalidate_users(array_values($affected_user_ids));
        CBT_Cache::invalidate_analytics();
        CBT_Cache::invalidate_analytics_exams(array_values(array_unique(array_map(static function (array $pair): int {
            return (int) ($pair['exam_id'] ?? 0);
        }, array_values($target_pairs)))));
        CBT_UI_State::clear_attempt_states_by_attempt_ids($affected_attempt_ids);

        $message = sprintf('Berhasil reset %d attempt sesuai filter.', $reset_total);
        if ($abandoned_total > 0) {
            $message .= ' ' . sprintf('%d attempt in_progress lama ditutup otomatis.', $abandoned_total);
        }
        $redirect_with($message);
    }

    public static function handle_bulk_force_complete_attempts(): void
    {
        if (!current_user_can('cbt_view_results')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_force_complete_attempts');

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

            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        };

        if ($return_status === 'completed') {
            $redirect_with(null, 'Filter status completed tidak memiliki attempt in_progress untuk dipaksa selesai.');
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $filter_kelas = trim($return_kelas);
        $filter_student_keyword = trim($return_student_keyword);

        $where_parts = ['1=1'];
        $where_params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }
        if ($return_exam_id > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $where_params[] = $return_exam_id;
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
        $where_parts[] = "a.status = 'in_progress'";
        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);

        $target_sql = "SELECT a.id
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

        $target_rows = $wpdb->get_results($target_sql, ARRAY_A);
        if (empty($target_rows)) {
            $redirect_with(null, 'Tidak ada attempt in_progress sesuai filter yang bisa dipaksa selesai.');
        }

        $target_attempt_ids = [];
        foreach ((array) $target_rows as $target_row) {
            $attempt_id = (int) ($target_row['id'] ?? 0);
            if ($attempt_id <= 0) {
                continue;
            }

            $target_attempt_ids[$attempt_id] = $attempt_id;
        }
        if (empty($target_attempt_ids)) {
            $redirect_with(null, 'Tidak ada attempt valid yang bisa dipaksa selesai.');
        }

        $now = current_time('mysql');
        $completed_total = 0;
        foreach (array_values($target_attempt_ids) as $attempt_id) {
            $completion_result = CBT_REST::finalize_attempt_completion((int) $attempt_id, $now);
            if (is_wp_error($completion_result)) {
                continue;
            }

            $completed_total++;
        }

        if ($completed_total <= 0) {
            $redirect_with(null, 'Tidak ada attempt yang berhasil dipaksa selesai.');
        }

        $failed_total = max(0, count($target_attempt_ids) - $completed_total);
        if ($failed_total > 0) {
            $redirect_with(sprintf('Berhasil memaksa %d attempt in_progress menjadi completed.', $completed_total), sprintf('%d attempt gagal diselesaikan. Coba ulang lagi untuk attempt yang tersisa.', $failed_total));
        }

        $redirect_with(sprintf('Berhasil memaksa %d attempt in_progress menjadi completed.', $completed_total));
    }

}
