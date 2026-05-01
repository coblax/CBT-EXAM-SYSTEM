<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Analytics_Service
{
    private const CACHE_TTL = 300;
    private const CACHE_VERSION = 'v4';
    private const OVERVIEW_PER_PAGE = 10;
    private const DISTRIBUTION_BUCKETS = [
        ['label' => '0-19%', 'min' => 0.0, 'max' => 19.99],
        ['label' => '20-39%', 'min' => 20.0, 'max' => 39.99],
        ['label' => '40-59%', 'min' => 40.0, 'max' => 59.99],
        ['label' => '60-79%', 'min' => 60.0, 'max' => 79.99],
        ['label' => '80-100%', 'min' => 80.0, 'max' => 100.0],
    ];

    public static function can_view(): bool
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
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $run_analytics_mode = self::get_run_analytics_mode($query);
        $has_run_analytics = $run_analytics_mode === 'single';
        $overview_page = self::normalize_positive_page(isset($query['cbt_overview_page']) ? (string) wp_unslash($query['cbt_overview_page']) : '');

        $accessible_exam_rows = self::get_accessible_exam_rows($is_admin_scope, $current_user_id);
        $kelas_filter_rows = CBT_Admin_Report_Exam_Service::get_distinct_user_meta_values('kode_kelas');

        $selected_exam_id = isset($query['cbt_exam_id']) ? absint(wp_unslash((string) $query['cbt_exam_id'])) : 0;
        $selected_exam = self::find_exam_row($selected_exam_id, $accessible_exam_rows);
        if (empty($selected_exam)) {
            $selected_exam_id = 0;
        }

        // Analytics now always runs across all classes; the class filter has been removed from the page.
        $selected_kelas = '';
        $selected_benchmark_kelas = self::normalize_benchmark_kelas(
            isset($query['cbt_benchmark_kelas']) ? (string) wp_unslash($query['cbt_benchmark_kelas']) : ''
        );

        $active_tab = self::normalize_tab(isset($query['cbt_analytics_tab']) ? (string) wp_unslash($query['cbt_analytics_tab']) : '');
        $exam_filter_rows = $accessible_exam_rows;

        $overview_data = self::get_overview_analytics(
            $accessible_exam_rows,
            $selected_exam_id,
            $selected_kelas,
            $is_admin_scope,
            $current_user_id
        );
        $overview_pagination = self::build_overview_pagination(
            (array) ($overview_data['exam_rows'] ?? []),
            $overview_page,
            $selected_exam_id,
            $selected_kelas,
            $has_run_analytics
        );

        if ($run_analytics_mode === 'all') {
            $processed_exam_count = self::warm_exam_analytics_for_rows(
                (array) ($overview_data['exam_rows'] ?? []),
                $selected_kelas,
                $is_admin_scope,
                $current_user_id
            );
            if ($processed_exam_count > 0) {
                $notice = sprintf('ANALYTIC ALL selesai untuk %d exam.', $processed_exam_count);
            } elseif ($error === '') {
                $error = 'Tidak ada exam pada scope filter saat ini untuk diproses oleh ANALYTIC ALL.';
            }
        }

        if ($has_run_analytics && ($selected_exam_id <= 0 || empty($selected_exam))) {
            $has_run_analytics = false;
            $active_tab = 'overview';
            if ($error === '') {
                $error = 'Pilih satu exam terlebih dahulu sebelum menjalankan Analytic.';
            }
            $overview_pagination = self::build_overview_pagination(
                (array) ($overview_data['exam_rows'] ?? []),
                $overview_page,
                0,
                $selected_kelas,
                false
            );
        } elseif ($has_run_analytics && $active_tab === 'overview') {
            $active_tab = 'exam';
        }

        $exam_analytics = null;
        $item_analysis_rows = [];
        $item_analysis_summary = [];
        $student_rows = [];
        if ($has_run_analytics && $selected_exam_id > 0 && !empty($selected_exam)) {
            $statistical_payload = self::get_exam_statistical_payload(
                $selected_exam,
                $selected_kelas,
                $is_admin_scope,
                $current_user_id
            );
            $exam_analytics = self::get_exam_analytics(
                $selected_exam,
                $selected_kelas,
                $is_admin_scope,
                $current_user_id,
                $statistical_payload,
                $selected_benchmark_kelas
            );
            $item_analysis_rows = array_values((array) ($statistical_payload['item_rows'] ?? []));
            $item_analysis_summary = (array) ($statistical_payload['item_summary'] ?? []);
            $student_rows = self::get_student_drilldown_rows(
                $selected_exam,
                $selected_kelas,
                $is_admin_scope,
                $current_user_id,
                $statistical_payload
            );
        }

        $active_filters = self::build_active_filters(
            $selected_exam,
            $selected_kelas
        );

        $analytics_entry_counts = [
            'accessible_exam_count' => count($accessible_exam_rows),
            'completed_attempt_count' => (int) ($overview_data['summary']['completed_attempts'] ?? 0),
            'kelas_count' => count($kelas_filter_rows),
        ];

        return compact(
            'accessible_exam_rows',
            'active_filters',
            'active_tab',
            'analytics_entry_counts',
            'error',
            'exam_analytics',
            'exam_filter_rows',
            'item_analysis_rows',
            'item_analysis_summary',
            'has_run_analytics',
            'notice',
            'overview_data',
            'overview_page',
            'overview_pagination',
            'selected_exam',
            'selected_exam_id',
            'selected_benchmark_kelas',
            'selected_kelas',
            'student_rows'
        );
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function build_analytics_url(array $args = []): string
    {
        return add_query_arg(
            array_filter(
                array_merge(['page' => 'cbt-analytics'], $args),
                static function ($value): bool {
                    return $value !== null && $value !== '';
                }
            ),
            admin_url('admin.php')
        );
    }

    public static function build_results_url(int $exam_id, string $studentQuery = ''): string
    {
        $args = [
            'page' => 'cbt-results',
        ];
        if ($exam_id > 0) {
            $args['cbt_exam_id'] = $exam_id;
        }
        if ($studentQuery !== '') {
            $args['cbt_student_q'] = $studentQuery;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    public static function normalize_tab(string $raw): string
    {
        $tab = sanitize_key($raw);
        if (!in_array($tab, ['overview', 'exam', 'items', 'students'], true)) {
            return 'overview';
        }

        return $tab;
    }

    private static function get_run_analytics_mode(array $query): string
    {
        if (!isset($query['cbt_run_analytics'])) {
            return '';
        }

        $value = sanitize_text_field(wp_unslash((string) $query['cbt_run_analytics']));
        if ($value === 'all') {
            return 'all';
        }
        if ($value === '1') {
            return 'single';
        }

        return '';
    }

    private static function normalize_positive_page(string $raw): int
    {
        $value = absint($raw);

        return $value > 0 ? $value : 1;
    }

    private static function normalize_benchmark_kelas(string $raw): string
    {
        return sanitize_text_field(trim($raw));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_accessible_exam_rows(bool $is_admin_scope, int $current_user_id): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $sql = "SELECT e.id,
                       e.title,
                       e.subject_id,
                       e.kkm_percentage,
                       e.duration_minutes,
                       e.total_questions,
                       e.status,
                       COALESCE(s.name, '') AS subject_name
                FROM {$exam_table} e
                LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                WHERE 1=1
                  AND e.title NOT LIKE %s";
        $params = ['Bank Soal - %'];
        if (!$is_admin_scope) {
            $sql .= ' AND e.created_by = %d';
            $params[] = $current_user_id;
        }
        $sql .= ' ORDER BY e.id DESC';
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['subject_id'] = (int) ($row['subject_id'] ?? 0);
            $row['kkm_percentage'] = self::normalize_kkm_percentage((float) ($row['kkm_percentage'] ?? 75.0));
            $row['duration_minutes'] = max(1, (int) ($row['duration_minutes'] ?? 60));
            $row['title'] = (string) ($row['title'] ?? '');
            $row['subject_name'] = (string) ($row['subject_name'] ?? '');
            $row['filter_label'] = self::build_exam_filter_label($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     * @return array<int,array{id:int,name:string}>
     */
    private static function build_subject_filter_rows(array $exam_rows): array
    {
        $subjects = [];
        foreach ($exam_rows as $exam_row) {
            $subject_id = (int) ($exam_row['subject_id'] ?? 0);
            if ($subject_id <= 0 || isset($subjects[$subject_id])) {
                continue;
            }

            $subjects[$subject_id] = [
                'id' => $subject_id,
                'name' => (string) ($exam_row['subject_name'] ?? ('Subject #' . $subject_id)),
            ];
        }

        uasort($subjects, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $subjects;
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     * @return array<int,array<string,mixed>>
     */
    private static function filter_exam_rows_by_subject(array $exam_rows, int $subject_id): array
    {
        if ($subject_id <= 0) {
            return $exam_rows;
        }

        return array_values(array_filter($exam_rows, static function (array $exam_row) use ($subject_id): bool {
            return (int) ($exam_row['subject_id'] ?? 0) === $subject_id;
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     * @return array<string,mixed>
     */
    private static function find_exam_row(int $exam_id, array $exam_rows): array
    {
        if ($exam_id <= 0) {
            return [];
        }

        foreach ($exam_rows as $exam_row) {
            if ((int) ($exam_row['id'] ?? 0) === $exam_id) {
                return $exam_row;
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $selected_exam
     * @return array<int,array{label:string,value:string}>
     */
    private static function build_active_filters(
        array $selected_exam,
        string $selected_kelas
    ): array {
        $filters = [];
        if (!empty($selected_exam)) {
            $filters[] = [
                'label' => 'Exam',
                'value' => (string) ($selected_exam['title'] ?? '-'),
            ];
        }
        if ($selected_kelas !== '') {
            $filters[] = [
                'label' => 'Kelas',
                'value' => $selected_kelas,
            ];
        }
        if (empty($filters)) {
            $filters[] = [
                'label' => 'Mode',
                'value' => 'Semua hasil yang bisa diakses',
            ];
        }

        return $filters;
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_overview_analytics(
        array $accessible_exam_rows,
        int $selected_exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        $cache_key = 'admin_analytics_overview_' . self::CACHE_VERSION . '_' . md5((string) wp_json_encode([
            'exam_id' => $selected_exam_id,
            'kelas' => $selected_kelas,
            'scope' => $is_admin_scope ? 'admin' : 'teacher',
            'user_id' => $is_admin_scope ? 0 : $current_user_id,
        ]));

        return CBT_Cache::remember(
            $cache_key,
            self::CACHE_TTL,
            [CBT_Cache::namespace_analytics(), CBT_Cache::namespace_catalog()],
            static function () use ($accessible_exam_rows, $selected_exam_id, $selected_kelas, $is_admin_scope, $current_user_id): array {
                $attempt_aggregates = self::get_overview_attempt_aggregates(
                    $selected_exam_id,
                    $selected_kelas,
                    $is_admin_scope,
                    $current_user_id
                );
                $manual_counts = self::get_manual_review_counts(
                    0,
                    $selected_exam_id,
                    $selected_kelas,
                    $is_admin_scope,
                    $current_user_id
                );

                $summary = self::build_summary_metrics_from_aggregates(
                    (int) ($attempt_aggregates['total_attempts'] ?? 0),
                    (float) ($attempt_aggregates['percentage_total'] ?? 0.0),
                    (int) ($attempt_aggregates['pass_count'] ?? 0),
                    (int) ($manual_counts['total'] ?? 0)
                );
                $catalog_rows = $accessible_exam_rows;
                $exam_groups = (array) ($attempt_aggregates['by_exam'] ?? []);
                if ($selected_exam_id > 0) {
                    $catalog_rows = array_values(array_filter($accessible_exam_rows, static function (array $exam_row) use ($selected_exam_id): bool {
                        return (int) ($exam_row['id'] ?? 0) === $selected_exam_id;
                    }));
                } elseif ($selected_kelas !== '') {
                    $catalog_rows = array_values(array_filter($accessible_exam_rows, static function (array $exam_row) use ($manual_counts, $exam_groups): bool {
                        $exam_id = (int) ($exam_row['id'] ?? 0);
                        if ($exam_id <= 0) {
                            return false;
                        }

                        if (!empty($manual_counts['by_exam'][$exam_id])) {
                            return true;
                        }

                        return !empty($exam_groups[$exam_id]);
                    }));
                }

                $overview_exam_rows = [];
                foreach ($catalog_rows as $exam_row) {
                    $exam_id = (int) ($exam_row['id'] ?? 0);
                    $group = (array) ($exam_groups[$exam_id] ?? []);
                    $completed_attempts = max(0, (int) ($group['completed_attempts'] ?? 0));
                    $average_percentage = $completed_attempts > 0
                        ? round(((float) ($group['percentage_total'] ?? 0.0)) / $completed_attempts, 2)
                        : 0.0;
                    $pass_rate = $completed_attempts > 0
                        ? round((((int) ($group['pass_count'] ?? 0)) / $completed_attempts) * 100, 2)
                        : 0.0;
                    $overview_exam_rows[] = [
                        'exam_id' => $exam_id,
                        'title' => (string) ($exam_row['title'] ?? '-'),
                        'subject_name' => (string) ($exam_row['subject_name'] ?? ''),
                        'completed_attempts' => $completed_attempts,
                        'average_percentage' => $average_percentage,
                        'average_percentage_display' => self::format_percent($average_percentage),
                        'pass_rate' => $pass_rate,
                        'pass_rate_display' => self::format_percent($pass_rate),
                        'manual_review_count' => (int) ($group['manual_review_count'] ?? ($manual_counts['by_exam'][$exam_id] ?? 0)),
                    ];
                }

                return [
                    'summary' => $summary,
                    'exam_rows' => $overview_exam_rows,
                ];
            }
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_overview_attempt_aggregates(
        int $selected_exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';

        $where_parts = ["a.status = 'completed'"];
        $params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $current_user_id;
        }
        if ($selected_exam_id > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $params[] = $selected_exam_id;
        }
        if ($selected_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $params[] = $selected_kelas;
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $effective_max_score_sql = "CASE
                WHEN COALESCE(a.max_score, 0) > 0 THEN COALESCE(a.max_score, 0)
                ELSE COALESCE(qtotal.total_points, 0)
            END";
        $normalized_kkm_sql = "LEAST(100, GREATEST(0, COALESCE(e.kkm_percentage, 75)))";
        $percentage_sql = "CASE
                WHEN {$effective_max_score_sql} > 0 THEN ROUND((GREATEST(0, COALESCE(a.score, 0)) / {$effective_max_score_sql}) * 100, 2)
                ELSE 0
            END";
        $is_passed_sql = "CASE
                WHEN {$effective_max_score_sql} > 0 THEN
                    CASE
                        WHEN (GREATEST(0, COALESCE(a.score, 0)) + 0.0001) >= ROUND({$effective_max_score_sql} * ({$normalized_kkm_sql} / 100), 2) THEN 1
                        ELSE 0
                    END
                WHEN {$normalized_kkm_sql} <= 0 THEN 1
                ELSE 0
            END";

        $sql = "SELECT a.exam_id,
                       COUNT(*) AS completed_attempts,
                       COALESCE(SUM({$percentage_sql}), 0) AS percentage_total,
                       COALESCE(SUM({$is_passed_sql}), 0) AS pass_count
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
                    SELECT exam_id, COALESCE(SUM(points), 0) AS total_points
                    FROM {$question_table}
                    WHERE COALESCE(is_active, 1) = 1
                    GROUP BY exam_id
                ) qtotal ON qtotal.exam_id = a.exam_id
                {$where_sql}
                GROUP BY a.exam_id";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $result = [
            'total_attempts' => 0,
            'percentage_total' => 0.0,
            'pass_count' => 0,
            'by_exam' => [],
        ];
        if (!is_array($rows)) {
            return $result;
        }

        foreach ($rows as $row) {
            $exam_id = (int) ($row['exam_id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $completed_attempts = max(0, (int) ($row['completed_attempts'] ?? 0));
            $percentage_total = round(max(0.0, (float) ($row['percentage_total'] ?? 0.0)), 2);
            $pass_count = max(0, (int) ($row['pass_count'] ?? 0));

            $result['total_attempts'] += $completed_attempts;
            $result['percentage_total'] += $percentage_total;
            $result['pass_count'] += $pass_count;
            $result['by_exam'][$exam_id] = [
                'completed_attempts' => $completed_attempts,
                'percentage_total' => $percentage_total,
                'pass_count' => $pass_count,
            ];
        }

        $result['percentage_total'] = round((float) $result['percentage_total'], 2);

        return $result;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function build_overview_pagination(
        array $rows,
        int $current_page,
        int $selected_exam_id,
        string $selected_kelas,
        bool $has_run_analytics
    ): array {
        $total_rows = count($rows);
        $per_page = self::OVERVIEW_PER_PAGE;
        $total_pages = max(1, (int) ceil($total_rows / $per_page));
        $current_page = max(1, min($current_page, $total_pages));
        $offset = ($current_page - 1) * $per_page;
        $current_rows = array_slice($rows, $offset, $per_page);

        $base_args = [
            'cbt_analytics_tab' => 'overview',
            'cbt_exam_id' => $selected_exam_id > 0 ? $selected_exam_id : null,
            'cbt_result_kelas' => $selected_kelas !== '' ? $selected_kelas : null,
            'cbt_run_analytics' => $has_run_analytics ? '1' : null,
        ];

        foreach ($current_rows as &$row) {
            $row['analytic_url'] = self::build_analytics_url([
                'cbt_analytics_tab' => 'exam',
                'cbt_exam_id' => (int) ($row['exam_id'] ?? 0),
                'cbt_result_kelas' => $selected_kelas !== '' ? $selected_kelas : null,
                'cbt_overview_page' => $current_page,
                'cbt_run_analytics' => '1',
            ]);
        }
        unset($row);

        $page_links = [];
        $window_start = max(1, $current_page - 2);
        $window_end = min($total_pages, $window_start + 4);
        $window_start = max(1, $window_end - 4);
        for ($page = $window_start; $page <= $window_end; $page++) {
            $page_links[] = [
                'page' => $page,
                'is_current' => $page === $current_page ? 1 : 0,
                'url' => self::build_analytics_url(array_merge($base_args, [
                    'cbt_overview_page' => $page,
                ])),
            ];
        }

        return [
            'rows' => $current_rows,
            'current_page' => $current_page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'total_rows' => $total_rows,
            'start_row' => $total_rows > 0 ? ($offset + 1) : 0,
            'end_row' => $total_rows > 0 ? min($total_rows, $offset + $per_page) : 0,
            'has_multiple_pages' => $total_pages > 1 ? 1 : 0,
            'prev_url' => $current_page > 1
                ? self::build_analytics_url(array_merge($base_args, ['cbt_overview_page' => $current_page - 1]))
                : '',
            'next_url' => $current_page < $total_pages
                ? self::build_analytics_url(array_merge($base_args, ['cbt_overview_page' => $current_page + 1]))
                : '',
            'page_links' => $page_links,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $exam_rows
     */
    private static function warm_exam_analytics_for_rows(
        array $exam_rows,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): int {
        $processed = 0;

        foreach ($exam_rows as $exam_row) {
            $exam = (array) $exam_row;
            $exam_id = (int) ($exam['exam_id'] ?? ($exam['id'] ?? 0));
            if ($exam_id <= 0) {
                continue;
            }

            $exam_scope = [
                'id' => $exam_id,
                'title' => (string) ($exam['title'] ?? '-'),
                'subject_name' => (string) ($exam['subject_name'] ?? ''),
                'kkm_percentage' => (float) ($exam['kkm_percentage'] ?? 75.0),
                'duration_minutes' => max(1, (int) ($exam['duration_minutes'] ?? 60)),
            ];

            $statistical_payload = self::get_exam_statistical_payload(
                $exam_scope,
                $selected_kelas,
                $is_admin_scope,
                $current_user_id
            );

            self::get_exam_analytics(
                $exam_scope,
                $selected_kelas,
                $is_admin_scope,
                $current_user_id,
                $statistical_payload,
                ''
            );

            self::get_student_drilldown_rows(
                $exam_scope,
                $selected_kelas,
                $is_admin_scope,
                $current_user_id,
                $statistical_payload
            );

            $processed++;
        }

        return $processed;
    }

    /**
     * @param array<string,mixed> $selected_exam
     * @return array<string,mixed>
     */
    private static function get_exam_analytics(
        array $selected_exam,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id,
        array $statistical_payload = [],
        string $selected_benchmark_kelas = ''
    ): array {
        $exam_id = (int) ($selected_exam['id'] ?? 0);
        if ($exam_id <= 0) {
            return [];
        }

        $cache_key = 'admin_analytics_exam_' . self::CACHE_VERSION . '_' . md5((string) wp_json_encode([
            'exam_id' => $exam_id,
            'kelas' => $selected_kelas,
            'benchmark_kelas' => $selected_benchmark_kelas,
            'scope' => $is_admin_scope ? 'admin' : 'teacher',
            'user_id' => $is_admin_scope ? 0 : $current_user_id,
        ]));

        return CBT_Cache::remember(
            $cache_key,
            self::CACHE_TTL,
            [
                CBT_Cache::namespace_analytics(),
                CBT_Cache::namespace_analytics_exam($exam_id),
                CBT_Cache::namespace_exam($exam_id),
            ],
            static function () use ($exam_id, $selected_exam, $selected_kelas, $selected_benchmark_kelas, $is_admin_scope, $current_user_id, $statistical_payload): array {
                $rows = self::get_completed_attempt_metric_rows(0, $exam_id, $selected_kelas, $is_admin_scope, $current_user_id);
                $manual_counts = self::get_manual_review_counts(0, $exam_id, $selected_kelas, $is_admin_scope, $current_user_id);
                $question_stats = self::get_exam_question_stats($exam_id);
                $payload = !empty($statistical_payload)
                    ? $statistical_payload
                    : self::get_exam_statistical_payload($selected_exam, $selected_kelas, $is_admin_scope, $current_user_id);

                $percentages = array_map(static function (array $row): float {
                    return (float) ($row['percentage'] ?? 0.0);
                }, $rows);
                $percentages = array_values($percentages);
                sort($percentages);

                $score_values = array_map(static function (array $row): float {
                    return (float) ($row['score'] ?? 0.0);
                }, $rows);

                $completed_attempts = count($rows);
                $pass_count = 0;
                $score_total = 0.0;
                foreach ($rows as $row) {
                    $pass_count += (int) ($row['is_passed'] ?? 0);
                    $score_total += (float) ($row['score'] ?? 0.0);
                }

                $average_percentage = $completed_attempts > 0 ? round(array_sum($percentages) / $completed_attempts, 2) : 0.0;
                $median_percentage = self::calculate_median($percentages);
                $highest_percentage = !empty($percentages) ? max($percentages) : 0.0;
                $lowest_percentage = !empty($percentages) ? min($percentages) : 0.0;
                $average_score = $completed_attempts > 0 ? round($score_total / $completed_attempts, 2) : 0.0;
                $fail_count = max(0, $completed_attempts - $pass_count);
                $pass_rate = $completed_attempts > 0 ? round(($pass_count / $completed_attempts) * 100, 2) : 0.0;
                $pending_manual_reviews = (int) ($manual_counts['total'] ?? 0);
                $distribution = self::build_distribution_buckets($percentages);
                $per_kelas_summary = self::build_exam_kelas_summary($rows, (array) ($manual_counts['by_class'] ?? []));
                $quality = (array) ($payload['exam_quality'] ?? []);
                $item_summary = (array) ($payload['item_summary'] ?? []);

                $current_max_score = (float) ($question_stats['total_points'] ?? 0.0);
                $kkm_percentage = self::normalize_kkm_percentage((float) ($selected_exam['kkm_percentage'] ?? 75.0));
                $passing_score = self::calculate_passing_score($current_max_score, $kkm_percentage);
                $duration_minutes = max(1, (int) ($selected_exam['duration_minutes'] ?? 60));
                $in_progress_count = self::count_in_progress_attempts($exam_id, $selected_kelas, $is_admin_scope, $current_user_id);

                return [
                    'exam' => [
                        'id' => $exam_id,
                        'title' => (string) ($selected_exam['title'] ?? '-'),
                        'subject_name' => (string) ($selected_exam['subject_name'] ?? ''),
                        'kkm_percentage' => $kkm_percentage,
                        'kkm_percentage_display' => self::format_number($kkm_percentage),
                        'duration_minutes' => $duration_minutes,
                        'current_max_score' => $current_max_score,
                        'current_max_score_display' => self::format_number($current_max_score),
                        'passing_score' => $passing_score,
                        'passing_score_display' => self::format_number($passing_score),
                        'total_questions' => (int) ($question_stats['total_questions'] ?? 0),
                        'archived_question_count' => (int) ($question_stats['archived_question_count'] ?? 0),
                    ],
                    'summary' => [
                        'completed_attempts' => $completed_attempts,
                        'average_percentage' => $average_percentage,
                        'average_percentage_display' => self::format_percent($average_percentage),
                        'median_percentage' => $median_percentage,
                        'median_percentage_display' => self::format_percent($median_percentage),
                        'highest_percentage' => $highest_percentage,
                        'highest_percentage_display' => self::format_percent($highest_percentage),
                        'lowest_percentage' => $lowest_percentage,
                        'lowest_percentage_display' => self::format_percent($lowest_percentage),
                        'average_score' => $average_score,
                        'average_score_display' => self::format_number($average_score),
                        'pass_count' => $pass_count,
                        'fail_count' => $fail_count,
                        'pass_rate' => $pass_rate,
                        'pass_rate_display' => self::format_percent($pass_rate),
                        'manual_review_count' => $pending_manual_reviews,
                        'has_temporary_status' => $pending_manual_reviews > 0 ? 1 : 0,
                    ],
                    'quality' => $quality,
                    'item_flags' => [
                        'weak_discrimination_count' => (int) ($item_summary['weak_discrimination_count'] ?? 0),
                        'high_omission_count' => (int) ($item_summary['high_omission_count'] ?? 0),
                        'pending_manual_count' => (int) ($item_summary['pending_manual_count'] ?? 0),
                        'suspect_key_count' => (int) ($item_summary['suspect_key_count'] ?? 0),
                        'failed_distractor_count' => (int) ($item_summary['failed_distractor_count'] ?? 0),
                        'anchor_item_count' => (int) ($item_summary['anchor_item_count'] ?? 0),
                        'cognitive_trap_count' => (int) ($item_summary['cognitive_trap_count'] ?? 0),
                    ],
                    'distribution' => $distribution,
                    'per_kelas_summary' => $per_kelas_summary,
                    'behavioral_quadrant' => self::build_behavioral_quadrant($rows, $duration_minutes, $kkm_percentage),
                    'benchmark_overlay' => self::build_benchmark_overlay($rows, $selected_benchmark_kelas),
                    'predictive_pass_rate' => self::build_predictive_pass_rate($completed_attempts, $pass_count, $in_progress_count),
                ];
            }
        );
    }

    /**
     * @param array<string,mixed> $selected_exam
     * @return array<string,mixed>
     */
    private static function get_exam_statistical_payload(
        array $selected_exam,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        $exam_id = (int) ($selected_exam['id'] ?? 0);
        if ($exam_id <= 0) {
            return [
                'attempt_rows' => [],
                'progress_map' => [],
                'question_meta' => [],
                'objective_attempt_metrics' => [],
                'item_rows' => [],
                'item_summary' => [],
                'exam_quality' => [],
            ];
        }

        $cache_key = 'admin_analytics_stats_' . self::CACHE_VERSION . '_' . md5((string) wp_json_encode([
            'exam_id' => $exam_id,
            'kelas' => $selected_kelas,
            'scope' => $is_admin_scope ? 'admin' : 'teacher',
            'user_id' => $is_admin_scope ? 0 : $current_user_id,
        ]));

        return CBT_Cache::remember(
            $cache_key,
            self::CACHE_TTL,
            [
                CBT_Cache::namespace_analytics(),
                CBT_Cache::namespace_analytics_exam($exam_id),
                CBT_Cache::namespace_exam($exam_id),
            ],
            static function () use ($selected_exam, $selected_kelas, $is_admin_scope, $current_user_id): array {
                $dataset = self::get_exam_progress_dataset($selected_exam, $selected_kelas, $is_admin_scope, $current_user_id);
                $attempt_rows = (array) ($dataset['attempt_rows'] ?? []);
                $progress_map = (array) ($dataset['progress_map'] ?? []);
                $question_meta = (array) ($dataset['question_meta'] ?? []);
                $objective_attempt_metrics = self::build_objective_attempt_metrics($attempt_rows, $progress_map, $question_meta);
                $item_rows = self::build_advanced_item_analysis_rows($attempt_rows, $progress_map, $question_meta, $objective_attempt_metrics);
                $item_summary = self::build_item_analysis_summary($item_rows);
                $exam_quality = self::build_exam_quality_metrics($objective_attempt_metrics, $item_rows);

                return [
                    'attempt_rows' => $attempt_rows,
                    'progress_map' => $progress_map,
                    'question_meta' => $question_meta,
                    'objective_attempt_metrics' => $objective_attempt_metrics,
                    'item_rows' => $item_rows,
                    'item_summary' => $item_summary,
                    'exam_quality' => $exam_quality,
                ];
            }
        );
    }

    /**
     * @param array<string,mixed> $selected_exam
     * @return array<int,array<string,mixed>>
     */
    private static function get_item_analysis_rows(
        array $selected_exam,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id,
        array $statistical_payload = []
    ): array {
        if (empty($statistical_payload)) {
            $statistical_payload = self::get_exam_statistical_payload($selected_exam, $selected_kelas, $is_admin_scope, $current_user_id);
        }

        return array_values((array) ($statistical_payload['item_rows'] ?? []));
    }

    /**
     * @param array<int,array<string,mixed>> $attempt_rows
     * @param array<int,array<string,mixed>> $progress_map
     * @param array<int,array<string,mixed>> $question_meta
     * @return array<string,mixed>
     */
    private static function build_objective_attempt_metrics(
        array $attempt_rows,
        array $progress_map,
        array $question_meta
    ): array {
        $attempt_metrics = [];
        $question_ratios = [];

        foreach ($attempt_rows as $attempt_row) {
            $attempt_id = (int) ($attempt_row['id'] ?? 0);
            if ($attempt_id <= 0) {
                continue;
            }

            $attempt_metrics[$attempt_id] = [
                'attempt_id' => $attempt_id,
                'objective_score_total' => 0.0,
                'objective_max_total' => 0.0,
                'objective_percentage' => 0.0,
                'objective_percentage_display' => '0.00%',
                'objective_item_count' => 0,
                'group_band' => 'middle',
            ];

            $progress_sections = (array) ($progress_map[$attempt_id] ?? []);
            $progress_items = array_merge(
                (array) ($progress_sections['active_items'] ?? []),
                (array) ($progress_sections['archived_items'] ?? [])
            );

            foreach ($progress_items as $progress_item_row) {
                $progress_item = (array) $progress_item_row;
                $question_id = (int) ($progress_item['question_id'] ?? 0);
                $meta = (array) ($question_meta[$question_id] ?? []);
                if ($question_id <= 0 || empty($meta) || empty($meta['is_objective'])) {
                    continue;
                }

                $status = (string) ($progress_item['status'] ?? 'unanswered');
                if ($status === 'manual') {
                    continue;
                }

                $effective_max_score = max(0.0, (float) ($meta['effective_max_score'] ?? ($meta['points'] ?? 0.0)));
                if ($effective_max_score <= 0.0) {
                    continue;
                }

                $score_awarded = max(0.0, (float) ($progress_item['score_awarded'] ?? 0.0));
                $clamped_score = min($effective_max_score, $score_awarded);
                $ratio = self::calculate_item_score_ratio($clamped_score, $effective_max_score);

                $attempt_metrics[$attempt_id]['objective_score_total'] += $clamped_score;
                $attempt_metrics[$attempt_id]['objective_max_total'] += $effective_max_score;
                $attempt_metrics[$attempt_id]['objective_item_count']++;

                if (!isset($question_ratios[$question_id])) {
                    $question_ratios[$question_id] = [];
                }
                $question_ratios[$question_id][$attempt_id] = $ratio;
            }
        }

        foreach ($attempt_metrics as &$metric) {
            $objective_max_total = max(0.0, (float) ($metric['objective_max_total'] ?? 0.0));
            $objective_score_total = max(0.0, (float) ($metric['objective_score_total'] ?? 0.0));
            $objective_percentage = $objective_max_total > 0
                ? round(($objective_score_total / $objective_max_total) * 100, 2)
                : 0.0;

            $metric['objective_percentage'] = $objective_percentage;
            $metric['objective_percentage_display'] = self::format_percent($objective_percentage);
        }
        unset($metric);

        $ranked_attempt_ids = array_keys($attempt_metrics);
        usort($ranked_attempt_ids, static function (int $leftId, int $rightId) use ($attempt_metrics): int {
            $left = (array) ($attempt_metrics[$leftId] ?? []);
            $right = (array) ($attempt_metrics[$rightId] ?? []);
            $percentageCompare = (float) ($right['objective_percentage'] ?? 0.0) <=> (float) ($left['objective_percentage'] ?? 0.0);
            if ($percentageCompare !== 0) {
                return $percentageCompare;
            }

            return $leftId <=> $rightId;
        });

        $completed_attempt_count = count($ranked_attempt_ids);
        $group_size = $completed_attempt_count > 0 ? max(1, (int) ceil($completed_attempt_count * 0.27)) : 0;
        $upper_ids = array_slice($ranked_attempt_ids, 0, $group_size);
        $lower_ids = $group_size > 0 ? array_slice($ranked_attempt_ids, -$group_size) : [];

        foreach ($upper_ids as $attempt_id) {
            if (isset($attempt_metrics[$attempt_id])) {
                $attempt_metrics[$attempt_id]['group_band'] = 'upper';
            }
        }
        foreach ($lower_ids as $attempt_id) {
            if (isset($attempt_metrics[$attempt_id]) && !in_array($attempt_id, $upper_ids, true)) {
                $attempt_metrics[$attempt_id]['group_band'] = 'lower';
            }
        }

        return [
            'attempts' => $attempt_metrics,
            'question_ratios' => $question_ratios,
            'completed_attempt_count' => $completed_attempt_count,
            'upper_group_size' => count($upper_ids),
            'lower_group_size' => count($lower_ids),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $attempt_rows
     * @param array<int,array<string,mixed>> $progress_map
     * @param array<int,array<string,mixed>> $question_meta
     * @param array<string,mixed> $objective_context
     * @return array<int,array<string,mixed>>
     */
    private static function build_advanced_item_analysis_rows(
        array $attempt_rows,
        array $progress_map,
        array $question_meta,
        array $objective_context
    ): array {
        $total_attempts = count($attempt_rows);
        if (empty($question_meta)) {
            return [];
        }

        $attempt_metrics = (array) ($objective_context['attempts'] ?? []);
        $question_ratios = (array) ($objective_context['question_ratios'] ?? []);
        $upper_group_size = max(0, (int) ($objective_context['upper_group_size'] ?? 0));
        $lower_group_size = max(0, (int) ($objective_context['lower_group_size'] ?? 0));
        $eligible_completed_attempts = max(0, (int) ($objective_context['completed_attempt_count'] ?? 0));

        $analytics_rows = [];
        foreach ($question_meta as $question_id => $meta) {
            $question_id = (int) $question_id;
            $options = array_values((array) ($meta['options'] ?? []));
            $detail = (array) ($meta['detail'] ?? []);
            $question_type = (string) ($meta['question_type'] ?? '');

            $option_analysis = [];
            foreach ($options as $option_row) {
                $option = (array) $option_row;
                $option_id = (int) ($option['id'] ?? 0);
                if ($option_id <= 0) {
                    continue;
                }
                $option_analysis[$option_id] = [
                    'option_id' => $option_id,
                    'label' => self::format_option_label((string) ($option['option_key'] ?? ''), (string) ($option['option_text'] ?? '')),
                    'is_correct' => (int) ($option['is_correct'] ?? 0) === 1 ? 1 : 0,
                    'count' => 0,
                    'upper_count' => 0,
                    'lower_count' => 0,
                ];
            }

            $matrix_analysis = [];
            if ($question_type === 'true_false_matrix') {
                $matrix_items = array_values((array) ($detail['matrix_items'] ?? []));
                foreach ($matrix_items as $index => $matrix_row) {
                    $matrix = (array) $matrix_row;
                    $matrix_analysis[] = [
                        'statement_number' => $index + 1,
                        'statement_text' => (string) ($matrix['text'] ?? ''),
                        'correct_answer' => ((string) ($matrix['answer'] ?? 'true') === 'false') ? 'Salah' : 'Benar',
                        'correct_count' => 0,
                        'wrong_count' => 0,
                        'unanswered_count' => 0,
                    ];
                }
            }

            $analytics_rows[$question_id] = [
                'question_id' => $question_id,
                'question_number' => (int) ($meta['question_number'] ?? 0),
                'question_text' => (string) ($meta['question_text'] ?? ''),
                'question_preview' => (string) ($meta['question_preview'] ?? ''),
                'question_type' => $question_type,
                'question_type_label' => (string) ($meta['question_type_label'] ?? ''),
                'points' => (float) ($meta['points'] ?? 0.0),
                'points_display' => self::format_number((float) ($meta['points'] ?? 0.0)),
                'effective_max_score' => (float) ($meta['effective_max_score'] ?? ($meta['points'] ?? 0.0)),
                'effective_max_score_display' => self::format_number((float) ($meta['effective_max_score'] ?? ($meta['points'] ?? 0.0))),
                'is_archived' => (int) ($meta['is_archived'] ?? 0),
                'is_objective' => !empty($meta['is_objective']) ? 1 : 0,
                'is_partial_credit' => !empty($meta['is_partial_credit']) ? 1 : 0,
                'seen_count' => $total_attempts,
                'answered_count' => 0,
                'correct_count' => 0,
                'wrong_count' => 0,
                'unanswered_count' => 0,
                'manual_count' => 0,
                'total_score_awarded' => 0.0,
                'distribution_counts' => [],
                'archived_attempt_count' => 0,
                'correct_answer_summary' => (string) ($meta['correct_answer_summary'] ?? '-'),
                'option_analysis_map' => $option_analysis,
                'matrix_analysis' => $matrix_analysis,
                'short_answer_expected_inputs' => max(1, count((array) ($detail['correct_answers'] ?? []))),
                'short_answer_total_inputs' => 0,
                'short_answer_matched_inputs' => 0,
                'short_answer_wrong_clusters' => [],
                'essay_score_values' => [],
                'upper_ratio_sum' => 0.0,
                'upper_ratio_count' => 0,
                'lower_ratio_sum' => 0.0,
                'lower_ratio_count' => 0,
            ];
        }

        foreach ($attempt_rows as $attempt_row) {
            $attempt_id = (int) ($attempt_row['id'] ?? 0);
            $progress_sections = (array) ($progress_map[$attempt_id] ?? []);
            $progress_items = array_merge(
                (array) ($progress_sections['active_items'] ?? []),
                (array) ($progress_sections['archived_items'] ?? [])
            );
            $attempt_metric = (array) ($attempt_metrics[$attempt_id] ?? []);
            $group_band = (string) ($attempt_metric['group_band'] ?? 'middle');

            foreach ($progress_items as $progress_item_row) {
                $progress_item = (array) $progress_item_row;
                $question_id = (int) ($progress_item['question_id'] ?? 0);
                if ($question_id <= 0 || !isset($analytics_rows[$question_id])) {
                    continue;
                }

                $row = &$analytics_rows[$question_id];
                $meta = (array) ($question_meta[$question_id] ?? []);
                $status = (string) ($progress_item['status'] ?? 'unanswered');
                $preview = trim((string) ($progress_item['answer_preview'] ?? ''));
                if ($preview === '') {
                    $preview = 'Belum dijawab';
                }

                if ($status !== 'unanswered') {
                    $row['answered_count']++;
                }
                if ($status === 'correct') {
                    $row['correct_count']++;
                } elseif ($status === 'wrong') {
                    $row['wrong_count']++;
                } elseif ($status === 'manual') {
                    $row['manual_count']++;
                } else {
                    $row['unanswered_count']++;
                }

                $row['total_score_awarded'] += max(0.0, (float) ($progress_item['score_awarded'] ?? 0.0));
                if (!isset($row['distribution_counts'][$preview])) {
                    $row['distribution_counts'][$preview] = 0;
                }
                $row['distribution_counts'][$preview]++;

                if (!empty($progress_item['is_archived'])) {
                    $row['archived_attempt_count']++;
                }

                $question_number = (int) ($progress_item['question_number'] ?? 0);
                if ($question_number > 0) {
                    $current_number = (int) ($row['question_number'] ?? 0);
                    if ($current_number <= 0 || $question_number < $current_number) {
                        $row['question_number'] = $question_number;
                    }
                }

                $score_ratio = isset($question_ratios[$question_id][$attempt_id])
                    ? (float) $question_ratios[$question_id][$attempt_id]
                    : self::calculate_item_score_ratio(
                        (float) ($progress_item['score_awarded'] ?? 0.0),
                        (float) ($row['effective_max_score'] ?? 0.0)
                    );

                if (!empty($row['is_objective']) && $status !== 'manual' && $eligible_completed_attempts >= 5) {
                    if ($group_band === 'upper') {
                        $row['upper_ratio_sum'] += $score_ratio;
                        $row['upper_ratio_count']++;
                    } elseif ($group_band === 'lower') {
                        $row['lower_ratio_sum'] += $score_ratio;
                        $row['lower_ratio_count']++;
                    }
                }

                $question_type = (string) ($row['question_type'] ?? '');
                if (in_array($question_type, ['multiple_choice', 'true_false'], true)) {
                    $selected_option_ids = array_values(array_filter(array_map('intval', (array) ($progress_item['selected_option_ids'] ?? []))));
                    foreach ($selected_option_ids as $option_id) {
                        if (!isset($row['option_analysis_map'][$option_id])) {
                            continue;
                        }
                        $row['option_analysis_map'][$option_id]['count']++;
                        if ($group_band === 'upper') {
                            $row['option_analysis_map'][$option_id]['upper_count']++;
                        } elseif ($group_band === 'lower') {
                            $row['option_analysis_map'][$option_id]['lower_count']++;
                        }
                    }
                } elseif ($question_type === 'multiple_answer') {
                    $selected_option_ids = array_values(array_filter(array_map('intval', (array) ($progress_item['selected_option_ids'] ?? []))));
                    foreach ($selected_option_ids as $option_id) {
                        if (!isset($row['option_analysis_map'][$option_id])) {
                            continue;
                        }
                        $row['option_analysis_map'][$option_id]['count']++;
                        if ($group_band === 'upper') {
                            $row['option_analysis_map'][$option_id]['upper_count']++;
                        } elseif ($group_band === 'lower') {
                            $row['option_analysis_map'][$option_id]['lower_count']++;
                        }
                    }
                } elseif ($question_type === 'true_false_matrix') {
                    $matrix_submission = (array) ($progress_item['true_false_matrix_submission'] ?? []);
                    foreach ($row['matrix_analysis'] as $index => $matrix_row) {
                        $statement_key = (string) ($index + 1);
                        $submitted = (string) ($matrix_submission[$statement_key] ?? '');
                        $expected = ((string) (($meta['detail']['matrix_items'][$index]['answer'] ?? 'true')) === 'false') ? 'false' : 'true';
                        if ($submitted === '') {
                            $row['matrix_analysis'][$index]['unanswered_count']++;
                        } elseif ($submitted === $expected) {
                            $row['matrix_analysis'][$index]['correct_count']++;
                        } else {
                            $row['matrix_analysis'][$index]['wrong_count']++;
                        }
                    }
                } elseif ($question_type === 'short_answer') {
                    $slots = array_values((array) ($progress_item['short_answer_slots'] ?? []));
                    $expected_inputs = max(1, count((array) ($meta['detail']['correct_answers'] ?? [])));
                    $row['short_answer_total_inputs'] += $expected_inputs;
                    foreach ($slots as $slot_row) {
                        $slot = (array) $slot_row;
                        $slot_status = (string) ($slot['status'] ?? 'empty');
                        $submitted_value = trim((string) ($slot['value'] ?? ''));
                        if ($slot_status === 'correct') {
                            $row['short_answer_matched_inputs']++;
                        } elseif ($slot_status === 'wrong' && $submitted_value !== '') {
                            $normalized_value = CBT_Admin_Questions_Helper::normalize_short_answer_compare_value($submitted_value);
                            if ($normalized_value !== '') {
                                if (!isset($row['short_answer_wrong_clusters'][$normalized_value])) {
                                    $row['short_answer_wrong_clusters'][$normalized_value] = [
                                        'label' => $submitted_value,
                                        'count' => 0,
                                    ];
                                }
                                $row['short_answer_wrong_clusters'][$normalized_value]['count']++;
                            }
                        }
                    }
                } elseif ($question_type === 'essay') {
                    if ($status !== 'unanswered') {
                        $row['essay_score_values'][] = max(0.0, (float) ($progress_item['score_awarded'] ?? 0.0));
                    }
                }
                unset($row);
            }
        }

        $rows = [];
        foreach ($analytics_rows as $row) {
            $seen_count = max(0, (int) ($row['seen_count'] ?? 0));
            $correct_count = max(0, (int) ($row['correct_count'] ?? 0));
            $manual_count = max(0, (int) ($row['manual_count'] ?? 0));
            $correct_rate = $seen_count > 0 ? round(($correct_count / $seen_count) * 100, 2) : 0.0;
            $omission_rate = $seen_count > 0 ? round((((int) ($row['unanswered_count'] ?? 0)) / $seen_count) * 100, 2) : 0.0;
            $average_awarded_score = $seen_count > 0
                ? round(((float) ($row['total_score_awarded'] ?? 0.0)) / $seen_count, 2)
                : 0.0;

            $difficulty = self::build_difficulty_meta(
                (string) ($row['question_type'] ?? ''),
                $correct_rate,
                $manual_count
            );
            $distribution_counts = (array) ($row['distribution_counts'] ?? []);
            arsort($distribution_counts);
            $distribution = [];
            foreach ($distribution_counts as $label => $count) {
                $distribution[] = [
                    'label' => (string) $label,
                    'count' => (int) $count,
                ];
            }

            $discrimination_value = null;
            if (
                !empty($row['is_objective']) &&
                (string) ($row['question_type'] ?? '') !== 'essay' &&
                $eligible_completed_attempts >= 5 &&
                (int) ($row['upper_ratio_count'] ?? 0) > 0 &&
                (int) ($row['lower_ratio_count'] ?? 0) > 0 &&
                $manual_count <= 0
            ) {
                $upper_average = ((float) ($row['upper_ratio_sum'] ?? 0.0)) / max(1, (int) ($row['upper_ratio_count'] ?? 1));
                $lower_average = ((float) ($row['lower_ratio_sum'] ?? 0.0)) / max(1, (int) ($row['lower_ratio_count'] ?? 1));
                $discrimination_value = round($upper_average - $lower_average, 4);
            }
            $discrimination = self::build_discrimination_meta($discrimination_value, $eligible_completed_attempts, $manual_count);
            $omission = self::build_omission_meta($omission_rate);

            $option_analysis = [];
            if (in_array((string) ($row['question_type'] ?? ''), ['multiple_choice', 'true_false'], true)) {
                $option_analysis = self::build_distractor_analysis_rows((array) ($row['option_analysis_map'] ?? []), $seen_count, $upper_group_size, $lower_group_size);
            } elseif ((string) ($row['question_type'] ?? '') === 'multiple_answer') {
                $option_analysis = self::build_multiple_answer_option_analysis_rows((array) ($row['option_analysis_map'] ?? []), $seen_count, $upper_group_size, $lower_group_size);
            }
            $option_analysis = self::decorate_item_option_analysis_display($option_analysis);

            $matrix_analysis = [];
            if ((string) ($row['question_type'] ?? '') === 'true_false_matrix') {
                $matrix_analysis = self::build_matrix_analysis_rows((array) ($row['matrix_analysis'] ?? []), $seen_count);
            }

            $short_answer_analysis = [];
            if ((string) ($row['question_type'] ?? '') === 'short_answer') {
                $short_answer_analysis = self::build_short_answer_analysis(
                    (int) ($row['short_answer_total_inputs'] ?? 0),
                    (int) ($row['short_answer_matched_inputs'] ?? 0),
                    (array) ($row['short_answer_wrong_clusters'] ?? [])
                );
            }

            $essay_analysis = [];
            if ((string) ($row['question_type'] ?? '') === 'essay') {
                $essay_analysis = self::build_essay_analysis(
                    (int) ($row['answered_count'] ?? 0),
                    (int) ($row['manual_count'] ?? 0),
                    (array) ($row['essay_score_values'] ?? []),
                    $seen_count
                );
            }

            $note_parts = [];
            if (!empty($row['is_archived'])) {
                $note_parts[] = 'Soal ini saat ini inactive/archived pada bank exam.';
            }
            if ((int) ($row['archived_attempt_count'] ?? 0) > 0) {
                $note_parts[] = sprintf(
                    'Muncul pada %d attempt historis sebagai soal archived.',
                    (int) ($row['archived_attempt_count'] ?? 0)
                );
            }
            if ((string) ($difficulty['tone'] ?? '') === 'manual') {
                $note_parts[] = 'Difficulty tier ditahan karena masih ada jawaban manual/essay.';
            }

            $insight = self::build_item_insight_meta(
                (string) ($row['question_type'] ?? ''),
                $seen_count,
                $manual_count,
                $discrimination,
                $omission,
                $option_analysis
            );
            $diagnostic_payload = self::build_item_diagnostic_payload(
                $row,
                $eligible_completed_attempts,
                $correct_rate,
                $discrimination,
                $option_analysis
            );

            $row['correct_rate'] = $correct_rate;
            $row['correct_rate_display'] = self::format_percent($correct_rate);
            $row['omission_rate'] = $omission_rate;
            $row['omission_rate_display'] = self::format_percent($omission_rate);
            $row['omission_label'] = (string) ($omission['label'] ?? '-');
            $row['omission_tone'] = (string) ($omission['tone'] ?? 'neutral');
            $row['average_awarded_score'] = $average_awarded_score;
            $row['average_awarded_score_display'] = self::format_number($average_awarded_score);
            $row['difficulty_label'] = (string) ($difficulty['label'] ?? '-');
            $row['difficulty_tone'] = (string) ($difficulty['tone'] ?? 'neutral');
            $row['discrimination_index'] = $discrimination['value'];
            $row['discrimination_display'] = (string) ($discrimination['display'] ?? 'Insufficient Data');
            $row['discrimination_label'] = (string) ($discrimination['label'] ?? 'Insufficient Data');
            $row['discrimination_tone'] = (string) ($discrimination['tone'] ?? 'neutral');
            $row['distribution'] = $distribution;
            $row['option_analysis'] = $option_analysis;
            $row['matrix_analysis_rows'] = $matrix_analysis;
            $row['short_answer_analysis'] = $short_answer_analysis;
            $row['essay_analysis'] = $essay_analysis;
            $row['insight_label'] = (string) ($insight['label'] ?? '-');
            $row['insight_tone'] = (string) ($insight['tone'] ?? 'neutral');
            $row['insight_key'] = self::build_item_insight_key($row['insight_label']);
            $row['insight_display_label'] = self::localize_item_insight_label($row['insight_label']);
            $row['insight_short_explainer'] = self::build_item_insight_short_explainer($row['insight_label']);
            $row['insight_reason_detail'] = self::build_item_insight_reason_detail(
                $row['insight_label'],
                (string) ($row['question_type'] ?? ''),
                $seen_count,
                $manual_count,
                $discrimination,
                $omission,
                $option_analysis
            );
            $row['insight_next_step'] = self::build_item_insight_next_step($row['insight_label']);
            $row['difficulty_short_explainer'] = self::build_item_difficulty_short_explainer($row['difficulty_label']);
            $row['diagnostic_tags'] = (array) ($diagnostic_payload['diagnostic_tags'] ?? []);
            $row['cognitive_alerts'] = (array) ($diagnostic_payload['cognitive_alerts'] ?? []);
            $row['note'] = trim(implode(' ', $note_parts));
            $row['search_text'] = self::build_item_search_text($row, $option_analysis);

            unset(
                $row['option_analysis_map'],
                $row['matrix_analysis'],
                $row['short_answer_expected_inputs'],
                $row['short_answer_total_inputs'],
                $row['short_answer_matched_inputs'],
                $row['short_answer_wrong_clusters'],
                $row['essay_score_values'],
                $row['upper_ratio_sum'],
                $row['upper_ratio_count'],
                $row['lower_ratio_sum'],
                $row['lower_ratio_count']
            );

            $rows[] = $row;
        }

        usort($rows, static function (array $left, array $right): int {
            $left_number = (int) ($left['question_number'] ?? 0);
            $right_number = (int) ($right['question_number'] ?? 0);
            if ($left_number !== $right_number) {
                return $left_number <=> $right_number;
            }

            return strnatcasecmp((string) ($left['question_preview'] ?? ''), (string) ($right['question_preview'] ?? ''));
        });

        return array_values($rows);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function build_item_analysis_summary(array $rows): array
    {
        $summary = [
            'objective_item_count' => 0,
            'weak_discrimination_count' => 0,
            'high_omission_count' => 0,
            'pending_manual_count' => 0,
            'problem_item_count' => 0,
            'suspect_key_count' => 0,
            'failed_distractor_count' => 0,
            'anchor_item_count' => 0,
            'cognitive_trap_count' => 0,
        ];

        foreach ($rows as $row) {
            if (!empty($row['is_objective'])) {
                $summary['objective_item_count']++;
            }

            $has_problem = false;
            if ((string) ($row['discrimination_label'] ?? '') === 'Weak' || (string) ($row['discrimination_label'] ?? '') === 'Inverse') {
                $summary['weak_discrimination_count']++;
                $has_problem = true;
            }
            if ((string) ($row['omission_label'] ?? '') === 'High') {
                $summary['high_omission_count']++;
                $has_problem = true;
            }
            if ((int) ($row['manual_count'] ?? 0) > 0) {
                $summary['pending_manual_count']++;
                $has_problem = true;
            }
            foreach ((array) ($row['diagnostic_tags'] ?? []) as $tag_row) {
                $tag = (array) $tag_row;
                $key = (string) ($tag['key'] ?? '');
                if ($key === 'suspect_key') {
                    $summary['suspect_key_count']++;
                    $has_problem = true;
                } elseif ($key === 'failed_distractor') {
                    $summary['failed_distractor_count']++;
                    $has_problem = true;
                } elseif ($key === 'anchor_item') {
                    $summary['anchor_item_count']++;
                }
            }
            if (!empty($row['cognitive_alerts'])) {
                $summary['cognitive_trap_count']++;
                $has_problem = true;
            }
            if ($has_problem) {
                $summary['problem_item_count']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $objective_context
     * @param array<int,array<string,mixed>> $item_rows
     * @return array<string,mixed>
     */
    private static function build_exam_quality_metrics(array $objective_context, array $item_rows): array
    {
        $attempt_metrics = (array) ($objective_context['attempts'] ?? []);
        $question_ratios = (array) ($objective_context['question_ratios'] ?? []);
        $question_diagnostics = self::build_reliability_question_diagnostics($item_rows);
        $eligible_question_ids = array_values(array_filter(array_map('intval', (array) ($question_diagnostics['included_question_ids'] ?? [])), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        if (!empty($eligible_question_ids)) {
            $question_ratios = array_intersect_key($question_ratios, array_fill_keys($eligible_question_ids, true));
        } else {
            $question_ratios = [];
        }

        $objective_percentages = [];
        foreach ($attempt_metrics as $metric_row) {
            $metric = (array) $metric_row;
            if ((float) ($metric['objective_max_total'] ?? 0.0) <= 0.0) {
                continue;
            }
            $objective_percentages[] = (float) ($metric['objective_percentage'] ?? 0.0);
        }

        $stddev = self::calculate_standard_deviation($objective_percentages);
        $variance = self::calculate_variance($objective_percentages);

        $average_objective_percentage = !empty($objective_percentages) ? round(array_sum($objective_percentages) / count($objective_percentages), 2) : 0.0;
        $reliability = self::calculate_exam_reliability(
            $question_ratios,
            (string) ($question_diagnostics['preferred_method'] ?? 'Insufficient Data')
        );
        $reliability_value = $reliability['value'];
        $sem = ($reliability_value !== null && $reliability_value >= 0.0 && $reliability_value <= 1.0)
            ? round($stddev * sqrt(max(0.0, 1.0 - $reliability_value)), 4)
            : null;

        $quality_tone = 'neutral';
        $quality_label = 'Insufficient Data';
        if ($reliability_value !== null) {
            if ($reliability_value >= 0.80) {
                $quality_label = 'Reliable';
                $quality_tone = 'pass';
            } elseif ($reliability_value >= 0.70) {
                $quality_label = 'Marginal';
                $quality_tone = 'warning';
            } else {
                $quality_label = 'Weak';
                $quality_tone = 'fail';
            }
        }

        $objective_profile = self::determine_objective_profile($question_diagnostics, $reliability);
        $diagnostics = self::build_reliability_diagnostics_payload($question_diagnostics, $reliability);

        return [
            'objective_attempt_count' => (int) ($reliability['attempt_count'] ?? 0),
            'eligible_attempt_count' => (int) ($reliability['attempt_count'] ?? 0),
            'average_objective_percentage' => $average_objective_percentage,
            'average_objective_percentage_display' => self::format_percent($average_objective_percentage),
            'variance' => $variance,
            'variance_display' => $variance === null ? 'Insufficient Data' : self::format_number($variance),
            'standard_deviation' => $stddev,
            'standard_deviation_display' => $stddev === null ? 'Insufficient Data' : self::format_number($stddev),
            'sem' => $sem,
            'sem_display' => $sem === null ? 'Insufficient Data' : self::format_number($sem),
            'reliability' => $reliability_value,
            'reliability_display' => $reliability_value === null ? 'Insufficient Data' : self::format_number($reliability_value),
            'reliability_method' => (string) ($reliability['method'] ?? 'Insufficient Data'),
            'reliability_preferred_method' => (string) ($question_diagnostics['preferred_method'] ?? 'Insufficient Data'),
            'reliability_label' => $quality_label,
            'reliability_tone' => $quality_tone,
            'insufficient_reason' => (string) ($reliability['reason'] ?? ''),
            'low_discrimination_item_count' => count(array_filter($item_rows, static function (array $row): bool {
                $label = (string) ($row['discrimination_label'] ?? '');
                return $label === 'Weak' || $label === 'Inverse';
            })),
            'high_omission_item_count' => count(array_filter($item_rows, static function (array $row): bool {
                return (string) ($row['omission_label'] ?? '') === 'High';
            })),
            'manual_component_count' => count(array_filter($item_rows, static function (array $row): bool {
                return (string) ($row['question_type'] ?? '') === 'essay' || (int) ($row['manual_count'] ?? 0) > 0;
            })),
            'eligible_objective_item_count' => (int) ($question_diagnostics['included_item_count'] ?? 0),
            'objective_profile' => $objective_profile,
            'objective_profile_label' => self::format_objective_profile_label($objective_profile),
            'objective_profile_reason' => (string) ($diagnostics['profile_reason'] ?? ''),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $item_rows
     * @return array<string,mixed>
     */
    private static function build_reliability_question_diagnostics(array $item_rows): array
    {
        $included_question_ids = [];
        $included_types = [];
        $excluded_types = [];
        $excluded_reasons = [];
        $mixed_types = [];
        $dichotomous_item_count = 0;
        $mixed_objective_item_count = 0;
        $excluded_item_count = 0;

        foreach ($item_rows as $item_row) {
            $row = (array) $item_row;
            $classification = self::classify_question_for_reliability($row);
            $question_id = (int) ($row['question_id'] ?? 0);
            $question_type = (string) ($row['question_type'] ?? '');
            $question_type_label = (string) ($row['question_type_label'] ?? self::format_question_type_label($question_type));

            if (!empty($classification['included']) && $question_id > 0) {
                $included_question_ids[$question_id] = $question_id;
            }

            if ((string) ($classification['category'] ?? '') === 'dichotomous') {
                $dichotomous_item_count++;
                self::increment_reliability_type_bucket($included_types, $question_type, $question_type_label);
                continue;
            }

            if ((string) ($classification['category'] ?? '') === 'mixed_objective') {
                $mixed_objective_item_count++;
                self::increment_reliability_type_bucket($included_types, $question_type, $question_type_label);
                self::increment_reliability_type_bucket($mixed_types, $question_type, $question_type_label);
                continue;
            }

            $excluded_item_count++;
            self::increment_reliability_type_bucket(
                $excluded_types,
                $question_type !== '' ? $question_type : 'unknown',
                $question_type_label !== '' ? $question_type_label : 'Unknown',
                (string) ($classification['reason'] ?? '')
            );
            self::increment_reliability_reason_bucket($excluded_reasons, (string) ($classification['reason'] ?? 'Tidak memenuhi syarat reliability.'));
        }

        $included_item_count = $dichotomous_item_count + $mixed_objective_item_count;
        $composition_profile = 'insufficient';
        if ($included_item_count > 0) {
            $composition_profile = $mixed_objective_item_count > 0 ? 'mixed_objective' : 'dichotomous';
        }

        $preferred_method = 'Insufficient Data';
        if ($composition_profile === 'dichotomous') {
            $preferred_method = 'KR-20';
        } elseif ($composition_profile === 'mixed_objective') {
            $preferred_method = "Cronbach's Alpha";
        }

        $mixed_type_labels = self::extract_bucket_labels($mixed_types);
        $profile_reason = 'Belum ada soal objective finalized yang layak masuk reliability.';
        if ($composition_profile === 'dichotomous') {
            $profile_reason = sprintf(
                'Semua %d butir objective yang masuk reliability saat ini masih bertipe benar/salah atau exact-match, jadi profilnya dikotomis.',
                $included_item_count
            );
        } elseif ($composition_profile === 'mixed_objective') {
            $profile_reason = sprintf(
                'Ada %1$d butir objective dengan skor parsial seperti %2$s, jadi komposisi paket ini dibaca sebagai mixed objective.',
                $mixed_objective_item_count,
                self::format_label_list($mixed_type_labels)
            );
        }

        $method_reason = 'Belum ada metode reliability yang bisa dipilih karena item objective finalized yang layak belum tersedia.';
        if ($preferred_method === 'KR-20') {
            $method_reason = 'KR-20 dipakai karena semua butir objective yang eligible masih memakai pola skor benar/salah atau exact-match.';
        } elseif ($preferred_method === "Cronbach's Alpha") {
            $method_reason = sprintf(
                "Cronbach's Alpha dipakai karena ada butir objective dengan skor parsial seperti %s, sehingga KR-20 tidak lagi mewakili paket soal ini dengan baik.",
                self::format_label_list($mixed_type_labels)
            );
        }

        return [
            'included_question_ids' => array_values($included_question_ids),
            'included_item_count' => $included_item_count,
            'dichotomous_item_count' => $dichotomous_item_count,
            'mixed_objective_item_count' => $mixed_objective_item_count,
            'excluded_item_count' => $excluded_item_count,
            'included_types' => self::finalize_reliability_type_buckets($included_types),
            'excluded_types' => self::finalize_reliability_type_buckets($excluded_types),
            'excluded_reasons' => self::finalize_reliability_reason_buckets($excluded_reasons),
            'mixed_types' => self::finalize_reliability_type_buckets($mixed_types),
            'composition_profile' => $composition_profile,
            'preferred_method' => $preferred_method,
            'profile_reason' => $profile_reason,
            'method_reason' => $method_reason,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{category:string,included:bool,reason:string}
     */
    private static function classify_question_for_reliability(array $row): array
    {
        $question_type = (string) ($row['question_type'] ?? '');
        $is_objective = !empty($row['is_objective']) && $question_type !== 'essay';
        if (!$is_objective) {
            return [
                'category' => 'excluded',
                'included' => false,
                'reason' => 'Essay atau komponen manual dinilai terpisah dan tidak ikut reliability.',
            ];
        }

        if ((int) ($row['manual_count'] ?? 0) > 0) {
            return [
                'category' => 'excluded',
                'included' => false,
                'reason' => 'Masih ada review manual, jadi butir ini belum finalized untuk reliability.',
            ];
        }

        switch ($question_type) {
            case 'multiple_choice':
            case 'true_false':
            case 'multiple_answer':
                return [
                    'category' => 'dichotomous',
                    'included' => true,
                    'reason' => '',
                ];
            case 'short_answer':
                return [
                    'category' => !empty($row['is_partial_credit']) ? 'mixed_objective' : 'dichotomous',
                    'included' => true,
                    'reason' => '',
                ];
            case 'true_false_matrix':
                return [
                    'category' => 'mixed_objective',
                    'included' => true,
                    'reason' => '',
                ];
            case 'essay':
                return [
                    'category' => 'excluded',
                    'included' => false,
                    'reason' => 'Essay atau komponen manual dinilai terpisah dan tidak ikut reliability.',
                ];
            default:
                return [
                    'category' => !empty($row['is_partial_credit']) ? 'mixed_objective' : 'dichotomous',
                    'included' => true,
                    'reason' => '',
                ];
        }
    }

    /**
     * @param array<string,array<string,mixed>> $buckets
     */
    private static function increment_reliability_type_bucket(array &$buckets, string $question_type, string $label, string $note = ''): void
    {
        $key = $question_type !== '' ? $question_type : 'unknown';
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'question_type' => $key,
                'label' => $label !== '' ? $label : 'Unknown',
                'count' => 0,
                'notes' => [],
            ];
        }

        $buckets[$key]['count'] = (int) ($buckets[$key]['count'] ?? 0) + 1;
        if ($note !== '') {
            $buckets[$key]['notes'][$note] = $note;
        }
    }

    /**
     * @param array<string,array<string,mixed>> $buckets
     */
    private static function increment_reliability_reason_bucket(array &$buckets, string $reason): void
    {
        $key = $reason !== '' ? $reason : 'Tidak memenuhi syarat reliability.';
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'reason' => $key,
                'count' => 0,
            ];
        }

        $buckets[$key]['count'] = (int) ($buckets[$key]['count'] ?? 0) + 1;
    }

    /**
     * @param array<string,array<string,mixed>> $buckets
     * @return array<int,array<string,mixed>>
     */
    private static function finalize_reliability_type_buckets(array $buckets): array
    {
        $rows = array_values(array_map(static function (array $bucket): array {
            $bucket['notes'] = array_values((array) ($bucket['notes'] ?? []));
            return $bucket;
        }, $buckets));

        usort($rows, static function (array $left, array $right): int {
            $count_compare = (int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0);
            if ($count_compare !== 0) {
                return $count_compare;
            }

            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<string,array<string,mixed>> $buckets
     * @return array<int,array<string,mixed>>
     */
    private static function finalize_reliability_reason_buckets(array $buckets): array
    {
        $rows = array_values($buckets);
        usort($rows, static function (array $left, array $right): int {
            $count_compare = (int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0);
            if ($count_compare !== 0) {
                return $count_compare;
            }

            return strnatcasecmp((string) ($left['reason'] ?? ''), (string) ($right['reason'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $buckets
     * @return array<int,string>
     */
    private static function extract_bucket_labels(array $buckets): array
    {
        return array_values(array_filter(array_map(static function (array $bucket): string {
            return (string) ($bucket['label'] ?? '');
        }, $buckets), static function (string $label): bool {
            return $label !== '';
        }));
    }

    /**
     * @param array<int,string> $labels
     */
    private static function format_label_list(array $labels): string
    {
        $labels = array_values(array_unique(array_filter(array_map('strval', $labels), static function (string $label): bool {
            return trim($label) !== '';
        })));
        if (empty($labels)) {
            return 'soal objective campuran';
        }
        if (count($labels) === 1) {
            return $labels[0];
        }
        if (count($labels) === 2) {
            return $labels[0] . ' dan ' . $labels[1];
        }

        $last = array_pop($labels);
        return implode(', ', $labels) . ', dan ' . $last;
    }

    /**
     * @param array<string,mixed> $question_diagnostics
     * @param array<string,mixed> $reliability
     */
    private static function determine_objective_profile(array $question_diagnostics, array $reliability): string
    {
        $composition_profile = (string) ($question_diagnostics['composition_profile'] ?? 'insufficient');
        if ($composition_profile === 'insufficient') {
            return 'insufficient';
        }

        if ((int) ($question_diagnostics['included_item_count'] ?? 0) < 2) {
            return 'insufficient';
        }

        if (!empty($reliability['reason'])) {
            return 'insufficient';
        }

        return $composition_profile;
    }

    private static function format_objective_profile_label(string $profile): string
    {
        switch ($profile) {
            case 'dichotomous':
                return 'Dikotomis';
            case 'mixed_objective':
                return 'Mixed Objective';
            default:
                return 'Belum Layak Dinilai';
        }
    }

    /**
     * @param array<string,mixed> $question_diagnostics
     * @param array<string,mixed> $reliability
     * @return array<string,mixed>
     */
    private static function build_reliability_diagnostics_payload(array $question_diagnostics, array $reliability): array
    {
        $composition_profile = (string) ($question_diagnostics['composition_profile'] ?? 'insufficient');
        $profile_reason = (string) ($question_diagnostics['profile_reason'] ?? '');
        $method_reason = (string) ($question_diagnostics['method_reason'] ?? '');
        $fallback_reason = (string) ($reliability['reason'] ?? '');

        return [
            'composition_profile' => $composition_profile,
            'composition_profile_label' => self::format_objective_profile_label($composition_profile),
            'profile_reason' => $profile_reason,
            'method' => (string) ($reliability['method'] ?? 'Insufficient Data'),
            'method_reason' => $method_reason,
            'fallback_reason' => $fallback_reason,
            'why_reason' => trim($method_reason . ($fallback_reason !== '' ? ' ' . $fallback_reason : '')),
            'included_types' => array_values((array) ($question_diagnostics['included_types'] ?? [])),
            'excluded_types' => array_values((array) ($question_diagnostics['excluded_types'] ?? [])),
            'excluded_reasons' => array_values((array) ($question_diagnostics['excluded_reasons'] ?? [])),
            'counts' => [
                'included_items' => (int) ($question_diagnostics['included_item_count'] ?? 0),
                'dichotomous_items' => (int) ($question_diagnostics['dichotomous_item_count'] ?? 0),
                'mixed_objective_items' => (int) ($question_diagnostics['mixed_objective_item_count'] ?? 0),
                'excluded_items' => (int) ($question_diagnostics['excluded_item_count'] ?? 0),
                'eligible_attempts' => (int) ($reliability['attempt_count'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $option_analysis_map
     * @return array<int,array<string,mixed>>
     */
    private static function build_distractor_analysis_rows(
        array $option_analysis_map,
        int $seen_count,
        int $upper_group_size,
        int $lower_group_size
    ): array {
        $rows = [];
        foreach ($option_analysis_map as $option_row) {
            $option = (array) $option_row;
            $count = max(0, (int) ($option['count'] ?? 0));
            $selection_rate = $seen_count > 0 ? round(($count / $seen_count) * 100, 2) : 0.0;
            $upper_rate = $upper_group_size > 0 ? round((((int) ($option['upper_count'] ?? 0)) / $upper_group_size) * 100, 2) : 0.0;
            $lower_rate = $lower_group_size > 0 ? round((((int) ($option['lower_count'] ?? 0)) / $lower_group_size) * 100, 2) : 0.0;

            $flags = [];
            $is_correct = !empty($option['is_correct']);
            if ($seen_count > 0 && !$is_correct && $selection_rate < 5.0) {
                $flags[] = 'Non-Functioning Distractor';
            }
            if ($seen_count > 0 && !$is_correct && (int) ($option['lower_count'] ?? 0) > (int) ($option['upper_count'] ?? 0)) {
                $flags[] = 'Attractive Distractor';
            }

            $rows[] = [
                'label' => (string) ($option['label'] ?? '-'),
                'is_correct' => $is_correct ? 1 : 0,
                'count' => $count,
                'selection_rate' => $selection_rate,
                'selection_rate_display' => self::format_percent($selection_rate),
                'upper_rate' => $upper_rate,
                'upper_rate_display' => self::format_percent($upper_rate),
                'lower_rate' => $lower_rate,
                'lower_rate_display' => self::format_percent($lower_rate),
                'flags' => $flags,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $option_analysis_map
     * @return array<int,array<string,mixed>>
     */
    private static function build_multiple_answer_option_analysis_rows(
        array $option_analysis_map,
        int $seen_count,
        int $upper_group_size,
        int $lower_group_size
    ): array {
        $rows = [];
        foreach ($option_analysis_map as $option_row) {
            $option = (array) $option_row;
            $count = max(0, (int) ($option['count'] ?? 0));
            $selection_rate = $seen_count > 0 ? round(($count / $seen_count) * 100, 2) : 0.0;
            $upper_rate = $upper_group_size > 0 ? round((((int) ($option['upper_count'] ?? 0)) / $upper_group_size) * 100, 2) : 0.0;
            $lower_rate = $lower_group_size > 0 ? round((((int) ($option['lower_count'] ?? 0)) / $lower_group_size) * 100, 2) : 0.0;
            $is_correct = !empty($option['is_correct']);

            $rows[] = [
                'label' => (string) ($option['label'] ?? '-'),
                'is_correct' => $is_correct ? 1 : 0,
                'count' => $count,
                'selection_rate' => $selection_rate,
                'selection_rate_display' => self::format_percent($selection_rate),
                'upper_rate' => $upper_rate,
                'upper_rate_display' => self::format_percent($upper_rate),
                'lower_rate' => $lower_rate,
                'lower_rate_display' => self::format_percent($lower_rate),
                'correct_option_selection_rate' => $is_correct ? $selection_rate : 0.0,
                'wrong_option_selection_rate' => !$is_correct ? $selection_rate : 0.0,
                'missed_correct_option_rate' => $is_correct ? round(max(0.0, 100.0 - $selection_rate), 2) : 0.0,
                'false_selection_rate' => !$is_correct ? $selection_rate : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $matrix_rows
     * @return array<int,array<string,mixed>>
     */
    private static function build_matrix_analysis_rows(array $matrix_rows, int $seen_count): array
    {
        $rows = [];
        foreach ($matrix_rows as $matrix_row) {
            $row = (array) $matrix_row;
            $correct_rate = $seen_count > 0 ? round((((int) ($row['correct_count'] ?? 0)) / $seen_count) * 100, 2) : 0.0;
            $wrong_rate = $seen_count > 0 ? round((((int) ($row['wrong_count'] ?? 0)) / $seen_count) * 100, 2) : 0.0;
            $omission_rate = $seen_count > 0 ? round((((int) ($row['unanswered_count'] ?? 0)) / $seen_count) * 100, 2) : 0.0;

            $rows[] = [
                'statement_number' => (int) ($row['statement_number'] ?? 0),
                'statement_text' => (string) ($row['statement_text'] ?? ''),
                'correct_answer' => (string) ($row['correct_answer'] ?? '-'),
                'correct_count' => (int) ($row['correct_count'] ?? 0),
                'wrong_count' => (int) ($row['wrong_count'] ?? 0),
                'unanswered_count' => (int) ($row['unanswered_count'] ?? 0),
                'correct_rate_display' => self::format_percent($correct_rate),
                'wrong_rate_display' => self::format_percent($wrong_rate),
                'omission_rate_display' => self::format_percent($omission_rate),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{label:string,count:int}> $wrong_clusters
     * @return array<string,mixed>
     */
    private static function build_short_answer_analysis(
        int $total_inputs,
        int $matched_inputs,
        array $wrong_clusters
    ): array {
        $accepted_match_rate = $total_inputs > 0 ? round(($matched_inputs / $total_inputs) * 100, 2) : 0.0;
        uasort($wrong_clusters, static function (array $left, array $right): int {
            $countCompare = (int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        $top_wrong = [];
        foreach (array_slice($wrong_clusters, 0, 5, true) as $cluster) {
            $cluster_row = (array) $cluster;
            $top_wrong[] = [
                'label' => (string) ($cluster_row['label'] ?? '-'),
                'count' => (int) ($cluster_row['count'] ?? 0),
            ];
        }

        return [
            'accepted_match_rate' => $accepted_match_rate,
            'accepted_match_rate_display' => self::format_percent($accepted_match_rate),
            'top_wrong_responses' => $top_wrong,
        ];
    }

    /**
     * @param array<int,float> $score_values
     * @return array<string,mixed>
     */
    private static function build_essay_analysis(
        int $answered_count,
        int $manual_count,
        array $score_values,
        int $seen_count
    ): array {
        $submission_rate = $seen_count > 0 ? round(($answered_count / $seen_count) * 100, 2) : 0.0;
        $average_score = !empty($score_values) ? round(array_sum($score_values) / count($score_values), 2) : 0.0;
        $min_score = !empty($score_values) ? min($score_values) : 0.0;
        $max_score = !empty($score_values) ? max($score_values) : 0.0;

        return [
            'submission_rate' => $submission_rate,
            'submission_rate_display' => self::format_percent($submission_rate),
            'pending_manual_review' => $manual_count,
            'average_awarded_score' => $average_score,
            'average_awarded_score_display' => self::format_number($average_score),
            'score_spread_display' => self::format_number((float) $min_score) . ' - ' . self::format_number((float) $max_score),
        ];
    }

    /**
     * @param array<string,mixed> $discrimination
     * @param array<string,mixed> $omission
     * @param array<int,array<string,mixed>> $option_analysis
     * @return array{label:string,tone:string}
     */
    private static function build_item_insight_meta(
        string $question_type,
        int $seen_count,
        int $manual_count,
        array $discrimination,
        array $omission,
        array $option_analysis
    ): array {
        if ($seen_count <= 0 || (string) ($discrimination['label'] ?? '') === 'Insufficient Data') {
            return [
                'label' => 'Insufficient Data',
                'tone' => 'neutral',
            ];
        }

        if ($question_type === 'essay' || $manual_count > 0) {
            return [
                'label' => 'Pending Manual Review',
                'tone' => 'warning',
            ];
        }

        $discrimination_label = (string) ($discrimination['label'] ?? '');
        if ($discrimination_label === 'Inverse') {
            return [
                'label' => 'Inverse Discrimination',
                'tone' => 'fail',
            ];
        }
        if ($discrimination_label === 'Weak') {
            return [
                'label' => 'Weak Discrimination',
                'tone' => 'warning',
            ];
        }
        if ((string) ($omission['label'] ?? '') === 'High') {
            return [
                'label' => 'High Omission',
                'tone' => 'warning',
            ];
        }

        foreach ($option_analysis as $option_row) {
            $option = (array) $option_row;
            $flags = array_values((array) ($option['flags'] ?? []));
            if (in_array('Attractive Distractor', $flags, true)) {
                return [
                    'label' => 'Attractive Distractor',
                    'tone' => 'warning',
                ];
            }
            if (in_array('Non-Functioning Distractor', $flags, true)) {
                return [
                    'label' => 'Distractor Issue',
                    'tone' => 'warning',
                ];
            }
        }

        return [
            'label' => 'Stable',
            'tone' => 'ok',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $discrimination
     * @param array<int,array<string,mixed>> $option_analysis
     * @return array{diagnostic_tags:array<int,array<string,string>>,cognitive_alerts:array<int,array<string,string>>}
     */
    private static function build_item_diagnostic_payload(
        array $row,
        int $completed_attempts,
        float $correct_rate,
        array $discrimination,
        array $option_analysis
    ): array {
        if ($completed_attempts < 10 || empty($row['is_objective']) || (string) ($row['question_type'] ?? '') === 'essay') {
            return [
                'diagnostic_tags' => [],
                'cognitive_alerts' => [],
            ];
        }

        $tags = [];
        $discrimination_value = $discrimination['value'] ?? null;
        if (is_numeric($discrimination_value) && (float) $discrimination_value < 0.0) {
            $tags[] = [
                'key' => 'suspect_key',
                'label' => 'Kunci Jawaban Meragukan',
                'tone' => 'fail',
                'message' => 'Daya beda negatif; kelompok atas justru lebih rendah daripada kelompok bawah.',
            ];
        }

        $failed_distractors = self::collect_failed_distractor_labels($option_analysis);
        if (!empty($failed_distractors)) {
            $tags[] = [
                'key' => 'failed_distractor',
                'label' => 'Pengecoh Gagal',
                'tone' => 'warning',
                'message' => 'Opsi salah tidak dipilih peserta: ' . implode(', ', $failed_distractors) . '.',
            ];
        }

        if ($correct_rate >= 40.0 && $correct_rate <= 60.0 && is_numeric($discrimination_value) && (float) $discrimination_value > 0.4) {
            $tags[] = [
                'key' => 'anchor_item',
                'label' => 'Soal Berkualitas',
                'tone' => 'pass',
                'message' => 'Kesukaran moderat dan daya beda tinggi; kandidat Bank Soal Emas.',
            ];
        }

        return [
            'diagnostic_tags' => $tags,
            'cognitive_alerts' => self::build_cognitive_trap_alerts((string) ($row['question_type'] ?? ''), $option_analysis),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $option_analysis
     * @return list<string>
     */
    private static function collect_failed_distractor_labels(array $option_analysis): array
    {
        $labels = [];
        foreach ($option_analysis as $option_row) {
            $option = (array) $option_row;
            if (!empty($option['is_correct'])) {
                continue;
            }
            if ((float) ($option['selection_rate'] ?? 0.0) <= 0.0) {
                $labels[] = (string) ($option['label'] ?? '-');
            }
        }

        return array_slice(array_values(array_unique(array_filter($labels, static function (string $label): bool {
            return trim($label) !== '';
        }))), 0, 4);
    }

    /**
     * @param array<int,array<string,mixed>> $option_analysis
     * @return array<int,array<string,string>>
     */
    private static function build_cognitive_trap_alerts(string $question_type, array $option_analysis): array
    {
        if (!in_array($question_type, ['multiple_choice', 'true_false'], true)) {
            return [];
        }

        $correct_option = null;
        foreach ($option_analysis as $option_row) {
            $option = (array) $option_row;
            if (!empty($option['is_correct'])) {
                $correct_option = $option;
                break;
            }
        }

        if (!is_array($correct_option)) {
            return [];
        }

        $correct_upper_rate = (float) ($correct_option['upper_rate'] ?? 0.0);
        $alerts = [];
        foreach ($option_analysis as $option_row) {
            $option = (array) $option_row;
            if (!empty($option['is_correct'])) {
                continue;
            }

            $upper_rate = (float) ($option['upper_rate'] ?? 0.0);
            if ($upper_rate > $correct_upper_rate && $upper_rate > 0.0) {
                $label = (string) ($option['label'] ?? '-');
                $alerts[] = [
                    'key' => 'cognitive_trap',
                    'label' => 'Trap Alert',
                    'tone' => 'warning',
                    'message' => sprintf(
                        'Perhatian: kelompok atas lebih sering memilih opsi %s daripada kunci. Kemungkinan ada ambiguitas redaksi atau opsi.',
                        $label
                    ),
                ];
            }
        }

        return array_slice($alerts, 0, 3);
    }

    /**
     * @param array<int,array<string,mixed>> $option_analysis
     * @return array<int,array<string,mixed>>
     */
    private static function decorate_item_option_analysis_display(array $option_analysis): array
    {
        foreach ($option_analysis as $index => $option_row) {
            $row = (array) $option_row;
            $flags = array_values(array_filter(array_map('strval', (array) ($row['flags'] ?? []))));
            $row['flags_display'] = array_values(array_map([self::class, 'localize_option_analysis_flag'], $flags));
            $option_analysis[$index] = $row;
        }

        return $option_analysis;
    }

    private static function build_item_insight_key(string $label): string
    {
        $normalized = strtolower(trim($label));
        switch ($normalized) {
            case 'insufficient data':
                return 'insufficient_data';
            case 'pending manual review':
                return 'pending_manual_review';
            case 'inverse discrimination':
                return 'inverse_discrimination';
            case 'weak discrimination':
                return 'weak_discrimination';
            case 'high omission':
                return 'high_omission';
            case 'attractive distractor':
                return 'attractive_distractor';
            case 'distractor issue':
                return 'distractor_issue';
            default:
                if ($normalized === '') {
                    return 'stable';
                }

                $fallback = preg_replace('/[^a-z0-9]+/', '_', $normalized);

                return is_string($fallback) && $fallback !== '' ? trim($fallback, '_') : 'stable';
        }
    }

    private static function localize_item_insight_label(string $label): string
    {
        switch (trim($label)) {
            case 'Insufficient Data':
                return 'Data Belum Cukup';
            case 'Pending Manual Review':
                return 'Menunggu Koreksi Manual';
            case 'Inverse Discrimination':
                return 'Daya Beda Terbalik';
            case 'Weak Discrimination':
                return 'Daya Beda Lemah';
            case 'High Omission':
                return 'Sering Dikosongkan';
            case 'Attractive Distractor':
                return 'Distraktor Menarik';
            case 'Distractor Issue':
                return 'Distraktor Bermasalah';
            case 'Stable':
                return 'Stabil';
            default:
                return trim($label) !== '' ? trim($label) : 'Stabil';
        }
    }

    private static function localize_option_analysis_flag(string $flag): string
    {
        switch (trim($flag)) {
            case 'Attractive Distractor':
                return 'Distraktor Menarik';
            case 'Non-Functioning Distractor':
                return 'Distraktor Tidak Berfungsi';
            default:
                return trim($flag) !== '' ? trim($flag) : '-';
        }
    }

    private static function build_item_insight_short_explainer(string $label): string
    {
        switch (trim($label)) {
            case 'Insufficient Data':
                return 'Belum cukup data final untuk menyimpulkan kondisi utama soal.';
            case 'Pending Manual Review':
                return 'Soal ini masih menunggu koreksi guru sebelum insight-nya final.';
            case 'Inverse Discrimination':
                return 'Kelompok bawah justru tampil lebih baik daripada kelompok atas pada butir ini.';
            case 'Weak Discrimination':
                return 'Soal belum cukup kuat membedakan peserta kuat dan lemah.';
            case 'High Omission':
                return 'Cukup banyak peserta melewati soal ini tanpa jawaban.';
            case 'Attractive Distractor':
                return 'Ada opsi salah yang efektif menarik peserta yang lebih lemah.';
            case 'Distractor Issue':
                return 'Ada distraktor yang kurang berfungsi sehingga butir perlu ditinjau.';
            default:
                return 'Tidak ada sinyal utama yang dominan pada butir ini.';
        }
    }

    /**
     * @param array<string,mixed> $discrimination
     * @param array<string,mixed> $omission
     * @param array<int,array<string,mixed>> $option_analysis
     */
    private static function build_item_insight_reason_detail(
        string $label,
        string $question_type,
        int $seen_count,
        int $manual_count,
        array $discrimination,
        array $omission,
        array $option_analysis
    ): string {
        switch (trim($label)) {
            case 'Insufficient Data':
                if ($seen_count <= 0) {
                    return 'Belum ada peserta final yang cukup untuk membaca butir ini.';
                }
                if ($question_type === 'essay' && $manual_count <= 0) {
                    return 'Butir essay dibaca melalui koreksi manual sehingga insight statistik objective tidak menjadi acuan utamanya.';
                }
                if ($manual_count > 0) {
                    return sprintf(
                        'Masih ada %d jawaban manual/essay yang belum final sehingga insight belum stabil.',
                        $manual_count
                    );
                }

                return 'Data peserta final yang layak untuk menghitung daya beda butir ini belum cukup.';
            case 'Pending Manual Review':
                return sprintf(
                    'Masih ada %d jawaban yang perlu koreksi manual sebelum kondisi soal bisa dibaca final.',
                    max(1, $manual_count)
                );
            case 'Inverse Discrimination':
                return sprintf(
                    'Nilai discrimination %s menunjukkan kelompok bawah tampil lebih baik daripada kelompok atas.',
                    (string) ($discrimination['display'] ?? '0.00')
                );
            case 'Weak Discrimination':
                return sprintf(
                    'Nilai discrimination %s masih lemah sehingga butir ini belum cukup membedakan peserta.',
                    (string) ($discrimination['display'] ?? '0.00')
                );
            case 'High Omission':
                return sprintf(
                    'Omission rate %s menunjukkan cukup banyak peserta membiarkan butir ini kosong.',
                    self::format_percent((float) ($omission['value'] ?? 0.0))
                );
            case 'Attractive Distractor':
                $labels = self::collect_item_option_labels_by_flag($option_analysis, 'Attractive Distractor');
                if (!empty($labels)) {
                    return sprintf(
                        'Flag distraktor menarik muncul pada opsi %s sehingga pengecoh terasa sangat meyakinkan bagi peserta yang lebih lemah.',
                        implode(', ', $labels)
                    );
                }

                return 'Ada distraktor yang lebih sering menarik kelompok bawah daripada kelompok atas.';
            case 'Distractor Issue':
                $labels = self::collect_item_option_labels_by_flag($option_analysis, 'Non-Functioning Distractor');
                if (!empty($labels)) {
                    return sprintf(
                        'Ada distraktor yang jarang dipilih sehingga kurang berfungsi; terlihat pada opsi %s.',
                        implode(', ', $labels)
                    );
                }

                return 'Ada distraktor yang kurang berfungsi sehingga kualitas pengecohnya perlu ditinjau.';
            default:
                return sprintf(
                    'Nilai discrimination %s, omission %s, dan analisis opsi tidak menunjukkan sinyal utama yang dominan.',
                    (string) ($discrimination['display'] ?? '0.00'),
                    self::format_percent((float) ($omission['value'] ?? 0.0))
                );
        }
    }

    private static function build_item_insight_next_step(string $label): string
    {
        switch (trim($label)) {
            case 'Insufficient Data':
                return 'Tunggu lebih banyak respons final atau selesaikan koreksi manual sebelum menarik kesimpulan.';
            case 'Pending Manual Review':
                return 'Selesaikan koreksi manual terlebih dahulu agar insight butir ini benar-benar final.';
            case 'Inverse Discrimination':
                return 'Periksa kembali kunci jawaban, redaksi stem, dan kesesuaian opsi karena daya beda terbalik.';
            case 'Weak Discrimination':
                return 'Tinjau ulang stem, tingkat kesulitan, dan kualitas opsi agar daya beda naik.';
            case 'High Omission':
                return 'Periksa apakah soal terlalu panjang, ambigu, atau petunjuknya kurang jelas.';
            case 'Attractive Distractor':
                return 'Cek apakah distraktor terlalu mirip dengan kunci atau stem belum cukup tegas.';
            case 'Distractor Issue':
                return 'Perbaiki distraktor agar lebih masuk akal dan benar-benar dipertimbangkan peserta.';
            default:
                return 'Butir ini relatif sehat; lanjutkan pemantauan bersama butir lain di paket exam.';
        }
    }

    private static function build_item_difficulty_short_explainer(string $label): string
    {
        switch (trim($label)) {
            case 'Pending Manual Review':
                return 'Tingkat kesulitan ditahan karena masih ada koreksi manual yang belum final.';
            case 'Mudah':
                return 'Mayoritas peserta menjawab butir ini dengan benar.';
            case 'Sedang':
                return 'Butir ini berada di rentang tengah dan masih cukup seimbang antara benar dan salah.';
            default:
                return 'Peserta yang menjawab benar masih relatif sedikit pada butir ini.';
        }
    }

    /**
     * @param array<int,array<string,mixed>> $option_analysis
     * @return list<string>
     */
    private static function collect_item_option_labels_by_flag(array $option_analysis, string $flag): array
    {
        $labels = [];
        foreach ($option_analysis as $option_row) {
            $row = (array) $option_row;
            $flags = array_values(array_filter(array_map('strval', (array) ($row['flags'] ?? []))));
            if (in_array($flag, $flags, true)) {
                $labels[] = (string) ($row['label'] ?? '-');
            }
        }

        $labels = array_values(array_unique(array_filter($labels, static function ($label): bool {
            return is_string($label) && trim($label) !== '';
        })));

        return array_slice($labels, 0, 3);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,array<string,mixed>> $option_analysis
     */
    private static function build_item_search_text(array $row, array $option_analysis = []): string
    {
        $terms = [
            (string) ($row['question_number'] ?? ''),
            (string) ($row['question_type_label'] ?? ''),
            (string) ($row['question_preview'] ?? ''),
            (string) ($row['difficulty_label'] ?? ''),
            (string) ($row['difficulty_short_explainer'] ?? ''),
            (string) ($row['discrimination_label'] ?? ''),
            (string) ($row['insight_label'] ?? ''),
            (string) ($row['insight_display_label'] ?? ''),
            (string) ($row['insight_short_explainer'] ?? ''),
            (string) ($row['omission_label'] ?? ''),
        ];

        foreach ((array) ($row['diagnostic_tags'] ?? []) as $tag_row) {
            $tag = (array) $tag_row;
            $terms[] = (string) ($tag['key'] ?? '');
            $terms[] = (string) ($tag['label'] ?? '');
            $terms[] = (string) ($tag['message'] ?? '');
        }

        foreach ((array) ($row['cognitive_alerts'] ?? []) as $alert_row) {
            $alert = (array) $alert_row;
            $terms[] = (string) ($alert['key'] ?? '');
            $terms[] = (string) ($alert['label'] ?? '');
            $terms[] = (string) ($alert['message'] ?? '');
        }

        foreach ($option_analysis as $option_row) {
            $option = (array) $option_row;
            $terms[] = (string) ($option['label'] ?? '');
            foreach ((array) ($option['flags'] ?? []) as $flag) {
                $terms[] = (string) $flag;
            }
            foreach ((array) ($option['flags_display'] ?? []) as $flag) {
                $terms[] = (string) $flag;
            }
        }

        return strtolower(trim(implode(' ', array_values(array_filter(array_map(static function ($value): string {
            return is_scalar($value) ? trim((string) $value) : '';
        }, $terms))))));
    }

    /**
     * @return array{value:?float,label:string,tone:string,display:string}
     */
    private static function build_discrimination_meta(?float $value, int $completed_attempts, int $manual_count): array
    {
        if ($manual_count > 0 || $value === null || $completed_attempts < 5) {
            return [
                'value' => null,
                'label' => 'Insufficient Data',
                'tone' => 'neutral',
                'display' => 'Insufficient Data',
            ];
        }

        $label = 'Weak';
        $tone = 'warning';
        if ($value >= 0.40) {
            $label = 'Excellent';
            $tone = 'pass';
        } elseif ($value >= 0.30) {
            $label = 'Good';
            $tone = 'ok';
        } elseif ($value >= 0.20) {
            $label = 'Fair';
            $tone = 'warning';
        } elseif ($value < 0.0) {
            $label = 'Inverse';
            $tone = 'fail';
        }

        return [
            'value' => round($value, 4),
            'label' => $label,
            'tone' => $tone,
            'display' => self::format_number(round($value, 4)),
        ];
    }

    /**
     * @return array{value:float,label:string,tone:string}
     */
    private static function build_omission_meta(float $value): array
    {
        if ($value >= 20.0) {
            return ['value' => $value, 'label' => 'High', 'tone' => 'fail'];
        }
        if ($value >= 10.0) {
            return ['value' => $value, 'label' => 'Medium', 'tone' => 'warning'];
        }

        return ['value' => $value, 'label' => 'Low', 'tone' => 'ok'];
    }

    private static function calculate_item_score_ratio(float $scoreAwarded, float $itemPoints): float
    {
        if (!is_finite($itemPoints) || $itemPoints <= 0.0) {
            return 0.0;
        }

        return round(min(1.0, max(0.0, $scoreAwarded / $itemPoints)), 4);
    }

    /**
     * @param array<int,array<int,float>> $question_ratios
     * @return array{value:?float,method:string,reason:string,item_count:int,attempt_count:int}
     */
    private static function calculate_exam_reliability(array $question_ratios, string $preferred_method = 'Insufficient Data'): array
    {
        $method = $preferred_method !== '' ? $preferred_method : 'Insufficient Data';
        if (empty($question_ratios)) {
            return [
                'value' => null,
                'method' => $method,
                'reason' => 'Belum ada butir objective finalized yang cukup untuk menghitung reliability.',
                'item_count' => 0,
                'attempt_count' => 0,
            ];
        }

        $question_ids = array_keys($question_ratios);
        $question_ids = array_values(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        }));
        $item_count = count($question_ids);

        $attempt_ids = [];
        foreach ($question_ratios as $ratio_map) {
            foreach ((array) $ratio_map as $attempt_id => $ratio) {
                $attempt_id = (int) $attempt_id;
                if ($attempt_id > 0) {
                    $attempt_ids[$attempt_id] = $attempt_id;
                }
            }
        }
        $attempt_ids = array_values($attempt_ids);
        sort($attempt_ids);
        if ($item_count < 2) {
            return [
                'value' => null,
                'method' => $method,
                'reason' => 'Minimal dua butir objective finalized diperlukan agar hubungan antarsoal bisa dibaca.',
                'item_count' => $item_count,
                'attempt_count' => count($attempt_ids),
            ];
        }
        if (count($attempt_ids) < 5) {
            return [
                'value' => null,
                'method' => $method,
                'reason' => 'Minimal lima peserta selesai diperlukan agar reliability tidak terlalu rapuh.',
                'item_count' => $item_count,
                'attempt_count' => count($attempt_ids),
            ];
        }

        $item_variances = [];
        $total_scores = [];

        foreach ($attempt_ids as $attempt_id) {
            $total_scores[$attempt_id] = 0.0;
        }

        foreach ($question_ids as $question_id) {
            $values = [];
            foreach ($attempt_ids as $attempt_id) {
                $value = isset($question_ratios[$question_id][$attempt_id]) ? (float) $question_ratios[$question_id][$attempt_id] : 0.0;
                $values[] = $value;
                $total_scores[$attempt_id] += $value;
            }
            $item_variances[] = self::calculate_variance($values);
        }

        $total_score_values = array_values($total_scores);
        $total_variance = self::calculate_variance($total_score_values);
        if ($total_variance === null || $total_variance <= 0.0) {
            return [
                'value' => null,
                'method' => $method,
                'reason' => 'Sebaran total skor objective masih terlalu datar, jadi reliability belum bisa dihitung dengan aman.',
                'item_count' => $item_count,
                'attempt_count' => count($attempt_ids),
            ];
        }

        if ($item_count < 2) {
            return [
                'value' => null,
                'method' => $method,
                'reason' => 'Jumlah item objective finalized belum cukup.',
                'item_count' => $item_count,
                'attempt_count' => count($attempt_ids),
            ];
        }

        $sum_item_variance = 0.0;
        foreach ($item_variances as $variance) {
            $sum_item_variance += max(0.0, (float) $variance);
        }

        $alpha = ($item_count / ($item_count - 1)) * (1 - ($sum_item_variance / $total_variance));
        $alpha = round(min(1.0, max(-1.0, $alpha)), 4);

        return [
            'value' => $alpha,
            'method' => $method,
            'reason' => '',
            'item_count' => $item_count,
            'attempt_count' => count($attempt_ids),
        ];
    }

    /**
     * @param array<int|float> $values
     */
    private static function calculate_variance(array $values): ?float
    {
        $values = array_values(array_filter(array_map('floatval', $values), static function (float $value): bool {
            return is_finite($value);
        }));
        $count = count($values);
        if ($count < 2) {
            return null;
        }

        $mean = array_sum($values) / $count;
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += (($value - $mean) ** 2);
        }

        return round($sum / ($count - 1), 4);
    }

    /**
     * @param array<int|float> $values
     */
    private static function calculate_standard_deviation(array $values): ?float
    {
        $variance = self::calculate_variance($values);
        if ($variance === null || $variance < 0.0) {
            return null;
        }

        return round(sqrt($variance), 4);
    }

    /**
     * @param array<string,mixed> $selected_exam
     * @return array<int,array<string,mixed>>
     */
    private static function get_student_drilldown_rows(
        array $selected_exam,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id,
        array $statistical_payload = []
    ): array {
        if (empty($statistical_payload)) {
            $statistical_payload = self::get_exam_statistical_payload($selected_exam, $selected_kelas, $is_admin_scope, $current_user_id);
        }

        $attempt_rows = (array) ($statistical_payload['attempt_rows'] ?? []);
        $progress_map = (array) ($statistical_payload['progress_map'] ?? []);
        $objective_attempt_metrics = (array) (($statistical_payload['objective_attempt_metrics']['attempts'] ?? []) ?: []);
        $kkm_percentage = self::normalize_kkm_percentage((float) ($selected_exam['kkm_percentage'] ?? 75.0));

        $rows = [];
        foreach ($attempt_rows as $attempt_row) {
            $attempt_id = (int) ($attempt_row['id'] ?? 0);
            $progress_sections = (array) ($progress_map[$attempt_id] ?? []);
            $active_items = (array) ($progress_sections['active_items'] ?? []);
            $archived_items = (array) ($progress_sections['archived_items'] ?? []);
            $progress_items = array_merge($active_items, $archived_items);

            $review_summary = self::summarize_progress_items($progress_items);
            $max_score = max(0.0, (float) ($attempt_row['max_score'] ?? 0.0));
            $score = max(0.0, (float) ($attempt_row['score'] ?? 0.0));
            $passing_score = self::calculate_passing_score($max_score, $kkm_percentage);
            $is_passed = $max_score > 0 ? ($score + 0.0001 >= $passing_score) : ($kkm_percentage <= 0.0);
            $objective_metric = (array) ($objective_attempt_metrics[$attempt_id] ?? []);

            $student_query = trim((string) ($attempt_row['student_nisn'] ?? ''));
            if ($student_query === '') {
                $student_query = trim((string) ($attempt_row['student_username'] ?? ''));
            }

            $rows[] = [
                'attempt_id' => $attempt_id,
                'student_name' => (string) ($attempt_row['student_name'] ?? '-'),
                'student_username' => (string) ($attempt_row['student_username'] ?? ''),
                'student_nisn' => (string) ($attempt_row['student_nisn'] ?? ''),
                'student_kelas' => (string) (($attempt_row['student_kelas'] ?? '') !== '' ? $attempt_row['student_kelas'] : '-'),
                'score' => $score,
                'score_display' => self::format_number($score),
                'max_score' => $max_score,
                'max_score_display' => self::format_number($max_score),
                'percentage' => (float) ($attempt_row['percentage'] ?? 0.0),
                'percentage_display' => self::format_percent((float) ($attempt_row['percentage'] ?? 0.0)),
                'objective_percentage' => (float) ($objective_metric['objective_percentage'] ?? 0.0),
                'objective_percentage_display' => (string) ($objective_metric['objective_percentage_display'] ?? '0.00%'),
                'group_band' => (string) ($objective_metric['group_band'] ?? 'middle'),
                'group_band_label' => self::format_group_band_label((string) ($objective_metric['group_band'] ?? 'middle')),
                'is_passed' => $is_passed ? 1 : 0,
                'pass_label' => $is_passed ? 'Lulus' : 'Tidak Lulus',
                'pass_tone' => $is_passed ? 'pass' : 'fail',
                'answered_summary' => sprintf(
                    '%d / %d',
                    (int) ($review_summary['answered_questions'] ?? 0),
                    (int) ($review_summary['total_questions'] ?? 0)
                ),
                'duration_label' => self::format_duration((int) ($attempt_row['duration_seconds'] ?? 0)),
                'finished_at_label' => self::format_datetime((string) ($attempt_row['finished_at'] ?? '')),
                'detail' => [
                    'kkm_percentage_display' => self::format_number($kkm_percentage),
                    'passing_score_display' => self::format_number($passing_score),
                    'review_summary' => $review_summary,
                    'archived_count' => count($archived_items),
                    'objective_percentage_display' => (string) ($objective_metric['objective_percentage_display'] ?? '0.00%'),
                    'group_band_label' => self::format_group_band_label((string) ($objective_metric['group_band'] ?? 'middle')),
                    'results_url' => self::build_results_url((int) ($selected_exam['id'] ?? 0), $student_query),
                ],
                'search_text' => strtolower(implode(' ', [
                    (string) ($attempt_row['student_name'] ?? ''),
                    (string) ($attempt_row['student_username'] ?? ''),
                    (string) ($attempt_row['student_nisn'] ?? ''),
                    (string) ($attempt_row['student_kelas'] ?? ''),
                    (string) ($objective_metric['group_band'] ?? 'middle'),
                    $is_passed ? 'lulus' : 'tidak lulus',
                ])),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $kelas_compare = strnatcasecmp((string) ($left['student_kelas'] ?? ''), (string) ($right['student_kelas'] ?? ''));
            if ($kelas_compare !== 0) {
                return $kelas_compare;
            }

            return strnatcasecmp((string) ($left['student_name'] ?? ''), (string) ($right['student_name'] ?? ''));
        });

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $selected_exam
     * @return array<string,mixed>
     */
    private static function get_exam_progress_dataset(
        array $selected_exam,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        $exam_id = (int) ($selected_exam['id'] ?? 0);
        if ($exam_id <= 0) {
            return [
                'attempt_rows' => [],
                'progress_map' => [],
                'question_meta' => [],
            ];
        }

        $cache_key = 'admin_analytics_progress_' . self::CACHE_VERSION . '_' . md5((string) wp_json_encode([
            'exam_id' => $exam_id,
            'kelas' => $selected_kelas,
            'scope' => $is_admin_scope ? 'admin' : 'teacher',
            'user_id' => $is_admin_scope ? 0 : $current_user_id,
        ]));

        return CBT_Cache::remember(
            $cache_key,
            self::CACHE_TTL,
            [
                CBT_Cache::namespace_analytics(),
                CBT_Cache::namespace_analytics_exam($exam_id),
                CBT_Cache::namespace_exam($exam_id),
            ],
            static function () use ($exam_id, $selected_kelas, $is_admin_scope, $current_user_id): array {
                global $wpdb;

                $attempt_rows = self::get_completed_attempt_metric_rows(0, $exam_id, $selected_kelas, $is_admin_scope, $current_user_id);
                if (empty($attempt_rows)) {
                    return [
                        'attempt_rows' => [],
                        'progress_map' => [],
                        'question_meta' => self::get_exam_question_metadata($exam_id),
                    ];
                }

                $question_table = $wpdb->prefix . 'cbt_questions';
                $answer_table = $wpdb->prefix . 'cbt_answers';
                $option_table = $wpdb->prefix . 'cbt_options';

                return [
                    'attempt_rows' => $attempt_rows,
                    'progress_map' => CBT_Admin_Results_Helper::build_attempt_answer_progress_map(
                        $attempt_rows,
                        $question_table,
                        $answer_table,
                        $option_table
                    ),
                    'question_meta' => self::get_exam_question_metadata($exam_id),
                ];
            }
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_completed_attempt_metric_rows(
        int $selected_subject_id,
        int $selected_exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $subject_table = $wpdb->prefix . 'cbt_subjects';

        $where_parts = ["a.status = 'completed'"];
        $params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $current_user_id;
        }
        if ($selected_subject_id > 0) {
            $where_parts[] = 'e.subject_id = %d';
            $params[] = $selected_subject_id;
        }
        if ($selected_exam_id > 0) {
            $where_parts[] = 'a.exam_id = %d';
            $params[] = $selected_exam_id;
        }
        if ($selected_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $params[] = $selected_kelas;
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $sql = "SELECT a.id,
                       a.exam_id,
                       a.student_id,
                       a.question_order,
                       a.option_order,
                       a.status,
                       a.score,
                       a.max_score,
                       a.finished_at,
                       a.duration_seconds,
                       e.title AS exam_title,
                       e.subject_id,
                       e.kkm_percentage,
                       COALESCE(s.name, '') AS subject_name,
                       u.display_name AS student_name,
                       u.user_login AS student_username,
                       COALESCE(kelas_meta.meta_value, '') AS student_kelas,
                       COALESCE(nisn_meta.meta_value, '') AS student_nisn,
                       COALESCE(qtotal.total_points, 0) AS exam_total_points
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                LEFT JOIN {$subject_table} s ON s.id = e.subject_id
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
                LEFT JOIN (
                    SELECT exam_id, COALESCE(SUM(points), 0) AS total_points
                    FROM {$question_table}
                    WHERE COALESCE(is_active, 1) = 1
                    GROUP BY exam_id
                ) qtotal ON qtotal.exam_id = a.exam_id
                {$where_sql}
                ORDER BY COALESCE(kelas_meta.meta_value, '') ASC, u.display_name ASC, a.id DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $score = max(0.0, (float) ($row['score'] ?? 0.0));
            $max_score = max(0.0, (float) ($row['max_score'] ?? 0.0));
            if ($max_score <= 0) {
                $max_score = max(0.0, (float) ($row['exam_total_points'] ?? 0.0));
            }
            $kkm_percentage = self::normalize_kkm_percentage((float) ($row['kkm_percentage'] ?? 75.0));
            $percentage = $max_score > 0 ? round(($score / $max_score) * 100, 2) : 0.0;
            $passing_score = self::calculate_passing_score($max_score, $kkm_percentage);
            $is_passed = $max_score > 0 ? ($score + 0.0001 >= $passing_score) : ($kkm_percentage <= 0.0);

            $student_name = trim((string) ($row['student_name'] ?? ''));
            if ($student_name === '') {
                $student_name = (string) ($row['student_username'] ?? '-');
            }

            $row['score'] = $score;
            $row['max_score'] = $max_score;
            $row['kkm_percentage'] = $kkm_percentage;
            $row['percentage'] = $percentage;
            $row['passing_score'] = $passing_score;
            $row['is_passed'] = $is_passed ? 1 : 0;
            $row['pass_label'] = $is_passed ? 'Lulus' : 'Tidak Lulus';
            $row['student_name'] = $student_name;
        }
        unset($row);

        return $rows;
    }

    private static function count_in_progress_attempts(
        int $exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): int {
        if ($exam_id <= 0) {
            return 0;
        }

        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where_parts = ["a.status = 'in_progress'", 'a.exam_id = %d'];
        $params = [$exam_id];

        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $current_user_id;
        }
        if ($selected_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $params[] = $selected_kelas;
        }

        $join_kelas = '';
        if ($selected_kelas !== '') {
            $join_kelas = " INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'kode_kelas'
                    GROUP BY user_id
                ) kelas_meta ON kelas_meta.user_id = u.ID";
        }

        $sql = "SELECT COUNT(*)
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                {$join_kelas}
                WHERE " . implode(' AND ', $where_parts);

        return max(0, (int) $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /**
     * @return array{total:int,by_exam:array<int,int>,by_class:array<string,int>,by_exam_class:array<int,array<string,int>>}
     */
    private static function get_manual_review_counts(
        int $selected_subject_id,
        int $selected_exam_id,
        string $selected_kelas,
        bool $is_admin_scope,
        int $current_user_id
    ): array {
        global $wpdb;

        $answer_table = $wpdb->prefix . 'cbt_answers';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';

        $where_parts = ["att.status = 'completed'", "q.question_type = 'essay'", "TRIM(COALESCE(ans.answer_text, '')) <> ''"];
        $params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $params[] = $current_user_id;
        }
        if ($selected_subject_id > 0) {
            $where_parts[] = 'e.subject_id = %d';
            $params[] = $selected_subject_id;
        }
        if ($selected_exam_id > 0) {
            $where_parts[] = 'att.exam_id = %d';
            $params[] = $selected_exam_id;
        }
        if ($selected_kelas !== '') {
            $where_parts[] = 'kelas_meta.meta_value = %s';
            $params[] = $selected_kelas;
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $sql = "SELECT att.exam_id,
                       COALESCE(kelas_meta.meta_value, '') AS student_kelas,
                       COUNT(*) AS manual_count
                FROM {$answer_table} ans
                INNER JOIN {$question_table} q ON q.id = ans.question_id
                INNER JOIN {$attempt_table} att ON att.id = ans.attempt_id
                INNER JOIN {$exam_table} e ON e.id = att.exam_id
                INNER JOIN {$wpdb->users} u ON u.ID = att.student_id
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'kode_kelas'
                    GROUP BY user_id
                ) kelas_meta ON kelas_meta.user_id = u.ID
                {$where_sql}
                GROUP BY att.exam_id, student_kelas";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $result = [
            'total' => 0,
            'by_exam' => [],
            'by_class' => [],
            'by_exam_class' => [],
        ];
        if (!is_array($rows)) {
            return $result;
        }

        foreach ($rows as $row) {
            $exam_id = (int) ($row['exam_id'] ?? 0);
            $kelas = (string) (($row['student_kelas'] ?? '') !== '' ? $row['student_kelas'] : '-');
            $count = (int) ($row['manual_count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $result['total'] += $count;
            if (!isset($result['by_exam'][$exam_id])) {
                $result['by_exam'][$exam_id] = 0;
            }
            if (!isset($result['by_class'][$kelas])) {
                $result['by_class'][$kelas] = 0;
            }
            if (!isset($result['by_exam_class'][$exam_id])) {
                $result['by_exam_class'][$exam_id] = [];
            }
            if (!isset($result['by_exam_class'][$exam_id][$kelas])) {
                $result['by_exam_class'][$exam_id][$kelas] = 0;
            }

            $result['by_exam'][$exam_id] += $count;
            $result['by_class'][$kelas] += $count;
            $result['by_exam_class'][$exam_id][$kelas] += $count;
        }

        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_exam_question_metadata(int $exam_id): array
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $question_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_text, question_type, points, correct_text, explanation, COALESCE(is_active, 1) AS is_active
                 FROM {$question_table}
                 WHERE exam_id = %d
                 ORDER BY id ASC",
                $exam_id
            ),
            ARRAY_A
        );
        if (!is_array($question_rows)) {
            return [];
        }

        $question_ids = array_values(array_filter(array_map('intval', array_column($question_rows, 'id')), static function ($question_id): bool {
            return $question_id > 0;
        }));
        $options_by_question = [];
        if (!empty($question_ids)) {
            $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
            $option_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id IN ({$placeholders})
                     ORDER BY question_id ASC, id ASC",
                    ...$question_ids
                ),
                ARRAY_A
            );

            foreach ((array) $option_rows as $option_row) {
                $question_id = (int) ($option_row['question_id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }

                if (!isset($options_by_question[$question_id])) {
                    $options_by_question[$question_id] = [];
                }

                $options_by_question[$question_id][] = [
                    'id' => (int) ($option_row['id'] ?? 0),
                    'option_key' => (string) ($option_row['option_key'] ?? ''),
                    'option_text' => (string) ($option_row['option_text'] ?? ''),
                    'is_correct' => (int) ($option_row['is_correct'] ?? 0),
                ];
            }
        }

        $metadata = [];
        foreach ($question_rows as $index => $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_type = (string) ($question['question_type'] ?? '');
            $options = (array) ($options_by_question[$question_id] ?? []);
            $detail = CBT_Admin_Questions_Helper::get_question_type_detail($question_id, $question_type);
            $matrix_items = $question_type === 'true_false_matrix'
                ? CBT_Admin_Questions_Helper::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''))
                : [];
            if ($question_type === 'true_false_matrix') {
                $detail['matrix_items'] = $matrix_items;
            }
            $short_answer_correct_answers = $question_type === 'short_answer'
                ? array_values(array_filter(array_map('strval', (array) ($detail['correct_answers'] ?? CBT_Admin_Questions_Helper::normalize_short_answer_values((string) ($question['correct_text'] ?? ''))))))
                : [];
            if ($question_type === 'short_answer') {
                $detail['correct_answers'] = $short_answer_correct_answers;
            }

            $effective_max_score = max(0.0, (float) ($question['points'] ?? 0.0));
            $is_partial_credit = false;
            if ($question_type === 'short_answer' && count($short_answer_correct_answers) > 1) {
                $effective_max_score = max(0.0, (float) ($question['points'] ?? 0.0)) * count($short_answer_correct_answers);
                $is_partial_credit = true;
            } elseif ($question_type === 'true_false_matrix') {
                $is_partial_credit = true;
            }

            $metadata[$question_id] = [
                'question_id' => $question_id,
                'question_number' => $index + 1,
                'question_text' => (string) ($question['question_text'] ?? ''),
                'question_preview' => self::build_question_preview((string) ($question['question_text'] ?? '')),
                'question_type' => $question_type,
                'question_type_label' => self::format_question_type_label($question_type),
                'points' => max(0.0, (float) ($question['points'] ?? 0.0)),
                'effective_max_score' => $effective_max_score,
                'is_archived' => ((int) ($question['is_active'] ?? 1) === 1) ? 0 : 1,
                'is_objective' => $question_type !== 'essay' ? 1 : 0,
                'is_partial_credit' => $is_partial_credit ? 1 : 0,
                'options' => $options,
                'detail' => $detail,
                'correct_answer_summary' => self::build_correct_answer_summary($question, $options),
            ];
        }

        return $metadata;
    }

    /**
     * @return array{total_questions:int,total_points:float,archived_question_count:int}
     */
    private static function get_exam_question_stats(int $exam_id): array
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return [
                'total_questions' => 0,
                'total_points' => 0.0,
                'archived_question_count' => 0,
            ];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(CASE WHEN COALESCE(is_active, 1) = 1 THEN 1 END) AS total_questions,
                        COALESCE(SUM(CASE WHEN COALESCE(is_active, 1) = 1 THEN points ELSE 0 END), 0) AS total_points,
                        COUNT(CASE WHEN COALESCE(is_active, 1) <> 1 THEN 1 END) AS archived_question_count
                 FROM {$question_table}
                 WHERE exam_id = %d",
                $exam_id
            ),
            ARRAY_A
        );

        return [
            'total_questions' => (int) ($row['total_questions'] ?? 0),
            'total_points' => round((float) ($row['total_points'] ?? 0.0), 2),
            'archived_question_count' => (int) ($row['archived_question_count'] ?? 0),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function build_summary_metrics(array $rows, int $manual_review_count): array
    {
        $completed_attempts = count($rows);
        $percentage_total = 0.0;
        $pass_count = 0;
        foreach ($rows as $row) {
            $percentage_total += (float) ($row['percentage'] ?? 0.0);
            $pass_count += (int) ($row['is_passed'] ?? 0);
        }

        $average_percentage = $completed_attempts > 0 ? round($percentage_total / $completed_attempts, 2) : 0.0;
        $pass_rate = $completed_attempts > 0 ? round(($pass_count / $completed_attempts) * 100, 2) : 0.0;

        return [
            'completed_attempts' => $completed_attempts,
            'average_percentage' => $average_percentage,
            'average_percentage_display' => self::format_percent($average_percentage),
            'pass_rate' => $pass_rate,
            'pass_rate_display' => self::format_percent($pass_rate),
            'manual_review_count' => max(0, $manual_review_count),
        ];
    }

    private static function build_summary_metrics_from_aggregates(
        int $completed_attempts,
        float $percentage_total,
        int $pass_count,
        int $manual_review_count
    ): array {
        $completed_attempts = max(0, $completed_attempts);
        $percentage_total = max(0.0, $percentage_total);
        $pass_count = max(0, $pass_count);

        $average_percentage = $completed_attempts > 0 ? round($percentage_total / $completed_attempts, 2) : 0.0;
        $pass_rate = $completed_attempts > 0 ? round(($pass_count / $completed_attempts) * 100, 2) : 0.0;

        return [
            'completed_attempts' => $completed_attempts,
            'average_percentage' => $average_percentage,
            'average_percentage_display' => self::format_percent($average_percentage),
            'pass_rate' => $pass_rate,
            'pass_rate_display' => self::format_percent($pass_rate),
            'manual_review_count' => max(0, $manual_review_count),
        ];
    }

    /**
     * @param array<int,float> $percentages
     * @return array<int,array<string,mixed>>
     */
    private static function build_distribution_buckets(array $percentages): array
    {
        $total = count($percentages);
        $buckets = [];
        foreach (self::DISTRIBUTION_BUCKETS as $bucket) {
            $count = 0;
            foreach ($percentages as $percentage) {
                $percentage = max(0.0, min(100.0, (float) $percentage));
                if ($percentage >= (float) $bucket['min'] && $percentage <= (float) $bucket['max']) {
                    $count++;
                }
            }

            $buckets[] = [
                'label' => (string) ($bucket['label'] ?? ''),
                'count' => $count,
                'bar_width' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
            ];
        }

        return $buckets;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function build_behavioral_quadrant(array $rows, int $duration_minutes, float $kkm_percentage): array
    {
        $duration_seconds = max(60, $duration_minutes * 60);
        $points = [];
        $duration_percentages = [];
        $counts = [
            'mastery' => 0,
            'diligent' => 0,
            'blind_guessing' => 0,
            'struggling' => 0,
        ];

        foreach ($rows as $row) {
            $attempt_duration = max(0, (int) ($row['duration_seconds'] ?? 0));
            $duration_percent = round(min(200.0, ($attempt_duration / $duration_seconds) * 100), 2);
            $duration_percentages[] = $duration_percent;
        }

        $median_duration_percent = !empty($duration_percentages)
            ? self::calculate_median($duration_percentages)
            : 0.0;

        foreach ($rows as $row) {
            $attempt_duration = max(0, (int) ($row['duration_seconds'] ?? 0));
            $duration_percent = round(min(200.0, ($attempt_duration / $duration_seconds) * 100), 2);
            $percentage = round(max(0.0, min(100.0, (float) ($row['percentage'] ?? 0.0))), 2);
            $quadrant = self::classify_behavioral_quadrant($duration_percent, $percentage, $median_duration_percent, $kkm_percentage);
            $counts[$quadrant]++;

            $points[] = [
                'attempt_id' => (int) ($row['id'] ?? 0),
                'student_name' => (string) ($row['student_name'] ?? '-'),
                'student_kelas' => (string) (($row['student_kelas'] ?? '') !== '' ? $row['student_kelas'] : '-'),
                'x' => $duration_percent,
                'y' => $percentage,
                'duration_label' => self::format_duration($attempt_duration),
                'percentage_display' => self::format_percent($percentage),
                'quadrant' => $quadrant,
                'quadrant_label' => self::format_behavioral_quadrant_label($quadrant),
            ];
        }

        return [
            'status' => !empty($points) ? 'ok' : 'empty',
            'x_axis_label' => 'Durasi (% dari waktu ujian)',
            'y_axis_label' => 'Nilai (%)',
            'duration_median_percent' => $median_duration_percent,
            'duration_median_percent_display' => self::format_percent($median_duration_percent),
            'kkm_percentage' => $kkm_percentage,
            'kkm_percentage_display' => self::format_percent($kkm_percentage),
            'points' => $points,
            'counts' => $counts,
        ];
    }

    private static function classify_behavioral_quadrant(
        float $duration_percent,
        float $percentage,
        float $median_duration_percent,
        float $kkm_percentage
    ): string {
        $is_fast = $duration_percent <= $median_duration_percent;
        $is_high_score = $percentage >= $kkm_percentage;

        if ($is_fast && $is_high_score) {
            return 'mastery';
        }
        if (!$is_fast && $is_high_score) {
            return 'diligent';
        }
        if ($is_fast) {
            return 'blind_guessing';
        }

        return 'struggling';
    }

    private static function format_behavioral_quadrant_label(string $quadrant): string
    {
        switch ($quadrant) {
            case 'mastery':
                return 'Mastery';
            case 'diligent':
                return 'Diligent';
            case 'blind_guessing':
                return 'Blind Guessing';
            default:
                return 'Struggling';
        }
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function build_benchmark_overlay(array $rows, string $selected_benchmark_kelas): array
    {
        $global_percentages = array_values(array_map(static function (array $row): float {
            return (float) ($row['percentage'] ?? 0.0);
        }, $rows));

        $class_groups = [];
        foreach ($rows as $row) {
            $kelas = (string) (($row['student_kelas'] ?? '') !== '' ? $row['student_kelas'] : '-');
            if (!isset($class_groups[$kelas])) {
                $class_groups[$kelas] = [
                    'kelas' => $kelas,
                    'percentages' => [],
                ];
            }
            $class_groups[$kelas]['percentages'][] = (float) ($row['percentage'] ?? 0.0);
        }

        uasort($class_groups, static function (array $left, array $right): int {
            $count_compare = count((array) ($right['percentages'] ?? [])) <=> count((array) ($left['percentages'] ?? []));
            if ($count_compare !== 0) {
                return $count_compare;
            }

            return strnatcasecmp((string) ($left['kelas'] ?? ''), (string) ($right['kelas'] ?? ''));
        });

        $selected = $selected_benchmark_kelas !== '' && isset($class_groups[$selected_benchmark_kelas])
            ? $selected_benchmark_kelas
            : (string) (array_key_first($class_groups) ?? '');
        $class_percentages = $selected !== '' ? (array) ($class_groups[$selected]['percentages'] ?? []) : [];
        $global_average = !empty($global_percentages) ? round(array_sum($global_percentages) / count($global_percentages), 2) : 0.0;
        $class_average = !empty($class_percentages) ? round(array_sum($class_percentages) / count($class_percentages), 2) : 0.0;

        $global_distribution = self::build_distribution_buckets($global_percentages);
        $class_distribution = self::build_distribution_buckets($class_percentages);

        return [
            'status' => !empty($global_percentages) ? 'ok' : 'empty',
            'selected_kelas' => $selected,
            'class_options' => array_values(array_map(static function (array $group): array {
                return [
                    'kelas' => (string) ($group['kelas'] ?? '-'),
                    'completed_attempts' => count((array) ($group['percentages'] ?? [])),
                ];
            }, $class_groups)),
            'labels' => array_values(array_map(static function (array $bucket): string {
                return (string) ($bucket['label'] ?? '');
            }, $global_distribution)),
            'global_counts' => array_values(array_map(static function (array $bucket): int {
                return (int) ($bucket['count'] ?? 0);
            }, $global_distribution)),
            'class_counts' => array_values(array_map(static function (array $bucket): int {
                return (int) ($bucket['count'] ?? 0);
            }, $class_distribution)),
            'global_average' => $global_average,
            'global_average_display' => self::format_percent($global_average),
            'class_average' => $class_average,
            'class_average_display' => self::format_percent($class_average),
            'delta_average' => round($class_average - $global_average, 2),
            'delta_average_display' => self::format_signed_percent($class_average - $global_average),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_predictive_pass_rate(int $completed_attempts, int $pass_count, int $in_progress_count): array
    {
        $completed_attempts = max(0, $completed_attempts);
        $pass_count = max(0, $pass_count);
        $in_progress_count = max(0, $in_progress_count);
        $current_pass_rate = $completed_attempts > 0 ? round(($pass_count / $completed_attempts) * 100, 2) : 0.0;

        if ($completed_attempts < 5) {
            return [
                'status' => 'insufficient_data',
                'completed_attempts' => $completed_attempts,
                'pass_count' => $pass_count,
                'in_progress_count' => $in_progress_count,
                'current_pass_rate' => $current_pass_rate,
                'current_pass_rate_display' => self::format_percent($current_pass_rate),
                'predicted_final_pass_rate' => $current_pass_rate,
                'predicted_final_pass_rate_display' => self::format_percent($current_pass_rate),
                'message' => 'Estimasi belum stabil karena completed attempt kurang dari 5.',
            ];
        }

        $trend = $completed_attempts > 0 ? $pass_count / $completed_attempts : 0.0;
        $projected_pass_count = $pass_count + ($in_progress_count * $trend);
        $projected_total = max(1, $completed_attempts + $in_progress_count);
        $predicted_rate = round(($projected_pass_count / $projected_total) * 100, 2);

        return [
            'status' => 'ok',
            'completed_attempts' => $completed_attempts,
            'pass_count' => $pass_count,
            'in_progress_count' => $in_progress_count,
            'current_pass_rate' => $current_pass_rate,
            'current_pass_rate_display' => self::format_percent($current_pass_rate),
            'predicted_final_pass_rate' => $predicted_rate,
            'predicted_final_pass_rate_display' => self::format_percent($predicted_rate),
            'message' => 'Estimasi sementara berdasarkan tren completed attempt saat ini.',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,int> $manualByClass
     * @return array<int,array<string,mixed>>
     */
    private static function build_exam_kelas_summary(array $rows, array $manualByClass): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $kelas = (string) (($row['student_kelas'] ?? '') !== '' ? $row['student_kelas'] : '-');
            if (!isset($groups[$kelas])) {
                $groups[$kelas] = [
                    'kelas' => $kelas,
                    'completed_attempts' => 0,
                    'pass_count' => 0,
                    'percentage_total' => 0.0,
                    'manual_review_count' => (int) ($manualByClass[$kelas] ?? 0),
                ];
            }

            $groups[$kelas]['completed_attempts']++;
            $groups[$kelas]['pass_count'] += (int) ($row['is_passed'] ?? 0);
            $groups[$kelas]['percentage_total'] += (float) ($row['percentage'] ?? 0.0);
        }

        $result = [];
        foreach ($groups as $group) {
            $completed_attempts = max(0, (int) ($group['completed_attempts'] ?? 0));
            $average_percentage = $completed_attempts > 0
                ? round(((float) ($group['percentage_total'] ?? 0.0)) / $completed_attempts, 2)
                : 0.0;
            $pass_rate = $completed_attempts > 0
                ? round((((int) ($group['pass_count'] ?? 0)) / $completed_attempts) * 100, 2)
                : 0.0;
            $result[] = [
                'kelas' => (string) ($group['kelas'] ?? '-'),
                'completed_attempts' => $completed_attempts,
                'average_percentage' => $average_percentage,
                'average_percentage_display' => self::format_percent($average_percentage),
                'pass_rate' => $pass_rate,
                'pass_rate_display' => self::format_percent($pass_rate),
                'manual_review_count' => (int) ($group['manual_review_count'] ?? 0),
            ];
        }

        usort($result, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['kelas'] ?? ''), (string) ($right['kelas'] ?? ''));
        });

        return $result;
    }

    /**
     * @param array<int,array<string,mixed>> $progress_items
     * @return array<string,int>
     */
    private static function summarize_progress_items(array $progress_items): array
    {
        $summary = [
            'total_questions' => count($progress_items),
            'answered_questions' => 0,
            'correct_questions' => 0,
            'wrong_questions' => 0,
            'manual_questions' => 0,
            'unanswered_questions' => 0,
        ];

        foreach ($progress_items as $progress_item_row) {
            $progress_item = (array) $progress_item_row;
            $status = (string) ($progress_item['status'] ?? 'unanswered');
            if ($status !== 'unanswered') {
                $summary['answered_questions']++;
            }

            if ($status === 'correct') {
                $summary['correct_questions']++;
            } elseif ($status === 'wrong') {
                $summary['wrong_questions']++;
            } elseif ($status === 'manual') {
                $summary['manual_questions']++;
            } else {
                $summary['unanswered_questions']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $question
     * @param array<int,array<string,mixed>> $options
     */
    private static function build_correct_answer_summary(array $question, array $options): string
    {
        $question_type = (string) ($question['question_type'] ?? '');
        $question_id = (int) ($question['id'] ?? 0);
        $detail = CBT_Admin_Questions_Helper::get_question_type_detail($question_id, $question_type);

        if (in_array($question_type, ['multiple_choice', 'multiple_answer'], true)) {
            $labels = [];
            foreach ($options as $option_row) {
                $option = (array) $option_row;
                if ((int) ($option['is_correct'] ?? 0) !== 1) {
                    continue;
                }

                $labels[] = self::format_option_label((string) ($option['option_key'] ?? ''), (string) ($option['option_text'] ?? ''));
            }

            return !empty($labels) ? implode(', ', $labels) : '-';
        }

        if ($question_type === 'true_false') {
            foreach ($options as $option_row) {
                $option = (array) $option_row;
                if ((int) ($option['is_correct'] ?? 0) === 1) {
                    return self::format_option_label((string) ($option['option_key'] ?? ''), (string) ($option['option_text'] ?? ''));
                }
            }

            $correctValue = isset($detail['correct_value']) ? (int) $detail['correct_value'] : 1;
            return $correctValue === 1 ? 'Benar' : 'Salah';
        }

        if ($question_type === 'short_answer') {
            $answers = [];
            if (!empty($detail['correct_answers']) && is_array($detail['correct_answers'])) {
                $answers = array_values(array_filter(array_map('strval', $detail['correct_answers'])));
            } else {
                $answers = CBT_Admin_Questions_Helper::normalize_short_answer_values((string) ($question['correct_text'] ?? ''));
            }

            return !empty($answers) ? implode(' | ', $answers) : '-';
        }

        if ($question_type === 'true_false_matrix') {
            $items = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
            if (empty($items)) {
                return '-';
            }

            $parts = [];
            foreach ($items as $index => $item_row) {
                $item = (array) $item_row;
                $parts[] = sprintf(
                    '%d. %s',
                    $index + 1,
                    ((string) ($item['answer'] ?? 'true') === 'false') ? 'Salah' : 'Benar'
                );
                if (count($parts) >= 4) {
                    break;
                }
            }

            return !empty($parts) ? implode(' | ', $parts) : '-';
        }

        if ($question_type === 'essay') {
            $rubric = trim((string) ($detail['rubric_text'] ?? ''));
            if ($rubric === '') {
                return 'Dinilai manual';
            }

            return wp_trim_words(wp_strip_all_tags($rubric), 18, '...');
        }

        return '-';
    }

    /**
     * @return array{label:string,tone:string}
     */
    private static function build_difficulty_meta(string $question_type, float $correct_rate, int $manual_count): array
    {
        if ($question_type === 'essay' || $manual_count > 0) {
            return [
                'label' => 'Pending Manual Review',
                'tone' => 'manual',
            ];
        }

        if ($correct_rate >= 80.0) {
            return [
                'label' => 'Mudah',
                'tone' => 'easy',
            ];
        }

        if ($correct_rate >= 40.0) {
            return [
                'label' => 'Sedang',
                'tone' => 'medium',
            ];
        }

        return [
            'label' => 'Sulit',
            'tone' => 'hard',
        ];
    }

    private static function calculate_median(array $values): float
    {
        $values = array_values(array_filter(array_map('floatval', $values), static function (float $value): bool {
            return is_finite($value);
        }));
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);
        if ($count % 2 === 1) {
            return round((float) $values[$middle], 2);
        }

        return round((((float) $values[$middle - 1]) + ((float) $values[$middle])) / 2, 2);
    }

    private static function calculate_passing_score(float $maxScore, float $kkmPercentage): float
    {
        if (!is_finite($maxScore) || $maxScore <= 0) {
            return 0.0;
        }

        return round(max(0.0, $maxScore) * (self::normalize_kkm_percentage($kkmPercentage) / 100), 2);
    }

    private static function normalize_kkm_percentage(float $value): float
    {
        if (!is_finite($value)) {
            return 75.0;
        }

        return round(min(100.0, max(0.0, $value)), 2);
    }

    /**
     * @param array<string,mixed> $exam
     */
    private static function build_exam_filter_label(array $exam): string
    {
        $title = trim((string) ($exam['title'] ?? ''));
        $subject_name = trim((string) ($exam['subject_name'] ?? ''));
        if ($subject_name === '') {
            return $title !== '' ? $title : 'Exam';
        }

        return ($title !== '' ? $title : 'Exam') . ' • ' . $subject_name;
    }

    private static function build_question_preview(string $rawQuestionText): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($rawQuestionText)) ?? '');
        if ($plain === '') {
            return '(Tanpa teks soal)';
        }

        return (string) wp_trim_words($plain, 16, '...');
    }

    private static function format_question_type_label(string $questionType): string
    {
        switch ($questionType) {
            case 'multiple_choice':
                return 'Multiple Choice';
            case 'multiple_answer':
                return 'Multiple Answer';
            case 'true_false':
                return 'True / False';
            case 'true_false_matrix':
                return 'TF Matrix';
            case 'short_answer':
                return 'Short Answer';
            case 'essay':
                return 'Essay';
            default:
                return ucwords(str_replace('_', ' ', $questionType));
        }
    }

    private static function format_group_band_label(string $band): string
    {
        switch ($band) {
            case 'upper':
                return 'Upper';
            case 'lower':
                return 'Lower';
            default:
                return 'Middle';
        }
    }

    private static function format_option_label(string $optionKey, string $optionText): string
    {
        $key = strtoupper(trim($optionKey));
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($optionText)) ?? '');
        $text = $text !== '' ? (string) wp_trim_words($text, 8, '...') : '';

        if ($key !== '' && $text !== '') {
            return $key . ' - ' . $text;
        }
        if ($key !== '') {
            return $key;
        }
        if ($text !== '') {
            return $text;
        }

        return '-';
    }

    private static function format_percent(float $value): string
    {
        return number_format_i18n($value, 2) . '%';
    }

    private static function format_signed_percent(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';
        return $prefix . number_format_i18n($value, 2) . '%';
    }

    private static function format_number(float $value): string
    {
        return number_format_i18n($value, 2);
    }

    private static function format_duration(int $durationSeconds): string
    {
        $durationSeconds = max(0, $durationSeconds);
        if ($durationSeconds <= 0) {
            return '-';
        }

        $hours = (int) floor($durationSeconds / HOUR_IN_SECONDS);
        $minutes = (int) floor(($durationSeconds % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
        $seconds = (int) ($durationSeconds % MINUTE_IN_SECONDS);

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    private static function format_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date('d M Y H:i', $timestamp, wp_timezone());
    }
}
